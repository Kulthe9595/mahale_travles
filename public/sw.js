// Caches only the built app shell (JS/CSS) and icons. Pages, bills and
// data always come from the server so nothing stale is ever shown.
const CACHE = 'mahale-shell-v1';
const CACHEABLE = ['/build/', '/icons/'];

self.addEventListener('install', () => self.skipWaiting());

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k))))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const url = new URL(event.request.url);
    const cacheable = event.request.method === 'GET'
        && url.origin === self.location.origin
        && CACHEABLE.some((prefix) => url.pathname.startsWith(prefix));

    if (!cacheable) return; // network only

    event.respondWith(
        caches.open(CACHE).then(async (cache) => {
            const hit = await cache.match(event.request);
            if (hit) return hit;
            const res = await fetch(event.request);
            if (res.ok) cache.put(event.request, res.clone());
            return res;
        }),
    );
});
