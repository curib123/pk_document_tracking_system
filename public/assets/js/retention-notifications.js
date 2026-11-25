(function () {
  "use strict";
  var modal = document.getElementById("retentionNotificationModal");
  if (!modal || !window.fetch || !window.bootstrap) return;
  var list = document.getElementById("retentionNotificationList");
  var storageKey = "pk-retention-alerts:" + modal.dataset.userId;
  var seen = new Set();
  var pending = [];
  var timer, retryTimer;
  var loading = false;
  var disposed = false;
  var stopped = false;
  var audioContext;
  var soundEnabled = false;
  try { soundEnabled = localStorage.getItem('pk-scan-alarm-sound') === 'yes'; } catch (_) {}
  function enableAudio() {
    var Audio = window.AudioContext || window.webkitAudioContext;
    if (!Audio) return;
    audioContext = audioContext || new Audio();
    if (audioContext.state === 'suspended') audioContext.resume().catch(function () {});
  }
  function sound() {
    if (!soundEnabled || !audioContext || audioContext.state !== 'running') return;
    [0, 0.35, 0.7].forEach(function (delay) {
      var oscillator = audioContext.createOscillator();
      var gain = audioContext.createGain();
      oscillator.frequency.value = 880;
      gain.gain.value = 0.08;
      oscillator.connect(gain); gain.connect(audioContext.destination);
      oscillator.start(audioContext.currentTime + delay);
      oscillator.stop(audioContext.currentTime + delay + 0.2);
    });
  }
  document.addEventListener('click', function (event) {
    if (event.target.closest('[data-scan-alarm-enable]')) {
      soundEnabled = true;
      try { localStorage.setItem('pk-scan-alarm-sound', 'yes'); } catch (_) {}
      enableAudio();
      event.target.textContent = 'Reminder sound enabled';
      setTimeout(sound, 100);
    } else if (soundEnabled) enableAudio();
  });
  function alreadySeen(item) {
    if (seen.has(item.key)) return true;
    if (item.daily) {
      try { return localStorage.getItem(storageKey + ':' + item.key) === 'seen'; } catch (_) {}
    }
    return false;
  }

  try {
    var stored = JSON.parse(window.sessionStorage.getItem(storageKey) || "[]");
    if (Array.isArray(stored)) stored.forEach(function (key) {
      if (typeof key === "string") seen.add(key);
    });
  } catch (_) {}

  function busy() {
    var root = document.documentElement;
    return (
      document.hidden ||
      document.body.inert ||
      root.hasAttribute("data-login-transition") ||
      root.hasAttribute("data-logout-active") ||
      root.hasAttribute("data-auth-history-gate") ||
      document.querySelector(".modal.show, .modal.showing, .modal.hiding") ||
      document.querySelector('form[aria-busy="true"]') ||
      document.activeElement?.matches(
        'input,textarea,select,[contenteditable="true"]',
      )
    );
  }

  function render(items) {
    list.replaceChildren();
    var registerPath = new URL(
      modal.dataset.listUrl,
      window.location.origin,
    ).pathname.replace(/notifications\/retention$/, "documents/");
    items.forEach(function (item) {
      var row = document.createElement("li");
      row.className = "task-notification-item";
      var url;
      try {
        url = new URL(item.url, window.location.origin);
      } catch (_) {}
      var safe =
        url &&
        url.origin === window.location.origin &&
        [registerPath + 'hardcopy', registerPath + 'scanned'].includes(url.pathname);
      var body = document.createElement(safe ? "a" : "div");
      body.className = "task-notification-link";
      if (safe) body.href = url.href;
      var reference = document.createElement("strong");
      reference.textContent = item.reference
        ? item.reference + " · " + (item.title || "Hardcopy record")
        : item.title || "Hardcopy record";
      var label = document.createElement("span");
      label.textContent = item.label || "Retention date reached";
      body.append(reference, label);
      row.append(body);
      list.append(row);
    });
  }

  function present() {
    window.clearTimeout(retryTimer);
    if (disposed || stopped || !pending.length || modal.classList.contains("show"))
      return;
    if (busy()) {
      if (!document.hidden) retryTimer = window.setTimeout(present, 750);
      return;
    }
    var showing = pending;
    pending = [];
    showing.forEach(function (item) {
      seen.add(item.key);
      if (item.daily) {
        try { localStorage.setItem(storageKey + ':' + item.key, 'seen'); } catch (_) {}
      }
    });
    try {
      window.sessionStorage.setItem(storageKey, JSON.stringify(Array.from(seen)));
    } catch (_) {}
    render(showing);
    window.bootstrap.Modal.getOrCreateInstance(modal).show();
    if (showing.some(function (item) { return item.alarm; })) sound();
  }

  async function poll() {
    window.clearTimeout(timer);
    if (disposed || stopped || loading || document.hidden) return;
    loading = true;
    try {
      var response = await window.fetch(modal.dataset.listUrl, {
        credentials: "same-origin",
        cache: "no-store",
      });
      if (response.status === 401) {
        stopped = true;
        pending = [];
        return;
      }
      if (!response.ok) throw new Error("Retention alerts unavailable");
      var data = await response.json();
      if (!disposed && !stopped && Array.isArray(data.items)) {
        pending = data.items.filter(function (item) {
          return item && typeof item.key === "string" && !alreadySeen(item);
        });
        present();
      }
    } catch (error) {
      console.error("Retention alerts could not be loaded.", error);
    } finally {
      loading = false;
      if (!disposed && !stopped) timer = window.setTimeout(poll, 60000);
    }
  }

  document.addEventListener("hidden.bs.modal", present);
  modal.addEventListener("hidden.bs.modal", present);
  document.addEventListener("visibilitychange", function () {
    if (!document.hidden) {
      poll();
      present();
    }
  });
  window.addEventListener("pagehide", function () {
    disposed = true;
    window.clearTimeout(timer);
    window.clearTimeout(retryTimer);
    window.bootstrap.Modal.getOrCreateInstance(modal).hide();
  });
  window.addEventListener("pageshow", function (event) {
    if (event.persisted) {
      disposed = false;
      poll();
    }
  });
  poll();
})();
