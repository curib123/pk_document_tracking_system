(function () {
  "use strict";
  var button = document.getElementById("printButton");
  var status = document.getElementById("printStatus");
  if (!button) return;
  var frame = document.getElementById("printFrame");
  var image = document.getElementById("printImage");
  function ready() {
    button.disabled = false;
  }
  button.addEventListener("click", function () {
    if (!frame) {
      window.print();
      return;
    }
    try {
      frame.contentWindow.focus();
      frame.contentWindow.print();
    } catch (error) {
      status.textContent =
        "Use the printer icon inside the PDF preview, or download the file to print.";
    }
  });
  var resource = frame || image;
  if (resource) {
    resource.addEventListener("load", ready);
    resource.addEventListener("error", function () {
      status.textContent = "Preview unavailable. Download the file to print.";
    });
    if (image && image.complete && image.naturalWidth > 0) ready();
    if (frame) {
      try {
        var loadedDocument = frame.contentDocument;
        if (
          !loadedDocument ||
          (loadedDocument.readyState === "complete" &&
            loadedDocument.URL !== "about:blank")
        )
          ready();
      } catch (error) {
        // Browser-owned PDF viewers can prevent document inspection.
        ready();
      }
    }
  } else ready();
})();
