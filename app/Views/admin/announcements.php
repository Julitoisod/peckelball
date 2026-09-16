<?= $this->extend($layout) ?>

<?= $this->section('content') ?>
<?php
/**
 * @var list<array<string, mixed>>      $rows
 * @var array<string, mixed>|null       $editing  row loaded by ?edit=<id>
 * @var list<string>                    $audiences
 */

// Rejected input wins over the row being edited, which wins over blank.
$val = static function (string $field, string $fallback = '') use ($editing): string {
    $old = old($field);

    return $old !== null ? (string) $old : (string) ($editing[$field] ?? $fallback);
};

$forInput = static fn (?string $sql): string => $sql === null || $sql === '' ? '' : date('Y-m-d\TH:i', strtotime($sql));
$now      = date('Y-m-d\TH:i');
?>

<div class="admin-cols">
  <div class="panel panel-flush">
    <div class="panel-head">
      <h2 class="t-h3" style="margin:0">Posted</h2>
      <span class="t-label"><?= count($rows) ?> total</span>
    </div>

    <?php if ($rows === []): ?>
      <div class="empty" style="border:0">
        <p style="margin:0">Nothing posted yet. The first public announcement shows up on the landing page.</p>
      </div>
    <?php else: ?>
      <div class="table-wrap">
        <table class="table">
          <thead>
            <tr><th>Announcement</th><th>Audience</th><th>Window</th><th>By</th><th></th></tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $row): ?>
              <?php
              $live = $row['audience'] === 'public'
                  && $row['published_at'] !== null
                  && $row['published_at'] <= date('Y-m-d H:i:s')
                  && ($row['expires_at'] === null || $row['expires_at'] >= date('Y-m-d H:i:s'));
              ?>
              <tr>
                <td>
                  <?php if ($row['is_pinned']): ?><span class="chip tone-live" style="padding:0.125rem 0.375rem">Pinned</span> <?php endif; ?>
                  <?= esc($row['title']) ?><br>
                  <span class="t-small t-mute"><?= esc(mb_strimwidth(strip_tags($row['body']), 0, 90, '…')) ?></span>
                </td>
                <td>
                  <span class="chip tone-<?= $live ? 'open' : 'closed' ?>">
                    <?= esc($row['audience']) ?>
                  </span>
                  <?php if ($live): ?><br><span class="t-label" style="color:var(--green)">On the front page</span><?php endif; ?>
                </td>
                <td class="mono t-small">
                  <?= esc($row['published_at'] === null ? 'not published' : date('d M, g:i A', strtotime($row['published_at']))) ?><br>
                  <span class="t-mute">
                    <?= esc($row['expires_at'] === null ? 'no expiry' : 'to ' . date('d M, g:i A', strtotime($row['expires_at']))) ?>
                  </span>
                </td>
                <td class="t-small"><?= esc($row['first_name'] ? $row['first_name'] . ' ' . $row['last_name'] : 'System') ?></td>
                <td>
                  <div class="row" style="gap:0.375rem;flex-wrap:nowrap">
                    <a class="btn btn-ghost btn-sm" href="<?= base_url('admin/announcements?edit=' . (int) $row['id']) ?>">Edit</a>
                    <form method="post" action="<?= base_url('admin/announcements/' . (int) $row['id'] . '/delete') ?>"
                          onsubmit="return confirm('Delete &quot;<?= esc($row['title'], 'js') ?>&quot;? This cannot be undone.')">
                      <?= csrf_field() ?>
                      <button class="btn btn-ghost btn-sm" type="submit">Delete</button>
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

  <div class="panel">
    <div class="row-between" style="margin-bottom:1rem">
      <h2 class="t-h3" style="margin:0"><?= $editing !== null ? 'Edit announcement' : 'New announcement' ?></h2>
      <?php if ($editing !== null): ?>
        <a class="btn btn-ghost btn-sm" href="<?= base_url('admin/announcements') ?>">Cancel</a>
      <?php endif; ?>
    </div>

    <form method="post" action="<?= base_url('admin/announcements/save') ?>" class="stack">
      <?= csrf_field() ?>
      <?php if ($editing !== null): ?>
        <input type="hidden" name="id" value="<?= (int) $editing['id'] ?>">
      <?php endif; ?>

      <div class="field">
        <label for="title">Title</label>
        <input class="input" type="text" id="title" name="title" required maxlength="160" value="<?= esc($val('title')) ?>">
      </div>

      <div class="field">
        <label for="body">Body</label>
        <textarea class="textarea" id="body" name="body" required maxlength="5000"><?= esc($val('body')) ?></textarea>
        <span class="field-hint">The landing page shows the first 140 characters.</span>
      </div>

      <div class="field">
        <label for="audience">Audience</label>
        <select class="select" id="audience" name="audience">
          <?php foreach ($audiences as $a): ?>
            <option value="<?= esc($a) ?>" <?= $val('audience', 'public') === $a ? 'selected' : '' ?>>
              <?= esc(ucfirst($a)) ?><?= $a === 'public' ? ' (landing page)' : '' ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="field">
        <label for="published_at">Publish at</label>
        <input class="input" type="datetime-local" id="published_at" name="published_at"
               value="<?= esc(old('published_at') ?? $forInput($editing['published_at'] ?? null) ?: $now) ?>">
      </div>

      <div class="field">
        <label for="expires_at">Expires</label>
        <input class="input" type="datetime-local" id="expires_at" name="expires_at"
               value="<?= esc(old('expires_at') ?? $forInput($editing['expires_at'] ?? null)) ?>">
        <span class="field-hint">Leave empty and it runs until you delete it.</span>
      </div>

      <label style="display:flex;align-items:center;gap:0.625rem">
        <input type="checkbox" name="is_pinned" value="1" <?= $val('is_pinned') === '1' ? 'checked' : '' ?>>
        <span class="t-small">Pin above the others</span>
      </label>

      <button class="btn" type="submit"><?= $editing !== null ? 'Save changes' : 'Post announcement' ?></button>
    </form>
  </div>
</div>
<?= $this->endSection() ?>
