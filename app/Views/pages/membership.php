<?= $this->extend($layout) ?>

<?= $this->section('head') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/home.css?v=5') ?>">
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?= partial('partials/page_head', [
    'heading' => 'Membership',
    'lede'    => 'Court credits, a standing discount on everything else, and a booking window nobody else gets. No joining fee, no contract.',
    'kicker'  => '<span class="t-label">' . esc($memberCount) . ' active members</span>',
]) ?>

<?php if ($current !== null): ?>
<section class="section-tight">
  <div class="container">
    <div class="alert tone-open">
      <b>Active</b>
      <span>
        You are on <strong><?= esc($current['plan_name']) ?></strong> with
        <?= (int) $current['credits_remaining'] ?> court-hours left, running to
        <?= esc(date('d F Y', strtotime($current['ends_on']))) ?>.
      </span>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="section">
  <div class="container">
    <div class="plan-grid">
      <?php foreach ($plans as $plan): ?>
        <div class="plan<?= $plan['is_featured'] ? ' is-featured' : '' ?>">
          <div>
            <div class="row" style="gap:0.5rem">
              <h2 class="t-h3" style="margin:0"><?= esc($plan['name']) ?></h2>
              <?php if ($plan['is_featured']): ?><span class="chip tone-live">Most taken</span><?php endif; ?>
            </div>
            <p class="t-small t-mute" style="margin:0.25rem 0 0"><?= esc($plan['tagline']) ?></p>
          </div>

          <div class="plan-price">
            <span class="t-metric"><?= esc(peso($plan['price'])) ?></span>
            <span class="t-label">/ <?= esc($plan['billing_period']) ?></span>
          </div>

          <div class="row" style="gap:1rem;padding-block:0.75rem;border-block:1px solid var(--line)">
            <div class="readout">
              <span class="mono" style="font-size:1rem;font-weight:600"><?= (int) $plan['court_credit_hours'] ?>h</span>
              <span class="t-label">Credits</span>
            </div>
            <div class="readout">
              <span class="mono" style="font-size:1rem;font-weight:600"><?= (int) $plan['discount_percent'] ?>%</span>
              <span class="t-label">Off extras</span>
            </div>
            <div class="readout">
              <span class="mono" style="font-size:1rem;font-weight:600"><?= (int) $plan['advance_booking_days'] ?>d</span>
              <span class="t-label">Ahead</span>
            </div>
          </div>

          <ul>
            <?php foreach (pipes($plan['perks']) as $perk): ?>
              <li><?= esc($perk) ?></li>
            <?php endforeach; ?>
          </ul>

          <?php if ($current !== null): ?>
            <span class="btn btn-block btn-ghost" aria-disabled="true">Already a member</span>
          <?php else: ?>
            <form method="post" action="<?= base_url('membership/join') ?>" style="margin-top:auto">
              <?= csrf_field() ?>
              <input type="hidden" name="plan_id" value="<?= (int) $plan['id'] ?>">
              <button class="btn btn-block <?= $plan['is_featured'] ? '' : 'btn-ghost' ?>" type="submit">
                Choose <?= esc($plan['name']) ?>
              </button>
            </form>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="panel ticks" style="margin-top:1.75rem">
      <h2 class="t-h3" style="margin-bottom:0.75rem">How it actually works</h2>
      <div class="grid-auto" style="gap:1.25rem">
        <div>
          <span class="t-label">Credits</span>
          <p class="t-small t-mute" style="margin:0.375rem 0 0">
            A credit is one hour on any court, peak or not. They reset each billing period 
            they do not roll over, so take the hours.
          </p>
        </div>
        <div>
          <span class="t-label">The discount</span>
          <p class="t-small t-mute" style="margin:0.375rem 0 0">
            Applies to court time past your credits, coaching, rentals and the pro shop.
            It stacks with nothing else, and it does not need a code.
          </p>
        </div>
        <div>
          <span class="t-label">Booking window</span>
          <p class="t-small t-mute" style="margin:0.375rem 0 0">
            Non-members book <?= esc(setting('booking.advance_days', 14)) ?> days out. Members book further,
            which in practice is how the 18:30 slots get taken.
          </p>
        </div>
        <div>
          <span class="t-label">Payment</span>
          <p class="t-small t-mute" style="margin:0.375rem 0 0">
            Nothing is charged online. Choose a plan here, settle at the desk, and it switches on the same day.
            Cancel any time. It runs to the end of the period you paid for.
          </p>
        </div>
      </div>
    </div>
  </div>
</section>
<?= $this->endSection() ?>
