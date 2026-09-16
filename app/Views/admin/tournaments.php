<?= $this->extend($layout) ?>

<?= $this->section('content') ?>
<?php
/**
 * @var list<array<string,mixed>> $rows
 * @var array<string,mixed>|null  $open   tournament with `teams` and `matches`
 * @var list<string> $formats
 * @var list<string> $levels
 * @var list<string> $statuses
 */
$val = static function (string $field, string $fallback = '') use ($open): string {
    $o = old($field);

    return $o !== null ? (string) $o : (string) ($open[$field] ?? $fallback);
};
$label = static fn (string $s): string => ucwords(str_replace('_', ' ', $s));
$tone  = static fn (string $s): string => match ($s) {
    'open', 'ongoing' => 'open',
    'closed'          => 'filling',
    'cancelled'       => 'booked',
    'completed'       => 'live',
    default           => 'closed',
};
?>

<div class="panel panel-flush" style="margin-bottom:1rem">
  <div class="panel-head">
    <h2 class="t-h3" style="margin:0">Tournaments</h2>
    <span class="t-label"><?= count($rows) ?> on the books</span>
  </div>
  <div class="table-wrap">
    <table class="table">
      <thead>
        <tr><th>Tournament</th><th>Format</th><th>Dates</th><th class="num">Teams</th><th class="num">Entry</th><th class="num">Pot</th><th class="num">Matches</th><th>State</th><th></th></tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $row): ?>
          <tr<?= in_array($row['status'], ['cancelled', 'completed'], true) ? ' style="opacity:0.6"' : '' ?>>
            <td>
              <?= esc($row['name']) ?><br>
              <span class="t-label"><?= esc($row['division']) ?> · <?= esc($row['skill_level']) ?></span>
            </td>
            <td class="t-label"><?= esc($label($row['format'])) ?></td>
            <td class="mono t-small" style="white-space:nowrap">
              <?= esc(date('d M', strtotime((string) $row['starts_on']))) ?><br>
              <span class="t-mute">to <?= esc(date('d M Y', strtotime((string) $row['ends_on']))) ?></span>
            </td>
            <td class="num"><?= (int) $row['confirmed'] ?> / <?= (int) $row['max_teams'] ?></td>
            <td class="num"><?= esc(peso($row['entry_fee'])) ?></td>
            <td class="num"><?= esc(peso($row['prize_pool'])) ?></td>
            <td class="num"><?= (int) $row['matches'] ?></td>
            <td><span class="chip tone-<?= $tone($row['status']) ?>"><?= esc(ucfirst($row['status'])) ?></span></td>
            <td>
              <div class="row" style="gap:0.375rem;flex-wrap:nowrap">
                <a class="btn btn-ghost btn-sm" href="<?= base_url('admin/tournaments?id=' . (int) $row['id']) ?>">Open</a>
                <?php if ((int) $row['matches'] > 0): ?>
                  <a class="btn btn-ghost btn-sm" href="<?= base_url('admin/scoring?t=' . (int) $row['id']) ?>">Score</a>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php if ($open !== null): ?>
  <div class="panel panel-flush" style="margin-bottom:1rem">
    <div class="panel-head">
      <h2 class="t-h3" style="margin:0"><?= esc($open['name']) ?> · teams</h2>
      <div class="row" style="gap:0.5rem;align-items:center">
        <span class="t-label"><?= count($open['matches']) ?> matches drawn</span>
        <?php if ($open['matches'] === []): ?>
          <form method="post" action="<?= base_url('admin/tournaments/' . (int) $open['id'] . '/draw') ?>">
            <?= csrf_field() ?>
            <button class="btn btn-sm" type="submit">Draw the bracket</button>
          </form>
        <?php else: ?>
          <form method="post" action="<?= base_url('admin/tournaments/' . (int) $open['id'] . '/draw/clear') ?>"
                onsubmit="return confirm('Clear every match in this bracket?')">
            <?= csrf_field() ?>
            <button class="btn btn-ghost btn-sm" type="submit">Clear bracket</button>
          </form>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($open['teams'] === []): ?>
      <div class="empty" style="border:0"><p style="margin:0">No entries yet. Teams register from the public tournament page.</p></div>
    <?php else: ?>
      <div class="table-wrap">
        <table class="table">
          <thead>
            <tr><th class="num">Seed</th><th>Team</th><th>Contact</th><th class="num">W</th><th class="num">L</th><th class="num">Pts</th><th>Entry fee</th><th>State</th></tr>
          </thead>
          <tbody>
            <?php foreach ($open['teams'] as $team): ?>
              <tr<?= in_array($team['status'], ['withdrawn', 'disqualified'], true) ? ' style="opacity:0.55"' : '' ?>>
                <td class="num"><?= $team['seed'] === null ? '-' : (int) $team['seed'] ?></td>
                <td>
                  <?= esc($team['team_name']) ?><br>
                  <span class="t-small t-mute">
                    <?= esc($team['first_name'] ? $team['first_name'] . ' ' . $team['last_name'] : 'Guest entry') ?>
                    <?php if ($team['partner_first']): ?> with <?= esc($team['partner_first'] . ' ' . $team['partner_last']) ?><?php endif; ?>
                  </span>
                </td>
                <td class="mono t-small"><?= esc($team['contact_phone'] ?: ($team['email'] ?? '-')) ?></td>
                <td class="num"><?= (int) $team['wins'] ?></td>
                <td class="num"><?= (int) $team['losses'] ?></td>
                <td class="num"><?= (int) $team['points'] ?></td>
                <td>
                  <?php if ($team['payment_status'] === 'paid'): ?>
                    <span class="chip tone-open">Paid</span>
                  <?php else: ?>
                    <form method="post" action="<?= base_url('admin/tournaments/' . (int) $open['id'] . '/teams/' . (int) $team['id'] . '/pay') ?>" class="row" style="gap:0.25rem;flex-wrap:nowrap">
                      <?= csrf_field() ?>
                      <select class="select" name="method" style="min-height:32px;padding:0.125rem 1.5rem 0.125rem 0.375rem;font-size:0.75rem">
                        <?php foreach (['cash', 'gcash', 'maya', 'card'] as $m): ?>
                          <option value="<?= $m ?>"><?= ucfirst($m) ?></option>
                        <?php endforeach; ?>
                      </select>
                      <button class="btn btn-sm" type="submit">Take <?= esc(peso($open['entry_fee'])) ?></button>
                    </form>
                  <?php endif; ?>
                </td>
                <td>
                  <form method="post" action="<?= base_url('admin/tournaments/' . (int) $open['id'] . '/teams/' . (int) $team['id']) ?>" class="row" style="gap:0.25rem;flex-wrap:nowrap">
                    <?= csrf_field() ?>
                    <select class="select" name="status" style="min-height:32px;padding:0.125rem 1.5rem 0.125rem 0.375rem;font-size:0.75rem">
                      <?php foreach (['registered', 'confirmed', 'withdrawn', 'disqualified'] as $s): ?>
                        <option value="<?= $s ?>" <?= $team['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                      <?php endforeach; ?>
                    </select>
                    <button class="btn btn-ghost btn-sm" type="submit">Set</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
<?php endif; ?>

<div class="panel" style="max-width:52rem">
  <div class="row-between" style="margin-bottom:1rem">
    <h2 class="t-h3" style="margin:0"><?= $open !== null ? 'Edit ' . esc($open['name']) : 'New tournament' ?></h2>
    <?php if ($open !== null): ?>
      <a class="btn btn-ghost btn-sm" href="<?= base_url('admin/tournaments') ?>">New instead</a>
    <?php endif; ?>
  </div>

  <form method="post" action="<?= base_url('admin/tournaments/save') ?>" class="stack">
    <?= csrf_field() ?>
    <?php if ($open !== null): ?><input type="hidden" name="id" value="<?= (int) $open['id'] ?>"><?php endif; ?>

    <div class="field">
      <label for="name">Name</label>
      <input class="input" type="text" id="name" name="name" required maxlength="160" value="<?= esc($val('name')) ?>">
    </div>

    <div class="row" style="gap:0.75rem">
      <div class="field" style="flex:1;min-width:0">
        <label for="division">Division</label>
        <input class="input" type="text" id="division" name="division" required maxlength="60" value="<?= esc($val('division', 'Mixed doubles')) ?>">
      </div>
      <div class="field" style="flex:1;min-width:0">
        <label for="skill_level">Skill</label>
        <select class="select" id="skill_level" name="skill_level">
          <?php foreach ($levels as $l): ?>
            <option value="<?= esc($l) ?>" <?= $val('skill_level', 'open') === $l ? 'selected' : '' ?>><?= esc(ucfirst($l)) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field" style="flex:1;min-width:0">
        <label for="format">Format</label>
        <select class="select" id="format" name="format">
          <?php foreach ($formats as $f): ?>
            <option value="<?= esc($f) ?>" <?= $val('format', 'single_elimination') === $f ? 'selected' : '' ?>><?= esc($label($f)) ?></option>
          <?php endforeach; ?>
        </select>
        <span class="field-hint">Only single elimination and round robin draw themselves.</span>
      </div>
    </div>

    <div class="row" style="gap:0.75rem">
      <div class="field" style="flex:1;min-width:0">
        <label for="starts_on">Starts</label>
        <input class="input" type="date" id="starts_on" name="starts_on" required value="<?= esc($val('starts_on', date('Y-m-d'))) ?>">
      </div>
      <div class="field" style="flex:1;min-width:0">
        <label for="ends_on">Ends</label>
        <input class="input" type="date" id="ends_on" name="ends_on" required value="<?= esc($val('ends_on', date('Y-m-d'))) ?>">
      </div>
      <div class="field" style="flex:1;min-width:0">
        <label for="registration_opens">Registration opens</label>
        <input class="input" type="date" id="registration_opens" name="registration_opens" value="<?= esc($val('registration_opens')) ?>">
      </div>
      <div class="field" style="flex:1;min-width:0">
        <label for="registration_closes">and closes</label>
        <input class="input" type="date" id="registration_closes" name="registration_closes" value="<?= esc($val('registration_closes')) ?>">
      </div>
    </div>

    <div class="row" style="gap:0.75rem">
      <div class="field" style="flex:1;min-width:0">
        <label for="entry_fee">Entry fee</label>
        <input class="input" type="number" id="entry_fee" name="entry_fee" min="0" step="0.01" value="<?= esc($val('entry_fee', '0')) ?>">
      </div>
      <div class="field" style="flex:1;min-width:0">
        <label for="prize_pool">Prize pool</label>
        <input class="input" type="number" id="prize_pool" name="prize_pool" min="0" step="0.01" value="<?= esc($val('prize_pool', '0')) ?>">
      </div>
      <div class="field" style="flex:1;min-width:0">
        <label for="max_teams">Max teams</label>
        <input class="input" type="number" id="max_teams" name="max_teams" required min="2" max="256" step="1" value="<?= esc($val('max_teams', '16')) ?>">
      </div>
      <div class="field" style="flex:1;min-width:0">
        <label for="tstatus">State</label>
        <select class="select" id="tstatus" name="status">
          <?php foreach ($statuses as $s): ?>
            <option value="<?= esc($s) ?>" <?= $val('status', 'draft') === $s ? 'selected' : '' ?>><?= esc(ucfirst($s)) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="field">
      <label for="description">Description</label>
      <textarea class="textarea" id="description" name="description" maxlength="5000"><?= esc($val('description')) ?></textarea>
    </div>

    <button class="btn" type="submit"><?= $open !== null ? 'Save tournament' : 'Create tournament' ?></button>
  </form>
</div>
<?= $this->endSection() ?>
