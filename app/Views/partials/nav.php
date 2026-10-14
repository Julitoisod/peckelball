<?php
$current = trim(uri_string(), '/');
$isOn    = static fn (string $path): bool => $path === '' ? $current === '' : str_starts_with($current, $path);
$user    = $auth->user();
?>
<header class="nav" data-nav>
  <div class="container-wide nav-inner">
    <a class="brand" href="<?= base_url() ?>">
      <?php // 68px artwork for a 34px slot. The 256px file was 91KB a phone decoded on first paint. ?>
      <img class="brand-mark" src="<?= base_url('assets/img/logo-mark-68.png') ?>" alt="" width="34" height="34">
      <span>
        <span class="brand-name">Go Paddle</span>
        <span class="brand-sub">Trento · <?= (int) court_mix()['total'] ?> Courts</span>
      </span>
    </a>

    <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="nav-links" data-nav-toggle>
      <span class="visually-hidden">Menu</span>
      <svg width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
        <path d="M2 5h16M2 10h16M2 15h16"/>
      </svg>
    </button>

    <nav class="nav-links" id="nav-links" aria-label="Primary">
      <a class="nav-link" href="<?= base_url('availability') ?>" <?= $isOn('availability') ? 'aria-current="page"' : '' ?>>Availability</a>
      <a class="nav-link" href="<?= base_url('courts') ?>" <?= $isOn('courts') ? 'aria-current="page"' : '' ?>>Courts</a>
      <a class="nav-link" href="<?= base_url('open-play') ?>" <?= $isOn('open-play') ? 'aria-current="page"' : '' ?>>Open play</a>
      <a class="nav-link" href="<?= base_url('coaching') ?>" <?= $isOn('coach') ? 'aria-current="page"' : '' ?>>Coaching</a>
      <a class="nav-link" href="<?= base_url('tournaments') ?>" <?= $isOn('tournaments') ? 'aria-current="page"' : '' ?>>Tournaments</a>
      <a class="nav-link" href="<?= base_url('membership') ?>" <?= $isOn('membership') ? 'aria-current="page"' : '' ?>>Membership</a>

      <div class="menu-more" data-menu>
        <button class="nav-link" type="button" aria-expanded="false" data-menu-toggle>
          More
          <svg width="10" height="10" viewBox="0 0 10 10" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true" style="display:inline-block;vertical-align:middle;margin-left:4px">
            <path d="M2 4l3 3 3-3"/>
          </svg>
        </button>
        <div class="menu-panel">
          <a href="<?= base_url('shop') ?>">Pro shop</a>
          <a href="<?= base_url('rentals') ?>">Equipment rental</a>
          <a href="<?= base_url('rankings') ?>">Rankings &amp; results</a>
          <a href="<?= base_url('app') ?>">Get the Android app</a>
          <a href="<?= base_url('about') ?>">About Go Paddle</a>
          <a href="<?= base_url('contact') ?>">Contact &amp; directions</a>
        </div>
      </div>
    </nav>

    <div class="nav-actions">
      <?php if ($user !== null): ?>
        <?php if ($auth->isStaff()): ?>
          <a class="btn btn-ghost btn-sm btn-desktop-only" href="<?= base_url('admin') ?>">Dashboard</a>
        <?php else: ?>
          <a class="btn btn-ghost btn-sm btn-desktop-only" href="<?= base_url('account') ?>"><?= esc($user['first_name']) ?></a>
        <?php endif; ?>
        <a class="btn btn-sm" href="<?= base_url('booking') ?>">Book a court</a>
      <?php else: ?>
        <a class="btn btn-ghost btn-sm btn-desktop-only" href="<?= base_url('login') ?>">Sign in</a>
        <a class="btn btn-sm" href="<?= base_url('booking') ?>">Book a court</a>
      <?php endif; ?>
    </div>
  </div>
</header>
