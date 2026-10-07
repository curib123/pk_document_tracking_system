import { ApiClient } from './api.js';
import { el, button, Modal, notice, table, inspect, labelOf } from './components.js';
import { field, formModal, actionModal, mountFields } from './forms.js';
import { workflowVersionModal } from './workflow-ui.js';
const api = new ApiClient();
const content = document.querySelector('#content'), navigation = document.querySelector('#navigation'), account = document.querySelector('#account'), accountActions = document.querySelector('#account-actions'), status = document.querySelector('#global-status');
let metadata, user, permissions = [], currentModule, page = 1, query = '', limit = 25, sort = '', direction = 'desc', listGeneration = 0;
const can = capability => permissions.includes(capability);
const fieldsFor = fields => fields.map(item => item.type === 'request_type' ? { ...item, requestTypes: metadata.request_types } : item);
const identity = row => ({ id: Number(row.id), version: Number(row.version) });
const reason = () => field('reason', 'textarea');
const comments = () => field('comments', 'textarea');
const singular = module => ({ users: 'user', roles: 'role', permissions: 'permission', areas: 'area', specifics: 'specific', assets: 'asset', locations: 'location', categories: 'category', workflows: 'workflow' })[module] || 'record';
function globalError(error) { status.textContent = error.message; }
async function refresh() { if (currentModule) await loadTable(); }
function afterChange(parent) { return async () => { parent?.forceCloseAfterSuccess(); await refresh(); }; }
function login() {
  return formModal('Sign in', [field('username'), field('password', 'password')], {}, api, values => api.request('auth.login', values, true), { submitLabel: 'Sign in', closeOnSuccess: true, after: boot, explanation: 'Use an administrator-created account. Public registration is not available.' });
}
function password() {
  return formModal('Change password', [field('current_password', 'password'), field('new_password', 'password'), field('confirm_password', 'password')], {}, api, values => api.request('auth.password', values, true), { after: boot, explanation: 'Use a new password of 12–72 bytes. Changing it invalidates your other sessions.' });
}
async function boot() {
  currentModule = null; listGeneration++; navigation.replaceChildren(); content.replaceChildren(); accountActions.replaceChildren();
  try {
    const session = await api.request('session'); user = session.user; permissions = session.permissions;
    if (!user) {
      account.textContent = 'Not signed in.'; status.textContent = 'Sign in to continue.';
      accountActions.append(button('Sign in', login)); login(); return;
    }
    account.textContent = `${user.first_name} ${user.last_name} — ${user.position_title}`;
    accountActions.append(button('Change password', password), button('Sign out', () => actionModal('Sign out', api, 'auth.logout', {}, [], { explanation: 'End this signed-in session?', after: boot, closeOnSuccess: true })));
    if (Number(user.require_password_change)) {
      status.textContent = 'You must change your initial password before accessing any module.'; password(); return;
    }
    metadata = await api.request('metadata'); permissions = metadata.permissions;
    if (can('dashboard.view')) navigation.append(button('Dashboard', dashboard));
    for (const module of metadata.modules) navigation.append(button(module.label, () => selectModule(module)));
    status.textContent = 'Ready.';
    const initial = metadata.modules.find(module => module.key === document.querySelector('meta[name=initial-module]')?.content);
    if (initial) await selectModule(initial); else if (can('dashboard.view')) await dashboard();
  } catch (error) { globalError(error); accountActions.append(button('Retry connection', boot)); }
}
async function dashboard() {
  currentModule = null; listGeneration++; content.replaceChildren(el('h2', {}, 'Dashboard'), el('p', {}, 'Loading…'));
  try {
    const data = await api.request('dashboard');
    if (currentModule) return;
    content.replaceChildren(el('h2', {}, 'Dashboard'));
    for (const [name, value] of Object.entries(data)) content.append(Array.isArray(value) ? el('section', {}, el('h3', {}, labelOf(name)), table(['status', 'total'], value)) : el('p', {}, `${labelOf(name)}: ${value}`));
  } catch (error) { globalError(error); }
}
async function selectModule(module) {
  currentModule = module; page = 1; query = ''; sort = module.columns[0]; direction = 'desc';
  content.replaceChildren(el('h2', {}, module.label));
  const controls = el('div');
  if (['softcopy','hardcopy'].includes(module.key)) {
    if (can(`${module.key}.direct`)) controls.append(button(`Direct create ${module.key}`, () => directDocument(module.key)));
    if (can('requests.add') && can(`${module.key}.request`)) controls.append(button('New document request', () => requestForm(null, { type: `${module.key}_create` })));
  } else if (module.key === 'workflows' && can('workflows.edit')) controls.append(button('Add workflow', () => editCatalog(module)));
  else if (module.fields.length && module.key !== 'settings' && can(`${module.key}.add`)) controls.append(button(`Add ${singular(module.key)}`, () => editCatalog(module)));
  if (['requests','my_requests','my_tasks','transfers','access','assignments','disposals'].includes(module.key) && can('requests.add')) controls.append(button('New request', () => requestForm()));
  if (module.key === 'files' && can('files.upload')) controls.append(button('Upload file', () => formModal('Upload private file', [field('file_id','upload')], {}, api, values => ({ id: values.file_id, message: 'Private file saved. Use it in a document/revision or attach it from a document dialog.' }), { after: refresh })));
  const search = el('input', { type: 'search', id: 'table-search', placeholder: 'Search records', value: query });
  const searchForm = el('form', { onsubmit: event => { event.preventDefault(); query = search.value; page = 1; loadTable().catch(globalError); } }, el('label', { htmlFor: 'table-search' }, 'Search'), search, el('button', { type: 'submit' }, 'Search'));
  const pageSize = el('select', { id:'page-size' }, [10,25,50,100].map(size => el('option', { value: size }, size))); pageSize.value = limit;
  pageSize.addEventListener('change', () => { limit = Number(pageSize.value); page = 1; loadTable().catch(globalError); });
  const sorting = el('select', { id:'table-sort' }, module.columns.map(column => el('option', { value: column }, labelOf(column))));
  sorting.value = sort; sorting.addEventListener('change', () => { sort=sorting.value; page=1; loadTable().catch(globalError); });
  const order = button('Reverse order', () => { direction = direction === 'asc' ? 'desc' : 'asc'; return loadTable(); });
  content.append(controls, searchForm, el('p', {}, el('label', { htmlFor:'page-size' }, 'Rows per page'), pageSize, el('label', { htmlFor:'table-sort' }, 'Sort by'), sorting, order), button('Refresh records', loadTable), el('p', { id:'table-status', role:'status' }), el('div', { id:'table-container' }), el('div', { id:'pagination' }));
  await loadTable();
}
async function loadTable() {
  if (!currentModule) return;
  const module = currentModule, generation = ++listGeneration;
  const message = document.querySelector('#table-status'); if (!message) return;
  message.textContent = 'Loading records…';
  try {
    const result = await api.request('list', { module:module.key, page, limit, q:query, sort, direction });
    if (generation !== listGeneration || currentModule !== module) return;
    if (page > result.pages) { page = result.pages; return loadTable(); }
    document.querySelector('#table-container').replaceChildren(table(module.columns, result.rows, module.key === 'sequences' ? null : row => button('View / actions', () => details(module.key, row.id))));
    message.textContent = `${result.total} matching records. Page ${result.page} of ${result.pages}.`;
    document.querySelector('#pagination').replaceChildren(button('Previous', () => { page--; return loadTable(); }, { disabled:page<=1 }),button('Next', () => { page++; return loadTable(); }, { disabled:page>=result.pages }));
  } catch (error) { if (generation === listGeneration) { message.textContent = error.message; document.querySelector('#table-container').replaceChildren(); } }
}
function editCatalog(module, row = {}, parent) {
  const existing = !!row.id; const operation = module.key === 'workflows' ? 'workflows.save' : 'catalog.save';
  if (module.key === 'workflows' && !existing) row = { ...row, active: 0 };
  return formModal(existing ? `Edit ${singular(module.key)}` : `Add ${singular(module.key)}`, fieldsFor(module.fields), row, api, values => api.request(operation, { module:module.key, ...(existing ? identity(row) : {}), ...values }, true), { after:afterChange(parent), disabled:existing ? (module.key === 'permissions' ? ['module_key','action_key'] : module.key === 'workflows' ? ['workflow_key','request_type'] : []) : [], explanation:module.key === 'workflows' ? 'New workflows start inactive. Publishing a version makes it the active workflow for that request type and deactivates the previous definition.' : module.key === 'settings' ? 'Appearance preferences are stored but no styling is applied in this functional build.' : '' });
}
function directDocument(domain, row = {}, parent) {
  const existing = !!row.id; const values = { ...row, file_id:null, reason:'' };
  return formModal(existing ? `Direct ${domain === 'softcopy' ? 'revision' : 'update'}` : `Direct create ${domain}`, metadata.document_fields[domain], values, api, data => api.request('documents.direct', { domain, ...(existing?identity(row):{}), ...data }, true), { after:afterChange(parent), disabled:existing ? (domain === 'hardcopy' ? ['area_id','specific_id','asset_id','location_id','holder_id'] : ['document_number']) : [], explanation:domain === 'hardcopy' && existing ? 'Update metadata here. Use a transfer request to change physical location or holder.' : 'Direct changes require dedicated authorization and an audit reason. Softcopy changes always create a preserved revision.' });
}
function requestForm(existing = null, preset = {}, parent) {
  const allowedTypes = metadata.request_types || [];
  const initialType = existing?.type || preset.type || allowedTypes[0] || 'softcopy_create';
  let type = allowedTypes.includes(initialType) ? initialType : (allowedTypes[0] || 'softcopy_create');
  const fixedDomainFor = requestType => {
    if (requestType.startsWith('softcopy') || requestType === 'assignment') return 'softcopy';
    if (requestType.startsWith('hardcopy') || requestType === 'transfer') return 'hardcopy';
    return null;
  };
  const initialDomain = existing?.softcopy_id ? 'softcopy' : existing?.hardcopy_id ? 'hardcopy' : preset.domain;
  let domain = fixedDomainFor(type) || (['softcopy','hardcopy'].includes(initialDomain) ? initialDomain : 'softcopy');
  let target = existing?.softcopy_id || existing?.hardcopy_id || preset.document_id || null;
  let values = existing?.payload || preset.values || {}; let head, payload;
  const modalTitle = existing ? 'Edit request draft' : preset.type ? `${labelOf(type)} request` : 'New request';
  const modal = new Modal(modalTitle, { explanation:'Save a draft first. Then open it in My requests to submit. Request approval and document creation are separate operations.' });
  const heading = el('div'), body = el('div'); modal.body.append(heading,body);

  const syncDomain = (requestType, preferredDomain = domain) => {
    const fixed = fixedDomainFor(requestType);
    domain = fixed || (['softcopy','hardcopy'].includes(preferredDomain) ? preferredDomain : 'softcopy');
  };
  const renderPayload = async () => {
    payload?.dispose(); body.replaceChildren();
    payload = await mountFields(body, metadata.request_fields[type] || [], values, api, { disabled:existing?.type?.startsWith('softcopy') && type!=='softcopy_create' ? ['document_number'] : [] });
  };
  const renderHead = async () => {
    head?.dispose(); heading.replaceChildren(); syncDomain(type);
    const creates=type.endsWith('_create');
    const descriptors=[field('type','select',true,null,{label:'Request Type',options:allowedTypes})];
    if (['access','disposal'].includes(type)) descriptors.push(field('domain','select',true,null,{options:['softcopy','hardcopy']}));
    if (!creates) descriptors.push(field('document_id','lookup',true,domain,{label:domain==='softcopy'?'Softcopy Document':'Hardcopy Document'}));
    head=await mountFields(heading,descriptors,{type,domain,document_id:target},api,{disabled:existing?['type','domain','document_id']:[]});

    const typeControl=head.controls.get('type');
    typeControl.value=type;
    typeControl.addEventListener('change',event=>modal.run(async()=>{
      const nextType=event.currentTarget.value;
      if (!allowedTypes.includes(nextType) || nextType===type) return;
      type=nextType;
      syncDomain(type);
      target=null;
      values={};
      await renderHead();
      await renderPayload();
    }));

    head.controls.get('domain')?.addEventListener('change',event=>modal.run(async()=>{
      const nextDomain=event.currentTarget.value;
      domain=['softcopy','hardcopy'].includes(nextDomain)?nextDomain:'softcopy';
      target=null;
      values={};
      await renderHead();
      await renderPayload();
    }));

    head.controls.get('document_id')?.addEventListener('change',event=>modal.run(async()=>{
      target=Number(event.currentTarget.value)||null;
      if (target && ['softcopy_revise','hardcopy_update'].includes(type)) {
        const data=await api.request('detail',{module:domain,id:target});
        values={...data.row,file_id:null,reason:''};
        await renderPayload();
      }
    }));
  };
  modal.setSubmit('Save draft',async()=>{
    const first=await head.read(), data=await payload.read();
    type=first.type || type;
    syncDomain(type,first.domain || domain);
    const selected=type.endsWith('_create')?null:Number(first.document_id);
    const result=await api.request('requests.save',{...(existing?identity(existing):{}),type,softcopy_id:domain==='softcopy'?selected:null,hardcopy_id:domain==='hardcopy'?selected:null,payload:data},true);
    await afterChange(parent)(); modal.done(result);
    modal.body.append(button('Open saved request',()=>{modal.forceCloseAfterSuccess();return details('my_requests',result.id);}));
  });
  modal.run(async()=>{syncDomain(type,initialDomain);await renderHead();await renderPayload();}).then(()=>modal.focusFirst());
  modal.node.addEventListener('close',()=>{head?.dispose();payload?.dispose();}); return modal;
}
async function details(moduleKey, id) {
  const definition=metadata.modules.find(module=>module.key===moduleKey) || metadata.modules.find(module=>module.key==='requests');
  const modal=new Modal(`${definition?.label || labelOf(moduleKey)} #${id}`); modal.closeButton.textContent='Close';
  await modal.run(async()=>{
    const result=await api.request('detail',{module:moduleKey,id}); const row=result.row, related=result.related;
    const actions=el('section',{'aria-label':'Record actions'}); modal.body.append(actions);
    const change=afterChange(modal);
    const action=(title,operation,fixed,fields=[],options={})=>actions.append(button(title,()=>actionModal(title,api,operation,fixed,fields,{after:change,...options})));
    if (definition?.fields.length && (moduleKey==='workflows'?can('workflows.edit'):can(`${moduleKey}.edit`))) actions.append(button('Edit',()=>editCatalog(definition,row,modal)));
    if (can(`${moduleKey}.delete`) && definition?.fields.length && !['settings','workflows'].includes(moduleKey)) action(moduleKey==='users'?'Deactivate account':'Delete unused record','catalog.delete',{module:moduleKey,...identity(row)},[reason()],{explanation:moduleKey==='users'?'Deactivate this account without deleting its history?':'Only unused catalogue records can be deleted. Referenced records are protected.'});
    if (moduleKey==='users' && can('users.edit') && Number(row.id)!==Number(user.id)) action('Reset initial password','users.reset_password',identity(row),[reason()]);
    if (moduleKey==='roles' && can('roles.edit')) actions.append(button('Assign permissions',()=>permissionsModal(row,related,modal)));
    if (['softcopy','hardcopy'].includes(moduleKey)) {
      const domain=moduleKey, active=row.status==='active';
      if (active && can(`${domain}.direct`)) actions.append(button(domain==='softcopy'?'Direct revision':'Direct update',()=>directDocument(domain,row,modal)));
      if (active && can('requests.add')) {
        if (can('access.request')) actions.append(button('Request access',()=>requestForm(null,{type:'access',domain,document_id:row.id},modal)));
        if (related.can_read_files) for (const type of domain==='softcopy'?['softcopy_revise','softcopy_cancel','assignment','disposal']:['hardcopy_update','transfer','disposal']) {
          const cap=type.startsWith(domain)?`${domain}.request`:`${type}.request`;
          if (can(cap)) actions.append(button(labelOf(type)+' request',()=>requestForm(null,{type,domain,document_id:row.id,values:['softcopy_revise','hardcopy_update'].includes(type)?{...row,file_id:null,reason:''}:{}},modal)));
        }
      }
      if (active && related.can_read_files && can('files.upload')) action('Add attachment','files.attach',{domain,document_id:row.id},[field('file_id','upload'),reason()]);
      if (active && can('workflows.edit')) {
        const config=Object.fromEntries(Object.entries(related.approver_config?.config || {}).map(([key,value])=>[key,value.user_id]));
        actions.append(button('Configure document approvers',()=>formModal('Configure document approvers',[field('config','json'),reason()],{config},api,values=>api.request('documents.approvers',{domain,...identity(row),...values},true),{after:change,explanation:'Map workflow document-assignment keys to user IDs, for example {"plant_manager":2}. This affects new submissions, not existing snapshots.'})));
      }
      if (related.revisions) {
        modal.body.append(el('h3',{},'Revision history'),table(['id','revision_number','revision_status','document_title','effective_date','new_revision_level'],related.revisions,revision=>[
          ...(related.can_read_files?[button('Download revision',()=>downloadModal(revision.file_id,related.files?.find(file=>Number(file.id)===Number(revision.file_id))?.original_name || `revision-${revision.revision_number}`))]:[]),
          ...(related.can_read_files&&can('files.generate')?[button('Generate artifact',()=>actionModal('Generate revision artifact',api,'files.artifact',{revision_id:revision.id},[field('artifact_type','select',true,null,{options:active&&revision.revision_status==='current'?['controlled','uncontrolled']:['uncontrolled']})],{after:change}))]:[]),
          button('View revision metadata',()=>notice('Revision metadata',JSON.stringify(revision,null,2)))
        ]));
      }
      if (related.files) modal.body.append(el('h3',{},'Document files'),table(['id','original_name','purpose','status'],related.files,file=>button('File actions',()=>fileModal(file,change))));
    }
    if (['requests','my_requests','my_tasks'].includes(moduleKey)) {
      const own=Number(row.requested_by)===Number(user.id), pending=related.steps?.find(step=>step.status==='pending');
      if (own&&['draft','returned'].includes(row.status)) {
        if (can('requests.edit')) actions.append(button('Edit draft',()=>requestForm(row,{},modal)));
        if (can('requests.submit')) action('Submit request','requests.submit',identity(row),[],{explanation:'Submit this saved request to its published approval workflow?'});
      }
      if (can('requests.cancel')&&(own||can('requests.manage'))&&['draft','returned','pending'].includes(row.status)) action('Cancel request','requests.cancel',identity(row),[reason()]);
      if (pending&&can('requests.approve')&&!own&&pending.candidates?.some(candidate=>Number(candidate.id)===Number(user.id))) {
        for (const decision of ['approve','reject','return']) action(decision==='return'?'Return for correction':labelOf(decision),'requests.decide',{...identity(row),step_id:pending.id,decision},[comments()]);
        if (row.payload?.file_id) actions.append(button('Review submitted file',()=>downloadModal(row.payload.file_id,'submitted-document')));
      }
      if (pending&&can('workflows.reassign')) action('Reassign approver','workflows.reassign',{step_id:pending.id,version:row.version},[field('user_id','lookup',true,'users'),reason()]);
      if (related.transfer) actions.append(button('Open transfer',()=>details('transfers',related.transfer.id)));
    }
    if (moduleKey==='transfers') {
      const sender=Number(row.current_holder_id)===Number(user.id)||can('transfer.manage');
      if (row.status==='for_transfer'&&sender) {
        action('Record physical delivery','transfers.dispatch',identity(row),[comments()],{explanation:'Confirm the document has physically been delivered. This does not change its recorded location until the named recipient accepts.'});
        action('Cancel undispatched transfer','transfers.cancel',identity(row),[reason()]);
      }
      if (row.status==='pending_recipient_acceptance'&&Number(row.recipient_id)===Number(user.id)) for (const decision of ['accepted','refused']) action(decision==='accepted'?'Accept receipt':'Refuse receipt','transfers.receive',{...identity(row),decision},[comments()]);
    }
    if (moduleKey==='access'&&row.status==='access_granted'&&(Number(row.user_id)===Number(user.id)||can('access.revoke'))) action(Number(row.user_id)===Number(user.id)?'Return access':'Revoke access','access.revoke',identity(row),[reason()]);
    if (moduleKey==='assignments'&&Number(row.active)&&can('assignment.manage')) action('Remove assignment','assignments.remove',identity(row),[reason()]);
    if (moduleKey==='notifications'&&!row.read_at&&can('notifications.edit')) action('Mark as read','notifications.read',identity(row));
    if (moduleKey==='files') fileActions(actions,row,change);
    if (moduleKey==='workflows') {
      if (can('workflows.edit')) actions.append(button('New draft version',()=>workflowVersionModal(row,null,api,metadata.workflow_template,change)));
      modal.body.append(el('h3',{},'Workflow versions'),table(['id','version_number','status','published_at'],related.versions || [],version=>[
        button('View workflow graph',()=>notice('Workflow graph',JSON.stringify(version.graph,null,2))),
        ...(can('workflows.edit')?[button(version.status==='draft'?'Edit draft nodes':'Copy to new draft',()=>workflowVersionModal(row,version,api,metadata.workflow_template,change,version.status!=='draft'))]:[]),
        ...(can('workflows.edit')&&version.status==='draft'?[button('Publish version',()=>actionModal('Publish workflow version',api,'workflows.publish',identity(version),[reason()],{after:change,explanation:'Use this version for new submissions. Existing requests will not change.'}))]:[])
      ]));
    }
    modal.body.append(inspect(row,'Record metadata'));
    for (const [key,value] of Object.entries(related)) if (!['files','revisions','versions','available_permissions'].includes(key)) modal.body.append(inspect(value,labelOf(key)));
  });
  return modal;
}
function downloadModal(id,name) { return formModal('Download document',[],{},api,()=>api.download(id,name),{submitLabel:'Download',explanation:'Access is checked by the server at download time. A downloaded copy cannot be remotely recalled.'}); }
function fileActions(container,file,after) {
  container.append(button('Download file',()=>downloadModal(file.id,file.original_name)));
  if (file.purpose==='upload' && !file.document_id && Number(file.uploaded_by)===Number(user.id) && can('files.upload')) {
    container.append(button('Attach to a document',()=>formModal('Choose attachment domain',[field('domain','select',true,null,{options:['softcopy','hardcopy']})],{},api,values=>{
      formModal('Attach existing private file',[field('document_id','lookup',true,values.domain),reason()],{},api,data=>api.request('files.attach',{domain:values.domain,file_id:file.id,...data},true),{after});
      return {message:'Choose a document in the attachment dialog.'};
    },{closeOnSuccess:true})));
  }
  if (file.purpose==='attachment'&&file.status==='pending') for (const decision of ['approved','rejected','cancelled']) {
    if (can('files.approve')||(decision==='cancelled'&&Number(file.uploaded_by)===Number(user.id))) container.append(button(`${labelOf(decision)} attachment`,()=>actionModal(`${labelOf(decision)} attachment`,api,'files.decide',{...identity(file),decision},[reason()],{after})));
  }
}
function fileModal(file,after) { const modal=new Modal(`File #${file.id}`);modal.closeButton.textContent='Close';fileActions(modal.body,file,async()=>{modal.forceCloseAfterSuccess();await after?.();});modal.body.append(inspect(file));return modal; }
function permissionsModal(role,related,parent) {
  const modal=new Modal('Assign role permissions',{explanation:'Permissions are checked server-side. Your own recovery capabilities and the last active administrator are protected.'});
  const selected=new Set((related.permission_ids || []).map(Number)), boxes=[];
  const search=el('input',{type:'search','aria-label':'Filter permissions',placeholder:'Filter permissions'});modal.body.append(search);
  for (const permission of related.available_permissions || []) {
    const input=el('input',{type:'checkbox',checked:selected.has(Number(permission.id))});
    const label=el('label',{},input,`${permission.module_label} → ${permission.action_label}`); const row=el('p',{},label);
    boxes.push({input,row,permission});modal.body.append(row);
  }
  search.addEventListener('input',()=>{for(const item of boxes)item.row.hidden=!`${item.permission.module_label} ${item.permission.action_label}`.toLowerCase().includes(search.value.toLowerCase());});
  const reasonInput=el('textarea',{id:'permission-change-reason',name:'reason',required:true,rows:3,cols:36});
  modal.body.append(el('p',{},el('label',{htmlFor:'permission-change-reason'},'Reason'),el('br'),reasonInput));
  modal.setSubmit('Save permissions',async()=>{
    const result=await api.request('roles.permissions',{...identity(role),permission_ids:boxes.filter(item=>item.input.checked).map(item=>Number(item.permission.id)),reason:reasonInput.value},true);
    await afterChange(parent)();modal.done(result);
  });return modal;
}
boot();
