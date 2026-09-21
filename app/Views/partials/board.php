<?php
/**
 * The availability board — courts × time slots (booking.slot_minutes).
 *
 * @var array $board    from Availability::board()
 * @var bool  $bookable link open cells straight into the booking form
 */
$bookable = $bookable ?? true;
$live     = $live ?? false;
$slots    = count($board['times']);
?>
<div class="board" data-board <?= $live ? 'data-live' : '' ?> data-date="<?= esc($board['date']) ?>">
  <div class="board-grid" style="--slots:<?= $slots ?>">
    <div class="board-corner"><?= esc(day_label($board['date'])) ?></div>
    <?php foreach ($board['times'] as $i => $time): ?>
      <?php // The service already knows peak, including windows that wrap midnight.
      $isPeak = (bool) ($board['courts'][0]['cells'][$i]['peak'] ?? false); ?>
      <div class="board-time<?= $isPeak ? ' is-peak' : '' ?>"><?= esc(clock($time, true)) ?></div>
    <?php endforeach; ?>

    <?php foreach ($board['courts'] as $court): ?>
      <?php $day = status_meta($court['day_state']); ?>
      <a class="board-court tone-<?= esc($day['tone']) ?>" href="<?= base_url('courts/' . $court['slug']) ?>" data-court="<?= (int) $court['id'] ?>">
        <span>
          <b><?= esc($court['code']) ?></b><br>
          <span><?= esc($court['name']) ?></span>
        </span>
        <span class="chip tone-<?= esc($day['tone']) ?>" style="padding:0.125rem 0.375rem">
          <span class="chip-glyph" aria-hidden="true"><?= $day['glyph'] ?></span><?= esc($court['open_slots']) ?>
        </span>
      </a>

      <?php foreach ($court['cells'] as $cell): ?>
        <?php
        $meta   = status_meta($cell['state']);
        $isOpen = $cell['state'] === 'open';
        $label  = sprintf('%s %s at %s, %s', $court['code'], $court['name'], $cell['time'], $meta['label']);
        ?>
        <?php if ($isOpen && $bookable): ?>
          <a class="cell is-bookable tone-<?= esc($meta['tone']) ?>"
             href="<?= base_url('booking?court=' . (int) $court['id'] . '&date=' . esc($board['date'], 'url') . '&start=' . esc($cell['time'], 'url')) ?>"
             title="<?= esc($label) ?> · <?= esc(peso($cell['rate'])) ?>/hr">
            <span aria-hidden="true"><?= $meta['glyph'] ?></span>
            <span class="visually-hidden"><?= esc($label) ?>. Book this slot.</span>
          </a>
        <?php else: ?>
          <div class="cell tone-<?= esc($meta['tone']) ?>" title="<?= esc($label) ?>">
            <span aria-hidden="true"><?= $meta['glyph'] ?></span>
            <span class="visually-hidden"><?= esc($label) ?></span>
          </div>
        <?php endif; ?>
      <?php endforeach; ?>
    <?php endforeach; ?>
  </div>
</div>

<?php
/* Every glyph the board can show has to be here, or the colour is doing the
   explaining on its own. Amber carries two meanings — a maintenance *cell* and
   a filling-up *court* — so both get a line rather than one standing in for
   the pair. Glyphs and labels come from status_meta(), same as the cells. */
?>
<div class="legend" style="margin-top:1rem">
  <span class="legend-item tone-open"><span class="legend-swatch" aria-hidden="true"></span>▲ Open</span>
  <span class="legend-item tone-booked"><span class="legend-swatch" aria-hidden="true"></span>■ Booked</span>
  <span class="legend-item tone-filling"><span class="legend-swatch" aria-hidden="true"></span>⚑ Maintenance</span>
  <span class="legend-item tone-filling"><span class="legend-swatch" aria-hidden="true"></span>◆ Court filling up</span>
  <span class="legend-item tone-closed"><span class="legend-swatch" aria-hidden="true"></span>· Past / closed</span>
  <?php if (has_peak()): ?>
  <span class="legend-item" style="margin-left:auto">Night rate <?= esc(night_window()) ?></span>
  <?php endif; ?>
</div>
