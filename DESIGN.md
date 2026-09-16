# Design

Bernie Pickleball — visual system.
Lane: **broadcast / court-graphics**. The reference is a live sports scorebug and a stadium
jumbotron, not a SaaS landing page. Hard geometry, telemetry typography, one field colour, and
a status palette that behaves like a contract.

---

## Theme

**Dark, always.** Not a preference toggle.

The scene sentence: *a player standing outside the covered courts at 7pm under the pole lights,
phone at 40% brightness, deciding whether to walk in.* The building is dark navy and lit blue;
a white page in that hand is a flashbulb. The dashboard runs on the same dark field because the
front desk sits under the same lights, and because the two surfaces share one component library.

There is no light mode. A half-hearted light theme on a system built entirely around glowing
status colour would be worse than none.

## Color

Strategy: **Committed.** Court navy carries ~85% of every surface. It is the field, not a
neutral. Signal colours sit on top of it the way graphics sit on a broadcast feed.

All values are OKLCH. Neutrals are tinted `+0.024–0.040` chroma toward the brand's own blue hue
(257–258°) — never warm-by-default.

### Field (navy ramp)

| Token | OKLCH | Hex | Use |
|---|---|---|---|
| `--bg-deep` | `0.155 0.038 258` | `#030c1c` | Page background, behind the 3D canvas |
| `--bg` | `0.196 0.040 258` | `#091527` | Default section background |
| `--surface` | `0.245 0.040 257` | `#142133` | Panels, table rows, form fields |
| `--raised` | `0.300 0.038 257` | `#212e41` | Hover, active row, popovers |
| `--line` | `0.375 0.035 257` | `#354254` | Decorative hairlines, table rules |
| `--line-strong` | `0.560 0.030 257` | `#697586` | Input borders, control boundaries (3.9:1 — AA non-text) |

### Ink

| Token | OKLCH | Hex | On `--bg` |
|---|---|---|---|
| `--fg` | `0.955 0.008 257` | `#edf0f6` | **16.0:1** |
| `--mute` | `0.700 0.024 257` | `#959fae` | **6.8:1** |

`--mute` is deliberately light for a "muted" token. Secondary text on this system still has to be
read outdoors at night; the usual grey-on-dark caption fails there.

### Signal

| Token | OKLCH | Hex | Meaning | On `--bg` |
|---|---|---|---|---|
| `--blue` | `0.680 0.175 244` | `#00a0fb` | Primary action, live data, focus | **6.5:1** |
| `--blue-dim` | `0.560 0.150 244` | `#007ac5` | Pressed states, chart secondary | 4.0:1 (large/UI only) |
| `--green` | `0.845 0.190 133` | `#99e557` | **AVAILABLE.** Nothing else. | **12.0:1** |
| `--amber` | `0.790 0.155 072` | `#f7a830` | Filling up / pending / maintenance due | **9.2:1** |
| `--red` | `0.660 0.200 025` | `#f4514f` | Booked / full / failure | **5.4:1** |

**The green rule.** `--green` is a *status*, never a brand flourish. It never appears on a
heading, a divider, a logo, an icon that isn't a status, or a button that isn't "book the open
slot". The moment green is decorative the availability grid stops meaning anything.

Filled buttons take **`--bg-deep` ink on a signal fill** (blue 6.9:1, green 12.8:1, amber 9.9:1,
red 5.7:1). Never white-on-signal — white on green is 1.5:1.

**Status is never colour alone.** Every state ships hue + text label + distinct glyph:
`▲ OPEN` · `◆ FILLING` · `■ BOOKED` · `✕ CLOSED`.

## Typography

Two Omnibus-Type siblings, self-hosted as variable woff2. Contrast is on the
**proportional-vs-monospaced** axis, not two lookalike sans-serifs.

- **Archivo Variable** (`wght 100–900`, `wdth 62–125`) — display and UI.
  The width axis is the point: `wdth 125` expanded caps for jumbotron display type,
  `wdth 85` condensed for dense labels and nav. One family doing two jobs the way a real
  broadcast package does.
- **Chivo Mono Variable** — all telemetry: times, court IDs, prices, scores, counts, dates,
  table numerics, kickers. `font-variant-numeric: tabular-nums` globally.

Mono here is literal, not costume: this interface's primary content *is* a schedule of numbers.

### Scale

Fluid, ratio ≈ 1.33. Display ceiling is `6rem`, letter-spacing floor `-0.035em`.

| Step | Size | Family / axis |
|---|---|---|
| `--t-display` | `clamp(2.75rem, 7vw, 6rem)` | Archivo `wdth 118`, `wght 800`, `ls -0.035em`, uppercase |
| `--t-h1` | `clamp(2rem, 4.2vw, 3.25rem)` | Archivo `wdth 112`, `wght 750`, `ls -0.025em` |
| `--t-h2` | `clamp(1.5rem, 2.6vw, 2.125rem)` | Archivo `wdth 105`, `wght 700`, `ls -0.018em` |
| `--t-h3` | `1.25rem` | Archivo `wght 650` |
| `--t-body` | `1rem / 1.65` | Archivo `wght 400` |
| `--t-small` | `0.8125rem / 1.5` | Archivo `wght 450` |
| `--t-label` | `0.6875rem` | Chivo Mono `wght 500`, `ls 0.09em`, uppercase |
| `--t-metric` | `clamp(1.75rem, 3vw, 2.75rem)` | Chivo Mono `wght 600`, tabular |

Line-height gets `+0.05` over the light-mode equivalent — light type on a dark field reads
thinner. Body copy caps at `68ch`.

### The kicker rule

Small tracked mono labels are a real part of this system — they are the scorebug's own grammar
(`CT 03`, `LIVE`, `18:30–20:00`, `₱ 450 / HR`). They are **data labels attached to data**.
They are *not* pasted above every section heading as an eyebrow. Sections are separated by a
2px rule and a change of field colour, not by `— ABOUT —`.

## Layout

- Container `1240px`, gutter `clamp(1rem, 4vw, 2.5rem)`.
- Section rhythm is varied on purpose: hero is `100svh`; the availability board is tight
  (`clamp(3rem, 6vw, 4.5rem)` block padding) because it's a data surface; narrative sections
  breathe (`clamp(5rem, 10vw, 8rem)`).
- **Hard geometry.** Radius scale stops at `4px` (`--r`: 2px, `--r-lg`: 4px). No pills except
  the live-status chip. Broadcast graphics have corners.
- Rules are `1px` hairline (`--line`) or `2px` structural (`--blue` at 40%). **No side-stripe
  accent borders** anywhere.
- Grids: `repeat(auto-fit, minmax(280px, 1fr))` for court cards; the availability board is a
  CSS Grid time-matrix (courts × slots), not cards.
- Z-scale is semantic: `--z-court: 0`, `--z-sticky: 100`, `--z-nav: 200`, `--z-backdrop: 300`,
  `--z-modal: 400`, `--z-toast: 500`.

### Signature elements

1. **The availability board** — a courts × time-slots matrix, mono, green/amber/red cells,
   live-updating. This is the site's identity. It appears on the landing page, on `/availability`,
   and in miniature in the dashboard.
2. **The scanline field** — a 2px repeating-linear-gradient at 3% white over dark sections.
   Barely visible; gives the navy the texture of a lit LED wall instead of flat paint.
3. **Corner ticks** — 10px L-shaped brackets at panel corners (broadcast lower-third framing),
   drawn with a single background-image, not extra DOM.

## Motion

GSAP for the hero choreography, plain CSS for state transitions.

- Easing is exponential ease-out only: `--ease: cubic-bezier(0.16, 1, 0.3, 1)`.
  No bounce, no elastic, no `ease-in-out` on entrances.
- Durations: `120ms` state, `280ms` transition, `900ms` hero choreography.
- **One orchestrated page-load** on the landing hero (court lights strike on → headline
  width-axis settles → availability panel slides in → live dot begins). Not a fade-on-scroll
  applied to every section.
- Scroll reveals exist on exactly two sections and **enhance an already-visible default** —
  content is never gated behind a JS class.
- The live dot pulse and the "just changed" cell flash are the only looping animations.

### Reduced motion

`@media (prefers-reduced-motion: reduce)` removes the camera drift, the hero choreography (all
elements render at final state), the pulse, and the cell flash. The 3D scene renders one static
frame and stops the render loop entirely. Nothing crossfades for longer than 100ms.

## The 3D scene

Three.js, `WebGLRenderer` with `antialias` off + `SMAA`-free FXAA-less approach — the geometry
is flat-shaded boxes and planes, so aliasing is minimal and the cost is near zero.

Budget and rules:

- **Nothing 3D blocks first paint.** The hero ships a static SVG court that is a genuinely
  finished visual. Three.js is fetched only after `load` + `requestIdleCallback`, and only when
  the canvas is in view.
- **Not loaded at all** when: `prefers-reduced-motion: reduce`, `navigator.connection.saveData`,
  `deviceMemory < 4`, `hardwareConcurrency < 4`, or no WebGL2. Those users keep the SVG.
- `pixelRatio` clamped to `1.5`. Render loop pauses on `visibilitychange` and when the canvas
  scrolls out of view.
- Scene content is **real**: 5 courts laid out in the facility's actual arrangement, each surface
  tinted by its live status (green available, amber filling, red booked). Hovering a court in the
  scene highlights its row in the availability board and vice-versa. If it stopped being real
  data it would be removed.
- Total added JS ≤ 165KB gzipped, loaded off the critical path.

Only **one** other page (`/courts/{id}`) gets a 3D view. Every other page is flat. The brief's
"don't put 3D everywhere" is enforced by this list, not by taste.

## Glassmorphism

Used **once**: the availability panel floating over the hero's 3D canvas
(`backdrop-filter: blur(20px) saturate(1.4)` over `--bg-deep` at 72%). It is justified there
because there is genuinely a moving scene behind it that must stay partly visible — that is what
the material is for. Everywhere else, panels are opaque `--surface`. No glass cards in grids, no
frosted nav, no blurred modals.

## Components

- **Buttons** — `--r` 2px, `--t-label` mono uppercase, dark ink on signal fill. Primary blue,
  "book open slot" green, ghost = 1px `--line-strong`. Min target 44×44.
- **Focus** — `outline: 2px solid var(--blue); outline-offset: 2px` plus a 1px `--bg-deep` ring
  so it survives on top of signal fills. Never `outline: none`.
- **Cards** — used only where a card is the right affordance (courts, coaches, plans, products).
  Opaque `--surface`, 1px `--line`, corner ticks, no nesting, no glass.
- **Availability cell** — the atom of the system. Mono time, status glyph, status hue, 2px left
  *inset* (background, not a border-left stripe).
- **Tables** — dashboard default. Zebra via `--surface`/`--bg`, sticky header, tabular numerics.
- **Forms** — 1px `--line-strong` border, `--surface` fill, label above, mono for numeric input.
  Placeholder is `--mute` (6.8:1), not a faint grey.

## Assets

- Fonts: self-hosted variable woff2 in `public/assets/fonts/`, `font-display: swap`, preloaded.
- Photography: Unsplash, verified IDs, `?auto=format&fit=crop&w=1600&q=80`. Used for coaches,
  facility, tournaments, shop. The hero is the 3D/SVG court, not a photo.
- Icons: inline SVG sprite, `currentColor`, no icon font, no icon library.
