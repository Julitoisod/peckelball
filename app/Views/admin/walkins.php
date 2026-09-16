<?= $this->extend($layout) ?>

<?= $this->section('content') ?>
<?php
/**
 * @var list<array{court:array<string,mixed>, slots:list<array<string,mixed>>}> $offers
 * @var int        $duration
 * @var list<int>  $durations
 * @var list<string> $methods
 * @var list<array<string,mixed>> $recent
 */
$val = static fn (string $field, string $fallback = ''): string => (string) (old($field) ?? $fallback);
$free = array_sum(array_map(static fn ($o) => count($o['slots']), $offers));
?>

<form method="post" action="<?= base_url('admin/walkins/create') ?>" class="stack">
  <?= csrf_field() ?>
  <input type="hidden" name="duration" value="<?= (int) $duration ?>">

  <div class="panel">
    <div class="row-between" style="margin-bottom:1rem;gap:1rem;flex-wrap:wrap">
      <div>
        <h2 class="t-h3" style="margin:0">Free right now</h2>
        <p class="t-small t-mute" style="margin:0.25rem 0 0">
          <?= $free ?> play time<?= $free === 1 ? '' : 's' ?> left today that fit <?= (int) $duration ?> minutes.
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

            <div class="tiles">
              <?php foreach ($offer['slots'] as $slot): ?>
                <?php $value = $offer['court']['id'] . '|' . $slot['start']; ?>
                <label class="tile">
                  <input type="radio" name="slot" value="<?= esc($value) ?>" <?= $val('slot') === $value ? 'checked' : '' ?> required>
                  <span class="tile-face">
                    <span class="mono" style="font-weight:600"><?= esc(clock($slot['start'])) ?></span>
                    <span class="t-label">to <?= esc(clock($slot['end'])) ?></span>
                    <span class="t-label" style="color:var(--green)"><?= esc(peso($slot['price'])) ?></span>
                  </span>
                </label>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <?php if ($offers !== []): ?>
    <div class="admin-cols">
      <div class="panel">
        <h2 class="t-h3" style="margin:0 0 1rem">Who is playing</h2>

        <div class="stack">
          <div class="row" style="gap:0.75rem">
            <div class="field" style="flex:1;min-width:0">
              <label for="guest_name">Name</label>
              <input class="input" type="text" id="guest_name" name="guest_name" required maxlength="120"
                     autocomplete="off" value="<?= esc($val('guest_name')) ?>">
            </div>
            <div class="field" style="flex:0 0 12rem">
              <label for="guest_phone">Phone</label>
              <input class="input mono" type="tel" id="guest_phone" name="guest_phone" maxlength="30"
                     autocomplete="off" value="<?= esc($val('guest_phone')) ?>">
            </div>
          </div>

          <div class="field" style="max-width:10rem">
            <label for="player_count">Players</label>
            <input class="input" type="number" id="player_count" name="player_count" min="1" max="8" step="1"
                   value="<?= esc($val('player_count', '2')) ?>" required>
          </div>

          <div class="field">
            <label for="notes">Note for the desk</label>
            <input class="input" type="text" id="notes" name="notes" maxlength="255" value="<?= esc($val('notes')) ?>">
          </div>
        </div>
      </div>

      <div class="panel">
        <h2 class="t-h3" style="margin:0 0 1rem">Payment</h2>

        <div class="field">
          <label for="method">Method</label>
          <select class="select" id="method" name="method">
            <?php foreach ($methods as $m): ?>
              <option value="<?= esc($m) ?>" <?= $val('method', 'cash') === $m ? 'selected' : '' ?>><?= esc(ucfirst($m)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <label style="display:flex;align-items:center;gap:0.625rem;margin-top:1rem">
          <input type="checkbox" name="paid_now" value="1" <?= old('paid_now') ? 'checked' : '' ?>>
          <span class="t-small">Cash handed over now</span>
        </label>
        <p class="field-hint" style="margin:0.5rem 0 0">
          The other methods settle on their own terminal, so they are recorded as taken either way.
          Leave this unticked and the booking sits in Payments as unpaid.
        </p>

        <button class="btn btn-block" type="submit" style="margin-top:1.25rem">Book the walk-in</button>
      </div>
    </div>
  <?php endif; ?>
</form>

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
              <td><?= esc($row['guest_name'] ?: 'Guest') ?></td>
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
<?= $this->endSection() ?>
