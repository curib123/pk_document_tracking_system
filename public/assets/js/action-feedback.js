/* A waiting notification for native POSTs; the returned page owns the result. */
(function () {
  "use strict";
  var panel = document.getElementById("appActionFeedback");
  if (!panel) return;
  var title = document.getElementById("actionFeedbackTitle");
  var message = document.getElementById("actionFeedbackMessage");
  var dismiss = document.getElementById("actionFeedbackDismiss");
  var pending = null,
    recovery = null,
    busyForm = null,
    priorBusy = null;

  function reset() {
    if (pending !== null) window.clearTimeout(pending);
    if (recovery !== null) window.clearTimeout(recovery);
    pending = null;
    recovery = null;
    panel.hidden = true;
    if (busyForm) {
      if (priorBusy === null) busyForm.removeAttribute("aria-busy");
      else busyForm.setAttribute("aria-busy", priorBusy);
    }
    busyForm = null;
    priorBusy = null;
  }

  // app.js cancels the first submit while confirmation is open. Recheck the
  // event after other listeners have run, without delaying the native POST.
  document.addEventListener("submit", function (event) {
    var form = event.target;
    var submitter = event.submitter;
    var method =
      submitter && submitter.hasAttribute("formmethod")
        ? submitter.formMethod
        : (form && form.method) || "";
    var target =
      submitter && submitter.hasAttribute("formtarget")
        ? submitter.formTarget
        : (form && form.target) || "";
    if (
      event.defaultPrevented ||
      method.toLowerCase() !== "post" ||
      (target && target !== "_self") ||
      pending !== null ||
      busyForm
    )
      return;
    pending = window.setTimeout(function () {
      pending = null;
      if (event.defaultPrevented) return;
      busyForm = form;
      priorBusy = form.getAttribute("aria-busy");
      form.setAttribute("aria-busy", "true");
      title.textContent = "Working on it";
      message.textContent =
        "Please wait while we prepare the result of your action.";
      dismiss.hidden = true;
      panel.hidden = false;
      recovery = window.setTimeout(function () {
        recovery = null;
        title.textContent = "Taking a little longer";
        message.textContent =
          "Your request may still be processing. You can dismiss this notice while you wait for the result.";
        dismiss.hidden = false;
      }, 12000);
    }, 180);
  });

  dismiss.addEventListener("click", reset);
  document.addEventListener("keydown", function (event) {
    if (event.key === "Escape" && !panel.hidden) reset();
  });
  window.addEventListener("pageshow", reset);
})();
