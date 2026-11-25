/*
 * Fullscreen file viewer with mouse pan and zoom.
 *
 * Opened from the Fullscreen button of a file preview in View Details.
 *   Scroll wheel / trackpad pinch: zoom in and out at the pointer
 *   Drag with the mouse (or one finger): pan
 *   Two fingers: pinch to zoom and pan
 *   Double-click: fit the page, or zoom in
 *   Keyboard: + and - zoom, 0 fits, 1 shows actual size, arrows and Page Up/Down move, Esc closes
 *
 * Images are shown directly. PDFs (and Word or Excel files, which the server converts to
 * PDF) are drawn page by page with the PDF.js copy hosted in vendor/pdfjs, so zooming stays
 * sharp and no browser PDF toolbar (save, print) appears. The file is the same authorized
 * preview URL the inline preview uses.
 */
(function () {
  "use strict";

  var MIN_SCALE = 0.1;
  var MAX_SCALE = 10;
  var CSS_PER_POINT = 96 / 72;
  var MAX_CANVAS_SIDE = 4096;
  var PAGE_GAP = 16;
  var KEEP_VISIBLE = 80;

  var script =
    document.currentScript ||
    document.querySelector('script[src*="preview-viewer.js"]');
  var assetBase = script && script.src ? script.src.replace(/[^\/]*$/, "") : "";
  var pdfLibrary = null;

  function loadPdfLibrary() {
    if (pdfLibrary) return pdfLibrary;
    pdfLibrary = new Promise(function (resolve, reject) {
      if (window.pdfjsLib) return resolve(window.pdfjsLib);
      var tag = document.createElement("script");
      tag.src = assetBase + "vendor/pdfjs/pdf.min.js";
      tag.onload = function () {
        if (!window.pdfjsLib) return reject(new Error("PDF library missing"));
        window.pdfjsLib.GlobalWorkerOptions.workerSrc =
          assetBase + "vendor/pdfjs/pdf.worker.min.js";
        resolve(window.pdfjsLib);
      };
      tag.onerror = function () {
        pdfLibrary = null;
        reject(new Error("PDF library failed to load"));
      };
      document.head.append(tag);
    });
    return pdfLibrary;
  }

  function el(tag, className, text) {
    var element = document.createElement(tag);
    if (className) element.className = className;
    if (text != null) element.textContent = text;
    return element;
  }

  function button(label, text, className) {
    var item = el("button", "pv-btn" + (className ? " " + className : ""), text);
    item.type = "button";
    item.setAttribute("aria-label", label);
    item.title = label;
    return item;
  }

  function clamp(value, low, high) {
    return Math.min(high, Math.max(low, value));
  }

  function open(options) {
    options = options || {};
    var kind = options.kind === "pdf" ? "pdf" : "image";
    var host =
      (options.opener && options.opener.closest(".modal")) || document.body;
    var closed = false;
    var inFullscreen = false;

    var overlay = el("div", "pv");
    overlay.setAttribute("role", "dialog");
    overlay.setAttribute("aria-modal", "true");
    overlay.setAttribute("aria-label", "Fullscreen view of " + (options.title || "file"));
    overlay.tabIndex = -1;

    var toolbar = el("div", "pv-toolbar");
    var title = el("div", "pv-title", options.title || "File preview");
    var pageLabel = el("div", "pv-page-label");
    pageLabel.setAttribute("aria-live", "polite");
    var zoomOut = button("Zoom out", "−");
    var zoomLabel = button("Fit to screen", "100%", "pv-zoom-label");
    var zoomIn = button("Zoom in", "+");
    var fit = button("Fit to screen", "Fit", "pv-fit");
    var close = button("Close fullscreen view", "Close", "pv-close");
    toolbar.append(title, pageLabel, zoomOut, zoomLabel, zoomIn, fit, close);

    var stage = el("div", "pv-stage");
    stage.tabIndex = 0;
    var layer = el("div", "pv-layer");
    var status = el("div", "pv-status", "Loading…");
    status.setAttribute("role", "status");
    var hint = el(
      "div",
      "pv-hint",
      "Scroll to zoom. Drag to move. Double-click to fit.",
    );
    stage.append(layer, status, hint);
    overlay.append(toolbar, stage);

    var state = { scale: 1, x: 0, y: 0, fitScale: 1, width: 0, height: 0 };
    var pages = [];
    var pdf = null;
    var renderTimer = 0;
    var frame = 0;

    function stageSize() {
      return { width: stage.clientWidth, height: stage.clientHeight };
    }

    function constrain() {
      var box = stageSize();
      var w = state.width * state.scale;
      var h = state.height * state.scale;
      state.x = clamp(state.x, KEEP_VISIBLE - w, box.width - KEEP_VISIBLE);
      state.y = clamp(state.y, KEEP_VISIBLE - h, box.height - KEEP_VISIBLE);
    }

    function apply() {
      frame = 0;
      constrain();
      layer.style.transform =
        "translate(" + state.x + "px," + state.y + "px) scale(" + state.scale + ")";
      zoomLabel.textContent = Math.round((state.scale / state.fitScale) * 100) + "%";
      updatePageLabel();
      if (kind === "pdf") {
        clearTimeout(renderTimer);
        renderTimer = setTimeout(renderVisible, 140);
      }
    }

    function schedule() {
      if (!frame) frame = requestAnimationFrame(apply);
    }

    function zoomAt(factor, cx, cy) {
      var next = clamp(state.scale * factor, state.fitScale * 0.2, MAX_SCALE);
      next = Math.max(next, MIN_SCALE);
      var ratio = next / state.scale;
      state.x = cx - (cx - state.x) * ratio;
      state.y = cy - (cy - state.y) * ratio;
      state.scale = next;
      schedule();
    }

    function zoomCenter(factor) {
      var box = stageSize();
      zoomAt(factor, box.width / 2, box.height / 2);
    }

    function fitToScreen() {
      var box = stageSize();
      if (!state.width || !box.width) return;
      var margin = 24;
      var byWidth = (box.width - margin) / state.width;
      var byHeight = (box.height - margin) / state.height;
      // A document is fitted by width so its text stays readable and the user pans down.
      state.fitScale = kind === "pdf" ? byWidth : Math.min(byWidth, byHeight);
      state.scale = state.fitScale;
      state.x = (box.width - state.width * state.scale) / 2;
      state.y = kind === "pdf" ? 12 : (box.height - state.height * state.scale) / 2;
      schedule();
    }

    function moveBy(dx, dy) {
      state.x += dx;
      state.y += dy;
      schedule();
    }

    // ---- PDF pages -------------------------------------------------------------------
    function updatePageLabel() {
      if (kind !== "pdf" || !pages.length) return;
      var box = stageSize();
      var middle = (box.height / 2 - state.y) / state.scale;
      var current = 1;
      for (var i = 0; i < pages.length; i++) {
        if (pages[i].top <= middle) current = i + 1;
      }
      pageLabel.textContent = "Page " + current + " of " + pages.length;
    }

    function renderVisible() {
      if (closed || !pages.length) return;
      var box = stageSize();
      var top = (-state.y - box.height * 0.5) / state.scale;
      var bottom = (-state.y + box.height * 1.5) / state.scale;
      // One layer pixel covers state.scale screen pixels: draw each page at that sharpness.
      var wanted = Math.min(Math.max(1, state.scale * (window.devicePixelRatio || 1)), 4);
      pages.forEach(function (page) {
        if (page.top + page.height < top || page.top > bottom) return;
        renderPage(page, wanted);
      });
    }

    function renderPage(page, quality) {
      var side = Math.max(page.width, page.height);
      var q = Math.max(1, Math.min(quality, MAX_CANVAS_SIDE / side));
      if (page.rendered >= q * 0.95 || page.rendering >= q * 0.95) return;
      page.rendering = q;
      if (page.task) page.task.cancel();
      var canvas = document.createElement("canvas");
      var viewport = page.pdfPage.getViewport({ scale: CSS_PER_POINT * q });
      canvas.width = Math.floor(viewport.width);
      canvas.height = Math.floor(viewport.height);
      var task = page.pdfPage.render({
        canvasContext: canvas.getContext("2d"),
        viewport: viewport,
      });
      page.task = task;
      task.promise
        .then(function () {
          if (closed) return;
          page.rendered = q;
          page.rendering = 0;
          page.task = null;
          page.holder.replaceChildren(canvas);
        })
        .catch(function () {
          page.rendering = 0;
        });
    }

    function buildPdf(url) {
      return loadPdfLibrary()
        .then(function (lib) {
          return lib.getDocument({ url: url, withCredentials: true }).promise;
        })
        .then(function (document_) {
          if (closed) {
            document_.destroy();
            return;
          }
          pdf = document_;
          var jobs = [];
          for (var number = 1; number <= pdf.numPages; number++)
            jobs.push(pdf.getPage(number));
          return Promise.all(jobs);
        })
        .then(function (pdfPages) {
          if (closed || !pdfPages) return;
          var top = 0;
          var widest = 0;
          pdfPages.forEach(function (pdfPage) {
            var view = pdfPage.getViewport({ scale: CSS_PER_POINT });
            var holder = el("div", "pv-page");
            holder.style.width = view.width + "px";
            holder.style.height = view.height + "px";
            holder.style.top = top + "px";
            layer.append(holder);
            pages.push({
              pdfPage: pdfPage,
              holder: holder,
              top: top,
              width: view.width,
              height: view.height,
              rendered: 0,
              rendering: 0,
              task: null,
            });
            top += view.height + PAGE_GAP;
            widest = Math.max(widest, view.width);
          });
          // Centre narrower pages inside the widest one.
          pages.forEach(function (page) {
            page.holder.style.left = (widest - page.width) / 2 + "px";
          });
          state.width = widest;
          state.height = top - PAGE_GAP;
          layer.style.width = state.width + "px";
          layer.style.height = state.height + "px";
          status.hidden = true;
          fitToScreen();
        });
    }

    function buildImage(url) {
      return new Promise(function (resolve, reject) {
        var image = new Image();
        image.alt = options.title || "";
        image.draggable = false;
        image.onload = function () {
          if (closed) return resolve();
          state.width = image.naturalWidth || 1;
          state.height = image.naturalHeight || 1;
          layer.style.width = state.width + "px";
          layer.style.height = state.height + "px";
          layer.append(image);
          status.hidden = true;
          fitToScreen();
          resolve();
        };
        image.onerror = function () {
          reject(new Error("Image failed to load"));
        };
        image.src = url;
      });
    }

    // ---- Mouse, touch and keyboard ---------------------------------------------------
    var pointers = new Map();
    var gesture = null;

    function localPoint(event) {
      var rect = stage.getBoundingClientRect();
      return { x: event.clientX - rect.left, y: event.clientY - rect.top };
    }

    stage.addEventListener(
      "wheel",
      function (event) {
        event.preventDefault();
        var unit = event.deltaMode === 1 ? 16 : event.deltaMode === 2 ? 120 : 1;
        var point = localPoint(event);
        zoomAt(Math.exp(-event.deltaY * unit * (event.ctrlKey ? 0.01 : 0.0015)), point.x, point.y);
      },
      { passive: false },
    );

    stage.addEventListener("pointerdown", function (event) {
      if (event.pointerType === "mouse" && event.button !== 0) return;
      stage.setPointerCapture(event.pointerId);
      pointers.set(event.pointerId, localPoint(event));
      stage.classList.add("is-grabbing");
      stage.focus({ preventScroll: true });
      gesture = null;
    });

    stage.addEventListener("pointermove", function (event) {
      if (!pointers.has(event.pointerId)) return;
      var before = pointers.get(event.pointerId);
      var point = localPoint(event);
      pointers.set(event.pointerId, point);
      if (pointers.size === 1) {
        moveBy(point.x - before.x, point.y - before.y);
      } else if (pointers.size === 2) {
        var pair = Array.from(pointers.values());
        var distance = Math.hypot(pair[0].x - pair[1].x, pair[0].y - pair[1].y);
        var centre = { x: (pair[0].x + pair[1].x) / 2, y: (pair[0].y + pair[1].y) / 2 };
        if (gesture) {
          moveBy(centre.x - gesture.centre.x, centre.y - gesture.centre.y);
          if (gesture.distance > 0) zoomAt(distance / gesture.distance, centre.x, centre.y);
        }
        gesture = { distance: distance, centre: centre };
      }
    });

    function release(event) {
      pointers.delete(event.pointerId);
      gesture = null;
      if (!pointers.size) stage.classList.remove("is-grabbing");
    }
    stage.addEventListener("pointerup", release);
    stage.addEventListener("pointercancel", release);

    stage.addEventListener("dblclick", function (event) {
      var point = localPoint(event);
      if (Math.abs(state.scale - state.fitScale) < state.fitScale * 0.05)
        zoomAt(2.5, point.x, point.y);
      else fitToScreen();
    });

    overlay.addEventListener("keydown", function (event) {
      var key = event.key;
      var box = stageSize();
      if (key === "Escape") {
        event.preventDefault();
        event.stopPropagation();
        shutdown();
      } else if (key === "+" || key === "=") zoomCenter(1.25);
      else if (key === "-" || key === "_") zoomCenter(0.8);
      else if (key === "0") fitToScreen();
      else if (key === "1") {
        var scaleRatio = 1 / state.scale;
        zoomAt(scaleRatio, box.width / 2, box.height / 2);
      } else if (key === "ArrowLeft") moveBy(60, 0);
      else if (key === "ArrowRight") moveBy(-60, 0);
      else if (key === "ArrowUp") moveBy(0, 60);
      else if (key === "ArrowDown") moveBy(0, -60);
      else if (key === "PageUp") moveBy(0, box.height * 0.9);
      else if (key === "PageDown" || key === " ") moveBy(0, -box.height * 0.9);
      else if (key === "Home") fitToScreen();
      else return;
      if (key !== "Escape") event.preventDefault();
    });

    zoomIn.addEventListener("click", function () { zoomCenter(1.25); });
    zoomOut.addEventListener("click", function () { zoomCenter(0.8); });
    fit.addEventListener("click", fitToScreen);
    zoomLabel.addEventListener("click", fitToScreen);
    close.addEventListener("click", function () { shutdown(); });

    function onResize() {
      schedule();
    }
    function onFullscreenChange() {
      var active = document.fullscreenElement || document.webkitFullscreenElement;
      if (active === overlay) inFullscreen = true;
      else if (inFullscreen) finish();
    }

    // Leaving fullscreen with Esc or the Close button always ends the viewer.
    function shutdown() {
      var active = document.fullscreenElement || document.webkitFullscreenElement;
      if (active === overlay) {
        var exit = document.exitFullscreen || document.webkitExitFullscreen;
        try {
          var request = exit.call(document);
          if (request && request.catch) request.catch(finish);
        } catch (error) {
          finish();
        }
      } else finish();
    }

    function finish() {
      if (closed) return;
      closed = true;
      clearTimeout(renderTimer);
      if (frame) cancelAnimationFrame(frame);
      window.removeEventListener("resize", onResize);
      document.removeEventListener("fullscreenchange", onFullscreenChange);
      document.removeEventListener("webkitfullscreenchange", onFullscreenChange);
      pages.forEach(function (page) {
        if (page.task) page.task.cancel();
      });
      if (pdf) pdf.destroy();
      overlay.remove();
      if (options.opener && options.opener.isConnected) options.opener.focus();
    }

    window.addEventListener("resize", onResize);
    document.addEventListener("fullscreenchange", onFullscreenChange);
    document.addEventListener("webkitfullscreenchange", onFullscreenChange);

    host.append(overlay);
    var request = overlay.requestFullscreen || overlay.webkitRequestFullscreen;
    if (request) {
      try {
        var promise = request.call(overlay);
        if (promise && promise.catch) promise.catch(function () {});
      } catch (error) {
        /* The overlay still covers the page when fullscreen is blocked. */
      }
    }
    stage.focus({ preventScroll: true });

    var loading = kind === "pdf" ? buildPdf(options.url) : buildImage(options.url);
    loading.catch(function () {
      if (closed) return;
      status.textContent = "This file could not be shown here.";
      status.hidden = false;
      status.classList.add("is-error");
      if (typeof options.onFail === "function") {
        finish();
        options.onFail();
      }
    });

    return { close: shutdown };
  }

  window.PKPreviewViewer = { open: open };
})();
