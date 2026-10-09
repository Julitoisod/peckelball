<?= $this->extend($layout) ?>

<?= $this->section('content') ?>
<?php
/**
 * Sign in. One centred column on the scanline field: the lockup, the heading
 * and the form read as a single stack instead of a logo pasted above a
 * left-aligned page.
 */
?>
<section class="auth field-bg field-scan">
  <div class="auth-inner">
    <div class="auth-head">
      <img class="auth-logo" src="<?= base_url('assets/img/logo-go-paddle.png') ?>"
           alt="Go Paddle Court — play, compete, connect" width="640" height="640">
      <h1 class="t-h2" style="margin:0 0 0.375rem">Sign in</h1>
      <p class="t-small t-mute" style="margin:0">Your bookings, your membership, your history.</p>
    </div>

    <form method="post" action="<?= base_url('login') ?>" class="panel ticks stack">
      <?= csrf_field() ?>
      <input type="hidden" name="next" value="<?= esc($next) ?>">

      <div class="field">
        <label for="email">Email</label>
        <input class="input" type="email" id="email" name="email" required autocomplete="email"
               autofocus value="<?= esc(old('email')) ?>">
      </div>

      <div class="field">
        <label for="password">Password</label>
        <input class="input" type="password" id="password" name="password" required autocomplete="current-password">
      </div>

      <button class="btn btn-block btn-lg" type="submit">Sign in</button>
    </form>

    <p class="t-small t-mute auth-foot">
      No account? <a href="<?= base_url('register') ?>" style="color:var(--blue-ink)">Create one</a>.
      It takes a minute and makes booking three taps.
    </p>

    <?php /* Seeded install only, and folded away: it is a crib sheet, not part
             of the page. The client has been told it is public. */ ?>
    <details class="auth-demo">
      <summary>
        <span class="t-label">Demo accounts</span>
        <span class="t-label auth-demo-cue">Show</span>
      </summary>
      <p class="t-small t-mute" style="margin:0.75rem 0 0.5rem">
        A seeded install. Any of these work with the password
        <span class="mono" style="color:var(--fg)">Bernie2026!</span>
      </p>
      <dl class="auth-demo-list">
        <?php foreach ([
            ['Owner', 'bernie@berniepickleball.ph'],
            ['Manager', 'maricel@berniepickleball.ph'],
            ['Receptionist', 'frontdesk@berniepickleball.ph'],
            ['Coach', 'jomar@berniepickleball.ph'],
            ['Maintenance', 'facilities@berniepickleball.ph'],
            ['Player', 'player@berniepickleball.ph'],
        ] as [$role, $email]): ?>
          <dt><?= esc($role) ?></dt>
          <dd class="mono"><?= esc($email) ?></dd>
        <?php endforeach; ?>
      </dl>
    </details>
  </div>
</section>
<?= $this->endSection() ?>
