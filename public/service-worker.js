/* Install once. All application responses stay live; no documents are cached. */
'use strict';
self.addEventListener('install', function (event) { event.waitUntil(self.skipWaiting()); });
self.addEventListener('activate', function (event) { event.waitUntil(self.clients.claim()); });
self.addEventListener('fetch', function (event) {
    if (event.request.method !== 'GET' || !event.request.url.startsWith(self.registration.scope)) return;
    event.respondWith(fetch(new Request(event.request, {cache: 'no-store'})).catch(function () {
        if (event.request.mode === 'navigate') {
            return new Response('<!doctype html><html lang="en"><meta name="viewport" content="width=device-width,initial-scale=1">' +
                '<title>Connection unavailable · PK DTS</title><body><h1>Connection unavailable</h1>' +
                '<p>Reconnect to your network, then refresh to use PK Document Control.</p></body></html>',
                {status: 503, headers: {'Content-Type': 'text/html; charset=utf-8', 'Cache-Control': 'no-store'}});
        }
        return new Response('Connection unavailable.', {status: 503, headers: {'Cache-Control': 'no-store'}});
    }));
});
