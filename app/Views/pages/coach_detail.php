<?= $this->extend($layout) ?>

<?= $this->section('content') ?>
<?php
$img  = unsplash($coach['photo_path'], 800, 1000);
$days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
?>

<?= partial('partials/page_head', [
    'heading' => $coach['first_name'] . ' ' . $coach['last_name'],
    'lede'    => $coach['headline'],
    'kicker'  => '<span class="chip tone-' . ($coach['is_accepting'] ? 'open' : 'booked') . '">'
        . ($coach['is_accepting'] ? 'Taking sessions' : 'Books full') . '</span>'
        . '<span class="chip tone-live">★ ' . esc(number_format((float) $coach['rating'], 1)) . '</span>',
    'actions' => '<a class="btn" href="' . base_url('booking?coach=' . (int) $coach['id']) . '">Book a session</a>'
        . '<a class="btn btn-ghost" href="' . base_url('coaches') . '">All coaches</a>',
]) ?>

<section class="section">
  <div class="container">
    <div style="display:grid;grid-template-columns:minmax(0,20rem) minmax(0,1fr);gap:clamp(1.5rem,3vw,2.5rem);align-items:start" class="coach-detail">
      <?php if ($img !== null): ?>
        <img src="<?= esc($img) ?>" alt="Coach <?= esc($coach['first_name'] . ' ' . $coach['last_name']) ?>"
             width="800" height="1000" style="width:100%;border-radius:var(--r-lg);border:1px solid var(--line)">
      <?php endif; ?>

      <div class="stack-4">
        <div class="prose">
          <p style="font-size:1.0625rem"><?= esc($coach['bio']) ?></p>
        </div>

        <div class="grid-auto">
          <div class="panel ticks readout">
            <span class="t-label">Private session</span>
            <span class="t-metric" style="font-size:1.5rem"><?= esc(peso($coach['hourly_rate'])) ?></span>
            <span class="t-small t-mute">Per hour, court fee on top</span>
          </div>
          <div class="panel ticks readout">
            <span class="t-label">Group rate</span>
            <span class="t-metric" style="font-size:1.5rem"><?= esc(peso($coach['group_rate'])) ?></span>
            <span class="t-small t-mute">Per player per hour, 3-4 players</span>
          </div>
          <div class="panel ticks readout">
            <span class="t-label">Experience</span>
            <span class="t-metric" style="font-size:1.5rem"><?= (int) $coach['years_experience'] ?> yrs</span>
            <span class="t-small t-mute"><?= number_format((int) $coach['sessions_taught']) ?> sessions taught</span>
          </div>
        </div>

        <div class="grid-auto-lg">
          <div class="panel">
            <h2 class="t-h3" style="margin-bottom:0.875rem">Coaches</h2>
            <div class="row" style="gap:0.375rem">
              <?php foreach (pipes($coach['specialties']) as $s): ?>
                <span class="chip tone-live"><?= esc($s) ?></span>
              <?php endforeach; ?>
            </div>
            <h3 class="t-label" style="margin:1.25rem 0 0.5rem">Certified</h3>
            <ul class="t-small t-mute" style="margin:0;padding-left:1.125rem">
              <?php foreach (pipes($coach['certifications']) as $c): ?>
                <li><?= esc($c) ?></li>
              <?php endforeach; ?>
            </ul>
          </div>

          <div class="panel">
            <h2 class="t-h3" style="margin-bottom:0.875rem">Usually on court</h2>
            <?php if ($availability === []): ?>
              <p class="t-small t-mute" style="margin:0">By arrangement. Ask the desk.</p>
            <?php else: ?>
              <div class="table-wrap">
                <table class="table" style="min-width:0">
                  <tbody>
                    <?php foreach ($availability as $a): ?>
                      <tr>
                        <td class="t-label" style="width:8rem"><?= esc($days[(int) $a['day_of_week']]) ?></td>
                        <td class="mono"><?= esc(clock($a['starts_at'])) ?>–<?= esc(clock($a['ends_at'])) ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <?php if ($sessions !== []): ?>
          <div class="panel panel-flush">
            <div class="panel-head"><h2 class="t-h3" style="margin:0">Next sessions booked</h2></div>
            <div class="table-wrap">
              <table class="table">
                <thead><tr><th>Date</th><th>Time</th><th>Type</th><th class="num">Players</th></tr></thead>
                <tbody>
                  <?php foreach ($sessions as $s): ?>
                    <tr>
                      <td><?= esc(day_label($s['session_date'])) ?></td>
                      <td class="mono"><?= esc(clock($s['start_time'])) ?>–<?= esc(clock($s['end_time'])) ?></td>
                      <td><?= esc(ucfirst(str_replace('_', '-', $s['session_type']))) ?></td>
                      <td class="num"><?= (int) $s['participants'] ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<style>@media (max-width: 800px) { .coach-detail { grid-template-columns: 1fr !important; } }</style>
<?= $this->endSection() ?>
