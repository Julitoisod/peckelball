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
   * A plain link to /booking opens a card over the page instead of leaving
   * it, and the whole booking happens there: court, day and as many hours as
   * the player wants, then who is playing and how they are paying. The card
   * posts the ordinary booking form, so the server validates, re-prices and
   * guards the slots exactly as it does for the full page. Links that already
   * carry a query (a board cell, "book 09:00 today") are specific — those
   * still navigate.
   *
   * Hours are picked one by one and have to run back to back, because a
   * booking is one start plus a length — two separate ranges would be two
   * bookings. Tapping away from the run starts a new one.
   *
   * The card holds no copy of the schedule: courts come from
   * /api/availability and the free hours from /api/slots at one slot each, so
   * it cannot disagree with the board. The server re-prices and re-checks
   * every slot on submit regardless.
   */
  const dialog = document.querySelector('[data-book-modal]');

  if (dialog && typeof dialog.showModal === 'function') {
    const cfg = (() => {
      try {
        return JSON.parse(document.querySelector('script[data-booking-config]')?.dataset.bookingConfig || '{}');
      } catch { return {}; }
    })();
    const step = cfg.step || 60;
    const max = cfg.max || 180;
    const maxHours = Math.max(1, Math.floor(max / step));

    const el = {
      form: dialog.querySelector('[data-book-form]'),
      title: dialog.querySelector('[data-book-title]'),
      back: dialog.querySelector('[data-book-back]'),
      close: dialog.querySelector('[data-book-close]'),
      step1: dialog.querySelector('[data-book-step="1"]'),
      step2: dialog.querySelector('[data-book-step="2"]'),
      courts: dialog.querySelector('[data-book-courts]'),
      date: dialog.querySelector('[data-book-date]'),
      times: dialog.querySelector('[data-book-times]'),
      summary: dialog.querySelector('[data-book-summary]'),
      price: dialog.querySelector('[data-book-price]'),
      go: dialog.querySelector('[data-book-continue]'),
      submit: dialog.querySelector('[data-book-submit]'),
      field: (name) => dialog.querySelector(`[data-book-field="${name}"]`),
    };
    const bookingUrl = el.form.getAttribute('action');
    const apiBase = bookingUrl.replace(/booking$/, '');
    const peso = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP', maximumFractionDigits: 0 });

    const state = { court: null, courtLabel: '', date: cfg.today || '', slots: [], picked: [], step: 1 };
    let courts = null;
    let slotToken = 0;

    const minutes = (hhmm) => {
      const [h, m] = hhmm.split(':').map(Number);
      return h * 60 + m;
    };
    const clock = (hhmm) => {
      const [h, m] = hhmm.split(':').map(Number);
      const suffix = h < 12 ? 'AM' : 'PM';
      const hour = h % 12 === 0 ? 12 : h % 12;
      return m === 0 ? `${hour}:00 ${suffix}` : `${hour}:${String(m).padStart(2, '0')} ${suffix}`;
    };
    const after = (hhmm, mins) => {
      const total = (minutes(hhmm) + mins) % 1440;
      return `${String(Math.floor(total / 60)).padStart(2, '0')}:${String(total % 60).padStart(2, '0')}`;
    };
    /** Does slot b start exactly when slot a ends? */
    const follows = (a, b) => a && b && after(a.start, step) === b.start;

    const courtTile = (court, checked, onPick) => {
      const wrap = document.createElement('label');
      wrap.className = 'tile';
      const input = document.createElement('input');
      input.type = 'radio';
      input.name = 'book-court';
      input.checked = checked;
      const face = document.createElement('span');
      face.className = 'tile-face';
      const b = document.createElement('b');
      b.textContent = court.code;
      const span = document.createElement('span');
      span.textContent = court.name;
      face.append(b, span);
      wrap.append(input, face);
      input.addEventListener('change', onPick);
      return wrap;
    };

    const paintPicked = () => {
      const picked = state.picked;
      [...el.times.children].forEach((node, i) => {
        if (node.tagName !== 'BUTTON') return;
        node.setAttribute('aria-pressed', picked.includes(i) ? 'true' : 'false');
      });

      if (picked.length === 0) {
        el.summary.textContent = 'Pick a play time';
        el.price.textContent = '';
        el.go.setAttribute('aria-disabled', 'true');
        return;
      }

      const first = state.slots[picked[0]];
      const total = picked.reduce((sum, i) => sum + Number(state.slots[i].price || 0), 0);
      const duration = picked.length * step;
      const hours = duration % 60 === 0 ? `${duration / 60}h` : `${duration}m`;

      el.summary.textContent = `${state.courtLabel} · ${clock(first.start)}–${clock(after(first.start, duration))} · ${hours}`;
      el.price.textContent = peso.format(total);
      el.go.setAttribute('aria-disabled', 'false');

      // What the form will actually post. The server re-checks all of it.
      el.field('court').value = state.court;
      el.field('date').value = state.date;
      el.field('duration').value = duration;
      el.field('start').value = first.start;
    };

    const hint = (text) => {
      let note = dialog.querySelector('[data-book-note]');
      if (!note) {
        note = document.createElement('span');
        note.className = 'field-error';
        note.dataset.bookNote = '';
      }
      // Under the times on step 1, above the buttons on step 2.
      (state.step === 2 ? el.step2 : el.times.parentElement).append(note);
      note.textContent = text;
      clearTimeout(hint.timer);
      hint.timer = setTimeout(() => { note.textContent = ''; }, 5000);
    };

    const pick = (i) => {
      const picked = state.picked;
      const slots = state.slots;

      // Nothing yet, or tapping the only picked hour clears it.
      if (picked.length === 0) {
        state.picked = [i];
      } else if (picked.length === 1 && picked[0] === i) {
        state.picked = [];
      } else if (picked.includes(i)) {
        // Inside the run: trim it to end here.
        state.picked = picked.slice(0, picked.indexOf(i) + 1);
      } else if (i === picked[picked.length - 1] + 1 && follows(slots[i - 1], slots[i])) {
        if (picked.length >= maxHours) {
          hint(`${maxHours} hours is the longest one booking can run.`);
          return;
        }
        state.picked = [...picked, i];
      } else if (i === picked[0] - 1 && follows(slots[i], slots[i + 1])) {
        if (picked.length >= maxHours) {
          hint(`${maxHours} hours is the longest one booking can run.`);
          return;
        }
        state.picked = [i, ...picked];
      } else {
        // Not touching the run — start a new one here.
        state.picked = [i];
        hint('Hours have to run back to back, so that started a new session.');
      }

      paintPicked();
    };

    const renderTimes = () => {
      el.times.textContent = '';
      state.slots.forEach((slot, i) => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'time-tile';
        btn.setAttribute('aria-pressed', 'false');
        const b = document.createElement('b');
        b.textContent = clock(slot.start);
        const span = document.createElement('span');
        span.textContent = peso.format(slot.price);
        btn.append(b, span);
        btn.addEventListener('click', () => pick(i));
        el.times.append(btn);
      });
      paintPicked();
    };

    const loadTimes = async () => {
      if (!state.court) return;
      const token = ++slotToken;
      el.times.textContent = 'Loading…';
      state.slots = [];
      state.picked = [];

      let slots = [];
      try {
        const res = await fetch(`${apiBase}api/slots?court=${state.court}&date=${encodeURIComponent(state.date)}&duration=${step}`, { headers: { Accept: 'application/json' } });
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

      state.slots = slots;
      if (slots.length === 0) {
        el.times.textContent = 'Nothing free on this day. Try another day.';
        paintPicked();
        return;
      }
      renderTimes();
    };

    const loadCourts = async () => {
      if (courts) return;
      const res = await fetch(`${apiBase}api/availability?date=${encodeURIComponent(state.date)}`, { headers: { Accept: 'application/json' } });
      courts = (await res.json()).courts || [];

      el.courts.textContent = '';
      courts.forEach((court, i) => {
        el.courts.append(courtTile(court, i === 0, () => {
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

    const open = async () => {
      state.date = state.date || cfg.today || '';
      el.date.value = state.date;
      el.date.min = cfg.today || '';
      if (cfg.advance && cfg.today) {
        const maxDate = new Date(`${cfg.today}T00:00:00`);
        maxDate.setDate(maxDate.getDate() + cfg.advance);
        el.date.max = `${maxDate.getFullYear()}-${String(maxDate.getMonth() + 1).padStart(2, '0')}-${String(maxDate.getDate()).padStart(2, '0')}`;
      }

      dialog.showModal();
      showStep(1);
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

    const showStep = (n) => {
      state.step = n;
      el.step1.hidden = n !== 1;
      el.step2.hidden = n !== 2;
      el.back.hidden = n === 1;
      el.go.hidden = n !== 1;
      el.submit.hidden = n !== 2;
      el.title.textContent = n === 1 ? 'Book a court' : 'Who is playing';
      if (n === 2) dialog.querySelector('[data-book-step="2"] input, [data-book-step="2"] select')?.focus();
      el.step1.scrollTop = 0;
      el.step2.scrollTop = 0;
    };

    el.go.addEventListener('click', () => {
      if (el.go.getAttribute('aria-disabled') === 'true') return;
      showStep(2);
    });
    el.back.addEventListener('click', () => showStep(1));
    el.close.addEventListener('click', () => dialog.close());

    // The guest fields are only required once they are on screen, or an empty
    // hidden input would block the browser from submitting step 2 at all.
    el.form.addEventListener('submit', (e) => {
      const name = el.form.querySelector('[name="guest_name"]');
      const phone = el.form.querySelector('[name="guest_phone"]');
      if (name && (!name.value.trim() || !phone.value.trim())) {
        e.preventDefault();
        hint('A name and a mobile number are needed to hold the court.');
        (name.value.trim() ? phone : name).focus();
      }
    });

    dialog.addEventListener('close', () => showStep(1));

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
      open();
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
