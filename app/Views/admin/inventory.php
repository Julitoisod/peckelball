<?= $this->extend($layout) ?>

<?= $this->section('content') ?>
<?php
/**
 * @var list<array<string,mixed>> $products
 * @var list<array<string,mixed>> $low        at or under the reorder level
 * @var list<array<string,mixed>> $movements
 * @var list<string> $types
 * @var float $value  stock at cost
 */
?>

<div class="kpi-grid" style="margin-bottom:1rem">
  <div class="kpi kpi-accent">
    <span class="t-label">Stock at cost</span>
    <span class="t-metric"><?= esc(peso($value)) ?></span>
    <span class="t-label"><?= count($products) ?> lines</span>
  </div>
  <div class="kpi <?= $low === [] ? '' : 'kpi-warn' ?>">
    <span class="t-label">At or under reorder</span>
    <span class="t-metric"><?= count($low) ?></span>
    <span class="t-label"><?= $low === [] ? 'nothing to order' : 'needs ordering' ?></span>
  </div>
  <div class="kpi">
    <span class="t-label">Units on the shelf</span>
    <span class="t-metric" style="font-size:1.5rem"><?= number_format(array_sum(array_map(static fn ($p) => (int) $p['stock_qty'], $products))) ?></span>
    <span class="t-label">across every line</span>
  </div>
  <div class="kpi">
    <span class="t-label">Movements logged</span>
    <span class="t-metric" style="font-size:1.5rem"><?= count($movements) ?></span>
    <span class="t-label">most recent 40 shown</span>
  </div>
</div>

<?php if ($low !== []): ?>
  <div class="alert tone-filling" role="status" style="margin-bottom:1rem">
    <b>Order these</b>
    <span>
      <?php $names = array_map(static fn ($p) => $p['name'] . ' (' . (int) $p['stock_qty'] . ' left)', $low); ?>
      <?= esc(implode(', ', array_slice($names, 0, 6))) ?><?= count($names) > 6 ? ' and ' . (count($names) - 6) . ' more' : '' ?>
    </span>
  </div>
<?php endif; ?>

<div class="admin-cols">
  <div class="panel panel-flush">
    <div class="panel-head">
      <h2 class="t-h3" style="margin:0">Shop stock</h2>
      <span class="t-label"><?= count(array_filter($products, static fn ($p) => (int) $p['is_active'] === 1)) ?> selling</span>
    </div>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr><th>Product</th><th>Category</th><th class="num">Cost</th><th class="num">Price</th><th class="num">Stock</th><th class="num">Reorder at</th></tr>
        </thead>
        <tbody>
          <?php foreach ($products as $p): ?>
            <?php
            $stock = (int) $p['stock_qty'];
            $level = (int) $p['reorder_level'];
            $hue   = $stock === 0 ? 'red' : ($stock <= $level ? 'amber' : 'green');
            ?>
            <tr<?= (int) $p['is_active'] ? '' : ' style="opacity:0.55"' ?>>
              <td>
                <span class="mono t-small"><?= esc($p['sku']) ?></span> <?= esc($p['name']) ?>
                <?php if ($p['brand']): ?><br><span class="t-label"><?= esc($p['brand']) ?></span><?php endif; ?>
              </td>
              <td class="t-label"><?= esc(ucfirst($p['category'])) ?></td>
              <td class="num"><?= esc(peso($p['cost_price'])) ?></td>
              <td class="num"><?= esc(peso($p['price'])) ?></td>
              <td class="num" style="color:var(--<?= $hue ?>);font-weight:600"><?= $stock ?></td>
              <td class="num t-mute"><?= $level ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="panel">
    <h2 class="t-h3" style="margin:0 0 1rem">Move stock</h2>

    <form method="post" action="<?= base_url('admin/inventory/move') ?>" class="stack">
      <?= csrf_field() ?>

      <div class="field">
        <label for="product_id">Product</label>
        <select class="select" id="product_id" name="product_id" required>
          <?php foreach ($products as $p): ?>
            <option value="<?= (int) $p['id'] ?>" <?= (int) old('product_id') === (int) $p['id'] ? 'selected' : '' ?>>
              <?= esc($p['sku'] . ' · ' . $p['name']) ?> (<?= (int) $p['stock_qty'] ?> on shelf)
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="row" style="gap:0.75rem">
        <div class="field" style="flex:1;min-width:0">
          <label for="type">Movement</label>
          <select class="select" id="type" name="type">
            <option value="in" <?= old('type') === 'in' ? 'selected' : '' ?>>In, delivery</option>
            <option value="out" <?= old('type') === 'out' ? 'selected' : '' ?>>Out, sold or used</option>
            <option value="adjustment" <?= old('type') === 'adjustment' ? 'selected' : '' ?>>Adjustment, counted</option>
          </select>
        </div>
        <div class="field" style="flex:0 0 8rem">
          <label for="qty">Quantity</label>
          <input class="input" type="number" id="qty" name="qty" required min="1" max="10000" step="1" value="<?= esc(old('qty') ?? '1') ?>">
        </div>
      </div>

      <p class="field-hint" style="margin:0">
        In and out are changes. An adjustment is the number actually on the shelf after counting it,
        and the ledger records the difference.
      </p>

      <div class="field">
        <label for="reason">Reason</label>
        <input class="input" type="text" id="reason" name="reason" maxlength="160" value="<?= esc(old('reason') ?? '') ?>"
               placeholder="Delivery from supplier, stock count, damaged">
      </div>

      <button class="btn" type="submit">Post the movement</button>
    </form>
  </div>
</div>

<div class="panel panel-flush" style="margin-top:1rem">
  <div class="panel-head">
    <h2 class="t-h3" style="margin:0">Movement history</h2>
    <span class="t-label">newest first</span>
  </div>

  <?php if ($movements === []): ?>
    <div class="empty" style="border:0">
      <p style="margin:0">Nothing logged yet. Every change posted from here is recorded with who and why.</p>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr><th>When</th><th>Product</th><th>Type</th><th class="num">Change</th><th class="num">Balance after</th><th>Reason</th><th>By</th></tr>
        </thead>
        <tbody>
          <?php foreach ($movements as $m): ?>
            <tr>
              <td class="mono t-small" style="white-space:nowrap"><?= esc(date('d M, g:i A', strtotime((string) $m['created_at']))) ?></td>
              <td><span class="mono t-small"><?= esc($m['sku']) ?></span> <?= esc($m['product_name']) ?></td>
              <td class="t-label"><?= esc($m['type']) ?></td>
              <td class="num" style="color:var(--<?= (int) $m['qty'] > 0 ? 'green' : 'red' ?>);font-weight:600">
                <?= (int) $m['qty'] > 0 ? '+' : '' ?><?= (int) $m['qty'] ?>
              </td>
              <td class="num"><?= (int) $m['balance'] ?></td>
              <td class="t-small t-mute"><?= esc($m['reason'] ?: '-') ?></td>
              <td class="t-small"><?= esc($m['first_name'] ? $m['first_name'] . ' ' . $m['last_name'] : 'System') ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
<?= $this->endSection() ?>
