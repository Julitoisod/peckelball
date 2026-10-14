<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= esc($title) ?></title>
<meta name="description" content="<?= esc($meta) ?>">
<meta name="theme-color" content="#07FA1B">
<link rel="manifest" href="<?= base_url('manifest.webmanifest') ?>">
<link rel="icon" href="<?= base_url('assets/icons/favicon-32.png') ?>" sizes="32x32" type="image/png">
<link rel="apple-touch-icon" href="<?= base_url('assets/icons/favicon-180.png') ?>">

<meta property="og:type" content="website">
<meta property="og:title" content="<?= esc($title) ?>">
<meta property="og:description" content="<?= esc($meta) ?>">
<meta property="og:site_name" content="Go Paddle">
<meta property="og:image" content="<?= base_url('assets/img/logo-go-paddle.png') ?>">
<meta name="twitter:card" content="summary_large_image">

<link rel="preload" href="<?= base_url('assets/fonts/archivo-latin.woff2') ?>" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="<?= base_url('assets/fonts/chivomono-latin.woff2') ?>" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= base_url('assets/css/app.css?v=28') ?>">
<?= $this->renderSection('head') ?>
<script>document.documentElement.classList.add('js');</script>
</head>
<body>
<div class="progress" data-progress aria-hidden="true"></div>
<a class="skip-link" href="#main">Skip to content</a>

<?= $this->include('partials/nav') ?>

<main id="main">
<?php $flashError = session()->getFlashdata('error'); $flashOk = session()->getFlashdata('success'); ?>
<?php if ($flashError || $flashOk): ?>
  <div class="container" style="padding-top:1.25rem">
    <div class="alert <?= $flashError ? 'tone-booked' : 'tone-open' ?>" role="status">
      <b><?= $flashError ? 'Problem' : 'Done' ?></b>
      <span><?= esc($flashError ?: $flashOk) ?></span>
    </div>
  </div>
<?php endif; ?>

<?= $this->renderSection('content') ?>
</main>

<?= $this->include('partials/footer') ?>

<?php // Quick-book card. Every page carries it; app.js opens it from any plain booking link. ?>
<?= $this->include('partials/book_modal') ?>
<script src="<?= base_url('assets/js/app.js?v=13') ?>" defer
        data-booking-config='<?= esc(json_encode([
            "min"     => service("siteSettings")->int("booking.min_minutes", 60),
            "max"     => service("siteSettings")->int("booking.max_minutes", 180),
            "step"    => service("availability")->slotMinutes(),
            "advance" => service("siteSettings")->int("booking.advance_days", 14),
            "today"   => business_date(),
        ]), 'attr') ?>'></script>
<?= $this->renderSection('scripts') ?>
</body>
</html>
