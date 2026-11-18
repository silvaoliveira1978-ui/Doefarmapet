'use strict';

const CACHE_NAME = 'adote-certo-static-v2';
const STATIC_FILES = [
    './style.css',
    './app.js',
    './manifest.json',
    './offline.html',
    './assets/pet-icon.svg'
];

self.addEventListener('install', function (event) {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(function (cache) {
                return cache.addAll(STATIC_FILES);
            })
            .then(function () {
                return self.skipWaiting();
            })
    );
});

self.addEventListener('activate', function (event) {
    event.waitUntil(
        caches.keys()
            .then(function (keys) {
                return Promise.all(keys.map(function (key) {
                    if (key !== CACHE_NAME && key.indexOf('adote-certo-') === 0) {
                        return caches.delete(key);
                    }
                    return Promise.resolve();
                }));
            })
            .then(function () {
                return self.clients.claim();
            })
    );
});

self.addEventListener('fetch', function (event) {
    const request = event.request;
    const url = new URL(request.url);

    if (request.method !== 'GET' || url.origin !== self.location.origin) {
        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request).catch(function () {
                return caches.match('./offline.html');
            })
        );
        return;
    }

    if (STATIC_FILES.some(function (file) {
        return new URL(file, self.location.href).pathname === url.pathname;
    })) {
        event.respondWith(
            caches.match(request).then(function (cached) {
                const fresh = fetch(request).then(function (response) {
                    if (response.ok) {
                        const copy = response.clone();
                        caches.open(CACHE_NAME).then(function (cache) {
                            cache.put(request, copy);
                        });
                    }
                    return response;
                });
                return cached || fresh;
            })
        );
    }
});
