<?= $this->extend($layout) ?>

<?= $this->section('content') ?>
<?php
/**
 * Open play at the desk: schedule a window, switch it off, keep the sheet.
 *
 * @var list<array<string,mixed>> $sessions   from today forward
 * @var array<int, list<array<string,mixed>>> $sheets  participants by session id
 * @var list<array<string,mixed>> $courts
 * @var list<string>              $times      slot start times for a day
 * @var string                    $defaultFinish  a sensible finish for the default start
 * @var list<array<string,mixed>> $past       the last 30 days, for the takings
 */
$val = static fn (string $field, string $fallback = ''): string => (string) (old($field) ?? $fallback);

$upcoming = array_values(array_filter($sessions, static fn ($s) => $s['status'] === 'open'));
$off      = array_values(array_filter($sessions, static fn ($s) => $s['status'] === 'closed'));
$collected = array_sum(array_map(static fn ($s) => (float) $s['taken_amount'], $past));
$signups   = array_sum(array_map(static fn ($s) => (int) $s['taken'], $past));
?>

<div class="kpi-grid">
  <div class="kpi kpi-accent">
    <span class="t-metric"><?= count($upcoming) ?></span>
    <span class="t-label">Sessions on</span>
  </div>
  <div class="kpi">
    <span class="t-metric"><?= (int) $signups ?></span>
    <span class="t-label">Sign-ups, 30 days</span>
  </div>
  <div class="kpi kpi-go">
    <span class="t-metric"><?= esc(peso($collected)) ?></span>
    <span class="t-label">Collected, 30 days</span>
  </div>
</div>

<div class="admin-cols">
  <div class="stack">
    <?php if ($sessions === []): ?>
      <div class="panel">
        <div class="empty" style="border:0">
          <p style="margin:0"><strong>No open play scheduled.</strong></p>
          <p class="t-small t-mute" style="margin:0.25rem 0 0">
            Set one up beside this: pick the court, the day, the hours, what each player pays and how many fit.
          </p>
        </div>
      </div>
    <?php endif; ?>

    <?php foreach ($sessions as $session): ?>
      <?php
      $id       = (int) $session['id'];
      $sheet    = $sheets[$id] ?? [];
      $isOpen   = $session['status'] === 'open';
      $left     = max(0, (int) $session['capacity'] - (int) $session['taken']);
      $unpaid   = count(array_filter($sheet, static fn ($p) => $p['payment_status'] !== 'paid'));
      ?>
      <div class="panel panel-flush">
        <div class="panel-head">
          <div>
            <h2 class="t-h3" style="margin:0">
              <?= esc($session['title'] ?: 'Open play') ?>
            </h2>
            <span class="t-small t-mute">
              <?= esc($session['court_code']) ?> ·
              <?= esc(day_label($session['session_date'])) ?>,
              <?= esc(clock($session['start_time'])) ?>&ndash;<?= esc(clock($session['end_time'])) ?>
              · <?= esc(length_label((int) $session['duration_minutes'], true)) ?>
              · <?= esc(peso($session['fee'])) ?> a head
            </span>
          </div>
          <div class="row" style="gap:0.5rem;flex:none">
            <span class="chip tone-<?= $isOpen ? ($left > 0 ? 'open' : 'filling') : 'closed' ?>">
              <?= $isOpen ? ($left > 0 ? $left . ' places left' : 'Full') : 'Off' ?>
            </span>
            <form method="post" action="<?= base_url('admin/open-play/' . $id . ($isOpen ? '/close' : '/reopen')) ?>"
                  <?= $isOpen ? 'onsubmit="return confirm(\'Switch this open play off? The court goes back on sale and the sheet is kept.\')"' : '' ?>>
              <?= csrf_field() ?>
              <button class="btn btn-sm <?= $isOpen ? 'btn-ghost' : 'btn-go' ?>" type="submit">
                <?= $isOpen ? 'Switch off' : 'Switch on' ?>
              </button>
            </form>
          </div>
        </div>

        <div style="padding:1rem 1.125rem">
          <div class="row-between" style="gap:1rem;margin-bottom:0.75rem">
            <span class="t-label">
              <?= (int) $session['taken'] ?> of <?= (int) $session['capacity'] ?> in
              <?php if ($unpaid > 0): ?>· <span style="color:var(--amber)"><?= $unpaid ?> unpaid</span><?php endif; ?>
            </span>
            <span class="t-label">Collected <?= esc(peso($session['taken_amount'])) ?></span>
          </div>

          <?php if ($sheet === []): ?>
            <p class="t-small t-mute" style="margin:0 0 1rem">Nobody on the sheet yet.</p>
          <?php else: ?>
            <div class="table-wrap" style="margin-bottom:1rem">
              <table class="table" style="min-width:28rem">
                <thead>
                  <tr><th>Player</th><th>Phone</th><th>Payment</th><th class="num">Fee</th><th></th></tr>
                </thead>
                <tbody>
                  <?php foreach ($sheet as $p): ?>
                    <tr>
                      <td><?= esc($p['name']) ?></td>
                      <td class="mono t-small"><?= esc($p['phone'] ?: '—') ?></td>
                      <td>
                        <span class="chip tone-<?= $p['payment_status'] === 'paid' ? 'open' : 'booked' ?>">
                          <?= esc(strtoupper($p['payment_method'])) ?> · <?= esc(ucfirst($p['payment_status'])) ?>
                        </span>
                      </td>
                      <td class="num"><?= esc(peso($p['amount'])) ?></td>
                      <td class="num" style="white-space:nowrap">
                        <?php if ($p['payment_status'] !== 'paid'): ?>
                          <form method="post" action="<?= base_url('admin/open-play/participants/' . (int) $p['id'] . '/paid') ?>" style="display:inline">
                            <?= csrf_field() ?>
                            <button class="btn btn-sm btn-go" type="submit">Paid</button>
                          </form>
                        <?php endif; ?>
                        <form method="post" action="<?= base_url('admin/open-play/participants/' . (int) $p['id'] . '/remove') ?>"
                              style="display:inline" onsubmit="return confirm('Take <?= esc($p['name'], 'attr') ?> off the sheet?')">
                          <?= csrf_field() ?>
                          <button class="btn btn-sm btn-ghost" type="submit">Remove</button>
                        </form>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>

          <?php if ($isOpen && $left > 0): ?>
            <?php // Same shape as the walk-in form: a name, a number, how they paid. ?>
            <form method="post" action="<?= base_url('admin/open-play/' . $id . '/register') ?>" class="row" style="gap:0.5rem;align-items:flex-end">
              <?= csrf_field() ?>
              <div class="field" style="flex:1;min-width:9rem">
                <label for="name-<?= $id ?>">Name</label>
                <input class="input" type="text" id="name-<?= $id ?>" name="name" required maxlength="120" autocomplete="off">
              </div>
              <div class="field" style="flex:0 0 9rem">
                <label for="phone-<?= $id ?>">Phone</label>
                <input class="input mono" type="tel" id="phone-<?= $id ?>" name="phone" maxlength="30" autocomplete="off">
              </div>
              <div class="field" style="flex:0 0 7.5rem">
                <label for="method-<?= $id ?>">Payment</label>
                <select class="select" id="method-<?= $id ?>" name="payment_method">
                  <option value="cash">Cash</option>
                  <option value="gcash">GCash</option>
                </select>
              </div>
              <label style="display:flex;align-items:center;gap:0.5rem;min-height:46px;flex:none">
                <input type="checkbox" name="paid" value="1" checked>
                <span class="t-small">Paid</span>
              </label>
              <button class="btn" type="submit" style="flex:none">Add to sheet</button>
            </form>
          <?php elseif ($isOpen): ?>
            <p class="t-small t-mute" style="margin:0">
              Full. Raise the places on a new session, or take somebody off the sheet.
            </p>
          <?php else: ?>
            <p class="t-small t-mute" style="margin:0">
              Switched off, so nobody else can sign up and the court is on sale again.
            </p>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="panel">
    <h2 class="t-h3" style="margin:0 0 1rem">Schedule open play</h2>

    <form method="post" action="<?= base_url('admin/open-play/open') ?>" class="stack">
      <?= csrf_field() ?>

      <div class="field">
        <label for="court_id">Court</label>
        <select class="select" id="court_id" name="court_id" required>
          <?php foreach ($courts as $court): ?>
            <option value="<?= (int) $court['id'] ?>" <?= $val('court_id') === (string) $court['id'] ? 'selected' : '' ?>>
              <?= esc($court['code'] . ' · ' . $court['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="field">
        <label for="date">Day</label>
        <input class="input" type="date" id="date" name="date" required
               value="<?= esc($val('date', $today)) ?>" min="<?= esc($today) ?>" max="<?= esc($maxDate) ?>">
      </div>

      <div class="row" style="gap:0.75rem">
        <div class="field" style="flex:1;min-width:7rem">
          <label for="start_time">Starts</label>
          <select class="select" id="start_time" name="start_time" required>
            <?php foreach ($times as $time): ?>
              <option value="<?= esc($time) ?>" <?= $val('start_time', $defaultStart) === $time ? 'selected' : '' ?>><?= esc(clock($time)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php /* Finish, not a length: start and finish is what the desk says
                 out loud, and the length is arithmetic. */ ?>
        <div class="field" style="flex:1;min-width:7rem">
          <label for="finish_time">Finishes</label>
          <select class="select" id="finish_time" name="finish_time" required>
            <?php foreach ($times as $time): ?>
              <option value="<?= esc($time) ?>" <?= $val('finish_time', $defaultFinish) === $time ? 'selected' : '' ?>><?= esc(clock($time)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="row" style="gap:0.75rem">
        <div class="field" style="flex:1;min-width:7rem">
          <label for="fee">Fee a head</label>
          <input class="input" type="number" id="fee" name="fee" required min="0" step="10"
                 value="<?= esc($val('fee', '100')) ?>">
        </div>
        <div class="field" style="flex:1;min-width:7rem">
          <label for="capacity">Places</label>
          <input class="input" type="number" id="capacity" name="capacity" required min="1" max="64" step="1"
                 value="<?= esc($val('capacity', '12')) ?>">
        </div>
      </div>

      <div class="field">
        <label for="title">Name it <span class="t-mute">(optional)</span></label>
        <input class="input" type="text" id="title" name="title" maxlength="120"
               placeholder="Thursday night open play" value="<?= esc($val('title')) ?>">
      </div>

      <div class="field">
        <label for="notes">Note <span class="t-mute">(optional)</span></label>
        <input class="input" type="text" id="notes" name="notes" maxlength="255"
               placeholder="Bring your own paddle" value="<?= esc($val('notes')) ?>">
      </div>

      <button class="btn btn-go btn-block" type="submit">Switch open play on</button>
      <p class="field-hint" style="margin:0">
        The court is held for those hours, so nobody can book it. Players can sign up here or on the
        website while it is on.
      </p>
    </form>
  </div>
</div>
<?= $this->endSection() ?>
