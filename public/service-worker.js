// Version bump triggers Chrome to refresh the cache
const CACHE_NAME = 'elonara-cache-v3';
const urlsToCache = [
  '/',
  '/manifest.json',
  '/offline.html',
  '/assets/css/app.css',
  '/assets/js/app.js',
  '/assets/icons/icon-192.png',
  '/assets/icons/icon-512.png'
];

// Install event: pre-cache key resources, including offline page
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME).then(cache => cache.addAll(urlsToCache))
  );
  self.skipWaiting(); // activate immediately
});

// Fetch event: try network first, fall back to cache, then offline page
self.addEventListener('fetch', event => {
  event.respondWith(
    fetch(event.request)
      .then(response => {
        // Cache fetched responses for reuse
        const clone = response.clone();
        caches.open(CACHE_NAME).then(cache => cache.put(event.request, clone));
        return response;
      })
      .catch(() =>
        caches.match(event.request).then(response => response || caches.match('/offline.html'))
      )
  );
});

// Activate event: clear old caches
self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(keys =>
      Promise.all(keys.filter(k => k !== CACHE_NAME).map(k => caches.delete(k)))
    )
  );
  self.clients.claim();
});

