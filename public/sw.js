/* Go Paddle service worker.
 *
 * Deliberately conservative: the availability board is a claim about *right
 * now*, so nothing that renders a board is ever served from cache. The shell,
 * fonts, CSS and JS are cached so the app opens instantly and the offline page
 * is always available — but a court that says "open" is always a live answer.
 */

const VERSION = 'bernie-v13';
// One bucket. Two (shell + runtime) meant the same font could be stored twice
// and the fetch handler had to guess which to look in.
const CACHE = VERSION;
const BASE = new URL('./', self.location).pathname;

// No `?v=` strings here. They have to match the markup exactly to ever be a
// hit, and the moment one side is bumped and the other is not you are caching
// a URL nobody requests while the real one goes to the network every time.
// The fetch handler is stale-while-revalidate, so freshness does not depend on
// this list being right — it only decides what is warm on first run.
const PRECACHE = [
  `${BASE}offline`,
  `${BASE}assets/fonts/archivo-latin.woff2`,
  `${BASE}assets/fonts/chivomono-latin.woff2`,
  `${BASE}assets/icons/favicon.svg`,
];

// Anything whose answer changes minute to minute. Never cached.
const LIVE = /\/(availability|booking|admin|account|api|login|register|logout)(\/|$|\?)/;

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE)
      // addAll rejects the whole batch on one 404; add individually so a
      // single missing asset cannot break installation.
      .then((cache) => Promise.all(PRECACHE.map((url) => cache.add(url).catch(() => {}))))
      .then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(keys.filter((k) => !k.startsWith(VERSION)).map((k) => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  const { request } = event;

  if (request.method !== 'GET') return;

  const url = new URL(request.url);
  if (url.origin !== self.location.origin) return;   // Unsplash etc: leave to the browser
  if (LIVE.test(url.pathname)) return;               // live data: always network

  // Static assets: stale-while-revalidate. The cached copy answers instantly,
  // and a fresh one is fetched behind it for next time. Cache-first was faster
  // by nothing measurable and pinned returning visitors to whatever CSS was
  // current the day they first loaded the site, because the only thing that
  // could ever evict it was a hand-edited `?v=` in two files at once.
  if (/\.(css|js|woff2|svg|png|webmanifest)$/.test(url.pathname)) {
    event.respondWith(
      caches.open(CACHE).then((cache) => cache.match(request).then((hit) => {
        const live = fetch(request).then((res) => {
          if (res.ok) cache.put(request, res.clone()).catch(() => {});
          return res;
        }).catch(() => hit);

        return hit || live;
      }))
    );
    return;
  }

  // Documents: network first, fall back to the offline page.
  if (request.mode === 'navigate') {
    event.respondWith(
      fetch(request).catch(() => caches.match(`${BASE}offline`).then((hit) => hit || new Response(
        '<h1>Offline</h1><p>Reconnect to see live court availability.</p>',
        { headers: { 'Content-Type': 'text/html; charset=utf-8' }, status: 503 }
      )))
    );
  }
});
