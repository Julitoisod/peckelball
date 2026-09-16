/* The paddle and the ball that ride the upper-right foreground.
 *
 * They are lit mostly by the facility's own lights, so they read as objects in
 * the scene rather than stickers on top of it. Two small lamps travel with
 * them: a cyan rim in the pole-light colour that keeps their silhouettes off
 * the navy, and a dim cool-white fill, because every light in the facility is
 * above and behind the courts and the near face of a foreground paddle
 * otherwise renders as a black hole in the picture.
 *
 * Everything here is built from primitives on purpose: a real paddle model is
 * a 2MB download to be four percent better at this size, and the shape a
 * player recognises is the silhouette — rounded face, bright edge guard,
 * wrapped grip, butt cap. Those are cheap.
 *
 * The scroll choreography lives in `update(t, p)`: `t` is wall time for the
 * idle float, `p` is hero scroll progress 0..1. The paddle turns to face you
 * and the ball serves in as you scroll — see hero.js for where p comes from.
 */

/** A paddle face as a rounded rectangle, extruded — the shape is the product. */
function roundedRect(THREE, w, h, r) {
  const s = new THREE.Shape();
  s.moveTo(-w / 2 + r, -h / 2);
  s.lineTo(w / 2 - r, -h / 2);
  s.quadraticCurveTo(w / 2, -h / 2, w / 2, -h / 2 + r);
  s.lineTo(w / 2, h / 2 - r);
  s.quadraticCurveTo(w / 2, h / 2, w / 2 - r, h / 2);
  s.lineTo(-w / 2 + r, h / 2);
  s.quadraticCurveTo(-w / 2, h / 2, -w / 2, h / 2 - r);
  s.lineTo(-w / 2, -h / 2 + r);
  s.quadraticCurveTo(-w / 2, -h / 2, -w / 2 + r, -h / 2);
  return s;
}

function extrude(THREE, shape, depth, curveSegments) {
  return new THREE.ExtrudeGeometry(shape, {
    depth,
    bevelEnabled: true,
    bevelThickness: 0.015,
    bevelSize: 0.015,
    bevelSegments: 2,
    curveSegments,
  });
}

/** The blue edge guard as a ring, so it frames the face instead of burying it. */
function paddleRim(THREE, w, h, r, thickness, depth, curveSegments) {
  const ring = roundedRect(THREE, w, h, r);
  ring.holes.push(roundedRect(THREE, w - thickness * 2, h - thickness * 2, Math.max(0.03, r - thickness)));
  return extrude(THREE, ring, depth, curveSegments);
}

/**
 * @param opts {tier:'full'|'lite'}
 * @returns {{group, update(t:number, p:number):void, lights: object[]}}
 */
export function buildProps(THREE, { tier }) {
  const full = tier === 'full';
  const seg = full ? 10 : 6;
  const group = new THREE.Group();

  // ----------------------------------------------------------------- ball
  const ball = new THREE.Group();
  group.add(ball);

  const shell = new THREE.Mesh(
    new THREE.SphereGeometry(0.62, full ? 32 : 16, full ? 24 : 12),
    new THREE.MeshStandardMaterial({
      color: 0x9fe75d,
      roughness: 0.42,
      metalness: 0.02,
      // A pickleball under floodlights is the brightest thing on the court.
      // A little emissive keeps it that way once the fog eats the key light.
      emissive: 0x2f5416,
      emissiveIntensity: 0.55,
    })
  );
  shell.castShadow = full;
  ball.add(shell);

  // Real pickleball perforation: recessed dark discs on a Fibonacci spiral, so
  // the holes stay evenly spread however the ball turns. Cheaper than boolean
  // geometry and identical at this size.
  const count = full ? 26 : 12;
  const holeGeo = new THREE.CircleGeometry(0.105, seg);
  const holeMat = new THREE.MeshStandardMaterial({ color: 0x2c4a13, roughness: 0.9 });
  for (let i = 0; i < count; i++) {
    const phi = Math.acos(1 - (2 * (i + 0.5)) / count);
    const theta = Math.PI * (1 + Math.sqrt(5)) * i;
    const hole = new THREE.Mesh(holeGeo, holeMat);
    hole.position.setFromSphericalCoords(0.612, phi, theta);
    hole.lookAt(0, 0, 0);
    hole.rotateY(Math.PI);
    ball.add(hole);
  }

  // --------------------------------------------------------------- paddle
  const paddle = new THREE.Group();
  paddle.scale.setScalar(0.86);
  group.add(paddle);

  const FW = 1.5;
  const FH = 1.95;
  const FR = 0.28;
  const FD = 0.09;

  const face = new THREE.Mesh(
    extrude(THREE, roundedRect(THREE, FW, FH, FR), FD, seg),
    new THREE.MeshStandardMaterial({ color: 0x33465f, roughness: 0.46, metalness: 0.3 })
  );
  face.position.z = -0.045;
  face.castShadow = full;
  paddle.add(face);

  // Raw-carbon reads as a directional sheen, not a texture map: two thin
  // strips catching the light across the face. One draw call each, and they
  // are what stops the face looking like flat plastic when it turns.
  if (full) {
    const sheenMat = new THREE.MeshBasicMaterial({
      color: 0x5f7da3, transparent: true, opacity: 0.22, depthWrite: false,
    });
    [[0.34, 0.055], [-0.22, 0.03]].forEach(([y, h]) => {
      const strip = new THREE.Mesh(new THREE.PlaneGeometry(FW * 0.82, h), sheenMat);
      strip.position.set(0, y, FD / 2 + 0.008);
      paddle.add(strip);
    });
  }

  // The blue edge guard, not a glow: a physical rim in the brand blue.
  const rimBand = new THREE.Mesh(
    paddleRim(THREE, FW + 0.12, FH + 0.12, FR + 0.04, 0.07, 0.13, seg),
    new THREE.MeshStandardMaterial({
      color: 0x1596e8, roughness: 0.3, metalness: 0.5, emissive: 0x0a5f9e, emissiveIntensity: 0.7,
    })
  );
  rimBand.position.z = -0.065;
  paddle.add(rimBand);

  const throat = new THREE.Mesh(
    new THREE.BoxGeometry(0.42, 0.38, 0.13),
    new THREE.MeshStandardMaterial({ color: 0x1b2637, roughness: 0.4, metalness: 0.3 })
  );
  throat.position.y = -1.14;
  paddle.add(throat);

  // Grip: short and thick. Overgrip ridges were tried here and cut — at ~130px
  // tall on screen the bands land under a pixel each and alias into something
  // that reads as a coil spring, not a wrap. The taper does the same job for
  // free.
  const grip = new THREE.Mesh(
    new THREE.CylinderGeometry(0.165, 0.19, 0.86, full ? 14 : 7),
    new THREE.MeshStandardMaterial({ color: 0x2b3646, roughness: 0.95 })
  );
  grip.position.y = -1.72;
  grip.castShadow = full;
  paddle.add(grip);

  const butt = new THREE.Mesh(
    new THREE.CylinderGeometry(0.21, 0.21, 0.09, full ? 14 : 7),
    new THREE.MeshStandardMaterial({ color: 0x0f8ce0, roughness: 0.35, metalness: 0.5 })
  );
  butt.position.y = -2.19;
  paddle.add(butt);

  // Two lamps travelling with the props.
  //
  // `rim` is the cyan back light that keeps their silhouettes off the navy.
  // `fill` is the concession: the facility's own lights are all above and
  // behind the courts, so a paddle held in the foreground got nothing on its
  // near face and rendered as a black slab inside a blue outline. One dim,
  // short-range fill in the same cool white as the floodlights is what makes
  // it a carbon face instead of a hole in the picture.
  const rim = new THREE.PointLight(0x37b6ff, 26, 14, 2);
  rim.position.set(-1.6, 1.4, -2.6);
  group.add(rim);

  const fill = new THREE.PointLight(0xd8e6ff, 16, 12, 2);
  fill.position.set(-1.1, 0.9, 3.4);
  group.add(fill);

  /* ------------------------------------------------------ choreography ---
   * Scroll is the serve. At the top of the page the paddle is turned away and
   * the ball is still up in the toss; scrolling the height of the hero brings
   * the paddle round to face you and drops the ball into it with a couple of
   * decaying bounces. Everything eases off `p`, so it is scrubbable in both
   * directions and lands in exactly the same place however fast you scroll.
   */

  // Smoothstep between two scroll positions.
  const band = (a, b, p) => {
    const t = Math.min(1, Math.max(0, (p - a) / (b - a)));
    return t * t * (3 - 2 * t);
  };

  // A damped bounce: 1 at the top, settling to 0. Three contacts, each
  // shorter than the last, because a ball that bounces evenly reads as a loop.
  const bounce = (t) => Math.abs(Math.cos(t * Math.PI * 3)) * (1 - t);

  /* The props sit high in the hero, so they leave the top of the viewport
   * after roughly a third of the hero's height. Spending the choreography
   * across the full 0..1 of scroll played most of it off screen — the bands
   * below are deliberately compressed into the part of the scroll where there
   * is still something to look at. */
  const update = (t, p) => {
    const turn = band(0, 0.30, p);

    // Paddle: face toward the camera at rest — the top of the page is the shot
    // that gets looked at, so it is composed, not mid-animation. Scrolling
    // rolls it through a turn and a half. The idle float never stops, so
    // pausing mid-scroll is not a freeze frame.
    paddle.position.set(-0.95, -0.35 + Math.sin(t * 0.47 + 1.2) * 0.1, -0.5);
    paddle.rotation.set(
      -0.14 + Math.sin(t * 0.28) * 0.05,
      0.34 + turn * Math.PI * 1.5 + Math.sin(t * 0.28) * 0.18,
      -0.24 + Math.sin(t * 0.21) * 0.06 - turn * 0.16
    );

    // Ball: held high beside the paddle at rest, served down into the frame as
    // you scroll, settling with a couple of decaying bounces. The travel is
    // deliberately short — anything taller and the rest pose puts the ball
    // outside the crop, which is the one frame that has to be right.
    const served = band(0.02, 0.24, p);
    ball.position.set(
      1.42 - served * 0.30,
      -0.15 + (1 - served) * 1.15 + bounce(served) * 0.5 + Math.sin(t * 0.62) * 0.12,
      0.2
    );
    // Spin scales with how fast it is still travelling — it slows as it lands.
    ball.rotation.y = t * 0.42 + (1 - served) * 2.6;
    ball.rotation.x = Math.sin(t * 0.3) * 0.4;
  };

  // `lights` is what the lights-on tween drives: peak intensity is stored on
  // each lamp so the caller fades them without knowing what they are for.
  rim.userData.peak = 26;
  fill.userData.peak = 16;

  return { group, update, lights: [rim, fill] };
}
