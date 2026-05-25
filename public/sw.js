const CACHE_VERSION = 'fann-v2';
const STATIC_CACHE = `${CACHE_VERSION}-static`;
const DYNAMIC_CACHE = `${CACHE_VERSION}-dynamic`;
const API_CACHE = `${CACHE_VERSION}-api`;

const STATIC_ASSETS = [
    '/site.webmanifest',
    '/favicon.svg',
    '/favicon.ico',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(STATIC_CACHE).then((cache) =>
            Promise.allSettled(
                STATIC_ASSETS.map((url) =>
                    fetch(url).then((res) => {
                        if (res.ok) cache.put(url, res);
                    })
                )
            )
        )
    );
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) =>
            Promise.all(
                keys
                    .filter((key) => key.startsWith('fann-') && key !== STATIC_CACHE && key !== DYNAMIC_CACHE && key !== API_CACHE)
                    .map((key) => caches.delete(key))
            )
        )
    );
    self.clients.claim();
});

self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);

    if (request.method !== 'GET') return;
    if (url.protocol !== 'https:' && url.protocol !== 'http:') return;

    // API requests: stale-while-revalidate (for courier order list)
    if (url.pathname.startsWith('/api/v1/currier/orders')) {
        event.respondWith(staleWhileRevalidate(request, API_CACHE));
        return;
    }

    // Static assets (images, fonts, icons): cache-first
    if (isStaticAsset(url.pathname)) {
        event.respondWith(cacheFirst(request, STATIC_CACHE));
        return;
    }

    // App shell (JS/CSS bundles): network-first with cache fallback
    if (url.pathname.startsWith('/build/')) {
        event.respondWith(cacheFirst(request, DYNAMIC_CACHE));
        return;
    }
});

async function cacheFirst(request, cacheName) {
    const cached = await caches.match(request);
    if (cached) return cached;

    try {
        const response = await fetch(request);
        if (response.ok) {
            const cache = await caches.open(cacheName);
            cache.put(request, response.clone());
        }
        return response;
    } catch {
        return new Response('Offline', { status: 503 });
    }
}

async function staleWhileRevalidate(request, cacheName) {
    const cache = await caches.open(cacheName);
    const cached = await cache.match(request);

    const fetchPromise = fetch(request).then((response) => {
        if (response.ok) {
            cache.put(request, response.clone());
        }
        return response;
    }).catch(() => cached);

    return cached || fetchPromise;
}

function isStaticAsset(pathname) {
    return /\.(png|jpg|jpeg|webp|svg|gif|ico|woff2?|ttf|eot)$/i.test(pathname);
}
