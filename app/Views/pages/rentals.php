<?= $this->extend($layout) ?>

<?= $this->section('content') ?>
<?= partial('partials/page_head', [
    'heading' => 'Equipment rental',
    'lede'    => 'Turn up with nothing. Everything here is charged by the hour and added to your booking, with no separate transaction.',
    'kicker'  => '<span class="t-label">' . esc($onLoan) . ' items out on court right now</span>',
    'actions' => '<a class="btn" href="' . base_url('booking') . '">Book with kit</a>',
]) ?>

<section class="section-tight">
  <div class="container">
    <div class="grid-auto">
      <?php foreach ($equipment as $e): ?>
        <?php
        $img = unsplash($e['image_path'], 640, 480);
        $out = (int) $e['available_qty'] === 0;
        $low = ! $out && (int) $e['available_qty'] <= 3;
        $tone = $out ? 'booked' : ($low ? 'filling' : 'open');
        $glyph = $out ? '■' : ($low ? '◆' : '▲');
        ?>
        <article class="card">
          <?php if ($img !== null): ?>
            <div class="card-media">
              <img src="<?= esc($img) ?>" alt="<?= esc($e['name']) ?>" loading="lazy" width="640" height="480">
              <span class="card-media-badge chip tone-<?= $tone ?>">
                <span class="chip-glyph" aria-hidden="true"><?= $glyph ?></span>
                <?= $out ? 'All out' : (int) $e['available_qty'] . ' available' ?>
              </span>
            </div>
          <?php endif; ?>
          <div class="card-body">
            <span class="t-label"><?= esc(ucfirst($e['category'])) ?> · <?= esc($e['code']) ?></span>
            <h2 class="t-h3" style="margin:-0.25rem 0 0"><?= esc($e['name']) ?></h2>
            <p class="t-small t-mute" style="margin:0"><?= esc($e['description']) ?></p>
            <div class="card-foot">
              <span>
                <span class="amount" style="font-size:1rem;font-weight:600"><?= esc(peso($e['rental_rate_hourly'])) ?></span>
                <span class="t-label">/hr</span>
              </span>
              <?php if ((float) $e['deposit'] > 0): ?>
                <span class="t-label"><?= esc(peso($e['deposit'])) ?> deposit</span>
              <?php else: ?>
                <span class="t-label">No deposit</span>
              <?php endif; ?>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section field-bg field-scan">
  <div class="container">
    <div class="grid-auto-lg">
      <div class="panel ticks">
        <h2 class="t-h3" style="margin-bottom:0.5rem">Deposits</h2>
        <p class="t-small t-mute" style="margin:0">
          Cash or a held card at the desk, refunded when the kit comes back in the condition it went out.
          Balls have no deposit. If you lose one in the grass, that is the game.
        </p>
      </div>
      <div class="panel ticks">
        <h2 class="t-h3" style="margin-bottom:0.5rem">Shoes are not optional</h2>
        <p class="t-small t-mute" style="margin:0">
          Covered courts need non-marking soles. If yours are not, we will loan you a pair 
          it protects a surface that costs more to resurface than it does to lend you shoes.
        </p>
      </div>
      <div class="panel ticks">
        <h2 class="t-h3" style="margin-bottom:0.5rem">Rent it, then buy it</h2>
        <p class="t-small t-mute" style="margin:0">
          Every paddle in the rack is also in the shop. Rent one, and if you buy the same model that day
          the rental comes off the price.
        </p>
      </div>
    </div>
  </div>
</section>
<?= $this->endSection() ?>
