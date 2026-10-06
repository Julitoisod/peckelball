<?php
/**
 * Quick-book card. Opens over whatever page you are on when a plain
 * "Book a court" link is clicked; picks the court, the day, the length and
 * the play time, then hands those four choices to /booking for the name and
 * the payment.
 *
 * A native <dialog> on purpose: focus trapping, Escape and the backdrop come
 * free. The shell is empty markup — courts come from /api/availability and
 * play times from /api/slots, so nothing here can drift from the real board.
 * With JavaScript off the trigger links are ordinary links to /booking.
 */
?>
<dialog class="book-modal" data-book-modal aria-labelledby="book-modal-title">
  <form method="dialog" class="book-modal-head">
    <h2 class="t-h3" id="book-modal-title">Book a court</h2>
    <button class="book-modal-close" value="close" aria-label="Close">&times;</button>
  </form>

  <div class="book-modal-body">
    <div class="field">
      <span class="field-label">Court</span>
      <div class="tiles" data-book-courts role="radiogroup" aria-label="Court"></div>
    </div>

    <div class="row" style="gap:0.75rem;align-items:flex-end">
      <div class="field" style="flex:1;min-width:9rem">
        <label for="book-modal-date">Date</label>
        <input class="input" type="date" id="book-modal-date" data-book-date>
      </div>
      <div class="field" style="flex:1;min-width:9rem">
        <span class="field-label">Length</span>
        <div class="tiles" data-book-durations role="radiogroup" aria-label="Length"></div>
      </div>
    </div>

    <div class="field">
      <span class="field-label">Play time</span>
      <div class="book-modal-times" data-book-times aria-live="polite"></div>
    </div>
  </div>

  <div class="book-modal-foot">
    <div class="readout">
      <span class="t-label" data-book-summary>Pick a play time</span>
      <span class="t-h3" data-book-price></span>
    </div>
    <a class="btn btn-lg" data-book-continue href="<?= base_url('booking') ?>" aria-disabled="true">Continue</a>
  </div>
</dialog>
