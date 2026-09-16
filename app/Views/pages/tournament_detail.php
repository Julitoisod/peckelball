<?= $this->extend($layout) ?>

<?= $this->section('content') ?>
<?php
$img = unsplash($tournament['banner_path'], 1400, 700);
$fmt = ucwords(str_replace('_', ' ', $tournament['format']));
?>

<section class="field-deep" style="position:relative;isolation:isolate">
  <?php if ($img !== null): ?>
    <div style="position:absolute;inset:0;z-index:-2">
      <img src="<?= esc($img) ?>" alt="" width="1400" height="700" style="width:100%;height:100%;object-fit:cover">
    </div>
    <div style="position:absolute;inset:0;z-index:-1;background:linear-gradient(to top, var(--bg-deep) 10%, color-mix(in oklch, var(--bg-deep) 78%, transparent) 60%, color-mix(in oklch, var(--bg-deep) 60%, transparent) 100%)"></div>
  <?php endif; ?>

  <div class="container" style="padding-block:clamp(3.5rem,9vw,7rem) clamp(2rem,4vw,3rem)">
    <div class="row" style="gap:0.5rem;margin-bottom:1rem">
      <span class="chip tone-<?= $canRegister ? 'open' : ($tournament['status'] === 'completed' ? 'closed' : 'filling') ?>">
        <span class="chip-glyph" aria-hidden="true"><?= $canRegister ? '▲' : '■' ?></span>
        <?= $canRegister ? 'Entry open' : ucfirst($tournament['status']) ?>
      </span>
      <span class="t-label"><?= esc($fmt) ?> · <?= esc($tournament['division']) ?> · <?= esc(ucfirst($tournament['skill_level'])) ?></span>
    </div>

    <h1 class="t-display" style="font-size:clamp(2.25rem,5.5vw,4.25rem)"><?= esc($tournament['name']) ?></h1>
    <p class="t-lede" style="margin-top:1rem"><?= esc($tournament['description']) ?></p>
  </div>
</section>

<section class="section-tight">
  <div class="container">
    <div class="grid-auto">
      <div class="panel ticks readout">
        <span class="t-label">Plays</span>
        <span class="t-metric" style="font-size:1.375rem"><?= esc(date('d M', strtotime($tournament['starts_on']))) ?><?= $tournament['ends_on'] !== $tournament['starts_on'] ? '–' . esc(date('d M', strtotime($tournament['ends_on']))) : '' ?></span>
        <span class="t-small t-mute"><?= esc(date('Y', strtotime($tournament['starts_on']))) ?></span>
      </div>
      <div class="panel ticks readout">
        <span class="t-label">Entry fee</span>
        <span class="t-metric" style="font-size:1.5rem"><?= esc(peso($tournament['entry_fee'])) ?></span>
        <span class="t-small t-mute">Covers both partners</span>
      </div>
      <div class="panel ticks readout readout-accent">
        <span class="t-label">Prize pot</span>
        <span class="t-metric" style="font-size:1.5rem"><?= esc(peso($tournament['prize_pool'])) ?></span>
      </div>
      <div class="panel ticks readout">
        <span class="t-label">Draw</span>
        <span class="t-metric" style="font-size:1.5rem"><?= count($participants) ?><span class="t-mute">/<?= (int) $tournament['max_teams'] ?></span></span>
        <span class="t-small t-mute"><?= $spotsLeft > 0 ? $spotsLeft . ' spots left' : 'Full' ?></span>
      </div>
    </div>
  </div>
</section>

<?php if ($canRegister): ?>
<section class="section-tight">
  <div class="container">
    <div class="panel" style="border-color:var(--blue)">
      <h2 class="t-h3" style="margin-bottom:0.5rem">Enter your team</h2>
      <p class="t-small t-mute" style="margin:0 0 1.25rem;max-width:60ch">
        Registration holds your spot. Settle the <?= esc(peso($tournament['entry_fee'])) ?> at the desk to confirm it.
        Unconfirmed teams are released when the draw fills.
        <?php if ($tournament['registration_closes']): ?>
          Entries close <?= esc(date('d F', strtotime($tournament['registration_closes']))) ?>.
        <?php endif; ?>
      </p>

      <form method="post" action="<?= base_url('tournaments/' . $tournament['slug'] . '/register') ?>">
        <?= csrf_field() ?>
        <div class="row" style="gap:1rem;align-items:flex-end">
          <div class="field" style="flex:2;min-width:14rem">
            <label for="team_name">Team name</label>
            <input class="input" type="text" id="team_name" name="team_name" required maxlength="120"
                   placeholder="Dink Responsibly" value="<?= esc(old('team_name')) ?>">
          </div>
          <div class="field" style="flex:1;min-width:12rem">
            <label for="contact_phone">Contact mobile</label>
            <input class="input" type="tel" id="contact_phone" name="contact_phone" required
                   placeholder="+63 9xx xxx xxxx" value="<?= esc(old('contact_phone')) ?>">
          </div>
          <button class="btn btn-go" type="submit" style="margin-bottom:0.0625rem">Enter</button>
        </div>
      </form>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="section">
  <div class="container">
    <div style="display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1.4fr);gap:clamp(1.25rem,2.5vw,2rem);align-items:start" class="tourn-grid">

      <div class="panel panel-flush">
        <div class="panel-head">
          <h2 class="t-h3" style="margin:0">The draw</h2>
          <span class="t-label"><?= count($participants) ?> teams</span>
        </div>
        <?php if ($participants === []): ?>
          <div class="empty" style="border:0"><p style="margin:0">No entries yet. Be the first name on the sheet.</p></div>
        <?php else: ?>
          <div class="table-wrap">
            <table class="table" style="min-width:0">
              <thead><tr><th style="width:3rem">#</th><th>Team</th><th>Status</th></tr></thead>
              <tbody>
                <?php foreach ($participants as $p): ?>
                  <tr>
                    <td class="mono t-mute"><?= (int) $p['seed'] ?></td>
                    <td><?= esc($p['team_name']) ?></td>
                    <td>
                      <span class="chip tone-<?= $p['payment_status'] === 'paid' ? 'open' : 'filling' ?>">
                        <?= $p['payment_status'] === 'paid' ? 'Confirmed' : 'Unpaid' ?>
                      </span>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>

      <div class="panel panel-flush">
        <div class="panel-head">
          <h2 class="t-h3" style="margin:0">Bracket</h2>
          <span class="t-label"><?= esc($fmt) ?></span>
        </div>

        <?php if ($rounds === []): ?>
          <div class="empty" style="border:0">
            <p class="t-body" style="margin:0"><strong>The bracket goes up when entries close.</strong></p>
            <p class="t-small" style="margin:0">Seeding is by last season's points, then by DUPR.</p>
          </div>
        <?php else: ?>
          <div style="padding:1rem">
            <?php foreach ($rounds as $label => $matches): ?>
              <h3 class="t-label" style="margin:1.25rem 0 0.625rem;color:var(--fg)"><?= esc($label) ?></h3>
              <div class="stack-2">
                <?php foreach ($matches as $m): ?>
                  <div style="display:grid;grid-template-columns:1fr auto 1fr;gap:0.75rem;align-items:center;padding:0.625rem 0.875rem;background:var(--bg);border:1px solid var(--line);border-radius:var(--r)">
                    <span style="font-size:0.875rem;<?= $m['winner_id'] && (int) $m['winner_id'] === (int) $m['participant_a'] ? 'color:var(--green);font-weight:600' : 'color:var(--mute)' ?>">
                      <?= esc($m['team_a'] ?? 'TBD') ?>
                    </span>
                    <span class="mono" style="font-size:0.75rem;white-space:nowrap;color:var(--fg)">
                      <?= $m['score_a'] !== null ? esc($m['score_a']) . ' - ' . esc($m['score_b']) : 'vs' ?>
                    </span>
                    <span style="font-size:0.875rem;text-align:right;<?= $m['winner_id'] && (int) $m['winner_id'] === (int) $m['participant_b'] ? 'color:var(--green);font-weight:600' : 'color:var(--mute)' ?>">
                      <?= esc($m['team_b'] ?? 'TBD') ?>
                    </span>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<style>@media (max-width: 880px) { .tourn-grid { grid-template-columns: 1fr !important; } }</style>
<?= $this->endSection() ?>
