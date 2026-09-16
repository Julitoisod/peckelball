# Bernie Pickleball

Court management and booking for a five-court pickleball facility in Trento,
Agusan del Sur.

CodeIgniter 4.7.4 · MariaDB/MySQL · Three.js · GSAP · hand-authored CSS design
system. No build step, no `node_modules` in the app — fonts and libraries are
vendored into `public/assets/`.

Design direction lives in [PRODUCT.md](PRODUCT.md) (who and why) and
[DESIGN.md](DESIGN.md) (how it looks, and the reasoning behind every token).

---

## Running it

### From a fresh clone

Two things are deliberately not in the repository:

- **`system/`**: the CodeIgniter 4.7.x framework. Download CodeIgniter 4.7
  and copy its `system/` folder into the project root.
- **`.env`**: copy `.env.example` to `.env` and set the database credentials.

### XAMPP (Apache + MariaDB)

1. Start **Apache** and **MySQL** in the XAMPP control panel.
2. Create the database and load it:

   ```
   c:\xampp\mysql\bin\mysql -u root -e "CREATE DATABASE bernie_pickleball CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
   c:\xampp\php\php spark migrate
   c:\xampp\php\php spark db:seed DatabaseSeeder
   ```

3. Open <http://localhost/pickil%20ball/>

   The root `.htaccess` rewrites into `public/`, so `app/`, `system/`,
   `writable/` and `.env` are not reachable over HTTP. `Config\App` detects the
   base URL from the request, so the same checkout also works if you move or
   rename the folder.

### Built-in server (no Apache)

```
c:\xampp\php\php spark serve
```

<http://localhost:8080>

---

## Sign-in

Every seeded account uses the password **`Bernie2026!`**.
Change it before this leaves a local machine — see `app/Database/Seeds/UserSeeder.php`.

| Role | Email | Reaches |
|---|---|---|
| Super Administrator | `admin@berniepickleball.ph` | Everything |
| Business Owner | `bernie@berniepickleball.ph` | Everything |
| Manager | `maricel@berniepickleball.ph` | Operations, no roles/settings/audit |
| Receptionist | `frontdesk@berniepickleball.ph` | Bookings, walk-ins, check-in, payments |
| Coach | `jomar@berniepickleball.ph` | Own sessions, read-only calendar |
| Maintenance Staff | `facilities@berniepickleball.ph` | Court maintenance only |
| Player | `player@berniepickleball.ph` | Public site + `/account` |

---

## How the double-booking guard works

Bookings store a start/end range, but the authoritative occupancy record is
`booking_slots` — **one row per slot** (`booking.slot_minutes`, currently 60),
with a `UNIQUE (court_id, slot_start)` key.

That makes an overlapping reservation a database-level impossibility rather
than an application-level promise. Two requests racing for the same 18:30 slot
both pass the availability pre-check; exactly one survives the insert, and the
loser is rolled back inside a transaction and told the slot went.

A `UNIQUE (court_id, date, start_time)` would only catch *identical* starts and
would happily let a 60-minute 18:00 booking sit on top of a 30-minute 18:30 one.

Cancelling deletes the slot rows, which is what actually frees the court.

## Hours that cross midnight

The facility runs **4:00 PM to 6:00 AM**, booked by the hour at a flat ₱250.
A booking's `booking_date` is the date its session *opens*; a 1:00 AM slot in
the Sep 18 session is stored in `booking_slots` as `2026-09-19 01:00`.
`Availability::slotTimestamp()`, `offset()` and `businessDate()` do the
conversion, and `business_date()` is what "today" means for anything
operational. Self-check: `php spark check:overnight`.

---

## Layout

```
app/
  Config/          Routes, Filters (auth + CSRF), Services, App (baseURL autodetect)
  Controllers/     Public pages, Api (read-only board JSON), Admin/*
  Database/
    Migrations/    27 tables in four domain migrations
    Seeds/         Roles/permissions, staff + players, facility, programs, 3 weeks of traffic
  Filters/         AuthFilter (permission-aware), GuestFilter
  Helpers/         bernie_helper.php — peso(), clock(), status_meta(), partial()
  Services/
    Availability   Builds the courts x slots board in 3 queries
    Bookings       Transactional create / cancel / check-in
    Auth           Session auth, bcrypt, lockout, permission cache
    Audit          Append-only activity log
    Notify         In-app notifications
    Settings       Typed access to the settings table
  Views/
    layouts/       public.php, admin.php
    partials/      board, facility_map, court_scene_svg, nav, footer, page_head
    pages/         16 public surfaces
    admin/         dashboard, bookings, courts, audit
public/assets/
  css/             app.css (design system), home.css, admin.css
  js/              app.js (shared), hero.js (3D + choreography)
  fonts/           Archivo + Chivo Mono variable woff2, self-hosted
  vendor/          three.module.min.js, gsap.min.js, ScrollTrigger.min.js
```

## The 3D scene

`public/assets/js/hero.js`. Rules it obeys:

- **Nothing 3D blocks first paint.** The hero ships a finished SVG court that is
  good enough on its own. Three.js is fetched after `load` + `requestIdleCallback`.
- **Not loaded at all** on `prefers-reduced-motion`, `saveData`, `deviceMemory < 4`,
  `hardwareConcurrency < 4`, viewport under 720px, or no WebGL2. Those users keep the SVG.
- **The scene is real data** — five courts in their actual `map_x`/`map_y`
  arrangement, each surface tinted by live occupancy. Hovering a court in the
  facility map highlights it in the scene.
- Render loop stops when the canvas scrolls out of view or the tab hides.
  `pixelRatio` clamped to 1.5.
- Only one other page (`/courts/{id}`) gets a 3D view. Everything else is flat.

## Security

- CSRF on every state-changing request (`Config\Filters` global `before`), with
  the read-only GET API excluded.
- `password_hash()` / `password_verify()` only, automatic rehash when PHP's cost
  moves, six failed attempts locks the account for 15 minutes, identical error
  text for unknown-email and wrong-password.
- Session regenerated on sign-in and registration (blocks fixation).
- Every query goes through the query builder — bound parameters throughout.
- Prices are recomputed server-side from court rates; a posted `total_amount`
  is ignored.
- `access.denied`, `auth.login`, `booking.created`, `booking.cancelled` and
  friends are written to `audit_logs` with actor, IP and user agent.

## Shipped vs. next

**Working now:** all 16 public pages, live availability board, the full booking
engine (court/date/duration/slot/players/equipment/coach/promo/payment with
conflict prevention), player accounts and self-service cancellation, auth and
seven-role RBAC, dashboard overview with peak-hour analytics, booking calendar
with check-in and cancel, court management, maintenance schedule, audit log,
PWA (installable, offline shell, live data never cached).

**Next pass:** the remaining dashboard modules are stubbed in the sidebar and
marked *soon* — walk-ins, customers, memberships, coaches, staff, tournament
brackets and live scoring, equipment, inventory, payments, promo codes, QR
check-in, announcements, the report suite, roles editor and settings. The schema
and permissions for all of them already exist; only the CRUD screens are pending.
