<?= $this->extend($layout) ?>

<?= $this->section('content') ?>
<?php
/**
 * @var list<array<string, mixed>> $rows
 * @var array<string, mixed>|null  $editing
 */
$val = static function (string $field, string $fallback = '') use ($editing): string {
    $old = old($field);

    return $old !== null ? (string) $old : (string) ($editing[$field] ?? $fallback);
};
$today = date('Y-m-d');
?>

<div class="admin-cols">
  <div class="panel panel-flush">
    <div class="panel-head">
      <h2 class="t-h3" style="margin:0">Codes</h2>
      <span class="t-label"><?= count(array_filter($rows, static fn ($r) => (int) $r['is_active'] === 1)) ?> live of <?= count($rows) ?></span>
    </div>

    <?php if ($rows === []): ?>
      <div class="empty" style="border:0"><p style="margin:0">No promo codes yet.</p></div>
    <?php else: ?>
      <div class="table-wrap">
        <table class="table">
          <thead>
            <tr><th>Code</th><th>Discount</th><th>Window</th><th class="num">Used</th><th>Status</th><th></th></tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $row): ?>
              <?php
              $spent   = (int) $row['max_uses'] > 0 && (int) $row['used_count'] >= (int) $row['max_uses'];
              $expired = $row['ends_on'] !== null && $row['ends_on'] < $today;
              $waiting = $row['starts_on'] !== null && $row['starts_on'] > $today;
              ?>
              <tr>
                <td class="mono" style="font-weight:600"><?= esc($row['code']) ?>
                  <?php if ($row['description']): ?>
                    <br><span class="t-small t-mute" style="font-family:var(--font-sans)"><?= esc(mb_strimwidth($row['description'], 0, 60, '…')) ?></span>
                  <?php endif; ?>
                </td>
                <td class="mono t-small">
                  <?= $row['discount_type'] === 'percent'
                      ? esc(rtrim(rtrim(number_format((float) $row['discount_value'], 2), '0'), '.')) . '%'
                      : esc(peso($row['discount_value'])) ?>
                  <?php if ((float) $row['min_amount'] > 0): ?>
                    <br><span class="t-mute">min <?= esc(peso($row['min_amount'])) ?></span>
                  <?php endif; ?>
                </td>
                <td class="mono t-small">
                  <?= esc($row['starts_on'] === null ? 'any time' : date('d M Y', strtotime($row['starts_on']))) ?><br>
                  <span class="t-mute"><?= esc($row['ends_on'] === null ? 'no end' : 'to ' . date('d M Y', strtotime($row['ends_on']))) ?></span>
                </td>
                <td class="num"><?= (int) $row['used_count'] ?><?= (int) $row['max_uses'] > 0 ? ' / ' . (int) $row['max_uses'] : '' ?></td>
                <td>
                  <?php
                  [$tone, $label] = match (true) {
                      ! (int) $row['is_active'] => ['closed', 'Off'],
                      $spent                    => ['booked', 'Used up'],
                      $expired                  => ['closed', 'Expired'],
                      $waiting                  => ['filling', 'Scheduled'],
                      default                   => ['open', 'Live'],
                  };
                  ?>
                  <span class="chip tone-<?= $tone ?>"><?= esc($label) ?></span>
                </td>
                <td>
                  <div class="row" style="gap:0.375rem;flex-wrap:nowrap">
                    <a class="btn btn-ghost btn-sm" href="<?= base_url('admin/promos?edit=' . (int) $row['id']) ?>">Edit</a>
                    <form method="post" action="<?= base_url('admin/promos/' . (int) $row['id'] . '/toggle') ?>">
                      <?= csrf_field() ?>
                      <button class="btn btn-ghost btn-sm" type="submit"><?= (int) $row['is_active'] ? 'Turn off' : 'Turn on' ?></button>
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
      <h2 class="t-h3" style="margin:0"><?= $editing !== null ? 'Edit code' : 'New code' ?></h2>
      <?php if ($editing !== null): ?>
        <a class="btn btn-ghost btn-sm" href="<?= base_url('admin/promos') ?>">Cancel</a>
      <?php endif; ?>
    </div>

    <form method="post" action="<?= base_url('admin/promos/save') ?>" class="stack">
      <?= csrf_field() ?>
      <?php if ($editing !== null): ?>
        <input type="hidden" name="id" value="<?= (int) $editing['id'] ?>">
      <?php endif; ?>

      <div class="field">
        <label for="code">Code</label>
        <input class="input mono" type="text" id="code" name="code" required maxlength="32"
               style="text-transform:uppercase" value="<?= esc($val('code')) ?>">
        <span class="field-hint">Letters, numbers, dashes. Saved uppercase.</span>
      </div>

      <div class="field">
        <label for="description">What it is for</label>
        <input class="input" type="text" id="description" name="description" maxlength="255" value="<?= esc($val('description')) ?>">
      </div>

      <div class="row" style="gap:0.75rem">
        <div class="field" style="flex:1;min-width:0">
          <label for="discount_type">Type</label>
          <select class="select" id="discount_type" name="discount_type">
            <option value="percent" <?= $val('discount_type', 'percent') === 'percent' ? 'selected' : '' ?>>Percent off</option>
            <option value="fixed" <?= $val('discount_type') === 'fixed' ? 'selected' : '' ?>>Fixed amount off</option>
          </select>
        </div>
        <div class="field" style="flex:1;min-width:0">
          <label for="discount_value">Value</label>
          <input class="input" type="number" id="discount_value" name="discount_value" required min="0.01" step="0.01"
                 value="<?= esc($val('discount_value')) ?>">
        </div>
      </div>

      <div class="row" style="gap:0.75rem">
        <div class="field" style="flex:1;min-width:0">
          <label for="min_amount">Minimum spend</label>
          <input class="input" type="number" id="min_amount" name="min_amount" min="0" step="0.01" value="<?= esc($val('min_amount', '0')) ?>">
        </div>
        <div class="field" style="flex:1;min-width:0">
          <label for="max_uses">Max uses</label>
          <input class="input" type="number" id="max_uses" name="max_uses" min="0" step="1" value="<?= esc($val('max_uses', '0')) ?>">
          <span class="field-hint">0 is unlimited.</span>
        </div>
      </div>

      <div class="row" style="gap:0.75rem">
        <div class="field" style="flex:1;min-width:0">
          <label for="starts_on">Starts</label>
          <input class="input" type="date" id="starts_on" name="starts_on" value="<?= esc($val('starts_on')) ?>">
        </div>
        <div class="field" style="flex:1;min-width:0">
          <label for="ends_on">Ends</label>
          <input class="input" type="date" id="ends_on" name="ends_on" value="<?= esc($val('ends_on')) ?>">
        </div>
      </div>

      <label style="display:flex;align-items:center;gap:0.625rem">
        <input type="checkbox" name="is_active" value="1" <?= ($editing === null && old('code') === null) || $val('is_active') === '1' ? 'checked' : '' ?>>
        <span class="t-small">Live now</span>
      </label>

      <?php if ($editing !== null && (int) $editing['used_count'] > 0): ?>
        <p class="t-small t-mute" style="margin:0">
          Used <?= (int) $editing['used_count'] ?> time<?= (int) $editing['used_count'] === 1 ? '' : 's' ?> already. Turn it off rather than deleting it so the bookings keep their discount trail.
        </p>
      <?php endif; ?>

      <button class="btn" type="submit"><?= $editing !== null ? 'Save changes' : 'Create code' ?></button>
    </form>
  </div>
</div>
<?= $this->endSection() ?>
