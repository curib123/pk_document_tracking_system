"""Browser regression using synthetic data: python tools/tests/scanned_browser.py."""
from pathlib import Path
import os
import base64
import io
import zipfile
import re
import socket
import subprocess
import time
from urllib.request import urlopen
from playwright.sync_api import sync_playwright, expect

ROOT = Path(__file__).resolve().parents[2]
PHP = os.environ.get('TEST_PHP', 'php')
CLI = [PHP, str(ROOT / 'tools/tests/scanned_integration.php')]
server = None


def dismiss(page):
    for selector in ['#appAlertModal', '#taskNotificationModal', '#retentionNotificationModal']:
        modal = page.locator(selector)
        if not modal.count():
            continue
        if selector != '#appAlertModal' and not modal.is_visible():
            continue
        page.wait_for_function("id => document.querySelector(id).classList.contains('show') && !window.bootstrap?.Modal.getInstance(document.querySelector(id))?._isTransitioning", arg=selector)
        modal.locator('[data-bs-dismiss=modal]').first.click()
        expect(modal).to_be_hidden()


def preview_uploads():
    # Small, valid fixtures exercise the shared image/PDF/Office/text preview paths.
    pdf = b'%PDF-1.4\n'
    objects = [b'<< /Type /Catalog /Pages 2 0 R >>',
               b'<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
               b'<< /Type /Page /Parent 2 0 R /MediaBox [0 0 200 200] /Contents 4 0 R >>',
               b'<< /Length 0 >>\nstream\n\nendstream']
    offsets = [0]
    for number, body in enumerate(objects, 1):
        offsets.append(len(pdf))
        pdf += f'{number} 0 obj\n'.encode() + body + b'\nendobj\n'
    xref = len(pdf)
    pdf += b'xref\n0 5\n0000000000 65535 f \n'
    pdf += b''.join(f'{offset:010d} 00000 n \n'.encode() for offset in offsets[1:])
    pdf += f'trailer\n<< /Size 5 /Root 1 0 R >>\nstartxref\n{xref}\n%%EOF\n'.encode()
    docx = io.BytesIO()
    with zipfile.ZipFile(docx, 'w', zipfile.ZIP_DEFLATED) as archive:
        archive.writestr('[Content_Types].xml', '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>')
        archive.writestr('_rels/.rels', '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>')
        archive.writestr('word/document.xml', '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>Scanned Office preview</w:t></w:r></w:p></w:body></w:document>')
    return [
        {'name':'scan-one.txt', 'mimeType':'text/plain', 'buffer':b'Scanned fixture one\n'},
        {'name':'scan-image.png', 'mimeType':'image/png', 'buffer':base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=')},
        {'name':'scan-pdf.pdf', 'mimeType':'application/pdf', 'buffer':pdf},
        {'name':'scan-office.docx', 'mimeType':'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'buffer':docx.getvalue()}]


def verify_previews(page, context):
    page.get_by_role('tab', name=re.compile('Scanned Attachments|Approved Files')).click()
    expect(page.locator('#viewModal .doc-modal-file-preview')).to_have_count(4)
    previews = page.locator('#viewModal .doc-modal-file-preview')
    for index in range(4):
        response = context.request.get(previews.nth(index).get_attribute('src').split('#')[0])
        assert response.status == 200, (response.status, response.body()[:200])
        assert response.headers['content-disposition'].startswith('inline;')
        if index >= 2:
            assert response.headers['content-type'].startswith('application/pdf')
            assert response.body().startswith(b'%PDF-')
    for filename in ['scan-image.png', 'scan-pdf.pdf', 'scan-office.docx']:
        page.get_by_role('button', name=f'Show {filename} fullscreen', exact=True).click()
        expect(page.locator('.pv')).to_be_visible()
        if filename.endswith('.png'):
            expect(page.locator('.pv img')).to_be_visible()
        else:
            expect(page.locator('.pv canvas').first).to_be_visible(timeout=20000)
        page.get_by_role('button', name='Close fullscreen view', exact=True).click()
        expect(page.locator('.pv')).to_have_count(0)


try:
    subprocess.run(CLI + ['--keep'], cwd=ROOT, check=True)
    name = (ROOT / 'storage/scanned-test-database.txt').read_text().strip()
    with socket.socket() as sock:
        sock.bind(('127.0.0.1', 0))
        port = sock.getsockname()[1]
    base = f'http://127.0.0.1:{port}'
    env = dict(os.environ, PK_TEST_DB='1', DB_DATABASE=name, CI_ENV='development')
    log = open(ROOT / 'storage/scanned-browser-server.log', 'w')
    server = subprocess.Popen([PHP, '-S', f'127.0.0.1:{port}', '-t', str(ROOT / 'public')], cwd=ROOT,
                              env=env, stdout=log, stderr=log,
                              creationflags=subprocess.CREATE_NO_WINDOW if os.name == 'nt' else 0)
    for attempt in range(50):
        try:
            urlopen(base + '/login', timeout=1).close()
            break
        except OSError:
            time.sleep(0.1)
    with sync_playwright() as p:
        browser = p.chromium.launch(channel=os.environ.get('PK_TEST_BROWSER', 'msedge'), args=['--disable-gpu'])
        errors = []

        def login(user):
            context = browser.new_context(viewport={'width': 1440, 'height': 1000}, reduced_motion='reduce')
            page = context.new_page()
            page.on('pageerror', lambda error: errors.append(str(error)) if 'ViewTransition opt-in disabled' not in str(error) else None)
            page.goto(base + '/login', wait_until='domcontentloaded')
            page.locator('[name=login]').fill(user)
            page.locator('[name=password]').fill('Scanned-Test-2026!')
            page.locator('button[type=submit]').click()
            page.wait_for_url('**/dashboard')
            return context, page

        uploader, page = login('uploader')
        page.goto(base + '/my-requests/scanned?action=scanned_create')
        expect(page.locator('#editModal')).to_be_visible()
        form = page.locator('#editForm')
        form.locator('[name=subject]').fill('Browser scanned upload')
        form.locator('[name=title]').fill('Browser Scanned Document')
        form.locator('[name=description]').fill('Scanned invoices for backup and review.')
        form.locator('[name=scanned_category_id]').select_option(index=1)
        form.locator('[name=retention_start_date]').fill('2026-11-01')
        form.locator('[name=retention_end_date]').fill('2026-11-17')
        expect(form.locator('[name=scan_due_date]')).to_have_count(0)
        expect(form.locator('[name=remarks]')).to_have_count(0)
        form.locator('[type=file]').set_input_files(preview_uploads())
        form.locator('button[type=submit]').click()
        page.locator('#confirmProceed').click()
        page.wait_for_url('**/my-requests/scanned')
        expect(page.locator('#appAlertMessage')).to_contain_text('Request draft saved')
        dismiss(page)
        row = page.locator('tbody tr').filter(has_text='Browser Scanned Document')
        expect(row).to_have_count(1)
        row.locator('.js-action[data-title=Submit]').click()
        page.locator('#confirmProceed').click()
        expect(page.locator('#appAlertMessage')).to_contain_text('Request submitted')

        dco, approver = login('dco')
        approver.goto(base + '/my-tasks/scanned')
        expect(approver.locator('#taskNotificationModal')).to_be_visible()
        dismiss(approver)
        row = approver.locator('tbody tr').filter(has_text='Browser Scanned Document')
        row.locator('.js-view').click()
        expect(approver.locator('#viewModal')).to_be_visible()
        expect(approver.locator('#viewModal')).to_contain_text('Scanned invoices for backup and review.')
        verify_previews(approver, dco)
        links = approver.locator('#viewModal a[href*="scanned/file"]')
        expect(links).to_have_count(4)
        file_url = links.first.get_attribute('href')
        assert dco.request.get(file_url).body() == b'Scanned fixture one\n'
        assert uploader.request.get(file_url).status == 200
        outside, outsider = login('outsider')
        assert outside.request.get(file_url).status == 403
        assert outside.request.get(file_url + '&preview=1').status == 403
        anonymous = browser.new_context().request.get(file_url, max_redirects=0)
        assert anonymous.status in [302, 303, 307] and anonymous.headers['location'].endswith('/login')
        approver.locator('#viewModal [data-bs-dismiss=modal]').first.click()
        row.locator('.js-action[data-decision=approved]').click()
        expect(approver.locator('#actionRemark')).to_be_hidden()
        approver.locator('#confirmProceed').click()
        expect(approver.locator('#appAlertMessage')).to_contain_text('Approval decision recorded')

        page.goto(base + '/documents/scanned')
        expect(page.locator('tbody tr')).to_contain_text('Browser Scanned Document')
        page.locator('tbody .js-view').click()
        expect(page.locator('#viewModal')).to_contain_text('Scanned invoices for backup and review.')
        expect(page.locator('#viewModal a[href*="scanned/file"]')).to_have_count(4)
        verify_previews(page, uploader)
        approved_url = page.locator('#viewModal a[href*="scanned/file"]').first.get_attribute('href')
        assert uploader.request.get(approved_url).status == 200
        assert outside.request.get(approved_url).status == 403
        assert outside.request.get(approved_url + '?preview=1').status == 403
        assert dco.request.get(file_url + '&preview=1').status == 403
        page.locator('#viewModal [data-bs-dismiss=modal]').first.click()
        edit_url = page.locator('tbody tr').filter(has_text='Browser Scanned Document').get_by_role('link', name='Request Update').get_attribute('href')
        page.goto(edit_url)
        expect(page.locator('#editForm [name=description]')).to_have_value('Scanned invoices for backup and review.')
        page.goto(base + '/documents/scanned')
        page.locator('[data-scan-alarm-enable]').first.click()
        assert page.evaluate("localStorage.getItem('pk-scan-alarm-sound')") == 'yes'
        page.get_by_role('link', name='Switch to folder tree view').click()
        expect(page.locator('.doc-folder-tree-mode')).to_be_visible()
        expect(page.locator('.document-layout-choice.is-active')).to_have_text('Folder')
        expect(page.locator('.doc-tree-node')).not_to_have_count(0)
        page.get_by_role('link', name='Switch to table view').click()
        expect(page.locator('[data-record-layout="table"]')).to_be_visible()
        page.screenshot(path=str(ROOT / 'storage/scanned-register-desktop.png'), full_page=True)
        page.set_viewport_size({'width': 390, 'height': 844})
        page.reload()
        expect(page.locator('tbody tr')).to_contain_text('Browser Scanned Document')
        assert page.evaluate('document.documentElement.scrollWidth <= innerWidth')
        page.screenshot(path=str(ROOT / 'storage/scanned-register-mobile.png'), full_page=True)

        # DCO Master Data creation and allocation, including inherited subfolders.
        approver.goto(base + '/places/scanned-categories')
        dismiss(approver)
        expect(approver.get_by_role('link', name='Scanned Categories', exact=True)).to_be_visible()
        approver.locator('.js-edit[data-record="{}"]').click()
        folder_form = approver.locator('#editForm')
        folder_form.locator('[name=name]').fill('Browser User Folder')
        folder_form.locator('[name="user_ids[]"][value="3"]').check()
        folder_form.locator('button[type=submit]').click()
        approver.locator('#confirmProceed').click()
        expect(approver.locator('#appAlertMessage')).to_contain_text('Record saved')
        dismiss(approver)
        folder_row = approver.locator('tbody tr').filter(has_text='Browser User Folder')
        expect(folder_row).to_contain_text('uploader Fixture')
        folder_row.locator('.js-edit').click()
        expect(folder_form.locator('[name="user_ids[]"][value="3"]')).to_be_checked()
        expect(folder_form.locator('[name="user_ids[]"][value="4"]')).not_to_be_checked()
        root_id = folder_form.locator('[name=id]').input_value()
        approver.locator('#editModal [data-bs-dismiss=modal]').first.click()
        approver.locator('.js-edit[data-record="{}"]').click()
        expect(folder_form.locator('[name=id]')).to_have_value('')
        folder_form.locator('[name=name]').fill('Browser Nested Folder')
        folder_form.locator('[name=parent_id]').select_option(root_id)
        folder_form.locator('button[type=submit]').click()
        approver.locator('#confirmProceed').click()
        expect(approver.locator('#appAlertMessage')).to_contain_text('Record saved')
        dismiss(approver)
        expect(approver.locator('tbody tr').filter(has_text='Browser User Folder / Browser Nested Folder')).to_have_count(1)
        approver.get_by_role('link', name='Switch to folder tree view').click()
        expect(approver.locator('.doc-folder-tree-mode')).to_be_visible()
        expect(approver.locator('.doc-tree-node')).not_to_have_count(0)
        approver.set_viewport_size({'width': 390, 'height': 844})
        approver.reload()
        assert approver.evaluate('document.documentElement.scrollWidth <= innerWidth')
        page.goto(base + '/my-requests/scanned?action=scanned_create')
        dismiss(page)
        expect(page.locator('[name=scanned_category_id] option').filter(has_text='Browser User Folder / Browser Nested Folder')).to_have_count(1)
        assert uploader.request.get(base + '/places/scanned-categories').status == 403
        # Change the root allocation and verify the user's selector updates immediately.
        approver.set_viewport_size({'width': 1440, 'height': 1000})
        approver.goto(base + '/places/scanned-categories')
        folder_row = approver.locator('tbody tr').filter(has_text='Browser User Folder').filter(has_not_text='Browser Nested Folder')
        folder_row.locator('.js-edit').click()
        folder_form = approver.locator('#editForm')
        folder_form.locator('[name="user_ids[]"][value="3"]').uncheck()
        folder_form.locator('[name="user_ids[]"][value="4"]').check()
        folder_form.locator('button[type=submit]').click()
        approver.locator('#confirmProceed').click()
        expect(approver.locator('#appAlertMessage')).to_contain_text('Record saved')
        page.goto(base + '/my-requests/scanned?action=scanned_create')
        expect(page.locator('[name=scanned_category_id] option').filter(has_text='Browser User Folder')).to_have_count(0)
        page.goto(base + '/documents/scanned')

        reminder = {'key': 'scan-daily:fixture:guide', 'title': '4:00 PM scanned file upload reminder',
                    'reference': 'Daily upload check', 'label': 'Upload outstanding scanned files',
                    'url': base + '//documents/scanned', 'daily': True, 'alarm': True}
        page.route('**/notifications/retention', lambda route: route.fulfill(json={'items': [reminder]}))
        page.reload()
        expect(page.locator('#retentionNotificationModal')).to_be_visible()
        expect(page.locator('#retentionNotificationList a')).to_have_count(1)
        expect(page.locator('#retentionNotificationList')).to_contain_text('Upload outstanding scanned files')
        dismiss(page)
        page.reload()
        page.wait_for_timeout(300)
        assert page.locator('#retentionNotificationModal.show').count() == 0
        assert not errors, errors
        browser.close()
        print('PASS DCO Master Data root/nested creation, allocation editing, upload folder scoping, folder view, mobile layout; inline image/PDF/text/Office previews, fullscreen, requests without remarks, DCO review, private files, mobile layout, alarm preference and daily reminder deduplication')
finally:
    if server:
        server.terminate()
        server.wait(timeout=10)
    subprocess.run(CLI + ['--cleanup'], cwd=ROOT, check=True)
