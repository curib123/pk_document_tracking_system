"""Exercise the shared read-only viewers inside the disposable browser harness."""
import os
import json
from pathlib import Path
from playwright.sync_api import expect


def run_checks(page, base, app_root):
    shots = Path(__file__).parent
    audit = app_root / 'storage/audit'
    audit.mkdir(parents=True, exist_ok=True)
    (audit / '2026-10-15.json').write_text(json.dumps([{
        'timestamp': '2026-10-15T14:20:00+08:00', 'actor_id': 1,
        'action': 'browser.viewer', 'subject': 'Read-only display fixture',
        'status': 'success', 'metadata': {'description': 'Long audit details remain readable. ' * 10}
    }]), encoding='utf-8')
    routes = ['documents/softcopy', 'documents/hardcopy', 'my-requests/softcopy',
              'my-tasks/softcopy', 'admin/users', 'admin/roles', 'places/location',
              'admin/workflows', 'admin/document-assignments', 'admin/audit']
    provider = page.evaluate('() => window.bootstrap.Modal.VERSION || "office network fallback"')
    print('Viewer browser provider: ' + provider, flush=True)
    if os.environ.get('PK_BROWSER_ONLINE') == '1':
        assert provider == '5.3.3', 'Online checks must use the real Bootstrap modal.'
    else:
        assert provider == 'office network fallback', 'Offline checks must actually block the CDN.'
    for width in [1440, 390, 320]:
        page.set_viewport_size({'width': width, 'height': 950})
        for route in routes:
            page.goto(base + route)
            assert page.locator('.js-view').count(), 'No viewer fixture at ' + route + ' (' + page.url + ')'
            page.locator('.js-view').first.click()
            modal = page.locator('#viewModal')
            expect(modal).to_be_visible()
            expect(modal).to_have_css('opacity', '1')
            expect(modal.locator('#viewModalTitle')).not_to_be_empty()
            expect(modal.locator('.doc-overview-value').first).to_be_visible()
            if route == 'admin/audit':
                expect(modal.locator('.doc-overview-item.is-wide')).to_contain_text('Long audit details remain readable.')
            assert modal.locator('.modal-body').evaluate('(e) => e.scrollWidth <= e.clientWidth + 1'), (route, width)
            assert modal.locator('.modal-content').evaluate('(e) => e.getBoundingClientRect().height <= innerHeight'), (route, width)
            assert modal.locator('.modal-content').evaluate('(e) => e.scrollWidth <= e.clientWidth + 1'), (route, width)
            for tab in modal.locator('[role="tab"]').all():
                tab.click()
                expect(tab).to_have_attribute('aria-selected', 'true')
                assert modal.locator('.modal-body').evaluate('(e) => e.scrollWidth <= e.clientWidth + 1'), (route, width)
            # Verify accessible keyboard navigation and explicit dismissal.
            tabs = modal.locator('[role="tab"]')
            if tabs.count():
                tabs.last.press('Home')
                expect(tabs.first).to_have_attribute('aria-selected', 'true')
                assert modal.locator('.doc-modal-navigation').evaluate('(e) => e.scrollWidth <= e.clientWidth + 1'), 'Concern tabs clip at ' + str(width)
                if width == 390 and route == 'documents/softcopy':
                    modal.locator('.doc-modal-navigation').evaluate('(e) => e.scrollIntoView({block: "start"})')
                    page.screenshot(path=str(shots / 'viewer-tabs-390.png'))
            if width in [1440, 390] and route in ['documents/softcopy', 'admin/users', 'my-requests/softcopy']:
                modal.locator('.modal-body').evaluate('(e) => e.scrollTop = 0')
                page.screenshot(path=str(shots / ('viewer-' + route.split('/')[0] + '-' + str(width) + '.png')))
            modal.get_by_role('button', name='Close', exact=True).last.click()
            expect(modal).to_be_hidden()
            expect(page.locator('#viewDetails')).to_be_empty()

            if route == 'documents/softcopy':
                page.locator('.js-file-history').first.click()
                history = page.locator('#fileHistoryModal')
                expect(history).to_be_visible()
                expect(history).to_have_css('opacity', '1')
                expect(history.get_by_role('link', name='Download', exact=True)).to_be_visible()
                expect(history.get_by_role('link', name='Print', exact=True)).to_be_visible()
                assert history.locator('.modal-body').evaluate('(e) => e.scrollWidth <= e.clientWidth + 1')
                if width in [1440, 390]:
                    page.screenshot(path=str(shots / ('viewer-file-history-' + str(width) + '.png')))
                history.get_by_role('button', name='Close', exact=True).last.click()
            elif route == 'admin/workflows':
                page.locator('[data-bs-target^="#workflowStepsModal-"]').first.click()
                workflow = page.locator('.workflow-steps-modal.show')
                expect(workflow).to_be_visible()
                expect(workflow).to_have_css('opacity', '1')
                expect(workflow.locator('[data-workflow-version]')).to_be_visible()
                assert workflow.locator('.modal-body').evaluate('(e) => e.scrollWidth <= e.clientWidth + 1')
                if width in [1440, 390]:
                    page.screenshot(path=str(shots / ('viewer-workflow-' + str(width) + '.png')))
                workflow.get_by_role('button', name='Close', exact=True).last.click()
    print('All shared record viewers, file history, workflow routes, tabs, actions and explicit close passed at 1440/390/320 pixels.', flush=True)
