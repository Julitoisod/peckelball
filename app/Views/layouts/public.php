<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= esc($title) ?></title>
<meta name="description" content="<?= esc($meta) ?>">
<meta name="theme-color" content="#07FA1B">
<link rel="manifest" href="<?= base_url('manifest.webmanifest') ?>">
<link rel="icon" href="<?= base_url('assets/icons/favicon.svg') ?>" type="image/svg+xml">
<link rel="apple-touch-icon" href="<?= base_url('assets/icons/icon-192.png') ?>">

<meta property="og:type" content="website">
<meta property="og:title" content="<?= esc($title) ?>">
<meta property="og:description" content="<?= esc($meta) ?>">
<meta property="og:site_name" content="Go Paddle">

<link rel="preload" href="<?= base_url('assets/fonts/archivo-latin.woff2') ?>" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="<?= base_url('assets/fonts/chivomono-latin.woff2') ?>" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= base_url('assets/css/app.css?v=8') ?>">
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

<script src="<?= base_url('assets/js/app.js?v=3') ?>" defer></script>
<?= $this->renderSection('scripts') ?>
</body>
</html>
