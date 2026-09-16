<?= $this->extend($layout) ?>

<?= $this->section('content') ?>
<?php
/**
 * @var list<array<string,mixed>> $rows     the ledger for the chosen range
 * @var list<array<string,mixed>> $mix      settled totals by method
 * @var list<array<string,mixed>> $owing    bookings still to collect on
 * @var array<string,mixed>|null  $focus    booking arriving from the check-in desk
 */
$who = static fn (array $r): string => $r['first_name']
    ? $r['first_name'] . ' ' . $r['last_name']
    : ($r['guest_name'] ?: 'Guest');
$outstanding = array_sum(array_map(static fn ($b) => (float) $b['total_amount'], $owing));
?>

<div class="kpi-grid" style="margin-bottom:1rem">
  <div class="kpi kpi-accent">
    <span class="t-label">Settled <?= $from === $to ? esc(day_label($from)) : esc(date('d M', strtotime($from)) . ' to ' . date('d M', strtotime($to))) ?></span>
    <span class="t-metric"><?= esc(peso($takings)) ?></span>
    <span class="t-label"><?= count($rows) ?> movement<?= count($rows) === 1 ? '' : 's' ?></span>
  </div>
  <?php foreach ($mix as $m): ?>
    <div class="kpi">
      <span class="t-label"><?= esc(ucfirst(str_replace('_', ' ', $m['method']))) ?></span>
      <span class="t-metric" style="font-size:1.5rem"><?= esc(peso($m['total'])) ?></span>
      <span class="t-label"><?= (int) $m['n'] ?> taken</span>
    </div>
  <?php endforeach; ?>
  <div class="kpi kpi-warn">
    <span class="t-label">Still owed</span>
    <span class="t-metric" style="font-size:1.5rem"><?= esc(peso($outstanding)) ?></span>
    <span class="t-label"><?= count($owing) ?> booking<?= count($owing) === 1 ? '' : 's' ?></span>
  </div>
</div>

<?php if ($focus !== null): ?>
  <div class="panel" style="margin-bottom:1rem;border-color:var(--blue)">
    <div class="row-between" style="gap:1.5rem;flex-wrap:wrap;align-items:flex-end">
      <div>
        <span class="t-label" style="color:var(--blue-ink)">From the arrivals desk</span>
        <h2 class="t-h3" style="margin:0.25rem 0 0.25rem"><?= esc($who($focus)) ?> · <?= esc($focus['court_code']) ?></h2>
        <p class="mono t-small t-mute" style="margin:0">
          <?= esc($focus['reference']) ?> · <?= esc(day_label($focus['booking_date'])) ?> <?= esc(clock($focus['start_time'])) ?>
          · total <?= esc(peso($focus['total_amount'])) ?>
        </p>
      </div>

      <?php if ((float) $focus['due'] > 0): ?>
        <form method="post" action="<?= base_url('admin/payments/record') ?>" class="row" style="gap:0.75rem;align-items:flex-end">
          <?= csrf_field() ?>
          <input type="hidden" name="booking_id" value="<?= (int) $focus['id'] ?>">
          <div class="field" style="flex:0 0 9rem">
            <label for="focus_amount">Amount</label>
            <input class="input" type="number" id="focus_amount" name="amount" min="0.01" step="0.01"
                   value="<?= esc(number_format((float) $focus['due'], 2, '.', '')) ?>">
          </div>
          <div class="field" style="flex:0 0 10rem">
            <label for="focus_method">Method</label>
            <select class="select" id="focus_method" name="method">
              <?php foreach ($methods as $m): ?>
                <option value="<?= esc($m) ?>"><?= esc(ucfirst(str_replace('_', ' ', $m))) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <button class="btn" type="submit">Take <?= esc(peso($focus['due'])) ?></button>
        </form>
      <?php else: ?>
        <span class="chip tone-open" style="align-self:center">Settled in full</span>
      <?php endif; ?>
    </div>
  </div>
<?php endif; ?>

<div class="panel panel-flush" style="margin-bottom:1rem">
  <div class="panel-head">
    <h2 class="t-h3" style="margin:0">Still to collect</h2>
    <span class="t-label"><?= count($owing) ?> open</span>
  </div>

  <?php if ($owing === []): ?>
    <div class="empty" style="border:0"><p style="margin:0">Nothing outstanding. Every session up to today is paid.</p></div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr><th>Booking</th><th>Who</th><th>When</th><th class="num">Total</th><th>State</th><th>Take payment</th></tr>
        </thead>
        <tbody>
          <?php foreach ($owing as $b): ?>
            <tr>
              <td class="mono t-small"><?= esc($b['reference']) ?><br><span class="t-mute"><?= esc($b['court_code']) ?></span></td>
              <td><?= esc($who($b)) ?></td>
              <td class="mono t-small" style="white-space:nowrap">
                <?= esc(day_label($b['booking_date'])) ?><br>
                <span class="t-mute"><?= esc(clock($b['start_time'])) ?></span>
              </td>
              <td class="num"><?= esc(peso($b['total_amount'])) ?></td>
              <td>
                <span class="chip tone-<?= $b['payment_status'] === 'partial' ? 'filling' : 'booked' ?>">
                  <?= esc(ucfirst($b['payment_status'])) ?>
                </span>
              </td>
              <td>
                <form method="post" action="<?= base_url('admin/payments/record') ?>" class="row" style="gap:0.375rem;flex-wrap:nowrap">
                  <?= csrf_field() ?>
                  <input type="hidden" name="booking_id" value="<?= (int) $b['id'] ?>">
                  <select class="select" name="method" style="min-height:36px;padding:0.25rem 1.75rem 0.25rem 0.5rem;font-size:0.8125rem">
                    <?php foreach ($methods as $m): ?>
                      <option value="<?= esc($m) ?>"><?= esc(ucfirst(str_replace('_', ' ', $m))) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <button class="btn btn-sm" type="submit">Take</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<div class="panel panel-flush">
  <div class="panel-head">
    <h2 class="t-h3" style="margin:0">Ledger</h2>
    <form method="get" action="<?= base_url('admin/payments') ?>" class="row" style="gap:0.5rem;flex-wrap:wrap;align-items:center">
      <input class="input" type="date" name="from" value="<?= esc($from) ?>" style="min-height:36px;width:auto">
      <input class="input" type="date" name="to" value="<?= esc($to) ?>" style="min-height:36px;width:auto">
      <select class="select" name="method" style="min-height:36px;width:auto;padding-block:0.25rem">
        <option value="">Any method</option>
        <?php foreach ($methods as $m): ?>
          <option value="<?= esc($m) ?>" <?= $method === $m ? 'selected' : '' ?>><?= esc(ucfirst(str_replace('_', ' ', $m))) ?></option>
        <?php endforeach; ?>
      </select>
      <select class="select" name="status" style="min-height:36px;width:auto;padding-block:0.25rem">
        <option value="">Any status</option>
        <?php foreach ($statuses as $s): ?>
          <option value="<?= esc($s) ?>" <?= $status === $s ? 'selected' : '' ?>><?= esc(ucfirst($s)) ?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn btn-ghost btn-sm" type="submit">Filter</button>
    </form>
  </div>

  <?php if ($rows === []): ?>
    <div class="empty" style="border:0"><p style="margin:0">No movements in that range.</p></div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr><th>Receipt</th><th>Booking</th><th>Who</th><th>Method</th><th class="num">Amount</th><th>Status</th><th>Taken by</th><th></th></tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $p): ?>
            <tr<?= $p['status'] === 'refunded' ? ' style="opacity:0.6"' : '' ?>>
              <td class="mono t-small">
                <?= esc($p['reference']) ?><br>
                <span class="t-mute"><?= esc(date('d M, g:i A', strtotime((string) $p['created_at']))) ?></span>
              </td>
              <td class="mono t-small"><?= esc($p['booking_reference'] ?? '-') ?><br><span class="t-mute"><?= esc($p['court_code'] ?? '') ?></span></td>
              <td class="t-small"><?= esc($who($p)) ?></td>
              <td class="t-label"><?= esc(str_replace('_', ' ', $p['method'])) ?></td>
              <td class="num"><?= esc(peso($p['amount'], true)) ?></td>
              <td>
                <span class="chip tone-<?= match ($p['status']) { 'paid' => 'open', 'pending' => 'filling', 'refunded' => 'closed', default => 'booked' } ?>">
                  <?= esc(ucfirst($p['status'])) ?>
                </span>
              </td>
              <td class="t-small t-mute"><?= esc($p['staff_first'] ? $p['staff_first'] . ' ' . $p['staff_last'] : 'Online') ?></td>
              <td>
                <?php if ($p['status'] === 'paid'): ?>
                  <form method="post" action="<?= base_url('admin/payments/' . (int) $p['id'] . '/refund') ?>"
                        onsubmit="this.reason.value = prompt('Why is <?= esc($p['reference'], 'js') ?> being refunded?') || ''; return this.reason.value !== '';">
                    <?= csrf_field() ?>
                    <input type="hidden" name="reason" value="">
                    <button class="btn btn-ghost btn-sm" type="submit">Refund</button>
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
<?= $this->endSection() ?>
