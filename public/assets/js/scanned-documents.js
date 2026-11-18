(function () {
  "use strict";
  function initialize() {
    var form = document.querySelector('[data-scanned-form]');
    if (!form || form.dataset.scannedReady) return;
    form.dataset.scannedReady = 'yes';
    var type = form.elements.type;
    var doc = form.elements.scanned_document_id;
    function update() {
      var editing = Boolean(form.elements.id.value);
      Array.from(type.options).forEach(function (option) {
        option.disabled = editing && option.value !== type.value;
      });
      form.querySelectorAll('[data-scanned-for]').forEach(function (section) {
        var show = section.dataset.scannedFor.split(' ').includes(type.value);
        section.hidden = !show;
        section.querySelectorAll('input,select,textarea').forEach(function (field) {
          field.disabled = !show;
          field.required = show && field.hasAttribute('data-scanned-required');
        });
      });
      var file = form.querySelector('[type="file"]');
      file.required = type.value === 'scanned_create' && !editing;
      form.elements.title.maxLength = type.value === 'scanned_category_create' ? 150 : 255;
      // Keep the target stable while editing an existing proposal.
      Array.from(doc.options).forEach(function (option) { option.disabled = editing && option.value !== doc.value; });
    }
    function fillDocument() {
      var option = doc.selectedOptions[0];
      var data;
      try { data = JSON.parse(option?.dataset.scannedRecord || '{}'); } catch (_) { data = {}; }
      form.elements.document_version.value = data.version || '';
      if (!data.id) return;
      form.elements.title.value = data.title || '';
      form.elements.description.value = data.description || '';
      form.elements.scanned_category_id.value = data.category_id || '';
      ['retention_start_date', 'retention_end_date'].forEach(function (key) { form.elements[key].value = data[key] || ''; });
    }
    type.addEventListener('change', update);
    doc.addEventListener('change', fillDocument);
    document.getElementById('editModal')?.addEventListener('show.bs.modal', update);
    form.addEventListener('reset', function () { setTimeout(update, 0); });
    var params = new URLSearchParams(window.location.search);
    var action = params.get('action');
    var picker = document.getElementById('requestActionPicker');
    var allowed = Array.from(type.options, function (option) { return option.value; });
    if (action === 'scanned_create' && !allowed.includes(action))
      action = 'scanned_category_create';
    if (picker && allowed.includes(action)) {
      picker.value = action;
      picker.dispatchEvent(new Event('change', { bubbles: true }));
      doc.value = params.get('document') || '';
      fillDocument();
      update();
      window.bootstrap?.Modal.getOrCreateInstance(document.getElementById('editModal')).show();
    }
    update();
  }
  initialize();
})();
