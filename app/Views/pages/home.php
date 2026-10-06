<?= $this->extend($layout) ?>

<?= $this->section('head') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/home.css?v=5') ?>">
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php
$openNow = $snapshot['courts_open'];
$next    = $snapshot['next_open'];
$shut    = ! ($snapshot['facility_open'] ?? true);
$peakStart = setting('pricing.peak_start', '17:00');
$peakEnd   = setting('pricing.peak_end', '21:00');

// True once the gate has shut for the night: the board below shows tomorrow, so
// the copy and every booking link on this page have to point at the same day.
$rolled  = $boardDate !== business_date();
$dayWord = strtolower(day_label($boardDate));
$bookUrl = base_url('booking' . ($rolled ? '?date=' . $boardDate : ''));
?>

<!-- ============================================================ HERO ==== -->
<?php /* The hero is one moment: status line, headline, lede, two buttons. The
         three readouts that used to sit under the buttons pushed them onto the
         fold line at 1280x720 and cut the status panel off, so they run as
         their own strip directly below instead. */ ?>
<section class="hero">
  <div class="hero-scene" data-hero-scene>
    <?= $this->include('partials/court_scene_svg') ?>
    <canvas data-hero-canvas aria-hidden="true"></canvas>
  </div>

  <div class="hero-inner">
    <div class="hero-copy">
      <div class="hero-meta">
        <span class="chip chip-live tone-<?= $shut ? 'closed' : 'live' ?>">
          <span class="dot<?= $shut ? '' : ' dot-pulse' ?>" aria-hidden="true"></span><?= $shut ? 'Closed' : 'Live' ?>
        </span>
        <span class="t-label" style="letter-spacing:0.12em">Trento, Agusan del Sur · <?= esc(hours_label()) ?></span>
      </div>

      <h1 class="t-display">
        <?php if ($openNow > 0): ?>
          <?= $openNow ?> Court<?= $openNow === 1 ? '' : 's' ?>
          <em>Open now.</em>
        <?php elseif ($shut): ?>
          Gates open
          <span class="accent-blue"><?= esc(clock(setting('hours.open', '06:00'))) ?></span>
        <?php elseif ($next !== null): ?>
          Next court
          <span class="accent-blue"><?= esc($next['time']) ?></span>
        <?php else: ?>
          <?= esc(ucfirst(courts_word())) ?> courts.
          <span class="accent-blue">Trento.</span>
        <?php endif; ?>
      </h1>

      <p class="t-lede">
        <?php if ($openNow > 0): ?>
          <?= esc(implode(', ', $snapshot['open_codes'])) ?> <?= count($snapshot['open_codes']) === 1 ? 'is' : 'are' ?>
          free this minute. Pick a slot below and you are on court in the time it takes to park.
        <?php elseif ($shut): ?>
          <?php /* The headline already carries the opening time; repeating it here
                   spends the lede on nothing. Say what can be done instead. */ ?>
          Nobody is on court at this hour. Book <?= $rolled ? 'a slot for tomorrow' : 'your slot now' ?>
          and walk straight on when the gate opens.
        <?php elseif ($next !== null): ?>
          Every court is in play right now. <?= esc($next['code']) ?> <?= esc($next['court']) ?> frees up at
          <?= esc($next['time']) ?>. Take it before the evening block goes.
        <?php else: ?>
          Three covered courts, two outdoor, open from six in the morning until ten at night.
          Book online or walk in.
        <?php endif; ?>
      </p>

      <div class="hero-actions">
        <a class="btn btn-lg <?= $openNow > 0 ? 'btn-go' : '' ?>" href="<?= esc($bookUrl) ?>">Book a court</a>
        <a class="btn btn-lg btn-ghost" href="#board">See the whole day</a>
      </div>
    </div>

    <!-- The single glass surface in the system — a moving scene sits behind it. -->
    <aside class="hero-panel" aria-label="Court status right now">
      <div class="hero-panel-head">
        <span class="t-label" style="color:var(--fg)">Court status</span>
        <span class="t-label mono" data-clock><?= date('g:i A') ?></span>
      </div>

      <?php foreach ($courts as $court): ?>
        <?php $m = status_meta($court['now_state']); ?>
        <a class="court-line tone-<?= esc($m['tone']) ?>" data-court="<?= (int) $court['id'] ?>"
           href="<?= base_url('courts/' . $court['slug']) ?>">
          <span class="court-line-code"><?= esc($court['code']) ?></span>
          <span class="court-line-name"><?= esc($court['name']) ?></span>
          <span class="court-line-state">
            <span aria-hidden="true"><?= $m['glyph'] ?></span>
            <?php if ($court['now_state'] === 'open'): ?>
              Open
            <?php elseif ($court['next_open'] !== null): ?>
              <?= $court['now_state'] === 'closed' ? 'Opens ' : 'Free ' ?><?= esc(clock($court['next_open'])) ?>
            <?php else: ?>
              <?= esc($m['label']) ?>
            <?php endif; ?>
          </span>
        </a>
      <?php endforeach; ?>

      <div class="hero-panel-foot">
        <a class="btn btn-block btn-ghost btn-sm" href="<?= base_url('availability') ?>">Full availability</a>
      </div>
    </aside>
  </div>
</section>

<!-- ==================================================== READOUT STRIP === -->
<section class="stat-strip">
  <div class="container">
    <div class="readout">
      <span class="t-metric"><?= number_format($stats['played_this_month']) ?></span>
      <span class="t-label">Sessions played this month</span>
    </div>
    <div class="readout">
      <span class="t-metric"><?= number_format($stats['sessions']) ?></span>
      <span class="t-label">Coaching hours delivered</span>
    </div>
    <div class="readout">
      <span class="t-metric"><?= peso($courts[0]['hourly_rate'] ?? 300) ?></span>
      <span class="t-label">From, per hour</span>
    </div>
  </div>
</section>

<!-- ====================================================== ANNOUNCEMENT == -->
<?php if ($notice !== null): ?>
<section class="field-bg" style="border-block:1px solid var(--line)">
  <div class="container" style="padding-block:0.875rem">
    <div class="row" style="gap:0.75rem 1.25rem;flex-wrap:nowrap;align-items:center">
      <span class="chip tone-live" style="flex:none"><span class="dot" aria-hidden="true"></span>Notice</span>
      <p class="t-small" style="margin:0;flex:1;min-width:0">
        <strong><?= esc($notice['title']) ?></strong>
        <span class="t-mute">· <?= esc(mb_strimwidth(strip_tags($notice['body']), 0, 140, '…')) ?></span>
      </p>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ========================================================== BOARD ===== -->
<section class="section-tight field-scan" id="board">
  <div class="container-wide">
    <div class="section-head">
      <div>
        <h2 class="t-h2"><?= esc(day_label($boardDate)) ?> on court</h2>
        <p class="t-small t-mute">
          Every <?= (int) setting('booking.slot_minutes', 30) ?> minutes across all <?= esc(courts_word()) ?> courts. Tap any open slot to book it.
          <?= esc($board['totals']['open']) ?> of <?= esc($board['totals']['slots']) ?> are free <?= esc($dayWord) ?>.
        </p>
      </div>
      <a class="btn btn-ghost btn-sm" href="<?= base_url('availability?date=' . $boardDate) ?>">Pick another day</a>
    </div>

    <?= partial('partials/board', ['board' => $board, 'bookable' => true]) ?>
  </div>
</section>

<!-- ==================================================== FACILITY MAP ==== -->
<section class="section field-bg">
  <div class="container">
    <div class="section-head">
      <div>
        <h2 class="t-h2">The compound</h2>
        <p class="t-small t-mute">Laid out as you will find it walking in from the highway. Colour is live status.</p>
      </div>
    </div>

    <div class="facility">
      <div class="facility-map">
        <?= partial('partials/facility_map', ['courts' => $courts]) ?>
      </div>

      <div class="stack">
        <?php foreach ($courts as $court): ?>
          <?php $m = status_meta($court['day_state']); ?>
          <a class="panel map-link" href="<?= base_url('courts/' . $court['slug']) ?>" style="text-decoration:none;padding:0.875rem 1rem" data-map-court="<?= (int) $court['id'] ?>">
            <div class="row-between" style="gap:0.5rem">
              <div>
                <div class="mono" style="font-size:0.8125rem;font-weight:600"><?= esc($court['code']) ?> · <?= esc($court['name']) ?></div>
                <div class="t-small t-mute"><?= esc(ucfirst($court['surface_type'])) ?> · <?= esc(rate_label($court)) ?>/hr</div>
              </div>
              <span class="chip tone-<?= esc($m['tone']) ?>">
                <span class="chip-glyph" aria-hidden="true"><?= $m['glyph'] ?></span><?= esc($court['open_slots']) ?> free
              </span>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>

<!-- ======================================================== COURTS ====== -->
<section class="section">
  <div class="container">
    <div class="section-head">
      <div>
        <h2 class="t-h2"><?= esc(ucfirst(courts_word())) ?> courts, no waiting list</h2>
        <p class="t-small t-mute"><?= esc(ucfirst(roof_phrase())) ?>, so rain does not cancel a session.</p>
      </div>
      <a class="btn btn-ghost btn-sm" href="<?= base_url('courts') ?>">All court detail</a>
    </div>

    <div class="grid-auto">
      <?php foreach (array_slice($courts, 0, 3) as $court): ?>
        <?php
        $m   = status_meta($court['day_state']);
        $img = unsplash($court['image_path'], 720, 450);
        ?>
        <article class="card">
          <?php if ($img !== null): ?>
            <div class="card-media">
              <img src="<?= esc($img) ?>" alt="<?= esc($court['name']) ?> at Go Paddle, <?= esc($court['surface_type']) ?> court" loading="lazy" width="720" height="450">
              <span class="card-media-badge chip tone-<?= esc($m['tone']) ?>">
                <span class="chip-glyph" aria-hidden="true"><?= $m['glyph'] ?></span><?= esc($m['label']) ?>
              </span>
            </div>
          <?php endif; ?>
          <div class="card-body">
            <h3 class="t-h3"><?= esc($court['name']) ?></h3>
            <p class="t-small t-mute" style="margin:0">
              <?= esc(ucfirst($court['surface_type'])) ?> ·
              <?= $court['next_open'] !== null ? 'Next open ' . esc(clock($court['next_open'])) : 'Fully booked today' ?>
            </p>
            <div class="card-foot">
              <span class="amount" style="font-size:0.875rem"><?= esc(peso($court['hourly_rate'])) ?><span class="t-mute" style="font-size:0.6875rem">/hr</span></span>
              <a class="btn btn-sm" href="<?= base_url('booking?court=' . (int) $court['id']) ?>">Book</a>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ======================================================= COACHING ===== -->
<section class="section field-bg field-scan">
  <div class="container">
    <div class="section-head">
      <div>
        <h2 class="t-h2">Coaching</h2>
        <p class="t-small t-mute">Four coaches, <?= number_format($stats['sessions']) ?> hours between them. Book a court and a coach in the same flow.</p>
      </div>
      <a class="btn btn-ghost btn-sm" href="<?= base_url('coaching') ?>">Book a session</a>
    </div>

    <div class="grid-auto">
      <?php foreach ($coaches as $coach): ?>
        <?php $img = unsplash($coach['photo_path'], 560, 700); ?>
        <article class="card coach-card">
          <?php if ($img !== null): ?>
            <div class="card-media">
              <img src="<?= esc($img) ?>" alt="Coach <?= esc($coach['first_name'] . ' ' . $coach['last_name']) ?>" loading="lazy" width="560" height="700">
            </div>
          <?php endif; ?>
          <div class="card-body">
            <h3 class="t-h3"><?= esc($coach['first_name'] . ' ' . $coach['last_name']) ?></h3>
            <p class="t-small t-mute" style="margin:0"><?= esc($coach['headline']) ?></p>
            <div class="row" style="gap:0.375rem">
              <?php foreach (array_slice(pipes($coach['specialties']), 0, 2) as $s): ?>
                <span class="chip tone-closed" style="color:var(--mute)"><?= esc($s) ?></span>
              <?php endforeach; ?>
            </div>
            <div class="card-foot">
              <span class="coach-rate"><?= esc(peso($coach['hourly_rate'])) ?><span class="t-mute" style="font-size:0.6875rem">/hr</span></span>
              <span class="mono t-small tone-live" style="color:var(--blue-ink)">★ <?= esc(number_format((float) $coach['rating'], 1)) ?></span>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ===================================================== MEMBERSHIP ===== -->
<section class="section">
  <div class="container">
    <div class="section-head">
      <div>
        <h2 class="t-h2">Membership</h2>
        <p class="t-small t-mute"><?= esc($stats['members']) ?> active members. Court credits, a standing discount, and you book further ahead than everyone else.</p>
      </div>
      <a class="btn btn-ghost btn-sm" href="<?= base_url('membership') ?>">Compare plans</a>
    </div>

    <div class="plan-grid">
      <?php foreach ($plans as $plan): ?>
        <div class="plan<?= $plan['is_featured'] ? ' is-featured' : '' ?>">
          <div>
            <div class="row" style="gap:0.5rem">
              <h3 class="t-h3" style="margin:0"><?= esc($plan['name']) ?></h3>
              <?php if ($plan['is_featured']): ?><span class="chip tone-live">Most taken</span><?php endif; ?>
            </div>
            <p class="t-small t-mute" style="margin:0.25rem 0 0"><?= esc($plan['tagline']) ?></p>
          </div>

          <div class="plan-price">
            <span class="t-metric"><?= esc(peso($plan['price'])) ?></span>
            <span class="t-label">/ <?= esc($plan['billing_period']) ?></span>
          </div>

          <ul>
            <?php foreach (array_slice(pipes($plan['perks']), 0, 4) as $perk): ?>
              <li><?= esc($perk) ?></li>
            <?php endforeach; ?>
          </ul>

          <a class="btn btn-block <?= $plan['is_featured'] ? '' : 'btn-ghost' ?>" href="<?= base_url('membership') ?>">Choose <?= esc($plan['name']) ?></a>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ==================================================== TOURNAMENTS ===== -->
<?php if ($tournaments !== []): ?>
<section class="section field-bg">
  <div class="container">
    <div class="section-head">
      <div>
        <h2 class="t-h2">Coming up</h2>
        <p class="t-small t-mute">Registration is open on both. Entry covers you and your partner.</p>
      </div>
      <a class="btn btn-ghost btn-sm" href="<?= base_url('tournaments') ?>">All tournaments</a>
    </div>

    <div class="stack">
      <?php foreach ($tournaments as $t): ?>
        <?php $img = unsplash($t['banner_path'], 560, 315); ?>
        <article class="tournament-row">
          <?php if ($img !== null): ?>
            <img src="<?= esc($img) ?>" alt="<?= esc($t['name']) ?>" loading="lazy" width="560" height="315">
          <?php endif; ?>
          <div class="stack-2">
            <div class="row" style="gap:0.5rem">
              <span class="chip tone-open"><span class="chip-glyph" aria-hidden="true">▲</span>Open</span>
              <span class="t-label"><?= esc(date('d M', strtotime($t['starts_on']))) ?><?= $t['ends_on'] !== $t['starts_on'] ? '–' . esc(date('d M', strtotime($t['ends_on']))) : '' ?></span>
            </div>
            <h3 class="t-h3"><?= esc($t['name']) ?></h3>
            <p class="t-small t-mute" style="margin:0;max-width:60ch"><?= esc(mb_strimwidth($t['description'], 0, 170, '…')) ?></p>
            <div class="row" style="gap:1.25rem;margin-top:0.25rem">
              <span class="amount t-small"><?= esc(peso($t['entry_fee'])) ?> <span class="t-mute">entry</span></span>
              <span class="amount t-small"><?= esc(peso($t['prize_pool'])) ?> <span class="t-mute">pot</span></span>
              <span class="mono t-small"><?= esc($t['max_teams']) ?> <span class="t-mute">teams max</span></span>
            </div>
          </div>
          <a class="btn" href="<?= base_url('tournaments/' . $t['slug']) ?>">Enter</a>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- =================================================== TESTIMONIALS ===== -->
<section class="section">
  <div class="container">
    <div class="section-head">
      <div><h2 class="t-h2">What the regulars say</h2></div>
    </div>

    <div class="grid-auto">
      <?php foreach ($testimonials as $t): ?>
        <?php $img = unsplash($t['photo_path'], 120, 120); ?>
        <figure class="quote reveal">
          <blockquote>&ldquo;<?= esc($t['quote']) ?>&rdquo;</blockquote>
          <figcaption>
            <?php if ($img !== null): ?>
              <img src="<?= esc($img) ?>" alt="" loading="lazy" width="40" height="40">
            <?php endif; ?>
            <span>
              <span style="display:block;font-size:0.875rem;font-weight:600"><?= esc($t['author_name']) ?></span>
              <span class="t-label" style="letter-spacing:0.06em"><?= esc($t['author_role']) ?></span>
            </span>
          </figcaption>
        </figure>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- =========================================================== CTA ====== -->
<section class="cta">
  <div class="container" style="position:relative;z-index:1">
    <div class="row-between" style="gap:2rem">
      <div style="max-width:36ch">
        <h2 class="t-h1" style="margin-bottom:0.75rem">Court time is going.<br>Take a slot.</h2>
        <p style="margin:0">
          <?php if (has_peak()): ?>Nights run <?= esc(night_window()) ?> and fill first.<?php endif; ?>
          <?= esc($board['totals']['open']) ?> slots are still open <?= esc($dayWord) ?>.
        </p>
      </div>
      <div class="row" style="gap:0.75rem">
        <a class="btn btn-lg" href="<?= esc($bookUrl) ?>">Book a court</a>
        <a class="btn btn-lg btn-ghost" href="tel:<?= esc(str_replace(' ', '', setting('facility.phone', ''))) ?>">Call the desk</a>
      </div>
    </div>
  </div>
</section>

<!-- ======================================================= CONTACT ====== -->
<section class="section-tight field-deep">
  <div class="container">
    <div class="grid-auto">
      <div>
        <span class="t-label">Where</span>
        <p class="t-body" style="margin:0.375rem 0 0"><?= esc(setting('facility.address', '')) ?></p>
        <a class="btn btn-ghost btn-sm" style="margin-top:0.75rem" href="<?= esc(setting('facility.maps_url', '#')) ?>" target="_blank" rel="noopener">Open in Maps</a>
      </div>
      <div>
        <span class="t-label">Hours</span>
        <p class="t-body mono" style="margin:0.375rem 0 0"><?= esc(hours_label()) ?></p>
        <?php
        // Derived, not stored: `booking.last_start` was never a real setting, so
        // this line claimed 21:00 whatever the closing time was. The last start
        // is simply the last one a shortest booking still fits before closing —
        // and round the clock there is no such limit, any hour can start one.
        $roundTheClock = setting('hours.open', '06:00') === setting('hours.close', '22:00');
        $lastStart     = date('H:i', strtotime(setting('hours.close', '22:00')) - setting('booking.min_minutes', 60) * 60);
        ?>
        <p class="t-small t-mute" style="margin:0.25rem 0 0"><?php if (has_peak()): ?>Night rate <?= esc(night_window()) ?>. <?php endif; ?><?= $roundTheClock ? 'Book any hour of the day.' : 'Last booking starts at ' . esc(clock($lastStart)) . '.' ?></p>
      </div>
      <div>
        <span class="t-label">Talk to us</span>
        <p class="t-body mono" style="margin:0.375rem 0 0">
          <a href="tel:<?= esc(str_replace(' ', '', setting('facility.phone', ''))) ?>"><?= esc(setting('facility.phone', '')) ?></a><br>
          <a href="mailto:<?= esc(setting('facility.email', '')) ?>" style="font-size:0.875rem"><?= esc(setting('facility.email', '')) ?></a>
        </p>
      </div>
    </div>
  </div>
</section>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<?php
// The 3D scene renders real occupancy, so it needs the same data the board
// does. Embedded rather than fetched — it is already in memory here.
$sceneCourts = array_map(static fn (array $c): array => [
    'id'    => (int) $c['id'],
    'code'  => $c['code'],
    'name'  => $c['name'],
    'x'     => (float) $c['map_x'],
    'y'     => (float) $c['map_y'],
    'state' => $c['day_state'],
    'now'   => $c['now_state'],
    'free'  => (int) $c['open_slots'],
    'rate'  => number_format((float) $c['hourly_rate']),
    'href'  => base_url('courts/' . $c['slug']),
], $courts);
?>
<script type="application/json" id="court-scene-data"><?= json_encode($sceneCourts, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<script type="module" src="<?= base_url('assets/js/hero.js?v=5') ?>"></script>
<?= $this->endSection() ?>
