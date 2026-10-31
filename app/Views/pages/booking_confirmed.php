<?= $this->extend($layout) ?>

<?= $this->section('content') ?>
<?php
$status  = booking_status_meta($booking['status']);
// The real calendar day: the 12:00 AM hour of a session falls on the next one.
$start   = service('availability')->slotTimestamp($booking['booking_date'], substr($booking['start_time'], 0, 5));
$waiting = $booking['status'] === 'pending';
$dropped = $booking['status'] === 'cancelled';
$settled = $booking['payment_status'] === 'paid';
?>

<section class="section">
  <div class="container" style="max-width:52rem">

    <div class="panel ticks" style="padding:clamp(1.5rem,3vw,2.5rem)">
      <div class="row" style="gap:0.625rem;margin-bottom:1.25rem">
        <span class="chip tone-<?= esc($status['tone']) ?>"><?= $waiting ? 'Waiting for approval' : esc($status['label']) ?></span>
      </div>

      <h1 class="t-h1" style="margin-bottom:0.75rem">
        <?= esc($booking['court_name']) ?>,<br>
        <?= esc(day_label(date('Y-m-d', $start))) ?> at <?= esc(clock($booking['start_time'])) ?>
      </h1>
      <p class="t-lede" style="margin:0">
        <?php if ($waiting): ?>
          We have your GCash reference and screenshot. This slot is not booked yet: it stays open until the desk has
          checked your payment. We will email you then, and this page changes too.
        <?php elseif ($dropped): ?>
          This booking is off and the court has gone back on the board.
          <?= esc($booking['cancellation_reason'] ?? '') ?>
        <?php else: ?>
          Show this reference at the desk. Arrive five minutes early and we will have the net checked and the balls out.
        <?php endif; ?>
      </p>

      <hr class="rule-strong" style="margin:1.75rem 0">

      <div class="grid-auto" style="gap:1.25rem">
        <div class="readout">
          <span class="t-label">Reference</span>
          <span class="t-metric" style="font-size:1.5rem;letter-spacing:0.01em"><?= esc($booking['reference']) ?></span>
        </div>
        <div class="readout">
          <span class="t-label">Court</span>
          <span class="t-metric" style="font-size:1.5rem"><?= esc($booking['court_code']) ?></span>
        </div>
        <div class="readout">
          <span class="t-label">Time</span>
          <span class="t-metric" style="font-size:1.5rem"><?= esc(clock($booking['start_time'])) ?>–<?= esc(clock($booking['end_time'])) ?></span>
        </div>
        <div class="readout readout-accent">
          <span class="t-label"><?= $settled ? 'Total paid' : ($waiting ? 'Total sent' : 'Total due at the desk') ?></span>
          <span class="t-metric" style="font-size:1.5rem"><?= esc(peso($booking['total_amount'])) ?></span>
        </div>
      </div>

      <hr class="rule" style="margin:1.75rem 0">

      <div class="table-wrap">
        <table class="table" style="min-width:0">
          <tbody>
            <tr><td class="t-label" style="width:14rem">Date</td><td class="mono"><?= esc(date('l, d F Y', $start)) ?></td></tr>
            <tr><td class="t-label">Duration</td><td class="mono"><?= (int) $booking['duration_minutes'] ?> minutes</td></tr>
            <tr><td class="t-label">Players</td><td class="mono"><?= (int) $booking['player_count'] ?></td></tr>
            <?php if ($coach !== null): ?>
              <tr><td class="t-label">Coach</td><td class="mono"><?= esc($coach['first_name'] . ' ' . $coach['last_name']) ?> · <?= esc(peso($booking['coaching_subtotal'])) ?></td></tr>
            <?php endif; ?>
            <?php foreach ($items as $item): ?>
              <tr><td class="t-label">Rental</td><td class="mono"><?= (int) $item['qty'] ?> × <?= esc($item['name']) ?> · <?= esc(peso($item['subtotal'])) ?></td></tr>
            <?php endforeach; ?>
            <tr><td class="t-label">Court fee</td><td class="amount"><?= esc(peso($booking['court_subtotal'])) ?></td></tr>
            <?php if ((float) $booking['discount_amount'] > 0): ?>
              <tr><td class="t-label">Discount</td><td class="amount" style="color:var(--green)">− <?= esc(peso($booking['discount_amount'])) ?></td></tr>
            <?php endif; ?>
            <tr><td class="t-label">Payment</td><td class="mono"><?= esc(strtoupper($booking['payment_method'])) ?> · <?= $settled ? 'Settled' : ($waiting ? 'Being checked' : 'Due at the desk') ?></td></tr>
            <?php if ($owner || $booking['guest_name']): ?>
              <tr><td class="t-label">Booked by</td><td class="mono"><?= esc($owner ? $auth->name() : $booking['guest_name']) ?></td></tr>
            <?php endif; ?>
            <?php $players = pipes($booking['player_names'] ?? null); ?>
            <?php if ($players !== []): ?>
              <tr>
                <td class="t-label">Playing</td>
                <td class="mono"><?= esc(implode(', ', $players)) ?></td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <div class="row" style="gap:0.75rem;margin-top:1.75rem">
        <a class="btn" href="<?= base_url($auth->check() ? 'account/bookings' : 'availability') ?>">
          <?= $auth->check() ? 'My bookings' : 'Book another' ?>
        </a>
        <a class="btn btn-ghost" href="<?= esc(setting('facility.maps_url', '#')) ?>" target="_blank" rel="noopener">Directions</a>
        <button class="btn btn-ghost" type="button" onclick="window.print()">Print</button>
      </div>
    </div>

    <?php // ponytail: the scanner shipped and reads this reference, typed or
          // scanned off a barcode gun. A QR *image* still needs an encoder,
          // which is a real dependency this project does not carry (no composer,
          // CI4 is vendored by hand), so that stays a decision, not a TODO. ?>
    <?php if (! $waiting && ! $dropped): ?>
    <div class="panel" style="margin-top:1.25rem">
      <div class="row" style="gap:1.5rem;align-items:center">
        <div style="flex:none;padding:1rem 1.25rem;background:var(--bg-deep);border:2px solid var(--blue);border-radius:var(--r)">
          <span class="t-label" style="display:block;margin-bottom:0.25rem">Show at the desk</span>
          <span class="mono" style="font-size:clamp(1.25rem,3vw,1.75rem);font-weight:600;letter-spacing:0.04em;color:var(--fg)"><?= esc($booking['reference']) ?></span>
        </div>
        <div>
          <h2 class="t-h3" style="margin-bottom:0.375rem">Check in without queueing</h2>
          <p class="t-small t-mute" style="margin:0;max-width:44ch">
            Read the reference to whoever is on the desk and they will have you on
            <?= esc($booking['court_code']) ?> in seconds. No printout needed.
          </p>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <?php if (! $auth->check()): ?>
      <div class="alert tone-live" style="margin-top:1.25rem">
        <b>Tip</b>
        <span><a href="<?= base_url('register') ?>">Create an account</a> and your next booking is three taps, plus you can cancel and rebook yourself.</span>
      </div>
    <?php endif; ?>
  </div>
</section>
<?= $this->endSection() ?>
