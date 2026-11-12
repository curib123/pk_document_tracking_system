/**
 * Softcopy Direct, Create only: pre-fills Document Title, Revision Level and
 * Effective Date from the chosen Document File (the title under the header,
 * and header cells "Revision Number" and "Effectivity Date").
 *
 * Everything runs in the browser; the file is not uploaded until the form is
 * submitted. Anything that cannot be found leaves its field empty, never a guess.
 * Supported: PDF, DOCX, XLSX and TXT. Images carry no readable fields.
 */
(function () {
  "use strict";

  var script =
    document.currentScript ||
    document.querySelector('script[src*="softcopy-autofill.js"]');
  var assetBase = script && script.src ? script.src.replace(/[^\/]*$/, "") : "";
  var MAX_BYTES = 15 * 1024 * 1024;
  var MAX_TEXT = 200000;
  var token = 0;
  var pdfLibrary = null;

  var MONTHS = {
    jan: 1, feb: 2, mar: 3, apr: 4, may: 5, jun: 6,
    jul: 7, aug: 8, sep: 9, oct: 10, nov: 11, dec: 12,
  };

  // ---------- readers: each resolves { title, text } ----------

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

  function readPdf(buffer) {
    return loadPdfLibrary().then(function (lib) {
      return lib.getDocument({ data: new Uint8Array(buffer) }).promise.then(function (pdf) {
        var title = "";
        var last = Math.min(pdf.numPages, 3);
        var pages = [];
        for (var n = 1; n <= last; n++) pages.push(pdf.getPage(n));
        return Promise.all([
          pdf.getMetadata().catch(function () { return null; }),
          Promise.all(pages).then(function (list) {
            return Promise.all(list.map(function (page) {
              return page.getTextContent().then(function (content) {
                return content.items.map(function (item) {
                  return item.str + (item.hasEOL ? "\n" : " ");
                }).join("");
              });
            }));
          }),
        ]).then(function (parts) {
          var meta = parts[0] && parts[0].info ? parts[0].info : {};
          title = typeof meta.Title === "string" ? meta.Title : "";
          pdf.destroy();
          return { title: title, text: parts[1].join("\n") };
        });
      });
    });
  }

  function readUint(view, offset, size) {
    return size === 2 ? view.getUint16(offset, true) : view.getUint32(offset, true);
  }

  // Minimal ZIP reader (stored + deflate) for DOCX/XLSX packages.
  function unzip(buffer, wanted) {
    var view = new DataView(buffer);
    var bytes = new Uint8Array(buffer);
    var end = -1;
    for (var i = buffer.byteLength - 22; i >= Math.max(0, buffer.byteLength - 65557); i--) {
      if (view.getUint32(i, true) === 0x06054b50) { end = i; break; }
    }
    if (end < 0) return Promise.reject(new Error("Not a zip package"));
    var count = readUint(view, end + 10, 2);
    var pos = readUint(view, end + 16, 4);
    var decoder = new TextDecoder("utf-8");
    var entries = {};
    for (var e = 0; e < count; e++) {
      if (view.getUint32(pos, true) !== 0x02014b50) break;
      var method = readUint(view, pos + 10, 2);
      var compressed = readUint(view, pos + 20, 4);
      var nameLen = readUint(view, pos + 28, 2);
      var extraLen = readUint(view, pos + 30, 2);
      var commentLen = readUint(view, pos + 32, 2);
      var local = readUint(view, pos + 42, 4);
      var name = decoder.decode(bytes.subarray(pos + 46, pos + 46 + nameLen));
      entries[name] = { method: method, size: compressed, local: local };
      pos += 46 + nameLen + extraLen + commentLen;
    }
    var names = Object.keys(entries).filter(wanted);
    return Promise.all(names.map(function (name) {
      var entry = entries[name];
      var l = entry.local;
      if (view.getUint32(l, true) !== 0x04034b50) return Promise.resolve([name, ""]);
      var start = l + 30 + readUint(view, l + 26, 2) + readUint(view, l + 28, 2);
      var data = bytes.subarray(start, start + entry.size);
      if (entry.method === 0) return Promise.resolve([name, decoder.decode(data)]);
      if (entry.method !== 8 || typeof DecompressionStream === "undefined") {
        return Promise.resolve([name, ""]);
      }
      var stream = new Blob([data]).stream().pipeThrough(new DecompressionStream("deflate-raw"));
      return new Response(stream).arrayBuffer().then(function (out) {
        return [name, decoder.decode(out)];
      }, function () { return [name, ""]; });
    })).then(function (pairs) {
      var files = {};
      pairs.forEach(function (pair) { files[pair[0]] = pair[1]; });
      return files;
    });
  }

  function decodeEntities(text) {
    return text
      .replace(/&#x([0-9a-f]+);/gi, function (m, h) { return String.fromCodePoint(parseInt(h, 16)); })
      .replace(/&#(\d+);/g, function (m, d) { return String.fromCodePoint(parseInt(d, 10)); })
      .replace(/&lt;/g, "<").replace(/&gt;/g, ">").replace(/&quot;/g, '"')
      .replace(/&apos;/g, "'").replace(/&amp;/g, "&");
  }

  function xmlToText(xml) {
    return decodeEntities(
      xml
        .replace(/<\/w:tc>|<\/c>/g, " | ")
        .replace(/<\/w:p>|<\/w:tr>|<\/si>|<w:br\s*\/?>|<w:tab\s*\/>/g, "\n")
        .replace(/<[^>]+>/g, "")
    );
  }

  function coreTitle(xml) {
    var match = /<dc:title[^>]*>([\s\S]*?)<\/dc:title>/i.exec(xml || "");
    return match ? decodeEntities(match[1]).trim() : "";
  }

  function readDocx(buffer) {
    return unzip(buffer, function (name) {
      return name === "docProps/core.xml" || name === "word/document.xml" ||
        /^word\/(header|footer)\d*\.xml$/.test(name);
    }).then(function (files) {
      var text = Object.keys(files).filter(function (name) {
        return name.indexOf("word/") === 0;
      }).sort().map(function (name) { return xmlToText(files[name]); }).join("\n");
      return { title: coreTitle(files["docProps/core.xml"]), text: text };
    });
  }

  function readXlsx(buffer) {
    return unzip(buffer, function (name) {
      return name === "docProps/core.xml" || name === "xl/sharedStrings.xml";
    }).then(function (files) {
      return {
        title: coreTitle(files["docProps/core.xml"]),
        text: xmlToText(files["xl/sharedStrings.xml"] || ""),
      };
    });
  }

  function read(file) {
    var name = file.name.toLowerCase();
    if (file.size > MAX_BYTES) return Promise.resolve({ title: "", text: "" });
    if (/\.txt$/.test(name)) {
      return file.text().then(function (text) { return { title: "", text: text }; });
    }
    var reader = /\.pdf$/.test(name) ? readPdf
      : /\.docx$/.test(name) ? readDocx
      : /\.xlsx$/.test(name) ? readXlsx : null;
    if (!reader) return Promise.resolve({ title: "", text: "" });
    return file.arrayBuffer().then(reader);
  }

  // ---------- field extraction ----------

  function pad(value) { return String(value).padStart(2, "0"); }

  function isoDate(year, month, day) {
    var date = new Date(Date.UTC(year, month - 1, day));
    if (date.getUTCFullYear() !== year || date.getUTCMonth() !== month - 1 ||
        date.getUTCDate() !== day) return "";
    return year + "-" + pad(month) + "-" + pad(day);
  }

  function parseDate(text) {
    var match;
    if ((match = /\b(\d{4})[-\/.](\d{1,2})[-\/.](\d{1,2})\b/.exec(text))) {
      return isoDate(+match[1], +match[2], +match[3]);
    }
    if ((match = /\b(\d{1,2})[-\/.](\d{1,2})[-\/.](\d{4})\b/.exec(text))) {
      var a = +match[1], b = +match[2];
      // Month/day/year unless the first part can only be a day.
      return a > 12 ? isoDate(+match[3], b, a) : isoDate(+match[3], a, b);
    }
    if ((match = /\b(\d{1,2})(?:st|nd|rd|th)?[\s,-]+([A-Za-z]{3,9})\.?,?[\s,-]+(\d{4}|\d{2})\b/.exec(text))
        && MONTHS[match[2].slice(0, 3).toLowerCase()]) {
      // A two-digit year such as 10-Apr-26 means 2026.
      var year = match[3].length === 2 ? 2000 + +match[3] : +match[3];
      return isoDate(year, MONTHS[match[2].slice(0, 3).toLowerCase()], +match[1]);
    }
    if ((match = /\b([A-Za-z]{3,9})\.?\s+(\d{1,2})(?:st|nd|rd|th)?,?\s+(\d{4})\b/.exec(text))
        && MONTHS[match[1].slice(0, 3).toLowerCase()]) {
      return isoDate(+match[3], MONTHS[match[1].slice(0, 3).toLowerCase()], +match[2]);
    }
    return "";
  }

  // Value that follows a label, whether the text reads "Label: value",
  // "Label | value" (table cells) or "Label value" (flattened PDF text).
  // `label` and `value` are regex literals; `value` must hold one capture group.
  function labelled(text, label, value) {
    var match = new RegExp(label.source + /[\s:|]*/.source + value.source, "i").exec(text);
    return match ? match[1].trim() : "";
  }

  // A header cell holding only a dash, "N/A" or the next label means "empty".
  function cleanToken(value) {
    value = (value || "").trim();
    if (!value || /^[-–—_.\/]+$/.test(value) || /^n\/?a$/i.test(value) ||
        /^(page|revision|effectiv|document|series|date)/i.test(value)) return "";
    return value.slice(0, 30);
  }

  function usableTitle(title) {
    title = (title || "").replace(/\s+/g, " ").trim();
    // Word/Office write the source file name or a placeholder when untitled.
    if (!title || /^(untitled|microsoft (word|excel)\b.*)$/i.test(title) ||
        /\.(docx?|xlsx?|pdf|txt)$/i.test(title)) return "";
    return title.slice(0, 255);
  }

  // Controlled forms put the title directly under the header block, which
  // ends with "Page Number | 1 of 1".
  function titleUnderHeader(text) {
    var match = /page\s*(?:no\.?|number)[\s:|]*(?:(?:PAGE|NUMPAGES|Arabic|MERGEFORMAT|[\\*]+)\s*)*[\d_]*\s*(?:of\s*[\d_]+)?[\s|]*/i.exec(text);
    if (!match) return "";
    var rest = text.slice(match.index + match[0].length);
    var line = /^[^\n|]{5,255}/.exec(rest);
    return line ? line[0].trim() : "";
  }

  function extract(info) {
    var text = (info.text || "").slice(0, MAX_TEXT);
    var title = usableTitle(info.title) ||
      usableTitle(labelled(text, /\b(?:document\s+title|title)\b/, /([^\n|]{3,255})/)) ||
      usableTitle(titleUnderHeader(text));
    var dateText = labelled(text, /\beffectiv(?:e|ity)\s+date/, /([^\n|]{6,40})/);
    return {
      title: title,
      revision: cleanToken(labelled(text, /\brevision\s*(?:no\.?|number|level)/, /([^\s|]+)/)),
      effective_date: dateText ? parseDate(dateText) : "",
    };
  }

  // ---------- form wiring ----------

  function setField(form, selector, value) {
    var field = form.querySelector(selector);
    // Hidden or disabled fields are not part of this action; leave them alone.
    if (!field || field.disabled || field.closest("[hidden]")) return false;
    field.value = value;
    return true;
  }

  document.addEventListener("change", function (event) {
    var input = event.target;
    if (!input || !input.matches || !input.matches('input[type="file"][name="revision_attachment"]')) return;
    var form = input.form;
    // Softcopy Direct "create" only; requests and revisions are never auto-filled.
    if (!form || form.dataset.softcopyDirect !== "yes" ||
        form.querySelector('[name="type"]')?.value !== "softcopy_create") return;
    var note = form.querySelector("[data-file-autofill-note]");
    var file = input.files && input.files[0];
    if (!file) return;

    var mine = ++token;
    if (note) { note.hidden = false; note.textContent = "Reading the file for details…"; }
    read(file).then(extract, function () {
      return { title: "", revision: "", effective_date: "" };
    }).then(function (found) {
      if (mine !== token) return; // a newer file was chosen meanwhile
      var filled = [];
      if (setField(form, '[name="title"]', found.title) && found.title) {
        filled.push("document title");
      }
      if (setField(form, '[name="new_revision_level"]', found.revision) && found.revision) {
        filled.push("revision level");
      }
      if (setField(form, '[name="effective_date"]', found.effective_date) && found.effective_date) {
        filled.push("effectivity date");
      }
      if (note) {
        note.textContent = filled.length
          ? "Filled from the file: " + filled.join(", ") + ". Check and edit them before saving."
          : "No document title, revision level or effectivity date could be read from this file; enter them manually.";
      }
    });
  });
})();
