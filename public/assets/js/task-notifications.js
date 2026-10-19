/* Durable once-only approval alerts. The server owns eligibility and shown state. */
(function () {
  "use strict";
  var modal = document.getElementById("taskNotificationModal");
  if (!modal || !window.fetch || !window.bootstrap) return;
  var list = document.getElementById("taskNotificationList");
  var queue = [],
    readyTasks = [],
    seen = new Set(),
    pollTimer,
    retryTimer,
    loading = false,
    claiming = false,
    disposed = false,
    stopped = false;
  var controllers = new Set();

  function cookieToken() {
    var prefix = modal.dataset.csrfCookie + "=";
    var cookie = document.cookie
      .split(";")
      .map(function (value) {
        return value.trim();
      })
      .find(function (value) {
        return value.startsWith(prefix);
      });
    return cookie
      ? decodeURIComponent(cookie.slice(prefix.length))
      : modal.dataset.csrfValue;
  }
  function syncCSRF(csrf) {
    if (csrf) {
      modal.dataset.csrfName = csrf.name;
      modal.dataset.csrfValue = csrf.value;
    }
    var token = cookieToken();
    document.querySelectorAll('input[type="hidden"]').forEach(function (field) {
      if (field.name === modal.dataset.csrfName) field.value = token;
    });
  }
  async function request(url, options) {
    var controller = new AbortController();
    controllers.add(controller);
    try {
      var response = await window.fetch(
        url,
        Object.assign(
          {
            credentials: "same-origin",
            cache: "no-store",
            signal: controller.signal,
          },
          options,
        ),
      );
      if (response.status === 401) {
        stopped = true;
        queue = [];
        return null;
      }
      if (!response.ok) throw new Error("Notification unavailable");
      var data = await response.json();
      syncCSRF(data.csrf);
      return Array.isArray(data.tasks) ? data.tasks : [];
    } finally {
      controllers.delete(controller);
    }
  }
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
  function render(tasks) {
    list.replaceChildren();
    var taskPrefix = new URL(
      modal.dataset.pendingUrl,
      window.location.origin,
    ).pathname.replace(/notifications\/tasks$/, "my-tasks/");
    tasks.forEach(function (task) {
      var item = document.createElement("li");
      item.className = "task-notification-item";
      var url;
      try {
        url = new URL(task.url, window.location.origin);
      } catch (_) {}
      var safe =
        url &&
        url.origin === window.location.origin &&
        url.pathname.startsWith(taskPrefix);
      var body = document.createElement(safe ? "a" : "div");
      body.className = "task-notification-link";
      if (safe) body.href = url.href;
      var reference = document.createElement("strong");
      reference.textContent = (task.request_reference ? task.request_reference + ' · ' : '') + (task.title || 'Approval task');
      var label = document.createElement("span");
      label.textContent = task.label;
      body.append(reference, label);
      item.append(body);
      list.append(item);
    });
  }
  async function present() {
    window.clearTimeout(retryTimer);
    if (
      disposed ||
      stopped ||
      claiming ||
      (!queue.length && !readyTasks.length)
    )
      return;
    if (busy()) {
      if (!document.hidden) retryTimer = window.setTimeout(present, 750);
      return;
    }
    if (readyTasks.length) {
      var ready = readyTasks;
      readyTasks = [];
      ready.forEach(function (task) {
        seen.add(task.key);
      });
      render(ready);
      window.bootstrap.Modal.getOrCreateInstance(modal).show();
      return;
    }
    claiming = true;
    var keys = queue.map(function (task) {
      return task.key;
    });
    var body = new URLSearchParams();
    keys.forEach(function (key) {
      body.append("keys[]", key);
    });
    body.set(modal.dataset.csrfName, cookieToken());
    try {
      var tasks = await request(modal.dataset.claimUrl, {
        method: "POST",
        body: body,
      });
      if (disposed || stopped || !tasks) return;
      queue = queue.filter(function (task) {
        return !keys.includes(task.key);
      });
      // Retain claimed items if another modal opened during the request.
      readyTasks = tasks.filter(function (task) {
        return !seen.has(task.key);
      });
    } catch (_) {
      // A later poll retries without discarding unclaimed tasks.
    } finally {
      claiming = false;
      if (readyTasks.length) present();
    }
  }
  async function poll() {
    window.clearTimeout(pollTimer);
    if (disposed || stopped || loading || document.hidden) return;
    loading = true;
    try {
      var tasks = await request(modal.dataset.pendingUrl);
      if (tasks && !disposed && !stopped) {
        queue = tasks.filter(function (task) {
          return !seen.has(task.key);
        });
        await present();
      }
    } catch (_) {
    } finally {
      loading = false;
      if (!disposed && !stopped) pollTimer = window.setTimeout(poll, 15000);
    }
  }
  document.addEventListener(
    "submit",
    function () {
      syncCSRF();
    },
    true,
  );
  document.addEventListener("hidden.bs.modal", function () {
    retryTimer = window.setTimeout(present, 0);
  });
  modal.addEventListener("hidden.bs.modal", function () {
    retryTimer = window.setTimeout(present, 0);
  });
  document.addEventListener("visibilitychange", function () {
    if (!document.hidden) {
      poll();
      present();
    }
  });
  window.addEventListener("pagehide", function () {
    disposed = true;
    window.clearTimeout(pollTimer);
    window.clearTimeout(retryTimer);
    controllers.forEach(function (controller) {
      controller.abort();
    });
    window.bootstrap.Modal.getOrCreateInstance(modal).hide();
  });
  window.addEventListener("pageshow", function (event) {
    if (event.persisted) {
      disposed = false;
      queue = [];
      poll();
    }
  });
  poll();
})();
