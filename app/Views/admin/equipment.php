<?= $this->extend($layout) ?>

<?= $this->section('content') ?>
<?php
/**
 * @var list<array<string, mixed>>  $rows
 * @var array<int, string>          $onLoan   quantity out on court today, by id
 * @var array<string, mixed>|null   $editing
 * @var list<string>                $categories
 * @var list<string>                $conditions
 */
$val = static function (string $field, string $fallback = '') use ($editing): string {
    $old = old($field);

    return $old !== null ? (string) $old : (string) ($editing[$field] ?? $fallback);
};
?>

<div class="admin-cols">
  <div class="panel panel-flush">
    <div class="panel-head">
      <h2 class="t-h3" style="margin:0">Catalogue</h2>
      <span class="t-label"><?= count(array_filter($rows, static fn ($r) => (int) $r['is_active'] === 1)) ?> rentable of <?= count($rows) ?></span>
    </div>

    <?php if ($rows === []): ?>
      <div class="empty" style="border:0"><p style="margin:0">Nothing in the rental catalogue yet.</p></div>
    <?php else: ?>
      <div class="table-wrap">
        <table class="table">
          <thead>
            <tr><th>Item</th><th>Category</th><th class="num">Rate</th><th class="num">Deposit</th><th class="num">Stock</th><th>Condition</th><th></th></tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $row): ?>
              <?php
              $out   = (int) ($onLoan[$row['id']] ?? 0);
              $free  = (int) $row['available_qty'];
              $total = (int) $row['total_qty'];
              ?>
              <tr<?= (int) $row['is_active'] ? '' : ' style="opacity:0.55"' ?>>
                <td>
                  <span class="mono" style="font-weight:600"><?= esc($row['code']) ?></span> <?= esc($row['name']) ?>
                  <?php if (! (int) $row['is_active']): ?>
                    <span class="chip tone-closed" style="padding:0.125rem 0.375rem">Off</span>
                  <?php endif; ?>
                  <?php if ($out > 0): ?>
                    <br><span class="t-label" style="color:var(--amber)"><?= $out ?> out on court today</span>
                  <?php endif; ?>
                </td>
                <td class="t-label"><?= esc(ucfirst($row['category'])) ?></td>
                <td class="num"><?= esc(peso($row['rental_rate_hourly'])) ?></td>
                <td class="num"><?= (float) $row['deposit'] > 0 ? esc(peso($row['deposit'])) : '-' ?></td>
                <td class="num" style="color:var(--<?= $free === 0 ? 'red' : ($free < $total ? 'amber' : 'green') ?>)">
                  <?= $free ?> / <?= $total ?>
                </td>
                <td>
                  <span class="chip tone-<?= match ($row['condition_grade']) { 'new', 'good' => 'open', 'fair' => 'filling', default => 'booked' } ?>">
                    <?= esc(ucfirst($row['condition_grade'])) ?>
                  </span>
                </td>
                <td>
                  <div class="row" style="gap:0.375rem;flex-wrap:nowrap">
                    <a class="btn btn-ghost btn-sm" href="<?= base_url('admin/equipment?edit=' . (int) $row['id']) ?>">Edit</a>
                    <form method="post" action="<?= base_url('admin/equipment/' . (int) $row['id'] . '/toggle') ?>">
                      <?= csrf_field() ?>
                      <button class="btn btn-ghost btn-sm" type="submit"><?= (int) $row['is_active'] ? 'Retire' : 'Restore' ?></button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <div class="panel">
    <div class="row-between" style="margin-bottom:1rem">
      <h2 class="t-h3" style="margin:0"><?= $editing !== null ? 'Edit item' : 'New item' ?></h2>
      <?php if ($editing !== null): ?>
        <a class="btn btn-ghost btn-sm" href="<?= base_url('admin/equipment') ?>">Cancel</a>
      <?php endif; ?>
    </div>

    <form method="post" action="<?= base_url('admin/equipment/save') ?>" class="stack">
      <?= csrf_field() ?>
      <?php if ($editing !== null): ?>
        <input type="hidden" name="id" value="<?= (int) $editing['id'] ?>">
      <?php endif; ?>

      <div class="row" style="gap:0.75rem">
        <div class="field" style="flex:0 0 8rem">
          <label for="code">Code</label>
          <input class="input mono" type="text" id="code" name="code" required maxlength="20"
                 style="text-transform:uppercase" value="<?= esc($val('code')) ?>">
        </div>
        <div class="field" style="flex:1;min-width:0">
          <label for="name">Name</label>
          <input class="input" type="text" id="name" name="name" required maxlength="120" value="<?= esc($val('name')) ?>">
        </div>
      </div>

      <div class="field">
        <label for="description">Description</label>
        <input class="input" type="text" id="description" name="description" maxlength="500" value="<?= esc($val('description')) ?>">
      </div>

      <div class="row" style="gap:0.75rem">
        <div class="field" style="flex:1;min-width:0">
          <label for="category">Category</label>
          <select class="select" id="category" name="category">
            <?php foreach ($categories as $c): ?>
              <option value="<?= esc($c) ?>" <?= $val('category', 'paddle') === $c ? 'selected' : '' ?>><?= esc(ucfirst($c)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field" style="flex:1;min-width:0">
          <label for="condition_grade">Condition</label>
          <select class="select" id="condition_grade" name="condition_grade">
            <?php foreach ($conditions as $c): ?>
              <option value="<?= esc($c) ?>" <?= $val('condition_grade', 'good') === $c ? 'selected' : '' ?>><?= esc(ucfirst($c)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="row" style="gap:0.75rem">
        <div class="field" style="flex:1;min-width:0">
          <label for="rental_rate_hourly">Rate per hour</label>
          <input class="input" type="number" id="rental_rate_hourly" name="rental_rate_hourly" required min="0" step="0.01"
                 value="<?= esc($val('rental_rate_hourly', '0')) ?>">
        </div>
        <div class="field" style="flex:1;min-width:0">
          <label for="deposit">Deposit</label>
          <input class="input" type="number" id="deposit" name="deposit" min="0" step="0.01" value="<?= esc($val('deposit', '0')) ?>">
        </div>
      </div>

      <div class="row" style="gap:0.75rem">
        <div class="field" style="flex:1;min-width:0">
          <label for="total_qty">Total owned</label>
          <input class="input" type="number" id="total_qty" name="total_qty" required min="0" step="1" value="<?= esc($val('total_qty', '0')) ?>">
        </div>
        <div class="field" style="flex:1;min-width:0">
          <label for="available_qty">Available</label>
          <input class="input" type="number" id="available_qty" name="available_qty" required min="0" step="1" value="<?= esc($val('available_qty', '0')) ?>">
          <span class="field-hint">Never more than the total.</span>
        </div>
      </div>

      <label style="display:flex;align-items:center;gap:0.625rem">
        <input type="checkbox" name="is_active" value="1" <?= ($editing === null && old('code') === null) || $val('is_active') === '1' ? 'checked' : '' ?>>
        <span class="t-small">Offer it on the booking form</span>
      </label>

      <button class="btn" type="submit"><?= $editing !== null ? 'Save changes' : 'Add item' ?></button>
    </form>
  </div>
</div>
<?= $this->endSection() ?>
