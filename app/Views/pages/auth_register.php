<?= $this->extend($layout) ?>

<?= $this->section('content') ?>
<section class="section">
  <div class="container" style="max-width:32rem">
    <h1 class="t-h1" style="margin-bottom:0.5rem">Create a player account</h1>
    <p class="t-lede" style="margin:0 0 1.75rem">
      Free. Books faster, cancels itself, remembers your court.
    </p>

    <form method="post" action="<?= base_url('register') ?>" class="panel stack">
      <?= csrf_field() ?>

      <div class="row" style="gap:1rem;align-items:flex-start">
        <div class="field" style="flex:1;min-width:10rem">
          <label for="first_name">First name</label>
          <input class="input" type="text" id="first_name" name="first_name" required autocomplete="given-name" value="<?= esc(old('first_name')) ?>">
        </div>
        <div class="field" style="flex:1;min-width:10rem">
          <label for="last_name">Last name</label>
          <input class="input" type="text" id="last_name" name="last_name" required autocomplete="family-name" value="<?= esc(old('last_name')) ?>">
        </div>
      </div>

      <div class="field">
        <label for="email">Email</label>
        <input class="input" type="email" id="email" name="email" required autocomplete="email" value="<?= esc(old('email')) ?>">
      </div>

      <div class="field">
        <label for="phone">Mobile <span class="t-mute">(so the desk can reach you)</span></label>
        <input class="input" type="tel" id="phone" name="phone" autocomplete="tel" placeholder="+63 9xx xxx xxxx" value="<?= esc(old('phone')) ?>">
      </div>

      <div class="field">
        <span class="field-label" id="skill-label">Where are you at?</span>
        <div class="tiles" role="radiogroup" aria-labelledby="skill-label">
          <?php foreach ([
              ['beginner', 'Beginner', 'New or under 3.0'],
              ['intermediate', 'Intermediate', '3.0 – 3.75'],
              ['advanced', 'Advanced', '4.0 – 4.5'],
              ['pro', 'Open', '4.5 and up'],
          ] as [$key, $name, $note]): ?>
            <label class="tile">
              <input type="radio" name="skill_level" value="<?= $key ?>" <?= (old('skill_level') ?? 'beginner') === $key ? 'checked' : '' ?>>
              <span class="tile-face"><b><?= $name ?></b><span><?= $note ?></span></span>
            </label>
          <?php endforeach; ?>
        </div>
        <span class="field-hint">Only used to slot you into the right clinic. You can change it any time.</span>
      </div>

      <div class="field">
        <label for="password">Password</label>
        <input class="input" type="password" id="password" name="password" required minlength="10" autocomplete="new-password">
        <span class="field-hint">At least 10 characters. This account can spend money at the desk.</span>
      </div>

      <div class="field">
        <label for="confirm">Confirm password</label>
        <input class="input" type="password" id="confirm" name="confirm" required minlength="10" autocomplete="new-password">
      </div>

      <button class="btn btn-block" type="submit">Create account</button>

      <p class="t-small t-mute" style="margin:0;text-align:center">
        Already have one? <a href="<?= base_url('login') ?>" style="color:var(--blue-ink)">Sign in</a>
      </p>
    </form>
  </div>
</section>
<?= $this->endSection() ?>
