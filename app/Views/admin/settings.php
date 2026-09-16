<?= $this->extend($layout) ?>

<?= $this->section('content') ?>
<?php
/**
 * @var array<string, list<array<string, mixed>>> $groups rows keyed by group
 * @var array<string, string>                     $posted values from a rejected save
 * @var array<string, string>                     $errors per-key messages
 */

// The control follows the stored type, plus the two key shapes that have a
// better native input than a text box.
$controlFor = static function (array $row): string {
    if ($row['type'] === 'bool') {
        return 'checkbox';
    }
    if ($row['type'] === 'int') {
        return 'number';
    }
    if ($row['type'] === 'json') {
        return 'textarea';
    }
    if (preg_match('/\.(open|close|peak_start|peak_end|last_start)$/', $row['key']) === 1) {
        return 'time';
    }
    if (str_contains($row['key'], 'email')) {
        return 'email';
    }
    if (str_contains($row['key'], 'url')) {
        return 'url';
    }

    return 'text';
};
?>

<form method="post" action="<?= base_url('admin/settings') ?>" class="stack">
  <?= csrf_field() ?>

  <?php if ($errors !== []): ?>
    <div class="alert tone-booked" role="alert">
      <b>Not saved</b>
      <span>Every value below is unchanged until the highlighted fields are valid.</span>
    </div>
  <?php endif; ?>

  <?php foreach ($groups as $group => $rows): ?>
    <div class="panel">
      <div class="row-between" style="margin-bottom:1rem">
        <h2 class="t-h3" style="margin:0"><?= esc(ucfirst($group)) ?></h2>
        <span class="t-label"><?= count($rows) ?> setting<?= count($rows) === 1 ? '' : 's' ?></span>
      </div>

      <div class="grid-auto" style="gap:1rem">
        <?php foreach ($rows as $row): ?>
          <?php
          $key     = $row['key'];
          $control = $controlFor($row);
          $error   = $errors[$key] ?? null;
          // Their rejected typing wins over the stored value, so a bad time
          // does not throw away the four fields they got right.
          $value   = array_key_exists($key, $posted) ? (string) $posted[$key] : (string) $row['value'];
          $id      = 'set_' . preg_replace('/[^a-z0-9]+/i', '_', $key);
          ?>
          <div class="field">
            <label for="<?= esc($id) ?>"><?= esc($row['label'] ?? $key) ?></label>

            <?php if ($control === 'checkbox'): ?>
              <label style="display:flex;align-items:center;gap:0.625rem;min-height:46px;text-transform:none;letter-spacing:0;font-family:inherit;color:var(--fg)">
                <input type="checkbox" id="<?= esc($id) ?>" name="s[<?= esc($key) ?>]" value="1" <?= $value === '1' ? 'checked' : '' ?>>
                <span class="t-small">Enabled</span>
              </label>
            <?php elseif ($control === 'textarea'): ?>
              <textarea class="textarea mono" id="<?= esc($id) ?>" name="s[<?= esc($key) ?>]"
                        <?= $error ? 'aria-invalid="true"' : '' ?>><?= esc($value) ?></textarea>
            <?php else: ?>
              <input class="input" type="<?= esc($control) ?>" id="<?= esc($id) ?>"
                     name="s[<?= esc($key) ?>]" value="<?= esc($value) ?>"
                     <?= $control === 'number' ? 'min="0" step="1"' : '' ?>
                     <?= $error ? 'aria-invalid="true"' : '' ?>>
            <?php endif; ?>

            <?php if ($error !== null): ?>
              <span class="field-error"><?= esc($error) ?></span>
            <?php endif; ?>
            <span class="field-hint mono" style="font-size:0.6875rem"><?= esc($key) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endforeach; ?>

  <div class="row" style="gap:0.75rem;align-items:center">
    <button class="btn" type="submit">Save settings</button>
    <span class="t-small t-mute">
      Times and slot length rebuild the availability board for every visitor the moment you save.
    </span>
  </div>
</form>
<?= $this->endSection() ?>
