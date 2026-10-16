/* Progressive presentation around the existing confirmed logout POST. */
(function () {
  "use strict";
  if (window.PKLogoutTransition) return;
  window.PKLogoutTransition = true;
  const root = document.documentElement;
  const key = "pk:logout-transition";
  const reduced = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  const animations = [],
    timers = new Set();
  let disposed = false;
  function later(fn, ms) {
    const id = window.setTimeout(() => {
      timers.delete(id);
      fn();
    }, ms);
    timers.add(id);
    return id;
  }
  const pause = (ms) => new Promise((resolve) => later(resolve, ms));
  function play(node, frames, options) {
    const animation = node.animate(
      frames,
      Object.assign({ fill: "forwards" }, options),
    );
    animation.finished.catch(() => {});
    animations.push(animation);
    return animation;
  }
  function clearMotion() {
    timers.forEach((id) => window.clearTimeout(id));
    timers.clear();
    animations.splice(0).forEach((animation) => animation.cancel());
  }
  const ease = "cubic-bezier(.22, 1, .36, 1)";

  if (root.hasAttribute("data-logout-destination")) {
    let handoff;
    try {
      handoff = JSON.parse(window.sessionStorage.getItem(key));
      window.sessionStorage.removeItem(key);
    } catch (_) {
      /* Storage may be disabled; ordinary login remains usable. */
    }
    if (root.hasAttribute("data-auth-expired")) handoff = { at: Date.now() };
    if (
      !handoff ||
      typeof handoff.at !== "number" ||
      !Number.isFinite(handoff.at) ||
      Date.now() - handoff.at > 10000 ||
      Date.now() < handoff.at
    )
      return;
    root.setAttribute("data-logout-return", "");
    let entry = Promise.resolve(),
      shell,
      overlay,
      priorInert = false,
      finished = false;
    window.addEventListener("pagereveal", (event) => {
      if (event.viewTransition)
        entry = event.viewTransition.finished.catch(() => {});
    });
    function release() {
      if (finished) return;
      finished = true;
      clearMotion();
      root.removeAttribute("data-logout-return");
      root.removeAttribute("data-logout-stage");
      if (shell) shell.inert = priorInert;
      overlay?.remove();
      if (!disposed)
        document.querySelector("#login")?.focus({ preventScroll: true });
    }
    later(release, 5000);
    window.addEventListener("pagehide", () => {
      disposed = true;
      release();
    });
    window.addEventListener("pageshow", (event) => {
      if (event.persisted) release();
    });
    async function revealLogin() {
      shell = document.querySelector(".login-shell");
      overlay = document.getElementById("logoutTransition");
      if (!shell || !overlay) {
        release();
        return;
      }
      priorInert = shell.inert;
      shell.inert = true;
      const logo = overlay.querySelector("#logoutTransitionLogo");
      try {
        await Promise.all([
          entry,
          logo.decode(),
          document.fonts ? document.fonts.ready : Promise.resolve(),
        ]);
        await new Promise((resolve) =>
          window.requestAnimationFrame(() =>
            window.requestAnimationFrame(resolve),
          ),
        );
        if (finished || disposed) return;
        const card = document.querySelector(".login-card");
        // Position the form while the opaque logo still covers the screen.
        if (window.innerWidth < 992 && card)
          card.scrollIntoView({ block: "center", behavior: "auto" });
        root.setAttribute("data-logout-stage", "return");
        if (!reduced) {
          play(
            shell,
            [
              { opacity: 0, transform: "scale(.985)" },
              { opacity: 1, transform: "scale(1)" },
            ],
            { duration: 400, easing: ease },
          );
          play(
            card,
            [
              { opacity: 0, transform: "translateY(8px)" },
              { opacity: 1, transform: "translateY(0)" },
            ],
            { duration: 260, delay: 130, easing: ease },
          );
          play(
            logo,
            [
              { opacity: 1, transform: "scale(1)" },
              { opacity: 0, transform: "scale(1.12)" },
            ],
            { duration: 330, easing: ease },
          );
          const offsets = [
            [-72, -32],
            [68, -28],
            [-58, 38],
            [62, 34],
          ];
          document.querySelectorAll(".logout-shape").forEach((shape, index) => {
            const [x, y] = offsets[index % offsets.length];
            play(
              shape,
              [
                { opacity: 0, transform: "translate(0,0) rotate(-20deg)" },
                { opacity: 0.35, offset: 0.2 },
                {
                  opacity: 0,
                  transform:
                    "translate(" +
                    x +
                    "px," +
                    y +
                    "px) rotate(-20deg) scale(.5)",
                },
              ],
              { duration: 360, easing: ease },
            );
          });
        }
        await play(overlay, [{ opacity: 1 }, { opacity: 0 }], {
          duration: reduced ? 160 : 400,
          easing: ease,
        }).finished;
        release();
      } catch (_) {
        release();
      }
    }
    if (document.readyState === "loading")
      document.addEventListener("DOMContentLoaded", revealLogin, {
        once: true,
      });
    else revealLogin();
    return;
  }

  const overlay = document.getElementById("logoutTransition");
  const surface =
    document.querySelector(".app-shell") ||
    document.querySelector("[data-required-password]");
  if (!overlay || !surface || !window.fetch || !surface.animate) return;
  const loginURL = new URL(overlay.dataset.loginUrl, window.location.href);
  const statusURL = overlay.dataset.statusUrl;
  const expiration = (Number(overlay.dataset.expiration) || 1800) * 1000 + 1000;
  let active = false,
    revoked = false,
    checking = false,
    priorInert = document.body.inert;
  let deadline = Date.now() + expiration,
    controller,
    channel;
  const disabled = new Map();
  function refreshCSRF(state) {
    if (!state.csrf) return;
    document.querySelectorAll("form[data-logo-logout]").forEach((form) => {
      const field = form.querySelector('[name="' + state.csrf.name + '"]');
      if (field) field.value = state.csrf.value;
    });
  }
  async function request(url, options) {
    const abort = new AbortController();
    controller = abort;
    const timeout = later(() => abort.abort(), 8000);
    try {
      const response = await window.fetch(
        url,
        Object.assign(
          {
            credentials: "same-origin",
            cache: "no-store",
            headers: {
              Accept: "application/json",
              "X-Requested-With": "XMLHttpRequest",
            },
            signal: abort.signal,
          },
          options,
        ),
      );
      if (!response.ok) throw new Error("Request not accepted");
      return await response.json();
    } finally {
      window.clearTimeout(timeout);
      timers.delete(timeout);
      if (controller === abort) controller = null;
    }
  }
  function navigate(verified) {
    if (disposed) return;
    try {
      window.sessionStorage.removeItem(key);
      if (verified)
        window.sessionStorage.setItem(key, JSON.stringify({ at: Date.now() }));
    } catch (_) {
      /* Storage is optional; the server still owns authentication. */
    }
    if (verified) {
      const bridge = document.createElement("style");
      bridge.textContent = "@view-transition { navigation: auto; }";
      document.head.append(bridge);
    }
    // Discard protected DOM, including read-only document modals outside the shell.
    document.body.replaceChildren(overlay);
    window.location.replace(loginURL.href);
  }
  function notifyOtherTabs() {
    if (channel) channel.postMessage({ type: "signed-out" });
    else
      try {
        window.localStorage.setItem("pk:sign-out", String(Date.now()));
      } catch (_) {}
  }
  async function verifyLogout(form) {
    try {
      const result = await request(form.action, {
        method: "POST",
        body: new FormData(form),
      });
      if (
        result.logged_out !== true ||
        new URL(result.login_url, loginURL).href !== loginURL.href
      )
        throw new Error("Logout not confirmed");
      return true;
    } catch (_) {
      if (disposed) return false;
      try {
        const state = await request(statusURL, { method: "GET" });
        refreshCSRF(state);
        if (state.authenticated === false) return true;
        if (state.authenticated === true) return false;
      } catch (_) {}
      // An unknown result must be resolved by the server's normal login gate.
      navigate(false);
      return null;
    }
  }
  function scheduleExpiry() {
    deadline = Date.now() + expiration;
    later(checkSession, expiration);
  }
  function restore() {
    clearMotion();
    root.removeAttribute("data-logout-active");
    root.removeAttribute("data-auth-history-gate");
    document.body.inert = priorInert;
    disabled.forEach((value, button) => {
      button.disabled = value;
    });
    disabled.clear();
    active = false;
    document.querySelectorAll("form[data-logo-logout]").forEach((form) => {
      form.dataset.confirmed = "";
      const field = form.querySelector('[name="confirmed"]');
      if (field) field.value = "no";
    });
    scheduleExpiry();
  }
  async function begin(form, alreadyRevoked = false) {
    if (active || disposed) return;
    active = true;
    priorInert = document.body.inert;
    document.body.inert = true;
    root.setAttribute("data-logout-active", "");
    root.removeAttribute("data-auth-history-gate");
    document.querySelectorAll("form[data-logo-logout]").forEach((item) =>
      item.querySelectorAll("button").forEach((button) => {
        disabled.set(button, button.disabled);
        button.disabled = true;
      }),
    );
    // Network invalidation starts before any animation/timing promise.
    const result = alreadyRevoked ? Promise.resolve(true) : verifyLogout(form);
    const departure = pause(reduced ? 160 : 560);
    const dissolved = reduced ? departure : pause(350);
    const veil = overlay.querySelector("#logoutTransitionVeil");
    const logo = overlay.querySelector("#logoutTransitionLogo");
    try {
      play(veil, [{ opacity: 0 }, { opacity: 1 }], {
        duration: reduced ? 160 : 430,
        easing: ease,
      });
      if (reduced)
        play(surface, [{ opacity: 1 }, { opacity: 0 }], { duration: 160 });
      else {
        play(
          surface,
          [
            { filter: "blur(0px) brightness(1)", opacity: 1 },
            { filter: "blur(3px) brightness(.9)", opacity: 0.1 },
          ],
          { duration: 350, easing: ease },
        );
        document
          .querySelectorAll(
            ".dashboard-welcome, .dashboard-stat-card, .dashboard-chart-card, .dashboard-activity-card, .page-content > .workspace-card",
          )
          .forEach((card, index) =>
            play(
              card,
              [
                { opacity: 1, transform: "translateY(0)" },
                { opacity: 0, transform: "translateY(-8px)" },
              ],
              { duration: 280, delay: Math.min(index * 14, 70), easing: ease },
            ),
          );
        document.querySelectorAll(".side-link").forEach((link, index) =>
          play(
            link,
            [
              { opacity: 1, transform: "translateX(0)" },
              { opacity: 0, transform: "translateX(-10px)" },
            ],
            { duration: 260, delay: Math.min(index * 8, 80), easing: ease },
          ),
        );
        play(
          logo,
          [
            { opacity: 0, transform: "scale(.82)" },
            { opacity: 1, transform: "scale(1)" },
          ],
          { duration: 280, delay: 180, easing: ease },
        );
        play(
          overlay.querySelector("#logoutTransitionGlow"),
          [
            { opacity: 0, transform: "scale(.85)" },
            { opacity: 0.35, transform: "scale(1)" },
          ],
          { duration: 280, delay: 180, easing: ease },
        );
      }
    } catch (_) {
      /* Unsupported individual effects do not delay sign-out. */
    }
    const success = await result;
    if (disposed || success === null) return;
    if (!success) {
      restore();
      const error = document.createElement("div");
      error.className = "alert alert-danger";
      error.setAttribute("role", "alert");
      error.textContent = "Sign out could not be completed. Please try again.";
      document.querySelector(".page-content")?.prepend(error);
      return;
    }
    revoked = true;
    if (!alreadyRevoked) notifyOtherTabs();
    await dissolved;
    if (disposed) return;
    document.body.replaceChildren(overlay);
    await departure;
    navigate(true);
  }
  async function checkSession() {
    if (active || checking || disposed || document.hidden) return;
    checking = true;
    try {
      const state = await request(statusURL, { method: "GET" });
      if (disposed) return;
      refreshCSRF(state);
      if (state.authenticated === false) {
        if (root.hasAttribute("data-auth-history-gate")) {
          revoked = true;
          navigate(false);
          return;
        }
        document.body.inert = priorInert;
        begin(null, true);
      } else if (state.authenticated === true) {
        root.removeAttribute("data-auth-history-gate");
        document.body.inert = priorInert;
        scheduleExpiry();
      } else navigate(false);
    } catch (_) {
      if (!disposed) navigate(false);
    } finally {
      checking = false;
    }
  }
  document.addEventListener("submit", (event) => {
    if (
      !event.target.matches("form[data-logo-logout]") ||
      event.defaultPrevented
    )
      return;
    if (event.target.querySelector('[name="confirmed"]')?.value !== "yes")
      return;
    event.preventDefault();
    begin(event.target);
  });
  function connectTabs() {
    if (!window.BroadcastChannel) return;
    try {
      channel = new window.BroadcastChannel("pk:authentication");
      channel.onmessage = (event) => {
        if (event.data?.type === "signed-out") begin(null, true);
      };
    } catch (_) {}
  }
  window.addEventListener("storage", (event) => {
    if (event.key === "pk:sign-out" && event.newValue) begin(null, true);
  });
  window.addEventListener("pagehide", () => {
    disposed = true;
    controller?.abort();
    clearMotion();
    channel?.close();
    channel = null;
    if (revoked) document.body.replaceChildren(overlay);
    else {
      document.body.inert = priorInert;
      root.removeAttribute("data-logout-active");
    }
    root.setAttribute("data-auth-history-gate", "");
    document.body.inert = true;
  });
  window.addEventListener("pageshow", (event) => {
    if (!event.persisted) return;
    disposed = false;
    active = false;
    connectTabs();
    checkSession();
  });
  document.addEventListener("visibilitychange", () => {
    if (!document.hidden && Date.now() >= deadline) checkSession();
  });
  connectTabs();
  scheduleExpiry();
})();
