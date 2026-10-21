/* Apply before styles paint, then wire the shared topbar control. */
(function () {
  "use strict";
  var root = document.documentElement;
  var key = "pk-dts-theme";
  var system = window.matchMedia("(prefers-color-scheme: dark)");
  var preference = null;
  try {
    var saved = window.localStorage.getItem(key);
    if (saved === "dark" || saved === "light") preference = saved;
  } catch (_) { /* Browser policy may disable storage. */ }

  function apply() {
    var theme = preference || (system.matches ? "dark" : "light");
    root.dataset.theme = theme;
    root.dataset.bsTheme = theme;
    var button = document.getElementById("themeToggle");
    if (button) {
      var label = theme === "dark" ? "Switch to light mode" : "Switch to dark mode";
      button.setAttribute("aria-label", label);
      button.title = label;
      button.setAttribute("aria-pressed", String(theme === "dark"));
      button.querySelector("i").className = "fa-solid " + (theme === "dark" ? "fa-sun" : "fa-moon");
      button.hidden = false;
    }
    window.dispatchEvent(new Event("pk-theme-change"));
  }

  apply();
  document.addEventListener("DOMContentLoaded", function () {
    apply();
    var button = document.getElementById("themeToggle");
    if (!button) return;
    button.addEventListener("click", function () {
      preference = root.dataset.theme === "dark" ? "light" : "dark";
      try { window.localStorage.setItem(key, preference); } catch (_) {}
      apply();
    });
  });
  system.addEventListener("change", function () { if (!preference) apply(); });
  window.addEventListener("storage", function (event) {
    if (event.key !== key && event.key !== null) return;
    preference = event.newValue === "dark" || event.newValue === "light" ? event.newValue : null;
    apply();
  });
})();
