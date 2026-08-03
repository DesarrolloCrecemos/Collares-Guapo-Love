/* =========================================================================
   Service worker del Panel Interno de Personalización.

   Se sirve desde la raíz del sitio para que su alcance cubra /wp-content/,
   donde viven los charms. Ese alcance amplio obliga a ser estrictos: este
   worker SOLO toca la ruta del panel y los assets del plugin. Todo lo demás
   —la tienda, el checkout, wp-admin— pasa de largo sin ser interceptado ni
   cacheado, porque servir una página de pago vieja sería mucho peor que no
   tener modo offline.

   Los marcadores __FERIA_*__ los reemplaza feria-panel.php al servir el archivo.
   ========================================================================= */

// v2: la ruta del panel cambió de /feria a /panel-interno, así que la caché
// vieja apunta a una URL que ya no existe. Al subir el nombre, activate la borra.
var CACHE  = 'collar-feria-v2';
var ASSETS = '__FERIA_ASSETS__';   // ruta de assets/ del plugin
var PANEL  = '__FERIA_PATH__';     // ruta del panel (CollarFeriaPanel::SLUG)

/* ------------------------------------------------------------------------
 * Ciclo de vida
 * --------------------------------------------------------------------- */

self.addEventListener('install', function () {
    self.skipWaiting();
});

self.addEventListener('activate', function (event) {
    event.waitUntil(
        caches.keys().then(function (keys) {
            return Promise.all(keys.map(function (key) {
                return key === CACHE ? null : caches.delete(key);
            }));
        }).then(function () {
            return self.clients.claim();
        })
    );
});

/* ------------------------------------------------------------------------
 * Precarga bajo demanda
 * --------------------------------------------------------------------- */

function report(done, total) {
    return self.clients.matchAll().then(function (clients) {
        clients.forEach(function (client) {
            client.postMessage({ type: 'precache-progress', done: done, total: total });
        });
    });
}

function precache(urls) {
    return caches.open(CACHE).then(function (cache) {
        var total = urls.length;
        var done  = 0;
        var index = 0;

        // De a 6: saturar la conexión de una feria con 250 peticiones simultáneas
        // hace que fallen a la mitad.
        function worker() {
            if (index >= urls.length) return Promise.resolve();

            var url = urls[index++];

            return fetch(url, { cache: 'reload' })
                .then(function (res) {
                    if (res && res.ok) return cache.put(url, res);
                })
                .catch(function () { /* un archivo que falle no aborta el resto */ })
                .then(function () {
                    done++;
                    if (done % 5 === 0 || done === total) report(done, total);
                    return worker();
                });
        }

        var pool = [];
        for (var i = 0; i < 6; i++) pool.push(worker());

        return Promise.all(pool).then(function () { return report(total, total); });
    });
}

self.addEventListener('message', function (event) {
    var msg = event.data || {};
    if (msg.type === 'precache' && Array.isArray(msg.urls)) {
        event.waitUntil(precache(msg.urls));
    }
});

/* ------------------------------------------------------------------------
 * Intercepción — deliberadamente estrecha
 * --------------------------------------------------------------------- */

self.addEventListener('fetch', function (event) {
    var req = event.request;

    if (req.method !== 'GET') return;

    var url;
    try {
        url = new URL(req.url);
    } catch (e) {
        return;
    }

    if (url.origin !== self.location.origin) return;

    // El panel: red primero para tomar cambios, caché como red de seguridad.
    var isPanel = url.pathname === PANEL || url.pathname === PANEL + '/';

    if (req.mode === 'navigate') {
        if (!isPanel) return;   // ninguna otra página del sitio se toca

        event.respondWith(
            fetch(req).then(function (res) {
                if (res && res.ok) {
                    var copy = res.clone();
                    caches.open(CACHE).then(function (c) { c.put(PANEL, copy); });
                }
                return res;
            }).catch(function () {
                // Se prueban las tres claves posibles: la petición tal cual y el
                // panel con y sin barra final, según cómo esté configurado el
                // formato de enlaces permanentes del sitio.
                return caches.match(req)
                    .then(function (hit) { return hit || caches.match(PANEL); })
                    .then(function (hit) { return hit || caches.match(PANEL + '/'); })
                    .then(function (hit) {
                    return hit || new Response(
                        '<h1>Sin conexión</h1><p>Abre el panel una vez con internet para poder usarlo sin señal.</p>',
                        { headers: { 'Content-Type': 'text/html; charset=utf-8' }, status: 503 }
                    );
                });
            })
        );
        return;
    }

    // Assets del configurador: caché primero, son inmutables en la práctica.
    if (url.pathname.indexOf(ASSETS) !== 0) return;

    event.respondWith(
        caches.match(req).then(function (hit) {
            if (hit) return hit;

            return fetch(req).then(function (res) {
                if (res && res.ok) {
                    var copy = res.clone();
                    caches.open(CACHE).then(function (c) { c.put(req, copy); });
                }
                return res;
            });
        })
    );
});
