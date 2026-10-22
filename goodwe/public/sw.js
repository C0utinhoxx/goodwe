
/*
 * GoodWe PWA - Service Worker
 *
 * Navegacao: prioriza a rede.
 * Arquivos estaticos: utiliza cache e atualiza em segundo plano.
 * Paginas privadas e APIs: nao sao armazenadas no cache.
 *
 * Notificacoes push:
 * Recebe alertas e abre a pagina de demanda do gestor.
 */

const CACHE_VERSION = 'v3';
const CACHE_PREFIX = 'goodwe-pwa-';
const CACHE_NAME = CACHE_PREFIX + CACHE_VERSION;

const OFFLINE_FALLBACK = new URL('./', self.location.href).href;

const PRECACHE_URLS = [
    './',
    './manifest.json',
    './favicon.ico',
    './imagens/goodwe-logo.png',
    './imagens/powertag-logo-branco.png',
    './imagens/powertag-logo-preto.png',
    './icons/icon-192.png',
    './icons/icon-512.png',
    './icons/apple-touch-icon.png',
];

self.addEventListener('install', (event) => {
    event.waitUntil((async () => {
        const cache = await caches.open(CACHE_NAME);

        await Promise.allSettled(
            PRECACHE_URLS.map((url) => {
                const address = new URL(url, self.location.href).href;

                return cache.add(new Request(address, {
                    cache: 'reload',
                }));
            })
        );

        await self.skipWaiting();
    })());
});

self.addEventListener('activate', (event) => {
    event.waitUntil((async () => {
        const keys = await caches.keys();

        await Promise.all(
            keys
                .filter((key) => {
                    return key.startsWith(CACHE_PREFIX)
                        && key !== CACHE_NAME;
                })
                .map((key) => caches.delete(key))
        );

        await self.clients.claim();
    })());
});

self.addEventListener('message', (event) => {
    if (event.data && event.data.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }
});

self.addEventListener('fetch', (event) => {
    const request = event.request;

    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);

    if (url.origin !== self.location.origin) {
        return;
    }

    if (
        url.pathname.endsWith('/sw.js') ||
        url.pathname.endsWith('/manifest.json')
    ) {
        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith(networkFirst(request));
        return;
    }

    if (!isStaticAsset(url)) {
        return;
    }

    event.respondWith(staleWhileRevalidate(request));
});

function isStaticAsset(url) {
    const path = url.pathname.toLowerCase();

    return (
        path.startsWith('/build/') ||
        path.startsWith('/css/') ||
        path.startsWith('/js/') ||
        path.startsWith('/imagens/') ||
        path.startsWith('/icons/') ||
        path.startsWith('/vendor/') ||
        /\.(css|js|png|jpg|jpeg|webp|svg|gif|ico|woff|woff2)$/.test(path)
    );
}

async function networkFirst(request) {
    try {
        const response = await fetch(request);

        const url = new URL(request.url);

        if (
            response &&
            response.ok &&
            response.type === 'basic' &&
            url.pathname === '/' &&
            !url.search
        ) {
            const cache = await caches.open(CACHE_NAME);

            await cache.put(
                OFFLINE_FALLBACK,
                response.clone()
            );
        }

        return response;
    } catch (error) {
        if (new URL(request.url).pathname === '/') {
            const cache = await caches.open(CACHE_NAME);

            const cached = await cache.match(OFFLINE_FALLBACK);

            if (cached) {
                return cached;
            }
        }

        throw error;
    }
}

async function staleWhileRevalidate(request) {
    const cache = await caches.open(CACHE_NAME);

    const cached = await cache.match(request);

    const fromNetwork = fetch(request)
        .then((response) => {
            if (
                response &&
                response.ok &&
                response.type === 'basic'
            ) {
                cache.put(request, response.clone());
            }

            return response;
        })
        .catch(() => null);

    if (cached) {
        return cached;
    }

    const network = await fromNetwork;

    if (network) {
        return network;
    }

    return fetch(request);
}

/*
 * NOTIFICACOES PUSH
 */

self.addEventListener('push', (event) => {
    let data = {};

    if (event.data) {
        try {
            data = event.data.json();
        } catch (error) {
            data = {
                body: event.data.text(),
            };
        }
    }

    const title = typeof data.title === 'string'
        ? data.title
        : 'GoodWe - Alerta de demanda';

    const body = typeof data.body === 'string'
        ? data.body
        : 'Um novo alerta foi registrado no estabelecimento.';

    const options = {
        body: body,
        icon: '/icons/icon-192.png',
        badge: '/icons/icon-192.png',
        tag: typeof data.tag === 'string'
            ? data.tag
            : 'goodwe-demand-alert',
        data: {
            url: getSafeNotificationUrl(data.url),
        },
        requireInteraction: false,
    };

    event.waitUntil(
        self.registration.showNotification(title, options)
    );
});

/*
 * CLIQUE NA NOTIFICACAO
 */

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const targetUrl = getSafeNotificationUrl(
        event.notification.data &&
        event.notification.data.url
    );

    event.waitUntil((async () => {
        const windows = await self.clients.matchAll({
            type: 'window',
            includeUncontrolled: true,
        });

        for (const client of windows) {
            if (client.url === targetUrl && 'focus' in client) {
                return client.focus();
            }
        }

        if (self.clients.openWindow) {
            return self.clients.openWindow(targetUrl);
        }
    })());
});

/*
 * Evita abrir enderecos externos por notificacoes.
 */

function getSafeNotificationUrl(value) {
    const fallback = new URL(
        '/gestor/demanda',
        self.location.origin
    ).href;

    if (
        typeof value !== 'string' ||
        !value.startsWith('/') ||
        value.startsWith('//')
    ) {
        return fallback;
    }

    try {
        const url = new URL(value, self.location.origin);

        if (
            url.origin !== self.location.origin ||
            url.pathname !== '/gestor/demanda'
        ) {
            return fallback;
        }

        return url.href;
    } catch (error) {
        return fallback;
    }
}
