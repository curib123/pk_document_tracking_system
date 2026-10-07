"""Exercise the native CI3 controller/service/model boundary against a disposable DB."""
import http.cookiejar, json, os, secrets, urllib.error, urllib.parse, urllib.request

if os.environ.get('PK_TEST_DB') != '1' or not os.environ.get('DB_DATABASE', '').endswith('_test'):
    raise SystemExit('This suite requires PK_TEST_DB=1 and a dedicated *_test database.')
BASE=os.environ['APP_URL'].rstrip('/')+'/index.php/'
changed_password=os.environ['CI_ROUTE_PASSWORD']
checks=0
class Client:
    def __init__(self):
        self.http=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
        self.token=''
    def call(self,path,body=None,query=None,status=200,csrf=True):
        global checks
        url=BASE+path+('?' + urllib.parse.urlencode(query,doseq=True) if query else '')
        headers={'Accept':'application/json'}
        if body is not None:
            headers['Content-Type']='application/json'
            if csrf: headers['X-CSRF-Token']=self.token
        req=urllib.request.Request(url,data=json.dumps(body).encode() if body is not None else None,headers=headers)
        try: response=self.http.open(req,timeout=20)
        except urllib.error.HTTPError as error: response=error
        raw=response.read()
        assert response.status==status,(path,response.status,raw[:500])
        data=json.loads(raw)
        self.token=data.get('csrf',self.token)
        assert data['ok']==(status==200),(path,data)
        checks+=1
        return data.get('data',data.get('error'))

admin=Client()
assert admin.call('auth/session')['user'] is None
admin.call('users/datatable',status=401)
admin.call('areas/save',status=405)
admin.call('auth/login',{'username':'admin','password':os.environ['PK_ADMIN_PASSWORD']},status=419,csrf=False)
admin.call('auth/login',{'username':'admin','password':os.environ['PK_ADMIN_PASSWORD']})
admin.call('dashboard/metadata',status=423)
admin.call('auth/password',{'current_password':os.environ['PK_ADMIN_PASSWORD'],'new_password':changed_password,'confirm_password':changed_password})
meta=admin.call('dashboard/metadata')
assert len(meta['modules'])==24
for module in meta['modules']:
    listing=admin.call(module['key']+'/datatable',query={'draw':3,'start':0,'length':10})
    assert listing['draw']==3 and listing['limit']==10
    assert listing['data']==listing['rows'] and listing['recordsTotal']>=listing['recordsFiltered']
name='Architecture '+secrets.token_hex(4)
# The route fixes its module; a payload selector cannot redirect the operation.
area=admin.call('areas/save',{'module':'roles','name':name,'active':1})
detail=admin.call('areas/view/'+str(area['id']))['row']
assert detail['name']==name
admin.call('areas/save',{'id':area['id'],'version':detail['version'],'name':name+' revised','active':1})
admin.call('areas/save',{'id':area['id'],'version':detail['version'],'name':'Stale','active':1},status=409)
admin.call('specifics/save',{'name':'Model boundary room','area_id':area['id'],'active':1})
admin.call('categories/save',{'name':'Model boundary category','folder_name':'model-boundary','active':1})
revised=name+' revised'
new=admin.call('areas/datatable',query={'q':revised})
assert any(row['name']==revised for row in new['rows'])
filtered=admin.call('areas/datatable',query={'draw':4,'search[value]':'not-present-'+name,'length':10})
assert filtered['recordsFiltered']==0 and filtered['recordsTotal']>0
role=next(x['id'] for x in admin.call('dashboard/lookups',query={'kind':'roles','q':'Staff'})['options'] if x['label']=='Staff')
username='route_'+secrets.token_hex(4)
account=admin.call('users/save',{'username':username,'first_name':'Route','last_name':'Staff','position_title':'Test','role_id':role,'active':1})
user_detail=admin.call('users/view/'+str(account['id']))['row']
assert 'password_hash' not in user_detail and 'session_version' not in user_detail
staff=Client();staff.call('auth/session')
staff.call('auth/login',{'username':username,'password':account['initial_password']})
staff.call('auth/password',{'current_password':account['initial_password'],'new_password':changed_password,'confirm_password':changed_password})
staff.call('users/datatable',status=403)
staff.call('areas/save',{'name':'Unauthorized'},status=403)
staff.call('my_requests/datatable')
staff.call('auth/logout',{})
assert staff.call('auth/session')['user'] is None
admin.call('auth/logout',{})
assert admin.call('auth/session')['user'] is None
print(f'PASS {checks} native HTTP responses, all 24 module data endpoints, route isolation, authorization, concurrency, and DataTables counts.')
