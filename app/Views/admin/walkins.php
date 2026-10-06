<?= $this->extend($layout) ?>

<?= $this->section('content') ?>
<?php
/**
 * Walk-ins at the desk. Same shape as the player's quick-book card: tap the
 * play time, a card opens, take the names and the money, done. The details
 * the public card dropped (head count, note, card/Maya) are gone from here
 * too — the desk can edit a booking afterwards if it needs more.
 *
 * @var list<array{court:array<string,mixed>, slots:list<array<string,mixed>>}> $offers
 * @var int          $duration
 * @var list<int>    $durations
 * @var list<string> $methods
 * @var list<array<string,mixed>> $recent
 */
$free = array_sum(array_map(static fn ($o) => count($o['slots']), $offers));
?>

<div class="panel">
  <div class="row-between" style="margin-bottom:1rem;gap:1rem;flex-wrap:wrap">
    <div>
      <h2 class="t-h3" style="margin:0">Free right now</h2>
      <p class="t-small t-mute" style="margin:0.25rem 0 0">
        <?= $free ?> play time<?= $free === 1 ? '' : 's' ?> left today that fit <?= (int) $duration ?> minutes.
        Tap one to book it.
      </p>
    </div>

    <?php /* Length changes which slots fit, so it reloads rather than
             filtering in the browser. One round trip, no JavaScript. */ ?>
    <div class="row" style="gap:0.5rem;align-items:center;flex:none">
      <span class="t-label">Length</span>
      <?php foreach ($durations as $d): ?>
        <a class="btn btn-sm <?= $d === $duration ? '' : 'btn-ghost' ?>"
           href="<?= base_url('admin/walkins?duration=' . $d) ?>"><?= $d ?>m</a>
      <?php endforeach; ?>
    </div>
  </div>

  <?php if ($offers === []): ?>
    <div class="empty" style="border:0">
      <p style="margin:0"><strong>Nothing left today at <?= (int) $duration ?> minutes.</strong></p>
      <p class="t-small t-mute" style="margin:0.25rem 0 0">Try a shorter session, or book them onto tomorrow from the booking calendar.</p>
    </div>
  <?php else: ?>
    <div class="stack">
      <?php foreach ($offers as $offer): ?>
        <div>
          <div class="row" style="gap:0.5rem;align-items:baseline;margin-bottom:0.5rem">
            <span class="mono" style="font-weight:600"><?= esc($offer['court']['code']) ?></span>
            <span class="t-small"><?= esc($offer['court']['name']) ?></span>
            <span class="t-label t-mute"><?= esc(ucfirst($offer['court']['surface_type'])) ?></span>
          </div>

          <div class="book-modal-times">
            <?php foreach ($offer['slots'] as $slot): ?>
              <button class="time-tile" type="button"
                      data-walkin-slot="<?= esc($offer['court']['id'] . '|' . $slot['start'], 'attr') ?>"
                      data-walkin-label="<?= esc($offer['court']['code'] . ' · ' . clock($slot['start']) . '–' . clock($slot['end']), 'attr') ?>"
                      data-walkin-price="<?= esc(peso($slot['price']), 'attr') ?>">
                <b><?= esc(clock($slot['start'])) ?></b>
                <span>to <?= esc(clock($slot['end'])) ?> · <?= esc(peso($slot['price'])) ?></span>
              </button>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php if ($recent !== []): ?>
  <div class="panel panel-flush" style="margin-top:1rem">
    <div class="panel-head">
      <h2 class="t-h3" style="margin:0">Walk-ins today</h2>
      <span class="t-label"><?= count($recent) ?> taken at the desk</span>
    </div>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr><th>Reference</th><th>Name</th><th>Court</th><th>Time</th><th class="num">Total</th><th>Payment</th><th>Status</th></tr>
        </thead>
        <tbody>
          <?php foreach ($recent as $row): ?>
            <?php $s = booking_status_meta($row['status']); ?>
            <tr>
              <td class="mono t-small"><?= esc($row['reference']) ?></td>
              <td>
                <?= esc($row['guest_name'] ?: 'Guest') ?>
                <?php $players = pipes($row['player_names'] ?? null); ?>
                <?php if (count($players) > 1): ?>
                  <br><span class="t-small t-mute">with <?= esc(implode(', ', array_slice($players, 1))) ?></span>
                <?php endif; ?>
              </td>
              <td class="mono"><?= esc($row['court_code']) ?></td>
              <td class="mono t-small" style="white-space:nowrap"><?= esc(clock($row['start_time'])) ?> to <?= esc(clock($row['end_time'])) ?></td>
              <td class="num"><?= esc(peso($row['total_amount'])) ?></td>
              <td>
                <span class="chip tone-<?= $row['payment_status'] === 'paid' ? 'open' : ($row['payment_status'] === 'partial' ? 'filling' : 'booked') ?>">
                  <?= esc(ucfirst($row['payment_status'])) ?>
                </span>
              </td>
              <td><span class="chip tone-<?= esc($s['tone']) ?>"><?= esc($s['label']) ?></span></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>

<?php /* The card. Same markup and styles as the player's quick-book card, one
         step: the slot was already chosen by the tile that opened this. */ ?>
<dialog class="book-modal" data-walkin-modal aria-labelledby="walkin-title">
  <div class="book-modal-head">
    <h2 class="t-h3" id="walkin-title">Walk-in</h2>
    <button class="book-modal-close" type="button" data-walkin-close aria-label="Close">&times;</button>
  </div>

  <form method="post" action="<?= base_url('admin/walkins/create') ?>" data-walkin-form>
    <?= csrf_field() ?>
    <input type="hidden" name="duration" value="<?= (int) $duration ?>">
    <input type="hidden" name="slot" data-walkin-field>

    <div class="book-modal-body">
      <div class="field">
        <span class="field-label">Name</span>
        <div class="book-modal-names" data-walkin-names>
          <div class="book-modal-name-row">
            <input class="input" type="text" name="player_names[]" aria-label="Player 1" autocomplete="off" maxlength="120">
          </div>
        </div>
        <button class="btn btn-ghost btn-sm" type="button" data-walkin-add-name style="align-self:flex-start">
          + Add another name
        </button>
      </div>

      <div class="field" style="max-width:14rem">
        <label for="walkin-phone">Phone <span class="t-mute">(optional)</span></label>
        <input class="input mono" type="tel" id="walkin-phone" name="guest_phone" maxlength="30" autocomplete="off">
      </div>

      <div class="field">
        <span class="field-label">Payment</span>
        <div class="tiles" role="radiogroup" aria-label="Payment method">
          <?php // ucfirst() would print "Gcash"; the brand is GCash.
          $methodLabels = ['cash' => 'Cash', 'gcash' => 'GCash']; ?>
          <?php foreach ($methods as $i => $m): ?>
            <label class="tile">
              <input type="radio" name="payment_method" value="<?= esc($m) ?>" <?= $i === 0 ? 'checked' : '' ?>>
              <span class="tile-face"><b><?= esc($methodLabels[$m] ?? ucfirst($m)) ?></b></span>
            </label>
          <?php endforeach; ?>
        </div>
      </div>

      <label style="display:flex;align-items:center;gap:0.625rem">
        <input type="checkbox" name="paid_now" value="1" checked>
        <span class="t-small">Cash handed over now</span>
      </label>
      <span class="field-hint">
        GCash settles on its own terminal, so it is recorded as taken either way.
        Untick this and a cash booking sits in Payments as unpaid.
      </span>
    </div>

    <div class="book-modal-foot">
      <div class="readout">
        <span class="t-label" data-walkin-summary></span>
        <span class="t-h3" data-walkin-price></span>
      </div>
      <button class="btn btn-go btn-lg" type="submit">Book the walk-in</button>
    </div>
  </form>
</dialog>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
(() => {
  // The desk's card. Deliberately its own few lines rather than sharing the
  // public one: there the slot is picked inside the card, here the tile that
  // opened it already decided, so the only moving part is the name rows.
  const dialog = document.querySelector('[data-walkin-modal]');
  if (!dialog || typeof dialog.showModal !== 'function') return;

  const form = dialog.querySelector('[data-walkin-form]');
  const slot = dialog.querySelector('[data-walkin-field]');
  const names = dialog.querySelector('[data-walkin-names]');
  const summary = dialog.querySelector('[data-walkin-summary]');
  const price = dialog.querySelector('[data-walkin-price]');
  const MAX_NAMES = 8;

  const rows = () => [...names.querySelectorAll('input[name="player_names[]"]')];

  const addRow = () => {
    if (rows().length >= MAX_NAMES) return;
    const row = document.createElement('div');
    row.className = 'book-modal-name-row';

    const input = document.createElement('input');
    input.className = 'input';
    input.type = 'text';
    input.name = 'player_names[]';
    input.maxLength = 120;
    input.autocomplete = 'off';
    input.setAttribute('aria-label', `Player ${rows().length + 1}`);

    const drop = document.createElement('button');
    drop.className = 'book-modal-name-drop';
    drop.type = 'button';
    drop.textContent = '\u00d7';
    drop.setAttribute('aria-label', 'Remove this name');
    drop.addEventListener('click', () => row.remove());

    row.append(input, drop);
    names.append(row);
    input.focus();
  };

  dialog.querySelector('[data-walkin-add-name]').addEventListener('click', addRow);
  dialog.querySelector('[data-walkin-close]').addEventListener('click', () => dialog.close());
  dialog.addEventListener('click', (e) => { if (e.target === dialog) dialog.close(); });

  document.querySelectorAll('[data-walkin-slot]').forEach((tile) => {
    tile.addEventListener('click', () => {
      slot.value = tile.dataset.walkinSlot;
      summary.textContent = tile.dataset.walkinLabel;
      price.textContent = tile.dataset.walkinPrice;
      dialog.showModal();
      rows()[0].focus();
    });
  });

  form.addEventListener('submit', (e) => {
    if (rows().every((input) => input.value.trim() === '')) {
      e.preventDefault();
      rows()[0].focus();
    }
  });
})();
</script>
<?= $this->endSection() ?>
