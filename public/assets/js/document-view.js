/* PK Document Control: read-only, permission-scoped document detail presentation.
 * No requests, mutation, or alternate data API. Receives existing JSON from
 * Document_detail_model and renders it using text nodes only.
 */
(function () {
    'use strict';

    var modal = document.getElementById('viewModal');
    if (!modal) return;

    var ICONS = {
        overview: 'fa-circle-info',
        storage: 'fa-box-archive',
        revision: 'fa-code-branch',
        files: 'fa-paperclip',
        requests: 'fa-clipboard-list',
        approvals: 'fa-list-check',
        transfers: 'fa-arrow-right-arrow-left',
        audit: 'fa-clock-rotate-left'
    };
    var HISTORY = {requests:true, approvals:true, transfers:true, audit:true};

    function node(tag, className, text) {
        var element = document.createElement(tag);
        if (className) element.className = className;
        if (text !== undefined && text !== null) element.textContent = String(text);
        return element;
    }

    function icon(name) {
        var element = node('i', 'fa-solid ' + (ICONS[name] || 'fa-file-lines'));
        element.setAttribute('aria-hidden', 'true');
        return element;
    }

    function valueText(value) {
        if (value == null || value === '') return 'Not specified';
        if (typeof value === 'object') {
            if (Array.isArray(value)) return value.map(valueText).join('\n');
            return Object.entries(value).map(function (entry) {
                return entry[0] + ': ' + valueText(entry[1]);
            }).join('\n');
        }
        return String(value);
    }

    function normalizedEntries(fields) {
        if (!fields || typeof fields !== 'object') return [];
        return Object.entries(fields);
    }

    function safeLink(url) {
        try {
            var parsed = new URL(String(url), window.location.origin);
            if (!['http:', 'https:'].includes(parsed.protocol)) return null;
            return parsed.origin === window.location.origin ? parsed.href : null;
        } catch (error) {
            return null;
        }
    }

    function reset() {
        modal.classList.remove('is-document-view');
        ['viewDocumentEyebrow','viewDocumentIcon','viewDocumentRef',
            'viewDocumentStatus','viewDocumentSummary','viewDocumentFootnote'].forEach(function(id) {
            var el=document.getElementById(id);
            if (!el) return;
            el.hidden=true;
            if (id==='viewDocumentSummary') el.replaceChildren();
        });
        var status=document.getElementById('viewDocumentStatus');
        if (status) status.className='doc-modal-status';
    }

    // The document header owns the identity, classification, storage and
    // custody overview. Tabs below it contain only independent concerns.
    function isOverviewSection(section) {
        return !!section && ['overview','storage','revision'].includes(section.key);
    }

    function renderOverview(sections,data) {
        var summary=document.getElementById('viewDocumentSummary');
        if (!summary) return;
        summary.replaceChildren();

        var heading=node('div','doc-overview-intro');
        var label=node('div','doc-overview-kicker','DOCUMENT OVERVIEW');
        heading.append(label,node('h3','doc-overview-title','Document information'));
        heading.append(node('p','doc-overview-description',
            'Key record details, ownership and '+(data.domain==='hardcopy'
                ? 'physical custody' : 'revision classification')+'.'));
        summary.append(heading);

        // Document name, reference, type and status are already displayed
        // in the modal title bar; avoid repeating them as metadata tiles.
        var repeated={'Title':true,'Reference / Copy Number':true,
            'Document Type':true,'Status':true};

        sections.forEach(function(section) {
            var entries=normalizedEntries(section.fields).filter(function(entry) {
                return !(section.key==='overview' && repeated[entry[0]]);
            });
            if (!entries.length) return;
            var group=node('section','doc-overview-group');
            var groupTitle=node('h4','doc-overview-group-title');
            groupTitle.append(icon(section.key),
                node('span','',section.key==='overview'?'Record & Ownership':section.title||'Document details'));
            group.append(groupTitle);
            var grid=node('dl','doc-overview-grid');
            entries.forEach(function(entry) {
                var item=node('div','doc-overview-item');
                item.append(node('dt','doc-overview-label',entry[0]),
                    node('dd','doc-overview-value',valueText(entry[1])));
                grid.append(item);
            });
            group.append(grid);
            summary.append(group);
        });
        summary.hidden=false;
    }

    function prepareHeading(data) {
        modal.classList.add('is-document-view');
        var h=data.header || {};
        var domain=data.domain==='hardcopy'?'hardcopy':'softcopy';
        var title=document.getElementById('viewModalTitle');
        if (title) title.textContent=h.title || 'Untitled document';

        var kicker=document.getElementById('viewDocumentEyebrow');
        if (kicker) {
            kicker.hidden=false;
            kicker.textContent=domain==='hardcopy'?'PHYSICAL DOCUMENT':'CONTROLLED DIGITAL DOCUMENT';
        }
        var kind=document.getElementById('viewDocumentIcon');
        if (kind) {
            kind.hidden=false;
            kind.replaceChildren(icon(domain==='hardcopy'?'storage':'files'));
        }
        var reference=document.getElementById('viewDocumentRef');
        if (reference) {
            reference.hidden=false;
            reference.textContent=(h.reference ? 'Reference  ·  '+h.reference : 'No reference assigned');
        }
        var status=document.getElementById('viewDocumentStatus');
        if (status) {
            var allowed=['active','disposed','archived','cancelled'];
            var key=String(h.status||'').toLowerCase();
            status.className='doc-modal-status status-'+(allowed.includes(key)?key:'neutral');
            status.textContent=key ? key.charAt(0).toUpperCase()+key.slice(1) : 'Unknown';
            status.hidden=false;
        }
        var footnote=document.getElementById('viewDocumentFootnote');
        if (footnote) footnote.hidden=false;
    }

    function sectionTitle(section) {
        var heading=node('div','doc-modal-section-heading');
        heading.append(node('h3','doc-modal-section-title',section.title||'Details'));
        if (section.description) heading.append(node('p','doc-modal-section-subtitle',section.description));
        return heading;
    }

    function emptyState(message) {
        var empty=node('div','doc-modal-empty');
        var circle=node('span','doc-modal-empty-icon');
        circle.append(icon('overview'));
        empty.append(circle,node('strong','doc-modal-empty-heading','No entries to display'),
            node('p','doc-modal-empty-message',message||'No details are available for this section.'));
        return empty;
    }

    function makeGrid(entries) {
        var list=node('dl','doc-modal-field-grid');
        entries.forEach(function(entry) {
            var field=node('div','doc-modal-field');
            field.append(node('dt','doc-modal-field-label',entry[0]));
            field.append(node('dd','doc-modal-field-value',valueText(entry[1])));
            list.append(field);
        });
        return list;
    }

    function makeActivity(entries) {
        var list=node('div','doc-modal-activity');
        entries.forEach(function(entry,index) {
            var item=node('article','doc-modal-activity-item');
            var indicator=node('span','doc-modal-activity-marker');
            indicator.append(icon('overview'));
            var body=node('div','doc-modal-activity-content');
            body.append(node('strong','doc-modal-activity-title',entry[0]));
            body.append(node('p','doc-modal-activity-description',valueText(entry[1])));
            item.append(indicator,body);
            list.append(item);
        });
        return list;
    }

    function makeFiles(section,entries) {
        var list=node('div','doc-modal-file-list');
        var links=Array.isArray(section.links)?section.links:[];
        entries.forEach(function(entry,index) {
            var row=node('article','doc-modal-file-item');
            var fileIcon=node('span','doc-modal-file-icon');
            fileIcon.append(icon('files'));
            var content=node('div','doc-modal-file-content');
            content.append(node('strong','doc-modal-file-title',
                entry[0].replace(/^\d+\.\s*/,'')));
            content.append(node('p','doc-modal-file-meta',valueText(entry[1])));
            row.append(fileIcon,content);
            var link=links[index] || {};
            var destination=link.url ? safeLink(link.url) : null;
            if (destination) {
                var anchor=node('a','btn btn-sm btn-outline-primary doc-modal-download','Download');
                anchor.href=destination;
                anchor.setAttribute('aria-label','Download '+entry[0].replace(/^\d+\.\s*/,''));
                anchor.append(icon('files'));
                row.append(anchor);
            }
            list.append(row);
        });
        return list;
    }

    function renderSection(panel,section) {
        panel.append(sectionTitle(section));
        var entries=normalizedEntries(section.fields);
        if (!entries.length) {
            panel.append(emptyState(section.empty));
        } else if (section.key==='files') {
            panel.append(makeFiles(section,entries));
        } else if (HISTORY[section.key]) {
            panel.append(makeActivity(entries));
        } else {
            panel.append(makeGrid(entries));
        }
    }

    function selectTab(shell,index,focusTab) {
        var tabs=Array.from(shell.querySelectorAll('[role="tab"]'));
        var panels=Array.from(shell.querySelectorAll('[role="tabpanel"]'));
        if (!tabs.length || index<0 || index>=tabs.length) return;
        tabs.forEach(function(tab,i) {
            var active=i===index;
            tab.setAttribute('aria-selected',String(active));
            tab.tabIndex=active?0:-1;
            tab.classList.toggle('is-active',active);
            panels[i].hidden=!active;
        });
        if (focusTab) tabs[index].focus();
    }

    function render(container,data) {
        reset();
        prepareHeading(data);
        container.replaceChildren();
        var sections=Array.isArray(data.sections)?data.sections:[];
        var overview=sections.filter(isOverviewSection);
        var concerns=sections.filter(function(section) {return !isOverviewSection(section);});
        renderOverview(overview,data);

        // Each tab is one separately scoped concern. Overview is NEVER a tab.
        if (!concerns.length) {
            container.append(emptyState('No additional document activity is available.'));
            return;
        }
        var layout=node('div','doc-modal-layout');
        var sidebar=node('nav','doc-modal-navigation');
        sidebar.setAttribute('role','tablist');
        sidebar.setAttribute('aria-label','Document detail concerns');
        var main=node('div','doc-modal-main');
        concerns.forEach(function(section,i) {
            var key=section.key || 'overview';
            var id='viewDocSection'+i;
            var tab=node('button','doc-modal-nav-item');
            tab.type='button';
            tab.id='viewDocTab'+i;
            tab.setAttribute('role','tab');
            tab.setAttribute('aria-controls',id);
            tab.setAttribute('aria-selected',i===0?'true':'false');
            tab.tabIndex=i===0?0:-1;
            if (i===0) tab.classList.add('is-active');
            tab.append(icon(key),node('span','doc-modal-tab-name',section.title||'Details'));
            sidebar.append(tab);
            var panel=node('section','doc-modal-panel');
            panel.id=id;
            panel.setAttribute('role','tabpanel');
            panel.setAttribute('aria-labelledby',tab.id);
            panel.tabIndex=0;
            panel.hidden=i!==0;
            renderSection(panel,section);
            main.append(panel);
        });
        layout.append(sidebar,main);
        container.append(layout);
    }

    modal.addEventListener('click',function(event) {
        var tab=event.target.closest('.doc-modal-nav-item[role="tab"]');
        if (!tab || !modal.classList.contains('is-document-view')) return;
        var shell=tab.closest('.doc-modal-layout');
        selectTab(shell,Array.from(shell.querySelectorAll('[role="tab"]')).indexOf(tab),false);
    });

    modal.addEventListener('keydown',function(event) {
        var tab=event.target.closest('.doc-modal-nav-item[role="tab"]');
        if (!tab || !modal.classList.contains('is-document-view')) return;
        var tabs=Array.from(tab.closest('.doc-modal-layout').querySelectorAll('[role="tab"]'));
        var at=tabs.indexOf(tab),next=at;
        if (event.key==='ArrowRight'||event.key==='ArrowDown') next=(at+1)%tabs.length;
        else if (event.key==='ArrowLeft'||event.key==='ArrowUp') next=(at+tabs.length-1)%tabs.length;
        else if (event.key==='Home') next=0;
        else if (event.key==='End') next=tabs.length-1;
        else return;
        event.preventDefault();
        selectTab(tab.closest('.doc-modal-layout'),next,true);
    });

    // Clear permission-scoped record details once the modal closes, so a
    // later generic View Details action never flashes the previous document.
    modal.addEventListener('hidden.bs.modal',function() {
        reset();
        var details=document.getElementById('viewDetails');
        if (details) details.replaceChildren();
    });

    window.PKDocumentView = {
        handles: function(data) {
            return !!data && data.kind==='document' &&
                ['hardcopy','softcopy'].includes(data.domain) &&
                Array.isArray(data.sections);
        },
        render:render,
        reset:reset
    };
})();
