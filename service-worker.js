// service-worker.js
const CACHE_NAME = 'portal-sac-v2';
const urlsToCache = [
  './',
  './login.html',
  './manifest.json',
  './favicon.ico',
  './LOGO ADI-GGM.png',
  './assets/img/icon-192.png',
  './assets/img/icon-512.png'
];

// Instalar el Service Worker y guardar los archivos base
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => {
        return cache.addAll(urlsToCache);
      })
  );
  self.skipWaiting();
});

// Limpiar cachés antiguos si actualizas la versión (v3, v4...)
self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(cacheNames => {
      return Promise.all(
        cacheNames.filter(name => name !== CACHE_NAME)
          .map(name => caches.delete(name))
      );
    })
  );
  self.clients.claim();
});

// Estrategia: "Network First, falling back to cache"
self.addEventListener('fetch', event => {
  // Solo interceptar peticiones GET (no tocamos los envíos de formularios POST)
  if (event.request.method !== 'GET') return;

  event.respondWith(
    fetch(event.request)
      .then(response => {
        // Si hay internet, guardamos una copia fresca en el caché en secreto
        const responseClone = response.clone();
        caches.open(CACHE_NAME).then(cache => {
          cache.put(event.request, responseClone);
        });
        return response;
      })
      .catch(() => {
        // Si falla la red (Modo Avión / Sin Señal), mostramos lo que tengamos guardado
        return caches.match(event.request);
      })
  );
});