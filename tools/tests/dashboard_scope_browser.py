"""Check dashboard counts and linked registers using a disposable database."""
import json
from pathlib import Path
import sys

from playwright.sync_api import expect

sys.path.insert(0, str(Path(__file__).resolve().parents[1] / 'documentation'))
from capture_screenshots import PASSWORD, demo_site, insert, sql


def add_approval(reference, kind, requester, assignment, assignee=None, node='review'):
    request = insert('requests', reference=reference, type=kind, requested_by=requester,
                     payload={'subject': reference}, status='submitted', current_node='review')
    insert('workflow_steps', request_id=request, node_key=node, label='Review',
           assignment=assignment, assigned_user_id=assignee, candidates=[], status='active')


def seed_scope_cases():
    add_approval('SCOPE-ROLE-TRANSFER', 'transfer', 1, {'type': 'role', 'value': 5})
    add_approval('SCOPE-STALE', 'softcopy_create', 1, {'type': 'user', 'value': 6}, 6, 'old')
    add_approval('SCOPE-UNSUPPORTED', 'obsolete_type', 6, {'type': 'user', 'value': 6}, 6)
    add_approval('SCOPE-AUDITOR-TASK', 'softcopy_create', 1, {'type': 'user', 'value': 4}, 4)
    insert('requests', reference='SCOPE-LEGACY-PENDING', type='softcopy_create',
           requested_by=6, payload={}, status='pending')
    insert('requests', reference='SCOPE-AUDITOR-PRIVATE', type='softcopy_create', requested_by=4, payload={})
    insert('external_documents', title='Leader External Reference', category_id=1, created_by=6, status='disposed')
    scanned_category = sql("SELECT id FROM scanned_categories WHERE name = 'Sample Scanned Records'")
    for title, owner, status in [('Leader Scanned Record', 6, 'active'),
                                 ('Admin Scanned Record', 1, 'active'),
                                 ('Deleted Scanned Record', 6, 'deleted')]:
        insert('scanned_documents', title=title, category_id=int(scanned_category),
               retention_start_date='2026-11-01', retention_end_date='2027-11-01',
               created_by=owner, approved_by=2, status=status)
    sql("""DELETE rp FROM role_permissions rp
        JOIN permissions p ON p.id = rp.permission_id
        WHERE rp.role_id = 4 AND NOT (p.module_key = 'dashboard' AND p.action_key = 'view');
        INSERT IGNORE INTO role_permissions (role_id, permission_id)
        SELECT 4, id FROM permissions WHERE module_key = 'dashboard' AND action_key = 'view';
        DELETE rp FROM role_permissions rp
        JOIN permissions p ON p.id = rp.permission_id
        WHERE rp.role_id = 5 AND (
            (p.module_key = 'documents' AND p.action_key IN ('access_all', 'view_all')) OR
            (p.module_key = 'backups' AND p.action_key = 'view')
        );
        INSERT IGNORE INTO role_permissions (role_id, permission_id)
        SELECT 5, id FROM permissions
        WHERE module_key IN ('external_documents', 'scanned_documents') AND action_key = 'view';
        UPDATE transfers SET recipient_id = 4 WHERE status = 'in_transit';""")


def signed_in(browser, base, account):
    context = browser.new_context(service_workers='block', reduced_motion='reduce')
    page = context.new_page()
    page.goto(base + 'login')
    page.locator('[name=login]').fill('demo-' + account)
    page.locator('[name=password]').fill(PASSWORD)
    page.locator('button[type=submit]').click()
    page.wait_for_url('**/dashboard')
    return page


def metric(page, key, value):
    expect(page.locator(f'.dashboard-page [data-metric="{key}"]')).to_have_text(str(value), timeout=3000)


def follow_count(page, link_pattern, total):
    href = page.locator(f'.dashboard-page a[href*="{link_pattern}"]').get_attribute('href')
    assert page.goto(href).ok
    expect(page.locator('[data-table-results]')).to_contain_text(f'of {total} records')
    return page.locator('[data-table-results]')


def check_document_register(page, kind, total):
    row = page.locator('.dashboard-page tr').filter(has=page.locator(f'[data-document-kind={kind}]'))
    expect(row.locator('td').first).to_have_text(str(total))
    chart = page.locator('.dashboard-page [data-chart-type=doughnut]')
    labels = json.loads(chart.get_attribute('data-chart-labels'))
    counts = json.loads(chart.get_attribute('data-chart-counts'))
    assert dict(zip(labels, counts))[kind.capitalize()] == total
    metric(page, 'documents', sum(counts))
    expect(chart).to_be_visible()
    return follow_count(page, f'documents/{kind}?', total)


def accept_assigned_handoff(page, base):
    transfer_id = sql("SELECT id FROM transfers WHERE recipient_id = 4 AND status = 'in_transit'")
    csrf = next(cookie['value'] for cookie in page.context.cookies() if cookie['name'] == 'pk_csrf_cookie')
    response = page.request.post(base + 'my-tasks/hardcopy-transfer/accept', form={
        'id': transfer_id, 'confirmed': 'yes', 'pk_csrf_token': csrf
    }, max_redirects=0)
    assert response.status in (302, 303)
    assert sql('SELECT status FROM transfers WHERE id = ' + transfer_id) == 'accepted'


def run():
    with demo_site() as (browser, base):
        seed_scope_cases()
        leader = signed_in(browser, base, 'leader')
        metric(leader, 'pending_tasks', 2)
        metric(leader, 'transfers', 1)
        expect(leader.locator('#appSidebar a[href$="my-tasks"] .nav-count')).to_have_text('3')
        request_chart = leader.locator('.dashboard-page [data-chart-type=bar]')
        labels = json.loads(request_chart.get_attribute('data-chart-labels'))
        counts = json.loads(request_chart.get_attribute('data-chart-counts'))
        assert dict(zip(labels, counts)) == {'Pending': 1, 'Approved': 1}, (labels, counts)

        approvals = follow_count(leader, 'status=submitted', 2)
        expect(approvals).to_contain_text('DEMO-2026-002')
        expect(approvals).to_contain_text('SCOPE-ROLE-TRANSFER')
        expect(approvals).not_to_contain_text('SCOPE-STALE')
        leader.goto(base + 'dashboard')
        handoffs = follow_count(leader, 'status=handoffs', 1)
        expect(handoffs).not_to_contain_text('SCOPE-ROLE-TRANSFER')
        expect(handoffs).to_contain_text('DEMO-TRANSFER-')
        leader.goto(base + 'dashboard')
        expect(leader.locator('.dashboard-page a[href$="admin/backups"]')).to_have_count(0)
        external = check_document_register(leader, 'external', 1)
        expect(external).to_contain_text('Leader External Reference')
        expect(external).not_to_contain_text('Sample Supplier Reference')
        leader.goto(base + 'dashboard')
        scanned = check_document_register(leader, 'scanned', 1)
        expect(scanned).to_contain_text('Leader Scanned Record')
        expect(scanned).not_to_contain_text('Admin Scanned Record')
        expect(scanned).not_to_contain_text('Deleted Scanned Record')

        auditor = signed_in(browser, base, 'auditor')
        metric(auditor, 'pending_tasks', 1)
        metric(auditor, 'transfers', 1)
        expect(auditor.locator('#appSidebar a[href$="my-tasks"] .nav-count')).to_have_text('2')
        expect(auditor.locator('.dashboard-page a[href$="my-requests"]')).to_have_count(0)
        expect(auditor.locator('.dashboard-page [data-chart-type=bar]')).to_have_count(0)
        expect(auditor.locator('.dashboard-page [data-document-kind=external]')).to_have_count(0)
        expect(auditor.locator('.dashboard-page [data-document-kind=scanned]')).to_have_count(0)
        expect(auditor.locator('.dashboard-page a[href$="admin/backups"]')).to_have_count(0)
        receipt = follow_count(auditor, 'status=handoffs', 1)
        expect(receipt).to_contain_text('In Transit')
        accept_assigned_handoff(auditor, base)
        auditor.goto(base + 'dashboard')
        expect(auditor.locator('.dashboard-page [data-metric=transfers]')).to_have_count(0)

        admin = signed_in(browser, base, 'admin')
        expect(admin.locator('.dashboard-page [data-chart-type=bar]')).to_have_count(1)
        assert 'Pending' not in admin.locator('[data-chart-type=bar]').get_attribute('data-chart-labels')
        external = check_document_register(admin, 'external', 2)
        expect(external).to_contain_text('Sample Supplier Reference')
        expect(external).to_contain_text('Leader External Reference')
        admin.goto(base + 'dashboard')
        scanned = check_document_register(admin, 'scanned', 2)
        expect(scanned).to_contain_text('Leader Scanned Record')
        expect(scanned).to_contain_text('Admin Scanned Record')
        expect(scanned).not_to_contain_text('Deleted Scanned Record')
        admin.goto(base + 'dashboard')
        admin.locator('.dashboard-page').get_by_role('link', name='Backup & Restore').click()
        admin.wait_for_url('**/admin/backups')
        expect(admin.get_by_role('heading', name='Backup & Restore')).to_be_visible()
    print('Dashboard counts and links match authorized document, backup, request and task access.')


if __name__ == '__main__':
    run()
