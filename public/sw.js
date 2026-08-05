/* Service worker mínimo: cache de shell estática + network-first para la app.
   No cachea rutas autenticadas/HTML de Inertia (siempre red). */
const CACHE = 'soporte-static-v1';
const PRECACHE = [
  '/manifest.webmanifest',
  '/images/LogoMono.png',
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE).then((cache) => cache.addAll(PRECACHE)).then(() => self.skipWaiting()),
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k))),
    ).then(() => self.clients.claim()),
  );
});

self.addEventListener('fetch', (event) => {
  const req = event.request;
  if (req.method !== 'GET') return;

  const url = new URL(req.url);
  if (url.origin !== self.location.origin) return;

  // No interceptar navegación SPA / API / Vite / hot
  if (req.mode === 'navigate') return;
  if (url.pathname.startsWith('/build/') || url.pathname.includes('hot')) {
    // Assets versionados: cache-first con fallback red
    event.respondWith(
      caches.open(CACHE).then(async (cache) => {
        const hit = await cache.match(req);
        if (hit) return hit;
        const res = await fetch(req);
        if (res.ok) cache.put(req, res.clone());
        return res;
      }),
    );
    return;
  }

  if (url.pathname.startsWith('/images/') || url.pathname === '/manifest.webmanifest') {
    event.respondWith(
      caches.open(CACHE).then(async (cache) => {
        const hit = await cache.match(req);
        if (hit) return hit;
        try {
          const res = await fetch(req);
          if (res.ok) cache.put(req, res.clone());
          return res;
        } catch {
          return hit || Response.error();
        }
      }),
    );
  }
});
