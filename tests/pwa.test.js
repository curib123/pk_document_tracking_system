const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

function setup({standalone=false,storage=new Map(),related=null}={}) {
  const events={};
  const media={matches:standalone,addEventListener(){}};
  const button={hidden:true,disabled:false,events:{},addEventListener(n,fn){this.events[n]=fn;}};
  const document={getElementById:()=>button,querySelector:()=>({href:'https://dts.test/dts/manifest.php'}),
    addEventListener(){},visibilityState:'visible'};
  const window={isSecureContext:true,matchMedia:()=>media,addEventListener(n,fn){events[n]=fn;},
    localStorage:{getItem:k=>storage.get(k)||null,setItem:(k,v)=>storage.set(k,v),removeItem:k=>storage.delete(k)}};
  vm.runInNewContext(fs.readFileSync('public/assets/js/pwa.js','utf8'),{window,document,navigator:related?{getInstalledRelatedApps:related}:{},URL});
  return {events,button,storage};
}
function prompt(outcome='accepted') {
  return {calls:0,preventDefault(){},async prompt(){this.calls++;return {outcome};}};
}
test('installation is shown only after browser eligibility, then remembered without another prompt',async()=>{
  const {events,button,storage}=setup();
  assert.equal(button.hidden,true);
  const event=prompt();await events.beforeinstallprompt(event);
  assert.equal(button.hidden,false);
  await button.events.click();
  assert.equal(event.calls,1);
  assert.equal(button.hidden,true);
  assert.equal(storage.size,1);
  const next=setup({storage});
  assert.equal(next.button.hidden,true,'An application update must not prompt again.');
});
test('standalone app never offers installation, and browser appinstalled hides an available prompt',async()=>{
  const standalone=setup({standalone:true});
  await standalone.events.beforeinstallprompt(prompt());
  assert.equal(standalone.button.hidden,true);
  const browser=setup();await browser.events.beforeinstallprompt(prompt());
  browser.events.appinstalled();assert.equal(browser.button.hidden,true);
});
test('dismissal is not treated as installation and a genuine post-uninstall prompt can be used',async()=>{
  const state=setup();await state.events.beforeinstallprompt(prompt('dismissed'));await state.button.events.click();
  assert.equal(state.storage.size,0);
  await state.events.beforeinstallprompt(prompt());await state.button.events.click();
  const reopened=setup({storage:state.storage});await reopened.events.beforeinstallprompt(prompt());
  assert.equal(reopened.button.hidden,false,'A browser-confirmed uninstallation must not leave a stale install flag.');
});
test('an installation completed during the related-app lookup cannot reveal a stale prompt',async()=>{
  let resolve;
  const lookup = new Promise(done=>resolve=done);
  const state=setup({related:()=>lookup});
  const eligibility=state.events.beforeinstallprompt(prompt());
  state.events.appinstalled();resolve([]);await eligibility;
  assert.equal(state.button.hidden,true);
});

test('worker fetches live GET responses without persisting data and leaves POST forms alone',async()=>{
  const events={};let request;
  const self={registration:{scope:'https://dts.test/dts/'},addEventListener:(n,fn)=>events[n]=fn,
    skipWaiting:()=>Promise.resolve(),clients:{claim:()=>Promise.resolve()}};
  vm.runInNewContext(fs.readFileSync('public/service-worker.js','utf8'),
    {self,URL,Request,Response,fetch:async r=>{request=r;return new Response('live response');}});
  let result;
  events.fetch({request:new Request('https://dts.test/dts/documents/softcopy'),respondWith:p=>{result=p;}});
  assert.equal(await (await result).text(),'live response');
  assert.equal(request.cache,'no-store');
  result=null;
  events.fetch({request:new Request('https://dts.test/dts/documents/softcopy/direct',{method:'POST'}),respondWith:p=>{result=p;}});
  assert.equal(result,null);
  events.fetch({request:new Request('https://other.test/file'),respondWith:p=>{result=p;}});
  assert.equal(result,null);
});
