"""Exercise the editable Document Number of a direct softcopy creation in an isolated MariaDB database.

Run: python tools/tests/softcopy_manual_number_browser.py
Covers: the direct form keeps a typed number until the category changes, an untouched number is only
a preview (the next free number is assigned), a typed number is used exactly or rejected when it is
already in use, and the request form stays read-only.
"""
import os
from pathlib import Path
import re
import secrets
import socket
import subprocess
import time
from urllib.request import urlopen

from playwright.sync_api import expect, sync_playwright


ROOT = Path(__file__).resolve().parents[2]
PHP = os.environ.get('TEST_PHP', 'php')
MYSQL = os.environ.get('TEST_MYSQL', 'mysql')
DATABASE = 'manual_number_' + secrets.token_hex(5) + '_test'
PASSWORD = 'Manual-Number-Test-2026!'
CATEGORY = 60
FIXTURE = {'name': 'manual-number-fixture.txt', 'mimeType': 'text/plain', 'buffer': b'Manual number fixture'}


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


def choose(field, value):
    # Searchable selects keep the native element; set it and notify the page.
    field.evaluate("(el, value) => { el.value = value; el.dispatchEvent(new Event('change', {bubbles: true})); }",
                   str(value))


def settle(page):
    # The flash alert exists only with a message; the task notice is fetched after load.
    page.wait_for_load_state('networkidle')
    dismiss(page)
    notice = page.locator('#taskNotificationModal')
    if notice.is_visible():
        notice.get_by_role('button', name='Close notification').click()
        expect(notice).to_be_hidden()


def open_add_form(page, base):
    page.goto(base + '/documents/softcopy?layout=table&folder=all')
    settle(page)
    page.get_by_role('button', name='Add New Softcopy Document').click()
    modal = page.locator('#editModal')
    expect(modal).to_be_visible()
    expect(modal.locator('#reqType')).to_have_value('softcopy_create')
    return modal


def approve(page, modal, subject, title):
    modal.get_by_label('Subject / Reason').fill(subject)
    modal.get_by_label('Proposed Document Title').fill(title)
    modal.locator('#revisionFile').set_input_files(FIXTURE)
    modal.get_by_role('button', name='Approve Directly').click()
    expect(page.locator('#confirmMessage')).to_have_text('Approve and apply this softcopy action directly?')
    page.locator('#confirmProceed').click()
    return page.locator('#appAlertModal')


def number_of(title):
    return sql(f"SELECT document_number FROM softcopy_documents WHERE title='{title}'")


def exercise(browser, base):
    admin = login(browser, base, 'number_admin')

    # The direct field is editable; a typed value survives until the category changes.
    modal = open_add_form(admin, base)
    number = modal.locator('#generatedDocumentNumber')
    edited = modal.locator('[name=document_number_edited]')
    expect(number).to_be_editable()
    choose(modal.locator('#reqCategory'), CATEGORY)
    expect(number).to_have_value('MNF-001')
    expect(edited).to_have_value('')
    number.fill('MNF-TYPED-1')
    expect(edited).to_have_value('1')
    modal.get_by_label('Revision Level', exact=True).fill('00')
    expect(number).to_have_value('MNF-TYPED-1')
    choose(modal.locator('#reqCategory'), 1)
    expect(number).to_have_value('DOC-1-001')
    expect(edited).to_have_value('')
    # Hidden inputs survive form.reset(): reopening must not reuse the edited flag.
    choose(modal.locator('#reqCategory'), CATEGORY)
    number.fill('MNF-TYPED-1')
    modal.locator('.modal-header .btn-close').click()
    expect(modal).to_be_hidden()
    modal = open_add_form(admin, base)
    expect(edited).to_have_value('')
    choose(modal.locator('#reqCategory'), CATEGORY)
    expect(number).to_have_value('MNF-001')

    # Untouched: the shown number is a preview. Another record takes it before approval.
    sql(f"INSERT INTO softcopy_documents (document_number,title,category_id,created_by,creation_source,creation_reason) "
        f"VALUES ('MNF-001','Concurrent fixture',{CATEGORY},111,'direct','Taken meanwhile')")
    alert = approve(admin, modal, 'Untouched preview', 'Untouched Fixture')
    expect(alert).to_contain_text('Generated document number: MNF-002.')
    assert number_of('Untouched Fixture') == 'MNF-002'

    # Typed: the exact number is used.
    modal = open_add_form(admin, base)
    choose(modal.locator('#reqCategory'), CATEGORY)
    expect(number).to_have_value('MNF-003')
    number.fill('MNF-CUSTOM-9')
    alert = approve(admin, modal, 'Typed number', 'Typed Fixture')
    expect(alert).to_contain_text('Generated document number: MNF-CUSTOM-9.')
    assert number_of('Typed Fixture') == 'MNF-CUSTOM-9'

    # Typed but already in use: rejected, nothing is created and the staged file is removed.
    files_before = sql('SELECT COUNT(*) FROM files')
    modal = open_add_form(admin, base)
    choose(modal.locator('#reqCategory'), CATEGORY)
    number.fill('mnf-001')
    alert = approve(admin, modal, 'Duplicate number', 'Duplicate Fixture')
    expect(alert).to_contain_text('That document number is already in use.')
    assert number_of('Duplicate Fixture') == ''
    assert sql('SELECT COUNT(*) FROM files') == files_before

    # Without the edited flag the server ignores a posted value and assigns the next number.
    csrf = next(cookie['value'] for cookie in admin.context.cookies() if cookie['name'] == 'pk_csrf_cookie')
    response = admin.request.post(base + '/documents/softcopy/direct', max_redirects=0, multipart={
        'pk_csrf_token': csrf, 'confirmed': 'yes', 'type': 'softcopy_create', 'subject': 'Posted preview',
        'title': 'Posted Fixture', 'category_id': str(CATEGORY), 'new_revision_level': '00',
        'generated_document_number': 'MNF-IGNORED-1', 'revision_attachment': FIXTURE})
    assert response.status in (302, 303), response.status
    assert number_of('Posted Fixture') == 'MNF-003'

    # A request never offers the edit: the field is read-only and has no edited flag.
    admin.goto(base + '/my-requests')
    settle(admin)
    admin.get_by_role('button', name='New Request').click()
    request = admin.locator('#editModal')
    request.locator('#requestActionPicker').select_option('softcopy_create')
    expect(request.locator('#generatedDocumentNumber')).not_to_be_editable()
    expect(request.locator('[name=document_number_edited]')).to_have_count(0)


def main():
    sql(f'CREATE DATABASE {DATABASE} CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci', database=None)
    server = None
    first_test_file = None
    try:
        sql((ROOT / 'database/pk_dts.sql').read_text(encoding='utf-8'))
        sql((ROOT / 'database/migrations/20261112_file_lifecycle_stage.sql').read_text(encoding='utf-8'))
        sql((ROOT / 'database/migrations/20261118_softcopy_revision_template.sql').read_text(encoding='utf-8'))
        password_hash = subprocess.check_output(
            [PHP, '-r', f"echo password_hash('{PASSWORD}', PASSWORD_DEFAULT);"], text=True)
        sql(f"""
            INSERT INTO users (id,username,first_name,last_name,position_title,role_id,password_hash,require_password_change)
            VALUES (111,'number_admin','Number','Fixture','Administrator',1,'{password_hash}',0);
            INSERT INTO categories (id,parent_id,name,folder_name,category_type,code_pattern,description,active,created_by)
            VALUES ({CATEGORY},NULL,'Manual Number Fixture','Manual Number Fixture','pattern','MNF-001','Fixture',1,111);
        """)
        # Only files uploaded by this test are removed from storage afterwards.
        first_test_file = int(sql('SELECT COALESCE(MAX(id),0) FROM files')) + 1
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
        print('Softcopy manual document number browser workflow passed.')
    finally:
        if server:
            server.terminate()
            server.wait()
        try:
            if first_test_file:
                for storage in sql(f'SELECT storage_name FROM files WHERE id>={first_test_file}').splitlines():
                    if re.fullmatch('[a-f0-9]{64}', storage):
                        (ROOT / 'storage/documents' / storage).unlink(missing_ok=True)
        finally:
            sql(f'DROP DATABASE {DATABASE}', database=None)


if __name__ == '__main__':
    main()
