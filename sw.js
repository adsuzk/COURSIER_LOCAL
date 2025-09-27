// Service Worker pour PWA
const CACHE_NAME = 'suzosky-coursier-v2.0.0';
// Utiliser des URLs relatives au scope du SW pour supporter sous-dossiers
const urlsToCache = [
  './app_mobile_web.html',
  './mobile_update_api.php',
  './api/coursier/login.php'
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
