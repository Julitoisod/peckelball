<?= $this->extend($layout) ?>

<?= $this->section('content') ?>
<?php
// CI4's old() takes a key; this form re-populates a whole nested payload
// (equipment quantities keyed by id), so read the flashed array directly.
$old      = session()->getFlashdata('_ci_old_input')['post'] ?? [];
$user     = $auth->user();
$selected = $selectedStart !== '' ? substr($selectedStart, 0, 5) : (string) ($old['start_time'] ?? '');
$slotMap  = array_column($slots, null, 'start');
if ($selected !== '' && ! isset($slotMap[$selected])) {
    $selected = '';
}
?>

<?= partial('partials/page_head', [
    'heading' => 'Book a court',
    'lede'    => 'Court, day, length, time. Four choices and you are done. Everything after that is optional.',
    'kicker'  => '<span class="chip chip-live tone-live"><span class="dot dot-pulse"></span>Live availability</span>'
        // "on Today" reads as a typo. Today and Tomorrow are already adverbs;
        // only a real date needs the preposition.
        . '<span class="t-label">' . count($slots) . ' play time' . (count($slots) === 1 ? '' : 's') . ' '
        . (in_array(day_label($date), ['Today', 'Tomorrow'], true) ? strtolower(day_label($date)) : 'on ' . esc(day_label($date))) . '</span>',
]) ?>

<section class="section">
  <div class="container">
    <div class="booking-layout">

      <!-- ===================================================== STEP 1-3 -->
      <div class="stack-4">
        <!-- Court / date / duration. Plain GET so it works with JS off. -->
        <form method="get" action="<?= base_url('booking') ?>" class="panel" data-picker>
          <h2 class="t-h3" style="margin-bottom:1.125rem">1 &middot; Court, day and length</h2>

          <div class="stack">
            <div class="field">
              <span class="field-label" id="court-label">Court</span>
              <div class="tiles" role="radiogroup" aria-labelledby="court-label">
                <?php foreach ($courts as $c): ?>
                  <label class="tile">
                    <input type="radio" name="court" value="<?= (int) $c['id'] ?>" <?= (int) $c['id'] === $courtId ? 'checked' : '' ?> data-autosubmit>
                    <span class="tile-face">
                      <b><?= esc($c['code']) ?></b>
                      <span><?= esc($c['name']) ?></span>
                      <span class="amount" style="color:var(--fg);font-size:0.6875rem"><?= esc(rate_label($c)) ?>/hr</span>
                    </span>
                  </label>
                <?php endforeach; ?>
              </div>
            </div>

            <div class="row" style="gap:1rem;align-items:flex-end">
              <div class="field" style="flex:1;min-width:11rem">
                <label for="date">Date</label>
                <input class="input" type="date" id="date" name="date" value="<?= esc($date) ?>"
                       min="<?= esc(business_date()) ?>" max="<?= esc($maxDate) ?>" data-autosubmit>
              </div>

              <div class="field" style="flex:1;min-width:11rem">
                <label for="duration">Duration</label>
                <select class="select" id="duration" name="duration" data-autosubmit>
                  <?php foreach ($durations as $d): ?>
                    <option value="<?= (int) $d ?>" <?= $d === $duration ? 'selected' : '' ?>>
                      <?= esc(length_label($d, true)) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <noscript><button class="btn" type="submit" style="margin-bottom:0.0625rem">Show times</button></noscript>
            </div>
          </div>
        </form>

        <!-- ===================================================== STEP 4 -->
        <form method="post" action="<?= base_url('booking') ?>" id="booking-form" class="stack-4">
          <?= csrf_field() ?>
          <input type="hidden" name="court_id" value="<?= $courtId ?>">
          <input type="hidden" name="date" value="<?= esc($date) ?>">
          <input type="hidden" name="duration" value="<?= $duration ?>">

          <div class="panel">
            <div class="row-between" style="margin-bottom:1.125rem">
              <h2 class="t-h3" style="margin:0">2 &middot; Play time</h2>
              <span class="t-label"><?= esc($court['name'] ?? '') ?> &middot; <?= esc(day_label($date)) ?></span>
            </div>

            <?php if ($slots === []): ?>
              <div class="empty">
                <p class="t-body" style="margin:0"><strong>No <?= $duration >= 60 ? ($duration / 60) . '-hour' : $duration . '-minute' ?> gap left on <?= esc($court['name'] ?? 'this court') ?> <?= esc(strtolower(day_label($date))) ?>.</strong></p>
                <p class="t-small" style="margin:0">Try a shorter session, another court, or the next day. The board below shows where the space is.</p>
              </div>
            <?php else: ?>
              <div class="tiles" role="radiogroup" aria-label="Play time">
                <?php foreach ($slots as $slot): ?>
                  <label class="tile">
                    <input type="radio" name="start_time" value="<?= esc($slot['start']) ?>" required
                           <?= $selected === $slot['start'] ? 'checked' : '' ?>
                           data-price="<?= esc((string) $slot['price']) ?>">
                    <span class="tile-face">
                      <b><?= esc(clock($slot['start'])) ?></b>
                      <span>to <?= esc(clock($slot['end'])) ?></span>
                      <?php /* Not --green: that token means AVAILABLE and nothing else (DESIGN.md).
                               Every tile in this list is available, so colouring the price green
                               says nothing and spends the one status colour on decoration. */ ?>
                      <span class="amount" style="color:var(--fg);font-size:0.6875rem"><?= esc(peso($slot['price'])) ?></span>
                    </span>
                  </label>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>

          <!-- ================================================= STEP 5 -->
          <div class="panel">
            <h2 class="t-h3" style="margin-bottom:1.125rem">3 &middot; Who is playing</h2>

            <div class="stack">
              <div class="field" style="max-width:16rem">
                <label for="player_count">Players</label>
                <select class="select" id="player_count" name="player_count">
                  <?php foreach ([1, 2, 3, 4, 5, 6, 7, 8] as $n): ?>
                    <option value="<?= $n ?>" <?= (int) ($old['player_count'] ?? 4) === $n ? 'selected' : '' ?>><?= $n ?> player<?= $n === 1 ? '' : 's' ?></option>
                  <?php endforeach; ?>
                </select>
              </div>

              <?php if ($user === null): ?>
                <div class="alert tone-live" style="margin-block:0.25rem">
                  <b>Guest</b>
                  <span>Booking without an account is fine. <a href="<?= base_url('login?next=/booking') ?>">Sign in</a> and we fill this in for you and keep your history.</span>
                </div>
                <div class="row" style="gap:1rem;align-items:flex-start">
                  <div class="field" style="flex:1;min-width:12rem">
                    <label for="guest_name">Name</label>
                    <input class="input" type="text" id="guest_name" name="guest_name" required autocomplete="name" value="<?= esc($old['guest_name'] ?? '') ?>">
                  </div>
                  <div class="field" style="flex:1;min-width:12rem">
                    <label for="guest_phone">Mobile</label>
                    <input class="input" type="tel" id="guest_phone" name="guest_phone" required autocomplete="tel" placeholder="+63 9xx xxx xxxx" value="<?= esc($old['guest_phone'] ?? '') ?>">
                  </div>
                </div>
                <div class="field">
                  <label for="guest_email">Email <span class="t-mute">(optional, for the confirmation)</span></label>
                  <input class="input" type="email" id="guest_email" name="guest_email" autocomplete="email" value="<?= esc($old['guest_email'] ?? '') ?>">
                </div>
              <?php else: ?>
                <div class="alert tone-open">
                  <b>Signed in</b>
                  <span>Booking as <?= esc($auth->name()) ?><?= $user['phone'] ? ' &middot; ' . esc($user['phone']) : '' ?>.</span>
                </div>
              <?php endif; ?>

              <div class="field">
                <label for="notes">Anything we should know</label>
                <textarea class="textarea" id="notes" name="notes" maxlength="500" placeholder="First time here, bringing a junior, need the ball machine"><?= esc($old['notes'] ?? '') ?></textarea>
              </div>
            </div>
          </div>

          <!-- ================================================= STEP 7 -->
          <div class="panel">
            <h2 class="t-h3" style="margin-bottom:1.125rem">4 &middot; Payment</h2>

            <div class="tiles" role="radiogroup" aria-label="Payment method" style="margin-bottom:1rem">
              <?php foreach ([
                  ['cash', 'Cash', 'Pay at the desk'],
                  ['gcash', 'GCash', 'Send on arrival'],
              ] as [$key, $name, $note]): ?>
                <label class="tile">
                  <input type="radio" name="payment_method" value="<?= $key ?>" required <?= ($old['payment_method'] ?? 'cash') === $key ? 'checked' : '' ?>>
                  <span class="tile-face"><b><?= $name ?></b><span><?= $note ?></span></span>
                </label>
              <?php endforeach; ?>
            </div>

            <div class="field" style="max-width:18rem">
              <label for="promo_code">Promo code <span class="t-mute">(optional)</span></label>
              <input class="input mono" type="text" id="promo_code" name="promo_code" maxlength="32"
                     placeholder="FIRSTDINK" value="<?= esc($old['promo_code'] ?? '') ?>" style="text-transform:uppercase">
            </div>
          </div>

          <button class="btn btn-go btn-lg btn-block" type="submit" <?= $slots === [] ? 'disabled' : '' ?>>
            Confirm booking
          </button>
          <p class="t-small t-mute" style="margin:0;text-align:center">
            Nothing is charged online. You settle at the desk when you arrive.
            Cancel free up to <?= esc(setting('booking.cutoff_minutes', 30)) ?> minutes before start.
          </p>
        </form>
      </div>

      <!-- ==================================================== SUMMARY -->
      <aside class="panel ticks booking-summary" aria-label="Booking summary">
        <h2 class="t-label" style="color:var(--fg);margin-bottom:1rem">Your booking</h2>

        <dl class="stack-2" style="margin:0">
          <?php
          $rows = [
              ['Court', ($court['code'] ?? '-') . ' &middot; ' . ($court['name'] ?? '-')],
              ['Date', day_label($date) . ', ' . date('d M Y', strtotime($date))],
              ['Length', $duration >= 60 ? ($duration / 60) . ' hour' . ($duration === 60 ? '' : 's') : $duration . ' min'],
          ];
          foreach ($rows as [$k, $v]): ?>
            <div class="row-between" style="gap:0.75rem;padding-bottom:0.5rem;border-bottom:1px solid color-mix(in oklch, var(--line) 60%, transparent)">
              <dt class="t-label"><?= esc($k) ?></dt>
              <dd class="mono" style="margin:0;font-size:0.8125rem;text-align:right"><?= $v ?></dd>
            </div>
          <?php endforeach; ?>

          <div class="row-between" style="gap:0.75rem;padding-bottom:0.5rem;border-bottom:1px solid color-mix(in oklch, var(--line) 60%, transparent)">
            <dt class="t-label">Play time</dt>
            <dd class="mono" style="margin:0;font-size:0.8125rem;text-align:right" data-summary-start><?= $selected !== '' ? esc(clock($selected)) : '-' ?></dd>
          </div>
        </dl>

        <div class="row-between" style="margin-top:1.125rem;padding-top:1rem;border-top:2px solid color-mix(in oklch, var(--blue) 45%, transparent)">
          <span class="t-label" style="color:var(--fg)">Court fee</span>
          <?php if ($selected !== '' && isset($slotMap[$selected])): ?>
            <span class="t-metric" style="font-size:1.5rem" data-summary-price><?= esc(peso($slotMap[$selected]['price'])) ?></span>
          <?php else: ?>
            <span class="t-label" data-summary-price>Pick a play time</span>
          <?php endif; ?>
        </div>

        <?php if ($court !== null && ! empty($court['description'])): ?>
          <hr class="rule" style="margin:1.25rem 0">
          <p class="t-small t-mute" style="margin:0"><?= esc(mb_strimwidth($court['description'], 0, 180, '...')) ?></p>
        <?php endif; ?>
      </aside>
    </div>
  </div>
</section>

<!-- ==================================================== WHOLE-DAY BOARD -->
<section class="section-tight field-bg field-scan">
  <div class="container-wide">
    <div class="section-head">
      <div>
        <h2 class="t-h2">Everything free on <?= esc(strtolower(day_label($date))) ?></h2>
        <p class="t-small t-mute">If your court is full, another one probably is not. Tap any open slot to switch to it.</p>
      </div>
    </div>
    <?= partial('partials/board', ['board' => $board, 'bookable' => true]) ?>
  </div>
</section>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<style>
  .booking-layout {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(0, 21rem);
    gap: clamp(1.25rem, 2.5vw, 2.25rem);
    align-items: start;
  }
  .booking-summary { position: sticky; top: 5.5rem; }
  @media (max-width: 900px) {
    .booking-layout { grid-template-columns: 1fr; }
    .booking-summary { position: static; }
  }
</style>
<script>
(() => {
  // Court / date / duration re-query the server for real availability; there is
  // no client-side copy of the schedule to drift out of date.
  const picker = document.querySelector('[data-picker]');
  picker?.querySelectorAll('[data-autosubmit]').forEach((el) => {
    el.addEventListener('change', () => picker.submit());
  });

  const startEl = document.querySelector('[data-summary-start]');
  const priceEl = document.querySelector('[data-summary-price]');
  const peso = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP', maximumFractionDigits: 0 });

  document.querySelectorAll('input[name="start_time"]').forEach((input) => {
    input.addEventListener('change', () => {
      // Reuse the tile's own label so the summary reads 12-hour like everything
      // else; input.value stays 24-hour because that is what the server wants.
      if (startEl) startEl.textContent = input.closest('.tile')?.querySelector('b')?.textContent ?? input.value;
      if (priceEl) {
        // Promote the placeholder label to a real metric once there is a figure.
        priceEl.className = 't-metric';
        priceEl.style.fontSize = '1.5rem';
        priceEl.textContent = peso.format(Number(input.dataset.price || 0));
      }
    });
  });
})();
</script>
<?= $this->endSection() ?>
