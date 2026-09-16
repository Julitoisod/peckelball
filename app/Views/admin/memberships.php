<?= $this->extend($layout) ?>

<?= $this->section('content') ?>
<?php
/**
 * @var list<array<string,mixed>> $pending  requests waiting for money
 * @var list<array<string,mixed>> $rows     everything already decided
 * @var list<array<string,mixed>> $plans
 * @var list<string> $methods
 */
$who = static fn (array $r): string => $r['first_name'] . ' ' . $r['last_name'];
$tone = static fn (string $s): string => match ($s) {
    'active' => 'open', 'pending' => 'filling', 'cancelled' => 'booked', default => 'closed',
};
$active = array_values(array_filter($rows, static fn ($r) => $r['status'] === 'active'));
$income = array_sum(array_map(static fn ($r) => (float) $r['price'], $active));
?>

<div class="kpi-grid" style="margin-bottom:1rem">
  <div class="kpi <?= $pending === [] ? '' : 'kpi-warn' ?>">
    <span class="t-label">Waiting to be paid</span>
    <span class="t-metric"><?= count($pending) ?></span>
    <span class="t-label"><?= $pending === [] ? 'nothing outstanding' : 'sitting on the desk' ?></span>
  </div>
  <div class="kpi kpi-accent">
    <span class="t-label">Running now</span>
    <span class="t-metric"><?= count($active) ?></span>
    <span class="t-label"><?= esc(peso($income)) ?> on the books</span>
  </div>
  <?php foreach ($plans as $plan): ?>
    <?php $n = count(array_filter($active, static fn ($r) => (int) $r['plan_id'] === (int) $plan['id'])); ?>
    <div class="kpi">
      <span class="t-label"><?= esc($plan['name']) ?></span>
      <span class="t-metric" style="font-size:1.5rem"><?= $n ?></span>
      <span class="t-label"><?= esc(peso($plan['price'])) ?> / <?= esc($plan['billing_period']) ?></span>
    </div>
  <?php endforeach; ?>
</div>

<?php if ($pending !== []): ?>
  <div class="panel panel-flush" style="margin-bottom:1rem;border-color:var(--amber)">
    <div class="panel-head">
      <h2 class="t-h3" style="margin:0">Requests at the desk</h2>
      <span class="t-label" style="color:var(--amber)"><?= count($pending) ?> to settle</span>
    </div>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr><th>Reference</th><th>Player</th><th>Plan</th><th class="num">Price</th><th>Asked</th><th>Take payment and switch on</th></tr>
        </thead>
        <tbody>
          <?php foreach ($pending as $row): ?>
            <tr>
              <td class="mono t-small"><?= esc($row['reference']) ?></td>
              <td>
                <?= esc($who($row)) ?><br>
                <span class="t-small t-mute"><?= esc($row['email']) ?></span>
              </td>
              <td><?= esc($row['plan_name']) ?><br><span class="t-label"><?= (int) $row['court_credit_hours'] ?> credit hours</span></td>
              <td class="num"><?= esc(peso($row['price'])) ?></td>
              <td class="mono t-small"><?= esc(date('d M Y', strtotime((string) $row['created_at']))) ?></td>
              <td>
                <form method="post" action="<?= base_url('admin/memberships/' . (int) $row['id'] . '/activate') ?>" class="row" style="gap:0.375rem;flex-wrap:nowrap">
                  <?= csrf_field() ?>
                  <select class="select" name="method" style="min-height:36px;padding:0.25rem 1.75rem 0.25rem 0.5rem;font-size:0.8125rem">
                    <?php foreach ($methods as $m): ?>
                      <option value="<?= esc($m) ?>"><?= esc(ucfirst(str_replace('_', ' ', $m))) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <button class="btn btn-sm" type="submit">Activate</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>

<div class="panel panel-flush">
  <div class="panel-head">
    <h2 class="t-h3" style="margin:0">Memberships</h2>
    <form method="get" action="<?= base_url('admin/memberships') ?>" class="row" style="gap:0.5rem;align-items:center">
      <select class="select" name="status" style="min-height:36px;width:auto;padding-block:0.25rem">
        <option value="">Every state</option>
        <?php foreach (['pending', 'active', 'expired', 'cancelled'] as $s): ?>
          <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn btn-ghost btn-sm" type="submit">Filter</button>
    </form>
  </div>

  <?php if ($rows === []): ?>
    <div class="empty" style="border:0"><p style="margin:0">Nothing here yet.</p></div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr><th>Reference</th><th>Player</th><th>Plan</th><th>Term</th><th class="num">Credits</th><th>Paid</th><th>State</th><th></th></tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $row): ?>
            <tr<?= $row['status'] === 'active' ? '' : ' style="opacity:0.65"' ?>>
              <td class="mono t-small"><?= esc($row['reference']) ?></td>
              <td>
                <?= esc($who($row)) ?>
                <?php if ($row['phone']): ?><br><span class="mono t-small t-mute"><?= esc($row['phone']) ?></span><?php endif; ?>
              </td>
              <td><?= esc($row['plan_name']) ?></td>
              <td class="mono t-small" style="white-space:nowrap">
                <?= esc(date('d M Y', strtotime((string) $row['starts_on']))) ?><br>
                <span class="t-mute">to <?= esc(date('d M Y', strtotime((string) $row['ends_on']))) ?></span>
              </td>
              <td class="num">
                <?php if ($row['status'] === 'active'): ?>
                  <form method="post" action="<?= base_url('admin/memberships/' . (int) $row['id'] . '/credits') ?>" class="row" style="gap:0.25rem;flex-wrap:nowrap;justify-content:flex-end">
                    <?= csrf_field() ?>
                    <input class="input mono" type="number" name="credits" min="0" step="1" value="<?= (int) $row['credits_remaining'] ?>"
                           style="min-height:32px;width:4.5rem;padding:0.125rem 0.375rem;text-align:right">
                    <button class="btn btn-ghost btn-sm" type="submit">Set</button>
                  </form>
                <?php else: ?>
                  <?= (int) $row['credits_remaining'] ?>
                <?php endif; ?>
              </td>
              <td class="t-small">
                <?php if ($row['payment_reference']): ?>
                  <span class="mono"><?= esc($row['payment_reference']) ?></span><br>
                  <span class="t-label"><?= esc(str_replace('_', ' ', (string) $row['payment_method'])) ?></span>
                <?php else: ?>
                  <span class="t-mute">not paid</span>
                <?php endif; ?>
              </td>
              <td><span class="chip tone-<?= $tone($row['status']) ?>"><?= esc(ucfirst($row['status'])) ?></span></td>
              <td>
                <?php if ($row['status'] === 'active'): ?>
                  <form method="post" action="<?= base_url('admin/memberships/' . (int) $row['id'] . '/cancel') ?>"
                        onsubmit="return confirm('Cancel <?= esc($row['reference'], 'js') ?>? The payment stays on the ledger.')">
                    <?= csrf_field() ?>
                    <button class="btn btn-ghost btn-sm" type="submit">Cancel</button>
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
