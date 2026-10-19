"""Category assignment, movement audits and native-file review inside the owned test DB."""
import base64
import io
import json
import zipfile
from playwright.sync_api import expect


def run_checks(page, context, base, app_root, sql, manifest, password):
    def scalar(statement): return sql(statement).strip().splitlines()[-1]
    sql("INSERT INTO categories(id,parent_id,name,folder_name,created_by) VALUES "
        "(81001,NULL,'Issue Parent','Issue Parent',1),(81002,81001,'Issue Child','Issue Child',1),"
        "(81003,81002,'Issue Grandchild','Issue Grandchild',1),(81004,NULL,'Issue Sibling','Issue Sibling',1)")
    for i in range(1, 6):
        sql(f"INSERT INTO softcopy_documents(id,document_number,title,category_id,created_by,status) VALUES "
            f"({81000+i},'ISSUE-{i}','Issue document {i}',{81000+min(i,4)},1,'{'disposed' if i==5 else 'active'}')")
    sql("INSERT INTO assignments(softcopy_id,user_id,assigned_by,active) VALUES(81001,1,1,1),(81002,2,1,0)")
    page.goto(base + 'admin/document-assignments?domain=softcopy&owner=2')
    expect(page.locator('#categoryAssignmentUser')).to_have_value('2')
    expect(page.locator('#categoryAssignmentCategory option[value="81003"]')).to_have_text('Issue Parent / Issue Child / Issue Grandchild')

    def assign(category, user):
        page.locator('#categoryAssignmentCategory').select_option(str(category), force=True)
        page.locator('#categoryAssignmentUser').select_option(str(user), force=True)
        page.locator('#categoryAssignmentForm').get_by_role('button', name='Assign All', exact=True).click()
        page.locator('#confirmProceed').click()
        page.wait_for_url('**folder=category%3A' + str(category) + '**')
        page.wait_for_load_state('load')
        if page.locator('.modal.show').count():
            page.locator('.modal.show [data-bs-dismiss="modal"]').first.click()

    assign(81001, 2)
    assert scalar('SELECT COUNT(*) FROM assignments WHERE user_id=2 AND active=1 AND softcopy_id BETWEEN 81001 AND 81005') == '3'
    assert scalar('SELECT active FROM assignments WHERE user_id=1 AND softcopy_id=81001') == '1'
    assign(81002, 1)
    assert scalar('SELECT COUNT(*) FROM assignments WHERE user_id=1 AND active=1 AND softcopy_id IN(81002,81003)') == '2'
    assert scalar('SELECT COUNT(*) FROM assignments WHERE softcopy_id IN(81004,81005)') == '0'
    events = [event for path in (app_root/'storage/audit').glob('*.json') for event in json.loads(path.read_text())]
    bulk = [event for event in events if event['action']=='admin/document-assignments/category']
    assert len(bulk)==2 and all(event['actor_id']==1 and event['status']=='success' for event in bulk)
    first = bulk[0]['metadata']
    assert set(k for k in first['after'] if k.startswith('softcopy_documents:')) == {'softcopy_documents:81001','softcopy_documents:81002','softcopy_documents:81003'}
    assert any(int(row.get('user_id',0))==2 and int(row.get('active',0))==0 for k,row in first['before'].items() if k.startswith('assignments:'))
    assert any(int(row.get('user_id',0))==2 and int(row.get('active',0))==1 for k,row in first['after'].items() if k.startswith('assignments:'))
    print('Parent/subcategory assignment includes descendants, reactivates grants, preserves other users and audits complete movement snapshots.', flush=True)

    # Native form confirmation and CSRF still apply to direct assignment endpoints.
    page.goto(base + 'admin/document-assignments?domain=softcopy')
    form = page.locator('#categoryAssignmentForm')
    csrf = form.locator('input[type="hidden"]').evaluate_all('(fields)=>Object.fromEntries(fields.map(f=>[f.name,f.value]))')
    response = context.request.post(base+'admin/document-assignments/category', form={**csrf,'category_id':'81001','recipient_id':'99999'})
    assert response.status == 400
    page.goto(base + 'documents/softcopy')
    page.locator('tr[data-record-id="'+str(manifest['txt']['document_id'])+'"]').locator('.js-view').click()
    expect(page.locator('#viewModal')).to_be_visible()
    expect(page.locator('#viewDocumentRef')).to_have_count(0)
    assert not page.locator('#viewModal').get_by_text('Reference', exact=True).count()
    page.locator('#viewModal').get_by_role('button', name='Close', exact=True).last.click()

    request_id = manifest['request']['id']
    file_id = scalar(f"SELECT JSON_UNQUOTE(JSON_EXTRACT(payload,'$.revision_file_id')) FROM requests WHERE id={request_id}")
    file_path = app_root/'storage/documents'/manifest['request']['storage_name']
    file_path.write_bytes(b'PK\x03\x04Browser native review copy')
    anonymous = context.browser.new_context()
    for extension, app, mime in [('docx','Word','application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
                                  ('xlsx','Excel','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')]:
        sql(f"UPDATE files SET original_name='review.{extension}',extension='{extension}',mime_type='{mime}',size={file_path.stat().st_size} WHERE id={file_id}")
        page.goto(base+'files/review/'+str(request_id))
        href = page.get_by_role('link', name='Open in '+app, exact=True).get_attribute('href')
        assert href.startswith('ms-'+app.lower()+':ofv|u|')
        content_url = href.split('|u|',1)[1]
        wps = page.get_by_role('link', name='Open in WPS', exact=True).get_attribute('href')
        encoded = wps.split('/wps/',1)[1]
        assert base64.urlsafe_b64decode(encoded+'='*((4-len(encoded)%4)%4)).decode()==content_url
        response = anonymous.request.get(content_url)
        assert response.status==200 and response.body()==file_path.read_bytes()
        assert response.headers['content-type']==mime
        assert anonymous.request.get(content_url.replace('/'+str(request_id)+'/', '/999999/',1)).status==403
        parts = content_url.split('/')
        parts[-2] = parts[-2][:-1] + ('0' if parts[-2][-1]!='0' else '1')
        assert anonymous.request.get('/'.join(parts)).status==403
        sql(f"UPDATE requests SET status='rejected' WHERE id={request_id}")
        assert anonymous.request.get(content_url).status==403
        sql(f"UPDATE requests SET status='submitted' WHERE id={request_id}")
        download = context.request.get(base+'files/review/'+str(request_id)+'?download=1')
        assert download.status==200 and download.body()==file_path.read_bytes()
        for width in [390, 320]:
            page.set_viewport_size({'width':width,'height':850})
            assert page.locator('body').evaluate('(el)=>el.scrollWidth<=innerWidth')
    package = context.request.get(base+'files/office-setup')
    assert package.status==200
    with zipfile.ZipFile(io.BytesIO(package.body())) as archive:
        assert set(archive.namelist()) == {'install.ps1','open-office.ps1','README.txt','config.json'}
        assert json.loads(archive.read('config.json'))['BaseUrl']==base
    assert anonymous.request.get(base+'files/review/'+str(request_id)).status==200  # follows the login redirect
    assert b'name="login"' in anonymous.request.get(base+'files/review/'+str(request_id)).body()
    anonymous.close()
    page.set_viewport_size({'width':1440,'height':1000})
    print('Word/Excel/WPS links, anonymous scoped native fetch, tamper/wrong-request/revoked access, downloads, setup ZIP and mobile layout passed.', flush=True)
