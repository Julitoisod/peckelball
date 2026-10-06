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

  /* ------------------------------------------------------ service worker */
  if ('serviceWorker' in navigator) {
    addEventListener('load', () => {
      const base = document.querySelector('link[rel="manifest"]')?.href.replace('manifest.webmanifest', '') || '/';
      navigator.serviceWorker.register(`${base}sw.js`, { scope: base }).catch(() => {});
    });
  }
})();
