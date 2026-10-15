"""User assignment viewing uses the shared modal and the existing assignment flow."""
from playwright.sync_api import expect


def run_checks(page, base, sql):
    soft = int(sql('SELECT MIN(id) FROM softcopy_documents').strip().splitlines()[-1])
    revoked = int(sql('SELECT MAX(id) FROM softcopy_documents').strip().splitlines()[-1])
    sql(f"INSERT INTO assignments(softcopy_id,user_id,assigned_by,active) VALUES({soft},1,1,1)")
    sql(f"INSERT INTO assignments(softcopy_id,user_id,assigned_by,active) VALUES({revoked},1,1,0)")
    # More than a page, and records owned by another user must never leak in.
    for index in range(12):
        sql(f"INSERT INTO hardcopy_documents(sequence_number,title,holder_id,created_by,status) VALUES('ASSIGN-{index:02}','Assigned document {index:02}',1,1,'active')")
    sql("INSERT INTO hardcopy_documents(sequence_number,title,holder_id,created_by,status) VALUES('OTHER-USER','Other user private record',2,1,'active'),('DISPOSED-USER','Disposed record',1,1,'disposed')")
    page.goto(base + 'admin/users')
    row = page.locator('tr', has=page.locator('td', has_text='browser-admin')).first
    pattern = '**/admin/users/1/assigned-documents'
    page.route(pattern, lambda route: route.abort(), times=1)
    row.get_by_role('button', name='Assigned Documents', exact=True).click()
    modal = page.locator('#viewModal')
    expect(modal).to_be_visible()
    expect(modal.get_by_text('Unable to load assigned documents. Please try again.', exact=True)).to_be_visible()
    modal.get_by_role('button', name='Retry', exact=True).click()
    expect(modal.locator('#viewModalTitle')).to_have_text('Browser Administrator')
    expect(modal.get_by_role('tab', name='Hardcopy (14)', exact=True)).to_be_visible()
    expect(modal.locator('.doc-assignment-item:visible')).to_have_count(10)
    modal.get_by_role('button', name='Next documents').click()
    expect(modal.locator('.doc-assignment-item:visible')).to_have_count(4)
    search = modal.get_by_role('searchbox', name='Search Hardcopy documents')
    search.fill('ASSIGN-11')
    expect(modal.locator('.doc-assignment-item:visible')).to_have_count(1)
    expect(modal.locator('.doc-assignment-item:visible')).to_contain_text('ASSIGN-11')
    search.fill('Other user')
    expect(modal.get_by_text('No documents match your search.', exact=True)).to_be_visible()
    search.fill('Disposed record')
    expect(modal.get_by_text('No documents match your search.', exact=True)).to_be_visible()
    modal.get_by_role('tab', name='Softcopy (1)', exact=True).click()
    expect(modal.locator('.doc-assignment-item:visible')).to_have_count(1)
    link = modal.get_by_role('link', name='Manage assignments', exact=True).filter(visible=True)
    expect(link).to_have_attribute('href', base + 'admin/document-assignments?domain=softcopy&folder=all&owner=1')
    link.click()
    page.get_by_role('button', name='Assign Document', exact=True).click()
    expect(page.locator('#assignmentUser')).to_have_value('1')
    page.locator('#assignmentSoftcopy').select_option(str(revoked), force=True)
    page.locator('#assignmentModal').get_by_role('button', name='Save Assignment', exact=True).click()
    page.locator('#confirmProceed').click()
    page.wait_for_load_state('load')
    assert sql(f'SELECT active FROM assignments WHERE softcopy_id={revoked} AND user_id=1').strip().splitlines()[-1] == '1'
    page.locator('.modal.show [data-bs-dismiss="modal"]').first.click() if page.locator('.modal.show').count() else None
    for width in [390, 320]:
        page.set_viewport_size({'width': width, 'height': 850})
        page.goto(base + 'admin/users')
        row = page.locator('tr', has=page.locator('td', has_text='browser-admin')).first
        row.get_by_role('button', name='Assigned Documents', exact=True).click()
        expect(modal.get_by_role('tab', name='Hardcopy (14)', exact=True)).to_be_visible()
        expect(modal.get_by_role('tab', name='Softcopy (2)', exact=True)).to_be_visible()
        assert modal.evaluate('(el)=>el.scrollWidth <= el.clientWidth + 1')
        page.screenshot(path=f'tests/user-assignments-{width}.png', full_page=True)
        modal.get_by_role('button', name='Close', exact=True).last.click()
    page.set_viewport_size({'width': 1440, 'height': 1000})
    page.goto(base + 'admin/users')
    # Actions must stay within their own cell, including the Deactivate action.
    assert page.locator('td.table-actions-cell').evaluate_all('''cells=>cells.every(cell=>
        Array.from(cell.querySelectorAll('button')).every(button=>{
            const b=button.getBoundingClientRect(),c=cell.getBoundingClientRect();
            return b.left>=c.left-1 && b.right<=c.right+1;
        }))''')
    held = []
    page.route(pattern, lambda route: held.append(route))
    row = page.locator('tr', has=page.locator('td', has_text='browser-admin')).first
    row.get_by_role('button', name='Assigned Documents', exact=True).click()
    expect(modal.get_by_role('status')).to_contain_text('Loading assigned documents')
    modal.get_by_role('button', name='Close', exact=True).last.click()
    expect(modal).to_be_hidden()
    assert held
    held[0].continue_()
    page.unroute(pattern)
    page.wait_for_timeout(500)
    expect(modal.locator('#viewDetails')).to_be_empty()
    row.get_by_role('button', name='View', exact=True).click()
    expect(modal.locator('#viewModalTitle')).to_have_text('Browser Administrator')
    expect(modal.get_by_role('tab')).to_have_count(0)
    modal.get_by_role('button', name='Close', exact=True).last.click()
    row = page.locator('tr', has=page.locator('td', has_text='browser-staff')).first
    row.get_by_role('button', name='Assigned Documents', exact=True).click()
    expect(modal.get_by_role('tab', name='Softcopy (0)', exact=True)).to_be_visible()
    modal.get_by_role('tab', name='Softcopy (0)', exact=True).click()
    expect(modal.get_by_text('No active Softcopy documents are assigned to this user.', exact=True)).to_be_visible()
    assert page.request.get(base + 'admin/users/999999/assigned-documents').status == 404
    # A users-view-only role cannot access assignment data through the direct URL.
    sql("INSERT IGNORE INTO role_permissions(role_id,permission_id) SELECT 5,id FROM permissions WHERE module_key='users' AND action_key='view'")
    modal.get_by_role('button', name='Close', exact=True).last.click()
    page.context.clear_cookies()
    page.goto(base + 'login')
    page.locator('[name="login"]').fill('browser-staff')
    import os
    page.locator('[name="password"]').fill(os.environ['PK_BROWSER_PASSWORD'])
    page.locator('button[type="submit"]').click()
    page.wait_for_url('**/dashboard')
    assert page.request.get(base + 'admin/users/1/assigned-documents').status == 403
    page.goto(base + 'admin/users')
    expect(page.get_by_role('button', name='Assigned Documents', exact=True)).to_have_count(0)
    sql("INSERT IGNORE INTO role_permissions(role_id,permission_id) SELECT 5,id FROM permissions WHERE module_key='assignment' AND action_key='direct'")
    sql("DELETE rp FROM role_permissions rp JOIN permissions p ON p.id=rp.permission_id WHERE rp.role_id=5 AND p.module_key='users' AND p.action_key='view'")
    assert page.request.get(base + 'admin/users/1/assigned-documents').status == 403
    print('User assignments: domains, paging/search, isolation, retry/close, saving, mobile/actions and both permission gates passed.')
