<?= $this->extend($layout) ?>

<?= $this->section('content') ?>
<?php
/**
 * @var list<array<string,mixed>>                    $roles
 * @var array<string, list<array<string,mixed>>>     $grouped  permissions by module
 * @var array<int, array<int, bool>>                 $granted  [roleId][permissionId]
 * @var int $myLevel
 * @var int $myRole
 * @var int $editing
 */
$editable = static fn (array $role): bool => (int) $role['level'] < $myLevel;
$current  = null;
foreach ($roles as $r) {
    if ((int) $r['id'] === $editing) {
        $current = $r;
    }
}
$totalPerms = array_sum(array_map('count', $grouped));
?>

<div class="panel panel-flush" style="margin-bottom:1rem">
  <div class="panel-head">
    <h2 class="t-h3" style="margin:0">Roles</h2>
    <span class="t-label"><?= $totalPerms ?> permissions in the system</span>
  </div>
  <div class="table-wrap">
    <table class="table">
      <thead>
        <tr><th>Role</th><th class="num">Level</th><th class="num">People</th><th class="num">Permissions</th><th>Staff access</th><th></th></tr>
      </thead>
      <tbody>
        <?php foreach ($roles as $role): ?>
          <?php $held = count($granted[(int) $role['id']] ?? []); ?>
          <tr<?= $editable($role) ? '' : ' style="opacity:0.6"' ?>>
            <td>
              <?= esc($role['name']) ?>
              <?php if ((int) $role['id'] === $myRole): ?>
                <span class="chip tone-live" style="padding:0.125rem 0.375rem;margin-left:0.375rem">Yours</span>
              <?php endif; ?>
              <br><span class="mono t-small t-mute"><?= esc($role['slug']) ?></span>
            </td>
            <td class="num"><?= (int) $role['level'] ?></td>
            <td class="num"><?= (int) $role['holders'] ?></td>
            <td class="num"><?= $held ?> / <?= $totalPerms ?></td>
            <td>
              <span class="chip tone-<?= (int) $role['is_staff'] ? 'open' : 'closed' ?>">
                <?= (int) $role['is_staff'] ? 'Dashboard' : 'Players only' ?>
              </span>
            </td>
            <td>
              <?php if ($editable($role)): ?>
                <a class="btn btn-ghost btn-sm" href="<?= base_url('admin/roles?role=' . (int) $role['id']) ?>">Edit</a>
              <?php else: ?>
                <span class="t-small t-mute"><?= (int) $role['id'] === $myRole ? 'your own' : 'above you' ?></span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php if ($current !== null && $editable($current)): ?>
  <div class="panel">
    <div class="row-between" style="margin-bottom:1rem;gap:1rem;flex-wrap:wrap">
      <div>
        <h2 class="t-h3" style="margin:0"><?= esc($current['name']) ?></h2>
        <p class="t-small t-mute" style="margin:0.25rem 0 0">
          <?= (int) $current['holders'] ?> account<?= (int) $current['holders'] === 1 ? '' : 's' ?> hold this role.
          Changes reach anyone signed in on their next click.
        </p>
      </div>
      <a class="btn btn-ghost btn-sm" href="<?= base_url('admin/roles') ?>">Close</a>
    </div>

    <form method="post" action="<?= base_url('admin/roles/' . (int) $current['id'] . '/save') ?>" class="stack">
      <?= csrf_field() ?>

      <?php foreach ($grouped as $module => $perms): ?>
        <div>
          <div class="row-between" style="margin-bottom:0.5rem;padding-bottom:0.375rem;border-bottom:1px solid var(--line)">
            <span class="t-label" style="color:var(--fg)"><?= esc($module) ?></span>
            <span class="t-label"><?= count($perms) ?></span>
          </div>
          <div class="grid-auto" style="gap:0.5rem 1rem">
            <?php foreach ($perms as $p): ?>
              <?php $on = isset($granted[(int) $current['id']][(int) $p['id']]); ?>
              <label style="display:flex;align-items:flex-start;gap:0.5rem;cursor:pointer">
                <input type="checkbox" name="perm[]" value="<?= esc($p['slug']) ?>" <?= $on ? 'checked' : '' ?> style="margin-top:0.2rem">
                <span>
                  <span class="t-small" style="color:var(--fg)"><?= esc($p['name']) ?></span><br>
                  <span class="mono t-label" style="letter-spacing:0.04em"><?= esc($p['slug']) ?></span>
                </span>
              </label>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>

      <p class="field-hint" style="margin:0">
        "Manage roles" cannot be taken off the last role that holds it, or this screen locks itself.
      </p>

      <button class="btn" type="submit">Save <?= esc($current['name']) ?> permissions</button>
    </form>
  </div>
<?php elseif ($editing > 0): ?>
  <div class="panel">
    <div class="empty" style="border:0">
      <p style="margin:0">That role sits at or above your own level, so its permissions are not yours to change.</p>
    </div>
  </div>
<?php endif; ?>
<?= $this->endSection() ?>
