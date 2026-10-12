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
  body.append(map.viewDetails);
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
    {key:'overview',title:'Overview',fields:{Title:'Safety Manual',Status:'Active'}},
    {key:'storage',title:'Storage & Custody',fields:{Area:'Plant',Location:'Rack 3'}},
    {key:'requests',title:'Related Requests',fields:{'REQ-1':'Returned for correction'}},
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
  assert.equal(details.querySelectorAll('[role="tab"]').length,4);
  assert.equal(details.querySelectorAll('[role="tabpanel"]').length,4);
  assert.equal(details.querySelectorAll('[role="tabpanel"]').filter(x=>!x.hidden).length,1);
  // Input is text content, never parsed into an HTML element.
  assert.equal(details.querySelectorAll('script').length,0);
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
  assert.equal(tabs[3].getAttribute('aria-selected'),'true');
  assert.equal(panels[3].hidden,false);
  assert.equal(tabs[3].focused,true);
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
