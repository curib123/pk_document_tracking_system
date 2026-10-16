const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

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

function timestampFormatter() {
  let notify;
  const pending = [];
  const body = {nodeType:1,matches:()=>false};
  const document = {body,querySelectorAll:()=>[],addEventListener(){},
    createTreeWalker:()=>({nextNode:()=>null})};
  class MutationObserver {
    constructor(callback) { notify=callback; }
    observe() {}
    disconnect() {}
  }
  const window = {MutationObserver,addEventListener(){}};
  vm.runInNewContext(fs.readFileSync('public/assets/js/plan-controls.js','utf8'),
    {document,window,MutationObserver,NodeFilter:{SHOW_TEXT:4}});
  return (input) => {
    let value=input;
    let writes=0;
    const text = {nodeType:3,parentElement:{matches:()=>false,closest:()=>true},
      get nodeValue(){return value;},
      set nodeValue(next){value=next;writes++;pending.push({type:'characterData',target:text});}};
    notify([{type:'childList',addedNodes:[text]}]);
    // Bound delivery so a recursive observer fails instead of hanging the suite.
    for(let pass=0;pass<4 && pending.length;pass++) notify(pending.splice(0));
    assert.equal(pending.length,0,'Timestamp formatting must stop triggering itself: '+input);
    return {value,writes};
  };
}

test('request timestamps settle after one conversion for every hour of the day',()=>{
  const format=timestampFormatter();
  for(let hour=0;hour<24;hour++) {
    const input='2026-10-14 '+String(hour).padStart(2,'0')+':15:26';
    const expected='2026-10-14 '+(hour%12||12)+':15:26 '+(hour>=12?'PM':'AM');
    assert.deepEqual(format(input),{value:expected,writes:1});
    assert.deepEqual(format(expected),{value:expected,writes:0});
  }
});

test('formatted timestamps stay unchanged when a request modal is observed again',()=>{
  const format=timestampFormatter();
  for(const suffix of ['AM','PM','am','pm']) {
    const text='2026-10-14 11:15:26 '+suffix;
    assert.deepEqual(format(text),{value:text,writes:0});
  }
  const mixed='Created 2026-10-14 11:15:26 AM; modified 2026-10-14 23:20:30';
  assert.deepEqual(format(mixed),{
    value:'Created 2026-10-14 11:15:26 AM; modified 2026-10-14 11:20:30 PM',writes:1});
});
