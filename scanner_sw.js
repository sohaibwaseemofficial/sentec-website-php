const CACHE_NAME = 'sentec-gate-v3';
const ASSETS_TO_CACHE = [
  'scanner_manifest.json',
  'images/favicon2.png',
  'images/SENTECNEWWHITELOGO.webp',
  'https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700;800&family=IBM+Plex+Mono:wght@500;700&display=swap',
  'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css',
  'https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js'
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      return cache.addAll(ASSETS_TO_CACHE).catch((err) => {
        console.warn('Some cache items failed to precache:', err);
      });
    }).then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) => {
      return Promise.all(
        keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key))
      );
    }).then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  const url = new URL(event.request.url);

  // Never cache API endpoints - they have their own IndexedDB/Outbox sync layer
  if (url.pathname.includes('/api/')) {
    return;
  }

  // Network-First for HTML navigation / scanner pages (so updates are immediately live)
  const isScannerPage = event.request.mode === 'navigate' || url.pathname.endsWith('scanner.php') || url.pathname.endsWith('/scanner');

  if (isScannerPage) {
    event.respondWith(
      fetch(event.request).then((networkResponse) => {
        if (networkResponse && networkResponse.status === 200) {
          const responseToCache = networkResponse.clone();
          caches.open(CACHE_NAME).then((cache) => {
            cache.put(event.request, responseToCache);
          });
        }
        return networkResponse;
      }).catch(() => {
        // Offline fallback to cached scanner page
        return caches.match(event.request).then((cached) => cached || caches.match('scanner') || caches.match('scanner.php'));
      })
    );
    return;
  }

  // Cache-First for static CDN and fonts
  event.respondWith(
    caches.match(event.request).then((cachedResponse) => {
      if (cachedResponse) {
        return cachedResponse;
      }
      return fetch(event.request).then((networkResponse) => {
        if (!networkResponse || networkResponse.status !== 200 || networkResponse.type !== 'basic') {
          return networkResponse;
        }
        const responseToCache = networkResponse.clone();
        caches.open(CACHE_NAME).then((cache) => {
          cache.put(event.request, responseToCache);
        });
        return networkResponse;
      });
    })
  );
});
