/* Shared progressive enhancements: all writes use ordinary CI3 forms, no API calls. */
(function () {
  "use strict";

  window.pkBootstrapUiReady = function () {
    if (
      window.pkBootstrapCssReady &&
      window.pkBootstrapScriptReady &&
      document.body
    ) {
      document.body.classList.remove("offline-ui");
    }
  };

  // Works on offline office networks when Bootstrap's CDN cannot be reached.
  // Keep native server form actions and confirmation semantics unchanged.
  var offlineBootstrap = null;
  if (!window.bootstrap) {
    document.body.classList.add("offline-ui");

    // Native forms keep working when the office network cannot load Bootstrap.
    // Reuse modal instances and restore keyboard focus like the full component.
    var fallbackInstances = new WeakMap();
    var fallbackOpenModal = null;

    function modalFocusables(modal) {
      return Array.from(
        modal.querySelectorAll(
          'button:not([disabled]), input:not([disabled]):not([type="hidden"]), ' +
            'select:not([disabled]), textarea:not([disabled]), a[href], [tabindex="0"]',
        ),
      ).filter(function (node) {
        return !node.closest("[hidden]") && node.getClientRects().length > 0;
      });
    }

    window.bootstrap = {
      Modal: {
        getOrCreateInstance: function (element) {
          if (!element) return { show: function () {}, hide: function () {} };
          if (fallbackInstances.has(element))
            return fallbackInstances.get(element);
          var returnFocus = null;
          var instance = {
            show: function () {
              if (element.classList.contains("show")) return;
              if (fallbackOpenModal && fallbackOpenModal !== element) {
                window.bootstrap.Modal.getOrCreateInstance(
                  fallbackOpenModal,
                ).hide();
              }
              returnFocus = document.activeElement;
              element.style.display = "block";
              element.classList.add("show");
              element.removeAttribute("aria-hidden");
              element.setAttribute("role", "dialog");
              element.setAttribute("aria-modal", "true");
              document.body.classList.add("modal-open");
              fallbackOpenModal = element;
              if (!document.querySelector(".fallback-backdrop")) {
                var backdrop = document.createElement("div");
                backdrop.className = "modal-backdrop fallback-backdrop show";
                document.body.appendChild(backdrop);
              }
              var focusable = modalFocusables(element);
              if (focusable.length) focusable[0].focus();
            },
            hide: function () {
              if (!element.classList.contains("show")) return;
              if (element.contains(document.activeElement))
                document.activeElement.blur();
              element.style.display = "none";
              element.classList.remove("show");
              element.setAttribute("aria-hidden", "true");
              element.removeAttribute("aria-modal");
              document
                .querySelectorAll(".fallback-backdrop")
                .forEach(function (node) {
                  node.remove();
                });
              document.body.classList.remove("modal-open");
              fallbackOpenModal = null;
              if (
                returnFocus &&
                returnFocus.isConnected &&
                !returnFocus.closest("[hidden]")
              ) {
                returnFocus.focus();
              }
              element.dispatchEvent(new Event("hidden.bs.modal"));
            },
          };
          fallbackInstances.set(element, instance);
          return instance;
        },
      },
    };
    offlineBootstrap = window.bootstrap;

    document.addEventListener("click", function (event) {
      if (window.bootstrap !== offlineBootstrap) return;
      var trigger = event.target.closest("[data-bs-toggle]");
      if (trigger) {
        var type = trigger.getAttribute("data-bs-toggle");
        var selector = trigger.getAttribute("data-bs-target");
        if (type === "modal" && selector) {
          var modal = document.querySelector(selector);
          if (modal) window.bootstrap.Modal.getOrCreateInstance(modal).show();
        } else if (type === "dropdown") {
          var menu = trigger
            .closest(".dropdown")
            ?.querySelector(".dropdown-menu");
          if (menu) {
            var isOpen = !menu.classList.contains("show");
            document
              .querySelectorAll(".offline-ui .dropdown-menu.show")
              .forEach(function (node) {
                node.classList.remove("show");
              });
            menu.classList.toggle("show", isOpen);
            trigger.setAttribute("aria-expanded", String(isOpen));
          }
        } else if (type === "collapse" && selector) {
          var pane = document.querySelector(selector);
          if (pane) {
            var isExpanded = !pane.classList.contains("show");
            pane.classList.toggle("show", isExpanded);
            trigger.classList.toggle("collapsed", !isExpanded);
            trigger.setAttribute("aria-expanded", String(isExpanded));
          }
        }
      }
      var dismiss = event.target.closest('[data-bs-dismiss="modal"]');
      if (dismiss) {
        var parentModal = dismiss.closest(".modal");
        if (parentModal)
          window.bootstrap.Modal.getOrCreateInstance(parentModal).hide();
      }
      // A click on the backdrop must not dismiss an open modal:
      // only Close/Cancel ([data-bs-dismiss]) may discard form input.
      if (!event.target.closest(".dropdown")) {
        document
          .querySelectorAll(".offline-ui .dropdown-menu.show")
          .forEach(function (menu) {
            menu.classList.remove("show");
            var button = menu
              .closest(".dropdown")
              ?.querySelector('[data-bs-toggle="dropdown"]');
            if (button) button.setAttribute("aria-expanded", "false");
          });
      }
    });

    document.addEventListener("keydown", function (event) {
      if (window.bootstrap !== offlineBootstrap) return;
      if (event.key === "Escape") {
        if (fallbackOpenModal) {
          // Escape may close dropdowns, but must never close a modal.
          // Its draft form values stay available for explicit Cancel.
          event.preventDefault();
        }
        document
          .querySelectorAll(".offline-ui .dropdown-menu.show")
          .forEach(function (menu) {
            menu.classList.remove("show");
          });
      }
      if (event.key !== "Tab" || !fallbackOpenModal) return;
      var nodes = modalFocusables(fallbackOpenModal);
      if (!nodes.length) return;
      var first = nodes[0],
        last = nodes[nodes.length - 1];
      if (
        event.shiftKey &&
        (document.activeElement === first ||
          !fallbackOpenModal.contains(document.activeElement))
      ) {
        event.preventDefault();
        last.focus();
      } else if (
        !event.shiftKey &&
        (document.activeElement === last ||
          !fallbackOpenModal.contains(document.activeElement))
      ) {
        event.preventDefault();
        first.focus();
      }
    });
  }

  // Keep detail modals human-readable even when the server returns nested
  // associative arrays or a previously serialized sections value.
  function readableDetail(value, depth) {
    if (value == null || value === "") return "—";
    if (depth > 4) return "More details available";
    if (Array.isArray(value)) {
      return value.length
        ? value
            .map(function (item) {
              return readableDetail(item, depth + 1);
            })
            .join("\n")
        : "—";
    }
    if (typeof value === "object") {
      return (
        Object.entries(value)
          .map(function (pair) {
            return pair[0] + ": " + readableDetail(pair[1], depth + 1);
          })
          .join("\n") || "—"
      );
    }
    if (typeof value === "boolean") return value ? "Yes" : "No";
    return String(value);
  }

  function renderRecordDetails(container, data) {
    container.replaceChildren();
    if (window.PKDocumentView) {
      window.PKDocumentView.render(container, data);
      return;
    }
    if (typeof data === "string") {
      try {
        data = JSON.parse(data);
      } catch (e) {
        data = { Details: data };
      }
    }
    if (!data || typeof data !== "object") data = { Details: data };
    var sections = data.sections;
    if (typeof sections === "string") {
      try {
        sections = JSON.parse(sections);
      } catch (e) {
        sections = null;
      }
    }
    if (!Array.isArray(sections))
      sections = [{ title: "Record Details", fields: data }];
    sections.forEach(function (section) {
      if (!section || typeof section !== "object")
        section = { fields: { Details: section } };
      var block = document.createElement("section");
      block.className = "document-detail-section";
      if (section.title) {
        var heading = document.createElement("h3");
        heading.className = "h6 document-detail-heading";
        heading.textContent = readableDetail(section.title, 0);
        block.append(heading);
      }
      var fields = section.fields || {};
      if (Array.isArray(fields)) {
        fields = Object.fromEntries(
          fields.map(function (v, i) {
            return [String(i + 1), v];
          }),
        );
      }
      var entries =
        fields && typeof fields === "object"
          ? Object.entries(fields)
          : [["Details", fields]];
      if (!entries.length) {
        var empty = document.createElement("p");
        empty.className = "small text-secondary";
        empty.textContent = section.empty || "No details are available.";
        block.append(empty);
      } else {
        var list = document.createElement("dl");
        list.className = "detail-grid mb-0";
        entries.forEach(function (pair) {
          var term = document.createElement("dt"),
            value = document.createElement("dd");
          term.textContent = pair[0];
          value.textContent = readableDetail(pair[1], 0);
          list.append(term, value);
        });
        block.append(list);
      }
      (Array.isArray(section.links) ? section.links : []).forEach(
        function (item) {
          if (!item || !item.url) return;
          try {
            var url = new URL(item.url, window.location.origin);
            if (
              url.origin !== window.location.origin ||
              !["http:", "https:"].includes(url.protocol)
            )
              return;
            var link = document.createElement("a");
            link.className = "btn btn-sm btn-outline-primary mt-2 me-2";
            link.href = url.href;
            link.textContent = readableDetail(item.label || "Open file", 0);
            block.append(link);
          } catch (error) {
            /* Reject malformed destinations. */
          }
        },
      );
      container.append(block);
    });
  }

  // Each data cell opens the exact same reusable View modal as the eye
  // action; action buttons and links remain independent and never trigger it.
  var recordViewRequest = 0;
  function openRecordDetails(serialized, recordTitle) {
    recordViewRequest++;
    var modal = document.getElementById("viewModal");
    var details = document.getElementById("viewDetails");
    var heading = document.getElementById("viewModalTitle");
    if (!modal || !details) return;
    try {
      var data = JSON.parse(serialized);
      if (!data || typeof data !== "object")
        throw new Error("Invalid record details");
      renderRecordDetails(details, data);
      if (heading && !window.PKDocumentView)
        heading.textContent = recordTitle || "View Details";
    } catch (error) {
      if (window.PKDocumentView) window.PKDocumentView.reset();
      if (heading) heading.textContent = "Details unavailable";
      var notice = document.createElement("p");
      notice.className = "text-danger mb-0";
      notice.setAttribute("role", "alert");
      notice.textContent =
        "Unable to display this record. Refresh the page and try again.";
      details.replaceChildren(notice);
      console.error("Record details could not be rendered:", error);
    }
    window.bootstrap.Modal.getOrCreateInstance(modal).show();
  }
  function openRemoteRecordDetails(button) {
    openRecordDetails(
      JSON.stringify({
        kind: "record",
        header: { title: button.getAttribute("data-title") },
        sections: [],
      }),
      button.getAttribute("data-title"),
    );
    var request = recordViewRequest;
    var details = document.getElementById("viewDetails");
    if (!details) return;
    var loading = document.createElement("div");
    loading.className = "doc-assignment-loading";
    loading.setAttribute("role", "status");
    var spinner = document.createElement("span");
    spinner.className = "spinner-border spinner-border-sm";
    spinner.setAttribute("aria-hidden", "true");
    loading.append(
      spinner,
      document.createTextNode(" Loading assigned documents…"),
    );
    details.replaceChildren(loading);
    Promise.resolve()
      .then(function () {
        var url = new URL(
          button.getAttribute("data-view-url"),
          window.location.origin,
        );
        if (
          url.origin !== window.location.origin ||
          !["http:", "https:"].includes(url.protocol)
        )
          throw new Error("Invalid view destination");
        return fetch(url.href, {
          credentials: "same-origin",
          cache: "no-store",
          headers: { Accept: "application/json" },
        });
      })
      .then(function (response) {
        if (!response.ok) throw new Error("Details unavailable");
        return response.json();
      })
      .then(function (data) {
        if (request !== recordViewRequest) return;
        if (!data || data.kind !== "record" || !Array.isArray(data.sections))
          throw new Error("Invalid details");
        renderRecordDetails(details, data);
      })
      .catch(function () {
        if (request !== recordViewRequest) return;
        var notice = document.createElement("div");
        notice.className = "doc-assignment-loading";
        notice.setAttribute("role", "alert");
        var message = document.createElement("p");
        message.textContent =
          "Unable to load assigned documents. Please try again.";
        var retry = document.createElement("button");
        retry.type = "button";
        retry.className = "btn btn-sm btn-outline-primary";
        retry.textContent = "Retry";
        retry.addEventListener("click", function () {
          openRemoteRecordDetails(button);
        });
        notice.append(message, retry);
        details.replaceChildren(notice);
      });
  }
  var sharedViewModal = document.getElementById("viewModal");
  if (sharedViewModal)
    sharedViewModal.addEventListener("hidden.bs.modal", function () {
      recordViewRequest++;
    });
  function showTableCellDetails(row) {
    if (!row || !row.hasAttribute("data-row-view")) return;
    openRecordDetails(
      row.getAttribute("data-row-view"),
      row.getAttribute("data-row-title"),
    );
  }
  document.addEventListener("click", function (event) {
    var cell = event.target.closest("td.table-cell-view");
    if (!cell || event.target.closest("a,button,input,select,textarea,label"))
      return;
    showTableCellDetails(cell.closest("tr[data-row-view]"));
  });
  document.addEventListener("keydown", function (event) {
    if (event.key !== "Enter" && event.key !== " ") return;
    var cell = event.target.closest("td.table-cell-view");
    if (!cell || event.target !== cell) return;
    event.preventDefault();
    showTableCellDetails(cell.closest("tr[data-row-view]"));
  });

  var sidebar = document.getElementById("appSidebar");
  var toggle = document.getElementById("sidebarToggle");
  var sidebarBackdrop = document.getElementById("sidebarBackdrop");
  var mobileNavigation = window.matchMedia("(max-width: 991.98px)");
  function setSidebar(open, returnFocus) {
    if (!sidebar || !toggle) return;
    open = !!open && mobileNavigation.matches;
    sidebar.classList.toggle("is-open", open);
    sidebar.inert = mobileNavigation.matches && !open;
    if (mobileNavigation.matches)
      sidebar.setAttribute("aria-hidden", open ? "false" : "true");
    else sidebar.removeAttribute("aria-hidden");
    toggle.setAttribute("aria-expanded", String(open));
    if (sidebarBackdrop) sidebarBackdrop.hidden = !open;
    document.body.classList.toggle("sidebar-open", open);
    if (open) sidebar.querySelector("button,a")?.focus();
    else if (returnFocus) toggle.focus();
  }
  toggle?.addEventListener("click", function () {
    setSidebar(!sidebar?.classList.contains("is-open"), true);
  });
  sidebarBackdrop?.addEventListener("click", function () {
    setSidebar(false, true);
  });
  sidebar?.addEventListener("click", function (event) {
    if (event.target.closest("a")) setSidebar(false, false);
  });
  mobileNavigation.addEventListener("change", function () {
    setSidebar(false, false);
  });
  document.addEventListener("keydown", function (event) {
    if (
      !sidebar?.classList.contains("is-open") ||
      document.querySelector(".modal.show")
    )
      return;
    if (event.key === "Escape") {
      event.preventDefault();
      setSidebar(false, true);
    }
    if (event.key === "Tab") {
      var focusable = Array.from(
        sidebar.querySelectorAll("button,a[href]"),
      ).filter(function (node) {
        return node.offsetParent !== null;
      });
      var first = focusable[0],
        last = focusable[focusable.length - 1];
      if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last?.focus();
      } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first?.focus();
      }
    }
  });
  setSidebar(false, false);

  function parse(button, key) {
    try {
      return JSON.parse(button.getAttribute("data-" + key) || "{}");
    } catch (error) {
      return {};
    }
  }

  function syncUserRoles(form, selected) {
    if (!form || !form.hasAttribute("data-user-role-form")) return;
    var primary = Number(form.querySelector('[name="role_id"]').value);
    form.querySelectorAll('input[name="role_ids[]"]').forEach(function (field) {
      if (selected)
        field.checked = selected.map(Number).includes(Number(field.value));
      field.disabled = primary > 0 && Number(field.value) === primary;
      if (field.disabled) field.checked = true;
    });
  }
  document.addEventListener("change", function (event) {
    if (event.target.matches("[data-user-primary-role]"))
      syncUserRoles(event.target.form);
  });
  document.addEventListener("reset", function (event) {
    if (event.target.hasAttribute("data-user-role-form"))
      setTimeout(function () {
        syncUserRoles(event.target);
      }, 0);
  });

  function updateSoftcopyCategoryKind(form) {
    if (!form?.hasAttribute("data-softcopy-category-setup")) return;
    var select = form.querySelector("[data-softcopy-category-type]");
    var codeWrap = form.querySelector("[data-category-number-pattern]");
    var code = form.querySelector('[name="code_pattern"]');
    if (!select || !codeWrap || !code) return;

    var isFolder = select.value === "folder";
    codeWrap.hidden = isFolder;
    code.disabled = isFolder;
    code.required = !isFolder;
    // A folder is a hierarchy node, never a numbered document category.
    // Preserve a typed code when temporarily switching types; the server
    // discards it if Folder is selected and never uses it for numbering.
    var parent = form.querySelector('[name="parent_id"]');
    if (parent) {
      var selected = parent.value;
      parent.querySelectorAll("[data-category-parent-kind]").forEach(function (option) {
        option.disabled = option.dataset.categoryParentKind !== "folder" &&
          option.value !== selected;
      });
    }
  }

  document.addEventListener("change", function (event) {
    if (event.target?.matches("[data-softcopy-category-type]")) {
      updateSoftcopyCategoryKind(event.target.form);
    }
  });
  document.addEventListener("reset", function (event) {
    if (!event.target?.hasAttribute("data-softcopy-category-setup")) return;
    setTimeout(function () { updateSoftcopyCategoryKind(event.target); }, 0);
  });

  document.addEventListener("click", function (event) {
    var edit = event.target.closest(".js-edit");
    if (edit) {
      var target = document.querySelector(
        edit.getAttribute("data-target") || "#editForm",
      );
      if (!target) return;
      target.reset();
      var record = parse(edit, "record");
      var lockedAttachment = !!record.locked_attachment;
      target
        .querySelectorAll('[name="revision_attachment"], [name="external_attachment[]"]')
        .forEach(function (field) {
          field.disabled = lockedAttachment;
          var message = field.parentElement?.querySelector(
            "[data-locked-attachment-message]",
          );
          if (message) message.hidden = !lockedAttachment;
        });
      target
        .querySelectorAll('input[name="permissions[]"]')
        .forEach(function (field) {
          field.checked = (record.permission_ids || [])
            .map(Number)
            .includes(Number(field.value));
        });
      target.querySelectorAll("[name]").forEach(function (field) {
        if (!Object.prototype.hasOwnProperty.call(record, field.name)) return;
        if (
          field.name === "document_number" &&
          field.tagName === "SELECT" &&
          record[field.name] != null
        ) {
          field.dataset.pendingValue = record[field.name];
        }
        if (field.type === "checkbox")
          field.checked = !!Number(record[field.name]);
        else field.value = record[field.name] == null ? "" : record[field.name];
      });
      syncUserRoles(target, record.role_ids || [record.role_id]);
      var heading = document.getElementById(
        target.id === "stepForm"
          ? "stepTitle"
          : target.id === "assignmentForm"
            ? "assignmentModalTitle"
            : "editTitle",
      );
      if (heading)
        heading.textContent = edit.getAttribute("data-title") || "Edit Record";
      target.dataset.confirmed = "";
      updateRequestActionFields();
      if (target.id === "stepForm") updateStepApprover();
      if (target.hasAttribute("data-location-upsert"))
        updateLocationHierarchy();
      if (target.hasAttribute("data-hardcopy-upsert"))
        updateHardcopyHierarchy();
      updateDocumentDomain();
      updateDisposalReason();
      updateHardcopyRequestAction();
      if (target.id === "assignmentForm") updateDocumentDomain();
      updateSoftcopyCategoryKind(target);
      updateHardcopyRetention();
      updateHardcopyTransfer();
      syncSearchableControls(target);
    }

    var view = event.target.closest(".js-view");
    if (view) {
      event.preventDefault();
      if (view.hasAttribute("data-view-url")) openRemoteRecordDetails(view);
      else
        openRecordDetails(
          view.getAttribute("data-display"),
          view.getAttribute("data-title"),
        );
    }

    var fileHistory = event.target.closest(".js-file-history");
    if (fileHistory) {
      var list = document.getElementById("fileHistoryList");
      if (list) {
        list.replaceChildren();
        var files = parse(fileHistory, "files");
        if (!Array.isArray(files)) files = [];
        files.forEach(function (file) {
          var item = document.createElement("article");
          item.className = "file-history-item";
          var emblem = document.createElement("span");
          emblem.className = "file-history-item-icon";
          var ico = document.createElement("i");
          ico.className = "fa-regular fa-file-lines";
          ico.setAttribute("aria-hidden", "true");
          emblem.append(ico);
          var meta = document.createElement("div");
          meta.className = "file-history-item-text";
          var name = document.createElement("strong");
          name.textContent = String(file.name || "Document revision");
          var small = document.createElement("small");
          small.textContent = String(file.detail || "");
          meta.append(name, small);
          item.append(emblem, meta);
          try {
            var target = file.url
              ? new URL(String(file.url), window.location.origin)
              : null;
            if (
              target &&
              target.origin === window.location.origin &&
              ["http:", "https:"].includes(target.protocol)
            ) {
              var download = document.createElement("a");
              download.className = "btn btn-outline-primary btn-sm";
              download.href = target.href;
              download.textContent = "Download";
              item.append(download);
            }
            var printTarget = file.print_url
              ? new URL(String(file.print_url), window.location.origin)
              : null;
            if (
              printTarget &&
              printTarget.origin === window.location.origin &&
              ["http:", "https:"].includes(printTarget.protocol)
            ) {
              var print = document.createElement("a");
              print.className = "btn btn-outline-primary btn-sm";
              print.href = printTarget.href;
              print.target = "_blank";
              print.rel = "noopener";
              print.textContent = "Print";
              item.append(print);
            }
          } catch (error) {
            /* Invalid download links are never rendered. */
          }
          list.append(item);
        });
      }
    }

    var upload = event.target.closest(".js-upload");
    if (upload) {
      document.querySelector("#uploadModal form")?.reset();
      document.getElementById("uploadDocumentId").value =
        upload.getAttribute("data-document-id") || "";
      document.getElementById("uploadDocumentName").textContent =
        upload.getAttribute("data-document-name") || "";
    }
  });

  // One request modal is reused across all request types; hide irrelevant fields.
  function updateRequestActionFields() {
    var select = document.getElementById("reqType");
    if (!select) return;
    var allowed;
    try {
      allowed = JSON.parse(select.form.dataset.allowedRequestTypes || "null");
    } catch (error) {
      allowed = [];
    }
    var editing = !!select.form.querySelector('[name="id"]')?.value;
    var current = select.value;
    Array.from(select.options).forEach(function (option) {
      var permitted =
        (!Array.isArray(allowed) || allowed.includes(option.value)) &&
        (!editing || option.value === current);
      option.disabled = option.hidden = !permitted;
    });
    if (select.selectedOptions[0]?.disabled || !select.value) {
      select.value =
        Array.from(select.options).find(function (option) {
          return !option.disabled;
        })?.value || "";
    }
    var operation = select.value;
    var required = {
      softcopy_id: [
        "softcopy_revise",
        "assignment",
        "access",
      ],
      hardcopy_id: ["hardcopy_update", "disposal", "transfer"],
      external_document_id: ["external_revise", "disposal"],
      document_domain: ["disposal"],
      title: [
        "softcopy_create",
        "hardcopy_create",
        "hardcopy_update",
        "softcopy_revise",
        "external_create",
        "external_revise",
      ],
      generated_document_number: ["softcopy_create"],
      category_id: ["softcopy_create"],
      recipient_id: ["assignment", "access", "transfer"],
      expires_at: ["access"],
      destination_location_id: ["transfer"],
      effective_date: ["softcopy_create", "softcopy_revise"],
      // Received and released dates are recorded by PHP, not user inputs.
      page_number: ["softcopy_revise"],
      new_revision_level: ["softcopy_create", "softcopy_revise"],
      revision_attachment: ["softcopy_create", "softcopy_revise"],
      external_attachment: ["external_create", "external_revise"],
    };
    Object.keys(required).forEach(function (name) {
      var field = select.form.querySelector('[name="' + name + '"]');
      if (!field) return;
      var show = required[name].includes(operation);
      var selectedDomain = select.form.querySelector(
        '[name="document_domain"]',
      )?.value;
      if (selectedDomain && ["assignment", "access", "disposal"].includes(operation)) {
        if (name === "softcopy_id") show = selectedDomain === "softcopy";
        if (name === "hardcopy_id") show = selectedDomain === "hardcopy";
        if (name === "external_document_id") show = selectedDomain === "external";
      }
      var parent =
        field.closest("[data-document-domain]") ||
        field.closest(".col-md-6, .col-12");
      if (parent) parent.hidden = !show;
      field.disabled = !show;
      // Requests may retain a staged revision, but direct revisions
      // must always attach a file for immediate approval.
      var direct = select.form.dataset.softcopyDirect === "yes";
      field.required =
        show &&
        (name !== "revision_attachment" || direct) &&
        name !== "series_number" &&
        name !== "effective_date";
      if (
        name === "revision_attachment" &&
        !direct &&
        !select.form.querySelector('[name="id"]')?.value
      ) {
        field.required = show;
      }
    });
    updateDirectRevisionLevel(select.form, operation);
    updateSoftcopyNumberFields(select.form);
    updateExternalRequestFields(select.form);
  }

  function updateDirectRevisionLevel(form, operation) {
    var level = form.querySelector("[data-direct-revision-level]");
    if (!level) return;
    var create = operation === "softcopy_create";
    var revise = operation === "softcopy_revise";
    var wrap = level.closest("[data-direct-revision-level-wrap]");
    if (wrap) wrap.hidden = !create && !revise;
    level.disabled = !create && !revise;
    level.required = create || revise;
    if (create) {
      level.value = "00";
      return;
    } else if (!revise) {
      level.value = "";
      return;
    }
    var source = form.querySelector("#reqSoftcopy");
    var record = {};
    try {
      record = JSON.parse(source?.selectedOptions[0]?.dataset.softcopyRecord || "{}");
    } catch (error) {
      record = {};
    }
    var currentNumber = Number(record.revision_number || 0);
    level.value = String(Math.max(0, currentNumber)).padStart(2, "0");
  }

  function updateExternalRequestFields(form) {
    var action = form.querySelector('[name="type"]');
    var existing = form.querySelector("[data-external-existing]");
    if (!action || !existing) return;
    var revise = action.value === "external_revise";
    existing.hidden = !revise;
    existing.querySelectorAll("select").forEach(function (field) {
      field.disabled = !revise;
      field.required = revise;
      if (!revise) field.value = "";
    });
    form.querySelectorAll("[data-external-description]").forEach(function (field) {
      var externalAction = ["external_create", "external_revise"].includes(action.value);
      var parent = field.closest(".col-md-6, .col-12");
      if (parent) parent.hidden = !externalAction;
      field.disabled = !externalAction;
      field.required = false;
      if (!externalAction) field.value = "";
    });
    form.querySelectorAll("[data-external-category]").forEach(function (parent) {
      var externalAction = ["external_create", "external_revise"].includes(action.value);
      parent.hidden = !externalAction;
      parent.querySelectorAll("select").forEach(function (field) {
        field.disabled = !externalAction;
      });
    });
    var attachment = form.querySelector('[name="external_attachment"]');
    if (attachment) {
      attachment.required = !form.querySelector('[name="id"]')?.value;
    }
  }

  function updateRetentionPeriod(form) {
    var startField = form.querySelector('[name="retention_start_date"]');
    var endField = form.querySelector('[name="retention_end_date"]');
    var periodField = form.querySelector("[data-retention-period]");
    if (!startField || !endField || !periodField) return;
    if (!startField.value || !endField.value || endField.value < startField.value) {
      periodField.value = "";
      endField.setCustomValidity(
        startField.value && endField.value && endField.value < startField.value
          ? "Retention end date must be on or after the start date."
          : "",
      );
      return;
    }
    endField.setCustomValidity("");

    var startParts = startField.value.split("-").map(Number);
    var endParts = endField.value.split("-").map(Number);
    var startDate = new Date(Date.UTC(startParts[0], startParts[1] - 1, startParts[2]));
    var endDate = new Date(Date.UTC(endParts[0], endParts[1] - 1, endParts[2]));
    function afterMonths(date, count) {
      var monthIndex = date.getUTCFullYear() * 12 + date.getUTCMonth() + count;
      var year = Math.floor(monthIndex / 12);
      var month = monthIndex % 12;
      var lastDay = new Date(Date.UTC(year, month + 1, 0)).getUTCDate();
      return new Date(Date.UTC(year, month, Math.min(date.getUTCDate(), lastDay)));
    }
    function dateValue(date) {
      return date.getTime();
    }
    var years = endDate.getUTCFullYear() - startDate.getUTCFullYear();
    var cursor = afterMonths(startDate, years * 12);
    if (dateValue(cursor) > dateValue(endDate)) {
      years -= 1;
      cursor = afterMonths(startDate, years * 12);
    }
    var months =
      (endDate.getUTCFullYear() - cursor.getUTCFullYear()) * 12 +
      endDate.getUTCMonth() -
      cursor.getUTCMonth();
    cursor = afterMonths(cursor, months);
    if (dateValue(cursor) > dateValue(endDate)) {
      months -= 1;
      cursor = afterMonths(afterMonths(startDate, years * 12), months);
    }
    var days = Math.round((dateValue(endDate) - dateValue(cursor)) / 86400000);
    var parts = [];
    [[years, "year"], [months, "month"], [days, "day"]].forEach(function (part) {
      if (part[0] > 0) parts.push(part[0] + " " + part[1] + (part[0] === 1 ? "" : "s"));
    });
    periodField.value = parts.join(", ") || "0 days";
  }

  function updateSoftcopyNumberFields(form) {
    var category = form.querySelector('[name="category_id"]');
    var checker = form.querySelector("[data-series-number-checker]");
    var autoField = form.querySelector('[name="generated_document_number"]');
    var manualField = form.querySelector('[name="document_number"]');
    var patternField = form.querySelector('[name="series_code_pattern"]');
    var seriesField = form.querySelector('[name="generated_series_number"]');
    var originalField = form.querySelector("#reqOriginalSeries");
    if (!category || !checker || !autoField || !manualField || !seriesField || !patternField) return;

    var create = form.querySelector('[name="type"]')?.value === "softcopy_create";
    var manual = create && checker.checked;
    var autoWrap = form.querySelector("[data-document-number-auto]");
    var manualWrap = form.querySelector("[data-document-number-manual]");
    var checkerWrap = form.querySelector("[data-series-number-checker-wrap]");
    var seriesWrap = form.querySelector("[data-series-number-field]");
    var patternWrap = form.querySelector("[data-series-pattern-setup]");
    var originalWrap = form.querySelector("[data-original-series-preview]");
    checker.disabled = !create;
    if (!create) checker.checked = false;
    if (checkerWrap) checkerWrap.hidden = !create;
    if (autoWrap) autoWrap.hidden = !create || manual;
    if (manualWrap) manualWrap.hidden = !manual;
    autoField.disabled = !create || manual;
    autoField.required = create && !manual;
    manualField.disabled = !manual;
    manualField.required = manual;
    seriesField.disabled = true; // Read-only preview, never posted.
    seriesField.required = false;

    var selected = category.options[category.selectedIndex];
    var docPattern = selected?.dataset.codePattern || "";
    var parts = docPattern.match(/^(.*?)(\d+)$/);
    var prefix = parts ? parts[1] : "";
    var initial = parts ? Number(parts[2]) : 0;
    var width = parts ? parts[2].length : 0;
    var numberOptions = {};
    try {
      numberOptions = JSON.parse(selected?.dataset.numberOptions || "{}");
    } catch (error) {
      numberOptions = {};
    }

    var currentNumber = manualField.value || manualField.dataset.pendingValue || "";
    manualField.replaceChildren(new Option("Choose an existing document number", ""));
    Object.keys(numberOptions).sort().forEach(function (number) {
      manualField.add(new Option(number, number));
    });
    manualField.value = Object.prototype.hasOwnProperty.call(numberOptions, currentNumber)
      ? currentNumber
      : "";
    delete manualField.dataset.pendingValue;

    var maximum = 0;
    var hasMatching = false;
    Object.keys(numberOptions).forEach(function (number) {
      if (!number.startsWith(prefix)) return;
      var suffix = number.slice(prefix.length);
      if (!/^\d+$/.test(suffix)) return;
      hasMatching = true;
      var value = Number(suffix);
      if (value >= maximum) {
        maximum = value;
        width = Math.max(width, suffix.length);
      }
    });
    if (selected?.dataset.nextNumber) {
      autoField.value = selected.dataset.nextNumber;
    } else if (prefix && initial > 0) {
      var nextDocument = String(hasMatching ? Math.max(initial, maximum + 1) : initial);
      autoField.value = prefix + nextDocument.padStart(Math.max(width, nextDocument.length), "0");
    } else {
      autoField.value = "";
    }

    // Each Document No. has its OWN first-time code pattern, not the category.
    var info = numberOptions[manualField.value] || {};
    var previouslyUsed = Array.isArray(info.series) ? info.series : [];
    var storedPattern = info.series_code_pattern || "";
    var needsSetup = manual && !!manualField.value && !storedPattern;
    if (patternWrap) patternWrap.hidden = !needsSetup;
    patternField.disabled = !needsSetup;
    patternField.required = needsSetup;
    if (manual && storedPattern) patternField.value = "";
    if (seriesWrap) seriesWrap.hidden = !manual || !manualField.value;
    if (originalWrap) originalWrap.hidden = true;
    seriesField.value = "";
    if (originalField) originalField.value = "";

    var seriesPattern = storedPattern || (needsSetup ? patternField.value.trim() : "");
    var seriesParts = seriesPattern.match(/^(.*?)(\d+)$/);
    var previewError = "";
    if (manual && manualField.value && seriesParts && BigInt(seriesParts[2]) > 0n) {
      var seriesPrefix = seriesParts[1];
      var start = BigInt(seriesParts[2]);
      var highest = 0n;
      var seriesWidth = seriesParts[2].length;
      previouslyUsed.forEach(function (series) {
        if (series.slice(0, seriesPrefix.length).toLowerCase() !== seriesPrefix.toLowerCase()) return;
        var digits = series.slice(seriesPrefix.length);
        if (!/^\d+$/.test(digits)) return;
        var seq = BigInt(digits);
        if (seq > highest) highest = seq;
        seriesWidth = Math.max(seriesWidth, digits.length);
      });
      var unnumbered = Number(info.unnumbered_count || 0);
      if (needsSetup && unnumbered === 1) {
        var preceding = seriesPrefix + String(start - 1n).padStart(seriesWidth, "0");
        if (originalField) originalField.value = preceding;
        if (originalWrap) originalWrap.hidden = false;
        if (previouslyUsed.some(function (value) {
          return value.toLowerCase() === preceding.toLowerCase();
        })) previewError = "Original series conflicts with an existing series. Choose a different first-series pattern.";
        if (start - 1n > highest) highest = start - 1n;
      } else if (needsSetup && unnumbered > 1) {
        previewError = "Multiple original records have no series. Resolve them before first setup.";
      }
      var nextSeries = highest + 1n > start ? highest + 1n : start;
      var digits = String(nextSeries);
      var preview = seriesPrefix + digits.padStart(Math.max(seriesWidth, digits.length), "0");
      if (preview.length <= 100) seriesField.value = preview;
    }

    var seriesGuide = form.querySelector("[data-series-number-guide]");
    if (seriesGuide) {
      seriesGuide.hidden = !manual || !manualField.value;
      seriesGuide.textContent = previewError || (needsSetup
        ? "First-time Series No. setup. The original document takes one less than the new first series. Both assignments occur on final approval."
        : "Series No. Code Pattern was already configured for this Document No. Next series is assigned on final approval.");
    }
  }
  // Selecting a different softcopy starts from that record, never a previous
  // document's proposed title, revision attachment or effective date.
  document.addEventListener("change", function (event) {
    if (event.target?.id !== "reqSoftcopy") return;
    var source = event.target,
      form = source.form;
    var record = {};
    try {
      record = JSON.parse(
        source.selectedOptions[0]?.dataset.softcopyRecord || "{}",
      );
    } catch (error) {}
    ["title", "document_number", "series_number", "category_id"].forEach(
      function (name) {
        var field = form.querySelector('[name="' + name + '"]');
        if (field) field.value = record[name] ?? "";
      },
    );
    ["effective_date", "revision_attachment"].forEach(
      function (name) {
        var field = form.querySelector('[name="' + name + '"]');
        if (field) field.value = "";
      },
    );
    updateDirectRevisionLevel(form, form.querySelector('[name="type"]')?.value);
    var subject = form.querySelector('[name="subject"]');
    if (
      subject &&
      (!subject.value || subject.value === subject.dataset.autoSubject)
    ) {
      subject.value = source.value
        ? "Document action: " + (record.title || "Selected softcopy")
        : "";
      subject.dataset.autoSubject = subject.value;
    }
  });
  document.addEventListener("input", function (event) {
    if (event.target?.id === "reqSeriesPattern") updateSoftcopyNumberFields(event.target.form);
    if (event.target?.matches('[name="retention_start_date"], [name="retention_end_date"]')) {
      updateRetentionPeriod(event.target.form);
    }
  });
  document.addEventListener("change", function (event) {
    if (event.target?.id !== "reqExternal") return;
    var select = event.target;
    var record = {};
    try {
      record = JSON.parse(select.selectedOptions[0]?.dataset.externalRecord || "{}");
    } catch (error) {
      record = {};
    }
    var form = select.form;
    ["title", "description", "external_document_number"].forEach(function (name) {
      var field = form.querySelector('[name="' + name + '"]');
      if (field) field.value = record[name] || "";
    });
    var category = form.querySelector('[name="external_category_id"]');
    if (category && record.category_id) category.value = record.category_id;
    var subsidiary = form.querySelector('[name="subsidiary_id"]');
    if (subsidiary) subsidiary.value = record.subsidiary_id || "";
    var subject = form.querySelector('[name="subject"]');
    if (subject && (!subject.value || subject.value === subject.dataset.autoSubject)) {
      var isDisposal = form.querySelector('[name="type"]')?.value === "disposal";
      subject.value = select.value
        ? (isDisposal ? "Disposal request: " : "External revision: ") +
          (record.title || "Selected document")
        : "";
      subject.dataset.autoSubject = subject.value;
    }
  });

  function updateDocumentDomain() {
    document.querySelectorAll("form").forEach(function (form) {
      var select = form.querySelector('[name="document_domain"]');
      if (!select) return;
      var domain = select.value;
      form
        .querySelectorAll("[data-document-domain]")
        .forEach(function (section) {
          var show = section.dataset.documentDomain === domain;
          section.hidden = !show;
          section.querySelectorAll("select").forEach(function (field) {
            field.disabled = !show;
            field.required = show;
            if (!show) field.value = "";
          });
        });
    });
  }
  function updateDisposalReason() {
    document
      .querySelectorAll("[data-disposal-reason]")
      .forEach(function (select) {
        var form = select.form;
        if (!form) return;
        var other = form.querySelector("[data-disposal-other]");
        if (!other) return;
        var show = select.value === "other";
        other.hidden = !show;
        other.querySelectorAll("input").forEach(function (field) {
          field.disabled = !show;
          field.required = show;
          if (!show) field.value = "";
        });
      });
  }
  document.addEventListener("change", function (event) {
    if (event.target?.matches('[name="retention_start_date"], [name="retention_end_date"]')) {
      updateRetentionPeriod(event.target.form);
    }
    if (event.target?.id === "reqType") updateRequestActionFields();
    if (event.target?.id === "reqCategory" ||
        event.target?.matches("[data-series-number-checker]") ||
        event.target?.id === "reqNumber") {
      // A one-time setup entered for a different Document No. must not carry over.
      if (event.target?.id === "reqCategory" || event.target?.id === "reqNumber") {
        var firstPattern = event.target.form?.querySelector('[name="series_code_pattern"]');
        if (firstPattern) firstPattern.value = "";
      }
      updateSoftcopyNumberFields(event.target.form);
    }
    if (event.target?.name === "document_domain") {
      updateDocumentDomain();
      updateRequestActionFields();
    }
    if (event.target?.hasAttribute("data-disposal-reason"))
      updateDisposalReason();
  });
  updateDocumentDomain();
  updateDisposalReason();
  updateRequestActionFields();
  document.querySelectorAll("[data-softcopy-category-setup]").forEach(updateSoftcopyCategoryKind);
  document.querySelectorAll("form").forEach(updateRetentionPeriod);

  function updateStepApprover() {
    var type = document.getElementById("stepApproverType");
    if (!type) return;
    [
      ["stepUserField", "user"],
      ["stepRoleField", "role"],
    ].forEach(function (pair) {
      var section = document.getElementById(pair[0]);
      if (!section) return;
      section.hidden = type.value !== pair[1];
      section.querySelectorAll("select").forEach(function (field) {
        field.disabled = section.hidden;
        field.required = !section.hidden;
      });
    });
  }
  var workflowParent = null;
  document.addEventListener("change", function (event) {
    if (event.target?.id === "stepApproverType") updateStepApprover();
    if (event.target?.hasAttribute("data-workflow-version")) {
      var modal = event.target.closest(".workflow-steps-modal");
      modal
        ?.querySelectorAll("[data-workflow-version-panel]")
        .forEach(function (panel) {
          panel.hidden =
            panel.dataset.workflowVersionPanel !== event.target.value;
        });
    }
  });
  document.addEventListener("click", function (event) {
    var button = event.target.closest(".js-workflow-step");
    if (!button) return;
    var form = document.getElementById("stepForm");
    var modal = document.getElementById("stepModal");
    if (!form || !modal) return;
    form.reset();
    form.dataset.confirmed = "";
    var record = parse(button, "step-record");
    form.querySelectorAll("[name]").forEach(function (field) {
      if (Object.hasOwn(record, field.name))
        field.value = record[field.name] ?? "";
    });
    document.getElementById("stepTitle").textContent = record.step_key
      ? "Edit Draft Step"
      : "Create New Step";
    updateStepApprover();
    workflowParent = button.closest(".workflow-steps-modal");
    function showStep() {
      window.bootstrap.Modal.getOrCreateInstance(modal).show();
    }
    if (workflowParent) {
      workflowParent.addEventListener("hidden.bs.modal", showStep, {
        once: true,
      });
      window.bootstrap.Modal.getOrCreateInstance(workflowParent).hide();
    } else showStep();
  });
  document
    .getElementById("stepModal")
    ?.addEventListener("hidden.bs.modal", function () {
      if (
        workflowParent &&
        !pendingForm &&
        document.getElementById("stepForm")?.dataset.confirmed !== "yes"
      ) {
        window.bootstrap.Modal.getOrCreateInstance(workflowParent).show();
      }
    });
  updateStepApprover();

  // Location upsert uses live predefined Area > Specific > Asset relationships.
  // The PHP service validates all relationships again before writing to MySQL.
  function updateLocationHierarchy(changed) {
    var form = document.querySelector("#editForm[data-location-upsert]");
    if (!form) return;
    var area = form.querySelector('[data-location-level="area"]');
    var specific = form.querySelector('[data-location-level="specific"]');
    var asset = form.querySelector('[data-location-level="asset"]');
    if (!area || !specific || !asset) return;

    function selectedOption(select) {
      return select.options[select.selectedIndex] || null;
    }

    if (changed === "asset" && asset.value) {
      var chosenAsset = selectedOption(asset);
      specific.value = chosenAsset.dataset.specificId || "";
      area.value = chosenAsset.dataset.areaId || "";
    } else if (changed === "specific" && specific.value) {
      var chosenSpecific = selectedOption(specific);
      area.value = chosenSpecific.dataset.areaId || "";
    }

    Array.from(specific.options).forEach(function (option) {
      if (!option.value) return;
      var available = !area.value || option.dataset.areaId === area.value;
      option.hidden = !available;
      option.disabled = !available;
    });
    if (selectedOption(specific) && selectedOption(specific).disabled) {
      specific.value = "";
    }

    Array.from(asset.options).forEach(function (option) {
      if (!option.value) return;
      var matchesSpecific =
        !specific.value || option.dataset.specificId === specific.value;
      var matchesArea = !area.value || option.dataset.areaId === area.value;
      var available = matchesSpecific && matchesArea;
      option.hidden = !available;
      option.disabled = !available;
    });
    if (selectedOption(asset) && selectedOption(asset).disabled) {
      asset.value = "";
    }

    var help = form.querySelector("#location-hierarchy-help");
    if (help) {
      help.textContent = asset.value
        ? "Area and specific automatically match the selected asset."
        : specific.value
          ? "Area automatically matches the selected specific."
          : "Select an Area and Specific. Asset Number is optional.";
    }
  }

  document.addEventListener("change", function (event) {
    var level =
      event.target && event.target.getAttribute("data-location-level");
    if (level) updateLocationHierarchy(level);
  });
  updateLocationHierarchy();

  // Hardcopy Upsert uses predefined pk_dts references; all relationships
  // are rechecked by the PHP model/service regardless of browser input.
  function updateHardcopyRetention() {
    document
      .querySelectorAll('#editForm [name="retention_enabled"]')
      .forEach(function (toggle) {
        var form = toggle.form;
        if (!form) return;
        var checked = toggle.checked;
        form
          .querySelectorAll("[data-hardcopy-retention]")
          .forEach(function (section) {
            section.hidden = !checked;
            section.querySelectorAll("input").forEach(function (field) {
              field.disabled = !checked;
              field.required = checked;
              if (!checked) field.value = "";
            });
          });
      });
  }

  // When editing an existing hardcopy request, fill the same predefined
  // upsert fields used by Direct Hardcopy, not only the title.
  function fillHardcopyRequestFromSelection() {
    var form = document.querySelector("#editForm[data-hardcopy-upsert]");
    if (!form || !form.querySelector("[data-hardcopy-existing]")) return;
    var select = form.querySelector('[name="hardcopy_id"]');
    var option = select?.options[select.selectedIndex];
    if (!option?.dataset.hardcopy) return;
    var record;
    try {
      record = JSON.parse(option.dataset.hardcopy);
    } catch (error) {
      return;
    }
    [
      "title",
      "area_id",
      "specific_id",
      "asset_id",
      "location_id",
      "sequence_number",
      "retention_start_date",
      "retention_end_date",
    ].forEach(function (name) {
      var input = form.querySelector('[name="' + name + '"]');
      if (input) input.value = record[name] ?? "";
    });
    var holder = form.querySelector('select[name="holder_id"]');
    if (holder) holder.value = record.holder_id ?? "";
    var retention = form.querySelector('[name="retention_enabled"]');
    if (retention) retention.checked = Number(record.retention_enabled) === 1;
    updateHardcopyHierarchy();
    updateHardcopyRetention();
  }
  document.addEventListener("change", function (event) {
    if (event.target?.id === "reqHardcopy") fillHardcopyRequestFromSelection();
  });

  function updateHardcopyRequestAction() {
    var form = document.querySelector("#editForm");
    if (!form || !form.querySelector("[data-hardcopy-existing]")) return;
    var type = form.querySelector("#reqType")?.value;
    var existing = form.querySelector("[data-hardcopy-existing]");
    var details = form.querySelector("[data-hardcopy-details]");
    var requiresExisting = type === "hardcopy_update" || type === "disposal";
    existing.hidden = !requiresExisting;
    existing.querySelectorAll("select").forEach(function (field) {
      field.disabled = !requiresExisting;
      field.required = requiresExisting;
    });
    var disposal = form.querySelector("[data-hardcopy-disposal]");
    if (disposal) {
      var isDisposal = type === "disposal";
      disposal.hidden = !isDisposal;
      disposal.querySelectorAll("input,select").forEach(function (field) {
        field.disabled = !isDisposal;
        field.required =
          isDisposal && field.hasAttribute("data-disposal-reason");
      });
    }
    var showDetails = type === "hardcopy_create" || type === "hardcopy_update";
    details.hidden = !showDetails;
    details
      .querySelectorAll("input, select, textarea")
      .forEach(function (field) {
        if (field.name === "holder_id" && field.type === "hidden") return;
        if (!field.hasAttribute("data-original-required")) {
          field.setAttribute(
            "data-original-required",
            field.required ? "yes" : "no",
          );
        }
        field.disabled = !showDetails;
        field.required =
          showDetails && field.getAttribute("data-original-required") === "yes";
      });
    if (showDetails) {
      var title = details.querySelector('[name="title"]');
      if (title) title.required = true;
    }
    updateHardcopyRetention();
    updateDisposalReason();
  }
  document.addEventListener("change", function (e) {
    if (e.target?.name === "retention_enabled") updateHardcopyRetention();
    if (e.target?.id === "reqType") updateHardcopyRequestAction();
  });
  updateHardcopyRetention();
  updateHardcopyRequestAction();

  function updateHardcopyHierarchy(changed) {
    var form = document.querySelector("#editForm[data-hardcopy-upsert]");
    if (!form) return;
    var area = form.querySelector('[data-hardcopy-level="area"]');
    var specific = form.querySelector('[data-hardcopy-level="specific"]');
    var asset = form.querySelector('[data-hardcopy-level="asset"]');
    var location = form.querySelector('[data-hardcopy-level="location"]');
    if (!area || !specific || !asset || !location) return;

    function chosen(select) {
      return select.options[select.selectedIndex] || null;
    }
    function filter(select, match) {
      Array.from(select.options).forEach(function (option) {
        if (!option.value) return;
        var valid = match(option);
        option.disabled = !valid;
        option.hidden = !valid;
      });
      var selected = chosen(select);
      if (selected && selected.disabled) select.value = "";
    }
    // Clearing an Asset must stay a real empty choice. If an existing
    // Location is tied to that Asset, clear that Location too; otherwise
    // a future save would silently restore the Asset through its parent.
    if (changed === "asset" && !asset.value && location.value) {
      var oldLocation = chosen(location);
      if (
        oldLocation &&
        oldLocation.dataset.assetId &&
        oldLocation.dataset.assetId !== "0"
      )
        location.value = "";
    }
    if (changed === "location" && location.value) {
      var loc = chosen(location);
      area.value = loc.dataset.areaId === "0" ? "" : loc.dataset.areaId || "";
      specific.value =
        loc.dataset.specificId === "0" ? "" : loc.dataset.specificId || "";
      asset.value =
        loc.dataset.assetId === "0" ? "" : loc.dataset.assetId || "";
    } else if (changed === "asset" && asset.value) {
      var a = chosen(asset);
      area.value = a.dataset.areaId || "";
      specific.value = a.dataset.specificId || "";
    } else if (changed === "specific" && specific.value) {
      area.value = chosen(specific).dataset.areaId || "";
    }
    filter(specific, function (opt) {
      return !area.value || opt.dataset.areaId === area.value;
    });
    filter(asset, function (opt) {
      return (
        (!area.value || opt.dataset.areaId === area.value) &&
        (!specific.value || opt.dataset.specificId === specific.value)
      );
    });
    filter(location, function (opt) {
      return (
        (!area.value ||
          !opt.dataset.areaId ||
          opt.dataset.areaId === "0" ||
          opt.dataset.areaId === area.value) &&
        (!specific.value ||
          !opt.dataset.specificId ||
          opt.dataset.specificId === "0" ||
          opt.dataset.specificId === specific.value) &&
        (!asset.value ||
          !opt.dataset.assetId ||
          opt.dataset.assetId === "0" ||
          opt.dataset.assetId === asset.value)
      );
    });
    var help = form.querySelector("#hardcopyHierarchyHelp");
    if (help) {
      help.textContent = location.value
        ? "Location selected. Its predefined area, specific and asset have been populated."
        : asset.value
          ? "Asset selected. Its area and specific are populated automatically."
          : specific.value
            ? "Specific selected. The parent area is populated automatically."
            : "Select Area, Specific and Location. Asset is optional.";
    }
  }
  document.addEventListener("change", function (event) {
    var level =
      event.target && event.target.getAttribute("data-hardcopy-level");
    if (level) updateHardcopyHierarchy(level);
  });
  updateHardcopyHierarchy();

  function updateHardcopyTransfer(changed) {
    var form = document.querySelector("#editForm[data-hardcopy-transfer]");
    if (!form) return;
    var source = form.querySelector("[data-transfer-source]");
    var area = form.querySelector('[data-transfer-level="area"]');
    var specific = form.querySelector('[data-transfer-level="specific"]');
    var asset = form.querySelector('[data-transfer-level="asset"]');
    var location = form.querySelector('[data-transfer-level="location"]');
    if (!source || !area || !specific || !asset || !location) return;
    var doc = {};
    try {
      doc = JSON.parse(
        source.options[source.selectedIndex]?.dataset.transferDoc || "{}",
      );
    } catch (e) {
      doc = {};
    }
    form.querySelectorAll("[data-transfer-origin]").forEach(function (input) {
      input.value = source.value
        ? doc[input.dataset.transferOrigin] || "Not assigned"
        : "";
    });
    if (changed === "source") {
      [area, specific, asset, location].forEach(function (field) {
        field.value = "";
      });
      var recipient = form.querySelector('[name="recipient_id"]');
      if (recipient) recipient.value = "";
      var subject = form.querySelector('[name="subject"]');
      if (
        subject &&
        (!subject.value || subject.value === subject.dataset.autoSubject)
      ) {
        subject.value = source.value
          ? "Transfer: " + (doc.title || "Selected hardcopy")
          : "";
        subject.dataset.autoSubject = subject.value;
      }
    }
    // Same optional Asset behavior for destination transfers.
    if (changed === "asset" && !asset.value && location.value) {
      var oldDestination = location.options[location.selectedIndex];
      if (
        oldDestination &&
        oldDestination.dataset.assetId &&
        oldDestination.dataset.assetId !== "0"
      )
        location.value = "";
    }
    if (changed === "location" && location.value) {
      var chosen = location.options[location.selectedIndex];
      area.value =
        chosen.dataset.areaId === "0" ? "" : chosen.dataset.areaId || "";
      specific.value =
        chosen.dataset.specificId === "0"
          ? ""
          : chosen.dataset.specificId || "";
      asset.value =
        chosen.dataset.assetId === "0" ? "" : chosen.dataset.assetId || "";
    } else if (changed === "asset" && asset.value) {
      var chosen = asset.options[asset.selectedIndex];
      specific.value = chosen.dataset.specificId || "";
      area.value = chosen.dataset.areaId || "";
    } else if (changed === "specific" && specific.value) {
      area.value =
        specific.options[specific.selectedIndex].dataset.areaId || "";
    }
    function filter(select, valid) {
      Array.from(select.options).forEach(function (o) {
        if (!o.value) return;
        o.disabled = o.hidden = !valid(o);
      });
      if (select.options[select.selectedIndex]?.disabled) select.value = "";
    }
    filter(specific, function (o) {
      return !area.value || o.dataset.areaId === area.value;
    });
    filter(asset, function (o) {
      return (
        (!area.value || o.dataset.areaId === area.value) &&
        (!specific.value || o.dataset.specificId === specific.value)
      );
    });
    filter(location, function (o) {
      return (
        (!area.value ||
          o.dataset.areaId === "0" ||
          o.dataset.areaId === area.value) &&
        (!specific.value ||
          o.dataset.specificId === "0" ||
          o.dataset.specificId === specific.value) &&
        (!asset.value ||
          o.dataset.assetId === "0" ||
          o.dataset.assetId === asset.value) &&
        (!doc.location_id || String(doc.location_id) !== o.value)
      );
    });
    var hint = form.querySelector("#transferHelp");
    if (hint)
      hint.textContent = location.value
        ? "Destination selected. Its parent Places were populated."
        : "Select Area, Specific and a destination Location. Asset is optional.";
  }
  document.addEventListener("change", function (e) {
    if (e.target?.hasAttribute("data-transfer-source"))
      updateHardcopyTransfer("source");
    var level = e.target?.getAttribute("data-transfer-level");
    if (level) updateHardcopyTransfer(level);
  });
  updateHardcopyTransfer();

  // Accessible progressive enhancement for all data-searchable dropdowns.
  // The original select stays form-associated and remains the source of truth.
  var searchableSelects = [];

  function syncSearchableControls(root) {
    searchableSelects.forEach(function (control) {
      if (root && !root.contains(control.select)) return;
      var select = control.select,
        input = control.input;
      var option = select.options[select.selectedIndex];
      input.value = select.value && option ? option.textContent.trim() : "";
      input.placeholder =
        select.options[0]?.textContent.trim() || "Search and select...";
      input.disabled = select.disabled;
      input.required = select.required;
      input.setAttribute("aria-invalid", "false");
      if (select.disabled) control.close();
    });
  }

  function setupSearchableSelects() {
    document
      .querySelectorAll("select[data-searchable]")
      .forEach(function (select, index) {
        if (select.dataset.searchReady === "yes") return;
        select.dataset.searchReady = "yes";

        var wrapper = document.createElement("div");
        wrapper.className = "searchable-select";
        select.parentNode.insertBefore(wrapper, select);
        wrapper.appendChild(select);
        select.classList.add("searchable-native");
        select.tabIndex = -1;
        select.setAttribute("aria-hidden", "true");

        var input = document.createElement("input");
        input.type = "text";
        input.className = "form-control searchable-input";
        input.autocomplete = "off";
        input.id = (select.id || "searchable-" + index) + "-search";
        input.setAttribute("role", "combobox");
        input.setAttribute("aria-autocomplete", "list");
        input.setAttribute("aria-expanded", "false");
        input.setAttribute("aria-haspopup", "listbox");
        var list = document.createElement("div");
        list.className = "searchable-options";
        list.id = input.id + "-list";
        list.setAttribute("role", "listbox");
        list.hidden = true;
        input.setAttribute("aria-controls", list.id);

        var label = select.id
          ? document.querySelector('label[for="' + select.id + '"]')
          : null;
        if (label) label.htmlFor = input.id;
        input.setAttribute(
          "aria-label",
          label
            ? label.textContent.trim()
            : select.getAttribute("aria-label") || "Select an option",
        );

        wrapper.appendChild(input);
        // Portal the listbox to body so scrollable Bootstrap modal bodies
        // cannot clip options near the bottom of a long form.
        document.body.appendChild(list);
        var active = -1;
        var visibleOptions = [];

        function highlight(next) {
          if (!visibleOptions.length) return;
          active = (next + visibleOptions.length) % visibleOptions.length;
          visibleOptions.forEach(function (option, i) {
            option.classList.toggle("is-active", i === active);
          });
          input.setAttribute(
            "aria-activedescendant",
            visibleOptions[active].id,
          );
          visibleOptions[active].scrollIntoView({ block: "nearest" });
        }

        function choose(value) {
          select.value = value;
          select.dispatchEvent(new Event("change", { bubbles: true }));
          close();
          syncSearchableControls();
          input.focus();
        }

        function render(query) {
          list.replaceChildren();
          active = -1;
          visibleOptions = [];
          input.removeAttribute("aria-activedescendant");
          var options = Array.from(select.options).filter(function (option) {
            if (option.disabled || option.hidden) return false;
            if (select.required && option.value === "") return false;
            return option.textContent
              .toLowerCase()
              .includes(query.trim().toLowerCase());
          });
          options.slice(0, 80).forEach(function (option, i) {
            var row = document.createElement("div");
            row.id = list.id + "-option-" + i;
            row.className = "searchable-option";
            row.setAttribute("role", "option");
            row.setAttribute(
              "aria-selected",
              String(select.value === option.value),
            );
            row.textContent = option.textContent.trim();
            row.addEventListener("pointerdown", function (event) {
              event.preventDefault();
            });
            row.addEventListener("click", function () {
              choose(option.value);
            });
            list.appendChild(row);
            visibleOptions.push(row);
          });
          if (!visibleOptions.length || options.length > 80) {
            var note = document.createElement("p");
            note.className = "searchable-message";
            note.textContent = !options.length
              ? "No matches found"
              : "Showing the first 80 matches. Type to narrow the results.";
            list.appendChild(note);
          }
        }

        function positionList() {
          var rect = input.getBoundingClientRect();
          var roomBelow = window.innerHeight - rect.bottom - 12;
          var roomAbove = rect.top - 12;
          var above = roomBelow < 180 && roomAbove > roomBelow;
          list.style.left = Math.max(8, rect.left) + "px";
          list.style.width =
            Math.max(160, Math.min(rect.width, window.innerWidth - 16)) + "px";
          list.style.maxHeight =
            Math.max(80, Math.min(285, above ? roomAbove : roomBelow)) + "px";
          list.style.top = above ? "auto" : rect.bottom + 4 + "px";
          list.style.bottom = above
            ? window.innerHeight - rect.top + 4 + "px"
            : "auto";
        }

        function open() {
          if (input.disabled) return;
          searchableSelects.forEach(function (control) {
            if (control.input !== input) control.close();
          });
          list.hidden = false;
          positionList();
          input.setAttribute("aria-expanded", "true");
          render("");
        }

        function close() {
          list.hidden = true;
          input.setAttribute("aria-expanded", "false");
          input.removeAttribute("aria-activedescendant");
          active = -1;
          var chosen = select.options[select.selectedIndex];
          input.value = select.value && chosen ? chosen.textContent.trim() : "";
        }

        input.addEventListener("focus", open);
        input.addEventListener("click", function () {
          if (list.hidden) open();
        });
        input.addEventListener("input", function () {
          if (list.hidden) open();
          render(input.value);
        });
        input.addEventListener("keydown", function (event) {
          if (event.key === "ArrowDown" || event.key === "ArrowUp") {
            event.preventDefault();
            if (list.hidden) open();
            highlight(active + (event.key === "ArrowDown" ? 1 : -1));
          } else if (event.key === "Enter" && !list.hidden) {
            event.preventDefault();
            if (active >= 0 && visibleOptions[active]) {
              visibleOptions[active].click();
            } else if (visibleOptions.length === 1) {
              visibleOptions[0].click();
            }
          } else if (event.key === "Escape") {
            if (!list.hidden) {
              event.stopPropagation();
              event.preventDefault();
              close();
            }
          } else if (event.key === "Tab") {
            close();
          }
        });
        document.addEventListener("pointerdown", function (event) {
          if (!wrapper.contains(event.target) && !list.contains(event.target))
            close();
        });
        document.addEventListener(
          "scroll",
          function () {
            if (!list.hidden) positionList();
          },
          true,
        );
        window.addEventListener("resize", function () {
          if (!list.hidden) positionList();
        });
        searchableSelects.push({ select: select, input: input, close: close });
      });
    syncSearchableControls();
  }

  setupSearchableSelects();
  document.addEventListener("change", function () {
    // Run after the page's dependent-select and form-section change handlers.
    Promise.resolve().then(function () {
      syncSearchableControls();
    });
  });
  document.addEventListener("reset", function () {
    setTimeout(function () {
      syncSearchableControls();
    }, 0);
  });
  document.addEventListener(
    "invalid",
    function (event) {
      if (!event.target.matches("select[data-searchable]")) return;
      var input =
        event.target.parentElement?.querySelector(".searchable-input");
      if (!input) return;
      event.preventDefault();
      input.setAttribute("aria-invalid", "true");
      input.focus();
    },
    true,
  );

  var pendingForm = null;
  var pendingParent = null;
  function showConfirmation() {
    window.bootstrap.Modal.getOrCreateInstance(
      document.getElementById("confirmModal"),
    ).show();
  }
  var confirmation = document.getElementById("confirmModal");
  if (confirmation)
    confirmation.addEventListener("hidden.bs.modal", function () {
      if (pendingForm && pendingParent) {
        window.bootstrap.Modal.getOrCreateInstance(pendingParent).show();
      }
      pendingParent = null;
      pendingForm = null;
    });
  document.addEventListener("submit", function (event) {
    var form = event.target;
    if (!form.matches("form[data-confirm]") || form.dataset.confirmed === "yes")
      return;
    if (!window.bootstrap) return; // Server also checks confirmed=yes.
    event.preventDefault();
    pendingForm = form;
    document.getElementById("confirmMessage").textContent =
      form.getAttribute("data-confirm") || "Continue?";
    pendingParent = form.closest(".modal.show");
    if (pendingParent) {
      pendingParent.addEventListener("hidden.bs.modal", showConfirmation, {
        once: true,
      });
      window.bootstrap.Modal.getOrCreateInstance(pendingParent).hide();
    } else {
      showConfirmation();
    }
  });

  var confirmButton = document.getElementById("confirmProceed");
  if (confirmButton)
    confirmButton.addEventListener("click", function () {
      if (!pendingForm) return;
      var form = pendingForm;
      pendingForm = null;
      window.bootstrap.Modal.getOrCreateInstance(
        document.getElementById("confirmModal"),
      ).hide();
      form.dataset.confirmed = "yes";
      var flag = form.querySelector('[name="confirmed"]');
      if (flag) flag.value = "yes";
      form.requestSubmit();
    });

  // A saved workflow returns to the same editor, refreshed with the latest
  // draft hash and approvals. Alerts take precedence over automatically
  // reopening the builder so modals never overlap.
  function reopenWorkflow() {
    var match = new URLSearchParams(window.location.search).get("workflow");
    if (!match || !/^[0-9]+$/.test(match)) return;
    var editor = document.getElementById("workflowStepsModal-" + match);
    if (!editor) return;
    var versions = editor.querySelector("[data-workflow-version]");
    var draft = editor.querySelector(
      '[data-workflow-version-panel][data-draft="yes"]',
    );
    if (versions && draft) {
      versions.value = draft.dataset.workflowVersionPanel;
      versions.dispatchEvent(new Event("change", { bubbles: true }));
    }
    window.bootstrap.Modal.getOrCreateInstance(editor).show();
  }
  var noticeModal = document.getElementById("appAlertModal");
  if (noticeModal) {
    noticeModal.addEventListener("hidden.bs.modal", reopenWorkflow, {
      once: true,
    });
    window.bootstrap.Modal.getOrCreateInstance(noticeModal).show();
  } else {
    reopenWorkflow();
  }

  document.addEventListener("change", function (event) {
    var control = event.target;
    if (
      control.matches("[data-auto-submit]") &&
      !control.closest("[data-live-ready]")
    ) {
      if (control.form) {
        var page = control.form.querySelector('[name="page"]');
        if (page) page.value = "1";
        control.form.requestSubmit();
      }
    }
  });
})();
