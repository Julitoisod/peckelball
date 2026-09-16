<?php
/**
 * Standard page masthead. Deliberately not an eyebrow-kicker template:
 * $kicker is a *data* label (a count, a date, a status) or nothing at all.
 *
 * @var string      $heading
 * @var string|null $lede
 * @var string|null $kicker
 * @var string|null $actions raw HTML for the right side
 */
?>
<section class="section-tight field-bg field-scan" style="border-bottom:2px solid color-mix(in oklch, var(--blue) 45%, transparent)">
  <div class="container">
    <div class="row-between" style="align-items:flex-end;gap:1.5rem 2rem">
      <div style="max-width:60ch">
        <?php if (! empty($kicker)): ?>
          <div class="row" style="gap:0.5rem;margin-bottom:0.75rem"><?= $kicker ?></div>
        <?php endif; ?>
        <h1 class="t-h1"><?= esc($heading) ?></h1>
        <?php if (! empty($lede)): ?>
          <p class="t-lede" style="margin:0.75rem 0 0"><?= esc($lede) ?></p>
        <?php endif; ?>
      </div>
      <?php if (! empty($actions)): ?>
        <div class="row" style="gap:0.625rem"><?= $actions ?></div>
      <?php endif; ?>
    </div>
  </div>
</section>
