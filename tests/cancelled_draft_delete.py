"""Exercise request deletion through HTTP against a disposable database/site.

Requires local PHP/MySQL and Playwright. Only the generated *_test database and
temporary site are changed; source records and attachment bytes are never used.
"""
import os
from pathlib import Path
import secrets
import shutil
import socket
import subprocess
import tempfile
import time
from urllib.request import urlopen

from playwright.sync_api import sync_playwright, expect

ROOT = Path(__file__).resolve().parents[1]
PHP = os.environ.get('TEST_PHP', r'C:\xampp\php\php.exe')
MYSQL = os.environ.get('TEST_MYSQL', r'C:\xampp\mysql\bin\mysql.exe')
DUMP = str(Path(MYSQL).with_name('mysqldump.exe'))
DATABASE = 'pk_dts_delete_' + secrets.token_hex(6) + '_test'
PASSWORD = secrets.token_urlsafe(24)
ENV = dict(os.environ, PK_TEST_DB='1', DB_DATABASE=DATABASE, PK_TEST_PASSWORD=PASSWORD)
ARGS = [MYSQL, '-h', os.environ.get('DB_HOST', '127.0.0.1'), '-u', os.environ.get('DB_USERNAME', 'root'), '-N', '-B']
if os.environ.get('DB_PASSWORD'):
    ENV['MYSQL_PWD'] = os.environ['DB_PASSWORD']


def sql(statement, database=DATABASE):
    return subprocess.run(ARGS + ([database] if database else []), input=statement.encode(),
                          capture_output=True, check=True, env=ENV).stdout.decode().strip()


def post(page, base, route, record, confirmed='yes'):
    token = next(c['value'] for c in page.context.cookies() if c['name'] == 'pk_csrf_cookie')
    return page.request.post(base + route, form={'id': str(record), 'confirmed': confirmed,
                                                'pk_csrf_token': token})


def run(page, base, site):
    hashed = subprocess.run([PHP, '-r', "echo password_hash(getenv('PK_TEST_PASSWORD'), PASSWORD_DEFAULT);"],
                            env=ENV, capture_output=True, check=True).stdout.hex()
    role = sql("SELECT id FROM roles WHERE name='Administrator' LIMIT 1")
    users = []
    for name in ['owner', 'other']:
        users.append(int(sql("INSERT INTO users(username,first_name,last_name,position_title,role_id,password_hash,require_password_change) "
                             "VALUES('delete-test-" + name + "','Delete','Test','Tester'," + role + ",CONVERT(0x" + hashed + " USING utf8mb4),0); SELECT LAST_INSERT_ID();")))
    owner, other = users

    def fixture(kind='hardcopy_create', status='cancelled', actor=owner, payload=None, extra=''):
        reference = 'DELETE-TEST-' + secrets.token_hex(6)
        import json
        data = json.dumps(payload or {'subject': reference, 'title': reference}).encode().hex()
        return int(sql("INSERT INTO requests(reference,type,requested_by,payload,status" + (',submitted_at' if extra else '') + ") VALUES('" +
                       reference + "','" + kind + "'," + str(actor) + ",CONVERT(0x" + data + " USING utf8mb4),'" + status + "'" + extra + "); SELECT LAST_INSERT_ID();"))

    def login(name):
        page.context.clear_cookies()
        page.goto(base + 'login')
        page.locator('[name="login"]').fill('delete-test-' + name)
        page.locator('[name="password"]').fill(PASSWORD)
        page.locator('button[type="submit"]').click()
        if name == 'owner':
            expect(page.locator('.page-content')).to_be_visible()
        else:
            expect(page.get_by_text('You do not have permission for this action.')).to_be_visible()

    login('owner')
    record = fixture()
    sql("INSERT INTO notifications(user_id,title,message,request_id) VALUES(" + str(owner) + ",'Delete test','Draft cancelled'," + str(record) + ")")
    page.goto(base + 'my-requests/hardcopy?period=all')
    button = page.locator('[data-url$="/delete"][data-id="' + str(record) + '"]')
    expect(button).to_be_visible()
    button.click()
    expect(page.locator('#confirmModal')).to_be_visible()
    expect(page.locator('#confirmMessage')).to_contain_text('permanently')
    page.locator('#confirmModal').get_by_role('button', name='Cancel', exact=True).click()
    assert sql('SELECT COUNT(*) FROM requests WHERE id=' + str(record)) == '1'
    assert post(page, base, 'my-requests/hardcopy/delete', record, 'no').status == 400
    assert sql('SELECT COUNT(*) FROM requests WHERE id=' + str(record)) == '1'
    page.reload()
    button.click()
    page.locator('#confirmProceed').click()
    expect(page.locator('[data-url$="/delete"][data-id="' + str(record) + '"]')).to_have_count(0)
    assert sql('SELECT COUNT(*) FROM requests WHERE id=' + str(record)) == '0'
    assert sql('SELECT COUNT(*) FROM notifications WHERE request_id=' + str(record)) == '0'
    assert post(page, base, 'my-requests/hardcopy/delete', record).status == 200
    assert sql('SELECT COUNT(*) FROM requests WHERE id=' + str(record)) == '0'
    audit = '\n'.join(p.read_text(encoding='utf-8') for p in (site / 'storage/audit').glob('*.json'))
    assert 'my-requests/hardcopy/delete' in audit, 'Deletion must be audited outside the deleted request'

    # Every request category uses the same guarded deletion endpoint.
    kinds = {'softcopy': ['softcopy_create', 'softcopy_revise', 'softcopy_cancel'],
             'hardcopy': ['hardcopy_create', 'hardcopy_update'], 'hardcopy-transfer': ['transfer'],
             'hardcopy-disposal': ['disposal'], 'access-grant': ['access'], 'document-assign': ['assignment']}
    for tab, types in kinds.items():
        for kind in types:
            record = fixture(kind)
            response = post(page, base, 'my-requests/' + tab + '/delete', record)
            assert response.status == 200
            assert sql('SELECT COUNT(*) FROM requests WHERE id=' + str(record)) == '0', kind

    for status in ['draft', 'returned', 'submitted', 'approved', 'rejected', 'completed']:
        record = fixture(status=status)
        post(page, base, 'my-requests/hardcopy/delete', record)
        assert sql('SELECT COUNT(*) FROM requests WHERE id=' + str(record)) == '1', status
    record = fixture(extra=",'2026-10-01 12:00:00'")
    post(page, base, 'my-requests/hardcopy/delete', record)
    assert sql('SELECT COUNT(*) FROM requests WHERE id=' + str(record)) == '1', 'Submitted history protected'
    record = fixture('softcopy_create')
    post(page, base, 'my-requests/hardcopy/delete', record)
    assert sql('SELECT COUNT(*) FROM requests WHERE id=' + str(record)) == '1', 'Wrong category protected'
    record = fixture(actor=other)
    post(page, base, 'my-requests/hardcopy/delete', record)
    assert sql('SELECT COUNT(*) FROM requests WHERE id=' + str(record)) == '1', 'Other owner protected'

    # Cancellation and deletion only retire the proposal's attachment.
    for initial in ['pending', 'deleted']:
        storage = secrets.token_hex(32)
        attachment = site / 'storage/documents' / storage
        attachment.write_text('Disposable test attachment', encoding='utf-8')
        file_id = int(sql("INSERT INTO files(original_name,storage_name,size,mime_type,fingerprint,extension,purpose,domain,uploaded_by,status) "
                          "VALUES('test.txt','" + storage + "',26,'text/plain','" + '0' * 64 + "','txt','creation','softcopy'," + str(owner) + ",'" + initial + "'); SELECT LAST_INSERT_ID();"))
        record = fixture('softcopy_create', payload={'controlled_file_id': file_id})
        post(page, base, 'my-requests/softcopy/delete', record)
        assert sql('SELECT COUNT(*) FROM requests WHERE id=' + str(record)) == '0'
        assert not attachment.exists(), 'Proposal bytes must be cleaned after commit'

    # A database error must roll back attachment retirement and child deletion.
    storage = secrets.token_hex(32)
    attachment = site / 'storage/documents' / storage
    attachment.write_text('Rollback test', encoding='utf-8')
    file_id = int(sql("INSERT INTO files(original_name,storage_name,size,mime_type,fingerprint,extension,purpose,domain,uploaded_by) "
                      "VALUES('rollback.txt','" + storage + "',13,'text/plain','" + '0' * 64 + "','txt','creation','softcopy'," + str(owner) + "); SELECT LAST_INSERT_ID();"))
    record = fixture('softcopy_create', payload={'controlled_file_id': file_id})
    sql("INSERT INTO notifications(user_id,title,message,request_id) VALUES(" + str(owner) + ",'Rollback','Test'," + str(record) + ")")
    sql("CREATE TRIGGER block_test_delete BEFORE DELETE ON requests FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Test rollback'")
    post(page, base, 'my-requests/softcopy/delete', record)
    assert sql('SELECT status FROM files WHERE id=' + str(file_id)) == 'pending'
    assert sql('SELECT COUNT(*) FROM requests WHERE id=' + str(record)) == '1'
    assert sql('SELECT COUNT(*) FROM notifications WHERE request_id=' + str(record)) == '1'
    assert attachment.exists(), 'Failed deletion must preserve bytes'
    sql('DROP TRIGGER block_test_delete')
    post(page, base, 'my-requests/softcopy/delete', record)
    assert not attachment.exists()

    # Approved document files must survive even a stale proposal attachment ID.
    storage = secrets.token_hex(32)
    attachment = site / 'storage/documents' / storage
    attachment.write_text('Shared bytes', encoding='utf-8')
    file_id = int(sql("INSERT INTO files(original_name,storage_name,size,mime_type,fingerprint,extension,purpose,domain,uploaded_by,status) "
                      "VALUES('approved.txt','" + storage + "',12,'text/plain','" + '0' * 64 + "','txt','creation','softcopy'," + str(owner) + ",'approved'); SELECT LAST_INSERT_ID();"))
    record = fixture('softcopy_create', payload={'controlled_file_id': file_id})
    post(page, base, 'my-requests/softcopy/delete', record)
    assert attachment.exists(), 'Approved shared file must retain its bytes'

    for domain in ['hardcopy', 'softcopy']:
        page.goto(base + 'documents/' + domain + '?period=all')
        choices = page.locator('select[name="status"] option').evaluate_all('(items) => items.map(item => item.value)')
        assert choices == ['', 'active', 'disposed', 'cancelled'], choices

    # Permission changes also apply to direct POSTs.
    record = fixture(actor=other)
    sql("INSERT INTO roles(name,active) VALUES('Delete test no permissions',1); UPDATE users SET role_id=LAST_INSERT_ID() WHERE id=" + str(other))
    login('other')
    assert post(page, base, 'my-requests/hardcopy/delete', record).status == 403
    assert sql('SELECT COUNT(*) FROM requests WHERE id=' + str(record)) == '1'
    print('PASS: confirmed hard deletion, all 9 types, ownership/status/category/permission guards, attachments and audit')


def main(checks=run):
    created = False
    server = None
    try:
        with tempfile.TemporaryDirectory(prefix='pk-dts-delete-', ignore_cleanup_errors=True) as directory:
            temp = Path(directory)
            dump = temp / 'database.sql'
            subprocess.run([DUMP] + ARGS[1:-2] + ['--single-transaction', '--hex-blob', '--result-file=' + str(dump),
                           os.environ.get('TEST_SOURCE_DATABASE', 'pk_dts')], env=ENV, capture_output=True, check=True)
            sql('CREATE DATABASE `' + DATABASE + '` CHARACTER SET utf8mb4', None)
            created = True
            with dump.open('rb') as source:
                subprocess.run(ARGS + [DATABASE], stdin=source, env=ENV, capture_output=True, check=True)
            site = temp / 'site'
            for name in ['application', 'public', 'vendor']:
                shutil.copytree(ROOT / name, site / name)
            (site / 'storage/documents').mkdir(parents=True)
            router = temp / 'router.php'
            router.write_text("<?php $p=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH); if(is_file(getcwd().'/public'.$p))return false; $_SERVER['SCRIPT_NAME']='/index.php'; require getcwd().'/public/index.php';", encoding='utf-8')
            with socket.socket() as sock:
                sock.bind(('127.0.0.1', 0))
                port = sock.getsockname()[1]
            base = 'http://127.0.0.1:' + str(port) + '/'
            with (temp / 'server.log').open('w', encoding='utf-8') as log:
                server = subprocess.Popen([PHP, '-S', '127.0.0.1:' + str(port), '-t', str(site / 'public'), str(router)],
                                          cwd=site, env=ENV, stdout=log, stderr=log)
                for _ in range(100):
                    try:
                        if urlopen(base + 'login', timeout=1).status == 200:
                            break
                    except OSError:
                        time.sleep(.1)
                with sync_playwright() as playwright:
                    browser = playwright.chromium.launch(channel='msedge', headless=True)
                    context = browser.new_context(service_workers='block')
                    context.route('https://**/*', lambda route: route.abort())
                    checks(context.new_page(), base, site)
                    browser.close()
    finally:
        if server:
            server.terminate()
            server.wait(timeout=10)
        if created:
            sql('DROP DATABASE `' + DATABASE + '`', None)


if __name__ == '__main__':
    main()
