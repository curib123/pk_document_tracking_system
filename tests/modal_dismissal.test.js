/* Bootstrap and offline modals must never lose unsaved input from outside clicks. */
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

function phpViews(dir) {
    return fs.readdirSync(dir,{withFileTypes:true}).flatMap(entry=>{
        const filename=path.join(dir,entry.name);
        return entry.isDirectory()?phpViews(filename):
            entry.isFile()&&filename.endsWith('.php')?[filename]:[];
    });
}

test('every Bootstrap and mandatory modal declares static backdrop and no Escape dismissal',()=>{
    let total=0;
    for(const filename of phpViews('application/view')) {
        const source=fs.readFileSync(filename,'utf8');
        const pattern=/<div class="([^"]*)"/g;
        for(const match of source.matchAll(pattern)) {
            if(!match[1].split(/\s+/).includes('modal')) continue;
            total++;
            const attrs=source.slice(match.index,match.index+match[0].length+110);
            assert.match(attrs,/data-bs-backdrop="static"/,
                filename+': an outside click must keep this modal open');
            assert.match(attrs,/data-bs-keyboard="false"/,
                filename+': Escape must not discard form input');
        }
    }
    assert.ok(total>=16,'Expected all existing modals, found only '+total);
});

function createOfflineModal() {
    const script=fs.readFileSync('public/assets/js/app.js','utf8');
    const marker='    // Keep detail modals human-readable even when the server returns nested';
    const end=script.indexOf(marker);
    assert.ok(end>0,'Could not isolate Bootstrap fallback.');
    const listeners={};
    let backdrop=null, hiddenEvents=0;
    const classSet=(initial=[])=>{
        const s=new Set(initial);
        return {add:n=>s.add(n),remove:n=>s.delete(n),contains:n=>s.has(n)};
    };
    const modal={
        classList:classSet(['modal']),style:{},attrs:{},
        field:{value:'Unsaved form changes'},
        querySelectorAll(){return [];},contains(){return false;},
        setAttribute(k,v){this.attrs[k]=v;},
        removeAttribute(k){delete this.attrs[k];},
        dispatchEvent(evt){if(evt.type==='hidden.bs.modal')hiddenEvents++;},
        closest(){return null;}
    };
    const document={
        body:{classList:classSet(),appendChild(node){backdrop=node;}},
        activeElement:null,
        querySelector(selector){
            if(selector==='.fallback-backdrop') return backdrop;
            if(selector==='#editor') return modal;
            return null;
        },
        querySelectorAll(selector){return selector==='.fallback-backdrop' && backdrop?[backdrop]:[];},
        createElement(){return {className:'',remove(){backdrop=null;}};},
        addEventListener(type,fn){listeners[type]=fn;}
    };
    const window={};
    const Event=class {constructor(type){this.type=type;}};
    vm.runInNewContext(script.slice(0,end)+'\n})();',
        {window,document,Event}, {filename:'offline-modal-fallback.js'});
    const instance=window.bootstrap.Modal.getOrCreateInstance(modal);
    return {modal,instance,listeners,getHidden:()=>hiddenEvents};
}

test('offline modal stays open after outside click and Escape; draft input remains',()=>{
    const x=createOfflineModal();
    x.instance.show();
    assert.equal(x.modal.classList.contains('show'),true);
    x.listeners.click({target:x.modal});
    assert.equal(x.modal.classList.contains('show'),true);
    assert.equal(x.modal.field.value,'Unsaved form changes');
    let prevented=false;
    x.listeners.keydown({key:'Escape',preventDefault(){prevented=true;}});
    assert.equal(prevented,true);
    assert.equal(x.modal.classList.contains('show'),true);
    assert.equal(x.getHidden(),0);
});

test('offline modal can still close with its explicit Cancel or Close button',()=>{
    const x=createOfflineModal();
    x.instance.show();
    const button={
        closest(selector){return selector==='.modal'?x.modal:null;}
    };
    const target={
        closest(selector){
            return selector==='[data-bs-dismiss="modal"]'?button:null;
        }
    };
    x.listeners.click({target});
    assert.equal(x.modal.classList.contains('show'),false);
    assert.equal(x.getHidden(),1);
});

test('native login notification also resists accidental Escape dismissal',()=>{
    const source=fs.readFileSync('application/view/reusable_components/auth_alert.php','utf8');
    assert.match(source,/addEventListener\('cancel',function\(event\)\{event\.preventDefault\(\);\}\)/);
    assert.match(source,/data-close-alert/,'Visible explicit confirmation remains available.');
});
