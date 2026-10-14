<?= $this->extend($layout) ?>

<?= $this->section('content') ?>
<?php
/**
 * The Android download. One button and the three taps that follow it, because
 * a file from outside the Play Store needs a sentence about the warning screen
 * or people stop at it.
 *
 * @var string|null $size   read off the file, so it cannot be wrong
 * @var string|null $built
 */
?>
<section class="section field-bg field-scan">
  <div class="container" style="max-width:40rem">
    <div style="text-align:center">
      <img src="<?= base_url('assets/img/logo-go-paddle.png') ?>" alt="" width="640" height="640"
           style="width:7rem;height:auto;border-radius:var(--r-lg);border:1px solid var(--line);margin:0 auto 1.25rem">
      <h1 class="t-h2" style="margin:0 0 0.5rem">Go Paddle on your phone</h1>
      <p class="t-small t-mute" style="margin:0 0 1.5rem">
        The same courts, the same bookings, one tap from the home screen. No account needed to install it.
      </p>
    </div>

    <div class="panel ticks stack" style="text-align:center">
      <?php if ($size !== null): ?>
        <a class="btn btn-lg btn-block" href="<?= base_url('downloads/go-paddle.apk') ?>" download>
          Download for Android
        </a>
        <p class="t-label" style="margin:0">
          <?= esc($size) ?> &middot; Android 7.0 and up &middot; Built <?= esc($built) ?>
        </p>
      <?php else: ?>
        <p class="t-small t-mute" style="margin:0">
          The build is being replaced right now. Try again in a few minutes, or ask the desk.
        </p>
      <?php endif; ?>
    </div>

    <div class="panel stack-2" style="margin-top:1rem;background:var(--bg)">
      <span class="t-label" style="color:var(--fg)">After it downloads</span>
      <ol class="t-small t-mute" style="margin:0;padding-left:1.25rem;line-height:1.7">
        <li>Open the file. Android asks whether to allow installing from this source. Say yes, it only
          applies to this one.</li>
        <li>Tap Install, then Open.</li>
        <li>It opens straight on the availability board, no sign-in screen in the way.</li>
      </ol>
      <p class="field-hint" style="margin:0.25rem 0 0">
        It is this website in an app window, so whatever the desk changes here shows up there at once,
        and it takes no space worth counting.
      </p>
    </div>

    <div class="panel stack-2" style="margin-top:1rem;background:var(--bg)">
      <span class="t-label" style="color:var(--fg)">On an iPhone</span>
      <p class="t-small t-mute" style="margin:0">
        There is no iPhone build. Open this site in Safari, tap Share, then <strong>Add to Home Screen</strong>.
        You get the same icon and the same full screen.
      </p>
    </div>
  </div>
</section>
<?= $this->endSection() ?>
