/* The hover readout for a court in the 3D scene.
 *
 * A DOM card rather than in-scene text: it inherits the type system, stays
 * crisp at every pixel ratio, and costs the renderer nothing. Position comes
 * from projecting the court's own centre, so it tracks the camera exactly.
 *
 * It is decorative — every court is already reachable as a real link in the
 * status panel — so it is hidden from assistive tech.
 */

/* Labels and tones mirror status_meta() in bernie_helper.php — same states,
   same colour meanings, so the card cannot drift from the rest of the page. */
const LABEL = {
  open: 'Available',
  filling: 'Filling up',
  booked: 'Booked',
  closed: 'Closed',
  maintenance: 'Maintenance',
  past: 'Closed',
};

const TONE_CLASS = {
  open: 'open',
  filling: 'filling',
  booked: 'booked',
  maintenance: 'filling',
  closed: 'closed',
  past: 'closed',
};

export function createHud(host) {
  const el = document.createElement('div');
  el.className = 'court-hud';
  el.setAttribute('aria-hidden', 'true');
  el.innerHTML =
    '<span class="court-hud-code mono"></span>' +
    '<span class="court-hud-name"></span>' +
    '<span class="court-hud-state"><span class="dot" aria-hidden="true"></span><span></span></span>' +
    '<span class="court-hud-rate mono"></span>' +
    '<span class="court-hud-cta">View schedule &rsaquo;</span>';
  host.appendChild(el);

  const code = el.querySelector('.court-hud-code');
  const name = el.querySelector('.court-hud-name');
  const state = el.querySelector('.court-hud-state');
  const stateText = state.querySelector('span:last-child');
  const rate = el.querySelector('.court-hud-rate');

  let shownId = null;

  return {
    /** @param court scene court record  @param x,y  pixels within `host` */
    show(court, x, y) {
      if (court.id !== shownId) {
        shownId = court.id;
        code.textContent = court.code;
        name.textContent = court.name;
        stateText.textContent = LABEL[court.now] ?? LABEL.closed;
        state.className = `court-hud-state tone-${TONE_CLASS[court.now] ?? 'closed'}`;
        rate.textContent = `₱${court.rate} / hour`;
      }
      el.style.transform = `translate3d(${Math.round(x)}px, ${Math.round(y)}px, 0)`;
      el.classList.add('is-on');
    },

    hide() {
      shownId = null;
      el.classList.remove('is-on');
    },
  };
}
