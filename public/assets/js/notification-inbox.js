/* Unread-only task inbox. No automatic popups or implicit read acknowledgments. */
(function () {
    'use strict';
    var root = document.getElementById('notificationBellPanel');
    if (!root) return;
    var button = document.getElementById('notificationBellButton');
    var panel = document.getElementById('notificationBellMenu');
    var list = document.getElementById('notificationInboxItems');
    var badge = document.getElementById('notificationBellCount');
    var indicator = document.getElementById('notificationInboxPage');
    var limitSelect = document.getElementById('notificationInboxLimit');
    var previous = panel.querySelector('[data-notification-previous]');
    var next = panel.querySelector('[data-notification-next]');
    var page = 1, pages = 1, total = 0, loading = false, disposed = false;
    var refreshTimer = null;
    function csrf() {
        var value = '', key = encodeURIComponent(root.dataset.notificationCookie) + '=';
        document.cookie.split(';').forEach(function (part) {
            var trimmed = part.trim();
            if (trimmed.indexOf(key) === 0) value = decodeURIComponent(trimmed.substring(key.length));
        });
        return value || document.querySelector('input[name="' + root.dataset.notificationCsrf + '"]')?.value || '';
    }
    function close() { panel.hidden = true; button.setAttribute('aria-expanded', 'false'); }
    function updateCount(count) {
        total = Math.max(0, Number(count) || 0);
        badge.hidden = total === 0;
        badge.textContent = total > 99 ? '99+' : String(total);
        button.setAttribute('aria-label', total + ' unread notifications');
    }
    function safeUrl(value) {
        try {
            var url = new URL(value, window.location.origin);
            var prefix = new URL(root.dataset.notificationList, window.location.href)
                .pathname.replace(/notifications\/inbox$/, 'my-tasks/');
            return url.origin === window.location.origin && url.pathname.startsWith(prefix) ? url.href : null;
        } catch (_) { return null; }
    }
    async function markRead(key, link) {
        var form = new URLSearchParams();
        form.set('key', key); form.set(root.dataset.notificationCsrf, csrf());
        try {
            var result = await window.fetch(root.dataset.notificationRead, {
                method: 'POST', body: form, credentials: 'same-origin', cache: 'no-store',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            if (!result.ok) return;
            var data = await result.json();
            if (!data.ok) return;
            window.location.assign(link);
        } catch (_) { }
    }
    function render(items) {
        list.replaceChildren();
        items.forEach(function (item) {
            var link = safeUrl(item.url);
            if (!link || !/^[1-9][0-9]*:[1-9][0-9]*$/.test(item.key)) return;
            var li = document.createElement('li'), btn = document.createElement('button');
            btn.type = 'button'; btn.className = 'notification-inbox-item';
            var title = document.createElement('strong'), subtitle = document.createElement('span');
            title.textContent = item.reference; subtitle.textContent = item.label;
            btn.append(title, subtitle);
            btn.addEventListener('click', function () { markRead(item.key, link); });
            li.append(btn); list.append(li);
        });
        if (!list.children.length) {
            var empty = document.createElement('li');
            empty.className = 'notification-inbox-empty';
            empty.textContent = 'No unread notifications';
            list.append(empty);
        }
        indicator.textContent = page + ' / ' + pages;
        previous.disabled = page <= 1; next.disabled = page >= pages;
    }
    async function load() {
        if (disposed || loading || document.hidden) return;
        loading = true;
        try {
            var url = new URL(root.dataset.notificationList, window.location.origin);
            url.searchParams.set('page', String(page));
            url.searchParams.set('limit', limitSelect.value);
            var response = await window.fetch(url.href, { credentials: 'same-origin', cache: 'no-store' });
            if (!response.ok) return;
            var data = await response.json();
            page = Number(data.page) || 1; pages = Number(data.pages) || 1;
            updateCount(data.total);
            if (!panel.hidden) render(Array.isArray(data.items) ? data.items : []);
        } catch (_) { } finally { loading = false; }
    }
    button.addEventListener('click', function () {
        var open = panel.hidden; panel.hidden = !open;
        button.setAttribute('aria-expanded', String(open));
        if (open) { page = 1; load(); }
    });
    panel.querySelector('[data-notification-close]').addEventListener('click', close);
    document.addEventListener('click', function (event) {
        if (!root.contains(event.target)) close();
    });
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && !panel.hidden) { close(); button.focus(); }
    });
    previous.addEventListener('click', function () { if (page > 1) { page--; load(); } });
    next.addEventListener('click', function () { if (page < pages) { page++; load(); } });
    limitSelect.addEventListener('change', function () { page = 1; load(); });
    document.addEventListener('visibilitychange', function () { if (!document.hidden) load(); });
    function poll() { if (disposed) return; load(); refreshTimer = window.setTimeout(poll, 30000); }
    window.addEventListener('pagehide', function () { disposed = true; window.clearTimeout(refreshTimer); });
    poll();
})();
