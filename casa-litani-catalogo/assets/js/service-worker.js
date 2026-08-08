/**
 * Caché básica para que el catálogo cargue rápido con señal débil (4G/3G):
 * cache-first para CSS/JS/imágenes propias del plugin, network-first para las
 * páginas HTML (para no mostrar contenido viejo si el catálogo cambió).
 */
const CLC_CACHE = 'clc-catalogo-v1';

self.addEventListener('install', function (event) {
  self.skipWaiting();
});

self.addEventListener('activate', function (event) {
  event.waitUntil(
    caches.keys().then(function (nombres) {
      return Promise.all(
        nombres.filter(function (n) { return n !== CLC_CACHE; }).map(function (n) { return caches.delete(n); })
      );
    })
  );
  self.clients.claim();
});

self.addEventListener('fetch', function (event) {
  const url = new URL(event.request.url);
  if (event.request.method !== 'GET' || url.origin !== self.location.origin) {
    return;
  }

  const esEstatico = /\.(css|js|jpg|jpeg|png|webp|gif|svg|woff2?)$/.test(url.pathname);

  if (esEstatico) {
    event.respondWith(
      caches.open(CLC_CACHE).then(function (cache) {
        return cache.match(event.request).then(function (respuestaCache) {
          const fetchPromise = fetch(event.request).then(function (respuestaRed) {
            cache.put(event.request, respuestaRed.clone());
            return respuestaRed;
          }).catch(function () { return respuestaCache; });
          return respuestaCache || fetchPromise;
        });
      })
    );
    return;
  }

  // HTML (fichas/categorías del catálogo): siempre intenta la red primero, cae al
  // caché solo si no hay conexión.
  if (event.request.headers.get('accept') && event.request.headers.get('accept').indexOf('text/html') !== -1) {
    event.respondWith(
      fetch(event.request).then(function (respuestaRed) {
        caches.open(CLC_CACHE).then(function (cache) { cache.put(event.request, respuestaRed.clone()); });
        return respuestaRed;
      }).catch(function () {
        return caches.match(event.request);
      })
    );
  }
});
