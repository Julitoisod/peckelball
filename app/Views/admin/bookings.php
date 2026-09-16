<?= $this->extend($layout) ?>

<?= $this->section('content') ?>
<div class="row-between" style="margin-bottom:1.25rem;gap:1rem">
  <form method="get" class="row" style="gap:0.75rem">
    <div class="field">
      <label for="date" class="visually-hidden">Date</label>
      <input class="input" type="date" id="date" name="date" value="<?= esc($date) ?>" onchange="this.form.submit()">
    </div>
    <div class="field">
      <label for="status" class="visually-hidden">Status</label>
      <select class="select" id="status" name="status" onchange="this.form.submit()">
        <option value="">All statuses</option>
        <?php foreach (['pending', 'confirmed', 'checked_in', 'completed', 'cancelled', 'no_show'] as $s): ?>
          <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= esc(booking_status_meta($s)['label']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <noscript><button class="btn" type="submit">Go</button></noscript>
  </form>

  <div class="row" style="gap:0.5rem">
    <a class="btn btn-ghost btn-sm" href="?date=<?= esc(date('Y-m-d', strtotime($date . ' -1 day')), 'url') ?>">← Prev</a>
    <a class="btn btn-ghost btn-sm" href="?date=<?= esc(business_date(), 'url') ?>">Today</a>
    <a class="btn btn-ghost btn-sm" href="?date=<?= esc(date('Y-m-d', strtotime($date . ' +1 day')), 'url') ?>">Next →</a>
  </div>
</div>

<div class="kpi-grid">
  <div class="kpi">
    <span class="t-metric"><?= number_format($totals['count']) ?></span>
    <span class="t-label">Bookings</span>
  </div>
  <div class="kpi kpi-accent">
    <span class="t-metric"><?= esc(peso($totals['revenue'])) ?></span>
    <span class="t-label">Booked value</span>
  </div>
  <div class="kpi kpi-warn">
    <span class="t-metric"><?= number_format($totals['unpaid']) ?></span>
    <span class="t-label">Still to collect</span>
  </div>
  <div class="kpi kpi-go">
    <span class="t-metric"><?= (int) $board['totals']['open'] ?></span>
    <span class="t-label">Slots still open</span>
  </div>
</div>

<div class="panel panel-flush field-scan" style="margin-bottom:1rem">
  <div class="panel-head">
    <h2 class="t-h3" style="margin:0"><?= esc(day_label($date)) ?> · <?= esc(date('l, d F Y', strtotime($date))) ?></h2>
    <span class="t-label"><?= (int) $board['totals']['booked'] ?> of <?= (int) $board['totals']['slots'] ?> slots taken</span>
  </div>
  <div style="padding:0.875rem">
    <?= partial('partials/board', ['board' => $board, 'bookable' => false]) ?>
  </div>
</div>

<div class="panel panel-flush">
  <div class="panel-head">
    <h2 class="t-h3" style="margin:0">Reservations</h2>
    <span class="t-label"><?= count($bookings) ?> rows</span>
  </div>

  <?php if ($bookings === []): ?>
    <div class="empty" style="border:0">
      <p class="t-body" style="margin:0"><strong>No bookings match that filter.</strong></p>
      <p class="t-small" style="margin:0">Try clearing the status filter or another day.</p>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th>Time</th><th>Court</th><th>Reference</th><th>Customer</th><th>Type</th>
            <th>Status</th><th>Payment</th><th class="num">Total</th><th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($bookings as $b): ?>
            <?php
            $s = booking_status_meta($b['status']);
            $active = ! in_array($b['status'], ['cancelled', 'completed', 'no_show'], true);
            ?>
            <tr>
              <td class="mono"><?= esc(clock($b['start_time'])) ?>–<?= esc(clock($b['end_time'])) ?><br><span class="t-label"><?= (int) $b['duration_minutes'] ?>m</span></td>
              <td class="mono"><?= esc($b['court_code']) ?><br><span class="t-label"><?= esc($b['court_name']) ?></span></td>
              <td class="mono"><?= esc($b['reference']) ?></td>
              <td>
                <?= esc($b['first_name'] ? $b['first_name'] . ' ' . $b['last_name'] : ($b['guest_name'] ?: '')) ?><br>
                <span class="t-label"><?= esc($b['phone'] ?: $b['guest_phone'] ?: '') ?></span>
              </td>
              <td class="t-label"><?= esc(ucfirst(str_replace('_', '-', $b['booking_type']))) ?><br><?= (int) $b['player_count'] ?>p</td>
              <td><span class="chip tone-<?= esc($s['tone']) ?>"><?= esc($s['label']) ?></span></td>
              <td>
                <span class="chip tone-<?= $b['payment_status'] === 'paid' ? 'open' : ($b['payment_status'] === 'refunded' ? 'closed' : 'filling') ?>">
                  <?= esc(ucfirst($b['payment_status'])) ?>
                </span><br>
                <span class="t-label"><?= esc(strtoupper($b['payment_method'])) ?></span>
              </td>
              <td class="num"><?= esc(peso($b['total_amount'])) ?></td>
              <td>
                <?php if ($active): ?>
                  <div class="row" style="gap:0.375rem;flex-wrap:nowrap">
                    <?php if ($b['status'] !== 'checked_in' && $auth->can('checkin.scan')): ?>
                      <form method="post" action="<?= base_url('admin/bookings/' . (int) $b['id'] . '/checkin') ?>">
                        <?= csrf_field() ?>
                        <button class="btn btn-go btn-sm" type="submit">In</button>
                      </form>
                    <?php endif; ?>
                    <?php if ($auth->can('bookings.manage')): ?>
                      <form method="post" action="<?= base_url('admin/bookings/' . (int) $b['id'] . '/cancel') ?>"
                            onsubmit="return confirm('Cancel <?= esc($b['reference']) ?>? The slot is released immediately.')">
                        <?= csrf_field() ?>
                        <input type="hidden" name="reason" value="Cancelled at the desk">
                        <button class="btn btn-ghost btn-sm" type="submit">Cancel</button>
                      </form>
                    <?php endif; ?>
                  </div>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
<?= $this->endSection() ?>
