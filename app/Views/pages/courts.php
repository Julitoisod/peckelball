<?= $this->extend($layout) ?>

<?= $this->section('content') ?>
<?= partial('partials/page_head', [
    'heading' => 'Five courts, and what each one is for',
    'lede'    => 'Three under a full roof, two in the open. Same net height, very different games.',
    'kicker'  => '<span class="t-label">' . esc($board['totals']['open']) . ' slots open today across all five</span>',
    'actions' => '<a class="btn" href="' . base_url('booking') . '">Book a court</a>'
        . '<a class="btn btn-ghost" href="' . base_url('availability') . '">Live board</a>',
]) ?>

<section class="section">
  <div class="container">
    <div class="stack-4">
      <?php foreach ($courts as $i => $court): ?>
        <?php
        $m   = status_meta($court['day_state']);
        $img = unsplash($court['image_path'], 900, 620);
        $flip = $i % 2 === 1;
        ?>
        <article class="panel panel-flush" style="display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1.1fr);gap:0" class="court-row">
          <div style="<?= $flip ? 'order:2' : '' ?>;min-height:16rem;background:var(--raised)">
            <?php if ($img !== null): ?>
              <img src="<?= esc($img) ?>" alt="<?= esc($court['name']) ?>, a <?= esc($court['surface_type']) ?> pickleball court at Bernie Pickleball in Trento"
                   loading="<?= $i < 2 ? 'eager' : 'lazy' ?>" width="900" height="620"
                   style="width:100%;height:100%;object-fit:cover">
            <?php endif; ?>
          </div>

          <div style="padding:clamp(1.25rem,2.5vw,2rem);display:flex;flex-direction:column;gap:1rem">
            <div class="row" style="gap:0.5rem">
              <span class="chip tone-<?= esc($m['tone']) ?>">
                <span class="chip-glyph" aria-hidden="true"><?= $m['glyph'] ?></span><?= esc($m['label']) ?>
              </span>
              <span class="chip tone-closed" style="color:var(--mute)"><?= esc(ucfirst($court['surface_type'])) ?></span>
              <span class="t-label"><?= esc($court['code']) ?></span>
            </div>

            <h2 class="t-h2"><?= esc($court['name']) ?></h2>
            <p class="t-body t-mute" style="margin:0"><?= esc($court['description']) ?></p>

            <div class="row" style="gap:1.5rem;margin-top:0.25rem">
              <div class="readout">
                <span class="t-metric" style="font-size:1.375rem"><?= esc(peso($court['hourly_rate'])) ?></span>
                <span class="t-label"><?= has_peak() ? 'Day / hr' : 'Per hour' ?></span>
              </div>
              <?php if (has_peak()): ?>
              <div class="readout">
                <span class="t-metric" style="font-size:1.375rem"><?= esc(peso($court['peak_rate'])) ?></span>
                <span class="t-label">Night / hr</span>
              </div>
              <?php endif; ?>
              <div class="readout">
                <span class="t-metric" style="font-size:1.375rem"><?= (int) $court['open_slots'] ?></span>
                <span class="t-label">Slots free today</span>
              </div>
            </div>

            <div class="row" style="gap:0.625rem;margin-top:auto;padding-top:0.5rem">
              <?php if ($court['next_open'] !== null): ?>
                <a class="btn btn-go" href="<?= base_url('booking?court=' . (int) $court['id'] . '&start=' . esc($court['next_open'], 'url')) ?>">
                  Book <?= esc($court['next_open']) ?>
                </a>
              <?php else: ?>
                <a class="btn" href="<?= base_url('booking?court=' . (int) $court['id'] . '&date=' . esc(date('Y-m-d', strtotime(business_date() . ' +1 day')), 'url')) ?>">Book tomorrow</a>
              <?php endif; ?>
              <a class="btn btn-ghost" href="<?= base_url('courts/' . $court['slug']) ?>">Court detail</a>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section-tight field-bg field-scan">
  <div class="container-wide">
    <div class="section-head">
      <div><h2 class="t-h2">All five, today</h2></div>
      <a class="btn btn-ghost btn-sm" href="<?= base_url('availability') ?>">Other days</a>
    </div>
    <?= partial('partials/board', ['board' => $board, 'bookable' => true]) ?>
  </div>
</section>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<style>
  @media (max-width: 860px) {
    .container article.panel-flush { grid-template-columns: 1fr !important; }
    .container article.panel-flush > div:first-child { order: 0 !important; min-height: 12rem; }
  }
</style>
<?= $this->endSection() ?>
