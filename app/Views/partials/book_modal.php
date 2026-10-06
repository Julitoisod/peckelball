<?php
/**
 * Quick-book card — the whole booking, without leaving the page.
 *
 * Step 1 picks the court, the day and as many back-to-back hours as the
 * player wants. Step 2 takes the names of who is playing, a mobile number
 * and how they are paying, in the same card.
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
$modalUser    = $auth->user();
$modalMethods = [
    ['cash', 'Cash', 'At the desk'],
    ['gcash', 'GCash', 'On arrival'],
];
?>
<dialog class="book-modal" data-book-modal aria-labelledby="book-modal-title">
  <div class="book-modal-head">
    <button class="book-modal-close" type="button" data-book-back hidden aria-label="Back to the times">&larr;</button>
    <h2 class="t-h3" id="book-modal-title" data-book-title>Book a court</h2>
    <button class="book-modal-close" type="button" data-book-close aria-label="Close">&times;</button>
  </div>

  <form method="post" action="<?= base_url('booking') ?>" data-book-form>
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
      <?php else: ?>
        <div class="alert tone-open">
          <b>Signed in</b>
          <span>Booking as <?= esc($auth->name()) ?><?= $modalUser['phone'] ? ' · ' . esc($modalUser['phone']) : '' ?>.</span>
        </div>
      <?php endif; ?>

      <div class="field">
        <span class="field-label">Payment</span>
        <div class="tiles" role="radiogroup" aria-label="Payment method">
          <?php foreach ($modalMethods as [$key, $name, $note]): ?>
            <label class="tile">
              <input type="radio" name="payment_method" value="<?= $key ?>" <?= $key === 'cash' ? 'checked' : '' ?>>
              <span class="tile-face"><b><?= $name ?></b><span><?= $note ?></span></span>
            </label>
          <?php endforeach; ?>
        </div>
        <span class="field-hint">
          Nothing is charged online. You settle at the desk. Cancel free up to
          <?= esc(setting('booking.cutoff_minutes', 30)) ?> minutes before start.
        </span>
      </div>

      <div class="field">
        <label for="book-modal-notes">Anything we should know <span class="t-mute">(optional)</span></label>
        <textarea class="textarea" id="book-modal-notes" name="notes" maxlength="500" rows="2"
                  style="min-height:4.5rem" placeholder="First time here, bringing a junior"></textarea>
      </div>
    </div>

    <div class="book-modal-foot">
      <div class="readout">
        <span class="t-label" data-book-summary>Pick a play time</span>
        <span class="t-h3" data-book-price></span>
      </div>
      <button class="btn btn-lg" type="button" data-book-continue aria-disabled="true">Continue</button>
      <button class="btn btn-go btn-lg" type="submit" data-book-submit hidden>Confirm booking</button>
    </div>
  </form>
</dialog>
