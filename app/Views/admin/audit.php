<?= $this->extend($layout) ?>

<?= $this->section('content') ?>
<div class="panel panel-flush">
  <div class="panel-head">
    <h2 class="t-h3" style="margin:0">Audit log</h2>
    <span class="t-label">Newest <?= count($logs) ?> entries · append-only</span>
  </div>

  <?php if ($logs === []): ?>
    <div class="empty" style="border:0"><p style="margin:0">Nothing logged yet.</p></div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr><th>When</th><th>Action</th><th>Actor</th><th>Entity</th><th>Detail</th><th>IP</th></tr>
        </thead>
        <tbody>
          <?php foreach ($logs as $log): ?>
            <tr>
              <td class="mono t-small" style="white-space:nowrap">
                <?= esc(date('d M', strtotime($log['created_at']))) ?><br>
                <span class="t-mute"><?= esc(date('g:i:s A', strtotime($log['created_at']))) ?></span>
              </td>
              <td class="mono" style="color:var(--blue-ink);font-size:0.75rem"><?= esc($log['action']) ?></td>
              <td class="t-small">
                <?= esc($log['first_name'] ? $log['first_name'] . ' ' . $log['last_name'] : 'System') ?><br>
                <span class="t-label"><?= esc($log['email'] ?? '') ?></span>
              </td>
              <td class="t-label"><?= esc($log['entity_type'] ?? '—') ?><?= $log['entity_id'] ? ' #' . (int) $log['entity_id'] : '' ?></td>
              <td class="t-small t-mute"><?= esc($log['summary'] ?? '—') ?></td>
              <td class="mono t-small t-mute"><?= esc($log['ip_address'] ?? '—') ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
<?= $this->endSection() ?>
