<?php
/**
 * Where to send a GCash payment. Shown wherever GCash is the chosen method:
 * on the review step of both booking cards, and again on the confirmation so
 * the player can still pay after they have left the page.
 *
 * The QR is a file, `assets/img/gcash-qr.jpg` — replace that file to change
 * the account and nothing else needs touching. Crop a new one to the code
 * itself: the app screenshot GCash exports is mostly chrome, and the code in
 * it ends up too small to scan off a phone held up to a phone. The name and
 * number beside it come from Settings, and are left out while they are blank.
 *
 * @var string|null $amount  pre-formatted, e.g. "₱400"
 */
$gcashName   = (string) setting('payments.gcash_name', '');
$gcashNumber = (string) setting('payments.gcash_number', '');
$amount      = $amount ?? null;
?>
<div class="gcash-pay">
  <img src="<?= base_url('assets/img/gcash-qr.jpg') ?>" alt="GCash QR code for Go Paddle"
       width="560" height="560" loading="lazy" decoding="async">
  <div class="gcash-pay-copy">
    <span class="t-label">Send with GCash</span>
    <p class="t-small" style="margin:0.25rem 0 0" data-gcash-line>
      Scan the QR in your GCash app<?= $amount !== null ? ' and send ' . esc($amount) : '' ?>.
    </p>
    <?php if ($gcashName !== '' || $gcashNumber !== ''): ?>
      <dl class="gcash-pay-who">
        <?php if ($gcashName !== ''): ?>
          <dt>Account</dt><dd><?= esc($gcashName) ?></dd>
        <?php endif; ?>
        <?php if ($gcashNumber !== ''): ?>
          <dt>Number</dt><dd class="mono"><?= esc($gcashNumber) ?></dd>
        <?php endif; ?>
      </dl>
    <?php endif; ?>
    <p class="t-small t-mute" style="margin:0.5rem 0 0">
      Keep the receipt and show it at the desk when you arrive. Transfer fees may apply.
    </p>
  </div>
</div>
