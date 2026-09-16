<?= $this->extend($layout) ?>

<?= $this->section('content') ?>
<?php
/**
 * @var list<array<string, mixed>> $rows
 * @var list<array<string, mixed>> $courts
 * @var list<array<string, mixed>> $staff
 * @var array<string, mixed>|null  $editing
 * @var list<string>               $types
 * @var list<string>               $statuses
 */
$val = static function (string $field, string $fallback = '') use ($editing): string {
    $old = old($field);

    return $old !== null ? (string) $old : (string) ($editing[$field] ?? $fallback);
};
$forInput = static fn (?string $sql): string => $sql === null || $sql === '' ? '' : date('Y-m-d\TH:i', strtotime($sql));
$blocking = array_filter($rows, static fn ($r) => in_array($r['status'], ['scheduled', 'in_progress'], true));
?>

<?php /* Stacked, not the usual two columns: seven columns of window detail put
         the action buttons behind a horizontal scroll at any sane sidebar width,
         and scanning the list is the frequent job here, not scheduling. */ ?>
<div class="stack">
  <div class="panel panel-flush">
    <div class="panel-head">
      <h2 class="t-h3" style="margin:0">Windows</h2>
      <span class="t-label"><?= count($blocking) ?> holding slots off the board</span>
    </div>

    <?php if ($rows === []): ?>
      <div class="empty" style="border:0"><p style="margin:0">No maintenance scheduled.</p></div>
    <?php else: ?>
      <div class="table-wrap">
        <table class="table">
          <thead>
            <tr><th>Court</th><th>Job</th><th>Window</th><th>Assigned</th><th class="num">Cost</th><th>Status</th><th></th></tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $row): ?>
              <?php $blocks = in_array($row['status'], ['scheduled', 'in_progress'], true); ?>
              <tr<?= $blocks ? '' : ' style="opacity:0.6"' ?>>
                <td class="mono" style="white-space:nowrap"><?= esc($row['court_code']) ?></td>
                <td>
                  <?= esc($row['title']) ?><br>
                  <span class="t-label"><?= esc(ucfirst($row['type'])) ?></span>
                  <?php if ($blocks): ?>
                    <span class="t-label" style="color:var(--amber)">· off the board</span>
                  <?php endif; ?>
                </td>
                <?php
                // Most windows start and end on the same day, and repeating the
                // date wrapped the column onto six lines.
                $from   = strtotime($row['scheduled_start']);
                $to     = strtotime($row['scheduled_end']);
                $sameDay = date('Y-m-d', $from) === date('Y-m-d', $to);
                ?>
                <td class="mono t-small" style="white-space:nowrap">
                  <?= esc(date('d M, g:i A', $from)) ?><br>
                  <span class="t-mute">to <?= esc($sameDay ? date('g:i A', $to) : date('d M, g:i A', $to)) ?></span>
                </td>
                <td class="t-small"><?= esc($row['first_name'] ? $row['first_name'] . ' ' . $row['last_name'] : 'Unassigned') ?></td>
                <td class="num"><?= (float) $row['cost'] > 0 ? esc(peso($row['cost'])) : '-' ?></td>
                <td>
                  <span class="chip tone-<?= match ($row['status']) { 'completed' => 'open', 'in_progress' => 'live', 'cancelled' => 'closed', default => 'filling' } ?>">
                    <?= esc(ucfirst(str_replace('_', ' ', $row['status']))) ?>
                  </span>
                </td>
                <td>
                  <div class="row" style="gap:0.375rem;flex-wrap:nowrap">
                    <a class="btn btn-ghost btn-sm" href="<?= base_url('admin/maintenance?edit=' . (int) $row['id']) ?>">Edit</a>
                    <?php
                    // One button, the next sensible move for this row.
                    [$next, $label] = match ($row['status']) {
                        'scheduled'   => ['in_progress', 'Start'],
                        'in_progress' => ['completed', 'Done'],
                        default       => ['scheduled', 'Reopen'],
                    };
                    ?>
                    <form method="post" action="<?= base_url('admin/maintenance/' . (int) $row['id'] . '/status') ?>">
                      <?= csrf_field() ?>
                      <input type="hidden" name="to" value="<?= esc($next) ?>">
                      <button class="btn btn-ghost btn-sm" type="submit"><?= esc($label) ?></button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <div class="panel" style="max-width:40rem">
    <div class="row-between" style="margin-bottom:1rem">
      <h2 class="t-h3" style="margin:0"><?= $editing !== null ? 'Edit window' : 'Close a court' ?></h2>
      <?php if ($editing !== null): ?>
        <a class="btn btn-ghost btn-sm" href="<?= base_url('admin/maintenance') ?>">Cancel</a>
      <?php endif; ?>
    </div>

    <form method="post" action="<?= base_url('admin/maintenance/save') ?>" class="stack">
      <?= csrf_field() ?>
      <?php if ($editing !== null): ?>
        <input type="hidden" name="id" value="<?= (int) $editing['id'] ?>">
      <?php endif; ?>

      <div class="field">
        <label for="court_id">Court</label>
        <select class="select" id="court_id" name="court_id" required>
          <?php foreach ($courts as $court): ?>
            <option value="<?= (int) $court['id'] ?>" <?= (int) $val('court_id') === (int) $court['id'] ? 'selected' : '' ?>>
              <?= esc($court['code'] . ' · ' . $court['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="field">
        <label for="title">Job</label>
        <input class="input" type="text" id="title" name="title" required maxlength="160" value="<?= esc($val('title')) ?>">
      </div>

      <div class="row" style="gap:0.75rem">
        <div class="field" style="flex:1;min-width:0">
          <label for="type">Type</label>
          <select class="select" id="type" name="type">
            <?php foreach ($types as $t): ?>
              <option value="<?= esc($t) ?>" <?= $val('type', 'routine') === $t ? 'selected' : '' ?>><?= esc(ucfirst($t)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field" style="flex:1;min-width:0">
          <label for="status">Status</label>
          <select class="select" id="status" name="status">
            <?php foreach ($statuses as $s): ?>
              <option value="<?= esc($s) ?>" <?= $val('status', 'scheduled') === $s ? 'selected' : '' ?>><?= esc(ucfirst(str_replace('_', ' ', $s))) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="field">
        <label for="scheduled_start">From</label>
        <input class="input" type="datetime-local" id="scheduled_start" name="scheduled_start" required
               value="<?= esc(old('scheduled_start') ?? $forInput($editing['scheduled_start'] ?? null)) ?>">
      </div>

      <div class="field">
        <label for="scheduled_end">To</label>
        <input class="input" type="datetime-local" id="scheduled_end" name="scheduled_end" required
               value="<?= esc(old('scheduled_end') ?? $forInput($editing['scheduled_end'] ?? null)) ?>">
        <span class="field-hint">Scheduled and in-progress windows take those cells off the public board.</span>
      </div>

      <div class="row" style="gap:0.75rem">
        <div class="field" style="flex:1;min-width:0">
          <label for="assigned_to">Assigned to</label>
          <select class="select" id="assigned_to" name="assigned_to">
            <option value="">Unassigned</option>
            <?php foreach ($staff as $person): ?>
              <option value="<?= (int) $person['id'] ?>" <?= (int) $val('assigned_to') === (int) $person['id'] ? 'selected' : '' ?>>
                <?= esc($person['first_name'] . ' ' . $person['last_name']) ?> · <?= esc($person['role_name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field" style="flex:0 0 9rem">
          <label for="cost">Cost</label>
          <input class="input" type="number" id="cost" name="cost" min="0" step="0.01" value="<?= esc($val('cost', '0')) ?>">
        </div>
      </div>

      <div class="field">
        <label for="description">Notes</label>
        <textarea class="textarea" id="description" name="description" maxlength="2000"><?= esc($val('description')) ?></textarea>
      </div>

      <button class="btn" type="submit"><?= $editing !== null ? 'Save changes' : 'Schedule it' ?></button>
    </form>
  </div>
</div>
<?= $this->endSection() ?>
