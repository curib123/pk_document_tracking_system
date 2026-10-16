/* Shared date-range controls and 12-hour display; does not modify model data. */
(function () {
  "use strict";
  function dateRange(form) {
    if (!form) return;
    var mode = Array.from(document.querySelectorAll("[data-date-period]")).find(
      function (select) {
        return select.form === form;
      },
    );
    if (!mode) return;
    var custom = mode.value === "custom";
    document.querySelectorAll("[data-date-custom]").forEach(function (label) {
      var field = label.querySelector("input");
      if (!field || field.form !== form) return;
      label.hidden = !custom;
      field.disabled = !custom;
      field.required = custom;
    });
    var fields = Array.from(
      document.querySelectorAll("[data-date-custom] input"),
    ).filter(function (field) {
      return field.form === form;
    });
    var from = fields.find(function (field) {
      return field.name === "from";
    });
    var to = fields.find(function (field) {
      return field.name === "to";
    });
    if (from && to) {
      from.max = to.value || "";
      to.min = from.value || "";
    }
  }
  document.querySelectorAll("[data-date-period]").forEach(function (select) {
    var form = select.form || document.getElementById("tableFilter");
    dateRange(form);
    select.addEventListener("change", function () {
      dateRange(form);
      if (select.value === "custom") {
        document
          .querySelectorAll("[data-date-custom] input")
          .forEach(function (field) {
            if (field.form === form && field.name === "from") field.focus();
          });
        return;
      }
      var page = form.querySelector('[name="page"]');
      if (page) page.value = "1";
      form.requestSubmit();
    });
    document
      .querySelectorAll("[data-date-custom] input")
      .forEach(function (field) {
        if (field.form === form)
          field.addEventListener("input", function () {
            dateRange(form);
          });
      });
  });
  document.addEventListener("pk:table-updated", function () {
    document.querySelectorAll("[data-date-period]").forEach(function (select) {
      dateRange(select.form);
    });
  });
  var pattern = /\b([12]\d{3}-\d{2}-\d{2})[ T](\d{2}):(\d{2})(?::(\d{2}))?\b/g;
  var selectors =
    "td, .doc-modal-body, .doc-modal-reference, .doc-overview-value, " +
    ".dashboard-activity-date, .file-history-item, .notification-inbox, " +
    ".detail-grid dd, .workflow-step-history, .table-cell-view, .backup-table";
  function convert(text) {
    return text.replace(
      pattern,
      function (match, day, hours, minutes, seconds) {
        var hour = Number(hours);
        if (hour > 23 || Number(minutes) > 59) return match;
        return (
          day +
          " " +
          (hour % 12 || 12) +
          ":" +
          minutes +
          (seconds ? ":" + seconds : "") +
          (hour >= 12 ? " PM" : " AM")
        );
      },
    );
  }
  function visit(node) {
    if (!node) return;
    if (node.nodeType === 3) {
      var parent = node.parentElement;
      if (
        parent &&
        parent.matches("script,style,textarea,code,pre,[contenteditable]")
      )
        return;
      if (parent && parent.closest(selectors)) {
        var formatted = convert(node.nodeValue);
        if (formatted !== node.nodeValue) node.nodeValue = formatted;
      }
      return;
    }
    if (
      node.nodeType !== 1 ||
      node.matches("script,style,textarea,code,pre,[contenteditable]")
    )
      return;
    var walker = document.createTreeWalker(node, NodeFilter.SHOW_TEXT);
    var textNodes = [],
      current;
    while ((current = walker.nextNode())) textNodes.push(current);
    textNodes.forEach(visit);
  }
  var host = document.body;
  if (!host) return;
  visit(host);
  if (!window.MutationObserver) return;
  var observer = new MutationObserver(function (mutations) {
    mutations.forEach(function (mutation) {
      if (mutation.type === "characterData") visit(mutation.target);
      else mutation.addedNodes.forEach(visit);
    });
  });
  observer.observe(host, {
    childList: true,
    subtree: true,
    characterData: true,
  });
  window.addEventListener(
    "pagehide",
    function () {
      observer.disconnect();
    },
    { once: true },
  );
})();
