<?= $this->extend($layout) ?>

<?= $this->section('content') ?>
<?= partial('partials/page_head', [
    'heading' => 'Book a coaching session',
    'lede'    => 'Pick the shape of the session, pick a coach, then pick a court and a time. The coach is checked against their own diary before we confirm.',
    'kicker'  => '<span class="t-label">' . count($coaches) . ' coaches taking bookings</span>',
]) ?>

<section class="section">
  <div class="container">
    <div class="section-head">
      <div>
        <h2 class="t-h2">Session types</h2>
        <p class="t-small t-mute">Court fee is on top and depends on which court and what time. The booking form shows the total before you confirm.</p>
      </div>
    </div>

    <div class="grid-auto">
      <?php foreach ($types as $type): ?>
        <div class="panel ticks stack-2">
          <span class="t-label"><?= esc($type['players']) ?></span>
          <h3 class="t-h3"><?= esc($type['name']) ?></h3>
          <p class="t-small t-mute" style="margin:0"><?= esc($type['note']) ?></p>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section field-bg field-scan">
  <div class="container">
    <div class="section-head">
      <div>
        <h2 class="t-h2">Pick your coach</h2>
        <p class="t-small t-mute">Each one takes you straight into the booking form with their rate already applied.</p>
      </div>
      <a class="btn btn-ghost btn-sm" href="<?= base_url('coaches') ?>">Read the full profiles</a>
    </div>

    <div class="grid-auto">
      <?php foreach ($coaches as $coach): ?>
        <?php $img = unsplash($coach['photo_path'], 560, 700); ?>
        <article class="card coach-card">
          <?php if ($img !== null): ?>
            <div class="card-media">
              <img src="<?= esc($img) ?>" alt="Coach <?= esc($coach['first_name'] . ' ' . $coach['last_name']) ?>" loading="lazy" width="560" height="700">
              <span class="card-media-badge chip tone-<?= $coach['is_accepting'] ? 'open' : 'booked' ?>">
                <span class="chip-glyph" aria-hidden="true"><?= $coach['is_accepting'] ? '▲' : '■' ?></span>
                <?= $coach['is_accepting'] ? 'Open' : 'Full' ?>
              </span>
            </div>
          <?php endif; ?>
          <div class="card-body">
            <h3 class="t-h3"><?= esc($coach['first_name'] . ' ' . $coach['last_name']) ?></h3>
            <p class="t-small t-mute" style="margin:0"><?= esc($coach['headline']) ?></p>
            <div class="row" style="gap:0.375rem">
              <?php foreach (array_slice(pipes($coach['specialties']), 0, 2) as $s): ?>
                <span class="chip tone-closed" style="color:var(--mute)"><?= esc($s) ?></span>
              <?php endforeach; ?>
            </div>
            <div class="card-foot">
              <span class="coach-rate"><?= esc(peso($coach['hourly_rate'])) ?><span class="t-mute" style="font-size:0.6875rem">/hr</span></span>
              <a class="btn btn-sm" href="<?= base_url('booking?coach=' . (int) $coach['id']) ?>">Book</a>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section-tight">
  <div class="container">
    <div class="panel ticks">
      <div class="row-between" style="gap:1.5rem">
        <div style="max-width:44ch">
          <h2 class="t-h3" style="margin-bottom:0.5rem">Not sure who to book?</h2>
          <p class="t-small t-mute" style="margin:0">
            Tell the desk your level and what is not working. They put beginners with Coach Rhea,
            juniors with Coach Kaye, and anyone chasing a tournament with Coach Dennis.
          </p>
        </div>
        <a class="btn btn-ghost" href="tel:<?= esc(str_replace(' ', '', setting('facility.phone', ''))) ?>">Call the desk</a>
      </div>
    </div>
  </div>
</section>
<?= $this->endSection() ?>
