<?= $this->extend($layout) ?>

<?= $this->section('content') ?>
<?php
/**
 * @var list<array<string,mixed>> $tournaments
 * @var int                       $pick
 * @var array<string,mixed>|null  $board  with `tournament`, `rounds`, `standings`
 * @var list<array<string,mixed>> $courts
 */
?>

<div class="panel" style="margin-bottom:1rem">
  <div class="row" style="gap:0.5rem;flex-wrap:wrap;align-items:center">
    <span class="t-label" style="flex:none">Tournament</span>
    <?php foreach ($tournaments as $t): ?>
      <a class="btn btn-sm <?= (int) $t['id'] === $pick ? '' : 'btn-ghost' ?>" href="<?= base_url('admin/scoring?t=' . (int) $t['id']) ?>">
        <?= esc($t['name']) ?>
        <span style="opacity:0.7">· <?= (int) $t['done'] ?>/<?= (int) $t['matches'] ?></span>
      </a>
    <?php endforeach; ?>
    <?php if ($tournaments === []): ?>
      <span class="t-small t-mute">Nothing open. Create a tournament and draw its bracket first.</span>
    <?php endif; ?>
  </div>
</div>

<?php if ($board !== null && $board['rounds'] === []): ?>
  <div class="panel">
    <div class="empty" style="border:0">
      <p style="margin:0"><strong>No bracket drawn for <?= esc($board['tournament']['name'] ?? 'this tournament') ?>.</strong></p>
      <p class="t-small t-mute" style="margin:0.25rem 0 0">
        Confirm the teams and draw it from
        <a href="<?= base_url('admin/tournaments?id=' . $pick) ?>" style="color:var(--blue-ink)">the tournament page</a>.
      </p>
    </div>
  </div>
<?php elseif ($board !== null): ?>
  <div class="admin-cols">
    <div class="stack">
      <?php foreach ($board['rounds'] as $label => $matches): ?>
        <div class="panel panel-flush">
          <div class="panel-head">
            <h2 class="t-h3" style="margin:0"><?= esc($label) ?></h2>
            <span class="t-label"><?= count(array_filter($matches, static fn ($m) => in_array($m['status'], ['completed', 'walkover'], true))) ?> of <?= count($matches) ?> played</span>
          </div>

          <div class="table-wrap">
            <table class="table">
              <thead>
                <tr><th class="num">#</th><th>Match</th><th>Court</th><th>Score</th><th>Result</th></tr>
              </thead>
              <tbody>
                <?php foreach ($matches as $m): ?>
                  <?php
                  $ready = $m['participant_a'] !== null && $m['participant_b'] !== null;
                  $done  = in_array($m['status'], ['completed', 'walkover'], true);
                  ?>
                  <tr<?= $m['status'] === 'live' ? ' style="background:color-mix(in oklch, var(--blue) 10%, transparent)"' : '' ?>>
                    <td class="num"><?= (int) $m['match_number'] ?></td>
                    <td>
                      <span style="<?= (int) $m['winner_id'] === (int) $m['participant_a'] ? 'font-weight:600;color:var(--green)' : '' ?>">
                        <?= esc($m['team_a'] ?? 'to be decided') ?>
                      </span>
                      <span class="t-mute"> v </span>
                      <span style="<?= (int) $m['winner_id'] === (int) $m['participant_b'] ? 'font-weight:600;color:var(--green)' : '' ?>">
                        <?= esc($m['team_b'] ?? ($m['status'] === 'walkover' ? 'bye' : 'to be decided')) ?>
                      </span>
                    </td>
                    <td class="mono t-small"><?= esc($m['court_code'] ?? '-') ?></td>
                    <td class="mono">
                      <?php if ($done): ?>
                        <?= esc(($m['score_a'] ?? '') . ' - ' . ($m['score_b'] ?? '')) ?>
                      <?php elseif ($ready): ?>
                        <form method="post" action="<?= base_url('admin/scoring/' . (int) $m['id'] . '/score') ?>" class="row" style="gap:0.25rem;flex-wrap:nowrap;align-items:center">
                          <?= csrf_field() ?>
                          <input class="input mono" type="text" name="score_a" required maxlength="40" placeholder="11"
                                 style="min-height:32px;width:3.5rem;padding:0.125rem 0.375rem;text-align:center">
                          <input class="input mono" type="text" name="score_b" required maxlength="40" placeholder="9"
                                 style="min-height:32px;width:3.5rem;padding:0.125rem 0.375rem;text-align:center">
                          <select class="select" name="winner" style="min-height:32px;padding:0.125rem 1.5rem 0.125rem 0.375rem;font-size:0.75rem">
                            <option value="a">A wins</option>
                            <option value="b">B wins</option>
                          </select>
                          <button class="btn btn-sm" type="submit">Post</button>
                        </form>
                      <?php else: ?>
                        <span class="t-small t-mute">waiting</span>
                      <?php endif; ?>
                    </td>
                    <td>
                      <?php if ($m['status'] === 'walkover'): ?>
                        <span class="chip tone-live">Bye</span>
                      <?php elseif ($done): ?>
                        <div class="row" style="gap:0.375rem;flex-wrap:nowrap">
                          <span class="chip tone-open">Done</span>
                          <form method="post" action="<?= base_url('admin/scoring/' . (int) $m['id'] . '/reopen') ?>"
                                onsubmit="return confirm('Reopen this match and undo the result?')">
                            <?= csrf_field() ?>
                            <button class="btn btn-ghost btn-sm" type="submit">Reopen</button>
                          </form>
                        </div>
                      <?php elseif ($ready): ?>
                        <form method="post" action="<?= base_url('admin/scoring/' . (int) $m['id'] . '/live') ?>" class="row" style="gap:0.25rem;flex-wrap:nowrap">
                          <?= csrf_field() ?>
                          <select class="select" name="court_id" style="min-height:32px;padding:0.125rem 1.5rem 0.125rem 0.375rem;font-size:0.75rem">
                            <option value="">Court</option>
                            <?php foreach ($courts as $court): ?>
                              <option value="<?= (int) $court['id'] ?>" <?= (int) $m['court_id'] === (int) $court['id'] ? 'selected' : '' ?>><?= esc($court['code']) ?></option>
                            <?php endforeach; ?>
                          </select>
                          <button class="btn btn-ghost btn-sm" type="submit"><?= $m['status'] === 'live' ? 'Off court' : 'On court' ?></button>
                        </form>
                      <?php else: ?>
                        <span class="chip tone-closed">Pending</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="panel panel-flush">
      <div class="panel-head">
        <h2 class="t-h3" style="margin:0">Standings</h2>
        <span class="t-label">2 points a win</span>
      </div>
      <div class="table-wrap">
        <table class="table">
          <thead><tr><th>Team</th><th class="num">W</th><th class="num">L</th><th class="num">Pts</th></tr></thead>
          <tbody>
            <?php foreach ($board['standings'] as $i => $s): ?>
              <tr>
                <td>
                  <span class="mono t-mute" style="font-size:0.75rem"><?= $i + 1 ?></span>
                  <?= esc($s['team_name']) ?>
                  <?php if ($s['status'] === 'disqualified'): ?><br><span class="t-label" style="color:var(--red)">disqualified</span><?php endif; ?>
                </td>
                <td class="num"><?= (int) $s['wins'] ?></td>
                <td class="num"><?= (int) $s['losses'] ?></td>
                <td class="num" style="font-weight:600"><?= (int) $s['points'] ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
<?php endif; ?>
<?= $this->endSection() ?>
