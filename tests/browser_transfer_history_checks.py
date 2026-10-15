"""Verify transfer milestones in the real document modal with disposable data."""
from pathlib import Path
from playwright.sync_api import expect


def run_checks(page, base, sql):
    document = sql("SELECT id FROM hardcopy_documents WHERE sequence_number='BROWSER-HC'").strip().splitlines()[-1]
    sql("INSERT INTO transfers (hardcopy_id,origin,destination,current_holder_id,recipient_id,document_copy_number,reason,comments,status,created_at,transferred_at,accepted_at) VALUES " +
        "(" + document + ",'{}','{}',1,2,'BROWSER-HC','Archive approved manual','Handle carefully','accepted','2026-10-14 09:00:00','2026-10-14 10:00:00','2026-10-15 11:00:00')," +
        "(" + document + ",'{}','{}',2,1,'BROWSER-HC','Return for filing',NULL,'for_transfer','2026-10-15 12:00:00',NULL,NULL)")

    def open_history(reference='BROWSER-HC'):
        page.goto(base + 'documents/hardcopy')
        page.locator('tbody tr').filter(has_text=reference).locator('.js-view').click()
        modal = page.locator('#viewModal')
        expect(modal).to_be_visible()
        modal.get_by_role('tab', name='Transfer History', exact=True).click()
        return modal, modal.locator('[role="tabpanel"]:visible')

    for width in [1440, 390, 320]:
        page.set_viewport_size({'width': width, 'height': 950})
        modal, panel = open_history()
        expect(panel.locator('.doc-modal-section-title')).to_have_text('Transfer History')
        expect(panel.locator('.doc-workflow-decision-action')).to_have_text(['For Transfer', 'In Transit', 'Accepted', 'For Transfer'])
        expect(panel.locator('.doc-workflow-date')).to_have_text(['Oct 14, 2026', 'Oct 15, 2026'])
        expect(panel.locator('.doc-workflow-decision-time')).to_have_text(['9:00:00 AM', '10:00:00 AM', '11:00:00 AM', '12:00:00 PM'])
        expect(panel.locator('.doc-workflow-decision-marker')).to_have_text(['1', '2', '3', '1'])
        expect(panel.locator('.doc-workflow-decision-comment').first).to_contain_text('Archive approved manual')
        expect(panel.locator('.doc-workflow-decision-comment').first).to_contain_text('Handle carefully')
        expect(panel.locator('.doc-workflow-decision-step').first).to_contain_text('Browser Administrator')
        expect(panel.locator('.doc-workflow-decision-step').first).to_contain_text('Browser Staff')
        expect(panel.locator('.doc-workflow-decision-request')).to_have_count(4)
        timeline = panel.locator('.is-animated-timeline')
        assert timeline.locator('li').first.evaluate('(e) => getComputedStyle(e).animationName') == 'doc-transfer-enter'
        assert timeline.locator('li').first.evaluate('(e) => getComputedStyle(e, "::before").animationName') == 'doc-transfer-connect'
        timeline.evaluate('(e) => Promise.all(e.getAnimations({subtree:true}).map(a => a.finished))')
        assert modal.locator('.modal-body').evaluate('(e) => e.scrollWidth <= e.clientWidth + 1')
        panel.get_by_label('Sort transfer history by timestamp').select_option('newest')
        expect(panel.locator('.doc-workflow-decision-time')).to_have_text(['12:00:00 PM', '11:00:00 AM', '10:00:00 AM', '9:00:00 AM'])
        panel.locator('.is-animated-timeline').evaluate('(e) => Promise.all(e.getAnimations({subtree:true}).map(a => a.finished))')
        if width in [1440, 390]:
            panel.locator('.doc-workflow-toolbar').evaluate('(e) => e.scrollIntoView({block:"start"})')
            page.screenshot(path=str(Path(__file__).parent / ('transfer-history-' + str(width) + '.png')))
        modal.get_by_role('button', name='Close', exact=True).last.click()

    page.emulate_media(reduced_motion='reduce')
    modal, panel = open_history()
    expect(panel.locator('.doc-workflow-decision').first).to_have_css('animation-name', 'none')
    expect(panel.locator('.doc-workflow-decision-marker').first).to_have_css('animation-name', 'none')
    modal.get_by_role('button', name='Close', exact=True).last.click()
    page.emulate_media(reduced_motion='no-preference')
    modal, panel = open_history('BROWSER-OLD')
    expect(panel.locator('.doc-modal-empty-message')).to_have_text('No transfers available within your permissions.')
    expect(panel.get_by_label('Sort transfer history by timestamp')).to_be_disabled()
    modal.get_by_role('button', name='Close', exact=True).last.click()
    print('Transfer History passed: completed and pending milestones, dates, sorting, actors, remarks, empty state, animation and reduced motion at 1440/390/320 pixels.', flush=True)
