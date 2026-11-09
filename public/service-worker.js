/* Network-only application: never cache authenticated documents or responses. */
"use strict";

function unavailableResponse() {
  return new Response(
    '<!doctype html><html lang="en"><meta name="viewport" content="width=device-width,initial-scale=1">' +
      '<title>Office network unavailable · PK DTS</title><body><h1>Office network unavailable</h1>' +
      '<p>Reconnect to the office LAN or check that the PK DTS server is running, then refresh.</p></body></html>',
    {
      status: 503,
      headers: {
        "Content-Type": "text/html; charset=utf-8",
        "Cache-Control": "no-store",
      },
    },
  );
}

self.addEventListener("install", function (event) {
  event.waitUntil(self.skipWaiting());
});
self.addEventListener("activate", function (event) {
  event.waitUntil(self.clients.claim());
});
self.addEventListener("fetch", function (event) {
  var request = event.request;
  // The browser serves static assets normally, including its HTTP cache.
  // Lack of public internet does not mean the office LAN is unavailable.
  // Never abort an otherwise successful slow office server response.
  if (
    request.method !== "GET" ||
    request.mode !== "navigate" ||
    !request.url.startsWith(self.registration.scope)
  ) return;
  event.respondWith(fetch(request).catch(unavailableResponse));
});
