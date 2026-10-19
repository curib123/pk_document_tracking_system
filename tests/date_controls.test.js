const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

test('date formatting settles after its own mutation instead of freezing the modal', () => {
  const text = {nodeType:3,nodeValue:'2026-10-19 11:20:30',parentElement:{matches:()=>false,closest:()=>true}};
  let observe;
  class Observer {constructor(callback){observe=callback;}observe(){}disconnect(){}}
  const document = {body:{nodeType:1,matches:()=>false},querySelectorAll:()=>[],addEventListener(){},
    createTreeWalker(){let visited=false;return {nextNode(){if(visited)return null;visited=true;return text;}};}};
  const window = {MutationObserver:Observer,addEventListener(){}};
  vm.runInNewContext(fs.readFileSync('public/assets/js/plan-controls.js','utf8'),
    {window,document,MutationObserver:Observer,NodeFilter:{SHOW_TEXT:4}});
  assert.equal(text.nodeValue,'2026-10-19 11:20:30 AM');
  for(let i=0;i<5;i++)observe([{type:'characterData',target:text}]);
  assert.equal(text.nodeValue,'2026-10-19 11:20:30 AM');
});

test('external date controls belong to the filter form and enable custom dates before focusing', () => {
  const page = {value:'3'};
  const form = {submits:0, querySelector: (selector)=>selector==='[name="page"]'?page:null,
    requestSubmit(){this.submits++;}};
  const select = {form,value:'all',listeners:{},addEventListener(event,fn){this.listeners[event]=fn;}};
  const fields = ['from','to'].map((name)=>({name,form,value:'',disabled:true,listeners:{},
    addEventListener(event,fn){this.listeners[event]=fn;},focus(){this.focused=true;}}));
  const labels = fields.map((field)=>({hidden:true,querySelector:()=>field}));
  const document = {body:null, listeners:{}, addEventListener(event, fn){this.listeners[event]=fn;}, getElementById:()=>form,
    querySelectorAll: (selector)=>selector==='[data-date-period]'?[select]:
      selector==='[data-date-custom]'?labels:selector==='[data-date-custom] input'?fields:[]};
  vm.runInNewContext(fs.readFileSync('public/assets/js/plan-controls.js','utf8'), {document});
  select.value='custom';select.listeners.change();
  assert.equal(fields[0].disabled,false);
  assert.equal(fields[0].required,true);
  assert.equal(fields[0].focused,true);
  assert.equal(labels[0].hidden,false);
  assert.equal(form.submits,0);
  fields[0].value='2026-01-01';fields[0].listeners.input();
  assert.equal(fields[1].min,'2026-01-01');
  select.value='week';select.listeners.change();
  assert.equal(fields[0].disabled,true);
  assert.equal(labels[0].hidden,true);
  assert.equal(page.value,'1');
  assert.equal(form.submits,1);
  select.value='custom';select.listeners.change();
  assert.equal(fields[0].disabled,false);
  select.value='all';document.listeners['pk:table-updated']();
  assert.equal(fields[0].disabled,true);
  assert.equal(labels[0].hidden,true);
  assert.equal(form.submits,1, 'Synchronizing a live response must not submit another request.');
});
