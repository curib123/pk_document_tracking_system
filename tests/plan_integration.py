#!/usr/bin/env python3
"""Plan.md HTTP/SQL regressions. Requires disposable fixtures from smoke.sh only."""
from __future__ import annotations
import html
import http.cookiejar
import json
import os
from pathlib import Path
import re
import subprocess
import urllib.error
import urllib.parse
import urllib.request

if os.environ.get('PK_TEST_DB') != '1' or os.environ.get('DB_DATABASE') != 'pk_dts_test':
    raise SystemExit('Refusing to test outside the explicitly selected disposable pk_dts_test database.')
BASE='http://127.0.0.1:8089/'
RESULTS=[]

def db(sql: str) -> str:
    result=subprocess.run(['mysql','-N','-s','-h127.0.0.1','-uroot','-prootpass','pk_dts_test','-e',sql],
                          text=True,capture_output=True)
    if result.returncode: raise AssertionError("Fixture SQL failed: "+result.stderr.strip())
    return result.stdout.strip()

def expect(condition: bool, message: str) -> None:
    if not condition: raise AssertionError(message)
    RESULTS.append(message)
    print('PASS:',message,flush=True)

class Session:
    def __init__(self):
        self.cookies=http.cookiejar.CookieJar()
        self.opener=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(self.cookies))
    def request(self,path: str,data=None):
        body=urllib.parse.urlencode(data,doseq=True).encode() if data is not None else None
        request=urllib.request.Request(BASE+path,data=body)
        try: response=self.opener.open(request,timeout=20)
        except urllib.error.HTTPError as error: response=error
        text=response.read().decode('utf-8','replace')
        if response.code>=500: raise AssertionError(f'{path}: HTTP {response.code}: {text[:1500]}')
        expect_no_php_error(text,path)
        return response.code,text,response.headers,response.url
    def get(self,path): return self.request(path)
    def post(self,page,path,fields):
        code,text,_,_=self.get(page)
        token=re.search(r'name="pk_csrf_token"\s+value="([^"]+)"',text)
        if not token: raise AssertionError(f'CSRF token missing on {page}: HTTP {code}')
        return self.request(path,{'pk_csrf_token':token.group(1),'confirmed':'yes',**fields})
    def upload(self,document_id,level,content=b'Controlled plan regression file.\n'):
        _,text,_,_=self.get('documents/softcopy')
        token=re.search(r'name="pk_csrf_token"\s+value="([^"]+)"',text).group(1)
        boundary='pk-plan-upload-boundary'
        fields={'pk_csrf_token':token,'confirmed':'yes','document_id':document_id,
                'new_revision_level':level,'page_number':1,'reason':'Plan upload audit test'}
        parts=[]
        for key,value in fields.items():
            parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="{key}"\r\n\r\n{value}\r\n'.encode())
        parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="attachment"; filename="plan.txt"\r\nContent-Type: text/plain\r\n\r\n'.encode()+content+b'\r\n')
        parts.append(f'--{boundary}--\r\n'.encode())
        request=urllib.request.Request(BASE+'files/upload',data=b''.join(parts),
            headers={'Content-Type':'multipart/form-data; boundary='+boundary})
        try: response=self.opener.open(request,timeout=20)
        except urllib.error.HTTPError as error: response=error
        return response.code,response.read().decode()
    def login(self,username,password):
        return self.post('login','login',{'login':username,'password':password})

def expect_no_php_error(text,path):
    for marker in ['A PHP Error was encountered','Severity: Warning','Fatal error:','Uncaught Exception']:
        if marker in text: raise AssertionError(f'Runtime error in {path}: {marker}')

def record_ids(text):
    return re.findall(r'data-record-id="(\d+)"',text)

def chart_data(text,canvas_id):
    tag=re.search(r'<canvas[^>]*\bid="'+canvas_id+r'"[^>]*>',text)
    if not tag: raise AssertionError('Chart canvas missing: '+canvas_id)
    data=re.search(r'data-chart-counts="([^"]*)"',tag.group())
    return json.loads(html.unescape(data.group(1)))

admin=Session();admin.login('test_admin','TemporaryTestPassword2026!')
admin_id=int(db("SELECT id FROM users WHERE username='test_admin'"))
staff_role=int(db("SELECT id FROM roles WHERE name='Staff'"))
admin_role=int(db("SELECT id FROM roles WHERE name='Administrator'"))
password_hash=db("SELECT password_hash FROM users WHERE id="+str(admin_id))

# First-login setup is generated, persisted server-side and cannot be bypassed.
code,body,headers,_=admin.post('admin/users','admin/users/save',{
    'username':'plan_first_login','first_name':'Plan','last_name':'First Login',
    'position_title':'Verification only','role_id':staff_role,'active':'1'})
match=re.search(r'id="temporaryPassword">([^<]+)',body)
expect(code==200 and match is not None,'Administrator receives a generated temporary credential only in the creation response')
temporary=html.unescape(match.group(1));uid=int(db("SELECT id FROM users WHERE username='plan_first_login'"))
expect(len(temporary)>=20 and db(f'SELECT require_password_change FROM users WHERE id={uid}')=='1',
       'New account has a random temporary password and persistent first-login requirement')
expect('no-store' in headers.get('Cache-Control',''),'Temporary credential response is non-cacheable')
expect(temporary not in db(f'SELECT password_hash FROM users WHERE id={uid}'),'Temporary password is stored as a hash, not plaintext')
first=Session();code,body,_,url=first.login('plan_first_login',temporary)
expect(url.endswith('/change-password') and 'requiredPasswordModal' in body,'First login opens the mandatory password modal')
for path in ['dashboard','documents/softcopy','my-tasks/softcopy','admin/users','files/download/1']:
    code,body,_,url=first.get(path)
    expect(url.endswith('/change-password') and 'requestChart' not in body,
           'First-login gate blocks protected route '+path)
for fields,label in [({'current_password':temporary,'new_password':temporary,'confirm_password':temporary},'Temporary password reuse'),
                     ({'current_password':temporary,'new_password':'PermanentPlanPassword2026!','confirm_password':'Mismatch'},'Mismatched confirmation')]:
    first.post('change-password','change-password',fields)
    expect(db(f'SELECT require_password_change FROM users WHERE id={uid}')=='1',label+' is rejected')
first.post('change-password','change-password',{'current_password':temporary,'new_password':'PermanentPlanPassword2026!',
                                              'confirm_password':'PermanentPlanPassword2026!'})
expect(db(f'SELECT require_password_change FROM users WHERE id={uid}')=='0','Successful password change releases the server-side gate')
old=Session();_,body,_,url=old.login('plan_first_login',temporary)
expect(url.endswith('/login'),'The temporary password is invalid after replacement')
expect('requiredPasswordModal' not in first.get('dashboard')[1],'Permanent-password session can access its authorized dashboard')

# A scoped staff register cannot leak someone else's records, files or analytics.
category=int(db('SELECT id FROM categories WHERE active=1 ORDER BY id LIMIT 1'))
db(f"INSERT INTO softcopy_documents(document_number,title,category_id,created_by,creation_source,creation_reason) VALUES "
   f"('PLAN-PRIVATE','Plan Restricted Secret',{category},{admin_id},'direct','Test fixture'),('PLAN-OWN','Plan Own Record',{category},{uid},'direct','Test fixture')")
private_id=int(db("SELECT id FROM softcopy_documents WHERE document_number='PLAN-PRIVATE'"))
own_id=int(db("SELECT id FROM softcopy_documents WHERE document_number='PLAN-OWN'"))
_,body,_,_=first.get('documents/softcopy?q=Plan')
expect('Plan Own Record' in body and 'Plan Restricted Secret' not in body,'Scoped register hides unrelated document metadata and action payloads')
_,dashboard,_,_=first.get('dashboard');counts=chart_data(dashboard,'documentTypeChart')
expect(sum(counts)==1,'Dashboard pie chart uses only the account\'s authorized document scope')
_,catalog,_,_=first.get('my-requests/access-grant')
expect('Plan Restricted Secret' in catalog,'Request catalog exposes permitted discovery choices without granting register access')
before=db('SELECT COUNT(*) FROM requests')
first.post('my-requests/softcopy','my-requests/softcopy/save',{'type':'softcopy_cancel','subject':'Unauthorized change','softcopy_id':private_id})
expect(db('SELECT COUNT(*) FROM requests')==before,'A forged document request cannot target an unrelated protected document')

# Membership scopes, expiry/revocation and writable versus readable permissions.
db(f"INSERT INTO assignments(softcopy_id,user_id,assigned_by,active) VALUES ({private_id},{uid},{admin_id},1)")
expect('Plan Restricted Secret' in first.get('documents/softcopy?q=Plan')[1],'Active assignment exposes the assigned document')
db(f'UPDATE assignments SET active=0 WHERE softcopy_id={private_id} AND user_id={uid}')
db(f"INSERT INTO access_grants(domain,document_id,user_id,granted_by,expires_at,reason) "
   f"VALUES ('softcopy',{private_id},{uid},{admin_id},'2099-12-31 23:59:59','Test grant')")
expect('Plan Restricted Secret' in first.get('documents/softcopy?q=Plan')[1],'An unexpired active grant exposes permitted metadata')
db(f"UPDATE access_grants SET expires_at='2000-01-01 00:00:00' WHERE document_id={private_id} AND user_id={uid}")
expect('Plan Restricted Secret' not in first.get('documents/softcopy?q=Plan')[1],'Expired grants no longer expose document metadata')

# Search, folder context, layout switching, dates, sort and detailed views share records.
query=f'documents/softcopy?folder=category:{category}&q=Plan&sort=title&dir=desc&limit=10'
_,table,_,_=admin.get(query+'&layout=table')
_,grid,_,_=admin.get(query+'&layout=grid')
expect(record_ids(table)==record_ids(grid) and str(private_id) in record_ids(table),
       'Folder/table and folder/cards use identical filtered and sorted record IDs')
expect('Document Identity' in table and 'Workflow and Approval History' in table and 'Document Audit' in table,
       'Document view payload groups identity, revision, related workflow and authorized audit details')
expect('Document Management Workspace' not in table and 'topbar-context' in table,'Top navigation uses concise module/page labels')
expect(admin.get('documents/softcopy?from=2026-13-99')[0]==400,'Invalid date filters produce a controlled validation response')
expect(admin.get('documents/softcopy?from=2099-01-01&to=2000-01-01')[0]==400,'Reversed date ranges are rejected')
expect(not record_ids(admin.get(query+'&from=2099-01-01')[1]),'Date filters constrain the actual database records')

# Assignment register uses the shared filters and pagination instead of a silent 100-row cap.
_,assignments,_,_=admin.get('admin/document-assignments?domain=hardcopy&q=Folder&sort=title&dir=asc&limit=10')
expect('tableFilter' in assignments and 'Folder Hardcopy Alpha' in assignments,
       'Administrative assignments expose shared search, sorting and pagination')
expect(not record_ids(admin.get('admin/document-assignments?domain=hardcopy&q=NoSuchPlanRecord')[1]),
       'Assignment search filters database rows rather than only hiding rendered cells')
expected_drafts=set(db("SELECT DISTINCT workflow_id FROM workflow_versions WHERE status='draft'").splitlines())
expect(set(record_ids(admin.get('admin/workflows?publication=draft')[1]))==expected_drafts,
       'Workflow publication filter returns exactly the workflows with matching versions')

# Backend defenses do not depend on button visibility.
expect(first.get('admin/users')[0]==403,'Staff cannot open user administration directly')
code,_,_,_=first.post('dashboard','admin/users/save',{'username':'plan_escalate','first_name':'Bad','last_name':'Actor',
    'position_title':'Test','role_id':admin_role,'active':'1'})
expect(code==403 and db("SELECT COUNT(*) FROM users WHERE username='plan_escalate'")=='0',
       'Direct POST cannot bypass user-management permissions')
db("INSERT INTO roles(name,active) VALUES ('Plan Delegated Admin',1)")
delegated_role=int(db("SELECT id FROM roles WHERE name='Plan Delegated Admin'"))
db(f"INSERT INTO role_permissions(role_id,permission_id) SELECT {delegated_role},id FROM permissions WHERE "
   "(module_key='dashboard' AND action_key='view') OR (module_key='users' AND action_key IN ('view','add','edit')) "
   "OR (module_key='roles' AND action_key IN ('view','add','edit'))")
db(f"INSERT INTO users(username,first_name,last_name,position_title,role_id,password_hash,require_password_change) "
   f"VALUES ('plan_delegate','Plan','Delegate','Verification',{delegated_role},'{password_hash}',0)")
delegate=Session();delegate.login('plan_delegate','TemporaryTestPassword2026!')
delegate.post('admin/users','admin/users/save',{'username':'plan_admin_escalation','first_name':'No','last_name':'Privilege',
                                             'position_title':'Test','role_id':admin_role,'active':'1'})
expect(db("SELECT COUNT(*) FROM users WHERE username='plan_admin_escalation'")=='0','Delegated user manager cannot assign Administrator or stronger privileges')
strong_permission=int(db("SELECT id FROM permissions WHERE module_key='documents' AND action_key='access_all'"))
delegate.post('admin/roles','admin/roles/save',{'name':'Plan Escalated Role','active':'1','permissions[]':[strong_permission]})
expect(db("SELECT COUNT(*) FROM roles WHERE name='Plan Escalated Role'")=='0','Delegated role manager cannot grant permissions they do not hold')

# Immutable request type and stale edit protection.
first.post('my-requests/softcopy','my-requests/softcopy/save',{'type':'softcopy_cancel','subject':'Plan Draft','softcopy_id':own_id})
rid=int(db(f"SELECT id FROM requests WHERE requested_by={uid} AND type='softcopy_cancel' ORDER BY id DESC LIMIT 1"))
version=int(db(f'SELECT version FROM requests WHERE id={rid}'))
first.post('my-requests/softcopy','my-requests/softcopy/save',{'id':rid,'version':version,'type':'softcopy_create',
                                                        'subject':'Changed type','title':'Wrong','category_id':category})
expect(db(f"SELECT type FROM requests WHERE id={rid}")=='softcopy_cancel','A saved request cannot be changed to a different action type')
first.post('my-requests/softcopy','my-requests/softcopy/save',{'id':rid,'version':version-1,'type':'softcopy_cancel',
                                                        'subject':'Stale edit','softcopy_id':own_id})
expect(db(f"SELECT JSON_UNQUOTE(JSON_EXTRACT(payload,'$.subject')) FROM requests WHERE id={rid}")=='Plan Draft','Stale request edits are rejected')
first.post('my-requests/softcopy','my-requests/softcopy/submit',{'id':rid})
expect(db(f'SELECT status FROM requests WHERE id={rid}')=='submitted','A valid scoped request uses the published workflow')
first.post('my-requests/softcopy','my-requests/softcopy/save',{'id':rid,'version':version,'type':'softcopy_cancel',
                                                        'subject':'Post-submit edit','softcopy_id':own_id})
expect(db(f"SELECT JSON_UNQUOTE(JSON_EXTRACT(payload,'$.subject')) FROM requests WHERE id={rid}")=='Plan Draft','Submitted request payload cannot be overwritten by a stale form')

# Publication/version sequencing and conditional step editing.
workflow=int(db("SELECT id FROM workflows WHERE request_type='softcopy_create' AND active=1"))
admin.post('admin/workflows','admin/workflows/clone',{'id':workflow})
draft=int(db(f"SELECT id FROM workflow_versions WHERE workflow_id={workflow} AND status='draft'"))
admin.post('admin/workflows','admin/workflows/clone',{'id':workflow})
expect(db(f"SELECT COUNT(*) FROM workflow_versions WHERE workflow_id={workflow} AND status='draft'")=='1','Duplicate editable workflow drafts are prevented')
original_graph=db(f'SELECT graph FROM workflow_versions WHERE id={draft}')
admin.post('admin/workflows','admin/workflows/step/save',{'workflow_version_id':draft,'graph_hash':'stale-hash',
    'name':'Stale workflow step','approver_type':'requester'})
expect(db(f'SELECT graph FROM workflow_versions WHERE id={draft}')==original_graph,'Stale workflow editor tabs cannot overwrite a changed route')
_,body,_,_=admin.get('admin/workflows')
expect('Step Actions' in body and 'Available for Request Approval' in body and 'Published and Draft Versions' in body,
       'Workflow Steps Modal distinguishes draft, published and current approval routes')

# Category cycles and valid filtered Places listings.
db(f"INSERT INTO categories(name,folder_name,created_by) VALUES ('Plan Parent','plan-parent',{admin_id})")
parent=int(db("SELECT id FROM categories WHERE folder_name='plan-parent'"))
db(f"INSERT INTO categories(name,folder_name,parent_id,created_by) VALUES ('Plan Child','plan-child',{parent},{admin_id})")
child=int(db("SELECT id FROM categories WHERE folder_name='plan-child'"))
admin.post('places/softcopy-categories','places/softcopy-categories/save',{'id':parent,'name':'Plan Parent',
    'folder_name':'plan-parent','parent_id':child,'active':'1'})
expect(db(f'SELECT IFNULL(parent_id,0) FROM categories WHERE id={parent}')=='0','Category cannot be moved below its own descendant')
_,body,_,_=admin.get(f'places/softcopy-categories?parent={parent}&sort=name&dir=desc')
expect(record_ids(body)==[str(child)],'Places parent filter and sorting operate on the real hierarchy')

# Assigned approvers must be able to discover their tasks without broad management grants.
db("INSERT INTO roles(name,active) VALUES ('Plan Sole Approver',1)")
approver_role=int(db("SELECT id FROM roles WHERE name='Plan Sole Approver'"))
db(f"INSERT INTO role_permissions(role_id,permission_id) SELECT {approver_role},id FROM permissions WHERE module_key='dashboard' AND action_key='view'")
db(f"INSERT INTO users(username,first_name,last_name,position_title,role_id,password_hash,require_password_change) VALUES ('plan_approver','Plan','Approver','Verifier',{approver_role},'{password_hash}',0)")
approver_id=int(db("SELECT id FROM users WHERE username='plan_approver'"))
db(f"UPDATE workflow_steps SET assigned_user_id={approver_id},assignment=JSON_OBJECT('type','user','value',{approver_id}) WHERE request_id={rid} AND status='active'")
approver=Session();approver.login('plan_approver','TemporaryTestPassword2026!')
expect('href="'+BASE+'my-tasks/softcopy"' in approver.get('dashboard')[1],
       'A specifically assigned approver can discover My Tasks without broad request permissions')
expect('Plan Draft' in approver.get('my-tasks/softcopy')[1], 'Assigned approver sees the correct scoped request and its readable proposal')
first.post('dashboard','my-tasks/softcopy/decide',{'id':rid,'decision':'approved'})
expect(db(f'SELECT status FROM requests WHERE id={rid}')=='submitted','The request owner cannot approve a step assigned to another account')

# The legacy Upload Revision button shares the same locked domain effects as Direct.
admin.upload(private_id,'02')
expect(db(f"SELECT COUNT(*) FROM status_history WHERE domain='softcopy' AND document_id={private_id} AND action='direct_revise'")=='1',
       'Upload Revision records the same document audit as the direct-revision workflow')
files_before=db('SELECT COUNT(*) FROM files');revisions_before=db(f'SELECT COUNT(*) FROM softcopy_revisions WHERE document_id={private_id}')
admin.upload(private_id,'02')
expect(db('SELECT COUNT(*) FROM files')==files_before and db(f'SELECT COUNT(*) FROM softcopy_revisions WHERE document_id={private_id}')==revisions_before,
       'Repeated revision level is rejected without orphaned staged files or duplicate revisions')
file_id=db(f'SELECT file_id FROM softcopy_revisions WHERE document_id={private_id} ORDER BY id DESC LIMIT 1')
expect(first.get('files/download/'+file_id)[0]==403,'Private file bytes remain blocked without a current assignment or grant')
db(f"UPDATE access_grants SET expires_at='2099-12-31 23:59:59',revoked_at=NULL WHERE document_id={private_id} AND user_id={uid}")
expect(first.get('files/download/'+file_id)[0]==200,'Current authorized grant permits approved file bytes')
db(f"UPDATE access_grants SET revoked_at=NOW() WHERE document_id={private_id} AND user_id={uid}")
expect(first.get('files/download/'+file_id)[0]==403,'Revoked grants cannot download previously visible file bytes')

# Neither generated credentials nor hashes should enter operational JSON audit logs.
root=Path(__file__).resolve().parents[1]
for path in (root/'storage').rglob('*.json*'):
    if path.is_file():
        text=path.read_text(errors='replace')
        expect(temporary not in text and 'PermanentPlanPassword2026!' not in text,'Audit file excludes generated and permanent password plaintext: '+path.name)

out=Path(os.environ.get('PK_TEST_RESULTS','/tmp/pk-plan-integration-results.json'))
out.write_text(json.dumps({'passed':len(RESULTS),'checks':RESULTS},indent=2))
print(f'Plan.md integration checks passed: {len(RESULTS)}',flush=True)
