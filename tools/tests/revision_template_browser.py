"""Exercise revision template approval and download in an isolated MariaDB database."""
from datetime import datetime, timedelta, timezone
import io
import os
from pathlib import Path
import re
import secrets
import socket
import subprocess
import time
from urllib.request import urlopen
import zipfile

from playwright.sync_api import expect, sync_playwright


ROOT = Path(__file__).resolve().parents[2]
PHP = os.environ.get('TEST_PHP', 'php')
MYSQL = os.environ.get('TEST_MYSQL', 'mysql')
DATABASE = 'revision_template_' + secrets.token_hex(5) + '_test'
PASSWORD = 'Revision-Template-Test-2026!'
ACTION = 'softcopy_revision_template'
LABEL = 'Request Template for Revision'


def sql(statement, database=DATABASE):
    command = [
        MYSQL, '--binary-mode', '--batch', '--skip-column-names',
        '-h', os.environ.get('DB_HOST', '127.0.0.1'),
        '-P', os.environ.get('DB_PORT', '3306'),
        '-u', os.environ.get('DB_USERNAME', 'root')
    ]
    if database:
        command.append(database)
    env = dict(os.environ, MYSQL_PWD=os.environ.get('DB_PASSWORD', ''))
    return subprocess.run(command, input=statement, capture_output=True, encoding='utf-8',
                          check=True, env=env).stdout.strip()


def dismiss(page):
    alert = page.locator('#appAlertModal')
    if alert.count():
        alert.locator('[data-bs-dismiss=modal]').first.click()
        expect(alert).to_be_hidden()


def login(browser, base, username):
    context = browser.new_context(reduced_motion='reduce')
    page = context.new_page()
    page.goto(base + '/login')
    page.locator('[name=login]').fill(username)
    page.locator('[name=password]').fill(PASSWORD)
    page.locator('button[type=submit]').click()
    page.wait_for_url('**/dashboard')
    return page


def post(page, path, *, files=None, statuses=(302, 303), **data):
    csrf = next(cookie['value'] for cookie in page.context.cookies() if cookie['name'] == 'pk_csrf_cookie')
    fields = dict(data, pk_csrf_token=csrf, confirmed='yes')
    body = {'multipart': dict(fields, **files)} if files else {'form': fields}
    response = page.request.post(path, **body, max_redirects=0)
    assert response.status in statuses, (path, response.status, response.text()[:500])


def document_template():
    template = io.BytesIO()
    with zipfile.ZipFile(template, 'w', zipfile.ZIP_DEFLATED) as archive:
        archive.writestr('[Content_Types].xml', '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>')
        archive.writestr('_rels/.rels', '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>')
        archive.writestr('word/document.xml', '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>Editable revision fixture</w:t></w:r></w:p></w:body></w:document>')
    return template.getvalue()


def exercise(browser, base):
    requester = login(browser, base, 'template_requester')
    outsider = login(browser, base, 'template_outsider')
    admin = login(browser, base, 'template_admin')
    admin.goto(base + '/admin/workflows?q=softcopy_revision_template')
    expect(admin.get_by_role('button', name=f'Step Actions for {LABEL}')).to_be_visible()
    workflow_id = sql(f"SELECT id FROM workflows WHERE workflow_key='{ACTION}'")
    post(admin, base + '/admin/workflows/clone', id=workflow_id)
    draft = sql(f"SELECT id FROM workflow_versions WHERE workflow_id={workflow_id} AND status='draft'")
    sql(f"UPDATE workflow_versions SET graph=JSON_SET(graph,'$.steps[0].approver.type','requester') WHERE id={draft}")
    post(admin, base + '/admin/workflows/publish', id=draft)
    assert sql(f'SELECT status FROM workflow_versions WHERE id={draft}') == 'draft'

    post(outsider, base + '/my-requests/softcopy/save', type=ACTION, softcopy_id=1, subject='Out of scope')
    assert sql(f"SELECT COUNT(*) FROM requests WHERE type='{ACTION}'") == '0'
    document_page = requester.goto(base + '/documents/softcopy?layout=table&folder=all&q=Handwashing')
    assert document_page.ok, requester.locator('body').inner_text()[:600]
    requester.get_by_role('link', name=LABEL, exact=True).first.click(timeout=5000)
    modal = requester.locator('#editModal')
    expect(modal).to_be_visible()
    expect(modal.locator('#requestActionPicker')).to_have_value(ACTION)
    expect(modal.locator('#reqSoftcopy')).to_have_value('1')
    expect(modal.locator('#revisionFile')).to_be_hidden()
    expect(modal.get_by_role('button', name='Save Draft', exact=True)).to_be_enabled()
    modal.get_by_label('Subject / Reason').fill('Editable copy for procedure changes')
    modal.get_by_role('button', name='Submit Request').click()
    expect(requester.locator('#confirmMessage')).to_have_text('Submit this request for approval?')
    requester.locator('#confirmProceed').click()
    expect(requester.locator('#appAlertModal')).to_contain_text('Request submitted for approval')
    dismiss(requester)

    request_id = sql(f"SELECT id FROM requests WHERE type='{ACTION}' AND status='submitted'")
    assert sql(f"SELECT COUNT(*) FROM requests WHERE type='{ACTION}' AND status='draft'") == '0'
    download_url = base + '/files/revision-template/' + request_id
    assert requester.request.get(download_url).status == 404
    dco = login(browser, base, 'template_dco')
    task_notice = dco.locator('#taskNotificationModal')
    expect(task_notice).to_be_visible()
    expect(task_notice).to_contain_text('Editable copy for procedure changes')
    task_notice.get_by_role('button', name='Close notification').click()
    expect(task_notice).to_be_hidden()
    decision_url = base + '/my-tasks/softcopy/decide'
    today = datetime.now(timezone(timedelta(hours=8))).date()
    yesterday = today - timedelta(days=1)
    template = document_template()
    template_upload = {'name': 'revision-template.docx', 'mimeType': 'application/octet-stream', 'buffer': template}
    post(outsider, decision_url, id=request_id, decision='approved')
    post(dco, decision_url, id=request_id, decision='approved')
    for expiry_fields in ({}, {'template_expires_on': str(yesterday)}, {'template_expires_on': '2099-02-30'}):
        post(dco, decision_url, id=request_id, decision='approved', files={'final_file': template_upload}, **expiry_fields)
        assert sql(f'SELECT status FROM requests WHERE id={request_id}') == 'submitted'
        assert sql("SELECT COUNT(*) FROM files WHERE purpose='revision_template'") == '0'
    post(dco, decision_url, id=request_id, decision='approved', template_expires_on=str(today),
         files={'final_file': {'name': 'readonly.pdf', 'mimeType': 'application/pdf', 'buffer': b'%PDF-1.4\n'}})
    assert sql(f'SELECT status FROM requests WHERE id={request_id}') == 'submitted'
    assert sql("SELECT COUNT(*) FROM files WHERE purpose='revision_template'") == '0'

    dco.goto(base + f'/my-tasks?request_category=softcopy&type={ACTION}')
    expect(dco.locator('#appAlertModal')).to_contain_text('Upload an editable Word')
    dismiss(dco)
    dco.get_by_role('button', name='Review request').click()
    review = dco.locator('#viewModal')
    expect(review.get_by_role('button', name='Return', exact=True)).to_have_count(0)
    review.get_by_role('button', name='Approve', exact=True).click()
    approval = dco.locator('#actionModal')
    upload = approval.get_by_label('Editable revision template')
    expect(upload).to_have_attribute('required', '')
    expect(approval.locator('#actionFinalDownload')).to_be_hidden()
    expiry = approval.get_by_label('Download expiry date')
    expect(expiry).to_have_attribute('required', '')
    expect(expiry).to_have_attribute('min', str(today))
    upload.set_input_files(template_upload)
    assert not approval.locator('form').evaluate('(form) => form.checkValidity()')
    expiry.fill(str(today))
    approval.get_by_role('button', name='Upload and Approve').click()
    expect(dco.locator('#appAlertModal')).to_contain_text('Approval decision recorded.')
    assert sql(f'SELECT status FROM requests WHERE id={request_id}') == 'approved'
    assert sql(f"SELECT JSON_UNQUOTE(JSON_EXTRACT(result,'$.template_expires_on')) FROM requests WHERE id={request_id}") == str(today)
    assert sql('SELECT current_revision_id FROM softcopy_documents WHERE id=1') == '1'
    requester.reload()
    download = requester.get_by_role('link', name='Download Template', exact=True)
    expect(download).to_be_visible()
    response = requester.request.get(download.get_attribute('href'))
    assert response.ok and response.body() == template
    assert 'revision-template.docx' in response.headers['content-disposition']
    assert outsider.request.get(download_url).status == 404
    assert dco.request.get(download_url).status == 404
    template_id = sql("SELECT id FROM files WHERE purpose='revision_template'")
    assert dco.request.get(base + '/files/download/' + template_id).status == 403

    approved_row = requester.get_by_role('row').filter(has_text='Editable copy for procedure changes')
    approved_row.get_by_role('button', name='View', exact=True).click()
    details = requester.locator('#viewModal')
    details.get_by_role('tab', name='Revision Template').click()
    expect(details.get_by_role('tabpanel', name='Revision Template')).to_contain_text(f'Download available through {today}')
    expect(details.get_by_role('link', name='Download Editable revision template')).to_be_visible()

    # A previously shared URL is blocked even if the requester kept it open.
    sql(f"UPDATE requests SET result=JSON_SET(result,'$.template_expires_on','{yesterday}') WHERE id={request_id}")
    assert requester.request.get(download_url).status == 410
    assert outsider.request.get(download_url).status == 404
    requester.reload()
    expect(download).to_have_count(0)
    approved_row.get_by_role('button', name='View', exact=True).click()
    details.get_by_role('tab', name='Revision Template').click()
    expect(details.get_by_role('tabpanel', name='Revision Template')).to_contain_text(f'The download expired after {yesterday}')
    expect(details.get_by_role('link', name='Download Editable revision template')).to_have_count(0)

    sql(f"UPDATE requests SET result=JSON_REMOVE(result,'$.template_expires_on') WHERE id={request_id}")
    assert requester.request.get(download_url).body() == template

    requester.goto(base + '/documents/softcopy?layout=table&folder=all&q=Handwashing')
    requester.get_by_role('link', name=LABEL, exact=True).first.click()
    modal.get_by_label('Subject / Reason').fill('Reject this template request')
    modal.get_by_role('button', name='Submit Request').click()
    requester.locator('#confirmModal').get_by_role('button', name='Cancel', exact=True).click()
    expect(modal).to_be_visible()
    modal.get_by_role('button', name='Save Draft', exact=True).click()
    expect(requester.locator('#confirmMessage')).to_have_text('Save request draft?')
    requester.locator('#confirmProceed').click()
    expect(requester.locator('#appAlertModal')).to_contain_text('Request draft saved.')
    dismiss(requester)
    rejected = sql(f"SELECT id FROM requests WHERE type='{ACTION}' AND status='draft'")
    assert sql(f'SELECT COUNT(*) FROM workflow_history WHERE request_id={rejected}') == '0'
    submit_url = base + '/my-requests/softcopy/save-and-submit'
    sql(f'UPDATE workflows SET active=0 WHERE id={workflow_id}')
    for post_data in ({}, {'id': rejected}):
        post(requester, submit_url, type=ACTION, softcopy_id=1, subject='Failed direct submission', **post_data)
    assert sql(f"SELECT COUNT(*) FROM requests WHERE type='{ACTION}'") == '2'
    assert sql(f"SELECT status,JSON_UNQUOTE(JSON_EXTRACT(payload,'$.subject')) FROM requests WHERE id={rejected}") == (
        'draft\tReject this template request')
    sql(f'UPDATE workflows SET active=1 WHERE id={workflow_id}')

    requester.goto(base + '/my-requests?request_category=softcopy')
    dismiss(requester)
    row = requester.get_by_role('row').filter(has_text='Reject this template request')
    row.get_by_role('button', name='Edit', exact=True).click()
    expect(modal.locator('#requestActionPicker')).to_be_disabled()
    expect(modal.get_by_label('Subject / Reason')).to_have_value('Reject this template request')
    modal.get_by_label('Subject / Reason').fill('Updated template draft')
    modal.get_by_role('button', name='Save Draft', exact=True).click()
    requester.locator('#confirmProceed').click()
    expect(requester.locator('#appAlertModal')).to_contain_text('Request draft saved.')
    dismiss(requester)
    assert sql(f"SELECT status,JSON_UNQUOTE(JSON_EXTRACT(payload,'$.subject')) FROM requests WHERE id={rejected}") == (
        'draft\tUpdated template draft')
    requester.get_by_role('row').filter(has_text='Updated template draft').get_by_role('button', name='Edit', exact=True).click()
    modal.get_by_role('button', name='Submit Request').click()
    requester.locator('#confirmProceed').click()
    expect(requester.locator('#appAlertModal')).to_contain_text('Request submitted for approval')
    assert sql(f'SELECT status FROM requests WHERE id={rejected}') == 'submitted'
    post(dco, decision_url, id=rejected, decision='rejected', remark='Fixture rejection')
    assert sql(f'SELECT status FROM requests WHERE id={rejected}') == 'rejected'
    assert requester.request.get(base + '/files/revision-template/' + rejected).status == 404
    assert sql("SELECT COUNT(*) FROM files WHERE purpose='revision_template'") == '1'

    storage = ROOT / 'storage/documents'
    existing_files = set(storage.iterdir())
    contents = ('Rollback fixture ' + secrets.token_hex(12)).encode()
    sql("UPDATE workflows SET active=0 WHERE request_type='scanned_create'")
    post(requester, base + '/my-requests/scanned/save-and-submit',
         type='scanned_create', subject='Rollback uploaded request', title='Rollback upload',
         scanned_category_id=50, retention_start_date='2026-11-01', retention_end_date='2026-12-01',
         files={'scanned_attachment[]': {'name': 'rollback.txt', 'mimeType': 'text/plain', 'buffer': contents}})
    requester.goto(base + '/my-requests')
    expect(requester.locator('#appAlertModal')).to_contain_text('A published default workflow is required')
    dismiss(requester)
    assert sql("SELECT COUNT(*) FROM requests WHERE type='scanned_create'") == '0'
    assert sql("SELECT COUNT(*) FROM files WHERE original_name='rollback.txt'") == '0'
    leftover = [path for path in set(storage.iterdir()) - existing_files
                if path.is_file() and path.stat().st_size == len(contents) and path.read_bytes() == contents]
    for path in leftover:
        path.unlink()
    assert not leftover, 'Failed submission left the uploaded file on disk.'

    sql("DELETE rp FROM role_permissions rp JOIN permissions p ON p.id=rp.permission_id "
        "WHERE rp.role_id=50 AND p.module_key='requests' AND p.action_key='submit'")
    post(requester, submit_url, statuses=(403,), type=ACTION, softcopy_id=1, subject='Missing permission')
    requester.goto(base + '/my-requests')
    requester.get_by_role('button', name='New Request').click()
    modal.locator('#requestActionPicker').select_option(ACTION)
    expect(modal.get_by_role('button', name='Save Draft')).to_be_enabled()
    expect(modal.get_by_role('button', name='Submit Request')).to_have_count(0)


def main():
    sql(f'CREATE DATABASE {DATABASE} CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci', database=None)
    server = None
    try:
        sql((ROOT / 'database/pk_dts.sql').read_text(encoding='utf-8'))
        sql((ROOT / 'database/migrations/20261112_file_lifecycle_stage.sql').read_text(encoding='utf-8'))
        sql((ROOT / 'database/migrations/20261118_softcopy_revision_template.sql').read_text(encoding='utf-8'))
        password_hash = subprocess.check_output(
            [PHP, '-r', f"echo password_hash('{PASSWORD}', PASSWORD_DEFAULT);"], text=True)
        sql(f"""
            INSERT INTO roles (id,name) VALUES (50,'Revision Fixture Requester');
            INSERT INTO users (id,username,first_name,last_name,position_title,role_id,password_hash,require_password_change)
            VALUES (101,'template_requester','Requester','Fixture','Requester',50,'{password_hash}',0),
              (102,'template_outsider','Outsider','Fixture','Requester',50,'{password_hash}',0),
              (103,'template_dco','DCO','Fixture','Document Control Officer',2,'{password_hash}',0),
              (104,'template_admin','Admin','Fixture','Administrator',1,'{password_hash}',0);
            INSERT INTO role_permissions (role_id,permission_id)
            SELECT 50,id FROM permissions WHERE (module_key='requests' AND action_key IN ('view','add','edit','submit'))
              OR (module_key='softcopy' AND action_key IN ('view','request_revision_template'))
              OR (module_key='scanned_documents' AND action_key='request')
              OR (module_key='dashboard' AND action_key='view');
            INSERT INTO scanned_categories (id,name,created_by,approved_by) VALUES (50,'Rollback fixture',101,103);
            UPDATE softcopy_documents SET created_by=101 WHERE id=1;
            UPDATE files SET locked_at=NOW() WHERE id=(SELECT file_id FROM softcopy_revisions WHERE id=1);
        """)
        with socket.socket() as sock:
            sock.bind(('127.0.0.1', 0))
            port = sock.getsockname()[1]
        base = f'http://127.0.0.1:{port}'
        env = dict(os.environ, PK_TEST_DB='1', DB_DATABASE=DATABASE, CI_ENV='development')
        server = subprocess.Popen([PHP, '-S', f'127.0.0.1:{port}', '-t', str(ROOT / 'public')],
                                  cwd=ROOT, env=env, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
        for _ in range(60):
            try:
                urlopen(base + '/login', timeout=1).close()
                break
            except OSError:
                time.sleep(.1)
        with sync_playwright() as playwright:
            browser = playwright.chromium.launch(channel=os.environ.get('PK_TEST_BROWSER', 'msedge'))
            exercise(browser, base)
            browser.close()
        print('Revision template browser workflow passed.')
    finally:
        if server:
            server.terminate()
            server.wait()
        try:
            for storage in sql("SELECT storage_name FROM files WHERE purpose='revision_template'").splitlines():
                if re.fullmatch('[a-f0-9]{64}', storage):
                    (ROOT / 'storage/documents' / storage).unlink(missing_ok=True)
        finally:
            sql(f'DROP DATABASE {DATABASE}', database=None)


if __name__ == '__main__':
    main()
