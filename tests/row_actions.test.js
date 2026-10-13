const test=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const vm=require('node:vm');

function setup(){
    const handlers={},shown=[],fields=Object.fromEntries(['id','decision','step_key','direction','confirmed','remark'].map(name=>[name,{value:'old',disabled:false}]));
    const reason={disabled:false,required:true,hasAttribute:name=>name==='data-disposal-reason',dispatchEvent(){this.changed=true;}};
    const other={disabled:false,required:false,hasAttribute:()=>false};
    const disposal={hidden:true,querySelectorAll:()=>[reason,other],querySelector:()=>reason};
    const form={dataset:{},attributes:{},submissions:0,reset(){Object.values(fields).forEach(field=>field.value='');},
        querySelector(selector){return selector==='#actionDisposalFields'?disposal:fields[selector.match(/name="([^"]+)"/)?.[1]];},
        setAttribute(key,value){this.attributes[key]=value;},requestSubmit(){this.submissions++;}};
    const nodes={actionForm:form,actionModal:{id:'actionModal'},actionTitle:{},actionDescription:{},actionRemarkFields:{hidden:false}};
    const document={getElementById:id=>nodes[id],addEventListener:(name,handler)=>handlers[name]=handler};
    const window={bootstrap:{Modal:{getOrCreateInstance:node=>({show:()=>shown.push(node.id)})}}};
    const file='public/assets/js/row-actions.js';
    if(fs.existsSync(file))vm.runInNewContext(fs.readFileSync(file,'utf8'),{window,document,Event:class{constructor(type){this.type=type;}}});
    function click(attributes={}){
        const action={getAttribute:name=>attributes[name]??null};
        handlers.click?.({target:{closest:selector=>selector==='.js-action'?action:null},preventDefault(){}});
    }
    return{click,shown,fields,reason,other,disposal,form,nodes};
}
for(const title of ['Submit','Approve','Cancel','Deactivate','Dispatch Hardcopy','Accept Hardcopy'])
test(title+' opens one confirmation without requesting a remark',()=>{
    const x=setup();
    x.click({'data-url':'/my-tasks/hardcopy/decide','data-id':'42','data-title':title,'data-description':'Confirm this record',
        'data-decision':title==='Approve'?'approved':''});
    assert.equal(x.form.submissions,1,'Actions without input should enter the shared confirmation directly.');
    assert.deepEqual(x.shown,[],'The remarks modal must not open.');
    assert.equal(x.fields.remark.disabled,true);
    assert.equal(x.nodes.actionRemarkFields.hidden,true);
    assert.equal(x.fields.id.value,'42');
    assert.equal(x.fields.confirmed.value,'no','Opening confirmation must not authorize the POST yet.');
});
for(const decision of ['returned','rejected'])
test(decision+' keeps its remark and uses that modal as the single confirmation',()=>{
    const x=setup();x.click({'data-url':'/my-tasks/hardcopy/decide','data-id':'42','data-decision':decision});
    assert.deepEqual(x.shown,['actionModal']);assert.equal(x.form.submissions,0);
    assert.equal(x.fields.remark.disabled,false);assert.equal(x.nodes.actionRemarkFields.hidden,false);
    assert.equal(x.form.dataset.confirmed,'yes','Continue in the details modal must not open a second confirmation.');
    assert.equal(x.fields.confirmed.value,'yes');assert.equal(x.fields.decision.value,decision);
});
test('disposal keeps validated reasons and resets them before the next simple action',()=>{
    const x=setup();x.click({'data-disposal':'yes','data-id':'9','data-title':'Dispose','data-url':'/documents/hardcopy/dispose'});
    assert.deepEqual(x.shown,['actionModal']);assert.equal(x.disposal.hidden,false);
    assert.equal(x.reason.disabled,false);assert.equal(x.reason.required,true);assert.equal(x.reason.changed,true);
    x.fields.remark.value='Old reason';x.click({'data-id':'10','data-title':'Submit','data-url':'/my-requests/hardcopy/submit'});
    assert.equal(x.disposal.hidden,true);assert.equal(x.reason.disabled,true);assert.equal(x.reason.required,false);
    assert.equal(x.fields.remark.value,'');assert.equal(x.fields.remark.disabled,true);
    assert.equal(x.form.dataset.confirmed,'');assert.equal(x.fields.confirmed.value,'no');
    assert.equal(x.form.action,'/my-requests/hardcopy/submit');assert.equal(x.fields.id.value,'10');
});
