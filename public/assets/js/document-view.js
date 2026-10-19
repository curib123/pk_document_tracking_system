/* PK Document Control: read-only, permission-scoped document detail presentation.
 * No requests, mutation, or alternate data API. Receives existing JSON from
 * Document_detail_model and renders it using text nodes only.
 */
(function () {
  "use strict";

  var modal = document.getElementById("viewModal");
  if (!modal) return;

  var ICONS = {
    overview: "fa-circle-info",
    storage: "fa-box-archive",
    hardcopy: "fa-box-archive",
    softcopy: "fa-file-lines",
    revision: "fa-code-branch",
    files: "fa-paperclip",
    requests: "fa-clipboard-list",
    approvals: "fa-list-check",
    transfers: "fa-arrow-right-arrow-left",
    disposal: "fa-box-archive",
    audit: "fa-clock-rotate-left",
  };
  var HISTORY = {
    requests: true,
    approvals: true,
    transfers: true,
    audit: true,
  };

  function node(tag, className, text) {
    var element = document.createElement(tag);
    if (className) element.className = className;
    if (text !== undefined && text !== null) element.textContent = String(text);
    return element;
  }

  function icon(name) {
    var element = node("i", "fa-solid " + (ICONS[name] || "fa-file-lines"));
    element.setAttribute("aria-hidden", "true");
    return element;
  }

  function valueText(value) {
    if (value == null || value === "") return "Not specified";
    if (typeof value === "boolean") return value ? "Yes" : "No";
    if (typeof value === "object") {
      if (Array.isArray(value)) return value.map(valueText).join("\n");
      return Object.entries(value)
        .map(function (entry) {
          return entry[0] + ": " + valueText(entry[1]);
        })
        .join("\n");
    }
    return String(value).replace(/\b\d{4}-\d{2}-\d{2}(?:[ T]\d{2}:\d{2}(?::\d{2}(?:\.\d+)?)?(?:Z|[+-]\d{2}:\d{2})?)?\b/g, function (raw) {
      var stamp = decisionTimestamp(raw);
      return stamp ? stamp.date + (raw.length > 10 ? " · " + stamp.time : "") : "Date not recorded";
    });
  }

  function normalizedEntries(fields) {
    if (!fields || typeof fields !== "object") return [];
    return Object.entries(fields);
  }

  function safeLink(url) {
    try {
      var parsed = new URL(String(url), window.location.origin);
      if (!["http:", "https:"].includes(parsed.protocol)) return null;
      return parsed.origin === window.location.origin ? parsed.href : null;
    } catch (error) {
      return null;
    }
  }

  function reset() {
    modal.classList.remove("is-document-view");
    modal.classList.remove("is-assignment-view");
    [
      "viewDocumentEyebrow",
      "viewDocumentIcon",
      "viewDocumentRef",
      "viewDocumentStatus",
      "viewDocumentSummary",
      "viewDocumentCustody",
      "viewDocumentFootnote",
    ].forEach(function (id) {
      var el = document.getElementById(id);
      if (!el) return;
      el.hidden = true;
      if (id === "viewDocumentSummary" || id === "viewDocumentCustody")
        el.replaceChildren();
    });
    var status = document.getElementById("viewDocumentStatus");
    if (status) status.className = "doc-modal-status";
  }

  // The document header owns the identity, classification, storage and
  // custody overview. Tabs below it contain only independent concerns.
  function isOverviewSection(section) {
    return (
      !!section && ["overview", "storage", "revision"].includes(section.key)
    );
  }

  var STORAGE_LEVELS = ["Area", "Specific", "Asset", "Location"];
  var STORAGE_HEADER_FIELDS = ["Current Holder", "Days until disposal"];

  function hasStorageValue(value) {
    return value != null && String(value).trim() !== "";
  }

  function makeStorageRoute(fields) {
    if (
      !fields ||
      !STORAGE_LEVELS.some(function (label) {
        return Object.prototype.hasOwnProperty.call(fields, label);
      })
    )
      return null;
    var levels = STORAGE_LEVELS.filter(function (label) {
      return label !== "Asset" || hasStorageValue(fields[label]);
    });
    var route = node("ol", "doc-storage-route");
    route.setAttribute("aria-label", "Storage route");
    route.setAttribute("style", "--route-count: " + levels.length);
    levels.forEach(function (label, index) {
      var assigned = hasStorageValue(fields[label]);
      var destination = label === "Location";
      var step = node(
        "li",
        "doc-storage-route-step" +
          (destination ? " is-destination" : "") +
          (assigned ? "" : " is-unassigned"),
      );
      step.setAttribute("style", "--route-index: " + index);
      if (destination && assigned)
        step.setAttribute("aria-current", "location");
      var marker = node("span", "doc-storage-route-marker", index + 1);
      marker.setAttribute("aria-hidden", "true");
      step.append(
        marker,
        node("span", "doc-storage-route-label", label),
        node(
          "strong",
          "doc-storage-route-value",
          assigned ? fields[label] : "Not specified",
        ),
      );
      route.append(step);
    });
    return route;
  }

  function renderOverview(sections) {
    var summary = document.getElementById("viewDocumentSummary");
    if (!summary) return;
    summary.replaceChildren();

    // Document name, reference, type and status are already displayed
    // in the modal title bar; avoid repeating them as metadata tiles.
    var repeated = {
      Title: true,
      "Reference / Copy Number": true,
      "Document Type": true,
      Status: true,
    };

    // Physical storage is the first overview group; creation metadata follows
    // the route. Keep the supplied data and other section ordering intact.
    var orderedSections = sections.filter(function (section) {
      return section.key === "storage";
    }).concat(sections.filter(function (section) {
      return section.key !== "storage";
    }));

    orderedSections.forEach(function (section) {
      var entries = normalizedEntries(section.fields).filter(function (entry) {
        return (
          !(section.key === "overview" && repeated[entry[0]]) &&
          !(
            section.key === "storage" &&
            (STORAGE_HEADER_FIELDS.includes(entry[0]) ||
              entry[0] === "Location Sequence")
          )
        );
      });
      var route =
        section.key === "storage" ? makeStorageRoute(section.fields) : null;
      if (!entries.length && !route) return;
      var group = node("section", "doc-overview-group");
      if (route) {
        group.append(route);
        entries = entries.filter(function (entry) {
          return !STORAGE_LEVELS.includes(entry[0]);
        });
      }
      var grid = node("dl", "doc-overview-grid");
      entries.forEach(function (entry) {
        var value = valueText(entry[1]);
        var item = node(
          "div",
          "doc-overview-item" +
            (value.length > 160 || value.includes("\n") ? " is-wide" : ""),
        );
        item.append(
          node("dt", "doc-overview-label", entry[0]),
          node("dd", "doc-overview-value", value),
        );
        grid.append(item);
      });
      if (entries.length) group.append(grid);
      summary.append(group);
    });
    summary.hidden = false;
  }

  // Every list/table action shares this same visual hierarchy. Requests for
  // softcopy/hardcopy supply the document shape directly; ordinary records
  // are adapted without altering their original, permission-scoped data.
  function normalizeRecord(data) {
    if (data && data.kind === "document" && Array.isArray(data.sections))
      return data;
    if (data && data.kind === "record" && Array.isArray(data.sections))
      return data;
    var sections = data && Array.isArray(data.sections) ? data.sections : null;
    var fields = data && typeof data === "object" ? data : { Details: data };
    if (sections) {
      var first = sections[0] || {};
      var firstFields =
        first.fields && typeof first.fields === "object" ? first.fields : {};
      return {
        kind: "record",
        domain: "record",
        header: {
          title: String(
            firstFields.Title ||
              firstFields.Name ||
              firstFields.Role ||
              first.title ||
              "Record details",
          ),
          reference: String(firstFields.Reference || firstFields.Code || ""),
          status: String(firstFields.Status || ""),
        },
        sections: [
          { key: "overview", title: "Record Overview", fields: firstFields },
        ].concat(
          sections.slice(1).map(function (section, index) {
            return Object.assign({}, section, {
              key: (section && section.key) || "detail_" + index,
            });
          }),
        ),
      };
    }
    var title =
      fields.Title ||
      fields.Name ||
      fields.Role ||
      fields.Document ||
      fields.Workflow ||
      fields.Reference ||
      fields.Username ||
      "Record details";
    var status = fields.Status || "";
    return {
      kind: "record",
      domain: "record",
      header: {
        title: String(title),
        reference: String(fields.Reference || fields.Code || ""),
        status: String(status),
      },
      sections: [{ key: "overview", title: "Record Overview", fields: fields }],
    };
  }

  function prepareHeading(data) {
    modal.classList.add("is-document-view");
    var h = data.header || {};
    var domain =
      data.domain === "hardcopy"
        ? "hardcopy"
        : data.domain === "softcopy"
          ? "softcopy"
          : "record";
    var title = document.getElementById("viewModalTitle");
    if (title) title.textContent = h.title || "Untitled document";

    var kicker = document.getElementById("viewDocumentEyebrow");
    if (kicker) {
      kicker.hidden = false;
      kicker.textContent =
        h.eyebrow ||
        (domain === "hardcopy"
          ? "PHYSICAL DOCUMENT"
          : domain === "softcopy"
            ? "CONTROLLED DIGITAL DOCUMENT"
            : "RECORD DETAILS");
    }
    var kind = document.getElementById("viewDocumentIcon");
    if (kind) {
      kind.hidden = false;
      kind.replaceChildren(
        icon(
          domain === "hardcopy"
            ? "storage"
            : domain === "softcopy"
              ? "files"
              : "overview",
        ),
      );
    }
    var reference = document.getElementById("viewDocumentRef");
    if (reference) {
      reference.hidden = !h.reference && domain === "record";
      reference.textContent = h.reference
        ? "Reference  ·  " + h.reference
        : "No reference assigned";
    }
    var custody = document.getElementById("viewDocumentCustody");
    if (custody) {
      var storage = (data.sections || []).find(function (section) {
        return section.key === "storage";
      });
      var fields = (storage && storage.fields) || {};
      STORAGE_HEADER_FIELDS.forEach(function (label) {
        if (!Object.prototype.hasOwnProperty.call(fields, label)) return;
        var item = node("div", "doc-modal-custody-item");
        item.append(
          node("dt", "doc-modal-custody-label", label),
          node("dd", "doc-modal-custody-value", valueText(fields[label])),
        );
        custody.append(item);
      });
      custody.hidden = !custody.children.length;
    }
    var status = document.getElementById("viewDocumentStatus");
    if (status) {
      var allowed = [
        "active",
        "disposed",
        "archived",
        "cancelled",
        "submitted",
        "pending",
        "returned",
        "approved",
        "rejected",
        "completed",
        "draft",
      ];
      var key = String(h.status || "").toLowerCase();
      status.className =
        "doc-modal-status status-" + (allowed.includes(key) ? key : "neutral");
      status.textContent = key
        ? key.charAt(0).toUpperCase() + key.slice(1)
        : "Unknown";
      status.hidden = !key;
    }
    var footnote = document.getElementById("viewDocumentFootnote");
    if (footnote) footnote.hidden = false;
  }

  function sectionTitle(section) {
    var heading = node("div", "doc-modal-section-heading");
    heading.append(
      node("h3", "doc-modal-section-title", section.title || "Details"),
    );
    if (section.description)
      heading.append(
        node("p", "doc-modal-section-subtitle", section.description),
      );
    return heading;
  }

  function emptyState(message) {
    var empty = node("div", "doc-modal-empty");
    var circle = node("span", "doc-modal-empty-icon");
    circle.append(icon("overview"));
    empty.append(
      circle,
      node("strong", "doc-modal-empty-heading", "No entries to display"),
      node(
        "p",
        "doc-modal-empty-message",
        message || "No details are available for this section.",
      ),
    );
    return empty;
  }

  function makeGrid(entries) {
    var list = node("dl", "doc-modal-field-grid");
    entries.forEach(function (entry) {
      var value = valueText(entry[1]);
      var field = node(
        "div",
        "doc-modal-field" +
          (value.length > 160 || value.includes("\n") ? " is-wide" : ""),
      );
      field.append(node("dt", "doc-modal-field-label", entry[0]));
      field.append(node("dd", "doc-modal-field-value", value));
      list.append(field);
    });
    return list;
  }

  function makeActivity(entries) {
    var list = node("div", "doc-modal-activity");
    entries.forEach(function (entry) {
      var item = node("article", "doc-modal-activity-item");
      var indicator = node("span", "doc-modal-activity-marker");
      indicator.append(icon("overview"));
      var body = node("div", "doc-modal-activity-content");
      body.append(node("strong", "doc-modal-activity-title", entry[0]));
      body.append(
        node("p", "doc-modal-activity-description", valueText(entry[1])),
      );
      item.append(indicator, body);
      list.append(item);
    });
    return list;
  }

  function makeFiles(section, entries) {
    var list = node("div", "doc-modal-file-list");
    var links = Array.isArray(section.links) ? section.links : [];
    entries.forEach(function (entry, index) {
      var row = node("article", "doc-modal-file-item");
      var fileIcon = node("span", "doc-modal-file-icon");
      fileIcon.append(icon("files"));
      var content = node("div", "doc-modal-file-content");
      content.append(
        node(
          "strong",
          "doc-modal-file-title",
          entry[0].replace(/^\d+\.\s*/, ""),
        ),
      );
      content.append(node("p", "doc-modal-file-meta", valueText(entry[1])));
      row.append(fileIcon, content);
      var link = links[index] || {};
      var destination = link.url ? safeLink(link.url) : null;
      if (destination) {
        var anchor = node(
          "a",
          "btn btn-sm btn-outline-primary doc-modal-download",
          "Download",
        );
        anchor.href = destination;
        anchor.setAttribute(
          "aria-label",
          "Download " + entry[0].replace(/^\d+\.\s*/, ""),
        );
        anchor.append(icon("files"));
        row.append(anchor);
      }
      var printDestination = link.print_url ? safeLink(link.print_url) : null;
      if (printDestination) {
        var print = node(
          "a",
          "btn btn-sm btn-outline-primary doc-modal-download",
          "Print",
        );
        print.href = printDestination;
        print.target = "_blank";
        print.rel = "noopener";
        print.setAttribute(
          "aria-label",
          "Print " + entry[0].replace(/^\d+\.\s*/, ""),
        );
        row.append(print);
      }
      list.append(row);
    });
    return list;
  }

  function decisionTimestamp(value) {
    var match =
      /^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2})(?::(\d{2})(?:\.\d+)?)?(Z|[+-]\d{2}:\d{2})?)?$/.exec(
        String(value || ""),
      );
    if (!match) return null;
    var year = Number(match[1]),
      month = Number(match[2]),
      day = Number(match[3]);
    var hour = Number(match[4] || 0),
      minute = Number(match[5] || 0),
      second = Number(match[6] || 0);
    var date = new Date(Date.UTC(year, month - 1, day, hour, minute, second));
    if (
      year < 1000 ||
      date.getUTCFullYear() !== year ||
      date.getUTCMonth() !== month - 1 ||
      date.getUTCDate() !== day ||
      hour > 23 ||
      minute > 59 ||
      second > 59
    )
      return null;
    // SQL values are Manila wall time. Explicit ISO offsets describe instants;
    // convert those to Manila before displaying or grouping by calendar day.
    if (match[7]) {
      var offset = match[7] === "Z" ? 0 :
        (Number(match[7].slice(1, 3)) * 60 + Number(match[7].slice(4, 6))) *
        (match[7][0] === "+" ? 1 : -1);
      if (Math.abs(offset) > 14 * 60 || Number(match[7].slice(4, 6)) > 59) return null;
      date = new Date(date.getTime() + (480 - offset) * 60000);
      hour = date.getUTCHours();
      minute = date.getUTCMinutes();
      second = date.getUTCSeconds();
    }
    var day = date.toISOString().slice(0, 10);
    return {
      order: date.getTime(),
      day: day,
      date: date.toLocaleDateString("en-US", {
        month: "short",
        day: "numeric",
        year: "numeric",
        timeZone: "UTC",
      }),
      time:
        (hour % 12 || 12) +
        ":" +
        String(minute).padStart(2, "0") +
        ":" +
        String(second).padStart(2, "0") +
        (hour >= 12 ? " PM" : " AM"),
      iso: day + "T" + String(hour).padStart(2, "0") + ":" +
        String(minute).padStart(2, "0") + ":" + String(second).padStart(2, "0") + "+08:00",
    };
  }

  function makeDecisionTimeline(history, order, isTransfer) {
    var timeline = node("div", "doc-workflow-timeline is-animated-timeline");
    var sorted = history
      .filter(function (event) {
        return event && typeof event === "object";
      })
      .map(function (event, index) {
        return {
          event: event,
          index: index,
          stamp: decisionTimestamp(event.created_at),
        };
      });
    var direction = order === "newest" ? -1 : 1;
    sorted.sort(function (left, right) {
      if (!left.stamp || !right.stamp)
        return left.stamp ? -1 : right.stamp ? 1 : left.index - right.index;
      return (
        (left.stamp.order - right.stamp.order || left.index - right.index) *
        direction
      );
    });
    var lastDay, list;
    sorted.forEach(function (record, index) {
      var event = record.event,
        stamp = record.stamp;
      var day = stamp ? stamp.day : "unknown";
      if (day !== lastDay) {
        var group = node("section", "doc-workflow-day");
        group.append(
          node(
            "h4",
            "doc-workflow-date",
            stamp ? stamp.date : "Date not recorded",
          ),
        );
        list = node("ol", "doc-workflow-decisions");
        group.append(list);
        timeline.append(group);
        lastDay = day;
      }
      var action = String(event.action || "").toLowerCase();
      var status = [
        "approved",
        "returned",
        "rejected",
        "submitted",
        "cancelled",
        "for_transfer",
        "in_transit",
        "accepted",
      ].includes(action)
        ? action
        : "neutral";
      var item = node("li", "doc-workflow-decision");
      item.setAttribute("data-decision-status", status);
      item.setAttribute("style", "--timeline-index: " + Math.min(index, 8));
      var marker = node("span", "doc-workflow-decision-marker");
      marker.setAttribute("aria-hidden", "true");
      if (isTransfer)
        marker.textContent =
          { for_transfer: "1", in_transit: "2", accepted: "3" }[status] || "·";
      else
        marker.append(
          node(
            "i",
            "fa-solid " +
              ({
                approved: "fa-check",
                returned: "fa-arrow-rotate-left",
                rejected: "fa-xmark",
                submitted: "fa-paper-plane",
                cancelled: "fa-ban",
                for_transfer: "fa-box-archive",
                in_transit: "fa-truck-fast",
                accepted: "fa-check",
              }[status] || "fa-circle"),
          ),
        );
      var heading = node("div", "doc-workflow-decision-heading");
      if (event.step_label)
        heading.append(
          node("h4", "doc-workflow-decision-step", event.step_label),
        );
      heading.append(
        node(
          "strong",
          "doc-workflow-decision-action",
          action.replace(/_/g, " ").replace(/\b\w/g, function (letter) {
            return letter.toUpperCase();
          }),
        ),
      );
      item.append(marker, heading);
      if (event.request_reference || event.transfer_reference)
        item.append(
          node(
            "p",
            "doc-workflow-decision-request",
            event.request_reference || event.transfer_reference,
          ),
        );
      var timestamp = node(
        "time",
        "doc-workflow-decision-time",
        stamp ? stamp.time : "Time not recorded",
      );
      if (stamp) timestamp.setAttribute("datetime", stamp.iso);
      item.append(timestamp);
      var actor = valueText(event.user_name);
      if (event.position_title) actor += " · " + event.position_title;
      item.append(node("p", "doc-workflow-decision-actor", actor));
      if (event.comments && String(event.comments).trim()) {
        var remarks = node("div", "doc-workflow-remarks");
        remarks.append(
          node("strong", "doc-workflow-remarks-label", "Remarks"),
          node("p", "doc-workflow-decision-comment", event.comments),
        );
        item.append(remarks);
      }
      list.append(item);
    });
    return timeline;
  }

  function makeStepHistory(history, order) {
    var group = node("div", "doc-workflow-history");
    group.append(
      node("h5", "doc-workflow-history-title", "Workflow Activity Log"),
      makeDecisionTimeline(history, order),
    );
    return group;
  }

  function makeWorkflowSteps(steps, order) {
    var list = node("ol", "doc-workflow-steps");
    list.setAttribute("aria-label", "Steps");
    steps.filter(function (step) { return step && typeof step === "object"; }).forEach(function (step, index) {
      var rawStatus = String(step.status || "pending").toLowerCase();
      var status = [
        "active",
        "pending",
        "approved",
        "returned",
        "rejected",
        "superseded",
        "cancelled",
      ].includes(rawStatus)
        ? rawStatus
        : "neutral";
      var statusText = String(step.status || "pending")
        .replace(/_/g, " ")
        .replace(/\b\w/g, function (letter) {
          return letter.toUpperCase();
        });
      var tile = node("li", "doc-workflow-step");
      tile.setAttribute("data-step-status", status);
      if (status === "active") tile.setAttribute("aria-current", "step");
      var heading = node("div", "doc-workflow-step-heading");
      var number = node("span", "doc-workflow-step-number", index + 1);
      number.setAttribute("aria-hidden", "true");
      heading.append(
        number,
        node("h4", "doc-workflow-step-name", valueText(step.label)),
        node("span", "doc-workflow-step-status", statusText),
      );
      tile.append(
        heading,
        node("p", "doc-workflow-step-approver", valueText(step.approver_label)),
      );
      if (Array.isArray(step.history) && step.history.length)
        tile.append(makeStepHistory(step.history, order));
      list.append(tile);
    });
    return list;
  }

  function makeAssignedDocuments(section) {
    var wrapper = node("div", "doc-assignment-browser");
    var toolbar = node("div", "doc-assignment-toolbar");
    var label = node("label", "doc-assignment-search-label");
    label.append(
      node("span", "visually-hidden", "Search " + section.label + " documents"),
    );
    var search = node("input", "form-control doc-assignment-search");
    search.type = "search";
    search.placeholder = "Search by title or reference…";
    search.setAttribute("aria-label", "Search " + section.label + " documents");
    search.setAttribute("autocomplete", "off");
    label.append(search);
    toolbar.append(label);
    var destination = section.manage_url && safeLink(section.manage_url);
    if (destination) {
      var manage = node(
        "a",
        "btn btn-sm btn-outline-primary doc-assignment-manage",
        "Manage assignments",
      );
      manage.href = destination;
      toolbar.append(manage);
    }
    var results = node("div", "doc-assignment-results");
    var footer = node("div", "doc-assignment-footer");
    var count = node("span", "doc-assignment-count");
    count.setAttribute("role", "status");
    var pagination = node("div", "doc-assignment-pagination");
    var previous = node(
      "button",
      "btn btn-sm btn-light doc-assignment-previous",
      "Previous",
    );
    var next = node(
      "button",
      "btn btn-sm btn-light doc-assignment-next",
      "Next",
    );
    previous.type = next.type = "button";
    previous.setAttribute("aria-label", "Previous documents");
    next.setAttribute("aria-label", "Next documents");
    pagination.append(previous, next);
    footer.append(count, pagination);
    var page = 0,
      size = section.compact ? 8 : 10;
    function update() {
      var query = String(search.value || "")
        .trim()
        .toLowerCase();
      var matches = section.documents.filter(function (doc) { return doc && typeof doc === "object"; }).filter(function (doc) {
        return (String(doc.title || "") + " " + String(doc.code || ""))
          .toLowerCase()
          .includes(query);
      });
      var start = page * size;
      results.replaceChildren();
      matches.slice(start, start + size).forEach(function (doc) {
        var item = node("article", "doc-assignment-item");
        var emblem = node("span", "doc-assignment-icon");
        emblem.append(icon(section.key));
        var text = node("div", "doc-assignment-text");
        text.append(
          node(
            "span",
            "doc-assignment-reference",
            doc.code || "No reference assigned",
          ),
          node("h4", "doc-assignment-title", doc.title || "Untitled document"),
          node("p", "doc-assignment-detail", doc.detail || ""),
        );
        item.append(emblem, text);
        results.append(item);
      });
      if (!matches.length)
        results.append(
          emptyState(query ? "No documents match your search." : section.empty),
        );
      count.textContent = matches.length
        ? start +
          1 +
          "–" +
          Math.min(start + size, matches.length) +
          " of " +
          matches.length +
          " documents"
        : "0 documents";
      previous.disabled = page === 0;
      next.disabled = start + size >= matches.length;
      pagination.hidden = matches.length <= size;
    }
    search.addEventListener("input", function () {
      page = 0;
      update();
    });
    previous.addEventListener("click", function () {
      if (page > 0) {
        page--;
        update();
      }
    });
    next.addEventListener("click", function () {
      if (!next.disabled) {
        page++;
        update();
      }
    });
    wrapper.append(toolbar, results, footer);
    update();
    return wrapper;
  }

  function renderSection(panel, section) {
    if (Array.isArray(section.documents)) {
      panel.append(sectionTitle(section), makeAssignedDocuments(section));
      return;
    }
    var entries = normalizedEntries(section.fields);
    var hasSteps =
      section.key === "approvals" &&
      Array.isArray(section.steps) &&
      section.steps.length;
    if (hasSteps || Array.isArray(section.events)) {
      panel.classList.add("has-workflow-steps");
      var toolbar = node("div", "doc-workflow-toolbar");
      toolbar.append(
        node(
          "h3",
          "doc-modal-section-title",
          section.key === "transfers" || section.title === "Approval History"
            ? section.title
            : "Approval Workflow",
        ),
      );
      var label = node("label", "doc-workflow-sort-label", "Sort by timestamp");
      var sort = node("select", "form-select form-select-sm doc-workflow-sort");
      sort.setAttribute(
        "aria-label",
        section.key === "transfers"
          ? "Sort transfer history by timestamp"
          : "Sort Workflow Activity Log by timestamp",
      );
      [
        ["oldest", "Oldest first"],
        ["newest", "Newest first"],
      ].forEach(function (option) {
        var item = node("option", "", option[1]);
        item.value = option[0];
        sort.append(item);
      });
      sort.value = "oldest";
      label.append(sort);
      toolbar.append(label);
      panel.append(toolbar);
      var metadata = entries.filter(function (entry) {
        return entry[0] !== "Steps";
      });
      if (hasSteps && metadata.length) panel.append(makeGrid(metadata));
      var result = node("div", "doc-workflow-results");
      result.setAttribute("aria-live", "polite");
      function update() {
        result.replaceChildren(
          hasSteps
            ? makeWorkflowSteps(section.steps, sort.value)
            : section.events.length
              ? makeDecisionTimeline(
                  section.events,
                  sort.value,
                  section.key === "transfers",
                )
              : emptyState(section.empty || "No approval decisions yet."),
        );
      }
      sort.disabled = hasSteps
        ? !section.steps.some(function (step) {
            return step && Array.isArray(step.history) && step.history.length;
          })
        : !section.events.length;
      sort.addEventListener("change", update);
      update();
      panel.append(result);
      return;
    }
    panel.append(sectionTitle(section));
    if (!entries.length) {
      panel.append(emptyState(section.empty));
    } else if (section.key === "files") {
      panel.append(makeFiles(section, entries));
    } else if (HISTORY[section.key]) {
      panel.append(makeActivity(entries));
    } else {
      panel.append(makeGrid(entries));
    }
  }

  function selectTab(shell, index, focusTab) {
    var tabs = Array.from(shell.querySelectorAll('[role="tab"]'));
    var panels = Array.from(shell.querySelectorAll('[role="tabpanel"]'));
    if (!tabs.length || index < 0 || index >= tabs.length) return;
    tabs.forEach(function (tab, i) {
      var active = i === index;
      tab.setAttribute("aria-selected", String(active));
      tab.tabIndex = active ? 0 : -1;
      tab.classList.toggle("is-active", active);
      panels[i].hidden = !active;
    });
    if (focusTab) {
      tabs[index].focus();
      tabs[index].scrollIntoView({
        block: "nearest",
        inline: "nearest",
        behavior: "auto",
      });
    }
  }

  function render(container, data) {
    data = normalizeRecord(data);
    // Validate before the heading scans storage sections as well as before tabs.
    data = Object.assign({}, data, {sections: (Array.isArray(data.sections) ? data.sections : []).filter(function (section) {
      return section && typeof section === "object" && !Array.isArray(section);
    })});
    reset();
    prepareHeading(data);
    container.replaceChildren();
    var body = modal.querySelector(".doc-modal-body");
    if (body) body.scrollTop = 0;
    // Historic or partially created request payloads can contain empty nodes.
    // Discard invalid sections rather than crashing the shared View modal.
    var sections = (Array.isArray(data.sections) ? data.sections : []).filter(function (section) {
      return section && typeof section === "object" && !Array.isArray(section);
    });
    var overview = sections.filter(isOverviewSection);
    var concerns = sections.filter(function (section) {
      return !isOverviewSection(section);
    });
    // User Management's Assigned Documents has its own compact presentation.
    // It is still permission-scoped by the existing authenticated controller.
    var assigned = data.kind === "record" &&
      data.header && data.header.eyebrow === "ASSIGNED DOCUMENTS" &&
      concerns.some(function (part) { return Array.isArray(part.documents); });
    if (assigned) {
      modal.classList.add("is-assignment-view");
      var summary = document.getElementById("viewDocumentSummary");
      if (summary) { summary.hidden = true; summary.replaceChildren(); }
      var totals = node("section", "doc-assignment-summary");
      totals.setAttribute("aria-label", "Assigned document counts");
      var total = concerns.reduce(function (count, part) {
        return count + (Array.isArray(part.documents) ? part.documents.length : 0);
      }, 0);
      [
        ["Total assigned", total],
        ["Hardcopy", concerns.filter(function (part) { return part.key === "hardcopy" && Array.isArray(part.documents); })
          .reduce(function (n, part) { return n + part.documents.length; }, 0)],
        ["Softcopy", concerns.filter(function (part) { return part.key === "softcopy" && Array.isArray(part.documents); })
          .reduce(function (n, part) { return n + part.documents.length; }, 0)]
      ].forEach(function (metric) {
        var item = node("div", "doc-assignment-stat");
        item.append(node("strong", "doc-assignment-stat-value", metric[1]),
          node("span", "doc-assignment-stat-label", metric[0]));
        totals.append(item);
      });
      container.append(totals);
    } else {
      renderOverview(overview);
    }

    // Each tab is one separately scoped concern. Overview is NEVER a tab.
    if (!concerns.length) {
      // Small records use the same overview card design without a
      // meaningless empty tab bar or fake activity section.
      return;
    }
    var layout = node("div", "doc-modal-layout");
    var sidebar = node("nav", "doc-modal-navigation");
    sidebar.setAttribute("role", "tablist");
    sidebar.setAttribute("aria-label", assigned ? "Assigned document types" : "Document detail concerns");
    var main = node("div", "doc-modal-main");
    concerns.forEach(function (section, i) {
      var key = section.key || "overview";
      var id = "viewDocSection" + i;
      var tab = node("button", "doc-modal-nav-item");
      tab.type = "button";
      tab.id = "viewDocTab" + i;
      tab.setAttribute("role", "tab");
      tab.setAttribute("aria-controls", id);
      tab.setAttribute("aria-selected", i === 0 ? "true" : "false");
      tab.tabIndex = i === 0 ? 0 : -1;
      if (i === 0) tab.classList.add("is-active");
      tab.append(
        icon(key),
        node("span", "doc-modal-tab-name", section.title || "Details"),
      );
      sidebar.append(tab);
      var panel = node("section", "doc-modal-panel");
      panel.id = id;
      panel.setAttribute("role", "tabpanel");
      panel.setAttribute("aria-labelledby", tab.id);
      panel.tabIndex = 0;
      panel.hidden = i !== 0;
      renderSection(panel, section);
      main.append(panel);
    });
    layout.append(sidebar, main);
    container.append(layout);
  }

  modal.addEventListener("click", function (event) {
    var tab = event.target.closest('.doc-modal-nav-item[role="tab"]');
    if (!tab || !modal.classList.contains("is-document-view")) return;
    var shell = tab.closest(".doc-modal-layout");
    selectTab(
      shell,
      Array.from(shell.querySelectorAll('[role="tab"]')).indexOf(tab),
      false,
    );
  });

  modal.addEventListener("keydown", function (event) {
    var tab = event.target.closest('.doc-modal-nav-item[role="tab"]');
    if (!tab || !modal.classList.contains("is-document-view")) return;
    var tabs = Array.from(
      tab.closest(".doc-modal-layout").querySelectorAll('[role="tab"]'),
    );
    var at = tabs.indexOf(tab),
      next = at;
    if (event.key === "ArrowRight" || event.key === "ArrowDown")
      next = (at + 1) % tabs.length;
    else if (event.key === "ArrowLeft" || event.key === "ArrowUp")
      next = (at + tabs.length - 1) % tabs.length;
    else if (event.key === "Home") next = 0;
    else if (event.key === "End") next = tabs.length - 1;
    else return;
    event.preventDefault();
    selectTab(tab.closest(".doc-modal-layout"), next, true);
  });

  // Clear permission-scoped record details once the modal closes, so a
  // later generic View Details action never flashes the previous document.
  modal.addEventListener("hidden.bs.modal", function () {
    reset();
    var details = document.getElementById("viewDetails");
    if (details) details.replaceChildren();
  });

  window.PKDocumentView = {
    handles: function (data) {
      return (
        !!data &&
        data.kind === "document" &&
        ["hardcopy", "softcopy"].includes(data.domain) &&
        Array.isArray(data.sections)
      );
    },
    render: render,
    reset: reset,
    formatDates: valueText,
  };
})();
