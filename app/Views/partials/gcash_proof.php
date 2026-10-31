<?php
/**
 * The two things a player must hand over before a booking can go through: the
 * reference on their GCash receipt and a screenshot of it. Booking::store()
 * refuses the booking without them, whatever the browser let through.
 *
 * @var string      $prefix    keeps the ids apart: both booking forms can be on one page
 * @var bool|null   $required  native validation, for forms with every field on screen
 * @var string|null $value     the reference, when the form comes back with an error
 */
$required = ! empty($required) ? 'required' : '';
?>
<div class="stack" style="margin-top:1rem">
  <div class="field">
    <label for="<?= esc($prefix) ?>-gcash-reference">GCash reference number</label>
    <input class="input mono" type="text" id="<?= esc($prefix) ?>-gcash-reference" name="gcash_reference"
           maxlength="40" autocomplete="off" placeholder="0000 000 000000"
           value="<?= esc($value ?? '') ?>" <?= $required ?>>
  </div>

  <div class="field">
    <label for="<?= esc($prefix) ?>-payment-proof">Screenshot of the receipt</label>
    <input class="input" type="file" id="<?= esc($prefix) ?>-payment-proof" name="payment_proof"
           accept="image/jpeg,image/png,image/webp" <?= $required ?>>
    <span class="field-hint">JPG, PNG or WebP, up to 8 MB.</span>
  </div>
</div>
