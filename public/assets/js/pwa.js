(function () {
    'use strict';
    var manifest = document.querySelector('link[rel="manifest"]');
    if (!manifest) return;
    var base = new URL('./', manifest.href);
    var storageKey = 'pk-dts:pwa-installed:' + base.pathname;
    var button = document.getElementById('pwaInstallButton');
    var display = window.matchMedia('(display-mode: standalone)');
    var pending = null;
    var installEpoch = 0;
    var installed = display.matches || navigator.standalone === true;
    try { installed = installed || window.localStorage.getItem(storageKey) === '1'; } catch (error) {}

    function sync() { if (button) button.hidden = installed || !pending; }
    function remember() {
        installEpoch++;
        installed = true;
        pending = null;
        try { window.localStorage.setItem(storageKey, '1'); } catch (error) {}
        sync();
    }
    async function relatedInstalled() {
        if (!navigator.getInstalledRelatedApps) return false;
        try {
            var apps = await navigator.getInstalledRelatedApps();
            return apps.some(function (app) {
                return app.platform === 'webapp' &&
                    ((app.url && new URL(app.url, base).href === manifest.href) ||
                        (app.id && new URL(app.id, base.origin).href === new URL('pk-document-control', base).href));
            });
        } catch (error) { return false; }
    }
    window.addEventListener('beforeinstallprompt', async function (event) {
        event.preventDefault();
        var epoch = installEpoch;
        var related = await relatedInstalled();
        if (epoch !== installEpoch) return;
        if (display.matches || navigator.standalone === true || related) { remember(); return; }
        // A genuine eligibility event also confirms a previous installation was removed.
        installed = false;
        try { window.localStorage.removeItem(storageKey); } catch (error) {}
        pending = event;
        sync();
    });
    window.addEventListener('appinstalled', remember);
    if (display.addEventListener) display.addEventListener('change', function () { if (display.matches) remember(); });
    window.addEventListener('storage', function (event) {
        if (event.key === storageKey && event.newValue === '1') remember();
    });
    if (button) button.addEventListener('click', async function () {
        if (!pending || installed) return;
        var prompt = pending;
        pending = null;
        button.disabled = true;
        sync();
        try {
            var choice = await prompt.prompt();
            choice = choice || await prompt.userChoice;
            if (choice && choice.outcome === 'accepted') remember();
        } catch (error) { /* The consumed prompt waits for the browser's next eligibility event. */ }
        finally { button.disabled = false; sync(); }
    });
    if (display.matches || navigator.standalone === true) remember();
    sync();

    if (window.isSecureContext && navigator.serviceWorker) {
        var registration;
        navigator.serviceWorker.register(new URL('service-worker.js', base).href,
            {scope: base.pathname, updateViaCache: 'none'}).then(function (value) {
                registration = value;
                return registration.update();
            }).catch(function () { /* The normal website remains available if registration is unavailable. */ });
        function update() { if (registration) registration.update().catch(function () {}); }
        window.addEventListener('online', update);
        document.addEventListener('visibilitychange', function () { if (document.visibilityState === 'visible') update(); });
    }
})();
