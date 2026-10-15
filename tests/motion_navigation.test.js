/* Browser-independent checks of progressive loading.
 * No DOM library or external npm package needed.
 */
const test=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const vm=require('node:vm');

function setup(options={}) {
    const listeners={};
    const windows={};
    const timers=new Map();
    let nextTimer=0;
    const page=(options.login||options.required)?null:{
        children:[],attrs:{},
        appendChild(el){this.children.push(el);},
        setAttribute(key,value){this.attrs[key]=value;},
        removeAttribute(key){delete this.attrs[key];}
    };
    const required=options.required?{classList:{
        values:new Set(),
        add(name){this.values.add(name);},
        remove(name){this.values.delete(name);},
        contains(name){return this.values.has(name);}
    }}:null;
    const classes=new Set(options.login?['login-page']:[]);
    const body={classList:{
        add(name){classes.add(name);},
        remove(name){classes.delete(name);},
        contains(name){return classes.has(name);}
    }};
    function element() {
        return {
            children:[],attrs:{},hidden:false,
            append(...children){this.children.push(...children);},
            appendChild(child){this.children.push(child);},
            setAttribute(name,value){this.attrs[name]=value;}
        };
    }
    const document={
        body,hidden:false,
        querySelector(selector){
            return selector==='.page-content'?page:
                selector==='[data-required-password]'?required:null;
        },
        createElement(){return element();},
        addEventListener(name,handler){listeners[name]=handler;}
    };
    const window={
        location:{href:'https://example.test/documents/hardcopy',origin:'https://example.test'},
        URL,
        matchMedia(){return {matches:false};},
        setTimeout(callback){const id=++nextTimer;timers.set(id,callback);return id;},
        clearTimeout(id){timers.delete(id);},
        addEventListener(name,handler){windows[name]=handler;}
    };
    vm.runInNewContext(fs.readFileSync('public/assets/js/motion.js','utf8'),
        {window,document,URL}, {filename:'motion.js'});
    const flush=()=>{const tasks=[...timers];timers.clear();tasks.forEach(([,fn])=>fn());};
    const anchor={
        href:'https://example.test/documents/softcopy',
        hasAttribute(){return false;},
        getAttribute(name){return name==='href'?'/documents/softcopy':null;}
    };
    return {listeners,windows,timers,flush,page,body,required,anchor};
}

test('same-origin navigation creates one shimmer and restores normal UI after pageshow',()=>{
    const x=setup();
    x.listeners.click({
        button:0,ctrlKey:false,metaKey:false,altKey:false,shiftKey:false,
        defaultPrevented:false,target:{closest(){return x.anchor;}}
    });
    assert.equal(x.timers.size,1);
    x.flush();
    assert.equal(x.page.attrs['aria-busy'],'true');
    assert.equal(x.page.children.length,1);
    const skeleton=x.page.children[0];
    assert.equal(skeleton.hidden,false);
    assert.equal(skeleton.attrs['aria-hidden'],'true');
    x.windows.pageshow();
    assert.equal(skeleton.hidden,true);
    assert.equal(x.page.attrs['aria-busy'],undefined);
});

test('canceled form confirmations never produce a skeleton',()=>{
    const x=setup();
    x.listeners.submit({defaultPrevented:true,target:{method:'post'}});
    assert.equal(x.timers.size,0);
    x.listeners.submit({defaultPrevented:false,target:{method:'post'}});
    assert.equal(x.timers.size,1);
});

test('auto-submit filters get loading shimmer; canceled clicks and downloads do not',()=>{
    const x=setup();
    x.listeners.change({target:{form:{},closest(){return null;},matches(selector){return selector==='[data-auto-submit]';}}});
    assert.equal(x.timers.size,1);
    x.windows.pageshow();
    const anchor={...x.anchor,hasAttribute(name){return name==='download';}};
    x.listeners.click({
        button:0,ctrlKey:false,metaKey:false,altKey:false,shiftKey:false,
        defaultPrevented:false,target:{closest(){return anchor;}}
    });
    assert.equal(x.timers.size,0);
    x.listeners.change({target:{form:{},closest(){return {};},matches(){return true;}}});
    assert.equal(x.timers.size,0, 'Live filters use their inline loading state.');
});

test('login and required-password forms use independent shimmer surfaces',()=>{
    const login=setup({login:true});
    login.listeners.submit({defaultPrevented:false,target:{method:'post'}});
    login.flush();
    assert.equal(login.body.classList.contains('motion-login-loading'),true);
    login.windows.pageshow();
    assert.equal(login.body.classList.contains('motion-login-loading'),false);

    const required=setup({required:true});
    required.listeners.submit({defaultPrevented:false,target:{method:'post'}});
    required.flush();
    assert.equal(required.required.classList.contains('motion-auth-loading'),true);
    required.windows.pageshow();
    assert.equal(required.required.classList.contains('motion-auth-loading'),false);
});
