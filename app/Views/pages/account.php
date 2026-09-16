<?= $this->extend($layout) ?>

<?= $this->section('content') ?>
<?= partial('partials/page_head', [
    'heading' => $user['first_name'] . "'s court",
    'lede'    => 'Everything you have booked, everything you have paid, and what is next.',
    'kicker'  => '<span class="chip tone-live">' . esc($user['role_name']) . '</span>'
        . '<span class="t-label">' . esc(ucfirst($user['skill_level'])) . ($user['dupr_rating'] ? ' · DUPR ' . esc(number_format((float) $user['dupr_rating'], 2)) : '') . '</span>',
    'actions' => '<a class="btn" href="' . base_url('booking') . '">Book a court</a>'
        . '<a class="btn btn-ghost" href="' . base_url('logout') . '">Sign out</a>',
]) ?>

<section class="section-tight">
  <div class="container">
    <div class="grid-auto">
      <div class="panel ticks readout">
        <span class="t-label">Sessions played</span>
        <span class="t-metric"><?= number_format($stats['total']) ?></span>
      </div>
      <div class="panel ticks readout readout-accent">
        <span class="t-label">Spent with us</span>
        <span class="t-metric"><?= esc(peso($stats['spend'])) ?></span>
      </div>
      <div class="panel ticks readout">
        <span class="t-label">Coaching sessions</span>
        <span class="t-metric"><?= number_format($stats['coached']) ?></span>
      </div>
      <?php if ($membership !== null): ?>
        <div class="panel ticks readout">
          <span class="t-label"><?= esc($membership['plan_name']) ?> credits left</span>
          <span class="t-metric"><?= (int) $membership['credits_remaining'] ?><span style="font-size:0.5em"> hrs</span></span>
          <span class="t-small t-mute">Renews <?= esc(date('d M Y', strtotime($membership['ends_on']))) ?></span>
        </div>
      <?php else: ?>
        <div class="panel ticks readout">
          <span class="t-label">Membership</span>
          <span class="t-metric" style="font-size:1.5rem">None</span>
          <a class="t-small" href="<?= base_url('membership') ?>" style="color:var(--blue-ink)">See the four plans →</a>
        </div>
      <?php endif; ?>
    </div>
  </div>
</section>

<section class="section-tight">
  <div class="container">
    <div style="display:grid;grid-template-columns:minmax(0,1.6fr) minmax(0,1fr);gap:clamp(1.25rem,2.5vw,2rem);align-items:start" class="acct-grid">
      <div class="panel panel-flush">
        <div class="panel-head">
          <h2 class="t-h3" style="margin:0">Coming up</h2>
          <a class="t-label" href="<?= base_url('account/bookings') ?>" style="color:var(--blue-ink)">All bookings →</a>
        </div>

        <?php if ($upcoming === []): ?>
          <div class="empty" style="border:0;border-radius:0">
            <p class="t-body" style="margin:0"><strong>Nothing booked.</strong></p>
            <p class="t-small" style="margin:0">The board is live right now. Take a slot before the evening block goes.</p>
            <a class="btn btn-go btn-sm" style="margin-top:0.5rem" href="<?= base_url('booking') ?>">Book a court</a>
          </div>
        <?php else: ?>
          <div class="table-wrap">
            <table class="table">
              <thead>
                <tr><th>When</th><th>Court</th><th>Time</th><th>Status</th><th class="num">Total</th></tr>
              </thead>
              <tbody>
                <?php foreach ($upcoming as $b): ?>
                  <?php $s = booking_status_meta($b['status']); ?>
                  <tr>
                    <td><?= esc(day_label($b['booking_date'])) ?></td>
                    <td><a href="<?= base_url('courts/' . $b['court_slug']) ?>"><?= esc($b['court_code']) ?></a></td>
                    <td class="mono"><?= esc(clock($b['start_time'])) ?>–<?= esc(clock($b['end_time'])) ?></td>
                    <td><span class="chip tone-<?= esc($s['tone']) ?>"><?= esc($s['label']) ?></span></td>
                    <td class="num"><?= esc(peso($b['total_amount'])) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>

      <div class="panel panel-flush">
        <div class="panel-head">
          <h2 class="t-h3" style="margin:0">Notifications</h2>
        </div>
        <?php if ($notifications === []): ?>
          <div class="empty" style="border:0"><p style="margin:0">Nothing yet.</p></div>
        <?php else: ?>
          <div>
            <?php foreach ($notifications as $n): ?>
              <div style="padding:0.875rem 1.125rem;border-bottom:1px solid color-mix(in oklch, var(--line) 55%, transparent)">
                <div class="row" style="gap:0.5rem;margin-bottom:0.25rem">
                  <span class="chip tone-<?= $n['read_at'] === null ? 'live' : 'closed' ?>" style="padding:0.0625rem 0.375rem"><?= esc($n['type']) ?></span>
                  <span class="t-label"><?= esc(date('d M, g:i A', strtotime($n['created_at']))) ?></span>
                </div>
                <strong style="font-size:0.875rem;display:block"><?= esc($n['title']) ?></strong>
                <?php if ($n['body']): ?>
                  <p class="t-small t-mute" style="margin:0.1875rem 0 0"><?= esc($n['body']) ?></p>
                <?php endif; ?>
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
<style>@media (max-width: 900px) { .acct-grid { grid-template-columns: 1fr !important; } }</style>
<?= $this->endSection() ?>
