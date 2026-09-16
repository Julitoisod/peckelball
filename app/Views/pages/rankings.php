<?= $this->extend($layout) ?>

<?= $this->section('content') ?>
<?= partial('partials/page_head', [
    'heading' => 'Rankings & results',
    'lede'    => 'Season points from every completed tournament, and every result we have scored on court.',
    'kicker'  => '<span class="t-label">' . count($standings) . ' teams ranked · ' . count($results) . ' matches recorded</span>',
    'actions' => '<a class="btn" href="' . base_url('tournaments') . '">Enter the next one</a>',
]) ?>

<section class="section-tight">
  <div class="container">
    <div style="display:grid;grid-template-columns:minmax(0,1.4fr) minmax(0,1fr);gap:clamp(1.25rem,2.5vw,2rem);align-items:start" class="rank-grid">

      <div class="panel panel-flush">
        <div class="panel-head">
          <h2 class="t-h3" style="margin:0">Season table</h2>
          <span class="t-label">Teams</span>
        </div>

        <?php if ($standings === []): ?>
          <div class="empty" style="border:0">
            <p class="t-body" style="margin:0"><strong>No completed events yet this season.</strong></p>
            <p class="t-small" style="margin:0">Points appear here the evening a tournament finishes.</p>
          </div>
        <?php else: ?>
          <div class="table-wrap">
            <table class="table">
              <thead>
                <tr><th style="width:3rem">#</th><th>Team</th><th class="num">Events</th><th class="num">W</th><th class="num">L</th><th class="num">Pts</th><th class="num">Best</th></tr>
              </thead>
              <tbody>
                <?php foreach ($standings as $i => $s): ?>
                  <tr>
                    <td class="mono" style="color:var(--<?= $i === 0 ? 'green' : ($i < 3 ? 'blue' : 'mute') ?>);font-weight:<?= $i < 3 ? '700' : '400' ?>"><?= $i + 1 ?></td>
                    <td style="font-weight:<?= $i === 0 ? '600' : '400' ?>"><?= esc($s['team_name']) ?></td>
                    <td class="num"><?= (int) $s['events'] ?></td>
                    <td class="num"><?= (int) $s['wins'] ?></td>
                    <td class="num"><?= (int) $s['losses'] ?></td>
                    <td class="num" style="color:var(--fg);font-weight:600"><?= (int) $s['points'] ?></td>
                    <td class="num"><?= $s['best'] !== null ? '#' . (int) $s['best'] : '' ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>

      <div class="panel panel-flush">
        <div class="panel-head">
          <h2 class="t-h3" style="margin:0">Top rated players</h2>
          <span class="t-label">DUPR</span>
        </div>
        <?php if ($players === []): ?>
          <div class="empty" style="border:0"><p style="margin:0">No rated players yet.</p></div>
        <?php else: ?>
          <div class="table-wrap">
            <table class="table" style="min-width:0">
              <thead><tr><th style="width:2.5rem">#</th><th>Player</th><th>Level</th><th class="num">Rating</th></tr></thead>
              <tbody>
                <?php foreach ($players as $i => $p): ?>
                  <tr>
                    <td class="mono t-mute"><?= $i + 1 ?></td>
                    <td><?= esc($p['first_name'] . ' ' . $p['last_name']) ?></td>
                    <td class="t-label"><?= esc(ucfirst($p['skill_level'])) ?></td>
                    <td class="num" style="color:var(--blue-ink);font-weight:600"><?= esc(number_format((float) $p['dupr_rating'], 2)) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>

<section class="section-tight field-bg field-scan">
  <div class="container">
    <div class="section-head">
      <div>
        <h2 class="t-h2">Recent results</h2>
        <p class="t-small t-mute">Scores as they went into the book on court.</p>
      </div>
    </div>

    <?php if ($results === []): ?>
      <div class="empty"><p style="margin:0">Nothing scored yet.</p></div>
    <?php else: ?>
      <div class="panel panel-flush">
        <div class="table-wrap">
          <table class="table">
            <thead>
              <tr><th>Date</th><th>Tournament</th><th>Round</th><th>Match</th><th>Score</th><th>Court</th></tr>
            </thead>
            <tbody>
              <?php foreach ($results as $r): ?>
                <tr>
                  <td class="mono"><?= $r['scheduled_at'] ? esc(date('d M Y', strtotime($r['scheduled_at']))) : '' ?></td>
                  <td><a href="<?= base_url('tournaments/' . $r['slug']) ?>"><?= esc($r['tournament']) ?></a></td>
                  <td class="t-label"><?= esc($r['round_label'] ?: 'Round ' . $r['round']) ?></td>
                  <td>
                    <span style="<?= (int) $r['winner_id'] === (int) $r['participant_a'] ? 'color:var(--green);font-weight:600' : 'color:var(--mute)' ?>"><?= esc($r['team_a'] ?? 'TBD') ?></span>
                    <span class="t-mute"> v </span>
                    <span style="<?= (int) $r['winner_id'] === (int) $r['participant_b'] ? 'color:var(--green);font-weight:600' : 'color:var(--mute)' ?>"><?= esc($r['team_b'] ?? 'TBD') ?></span>
                  </td>
                  <td class="mono"><?= esc($r['score_a']) ?>–<?= esc($r['score_b']) ?></td>
                  <td class="mono t-mute"><?= esc($r['court_code'] ?? '') ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php endif; ?>
  </div>
</section>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<style>@media (max-width: 900px) { .rank-grid { grid-template-columns: 1fr !important; } }</style>
<?= $this->endSection() ?>
