const test=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const vm=require('node:vm');

function deferred() { let resolve,reject;const promise=new Promise((a,b)=>{resolve=a;reject=b;});return {promise,resolve,reject}; }
function setup({armed=true,reduced=false,fontsReady=true,imageReady=true}={}) {
    const animations=[],timers=new Map(),events={},documentEvents={};
    const fonts=deferred(),image=deferred();
    if(fontsReady) fonts.resolve();if(imageReady) image.resolve();
    let next=0;
    function element() {
        return {attrs:{},dataset:{},inert:false,removed:false,
            hasAttribute(name){return Object.hasOwn(this.attrs,name);},
            getAttribute(name){return this.attrs[name]??null;},
            setAttribute(name,value){this.attrs[name]=String(value);},
            removeAttribute(name){delete this.attrs[name];},
            remove(){this.removed=true;},
            getBoundingClientRect(){return {width:240};},
            animate(frames,options){
                const task=deferred();
                const animation={element:this,frames,options,finished:task.promise,
                    finish:task.resolve,canceled:false,cancel(){this.canceled=true;task.resolve();}};
                animations.push(animation);return animation;
            }};
    }
    const root=element(),splash=element(),logo=element(),glow=element(),veil=element(),aperture=element(),app=element();
    if(armed) root.attrs['data-login-transition']='';
    logo.decode=()=>image.promise;
    const nodes={loginTransition:splash,loginTransitionLogo:logo,loginTransitionGlow:glow,
        loginTransitionVeil:veil,loginTransitionAperture:aperture};
    const document={documentElement:root,readyState:'loading',fonts:{ready:fonts.promise},
        getElementById(id){return nodes[id]||null;},querySelector(selector){return selector==='.app-shell'?app:null;},
        addEventListener(name,fn){documentEvents[name]=fn;}};
    const window={innerWidth:1440,innerHeight:1000,matchMedia(){return {matches:reduced};},
        requestAnimationFrame(fn){fn();},
        setTimeout(fn,ms){const id=++next;timers.set(id,{fn,ms});return id;},
        clearTimeout(id){timers.delete(id);},addEventListener(name,fn){events[name]=fn;}};
    const path='public/assets/js/login-transition.js';
    if(fs.existsSync(path)) vm.runInNewContext(fs.readFileSync(path,'utf8'),{window,document,Promise,Math},{filename:path});
    const flush=async()=>{for(let i=0;i<12;i++) await Promise.resolve();};
    const tick=(duration)=>{for(const [id,timer] of [...timers]) if(timer.ms===duration){timers.delete(id);timer.fn();}};
    return {root,splash,logo,glow,veil,aperture,app,animations,timers,events,fonts,image,flush,tick,
        ready:async()=>{documentEvents.DOMContentLoaded?.();await flush();}};
}

test('ordinary dashboard visits never install an animation or gate interaction',async()=>{
    const x=setup({armed:false});await x.ready();
    assert.equal(x.animations.length,0);assert.equal(x.app.inert,false);assert.equal(x.timers.size,0);
});
test('logo enters at 65 percent and the final reveal waits for layout fonts',async()=>{
    const x=setup({fontsReady:false});await x.ready();
    const entrance=x.animations.find(a=>a.element===x.logo);
    assert.ok(entrance,'Authenticated dashboard must animate its logo');
    assert.equal(entrance.frames[0].transform,'scale(.65)');assert.equal(entrance.options.duration,420);
    assert.equal(x.app.inert,true);
    entrance.finish();await x.flush();x.tick(130);await x.flush();
    assert.equal(x.animations.filter(a=>a.element===x.aperture).length,0,'No reveal before fonts settle');
    x.fonts.resolve();await x.flush();
    const reveal=x.animations.find(a=>a.element===x.aperture);
    assert.ok(reveal,'Ready dashboard must emerge through a circular aperture');
    assert.equal(reveal.options.duration,520);
    reveal.finish();await x.flush();
    assert.equal(x.splash.removed,true);assert.equal(x.root.hasAttribute('data-login-transition'),false);
    assert.equal(x.app.inert,false);assert.equal(x.app.getAttribute('aria-busy'),null);
});
test('reduced motion uses a brief fade without zoom, blur or an aperture animation',async()=>{
    const x=setup({reduced:true});await x.ready();
    assert.equal(x.animations.length,1);
    assert.equal(x.animations[0].element,x.splash);assert.equal(x.animations[0].options.duration,160);
    assert.equal(x.animations[0].frames[0].opacity,1);
    assert.equal(x.animations[0].frames[0].transform,undefined);
    x.animations[0].finish();await x.flush();assert.equal(x.app.inert,false);
});
test('history restoration and orientation changes release the gate without replay',async()=>{
    const x=setup();await x.ready();const count=x.animations.length;
    x.events.pageshow({persisted:true});await x.flush();
    assert.equal(x.splash.removed,true);assert.equal(x.app.inert,false);
    await x.ready();assert.equal(x.animations.length,count);
    const rotated=setup();await rotated.ready();rotated.events.resize();await rotated.flush();
    assert.equal(rotated.splash.removed,true);assert.equal(rotated.app.inert,false);
});
test('missing logo and stalled readiness cannot leave a blocked dashboard',async()=>{
    const broken=setup({imageReady:false});await broken.ready();broken.image.reject(new Error('image unavailable'));
    await broken.flush();assert.equal(broken.splash.removed,true);
    const stalled=setup({fontsReady:false});await stalled.ready();stalled.tick(5000);await stalled.flush();
    assert.equal(stalled.splash.removed,true);assert.equal(stalled.app.inert,false);
});
test('a native login page crossfade completes before logo entrance begins',async()=>{
    const x=setup();const entry=deferred();x.events.pagereveal?.({viewTransition:{finished:entry.promise}});
    await x.ready();assert.equal(x.animations.length,0);
    entry.resolve();await x.flush();assert.ok(x.animations.some(a=>a.element===x.logo));
});
