<?php
/**
 * Hero fallback scene — a finished visual in its own right, not a placeholder.
 *
 * Ships in the HTML and paints immediately. three.js replaces it later (and
 * only if the device can afford it, see hero.js); everyone else keeps this.
 * Geometry is perspective-correct: screen position `s` for court distance `u`
 * uses s = u·wFar / (u·wFar + (1-u)·wNear), which is why the kitchen lines sit
 * where they actually would rather than where a linear lerp would put them.
 */
?>
<svg class="hero-fallback" viewBox="0 0 1600 900" preserveAspectRatio="xMidYMid slice" role="img"
     aria-label="Go Paddle's floodlit centre court seen from behind the baseline, net and kitchen lines picked out under the pole lights.">
  <defs>
    <linearGradient id="sky" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0%"   stop-color="#FFFFFF"/>
      <stop offset="55%"  stop-color="#F4FFF5"/>
      <stop offset="100%" stop-color="#CFFED3"/>
    </linearGradient>

    <radialGradient id="pool" cx="50%" cy="50%" r="50%">
      <stop offset="0%"   stop-color="#39FB49" stop-opacity="0.30"/>
      <stop offset="60%"  stop-color="#39FB49" stop-opacity="0.07"/>
      <stop offset="100%" stop-color="#39FB49" stop-opacity="0"/>
    </radialGradient>

    <!-- Court red, the same surface colour hero/world.js paints in WebGL
         (PALETTE.court #a02c33). These two cross-fade into each other, so a
         blue court here meant the courts changed colour a second after load. -->
    <linearGradient id="surface" x1="0" y1="1" x2="0" y2="0">
      <stop offset="0%"   stop-color="oklch(0.435 0.135 22)"/>
      <stop offset="100%" stop-color="oklch(0.345 0.110 22)"/>
    </linearGradient>

    <linearGradient id="apron" x1="0" y1="1" x2="0" y2="0">
      <stop offset="0%"   stop-color="oklch(0.34 0.075 150)"/>
      <stop offset="100%" stop-color="oklch(0.26 0.060 152)"/>
    </linearGradient>

    <linearGradient id="beam" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0%"   stop-color="#9DFDA5" stop-opacity="0.14"/>
      <stop offset="100%" stop-color="#9DFDA5" stop-opacity="0"/>
    </linearGradient>

    <linearGradient id="fade" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0%"   stop-color="#FFFFFF" stop-opacity="0.85"/>
      <stop offset="45%"  stop-color="#FFFFFF" stop-opacity="0"/>
    </linearGradient>
  </defs>

  <rect width="1600" height="900" fill="url(#sky)"/>

  <!-- Pole lights and their beams -->
  <g opacity="0.9">
    <ellipse cx="420" cy="700" rx="620" ry="290" fill="url(#pool)"/>
    <ellipse cx="1180" cy="700" rx="620" ry="290" fill="url(#pool)"/>
    <polygon points="352,150 388,150 640,760 120,760" fill="url(#beam)"/>
    <polygon points="1212,150 1248,150 1480,760 960,760" fill="url(#beam)"/>
    <g fill="#4F9C57">
      <rect x="364" y="150" width="12" height="470"/>
      <rect x="1224" y="150" width="12" height="470"/>
    </g>
    <g fill="#0B2410">
      <rect x="336" y="136" width="68" height="16" rx="2"/>
      <rect x="1196" y="136" width="68" height="16" rx="2"/>
    </g>
  </g>

  <!-- Apron (the green surround) -->
  <polygon points="150,880 1450,880 1120,406 480,406" fill="url(#apron)"/>

  <!-- Playing surface -->
  <polygon points="300,820 1300,820 1010,430 590,430" fill="url(#surface)"/>

  <!-- Court markings. u = fraction of court length from the near baseline. -->
  <g stroke="oklch(0.96 0.01 257)" stroke-opacity="0.82" stroke-width="3" fill="none" stroke-linecap="square">
    <!-- Baselines -->
    <line x1="300"   y1="820"   x2="1300"  y2="820"/>
    <line x1="590"   y1="430"   x2="1010"  y2="430"/>
    <!-- Sidelines -->
    <line x1="300"   y1="820"   x2="590"   y2="430"/>
    <line x1="1300"  y1="820"   x2="1010"  y2="430"/>
    <!-- Kitchen lines: u = 15/44 and 29/44 -->
    <line x1="351.8" y1="750.4" x2="1248.2" y2="750.4"/>
    <line x1="430.0" y1="645.2" x2="1170.0" y2="645.2"/>
    <!-- Centre lines, service boxes only -->
    <line x1="800"   y1="820"   x2="800"    y2="750.4"/>
    <line x1="800"   y1="645.2" x2="800"    y2="430"/>
  </g>

  <!-- Net at u = 0.5 -->
  <g>
    <rect x="385.8" y="640" width="828.4" height="66" fill="#0B2410" fill-opacity="0.62"/>
    <path d="M385.8 640 H1214.2" stroke="oklch(0.96 0.01 257)" stroke-width="4"/>
    <path d="M385.8 706 H1214.2" stroke="oklch(0.70 0.024 257)" stroke-width="2"/>
    <g stroke="oklch(0.70 0.024 257)" stroke-opacity="0.45" stroke-width="1.2">
      <?php for ($i = 1; $i < 46; $i++): ?>
        <line x1="<?= round(385.8 + $i * 18.4, 1) ?>" y1="640" x2="<?= round(385.8 + $i * 18.4, 1) ?>" y2="706"/>
      <?php endfor; ?>
      <line x1="385.8" y1="662" x2="1214.2" y2="662"/>
      <line x1="385.8" y1="684" x2="1214.2" y2="684"/>
    </g>
    <rect x="378" y="628" width="12" height="96" fill="#4F9C57"/>
    <rect x="1210" y="628" width="12" height="96" fill="#4F9C57"/>
  </g>

  <!-- Paddle resting on the near baseline, and the ball mid-bounce -->
  <g transform="translate(1010 792) rotate(-14)">
    <rect x="-52" y="-72" width="104" height="118" rx="12" fill="#CFFED3" stroke="#07FA1B" stroke-width="2.5"/>
    <rect x="-46" y="-66" width="92" height="106" rx="9" fill="#EDFFEF"/>
    <rect x="-13" y="44" width="26" height="54" rx="7" fill="#4F9C57"/>
  </g>

  <g>
    <ellipse cx="612" cy="784" rx="30" ry="7" fill="#0B2410" fill-opacity="0.25"/>
    <circle cx="612" cy="720" r="21" fill="oklch(0.845 0.190 133)"/>
    <g fill="oklch(0.30 0.09 140)" fill-opacity="0.55">
      <circle cx="605" cy="712" r="2.6"/><circle cx="618" cy="711" r="2.6"/>
      <circle cx="600" cy="723" r="2.6"/><circle cx="612" cy="722" r="2.6"/>
      <circle cx="623" cy="723" r="2.6"/><circle cx="606" cy="732" r="2.6"/>
      <circle cx="618" cy="731" r="2.6"/>
    </g>
  </g>

  <!-- Top fade so the sticky nav always has contrast under it -->
  <rect width="1600" height="380" fill="url(#fade)"/>
</svg>
