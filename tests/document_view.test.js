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
    if (/^\.[a-z0-9_-]+$/i.test(selector)) return this.classList.contains(selector.slice(1));
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
  scrollIntoView(options) { this.scrolledIntoView = options; }
}

function setup() {
  const ids = ['viewModal','viewDetails','viewModalTitle','viewDocumentEyebrow',
    'viewDocumentIcon','viewDocumentRef','viewDocumentStatus',
    'viewDocumentSummary','viewDocumentCustody','viewDocumentFootnote'];
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

test('assigned document lists search every page, preserve text and reject unsafe destinations',()=>{
  const {window,details}=setup();
  const documents=Array.from({length:12},(_,i)=>({title:'Document '+i,code:'HC-'+i,detail:'Current holder'}));
  documents[11].title='<script>unsafe title</script>';
  window.PKDocumentView.render(details,{kind:'record',header:{title:'Assigned user'},sections:[
    {key:'hardcopy',title:'Hardcopy (12)',label:'Hardcopy',documents,manage_url:'https://outside.test/private'}
  ]});
  assert.equal(details.querySelectorAll('.doc-assignment-item').length,10);
  const next=details.querySelector('.doc-assignment-next');next.listeners.click();
  assert.equal(details.querySelectorAll('.doc-assignment-item').length,2);
  const search=details.querySelector('.doc-assignment-search');
  assert.equal(search.getAttribute('autocomplete'),'off');
  search.value='HC-11';search.listeners.input();
  assert.equal(details.querySelectorAll('.doc-assignment-item').length,1);
  assert.equal(details.querySelector('.doc-assignment-title').textContent,'<script>unsafe title</script>');
  assert.equal(details.querySelector('.doc-assignment-manage'),null);
  search.value='missing';search.listeners.input();
  assert.equal(details.querySelector('.doc-modal-empty-message').textContent,'No documents match your search.');
});

test('document modal activates only on authorized document display shapes',() => {
  const {window,details,map,modal}=setup();
  assert.equal(window.PKDocumentView.handles(data),true);
  assert.equal(window.PKDocumentView.handles({kind:'document',domain:'other',sections:[]}),false);
  window.PKDocumentView.render(details,data);
  assert.equal(modal.classList.contains('is-document-view'),true);
  assert.equal(map.viewModalTitle.textContent,data.header.title);
  assert.equal(map.viewDocumentStatus.textContent,'Active');
  assert.equal(map.viewDocumentSummary.children.length,2); // Preserve overview and storage groups.
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
  assert.equal(overview.querySelectorAll('.doc-overview-group-title').length,0);
  const labels=overview.querySelectorAll('.doc-overview-label').map((n)=>n.textContent);
  assert.deepEqual(labels,['Created By','Created']);
  assert.deepEqual(overview.querySelectorAll('.doc-storage-route-value').map((n)=>n.textContent),['Plant','Not specified','Rack 3']);
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
  const labels=groups.flatMap((g)=>g.querySelector('.doc-overview-grid').children.map((n)=>n.children[0].textContent));
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
  assert.equal(tabs[2].scrolledIntoView?.inline,'nearest');
  assert.equal(tabs[2].scrolledIntoView?.block,'nearest');
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

test('superseded controlled files retain their history without a Download action',() => {
  const {window,details}=setup();
  window.PKDocumentView.render(details,{
    kind:'document',domain:'softcopy',header:{title:'Revised policy'},
    sections:[{key:'files',title:'Controlled Files',fields:{
      '1. previous.txt':'Earlier revision · Superseded','2. revised.txt':'Approved revision · Current'
    },links:[{url:'',label:'Download previous.txt'},{url:'/files/download/710',label:'Download revised.txt'}]}]
  });
  assert.equal(details.querySelectorAll('.doc-modal-file-item').length,2,'Old metadata must remain visible.');
  const links=details.querySelectorAll('.doc-modal-download');
  assert.equal(links.length,1,'Only the current controlled file should offer download.');
  assert.equal(links[0].href,'https://dts.test/files/download/710');
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

test('record viewers describe boolean values and reset scroll for the next record',() => {
  const {window,modal,details,map}=setup();
  modal.querySelector('.doc-modal-body').scrollTop=480;
  window.PKDocumentView.render(details,{'Name':'Location','Enabled':true,'Archived':false});
  const values=map.viewDocumentSummary.querySelectorAll('.doc-overview-value').map((n)=>n.textContent);
  assert.deepEqual(values,['Location','Yes','No']);
  assert.equal(modal.querySelector('.doc-modal-body').scrollTop,0);
});

test('file viewers expose permitted print actions and reject external print links',() => {
  const {window,details}=setup();
  window.PKDocumentView.render(details,{kind:'document',domain:'softcopy',sections:[
    {key:'files',fields:{'1. current.txt':'Current','2. old.txt':'Superseded'},links:[
      {url:'/files/download/1',print_url:'/files/print/1'},
      {url:'',print_url:'https://outside.test/print/2'}
    ]}
  ]});
  const links=details.querySelectorAll('.doc-modal-download');
  assert.equal(links.length,2);
  assert.equal(links[1].textContent,'Print');
  assert.equal(links[1].href,'https://dts.test/files/print/1');
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

test('each approval step renders separately with its ordered label, approver and status',() => {
  const {window,details}=setup();
  window.PKDocumentView.render(details,{
    kind:'record',domain:'record',header:{title:'Approval request',status:'submitted'},
    sections:[{key:'approvals',title:'Approval Route',fields:{
      'Workflow Version':'Assigned at submission','Steps':'Legacy combined text'
    },steps:[
      {label:'Noted By',approver_label:'Staff1 Staff1',status:'active'},
      {label:'Plant Manager Approval',approver_label:'Plant Manager',status:'pending'},
      {label:'Document Officer Approval',approver_label:'Document Control Officer',status:'pending'}
    ]}]
  });
  const tiles=details.querySelectorAll('.doc-workflow-step');
  assert.equal(tiles.length,3,'Every approval step needs its own tile.');
  assert.deepEqual(tiles.map(tile=>tile.querySelector('.doc-workflow-step-name').textContent),
    ['Noted By','Plant Manager Approval','Document Officer Approval']);
  assert.deepEqual(tiles.map(tile=>tile.querySelector('.doc-workflow-step-approver').textContent),
    ['Staff1 Staff1','Plant Manager','Document Control Officer']);
  assert.deepEqual(tiles.map(tile=>tile.getAttribute('data-step-status')),['active','pending','pending']);
  assert.deepEqual(tiles.map(tile=>tile.querySelector('.doc-workflow-step-status').textContent),
    ['Active','Pending','Pending'],'Status must remain readable alongside its color.');
  assert.equal(details.querySelectorAll('.doc-modal-activity-description').length,0,
    'The old combined Steps paragraph must not duplicate the tiles.');
});

test('workflow step names remain text and unknown statuses use a neutral color',() => {
  const {window,details}=setup();
  window.PKDocumentView.render(details,{
    kind:'record',domain:'record',header:{title:'Approval request'},
    sections:[{key:'approvals',title:'Approval Route',fields:{},steps:[
      {label:'<script>alert(1)</script>',approver_label:'<img src=x onerror=alert(1)>',status:'unexpected" onclick="bad'},
      {label:'Earlier review',approver_label:'Officer',status:'approved'},
      {label:'Returned review',approver_label:'Manager',status:'returned'},
      {label:'Rejected review',approver_label:'Manager',status:'rejected'},
      {label:'Earlier submission',approver_label:'Officer',status:'superseded'}
    ]}]
  });
  const tiles=details.querySelectorAll('.doc-workflow-step');
  assert.equal(tiles.length,5);
  assert.equal(tiles[0].querySelector('.doc-workflow-step-name').textContent,'<script>alert(1)</script>');
  assert.equal(tiles[0].querySelector('.doc-workflow-step-approver').textContent,'<img src=x onerror=alert(1)>');
  assert.deepEqual(tiles.map(tile=>tile.getAttribute('data-step-status')),
    ['neutral','approved','returned','rejected','superseded']);
});

test('approval tiles retain every repeated decision, actor, timestamp and comment safely',() => {
  const {window,details}=setup();
  const history=[
    {action:'returned',user_name:'Manager',position_title:'Plant Manager',created_at:'2026-10-13 10:00:00',comments:'First return'},
    {action:'returned',user_name:'Manager',position_title:'Plant Manager',created_at:'2026-10-13 11:00:00',comments:'Second return\nCheck the document'},
    {action:'approved',user_name:'<img src=x onerror=alert(1)>',position_title:'Plant Manager',created_at:'2026-10-13 12:00:00',comments:'<script>alert(1)</script>'},
    {action:'unexpected" onclick="bad',user_name:'Manager',position_title:'Plant Manager',created_at:'2026-10-13 13:00:00',comments:''}
  ];
  window.PKDocumentView.render(details,{
    kind:'record',domain:'record',header:{title:'Approval request'},
    sections:[{key:'approvals',title:'Approval Route',fields:{},steps:[
      {label:'Plant Manager Approval',approver_label:'Plant Manager',status:'active',history},
      {label:'Final Approval',approver_label:'Document Officer',status:'returned',history:[
        {action:'returned',user_name:'Officer',created_at:'2026-10-13 09:00:00',comments:'Final approver returned'}
      ]},
      {label:'Unreviewed step',approver_label:'Staff',status:'pending'}
    ]}]
  });
  const tiles=details.querySelectorAll('.doc-workflow-step');
  const events=tiles[0].querySelectorAll('.doc-workflow-decision');
  assert.equal(events.length,4,'Earlier returns must stay visible after another decision.');
  assert.deepEqual(events.map(event=>event.getAttribute('data-decision-status')),
    ['returned','returned','approved','neutral']);
  assert.deepEqual(events.map(event=>event.querySelector('.doc-workflow-decision-action').textContent),
    ['Returned','Returned','Approved','Unexpected" Onclick="Bad']);
  assert.deepEqual(events.map(event=>event.querySelector('.doc-workflow-decision-time').textContent),
    ['10:00:00 AM','11:00:00 AM','12:00:00 PM','1:00:00 PM']);
  assert.equal(events[0].querySelector('.doc-workflow-decision-time').getAttribute('datetime'),'2026-10-13T10:00:00');
  assert.equal(events[0].querySelector('.doc-workflow-decision-actor').textContent,'Manager · Plant Manager');
  assert.equal(events[1].querySelector('.doc-workflow-decision-comment').textContent,'Second return\nCheck the document');
  assert.equal(events[2].querySelector('.doc-workflow-decision-actor').textContent,'<img src=x onerror=alert(1)> · Plant Manager');
  assert.equal(events[2].querySelector('.doc-workflow-decision-comment').textContent,'<script>alert(1)</script>');
  assert.equal(events[3].querySelector('.doc-workflow-decision-comment'),null);
  assert.equal(tiles[1].querySelectorAll('.doc-workflow-decision').length,1,'Decisions must stay under their own step.');
  assert.equal(tiles[2].querySelectorAll('.doc-workflow-decision').length,0);
  assert.equal(tiles[0].getAttribute('data-step-status'),'active','Current task status stays separate from saved decisions.');
});

test('decision history uses dated vertical steps and sorts repeated reviews by timestamp',()=>{
  const {window,details}=setup();
  const events=[
    {step_label:'Noted By',action:'approved',user_name:'Staff2 Staff2',position_title:'Staff2',created_at:'2026-10-15 14:17:12'},
    {step_label:'Plant Manager Approval',action:'approved',user_name:'Plant Manager',position_title:'manager',created_at:'2026-10-15 14:18:32'},
    {step_label:'Document Officer Approval',action:'returned',user_name:'Document Officer',position_title:'Document Controll Officer',created_at:'2026-10-15 14:18:54',comments:'fff'},
    {step_label:'Plant Manager Approval',action:'approved',user_name:'Plant Manager',position_title:'manager',created_at:'2026-10-15 14:20:09'}
  ];
  const original=JSON.stringify(events);
  window.PKDocumentView.render(details,{kind:'record',header:{title:'Request'},sections:[
    {key:'audit',title:'Decision History',fields:{},events}
  ]});
  const select=details.querySelector('.doc-workflow-sort');
  assert.ok(select,'Decision history needs a timestamp sort control.');
  assert.deepEqual(select.children.map(option=>option.textContent),['Oldest first','Newest first']);
  assert.equal(details.querySelector('.doc-workflow-date').textContent,'Oct 15, 2026');
  assert.deepEqual(details.querySelectorAll('.doc-workflow-decision-time').map(item=>item.textContent),
    ['2:17:12 PM','2:18:32 PM','2:18:54 PM','2:20:09 PM']);
  assert.deepEqual(details.querySelectorAll('.doc-workflow-decision-step').map(item=>item.textContent),events.map(event=>event.step_label));
  assert.equal(details.querySelector('.doc-workflow-decision-comment').textContent,'fff');
  assert.ok(details.querySelector('.is-animated-timeline'));
  assert.equal(details.querySelector('.doc-workflow-decision-marker').children[0].className,'fa-solid fa-check');
  select.value='newest';select.listeners.change();
  assert.deepEqual(details.querySelectorAll('.doc-workflow-decision-time').map(item=>item.textContent),
    ['2:20:09 PM','2:18:54 PM','2:18:32 PM','2:17:12 PM']);
  assert.equal(JSON.stringify(events),original,'Sorting must leave saved records untouched.');
});

test('approval sorting reorders decisions within each step and preserves the assigned route',()=>{
  const {window,details}=setup();
  window.PKDocumentView.render(details,{kind:'record',header:{title:'Request'},sections:[
    {key:'approvals',title:'Approval Route',fields:{},steps:[
      {label:'First review',approver_label:'Staff',status:'approved',history:[
        {action:'approved',user_name:'Staff',created_at:'2026-10-14 10:00:00'},
        {action:'approved',user_name:'Staff',created_at:'2026-10-15 11:00:00'}
      ]},
      {label:'Final review',approver_label:'Manager',status:'active'}
    ]}
  ]});
  const select=details.querySelector('.doc-workflow-sort');
  assert.ok(select);
  select.value='newest';select.listeners.change();
  assert.deepEqual(details.querySelectorAll('.doc-workflow-step-name').map(item=>item.textContent),['First review','Final review']);
  assert.deepEqual(details.querySelectorAll('.doc-workflow-date').map(item=>item.textContent),['Oct 15, 2026','Oct 14, 2026']);
});

test('document Approval History uses the decision stepper with request context and safe remarks',()=>{
  const {window,details}=setup();
  const events=[
    {request_reference:'REQ-42',step_label:'Manager Review',action:'returned',user_name:'Manager',position_title:'Plant Manager',comments:'Correct <script>alert(1)</script>',created_at:'2026-10-15 10:00:00'},
    {request_reference:'REQ-43',step_label:'Final Review',action:'approved',user_name:'Officer',comments:'Accepted',created_at:'2026-10-16 11:00:00'}
  ];
  const original=JSON.stringify(events);
  window.PKDocumentView.render(details,{kind:'document',domain:'softcopy',header:{title:'Policy'},sections:[
    {key:'approvals',title:'Approval History',fields:{},events}
  ]});
  assert.equal(details.querySelector('.doc-modal-section-title').textContent,'Approval History');
  assert.equal(details.querySelectorAll('.doc-workflow-decision').length,2);
  assert.ok(details.querySelector('.is-animated-timeline'));
  assert.equal(details.querySelector('.doc-workflow-decision-marker').children[0].className,'fa-solid fa-arrow-rotate-left');
  assert.deepEqual(details.querySelectorAll('.doc-workflow-decision-request').map(el=>el.textContent),['REQ-42','REQ-43']);
  assert.equal(details.querySelector('.doc-workflow-decision-comment').textContent,events[0].comments);
  assert.equal(details.querySelectorAll('script').length,0);
  const sort=details.querySelector('.doc-workflow-sort');
  sort.value='newest';sort.listeners.change();
  assert.deepEqual(details.querySelectorAll('.doc-workflow-decision-request').map(el=>el.textContent),['REQ-43','REQ-42']);
  assert.equal(JSON.stringify(events),original);
});

test('storage route follows the saved hierarchy, ends at Location and keeps custody metadata',()=>{
  const {window,details,map}=setup();
  const fields={Area:'Plant',Specific:'Production',Asset:'Machine 12',Location:'Shelf 4',
    'Location Sequence':4,'Current Holder':'Records Officer','Days until disposal':'60 days'};
  const original=JSON.stringify(fields);
  window.PKDocumentView.render(details,{kind:'document',domain:'hardcopy',header:{title:'Manual'},sections:[
    {key:'storage',fields}
  ]});
  const route=map.viewDocumentSummary.querySelector('.doc-storage-route');
  assert.equal(route.tagName,'OL');
  assert.equal(route.getAttribute('aria-label'),'Storage route');
  assert.deepEqual(route.querySelectorAll('.doc-storage-route-label').map(el=>el.textContent),['Area','Specific','Asset','Location']);
  assert.deepEqual(route.querySelectorAll('.doc-storage-route-value').map(el=>el.textContent),['Plant','Production','Machine 12','Shelf 4']);
  assert.equal(route.children[3].getAttribute('aria-current'),'location');
  assert.equal(route.children[3].classList.contains('is-destination'),true);
  assert.deepEqual(map.viewDocumentSummary.querySelectorAll('.doc-overview-label').map(el=>el.textContent),['Location Sequence']);
  assert.deepEqual(map.viewDocumentSummary.querySelectorAll('.doc-overview-value').map(el=>el.textContent),['4']);
  assert.equal(map.viewDocumentCustody.hidden,false);
  assert.deepEqual(map.viewDocumentCustody.querySelectorAll('.doc-modal-custody-label').map(el=>el.textContent),['Current Holder','Days until disposal']);
  assert.deepEqual(map.viewDocumentCustody.querySelectorAll('.doc-modal-custody-value').map(el=>el.textContent),['Records Officer','60 days']);
  assert.equal(JSON.stringify(fields),original);
  window.PKDocumentView.render(details,{Name:'Another record'});
  assert.equal(map.viewDocumentCustody.hidden,true);
  assert.equal(map.viewDocumentCustody.children.length,0);
});

test('storage route skips only an empty Asset and renumbers the final Location',()=>{
  for(const asset of [null,undefined,'','   ']) {
    const {window,details,map}=setup();
    window.PKDocumentView.render(details,{kind:'document',domain:'hardcopy',header:{title:'Manual'},sections:[
      {key:'storage',fields:{Area:'Plant',Specific:'Office',Asset:asset,Location:'Cabinet'}}
    ]});
    const route=map.viewDocumentSummary.querySelector('.doc-storage-route');
    assert.ok(route);
    assert.deepEqual(route.querySelectorAll('.doc-storage-route-label').map(el=>el.textContent),['Area','Specific','Location']);
    assert.deepEqual(route.querySelectorAll('.doc-storage-route-marker').map(el=>el.textContent),['1','2','3']);
    assert.equal(route.children[2].getAttribute('aria-current'),'location');
  }
});

test('storage route handles missing destinations and unsafe text, and resets for the next record',()=>{
  const {window,details,map}=setup();
  const name='<script>alert(1)</script>';
  window.PKDocumentView.render(details,{kind:'document',domain:'hardcopy',header:{title:'Manual'},sections:[
    {key:'storage',fields:{Area:name,Specific:'',Asset:'0',Location:null}}
  ]});
  const route=map.viewDocumentSummary.querySelector('.doc-storage-route');
  assert.equal(route.querySelector('.doc-storage-route-value').textContent,name);
  assert.equal(route.querySelectorAll('script').length,0);
  assert.equal(route.children.length,4,'An asset named 0 is a recorded value.');
  assert.equal(route.children[3].getAttribute('aria-current'),null);
  assert.equal(route.children[3].classList.contains('is-unassigned'),true);
  window.PKDocumentView.render(details,{Name:'Another record'});
  assert.equal(map.viewDocumentSummary.querySelector('.doc-storage-route'),null);
});

test('Transfer History uses the shared dated stepper, preserves milestones and sorts without changing data',()=>{
  const {window,details}=setup();
  const events=[
    {action:'for_transfer',step_label:'Office → Records',user_name:'John',position_title:'Current holder',comments:'Reason: Archive <script>',created_at:'2026-10-15 09:00:00'},
    {action:'in_transit',step_label:'Office → Records',user_name:'John',position_title:'Current holder',created_at:'2026-10-15 10:00:00'},
    {action:'accepted',step_label:'Office → Records',user_name:'Paul',position_title:'Recipient',created_at:'2026-10-16 11:00:00'}
  ];
  const original=JSON.stringify(events);
  window.PKDocumentView.render(details,{kind:'document',domain:'hardcopy',header:{title:'Manual'},sections:[
    {key:'transfers',title:'Transfer History',fields:{},events}
  ]});
  assert.equal(details.querySelector('.doc-modal-section-title').textContent,'Transfer History');
  assert.ok(details.querySelector('.is-animated-timeline'));
  assert.equal(details.querySelector('.doc-workflow-sort').getAttribute('aria-label'),'Sort transfer history by timestamp');
  assert.deepEqual(details.querySelectorAll('.doc-workflow-decision-action').map(el=>el.textContent),['For Transfer','In Transit','Accepted']);
  assert.deepEqual(details.querySelectorAll('.doc-workflow-decision').map(el=>el.getAttribute('data-decision-status')),['for_transfer','in_transit','accepted']);
  assert.equal(details.querySelector('.doc-workflow-decision-comment').textContent,events[0].comments);
  const sort=details.querySelector('.doc-workflow-sort');sort.value='newest';sort.listeners.change();
  assert.deepEqual(details.querySelectorAll('.doc-workflow-decision-action').map(el=>el.textContent),['Accepted','In Transit','For Transfer']);
  assert.equal(JSON.stringify(events),original);
});


test('user Assigned Documents modal shows compact totals, category tabs and cleans up on close',()=>{
  const {window,details,modal,map}=setup();
  window.PKDocumentView.render(details,{
    kind:'record',domain:'record',header:{title:'Assigned QA employee',eyebrow:'ASSIGNED DOCUMENTS'},
    sections:[
      {key:'overview',fields:{'Assigned Documents':3}},
      {key:'hardcopy',title:'Hardcopy (2)',label:'Hardcopy',compact:true,documents:[
        {id:1,title:'Shelf One',code:'HC-1',detail:'Location A · Sequence 1'},
        {id:2,title:'Shelf Two',code:'HC-2',detail:'Location B · Sequence 2'}]},
      {key:'softcopy',title:'Softcopy (1)',label:'Softcopy',compact:true,documents:[
        {id:3,title:'Digital Policy',code:'SC-3',detail:'Assigned today'}]}
    ]
  });
  assert.equal(modal.classList.contains('is-assignment-view'),true);
  assert.equal(map.viewDocumentSummary.hidden,true);
  assert.deepEqual(details.querySelectorAll('.doc-assignment-stat-value').map(x=>x.textContent),['3','2','1']);
  const tabs=details.querySelectorAll('[role="tab"]');
  assert.equal(tabs.length,2);
  assert.equal(details.querySelectorAll('.doc-assignment-item').length,3);
  modal.listeners.click({target:tabs[1]});
  assert.equal(tabs[1].getAttribute('aria-selected'),'true');
  assert.equal(details.querySelectorAll('[role="tabpanel"]')[0].hidden,true);
  modal.listeners['hidden.bs.modal']();
  assert.equal(modal.classList.contains('is-assignment-view'),false);
  assert.equal(details.children.length,0);
});

test('newly drafted requests render safely without approval steps or history',()=>{
  const {window,details,modal}=setup();
  for(const domain of ['softcopy','hardcopy','record']){
    const request={
      kind:domain==='record'?'record':'document',domain,header:{title:'New request',status:'draft'},
      sections:[
        {key:'overview',fields:{'Request Reference':'REQ-NEW','Request Action':'Create'}},
        {key:'approvals',title:'Approval Route',fields:{Steps:'No steps assigned yet'},steps:[]},
        {key:'audit',title:'Decision History',fields:{History:'No approval decisions yet'},events:[]}
      ]
    };
    window.PKDocumentView.render(details,request);
    assert.equal(modal.classList.contains('is-document-view'),true);
    assert.equal(details.querySelectorAll('[role="tab"]').length,2);
    assert.equal(details.querySelectorAll('[role="tabpanel"]').length,2);
  }
});
