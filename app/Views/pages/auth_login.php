<?= $this->extend($layout) ?>

<?= $this->section('content') ?>
<section class="section">
  <div class="container" style="max-width:26rem">
    <img src="<?= base_url('assets/img/logo-go-paddle.png') ?>" alt="Go Paddle Court — play, compete, connect"
         width="640" height="640" style="width:9rem;height:auto;border-radius:var(--r-lg);margin-bottom:1.25rem">
    <h1 class="t-h1" style="margin-bottom:0.5rem">Sign in</h1>
    <p class="t-lede" style="margin:0 0 1.75rem">Your bookings, your membership, your history.</p>

    <form method="post" action="<?= base_url('login') ?>" class="panel stack">
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

      <button class="btn btn-block" type="submit">Sign in</button>

      <p class="t-small t-mute" style="margin:0;text-align:center">
        No account? <a href="<?= base_url('register') ?>" style="color:var(--blue-ink)">Create one</a>.
        It takes a minute and makes booking three taps.
      </p>
    </form>

    <div class="panel" style="margin-top:1rem;background:var(--bg)">
      <span class="t-label" style="color:var(--fg)">Demo accounts</span>
      <p class="t-small t-mute" style="margin:0.5rem 0 0.75rem">
        This is a seeded install. Any of these work with the password
        <span class="mono" style="color:var(--fg)">Bernie2026!</span>
      </p>
      <div class="table-wrap">
        <table class="table" style="min-width:0;font-size:0.75rem">
          <tbody>
            <?php foreach ([
                ['Owner', 'bernie@berniepickleball.ph'],
                ['Manager', 'maricel@berniepickleball.ph'],
                ['Receptionist', 'frontdesk@berniepickleball.ph'],
                ['Coach', 'jomar@berniepickleball.ph'],
                ['Maintenance', 'facilities@berniepickleball.ph'],
                ['Player', 'player@berniepickleball.ph'],
            ] as [$role, $email]): ?>
              <tr>
                <td class="t-label" style="width:8rem"><?= esc($role) ?></td>
                <td class="mono"><?= esc($email) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</section>
<?= $this->endSection() ?>
