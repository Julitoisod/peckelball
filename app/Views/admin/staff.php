<?= $this->extend($layout) ?>

<?= $this->section('content') ?>
<?php
/**
 * @var list<array<string,mixed>> $rows
 * @var list<array<string,mixed>> $roles     only those at or below your level
 * @var int $myId
 * @var int $myLevel
 * @var list<string> $statuses
 */
$tone = static fn (string $s): string => match ($s) {
    'active' => 'open', 'suspended' => 'booked', default => 'closed',
};
?>

<div class="panel panel-flush" style="margin-bottom:1rem">
  <div class="panel-head">
    <h2 class="t-h3" style="margin:0">Roster</h2>
    <span class="t-label"><?= count(array_filter($rows, static fn ($r) => $r['status'] === 'active')) ?> active of <?= count($rows) ?></span>
  </div>

  <div class="table-wrap">
    <table class="table">
      <thead>
        <tr><th>Name</th><th>Contact</th><th>Role and access</th><th>Last signed in</th><th>State</th><th>Password</th></tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $row): ?>
          <?php
          $isMe     = (int) $row['id'] === $myId;
          $tooHigh  = (int) $row['role_level'] >= $myLevel;
          $readOnly = $isMe || $tooHigh;
          ?>
          <tr<?= $row['status'] === 'active' ? '' : ' style="opacity:0.6"' ?>>
            <td>
              <?= esc($row['first_name'] . ' ' . $row['last_name']) ?>
              <?php if ($isMe): ?><span class="chip tone-live" style="padding:0.125rem 0.375rem;margin-left:0.375rem">You</span><?php endif; ?>
              <br><span class="t-label"><?= esc($row['role_name']) ?></span>
            </td>
            <td class="t-small">
              <?= esc($row['email']) ?>
              <?php if ($row['phone']): ?><br><span class="mono t-mute"><?= esc($row['phone']) ?></span><?php endif; ?>
            </td>
            <td>
              <?php if ($readOnly): ?>
                <span class="t-small t-mute">
                  <?= $isMe ? 'Your own account. Someone else has to change it.' : 'At or above your level, so not yours to change.' ?>
                </span>
              <?php else: ?>
                <form method="post" action="<?= base_url('admin/staff/' . (int) $row['id'] . '/update') ?>" class="row" style="gap:0.375rem;flex-wrap:nowrap">
                  <?= csrf_field() ?>
                  <select class="select" name="role_id" style="min-height:36px;padding:0.25rem 1.75rem 0.25rem 0.5rem;font-size:0.8125rem">
                    <?php foreach ($roles as $r): ?>
                      <option value="<?= (int) $r['id'] ?>" <?= $r['name'] === $row['role_name'] ? 'selected' : '' ?>><?= esc($r['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <select class="select" name="status" style="min-height:36px;padding:0.25rem 1.75rem 0.25rem 0.5rem;font-size:0.8125rem">
                    <?php foreach ($statuses as $s): ?>
                      <option value="<?= esc($s) ?>" <?= $row['status'] === $s ? 'selected' : '' ?>><?= esc(ucfirst($s)) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <button class="btn btn-sm" type="submit">Save</button>
                </form>
              <?php endif; ?>
            </td>
            <td class="mono t-small"><?= esc($row['last_login_at'] ? date('d M Y', strtotime((string) $row['last_login_at'])) : 'never') ?></td>
            <td><span class="chip tone-<?= $tone($row['status']) ?>"><?= esc(ucfirst($row['status'])) ?></span></td>
            <td>
              <?php if (! $readOnly): ?>
                <form method="post" action="<?= base_url('admin/staff/' . (int) $row['id'] . '/password') ?>"
                      onsubmit="this.password.value = prompt('New password for <?= esc($row['first_name'], 'js') ?>, at least 10 characters:') || ''; return this.password.value.length >= 10;">
                  <?= csrf_field() ?>
                  <input type="hidden" name="password" value="">
                  <button class="btn btn-ghost btn-sm" type="submit">Reset</button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="panel" style="max-width:44rem">
  <h2 class="t-h3" style="margin:0 0 1rem">Add someone to the team</h2>

  <form method="post" action="<?= base_url('admin/staff/create') ?>" class="stack">
    <?= csrf_field() ?>
    <div class="row" style="gap:0.75rem">
      <div class="field" style="flex:1;min-width:0">
        <label for="first_name">First name</label>
        <input class="input" type="text" id="first_name" name="first_name" required maxlength="60" value="<?= esc(old('first_name') ?? '') ?>">
      </div>
      <div class="field" style="flex:1;min-width:0">
        <label for="last_name">Last name</label>
        <input class="input" type="text" id="last_name" name="last_name" required maxlength="60" value="<?= esc(old('last_name') ?? '') ?>">
      </div>
    </div>

    <div class="row" style="gap:0.75rem">
      <div class="field" style="flex:1;min-width:0">
        <label for="email">Email</label>
        <input class="input" type="email" id="email" name="email" required maxlength="160" value="<?= esc(old('email') ?? '') ?>">
      </div>
      <div class="field" style="flex:0 0 12rem">
        <label for="phone">Phone</label>
        <input class="input mono" type="tel" id="phone" name="phone" maxlength="30" value="<?= esc(old('phone') ?? '') ?>">
      </div>
    </div>

    <div class="row" style="gap:0.75rem">
      <div class="field" style="flex:1;min-width:0">
        <label for="role_id">Role</label>
        <select class="select" id="role_id" name="role_id" required>
          <?php foreach ($roles as $r): ?>
            <option value="<?= (int) $r['id'] ?>" <?= (int) old('role_id') === (int) $r['id'] ? 'selected' : '' ?>><?= esc($r['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <span class="field-hint">Only roles at or below your own are listed.</span>
      </div>
      <div class="field" style="flex:1;min-width:0">
        <label for="password">First password</label>
        <input class="input" type="text" id="password" name="password" required minlength="10" maxlength="200" autocomplete="off">
        <span class="field-hint">Shown in the clear so you can read it out. At least 10 characters.</span>
      </div>
    </div>

    <button class="btn" type="submit">Create the account</button>
  </form>
</div>
<?= $this->endSection() ?>
