<?= $this->extend($layout) ?>

<?= $this->section('content') ?>
<?php
/**
 * @var list<array<string,mixed>>  $expected
 * @var list<array<string,mixed>>  $arrived
 * @var array<string,mixed>|false  $found  the booking just admitted
 */
$now = date('H:i:s');
?>

<div class="panel" style="margin-bottom:1rem">
  <form method="post" action="<?= base_url('admin/checkin/scan') ?>">
    <?= csrf_field() ?>
    <div class="row" style="gap:0.75rem;align-items:flex-end">
      <div class="field" style="flex:1;min-width:0">
        <label for="code">Scan or type</label>
        <?php /* autofocus so a scanner, which is just a keyboard that types and
                 hits Enter, works the moment the page loads. */ ?>
        <input class="input mono" type="text" id="code" name="code" autofocus autocomplete="off"
               placeholder="BK-260813-4F2A or a scanned token"
               style="font-size:1.125rem;letter-spacing:0.04em">
      </div>
      <button class="btn btn-lg" type="submit" style="flex:none">Check in</button>
    </div>
    <p class="field-hint" style="margin:0.625rem 0 0">
      Any USB or Bluetooth scanner works: they type the code and press Enter, which submits this form.
    </p>
  </form>
</div>

<?php if (is_array($found)): ?>
  <div class="panel" style="margin-bottom:1rem;border-color:var(--green)">
    <div class="row-between" style="gap:1.5rem;flex-wrap:wrap">
      <div>
        <span class="t-label" style="color:var(--green)">On court</span>
        <h2 class="t-h2" style="margin:0.25rem 0 0.5rem"><?= esc($found['court']) ?></h2>
        <p class="mono" style="margin:0"><?= esc($found['reference']) ?> · <?= esc($found['time']) ?></p>
        <p class="t-small t-mute" style="margin:0.25rem 0 0">
          <?= esc($found['who']) ?> · <?= (int) $found['players'] ?> player<?= (int) $found['players'] === 1 ? '' : 's' ?>
        </p>
      </div>

      <?php if ((float) $found['due'] > 0): ?>
        <div style="text-align:right">
          <span class="t-label" style="color:var(--amber)">Still to collect</span>
          <div class="t-metric" style="color:var(--amber)"><?= esc(peso($found['due'])) ?></div>
          <a class="btn btn-sm" style="margin-top:0.5rem" href="<?= base_url('admin/payments?booking=' . (int) $found['id']) ?>">Take payment</a>
        </div>
      <?php else: ?>
        <div style="text-align:right">
          <span class="t-label" style="color:var(--green)">Paid in full</span>
          <div class="t-metric" style="color:var(--green)">✓</div>
        </div>
      <?php endif; ?>
    </div>
  </div>
<?php endif; ?>

<div class="admin-cols">
  <div class="panel panel-flush">
    <div class="panel-head">
      <h2 class="t-h3" style="margin:0">Expected today</h2>
      <span class="t-label"><?= count($expected) ?> still to arrive</span>
    </div>

    <?php if ($expected === []): ?>
      <div class="empty" style="border:0"><p style="margin:0">Everyone booked today is already on court.</p></div>
    <?php else: ?>
      <div class="table-wrap">
        <table class="table">
          <thead>
            <tr><th>Time</th><th>Court</th><th>Who</th><th>Payment</th><th></th></tr>
          </thead>
          <tbody>
            <?php foreach ($expected as $row): ?>
              <?php
              $late = $row['start_time'] < $now;
              $who  = $row['first_name'] ? $row['first_name'] . ' ' . $row['last_name'] : ($row['guest_name'] ?: 'Guest');
              ?>
              <tr>
                <td class="mono t-small" style="white-space:nowrap">
                  <?= esc(clock($row['start_time'])) ?>
                  <?php if ($late): ?><br><span class="t-label" style="color:var(--amber)">started</span><?php endif; ?>
                </td>
                <td class="mono"><?= esc($row['court_code']) ?></td>
                <td>
                  <?= esc($who) ?><br>
                  <span class="mono t-small t-mute"><?= esc($row['reference']) ?></span>
                  <?php $players = pipes($row['player_names'] ?? null); ?>
                  <?php if (count($players) > 1): ?>
                    <br><span class="t-small t-mute">with <?= esc(implode(', ', array_slice($players, 1))) ?></span>
                  <?php endif; ?>
                </td>
                <td>
                  <span class="chip tone-<?= $row['payment_status'] === 'paid' ? 'open' : ($row['payment_status'] === 'partial' ? 'filling' : 'booked') ?>">
                    <?= esc(ucfirst($row['payment_status'])) ?>
                  </span>
                </td>
                <td>
                  <form method="post" action="<?= base_url('admin/checkin/' . (int) $row['id'] . '/admit') ?>">
                    <?= csrf_field() ?>
                    <button class="btn btn-sm <?= $late ? '' : 'btn-ghost' ?>" type="submit">Check in</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <div class="panel panel-flush">
    <div class="panel-head">
      <h2 class="t-h3" style="margin:0">On court</h2>
      <span class="t-label"><?= count($arrived) ?> checked in</span>
    </div>

    <?php if ($arrived === []): ?>
      <div class="empty" style="border:0"><p style="margin:0">Nobody has checked in yet today.</p></div>
    <?php else: ?>
      <div class="table-wrap">
        <table class="table">
          <thead>
            <tr><th>Court</th><th>Who</th><th>Session</th><th>In</th></tr>
          </thead>
          <tbody>
            <?php foreach ($arrived as $row): ?>
              <tr>
                <td class="mono"><?= esc($row['court_code']) ?></td>
                <td><?= esc($row['first_name'] ? $row['first_name'] . ' ' . $row['last_name'] : ($row['guest_name'] ?: 'Guest')) ?></td>
                <td class="mono t-small" style="white-space:nowrap"><?= esc(clock($row['start_time'])) ?> to <?= esc(clock($row['end_time'])) ?></td>
                <td class="mono t-small"><?= esc($row['checked_in_at'] ? clock(date('H:i', strtotime((string) $row['checked_in_at']))) : '-') ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>
<?= $this->endSection() ?>
