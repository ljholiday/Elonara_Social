self.addEventListener("install", event => {
  self.skipWaiting();
});

self.addEventListener("activate", event => {
  event.waitUntil(caches.keys().then(keys => Promise.all(keys.map(k => caches.delete(k)))));
  self.clients.claim();
});

// Pass-through: no offline caching
self.addEventListener("fetch", event => {
  event.respondWith(fetch(event.request));
});

