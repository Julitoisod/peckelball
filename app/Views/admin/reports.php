<?= $this->extend($layout) ?>

<?= $this->section('content') ?>
<?php
/**
 * @var string $from
 * @var string $to
 * @var int    $days
 * @var float  $takings
 * @var list<array<string,mixed>> $byDay
 * @var list<array<string,mixed>> $byMethod
 * @var list<array<string,mixed>> $byPurpose
 * @var list<array<string,mixed>> $courts
 * @var list<array<string,mixed>> $players
 * @var list<array<string,mixed>> $coaches
 * @var array<string,mixed> $bookings
 * @var int $slots  slot starts the facility runs in a day
 */
$peak = 0.0;
foreach ($byDay as $d) {
    $peak = max($peak, (float) $d['total']);
}
// Occupancy against the slots actually run: courts times slots times days.
$courtCount = count($courts);
$capacity   = $courtCount * $slots * $days * (service('availability')->slotMinutes());
$booked     = (int) $bookings['minutes'];
$occupancy  = $capacity > 0 ? round($booked / $capacity * 100, 1) : 0.0;

// reports.sales, reports.bookings and reports.customers were granted to roles
// and gated nothing. Each now decides whether its own section renders, so a
// role can be given the booking numbers without the money.
$canSales     = $auth->can('reports.sales', 'reports.analytics');
$canBookings  = $auth->can('reports.bookings', 'reports.analytics');
$canCustomers = $auth->can('reports.customers', 'reports.analytics');
?>

<div class="panel" style="margin-bottom:1rem">
  <form method="get" action="<?= base_url('admin/reports') ?>" class="row" style="gap:0.75rem;align-items:flex-end;flex-wrap:wrap">
    <div class="field" style="flex:0 0 11rem">
      <label for="from">From</label>
      <input class="input" type="date" id="from" name="from" value="<?= esc($from) ?>">
    </div>
    <div class="field" style="flex:0 0 11rem">
      <label for="to">To</label>
      <input class="input" type="date" id="to" name="to" value="<?= esc($to) ?>">
    </div>
    <button class="btn" type="submit" style="flex:none">Run</button>
    <div class="row" style="gap:0.375rem;flex:none">
      <a class="btn btn-ghost btn-sm" href="<?= base_url('admin/reports?from=' . date('Y-m-d') . '&to=' . date('Y-m-d')) ?>">Today</a>
      <a class="btn btn-ghost btn-sm" href="<?= base_url('admin/reports?from=' . date('Y-m-d', strtotime('-6 days')) . '&to=' . date('Y-m-d')) ?>">7 days</a>
      <a class="btn btn-ghost btn-sm" href="<?= base_url('admin/reports?from=' . date('Y-m-01') . '&to=' . date('Y-m-d')) ?>">This month</a>
    </div>
    <span class="t-small t-mute" style="margin-left:auto">
      <?= $days ?> day<?= $days === 1 ? '' : 's' ?>. Revenue is settled payments, not booking totals.
    </span>
  </form>
</div>

<div class="kpi-grid" style="margin-bottom:1rem">
  <?php if ($canSales): ?>
  <div class="kpi kpi-accent">
    <span class="t-label">Collected</span>
    <span class="t-metric"><?= esc(peso($takings)) ?></span>
    <span class="t-label"><?= esc(peso($days > 0 ? $takings / $days : 0)) ?> a day</span>
  </div>
  <?php endif; ?>
  <div class="kpi">
    <span class="t-label">Sessions played</span>
    <span class="t-metric" style="font-size:1.5rem"><?= number_format((int) $bookings['total']) ?></span>
    <span class="t-label"><?= (int) $bookings['cancelled'] ?> cancelled</span>
  </div>
  <div class="kpi">
    <span class="t-label">Court hours booked</span>
    <span class="t-metric" style="font-size:1.5rem"><?= number_format($booked / 60, 1) ?></span>
    <span class="t-label"><?= $occupancy ?>% of the hours we opened</span>
  </div>
  <?php foreach ($canSales ? array_slice($byPurpose, 0, 3) : [] as $p): ?>
    <div class="kpi">
      <span class="t-label"><?= esc(ucfirst((string) $p['label'])) ?></span>
      <span class="t-metric" style="font-size:1.5rem"><?= esc(peso($p['total'])) ?></span>
      <span class="t-label"><?= (int) $p['n'] ?> payments</span>
    </div>
  <?php endforeach; ?>
</div>

<?php if ($canSales): ?>
<div class="panel" style="margin-bottom:1rem">
  <div class="row-between" style="margin-bottom:1rem">
    <h2 class="t-h3" style="margin:0">Collected by day</h2>
    <span class="t-label"><?= count($byDay) ?> trading day<?= count($byDay) === 1 ? '' : 's' ?></span>
  </div>

  <?php if ($byDay === []): ?>
    <div class="empty" style="border:0"><p style="margin:0">Nothing was collected in that range.</p></div>
  <?php else: ?>
    <?php /* The same `.bars` component the dashboard's peak-hours chart uses, so
             there is one bar chart in this admin rather than two that disagree.
             It is a column grid: one `.bar` per day with `--v` as a percentage. */ ?>
    <div class="bars" style="--cols:<?= count($byDay) ?>">
      <?php foreach ($byDay as $d): ?>
        <span class="bar" title="<?= esc(date('D d M', strtotime((string) $d['day']))) ?> · <?= esc(peso($d['total'])) ?> · <?= (int) $d['n'] ?> payments">
          <i style="--v:<?= $peak > 0 ? round((float) $d['total'] / $peak * 100) : 0 ?>"></i>
          <b><?= esc(date('d', strtotime((string) $d['day']))) ?></b>
        </span>
      <?php endforeach; ?>
    </div>
    <p class="t-small t-mute" style="margin:2rem 0 0">
      Best day was <?= esc(peso($peak)) ?>. Hover a column for the date and the count.
    </p>
  <?php endif; ?>
</div>
<?php endif; ?>

<div class="admin-cols admin-cols-even" style="margin-bottom:1rem">
  <?php if ($canBookings): ?>
  <div class="panel panel-flush">
    <div class="panel-head"><h2 class="t-h3" style="margin:0">Court use</h2><span class="t-label">paid only in the money column</span></div>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Court</th><th class="num">Bookings</th><th class="num">Hours</th><th class="num">Collected</th><th class="num">Share</th></tr></thead>
        <tbody>
          <?php $totalMinutes = max(1, array_sum(array_map(static fn ($c) => (int) $c['minutes'], $courts))); ?>
          <?php foreach ($courts as $c): ?>
            <tr>
              <td><span class="mono"><?= esc($c['code']) ?></span> <?= esc($c['name']) ?></td>
              <td class="num"><?= (int) $c['bookings'] ?></td>
              <td class="num"><?= number_format((int) $c['minutes'] / 60, 1) ?></td>
              <td class="num"><?= esc(peso($c['collected'])) ?></td>
              <td class="num"><?= round((int) $c['minutes'] / $totalMinutes * 100) ?>%</td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <?php endif; ?>

  <?php if ($canSales): ?>
  <div class="panel panel-flush">
    <div class="panel-head"><h2 class="t-h3" style="margin:0">How they paid</h2></div>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Method</th><th class="num">Payments</th><th class="num">Total</th><th class="num">Share</th></tr></thead>
        <tbody>
          <?php foreach ($byMethod as $m): ?>
            <tr>
              <td class="t-label"><?= esc(str_replace('_', ' ', (string) $m['label'])) ?></td>
              <td class="num"><?= (int) $m['n'] ?></td>
              <td class="num"><?= esc(peso($m['total'])) ?></td>
              <td class="num"><?= $takings > 0 ? round((float) $m['total'] / $takings * 100) : 0 ?>%</td>
            </tr>
          <?php endforeach; ?>
          <?php if ($byMethod === []): ?>
            <tr><td colspan="4" class="t-small t-mute">Nothing settled in that range.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>
</div>

<div class="admin-cols admin-cols-even">
  <?php if ($canCustomers): ?>
  <div class="panel panel-flush">
    <div class="panel-head"><h2 class="t-h3" style="margin:0">Who played most</h2><span class="t-label">top 10 by spend</span></div>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Player</th><th>Phone</th><th class="num">Bookings</th><th class="num">Spend</th></tr></thead>
        <tbody>
          <?php foreach ($players as $p): ?>
            <tr>
              <td><?= esc($p['first_name'] . ' ' . $p['last_name']) ?></td>
              <td class="mono t-small"><?= esc($p['phone'] ?: '-') ?></td>
              <td class="num"><?= (int) $p['bookings'] ?></td>
              <td class="num"><?= esc(peso($p['spend'])) ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if ($players === []): ?>
            <tr><td colspan="4" class="t-small t-mute">No account bookings in that range.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <?php endif; ?>

  <?php if ($canBookings): ?>
  <div class="panel panel-flush">
    <div class="panel-head"><h2 class="t-h3" style="margin:0">Coaching load</h2></div>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Coach</th><th class="num">Sessions</th><th class="num">Fees</th></tr></thead>
        <tbody>
          <?php foreach ($coaches as $c): ?>
            <tr>
              <td><?= esc($c['first_name'] . ' ' . $c['last_name']) ?></td>
              <td class="num"><?= (int) $c['sessions'] ?></td>
              <td class="num"><?= esc(peso($c['fees'])) ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if ($coaches === []): ?>
            <tr><td colspan="3" class="t-small t-mute">No coaching sessions in that range.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>
</div>
<?= $this->endSection() ?>
