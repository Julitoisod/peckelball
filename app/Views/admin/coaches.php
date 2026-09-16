<?= $this->extend($layout) ?>

<?= $this->section('content') ?>
<?php
/**
 * @var list<array<string,mixed>> $coaches
 * @var array<string,mixed>|null  $editing   with an `availability` list
 * @var list<array<string,mixed>> $linkable  accounts with no coach profile yet
 * @var list<string>              $days
 */
$val = static function (string $field, string $fallback = '') use ($editing): string {
    $old = old($field);

    return $old !== null ? (string) $old : (string) ($editing[$field] ?? $fallback);
};
?>

<div class="panel panel-flush" style="margin-bottom:1rem">
  <div class="panel-head">
    <h2 class="t-h3" style="margin:0">Coaching team</h2>
    <span class="t-label"><?= count(array_filter($coaches, static fn ($c) => (int) $c['is_accepting'] === 1)) ?> taking sessions of <?= count($coaches) ?></span>
  </div>

  <?php if ($coaches === []): ?>
    <div class="empty" style="border:0"><p style="margin:0">No coaches yet. Link an account below to create the first profile.</p></div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr><th>Coach</th><th>Headline</th><th class="num">Rate</th><th class="num">Group</th><th class="num">Rating</th><th class="num">Sessions</th><th>State</th><th></th></tr>
        </thead>
        <tbody>
          <?php foreach ($coaches as $c): ?>
            <tr<?= (int) $c['is_accepting'] ? '' : ' style="opacity:0.6"' ?>>
              <td>
                <?= esc($c['first_name'] . ' ' . $c['last_name']) ?><br>
                <span class="t-small t-mute"><?= esc($c['email']) ?></span>
              </td>
              <td class="t-small"><?= esc(mb_strimwidth((string) $c['headline'], 0, 60, '…')) ?></td>
              <td class="num"><?= esc(peso($c['hourly_rate'])) ?></td>
              <td class="num"><?= (float) $c['group_rate'] > 0 ? esc(peso($c['group_rate'])) : '-' ?></td>
              <td class="num"><?= esc(number_format((float) $c['rating'], 1)) ?></td>
              <td class="num"><?= (int) $c['sessions_taught'] ?></td>
              <td>
                <span class="chip tone-<?= (int) $c['is_accepting'] ? 'open' : 'closed' ?>">
                  <?= (int) $c['is_accepting'] ? 'Accepting' : 'Paused' ?>
                </span>
              </td>
              <td>
                <div class="row" style="gap:0.375rem;flex-wrap:nowrap">
                  <a class="btn btn-ghost btn-sm" href="<?= base_url('admin/coaches?edit=' . (int) $c['id']) ?>">Edit</a>
                  <form method="post" action="<?= base_url('admin/coaches/' . (int) $c['id'] . '/toggle') ?>">
                    <?= csrf_field() ?>
                    <button class="btn btn-ghost btn-sm" type="submit"><?= (int) $c['is_accepting'] ? 'Pause' : 'Resume' ?></button>
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

<div class="admin-cols">
  <div class="panel">
    <div class="row-between" style="margin-bottom:1rem">
      <h2 class="t-h3" style="margin:0"><?= $editing !== null ? 'Edit ' . esc($editing['first_name']) : 'New coach' ?></h2>
      <?php if ($editing !== null): ?>
        <a class="btn btn-ghost btn-sm" href="<?= base_url('admin/coaches') ?>">Cancel</a>
      <?php endif; ?>
    </div>

    <form method="post" action="<?= base_url('admin/coaches/save') ?>" class="stack">
      <?= csrf_field() ?>
      <?php if ($editing !== null): ?>
        <input type="hidden" name="id" value="<?= (int) $editing['id'] ?>">
      <?php else: ?>
        <div class="field">
          <label for="user_id">Account</label>
          <select class="select" id="user_id" name="user_id" required>
            <option value="">Pick the account they sign in with</option>
            <?php foreach ($linkable as $u): ?>
              <option value="<?= (int) $u['id'] ?>" <?= (int) old('user_id') === (int) $u['id'] ? 'selected' : '' ?>>
                <?= esc($u['first_name'] . ' ' . $u['last_name']) ?> · <?= esc($u['email']) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <span class="field-hint">Only accounts without a coach profile are listed.</span>
        </div>
      <?php endif; ?>

      <div class="field">
        <label for="headline">Headline</label>
        <input class="input" type="text" id="headline" name="headline" maxlength="160" value="<?= esc($val('headline')) ?>">
      </div>

      <div class="field">
        <label for="bio">Bio</label>
        <textarea class="textarea" id="bio" name="bio" maxlength="3000"><?= esc($val('bio')) ?></textarea>
      </div>

      <div class="field">
        <label for="specialties">Specialties</label>
        <input class="input" type="text" id="specialties" name="specialties" maxlength="500" value="<?= esc($val('specialties')) ?>">
        <span class="field-hint">Separate with a pipe: Juniors|Footwork|Group clinics</span>
      </div>

      <div class="field">
        <label for="certifications">Certifications</label>
        <input class="input" type="text" id="certifications" name="certifications" maxlength="500" value="<?= esc($val('certifications')) ?>">
      </div>

      <div class="row" style="gap:0.75rem">
        <div class="field" style="flex:1;min-width:0">
          <label for="hourly_rate">Hourly rate</label>
          <input class="input" type="number" id="hourly_rate" name="hourly_rate" required min="0" step="0.01" value="<?= esc($val('hourly_rate', '0')) ?>">
        </div>
        <div class="field" style="flex:1;min-width:0">
          <label for="group_rate">Group rate</label>
          <input class="input" type="number" id="group_rate" name="group_rate" min="0" step="0.01" value="<?= esc($val('group_rate', '0')) ?>">
        </div>
      </div>

      <div class="row" style="gap:0.75rem">
        <div class="field" style="flex:1;min-width:0">
          <label for="years_experience">Years coaching</label>
          <input class="input" type="number" id="years_experience" name="years_experience" min="0" max="70" step="1" value="<?= esc($val('years_experience', '0')) ?>">
        </div>
        <div class="field" style="flex:1;min-width:0">
          <label for="sort_order">Order</label>
          <input class="input" type="number" id="sort_order" name="sort_order" min="0" step="1" value="<?= esc($val('sort_order', '0')) ?>">
        </div>
      </div>

      <label style="display:flex;align-items:center;gap:0.625rem">
        <input type="checkbox" name="is_accepting" value="1" <?= ($editing === null && old('headline') === null) || $val('is_accepting') === '1' ? 'checked' : '' ?>>
        <span class="t-small">Listed on the coaching page</span>
      </label>

      <?php if ($editing !== null): ?>
        <p class="field-hint" style="margin:0">
          Rating <?= esc(number_format((float) $editing['rating'], 1)) ?> and <?= (int) $editing['sessions_taught'] ?> sessions taught are earned figures, not editable here.
        </p>
      <?php endif; ?>

      <button class="btn" type="submit"><?= $editing !== null ? 'Save profile' : 'Create coach' ?></button>
    </form>
  </div>

  <?php if ($editing !== null): ?>
    <div class="panel">
      <h2 class="t-h3" style="margin:0 0 1rem">Hours <?= esc($editing['first_name']) ?> teaches</h2>

      <?php if ($editing['availability'] === []): ?>
        <div class="empty" style="border:0;margin-bottom:1rem">
          <p style="margin:0">No windows set. Without them the booking form has no coaching hours to offer.</p>
        </div>
      <?php else: ?>
        <div class="table-wrap" style="margin-bottom:1rem">
          <table class="table">
            <thead><tr><th>Day</th><th>From</th><th>To</th><th></th></tr></thead>
            <tbody>
              <?php foreach ($editing['availability'] as $slot): ?>
                <tr>
                  <td><?= esc($days[(int) $slot['day_of_week']] ?? '?') ?></td>
                  <td class="mono t-small"><?= esc(clock($slot['starts_at'])) ?></td>
                  <td class="mono t-small"><?= esc(clock($slot['ends_at'])) ?></td>
                  <td>
                    <form method="post" action="<?= base_url('admin/coaches/' . (int) $editing['id'] . '/slots/' . (int) $slot['id'] . '/drop') ?>">
                      <?= csrf_field() ?>
                      <button class="btn btn-ghost btn-sm" type="submit">Remove</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>

      <form method="post" action="<?= base_url('admin/coaches/' . (int) $editing['id'] . '/slots') ?>" class="row" style="gap:0.5rem;align-items:flex-end">
        <?= csrf_field() ?>
        <div class="field" style="flex:1;min-width:0">
          <label for="day_of_week">Day</label>
          <select class="select" id="day_of_week" name="day_of_week">
            <?php foreach ($days as $i => $day): ?>
              <option value="<?= $i ?>"><?= esc($day) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field" style="flex:0 0 7rem">
          <label for="starts_at">From</label>
          <input class="input" type="time" id="starts_at" name="starts_at" required value="08:00">
        </div>
        <div class="field" style="flex:0 0 7rem">
          <label for="ends_at">To</label>
          <input class="input" type="time" id="ends_at" name="ends_at" required value="12:00">
        </div>
        <button class="btn" type="submit" style="flex:none">Add</button>
      </form>
    </div>
  <?php endif; ?>
</div>
<?= $this->endSection() ?>
