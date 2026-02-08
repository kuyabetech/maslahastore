self.addEventListener('install', event => {
    event.waitUntil(
        caches.open('maslaha-v1').then(cache => {
            return cache.addAll([
                '/',
                '/assets/css/style.css',
                '/assets/js/main.js'
                // add more static files later
            ]);
        })
    );
});

self.addEventListener('fetch', event => {
    event.respondWith(
        caches.match(event.request).then(response => {
            return response || fetch(event.request);
        })
    );
});