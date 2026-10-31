<?= $this->extend($layout) ?>

<?= $this->section('content') ?>
<?php
$peakMap = array_column($peak, 'n', 'hr');
$peakMax = $peakMap === [] ? 1 : max($peakMap);
// Hours in playing order, wrapping past midnight (16, 17 … 23, 0 … 5).
$open    = (int) substr((string) setting('hours.open', '06:00'), 0, 2);
$close   = (int) substr((string) setting('hours.close', '22:00'), 0, 2);
$hours   = [];
for ($i = 0, $n = (($close - $open) % 24 + 24) % 24 ?: 24; $i < $n; $i++) {
    $hours[] = ($open + $i) % 24;
}
$peakS   = (int) substr((string) setting('pricing.peak_start', '17:00'), 0, 2);
$peakLen = (((int) substr((string) setting('pricing.peak_end', '21:00'), 0, 2) - $peakS) % 24 + 24) % 24;
?>

<div class="kpi-grid">
  <div class="kpi">
    <span class="t-metric"><?= number_format($metrics['today_bookings']) ?></span>
    <span class="t-label">Bookings today</span>
  </div>
  <div class="kpi kpi-go">
    <span class="t-metric"><?= number_format($metrics['on_court']) ?></span>
    <span class="t-label">On court now</span>
  </div>
  <?php /* Every staff role holds dashboard.view because everyone needs a landing
           page, but the till is not everyone's business: the four coaches and
           the maintenance staff were being shown the facility's takings. */ ?>
  <?php if ($auth->can('payments.view', 'reports.sales')): ?>
    <div class="kpi kpi-accent">
      <span class="t-metric"><?= esc(peso($metrics['revenue_today'])) ?></span>
      <span class="t-label">Taken today</span>
    </div>
    <div class="kpi">
      <span class="t-metric"><?= esc(peso($metrics['revenue_month'])) ?></span>
      <span class="t-label">This month</span>
    </div>
  <?php endif; ?>
  <div class="kpi">
    <span class="t-metric"><?= (int) $metrics['utilisation'] ?>%</span>
    <span class="t-label">Court utilisation</span>
  </div>
  <div class="kpi kpi-warn">
    <span class="t-metric"><?= number_format($metrics['awaiting']) ?></span>
    <span class="t-label">Unpaid, upcoming</span>
  </div>
  <div class="kpi">
    <span class="t-metric"><?= number_format($metrics['members']) ?></span>
    <span class="t-label">Active members</span>
  </div>
  <div class="kpi <?= $metrics['low_stock'] > 0 ? 'kpi-warn' : '' ?>">
    <span class="t-metric"><?= number_format($metrics['low_stock']) ?></span>
    <span class="t-label">Products to reorder</span>
  </div>
</div>

<div class="panel panel-flush field-scan" style="margin-bottom:1rem">
  <div class="panel-head">
    <div class="row" style="gap:0.75rem">
      <h2 class="t-h3" style="margin:0">Today's board</h2>
      <span class="t-label"><?= esc(date('l, d F Y', strtotime($today))) ?></span>
    </div>
    <a class="btn btn-ghost btn-sm" href="<?= base_url('admin/bookings') ?>">Full calendar</a>
  </div>
  <div style="padding:0.875rem">
    <?= partial('partials/board', ['board' => $board, 'bookable' => false, 'live' => true]) ?>
  </div>
</div>

<div class="admin-cols">
  <div class="stack">
    <?php /* Names, phone numbers and what each person paid. That is the booking
             calendar's content, so it answers to the same permission. */ ?>
    <?php if ($auth->can('bookings.view')): ?>
    <div class="panel panel-flush">
      <div class="panel-head">
        <h2 class="t-h3" style="margin:0">Arrivals</h2>
        <span class="t-label"><?= count($arrivals) ?> today</span>
      </div>

      <?php if ($arrivals === []): ?>
        <div class="empty" style="border:0"><p style="margin:0">Nothing booked today.</p></div>
      <?php else: ?>
        <div class="table-wrap">
          <table class="table">
            <thead>
              <tr><th>Time</th><th>Court</th><th>Who</th><th>Type</th><th>Status</th><th class="num">Total</th><th></th></tr>
            </thead>
            <tbody>
              <?php foreach ($arrivals as $b): ?>
                <?php $s = booking_status_meta($b['status']); ?>
                <tr>
                  <td class="mono"><?= esc(clock($b['start_time'])) ?><br><span class="t-label"><?= esc(clock($b['end_time'])) ?></span></td>
                  <td class="mono"><?= esc($b['court_code']) ?></td>
                  <td>
                    <?= esc($b['first_name'] ? $b['first_name'] . ' ' . $b['last_name'] : ($b['guest_name'] ?: 'Walk-in')) ?><br>
                    <span class="t-label mono"><?= esc($b['reference']) ?></span>
                  </td>
                  <td class="t-label"><?= esc(ucfirst(str_replace('_', '-', $b['booking_type']))) ?></td>
                  <td>
                    <span class="chip tone-<?= esc($s['tone']) ?>"><?= esc($s['label']) ?></span>
                    <?php if ($b['payment_status'] === 'unpaid'): ?>
                      <span class="chip tone-filling" style="margin-top:0.25rem">Unpaid</span>
                    <?php endif; ?>
                  </td>
                  <td class="num"><?= esc(peso($b['total_amount'])) ?></td>
                  <td>
                    <?php if (! in_array($b['status'], ['checked_in', 'pending'], true) && $auth->can('checkin.scan')): ?>
                      <form method="post" action="<?= base_url('admin/bookings/' . (int) $b['id'] . '/checkin') ?>">
                        <?= csrf_field() ?>
                        <button class="btn btn-go btn-sm" type="submit">Check in</button>
                      </form>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="panel">
      <div class="row-between" style="margin-bottom:0.5rem">
        <h2 class="t-h3" style="margin:0">Peak hours</h2>
        <span class="t-label">Slots sold, last 30 days</span>
      </div>
      <div class="bars" style="--cols:<?= count($hours) ?>">
        <?php foreach ($hours as $h): ?>
          <?php
          $v = (int) ($peakMap[$h] ?? 0);
          $pct = $peakMax === 0 ? 0 : round($v / $peakMax * 100);
          ?>
          <span class="bar<?= (($h - $peakS) % 24 + 24) % 24 < $peakLen ? ' is-peak' : '' ?>" title="<?= sprintf('%02d:00', $h) ?> · <?= $v ?> slots">
            <i style="--v:<?= $pct ?>"></i>
            <b><?= sprintf('%02d', $h) ?></b>
          </span>
        <?php endforeach; ?>
      </div>
      <p class="t-small t-mute" style="margin:2rem 0 0">
        Busiest hour is <?= $peakMap === [] ? 'n/a' : sprintf('%02d:00', (int) array_search($peakMax, $peakMap, true)) ?>
        at <?= (int) $peakMax ?> slots.<?= has_peak() ? ' The highlighted band is the peak-rate window.' : '' ?>
      </p>
    </div>
  </div>

  <div class="stack">
    <div class="panel panel-flush">
      <div class="panel-head"><h2 class="t-h3" style="margin:0">Maintenance due</h2></div>
      <?php if ($maintenance === []): ?>
        <div class="empty" style="border:0"><p style="margin:0">Nothing scheduled.</p></div>
      <?php else: ?>
        <div>
          <?php foreach ($maintenance as $m): ?>
            <div style="padding:0.75rem 1.125rem;border-bottom:1px solid color-mix(in oklch, var(--line) 55%, transparent)">
              <div class="row-between" style="gap:0.5rem;margin-bottom:0.25rem">
                <span class="mono" style="font-size:0.8125rem;font-weight:600"><?= esc($m['court_code']) ?></span>
                <span class="chip tone-filling"><span class="chip-glyph" aria-hidden="true">⚑</span><?= esc(ucfirst($m['type'])) ?></span>
              </div>
              <strong style="font-size:0.875rem;display:block"><?= esc($m['title']) ?></strong>
              <span class="t-label"><?= esc(date('D d M, g:i A', strtotime($m['scheduled_start']))) ?> · <?= esc(peso($m['cost'])) ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <div class="panel panel-flush">
      <div class="panel-head">
        <h2 class="t-h3" style="margin:0">Recent activity</h2>
        <?php if ($auth->can('audit.view')): ?>
          <a class="t-label" href="<?= base_url('admin/audit') ?>" style="color:var(--blue-ink)">Full log →</a>
        <?php endif; ?>
      </div>
      <?php if ($recent === []): ?>
        <div class="empty" style="border:0"><p style="margin:0">Nothing logged yet.</p></div>
      <?php else: ?>
        <div>
          <?php foreach ($recent as $log): ?>
            <div style="padding:0.625rem 1.125rem;border-bottom:1px solid color-mix(in oklch, var(--line) 55%, transparent)">
              <div class="row-between" style="gap:0.5rem">
                <span class="mono" style="font-size:0.75rem;color:var(--blue-ink)"><?= esc($log['action']) ?></span>
                <span class="t-label"><?= esc(date('g:i A', strtotime($log['created_at']))) ?></span>
              </div>
              <span class="t-small t-mute" style="display:block">
                <?= esc($log['summary'] ?? '') ?>
                <?php if ($log['first_name']): ?> · <?= esc($log['first_name']) ?><?php endif; ?>
              </span>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
<?= $this->endSection() ?>
