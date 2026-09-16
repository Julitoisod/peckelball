<?= $this->extend($layout) ?>

<?= $this->section('content') ?>
<?= partial('partials/page_head', [
    'heading' => 'The coaches',
    'lede'    => 'Four of them, certified, and all of them still play. Book a coach and a court in the same step.',
    'kicker'  => '<span class="t-label">' . count($coaches) . ' coaches · ' . number_format(array_sum(array_column($coaches, 'sessions_taught'))) . ' sessions taught</span>',
    'actions' => '<a class="btn" href="' . base_url('coaching') . '">Book a session</a>',
]) ?>

<section class="section">
  <div class="container">
    <div class="stack-4">
      <?php foreach ($coaches as $i => $coach): ?>
        <?php $img = unsplash($coach['photo_path'], 640, 800); ?>
        <article class="panel panel-flush" style="display:grid;grid-template-columns:minmax(0,0.7fr) minmax(0,1.3fr)" class="coach-row">
          <div style="min-height:18rem;background:var(--raised)">
            <?php if ($img !== null): ?>
              <img src="<?= esc($img) ?>" alt="Coach <?= esc($coach['first_name'] . ' ' . $coach['last_name']) ?>"
                   loading="<?= $i < 2 ? 'eager' : 'lazy' ?>" width="640" height="800"
                   style="width:100%;height:100%;object-fit:cover">
            <?php endif; ?>
          </div>

          <div style="padding:clamp(1.25rem,2.5vw,2rem);display:flex;flex-direction:column;gap:0.875rem">
            <div class="row" style="gap:0.5rem">
              <span class="chip tone-<?= $coach['is_accepting'] ? 'open' : 'booked' ?>">
                <span class="chip-glyph" aria-hidden="true"><?= $coach['is_accepting'] ? '▲' : '■' ?></span>
                <?= $coach['is_accepting'] ? 'Taking sessions' : 'Books full' ?>
              </span>
              <span class="chip tone-live">★ <?= esc(number_format((float) $coach['rating'], 1)) ?></span>
              <span class="t-label"><?= (int) $coach['years_experience'] ?> years</span>
            </div>

            <h2 class="t-h2"><?= esc($coach['first_name'] . ' ' . $coach['last_name']) ?></h2>
            <p class="t-h3" style="color:var(--mute);font-weight:500;margin:-0.375rem 0 0"><?= esc($coach['headline']) ?></p>
            <p class="t-body t-mute" style="margin:0"><?= esc($coach['bio']) ?></p>

            <div class="row" style="gap:0.375rem">
              <?php foreach (pipes($coach['specialties']) as $s): ?>
                <span class="chip tone-closed" style="color:var(--mute)"><?= esc($s) ?></span>
              <?php endforeach; ?>
            </div>

            <div class="row" style="gap:1.5rem;padding-top:0.5rem;border-top:1px solid var(--line);margin-top:0.25rem">
              <div class="readout">
                <span class="t-metric" style="font-size:1.375rem"><?= esc(peso($coach['hourly_rate'])) ?></span>
                <span class="t-label">Private / hr</span>
              </div>
              <div class="readout">
                <span class="t-metric" style="font-size:1.375rem"><?= esc(peso($coach['group_rate'])) ?></span>
                <span class="t-label">Group / player / hr</span>
              </div>
              <div class="readout">
                <span class="t-metric" style="font-size:1.375rem"><?= number_format((int) $coach['sessions_taught']) ?></span>
                <span class="t-label">Sessions taught</span>
              </div>
            </div>

            <?php if (pipes($coach['certifications']) !== []): ?>
              <p class="t-small t-mute" style="margin:0">
                <?= esc(implode(' · ', pipes($coach['certifications']))) ?>
              </p>
            <?php endif; ?>

            <div class="row" style="gap:0.625rem;margin-top:auto;padding-top:0.5rem">
              <a class="btn" href="<?= base_url('booking?coach=' . (int) $coach['id']) ?>">Book <?= esc($coach['first_name']) ?></a>
              <a class="btn btn-ghost" href="<?= base_url('coaches/' . (int) $coach['id']) ?>">Availability</a>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<style>
  @media (max-width: 820px) {
    .container article.panel-flush { grid-template-columns: 1fr !important; }
    .container article.panel-flush > div:first-child { min-height: 14rem; }
  }
</style>
<?= $this->endSection() ?>
