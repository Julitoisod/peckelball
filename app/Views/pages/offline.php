<?= $this->extend($layout) ?>

<?= $this->section('content') ?>
<section class="section">
  <div class="container" style="max-width:34rem;text-align:center">
    <span class="chip tone-booked" style="margin-bottom:1.25rem"><span class="chip-glyph" aria-hidden="true">■</span>No connection</span>
    <h1 class="t-h1" style="margin-bottom:0.75rem">You are offline</h1>
    <p class="t-lede" style="margin:0 auto 1.75rem">
      The availability board is a live claim about right now, so we will not show you a stale one.
      Reconnect and it refreshes itself.
    </p>
    <div class="row" style="gap:0.75rem;justify-content:center">
      <button class="btn" type="button" onclick="location.reload()">Try again</button>
      <a class="btn btn-ghost" href="tel:<?= esc(str_replace(' ', '', setting('facility.phone', '+639175550100'))) ?>">Call the desk</a>
    </div>
  </div>
</section>
<?= $this->endSection() ?>
