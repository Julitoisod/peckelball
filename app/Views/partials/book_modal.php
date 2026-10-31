<?php
/**
 * Quick-book card — the whole booking, without leaving the page.
 *
 * Step 1 picks the court, the day and as many back-to-back hours as the
 * player wants. Step 2 takes the names of who is playing and a mobile number.
 * Step 3 reads the whole thing back with the total and the GCash QR, and the
 * player hands in the reference and a screenshot of the receipt. The booking
 * then waits until the desk approves the payment, which is what takes the court.
 * Anything to tell the desk goes on the full booking page, which still has a
 * notes box.
 * Submitting posts the ordinary booking form to /booking, so the server
 * validates, re-prices and guards the slots exactly as it does for the full
 * page, and the player lands on the confirmation.
 *
 * A native <dialog> on purpose: focus trapping, Escape and the backdrop come
 * free. Step 1 is empty markup — courts come from /api/availability and the
 * free hours from /api/slots, so nothing here can drift from the real board.
 * With JavaScript off the trigger links are ordinary links to /booking.
 *
 * @var \App\Services\Auth $auth
 */
$modalUser = $auth->user();
?>
<dialog class="book-modal" data-book-modal aria-labelledby="book-modal-title">
  <div class="book-modal-head">
    <button class="book-modal-close" type="button" data-book-back hidden aria-label="Back to the times">&larr;</button>
    <h2 class="t-h3" id="book-modal-title" data-book-title>Book a court</h2>
    <button class="book-modal-close" type="button" data-book-close aria-label="Close">&times;</button>
  </div>

  <form method="post" action="<?= base_url('booking') ?>" enctype="multipart/form-data" data-book-form>
    <?= csrf_field() ?>
    <input type="hidden" name="court_id" data-book-field="court">
    <input type="hidden" name="date" data-book-field="date">
    <input type="hidden" name="duration" data-book-field="duration">
    <input type="hidden" name="start_time" data-book-field="start">
    <?php // The head count follows the names; this is only the fallback. ?>
    <input type="hidden" name="player_count" value="1">

    <div class="book-modal-body" data-book-step="1">
      <div class="field">
        <span class="field-label">Court</span>
        <div class="tiles" data-book-courts role="radiogroup" aria-label="Court"></div>
      </div>

      <div class="field">
        <label for="book-modal-date">Date</label>
        <input class="input" type="date" id="book-modal-date" data-book-date>
      </div>

      <div class="field">
        <span class="field-label">Play time</span>
        <span class="field-hint">Tap every hour you want. They have to run back to back.</span>
        <div class="book-modal-times" data-book-times aria-live="polite"></div>
      </div>
    </div>

    <div class="book-modal-body" data-book-step="2" hidden>
      <?php /* One row per player. The first is the contact — for a guest
               booking the server copies it into guest_name, which is the name
               every existing screen already reads. Add rows up to eight. */ ?>
      <div class="field">
        <span class="field-label">Name</span>
        <div class="book-modal-names" data-book-names>
          <div class="book-modal-name-row">
            <input class="input" type="text" name="player_names[]" aria-label="Player 1"
                   autocomplete="name" maxlength="120"
                   value="<?= $modalUser !== null ? esc($auth->name()) : '' ?>">
          </div>
        </div>
        <button class="btn btn-ghost btn-sm" type="button" data-book-add-name style="align-self:flex-start">
          + Add another name
        </button>
      </div>

      <?php if ($modalUser === null): ?>
        <div class="field">
          <label for="book-modal-phone">Mobile</label>
          <input class="input" type="tel" id="book-modal-phone" name="guest_phone" autocomplete="tel" placeholder="+63 9xx xxx xxxx" maxlength="30">
        </div>
        <div class="field">
          <label for="book-modal-email">Email</label>
          <input class="input" type="email" id="book-modal-email" name="guest_email" autocomplete="email" placeholder="you@gmail.com" maxlength="160">
          <span class="field-hint">We email you when your booking is approved.</span>
        </div>
      <?php else: ?>
        <div class="alert tone-open">
          <b>Signed in</b>
          <span>Booking as <?= esc($auth->name()) ?><?= $modalUser['phone'] ? ' · ' . esc($modalUser['phone']) : '' ?>.</span>
        </div>
      <?php endif; ?>

      <span class="field-hint">Online bookings are paid by GCash. The QR is on the next step.</span>
    </div>

    <?php /* Step 3: what it costs, read back before anything is booked. The
             rows are filled from the form by app.js — the price shown is the
             one the API gave for each hour, and the server re-prices it on
             submit regardless. */ ?>
    <div class="book-modal-body" data-book-step="3" hidden>
      <dl class="book-modal-review">
        <dt>Court</dt><dd data-review="court"></dd>
        <dt>Day</dt><dd data-review="date"></dd>
        <dt>Play time</dt><dd data-review="time"></dd>
        <dt>Playing</dt><dd data-review="players"></dd>
        <dt data-review-label="mobile">Mobile</dt><dd data-review="mobile"></dd>
        <dt data-review-label="email">Email</dt><dd data-review="email"></dd>
        <dt>Payment</dt><dd>GCash</dd>
      </dl>

      <div class="book-modal-total">
        <span class="t-label">Total to send</span>
        <span class="t-metric" data-review="total"></span>
      </div>

      <div data-review-gcash>
        <?= partial('partials/gcash_pay', ['note' => 'Take a screenshot of the receipt. You need it and the reference number below.']) ?>
      </div>

      <?= partial('partials/gcash_proof', ['prefix' => 'book-modal']) ?>

      <span class="field-hint" style="margin-top:0.75rem;display:block">
        This does not book the slot yet. It is booked when the desk approves your payment, then we email you.
      </span>
    </div>

    <div class="book-modal-foot">
      <div class="readout">
        <span class="t-label" data-book-summary>Pick a play time</span>
        <span class="t-h3" data-book-price></span>
      </div>
      <button class="btn btn-lg" type="button" data-book-continue aria-disabled="true">Continue</button>
      <button class="btn btn-lg" type="button" data-book-review hidden>See the total</button>
      <button class="btn btn-go btn-lg" type="submit" data-book-submit hidden>Send for approval</button>
    </div>
  </form>
</dialog>
