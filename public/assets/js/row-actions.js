/* Row actions ask for input only when their operation uses it. */
(function () {
  "use strict";
  var form = document.getElementById("actionForm");
  if (!form) return;
  var finalFile = document.getElementById("actionFinalFile");
  var finalFileTypes = finalFile?.accept;
  document.addEventListener("click", function (event) {
    var action = event.target.closest(".js-action");
    if (!action) return;
    event.preventDefault();
    form.reset();
    form.action = action.getAttribute("data-url");
    ["id", "decision", "step_key", "direction"].forEach(function (name) {
      var field = form.querySelector('[name="' + name + '"]');
      if (field)
        field.value =
          action.getAttribute("data-" + name.replace("_", "-")) || "";
    });
    var decision = action.getAttribute("data-decision");
    var isDisposal = action.getAttribute("data-disposal") === "yes";
    var needsFinal = action.getAttribute("data-final-upload") === "yes";
    var needsTemplate = action.getAttribute("data-template-upload") === "yes";
    var needsPassword = action.getAttribute("data-password-confirm") === "yes";
    var skipRemarks = action.getAttribute("data-skip-remarks") === "yes";
    var needsRemark = action.getAttribute("data-remark-required") === "yes";
    var needsDetails =
      needsRemark ||
      isDisposal ||
      needsFinal ||
      needsPassword ||
      (!skipRemarks && ["returned", "rejected"].includes(decision));
    var title = action.getAttribute("data-title") || "Confirm Action";
    var description =
      action.getAttribute("data-description") ||
      "This action will be saved to the system.";
    document.getElementById("actionTitle").textContent = title;
    document.getElementById("actionDescription").textContent = description;
    form.setAttribute("data-confirm", title + "? " + description);
    var showRemark = !skipRemarks && (needsRemark || (needsDetails && !needsFinal && !needsPassword));
    document.getElementById("actionRemarkFields").hidden = !showRemark;
    form.querySelector('[name="remark"]').disabled = !showRemark;
    form.querySelector('[name="remark"]').required = needsRemark;
    var remarkLabel = document.querySelector('#actionRemarkFields .optional-label');
    if (remarkLabel) remarkLabel.textContent = needsRemark ? '(required)' : '(optional)';
    var passBox = document.getElementById("actionPasswordFields");
    if (passBox) {
      passBox.hidden = !needsPassword;
      passBox.querySelectorAll("input").forEach(function (input) {
        input.disabled = !needsPassword;
        input.required = needsPassword;
        input.value = "";
      });
    }
    var finalBox = document.getElementById("actionFinalFields");
    if (finalBox && finalFile) {
      finalBox.hidden = !needsFinal;
      finalFile.disabled = !needsFinal;
      finalFile.required = needsFinal;
      finalFile.accept = needsTemplate ? ".docx,.xlsx,.txt" : finalFileTypes;
      document.getElementById("actionFinalFileLabel").textContent =
        needsTemplate ? "Editable revision template" : "Final edited file";
      finalBox.querySelectorAll("[data-final-document-copy]").forEach(function (copy) {
        copy.hidden = needsTemplate;
      });
      finalBox.querySelector("[data-template-copy]").hidden = !needsTemplate;
      var dl = document.getElementById("actionFinalDownload");
      if (dl) {
        var url = action.getAttribute("data-final-url") || "";
        dl.hidden = !url;
        dl.setAttribute("href", url || "#");
      }
    }
    document.getElementById("actionContinue").textContent = needsTemplate ? "Upload and Approve" : "Continue";
    form.enctype = needsFinal ? "multipart/form-data" : "";
    if (!needsFinal) form.removeAttribute("enctype");
    var disposal = form.querySelector("#actionDisposalFields");
    if (disposal) {
      disposal.hidden = !isDisposal;
      disposal.querySelectorAll("select,input").forEach(function (field) {
        field.disabled = !isDisposal;
        field.required =
          isDisposal && field.hasAttribute("data-disposal-reason");
      });
      var reason = disposal.querySelector("[data-disposal-reason]");
      if (reason) reason.dispatchEvent(new Event("change", { bubbles: true }));
    }
    // Continue in the remarks/reasons dialog is itself the confirmation.
    // Other row actions enter the shared confirmation without this dialog.
    form.dataset.confirmed = needsDetails ? "yes" : "";
    form.querySelector('[name="confirmed"]').value = needsDetails
      ? "yes"
      : "no";
    var next = document.getElementById(needsDetails ? "actionModal" : "confirmModal");
    function confirmAction() {
      if (needsDetails) window.bootstrap.Modal.getOrCreateInstance(next).show();
      else form.requestSubmit();
    }
    var review = action.closest("#viewModal.show");
    if (!review) {
      confirmAction();
      return;
    }

    // Bootstrap supports one open modal. Keep the review's selected tab and
    // scroll position when the reviewer cancels a decision.
    review.setAttribute("data-review-suspended", "");
    review.addEventListener("hidden.bs.modal", confirmAction, { once: true });
    next.addEventListener("hidden.bs.modal", function () {
      review.removeAttribute("data-review-suspended");
      if (!needsDetails && form.querySelector('[name="confirmed"]').value === "yes") return;
      review.addEventListener("shown.bs.modal", function () { action.focus(); }, { once: true });
      window.bootstrap.Modal.getOrCreateInstance(review).show();
    }, { once: true });
    window.bootstrap.Modal.getOrCreateInstance(review).hide();
  });
})();
