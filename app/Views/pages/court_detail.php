<?= $this->extend($layout) ?>

<?= $this->section('content') ?>
<?php
$m    = $live !== null ? status_meta($live['day_state']) : status_meta('closed');
$img  = unsplash($court['image_path'], 1400, 720);
$days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
?>

<section class="field-deep" style="position:relative;isolation:isolate">
  <?php if ($img !== null): ?>
    <div style="position:absolute;inset:0;z-index:-2">
      <img src="<?= esc($img) ?>" alt="" width="1400" height="720" style="width:100%;height:100%;object-fit:cover">
    </div>
    <div style="position:absolute;inset:0;z-index:-1;background:linear-gradient(to top, var(--bg-deep) 8%, color-mix(in oklch, var(--bg-deep) 72%, transparent) 58%, color-mix(in oklch, var(--bg-deep) 55%, transparent) 100%)"></div>
  <?php endif; ?>

  <div class="container" style="padding-block:clamp(4rem,10vw,8rem) clamp(2rem,4vw,3rem)">
    <div class="row" style="gap:0.5rem;margin-bottom:1rem">
      <span class="chip tone-<?= esc($m['tone']) ?>"><span class="chip-glyph" aria-hidden="true"><?= $m['glyph'] ?></span><?= esc($m['label']) ?> today</span>
      <span class="chip tone-closed" style="color:var(--mute)"><?= esc(ucfirst($court['surface_type'])) ?></span>
      <span class="t-label"><?= esc($court['code']) ?></span>
    </div>
    <h1 class="t-display" style="font-size:clamp(2.25rem,5.5vw,4.25rem)"><?= esc($court['name']) ?></h1>
    <p class="t-lede" style="margin-top:1rem"><?= esc($court['description']) ?></p>

    <div class="row" style="gap:0.75rem;margin-top:1.75rem">
      <?php if ($live !== null && $live['next_open'] !== null): ?>
        <a class="btn btn-go btn-lg" href="<?= base_url('booking?court=' . (int) $court['id'] . '&start=' . esc($live['next_open'], 'url')) ?>">Book <?= esc($live['next_open']) ?> today</a>
      <?php else: ?>
        <a class="btn btn-lg" href="<?= base_url('booking?court=' . (int) $court['id'] . '&date=' . esc(date('Y-m-d', strtotime(business_date() . ' +1 day')), 'url')) ?>">Book tomorrow</a>
      <?php endif; ?>
      <a class="btn btn-lg btn-ghost" href="<?= base_url('courts') ?>">All courts</a>
    </div>
  </div>
</section>

<section class="section-tight">
  <div class="container">
    <div class="grid-auto">
      <div class="panel ticks readout">
        <span class="t-label"><?= has_peak() ? 'Off-peak rate' : 'Rate' ?></span>
        <span class="t-metric"><?= esc(peso($court['hourly_rate'])) ?></span>
        <span class="t-small t-mute"><?= has_peak() ? 'Per hour, before ' . esc(clock(setting('pricing.peak_start', '17:00'))) : 'Per hour, ' . esc(clock(setting('hours.open', '06:00'))) . '–' . esc(clock(setting('hours.close', '22:00'))) ?></span>
      </div>
      <?php if (has_peak()): ?>
      <div class="panel ticks readout readout-accent">
        <span class="t-label">Peak rate</span>
        <span class="t-metric"><?= esc(peso($court['peak_rate'])) ?></span>
        <span class="t-small t-mute"><?= esc(setting('pricing.peak_start', '17:00')) ?>–<?= esc(setting('pricing.peak_end', '21:00')) ?>, every day</span>
      </div>
      <?php endif; ?>
      <div class="panel ticks readout">
        <span class="t-label">Surface</span>
        <span class="t-metric" style="font-size:1.5rem"><?= esc($court['surface']) ?></span>
        <span class="t-small t-mute"><?= $court['has_lights'] ? 'Floodlit until close' : 'Daylight only' ?> · <?= (int) $court['capacity'] ?> players</span>
      </div>
      <div class="panel ticks readout">
        <span class="t-label">Free today</span>
        <span class="t-metric"><?= $live !== null ? (int) $live['open_slots'] : 0 ?></span>
        <span class="t-small t-mute"><?= (int) setting('booking.slot_minutes', 30) ?>-minute slots still open</span>
      </div>
    </div>
  </div>
</section>

<section class="section-tight field-bg field-scan">
  <div class="container-wide">
    <div class="section-head">
      <div>
        <h2 class="t-h2">Today on <?= esc($court['code']) ?></h2>
        <p class="t-small t-mute">Tap any open slot to book it directly.</p>
      </div>
      <a class="btn btn-ghost btn-sm" href="<?= base_url('availability') ?>">Compare all courts</a>
    </div>
    <?= partial('partials/board', ['board' => $board, 'bookable' => true]) ?>
  </div>
</section>

<section class="section">
  <div class="container">
    <div class="grid-auto-lg">
      <div class="panel">
        <h2 class="t-h3" style="margin-bottom:1rem">Opening hours</h2>
        <div class="table-wrap">
          <table class="table" style="min-width:0">
            <tbody>
              <?php foreach ($hours as $h): ?>
                <tr>
                  <td class="t-label" style="width:9rem"><?= esc($days[(int) $h['day_of_week']]) ?></td>
                  <td class="mono"><?= $h['is_closed'] ? 'Closed' : esc(clock($h['opens_at']) . '–' . clock($h['closes_at'])) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <div class="panel">
        <h2 class="t-h3" style="margin-bottom:1rem">Scheduled maintenance</h2>
        <?php if ($maintenance === []): ?>
          <div class="empty" style="padding:2rem 1rem">
            <p style="margin:0">Nothing booked in. This court is open every slot it is scheduled for.</p>
          </div>
        <?php else: ?>
          <div class="stack-2">
            <?php foreach ($maintenance as $job): ?>
              <div style="padding:0.75rem;background:var(--bg);border:1px solid var(--line);border-radius:var(--r)">
                <div class="row-between" style="gap:0.5rem">
                  <strong style="font-size:0.9375rem"><?= esc($job['title']) ?></strong>
                  <span class="chip tone-filling"><span class="chip-glyph" aria-hidden="true">⚑</span><?= esc(ucfirst($job['type'])) ?></span>
                </div>
                <p class="t-small t-mute" style="margin:0.375rem 0 0"><?= esc($job['description']) ?></p>
                <p class="t-label" style="margin:0.5rem 0 0">
                  <?= esc(date('D d M, g:i A', strtotime($job['scheduled_start']))) ?>–<?= esc(date('g:i A', strtotime($job['scheduled_end']))) ?>
                </p>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <div class="panel">
        <h2 class="t-h3" style="margin-bottom:1rem">Next up on this court</h2>
        <?php if ($upcoming === []): ?>
          <div class="empty" style="padding:2rem 1rem"><p style="margin:0">Nothing booked yet. First come.</p></div>
        <?php else: ?>
          <div class="table-wrap">
            <table class="table" style="min-width:0">
              <tbody>
                <?php foreach ($upcoming as $b): ?>
                  <tr>
                    <td class="t-label" style="width:8rem"><?= esc(day_label($b['booking_date'])) ?></td>
                    <td class="mono"><?= esc(clock($b['start_time'])) ?>–<?= esc(clock($b['end_time'])) ?></td>
                    <td class="t-small t-mute"><?= esc(ucfirst(str_replace('_', '-', $b['booking_type']))) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>
<?= $this->endSection() ?>
