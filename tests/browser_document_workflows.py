"""Run with Python + Playwright and local PHP/MySQL; all writes use an owned test DB."""
import json
import contextlib
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
MYSQL = os.environ.get('PK_MYSQL', r'C:\xampp\mysql\bin\mysql.exe')
PHP = shutil.which('php')
database = 'pk_dts_browser_' + secrets.token_hex(6) + '_test'
env = dict(os.environ, PK_TEST_DB='1', DB_DATABASE=database,
           PK_BROWSER_PASSWORD=secrets.token_urlsafe(24))
mysql_args = [MYSQL, '-h', env.get('DB_HOST', '127.0.0.1'), '-u', env.get('DB_USERNAME', 'root')]
mysql_env = dict(env, MYSQL_PWD=env.get('DB_PASSWORD', ''))
manifest = None
server = None
created = False
temp_directory = tempfile.TemporaryDirectory(prefix='pk-dts-browser-')
log = None

def sql(statement, use_database=True):
    result = subprocess.run(mysql_args + ([database] if use_database else []),
                            input=statement.encode(), env=mysql_env, capture_output=True, check=True)
    return result.stdout.decode()

try:
    sql('CREATE DATABASE `' + database + '` CHARACTER SET utf8mb4;', False)
    created = True
    sql((ROOT / 'tests/fixtures/pk_dts.sql').read_text(encoding='utf-8'))
    sql((ROOT / 'tests/fixtures/seed.sql').read_text(encoding='utf-8'))
    result = subprocess.run([PHP, str(ROOT / 'tests/browser_document_fixture.php')],
                            env=env, capture_output=True, check=True)
    manifest = json.loads(result.stdout)
    for migration in ['20261013_user_role_assignments.sql', '20261013_task_notification_receipts.sql']:
        sql((ROOT / 'database/migrations' / migration).read_text(encoding='utf-8'))
    with contextlib.nullcontext(temp_directory.name) as temp:
        router = Path(temp) / 'router.php'
        # Built-in server must serve static assets and normalize the front-controller path.
        router.write_text("<?php $path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH); "
                          "if (is_file(getcwd().'/public'.$path)) return false; "
                          "$_SERVER['SCRIPT_NAME']='/index.php'; require getcwd().'/public/index.php';", encoding='utf-8')
        log = open(Path(temp) / 'server.log', 'w+', encoding='utf-8')
        with socket.socket() as port_check:
            port_check.bind(('127.0.0.1', 8096))
        server = subprocess.Popen([PHP, '-S', '127.0.0.1:8096', '-t', str(ROOT / 'public'), str(router)],
                                  cwd=ROOT, env=env, stdout=log, stderr=log)
        base = 'http://127.0.0.1:8096/'
        for _ in range(40):
            if server.poll() is not None:
                raise RuntimeError('The isolated PHP test server did not start.')
            try:
                with urlopen(base + 'login', timeout=1) as response:
                    if response.status == 200: break
            except Exception:
                time.sleep(.15)
        else:
            raise RuntimeError('The isolated PHP test server did not become ready.')
        with sync_playwright() as p:
            browser = p.chromium.launch(channel='msedge', headless=True)
            context = browser.new_context(viewport={'width': 1440, 'height': 1000})
            # Also validate the app's existing offline modal implementation.
            context.route('https://**', lambda route: route.abort())
            page = context.new_page()
            errors = []
            page.on('pageerror', lambda error: errors.append(str(error)))
            page.goto(base + 'login')
            page.locator('[name="login"]').fill('browser-admin')
            page.locator('[name="password"]').fill(env['PK_BROWSER_PASSWORD'])
            page.locator('button[type="submit"]').click()
            page.wait_for_url('**/dashboard')
            page.wait_for_timeout(800)
            page.locator('.modal.show [data-bs-dismiss="modal"]').first.click() if page.locator('.modal.show').count() else None

            for route in ['documents/hardcopy', 'documents/softcopy', 'my-requests/softcopy', 'my-tasks/softcopy']:
                page.goto(base + route)
                if page.locator('.modal.show').count():
                    page.locator('.modal.show [data-bs-dismiss="modal"]').first.click()
                period = page.locator('[data-date-period]')
                expect(period.locator('option')).to_have_count(6)
                for preset in ['day', 'week', 'month', 'year']:
                    period.select_option(preset)
                    page.wait_for_url('**period=' + preset + '**')
                    page.wait_for_load_state('load')
                    expect(period).to_have_value(preset)
                    expect(page.locator('[data-date-custom]').first).to_be_hidden()
                period.select_option('custom')
                expect(page.locator('[name="from"]')).to_be_focused()
                page.locator('[name="from"]').fill('2026-01-20')
                page.locator('[name="to"]').fill('2026-01-15')
                assert not page.locator('#tableFilter').evaluate('(e)=>e.checkValidity()')
                page.locator('[name="from"]').fill('2025-01-15')
                page.locator('[name="to"]').fill('2025-01-15')
                page.locator('button[form="tableFilter"]').click()
                page.wait_for_url('**period=custom**')
                page.wait_for_load_state('load')
                if route == 'documents/hardcopy':
                    expect(page.locator('tbody')).to_contain_text('Older hardcopy document')
                    expect(page.locator('tbody')).not_to_contain_text('Browser hardcopy document')
                period.select_option('all')
                page.wait_for_url('**period=all**')
                page.wait_for_load_state('load')
                assert 'from=' not in page.url and 'to=' not in page.url
            print('Browser date presets and inclusive custom filtering passed on all four registers.')

            for width in [1440, 390, 320]:
                page.set_viewport_size({'width': width, 'height': 900})
                for route in ['documents/softcopy', 'documents/hardcopy', 'my-requests/softcopy', 'my-tasks/softcopy', 'admin/users', 'admin/roles', 'places/location']:
                    page.goto(base + route)
                    if page.locator('.modal.show').count(): page.locator('.modal.show [data-bs-dismiss="modal"]').first.click()
                    page.locator('.js-view').first.click()
                    viewer = page.locator('#viewModal')
                    expect(viewer).to_be_visible()
                    assert viewer.locator('.modal-body').evaluate('(e)=>e.scrollWidth<=e.clientWidth+2'), route + ' overflows at ' + str(width)
                    assert viewer.locator('.modal-content').evaluate('(e)=>e.getBoundingClientRect().height<=innerHeight'), route + ' exceeds viewport'
                    tabs = viewer.locator('[role="tab"]')
                    if tabs.count():
                        tabs.last.click()
                        expect(tabs.last).to_have_attribute('aria-selected', 'true')
                        tabs.last.press('Home')
                        expect(tabs.first).to_have_attribute('aria-selected', 'true')
                    if route == 'documents/softcopy':
                        page.screenshot(path=str(ROOT / ('tests/controlled-browser-' + str(width) + '.png')))
                    viewer.locator('[data-bs-dismiss="modal"]').last.click()
                    expect(viewer).to_be_hidden()
                    expect(page.locator('#viewDetails')).to_be_empty()
                    if route == 'documents/softcopy':
                        page.locator('.js-file-history').first.click()
                        history = page.locator('#fileHistoryModal')
                        expect(history).to_be_visible()
                        expect(history.get_by_role('link', name='Print', exact=True)).to_be_visible()
                        assert history.locator('.modal-body').evaluate('(e)=>e.scrollWidth<=e.clientWidth+2')
                        history.locator('[data-bs-dismiss="modal"]').last.click()
            print('Browser document, request, task, user, role and location viewers passed at 1440, 390 and 320 pixels.')

            page.set_viewport_size({'width': 1440, 'height': 1000})
            for extension in ['txt', 'png', 'pdf', 'docx']:
                page.goto(base + 'files/print/' + str(manifest[extension]['file_id']))
                expect(page.locator('h1')).to_contain_text('controlled')
                if extension == 'docx':
                    expect(page.locator('#printStatus')).to_contain_text('Word or Excel')
                    continue
                expect(page.locator('#printButton')).to_be_enabled(timeout=15000)
                if extension == 'txt':
                    assert page.locator('script:not([src])').count() == 0
                    expect(page.locator('.print-text')).to_contain_text('<script>')
                if extension in ['txt', 'png']:
                    page.evaluate('() => { window.printCalls=0; window.print=()=>{window.printCalls++;}; }')
                    page.locator('#printButton').click()
                    assert page.evaluate('window.printCalls') == 1
                if extension == 'pdf':
                    page.wait_for_timeout(1200)
                    page.screenshot(path=str(ROOT / 'tests/controlled-print-pdf.png'))
                    response = context.request.get(base + 'files/print-content/' + str(manifest[extension]['file_id']))
                    assert response.status == 200 and response.body().startswith(b'%PDF')
            print('Browser text/image print invocation, protected two-page PDF preview and Office fallback passed.')
            guest = browser.new_context()
            response = guest.request.get(base + 'files/print-content/' + str(manifest['txt']['file_id']))
            assert '/login' in response.url and b'Browser controlled text' not in response.body()
            guest.close()
            staff = browser.new_context()
            staff.route('https://**', lambda route: route.abort())
            staff_page = staff.new_page()
            staff_page.goto(base + 'login')
            staff_page.locator('[name="login"]').fill('browser-staff')
            staff_page.locator('[name="password"]').fill(env['PK_BROWSER_PASSWORD'])
            staff_page.locator('button[type="submit"]').click()
            staff_page.wait_for_url('**/dashboard')
            for endpoint in ['files/print/', 'files/print-content/']:
                assert staff.request.get(base + endpoint + str(manifest['txt']['file_id'])).status == 403
            staff.close()

            page.goto(base + 'my-tasks/softcopy')
            if page.locator('.modal.show').count(): page.locator('.modal.show [data-bs-dismiss="modal"]').first.click()
            page.locator('button[data-decision="rejected"]').first.click()
            page.locator('#actionForm [name="remark"]').fill('Browser rejection proof')
            page.locator('#actionForm button[type="submit"]').click()
            page.wait_for_load_state('networkidle')
            assert not (ROOT / 'storage/documents' / manifest['request']['storage_name']).exists()
            assert (ROOT / 'storage/documents' / manifest['txt']['storage_name']).exists()
            assert 'rejected' in sql('SELECT status FROM requests WHERE id=' + str(manifest['request']['id']))
            page.goto(base + 'documents/softcopy')
            if page.locator('.modal.show').count(): page.locator('.modal.show [data-bs-dismiss="modal"]').first.click()
            page.locator('tbody tr').filter(has_text='BROWSER-PNG').locator('[data-disposal="yes"]').click()
            page.locator('#actionForm [name="disposal_reason"]').select_option('other')
            page.locator('#actionForm [name="disposal_other"]').fill('Browser disposal proof')
            page.locator('#actionForm button[type="submit"]').click()
            page.wait_for_load_state('networkidle')
            assert not (ROOT / 'storage/documents' / manifest['png']['storage_name']).exists()
            assert 'disposed' in sql('SELECT status FROM softcopy_documents WHERE id=' + str(manifest['png']['document_id']))
            for endpoint in ['files/print/', 'files/print-content/', 'files/download/']:
                assert context.request.get(base + endpoint + str(manifest['png']['file_id'])).status == 403
            assert not errors, errors
            print('Browser rejection/disposal, database status and attachment deletion passed; current file survived; retired URLs blocked; no page errors.')
            browser.close()
        log.close()
finally:
    if server:
        server.terminate()
        server.wait(timeout=10)
    if log and not log.closed: log.close()
    temp_directory.cleanup()
    if manifest:
        directory = (ROOT / 'storage/documents').resolve()
        for storage in manifest['blobs']:
            target = (directory / storage).resolve()
            if target.parent == directory and len(storage) == 64:
                target.unlink(missing_ok=True)
        (ROOT / 'storage' / manifest['cleanup_marker']).unlink(missing_ok=True)
    if created and database.startswith('pk_dts_browser_') and database.endswith('_test'):
        sql('DROP DATABASE `' + database + '`;', False)
