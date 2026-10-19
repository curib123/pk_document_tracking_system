"""Verify structured workflow timelines on real request/task pages in a test DB."""
import json
from pathlib import Path
from playwright.sync_api import expect


def run_checks(page, base, sql, manifest):
    def scalar(statement):
        return sql(statement).strip().splitlines()[-1]

    def verify_animations(panel):
        timeline = panel.locator('.is-animated-timeline').first
        expect(timeline).to_be_visible()
        expect(timeline.locator('.doc-workflow-decision').first).to_have_css('animation-name', 'doc-transfer-enter')
        expect(timeline.locator('.doc-workflow-decision-marker').first).to_have_css('animation-name', 'doc-transfer-marker')
        assert timeline.locator('li').first.evaluate('(e) => getComputedStyle(e, "::before").animationName') == 'doc-transfer-connect'
        page.emulate_media(reduced_motion='reduce')
        expect(timeline.locator('.doc-workflow-decision').first).to_have_css('animation-name', 'none')
        expect(timeline.locator('.doc-workflow-decision-marker').first).to_have_css('animation-name', 'none')
        page.emulate_media(reduced_motion='no-preference')
        timeline.evaluate('(e) => Promise.all(e.getAnimations({subtree:true}).map(a => a.finished))')

    request_id = str(manifest['request']['id'])
    noted = scalar('SELECT id FROM workflow_steps WHERE request_id=' + request_id + ' LIMIT 1')
    sql("UPDATE workflow_steps SET label='Noted By',status='approved',assigned_name='Staff2 Staff2' WHERE id=" + noted)
    manager = scalar("INSERT INTO workflow_steps (request_id,node_key,label,assignment,candidates,assigned_user_id,assigned_name,status) VALUES (" +
                     request_id + ",'step_2','Plant Manager Approval','{\"type\":\"user\",\"value\":1}','[1]',1,'Plant Manager','approved'); SELECT LAST_INSERT_ID()")
    officer = scalar("INSERT INTO workflow_steps (request_id,node_key,label,assignment,candidates,assigned_user_id,assigned_name,status) VALUES (" +
                     request_id + ",'step_3','Document Officer Approval','{\"type\":\"user\",\"value\":1}','[1]',1,'Document Officer','active'); SELECT LAST_INSERT_ID()")
    sql("UPDATE requests SET requested_by=1,current_node='step_3' WHERE id=" + request_id)
    for step, action, name, position, time, comments in [
        (noted, 'approved', 'Staff2 Staff2', 'Staff2', '14:17:12', ''),
        (manager, 'approved', 'Plant Manager', 'manager', '14:18:32', ''),
        (officer, 'returned', 'Document Officer', 'Document Controll Officer', '14:18:54', 'fff'),
        (manager, 'approved', 'Plant Manager', 'manager', '14:20:09', '')
    ]:
        sql("INSERT INTO workflow_history (request_id,step_id,action,user_id,user_name,position_title,created_at,comments) VALUES (" +
            ','.join([request_id, step, json.dumps(action), '1', json.dumps(name), json.dumps(position),
                      json.dumps('2026-10-15 ' + time), json.dumps(comments)]) + ')')
    # Record the fixture's new active occurrence as shown so this display-only
    # test does not compete with the separately verified automatic notification.
    sql('INSERT INTO task_notification_receipts (user_id,step_id,activation) SELECT 1,id,version FROM workflow_steps WHERE id=' + officer)

    for route in ['my-requests/softcopy', 'my-tasks/softcopy']:
        page.goto(base + route)
        page.locator('tbody tr').filter(has_text='BROWSER-REQUEST').locator('.js-view').click()
        modal = page.locator('#viewModal')
        expect(modal).to_be_visible()
        expect(modal.locator('.doc-overview-group-title')).to_have_count(0)
        modal.get_by_role('tab', name='Workflow Activity Log', exact=True).click()
        panel = modal.locator('[role="tabpanel"]:visible')
        verify_animations(panel)
        expect(panel.locator('.doc-workflow-decision')).to_have_count(4)
        expect(panel.locator('.doc-workflow-date')).to_have_text('Oct 15, 2026')
        expect(panel.locator('.doc-workflow-decision-time')).to_have_text(['2:17:12 PM', '2:18:32 PM', '2:18:54 PM', '2:20:09 PM'])
        expect(panel.locator('.doc-workflow-decision-comment')).to_have_text('fff')
        panel.locator('.doc-workflow-sort').select_option('newest')
        expect(panel.locator('.doc-workflow-decision-time')).to_have_text(['2:20:09 PM', '2:18:54 PM', '2:18:32 PM', '2:17:12 PM'])
        for width in [1440, 390, 320]:
            page.set_viewport_size({'width': width, 'height': 950})
            assert modal.locator('.modal-body').evaluate('(e) => e.scrollWidth <= e.clientWidth + 1')
            items = panel.locator('.doc-workflow-decision')
            assert items.nth(1).bounding_box()['y'] > items.first.bounding_box()['y']
            if route.startswith('my-tasks') and width in [1440, 390]:
                panel.locator('.doc-workflow-toolbar').evaluate('(e) => e.scrollIntoView({block: "start"})')
                page.screenshot(path=str(Path(__file__).parent / ('workflow-stepper-' + str(width) + '.png')))
        modal.get_by_role('tab', name='Approval Process Timeline', exact=True).click()
        panel = modal.locator('[role="tabpanel"]:visible')
        expect(panel.locator('.doc-workflow-step-name')).to_have_text(['Noted By', 'Plant Manager Approval', 'Document Officer Approval'])
        panel.locator('.doc-workflow-sort').select_option('newest')
        expect(panel.locator('.doc-workflow-step-name')).to_have_text(['Noted By', 'Plant Manager Approval', 'Document Officer Approval'])
        expect(panel.locator('.doc-workflow-step').nth(1).locator('.doc-workflow-decision-time')).to_have_text(['2:20:09 PM', '2:18:32 PM'])
        assert modal.locator('.modal-body').evaluate('(e) => e.scrollWidth <= e.clientWidth + 1')
        modal.get_by_role('button', name='Close', exact=True).last.click()
        page.set_viewport_size({'width': 1440, 'height': 950})

    hardcopy_id = scalar("SELECT id FROM hardcopy_documents WHERE sequence_number='BROWSER-HC'")
    hardcopy_request = scalar("INSERT INTO requests (reference,type,requested_by,hardcopy_id,payload,status) VALUES " +
                              "('BROWSER-HC-REQUEST','hardcopy_update',1," + hardcopy_id + ",'{}','completed'); SELECT LAST_INSERT_ID()")
    sql("INSERT INTO workflow_history (request_id,action,user_id,user_name,position_title,created_at,comments) VALUES (" +
        hardcopy_request + ",'approved',1,'Records Officer','Document Control','2026-10-15 15:00:00','Hardcopy accepted')")
    for domain, reference, count in [('softcopy', 'BROWSER-TXT', 4), ('hardcopy', 'BROWSER-HC', 1)]:
        page.goto(base + 'documents/' + domain)
        page.locator('tbody tr').filter(has_text=reference).locator('.js-view').first.click()
        modal = page.locator('#viewModal')
        expect(modal.locator('.doc-overview-group-title')).to_have_count(0)
        modal.get_by_role('tab', name='Approval History', exact=True).click()
        panel = modal.locator('[role="tabpanel"]:visible')
        expect(panel.locator('.doc-workflow-toolbar h3')).to_have_text('Approval History')
        verify_animations(panel)
        expect(panel.locator('.doc-workflow-decision')).to_have_count(count)
        expected_reference = 'Request #' + (str(manifest['request']['id']) if domain == 'softcopy' else hardcopy_request)
        expect(panel.locator('.doc-workflow-decision-request')).to_have_text([expected_reference] * count)
        expect(panel.locator('.doc-workflow-decision-comment')).to_have_text('fff' if domain == 'softcopy' else 'Hardcopy accepted')
        panel.locator('.doc-workflow-sort').select_option('newest')
        if domain == 'softcopy':
            expect(panel.locator('.doc-workflow-decision-time')).to_have_text(['2:20:09 PM', '2:18:54 PM', '2:18:32 PM', '2:17:12 PM'])
        for width in [1440, 390, 320]:
            page.set_viewport_size({'width': width, 'height': 950})
            assert modal.locator('.modal-body').evaluate('(e) => e.scrollWidth <= e.clientWidth + 1'), (domain, width)
            grid = modal.locator('.doc-overview-grid').first
            group = modal.locator('.doc-overview-group').first
            assert abs(grid.bounding_box()['width'] - group.bounding_box()['width']) < 2, 'Removed title must leave no empty column'
            if width in [1440, 390]:
                panel.locator('.doc-workflow-toolbar').evaluate('(e) => e.scrollIntoView({block: "start"})')
                page.screenshot(path=str(Path(__file__).parent / ('approval-history-' + domain + '-' + str(width) + '.png')))
        modal.get_by_role('button', name='Close', exact=True).last.click()
        page.set_viewport_size({'width': 1440, 'height': 950})
    print('Softcopy/hardcopy Approval History shares the decision stepper; overview titles removed with full-width metadata at 1440/390/320 pixels.', flush=True)
    print('Request/task vertical workflow, dated decisions, both timestamp orders, repeated approvals, remarks and mobile sizing passed.', flush=True)
