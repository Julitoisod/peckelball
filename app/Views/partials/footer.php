<footer class="footer">
  <div class="container" style="padding-block:clamp(3rem,6vw,4.5rem) 0">
    <div class="footer-grid">
      <div style="grid-column:span 2;min-width:min(100%,22rem)">
        <a class="brand" href="<?= base_url() ?>" style="margin-bottom:1rem">
          <span class="brand-mark" aria-hidden="true">BP</span>
          <span>
            <span class="brand-name">Bernie Pickleball</span>
            <span class="brand-sub">Trento · 5 Courts</span>
          </span>
        </a>
        <p class="t-small t-mute" style="max-width:34ch;margin:0 0 1.25rem">
          Five courts, three covered, open 06:00 to 22:00 every day. Walk in or book ahead.
          The board on this site is the same board the front desk is looking at.
        </p>
        <div class="stack-2 mono" style="font-size:0.8125rem">
          <a href="tel:<?= esc(str_replace(' ', '', setting('facility.phone', ''))) ?>" style="color:var(--fg)"><?= esc(setting('facility.phone', '')) ?></a>
          <a href="mailto:<?= esc(setting('facility.email', '')) ?>"><?= esc(setting('facility.email', '')) ?></a>
        </div>
      </div>

      <div>
        <h3>Play</h3>
        <ul>
          <li><a href="<?= base_url('availability') ?>">Live availability</a></li>
          <li><a href="<?= base_url('booking') ?>">Book a court</a></li>
          <li><a href="<?= base_url('courts') ?>">The courts</a></li>
          <li><a href="<?= base_url('rentals') ?>">Equipment rental</a></li>
        </ul>
      </div>

      <div>
        <h3>Improve</h3>
        <ul>
          <li><a href="<?= base_url('coaching') ?>">Book a coach</a></li>
          <li><a href="<?= base_url('coaches') ?>">Our coaches</a></li>
          <li><a href="<?= base_url('membership') ?>">Membership</a></li>
        </ul>
      </div>

      <div>
        <h3>Compete</h3>
        <ul>
          <li><a href="<?= base_url('tournaments') ?>">Tournaments</a></li>
          <li><a href="<?= base_url('rankings') ?>">Rankings &amp; results</a></li>
          <li><a href="<?= base_url('shop') ?>">Pro shop</a></li>
        </ul>
      </div>

      <div>
        <h3>Facility</h3>
        <ul>
          <li><a href="<?= base_url('about') ?>">About us</a></li>
          <li><a href="<?= base_url('contact') ?>">Contact</a></li>
          <li><a href="<?= esc(setting('facility.maps_url', '#')) ?>" rel="noopener" target="_blank">Directions</a></li>
          <li><a href="<?= base_url($auth->check() ? 'account' : 'login') ?>"><?= $auth->check() ? 'My account' : 'Sign in' ?></a></li>
        </ul>
      </div>
    </div>

    <div class="footer-bar">
      <p class="t-small t-mute" style="margin:0"><?= esc(setting('facility.address', 'Trento, Agusan del Sur')) ?></p>
      <p class="t-label" style="margin:0">© <?= date('Y') ?> Bernie Pickleball</p>
    </div>
  </div>
</footer>
