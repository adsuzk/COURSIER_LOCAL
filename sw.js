// Service Worker pour PWA
const CACHE_NAME = 'suzosky-coursier-v2.0.0';
const urlsToCache = [
  '/coursier_prod/app_mobile_web.html',
  '/coursier_prod/mobile_update_api.php',
  '/coursier_prod/api/coursier/login.php'
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then((cache) => {
        return cache.addAll(urlsToCache);
      })
  );
});

self.addEventListener('fetch', (event) => {
  event.respondWith(
    caches.match(event.request)
      .then((response) => {
        if (response) {
          return response;
        }
        return fetch(event.request);
      }
    )
  );
});
