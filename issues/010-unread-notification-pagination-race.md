# ISSUE 010 — Rapid Next/Previous changes can show the wrong notification page

**Severity:** Medium (UI correctness on slow networks)  
**Type:** Confirmed async state race from code  
**Status:** Proposed fix only; NOT applied

## Evidence
In public/assets/js/notification-inbox.js:
- load() immediately returns if loading===true.
- Clicking Next/Previous modifies the global page state, but a pending fetch is still in flight.
- Its response overwrites page with the OLD data.page, showing old items with old pagination. The user's attempted navigation is lost.
- Changing Rows while a fetch is in progress has the same problem.

## Copyable fix
Within load(), replace the current loading early-return and startup with:

~~~js
if (disposed || document.hidden) return;
if (loading) {
    pendingReload = true;
    return;
}
loading = true;
const requestedPage = page;
const requestedLimit = limitSelect.value;
~~~

Declare a new variable with the existing state variables:

~~~js
var pendingReload = false;
~~~

Construct the URL using the captured values:

~~~js
url.searchParams.set('page', String(requestedPage));
url.searchParams.set('limit', requestedLimit);
~~~

Before applying response data to global page/total, check:

~~~js
if (requestedPage !== page || requestedLimit !== limitSelect.value) {
    pendingReload = true;
    return;
}
~~~

Finally replace finally {loading=false;} with:

~~~js
finally {
    loading = false;
    if (pendingReload && !disposed) {
        pendingReload = false;
        load();
    }
}
~~~

Do not remove the existing same-origin URL checks or explicit POST mark-read behavior.

## Verify
Throttle the browser network, open unread notifications, immediately click Next before page 1 finishes, change page size during loading, and check the final page/label/content match the latest user selection. Repeat with no notifications, session expiry and clicking the bell while a request is in flight.
