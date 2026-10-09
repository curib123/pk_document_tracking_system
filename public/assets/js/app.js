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

    function renderRecordDetails(container,data) {
        container.replaceChildren();
        var sections=Array.isArray(data.sections)?data.sections:[{fields:data}];
        sections.forEach(function(section) {
            var block=document.createElement('section');block.className='document-detail-section';
            if (section.title) {
                var heading=document.createElement('h3');heading.className='h6';heading.textContent=section.title;block.append(heading);
            }
            var entries=Object.entries(section.fields||{});
            if (!entries.length) {
                var empty=document.createElement('p');empty.className='small text-secondary';
                empty.textContent=section.empty||'No details are available.';block.append(empty);
            } else {
                var list=document.createElement('dl');list.className='detail-grid mb-0';
                entries.forEach(function(pair) {
                    var term=document.createElement('dt'),value=document.createElement('dd');
                    term.textContent=pair[0];value.textContent=pair[1]==null||pair[1]===''?'—':String(pair[1]);
                    list.append(term,value);
                });block.append(list);
            }
            (section.links||[]).forEach(function(item) {
                try {
                    var url=new URL(item.url,window.location.origin);
                    if (url.origin!==window.location.origin || !['http:','https:'].includes(url.protocol)) return;
                    var link=document.createElement('a');link.className='btn btn-sm btn-outline-primary mt-2 me-2';
                    link.href=url.href;link.textContent=item.label;block.append(link);
                } catch (error) { /* A malformed link never becomes an executable URL. */ }
            });
            container.append(block);
        });
    }

    // Each data cell opens the exact same reusable View modal as the eye
    // action; action buttons and links remain independent and never trigger it.
    function showTableCellDetails(row) {
        if (!row || !row.hasAttribute('data-row-view')) return;
        var data;
        try { data=JSON.parse(row.getAttribute('data-row-view')||'{}'); }
        catch(error) { return; }
        var modal=document.getElementById('viewModal');
        var details=document.getElementById('viewDetails');
        var heading=document.getElementById('viewModalTitle');
        if (!modal || !details) return;
        renderRecordDetails(details,data);
        if (heading) heading.textContent=row.getAttribute('data-row-title')||'View Details';
        window.bootstrap.Modal.getOrCreateInstance(modal).show();
    }
    document.addEventListener('click',function(event) {
        var cell=event.target.closest('td.table-cell-view');
        if (!cell || event.target.closest('a,button,input,select,textarea,label')) return;
        showTableCellDetails(cell.closest('tr[data-row-view]'));
    });
    document.addEventListener('keydown',function(event) {
        if (event.key!=='Enter' && event.key!==' ') return;
        var cell=event.target.closest('td.table-cell-view');
        if (!cell || event.target!==cell) return;
        event.preventDefault();
        showTableCellDetails(cell.closest('tr[data-row-view]'));
    });

    var sidebar=document.getElementById('appSidebar');
    var toggle=document.getElementById('sidebarToggle');
    var sidebarBackdrop=document.getElementById('sidebarBackdrop');
    var mobileNavigation=window.matchMedia('(max-width: 991.98px)');
    function setSidebar(open,returnFocus) {
        if (!sidebar || !toggle) return;
        open=!!open && mobileNavigation.matches;
        sidebar.classList.toggle('is-open',open);
        sidebar.inert=mobileNavigation.matches && !open;
        if (mobileNavigation.matches) sidebar.setAttribute('aria-hidden',open?'false':'true');
        else sidebar.removeAttribute('aria-hidden');
        toggle.setAttribute('aria-expanded',String(open));
        if (sidebarBackdrop) sidebarBackdrop.hidden=!open;
        document.body.classList.toggle('sidebar-open',open);
        if (open) sidebar.querySelector('button,a')?.focus();
        else if (returnFocus) toggle.focus();
    }
    toggle?.addEventListener('click',function(){setSidebar(!sidebar?.classList.contains('is-open'),true);});
    sidebarBackdrop?.addEventListener('click',function(){setSidebar(false,true);});
    document.getElementById('sidebarClose')?.addEventListener('click',function(){setSidebar(false,true);});
    sidebar?.addEventListener('click',function(event){if (event.target.closest('a')) setSidebar(false,false);});
    mobileNavigation.addEventListener('change',function(){setSidebar(false,false);});
    document.addEventListener('keydown',function(event) {
        if (!sidebar?.classList.contains('is-open') || document.querySelector('.modal.show')) return;
        if (event.key==='Escape') {event.preventDefault();setSidebar(false,true);}
        if (event.key==='Tab') {
            var focusable=Array.from(sidebar.querySelectorAll('button,a[href]')).filter(function(node){return node.offsetParent!==null;});
            var first=focusable[0],last=focusable[focusable.length-1];
            if (event.shiftKey && document.activeElement===first) {event.preventDefault();last?.focus();}
            else if (!event.shiftKey && document.activeElement===last) {event.preventDefault();first?.focus();}
        }
    });
    setSidebar(false,false);

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
            var heading = document.getElementById(target.id === 'stepForm' ? 'stepTitle'
                : (target.id === 'assignmentForm' ? 'assignmentModalTitle' : 'editTitle'));
            if (heading) heading.textContent = edit.getAttribute('data-title') || 'Edit Record';
            target.dataset.confirmed = '';
            updateRequestActionFields();
            if (target.id === 'stepForm') updateStepApprover();
            if (target.hasAttribute('data-location-upsert')) updateLocationHierarchy();
            if (target.hasAttribute('data-hardcopy-upsert')) updateHardcopyHierarchy();
            updateDocumentDomain();
            updateDisposalReason();
            updateHardcopyRequestAction();
            if (target.id==='assignmentForm') updateDocumentDomain();
            updateHardcopyRetention();
            updateHardcopyTransfer();
        }

        var view = event.target.closest('.js-view');
        if (view) {
            var data = parse(view, 'display');
            var details = document.getElementById('viewDetails');
            var title = document.getElementById('viewModalTitle');
            if (title) title.textContent = view.getAttribute('data-title') || 'Details';
            if (details) renderRecordDetails(details,data);
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
            document.querySelector('#uploadModal form')?.reset();
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

    // One request modal is reused across all request types; hide irrelevant fields.
    function updateRequestActionFields() {
        var select = document.getElementById('reqType');
        if (!select) return;
        var allowed;
        try { allowed=JSON.parse(select.form.dataset.allowedRequestTypes||'null'); } catch (error) { allowed=[]; }
        var editing=!!select.form.querySelector('[name="id"]')?.value;
        var current=select.value;
        Array.from(select.options).forEach(function(option) {
            var permitted=(!Array.isArray(allowed)||allowed.includes(option.value)) && (!editing||option.value===current);
            option.disabled=option.hidden=!permitted;
        });
        if (select.selectedOptions[0]?.disabled || !select.value) {
            select.value=Array.from(select.options).find(function(option){return !option.disabled;})?.value||'';
        }
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
    // Selecting a different softcopy starts from that record, never a previous
    // document's proposed title, revision attachment or effective date.
    document.addEventListener('change',function(event) {
        if (event.target?.id!=='reqSoftcopy') return;
        var source=event.target, form=source.form;
        var record={};
        try {record=JSON.parse(source.selectedOptions[0]?.dataset.softcopyRecord||'{}');} catch (error) {}
        ['title','document_number','series_number','category_id'].forEach(function(name) {
            var field=form.querySelector('[name="'+name+'"]');
            if (field) field.value=record[name]??'';
        });
        ['new_revision_level','effective_date','revision_attachment'].forEach(function(name) {
            var field=form.querySelector('[name="'+name+'"]');
            if (field) field.value='';
        });
        var subject=form.querySelector('[name="subject"]');
        if (subject && (!subject.value || subject.value===subject.dataset.autoSubject)) {
            subject.value=source.value?'Document action: '+(record.title||'Selected softcopy'):'';
            subject.dataset.autoSubject=subject.value;
        }
    });

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
        var type=document.getElementById('stepApproverType');
        if (!type) return;
        [['stepUserField','user'],['stepRoleField','role']].forEach(function(pair) {
            var section=document.getElementById(pair[0]);
            if (!section) return;
            section.hidden=type.value!==pair[1];
            section.querySelectorAll('select').forEach(function(field) {
                field.disabled=section.hidden;
                field.required=!section.hidden;
            });
        });
    }
    var workflowParent=null;
    document.addEventListener('change',function(event) {
        if (event.target?.id==='stepApproverType') updateStepApprover();
        if (event.target?.hasAttribute('data-workflow-version')) {
            var modal=event.target.closest('.workflow-steps-modal');
            modal?.querySelectorAll('[data-workflow-version-panel]').forEach(function(panel) {
                panel.hidden=panel.dataset.workflowVersionPanel!==event.target.value;
            });
        }
    });
    document.addEventListener('click',function(event) {
        var button=event.target.closest('.js-workflow-step');
        if (!button) return;
        var form=document.getElementById('stepForm');
        var modal=document.getElementById('stepModal');
        if (!form || !modal) return;
        form.reset();form.dataset.confirmed='';
        var record=parse(button,'step-record');
        form.querySelectorAll('[name]').forEach(function(field) {
            if (Object.hasOwn(record,field.name)) field.value=record[field.name]??'';
        });
        document.getElementById('stepTitle').textContent=record.step_key?'Edit Draft Step':'Create New Step';
        updateStepApprover();
        workflowParent=button.closest('.workflow-steps-modal');
        function showStep() {window.bootstrap.Modal.getOrCreateInstance(modal).show();}
        if (workflowParent) {
            workflowParent.addEventListener('hidden.bs.modal',showStep,{once:true});
            window.bootstrap.Modal.getOrCreateInstance(workflowParent).hide();
        } else showStep();
    });
    document.getElementById('stepModal')?.addEventListener('hidden.bs.modal',function() {
        if (workflowParent && !pendingForm && document.getElementById('stepForm')?.dataset.confirmed!=='yes') {
            window.bootstrap.Modal.getOrCreateInstance(workflowParent).show();
        }
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
            input.value=source.value?(doc[input.dataset.transferOrigin]||'Not assigned'):'';
        });
        if (changed==='source') {
            [area,specific,asset,location].forEach(function(field){field.value='';});
            var recipient=form.querySelector('[name="recipient_id"]');
            if (recipient) recipient.value='';
            var subject=form.querySelector('[name="subject"]');
            if (subject && (!subject.value || subject.value===subject.dataset.autoSubject)) {
                subject.value=source.value?'Transfer: '+(doc.title||'Selected hardcopy'):'';
                subject.dataset.autoSubject=subject.value;
            }
        }
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
