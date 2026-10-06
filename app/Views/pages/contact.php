<?= $this->extend($layout) ?>

<?= $this->section('content') ?>
<?php $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday']; ?>

<?= partial('partials/page_head', [
    'heading' => 'Contact & directions',
    'lede'    => 'On the national highway in Trento. If you are coming from Bunawan, we are on the right just past the barangay hall.',
    'kicker'  => '<span class="chip chip-live tone-live"><span class="dot dot-pulse"></span>Open daily</span>'
        . '<span class="t-label">' . esc(hours_label()) . '</span>',
]) ?>

<section class="section">
  <div class="container">
    <div style="display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:clamp(1.5rem,3vw,2.5rem);align-items:start" class="contact-grid">

      <div class="stack">
        <div class="panel ticks">
          <h2 class="t-h3" style="margin-bottom:1rem">Reach the desk</h2>
          <dl class="stack-2" style="margin:0">
            <div style="padding-bottom:0.625rem;border-bottom:1px solid color-mix(in oklch, var(--line) 60%, transparent)">
              <dt class="t-label">Phone</dt>
              <dd style="margin:0.1875rem 0 0"><a class="mono" style="font-size:1.0625rem;color:var(--fg)" href="tel:<?= esc(str_replace(' ', '', setting('facility.phone', ''))) ?>"><?= esc(setting('facility.phone', '')) ?></a></dd>
            </div>
            <div style="padding-bottom:0.625rem;border-bottom:1px solid color-mix(in oklch, var(--line) 60%, transparent)">
              <dt class="t-label">Email</dt>
              <dd style="margin:0.1875rem 0 0"><a class="mono" style="color:var(--fg)" href="mailto:<?= esc(setting('facility.email', '')) ?>"><?= esc(setting('facility.email', '')) ?></a></dd>
            </div>
            <div>
              <dt class="t-label">Address</dt>
              <dd style="margin:0.1875rem 0 0"><?= esc(setting('facility.address', '')) ?></dd>
            </div>
          </dl>
          <a class="btn btn-ghost btn-block" style="margin-top:1.125rem" href="<?= esc(setting('facility.maps_url', '#')) ?>" target="_blank" rel="noopener">Open in Google Maps</a>
        </div>

        <div class="panel panel-flush">
          <div class="panel-head"><h2 class="t-h3" style="margin:0">Opening hours</h2></div>
          <div class="table-wrap">
            <table class="table" style="min-width:0">
              <tbody>
                <?php foreach ($hours as $h): ?>
                  <?php $isToday = (int) $h['day_of_week'] === (int) date('w'); ?>
                  <tr style="<?= $isToday ? 'background:color-mix(in oklch, var(--blue) 12%, transparent)' : '' ?>">
                    <td class="t-label" style="width:9rem;<?= $isToday ? 'color:var(--fg)' : '' ?>">
                      <?= esc($days[(int) $h['day_of_week']]) ?><?= $isToday ? ' · today' : '' ?>
                    </td>
                    <td class="mono"><?= esc(clock($h['opens_at'])) ?>–<?= esc(clock($h['closes_at'])) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

        <div class="panel">
          <h2 class="t-h3" style="margin-bottom:0.625rem">Getting here</h2>
          <ul class="t-small t-mute" style="margin:0;padding-left:1.125rem;display:flex;flex-direction:column;gap:0.4375rem">
            <li><strong style="color:var(--fg)">From Bunawan</strong>: 25 minutes on the national highway. We are on the right past the barangay hall, blue roof.</li>
            <li><strong style="color:var(--fg)">From Butuan</strong>: about 2 hours. Follow the highway through Trento centre and keep going 1.5km.</li>
            <li><strong style="color:var(--fg)">By habal-habal</strong>: say &ldquo;Go Paddle, tapad sa highway&rdquo; and every driver in Trento knows it.</li>
            <li><strong style="color:var(--fg)">Parking</strong>: free, on the gravel apron at the front. Room for about fifteen cars.</li>
          </ul>
        </div>
      </div>

      <div class="panel">
        <h2 class="t-h3" style="margin-bottom:0.5rem">Send a message</h2>
        <p class="t-small t-mute" style="margin:0 0 1.25rem">
          For bookings, use the <a href="<?= base_url('booking') ?>" style="color:var(--blue-ink)">booking form</a>. It is faster and
          it holds the court. This is for everything else.
        </p>

        <form method="post" action="<?= base_url('contact') ?>" class="stack">
          <?= csrf_field() ?>

          <div class="field">
            <label for="name">Your name</label>
            <input class="input" type="text" id="name" name="name" required maxlength="120" autocomplete="name" value="<?= esc(old('name')) ?>">
          </div>

          <div class="field">
            <label for="email">Email</label>
            <input class="input" type="email" id="email" name="email" required maxlength="160" autocomplete="email" value="<?= esc(old('email')) ?>">
          </div>

          <div class="field">
            <label for="message">Message</label>
            <textarea class="textarea" id="message" name="message" required minlength="10" maxlength="2000"
                      placeholder="Corporate booking, a school group, sponsoring a tournament…"><?= esc(old('message')) ?></textarea>
            <span class="field-hint">We answer within a day, sooner if the desk is quiet.</span>
          </div>

          <button class="btn btn-block" type="submit">Send message</button>
        </form>
      </div>
    </div>
  </div>
</section>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<style>@media (max-width: 860px) { .contact-grid { grid-template-columns: 1fr !important; } }</style>
<?= $this->endSection() ?>
