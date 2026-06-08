/**
 * MedAgent AI – Service Worker
 *
 * Strategy:
 *   • Precache: app shell + offline page + icons + fonts CSS at install.
 *   • Runtime:
 *       - Navigation requests (HTML): network-first, fall back to /offline.html
 *         (never caches authed pages — see SKIP_CACHE_PATTERNS).
 *       - Static GET assets (CSS, JS, images, fonts): stale-while-revalidate.
 *       - Same-origin POST / API / auth / admin / uploads: never intercepted.
 *       - Cross-origin (CDNs, fonts): cache-first with network fallback.
 *
 * Security:
 *   • Only intercepts GET requests.
 *   • Never caches responses that lack a 200 status or contain Set-Cookie.
 *   • Skips any request to /api/, /admin/, /login, /logout, /cases/*/messages,
 *     /cases/*/report — these always go to the network.
 *   • Cache name is versioned; old caches are purged on activate.
 *
 * Bump APP_VERSION on every deploy that changes precached files.
 */
'use strict';

const APP_VERSION = '1.0.1';
const PRECACHE   = `medagent-precache-v${APP_VERSION}`;
const RUNTIME    = `medagent-runtime-v${APP_VERSION}`;
const FONT_CACHE = `medagent-fonts-v${APP_VERSION}`;

// Files known at install time. Anything else is opportunistically cached.
const PRECACHE_URLS = [
  '/offline.html',
  '/manifest.webmanifest',
  '/assets/pwa.css',
  '/assets/pwa.js',
  '/favicon.svg',
  '/app-icon.svg',
  '/icons/icon-192.png',
  '/icons/icon-512.png',
  '/icons/icon-192-maskable.png',
  '/icons/icon-512-maskable.png',
  '/icons/apple-touch-icon.png',
  '/icons/favicon-32.png',
];

// Paths whose GET responses MUST NOT be cached.
const SKIP_CACHE_PATTERNS = [
  /^\/api\//,
  /^\/admin(\/|$)/,
  /^\/login(\/|\?|$)/,
  /^\/logout(\/|$)/,
  /^\/register(\/|\?|$)/,
  /^\/cases\/\d+\/messages/,
  /^\/cases\/\d+\/report/,
  /^\/cases\/\d+\/documents/,
];

// Same-origin asset extensions handled with stale-while-revalidate.
const ASSET_EXT = /\.(?:css|js|mjs|png|jpg|jpeg|gif|svg|webp|avif|ico|woff2?|ttf|otf|json|webmanifest)$/i;

// ---------- Install / activate ----------

self.addEventListener('install', (event) => {
  event.waitUntil((async () => {
    const cache = await caches.open(PRECACHE);
    // Add one at a time so a single 404 doesn't abort the whole install.
    await Promise.allSettled(
      PRECACHE_URLS.map((url) => cache.add(new Request(url, { cache: 'reload' })))
    );
    await self.skipWaiting();
  })());
});

self.addEventListener('activate', (event) => {
  event.waitUntil((async () => {
    const keep = new Set([PRECACHE, RUNTIME, FONT_CACHE]);
    const names = await caches.keys();
    await Promise.all(names.map((n) => (keep.has(n) ? null : caches.delete(n))));
    if ('navigationPreload' in self.registration) {
      try { await self.registration.navigationPreload.enable(); } catch (_) {}
    }
    await self.clients.claim();
  })());
});

// ---------- Helpers ----------

function isSkipPath(pathname) {
  return SKIP_CACHE_PATTERNS.some((re) => re.test(pathname));
}

function isCacheableResponse(response) {
  // Don't cache opaque, redirects, errors, or responses with cookies.
  if (!response || response.status !== 200) return false;
  if (response.type === 'opaqueredirect' || response.type === 'error') return false;
  if (response.headers.get('Set-Cookie')) return false;
  // Don't cache anything declared private/no-store.
  const cc = (response.headers.get('Cache-Control') || '').toLowerCase();
  if (cc.includes('no-store') || cc.includes('private')) return false;
  return true;
}

async function networkFirst(request, cacheName, fallbackUrl) {
  const cache = await caches.open(cacheName);
  try {
    const preload = await self.registration.navigationPreload?.preloadResponse?.then?.(() => null);
    const fresh = preload || await fetch(request);
    if (isCacheableResponse(fresh)) cache.put(request, fresh.clone()).catch(() => {});
    return fresh;
  } catch (_) {
    const cached = await cache.match(request);
    if (cached) return cached;
    if (fallbackUrl) {
      const fallback = await caches.match(fallbackUrl);
      if (fallback) return fallback;
    }
    return new Response('Offline', { status: 503, statusText: 'Offline' });
  }
}

async function staleWhileRevalidate(request, cacheName) {
  const cache = await caches.open(cacheName);
  const cached = await cache.match(request);
  const networkPromise = fetch(request)
    .then((response) => {
      if (isCacheableResponse(response)) cache.put(request, response.clone()).catch(() => {});
      return response;
    })
    .catch(() => null);
  return cached || (await networkPromise) || new Response('Offline', { status: 503 });
}

async function cacheFirst(request, cacheName) {
  const cache = await caches.open(cacheName);
  const cached = await cache.match(request);
  if (cached) return cached;
  try {
    const fresh = await fetch(request);
    if (isCacheableResponse(fresh) || fresh.type === 'opaque') {
      cache.put(request, fresh.clone()).catch(() => {});
    }
    return fresh;
  } catch (_) {
    return new Response('Offline', { status: 503 });
  }
}

// ---------- Fetch routing ----------

self.addEventListener('fetch', (event) => {
  const req = event.request;
  if (req.method !== 'GET') return;                 // never intercept writes
  const url = new URL(req.url);

  // Cross-origin: handle fonts/CDNs (cache-first, opaque tolerated), bypass
  // everything else (let the browser handle it directly).
  if (url.origin !== self.location.origin) {
    if (/fonts\.(googleapis|gstatic)\.com$/i.test(url.hostname) ||
        /cdn\.tailwindcss\.com$/i.test(url.hostname) ||
        /cdn\.jsdelivr\.net$/i.test(url.hostname)) {
      event.respondWith(cacheFirst(req, FONT_CACHE));
    }
    return;
  }

  // Same-origin sensitive paths -> never cache, never serve from cache.
  if (isSkipPath(url.pathname)) return;

  // Navigation requests (page loads): network-first, offline fallback.
  if (req.mode === 'navigate' ||
      (req.headers.get('Accept') || '').includes('text/html')) {
    event.respondWith(networkFirst(req, RUNTIME, '/offline.html'));
    return;
  }

  // Static assets by extension: stale-while-revalidate.
  if (ASSET_EXT.test(url.pathname) || url.pathname.startsWith('/icons/') ||
      url.pathname.startsWith('/assets/') || url.pathname.startsWith('/screenshots/')) {
    event.respondWith(staleWhileRevalidate(req, RUNTIME));
    return;
  }
  // Everything else -> default network behavior.
});

// ---------- Messaging ----------

self.addEventListener('message', (event) => {
  const data = event.data || {};
  if (data.type === 'SKIP_WAITING') self.skipWaiting();
  if (data.type === 'CLEAR_CACHES') {
    event.waitUntil((async () => {
      const names = await caches.keys();
      await Promise.all(names.map((n) => caches.delete(n)));
    })());
  }
});

// ---------- Push (fallback if OneSignal SDK is not active) ----------
// OneSignal registers its own service worker (OneSignalSDKWorker.js) and
// handles push there. These handlers run only if you ever decide to publish
// raw web-push directly via this SW.

self.addEventListener('push', (event) => {
  let payload = {};
  try { payload = event.data ? event.data.json() : {}; } catch (_) {
    payload = { title: 'MedAgent AI', body: event.data ? event.data.text() : '' };
  }
  const title = payload.title || 'MedAgent AI';
  const options = {
    body: payload.body || '',
    icon: '/icons/icon-192.png',
    badge: '/icons/icon-96.png',
    data: { url: payload.url || '/dashboard' },
    tag: payload.tag || 'medagent-notification',
    renotify: true,
  };
  event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const target = (event.notification.data && event.notification.data.url) || '/dashboard';
  event.waitUntil((async () => {
    const all = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
    for (const c of all) {
      if (new URL(c.url).origin === self.location.origin) {
        await c.focus();
        c.navigate(target).catch(() => {});
        return;
      }
    }
    await self.clients.openWindow(target);
  })());
});
