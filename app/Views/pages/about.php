<?= $this->extend($layout) ?>

<?= $this->section('content') ?>
<?php $hero = unsplash('photo-1756477558468-b3e485757470', 1600, 800); ?>

<section class="field-deep" style="position:relative;isolation:isolate">
  <?php if ($hero !== null): ?>
    <div style="position:absolute;inset:0;z-index:-2">
      <img src="<?= esc($hero) ?>" alt="Play under way on a covered court at Go Paddle" width="1600" height="800" style="width:100%;height:100%;object-fit:cover">
    </div>
    <div style="position:absolute;inset:0;z-index:-1;background:linear-gradient(to top, var(--bg-deep) 8%, color-mix(in oklch, var(--bg-deep) 76%, transparent) 58%, color-mix(in oklch, var(--bg-deep) 58%, transparent) 100%)"></div>
  <?php endif; ?>

  <div class="container" style="padding-block:clamp(4rem,10vw,8rem) clamp(2rem,4vw,3rem)">
    <h1 class="t-display" style="font-size:clamp(2.25rem,6vw,4.75rem);max-width:16ch">
      Five courts on the highway
    </h1>
    <p class="t-lede" style="margin-top:1.25rem;font-size:clamp(1.0625rem,1.6vw,1.375rem)">
      Go Paddle is a family-run facility in Trento, Agusan del Sur. It started with one
      resurfaced basketball slab and a portable net. It is now five courts, four coaches and a
      Thursday league that fills every week.
    </p>
  </div>
</section>

<section class="section-tight">
  <div class="container">
    <div class="grid-auto">
      <div class="panel ticks readout">
        <span class="t-label">Sessions played</span>
        <span class="t-metric"><?= number_format($stats['bookings']) ?></span>
      </div>
      <div class="panel ticks readout readout-accent">
        <span class="t-label">Active members</span>
        <span class="t-metric"><?= number_format($stats['members']) ?></span>
      </div>
      <div class="panel ticks readout">
        <span class="t-label">Coaching hours</span>
        <span class="t-metric"><?= number_format($stats['coaching']) ?></span>
      </div>
      <div class="panel ticks readout">
        <span class="t-label">Courts</span>
        <span class="t-metric"><?= count($courts) ?></span>
        <span class="t-small t-mute">3 covered · 2 outdoor</span>
      </div>
    </div>
  </div>
</section>

<section class="section">
  <div class="container">
    <div style="display:grid;grid-template-columns:minmax(0,1.3fr) minmax(0,1fr);gap:clamp(1.5rem,3vw,3rem);align-items:start" class="about-grid">
      <div class="prose">
        <h2 class="t-h2">Why we built it</h2>
        <p>
          Bernardo &ldquo;Bernie&rdquo; Alcantara came back to Trento in 2023 with a paddle he had been
          given in Davao and nowhere to use it. The nearest proper court was two hours away. So the
          basketball slab behind the house got resurfaced, a portable net went up, and by the second
          month there were fourteen people waiting for a turn.
        </p>
        <p>
          The problem after that was never demand. It was the notebook. One court, one phone number,
          and a queue of people who had driven in for nothing because someone had already taken the
          slot and nobody had written it down.
        </p>

        <h2 class="t-h2">What changed</h2>
        <p>
          Five courts now, three of them under a full roof, which in Agusan matters more than
          anything else we could have spent the money on. Rain does not cancel a session here.
        </p>
        <p>
          And the notebook is gone. <strong>The board on this site is the board at the desk.</strong>
          When you look at Court 3 and it says open at 18:30, that is not a marketing claim, it is
          the reservation system. Book it and it goes red for everybody else the same second.
        </p>

        <h2 class="t-h2">How we run it</h2>
        <p>
          Open 06:00 to 22:00, every day, including the days nobody expects us to be. Walk-ins are
          welcome and always will be. A facility that only takes online bookings stops being a
          local one. But the 18:30 slot goes to whoever booked it first, member or not.
        </p>
        <p>
          Beginners get a rented paddle for <?= esc(peso(80)) ?> and someone to show them the two-bounce
          rule. That is how most of the people on the season table started.
        </p>
      </div>

      <div class="stack">
        <div class="panel">
          <h3 class="t-h3" style="margin-bottom:0.875rem">The courts</h3>
          <div class="stack-2">
            <?php foreach ($courts as $c): ?>
              <a href="<?= base_url('courts/' . $c['slug']) ?>" style="display:flex;justify-content:space-between;gap:0.75rem;padding:0.5rem 0;border-bottom:1px solid color-mix(in oklch, var(--line) 55%, transparent);text-decoration:none">
                <span>
                  <span class="mono" style="font-size:0.8125rem;font-weight:600"><?= esc($c['code']) ?></span>
                  <span class="t-small t-mute"> <?= esc($c['name']) ?></span>
                </span>
                <span class="t-label"><?= esc(ucfirst($c['surface_type'])) ?></span>
              </a>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="panel">
          <h3 class="t-h3" style="margin-bottom:0.875rem">The coaches</h3>
          <div class="stack-2">
            <?php foreach ($coaches as $c): ?>
              <?php $img = unsplash($c['photo_path'], 96, 96); ?>
              <a href="<?= base_url('coaches/' . (int) $c['id']) ?>" style="display:flex;gap:0.75rem;align-items:center;padding:0.375rem 0;text-decoration:none">
                <?php if ($img !== null): ?>
                  <img src="<?= esc($img) ?>" alt="" width="40" height="40" loading="lazy" style="width:40px;height:40px;border-radius:50%;object-fit:cover;flex:none">
                <?php endif; ?>
                <span>
                  <span style="display:block;font-size:0.875rem;font-weight:600"><?= esc($c['first_name'] . ' ' . $c['last_name']) ?></span>
                  <span class="t-label" style="letter-spacing:0.05em"><?= esc(mb_strimwidth($c['headline'], 0, 42, '…')) ?></span>
                </span>
              </a>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="panel ticks">
          <h3 class="t-h3" style="margin-bottom:0.5rem">Find us</h3>
          <p class="t-small t-mute" style="margin:0 0 0.875rem"><?= esc(setting('facility.address', '')) ?></p>
          <a class="btn btn-ghost btn-block btn-sm" href="<?= esc(setting('facility.maps_url', '#')) ?>" target="_blank" rel="noopener">Open in Maps</a>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="cta">
  <div class="container" style="position:relative;z-index:1">
    <div class="row-between" style="gap:2rem">
      <div style="max-width:34ch">
        <h2 class="t-h1" style="margin-bottom:0.75rem">Come and have a hit.</h2>
        <p style="margin:0">Paddle included if you do not own one. Nobody here started knowing how to play.</p>
      </div>
      <div class="row" style="gap:0.75rem">
        <a class="btn btn-lg" href="<?= base_url('booking') ?>">Book a court</a>
        <a class="btn btn-lg btn-ghost" href="<?= base_url('contact') ?>">Get directions</a>
      </div>
    </div>
  </div>
</section>
<?= $this->endSection() ?>

<?= $this->section('head') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/home.css?v=5') ?>">
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<style>@media (max-width: 880px) { .about-grid { grid-template-columns: 1fr !important; } }</style>
<?= $this->endSection() ?>
