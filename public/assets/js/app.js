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
            if (target.id === 'stepForm') updateStepApprover();
            if (target.hasAttribute('data-location-upsert')) updateLocationHierarchy();
            if (target.hasAttribute('data-hardcopy-upsert')) updateHardcopyHierarchy();
            updateDocumentDomain();
            updateDisposalReason();
            updateHardcopyRequestAction();
            if (target.id==='assignmentForm') updateDocumentDomain();
            updateHardcopyRetention();
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
            var stepKey = form.querySelector('[name="step_key"]');
            if (stepKey) stepKey.value = action.getAttribute('data-step-key') || '';
            var title = action.getAttribute('data-title') || 'Confirm Action';
            document.getElementById('actionTitle').textContent = title;
            document.getElementById('actionDescription').textContent =
                action.getAttribute('data-description') || 'This action will be saved to the system.';
            form.setAttribute('data-confirm', title + '?');
            var disposal=form.querySelector('#actionDisposalFields');
            if (disposal) {
                disposal.hidden=action.getAttribute('data-disposal')!=='yes';
                disposal.querySelectorAll('select,input').forEach(function(field) {
                    field.disabled=disposal.hidden;
                    field.required=!disposal.hidden && field.hasAttribute('data-disposal-reason');
                });
                updateDisposalReason();
            }
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
            series_number: ['softcopy_create'],
            category_id: ['softcopy_create'],
            recipient_id: ['assignment','access','transfer'],
            expires_at: ['access'],
            destination_location_id: ['transfer'],
            new_revision_level: ['softcopy_revise'],
            effective_date: ['softcopy_revise'],
            // Received and released dates are recorded by PHP, not user inputs.
            page_number: ['softcopy_revise'],
            revision_attachment: ['softcopy_create','softcopy_revise']
        };
        Object.keys(required).forEach(function (name) {
            var field = select.form.querySelector('[name="' + name + '"]');
            if (!field) return;
            var show = required[name].includes(operation);
            var selectedDomain = select.form.querySelector('[name="document_domain"]')?.value;
            if (selectedDomain && ['assignment','access'].includes(operation)) {
                if (name==='softcopy_id') show=selectedDomain==='softcopy';
                if (name==='hardcopy_id') show=selectedDomain==='hardcopy';
            }
            var parent = field.closest('[data-document-domain]') ||
                field.closest('.col-md-6, .col-12');
            if (parent) parent.hidden = !show;
            field.disabled = !show;
            // Requests may retain a staged revision, but direct revisions
            // must always attach a file for immediate approval.
            var direct = select.form.dataset.softcopyDirect === 'yes';
            field.required = show && (name !== 'revision_attachment' || direct)
                && name !== 'series_number' && name !== 'effective_date';
            if (name === 'revision_attachment' && !direct && !select.form.querySelector('[name="id"]')?.value) {
                field.required = show;
            }
        });
    }
    function updateDocumentDomain() {
        document.querySelectorAll('form').forEach(function(form) {
            var select=form.querySelector('[name="document_domain"]');
            if (!select) return;
            var domain=select.value;
            form.querySelectorAll('[data-document-domain]').forEach(function(section) {
                var show=section.dataset.documentDomain===domain;
                section.hidden=!show;
                section.querySelectorAll('select').forEach(function(field) {
                    field.disabled=!show;
                    field.required=show;
                    if (!show) field.value='';
                });
            });
        });
    }
    function updateDisposalReason() {
        document.querySelectorAll('[data-disposal-reason]').forEach(function(select) {
            var form=select.form;
            if (!form) return;
            var other=form.querySelector('[data-disposal-other]');
            if (!other) return;
            var show=select.value==='other';
            other.hidden=!show;
            other.querySelectorAll('input').forEach(function(field) {
                field.disabled=!show;
                field.required=show;
                if (!show) field.value='';
            });
        });
    }
    document.addEventListener('change', function(event) {
        if (event.target?.id==='reqType') updateRequestActionFields();
        if (event.target?.name==='document_domain') {
            updateDocumentDomain(); updateRequestActionFields();
        }
        if (event.target?.hasAttribute('data-disposal-reason')) updateDisposalReason();
    });
    updateDocumentDomain();
    updateDisposalReason();
    updateRequestActionFields();

    function updateStepApprover() {
        var type = document.getElementById('sType');
        if (!type) return;
        var selected = type.value;
        document.querySelectorAll('[data-approver-option]').forEach(function (section) {
            var visible = section.getAttribute('data-approver-option') === selected;
            section.hidden = !visible;
            section.querySelectorAll('select').forEach(function (field) {
                field.disabled = !visible;
                field.required = visible;
            });
        });
        var info = document.getElementById('approverAutoInfo');
        if (info) info.hidden = selected === 'user' || selected === 'role';
    }
    document.addEventListener('change', function (event) {
        if (event.target && event.target.id === 'sType') updateStepApprover();
    });
    updateStepApprover();

    // Location upsert uses live predefined Area > Specific > Asset relationships.
    // The PHP service validates all relationships again before writing to MySQL.
    function updateLocationHierarchy(changed) {
        var form = document.querySelector('#editForm[data-location-upsert]');
        if (!form) return;
        var area = form.querySelector('[data-location-level="area"]');
        var specific = form.querySelector('[data-location-level="specific"]');
        var asset = form.querySelector('[data-location-level="asset"]');
        if (!area || !specific || !asset) return;

        function selectedOption(select) {
            return select.options[select.selectedIndex] || null;
        }

        if (changed === 'asset' && asset.value) {
            var chosenAsset = selectedOption(asset);
            specific.value = chosenAsset.dataset.specificId || '';
            area.value = chosenAsset.dataset.areaId || '';
        } else if (changed === 'specific' && specific.value) {
            var chosenSpecific = selectedOption(specific);
            area.value = chosenSpecific.dataset.areaId || '';
        }

        Array.from(specific.options).forEach(function (option) {
            if (!option.value) return;
            var available = !area.value || option.dataset.areaId === area.value;
            option.hidden = !available;
            option.disabled = !available;
        });
        if (selectedOption(specific) && selectedOption(specific).disabled) {
            specific.value = '';
        }

        Array.from(asset.options).forEach(function (option) {
            if (!option.value) return;
            var matchesSpecific = !specific.value || option.dataset.specificId === specific.value;
            var matchesArea = !area.value || option.dataset.areaId === area.value;
            var available = matchesSpecific && matchesArea;
            option.hidden = !available;
            option.disabled = !available;
        });
        if (selectedOption(asset) && selectedOption(asset).disabled) {
            asset.value = '';
        }

        var help = form.querySelector('#location-hierarchy-help');
        if (help) {
            help.textContent = asset.value ? 'Area and specific automatically match the selected asset.'
                : specific.value ? 'Area automatically matches the selected specific.'
                : 'Select an area to filter specifics and assets, or leave the hierarchy optional.';
        }
    }

    document.addEventListener('change', function (event) {
        var level = event.target && event.target.getAttribute('data-location-level');
        if (level) updateLocationHierarchy(level);
    });
    updateLocationHierarchy();

    // Hardcopy Upsert uses predefined pk_dts references; all relationships
    // are rechecked by the PHP model/service regardless of browser input.
    function updateHardcopyRetention() {
        document.querySelectorAll('#editForm [name="retention_enabled"]').forEach(function (toggle) {
            var form=toggle.form;
            if (!form) return;
            var checked=toggle.checked;
            form.querySelectorAll('[data-hardcopy-retention]').forEach(function (section) {
                section.hidden=!checked;
                section.querySelectorAll('input').forEach(function (field) {
                    field.disabled=!checked;
                    field.required=checked;
                    if (!checked) field.value='';
                });
            });
        });
    }

    // When editing an existing hardcopy request, fill the same predefined
    // upsert fields used by Direct Hardcopy, not only the title.
    function fillHardcopyRequestFromSelection() {
        var form=document.querySelector('#editForm[data-hardcopy-upsert]');
        if (!form || !form.querySelector('[data-hardcopy-existing]')) return;
        var select=form.querySelector('[name="hardcopy_id"]');
        var option=select?.options[select.selectedIndex];
        if (!option?.dataset.hardcopy) return;
        var record;
        try { record=JSON.parse(option.dataset.hardcopy); }
        catch(error) { return; }
        [
          'title','area_id','specific_id','asset_id','location_id','sequence_number',
          'retention_start_date','retention_end_date'
        ].forEach(function(name) {
            var input=form.querySelector('[name="'+name+'"]');
            if (input) input.value=record[name]??'';
        });
        var holder=form.querySelector('select[name="holder_id"]');
        if (holder) holder.value=record.holder_id??'';
        var retention=form.querySelector('[name="retention_enabled"]');
        if (retention) retention.checked=Number(record.retention_enabled)===1;
        updateHardcopyHierarchy();
        updateHardcopyRetention();
    }
    document.addEventListener('change',function(event) {
        if (event.target?.id==='reqHardcopy') fillHardcopyRequestFromSelection();
    });

    function updateHardcopyRequestAction() {
        var form=document.querySelector('#editForm');
        if (!form || !form.querySelector('[data-hardcopy-existing]')) return;
        var type=form.querySelector('#reqType')?.value;
        var existing=form.querySelector('[data-hardcopy-existing]');
        var details=form.querySelector('[data-hardcopy-details]');
        var requiresExisting=type==='hardcopy_update' || type==='disposal';
        existing.hidden=!requiresExisting;
        existing.querySelectorAll('select').forEach(function (field) {
            field.disabled=!requiresExisting;
            field.required=requiresExisting;
        });
        var disposal=form.querySelector('[data-hardcopy-disposal]');
        if (disposal) {
            var isDisposal=type==='disposal';
            disposal.hidden=!isDisposal;
            disposal.querySelectorAll('input,select').forEach(function(field) {
                field.disabled=!isDisposal;
                field.required=isDisposal && field.hasAttribute('data-disposal-reason');
            });
        }
        var showDetails=type==='hardcopy_create' || type==='hardcopy_update';
        details.hidden=!showDetails;
        details.querySelectorAll('input, select, textarea').forEach(function (field) {
            if (field.name==='holder_id' && field.type==='hidden') return;
            field.disabled=!showDetails;
            if (!showDetails) field.required=false;
        });
        if (showDetails) {
            var title=details.querySelector('[name="title"]');
            if (title) title.required=true;
        }
        updateHardcopyRetention();
        updateDisposalReason();
    }
    document.addEventListener('change', function (e) {
        if (e.target?.name==='retention_enabled') updateHardcopyRetention();
        if (e.target?.id==='reqType') updateHardcopyRequestAction();
    });
    updateHardcopyRetention();
    updateHardcopyRequestAction();

    function updateHardcopyHierarchy(changed) {
        var form=document.querySelector('#editForm[data-hardcopy-upsert]');
        if (!form) return;
        var area=form.querySelector('[data-hardcopy-level="area"]');
        var specific=form.querySelector('[data-hardcopy-level="specific"]');
        var asset=form.querySelector('[data-hardcopy-level="asset"]');
        var location=form.querySelector('[data-hardcopy-level="location"]');
        if (!area || !specific || !asset || !location) return;

        function chosen(select) {
            return select.options[select.selectedIndex] || null;
        }
        function filter(select, match) {
            Array.from(select.options).forEach(function (option) {
                if (!option.value) return;
                var valid=match(option);
                option.disabled=!valid;
                option.hidden=!valid;
            });
            var selected=chosen(select);
            if (selected && selected.disabled) select.value='';
        }
        if (changed==='location' && location.value) {
            var loc=chosen(location);
            area.value=loc.dataset.areaId==='0'?'':(loc.dataset.areaId||'');
            specific.value=loc.dataset.specificId==='0'?'':(loc.dataset.specificId||'');
            asset.value=loc.dataset.assetId==='0'?'':(loc.dataset.assetId||'');
        } else if (changed==='asset' && asset.value) {
            var a=chosen(asset);
            area.value=a.dataset.areaId||'';
            specific.value=a.dataset.specificId||'';
        } else if (changed==='specific' && specific.value) {
            area.value=chosen(specific).dataset.areaId||'';
        }
        filter(specific,function(opt) {
            return !area.value || opt.dataset.areaId===area.value;
        });
        filter(asset,function(opt) {
            return (!area.value || opt.dataset.areaId===area.value) &&
                   (!specific.value || opt.dataset.specificId===specific.value);
        });
        filter(location,function(opt) {
            return (!area.value || !opt.dataset.areaId || opt.dataset.areaId==='0' ||
                    opt.dataset.areaId===area.value) &&
                   (!specific.value || !opt.dataset.specificId ||
                    opt.dataset.specificId==='0' || opt.dataset.specificId===specific.value) &&
                   (!asset.value || !opt.dataset.assetId || opt.dataset.assetId==='0' ||
                    opt.dataset.assetId===asset.value);
        });
        var help=form.querySelector('#hardcopyHierarchyHelp');
        if (help) {
            help.textContent=location.value
                ? 'Location selected. Its predefined area, specific and asset have been populated.'
                : asset.value ? 'Asset selected. Its area and specific are populated automatically.'
                : specific.value ? 'Specific selected. The parent area is populated automatically.'
                : 'Choose an area to filter the available specifics, assets and locations.';
        }
    }
    document.addEventListener('change', function(event) {
        var level=event.target && event.target.getAttribute('data-hardcopy-level');
        if (level) updateHardcopyHierarchy(level);
    });
    updateHardcopyHierarchy();

    function updateHardcopyTransfer(changed) {
        var form=document.querySelector('#editForm[data-hardcopy-transfer]');
        if (!form) return;
        var source=form.querySelector('[data-transfer-source]');
        var area=form.querySelector('[data-transfer-level="area"]');
        var specific=form.querySelector('[data-transfer-level="specific"]');
        var asset=form.querySelector('[data-transfer-level="asset"]');
        var location=form.querySelector('[data-transfer-level="location"]');
        if (!source || !area || !specific || !asset || !location) return;
        var doc={};
        try {doc=JSON.parse(source.options[source.selectedIndex]?.dataset.transferDoc||'{}');}
        catch(e) {doc={};}
        form.querySelectorAll('[data-transfer-origin]').forEach(function(input) {
            input.value=doc[input.dataset.transferOrigin]||'Not assigned';
        });
        if (changed==='location' && location.value) {
            var chosen=location.options[location.selectedIndex];
            area.value=chosen.dataset.areaId==='0'?'':(chosen.dataset.areaId||'');
            specific.value=chosen.dataset.specificId==='0'?'':(chosen.dataset.specificId||'');
            asset.value=chosen.dataset.assetId==='0'?'':(chosen.dataset.assetId||'');
        } else if (changed==='asset' && asset.value) {
            var chosen=asset.options[asset.selectedIndex];
            specific.value=chosen.dataset.specificId||'';
            area.value=chosen.dataset.areaId||'';
        } else if (changed==='specific' && specific.value) {
            area.value=specific.options[specific.selectedIndex].dataset.areaId||'';
        }
        function filter(select,valid) {
            Array.from(select.options).forEach(function(o) {
                if (!o.value) return;
                o.disabled=o.hidden=!valid(o);
            });
            if (select.options[select.selectedIndex]?.disabled) select.value='';
        }
        filter(specific,function(o){return !area.value||o.dataset.areaId===area.value;});
        filter(asset,function(o){return (!area.value||o.dataset.areaId===area.value) &&
            (!specific.value||o.dataset.specificId===specific.value);});
        filter(location,function(o){
            return (!area.value||o.dataset.areaId==='0'||o.dataset.areaId===area.value) &&
                (!specific.value||o.dataset.specificId==='0'||o.dataset.specificId===specific.value) &&
                (!asset.value||o.dataset.assetId==='0'||o.dataset.assetId===asset.value) &&
                (!doc.location_id||String(doc.location_id)!==o.value);
        });
        var hint=form.querySelector('#transferHelp');
        if (hint) hint.textContent=location.value?
            'Destination selected. Its parent Places were populated.' :
            'Select a predefined destination different from the original location.';
    }
    document.addEventListener('change',function(e) {
        if (e.target?.hasAttribute('data-transfer-source')) updateHardcopyTransfer('source');
        var level=e.target?.getAttribute('data-transfer-level');
        if (level) updateHardcopyTransfer(level);
    });
    updateHardcopyTransfer();

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
