<?= $this->extend($layout) ?>

<?= $this->section('content') ?>
<?php
$fmt = static fn (string $f): string => ucwords(str_replace('_', ' ', $f));
?>

<?= partial('partials/page_head', [
    'heading' => 'Tournaments',
    'lede'    => 'Leagues on Thursdays, opens on weekends, and a junior cup twice a year. Entry covers both partners.',
    'kicker'  => '<span class="t-label">' . count($upcoming) . ' upcoming · ' . count($past) . ' in the books</span>',
    'actions' => '<a class="btn btn-ghost" href="' . base_url('rankings') . '">Rankings &amp; results</a>',
]) ?>

<section class="section">
  <div class="container">
    <div class="section-head">
      <div><h2 class="t-h2">Open for entry</h2></div>
    </div>

    <?php if ($upcoming === []): ?>
      <div class="empty"><p style="margin:0">Nothing on the calendar right now. The next open usually lands within a month.</p></div>
    <?php else: ?>
      <div class="stack">
        <?php foreach ($upcoming as $t): ?>
          <?php
          $img  = unsplash($t['banner_path'], 640, 360);
          $full = $t['entered'] >= (int) $t['max_teams'];
          $tone = $t['status'] === 'open' ? ($full ? 'booked' : 'open') : 'filling';
          ?>
          <article class="tournament-row">
            <?php if ($img !== null): ?>
              <img src="<?= esc($img) ?>" alt="<?= esc($t['name']) ?>" loading="lazy" width="640" height="360">
            <?php endif; ?>

            <div class="stack-2">
              <div class="row" style="gap:0.5rem">
                <span class="chip tone-<?= $tone ?>">
                  <span class="chip-glyph" aria-hidden="true"><?= $full ? '■' : '▲' ?></span>
                  <?= $full ? 'Draw full' : ucfirst($t['status']) ?>
                </span>
                <span class="t-label">
                  <?= esc(date('d M', strtotime($t['starts_on']))) ?><?= $t['ends_on'] !== $t['starts_on'] ? '–' . esc(date('d M Y', strtotime($t['ends_on']))) : esc(date(' Y', strtotime($t['starts_on']))) ?>
                </span>
                <span class="t-label">· <?= esc($fmt($t['format'])) ?></span>
              </div>

              <h3 class="t-h3"><?= esc($t['name']) ?></h3>
              <p class="t-small t-mute" style="margin:0;max-width:62ch"><?= esc(mb_strimwidth($t['description'], 0, 190, '…')) ?></p>

              <div class="row" style="gap:1.25rem;margin-top:0.375rem">
                <span class="amount t-small"><?= esc(peso($t['entry_fee'])) ?> <span class="t-mute">entry</span></span>
                <span class="amount t-small"><?= esc(peso($t['prize_pool'])) ?> <span class="t-mute">prize pot</span></span>
                <span class="mono t-small"><?= (int) $t['entered'] ?>/<?= (int) $t['max_teams'] ?> <span class="t-mute">teams</span></span>
                <span class="mono t-small"><?= esc(ucfirst($t['skill_level'])) ?> <span class="t-mute"><?= esc($t['division']) ?></span></span>
              </div>
            </div>

            <a class="btn <?= $full ? 'btn-ghost' : '' ?>" href="<?= base_url('tournaments/' . $t['slug']) ?>">
              <?= $full ? 'Details' : 'Enter' ?>
            </a>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php if ($past !== []): ?>
<section class="section field-bg field-scan">
  <div class="container">
    <div class="section-head">
      <div><h2 class="t-h2">Played out</h2></div>
      <a class="btn btn-ghost btn-sm" href="<?= base_url('rankings') ?>">Season table</a>
    </div>

    <div class="grid-auto">
      <?php foreach ($past as $t): ?>
        <?php $img = unsplash($t['banner_path'], 640, 400); ?>
        <a class="card" href="<?= base_url('tournaments/' . $t['slug']) ?>" style="text-decoration:none">
          <?php if ($img !== null): ?>
            <div class="card-media">
              <img src="<?= esc($img) ?>" alt="<?= esc($t['name']) ?>" loading="lazy" width="640" height="400">
              <span class="card-media-badge chip tone-closed">Completed</span>
            </div>
          <?php endif; ?>
          <div class="card-body">
            <h3 class="t-h3"><?= esc($t['name']) ?></h3>
            <p class="t-small t-mute" style="margin:0"><?= esc(date('d M Y', strtotime($t['starts_on']))) ?> · <?= esc($fmt($t['format'])) ?></p>
            <div class="card-foot">
              <span class="mono t-small"><?= (int) $t['entered'] ?> teams</span>
              <span class="t-label" style="color:var(--blue-ink)">See the bracket →</span>
            </div>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>
<?= $this->endSection() ?>
