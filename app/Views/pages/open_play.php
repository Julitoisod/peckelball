<?= $this->extend($layout) ?>

<?= $this->section('content') ?>
<?php
/**
 * Open play for players. One card for signing up, filled by whichever
 * session's button opened it — the same shape as the quick-book card.
 *
 * @var list<array<string,mixed>> $sessions  open sessions, soonest first
 * @var \App\Services\Auth        $auth
 */
$player = $auth->user();
$free   = static fn (array $s): int => max(0, (int) $s['capacity'] - (int) $s['taken']);
?>
<?= partial('partials/page_head', [
    'heading' => 'Open play',
    'lede'    => 'Turn up on your own or with one other, pay per head, and play with whoever is on. No need to book a whole court.',
    'kicker'  => '<span class="t-label">' . count($sessions) . ' session' . (count($sessions) === 1 ? '' : 's') . ' coming up</span>',
    'actions' => '<a class="btn btn-ghost" href="' . base_url('availability') . '">Live board</a>',
]) ?>

<section class="section">
  <div class="container">
    <?php if ($sessions === []): ?>
      <div class="empty">
        <p style="margin:0"><strong>No open play on the books right now.</strong></p>
        <p class="t-small t-mute" style="margin:0.25rem 0 0">
          Book a court any hour instead — we are open round the clock — or
          <a href="<?= base_url('contact') ?>">ask us</a> when the next one is.
        </p>
      </div>
    <?php else: ?>
      <div class="grid-auto">
        <?php foreach ($sessions as $session): ?>
          <?php $left = $free($session); ?>
          <div class="panel stack-2">
            <div class="row-between" style="gap:0.75rem">
              <span class="t-label"><?= esc(day_label($session['session_date'])) ?></span>
              <span class="chip tone-<?= $left > 0 ? 'open' : 'booked' ?>">
                <?= $left > 0 ? $left . ' places left' : 'Full' ?>
              </span>
            </div>

            <h2 class="t-h3" style="margin:0"><?= esc($session['title'] ?: 'Open play') ?></h2>
            <p class="t-small t-mute" style="margin:0">
              <?= esc($session['court_code']) ?> · <?= esc($session['court_name']) ?><br>
              <?= esc(clock($session['start_time'])) ?>&ndash;<?= esc(clock($session['end_time'])) ?>
              · <?= esc(length_label((int) $session['duration_minutes'], true)) ?>
            </p>
            <?php if ($session['notes']): ?>
              <p class="t-small" style="margin:0"><?= esc($session['notes']) ?></p>
            <?php endif; ?>

            <div class="row-between" style="gap:0.75rem;margin-top:auto;padding-top:0.5rem">
              <span class="readout">
                <span class="t-h3"><?= esc(peso($session['fee'])) ?></span>
                <span class="t-label">a head</span>
              </span>
              <?php if ($left > 0): ?>
                <button class="btn" type="button"
                        data-op-join="<?= (int) $session['id'] ?>"
                        data-op-label="<?= esc($session['court_code'] . ' · ' . day_label($session['session_date']) . ', ' . clock($session['start_time']) . '–' . clock($session['end_time']), 'attr') ?>"
                        data-op-fee="<?= esc(peso($session['fee']), 'attr') ?>"
                        data-op-left="<?= $left ?>">
                  Sign up
                </button>
              <?php else: ?>
                <span class="t-small t-mute">No places left</span>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php if ($sessions !== []): ?>
  <?php // One card, pointed at whichever session was tapped. ?>
  <dialog class="book-modal" data-op-modal aria-labelledby="op-title">
    <div class="book-modal-head">
      <h2 class="t-h3" id="op-title">Sign up for open play</h2>
      <button class="book-modal-close" type="button" data-op-close aria-label="Close">&times;</button>
    </div>

    <form method="post" action="<?= base_url('open-play/0/join') ?>" data-op-form>
      <?= csrf_field() ?>

      <div class="book-modal-body">
        <dl class="book-modal-review">
          <dt>Session</dt><dd data-op-review="label"></dd>
          <dt>Places left</dt><dd data-op-review="left"></dd>
        </dl>

        <?php if ($player === null): ?>
          <div class="row" style="gap:0.75rem;align-items:flex-start">
            <div class="field" style="flex:1;min-width:10rem">
              <label for="op-name">Name</label>
              <input class="input" type="text" id="op-name" name="name" maxlength="120" autocomplete="name">
            </div>
            <div class="field" style="flex:1;min-width:10rem">
              <label for="op-phone">Mobile</label>
              <input class="input" type="tel" id="op-phone" name="phone" maxlength="30" autocomplete="tel"
                     placeholder="+63 9xx xxx xxxx">
            </div>
          </div>
        <?php else: ?>
          <div class="alert tone-open">
            <b>Signed in</b>
            <span>Signing up as <?= esc($auth->name()) ?>.</span>
          </div>
        <?php endif; ?>

        <div data-op-gcash>
          <?= $this->include('partials/gcash_pay') ?>
        </div>
      </div>

      <div class="book-modal-foot">
        <div class="readout">
          <span class="t-label">Fee a head</span>
          <span class="t-h3" data-op-review="fee"></span>
        </div>
        <button class="btn btn-go btn-lg" type="submit">Sign me up</button>
      </div>
    </form>
  </dialog>
<?php endif; ?>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
(() => {
  // Its own few lines rather than the booking card's: there is no court, day
  // or hours to pick here — the session was chosen by the button.
  const dialog = document.querySelector('[data-op-modal]');
  if (!dialog || typeof dialog.showModal !== 'function') return;

  const form = dialog.querySelector('[data-op-form]');
  const action = form.getAttribute('action');
  const set = (key, value) => {
    const node = dialog.querySelector(`[data-op-review="${key}"]`);
    if (node) node.textContent = value;
  };

  const paintGcash = () => {
    const line = dialog.querySelector('[data-op-gcash] [data-gcash-line]');
    const fee = dialog.querySelector('[data-op-review="fee"]').textContent;
    if (line) line.textContent = `Scan the QR in your GCash app and send ${fee}.`;
  };

  dialog.querySelector('[data-op-close]').addEventListener('click', () => dialog.close());
  dialog.addEventListener('click', (e) => { if (e.target === dialog) dialog.close(); });

  document.querySelectorAll('[data-op-join]').forEach((button) => {
    button.addEventListener('click', () => {
      form.setAttribute('action', action.replace(/\/\d+\/join$/, `/${button.dataset.opJoin}/join`));
      set('label', button.dataset.opLabel);
      set('left', button.dataset.opLeft);
      set('fee', button.dataset.opFee);
      paintGcash();
      dialog.showModal();
      form.querySelector('#op-name')?.focus();
    });
  });

  form.addEventListener('submit', (e) => {
    const name = form.querySelector('#op-name');
    const phone = form.querySelector('#op-phone');
    if (name && (!name.value.trim() || !phone.value.trim())) {
      e.preventDefault();
      (name.value.trim() ? phone : name).focus();
    }
  });
})();
</script>
<?= $this->endSection() ?>
