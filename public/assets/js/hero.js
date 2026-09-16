/* Hero: an interactive view of the actual five courts, tinted by live
 * occupancy, plus the page-load choreography.
 *
 * Rules this file obeys (DESIGN.md § The 3D scene):
 *   1. Nothing here blocks first paint. The SVG scene is already on screen.
 *   2. It does not load at all on a device that cannot afford it, and it
 *      loads a cheaper build on a device that can only just afford it.
 *   3. The render loop stops when the canvas is off screen or the tab hides.
 *   4. The scene shows real data. If it stopped being real it would be cut.
 *
 * Geometry lives in hero/world.js, the foreground props in hero/props.js and
 * the hover card in hero/hud.js. This file owns the camera, the frame and the
 * pointer.
 */

import { buildWorld, PALETTE } from './hero/world.js';
import { buildProps } from './hero/props.js';
import { createHud } from './hero/hud.js';

const scene = document.querySelector('[data-hero-scene]');
const canvas = document.querySelector('[data-hero-canvas]');
const hero = document.querySelector('.hero');
const reduced = matchMedia('(prefers-reduced-motion: reduce)');
const canHover = matchMedia('(hover: hover) and (pointer: fine)');

/** Courts, with their live status, embedded by the view. */
function courtData() {
  const el = document.getElementById('court-scene-data');
  if (!el) return [];
  try {
    return JSON.parse(el.textContent);
  } catch {
    return [];
  }
}

/**
 * What can this device afford? 'off' keeps the SVG, which is a finished
 * picture in its own right; 'lite' is the same facility with the expensive
 * half switched off, for phones and tablets.
 */
function tier() {
  if (!scene || !canvas || !hero) return 'off';
  if (reduced.matches) return 'off';
  if (navigator.connection?.saveData) return 'off';
  if ((navigator.deviceMemory ?? 8) < 4) return 'off';
  if ((navigator.hardwareConcurrency ?? 8) < 4) return 'off';
  if (innerWidth < 520) return 'off';
  if (!document.createElement('canvas').getContext('webgl2')) return 'off';

  const lite = innerWidth < 1100
    || (navigator.deviceMemory ?? 8) < 8
    || (navigator.hardwareConcurrency ?? 8) < 6;

  return lite ? 'lite' : 'full';
}

async function buildScene(level) {
  const THREE = await import('../vendor/three.module.min.js');
  const courts = courtData();
  if (courts.length === 0) return;

  const full = level === 'full';

  const renderer = new THREE.WebGLRenderer({
    canvas,
    antialias: full,
    alpha: true,
    powerPreference: full ? 'high-performance' : 'low-power',
  });
  renderer.setPixelRatio(Math.min(devicePixelRatio, full ? 1.6 : 1.25));
  renderer.toneMapping = THREE.ACESFilmicToneMapping;
  renderer.toneMappingExposure = 1.18;
  if (full) {
    renderer.shadowMap.enabled = true;
    renderer.shadowMap.type = THREE.PCFSoftShadowMap;
  }

  const world = new THREE.Scene();
  // Depth without haze: dense enough to sink the far fence into the mint haze,
  // light enough that the courts stay legible.
  world.fog = new THREE.FogExp2(PALETTE.fog, full ? 0.0055 : 0.0068);

  const camera = new THREE.PerspectiveCamera(40, 1, 0.5, 400);

  const { group, rigs, lamps, heads, key, bounds } = buildWorld(THREE, courts, { tier: level });
  world.add(group);

  const props = buildProps(THREE, { tier: level });
  world.add(props.group);

  // Lite runs four poles instead of six, so the same per-lamp peak leaves the
  // facility a third darker — it read as geometry in fog rather than a lit
  // court. Scale the peak by the shortfall instead of hand-tuning a number.
  const lampPeak = (full ? 1150 : 820) * (full ? 1 : 6 / lamps.length);
  lamps.forEach((l) => { l.userData.peak = lampPeak; });

  const hud = canHover.matches ? createHud(hero) : null;

  // ------------------------------------------------------------- framing
  // Camera sits on a 34° arc above the facility, yawed a little left so the
  // five courts stagger in depth instead of stacking into a grid.
  const YAW = -0.34;
  const PITCH_TOP = 0.55;   // ~31°
  const PITCH_LOW = 0.40;   // ~23°, where the scroll lands
  const center = new THREE.Vector3(bounds.centerX, 0, bounds.centerZ);
  const lookAt = new THREE.Vector3();
  const basePos = new THREE.Vector3();

  let reach = 60;
  let scrollP = 0;
  let intro = 1;
  let lit = 0;                       // 0..1, driven by the lights-on tween
  const hudOffset = { x: 0, y: 0 };  // scene box relative to the hero box
  const propFrame = { right: 0.58, up: 0.42, scale: 1 };

  /** Base camera position for a scroll position — parallax is added later. */
  const framePos = (p, out) => {
    const pitch = PITCH_TOP + (PITCH_LOW - PITCH_TOP) * p;
    const dist = reach * (1 - 0.26 * p) + intro * 16;
    const flat = Math.cos(pitch) * dist;
    return out.set(
      center.x + Math.sin(YAW) * flat,
      Math.sin(pitch) * dist + intro * 6,
      center.z + Math.cos(YAW) * flat
    );
  };

  const frameLook = (p, out) => out.set(center.x + 2, 0.4 + 4.2 * p, center.z - 2 - 4 * p);

  const resize = () => {
    const w = scene.clientWidth;
    const h = scene.clientHeight;
    if (w === 0 || h === 0) return;
    renderer.setSize(w, h, false);
    camera.aspect = w / h;
    camera.updateProjectionMatrix();

    // Distance is set by how tall the facility reads on screen, not by fitting
    // its width: pulling back until every corner fits gives a satellite photo.
    // Narrow viewports back off a little more, and crop rather than shrink.
    const W = bounds.maxX - bounds.minX;
    const D = bounds.maxZ - bounds.minZ;
    const onScreenDepth = (Math.abs(W * Math.sin(YAW)) + Math.abs(D * Math.cos(YAW))) * Math.sin(PITCH_TOP);
    const halfFov = (camera.fov * Math.PI) / 360;
    const zoom = 1.9 + Math.min(0.5, Math.max(0, 1.6 / camera.aspect - 1) * 0.6);
    reach = ((onScreenDepth / 2) / Math.tan(halfFov)) * zoom;

    // Where the props sit is a function of how much frame there is beside the
    // courts. The old fixed 0.58/0.42 was tuned at one aspect and landed the
    // paddle flat on top of court three everywhere else — a foreground object
    // resting on a mid-ground surface reads as a z-fighting bug, not depth.
    // Clearance is bought sideways, not upward: .hero-scene bleeds 11% above
    // the hero and that band is cropped, so anything past ~0.75 of the
    // half-height is behind the crop rather than high in frame.
    const wide = Math.min(1, Math.max(0, (camera.aspect - 1.0) / 0.8));
    const narrow = camera.aspect < 1.1;
    propFrame.right = narrow ? 0.44 : 0.66 + wide * 0.16;
    propFrame.up = narrow ? 0.52 : 0.46 + wide * 0.08;
    propFrame.scale = narrow ? 0.58 : 0.70 - wide * 0.05;

    const sr = scene.getBoundingClientRect();
    const hr = hero.getBoundingClientRect();
    hudOffset.x = sr.left - hr.left;
    hudOffset.y = sr.top - hr.top;
  };
  resize();
  addEventListener('resize', resize, { passive: true });

  // ------------------------------------------------------- scroll + mouse
  const onScroll = () => {
    const h = hero.offsetHeight || 1;
    scrollP = Math.min(1, Math.max(0, scrollY / h));
  };
  onScroll();
  addEventListener('scroll', onScroll, { passive: true });

  const pointer = new THREE.Vector2(0, 0);   // -1..1, damped
  const pointerTo = new THREE.Vector2(0, 0);
  const ndc = new THREE.Vector2(0, 0);
  let pointerInside = false;

  if (canHover.matches) {
    hero.addEventListener('pointermove', (e) => {
      const r = scene.getBoundingClientRect();
      pointerTo.set(((e.clientX - r.left) / r.width) * 2 - 1, ((e.clientY - r.top) / r.height) * 2 - 1);
      ndc.set(pointerTo.x, -pointerTo.y);
      // Ignore the copy column and the status panel — a hover card fired from
      // behind an opaque surface reads as a bug. Their grid parent covers the
      // whole hero and must not count, only the two blocks that paint.
      pointerInside = !e.target.closest('.hero-copy, .hero-panel');
    }, { passive: true });

    hero.addEventListener('pointerleave', () => {
      pointerTo.set(0, 0);
      pointerInside = false;
    }, { passive: true });
  }

  // -------------------------------------------------------------- hovering
  const raycaster = new THREE.Raycaster();
  const slabs = rigs.map((r) => r.slab);
  const projected = new THREE.Vector3();
  const tmpA = new THREE.Vector3();
  const tmpB = new THREE.Vector3();
  const tmpC = new THREE.Vector3();
  let hovered = null;   // court id
  let pinned = null;    // set by the status panel / facility map

  const hoveredRig = () => rigs.find((r) => r.court.id === (pinned ?? hovered)) ?? null;

  const pick = () => {
    if (!pointerInside) return null;
    raycaster.setFromCamera(ndc, camera);
    const hit = raycaster.intersectObjects(slabs, false)[0];
    return hit ? hit.object.userData.court.id : null;
  };

  hero.addEventListener('click', (e) => {
    if (e.target.closest('.hero-copy, .hero-panel')) return;
    const rig = hoveredRig();
    if (rig) location.href = rig.court.href;
  });

  // The status panel and the facility map drive the same highlight.
  document.querySelectorAll('[data-map-court], [data-court]').forEach((el) => {
    const id = Number(el.dataset.mapCourt ?? el.dataset.court);
    el.addEventListener('pointerenter', () => { pinned = id; });
    el.addEventListener('pointerleave', () => { pinned = null; });
  });

  // ---------------------------------------------------------------- frame
  let raf = null;
  let visible = true;
  const clock = new THREE.Clock();

  const frame = () => {
    const dt = Math.min(clock.getDelta(), 0.05);
    const t = clock.getElapsedTime();
    const damp = THREE.MathUtils.damp;

    intro = damp(intro, 0, 1.6, dt);
    if (canHover.matches) {
      pointer.x = damp(pointer.x, pointerTo.x, 3.2, dt);
      pointer.y = damp(pointer.y, pointerTo.y, 3.2, dt);
    }

    // Camera: framing, then a slow drift, then the parallax on top. All three
    // are small — the building has to feel steady, not handheld.
    framePos(scrollP, basePos);
    frameLook(scrollP, lookAt);

    // Slide the whole frame sideways so the facility sits in the right half,
    // clear of the copy column, without yawing the camera off the courts.
    const forward = tmpA.copy(lookAt).sub(basePos);
    const range = forward.length();
    forward.divideScalar(range);
    const right = tmpB.crossVectors(forward, camera.up).normalize();
    const halfH = Math.tan((camera.fov * Math.PI) / 360) * range;
    // How far right the facility can sit is a function of how much frame there
    // is: wide screens have room beside the copy column, square ones do not.
    const room = Math.min(0.45, Math.max(0, camera.aspect - 1));
    const shift = halfH * camera.aspect * room;
    basePos.addScaledVector(right, -shift);
    lookAt.addScaledVector(right, -shift);

    const sway = full ? 1 : 0.5;
    camera.position.set(
      basePos.x + Math.sin(t * 0.09) * 1.1 * sway - pointer.x * 3.4 * sway,
      basePos.y + Math.sin(t * 0.13) * 0.4 * sway - pointer.y * 2.1 * sway,
      basePos.z + Math.cos(t * 0.07) * 0.8 * sway
    );
    camera.lookAt(lookAt);

    // Props ride in camera space so they hold the upper-right corner at every
    // aspect ratio. Solved against the undrifted camera, so they still
    // parallax against the courts rather than sitting welded to the lens.
    const dist = 26;
    const propH = Math.tan((camera.fov * Math.PI) / 360) * dist;
    const up = tmpC.crossVectors(right, forward).normalize();
    props.group.position.copy(basePos)
      .addScaledVector(forward, dist)
      .addScaledVector(right, propH * camera.aspect * propFrame.right)
      .addScaledVector(up, propH * propFrame.up);
    props.group.quaternion.copy(camera.quaternion);
    props.group.scale.setScalar(propFrame.scale);
    // Scroll drives the serve. Same `scrollP` the camera uses, so the paddle
    // turning and the camera dropping toward the courts are one movement.
    props.update(t, scrollP);

    // Hover: raise, brighten, light the edge.
    hovered = pick();
    const activeId = pinned ?? hovered;
    rigs.forEach((rig) => {
      const on = rig.court.id === activeId;
      rig.lift = damp(rig.lift, on ? 1 : 0, 7, dt);
      rig.root.position.y = rig.lift * 0.42;
      rig.surfaceMat.emissiveIntensity = rig.lift * 0.5;
      rig.edgeMat.opacity = 0.42 + rig.lift * 0.58;
    });
    hero.classList.toggle('is-court-hover', hovered !== null);

    if (hud) {
      const rig = hoveredRig();
      if (rig) {
        projected.copy(rig.center).setY(rig.center.y + 1.6 + rig.lift * 0.42).project(camera);
        hud.show(
          rig.court,
          (projected.x * 0.5 + 0.5) * scene.clientWidth + hudOffset.x,
          (-projected.y * 0.5 + 0.5) * scene.clientHeight + hudOffset.y
        );
      } else {
        hud.hide();
      }
    }

    // Floodlights breathe by a couple of percent. Any more is a fault report.
    lamps.forEach((l, i) => {
      l.intensity = l.userData.peak * lit * (full ? 1 + Math.sin(t * 1.7 + i) * 0.018 : 1);
    });

    renderer.render(world, camera);
    raf = requestAnimationFrame(frame);
  };

  const start = () => { if (raf === null && visible) { clock.getDelta(); raf = requestAnimationFrame(frame); } };
  const stop = () => { if (raf !== null) { cancelAnimationFrame(raf); raf = null; } };

  document.addEventListener('visibilitychange', () => {
    if (document.hidden) stop(); else start();
  });

  if ('IntersectionObserver' in window) {
    new IntersectionObserver(([entry]) => {
      visible = entry.isIntersecting;
      if (visible) start(); else stop();
    }, { threshold: 0.01 }).observe(scene);
  }

  // First frame before revealing, so the canvas never flashes empty.
  framePos(0, basePos);
  frameLook(0, lookAt);
  camera.position.copy(basePos);
  camera.lookAt(lookAt);
  renderer.render(world, camera);
  scene.dataset.webgl = 'on';
  start();

  // ------------------------------------------------------ lights strike on
  // The facility switches on rather than arriving already lit — it is the
  // entrance animation, and it costs one eased tween.
  const strike = (ms) => {
    const t0 = performance.now();
    const step = (now) => {
      const p = Math.min(1, (now - t0) / ms);
      const eased = 1 - Math.pow(1 - p, 3);
      lit = eased;
      key.intensity = eased * 2.6;
      props.lights.forEach((l) => { l.intensity = eased * l.userData.peak; });
      heads.forEach((h) => h.material.color.setRGB(0.04 + eased * 0.86, 0.09 + eased * 0.86, 0.16 + eased * 0.82));
      if (p < 1) requestAnimationFrame(step);
    };
    requestAnimationFrame(step);
  };
  strike(1600);
}

/* ------------------------------------------------------- choreography --- */

function loadGsap() {
  return new Promise((resolve) => {
    if (window.gsap) return resolve(window.gsap);
    const s = document.createElement('script');
    s.src = new URL('../vendor/gsap.min.js', import.meta.url).href;
    s.onload = () => resolve(window.gsap ?? null);
    s.onerror = () => resolve(null);
    document.head.appendChild(s);
  });
}

async function choreograph() {
  // The hero is fully readable before this runs. Skip entirely when motion is
  // unwanted or the tab is hidden — a timeline that starts in a background tab
  // does not tick, and gsap.from() would leave the hero stuck at opacity 0.
  if (reduced.matches || document.hidden) return;

  const gsap = await loadGsap();
  if (!gsap) return;

  const TARGETS = [
    '.hero-meta', '.hero-copy .t-display', '.hero-copy .t-lede',
    '.hero-actions .btn', '.hero-panel', '.court-line', '.stat-strip .readout',
  ];

  // One orchestrated page-load, not a fade applied to every section.
  const tl = gsap.timeline({
    defaults: { ease: 'expo.out' },
    // Strip the inline styles gsap leaves behind so the hero ends up in exactly
    // the state the CSS describes, not a JS approximation of it.
    onComplete: () => gsap.set(TARGETS, { clearProps: 'all' }),
  })
    .from('.hero-meta', { y: 14, opacity: 0, duration: 0.6 })
    .from('.hero-copy .t-display', { y: 26, opacity: 0, duration: 0.75 }, '-=0.42')
    .from('.hero-copy .t-lede', { y: 16, opacity: 0, duration: 0.65 }, '-=0.55')
    .from('.hero-actions .btn', { y: 12, opacity: 0, duration: 0.5, stagger: 0.06 }, '-=0.45')
    .from('.hero-panel', { x: 28, opacity: 0, duration: 0.75 }, '-=0.7')
    .from('.court-line', { opacity: 0, duration: 0.4, stagger: 0.045 }, '-=0.5')
    .from('.stat-strip .readout', { y: 10, opacity: 0, duration: 0.5, stagger: 0.05 }, '-=0.45');

  // Failsafe. If the timeline is ever starved — tab hidden mid-run, rAF
  // throttled, a headless renderer that stops the clock — snap to the end
  // state rather than leaving the hero half-faded. Content visibility is
  // never allowed to depend on an animation finishing.
  const guard = setTimeout(() => {
    if (tl.progress() < 1) {
      tl.progress(1);
      gsap.set(TARGETS, { clearProps: 'all' });
    }
  }, 4000);
  tl.eventCallback('onComplete', () => {
    clearTimeout(guard);
    gsap.set(TARGETS, { clearProps: 'all' });
  });

  document.addEventListener('visibilitychange', () => {
    if (document.hidden && tl.progress() < 1) {
      tl.progress(1);
      gsap.set(TARGETS, { clearProps: 'all' });
    }
  });
}

/* ------------------------------------------------------------- kickoff --- */

choreograph();

const level = tier();
if (level !== 'off') {
  const boot = () => buildScene(level).catch(() => { /* keep the SVG */ });
  if ('requestIdleCallback' in window) {
    requestIdleCallback(boot, { timeout: 2500 });
  } else {
    setTimeout(boot, 900);
  }
}
