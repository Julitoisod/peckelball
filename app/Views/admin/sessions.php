<?= $this->extend($layout) ?>

<?= $this->section('content') ?>
<?php
/**
 * @var int  $coachId
 * @var bool $isCoach     the viewer has a coach profile of their own
 * @var bool $supervisor  the viewer holds coaches.manage
 * @var list<array<string,mixed>> $coaches   only populated for a supervisor
 * @var list<array<string,mixed>> $upcoming
 * @var list<array<string,mixed>> $past
 * @var float $earned
 * @var int   $taught
 */
$who = static fn (array $r): string => $r['first_name']
    ? $r['first_name'] . ' ' . $r['last_name']
    : ($r['guest_name'] ?: 'Guest');
$tone = static fn (string $s): string => match ($s) {
    'completed' => 'closed', 'confirmed' => 'open', 'cancelled' => 'booked', default => 'filling',
};
?>

<?php if ($supervisor && $coaches !== []): ?>
  <div class="panel" style="margin-bottom:1rem">
    <div class="row" style="gap:0.5rem;flex-wrap:wrap;align-items:center">
      <span class="t-label" style="flex:none">Diary for</span>
      <?php foreach ($coaches as $c): ?>
        <a class="btn btn-sm <?= (int) $c['id'] === $coachId ? '' : 'btn-ghost' ?>"
           href="<?= base_url('admin/sessions?coach=' . (int) $c['id']) ?>">
          <?= esc($c['first_name'] . ' ' . $c['last_name']) ?>
        </a>
      <?php endforeach; ?>
      <span class="t-small t-mute" style="margin-left:auto">
        You hold coaches.manage, so you can read any diary. A coach only ever sees their own.
      </span>
    </div>
  </div>
<?php endif; ?>

<?php if ($coachId === 0): ?>
  <div class="panel">
    <div class="empty" style="border:0">
      <p style="margin:0"><strong>No coach profile on this account.</strong></p>
      <p class="t-small t-mute" style="margin:0.25rem 0 0">
        This screen shows the sessions you are teaching. Ask a manager to link a coach profile to your account.
      </p>
    </div>
  </div>
<?php else: ?>
  <div class="kpi-grid" style="margin-bottom:1rem">
    <div class="kpi kpi-accent">
      <span class="t-label">Coming up</span>
      <span class="t-metric"><?= count($upcoming) ?></span>
      <span class="t-label">session<?= count($upcoming) === 1 ? '' : 's' ?> booked</span>
    </div>
    <div class="kpi">
      <span class="t-label">Taught</span>
      <span class="t-metric" style="font-size:1.5rem"><?= $taught ?></span>
      <span class="t-label">completed sessions</span>
    </div>
    <div class="kpi">
      <span class="t-label">Coaching fees earned</span>
      <span class="t-metric" style="font-size:1.5rem"><?= esc(peso($earned)) ?></span>
      <span class="t-label">on completed sessions</span>
    </div>
  </div>

  <div class="panel panel-flush" style="margin-bottom:1rem">
    <div class="panel-head">
      <h2 class="t-h3" style="margin:0">Coming up</h2>
      <span class="t-label"><?= count($upcoming) ?> to teach</span>
    </div>

    <?php if ($upcoming === []): ?>
      <div class="empty" style="border:0"><p style="margin:0">Nothing booked yet. Players pick you from the coaching page.</p></div>
    <?php else: ?>
      <div class="table-wrap">
        <table class="table">
          <thead>
            <tr><th>When</th><th>Court</th><th>Player</th><th>Contact</th><th>Type</th><th class="num">People</th><th class="num">Rate</th><th>State</th></tr>
          </thead>
          <tbody>
            <?php foreach ($upcoming as $s): ?>
              <tr<?= $s['session_date'] === business_date() ? ' style="background:color-mix(in oklch, var(--blue) 8%, transparent)"' : '' ?>>
                <td class="mono t-small" style="white-space:nowrap">
                  <?= esc(day_label($s['session_date'])) ?><br>
                  <span class="t-mute"><?= esc(clock($s['start_time'])) ?> to <?= esc(clock($s['end_time'])) ?></span>
                </td>
                <td class="mono"><?= esc($s['court_code'] ?? '-') ?></td>
                <td>
                  <?= esc($who($s)) ?>
                  <?php if ($s['skill_level']): ?><br><span class="t-label"><?= esc($s['skill_level']) ?></span><?php endif; ?>
                </td>
                <td class="mono t-small"><?= esc($s['phone'] ?: '-') ?></td>
                <td class="t-label"><?= esc($s['session_type']) ?></td>
                <td class="num"><?= (int) $s['participants'] ?></td>
                <td class="num"><?= esc(peso($s['rate'])) ?></td>
                <td><span class="chip tone-<?= $tone($s['status']) ?>"><?= esc(ucfirst(str_replace('_', ' ', $s['status']))) ?></span></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <div class="panel panel-flush">
    <div class="panel-head">
      <h2 class="t-h3" style="margin:0">Already taught</h2>
      <span class="t-label">most recent 30</span>
    </div>

    <?php if ($past === []): ?>
      <div class="empty" style="border:0"><p style="margin:0">No finished sessions yet.</p></div>
    <?php else: ?>
      <div class="table-wrap">
        <table class="table">
          <thead><tr><th>When</th><th>Court</th><th>Player</th><th>Type</th><th class="num">Rate</th><th>State</th></tr></thead>
          <tbody>
            <?php foreach ($past as $s): ?>
              <tr<?= $s['status'] === 'cancelled' ? ' style="opacity:0.55"' : '' ?>>
                <td class="mono t-small" style="white-space:nowrap">
                  <?= esc(date('d M Y', strtotime((string) $s['session_date']))) ?><br>
                  <span class="t-mute"><?= esc(clock($s['start_time'])) ?></span>
                </td>
                <td class="mono"><?= esc($s['court_code'] ?? '-') ?></td>
                <td><?= esc($who($s)) ?></td>
                <td class="t-label"><?= esc($s['session_type']) ?></td>
                <td class="num"><?= esc(peso($s['rate'])) ?></td>
                <td><span class="chip tone-<?= $tone($s['status']) ?>"><?= esc(ucfirst(str_replace('_', ' ', $s['status']))) ?></span></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
<?php endif; ?>
<?= $this->endSection() ?>
