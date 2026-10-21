"""Exercise individual and bulk assignments on a disposable site/database."""
import os
from urllib.parse import urlencode

from playwright.sync_api import expect
from cancelled_draft_delete import ROOT, sql, main
from theme_toggle import login


def choose(page, select_id, text):
    field = page.locator('#' + select_id + '-search')
    field.fill(text)
    page.locator('#' + select_id + '-search-list').get_by_role('option', name=text, exact=True).click()


def save(page, form):
    page.locator(form).locator('button[type="submit"]').click()
    expect(page.locator('#confirmModal')).to_be_visible()
    page.locator('#confirmProceed').click()
    expect(page.locator('#appAlertModal')).to_be_visible()
    return page.locator('#appAlertMessage').inner_text()


def post(page, base, action, data):
    token = next(c['value'] for c in page.context.cookies() if c['name'] == 'pk_csrf_cookie')
    return page.request.post(base + 'admin/document-assignments/' + action,
                             form=dict(data, confirmed='yes', pk_csrf_token=token))


def run(page, base, site):
    login(page, base)
    actor = sql("SELECT id FROM users WHERE username='theme-test'")
    recipient = sql("INSERT INTO users(username,first_name,last_name,position_title,role_id,password_hash,require_password_change) "
                    "SELECT 'assign-recipient','Assignment','Recipient','Tester',role_id,password_hash,0 FROM users WHERE id=" + actor + "; SELECT LAST_INSERT_ID()")
    inactive = sql("INSERT INTO users(username,first_name,last_name,position_title,role_id,password_hash,require_password_change,active) "
                   "SELECT 'assign-inactive','Inactive','Recipient','Tester',role_id,password_hash,0,0 FROM users WHERE id=" + actor + "; SELECT LAST_INSERT_ID()")

    def category(name, parent=None):
        return sql("INSERT INTO categories(name,folder_name,created_by,parent_id) VALUES('ASSIGN " + name + "','ASSIGN-" + name + "'," + actor + "," + (parent or 'NULL') + "); SELECT LAST_INSERT_ID()")

    parent = category('Parent')
    child = category('Child', parent)
    sibling = category('Other')
    soft = []
    for name, cat, status in [('PARENT', parent, 'active'), ('CHILD', child, 'active'), ('OTHER', sibling, 'active'), ('DISPOSED', parent, 'disposed')]:
        soft.append(sql("INSERT INTO softcopy_documents(document_number,title,category_id,created_by,status,creation_source,creation_reason) "
                        "VALUES('ASSIGN-" + name + "','ASSIGN " + name + "'," + cat + "," + actor + ",'" + status + "','direct','Test'); SELECT LAST_INSERT_ID()"))
    location = sql("INSERT INTO locations(name,sequence_number) VALUES('ASSIGN Location',99123); SELECT LAST_INSERT_ID()")
    hard = []
    for name, status in [('ONE', 'active'), ('TWO', 'active'), ('DISPOSED', 'disposed')]:
        hard.append(sql("INSERT INTO hardcopy_documents(title,created_by,holder_id,location_id,status,creation_source,creation_reason) "
                        "VALUES('ASSIGN " + name + "'," + actor + "," + actor + "," + location + ",'" + status + "','direct','Test'); SELECT LAST_INSERT_ID()"))
    case = os.environ.get('ASSIGNMENT_CASE', 'all')
    if case in ['all', 'bulk']:
        page.goto(base + 'admin/document-assignments?domain=softcopy&folder=all')
        choose(page, 'categoryAssignmentCategory', 'ASSIGN Parent')
        choose(page, 'categoryAssignmentUser', 'Assignment Recipient')
        message = save(page, '#categoryAssignmentForm')
        assert sql('SELECT COUNT(*) FROM assignments WHERE user_id=' + recipient + ' AND active=1 AND softcopy_id IN (' + ','.join(soft[:2]) + ')') == '2', message
        assert sql('SELECT COUNT(*) FROM assignments WHERE user_id=' + recipient + ' AND softcopy_id IN (' + ','.join(soft[2:]) + ')') == '0'
        # Repeat must reactivate/reuse the same assignments without duplicates.
        post(page, base, 'category', {'category_id': parent, 'recipient_id': recipient})
        assert sql('SELECT COUNT(*) FROM assignments WHERE user_id=' + recipient + ' AND softcopy_id IN (' + ','.join(soft[:2]) + ')') == '2'
        page.goto(base + 'admin/document-assignments?domain=hardcopy&folder=all')
        choose(page, 'locationAssignmentPlace', 'ASSIGN Location · Sequence 99123')
        choose(page, 'locationAssignmentUser', 'Assignment Recipient')
        message = save(page, '.assignments-location-form')
        assert sql('SELECT COUNT(*) FROM hardcopy_documents WHERE id IN (' + ','.join(hard[:2]) + ') AND holder_id=' + recipient) == '2', message
        assert sql('SELECT holder_id FROM hardcopy_documents WHERE id=' + hard[2]) == actor
        response = post(page, base, 'category', {'category_id': '99999999', 'recipient_id': recipient})
        assert response.status == 200, 'Invalid categories must return a usable assignment page'
        response = post(page, base, 'location', {'location_id': '99999999', 'recipient_id': recipient})
        assert response.status == 200, 'Invalid locations must return a usable assignment page'
        before = sql('SELECT COUNT(*) FROM assignments WHERE user_id=' + inactive)
        response = post(page, base, 'category', {'category_id': parent, 'recipient_id': inactive})
        assert response.status == 200
        assert sql('SELECT COUNT(*) FROM assignments WHERE user_id=' + inactive) == before
        print('PASS: category/subcategory and location bulk assignment, repeat safety, inactive/disposed exclusion and invalid input')

    if case in ['all', 'individual']:
        for domain, doc in [('hardcopy', hard[0]), ('softcopy', soft[2])]:
            page.goto(base + 'admin/document-assignments?' + urlencode({'domain': domain, 'folder': 'all', 'owner': recipient}))
            new = page.get_by_role('button', name='Assign Document', exact=True)
            expect(new).to_be_visible()
            new.click()
            expect(page.locator('#assignmentModal')).to_be_visible()
            expect(page.locator('#assignmentForm [name="recipient_id"]')).to_have_value(recipient)
            choose(page, 'assignmentHardcopy' if domain == 'hardcopy' else 'assignmentSoftcopy', 'ASSIGN ONE' if domain == 'hardcopy' else 'ASSIGN-OTHER · ASSIGN OTHER')
            page.locator('#assignmentModal').get_by_role('button', name='Cancel', exact=True).click()
            expect(page.locator('#assignmentModal')).to_be_hidden()
            new.click()
            choose(page, 'assignmentHardcopy' if domain == 'hardcopy' else 'assignmentSoftcopy', 'ASSIGN ONE' if domain == 'hardcopy' else 'ASSIGN-OTHER · ASSIGN OTHER')
            message = save(page, '#assignmentForm')
            if domain == 'hardcopy':
                assert sql('SELECT holder_id FROM hardcopy_documents WHERE id=' + doc) == recipient, message
            else:
                assert sql('SELECT COUNT(*) FROM assignments WHERE softcopy_id=' + doc + ' AND user_id=' + recipient + ' AND active=1') == '1', message
            # Filtering by assignee must still leave the row's document editable.
            page.goto(base + 'admin/document-assignments?' + urlencode({'domain': domain, 'folder': 'all', 'q': 'Assignment Recipient'}))
            row = page.locator('tbody tr').filter(has_text='ASSIGN ONE' if domain == 'hardcopy' else 'ASSIGN OTHER')
            row.get_by_role('button', name='Edit', exact=True).click()
            expect(page.locator('#assignmentModal')).to_be_visible()
            expect(page.locator('#assignmentForm [name="' + domain + '_id"]')).to_have_value(doc)
            expect(page.locator('select[name="owner"]')).to_be_visible()
            choose(page, 'assignmentUser', 'Theme Test')
            assert 'successfully' in save(page, '#assignmentForm')
        for width in [1440, 390, 320]:
            page.set_viewport_size({'width': width, 'height': 950})
            page.goto(base + 'admin/document-assignments?domain=softcopy&folder=all')
            page.get_by_role('button', name='Assign Document', exact=True).click()
            modal = page.locator('#assignmentModal')
            expect(modal).to_be_visible()
            assert modal.locator('.modal-content').evaluate('(e) => e.scrollWidth <= e.clientWidth + 1')
            evidence = ROOT / 'storage/assignment-checks'
            evidence.mkdir(parents=True, exist_ok=True)
            page.screenshot(path=str(evidence / ('assignment-' + str(width) + '.png')))
        print('PASS: new/edit hardcopy and softcopy assignments, selected user, cancellation, assignee search and responsive modal')


if __name__ == '__main__':
    main(checks=run)
