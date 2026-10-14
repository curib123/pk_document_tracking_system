const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

function setup(contentDocument) {
  const button = {disabled:true,events:{},addEventListener(event,fn){this.events[event]=fn;}};
  const status = {textContent:'Preparing print preview'};
  const frame = {contentDocument,events:{},addEventListener(event,fn){this.events[event]=fn;},
    contentWindow:{calls:0,focus(){},print(){this.calls++;}}};
  const document = {getElementById:(id)=>({printButton:button,printStatus:status,printFrame:frame})[id]||null};
  vm.runInNewContext(fs.readFileSync('public/assets/js/controlled-print.js','utf8'),{document,window:{}});
  return {button,status,frame};
}

test('an already-loaded PDF enables Print when the deferred script starts late',()=>{
  const {button,frame}=setup({readyState:'complete',URL:'https://dts.test/files/print-content/1'});
  assert.equal(button.disabled,false);
  button.events.click();
  assert.equal(frame.contentWindow.calls,1);
});

test('the initial blank frame must wait for its actual PDF resource',()=>{
  const {button,frame}=setup({readyState:'complete',URL:'about:blank'});
  assert.equal(button.disabled,true);
  frame.events.load();
  assert.equal(button.disabled,false);
});
