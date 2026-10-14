<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= esc($title) ?></title>
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#07FA1B">
<link rel="icon" href="<?= base_url('assets/icons/favicon-32.png') ?>" sizes="32x32" type="image/png">
<link rel="preload" href="<?= base_url('assets/fonts/archivo-latin.woff2') ?>" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="<?= base_url('assets/fonts/chivomono-latin.woff2') ?>" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= base_url('assets/css/app.css?v=28') ?>">
<link rel="stylesheet" href="<?= base_url('assets/css/admin.css?v=4') ?>">
<script>document.documentElement.classList.add('js');</script>
</head>
<body class="admin">
<a class="skip-link" href="#main">Skip to content</a>

<?php
$user = $auth->user();
$here = trim(uri_string(), '/');
$on   = static fn (string $p): bool => $p === 'admin' ? $here === 'admin' : str_starts_with($here, $p);

// Only render what this role can actually reach — a sidebar full of links that
// bounce you back to the dashboard is worse than a short one.
$nav = [
    ['Front desk', [
        ['admin/checkin',     'Check-in',         'checkin.scan'],
        ['admin/walkins',     'Walk-ins',         'bookings.walkin'],
        ['admin/open-play',   'Open play',        'openplay.view'],
        ['admin/payments',    'Payments',         'payments.view'],
    ]],
    ['Operations', [
        ['admin',             'Overview',         'dashboard.view'],
        ['admin/bookings',    'Booking calendar', 'bookings.view'],
        ['admin/courts',      'Courts',           'courts.view'],
        ['admin/maintenance', 'Maintenance',      'maintenance.manage'],
    ]],
    ['People', [
        ['admin/customers',   'Customers',        'customers.manage'],
        ['admin/memberships', 'Memberships',      'memberships.manage'],
        ['admin/coaches',     'Coaches',          'coaches.manage'],
        ['admin/staff',       'Staff',            'staff.manage'],
    ]],
    ['Coaching', [
        ['admin/sessions',    'My sessions',      'sessions.own'],
    ]],
    ['Compete', [
        ['admin/tournaments', 'Tournaments',      'tournaments.manage'],
        ['admin/scoring',     'Live scoring',     'tournaments.score'],
    ]],
    ['Catalogue', [
        ['admin/equipment',   'Equipment',        'equipment.manage'],
        ['admin/inventory',   'Inventory',        'products.manage'],
        ['admin/promos',      'Promo codes',      'promos.manage'],
    ]],
    ['System', [
        ['admin/reports',       'Reports',        'reports.analytics'],
        ['admin/announcements', 'Announcements',  'announcements.manage'],
        ['admin/settings',      'Settings',       'settings.manage'],
        ['admin/roles',         'Roles',          'roles.manage'],
        ['admin/audit',         'Audit log',      'audit.view'],
    ]],
];
?>

<div class="admin-shell">
  <aside class="admin-side" id="admin-side">
    <a class="brand" href="<?= base_url() ?>" style="padding:1rem 1.125rem">
      <img class="brand-mark" src="<?= base_url('assets/img/logo-mark-68.png') ?>" alt="" width="34" height="34">
      <span>
        <span class="brand-name">Go Paddle</span>
        <span class="brand-sub">Operations</span>
      </span>
    </a>

    <nav aria-label="Dashboard">
      <?php foreach ($nav as [$group, $items]): ?>
        <?php $visible = array_values(array_filter($items, static fn ($i) => $auth->can($i[2]))); ?>
        <?php if ($visible === []) { continue; } ?>
        <h2 class="t-label" style="padding:1rem 1.125rem 0.375rem"><?= esc($group) ?></h2>
        <?php foreach ($visible as [$path, $label]): ?>
          <a class="admin-link" href="<?= base_url($path) ?>" <?= $on($path) ? 'aria-current="page"' : '' ?>><?= esc($label) ?></a>
        <?php endforeach; ?>
      <?php endforeach; ?>
    </nav>
  </aside>

  <div class="admin-main">
    <header class="admin-top">
      <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="admin-side" data-side-toggle style="display:none">
        <span class="visually-hidden">Menu</span>
        <svg width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path d="M2 5h16M2 10h16M2 15h16"/></svg>
      </button>

      <div style="min-width:0">
        <h1 class="t-h3" style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= esc(strtok($title, '|')) ?></h1>
      </div>

      <div class="row" style="gap:0.75rem;margin-left:auto;flex:none">
        <span class="chip chip-live tone-live"><span class="dot dot-pulse" aria-hidden="true"></span><span data-clock><?= date('g:i A') ?></span></span>
        <span class="t-label" style="text-align:right;line-height:1.3">
          <?= esc($auth->name()) ?><br>
          <span style="color:var(--blue-ink)"><?= esc($user['role_name']) ?></span>
        </span>
        <a class="btn btn-ghost btn-sm" href="<?= base_url('logout') ?>">Sign out</a>
      </div>
    </header>

    <main id="main" class="admin-body">
      <?php $flashError = session()->getFlashdata('error'); $flashOk = session()->getFlashdata('success'); ?>
      <?php if ($flashError || $flashOk): ?>
        <div class="alert <?= $flashError ? 'tone-booked' : 'tone-open' ?>" role="status" style="margin-bottom:1.25rem">
          <b><?= $flashError ? 'Problem' : 'Done' ?></b>
          <span><?= esc($flashError ?: $flashOk) ?></span>
        </div>
      <?php endif; ?>

      <?= $this->renderSection('content') ?>
    </main>
  </div>
</div>

<script src="<?= base_url('assets/js/app.js?v=12') ?>" defer></script>
<script>
(() => {
  const toggle = document.querySelector('[data-side-toggle]');
  const side = document.getElementById('admin-side');
  if (!toggle || !side) return;
  toggle.style.display = '';
  toggle.addEventListener('click', () => {
    const open = side.dataset.open === 'true';
    side.dataset.open = String(!open);
    toggle.setAttribute('aria-expanded', String(!open));
  });
})();
</script>
<?= $this->renderSection('scripts') ?>
</body>
</html>
