const test=require('node:test'),assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm');
function deferred(){let resolve,reject;const promise=new Promise((a,b)=>{resolve=a;reject=b;});return {promise,resolve,reject};}
function setup({incoming=false,reduced=false,marker=null}={}){
 const events={},docEvents={},timers=new Map(),calls=[],animations=[],navigations=[],storage=new Map();let next=0;
 if(marker)storage.set('pk:logout-transition',JSON.stringify(marker));
 function element(id='') {return {id,attrs:{},dataset:{},inert:false,removed:false,disabled:false,value:'',children:[],
  hasAttribute(k){return Object.hasOwn(this.attrs,k);},getAttribute(k){return this.attrs[k]??null;},setAttribute(k,v){this.attrs[k]=String(v);},removeAttribute(k){delete this.attrs[k];},
  remove(){this.removed=true;},focus(){this.focused=true;},prepend(e){this.children.unshift(e);},replaceChildren(...els){this.children=els;this.replaced=true;},
  matches(selector){return selector==='form[data-logo-logout]'&&this===form;},querySelector(selector){return nodes[selector]||null;},querySelectorAll(){return [];},
  animate(frames,options){const task=deferred(),a={element:this,frames,options,finished:task.promise,finish:task.resolve,cancel:task.resolve};animations.push(a);return a;},decode(){return Promise.resolve();}};}
 const root=element(),body=element(),app=element(),overlay=element('logoutTransition'),veil=element('logoutTransitionVeil'),logo=element('logoutTransitionLogo'),glow=element('logoutTransitionGlow'),form=element(),button=element(),csrf=element(),confirmed=element(),page=element(),shell=element(),card=element(),login=element();
 overlay.dataset={loginUrl:'http://localhost/login',statusUrl:'http://localhost/login?session_status=1',expiration:'1800'};
 form.method='post';form.action='http://localhost/logout';form.dataset.confirmed='yes';confirmed.value='yes';csrf.name='pk_csrf_token';
 form.querySelectorAll=()=>[button];
 const nodes={'#logoutTransitionLogo':logo,'#logoutTransitionVeil':veil,'#logoutTransitionGlow':glow,'[name="confirmed"]':confirmed,'[name="pk_csrf_token"]':csrf};
 if(incoming)root.attrs['data-logout-destination']='';
 const document={documentElement:root,body,readyState:incoming?'loading':'complete',hidden:false,fonts:{ready:Promise.resolve()},activeElement:body,
  getElementById(id){return id==='logoutTransition'?overlay:null;},querySelector(s){return {'.app-shell':app,'.page-content':page,'.login-shell':shell,'.login-card':card,'#login':login}[s]||null;},
  querySelectorAll(s){return s.includes('data-logo-logout')?[form]:s.includes('side-link')?[element(),element()]:s.includes('logout-shape')?[element(),element(),element(),element()]:[card];},
  createElement(){return element();},addEventListener(name,fn){docEvents[name]=fn;}};
 const window={location:{href:'http://localhost/'+(incoming?'login':'dashboard'),origin:'http://localhost',replace(url){navigations.push(url);}},
  sessionStorage:{getItem(k){return storage.get(k)??null;},setItem(k,v){storage.set(k,v);},removeItem(k){storage.delete(k);}},
  matchMedia(){return {matches:reduced};},setTimeout(fn,ms){const id=++next;timers.set(id,{fn,ms});return id;},clearTimeout(id){timers.delete(id);},requestAnimationFrame(fn){fn();},
  addEventListener(name,fn){events[name]=fn;},fetch(url,options){const result=deferred();calls.push({url,options,...result});return result.promise;}};
 const path='public/assets/js/logout-transition.js';
 if(fs.existsSync(path))vm.runInNewContext(fs.readFileSync(path,'utf8'),{document,window,URL,Date,Promise,Math,AbortController,FormData:class{}},{filename:path});
 const flush=async()=>{for(let i=0;i<20;i++)await Promise.resolve();};
 const tick=ms=>{for(const [id,t]of[...timers])if(t.ms===ms){timers.delete(id);t.fn();}};
 const submit=(prevented=false)=>{const event={target:form,defaultPrevented:prevented,preventDefault(){this.defaultPrevented=true;}};docEvents.submit?.(event);return event;};
 const respond=(call,data,ok=true)=>call.resolve({ok,json:async()=>data});
 return {root,body,app,overlay,form,button,csrf,confirmed,shell,card,login,events,docEvents,timers,calls,animations,navigations,storage,flush,tick,submit,respond,ready:async()=>{docEvents.DOMContentLoaded?.();await flush();}};
}
test('confirmed logout sends the POST immediately and prevents duplicate requests',async()=>{
 const x=setup(),event=x.submit();assert.equal(event.defaultPrevented,true);assert.equal(x.calls.length,1);
 assert.equal(x.calls[0].options.method,'POST');assert.equal(x.body.inert,true);
 x.submit();assert.equal(x.calls.length,1);assert.equal(x.navigations.length,0);
 x.respond(x.calls[0],{logged_out:true,login_url:'http://localhost/login'});await x.flush();
 x.tick(350);await x.flush();assert.equal(x.body.replaced,true,'Protected DOM must be discarded after the dissolve');
 assert.equal(x.navigations.length,0);x.tick(560);await x.flush();assert.equal(x.navigations[0],'http://localhost/login');
 assert.ok(x.storage.has('pk:logout-transition'));
});
test('canceled confirmation remains untouched',()=>{const x=setup();x.submit(true);assert.equal(x.calls.length,0);assert.equal(x.animations.length,0);});
test('failed logout restores an authenticated dashboard and refreshes the CSRF field',async()=>{
 const x=setup();x.submit();x.respond(x.calls[0],{},false);await x.flush();assert.equal(x.calls.length,2);
 x.respond(x.calls[1],{authenticated:true,csrf:{name:'pk_csrf_token',value:'fresh'}});await x.flush();
 assert.equal(x.body.inert,false);assert.equal(x.body.replaced,undefined);assert.equal(x.navigations.length,0);
 assert.equal(x.csrf.value,'fresh');assert.equal(x.form.dataset.confirmed,'');assert.equal(x.confirmed.value,'no');
 assert.equal(x.storage.size,0);
});
test('a lost logout response is reconciled against the actual server identity',async()=>{
 const x=setup();x.submit();x.calls[0].reject(new Error('connection lost'));await x.flush();
 x.respond(x.calls[1],{authenticated:false});await x.flush();x.tick(350);x.tick(560);await x.flush();
 assert.equal(x.navigations[0],'http://localhost/login');
});
test('expiry uses a read-only status check and never sends a duplicate logout POST',async()=>{
 const x=setup();x.tick(1801000);await x.flush();assert.equal(x.calls[0].options.method,'GET');
 x.respond(x.calls[0],{authenticated:false});await x.flush();x.tick(350);x.tick(560);await x.flush();
 assert.equal(x.calls.length,1);assert.equal(x.navigations[0],'http://localhost/login');
});
test('reduced motion uses only opacity and a short departure',async()=>{
 const x=setup({reduced:true});x.submit();assert.ok(x.animations.length>0);
 for(const a of x.animations)for(const frame of a.frames){assert.equal(frame.transform,undefined);assert.equal(frame.filter,undefined);}
 x.respond(x.calls[0],{logged_out:true,login_url:'http://localhost/login'});await x.flush();x.tick(160);await x.flush();
 assert.equal(x.navigations[0],'http://localhost/login');
});
test('login consumes one fresh handoff, reveals the form and restores keyboard focus',async()=>{
 const x=setup({incoming:true,marker:{at:Date.now()}});assert.equal(x.storage.size,0);assert.equal(x.root.hasAttribute('data-logout-return'),true);
 await x.ready();const reveal=x.animations.find(a=>a.element===x.overlay);assert.ok(reveal);reveal.finish();await x.flush();
 assert.equal(x.root.hasAttribute('data-logout-return'),false);assert.equal(x.shell.inert,false);assert.equal(x.login.focused,true);
 const stale=setup({incoming:true,marker:{at:Date.now()-30000}});await stale.ready();assert.equal(stale.animations.length,0);
 const malformed=setup({incoming:true,marker:{at:'invalid'}});await malformed.ready();assert.equal(malformed.animations.length,0);
});
test('history restoration verifies identity before unlocking protected content',async()=>{
 const x=setup();x.events.pagehide();assert.equal(x.root.hasAttribute('data-auth-history-gate'),true);
 x.events.pageshow({persisted:true});await x.flush();assert.equal(x.calls.length,1);
 x.respond(x.calls[0],{authenticated:true});await x.flush();assert.equal(x.body.inert,false);assert.equal(x.root.hasAttribute('data-auth-history-gate'),false);
});
test('expired history never uncovers cached protected DOM during its transition',async()=>{
 const x=setup();x.events.pagehide();x.events.pageshow({persisted:true});await x.flush();
 x.respond(x.calls[0],{authenticated:false});await x.flush();
 assert.equal(x.body.replaced,true,'Discard expired history before removing its visibility gate');
 assert.equal(x.body.inert,true);
 assert.equal(x.navigations[0],'http://localhost/login');assert.equal(x.storage.size,0,'History must not replay the logo handoff');
});
