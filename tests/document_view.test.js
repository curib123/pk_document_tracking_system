/* Minimal browser-independent DOM regression tests for read-only document modals.
 * Runs with built-in Node test runner; no npm install or network required.
 */
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

class Element {
  constructor(tag = 'div') {
    this.tagName = tag.toUpperCase();
    this.children = [];
    this.parentNode = null;
    this.attributes = {};
    this.className = '';
    this.textContent = '';
    this.hidden = false;
    this.tabIndex = 0;
    this.listeners = {};
    this.scrollTop = 0;
    this.classList = {
      contains: (c) => this.className.split(/\s+/).includes(c),
      add: (c) => {
        if (!this.classList.contains(c)) this.className = (this.className + ' ' + c).trim();
      },
      remove: (c) => {
        this.className = this.className.split(/\s+/).filter((s) => s !== c).join(' ');
      },
      toggle: (c, active) => {
        if (active) this.classList.add(c);
        else this.classList.remove(c);
      }
    };
  }
  setAttribute(key, value) { this.attributes[key] = String(value); }
  getAttribute(key) { return this.attributes[key] || null; }
  append(...elements) {
    elements.forEach((element) => {
      element.parentNode = this;
      this.children.push(element);
    });
  }
  replaceChildren(...elements) {
    this.children.forEach((child) => { child.parentNode = null; });
    this.children = [];
    this.append(...elements);
  }
  addEventListener(name, fn) { this.listeners[name] = fn; }
  matches(selector) {
    if (selector === '.doc-modal-layout') return this.classList.contains('doc-modal-layout');
    if (selector === '.doc-modal-nav-item[role="tab"]')
      return this.classList.contains('doc-modal-nav-item') && this.attributes.role === 'tab';
    if (selector === '[role="tab"]') return this.attributes.role === 'tab';
    if (selector === '[role="tabpanel"]') return this.attributes.role === 'tabpanel';
    if (selector === '.doc-modal-body') return this.classList.contains('doc-modal-body');
    return false;
  }
  closest(selector) {
    let current = this;
    while (current) {
      if (current.matches(selector)) return current;
      current = current.parentNode;
    }
    return null;
  }
  querySelectorAll(selector) {
    const hits = [];
    const visit = (element) => {
      element.children.forEach((child) => {
        if (child.matches(selector)) hits.push(child);
        visit(child);
      });
    };
    visit(this);
    return hits;
  }
  querySelector(selector) { return this.querySelectorAll(selector)[0] || null; }
  focus() { this.focused = true; }
}

function setup() {
  const ids = ['viewModal','viewDetails','viewModalTitle','viewDocumentEyebrow',
    'viewDocumentIcon','viewDocumentRef','viewDocumentStatus',
    'viewDocumentSummary','viewDocumentFootnote'];
  const map = Object.fromEntries(ids.map((id) => [id,new Element()]));
  const body = new Element();
  body.className = 'doc-modal-body';
  map.viewModal.append(body);
  body.append(map.viewDocumentSummary,map.viewDetails);
  const document = {
    getElementById: (id) => map[id] || null,
    createElement: (tag) => new Element(tag)
  };
  const window = {location:{origin:'https://dts.test'}};
  vm.runInNewContext(
    fs.readFileSync('public/assets/js/document-view.js','utf8'),
    {window,document,URL},
    {filename:'document-view.js'}
  );
  return {window,modal:map.viewModal,details:map.viewDetails,map};
}

const data = {
  kind:'document',
  domain:'hardcopy',
  header:{
    title:'Safety manual <script>alert(1)</script>',
    reference:'HC-104',
    status:'active',
    updated_at:'2026-10-12 12:00:00',
    record_version:2,
    context_label:'Current holder',
    context_value:'Office Records'
  },
  sections:[
    {key:'overview',title:'Overview',fields:{Title:'Safety Manual',Status:'Active','Created By':'Records Officer','Created':'2026-10-11'}},
    {key:'storage',title:'Storage & Custody',fields:{Area:'Plant',Location:'Rack 3'}},
    {key:'requests',title:'Related Requests',fields:{'REQ-1':'Returned for correction'}},
    {key:'approvals',title:'Approval History',fields:{'REQ-1':'Supervisor approved'}},
    {key:'files',title:'Controlled Files',fields:{
      '1. report.pdf':'Verified file',
      '2. private.pdf':'Unavailable'
    },links:[
      {url:'/files/download/12',label:'Download report'},
      {url:'https://outside.example/file',label:'Untrusted'}
    ]}
  ]
};

test('document modal activates only on authorized document display shapes',() => {
  const {window,details,map,modal}=setup();
  assert.equal(window.PKDocumentView.handles(data),true);
  assert.equal(window.PKDocumentView.handles({kind:'document',domain:'other',sections:[]}),false);
  window.PKDocumentView.render(details,data);
  assert.equal(modal.classList.contains('is-document-view'),true);
  assert.equal(map.viewModalTitle.textContent,data.header.title);
  assert.equal(map.viewDocumentStatus.textContent,'Active');
  assert.equal(map.viewDocumentSummary.children.length,3);
  assert.equal(map.viewDocumentSummary.hidden,false);
  assert.equal(map.viewDocumentSummary.parentNode.children[0],map.viewDocumentSummary);
  const tabLabels=details.querySelectorAll('[role="tab"]').map((t)=>t.children[1].textContent);
  assert.deepEqual(tabLabels,['Related Requests','Approval History','Controlled Files']);
  assert.equal(details.querySelectorAll('[role="tab"]').length,3);
  assert.equal(details.querySelectorAll('[role="tabpanel"]').length,3);
  assert.equal(details.querySelectorAll('[role="tabpanel"]').filter(x=>!x.hidden).length,1);
  // Input is text content, never parsed into an HTML element.
  assert.equal(details.querySelectorAll('script').length,0);
});

test('all identity and custody fields appear above the tabs, not inside a tab',() => {
  const {window,details,map}=setup();
  window.PKDocumentView.render(details,data);
  const overview=map.viewDocumentSummary;
  const groups=overview.children.filter((c)=>c.classList.contains('doc-overview-group'));
  assert.equal(groups.length,2);
  const labels=groups.flatMap((g)=>g.children[1].children.map((n)=>n.children[0].textContent));
  assert.deepEqual(labels,['Created By','Created','Area','Location']);
  assert.equal(overview.parentNode.children[1],details);
  assert.equal(details.querySelectorAll('[role="tab"]').filter((t)=>t.textContent==='Overview').length,0);
});

test('softcopy revision and classification fields remain in the top overview',() => {
  const {window,details,map}=setup();
  const softcopy={
    kind:'document',domain:'softcopy',
    header:{title:'Release guide',reference:'SC-12',status:'active',record_version:3},
    sections:[
      {key:'overview',title:'Overview',fields:{'Title':'Release guide','Created By':'Records Officer','Record Version':3}},
      {key:'revision',title:'Revision & Classification',fields:{'Category / Folder':'Policies','Series':'2026','Revision Level':'C','Pages':6}},
      {key:'files',title:'Controlled Files',fields:{}},
      {key:'approvals',title:'Approval History',fields:{}}
    ]
  };
  window.PKDocumentView.render(details,softcopy);
  const groups=map.viewDocumentSummary.children.filter((c)=>c.classList.contains('doc-overview-group'));
  assert.equal(groups.length,2);
  const labels=groups.flatMap((g)=>g.children[1].children.map((n)=>n.children[0].textContent));
  assert.deepEqual(labels,['Created By','Record Version','Category / Folder','Series','Revision Level','Pages']);
  assert.equal(details.querySelectorAll('[role="tab"]').length,2);
  assert.equal(details.querySelectorAll('[role="tab"]')[0].children[1].textContent,'Controlled Files');
  assert.equal(map.viewDocumentSummary.parentNode.children[0],map.viewDocumentSummary);
});

test('tab clicks and arrow keys change only the selected panel',() => {
  const {window,modal,details}=setup();
  window.PKDocumentView.render(details,data);
  const tabs=details.querySelectorAll('[role="tab"]');
  const panels=details.querySelectorAll('[role="tabpanel"]');
  modal.listeners.click({target:tabs[1]});
  assert.equal(tabs[1].getAttribute('aria-selected'),'true');
  assert.equal(panels[0].hidden,true);
  assert.equal(panels[1].hidden,false);
  let prevented=false;
  modal.listeners.keydown({
    target:tabs[1],
    key:'End',
    preventDefault:()=>{prevented=true;}
  });
  assert.equal(prevented,true);
  assert.equal(tabs[2].getAttribute('aria-selected'),'true');
  assert.equal(panels[2].hidden,false);
  assert.equal(tabs[2].focused,true);
});

test('download links only include same-origin file URLs',() => {
  const {window,details}=setup();
  window.PKDocumentView.render(details,data);
  const all = [];
  function walk(el) {
    if (el.tagName==='A') all.push(el);
    el.children.forEach(walk);
  }
  walk(details);
  assert.equal(all.length,1);
  assert.equal(all[0].href,'https://dts.test/files/download/12');
});

test('reset returns reusable modal to ordinary view state',() => {
  const {window,details,map,modal}=setup();
  window.PKDocumentView.render(details,data);
  modal.listeners['hidden.bs.modal']();
  assert.equal(details.children.length,0);
  assert.equal(modal.classList.contains('is-document-view'),false);
  assert.equal(map.viewDocumentSummary.hidden,true);
  assert.equal(map.viewDocumentSummary.children.length,0);
  assert.equal(map.viewDocumentStatus.hidden,true);
});


test('every module can use the same SaaS view details layout with a plain record',() => {
  const {window,modal,details,map}=setup();
  window.PKDocumentView.render(details,{
    'Role':'Records Officer','Status':'Active','Permission Count':5
  });
  assert.equal(modal.classList.contains('is-document-view'),true);
  assert.equal(map.viewModalTitle.textContent,'Records Officer');
  assert.equal(map.viewDocumentEyebrow.textContent,'RECORD DETAILS');
  assert.equal(map.viewDocumentSummary.hidden,false);
  assert.equal(details.querySelectorAll('[role="tab"]').length,0);
  assert.equal(details.children.length,0,'Do not add meaningless empty activity to simple records');
});

test('request details use the exact same overview-plus-concerns view as system documents',() => {
  const {window,details,map,modal}=setup();
  window.PKDocumentView.render(details,{
    kind:'document',domain:'softcopy',
    header:{title:'Proposed SOP',reference:'SOFT-123',status:'submitted',record_version:1},
    sections:[
      {key:'overview',title:'Request & Ownership',fields:{'Request Reference':'REQ-15','Requested By':'Jane Smith'}},
      {key:'revision',title:'Proposed Classification & Revision',fields:{'Proposed Title':'Proposed SOP','Series':'PK'}},
      {key:'files',title:'Controlled File',fields:{'Attachment':'Pending approval'}},
      {key:'approvals',title:'Approval Route',fields:{'Step 1':'Supervisor'}},
      {key:'audit',title:'Decision History',fields:{}}
    ]
  });
  assert.equal(modal.classList.contains('is-document-view'),true);
  assert.equal(map.viewDocumentStatus.textContent,'Submitted');
  assert.equal(map.viewDocumentSummary.children.filter(c=>c.classList.contains('doc-overview-group')).length,2);
  const labels=details.querySelectorAll('[role="tab"]').map(x=>x.children[1].textContent);
  assert.deepEqual(labels,['Controlled File','Approval Route','Decision History']);
});

test('nested generic sections retain metadata and put related concerns in tabs',() => {
  const {window,details,map}=setup();
  window.PKDocumentView.render(details,{
    sections:[
      {title:'Account Overview',fields:{'Name':'Alex','Status':'Active'}},
      {title:'Access Grants',fields:{'Staff':'Read only'}},
      {title:'History',fields:{'Changed':'Today'}}
    ]
  });
  assert.equal(map.viewModalTitle.textContent,'Alex');
  assert.equal(map.viewDocumentSummary.hidden,false);
  assert.deepEqual(details.querySelectorAll('[role="tab"]').map(x=>x.children[1].textContent),
    ['Access Grants','History']);
});
