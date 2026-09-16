<?= $this->extend($layout) ?>

<?= $this->section('content') ?>
<div class="kpi-grid">
  <?php foreach ($courts as $court): ?>
    <?php
    $m   = status_meta($court['day_state']);
    $rev = $revenue[$court['id']] ?? null;
    ?>
    <div class="kpi">
      <div class="row-between" style="margin-bottom:0.5rem">
        <span class="mono" style="font-size:0.8125rem;font-weight:600"><?= esc($court['code']) ?></span>
        <span class="chip tone-<?= esc($m['tone']) ?>"><span class="chip-glyph" aria-hidden="true"><?= $m['glyph'] ?></span><?= esc($m['label']) ?></span>
      </div>
      <span class="t-metric" style="font-size:1.5rem"><?= esc(peso($rev['total'] ?? 0)) ?></span>
      <span class="t-label">This month · <?= (int) ($rev['n'] ?? 0) ?> bookings</span>
      <p class="t-small t-mute" style="margin:0.5rem 0 0">
        <?= esc($court['name']) ?> · <?= esc(ucfirst($court['surface_type'])) ?><br>
        <?= (int) $court['open_slots'] ?> slots free today
      </p>
    </div>
  <?php endforeach; ?>
</div>

<div class="panel panel-flush" style="margin-bottom:1rem">
  <div class="panel-head">
    <h2 class="t-h3" style="margin:0">Courts</h2>
    <span class="t-label"><?= count($courts) ?> in service</span>
  </div>
  <div class="table-wrap">
    <table class="table">
      <thead>
        <tr><th>Code</th><th>Name</th><th>Type</th><th>Surface</th><th>Status</th><th class="num">Off-peak</th><th class="num">Peak</th><th class="num">Free today</th><th></th></tr>
      </thead>
      <tbody>
        <?php foreach ($courts as $court): ?>
          <?php $m = status_meta($court['day_state']); ?>
          <tr>
            <td class="mono"><?= esc($court['code']) ?></td>
            <td><?= esc($court['name']) ?></td>
            <td class="t-label"><?= esc(ucfirst($court['surface_type'])) ?></td>
            <td class="t-small t-mute"><?= esc($court['surface'] ?? '—') ?></td>
            <td>
              <span class="chip tone-<?= $court['status'] === 'active' ? 'open' : 'filling' ?>"><?= esc(ucfirst($court['status'])) ?></span>
            </td>
            <td class="num"><?= esc(peso($court['hourly_rate'])) ?></td>
            <td class="num"><?= esc(peso($court['peak_rate'])) ?></td>
            <td class="num" style="color:var(--<?= $m['tone'] === 'open' ? 'green' : ($m['tone'] === 'filling' ? 'amber' : 'red') ?>)"><?= (int) $court['open_slots'] ?></td>
            <td>
              <div class="row" style="gap:0.375rem;flex-wrap:nowrap">
                <a class="btn btn-ghost btn-sm" href="<?= base_url('courts/' . $court['slug']) ?>">View</a>
                <?php if ($canManage): ?>
                  <a class="btn btn-ghost btn-sm" href="<?= base_url('admin/courts?edit=' . (int) $court['id']) ?>">Edit</a>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php if ($canManage && $editing !== null): ?>
  <div class="panel" style="margin-bottom:1rem;max-width:44rem">
    <div class="row-between" style="margin-bottom:1rem">
      <h2 class="t-h3" style="margin:0">Edit <?= esc($editing['code']) ?></h2>
      <a class="btn btn-ghost btn-sm" href="<?= base_url('admin/courts') ?>">Cancel</a>
    </div>

    <form method="post" action="<?= base_url('admin/courts/' . (int) $editing['id'] . '/update') ?>" class="stack">
      <?= csrf_field() ?>
      <?php $val = static fn (string $f): string => (string) (old($f) ?? $editing[$f] ?? ''); ?>

      <div class="field">
        <label for="name">Name</label>
        <input class="input" type="text" id="name" name="name" required maxlength="120" value="<?= esc($val('name')) ?>">
      </div>

      <div class="row" style="gap:0.75rem">
        <div class="field" style="flex:1;min-width:0">
          <label for="surface_type">Where it sits</label>
          <select class="select" id="surface_type" name="surface_type">
            <?php foreach ($surfaces as $s): ?>
              <option value="<?= esc($s) ?>" <?= $val('surface_type') === $s ? 'selected' : '' ?>><?= esc(ucfirst($s)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field" style="flex:1;min-width:0">
          <label for="surface">What it is made of</label>
          <input class="input" type="text" id="surface" name="surface" maxlength="120" value="<?= esc($val('surface')) ?>"
                 placeholder="Acrylic over concrete">
        </div>
      </div>

      <div class="row" style="gap:0.75rem">
        <div class="field" style="flex:1;min-width:0">
          <label for="hourly_rate">Off-peak rate</label>
          <input class="input" type="number" id="hourly_rate" name="hourly_rate" required min="1" step="0.01" value="<?= esc($val('hourly_rate')) ?>">
        </div>
        <div class="field" style="flex:1;min-width:0">
          <label for="peak_rate">Peak rate</label>
          <input class="input" type="number" id="peak_rate" name="peak_rate" required min="1" step="0.01" value="<?= esc($val('peak_rate')) ?>">
        </div>
        <div class="field" style="flex:1;min-width:0">
          <label for="cstatus">Status</label>
          <select class="select" id="cstatus" name="status">
            <?php foreach ($states as $s): ?>
              <option value="<?= esc($s) ?>" <?= $val('status') === $s ? 'selected' : '' ?>><?= esc(ucfirst($s)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <p class="field-hint" style="margin:0">
        Anything other than active takes the court off the public board immediately.
        Rates apply to new bookings; sessions already booked keep the price they were quoted.
      </p>

      <button class="btn" type="submit">Save <?= esc($editing['code']) ?></button>
    </form>
  </div>
<?php endif; ?>

<div class="panel panel-flush">
  <div class="panel-head">
    <h2 class="t-h3" style="margin:0">Maintenance schedule</h2>
    <span class="t-label"><?= count($maintenance) ?> jobs</span>
  </div>

  <?php if ($maintenance === []): ?>
    <div class="empty" style="border:0"><p style="margin:0">Nothing on the maintenance schedule.</p></div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr><th>Court</th><th>Job</th><th>Type</th><th>Window</th><th>Assigned</th><th>Status</th><th class="num">Cost</th></tr>
        </thead>
        <tbody>
          <?php foreach ($maintenance as $m): ?>
            <tr>
              <td class="mono"><?= esc($m['court_code']) ?></td>
              <td>
                <?= esc($m['title']) ?><br>
                <span class="t-small t-mute"><?= esc(mb_strimwidth((string) $m['description'], 0, 70, '…')) ?></span>
              </td>
              <td class="t-label"><?= esc(ucfirst($m['type'])) ?></td>
              <td class="mono t-small">
                <?= esc(date('d M, g:i A', strtotime($m['scheduled_start']))) ?><br>
                <span class="t-mute">to <?= esc(date('g:i A', strtotime($m['scheduled_end']))) ?></span>
              </td>
              <td class="t-small"><?= esc($m['first_name'] ? $m['first_name'] . ' ' . $m['last_name'] : 'Unassigned') ?></td>
              <td>
                <span class="chip tone-<?= match ($m['status']) { 'completed' => 'open', 'in_progress' => 'live', 'cancelled' => 'closed', default => 'filling' } ?>">
                  <?= esc(ucfirst(str_replace('_', ' ', $m['status']))) ?>
                </span>
              </td>
              <td class="num"><?= esc(peso($m['cost'])) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
<?= $this->endSection() ?>
