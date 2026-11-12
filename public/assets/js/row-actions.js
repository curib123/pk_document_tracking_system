/* Row actions ask for input only when their operation uses it. */
(function () {
  "use strict";
  var form = document.getElementById("actionForm");
  if (!form) return;
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
    var needsDetails =
      isDisposal || needsFinal || ["returned", "rejected"].includes(decision);
    var title = action.getAttribute("data-title") || "Confirm Action";
    var description =
      action.getAttribute("data-description") ||
      "This action will be saved to the system.";
    document.getElementById("actionTitle").textContent = title;
    document.getElementById("actionDescription").textContent = description;
    form.setAttribute("data-confirm", title + "? " + description);
    var showRemark = needsDetails && !needsFinal;
    document.getElementById("actionRemarkFields").hidden = !showRemark;
    form.querySelector('[name="remark"]').disabled = !showRemark;
    var needsPassword = action.getAttribute("data-password-confirm") === "yes";
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
    var finalFile = document.getElementById("actionFinalFile");
    if (finalBox && finalFile) {
      finalBox.hidden = !needsFinal;
      finalFile.disabled = !needsFinal;
      finalFile.required = needsFinal;
      var dl = document.getElementById("actionFinalDownload");
      if (dl) {
        var url = action.getAttribute("data-final-url") || "";
        dl.hidden = !url;
        dl.setAttribute("href", url || "#");
      }
    }
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
    if (needsDetails)
      window.bootstrap.Modal.getOrCreateInstance(
        document.getElementById("actionModal"),
      ).show();
    else form.requestSubmit();
  });
})();
