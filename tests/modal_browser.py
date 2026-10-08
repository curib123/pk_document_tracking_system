"""UI contract tests against the real JS and native dialog; API responses are simulated.
This suite does not stand in for the separate real-MySQL integration suite.
Run: python tests/modal_browser.py (requires playwright and Chromium).
"""
import json, os, pathlib, subprocess, threading, time, re
from http.server import ThreadingHTTPServer, SimpleHTTPRequestHandler
from urllib.parse import urlparse, parse_qs
from playwright.sync_api import sync_playwright, expect
ROOT = pathlib.Path(__file__).resolve().parents[1]
RENDER_PHP = "require 'application/bootstrap.php'; require 'application/helpers/ui_helper.php'; $initial_module=''; $page_title='PK Document Tracking System'; require 'application/views/templates/header.php'; require 'application/views/modules/index.php'; require 'application/views/templates/footer.php';"
class Handler(SimpleHTTPRequestHandler):
    def __init__(self, *args, **kwargs): super().__init__(*args, directory=str(ROOT / 'public'), **kwargs)
    def log_message(self, *args): pass
    def do_GET(self):
        if self.path == '/':
            content = subprocess.check_output(['php','-r',RENDER_PHP], cwd=ROOT)
            self.send_response(200); self.send_header('Content-Type','text/html; charset=utf-8'); self.end_headers(); self.wfile.write(content)
        else: super().do_GET()
server = ThreadingHTTPServer(('127.0.0.1', 0), Handler)
threading.Thread(target=server.serve_forever, daemon=True).start()
url = f'http://127.0.0.1:{server.server_port}'
metadata = json.loads(subprocess.check_output(['php','-r', "require 'application/bootstrap.php'; echo json_encode(['modules'=>array_values(Pk\\Core\\UiSchema::modules()),'document_fields'=>['softcopy'=>Pk\\Core\\UiSchema::documentFields('softcopy'),'hardcopy'=>Pk\\Core\\UiSchema::documentFields('hardcopy')],'request_fields'=>Pk\\Core\\UiSchema::requestFields(),'request_types'=>Request_service::TYPES,'workflow_template'=>Pk\\Core\\WorkflowGraph::defaults()]);"],cwd=ROOT))
permissions = [f'{module}.{action}' for module in ['users','roles','permissions','areas','specifics','assets','locations','categories','files','workflows','softcopy','hardcopy','requests','transfer','access','assignment','disposal','notifications','audit','sequences','settings','dashboard','documents'] for action in ['view','add','edit','delete','direct','approve','request','submit','cancel','reassign','upload','manage','access_all']]
user = {'id':1,'username':'admin','first_name':'Admin','last_name':'Test','position_title':'Admin','role_id':1,'require_password_change':0}
seen_paths=[]
endpoint_routes=json.loads(subprocess.check_output(['php','-r', "require 'application/bootstrap.php'; echo json_encode((new Endpoint_registry())->browserRoutes());"],cwd=ROOT))
state = {'user':None,'save_calls':0,'permission_payload':None,'workflow_payload':None,'workflow_delete_payload':None,'profile_payload':None,'direct_assignment_payload':None}
userrow={**user,'active':1,'version':1,'middle_name':None,'leader_id':None}
errors=[]
def route_api(request):
    q=parse_qs(urlparse(request['url']).query)
    data=json.loads(request['body']) if request['method']=='POST' else {k:v[0] for k,v in q.items()}
    path=urlparse(request['url']).path.split('/index.php/')[-1];seen_paths.append(path)
    key=next((key for key,value in endpoint_routes.items() if value==path),None)
    assert key is not None, f'Unexpected native endpoint: {path}'
    op,_,selector=key.partition('@')
    if selector:data['domain' if op=='documents.direct' else 'module']=selector
    status=200
    if op=='session': result={'user':state['user'],'permissions':permissions if state['user'] else []}
    elif op=='auth.login': state['user']=user; result={'user':user,'permissions':permissions}
    elif op=='auth.profile':
        state['profile_payload']=data
        state['user']={**state['user'],'username':data['username'],'first_name':data['first_name'],'middle_name':data.get('middle_name'),'last_name':data['last_name']}
        result={'user':state['user'],'message':'Profile updated.'}
    elif op=='metadata': result={**metadata,'user':user,'permissions':permissions}
    elif op=='dashboard': result={'softcopy':[], 'hardcopy':[], 'my_requests':[], 'unread_notifications':0,'pending_receipts':0}
    elif op=='list':
        rows=[userrow] if data.get('module')=='users' else ([{'id':1,'name':'Administrator','active':1,'version':1}] if data.get('module')=='roles' else ([{'id':7,'document_number':'DOC-001','title':'Quality Manual','status':'active','version':1}] if data.get('module')=='softcopy' else ([{'id':8,'title':'Controlled Hardcopy','sequence_number':'HC-001','status':'active','version':1}] if data.get('module')=='hardcopy' else ([{'id':9,'workflow_key':'test_flow','name':'Test Workflow','request_type':'access','active':1,'version':1}] if data.get('module')=='workflows' else []))))
        result={'rows':rows,'page':1,'pages':1,'limit':25,'total':len(rows)}
    elif op=='lookups':
        kind=data.get('kind')
        if kind in ['roles','users']:
            options=[{'id':1,'label':'Administrator'}]
        elif kind=='softcopy':
            options=[{'id':7,'label':'DOC-001 — Quality Manual'}]
        elif kind=='hardcopy':
            options=[{'id':8,'label':'Controlled Hardcopy'}]
        elif kind=='areas':
            options=[{'id':11,'label':'Admin Area'}]
        elif kind=='specifics':
            options=[{'id':12,'label':'Records Room','area_id':11,'area_label':'Admin Area'}]
        elif kind=='assets':
            options=[{'id':13,'label':'CAB-13','specific_id':12,'specific_label':'Records Room','area_id':11,'area_label':'Admin Area'}]
        elif kind=='locations':
            options=[{'id':14,'label':'LOC-14 — Shelf A → CAB-13 → Records Room → Admin Area','asset_id':13,'asset_label':'CAB-13','specific_id':12,'specific_label':'Records Room','area_id':11,'area_label':'Admin Area'}]
        else:
            options=[]
        result={'options':options,'more':False}
    elif op=='detail':
        if data.get('module')=='roles': result={'row':{'id':1,'name':'Administrator','active':1,'version':1},'related':{'permission_ids':[1],'available_permissions':[{'id':1,'module_label':'Users','action_label':'View'}]}}
        elif data.get('module')=='softcopy': result={'row':{'id':7,'document_number':'DOC-001','title':'Quality Manual','category_id':1,'status':'active','version':1,'created_by':7,'created_by_name':'Admin Test — Admin'},'related':{'can_read_files':True,'files':[],'revisions':[]}}
        elif data.get('module')=='hardcopy': result={'row':{'id':8,'title':'Controlled Hardcopy','status':'active','version':1},'related':{'can_read_files':True}}
        elif data.get('module')=='workflows': result={'row':{'id':9,'workflow_key':'test_flow','name':'Test Workflow','request_type':'access','active':1,'version':1,'graph':{'private':'do-not-render'}},'related':{'versions':[{'id':31,'workflow_id':9,'version_number':2,'status':'draft','is_default':0,'graph':{'steps':[{'name':'Administrator review','approver':{'type':'role','value':1,'label':'Administrator'}}]},'version':1,'published_at':None}]}}
        else: result={'row':userrow,'related':{}}
    elif op=='roles.permissions': state['permission_payload']=data; result={'message':'Permissions updated.'}
    elif op=='workflows.version': state['workflow_payload']=data; result={'id':21,'message':'Draft version saved.'}
    elif op=='workflows.delete_version': state['workflow_delete_payload']=data; result={'message':'Draft workflow version removed.'}
    elif op=='assignments.direct': state['direct_assignment_payload']=data; result={'id':41,'message':'Document assigned directly without a workflow request.'}
    elif op=='catalog.save':
        state['save_calls']+=1; status=422
        result=None
    else: result={'message':'Test operation recorded'}
    body={'ok':True,'data':result,'csrf':'a'*64} if status==200 else {'ok':False,'error':{'message':'Username already exists.','fields':{'username':'Choose another username.'}},'csrf':'a'*64}
    return {'status':status,'body':body}
try:
    with sync_playwright() as p:
        browser=p.chromium.launch(executable_path=os.environ.get('CHROMIUM_PATH','/usr/bin/chromium'),headless=True,args=['--no-sandbox'])
        page=browser.new_page(viewport={'width':1000,'height':800}); page.set_default_timeout(5000)
        page.on('pageerror',lambda error:errors.append(str(error)))
        page.expose_function('test_api',route_api)
        html=subprocess.check_output(['php','-r',RENDER_PHP],cwd=ROOT).decode()
        html=re.sub(r'<script[^>]*>.*?</script>','',html,flags=re.S)
        page.set_content(html)
        page.evaluate("() => { window.fetch = async (url, options={}) => { const r=await window.test_api({url:String(url),method:options.method||'GET',body:options.body||'{}'}); return new Response(JSON.stringify(r.body), {status:r.status,headers:{'Content-Type':'application/json'}}); }; }")
        blobs={}
        for name in ['api.js','components.js','forms.js','workflow-ui.js','app.js']:
            source=(ROOT/'public/assets/js'/name).read_text()
            for dependency,blob in blobs.items(): source=source.replace("'./"+dependency+"'",json.dumps(blob))
            blobs[name]=page.evaluate("source=>URL.createObjectURL(new Blob([source],{type:'text/javascript'}))",source)
        page.evaluate('url=>import(url)',blobs['app.js'])
        login=page.get_by_role('dialog',name='Sign in',exact=True)
        expect(login).to_be_visible()
        expect(login.get_by_label('Username',exact=True)).to_be_focused()
        login.get_by_label('Username',exact=True).fill('admin'); login.get_by_label('Password',exact=True).fill('test-password-long')
        login.get_by_role('button',name='Sign in',exact=True).click()
        expect(page.get_by_role('navigation').get_by_role('button',name='Users',exact=True)).to_be_visible()
        assert page.evaluate("""() => [...document.querySelectorAll('#navigation details')].some(group =>
            group.querySelector('summary')?.textContent.trim() === 'Document Setup'
            && [...group.querySelectorAll('button')].some(button => button.textContent.trim() === 'Sequences')
        )"""), 'Sequences must be grouped under Document Setup'

        # Every signed-in user can view/edit their own profile without admin user-management access.
        page.get_by_role('button',name='My profile',exact=True).click()
        profile=page.get_by_role('dialog',name='My profile',exact=True)
        expect(profile.get_by_label('Username',exact=True)).to_have_value('admin')
        expect(profile.get_by_label('First Name',exact=True)).to_have_value('Admin')
        expect(profile.get_by_label('Position Title',exact=True)).to_be_disabled()
        profile.get_by_label('First Name',exact=True).fill('Admin Updated')
        profile.get_by_role('button',name='Save',exact=True).click()
        expect(profile).not_to_be_visible()
        assert state['profile_payload']['first_name']=='Admin Updated'
        expect(page.locator('#account')).to_contain_text('Admin Updated')

        # Result modals are allowed to remain visible; close them before navigation.
        for dlg in page.get_by_role('dialog').all():
            if dlg.is_visible(): dlg.get_by_role('button',name='Close',exact=True).click()
        page.get_by_role('navigation').get_by_role('button',name='Users',exact=True).click()
        page.get_by_role('button',name='Add user',exact=True).click()
        modal=page.get_by_role('dialog',name='Add user',exact=True)
        expect(modal).to_be_visible()
        expect(modal).to_have_attribute('aria-busy','false')
        assert page.evaluate("document.querySelector('dialog[open]').matches(':modal')"), 'Must use showModal(), not just open=true'
        assert page.evaluate("document.querySelectorAll('style,link[rel=stylesheet],[style]').length")==0, 'No author styling is permitted'
        page.keyboard.press('Escape'); expect(modal).not_to_be_visible()
        expect(page.get_by_role('button',name='Add user',exact=True)).to_be_focused()
        page.get_by_role('button',name='Add user',exact=True).click(); modal=page.get_by_role('dialog',name='Add user',exact=True)
        modal.get_by_label('Username',exact=True).fill('existing-user')
        modal.get_by_label('First Name',exact=True).fill('Test'); modal.get_by_label('Last Name',exact=True).fill('User')
        modal.get_by_label('Position Title',exact=True).fill('Clerk'); modal.get_by_label('Role',exact=True).select_option('1')
        expect(modal.get_by_label('Role',exact=True).locator('option[value="1"]')).to_have_text('Administrator')
        assert '#1' not in modal.get_by_label('Role',exact=True).inner_text()
        modal.get_by_role('button',name='Save',exact=True).click()
        expect(modal.get_by_role('alert')).to_contain_text('Username already exists.')
        expect(modal.get_by_label('Username',exact=True)).to_have_value('existing-user')
        expect(modal.get_by_role('button',name='Save',exact=True)).to_be_enabled()
        assert state['save_calls']==1
        modal.get_by_role('button',name='Cancel',exact=True).click()
        assert not errors, errors
        # Remarks are optional; the action must submit even when left empty.
        page.get_by_role('navigation').get_by_role('button',name='Roles',exact=True).click()
        page.get_by_role('button',name='View / actions',exact=True).click()
        page.get_by_role('button',name='Assign permissions',exact=True).click()
        perm=page.get_by_role('dialog',name='Assign role permissions',exact=True)
        expect(perm.get_by_label('Users → View',exact=True)).to_be_visible()
        assert '#1' not in perm.inner_text()
        expect(perm.get_by_label('Remarks',exact=True)).to_be_visible()
        perm.get_by_role('button',name='Save permissions',exact=True).click()
        expect(perm.get_by_role('heading',name='Result',exact=True)).to_be_visible()
        assert state['permission_payload']['reason']==''
        assert state['permission_payload']['permission_ids']==[1]
        perm.get_by_role('button',name='Close',exact=True).click()
        # Hardcopy transfer action must never fall back to softcopy_create.
        page.get_by_role('navigation').get_by_role('button',name='Hardcopy documents',exact=True).click()
        assert 'Id' not in page.locator('#table-container thead').inner_text()
        assert page.evaluate("document.querySelector('#table-filters').compareDocumentPosition(document.querySelector('#table-container')) & Node.DOCUMENT_POSITION_FOLLOWING")
        assert page.evaluate("document.querySelector('#table-container').compareDocumentPosition(document.querySelector('#table-footer')) & Node.DOCUMENT_POSITION_FOLLOWING")
        assert page.locator('#table-footer').get_by_label('Rows per page',exact=True).is_visible()
        page.get_by_role('button',name='View / actions',exact=True).click()
        hard=page.get_by_role('dialog',name='Controlled Hardcopy',exact=True)
        hard.get_by_role('button',name='Transfer request',exact=True).click()
        transfer=page.get_by_role('dialog',name='Transfer request',exact=True)
        expect(transfer.get_by_label('Request Type',exact=True)).to_have_value('transfer')
        expect(transfer.get_by_label('Request Type',exact=True)).to_be_disabled()
        expect(transfer.get_by_label('Hardcopy Document',exact=True)).to_have_value('8')
        expect(transfer.get_by_label('Hardcopy Document',exact=True).locator('option[value="8"]')).to_have_text('Controlled Hardcopy')

        # Predefined physical hierarchy auto-populates upward.
        transfer.get_by_label('Location',exact=True).select_option('14')
        expect(transfer.get_by_label('Asset Number',exact=True)).to_have_value('13')
        expect(transfer.get_by_label('Specific Location',exact=True)).to_have_value('12')
        expect(transfer.get_by_label('Area',exact=True)).to_have_value('11')

        transfer.get_by_label('Asset Number',exact=True).select_option('13')
        expect(transfer.get_by_label('Specific Location',exact=True)).to_have_value('12')
        expect(transfer.get_by_label('Area',exact=True)).to_have_value('11')

        transfer.get_by_label('Specific Location',exact=True).select_option('12')
        expect(transfer.get_by_label('Area',exact=True)).to_have_value('11')

        assert '#8' not in transfer.inner_text()
        transfer.get_by_role('button',name='Cancel',exact=True).click()
        hard.get_by_role('button',name='Close',exact=True).click()

        # Specific request buttons must open with their own request type and target synchronized.
        page.get_by_role('navigation').get_by_role('button',name='Softcopy documents',exact=True).click()
        page.get_by_role('button',name='View / actions',exact=True).click()
        soft=page.get_by_role('dialog',name='DOC-001 — Quality Manual',exact=True)
        expect(soft.get_by_text('Created By:',exact=True)).to_be_visible()
        assert 'Admin Test — Admin' in soft.inner_text()
        assert 'Created By: 7' not in soft.inner_text()
        soft.get_by_role('button',name='Softcopy Revise request',exact=True).click()
        revise=page.get_by_role('dialog',name='Softcopy Revise request',exact=True)
        expect(revise.get_by_label('Request Type',exact=True)).to_have_value('softcopy_revise')
        expect(revise.get_by_label('Request Type',exact=True)).to_be_disabled()
        expect(revise.get_by_label('Softcopy Document',exact=True)).to_have_value('7')
        revise.get_by_role('button',name='Cancel',exact=True).click()
        soft.get_by_role('button',name='Request access',exact=True).click()
        access=page.get_by_role('dialog',name='Access request',exact=True)
        expect(access.get_by_label('Request Type',exact=True)).to_have_value('access')
        expect(access.get_by_label('Request Type',exact=True)).to_be_disabled()
        expect(access.get_by_label('Domain',exact=True)).to_have_value('softcopy')
        expect(access.get_by_label('Softcopy Document',exact=True)).to_have_value('7')
        access.get_by_role('button',name='Cancel',exact=True).click()
        soft.get_by_role('button',name='Close',exact=True).click()

        # Request pages must open a deterministic, locked request type instead of falling back to the first type.
        page.get_by_role('navigation').get_by_role('button',name='Hardcopy transfers',exact=True).click()
        expect(page.get_by_role('button',name='Direct hardcopy transfer',exact=True)).to_be_visible()
        page.get_by_role('button',name='Direct hardcopy transfer',exact=True).click()
        direct_transfer=page.get_by_role('dialog',name='Direct hardcopy transfer',exact=True)
        expect(direct_transfer.get_by_label('Hardcopy Document',exact=True)).to_be_visible()
        expect(direct_transfer.get_by_label('Location',exact=True)).to_be_visible()
        expect(direct_transfer.get_by_label('Recipient',exact=True)).to_be_visible()
        expect(direct_transfer.get_by_label('Remarks',exact=True)).to_be_visible()
        assert direct_transfer.get_by_label('Remarks',exact=True).get_attribute('required') is None
        assert direct_transfer.get_by_label('Request Type',exact=True).count()==0
        direct_transfer.get_by_role('button',name='Cancel',exact=True).click()

        page.get_by_role('button',name='Transfer request',exact=True).click()
        transfer_page_request=page.get_by_role('dialog',name='Transfer request',exact=True)
        expect(transfer_page_request.get_by_label('Request Type',exact=True)).to_have_value('transfer')
        expect(transfer_page_request.get_by_label('Request Type',exact=True)).to_be_disabled()
        transfer_page_request.get_by_role('button',name='Cancel',exact=True).click()

        page.get_by_role('navigation').get_by_role('button',name='Access grants',exact=True).click()
        expect(page.get_by_role('button',name='Direct grant access',exact=True)).to_be_visible()
        page.get_by_role('button',name='Direct grant access',exact=True).click()
        direct_access=page.get_by_role('dialog',name='Direct grant access',exact=True)
        expect(direct_access.get_by_label('Domain',exact=True)).to_have_value('softcopy')
        expect(direct_access.get_by_label('Softcopy Document',exact=True)).to_be_visible()
        expect(direct_access.get_by_label('Grant Access To',exact=True)).to_be_visible()
        expect(direct_access.get_by_label('Expiration Date',exact=True)).to_be_visible()
        assert direct_access.get_by_label('Remarks',exact=True).get_attribute('required') is None
        assert direct_access.get_by_label('Request Type',exact=True).count()==0
        direct_access.get_by_role('button',name='Cancel',exact=True).click()

        page.get_by_role('button',name='Access request',exact=True).click()
        access_page_request=page.get_by_role('dialog',name='Access request',exact=True)
        expect(access_page_request.get_by_label('Request Type',exact=True)).to_have_value('access')
        expect(access_page_request.get_by_label('Request Type',exact=True)).to_be_disabled()
        access_page_request.get_by_role('button',name='Cancel',exact=True).click()

        page.get_by_role('navigation').get_by_role('button',name='Assigned documents',exact=True).click()
        expect(page.get_by_role('button',name='Direct assign document',exact=True)).to_be_visible()
        expect(page.get_by_role('button',name='Assignment request',exact=True)).to_be_visible()

        page.get_by_role('button',name='Direct assign document',exact=True).click()
        direct_assign=page.get_by_role('dialog',name='Direct assign document',exact=True)
        expect(direct_assign.get_by_label('Softcopy Document',exact=True)).to_be_visible()
        expect(direct_assign.get_by_label('Assign To',exact=True)).to_be_visible()
        assert direct_assign.get_by_label('Remarks',exact=True).get_attribute('required') is None
        assert direct_assign.get_by_label('Request Type',exact=True).count()==0
        direct_assign.get_by_role('button',name='Cancel',exact=True).click()

        page.get_by_role('button',name='Assignment request',exact=True).click()
        assignment_request=page.get_by_role('dialog',name='Assignment request',exact=True)
        expect(assignment_request.get_by_label('Request Type',exact=True)).to_have_value('assignment')
        expect(assignment_request.get_by_label('Request Type',exact=True)).to_be_disabled()
        assignment_request.get_by_role('button',name='Cancel',exact=True).click()

        page.get_by_role('navigation').get_by_role('button',name='Disposal records',exact=True).click()
        expect(page.get_by_role('button',name='Direct disposal',exact=True)).to_be_visible()
        page.get_by_role('button',name='Direct disposal',exact=True).click()
        direct_disposal=page.get_by_role('dialog',name='Direct disposal',exact=True)
        expect(direct_disposal.get_by_label('Domain',exact=True)).to_have_value('softcopy')
        expect(direct_disposal.get_by_label('Softcopy Document',exact=True)).to_be_visible()
        expect(direct_disposal.get_by_label('Disposal Action',exact=True)).to_be_visible()
        assert direct_disposal.get_by_label('Remarks',exact=True).get_attribute('required') is None
        assert direct_disposal.get_by_label('Request Type',exact=True).count()==0
        direct_disposal.get_by_role('button',name='Cancel',exact=True).click()

        page.get_by_role('button',name='Disposal request',exact=True).click()
        disposal_request=page.get_by_role('dialog',name='Disposal request',exact=True)
        expect(disposal_request.get_by_label('Request Type',exact=True)).to_have_value('disposal')
        expect(disposal_request.get_by_label('Request Type',exact=True)).to_be_disabled()
        disposal_request.get_by_role('button',name='Cancel',exact=True).click()

        # Workflow drafts use ordered add/remove steps and supported dynamic approvers.
        page.get_by_role('navigation').get_by_role('button',name='Workflow builder',exact=True).click()
        page.get_by_role('button',name='View / actions',exact=True).click()
        workflow=page.get_by_role('dialog',name='Test Workflow',exact=True)
        expect(workflow.get_by_role('button',name='Remove draft version',exact=True)).to_be_visible()
        assert workflow.locator('pre').count()==0, 'Raw JSON metadata must not be rendered'
        assert 'Record metadata' not in workflow.inner_text()
        assert 'do-not-render' not in workflow.inner_text()
        workflow.get_by_role('button',name='New draft version',exact=True).click()
        draft=page.get_by_role('dialog',name='New workflow version',exact=True)
        draft.get_by_role('button',name='Add approval step',exact=True).click()
        step=page.get_by_role('dialog',name='Add approval step',exact=True)
        step.get_by_label('Step Name',exact=True).fill('Requester confirmation')
        step.get_by_label('Who Approves',exact=True).select_option('requester')
        step.get_by_role('button',name='Save step',exact=True).click()
        expect(draft.get_by_role('cell',name='Requester confirmation',exact=True)).to_be_visible()
        draft.get_by_role('button',name='Remove',exact=True).click()
        expect(draft.get_by_text('This draft has no approval steps yet.')).to_be_visible()
        draft.get_by_role('button',name='Add approval step',exact=True).click()
        step=page.get_by_role('dialog',name='Add approval step',exact=True)
        step.get_by_label('Step Name',exact=True).fill('Requester confirmation')
        step.get_by_label('Who Approves',exact=True).select_option('requester')
        step.get_by_role('button',name='Save step',exact=True).click()
        draft.get_by_role('button',name='Save draft version',exact=True).click()
        assert state['workflow_payload']['graph']['steps']==[{'name':'Requester confirmation','approver':{'type':'requester'}}]
        draft.get_by_role('button',name='Close',exact=True).click()
        # Parent workflow details closes automatically after the draft save refresh.

        page.get_by_role('navigation').get_by_role('button',name='Workflow builder',exact=True).click()
        page.get_by_role('button',name='View / actions',exact=True).click()
        workflow=page.get_by_role('dialog',name='Test Workflow',exact=True)
        workflow.get_by_role('button',name='Remove draft version',exact=True).click()
        remove_draft=page.get_by_role('dialog',name='Remove draft workflow version',exact=True)
        assert remove_draft.get_by_label('Remarks',exact=True).get_attribute('required') is None
        remove_draft.get_by_role('button',name='Confirm',exact=True).click()
        assert state['workflow_delete_payload']['id']==31
        assert state['workflow_delete_payload']['reason'] is None
        remove_draft.get_by_role('button',name='Close',exact=True).click()

        # All visible navigation modules must render without runtime failures.
        for module in metadata['modules']:
            if module.get('navigation_hidden'):
                continue
            page.get_by_role('navigation').get_by_role('button',name=module['label'],exact=True).click()
            expect(page.locator('main h2')).to_have_text(module['label'])
        assert not errors, errors
        assert 'auth/login' in seen_paths and 'users/save' in seen_paths and 'roles/permissions' in seen_paths
        page.screenshot(path=str(ROOT/'tests/modal-browser.png'),full_page=True)
        browser.close()
        print('PASS: native dialog, hardcopy transfer preset synchronization, predefined location hierarchy auto-population, table controls below results, ordered workflow step add/remove/save, removable workflow drafts, no raw JSON metadata, human-readable lookup labels without IDs, no CSS, modal login, Escape/focus, failed-save recovery, one submission, self profile, contextual request presets, permission-controlled direct transfer/access/assignment/disposal, sequences under Document Setup, readable created-by values, optional remarks, all modules, no JS runtime errors')
finally: server.shutdown()
