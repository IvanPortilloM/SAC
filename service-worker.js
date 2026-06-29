// service-worker.js  —  Portal SAC ADIGGM
// Estrategia:
//   · SHELL (HTML/JS/CSS):  network-first  -> tus cambios se ven SIEMPRE que haya red.
//   · IMÁGENES:             cache-first    -> carga instantánea en visitas repetidas.
//   · /api/ (financiero):   nunca se cachea -> los saldos siempre son reales.
// La versión nueva NO se activa sola: avisa al usuario con un aviso "Actualizar"
// (ver js/sw-register.js) para no interrumpir un formulario a medio llenar.

const APP_VERSION = 'v4-2026-06-03';            // súbela al desplegar cambios grandes
const SHELL_CACHE = `sac-shell-${APP_VERSION}`;
const IMG_CACHE   = `sac-img-${APP_VERSION}`;

// Mínimo para que la app abra sin conexión.
const SHELL_ASSETS = [
  './login.html',
  './manifest.json',
  './css/styles.css',
  './LOGO ADI-GGM.png',
  './assets/img/icon-192.png',
  './assets/img/icon-512.png'
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(SHELL_CACHE).then((cache) =>
      // addAll falla si UN archivo no está; lo hacemos tolerante.
      Promise.allSettled(SHELL_ASSETS.map((url) => cache.add(url)))
    )
  );
  // OJO: no llamamos skipWaiting aquí. Esperamos la confirmación del usuario.
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(
        keys.filter((k) => k !== SHELL_CACHE && k !== IMG_CACHE)
            .map((k) => caches.delete(k))
      ))
      .then(() => self.clients.claim())
  );
});

// El cliente confirma "Actualizar" -> activamos la versión nueva de inmediato.
self.addEventListener('message', (event) => {
  if (event.data && event.data.type === 'SKIP_WAITING') {
    self.skipWaiting();
  }
});

const isImage = (url) => /\.(png|jpe?g|gif|webp|avif|svg|ico)$/i.test(url.pathname);

self.addEventListener('fetch', (event) => {
  const req = event.request;
  if (req.method !== 'GET') return;                    // no tocar POST (formularios)

  const url = new URL(req.url);
  if (url.origin !== self.location.origin) return;     // dejar pasar CDNs/externos
  if (url.pathname.includes('/api/')) return;          // datos financieros: siempre frescos

  // IMÁGENES -> cache-first
  if (isImage(url)) {
    event.respondWith(
      caches.match(req).then((cached) =>
        cached || fetch(req).then((res) => {
          if (res && res.status === 200) {
            const copy = res.clone();
            caches.open(IMG_CACHE).then((c) => c.put(req, copy));
          }
          return res;
        }).catch(() => cached)
      )
    );
    return;
  }

  // SHELL (HTML/JS/CSS) -> network-first, con respaldo del caché si no hay red.
  event.respondWith(
    fetch(req).then((res) => {
      if (res && res.status === 200) {
        const copy = res.clone();
        caches.open(SHELL_CACHE).then((c) => c.put(req, copy));
      }
      return res;
    }).catch(() =>
      caches.match(req).then((cached) => cached || caches.match('./login.html'))
    )
  );
});
