<?php
/**
 * Top-down plan of the compound. Court positions come straight from
 * courts.map_x / map_y (metres from the north-west corner), so the drawing
 * and the building stay in sync from one source. 20px = 1m.
 *
 * @var list<array<string,mixed>> $courts
 */
$scale = 20;
$tileW = 12 * $scale;   // 12m footprint including run-off
$tileH = 15 * $scale;
$pad   = 12;

$maxX = 0;
$maxY = 0;
foreach ($courts as $c) {
    $maxX = max($maxX, (float) $c['map_x'] * $scale + $tileW);
    $maxY = max($maxY, (float) $c['map_y'] * $scale + $tileH);
}

// Status tones come from the stylesheet, not from a second copy of the palette
// kept in sync by hand. `fill="var(--green)"` is legal: SVG presentation
// attributes are parsed as CSS values.
$tone = [
    'open'    => 'var(--green)',
    'filling' => 'var(--amber)',
    'booked'  => 'var(--red)',
    'closed'  => 'var(--grey)',
];

// Label band, drawn immediately above each tile so the code chip and the
// status word never land on the playing surface. Every row is offset by it,
// which is also what keeps the top row's band inside the viewBox.
// 20px, not more: the rows are pitched 16m apart around a 15m tile, so the
// gap between two tiles is exactly 1m of drawing and the band has to fit in it.
$band = 20;
?>
<svg viewBox="0 0 <?= $maxX + $pad * 2 ?> <?= $maxY + $pad * 2 + $band + 34 ?>" role="group" aria-label="Plan of the five courts at Go Paddle">
  <?php foreach ($courts as $court): ?>
    <?php
    $x = $pad + (float) $court['map_x'] * $scale;
    $y = $pad + $band + (float) $court['map_y'] * $scale;
    $m = status_meta($court['day_state']);
    $c = $tone[$m['tone']] ?? $tone['closed'];

    // Playing area: 6.10m × 13.41m, centred in the 12m × 15m footprint. The
    // label band is drawn *above* the footprint, not inside it — a 12m tile
    // around a 13.41m court leaves 1.6m of run-off in total, and spending any
    // of it on a text band put the baseline hard against the tile edge.
    $pw = 6.10 * $scale;
    $ph = 13.41 * $scale;
    $px = $x + ($tileW - $pw) / 2;
    $py = $y + ($tileH - $ph) / 2;
    $kitchen = 2.13 * $scale;   // 7ft non-volley zone
    $netY    = $py + $ph / 2;
    ?>
    <a class="map-court" href="<?= base_url('courts/' . $court['slug']) ?>"
       data-map-court="<?= (int) $court['id'] ?>"
       aria-label="<?= esc($court['code'] . ' ' . $court['name'] . ', ' . $m['label'] . ', ' . $court['open_slots'] . ' slots free today') ?>">
      <rect x="<?= $x ?>" y="<?= $y - $band ?>" width="<?= $tileW ?>" height="<?= $tileH + $band ?>" rx="3"
            fill="<?= $c ?>" fill-opacity="0.10" stroke="<?= $c ?>" stroke-opacity="0.65"/>

      <?php /* Court red, exactly like the 3D scene: the surface is a surface, not
               a status light. Status lives in the tile wash, the code chip and the
               label — the same division of labour hero/world.js uses. */ ?>
      <rect class="map-surface" x="<?= $px ?>" y="<?= $py ?>" width="<?= $pw ?>" height="<?= $ph ?>"
            fill="var(--court)" fill-opacity="0.92"/>

      <g class="map-line">
        <rect x="<?= $px ?>" y="<?= $py ?>" width="<?= $pw ?>" height="<?= $ph ?>" fill="none"/>
        <line x1="<?= $px ?>" y1="<?= $netY - $kitchen ?>" x2="<?= $px + $pw ?>" y2="<?= $netY - $kitchen ?>"/>
        <line x1="<?= $px ?>" y1="<?= $netY + $kitchen ?>" x2="<?= $px + $pw ?>" y2="<?= $netY + $kitchen ?>"/>
        <line x1="<?= $px + $pw / 2 ?>" y1="<?= $py ?>" x2="<?= $px + $pw / 2 ?>" y2="<?= $netY - $kitchen ?>"/>
        <line x1="<?= $px + $pw / 2 ?>" y1="<?= $netY + $kitchen ?>" x2="<?= $px + $pw / 2 ?>" y2="<?= $py + $ph ?>"/>
      </g>
      <line class="map-net" x1="<?= $px - 6 ?>" y1="<?= $netY ?>" x2="<?= $px + $pw + 6 ?>" y2="<?= $netY ?>"/>

      <?php // Chip sized to the code: 11px mono with 0.06em tracking is ~7.3px a glyph. ?>
      <rect x="<?= $x + 5 ?>" y="<?= $y - $band + 3 ?>" width="<?= 11 + (int) ceil(mb_strlen($court['code']) * 7.3) ?>" height="15" rx="2" fill="<?= $c ?>"/>
      <text class="map-label" x="<?= $x + 10 ?>" y="<?= $y - $band + 14 ?>"><?= esc($court['code']) ?></text>
      <text class="map-sub" x="<?= $x + $tileW - 6 ?>" y="<?= $y - $band + 14 ?>" text-anchor="end"
            fill="<?= $c ?>"><?= esc(strtoupper($m['label'])) ?> · <?= (int) $court['open_slots'] ?></text>
    </a>
  <?php endforeach; ?>

  <!-- Orientation: the highway is the north edge you walk in from. -->
  <g>
    <line x1="<?= $pad ?>" y1="<?= $maxY + $pad + $band + 14 ?>" x2="<?= $maxX + $pad ?>" y2="<?= $maxY + $pad + $band + 14 ?>"
          stroke="oklch(0.375 0.035 257)" stroke-width="1"/>
    <text class="map-sub" x="<?= $pad ?>" y="<?= $maxY + $pad + $band + 30 ?>" fill="oklch(0.700 0.024 257)">NATIONAL HIGHWAY · ENTRANCE &amp; DESK</text>
    <text class="map-sub" x="<?= $maxX + $pad ?>" y="<?= $maxY + $pad + $band + 30 ?>" text-anchor="end" fill="oklch(0.700 0.024 257)">AGUSAN RIVER →</text>
  </g>
</svg>
