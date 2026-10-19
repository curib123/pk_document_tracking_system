"""Real notification polling/claim regression inside the isolated browser database."""
from playwright.sync_api import expect


def acknowledge_initial_task(page):
    modal = page.locator('#taskNotificationModal')
    expect(modal).to_be_visible(timeout=15000)
    expect(modal).to_contain_text('Browser revision proposal')
    modal.get_by_role('button', name='Got it', exact=True).click()
    expect(modal).to_be_hidden()


def run_checks(page, context, base, sql):
    def value(statement):
        return sql(statement).strip().splitlines()[-1]

    acknowledge_initial_task(page)
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
