const test=require('node:test'),assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm');
function setup(shared=new Set()){
 const listeners={},windowListeners={},timers=new Map();let next=0,now=0;
 const element=tag=>({tagName:tag.toUpperCase(),children:[],attributes:{},textContent:'',hidden:false,
  append(...nodes){this.children.push(...nodes);},replaceChildren(...nodes){this.children=nodes;},
  setAttribute(name,value){this.attributes[name]=String(value);},
  addEventListener(name,handler){(this.listeners||={})[name]=handler;},
  classList:{contains(){return false;}}});
 const modal=element('div'),list=element('ol');modal.dataset={pendingUrl:'/notifications/tasks',claimUrl:'/notifications/tasks/claim',csrfName:'csrf',csrfValue:'old',csrfCookie:'pk_csrf'};
 const x={tasks:[{key:'101:1',reference:'REQ-42',label:'Plant Manager Approval',url:'/my-tasks/hardcopy?q=REQ-42'}],shown:0,requests:[],busy:false,open:false,fail:false,stopped:false};
 const tokenField={name:'csrf',value:'old'},otherField={name:'id',value:'42'};
 const document={hidden:false,cookie:'pk_csrf=old',body:{inert:false},activeElement:{matches:()=>false},
  documentElement:{hasAttribute:()=>false},getElementById:id=>id==='taskNotificationModal'?modal:id==='taskNotificationList'?list:null,
  createElement:element,querySelector:selector=>selector.startsWith('.modal')?(x.busy||x.open?{}:null):null,
  querySelectorAll:()=>[tokenField,otherField],addEventListener(name,handler){(listeners[name]||=[]).push(handler);}};
 const response=data=>({ok:true,status:200,json:async()=>data});
 const window={location:{origin:'https://dts.test'},
  setTimeout(fn,delay){const id=++next;timers.set(id,{fn,at:now+delay});return id;},clearTimeout:id=>timers.delete(id),
  addEventListener(name,handler){windowListeners[name]=handler;},
  bootstrap:{Modal:{getOrCreateInstance:()=>({show(){x.shown++;x.open=true;},hide(){x.open=false;modal.listeners?.['hidden.bs.modal']?.();}})}},
  async fetch(url,options={}){
   x.requests.push({url,options});if(x.fail)throw new Error('Unavailable');
   if(x.stopped)return{ok:false,status:401};
   if(options.method==='POST'){
    x.onClaim?.();
    const requested=options.body.getAll('keys[]');const claimed=x.tasks.filter(task=>requested.includes(task.key)&&!shared.has(task.key));
    claimed.forEach(task=>shared.add(task.key));document.cookie='pk_csrf=fresh';
    return response({tasks:claimed,csrf:{name:'csrf',value:'fresh'}});
   }
   return response({tasks:x.tasks.filter(task=>!shared.has(task.key)),csrf:{name:'csrf',value:'old'}});
  }};
 const file='public/assets/js/task-notifications.js';
 if(fs.existsSync(file))vm.runInNewContext(fs.readFileSync(file,'utf8'),{window,document,URL,URLSearchParams,AbortController});
 async function flush(){for(let i=0;i<15;i++)await Promise.resolve();}
 async function advance(ms){now+=ms;for(const[id,timer]of[...timers])if(timer.at<=now){timers.delete(id);timer.fn();}await flush();}
 function dispatch(name,event={}){for(const listener of listeners[name]||[])listener(event);}
 function close(){x.open=false;modal.listeners?.['hidden.bs.modal']?.();dispatch('hidden.bs.modal');}
 return Object.assign(x,{shared,modal,list,document,tokenField,otherField,flush,advance,dispatch,close,windowListeners});
}
test('a new task shows automatically once and stays seen after refresh or another browser',async()=>{
 const x=setup();await x.flush();assert.equal(x.shown,1);assert.equal(x.shared.has('101:1'),true);
 x.close();await x.advance(15000);assert.equal(x.shown,1,'Polling must not repeat a shown task.');
 const refreshed=setup(x.shared);await refreshed.flush();assert.equal(refreshed.shown,0,'Shown state must outlive the page.');
});
test('another task and a reactivated approval each create one new popup',async()=>{
 const x=setup();await x.flush();x.close();
 x.tasks.push({key:'102:1',reference:'REQ-43',label:'Final Approval',url:'/my-tasks/hardcopy?q=REQ-43'});
 await x.advance(15000);assert.equal(x.shown,2);assert.equal(x.list.children.length,1);
 x.close();x.tasks=[{key:'101:2',reference:'REQ-42',label:'Recheck',url:'/my-tasks/hardcopy?q=REQ-42'}];
 await x.advance(15000);assert.equal(x.shown,3);x.close();await x.advance(15000);assert.equal(x.shown,3);
});
test('alerts wait for an existing modal and avoid claiming invisible notifications',async()=>{
 const x=setup();x.busy=true;await x.flush();assert.equal(x.shown,0);assert.equal(x.shared.size,0);
 x.busy=false;await x.advance(1000);assert.equal(x.shown,1);
 const hidden=setup();hidden.document.hidden=true;await hidden.flush();assert.equal(hidden.shown,0);assert.equal(hidden.shared.size,0);
 hidden.document.hidden=false;hidden.dispatch('visibilitychange');await hidden.flush();assert.equal(hidden.shown,1);
});
test('new arrivals wait until the currently shown task alert closes',async()=>{
 const x=setup();await x.flush();x.tasks.push({key:'102:1',reference:'REQ-43',label:'Final',url:'/my-tasks/hardcopy'});
 await x.advance(15000);assert.equal(x.shown,1);assert.equal(x.shared.has('102:1'),false);
 x.close();await x.advance(1000);assert.equal(x.shown,2);
});
test('competing pages use the server claim so only one can display a task',async()=>{
 const shared=new Set(),first=setup(shared),second=setup(shared);await first.flush();await second.flush();
 assert.equal(first.shown+second.shown,1);
});
test('references stay plain text and task links cannot navigate to an external site',async()=>{
 const x=setup();x.tasks=[{key:'101:1',reference:'<script>alert(1)</script>',label:'<img src=x>',url:'https://outside.example/steal'}];
 await x.flush();assert.equal(x.shown,1);
 assert.equal(x.list.children[0].children[0].children[0].textContent,'<script>alert(1)</script>');
 assert.equal(x.list.children[0].children[0].tagName,'DIV');
});
test('claims and later form submissions use the current CSRF cookie',async()=>{
 const x=setup();x.document.cookie='pk_csrf=latest';await x.flush();
 const post=x.requests.find(request=>request.options.method==='POST');assert.equal(post.options.body.get('csrf'),'latest');
 assert.equal(x.tokenField.value,'fresh');assert.equal(x.otherField.value,'42');
 x.document.cookie='pk_csrf=another-tab';x.dispatch('submit',{defaultPrevented:true});assert.equal(x.tokenField.value,'another-tab');
});
test('network failures and expired sessions show nothing and preserve task state',async()=>{
 const unavailable=setup();unavailable.fail=true;await unavailable.flush();assert.equal(unavailable.shown,0);assert.equal(unavailable.shared.size,0);
 unavailable.fail=false;await unavailable.advance(15000);assert.equal(unavailable.shown,1);
 const expired=setup();expired.stopped=true;await expired.flush();assert.equal(expired.shown,0);assert.equal(expired.shared.size,0);
});
test('a modal opened during the claim defers presentation and history restoration resumes polling',async()=>{
 const x=setup();x.onClaim=()=>{x.busy=true;};await x.flush();assert.equal(x.shown,0);assert.equal(x.shared.size,1);
 x.windowListeners.pagehide();x.busy=false;x.windowListeners.pageshow({persisted:true});await x.flush();await x.advance(1000);
 assert.equal(x.shown,1,'The saved occurrence must remain presentable after a back/forward restoration.');
 x.close();x.onClaim=null;x.tasks.push({key:'102:1',reference:'REQ-43',label:'Final',url:'/my-tasks/hardcopy'});
 await x.advance(15000);assert.equal(x.shown,2,'History restoration must not leave polling stuck.');
});
