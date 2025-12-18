<?php
header('Content-Type: application/javascript');
header('Cache-Control: public, max-age=3600');
?>
// Service Worker - Offline Support & Smart Caching Strategy
const CACHE_NAME = 'webdaddy-cache-v1';
const URLS_TO_CACHE = [
  '/',
  '/about.php',
  '/contact.php',
  '/faq.php',
  '/careers.php',
  '/blog/',
  '/assets/css/premium.css',
  '/assets/js/performance.js',
  '/assets/js/scroll-restoration.js',
  '/assets/js/network-optimization.js',
  '/assets/images/webdaddy-logo.png'
];

// Install event - cache critical pages
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      return cache.addAll(URLS_TO_CACHE);
    })
  );
  self.skipWaiting();
});

// Activate event - clean up old caches
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((cacheNames) => {
      return Promise.all(
        cacheNames.map((cacheName) => {
          if (cacheName !== CACHE_NAME) {
            return caches.delete(cacheName);
          }
        })
      );
    })
  );
  self.clients.claim();
});

// Fetch event - cache-first strategy for assets, network-first for pages
self.addEventListener('fetch', (event) => {
  const { request } = event;
  const url = new URL(request.url);

  // Skip non-GET requests
  if (request.method !== 'GET') {
    return;
  }

  // Skip external domains
  if (url.origin !== location.origin) {
    return;
  }

  // Cache-first strategy for assets (CSS, JS, images, fonts)
  if (/\.(css|js|woff2?|ttf|otf|svg|png|jpe?g|gif|webp|ico|mp4)$/.test(url.pathname)) {
    event.respondWith(
      caches.match(request).then((response) => {
        if (response) return response;
        return fetch(request).then((response) => {
          if (!response || response.status !== 200 || response.type === 'error') {
            return response;
          }
          const responseToCache = response.clone();
          caches.open(CACHE_NAME).then((cache) => {
            cache.put(request, responseToCache);
          });
          return response;
        });
      })
    );
    return;
  }

  // Network-first strategy for HTML pages (with offline fallback)
  event.respondWith(
    fetch(request)
      .then((response) => {
        if (!response || response.status !== 200) {
          return caches.match(request).then((cached) => cached || response);
        }
        const responseToCache = response.clone();
        caches.open(CACHE_NAME).then((cache) => {
          cache.put(request, responseToCache);
        });
        return response;
      })
      .catch(() => {
        return caches.match(request).then((response) => {
          return response || caches.match('/');
        });
      })
  );
});