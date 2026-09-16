<?= $this->extend($layout) ?>

<?= $this->section('content') ?>
<?= partial('partials/page_head', [
    'heading' => 'The pro shop',
    'lede'    => 'What we sell at the desk. Reserve here, try it on court, pay when you pick it up.',
    'kicker'  => '<span class="t-label">' . count($products) . ' items in stock</span>',
]) ?>

<section class="section-tight">
  <div class="container">
    <div class="row" style="gap:0.5rem;margin-bottom:1.5rem">
      <a class="chip <?= $active === '' ? 'tone-live' : 'tone-closed' ?>" href="<?= base_url('shop') ?>"
         style="text-decoration:none;<?= $active === '' ? '' : 'color:var(--mute)' ?>">All</a>
      <?php foreach ($categories as $cat => $n): ?>
        <a class="chip <?= $active === $cat ? 'tone-live' : 'tone-closed' ?>" href="<?= base_url('shop?c=' . esc($cat, 'url')) ?>"
           style="text-decoration:none;<?= $active === $cat ? '' : 'color:var(--mute)' ?>">
          <?= esc(ucfirst($cat)) ?> <span style="opacity:0.6"><?= (int) $n ?></span>
        </a>
      <?php endforeach; ?>
    </div>

    <?php if ($products === []): ?>
      <div class="empty"><p style="margin:0">Nothing in that category right now.</p></div>
    <?php else: ?>
      <div class="grid-auto">
        <?php foreach ($products as $p): ?>
          <?php
          $img = unsplash($p['image_path'], 640, 480);
          $low = (int) $p['stock_qty'] <= (int) $p['reorder_level'];
          ?>
          <a class="card" href="<?= base_url('shop/' . $p['slug']) ?>" style="text-decoration:none">
            <?php if ($img !== null): ?>
              <div class="card-media">
                <img src="<?= esc($img) ?>" alt="<?= esc($p['name']) ?>" loading="lazy" width="640" height="480">
                <?php if ($p['is_featured']): ?>
                  <span class="card-media-badge chip tone-live">Pick of the shop</span>
                <?php elseif ($low): ?>
                  <span class="card-media-badge chip tone-filling"><span class="chip-glyph" aria-hidden="true">◆</span>Last <?= (int) $p['stock_qty'] ?></span>
                <?php endif; ?>
              </div>
            <?php endif; ?>
            <div class="card-body">
              <span class="t-label"><?= esc($p['brand']) ?></span>
              <h2 class="t-h3" style="margin:-0.25rem 0 0"><?= esc($p['name']) ?></h2>
              <p class="t-small t-mute" style="margin:0"><?= esc(mb_strimwidth($p['description'], 0, 90, '…')) ?></p>
              <div class="card-foot">
                <span class="amount" style="font-size:1rem;font-weight:600"><?= esc(peso($p['price'])) ?></span>
                <span class="t-label" style="color:var(--<?= $low ? 'amber' : 'green' ?>)"><?= (int) $p['stock_qty'] ?> in stock</span>
              </div>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<section class="section-tight field-bg field-scan">
  <div class="container">
    <div class="panel ticks">
      <div class="row-between" style="gap:1.5rem">
        <div style="max-width:48ch">
          <h2 class="t-h3" style="margin-bottom:0.5rem">Try before you buy</h2>
          <p class="t-small t-mute" style="margin:0">
            Every paddle we sell is also in the rental rack. Rent it for an hour, and if you buy it that
            day the rental comes off the price. Members take a further <?= esc(setting('shop.member_discount', '10')) ?>% off.
          </p>
        </div>
        <a class="btn btn-ghost" href="<?= base_url('rentals') ?>">See the rental rack</a>
      </div>
    </div>
  </div>
</section>
<?= $this->endSection() ?>
