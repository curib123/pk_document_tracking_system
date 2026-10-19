"""Real notification polling/claim regression inside the isolated browser database."""
import os
from playwright.sync_api import expect


def acknowledge_initial_task(page):
    # Automatic alerts intentionally wait while their tab is in the background.
    page.bring_to_front()
    modal = page.locator('#taskNotificationModal')
    expect(modal).to_be_visible(timeout=15000)
    expect(modal).to_contain_text('Browser revision proposal')
    modal.get_by_role('button', name='Got it', exact=True).click()
    expect(modal).to_be_hidden()


def run_checks(page, context, base, sql):
    def value(statement):
        return sql(statement).strip().splitlines()[-1]

    acknowledge_initial_task(page)
    provider = page.evaluate('() => window.bootstrap.Modal.VERSION || "offline fallback"')
    assert provider == ('5.3.3' if os.environ.get('PK_BROWSER_ONLINE') == '1' else 'offline fallback')
    print('Task notification browser provider: ' + provider, flush=True)
    assert value('SELECT COUNT(*) FROM task_notification_receipts WHERE user_id=1') == '1'
    with page.expect_response(lambda response: response.url.endswith('/notifications/tasks') and response.status == 200):
        page.reload()
    expect(page.locator('#taskNotificationModal')).to_be_hidden()
    assert context.request.get(base + 'notifications/tasks').json()['tasks'] == []
    marker = page.evaluate('() => window.taskPageMarker = Math.random()')

    def new_task(reference):
        request_id = value("INSERT INTO requests (reference,type,requested_by,payload,status,current_node) VALUES ('" +
                           reference + "','hardcopy_create',2,'{}','submitted','step_1'); SELECT LAST_INSERT_ID();")
        return value("INSERT INTO workflow_steps (request_id,node_key,label,assignment,candidates,assigned_user_id,status) VALUES (" +
                     request_id + ",'step_1','New task approval','{\"type\":\"user\",\"value\":1}','[1]',1,'active'); SELECT LAST_INSERT_ID();")

    step_id = new_task('BROWSER-ALERT-NEW')
    modal = page.locator('#taskNotificationModal')
    expect(modal).to_be_visible(timeout=20000)
    expect(modal).to_contain_text('Hardcopy Create')
    assert page.evaluate('window.taskPageMarker') == marker, 'New task alerts must not need a reload.'
    assert value('SELECT COUNT(*) FROM task_notification_receipts WHERE user_id=1 AND step_id=' + step_id) == '1'
    modal.get_by_role('button', name='Got it', exact=True).click()

    # A form must retain its input while a newly assigned task waits to show.
    with page.expect_response(lambda response: response.url.endswith('/notifications/tasks') and response.status == 200):
        page.goto(base + 'admin/backups')
    page.get_by_role('button', name='Import backup', exact=True).click()
    form_modal = page.locator('#backupImportModal')
    expect(form_modal).to_be_visible()
    deferred_step = new_task('BROWSER-ALERT-DEFERRED')
    with page.expect_response(lambda response: response.url.endswith('/notifications/tasks') and response.status == 200, timeout=20000):
        pass
    expect(modal).to_be_hidden()
    assert value('SELECT COUNT(*) FROM task_notification_receipts WHERE user_id=1 AND step_id=' + deferred_step) == '0'
    form_modal.get_by_role('button', name='Cancel', exact=True).click()
    expect(modal).to_be_visible(timeout=5000)
    expect(modal).to_contain_text('Hardcopy Create')
    modal.get_by_role('button', name='Got it', exact=True).click()
    assert context.request.get(base + 'notifications/tasks').json()['tasks'] == []
    print('Automatic task alerts: initial task, live arrivals, durable receipts, refresh deduplication and deferral behind an open form passed.', flush=True)

    # Exercise the server's full notification batch without changing real task data.
    statements = []
    for index in range(100):
        title = 'Approval document ' + str(index + 1)
        if index == 0:
            title += ' ' + 'LongUnbrokenDocumentTitle' * 12
        statements.append(
            "INSERT INTO requests (reference,type,requested_by,payload,status,current_node) VALUES "
            "('BROWSER-BULK-" + str(index + 1) + "','hardcopy_create',2,'" +
            '{"title":"' + title + '"}' + "','submitted','step_1'); "
            "INSERT INTO workflow_steps (request_id,node_key,label,assignment,candidates,assigned_user_id,status) VALUES "
            "(LAST_INSERT_ID(),'step_1','Bulk task approval','{\"type\":\"user\",\"value\":1}','[1]',1,'active');"
        )
    sql('\n'.join(statements))
    page.reload()
    expect(modal).to_be_visible(timeout=15000)
    tasks = modal.locator('.task-notification-link')
    expect(tasks).to_have_count(100)
    task_list = modal.locator('#taskNotificationList')
    for width, height in [(1440, 1000), (390, 844), (320, 568), (844, 390)]:
        page.set_viewport_size({'width': width, 'height': height})
        tasks.first.focus()
        geometry = modal.evaluate('''modal => {
            const content = modal.querySelector('.modal-content').getBoundingClientRect();
            const list = modal.querySelector('#taskNotificationList');
            return {top: content.top, bottom: content.bottom, left: content.left, right: content.right,
                listHeight: list.clientHeight, listContent: list.scrollHeight,
                listWidth: list.clientWidth, listContentWidth: list.scrollWidth};
        }''')
        assert geometry['top'] >= 0 and geometry['bottom'] <= height, (width, height, geometry)
        assert geometry['left'] >= 0 and geometry['right'] <= width, (width, height, geometry)
        assert geometry['listHeight'] > 0 and geometry['listContent'] > geometry['listHeight'], \
            ('The task list must scroll inside the modal.', width, height, geometry)
        assert geometry['listContentWidth'] <= geometry['listWidth'] + 1, (width, height, geometry)
        title_top = modal.locator('#taskNotificationTitle').bounding_box()['y']
        tasks.last.focus()
        expect(tasks.last).to_be_in_viewport(ratio=1)
        assert task_list.evaluate('list => list.scrollTop') > 0
        assert abs(modal.locator('#taskNotificationTitle').bounding_box()['y'] - title_top) < 1, \
            'Scrolling tasks must keep the heading visible.'
        expect(modal.locator('#taskNotificationTitle')).to_be_in_viewport(ratio=1)
        expect(modal.get_by_role('button', name='Close notification')).to_be_in_viewport(ratio=1)
        expect(modal.get_by_role('button', name='Got it', exact=True)).to_be_in_viewport(ratio=1)
        page.keyboard.press('Tab')
        expect(modal.get_by_role('button', name='Got it', exact=True)).to_be_focused()
    modal.get_by_role('button', name='Got it', exact=True).click()
    expect(modal).to_be_hidden()
    page.reload()
    expect(modal).to_be_hidden()
    assert context.request.get(base + 'notifications/tasks').json()['tasks'] == []
    print('100-task alert: all tasks accessible, long titles wrapped, list-only scrolling and fixed controls passed at desktop, mobile and landscape sizes.', flush=True)
