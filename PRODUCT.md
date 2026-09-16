# Product

## Register

brand

> The public site is the primary surface and design **is** the product there — it has to sell
> court time to walk-in players in Trento. The staff dashboard behind `/admin` is a
> **product**-register surface and follows `reference/product.md` rules instead.

## Users

**Players (majority, mobile, in a hurry).** Trento locals and visitors deciding *right now*
whether there is a free court tonight. They are usually on a phone, often on cellular, often
standing outside or in a car. Their job: find an open slot, book it, and get a confirmation
they can show at the desk. Everything else on the site is secondary to that.

**Members & regulars.** Know the facility, know their coach, book the same slot weekly. They
want speed and repeat-booking, not discovery.

**Coaches.** Check their session roster, availability, and who booked them.

**Owner / manager / receptionist / maintenance staff.** Live in the dashboard all day on a
desktop at the front desk. Their job is throughput: take a walk-in, resolve a double-booking,
check someone in, close the day's sales. Dense, fast, keyboard-friendly beats pretty.

## Product Purpose

Bernie Pickleball is a 5-court facility in Trento, Agusan del Sur. The site replaces a
phone-and-notebook booking process with real-time availability and self-service online
booking, and gives the owner one place to run courts, coaching, tournaments, rentals, retail
and reporting.

Success = a first-time visitor can go from landing page to confirmed booking in under 60
seconds on a phone, and the front desk never double-books a court again.

## Brand Personality

**Live · precise · physical.**

Voice is a broadcast operator's, not a marketer's: short, present-tense, factual. "Court 3 is
open at 6:30." Numbers are the copy. No hype adjectives, no "elevate your game," no exclamation
marks. Confidence comes from the facility telling you exactly what is true right now.

The emotional goal is *certainty* — the feeling of looking at a live scoreboard and knowing
the state of the building.

## Anti-references

- **Generic sports-facility SaaS.** Navy hero + stock photo of smiling people high-fiving +
  three identical feature cards with rounded icons. This is the exact template to avoid.
- **Gym-chain aggression.** All-caps motivational copy, red/black, torn-paper edges.
- **Editorial-typographic minimalism.** Display serif, italic headline, ruled columns, no
  imagery. Wrong register entirely — this facility is loud, lit, and full of people.
- **Cream/sand "premium wellness."** Warm near-white backgrounds, muted sage, thin serif.
- **Decorative 3D.** A spinning object that carries no information. If the 3D scene is not
  showing real court state, it should not be on the page.

## Design Principles

1. **The scoreboard is the hero.** Live court state is the most valuable thing this business
   has. It goes above the fold, on every page, and it is never a decorative widget.
2. **Numbers are the design.** Times, court IDs, prices, scores and counts are typeset as
   first-class content in mono, not buried in sentences.
3. **Green means available. Only.** The status palette is a contract with the user. Green is
   never decorative, never a brand flourish, never a heading color.
4. **3D must earn its frame budget.** The court scene renders real courts with real occupancy.
   It loads after the page is usable, never before, and it has a static fallback that is
   good enough to ship on its own.
5. **Booking is one thought.** Every screen either advances a booking or gets out of the way.
   Nothing between "I want a court" and "here is your confirmation" is optional decoration.

## Accessibility & Inclusion

- **WCAG 2.2 AA.** Body text ≥ 4.5:1, large text and UI boundaries ≥ 3:1. Verified, not assumed.
- **Status is never color alone.** Every availability state carries a text label and a distinct
  shape/icon as well as a hue — this is a booking system used by people with deuteranopia, and
  green-vs-red is the single most common confusion pair.
- **`prefers-reduced-motion`** kills the 3D camera drift, the pulse animations, and all scroll
  reveals; content renders in its final state immediately.
- **Keyboard-complete booking flow.** Court and time-slot pickers are real radio inputs, not
  divs. Visible focus rings everywhere, on a dark background where default rings disappear.
- **Low-end Android is the target device**, not a MacBook. The 3D scene degrades to a static
  SVG court on low memory, low core count, or Save-Data.
- Content is legible without the 3D scene, without JavaScript-driven reveals, and at 200% zoom.
