<?= $this->extend($layout) ?>

<?= $this->section('content') ?>
<?= partial('partials/page_head', [
    'heading' => 'My bookings',
    'lede'    => 'Cancel free up to ' . esc(setting('booking.cutoff_minutes', 30)) . ' minutes before start. Inside that, call the desk.',
    'kicker'  => '<span class="t-label">' . count($upcoming) . ' upcoming · ' . count($past) . ' in history</span>',
    'actions' => '<a class="btn" href="' . base_url('booking') . '">Book a court</a>',
]) ?>

<section class="section-tight">
  <div class="container">
    <div class="panel panel-flush" style="margin-bottom:1.5rem">
      <div class="panel-head"><h2 class="t-h3" style="margin:0">Upcoming</h2></div>

      <?php if ($upcoming === []): ?>
        <div class="empty" style="border:0">
          <p class="t-body" style="margin:0"><strong>No bookings ahead of you.</strong></p>
          <a class="btn btn-go btn-sm" style="margin-top:0.5rem" href="<?= base_url('booking') ?>">Take a slot</a>
        </div>
      <?php else: ?>
        <div class="table-wrap">
          <table class="table">
            <thead>
              <tr><th>Reference</th><th>When</th><th>Court</th><th>Time</th><th>Status</th><th class="num">Total</th><th></th></tr>
            </thead>
            <tbody>
              <?php foreach ($upcoming as $b): ?>
                <?php
                $s = booking_status_meta($b['status']);
                $cutoff = service('siteSettings')->int('booking.cutoff_minutes', 30);
                $canCancel = strtotime($b['booking_date'] . ' ' . $b['start_time']) > time() + $cutoff * 60;
                ?>
                <tr>
                  <td class="mono"><a href="<?= base_url('booking/confirmed/' . $b['reference']) ?>"><?= esc($b['reference']) ?></a></td>
                  <td><?= esc(day_label($b['booking_date'])) ?><br><span class="t-label"><?= esc(date('d M Y', strtotime($b['booking_date']))) ?></span></td>
                  <td><a href="<?= base_url('courts/' . $b['court_slug']) ?>"><?= esc($b['court_code']) ?></a><br><span class="t-label"><?= esc($b['court_name']) ?></span></td>
                  <td class="mono"><?= esc(clock($b['start_time'])) ?>–<?= esc(clock($b['end_time'])) ?></td>
                  <td><span class="chip tone-<?= esc($s['tone']) ?>"><?= esc($s['label']) ?></span></td>
                  <td class="num"><?= esc(peso($b['total_amount'])) ?><br><span class="t-label"><?= esc($b['payment_status']) ?></span></td>
                  <td>
                    <?php if ($canCancel): ?>
                      <form method="post" action="<?= base_url('account/bookings/' . (int) $b['id'] . '/cancel') ?>"
                            onsubmit="return confirm('Cancel <?= esc($b['reference']) ?>? The slot goes straight back on the board.')">
                        <?= csrf_field() ?>
                        <button class="btn btn-ghost btn-sm" type="submit">Cancel</button>
                      </form>
                    <?php else: ?>
                      <span class="t-label">Call the desk</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

    <div class="panel panel-flush">
      <div class="panel-head"><h2 class="t-h3" style="margin:0">History</h2></div>

      <?php if ($past === []): ?>
        <div class="empty" style="border:0"><p style="margin:0">Nothing played yet.</p></div>
      <?php else: ?>
        <div class="table-wrap">
          <table class="table">
            <thead>
              <tr><th>Reference</th><th>Date</th><th>Court</th><th>Time</th><th>Status</th><th class="num">Total</th></tr>
            </thead>
            <tbody>
              <?php foreach ($past as $b): ?>
                <?php $s = booking_status_meta($b['status']); ?>
                <tr>
                  <td class="mono"><?= esc($b['reference']) ?></td>
                  <td class="mono"><?= esc(date('d M Y', strtotime($b['booking_date']))) ?></td>
                  <td><?= esc($b['court_code']) ?></td>
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
  </div>
</section>
<?= $this->endSection() ?>
