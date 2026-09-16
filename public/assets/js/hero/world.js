/* The facility, built as a place rather than a diagram.
 *
 * Five real courts on one ground plane, with the things a court actually has:
 * a slab with thickness, run-off, a net that hangs, posts, fencing, flood
 * poles and a clubhouse behind. Status is carried by the edge strip and the
 * HUD, not by repainting the surface — the surface stays court red, because
 * that is what the surface is.
 *
 * THREE is passed in rather than imported: the entry module already resolved
 * the vendor path, and one import site is one path to get wrong.
 */

export const PALETTE = {
  court: 0xa02c33,
  apron: 0x1b4531,
  ground: 0x122519,
  line: 0xdfe7f4,
  steel: 0x39485c,
  darkSteel: 0x212c3c,
  net: 0xb9c6d8,
  fog: 0xedffef,
};

/** Status tones. Green means available and nothing else (DESIGN.md). */
export const TONE = {
  open: 0x8fe04f,
  filling: 0xf7a830,
  booked: 0xf4514f,
  closed: 0x64748b,
  maintenance: 0xf7a830,
  past: 0x64748b,
};

/* Court dimensions are regulation: 20ft x 44ft, kitchen 7ft. */
const PLAY_W = 6.1;
const PLAY_D = 13.41;
const KITCHEN = 2.13;
const SLAB_W = 8.6;
const SLAB_D = 15.4;
const SLAB_H = 0.18;
const TILE_W = 12;
const TILE_D = 15;

/** A transparent mesh texture — used for both the nets and the perimeter fence. */
function meshTexture(THREE, { step, diagonal, alpha, line }) {
  const c = document.createElement('canvas');
  c.width = c.height = 64;
  const g = c.getContext('2d');
  g.strokeStyle = `rgba(198,214,236,${alpha})`;
  g.lineWidth = line;
  g.beginPath();
  for (let i = 0; i <= 64; i += step) {
    if (diagonal) {
      g.moveTo(i - 64, 0); g.lineTo(i, 64);
      g.moveTo(i, 0); g.lineTo(i - 64, 64);
    } else {
      g.moveTo(i, 0); g.lineTo(i, 64);
      g.moveTo(0, i); g.lineTo(64, i);
    }
  }
  g.stroke();

  const tex = new THREE.CanvasTexture(c);
  tex.wrapS = tex.wrapT = THREE.RepeatWrapping;
  tex.colorSpace = THREE.SRGBColorSpace;
  return tex;
}

/**
 * @param courts  [{id, code, name, x, y, state, now, free, rate, href}]
 * @param opts    {tier: 'full'|'lite'}
 * @returns {{group, rigs, lamps, heads, key, bounds}}
 */
export function buildWorld(THREE, courts, { tier }) {
  const full = tier === 'full';
  const group = new THREE.Group();

  let minX = Infinity, maxX = -Infinity, minZ = Infinity, maxZ = -Infinity;
  courts.forEach((c) => {
    minX = Math.min(minX, c.x); maxX = Math.max(maxX, c.x + TILE_W);
    minZ = Math.min(minZ, c.y); maxZ = Math.max(maxZ, c.y + TILE_D);
  });
  const centerX = (minX + maxX) / 2;
  const centerZ = (minZ + maxZ) / 2;
  const bounds = { minX, maxX, minZ, maxZ, centerX, centerZ };

  // ---------------------------------------------------------------- ground
  // Generous run-off: the apron edge has to leave the frame rather than sit in
  // it as a hard rectangle floating on the dark.
  const apronW = maxX - minX + 26;
  const apronD = maxZ - minZ + 24;

  const ground = new THREE.Mesh(
    new THREE.PlaneGeometry(260, 260),
    new THREE.MeshStandardMaterial({ color: PALETTE.ground, roughness: 1 })
  );
  ground.rotation.x = -Math.PI / 2;
  ground.position.set(centerX, -0.08, centerZ);
  group.add(ground);

  const apron = new THREE.Mesh(
    new THREE.BoxGeometry(apronW, 0.1, apronD),
    new THREE.MeshStandardMaterial({ color: PALETTE.apron, roughness: 0.94 })
  );
  apron.position.set(centerX, -0.05, centerZ);
  apron.receiveShadow = full;
  group.add(apron);

  // ----------------------------------------------------------- shared bits
  const lineMat = new THREE.MeshStandardMaterial({
    color: PALETTE.line, roughness: 0.55, emissive: 0x243550, emissiveIntensity: 0.5,
  });
  const steelMat = new THREE.MeshStandardMaterial({ color: PALETTE.steel, roughness: 0.45, metalness: 0.65 });
  const darkSteelMat = new THREE.MeshStandardMaterial({ color: PALETTE.darkSteel, roughness: 0.55, metalness: 0.5 });

  const netTex = meshTexture(THREE, { step: 6, diagonal: false, alpha: 0.95, line: 1.4 });
  netTex.repeat.set(24, 4);
  const netMat = new THREE.MeshStandardMaterial({
    map: netTex, color: PALETTE.net, transparent: true, opacity: 0.9,
    side: THREE.DoubleSide, depthWrite: false, roughness: 0.8,
    emissive: 0x223349, emissiveIntensity: 0.6,
  });

  const linePlane = new THREE.PlaneGeometry(1, 1);
  const postGeo = new THREE.CylinderGeometry(0.05, 0.065, 1.05, full ? 10 : 6);
  const netGeo = new THREE.PlaneGeometry(PLAY_W + 0.6, 0.86);
  const tapeGeo = new THREE.BoxGeometry(PLAY_W + 0.62, 0.05, 0.02);

  // --------------------------------------------------------------- courts
  const rigs = courts.map((court) => {
    const cx = court.x + TILE_W / 2;
    const cz = court.y + TILE_D / 2;
    const root = new THREE.Group();
    root.position.set(cx, 0, cz);
    group.add(root);

    const tone = TONE[court.now] ?? TONE.closed;

    // Court red, nudged a fraction toward the live status so a glance at the
    // surface still tells you something. The tint never becomes the colour.
    const surfaceMat = new THREE.MeshStandardMaterial({
      color: new THREE.Color(PALETTE.court).lerp(new THREE.Color(tone), 0.09),
      roughness: 0.74,
      metalness: 0.04,
      // Hover brightens the court, it does not recolour it: the emissive is the
      // surface's own red, so a lit court is a brighter court and the status
      // stays where status belongs — the edge strip and the readout.
      emissive: new THREE.Color(PALETTE.court),
      emissiveIntensity: 0,
    });

    const slab = new THREE.Mesh(new THREE.BoxGeometry(SLAB_W, SLAB_H, SLAB_D), surfaceMat);
    slab.position.y = SLAB_H / 2;
    slab.receiveShadow = full;
    slab.castShadow = full;
    slab.userData.court = court;
    root.add(slab);

    // Edge strip: the status light. Four thin bars sunk into the slab rim.
    const edgeMat = new THREE.MeshBasicMaterial({ color: tone, transparent: true, opacity: 0.42 });
    const edgeGeo = new THREE.BoxGeometry(1, 0.035, 1);
    [[SLAB_W, 0.09, 0, SLAB_D / 2], [SLAB_W, 0.09, 0, -SLAB_D / 2],
     [0.09, SLAB_D, SLAB_W / 2, 0], [0.09, SLAB_D, -SLAB_W / 2, 0]].forEach(([w, d, x, z]) => {
      const bar = new THREE.Mesh(edgeGeo, edgeMat);
      bar.scale.set(w, 1, d);
      bar.position.set(x, SLAB_H - 0.01, z);
      root.add(bar);
    });

    // Markings
    const y = SLAB_H + 0.006;
    const L = 0.075;
    const half = PLAY_D / 2;
    const box = half - KITCHEN;
    const mark = (w, d, x, z) => {
      const m = new THREE.Mesh(linePlane, lineMat);
      m.scale.set(w, d, 1);
      m.rotation.x = -Math.PI / 2;
      m.position.set(x, y, z);
      root.add(m);
    };
    mark(PLAY_W, L, 0, -half); mark(PLAY_W, L, 0, half);
    mark(L, PLAY_D, -PLAY_W / 2, 0); mark(L, PLAY_D, PLAY_W / 2, 0);
    mark(PLAY_W, L, 0, -KITCHEN); mark(PLAY_W, L, 0, KITCHEN);
    mark(L, box, 0, -KITCHEN - box / 2); mark(L, box, 0, KITCHEN + box / 2);

    // Net, tape and posts
    const net = new THREE.Mesh(netGeo, netMat);
    net.position.set(0, SLAB_H + 0.43, 0);
    root.add(net);

    const tape = new THREE.Mesh(tapeGeo, lineMat);
    tape.position.set(0, SLAB_H + 0.86, 0);
    root.add(tape);

    [-1, 1].forEach((s) => {
      const post = new THREE.Mesh(postGeo, darkSteelMat);
      post.position.set(s * (PLAY_W / 2 + 0.3), SLAB_H + 0.525, 0);
      post.castShadow = full;
      root.add(post);
    });

    return {
      court,
      root,
      slab,
      surfaceMat,
      edgeMat,
      center: new THREE.Vector3(cx, SLAB_H, cz),
      lift: 0,
    };
  });

  // ---------------------------------------------------------------- fence
  const fx = apronW / 2 - 0.4;
  const fz = apronD / 2 - 0.4;
  const fenceH = 3.4;
  const fenceTex = meshTexture(THREE, { step: 9, diagonal: true, alpha: 0.55, line: 1 });
  const fenceMat = new THREE.MeshBasicMaterial({
    map: fenceTex, transparent: true, opacity: 0.16, side: THREE.DoubleSide, depthWrite: false,
  });

  [[apronW, 0, -fz, 0], [apronW, 0, fz, 0], [apronD, -fx, 0, Math.PI / 2], [apronD, fx, 0, Math.PI / 2]]
    .forEach(([len, ox, oz, rot]) => {
      const tex = fenceTex.clone();
      tex.needsUpdate = true;
      tex.repeat.set(len / 2.2, fenceH / 2.2);
      const panel = new THREE.Mesh(new THREE.PlaneGeometry(len, fenceH), fenceMat.clone());
      panel.material.map = tex;
      panel.position.set(centerX + ox, fenceH / 2, centerZ + oz);
      panel.rotation.y = rot;
      group.add(panel);

      // Rails read as structure where the mesh itself is nearly invisible.
      [0.06, fenceH].forEach((ry) => {
        const rail = new THREE.Mesh(new THREE.BoxGeometry(len, 0.07, 0.07), darkSteelMat);
        rail.position.set(centerX + ox, ry, centerZ + oz);
        rail.rotation.y = rot;
        group.add(rail);
      });
    });

  // ----------------------------------------------------------- flood poles
  const poleSpots = full
    ? [[-fx + 1.5, -fz + 1.5], [0, -fz + 1.5], [fx - 1.5, -fz + 1.5],
       [-fx + 1.5, fz - 1.5], [0, fz - 1.5], [fx - 1.5, fz - 1.5]]
    : [[-fx + 1.5, -fz + 1.5], [fx - 1.5, -fz + 1.5], [-fx + 1.5, fz - 1.5], [fx - 1.5, fz - 1.5]];

  const poleGeo = new THREE.CylinderGeometry(0.13, 0.22, 11, full ? 10 : 6);
  const armGeo = new THREE.BoxGeometry(2.6, 0.16, 0.5);
  const headGeo = new THREE.BoxGeometry(0.9, 0.28, 0.55);
  const heads = [];
  const lamps = [];

  poleSpots.forEach(([ox, oz]) => {
    const px = centerX + ox;
    const pz = centerZ + oz;

    const pole = new THREE.Mesh(poleGeo, steelMat);
    pole.position.set(px, 5.5, pz);
    pole.castShadow = full;
    group.add(pole);

    const arm = new THREE.Mesh(armGeo, darkSteelMat);
    arm.position.set(px, 10.9, pz);
    arm.lookAt(centerX, 10.9, centerZ);
    group.add(arm);

    [-0.85, 0.85].forEach((off) => {
      const head = new THREE.Mesh(headGeo, new THREE.MeshBasicMaterial({ color: 0x0b1a2e }));
      head.position.set(px, 10.75, pz);
      head.lookAt(centerX, 0, centerZ);
      head.translateX(off);
      group.add(head);
      heads.push(head);
    });

    const lamp = new THREE.PointLight(0xd8e6ff, 0, 70, 1.75);
    lamp.position.set(px, 10.4, pz);
    group.add(lamp);
    lamps.push(lamp);
  });

  // ------------------------------------------------- clubhouse and benches
  if (full) {
    const shell = new THREE.MeshStandardMaterial({ color: 0x16202f, roughness: 0.9 });
    const club = new THREE.Mesh(new THREE.BoxGeometry(18, 4.2, 7), shell);
    club.position.set(centerX + 4, 2.1, minZ - 17);
    club.castShadow = full;
    group.add(club);

    const roof = new THREE.Mesh(new THREE.BoxGeometry(19, 0.25, 8), darkSteelMat);
    roof.position.set(centerX + 4, 4.3, minZ - 17);
    group.add(roof);

    const winMat = new THREE.MeshBasicMaterial({ color: 0x2b4a6d });
    for (let i = -2; i <= 2; i++) {
      const win = new THREE.Mesh(new THREE.BoxGeometry(2.2, 1.1, 0.1), winMat);
      win.position.set(centerX + 4 + i * 3.2, 2.5, minZ - 13.45);
      group.add(win);
    }

    // Courtside benches: the only furniture. Anything more is set dressing.
    courts.slice(0, 3).forEach((c) => {
      const bench = new THREE.Mesh(new THREE.BoxGeometry(2.6, 0.42, 0.6), darkSteelMat);
      bench.position.set(c.x + TILE_W / 2, 0.21, c.y + TILE_D + 0.6);
      bench.castShadow = full;
      group.add(bench);
    });
  }

  // -------------------------------------------------------------- lighting
  const ambient = new THREE.HemisphereLight(0xffffff, 0x9dfda5, 2.4);
  group.add(ambient);

  // The one shadow caster. Cool, high and off to the left, so the poles and
  // posts drop the contact shadows that make the slabs sit on the ground.
  const key = new THREE.DirectionalLight(0xfffbe8, 0);
  key.position.set(centerX - 34, 44, centerZ + 26);
  key.target.position.set(centerX, 0, centerZ);
  group.add(key.target);
  if (full) {
    key.castShadow = true;
    key.shadow.mapSize.set(1024, 1024);
    key.shadow.camera.near = 10;
    key.shadow.camera.far = 130;
    const r = Math.max(apronW, apronD) * 0.62;
    Object.assign(key.shadow.camera, { left: -r, right: r, top: r, bottom: -r });
    key.shadow.bias = -0.0012;
    key.shadow.normalBias = 0.03;
  }
  group.add(key);

  // A cyan rim from behind, so court edges and posts separate from the navy.
  const rim = new THREE.DirectionalLight(0x39fb49, 0.9);
  rim.position.set(centerX + 30, 12, centerZ - 40);
  group.add(rim);

  return { group, rigs, lamps, heads, key, bounds };
}
