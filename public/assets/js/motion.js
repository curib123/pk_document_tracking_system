/* Progressive enhancement for full-page CodeIgniter navigation.
 * A shimmer is shown on the departing page during slow page loads.
 * No persistent storage, API endpoints, or changes to the submitted data.
 */
(function () {
  "use strict";

  var page = document.querySelector(".page-content");
  var body = document.body;
  var pending = null;
  var recovery = null;
  var skeleton = null;
  var reduced =
    window.matchMedia &&
    window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  function make(tag, cls) {
    var element = document.createElement(tag);
    element.className = cls;
    return element;
  }

  function ensureSkeleton() {
    if (skeleton || !page) return skeleton;
    skeleton = make("div", "motion-skeleton");
    skeleton.hidden = true;
    skeleton.setAttribute("aria-hidden", "true");
    var line = make("span", "motion-skeleton-block motion-skeleton-line");
    var shortLine = make(
      "span",
      "motion-skeleton-block motion-skeleton-line short",
    );
    var banner = make("div", "motion-skeleton-block motion-skeleton-banner");
    var cards = make("div", "motion-skeleton-cards");
    var rows = make("div", "motion-skeleton-rows");
    for (var i = 0; i < 4; i++)
      cards.appendChild(make("div", "motion-skeleton-block"));
    for (var j = 0; j < 4; j++)
      rows.appendChild(make("div", "motion-skeleton-block"));
    skeleton.append(line, shortLine, banner, cards, rows);
    page.appendChild(skeleton);
    return skeleton;
  }

  function reset() {
    if (pending !== null) {
      window.clearTimeout(pending);
      pending = null;
    }
    if (recovery !== null) {
      window.clearTimeout(recovery);
      recovery = null;
    }
    if (skeleton) skeleton.hidden = true;
    if (page) page.removeAttribute("aria-busy");
    body.classList.remove("motion-login-loading");
    var required = document.querySelector("[data-required-password]");
    if (required) required.classList.remove("motion-auth-loading");
  }

  function begin() {
    if (
      pending !== null ||
      (skeleton && !skeleton.hidden) ||
      body.classList.contains("motion-login-loading")
    )
      return;
    pending = window.setTimeout(
      function () {
        pending = null;
        if (page) {
          var node = ensureSkeleton();
          node.hidden = false;
          page.setAttribute("aria-busy", "true");
        } else if (body.classList.contains("login-page")) {
          body.classList.add("motion-login-loading");
        } else {
          var required = document.querySelector("[data-required-password]");
          if (required) required.classList.add("motion-auth-loading");
        }
        // If navigation is prevented by the browser/network, restore the UI.
        recovery = window.setTimeout(reset, 12000);
      },
      reduced ? 0 : 130,
    );
  }

  document.addEventListener("click", function (event) {
    if (
      event.defaultPrevented ||
      event.button !== 0 ||
      event.ctrlKey ||
      event.metaKey ||
      event.shiftKey ||
      event.altKey
    )
      return;
    var anchor = event.target.closest("a[href]");
    if (
      !anchor ||
      anchor.hasAttribute("download") ||
      (anchor.classList &&
        anchor.classList.contains("record-action-download")) ||
      (anchor.getAttribute("target") &&
        anchor.getAttribute("target") !== "_self") ||
      anchor.hasAttribute("data-bs-toggle")
    )
      return;
    var href = anchor.getAttribute("href");
    if (!href || href.charAt(0) === "#" || href.indexOf("javascript:") === 0)
      return;
    try {
      var destination = new URL(anchor.href, window.location.href);
      if (
        destination.origin !== window.location.origin ||
        !["http:", "https:"].includes(destination.protocol) ||
        /\/files\/download\//.test(destination.pathname)
      )
        return;
      var here = new URL(window.location.href);
      if (
        destination.pathname === here.pathname &&
        destination.search === here.search &&
        destination.hash !== here.hash
      )
        return;
      if (destination.href === here.href) return;
      begin();
    } catch (error) {
      /* Invalid URLs cannot trigger navigation skeletons. */
    }
  });

  // Registered after app.js's confirmation handler. A modal confirmation
  // prevents the original submit; do not cover that modal with a skeleton.
  document.addEventListener("submit", function (event) {
    if (
      !event.defaultPrevented &&
      event.target &&
      event.target.method &&
      event.target.method.toLowerCase() !== "dialog"
    )
      begin();
  });

  // Autosubmit filters use form.submit(), which skips the submit event.
  document.addEventListener("change", function (event) {
    if (
      event.target &&
      event.target.matches("[data-auto-submit]") &&
      event.target.form &&
      !event.target.closest("[data-live-ready]")
    )
      begin();
  });

  window.addEventListener("pageshow", reset);
  document.addEventListener("visibilitychange", function () {
    if (!document.hidden && skeleton && !skeleton.hidden) reset();
  });
  document.addEventListener("keydown", function (event) {
    if (event.key === "Escape" && skeleton && !skeleton.hidden) reset();
  });
})();
