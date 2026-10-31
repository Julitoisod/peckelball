<?php
/**
 * Shown from the first paint until the page has loaded, so a refresh on a slow
 * phone connection reads as "working" and not as a stuck white screen.
 *
 * The CSS lives here and not in app.css, and its colours are hex and not tokens,
 * because this has to paint with nothing else in place. It can never trap the
 * page: it needs html.js, so with JavaScript off it does not exist, the script
 * stops waiting after 2.5 seconds, and the CSS clears it after 4 if the script
 * never ran at all. 600 sits above --z-toast, the top of the z-scale.
 */
?>
<style>
  .preloader { display: none; }
  html.js .preloader {
    position: fixed;
    inset: 0;
    z-index: 600;
    display: grid;
    place-content: center;
    justify-items: center;
    gap: 0.875rem;
    background: #F4FFF5;
    transition: opacity 200ms ease, visibility 200ms;
    animation: preloader-out 200ms ease 4s forwards;
  }
  html.js .preloader.is-done { opacity: 0; visibility: hidden; }
  .preloader-bar { width: 9rem; height: 3px; overflow: hidden; background: #CFFED3; }
  .preloader-bar i { display: block; width: 40%; height: 100%; background: #067A14; animation: preloader-run 800ms ease-in-out infinite alternate; }
  .preloader b { font: 500 0.6875rem ui-monospace, Consolas, monospace; letter-spacing: 0.09em; text-transform: uppercase; color: #3F6647; }
  @keyframes preloader-run { to { transform: translateX(150%); } }
  @keyframes preloader-out { to { opacity: 0; visibility: hidden; } }
  @media (prefers-reduced-motion: reduce) {
    .preloader-bar i { width: 100%; animation: none; }
  }
</style>
<div class="preloader" id="preloader" role="status">
  <img src="<?= base_url('assets/img/logo-mark-68.png') ?>" alt="" width="68" height="68">
  <div class="preloader-bar"><i></i></div>
  <b>Loading</b>
</div>
<script>
(() => {
  const el = document.getElementById('preloader');
  const done = () => {
    el.classList.add('is-done');
    setTimeout(() => el.remove(), 300);
  };

  addEventListener('load', done, { once: true });
  setTimeout(done, 2500);
})();
</script>
