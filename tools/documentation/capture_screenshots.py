"""Capture current PK DTS screens with synthetic records in a disposable site.

Run: python -X utf8 tools/documentation/capture_screenshots.py
To refresh only some screens: set PK_CAPTURE_ONLY=files,review-file first (supported: those two).
Requires local PHP/MySQL and Playwright Chromium/Edge. Only DDL and allowlisted
static role/permission/settings seeds are read from database/pk_dts.sql. Company
records, source database contents, private files and existing sessions are unused.
The generated *_documentation_test database and temporary site are removed.
"""
from pathlib import Path
import hashlib
import json
import os
import re
import secrets
import shutil
import socket
import subprocess
import tempfile
import time
from urllib.request import urlopen

import pymupdf
from playwright.sync_api import sync_playwright, expect

ROOT = Path(__file__).resolve().parents[2]
OUTPUT = Path(__file__).resolve().parent / 'screenshots'
PHP = os.environ.get('TEST_PHP', r'C:\xampp\php\php.exe')
MYSQL = os.environ.get('TEST_MYSQL', r'C:\xampp\mysql\bin\mysql.exe')
DATABASE = 'pk_dts_' + secrets.token_hex(6) + '_documentation_test'
PASSWORD = secrets.token_urlsafe(24)
ENV = dict(os.environ, PK_TEST_DB='1', DB_DATABASE=DATABASE,
           PK_CAPTURE_PASSWORD=PASSWORD, PK_BACKUP_SIGNING_KEY=secrets.token_hex(32),
           PK_BACKUP_PASSWORD=secrets.token_urlsafe(24))
if os.environ.get('DB_PASSWORD'):
    ENV['MYSQL_PWD'] = os.environ['DB_PASSWORD']
MYSQL_ARGS = [MYSQL, '-h', os.environ.get('DB_HOST', '127.0.0.1'), '-u',
              os.environ.get('DB_USERNAME', 'root'), '-P', os.environ.get('DB_PORT', '3306'), '-N', '-B']


def sql(statement, database=DATABASE):
    if database:
        assert re.fullmatch(r'pk_dts_[0-9a-f]+_documentation_test', database)
    result = subprocess.run(MYSQL_ARGS + ([database] if database else []),
                            input=statement.encode(), capture_output=True, env=ENV)
    if result.returncode:
        raise RuntimeError(result.stderr.decode(errors='replace'))
    return result.stdout.decode().strip()


def literal(value):
    if value is None:
        return 'NULL'
    if isinstance(value, (int, float)):
        return str(value)
    if isinstance(value, (dict, list)):
        value = json.dumps(value)
    if value == '':
        return "''"
    return 'CONVERT(0x' + str(value).encode().hex() + ' USING utf8mb4)'


def insert(table, **fields):
    return int(sql('INSERT INTO `' + table + '` (`' + '`,`'.join(fields) + '`) VALUES (' +
                   ','.join(literal(value) for value in fields.values()) + '); SELECT LAST_INSERT_ID();'))


def seed(site):
    source = (ROOT / 'database/pk_dts.sql').read_text(encoding='utf-8')
    ddl = re.findall(r'^(?:CREATE TABLE|ALTER TABLE)\s+`[^`]+`.*?;\s*$', source, re.M | re.S)
    assert ddl and all(statement.startswith(('CREATE TABLE', 'ALTER TABLE')) for statement in ddl)
    ddl = [re.sub(r'AUTO_INCREMENT=\d+', 'AUTO_INCREMENT=1', statement) for statement in ddl]
    sql('SET FOREIGN_KEY_CHECKS=0;\n' + '\n'.join(ddl) + '\nSET FOREIGN_KEY_CHECKS=1;')
    static = re.findall(r'^INSERT INTO `(roles|permissions|role_permissions|settings)`\s+.*?;\s*$', source, re.M | re.S)
    assert set(static) == {'roles', 'permissions', 'role_permissions', 'settings'}
    for statement in re.finditer(r'^INSERT INTO `(?:roles|permissions|role_permissions|settings)`\s+.*?;\s*$', source, re.M | re.S):
        sql(statement.group(0))
    sql((ROOT / 'database/migrations/20261013_user_role_assignments.sql').read_text())
    hashed = subprocess.run([PHP, '-r', "echo password_hash(getenv('PK_CAPTURE_PASSWORD'), PASSWORD_DEFAULT);"],
                            env=ENV, capture_output=True, check=True).stdout.decode()
    people = [('admin', 'Alex', 'Administrator', 1), ('controller', 'Casey', 'Controller', 2),
              ('manager', 'Morgan', 'Manager', 3), ('auditor', 'Avery', 'Auditor', 4),
              ('staff', 'Sam', 'Staff', 5)]
    for username, first, last, role in people:
        insert('users', username='demo-' + username, first_name=first, last_name=last,
               position_title='Documentation Example', role_id=role, password_hash=hashed,
               require_password_change=0)
    sql('UPDATE users SET leader_id=1 WHERE id=5')
    area = insert('areas', name='Sample Office')
    specific = insert('specifics', name='Records Section', area_id=area)
    location = insert('locations', name='Records Cabinet A', sequence_number=1, area_id=area, specific_id=specific)
    insert('locations', name='Records Cabinet B', sequence_number=2, area_id=area, specific_id=specific)
    category = insert('categories', name='Quality Procedures', folder_name='Quality Procedures',
                      code_pattern='QP-001', created_by=1)
    external_category = insert('external_categories', name='Supplier References',
                               folder_name='Supplier References', created_by=1)
    for title in ['Sample Inspection Record', 'Sample Dispatch Record', 'Sample Incoming Record']:
        insert('hardcopy_documents', title=title, area_id=area, specific_id=specific,
               location_id=location, holder_id=1, created_by=1, creation_source='direct',
               creation_reason='Synthetic documentation example', retention_enabled=1,
               retention_start_date='2026-01-01', retention_end_date='2027-01-01')
    document = pymupdf.open()
    page = document.new_page()
    page.insert_text((60, 80), 'Sample Quality Procedure', fontsize=22)
    page.insert_text((60, 120), 'Documentation example only. No company information.')
    data = document.tobytes()
    document.close()
    files = []
    for domain, status in [('softcopy', 'approved'), ('external', 'approved'), ('softcopy', 'pending')]:
        storage = secrets.token_hex(32)
        (site / 'storage/documents' / storage).write_bytes(data)
        files.append(insert('files', original_name='Sample Quality Procedure.pdf', storage_name=storage,
                            size=len(data), mime_type='application/pdf', fingerprint=hashlib.sha256(data).hexdigest(),
                            extension='pdf', purpose='creation', domain=domain,
                            document_id=1 if status == 'approved' else None,
                            uploaded_by=1 if status == 'approved' else 5,
                            approved_by=1 if status == 'approved' else None, status=status))
    insert('softcopy_documents', document_number='QP-001', series_number=None, title='Sample Quality Procedure',
           category_id=category, created_by=1, creation_source='direct', creation_reason='Documentation example')
    revision = insert('softcopy_revisions', document_id=1, revision_number=1, reason='Initial controlled issue',
                      effective_date='2026-11-01', page_number=1, series_number=None,
                      document_title='Sample Quality Procedure', new_revision_level='00',
                      new_effective_date='2026-11-01', date_received='2026-11-01', date_released='2026-11-01',
                      approval_date='2026-11-01', file_id=files[0], uploaded_by=1, approved_by=1)
    sql('UPDATE softcopy_documents SET current_revision_id=' + str(revision))
    insert('external_documents', title='Sample Supplier Reference', description='Synthetic external reference for training.',
           category_id=external_category, created_by=1)
    revision = insert('external_revisions', document_id=1, revision_number=1, revision_level='00',
                      description='Initial reference issue', file_id=files[1], uploaded_by=1, approved_by=1)
    sql('UPDATE external_documents SET current_revision_id=' + str(revision))
    payload = dict(subject='Issue a new quality procedure', title='Sample Revised Procedure', category_id=category,
                   controlled_file_id=files[2], remarks='Synthetic training request')
    leader = {'name': 'Noted By', 'approver': {'type': 'requester_leader', 'value': None, 'label': "Requester's leader"}}
    manager = {'name': 'Plant Manager Review & Approval', 'approver': {'type': 'role', 'value': 3, 'label': 'Plant Manager'}}
    controller = {'name': 'Document Control Officer Review & Approval', 'approver': {'type': 'role', 'value': 2, 'label': 'Document Control Officer'}}
    requester = {'name': 'Requester Confirmation', 'approver': {'type': 'requester', 'value': None, 'label': 'Requester account'}}
    for kind in ['softcopy_create', 'softcopy_revise', 'external_create', 'external_revise',
                 'transfer', 'access', 'assignment', 'disposal']:
        route = [leader, manager, controller] if kind.startswith(('softcopy_', 'external_')) else [manager, controller, leader if kind == 'assignment' else requester]
        graph = {'steps': [dict(step, key='step_' + str(index+1)) for index, step in enumerate(route)]}
        if kind.startswith(('softcopy_', 'external_')):
            graph['document_lock_step_key'] = 'step_2'  # Plant Manager approval locks the file
        workflow = insert('workflows', workflow_key=kind, name=kind.replace('_', ' ').title(),
                          request_type=kind, active=1, created_by=1)
        insert('workflow_versions', workflow_id=workflow, version_number=1, status='published',
               is_default=1, graph=graph, created_by=1, published_at='2026-11-01 09:00:00')

    graph['document_lock_step_key'] = 'step_2'
    insert('requests', reference='DEMO-2026-001', type='softcopy_create', requested_by=1, payload=payload)
    request = insert('requests', reference='DEMO-2026-002', type='softcopy_create', requested_by=5,
                     payload=payload, status='submitted', workflow_version_id=1, snapshot=graph,
                     current_node='step_1', submitted_at='2026-11-05 09:00:00')
    insert('workflow_steps', request_id=request, node_key='step_1', label='Noted By',
           assignment={'type': 'requester_leader', 'value': None, 'label': "Requester's leader"}, candidates=[],
           assigned_user_id=1, assigned_name='Alex Administrator', assigned_position='Documentation Example', status='active')
    insert('workflow_steps', request_id=request, node_key='step_2', label='Plant Manager Review & Approval',
           assignment={'type': 'role', 'value': 3, 'label': 'Plant Manager'}, candidates=[], status='pending')
    insert('workflow_steps', request_id=request, node_key='step_3', label='Document Control Officer Review & Approval',
           assignment={'type': 'role', 'value': 2, 'label': 'Document Control Officer'}, candidates=[], status='pending')
    insert('notifications', user_id=1, title='Sample request ready for review',
           message='A synthetic training request is awaiting your review.', request_id=request)
    for hardcopy, requester, holder, recipient, status in [(2, 1, 1, 5, 'for_transfer'), (3, 5, 5, 1, 'in_transit')]:
        req = insert('requests', reference='DEMO-TRANSFER-' + str(hardcopy), type='transfer', hardcopy_id=hardcopy,
                     requested_by=requester, payload={'subject': 'Move sample record', 'recipient_id': recipient},
                     status='approved')
        insert('transfers', request_id=req, hardcopy_id=hardcopy, origin={'location_name': 'Records Cabinet A'},
               destination={'location_id': 2, 'location_name': 'Records Cabinet B'}, current_holder_id=holder,
               recipient_id=recipient, document_copy_number='', reason='Synthetic training handoff', status=status)
    return request


def capture(page, base, review_request):
    OUTPUT.mkdir(parents=True, exist_ok=True)
    manifest = {}
    control_labels = json.loads((OUTPUT.parent / 'capture_controls.json').read_text())
    # PK_CAPTURE_ONLY=files,review-file refreshes just those screens and merges them into the manifest.
    only = {name for name in os.environ.get('PK_CAPTURE_ONLY', '').split(',') if name}

    def goto(route):
        response = page.goto(base + route)
        assert response and response.status == 200, (route, response.status if response else None)
        page.wait_for_load_state('networkidle')
        for selector in ['#appAlertModal', '#taskNotificationModal', '#retentionNotificationModal']:
            modal = page.locator(selector)
            if modal.is_visible():
                dismiss = modal.locator('[data-bs-dismiss="modal"]').first
                if dismiss.count():
                    dismiss.click()
        page.wait_for_timeout(250)
        assert not page.get_by_text('A Database Error Occurred', exact=True).is_visible(), route

    def shot(identifier, caption, selector=None, controls=()):
        if only and identifier not in only:
            return
        target = page.locator(selector) if selector else page
        if selector:
            expect(target).to_be_visible()
        page.wait_for_timeout(200)
        bounds = target.bounding_box() if selector else {'x': 0, 'y': 0}
        marks = []
        for label, locator in controls:
            item = page.locator(locator).first
            if item.count() and item.is_visible():
                box = item.bounding_box()
                marks.append(dict(label=label, x=round(box['x']-bounds['x'], 1),
                                  y=round(box['y']-bounds['y'], 1), width=round(box['width'], 1),
                                  height=round(box['height'], 1)))
        scope = page.locator(selector) if selector else page
        for label in control_labels[identifier]:
            if len(marks) >= 3:
                break
            if any(''.join(mark['label'].lower().split()) == ''.join(label.lower().split()) for mark in marks):
                continue
            candidates = scope.get_by_text(label, exact=True)
            for index in range(candidates.count()):
                candidate = candidates.nth(index)
                if candidate.is_visible():
                    box = candidate.bounding_box()
                    if (box and box['x'] >= bounds['x'] and box['y'] >= bounds['y'] and
                            box['x']+box['width'] <= 1440 and box['y']+box['height'] <= 900):
                        marks.append(dict(label=label, x=round(box['x']-bounds['x'], 1),
                                          y=round(box['y']-bounds['y'], 1), width=round(box['width'], 1),
                                          height=round(box['height'], 1)))
                        break
        assert 1 <= len(marks) <= 3, (identifier, control_labels[identifier])
        filename = identifier + '.png'
        target.screenshot(path=str(OUTPUT / filename), animations='disabled')
        image = pymupdf.Pixmap(str(OUTPUT / filename))
        assert image.width > 400 and image.height > 200
        assert all(mark['x'] >= 0 and mark['y'] >= 0 and
                   mark['x']+mark['width'] <= image.width+1 and
                   mark['y']+mark['height'] <= image.height+1 for mark in marks), identifier
        manifest[identifier] = dict(file=filename, route=page.url.removeprefix(base), caption=caption,
                                    width=image.width, height=image.height, controls=marks)
        print('Captured ' + identifier, flush=True)

    if only:
        # Refresh only these screens; everything else in the manifest is kept as is.
        goto('login')
        page.locator('[name="login"]').fill('demo-admin')
        page.locator('[name="password"]').fill(PASSWORD)
        page.locator('button[type="submit"]').click()
        page.wait_for_url('**/dashboard')
        if 'files' in only:
            goto('documents/softcopy?layout=table&folder=all')
            page.get_by_role('button', name='View', exact=True).first.click()
            page.get_by_role('tab', name='Document Files', exact=True).click()
            page.add_style_tag(content='.doc-modal-file-pdf{height:150px !important}')
            shot('files', 'Preview the current file in View Details. Download appears when the file is unlocked.', '#viewModal .modal-content')
        if 'review-file' in only:
            goto('my-tasks/softcopy?period=all')
            page.get_by_role('button', name='View', exact=True).first.click()
            page.get_by_role('tab', name='Document File', exact=True).click()
            page.add_style_tag(content='.doc-modal-file-pdf{height:260px !important}')
            shot('review-file', 'Open the Document File tab in View Details to preview the proposed attachment before deciding.', '#viewModal .modal-content')
        if 'workflow-tasks' in only:
            # Extra screen for the request-workflow deck; kept outside the guide manifest.
            only.discard('workflow-tasks')
            page.set_viewport_size({'width': 1700, 'height': 900})  # wide enough to show every row action
            goto('my-tasks/softcopy?period=all')
            extra = OUTPUT / 'workflow'
            extra.mkdir(exist_ok=True)
            marks = {}
            for label in ['Approve', 'Return', 'Reject']:
                box = page.get_by_role('button', name=label, exact=True).first.bounding_box()
                marks[label] = {key: round(box[key], 1) for key in ('x', 'y', 'width', 'height')}
            page.screenshot(path=str(extra / 'my-tasks.png'), animations='disabled')
            (extra / 'manifest.json').write_text(json.dumps({'my-tasks': {'file': 'my-tasks.png', 'width': 1700,
                                                 'height': 900, 'controls': marks}}, indent=2) + '\n', encoding='utf-8')
            print('PASS: refreshed workflow-tasks')
            if not only:
                return
        assert set(manifest) == only, (only-set(manifest), set(manifest)-only)
        manifest = {**json.loads((OUTPUT / 'manifest.json').read_text(encoding='utf-8')), **manifest}
        (OUTPUT / 'manifest.json').write_text(json.dumps(manifest, indent=2) + '\n', encoding='utf-8')
        print('PASS: refreshed ' + ', '.join(sorted(only)))
        return

    def form(identifier, route, button, caption, kind=None):
        goto(route)
        page.locator('[data-title="' + button + '"]').click()
        expect(page.locator('#editModal')).to_be_visible()
        if kind:
            page.locator('#reqType').select_option(kind)
        if identifier == 'transfer-request':
            page.locator('#transferSource').select_option('1', force=True)
            page.locator('#transfer-location').select_option('2', force=True)
            page.locator('#transferRecipient').select_option('5', force=True)
            page.locator('#transferSubject').fill('Move sample record to Records Cabinet B')
            page.locator('#editModal .modal-body').evaluate('(element) => element.scrollTop=element.scrollHeight')
        shot(identifier, caption, '#editModal .modal-content',
             [('Action', '#reqType'), ('Document title', '#reqTitle, #externalTitle, [name="title"]'),
              ('Save', '#editForm button[type="submit"]')])

    goto('login')
    shot('sign-in', 'Sign in using your own account.', controls=[('Username', '[name="login"]'), ('Password', '[name="password"]')])
    page.locator('[name="login"]').fill('demo-admin')
    page.locator('[name="password"]').fill(PASSWORD)
    page.locator('button[type="submit"]').click()
    page.wait_for_url('**/dashboard')
    goto('dashboard')
    for identifier in ['welcome', 'navigation', 'help']:
        shot(identifier, 'Overview and the role-based navigation menu.', controls=[('Document workspaces', '#appSidebar'), ('Account menu', '.user-menu, .topbar-user')])
    goto('admin/roles')
    shot('roles', 'The five standard roles and their permissions.', controls=[('Administrator', 'tbody tr:first-child td:first-child')])
    page.get_by_role('button', name='Edit', exact=True).first.click()
    shot('permissions', 'Review the permissions assigned to a role.', '#editModal .modal-content')
    goto('documents/hardcopy?layout=table&folder=all&period=all')
    shot('find-documents', 'Search, filter, and inspect records in the document register.')
    page.get_by_role('button', name='View', exact=True).first.click()
    shot('hardcopy-track', 'Inspect storage, custody, and history in View Details.', '#viewModal .modal-content')
    form('hardcopy-create', 'documents/hardcopy?layout=table&folder=all', 'Add New Hardcopy Document', 'Register a hardcopy directly.')
    retention = page.locator('[name="retention_enabled"]')
    retention.check()
    shot('retention', 'Enable retention tracking and specify the storage period.', '#editModal .modal-content')
    goto('documents/hardcopy?layout=table&folder=all')
    page.get_by_role('button', name='Edit', exact=True).first.click()
    shot('hardcopy-edit', 'Update an existing hardcopy record.', '#editModal .modal-content')
    form('softcopy-direct-create', 'documents/softcopy?layout=table&folder=all', 'Add New Softcopy Document', 'Create a controlled softcopy directly.')
    page.locator('#reqCategory').select_option('1', force=True)
    page.locator('[name="series_number_checker"]').check()
    page.locator('#reqNumber').select_option('QP-001', force=True)
    page.locator('#reqSeriesPattern').fill('001')
    expect(page.locator('#reqOriginalSeries')).to_have_value('000')
    expect(page.locator('#reqSeries')).to_have_value('001')
    shot('softcopy-numbering', 'The first Series No. Code Pattern is configured per Document No.', '#editModal .modal-content')
    goto('documents/softcopy?layout=table&folder=all')
    page.get_by_role('button', name='Edit', exact=True).first.click()
    shot('softcopy-direct-revise', 'Create a direct revision of the selected softcopy.', '#editModal .modal-content')
    for action in ['create', 'revise']:
        form('softcopy-request-' + action, 'my-requests/softcopy', 'New Request',
             'Prepare a softcopy ' + action + ' request.', 'softcopy_' + action)
    form('external-direct-create', 'documents/external?layout=table&folder=all', 'Add New External Document', 'Register an external document directly.')
    goto('documents/external?layout=table&folder=all')
    page.get_by_role('button', name='Edit', exact=True).first.click()
    shot('external-direct-revise', 'Revise an existing external document.', '#editModal .modal-content')
    for action in ['create', 'revise']:
        form('external-request-' + action, 'my-requests/external', 'New Request',
             'Prepare an external document ' + action + ' request.', 'external_' + action)
    goto('documents/softcopy?layout=table&folder=all')
    page.get_by_role('button', name='View', exact=True).first.click()
    page.get_by_role('tab', name='Document Files', exact=True).click()
    page.add_style_tag(content='.doc-modal-file-pdf{height:150px !important}')
    shot('files', 'Preview the current file in View Details. Download appears when the file is unlocked.', '#viewModal .modal-content')
    goto('my-tasks/softcopy?period=all')
    page.get_by_role('button', name='View', exact=True).first.click()
    page.get_by_role('tab', name='Document File', exact=True).click()
    page.add_style_tag(content='.doc-modal-file-pdf{height:260px !important}')
    shot('review-file', 'Open the Document File tab in View Details to preview the proposed attachment before deciding.', '#viewModal .modal-content')
    goto('my-requests/softcopy?period=all')
    shot('request-lifecycle', 'A saved draft remains in My Requests until submitted.')
    goto('my-tasks/softcopy?period=all')
    page.get_by_role('button', name='Approve', exact=True).first.click()
    shot('approvals', 'Review the selected approval decision before confirming.',
         '#confirmModal .modal-content', controls=[('Approval decision', '#confirmMessage'), ('Confirm', '#confirmProceed')])
    goto('my-tasks/softcopy?period=all')
    page.get_by_role('button', name='View', exact=True).first.click()
    page.get_by_role('tab', name='Approval Process Timeline', exact=True).click()
    shot('approval-routes', 'View Details includes the request approval process.', '#viewModal .modal-content')
    for identifier, route in [('transfer-request', 'hardcopy-transfer'), ('access-request', 'access-grant'),
                              ('assignment-request', 'document-assign'), ('disposal-request', 'hardcopy-disposal')]:
        form(identifier, 'my-requests/' + route, 'New Request', 'Prepare the request and save it as a draft.')
    goto('my-tasks/hardcopy-transfer?period=all')
    shot('transfer-dispatch', 'The requester dispatches an approved physical transfer.')
    shot('transfer-accept', 'The destination recipient accepts the received hardcopy.')
    goto('documents/hardcopy?layout=table&folder=all')
    page.get_by_role('button', name='Dispose', exact=True).first.click()
    shot('disposal-direct', 'Choose the disposal reason before confirming.', '#actionModal .modal-content')
    for identifier, route, caption in [
        ('direct-assignments', 'admin/document-assignments?domain=softcopy&folder=all', 'Manage direct document assignments.'),
        ('places', 'places/location', 'Maintain the places used by hardcopy storage.'),
        ('categories', 'places/softcopy-categories', 'Maintain controlled softcopy categories and numbering patterns.'),
        ('users', 'admin/users', 'Manage individual user accounts and assigned roles.'),
        ('workflows', 'admin/workflows', 'Maintain published approval routes in Workflow Builder.'),
        ('audit', 'admin/audit', 'Review recorded system actions.'),
        ('backups', 'admin/backups', 'Manage encrypted backup archives.')]:
        goto(route)
        if identifier in ['places', 'users']:
            page.locator('button.js-edit[data-record="{}"]').first.click()
            shot(identifier, caption, '#editModal .modal-content')
        elif identifier == 'direct-assignments':
            page.locator('[data-title="Assign Document"]').click()
            shot(identifier, caption, '#assignmentModal .modal-content')
        elif identifier == 'workflows':
            page.locator('[data-bs-target^="#workflowStepsModal-"]').first.click()
            shot(identifier, caption, '.modal.show .modal-content')
        else:
            shot(identifier, caption)
    page.locator('[data-bs-target="#backupRestoreModal"]').click()
    shot('restore', 'Choose the archive and enter the required password and confirmation.', '#backupRestoreModal .modal-content')
    goto('dashboard')
    page.locator('#notificationBellButton').click()
    shot('notifications', 'Open the bell to inspect your notification inbox.')
    expected = {item['id'] for item in json.loads((OUTPUT.parent / 'guide.json').read_text())['sections']
                if item.get('kind') != 'approval-reference'}
    assert set(manifest) == expected, (expected-set(manifest), set(manifest)-expected)
    (OUTPUT / 'manifest.json').write_text(json.dumps(manifest, indent=2) + '\n', encoding='utf-8')
    print(f'PASS: {len(manifest)} current guide screens captured from synthetic records.')


def main():
    server = None
    created = False
    temp = Path(tempfile.mkdtemp(prefix='pk-dts-documentation-')).resolve()
    try:
        site = temp / 'site'
        ENV['PK_BACKUP_DIR'] = str(site / 'storage/backups')
        for name in ['application', 'public', 'vendor']:
            shutil.copytree(ROOT / name, site / name)
        (site / 'storage/documents').mkdir(parents=True)
        sql('CREATE DATABASE `' + DATABASE + '` CHARACTER SET utf8mb4', None)
        created = True
        review_request = seed(site)
        router = temp / 'router.php'
        router.write_text("<?php $p=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH); if(is_file(getcwd().'/public'.$p))return false; $_SERVER['SCRIPT_NAME']='/public/index.php'; require getcwd().'/public/index.php';", encoding='utf-8')
        with socket.socket() as sock:
            sock.bind(('127.0.0.1', 0))
            port = sock.getsockname()[1]
        base = f'http://127.0.0.1:{port}/'
        with (temp / 'server.log').open('w', encoding='utf-8') as log:
            try:
                server = subprocess.Popen([PHP, '-S', '127.0.0.1:' + str(port), '-t', str(site / 'public'), str(router)],
                                          cwd=site, env=ENV, stdout=log, stderr=log,
                                          creationflags=getattr(subprocess, 'CREATE_NO_WINDOW', 0))
                for _ in range(100):
                    try:
                        if urlopen(base + 'login', timeout=1).status == 200:
                            break
                    except OSError:
                        time.sleep(.1)
                with sync_playwright() as playwright:
                    browser = playwright.chromium.launch(channel='msedge', headless=True)
                    context = browser.new_context(viewport={'width': 1440, 'height': 900},
                                                  device_scale_factor=1, service_workers='block')
                    context.set_default_timeout(12000)
                    capture(context.new_page(), base, review_request)
                    browser.close()
            finally:
                if server:
                    server.terminate()
                    server.wait(timeout=10)
                    server = None
    finally:
        if server:
            server.terminate()
            server.wait(timeout=10)
        try:
            if created:
                sql('DROP DATABASE `' + DATABASE + '`', None)
        finally:
            assert temp.parent == Path(tempfile.gettempdir()).resolve() and temp.name.startswith('pk-dts-documentation-')
            shutil.rmtree(temp)


if __name__ == '__main__':
    main()
