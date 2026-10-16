(function () {
  "use strict";
  const root = document.documentElement;
  if (!root.hasAttribute("data-login-transition")) return;

  let started = false,
    ended = false,
    gated = false,
    app,
    splash,
    originalInert,
    originalBusy;
  let entry = Promise.resolve();
  const animations = [];
  const timers = [];
  function finish() {
    if (ended) return;
    ended = true;
    timers.forEach((id) => window.clearTimeout(id));
    animations.forEach((animation) => animation.cancel());
    if (gated) root.setAttribute("data-login-presented", "");
    root.removeAttribute("data-login-transition");
    root.removeAttribute("data-login-stage");
    if (app && gated) {
      app.inert = originalInert;
      if (originalBusy === null) app.removeAttribute("aria-busy");
      else app.setAttribute("aria-busy", originalBusy);
    }
    (splash || document.getElementById("loginTransition"))?.remove();
  }
  // Register in the head: pagereveal runs before the new document's first paint.
  window.addEventListener("pagereveal", (event) => {
    if (event.viewTransition)
      entry = event.viewTransition.finished.catch(() => {});
  });
  window.addEventListener("pageshow", (event) => {
    if (event.persisted) finish();
  });
  window.addEventListener("pagehide", finish);
  window.addEventListener("resize", finish, { once: true });
  timers.push(window.setTimeout(finish, 5000));

  function animate(element, frames, options) {
    const animation = element.animate(
      frames,
      Object.assign({ fill: "forwards" }, options),
    );
    animations.push(animation);
    return animation;
  }
  const nextFrame = () =>
    new Promise((resolve) => window.requestAnimationFrame(resolve));
  const pause = (ms) =>
    new Promise((resolve) => timers.push(window.setTimeout(resolve, ms)));
  async function begin() {
    if (started || ended) return;
    started = true;
    splash = document.getElementById("loginTransition");
    app = document.querySelector(".app-shell");
    const logo = document.getElementById("loginTransitionLogo");
    const glow = document.getElementById("loginTransitionGlow");
    const veil = document.getElementById("loginTransitionVeil");
    const aperture = document.getElementById("loginTransitionAperture");
    if (!app || !splash || !logo || !aperture || !app.animate) {
      finish();
      return;
    }
    originalInert = app.inert;
    originalBusy = app.getAttribute("aria-busy");
    gated = true;
    app.inert = true;
    app.setAttribute("aria-busy", "true");
    try {
      await Promise.all([
        entry,
        logo.decode ? logo.decode() : Promise.resolve(),
      ]);
      if (ended) return;
      const layoutReady = async () => {
        if (document.fonts) await document.fonts.ready;
        // Dashboard DOM and chart setup already ran at DOMContentLoaded.
        await nextFrame();
        await nextFrame();
      };
      if (window.matchMedia("(prefers-reduced-motion: reduce)").matches) {
        await layoutReady();
        if (ended) return;
        await animate(splash, [{ opacity: 1 }, { opacity: 0 }], {
          duration: 160,
        }).finished;
        finish();
        return;
      }
      const width = window.innerWidth,
        height = window.innerHeight;
      veil.setAttribute("viewBox", "0 0 " + width + " " + height);
      aperture.setAttribute("cx", width / 2);
      aperture.setAttribute("cy", height / 2);
      root.setAttribute("data-login-stage", "enter");
      const easeOut = "cubic-bezier(.16, 1, .3, 1)";
      animate(
        app,
        [
          { opacity: 0, filter: "blur(0px) brightness(1)" },
          { opacity: 0.45, filter: "blur(5px) brightness(.96)" },
        ],
        { duration: 350, easing: easeOut },
      );
      animate(
        glow,
        [
          { opacity: 0.15, transform: "scale(.8)" },
          { opacity: 0.65, transform: "scale(1.06)", offset: 0.8 },
          { opacity: 0.4, transform: "scale(1)" },
        ],
        { duration: 420, easing: easeOut },
      );
      await animate(
        logo,
        [
          { opacity: 0, transform: "scale(.65)" },
          { opacity: 1, transform: "scale(1)" },
        ],
        { duration: 420, easing: easeOut },
      ).finished;
      if (ended) return;
      await Promise.all([pause(130), layoutReady()]);
      if (ended) return;
      root.setAttribute("data-login-stage", "reveal");
      const reveal = { duration: 520, easing: "cubic-bezier(.7, 0, .2, 1)" };
      const zoom =
        (Math.max(width, height) * 2.5) / logo.getBoundingClientRect().width;
      animate(
        logo,
        [
          { opacity: 1, transform: "scale(1)" },
          { opacity: 0.8, offset: 0.45 },
          { opacity: 0, transform: "scale(" + zoom + ")" },
        ],
        reveal,
      );
      animate(
        glow,
        [
          { opacity: 0.4, transform: "scale(1)" },
          { opacity: 0, transform: "scale(2.2)" },
        ],
        reveal,
      );
      animate(
        app,
        [
          {
            opacity: 0.45,
            filter: "blur(5px) brightness(.96)",
            transform: "translateY(8px)",
          },
          {
            opacity: 1,
            filter: "blur(0px) brightness(1)",
            transform: "translateY(0)",
          },
        ],
        reveal,
      );
      animate(
        veil,
        [{ opacity: 1 }, { opacity: 1, offset: 0.65 }, { opacity: 0 }],
        reveal,
      );
      await animate(
        aperture,
        [
          { transform: "scale(0)" },
          { transform: "scale(" + (Math.hypot(width, height) / 2 + 24) + ")" },
        ],
        reveal,
      ).finished;
      finish();
    } catch (_) {
      // Missing assets, unsupported animation, or interrupted navigation unlock immediately.
      finish();
    }
  }
  if (document.readyState === "loading")
    document.addEventListener("DOMContentLoaded", begin, { once: true });
  else begin();
})();
