/* Go Paddle — shared behaviour.
 * No framework. Everything here is progressive: the page is complete and
 * usable before this file runs, and stays complete if it never does. */

(() => {
  'use strict';

  const reduced = matchMedia('(prefers-reduced-motion: reduce)');

  /* ------------------------------------------------------------------ nav */
  const nav = document.querySelector('[data-nav]');
  const navToggle = document.querySelector('[data-nav-toggle]');

  if (nav && navToggle) {
    navToggle.addEventListener('click', () => {
      const open = nav.dataset.open === 'true';
      nav.dataset.open = String(!open);
      navToggle.setAttribute('aria-expanded', String(!open));
    });

    // Close the mobile sheet on Escape or on navigation.
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && nav.dataset.open === 'true') {
        nav.dataset.open = 'false';
        navToggle.setAttribute('aria-expanded', 'false');
        navToggle.focus();
      }
    });
  }

  /* ------------------------------------------------------------ dropdown */
  document.querySelectorAll('[data-menu]').forEach((menu) => {
    const toggle = menu.querySelector('[data-menu-toggle]');
    if (!toggle) return;

    toggle.addEventListener('click', (e) => {
      e.stopPropagation();
      const open = menu.dataset.open === 'true';
      menu.dataset.open = String(!open);
      toggle.setAttribute('aria-expanded', String(!open));
    });

    document.addEventListener('click', (e) => {
      if (!menu.contains(e.target) && menu.dataset.open === 'true') {
        menu.dataset.open = 'false';
        toggle.setAttribute('aria-expanded', 'false');
      }
    });
  });

  /* ------------------------------------------------------- court linking */
  /* The facility plan and the list beside it are two views of the same five
   * courts, so pointing at one has to light the other. Works with no WebGL —
   * hero.js reads the same attribute to pin the 3D court, but this does not
   * depend on it. */
  const linked = document.querySelectorAll('[data-map-court]');
  if (linked.length) {
    const byId = new Map();
    linked.forEach((el) => {
      const id = el.dataset.mapCourt;
      if (!byId.has(id)) byId.set(id, []);
      byId.get(id).push(el);
    });

    const mark = (id, on) => byId.get(id)?.forEach((el) => el.classList.toggle('is-linked', on));

    linked.forEach((el) => {
      const id = el.dataset.mapCourt;
      el.addEventListener('pointerenter', () => mark(id, true));
      el.addEventListener('pointerleave', () => mark(id, false));
      el.addEventListener('focus', () => mark(id, true));
      el.addEventListener('blur', () => mark(id, false));
    });
  }

  /* -------------------------------------------------------------- clock */
  const clocks = document.querySelectorAll('[data-clock]');
  if (clocks.length) {
    const tick = () => {
      const now = new Date();
      const text = now.toLocaleTimeString('en-PH', { hour: 'numeric', minute: '2-digit', hour12: true });
      clocks.forEach((el) => { el.textContent = text; });
    };
    tick();
    setInterval(tick, 20000);
  }

  /* ----------------------------------------------------- scroll progress */
  /* One custom property per frame, read by `transform: scaleX()` in CSS. No
   * layout, no reads inside the scroll handler — the value is written on the
   * next animation frame and coalesced, so a fast wheel spin costs one write. */
  const progress = document.querySelector('[data-progress]');
  if (progress) {
    let queued = false;

    const paint = () => {
      queued = false;
      const max = document.documentElement.scrollHeight - innerHeight;
      progress.style.setProperty('--p', max > 0 ? Math.min(scrollY / max, 1).toFixed(4) : '0');
    };

    addEventListener('scroll', () => {
      if (queued) return;
      queued = true;
      requestAnimationFrame(paint);
    }, { passive: true });

    addEventListener('resize', paint, { passive: true });
    paint();
  }

  /* ------------------------------------------------------------ reveals */
  const revealables = document.querySelectorAll('.reveal');
  if (revealables.length && !reduced.matches && 'IntersectionObserver' in window) {
    const io = new IntersectionObserver((entries) => {
      entries.forEach((entry, i) => {
        if (!entry.isIntersecting) return;
        // Stagger within the batch that entered together — one list, one motion.
        entry.target.style.transitionDelay = `${Math.min(i, 5) * 70}ms`;
        entry.target.classList.add('is-in');
        io.unobserve(entry.target);
      });
    }, { rootMargin: '0px 0px -12% 0px', threshold: 0.15 });

    revealables.forEach((el) => io.observe(el));
  } else {
    revealables.forEach((el) => el.classList.add('is-in'));
  }

  /* -------------------------------------------------------- live board */
  /* The board is a claim about right now. Refresh it while the tab is
   * visible so a slot that went during a long read is not still green. */
  const board = document.querySelector('[data-board][data-live]');
  if (board) {
    const REFRESH_MS = 60000;
    let timer = null;

    const refresh = async () => {
      try {
        const res = await fetch(`api/availability?date=${encodeURIComponent(board.dataset.date)}&html=1`, {
          headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });
        if (!res.ok) return;
        const html = await res.text();
        const next = new DOMParser().parseFromString(html, 'text/html').querySelector('.board-grid');
        const current = board.querySelector('.board-grid');
        if (next && current) current.replaceWith(next);
      } catch { /* offline or slow — the stale board is still better than a blank one */ }
    };

    const start = () => { stop(); timer = setInterval(refresh, REFRESH_MS); };
    const stop = () => { if (timer) clearInterval(timer); timer = null; };

    document.addEventListener('visibilitychange', () => (document.hidden ? stop() : start()));
    start();
  }

  /* --------------------------------------------------- one submit only --- */
  // A second tap on Book sends the same CSRF token twice; the server spends it
  // on the first request and refuses the second. Locking the form is the fix
  // that stops the double booking attempt as well as the error.
  document.addEventListener('submit', (e) => {
    const form = e.target;
    if (!(form instanceof HTMLFormElement) || form.method.toLowerCase() !== 'post') return;

    // The cancel forms are guarded by an inline `onsubmit="return confirm(…)"`,
    // which runs first. Saying No cancels the submit — locking the button then
    // would leave a dead Cancel on every row of the desk's busiest screen.
    if (e.defaultPrevented) return;

    if (form.dataset.submitting === '1') {
      e.preventDefault();
      return;
    }
    form.dataset.submitting = '1';

    // Deferred: a disabled submit button is dropped from the payload, and the
    // browser has not serialised the form yet when this handler runs.
    setTimeout(() => {
      form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach((btn) => {
        btn.disabled = true;
        btn.setAttribute('aria-busy', 'true');
      });
    }, 0);
  });

  // Coming back to a locked form — bfcache, or the browser back button after a
  // failed submit — has to unlock it, or the page is a dead end.
  addEventListener('pageshow', () => {
    document.querySelectorAll('form[data-submitting]').forEach((form) => {
      delete form.dataset.submitting;
      form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach((btn) => {
        btn.disabled = false;
        btn.removeAttribute('aria-busy');
      });
    });
  });


  /* ------------------------------------------------------- quick booking */
  /*
   * A plain link to /booking opens a card over the page instead of leaving it:
   * court, day, play time, then Continue hands those to the booking page,
   * where the length, the name and the payment are set. The card books the
   * shortest session; anything longer is a choice better made beside the
   * price breakdown. Links that already carry a query (a board cell, "book
   * 09:00 today") are specific — those still navigate.
   *
   * The card holds no copy of the schedule: courts come from /api/availability
   * and play times from /api/slots, so it cannot disagree with the board.
   */
  const dialog = document.querySelector('[data-book-modal]');

  if (dialog && typeof dialog.showModal === 'function') {
    const cfg = (() => {
      try {
        return JSON.parse(document.querySelector('script[data-booking-config]')?.dataset.bookingConfig || '{}');
      } catch { return {}; }
    })();
    const min = cfg.min || 60;

    const el = {
      courts: dialog.querySelector('[data-book-courts]'),
      date: dialog.querySelector('[data-book-date]'),
      times: dialog.querySelector('[data-book-times]'),
      summary: dialog.querySelector('[data-book-summary]'),
      price: dialog.querySelector('[data-book-price]'),
      go: dialog.querySelector('[data-book-continue]'),
    };
    const bookingUrl = el.go.getAttribute('href');
    const peso = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP', maximumFractionDigits: 0 });

    const state = { court: null, courtLabel: '', date: cfg.today || '', duration: min, start: null, price: 0 };
    let courts = null;
    let slotToken = 0;

    const tile = (label, sub, checked, group, onPick) => {
      const wrap = document.createElement('label');
      wrap.className = 'tile';
      const input = document.createElement('input');
      input.type = 'radio';
      input.name = group;
      input.checked = checked;
      const face = document.createElement('span');
      face.className = 'tile-face';
      const b = document.createElement('b');
      b.textContent = label;
      face.append(b);
      if (sub) {
        const span = document.createElement('span');
        span.textContent = sub;
        face.append(span);
      }
      wrap.append(input, face);
      input.addEventListener('change', onPick);
      return wrap;
    };

    const renderSummary = () => {
      const ready = state.court && state.start;
      el.summary.textContent = ready
        ? `${state.courtLabel} · ${state.start.label} · ${state.duration / 60}h`
        : 'Pick a play time';
      el.price.textContent = ready ? peso.format(state.price) : '';
      el.go.setAttribute('aria-disabled', ready ? 'false' : 'true');
      el.go.href = ready
        ? `${bookingUrl}?court=${state.court}&date=${encodeURIComponent(state.date)}&duration=${state.duration}&start=${encodeURIComponent(state.start.value)}`
        : bookingUrl;
    };

    const clock = (hhmm) => {
      const [h, m] = hhmm.split(':').map(Number);
      const suffix = h < 12 ? 'AM' : 'PM';
      const hour = h % 12 === 0 ? 12 : h % 12;
      return m === 0 ? `${hour}:00 ${suffix}` : `${hour}:${String(m).padStart(2, '0')} ${suffix}`;
    };

    const loadTimes = async () => {
      if (!state.court) return;
      const token = ++slotToken;
      el.times.textContent = 'Loading…';
      state.start = null;
      renderSummary();

      let slots = [];
      try {
        const res = await fetch(`${bookingUrl.replace(/booking$/, '')}api/slots?court=${state.court}&date=${encodeURIComponent(state.date)}&duration=${state.duration}`, { headers: { Accept: 'application/json' } });
        slots = (await res.json()).slots || [];
      } catch {
        el.times.textContent = 'Could not load play times. ';
        const link = document.createElement('a');
        link.href = bookingUrl;
        link.textContent = 'Open the booking page';
        el.times.append(link);
        return;
      }
      if (token !== slotToken) return;

      el.times.textContent = '';
      if (slots.length === 0) {
        el.times.textContent = 'Nothing free that long on this day. Try a shorter session or another day.';
        return;
      }

      slots.forEach((slot) => {
        el.times.append(tile(clock(slot.start), peso.format(slot.price), false, 'book-start', () => {
          state.start = { value: slot.start, label: clock(slot.start) };
          state.price = slot.price;
          renderSummary();
        }));
      });
    };

    const loadCourts = async () => {
      if (courts) return;
      const res = await fetch(`${bookingUrl.replace(/booking$/, '')}api/availability?date=${encodeURIComponent(state.date)}`, { headers: { Accept: 'application/json' } });
      courts = (await res.json()).courts || [];

      el.courts.textContent = '';
      courts.forEach((court, i) => {
        el.courts.append(tile(court.code, court.name, i === 0, 'book-court', () => {
          state.court = court.id;
          state.courtLabel = court.code;
          loadTimes();
        }));
      });

      if (courts.length > 0) {
        state.court = courts[0].id;
        state.courtLabel = courts[0].code;
      }
    };

    const open = async (prefill) => {
      state.date = prefill.date || state.date || cfg.today || '';
      el.date.value = state.date;
      el.date.min = cfg.today || '';
      if (cfg.advance) {
        const maxDate = new Date(`${cfg.today}T00:00:00`);
        maxDate.setDate(maxDate.getDate() + cfg.advance);
        el.date.max = maxDate.toISOString().slice(0, 10);
      }

      dialog.showModal();
      try {
        await loadCourts();
        await loadTimes();
      } catch {
        el.times.textContent = 'Could not load availability.';
      }
    };

    el.date.addEventListener('change', () => {
      state.date = el.date.value;
      loadTimes();
    });

    el.go.addEventListener('click', (e) => {
      if (el.go.getAttribute('aria-disabled') === 'true') e.preventDefault();
    });

    // Clicking the backdrop closes it, the way a card over a page should.
    dialog.addEventListener('click', (e) => {
      if (e.target === dialog) dialog.close();
    });

    document.addEventListener('click', (e) => {
      const link = e.target.closest('a[href]');
      if (!link || e.metaKey || e.ctrlKey || e.shiftKey || link.target === '_blank') return;

      const url = new URL(link.href, location.href);
      if (url.origin !== location.origin) return;
      if (!/\/booking\/?$/.test(url.pathname) || url.search) return;
      // On the booking page itself the full form is already in front of them.
      if (/\/booking\/?$/.test(location.pathname)) return;

      e.preventDefault();
      if (nav?.dataset.open === 'true') nav.dataset.open = 'false';
      open({});
    });
  }

  /* ------------------------------------------------------ service worker */
  if ('serviceWorker' in navigator) {
    addEventListener('load', () => {
      const base = document.querySelector('link[rel="manifest"]')?.href.replace('manifest.webmanifest', '') || '/';
      navigator.serviceWorker.register(`${base}sw.js`, { scope: base }).catch(() => {});
    });
  }
})();
