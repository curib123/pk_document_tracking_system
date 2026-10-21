"""Assignment form usability in both themes and constrained viewports."""
import os
from playwright.sync_api import expect
from cancelled_draft_delete import ROOT, sql, main
from document_assignments import choose
from theme_toggle import login


def run(page, base, site):
    login(page, base)
    actor = sql("SELECT id FROM users WHERE username='theme-test'")
    category = sql("INSERT INTO categories(name,folder_name,created_by) VALUES('Layout test','layout-test'," + actor + "); SELECT LAST_INSERT_ID()")
    title = 'ASSIGN-LAYOUT ' + 'Operations reference and document register ' * 4
    document = sql("INSERT INTO softcopy_documents(document_number,title,created_by,category_id,status,creation_source,creation_reason) "
                   "VALUES('ASSIGN-LAYOUT-001','" + title.rstrip() + "'," + actor + "," + category + ",'active','direct','Test'); SELECT LAST_INSERT_ID()")
    evidence = ROOT / 'storage/assignment-layout'
    evidence.mkdir(parents=True, exist_ok=True)
    phase = os.environ.get('ASSIGNMENT_LAYOUT_PHASE', 'after')
    for theme in ['light', 'dark']:
        page.evaluate('(theme) => localStorage.setItem("pk-dts-theme", theme)', theme)
        for width, height in [(1440, 950), (390, 740), (320, 480), (844, 390)]:
            page.set_viewport_size({'width': width, 'height': height})
            page.goto(base + 'admin/document-assignments?domain=softcopy&folder=all&owner=' + actor)
            page.get_by_role('button', name='Assign Document', exact=True).click()
            modal = page.locator('#assignmentModal')
            expect(modal).to_be_visible()
            modal.locator('.modal-content').evaluate('(e) => Promise.all(e.getAnimations({subtree:true}).filter(a => a.effect.getComputedTiming().iterations !== Infinity).map(a => a.finished))')
            bounds = modal.locator('.modal-content').bounding_box()
            page.screenshot(path=str(evidence / (phase + '-' + theme + '-' + str(width) + '.png')))
            assert bounds['x'] >= 7 and bounds['x'] + bounds['width'] <= width - 7, bounds
            assert bounds['y'] >= 7 and bounds['y'] + bounds['height'] <= height - 7, bounds
            assert modal.locator('.modal-content').evaluate('(e) => e.scrollWidth <= e.clientWidth + 1')
            expect(modal.locator('.modal-footer button[type="submit"]')).to_be_in_viewport()
            choose(page, 'assignmentSoftcopy', 'ASSIGN-LAYOUT-001 · ' + title.rstrip())
            expect(modal.locator('[name="softcopy_id"]')).to_have_value(document)
            # Confirm cancellation must return the chosen document/user intact.
            modal.locator('button[type="submit"]').click()
            expect(page.locator('#confirmModal')).to_be_visible()
            page.locator('#confirmModal').get_by_role('button', name='Cancel', exact=True).click()
            expect(modal).to_be_visible()
            expect(modal.locator('[name="softcopy_id"]')).to_have_value(document)
            expect(modal.locator('[name="recipient_id"]')).to_have_value(actor)
            modal.get_by_role('button', name='Close', exact=True).click()
            expect(modal).to_be_hidden()
    assert sql('SELECT COUNT(*) FROM assignments WHERE softcopy_id=' + document) == '0', 'Cancelling must not save'
    print('PASS: assignment modal fits desktop/mobile/landscape in both themes; long labels, confirmation cancel and Close preserve values')


if __name__ == '__main__':
    main(checks=run)
