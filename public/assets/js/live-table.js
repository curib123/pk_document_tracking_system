/* In-place GETs reuse server-rendered, permission-scoped tables and folders. */
(function () {
  "use strict";
  var host = document.querySelector("[data-live-table]");
  if (!host || !window.fetch || !window.AbortController) return;
  var form = host.querySelector("#tableFilter");
  var status = host.querySelector("[data-table-status]");
  if (!form || !status) return;
  host.setAttribute("data-live-ready", "yes");
  var timer,
    controller,
    version = 0,
    composing = false,
    pendingUrl;

  function cancel() {
    window.clearTimeout(timer);
    version++;
    if (controller) controller.abort();
    host.removeAttribute("aria-busy");
    message("");
  }
  function formUrl() {
    var url = new URL(form.action, window.location.href);
    url.search = new URLSearchParams(new FormData(form)).toString();
    return url;
  }
  function message(text, retry) {
    status.replaceChildren(document.createTextNode(text));
    status.hidden = !text;
    status.classList.toggle("is-error", !!retry);
    if (retry) {
      var button = document.createElement("button");
      button.type = "button";
      button.className = "btn btn-light btn-sm";
      button.textContent = "Retry";
      button.addEventListener("click", function () {
        update(pendingUrl, "replace");
      });
      status.append(button);
    }
  }
  async function update(url, historyMode) {
    cancel();
    var ticket = version;
    var request = new AbortController();
    controller = request;
    pendingUrl = url;
    host.setAttribute("aria-busy", "true");
    message("Updating records…");
    var timeout = window.setTimeout(function () {
      request.abort();
    }, 15000);
    try {
      var response = await fetch(url.href, {
        credentials: "same-origin",
        cache: "no-store",
        signal: request.signal,
        headers: { "X-PK-Table": "1" },
      });
      if (ticket !== version) return;
      if (
        response.redirected &&
        new URL(response.url).origin === window.location.origin
      ) {
        window.location.assign(response.url);
        return;
      }
      if (!response.ok || response.headers.get("X-PK-Table") !== "1")
        throw new Error("Unable to update records. Please try again.");
      var html = await response.text();
      if (ticket !== version) return;
      var fragment = new DOMParser().parseFromString(html, "text/html");
      var next = fragment.querySelector("[data-live-table]");
      var results = next && next.querySelector("[data-table-results]");
      if (!results)
        throw new Error(
          "Unable to read the updated records. Please try again.",
        );
      var active = document.activeElement;
      var focusName =
        active && active.closest("[data-table-results]") ? active.name : "";
      host.querySelector("[data-table-results]").replaceWith(results);
      // Keep the search input (including caret/IME), date controls and forms alive.
      Array.from(form.elements).forEach(function (field) {
        var incoming = next.querySelector('[name="' + field.name + '"]');
        if (incoming && (field !== active || historyMode === "none"))
          field.value = incoming.value;
      });
      var clear = host.querySelector("[data-table-clear]");
      var nextClear = next.querySelector("[data-table-clear]");
      if (clear && nextClear) {
        clear.hidden = nextClear.hidden;
        clear.href = nextClear.href;
      }
      if (focusName)
        Array.from(form.elements)
          .find(function (field) {
            return field.name === focusName;
          })
          ?.focus();
      if (historyMode !== "none") {
        var historyMethod =
          historyMode === "replace" ? "replaceState" : "pushState";
        window.history[historyMethod]({ pkTable: true }, "", url.href);
      }
      // Layout switch links must carry the current search and filters.
      document
        .querySelectorAll(".document-layout-choice")
        .forEach(function (link) {
          var destination = new URL(url.href);
          destination.searchParams.set(
            "layout",
            new URL(link.href).searchParams.get("layout"),
          );
          destination.searchParams.set("page", "1");
          link.href = destination.href;
        });
      message("");
      document.dispatchEvent(new Event("pk:table-updated"));
    } catch (error) {
      if (ticket !== version) return;
      message(
        error.name === "AbortError"
          ? "The update timed out. Please try again."
          : error.message,
        true,
      );
    } finally {
      window.clearTimeout(timeout);
      if (ticket === version) {
        host.removeAttribute("aria-busy");
        controller = null;
      }
    }
  }
  function schedule() {
    cancel();
    if (composing) return;
    timer = window.setTimeout(function () {
      form.requestSubmit();
    }, 250);
  }
  form.querySelector('[name="q"]').addEventListener("input", schedule);
  form
    .querySelector('[name="q"]')
    .addEventListener("compositionstart", function () {
      composing = true;
      cancel();
    });
  form
    .querySelector('[name="q"]')
    .addEventListener("compositionend", function () {
      composing = false;
      schedule();
    });
  document.addEventListener("submit", function (event) {
    if (event.target !== form) return;
    event.preventDefault();
    form.querySelector('[name="page"]').value = "1";
    update(formUrl(), "replace");
  });
  host.addEventListener("change", function (event) {
    if (event.target.matches("[data-auto-submit]")) form.requestSubmit();
    if (
      event.target.matches("[data-date-custom] input") &&
      form.checkValidity() &&
      form.elements.from.value &&
      form.elements.to.value
    )
      form.requestSubmit();
  });
  host.addEventListener("click", function (event) {
    if (
      event.defaultPrevented ||
      event.button !== 0 ||
      event.ctrlKey ||
      event.metaKey ||
      event.shiftKey ||
      event.altKey
    )
      return;
    var link = event.target.closest("a[href]");
    if (!link || link.target || link.hasAttribute("download")) return;
    var url = new URL(link.href, window.location.href);
    if (
      url.origin !== window.location.origin ||
      url.pathname !== new URL(form.action).pathname
    )
      return;
    event.preventDefault();
    update(url, "push");
  });
  window.addEventListener("popstate", function () {
    update(new URL(window.location.href), "none");
  });
  window.addEventListener("pagehide", cancel);
})();
