/* Shared progressive enhancements: all writes use ordinary CI3 forms, no API calls. */
(function () {
    'use strict';

    // Works on offline office networks when Bootstrap's CDN cannot be reached.
    // Keep native server form actions and confirmation semantics unchanged.
    if (!window.bootstrap) {
        document.body.classList.add('offline-ui');
        window.bootstrap = {
            Modal: {
                getOrCreateInstance: function (element) {
                    return {
                        show: function () {
                            if (!element) return;
                            element.style.display = 'block';
                            element.classList.add('show');
                            element.removeAttribute('aria-hidden');
                            var backdrop = document.createElement('div');
                            backdrop.className = 'modal-backdrop fallback-backdrop';
                            document.body.appendChild(backdrop);
                        },
                        hide: function () {
                            if (!element) return;
                            element.style.display = 'none';
                            element.classList.remove('show');
                            element.setAttribute('aria-hidden', 'true');
                            document.querySelectorAll('.fallback-backdrop').forEach(function (node) {
                                node.remove();
                            });
                            element.dispatchEvent(new Event('hidden.bs.modal'));
                        }
                    };
                }
            }
        };
        document.addEventListener('click', function (event) {
            var trigger = event.target.closest('[data-bs-toggle]');
            if (trigger) {
                var type = trigger.getAttribute('data-bs-toggle');
                if (type === 'modal') {
                    var target = document.querySelector(trigger.getAttribute('data-bs-target'));
                    if (target) window.bootstrap.Modal.getOrCreateInstance(target).show();
                } else if (type === 'dropdown') {
                    var menu = trigger.closest('.dropdown').querySelector('.dropdown-menu');
                    menu.classList.toggle('show');
                } else if (type === 'collapse') {
                    var pane = document.querySelector(trigger.getAttribute('data-bs-target'));
                    if (pane) pane.classList.toggle('show');
                }
            }
            var dismiss = event.target.closest('[data-bs-dismiss="modal"]');
            if (dismiss) {
                var modal = dismiss.closest('.modal');
                if (modal) window.bootstrap.Modal.getOrCreateInstance(modal).hide();
            }
            if (event.target.classList.contains('modal') && event.target.classList.contains('show')) {
                window.bootstrap.Modal.getOrCreateInstance(event.target).hide();
            }
        });
        document.addEventListener('keydown', function (event) {
            if (event.key !== 'Escape') return;
            var modal = document.querySelector('.modal.show');
            if (modal) window.bootstrap.Modal.getOrCreateInstance(modal).hide();
        });
    }

    var sidebar = document.getElementById('appSidebar');
    var toggle = document.getElementById('sidebarToggle');
    if (toggle && sidebar) toggle.addEventListener('click', function () {
        sidebar.classList.toggle('is-open');
    });

    function parse(button, key) {
        try { return JSON.parse(button.getAttribute('data-' + key) || '{}'); }
        catch (error) { return {}; }
    }

    document.addEventListener('click', function (event) {
        var edit = event.target.closest('.js-edit');
        if (edit) {
            var target = document.querySelector(edit.getAttribute('data-target') || '#editForm');
            if (!target) return;
            target.reset();
            var record = parse(edit, 'record');
            target.querySelectorAll('input[name="permissions[]"]').forEach(function (field) {
                field.checked = (record.permission_ids || []).map(Number).includes(Number(field.value));
            });
            target.querySelectorAll('[name]').forEach(function (field) {
                if (!Object.prototype.hasOwnProperty.call(record, field.name)) return;
                if (field.type === 'checkbox') field.checked = !!Number(record[field.name]);
                else field.value = record[field.name] == null ? '' : record[field.name];
            });
            var heading = document.getElementById(target.id === 'stepForm' ? 'stepTitle' : 'editTitle');
            if (heading) heading.textContent = edit.getAttribute('data-title') || 'Edit Record';
            target.dataset.confirmed = '';
            if (target.dataset.requestType) refreshRequestForm();
            updateRequestActionFields();
        }

        var view = event.target.closest('.js-view');
        if (view) {
            var data = parse(view, 'display');
            var details = document.getElementById('viewDetails');
            var title = document.getElementById('viewModalTitle');
            if (title) title.textContent = view.getAttribute('data-title') || 'Details';
            if (details) {
                details.replaceChildren();
                Object.keys(data).forEach(function (key) {
                    var dt = document.createElement('dt');
                    var dd = document.createElement('dd');
                    dt.textContent = key;
                    dd.textContent = data[key] == null || data[key] === '' ? '—' : String(data[key]);
                    details.appendChild(dt);
                    details.appendChild(dd);
                });
            }
        }

        var fileHistory = event.target.closest('.js-file-history');
        if (fileHistory) {
            var list = document.getElementById('fileHistoryList');
            if (list) {
                list.replaceChildren();
                parse(fileHistory, 'files').forEach(function (file) {
                    var item = document.createElement('div');
                    item.className = 'd-flex justify-content-between align-items-center gap-3 border-bottom py-3';
                    var meta = document.createElement('div');
                    var name = document.createElement('strong');
                    var small = document.createElement('small');
                    name.className = 'd-block';
                    small.className = 'text-secondary d-block mt-1';
                    name.textContent = file.name;
                    small.textContent = file.detail;
                    meta.append(name, small);
                    var download = document.createElement('a');
                    download.className = 'btn btn-outline-primary btn-sm';
                    download.href = file.url;
                    download.textContent = 'Download';
                    item.append(meta, download);
                    list.appendChild(item);
                });
            }
        }

        var upload = event.target.closest('.js-upload');
        if (upload) {
            document.getElementById('uploadDocumentId').value = upload.getAttribute('data-document-id') || '';
            document.getElementById('uploadDocumentName').textContent = upload.getAttribute('data-document-name') || '';
        }

        var action = event.target.closest('.js-action');
        if (action) {
            var form = document.getElementById('actionForm');
            if (!form) return;
            form.reset();
            form.dataset.confirmed = '';
            form.action = action.getAttribute('data-url');
            form.querySelector('[name="id"]').value = action.getAttribute('data-id') || '';
            form.querySelector('[name="decision"]').value = action.getAttribute('data-decision') || '';
            var title = action.getAttribute('data-title') || 'Confirm Action';
            document.getElementById('actionTitle').textContent = title;
            document.getElementById('actionDescription').textContent =
                action.getAttribute('data-description') || 'This action will be saved to the system.';
            form.setAttribute('data-confirm', title + '?');
        }
    });

    // Sections shown in the modal depend on the request's operation.
    function refreshRequestForm() {
        var form = document.querySelector('#editForm[data-request-type]');
        if (!form) return;
        var operation = form.querySelector('[name="operation"]').value;
        var sections = {
            document: operation !== 'create',
            proposal: operation === 'create' || operation === 'revise',
            location: operation === 'transfer',
            user: operation === 'grant' || operation === 'assign',
            expiry: operation === 'grant'
        };
        Object.keys(sections).forEach(function (key) {
            var section = form.querySelector('[data-request-section="' + key + '"]');
            if (!section) return;
            section.hidden = !sections[key];
            section.querySelectorAll('input,select,textarea').forEach(function (field) {
                field.disabled = !sections[key];
                field.required = sections[key] && (
                    (key === 'proposal' && ['proposed_code', 'proposed_title', 'proposed_version'].includes(field.name)) ||
                    (key === 'document' && field.name === 'document_id') ||
                    (key === 'location' && field.name === 'target_place_id') ||
                    (key === 'user' && field.name === 'target_user_id')
                );
            });
        });
    }
    document.addEventListener('change', function (event) {
        if (event.target.matches('#requestOperation')) refreshRequestForm();
    });
    refreshRequestForm();

    // One request modal is reused across all request types; hide irrelevant fields.
    function updateRequestActionFields() {
        var select = document.getElementById('reqType');
        if (!select) return;
        var operation = select.value;
        var required = {
            softcopy_id: ['softcopy_revise','softcopy_cancel','assignment','access'],
            hardcopy_id: ['hardcopy_update','disposal','transfer'],
            title: ['softcopy_create','hardcopy_create','hardcopy_update','softcopy_revise'],
            document_number: ['softcopy_create'],
            category_id: ['softcopy_create'],
            recipient_id: ['assignment','access','transfer'],
            expires_at: ['access'],
            destination_location_id: ['transfer'],
            new_revision_level: ['softcopy_revise'],
            effective_date: ['softcopy_revise'],
            date_received: ['softcopy_revise'],
            date_released: ['softcopy_revise'],
            page_number: ['softcopy_revise'],
            revision_attachment: ['softcopy_revise']
        };
        Object.keys(required).forEach(function (name) {
            var field = select.form.querySelector('[name="' + name + '"]');
            if (!field) return;
            var show = required[name].includes(operation);
            var parent = field.closest('.col-md-6, .col-12');
            if (parent) parent.hidden = !show;
            field.disabled = !show;
            field.required = show && name !== 'revision_attachment';
        });
    }
    document.addEventListener('change', function (event) {
        if (event.target && event.target.id === 'reqType') updateRequestActionFields();
    });
    updateRequestActionFields();

    var pendingForm = null;
    var pendingParent = null;
    function showConfirmation() {
        window.bootstrap.Modal.getOrCreateInstance(document.getElementById('confirmModal')).show();
    }
    var confirmation = document.getElementById('confirmModal');
    if (confirmation) confirmation.addEventListener('hidden.bs.modal', function () {
        if (pendingForm && pendingParent) {
            window.bootstrap.Modal.getOrCreateInstance(pendingParent).show();
        }
        pendingParent = null;
        pendingForm = null;
    });
    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (!form.matches('form[data-confirm]') || form.dataset.confirmed === 'yes') return;
        if (!window.bootstrap) return; // Server also checks confirmed=yes.
        event.preventDefault();
        pendingForm = form;
        document.getElementById('confirmMessage').textContent = form.getAttribute('data-confirm') || 'Continue?';
        pendingParent = form.closest('.modal.show');
        if (pendingParent) {
            pendingParent.addEventListener('hidden.bs.modal', showConfirmation, {once: true});
            window.bootstrap.Modal.getOrCreateInstance(pendingParent).hide();
        } else {
            showConfirmation();
        }
    });

    var confirmButton = document.getElementById('confirmProceed');
    if (confirmButton) confirmButton.addEventListener('click', function () {
        if (!pendingForm) return;
        var form = pendingForm;
        pendingForm = null;
        window.bootstrap.Modal.getOrCreateInstance(document.getElementById('confirmModal')).hide();
        form.dataset.confirmed = 'yes';
        var flag = form.querySelector('[name="confirmed"]');
        if (flag) flag.value = 'yes';
        form.requestSubmit();
    });

    document.querySelectorAll('[data-auto-submit]').forEach(function (control) {
        control.addEventListener('change', function () {
            if (this.form) {
                var page = this.form.querySelector('[name="page"]');
                if (page) page.value = '1';
                this.form.submit();
            }
        });
    });
})();
