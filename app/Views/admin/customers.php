<?= $this->extend($layout) ?>

<?= $this->section('content') ?>
<?php
/**
 * @var list<array<string,mixed>> $rows
 * @var array<string,mixed>|null  $viewing  the profile opened by ?id=
 * @var list<string> $statuses
 * @var list<string> $skills
 */
$val = static function (string $field, string $fallback = '') use ($viewing): string {
    $old = old($field);

    return $old !== null ? (string) $old : (string) ($viewing[$field] ?? $fallback);
};
$tone = static fn (string $s): string => match ($s) {
    'active' => 'open', 'suspended' => 'booked', default => 'closed',
};
?>

<div class="panel" style="margin-bottom:1rem">
  <form method="get" action="<?= base_url('admin/customers') ?>" class="row" style="gap:0.75rem;align-items:flex-end">
    <div class="field" style="flex:1;min-width:0">
      <label for="q">Find a player</label>
      <input class="input" type="search" id="q" name="q" value="<?= esc($q) ?>"
             placeholder="Name, email or phone" autocomplete="off">
    </div>
    <div class="field" style="flex:0 0 11rem">
      <label for="status">Account</label>
      <select class="select" id="status" name="status">
        <option value="">Any state</option>
        <?php foreach ($statuses as $s): ?>
          <option value="<?= esc($s) ?>" <?= $status === $s ? 'selected' : '' ?>><?= esc(ucfirst($s)) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button class="btn" type="submit" style="flex:none">Search</button>
  </form>
</div>

<?php if ($viewing !== null): ?>
  <div class="admin-cols" style="margin-bottom:1rem">
    <div class="panel">
      <div class="row-between" style="margin-bottom:1rem;gap:1rem">
        <div>
          <h2 class="t-h3" style="margin:0"><?= esc($viewing['first_name'] . ' ' . $viewing['last_name']) ?></h2>
          <p class="t-small t-mute" style="margin:0.25rem 0 0">
            Member since <?= esc(date('d M Y', strtotime((string) $viewing['created_at']))) ?>
            <?php if ($viewing['last_login_at']): ?>
              · last signed in <?= esc(date('d M Y', strtotime((string) $viewing['last_login_at']))) ?>
            <?php endif; ?>
          </p>
        </div>
        <a class="btn btn-ghost btn-sm" href="<?= base_url('admin/customers') ?>">Close</a>
      </div>

      <div class="row" style="gap:1.5rem;flex-wrap:wrap;margin-bottom:1.25rem">
        <div class="readout">
          <span class="t-metric" style="font-size:1.5rem"><?= count($viewing['bookings']) ?></span>
          <span class="t-label">Recent bookings</span>
        </div>
        <div class="readout">
          <span class="t-metric" style="font-size:1.5rem;color:var(--<?= $viewing['owed'] > 0 ? 'amber' : 'green' ?>)"><?= esc(peso($viewing['owed'])) ?></span>
          <span class="t-label">Owed</span>
        </div>
        <?php if ($viewing['membership'] !== null): ?>
          <div class="readout">
            <span class="t-metric" style="font-size:1.5rem"><?= esc($viewing['membership']['plan_name']) ?></span>
            <span class="t-label"><?= esc(ucfirst($viewing['membership']['status'])) ?> to <?= esc(date('d M Y', strtotime((string) $viewing['membership']['ends_on']))) ?></span>
          </div>
        <?php endif; ?>
      </div>

      <?php if ($viewing['bookings'] === []): ?>
        <div class="empty" style="border:0"><p style="margin:0">No bookings on this account yet.</p></div>
      <?php else: ?>
        <div class="table-wrap">
          <table class="table">
            <thead><tr><th>Reference</th><th>Court</th><th>When</th><th class="num">Total</th><th>Payment</th><th>Status</th></tr></thead>
            <tbody>
              <?php foreach ($viewing['bookings'] as $b): ?>
                <?php $s = booking_status_meta($b['status']); ?>
                <tr>
                  <td class="mono t-small"><?= esc($b['reference']) ?></td>
                  <td class="mono"><?= esc($b['court_code']) ?></td>
                  <td class="mono t-small" style="white-space:nowrap"><?= esc(date('d M', strtotime((string) $b['booking_date']))) ?> <?= esc(clock($b['start_time'])) ?></td>
                  <td class="num"><?= esc(peso($b['total_amount'])) ?></td>
                  <td><span class="chip tone-<?= $b['payment_status'] === 'paid' ? 'open' : 'booked' ?>"><?= esc(ucfirst($b['payment_status'])) ?></span></td>
                  <td><span class="chip tone-<?= esc($s['tone']) ?>"><?= esc($s['label']) ?></span></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

    <div class="panel">
      <h2 class="t-h3" style="margin:0 0 1rem">Details</h2>
      <form method="post" action="<?= base_url('admin/customers/' . (int) $viewing['id'] . '/update') ?>" class="stack">
        <?= csrf_field() ?>
        <div class="row" style="gap:0.75rem">
          <div class="field" style="flex:1;min-width:0">
            <label for="first_name">First name</label>
            <input class="input" type="text" id="first_name" name="first_name" required maxlength="60" value="<?= esc($val('first_name')) ?>">
          </div>
          <div class="field" style="flex:1;min-width:0">
            <label for="last_name">Last name</label>
            <input class="input" type="text" id="last_name" name="last_name" required maxlength="60" value="<?= esc($val('last_name')) ?>">
          </div>
        </div>
        <div class="field">
          <label for="email">Email</label>
          <input class="input" type="email" id="email" name="email" required maxlength="160" value="<?= esc($val('email')) ?>">
        </div>
        <div class="field">
          <label for="phone">Phone</label>
          <input class="input mono" type="tel" id="phone" name="phone" maxlength="30" value="<?= esc($val('phone')) ?>">
        </div>
        <div class="row" style="gap:0.75rem">
          <div class="field" style="flex:1;min-width:0">
            <label for="skill_level">Skill</label>
            <select class="select" id="skill_level" name="skill_level">
              <?php foreach ($skills as $s): ?>
                <option value="<?= esc($s) ?>" <?= $val('skill_level') === $s ? 'selected' : '' ?>><?= esc(ucfirst($s)) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field" style="flex:1;min-width:0">
            <label for="cstatus">Account</label>
            <select class="select" id="cstatus" name="status">
              <?php foreach ($statuses as $s): ?>
                <option value="<?= esc($s) ?>" <?= $val('status') === $s ? 'selected' : '' ?>><?= esc(ucfirst($s)) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <p class="field-hint" style="margin:0">Suspended accounts cannot sign in or book. Passwords are not editable here.</p>
        <button class="btn" type="submit">Save details</button>
      </form>
    </div>
  </div>
<?php endif; ?>

<div class="panel panel-flush">
  <div class="panel-head">
    <h2 class="t-h3" style="margin:0">Players</h2>
    <span class="t-label"><?= count($rows) ?> shown<?= count($rows) === 100 ? ', narrow the search for more' : '' ?></span>
  </div>

  <?php if ($rows === []): ?>
    <div class="empty" style="border:0"><p style="margin:0">Nobody matches that.</p></div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr><th>Player</th><th>Contact</th><th>Skill</th><th class="num">Bookings</th><th class="num">Spend</th><th>Last played</th><th>Account</th><th></th></tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $row): ?>
            <tr>
              <td>
                <?= esc($row['first_name'] . ' ' . $row['last_name']) ?>
                <?php if ($row['membership_status'] === 'active'): ?>
                  <br><span class="t-label" style="color:var(--blue-ink)"><?= esc($row['plan_name']) ?> member</span>
                <?php endif; ?>
              </td>
              <td class="t-small">
                <?= esc($row['email']) ?>
                <?php if ($row['phone']): ?><br><span class="mono t-mute"><?= esc($row['phone']) ?></span><?php endif; ?>
              </td>
              <td class="t-label"><?= esc(ucfirst($row['skill_level'])) ?></td>
              <td class="num"><?= (int) $row['bookings'] ?></td>
              <td class="num"><?= esc(peso($row['spend'])) ?></td>
              <td class="mono t-small"><?= esc($row['last_played'] ? date('d M Y', strtotime((string) $row['last_played'])) : '-') ?></td>
              <td><span class="chip tone-<?= $tone($row['status']) ?>"><?= esc(ucfirst($row['status'])) ?></span></td>
              <td><a class="btn btn-ghost btn-sm" href="<?= base_url('admin/customers?id=' . (int) $row['id']) ?>">Open</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
<?= $this->endSection() ?>
