<?= $this->extend($layout) ?>

<?= $this->section('content') ?>
<?php
$img = unsplash($product['image_path'], 1000, 800);
$low = (int) $product['stock_qty'] <= (int) $product['reorder_level'];
?>

<section class="section">
  <div class="container">
    <p class="t-label" style="margin-bottom:1.25rem">
      <a href="<?= base_url('shop') ?>" style="color:var(--mute)">Pro shop</a> /
      <a href="<?= base_url('shop?c=' . esc($product['category'], 'url')) ?>" style="color:var(--mute)"><?= esc(ucfirst($product['category'])) ?></a>
    </p>

    <div style="display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:clamp(1.5rem,3vw,3rem);align-items:start" class="product-grid">
      <?php if ($img !== null): ?>
        <img src="<?= esc($img) ?>" alt="<?= esc($product['name']) ?>" width="1000" height="800"
             style="width:100%;border-radius:var(--r-lg);border:1px solid var(--line)">
      <?php endif; ?>

      <div class="stack">
        <div class="row" style="gap:0.5rem">
          <?php if ($product['is_featured']): ?><span class="chip tone-live">Pick of the shop</span><?php endif; ?>
          <span class="chip tone-<?= $low ? 'filling' : 'open' ?>">
            <span class="chip-glyph" aria-hidden="true"><?= $low ? '◆' : '▲' ?></span>
            <?= (int) $product['stock_qty'] ?> in stock
          </span>
          <span class="t-label"><?= esc($product['sku']) ?></span>
        </div>

        <span class="t-label"><?= esc($product['brand']) ?></span>
        <h1 class="t-h1" style="margin:-0.5rem 0 0"><?= esc($product['name']) ?></h1>

        <div class="row" style="align-items:baseline;gap:0.5rem">
          <span class="t-metric"><?= esc(peso($product['price'])) ?></span>
          <span class="t-label">at the desk</span>
        </div>

        <p class="t-body t-mute" style="margin:0"><?= esc($product['description']) ?></p>

        <div class="alert tone-live">
          <b>How to buy</b>
          <span>
            We do not take card online. Tell the desk the SKU
            <span class="mono" style="color:var(--fg)"><?= esc($product['sku']) ?></span> and we will put it aside
            for 48 hours. Members take their plan discount off this price.
          </span>
        </div>

        <div class="row" style="gap:0.625rem">
          <a class="btn" href="tel:<?= esc(str_replace(' ', '', setting('facility.phone', ''))) ?>">Call to reserve</a>
          <a class="btn btn-ghost" href="<?= base_url('rentals') ?>">Rent one first</a>
        </div>
      </div>
    </div>

    <?php if ($related !== []): ?>
      <div class="section-head" style="margin-top:clamp(3rem,6vw,4.5rem)">
        <div><h2 class="t-h2">Also in <?= esc($product['category']) ?></h2></div>
      </div>
      <div class="grid-auto">
        <?php foreach ($related as $r): ?>
          <?php $rimg = unsplash($r['image_path'], 560, 420); ?>
          <a class="card" href="<?= base_url('shop/' . $r['slug']) ?>" style="text-decoration:none">
            <?php if ($rimg !== null): ?>
              <div class="card-media"><img src="<?= esc($rimg) ?>" alt="<?= esc($r['name']) ?>" loading="lazy" width="560" height="420"></div>
            <?php endif; ?>
            <div class="card-body">
              <h3 class="t-h3"><?= esc($r['name']) ?></h3>
              <div class="card-foot">
                <span class="amount" style="font-weight:600"><?= esc(peso($r['price'])) ?></span>
                <span class="t-label" style="color:var(--blue-ink)">View →</span>
              </div>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<style>@media (max-width: 820px) { .product-grid { grid-template-columns: 1fr !important; } }</style>
<?= $this->endSection() ?>
