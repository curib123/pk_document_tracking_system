/**
 * Signatories References: optional, repeatable Title / Full Name / Position rows
 * on the softcopy create/revise forms.
 *
 * Every field is the same editable, searchable dropdown: pick a saved value or
 * type a new one. Options come from data-options on the section (distinct values
 * already saved, rendered by PHP). The three fields are independent; choosing a
 * value in one never changes another. It reuses the .searchable-* styling of the
 * existing select-based dropdowns, which cannot accept typed values.
 */
(function () {
  "use strict";

  var SECTION = "[data-signatory-references]";
  var ACTIVE_TYPES = ["softcopy_create", "softcopy_revise"];
  var MAX_ROWS = 20;
  var MAX_SHOWN = 80;

  // ---------- helpers ----------

  function normalize(value) {
    return String(value == null ? "" : value)
      .replace(/[\s ]+/g, " ")
      .trim();
  }

  function key(value) {
    return normalize(value).toLowerCase();
  }

  function optionsFor(section, field, except) {
    var data = {};
    try {
      data = JSON.parse(section.dataset.options || "{}");
    } catch (error) {
      data = {};
    }
    var seen = Object.create(null);
    var merged = [];
    function add(value) {
      var clean = normalize(value);
      var id = key(clean);
      if (!clean || seen[id]) return;
      seen[id] = true;
      merged.push(clean);
    }
    (Array.isArray(data[field]) ? data[field] : []).forEach(add);
    // Values typed in other rows of this form are offered straight away
    // (never the field being typed in, or it would suggest its own partial text).
    section
      .querySelectorAll('input[data-combobox="' + field + '"]')
      .forEach(function (input) {
        if (input !== except) add(input.value);
      });
    return merged.sort(function (a, b) {
      return a.localeCompare(b, undefined, { sensitivity: "base", numeric: true });
    });
  }

  // ---------- the editable dropdown (one shared listbox) ----------

  var list = document.createElement("div");
  list.className = "searchable-options";
  list.id = "signatory-combobox-list";
  list.setAttribute("role", "listbox");
  list.hidden = true;
  var current = null;
  var rowsShown = [];
  var active = -1;

  function mountList() {
    if (!list.isConnected) document.body.appendChild(list);
  }

  function position() {
    if (!current) return;
    var rect = current.getBoundingClientRect();
    var roomBelow = window.innerHeight - rect.bottom - 12;
    var roomAbove = rect.top - 12;
    var above = roomBelow < 180 && roomAbove > roomBelow;
    list.style.left = Math.max(8, rect.left) + "px";
    list.style.width =
      Math.max(160, Math.min(rect.width, window.innerWidth - 16)) + "px";
    list.style.maxHeight =
      Math.max(80, Math.min(285, above ? roomAbove : roomBelow)) + "px";
    list.style.top = above ? "auto" : rect.bottom + 4 + "px";
    list.style.bottom = above ? window.innerHeight - rect.top + 4 + "px" : "auto";
  }

  function close() {
    list.hidden = true;
    rowsShown = [];
    active = -1;
    if (current) {
      current.setAttribute("aria-expanded", "false");
      current.removeAttribute("aria-activedescendant");
    }
  }

  function highlight(next) {
    if (!rowsShown.length) return;
    active = (next + rowsShown.length) % rowsShown.length;
    rowsShown.forEach(function (row, i) {
      row.classList.toggle("is-active", i === active);
    });
    current.setAttribute("aria-activedescendant", rowsShown[active].id);
    rowsShown[active].scrollIntoView({ block: "nearest" });
  }

  function choose(value) {
    if (!current) return;
    var input = current;
    input.value = value;
    // Only this field changes; the other two dropdowns are never touched.
    input.dispatchEvent(new Event("input", { bubbles: true }));
    input.dispatchEvent(new Event("change", { bubbles: true }));
    close();
    input.focus();
  }

  function render() {
    list.replaceChildren();
    rowsShown = [];
    active = -1;
    current.removeAttribute("aria-activedescendant");
    var section = current.closest(SECTION);
    var all = section ? optionsFor(section, current.dataset.combobox, current) : [];
    if (!all.length) {
      // No saved values yet: typing a new one is all that is needed.
      list.hidden = true;
      return;
    }
    var query = key(current.value);
    var matches = all.filter(function (option) {
      return !query || key(option).indexOf(query) !== -1;
    });
    matches.slice(0, MAX_SHOWN).forEach(function (option, i) {
      var row = document.createElement("div");
      row.id = list.id + "-option-" + i;
      row.className = "searchable-option";
      row.setAttribute("role", "option");
      row.setAttribute("aria-selected", String(key(option) === query));
      row.textContent = option;
      row.addEventListener("pointerdown", function (event) {
        event.preventDefault();
      });
      row.addEventListener("click", function () {
        choose(option);
      });
      list.appendChild(row);
      rowsShown.push(row);
    });
    if (!matches.length || matches.length > MAX_SHOWN) {
      var note = document.createElement("p");
      note.className = "searchable-message";
      note.textContent = !matches.length
        ? "No saved match. What you type will be used as a new entry."
        : "Showing the first " + MAX_SHOWN + " matches. Type to narrow the results.";
      list.appendChild(note);
    }
    list.hidden = false;
    position();
  }

  function open(input) {
    if (input.disabled || input.readOnly) return;
    if (current && current !== input) close();
    mountList();
    current = input;
    list.setAttribute("aria-label", input.getAttribute("aria-label") || "Saved values");
    input.setAttribute("aria-controls", list.id);
    input.setAttribute("aria-expanded", "true");
    render();
    if (list.hidden) input.setAttribute("aria-expanded", "false");
  }

  function isCombobox(target) {
    return !!(target && target.matches && target.matches("input[data-combobox]"));
  }

  document.addEventListener("focusin", function (event) {
    if (isCombobox(event.target)) open(event.target);
  });
  document.addEventListener("click", function (event) {
    if (isCombobox(event.target) && list.hidden) open(event.target);
  });
  document.addEventListener("input", function (event) {
    if (!isCombobox(event.target)) return;
    if (current !== event.target) open(event.target);
    else render();
  });
  document.addEventListener("focusout", function (event) {
    if (!isCombobox(event.target)) return;
    // Tidy whitespace only; the text itself is kept exactly as typed.
    var tidy = normalize(event.target.value);
    if (tidy !== event.target.value) event.target.value = tidy;
  });
  document.addEventListener("keydown", function (event) {
    if (!isCombobox(event.target)) return;
    var input = event.target;
    if (event.key === "ArrowDown" || event.key === "ArrowUp") {
      event.preventDefault();
      if (list.hidden || current !== input) open(input);
      highlight(active + (event.key === "ArrowDown" ? 1 : -1));
    } else if (event.key === "Enter" && !list.hidden && current === input) {
      // Never submit the form from an open list; it either picks or just closes.
      event.preventDefault();
      if (active >= 0 && rowsShown[active]) rowsShown[active].click();
      else close();
    } else if (event.key === "Escape" && !list.hidden) {
      event.stopPropagation();
      event.preventDefault();
      close();
    } else if (event.key === "Tab") {
      close();
    }
  });
  document.addEventListener("pointerdown", function (event) {
    if (!current) return;
    if (event.target === current || list.contains(event.target)) return;
    close();
  });
  document.addEventListener(
    "scroll",
    function () {
      if (!list.hidden) position();
    },
    true,
  );
  window.addEventListener("resize", function () {
    if (!list.hidden) position();
  });

  // ---------- repeatable rows ----------

  function rowsOf(section) {
    return section.querySelectorAll("[data-signatory-row]");
  }

  function updateControls(section) {
    var count = rowsOf(section).length;
    var add = section.querySelector("[data-signatory-add]");
    if (add) add.disabled = count >= MAX_ROWS;
    var empty = section.querySelector("[data-signatory-empty]");
    if (empty) empty.hidden = count > 0;
    rowsOf(section).forEach(function (row, i) {
      var heading = row.querySelector("[data-signatory-number]");
      if (heading) heading.textContent = "Signatory " + (i + 1);
    });
  }

  var rowSerial = 0;

  function addRow(section, values) {
    if (rowsOf(section).length >= MAX_ROWS) return null;
    var template = section.querySelector("template[data-signatory-template]");
    if (!template) return null;
    var row = template.content.firstElementChild.cloneNode(true);
    var serial = ++rowSerial; // Global: the page can hold several signatory sections.
    row.querySelectorAll("input[data-combobox]").forEach(function (input) {
      var field = input.dataset.combobox;
      input.id = "signatory-" + field + "-" + serial;
      var label = row.querySelector('label[data-for="' + field + '"]');
      if (label) label.htmlFor = input.id;
      input.setAttribute("aria-label", label ? label.textContent.trim() : field);
      input.value = normalize((values || {})[field] || "");
    });
    section.querySelector("[data-signatory-rows]").appendChild(row);
    updateControls(section);
    return row;
  }

  function clearRows(section) {
    close();
    rowsOf(section).forEach(function (row) {
      row.remove();
    });
    updateControls(section);
  }

  // Rows only apply to creating or revising a softcopy.
  function refresh(form) {
    var type = form.querySelector('[name="type"]');
    form.querySelectorAll(SECTION).forEach(function (section) {
      var applies = !type || ACTIVE_TYPES.indexOf(type.value) !== -1;
      section.hidden = !applies;
      section.querySelectorAll("input, button").forEach(function (field) {
        field.disabled = !applies;
      });
      if (applies) updateControls(section);
    });
  }

  document.addEventListener("click", function (event) {
    var add = event.target.closest("[data-signatory-add]");
    if (add) {
      var section = add.closest(SECTION);
      var row = section && addRow(section, {});
      var first = row && row.querySelector("input[data-combobox]");
      if (first) first.focus();
      return;
    }
    var remove = event.target.closest("[data-signatory-remove]");
    if (remove) {
      var owner = remove.closest(SECTION);
      var target = remove.closest("[data-signatory-row]");
      close();
      if (target) target.remove();
      if (owner) updateControls(owner);
      return;
    }
    // Editing a saved draft request restores its signatories after the form is filled.
    var edit = event.target.closest(".js-edit");
    if (!edit) return;
    var form = document.querySelector(edit.getAttribute("data-target") || "#editForm");
    if (!form || !form.querySelector(SECTION)) return;
    var record = {};
    try {
      record = JSON.parse(edit.getAttribute("data-record") || "{}");
    } catch (error) {
      record = {};
    }
    form.querySelectorAll(SECTION).forEach(function (section) {
      clearRows(section);
      (Array.isArray(record.signatories) ? record.signatories : []).forEach(function (item) {
        addRow(section, item);
      });
    });
    refresh(form);
  });

  document.addEventListener("change", function (event) {
    if (event.target && event.target.name === "type" && event.target.form) {
      refresh(event.target.form);
    }
  });

  document.addEventListener("reset", function (event) {
    var form = event.target;
    if (!form || !form.querySelectorAll) return;
    form.querySelectorAll(SECTION).forEach(clearRows);
    setTimeout(function () {
      refresh(form);
    }, 0);
  });

  function init() {
    document.querySelectorAll("form").forEach(function (form) {
      if (form.querySelector(SECTION)) refresh(form);
    });
  }
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
