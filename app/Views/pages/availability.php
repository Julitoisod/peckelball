<?= $this->extend($layout) ?>

<?= $this->section('content') ?>
<?= partial('partials/page_head', [
    'heading' => 'Live availability',
    'lede'    => 'The same board the front desk is looking at, refreshed every minute. Tap any open slot to take it.',
    'kicker'  => '<span class="chip chip-live tone-live"><span class="dot dot-pulse"></span>Live</span>'
        . '<span class="t-label">' . esc($board['totals']['open']) . ' of ' . esc($board['totals']['slots']) . ' slots open · ' . esc(day_label($date)) . '</span>',
    'actions' => '<a class="btn" href="' . base_url('booking?date=' . esc($date, 'url')) . '">Book a court</a>',
]) ?>

<section class="section-tight">
  <div class="container-wide">
    <!-- Day picker. Real links, so it works with JS off and is shareable. -->
    <div class="board" style="margin-bottom:1.5rem;padding-bottom:0.5rem">
      <div class="row" style="flex-wrap:nowrap;gap:0.375rem;min-width:max-content">
        <?php foreach ($days as $day): ?>
          <?php $active = $day['date'] === $date; ?>
          <a class="tile-face" href="<?= base_url('availability?date=' . esc($day['date'], 'url')) ?>"
             style="text-decoration:none;min-width:5.25rem;text-align:center;<?= $active ? 'background:color-mix(in oklch, var(--blue) 22%, var(--surface));border-color:var(--blue)' : '' ?>"
             <?= $active ? 'aria-current="page"' : '' ?>>
            <b><?= esc($day['label']) ?></b>
            <span><?= esc(date('d M', strtotime($day['date']))) ?></span>
          </a>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="panel panel-flush field-scan">
      <div class="panel-head">
        <div class="row" style="gap:0.75rem">
          <h2 class="t-h3" style="margin:0"><?= esc(day_label($date)) ?></h2>
          <span class="t-label"><?= esc(date('l, d F Y', strtotime($date))) ?></span>
        </div>
        <span class="t-label mono" data-clock><?= date('g:i A') ?></span>
      </div>

      <div style="padding:clamp(0.75rem,1.5vw,1.25rem)">
        <?= partial('partials/board', ['board' => $board, 'bookable' => true, 'live' => true]) ?>
      </div>
    </div>

    <div class="grid-auto" style="margin-top:1.5rem">
      <?php foreach ($board['courts'] as $court): ?>
        <?php $m = status_meta($court['day_state']); ?>
        <div class="panel">
          <div class="row-between" style="margin-bottom:0.75rem">
            <div>
              <div class="mono" style="font-weight:600"><?= esc($court['code']) ?></div>
              <div class="t-small t-mute"><?= esc($court['name']) ?></div>
            </div>
            <span class="chip tone-<?= esc($m['tone']) ?>">
              <span class="chip-glyph" aria-hidden="true"><?= $m['glyph'] ?></span><?= esc($m['label']) ?>
            </span>
          </div>
          <div class="row-between">
            <span class="t-small t-mute"><?= (int) $court['open_slots'] ?> slots free</span>
            <?php if ($court['next_open'] !== null): ?>
              <a class="btn btn-sm" href="<?= base_url('booking?court=' . (int) $court['id'] . '&date=' . esc($date, 'url') . '&start=' . esc($court['next_open'], 'url')) ?>">
                Take <?= esc(clock($court['next_open'])) ?>
              </a>
            <?php else: ?>
              <span class="t-label">Full</span>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?= $this->endSection() ?>
