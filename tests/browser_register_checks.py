"""Focused register regressions; called only inside the disposable browser harness."""
from pathlib import Path
from playwright.sync_api import expect


def run_checks(page, context, base, app_root, manifest):
    page.goto(base + 'documents/softcopy')
    marker = page.evaluate('() => window.registerDocumentMarker = Math.random()')
    search = page.locator('#tableFilter [name="q"]')
    search.fill('BROWSER-PNG')
    expect(page.locator('tbody tr[data-record-id]')).to_have_count(1)
    expect(page.locator('tbody')).to_contain_text('BROWSER-PNG')
    assert page.evaluate('window.registerDocumentMarker') == marker
    expect(search).to_be_focused()
    search.fill('no-such-document')
    expect(page.locator('tbody')).to_contain_text('No matching records')
    search.fill('BROWSER-TXT')
    expect(page.locator('tbody')).to_contain_text('BROWSER-TXT')
    search.fill('')
    expect(page.locator('tbody tr[data-record-id]')).to_have_count(4)
    page.locator('.document-layout-choice').filter(has_text='Folder').click()
    expect(page.locator('.doc-folder-tree-mode')).to_be_visible()
    expect(page.locator('[data-live-table]')).to_have_attribute('data-live-ready', 'yes')
    search.fill('BROWSER-PNG')
    expect(page.locator('.doc-tree-file')).to_have_count(1)
    search.fill('')
    expect(page.locator('.doc-tree-file')).to_have_count(4)
    print('Live table/folder search, empty states and focus passed.', flush=True)

    for route in ['my-requests/softcopy', 'my-tasks/softcopy']:
        page.goto(base + route)
        page.locator('.js-view').first.click()
        viewer = page.locator('#viewModal')
        expect(viewer).to_be_visible()
        expect(viewer.locator('#viewDocumentSummary')).not_to_be_empty()
        tabs = viewer.locator('[role="tab"]')
        tabs.last.click()
        expect(tabs.last).to_have_attribute('aria-selected', 'true')
        viewer.locator('[data-bs-dismiss="modal"]').last.click()
        expect(viewer).to_be_hidden()
    print('Request and task modal opening, tabs and closing passed.', flush=True)

    backups = Path(app_root) / 'storage/backups'
    backups.mkdir(exist_ok=True)
    for index in range(32):
        (backups / ('pk-dts-manual-20991015-170000-%010d.zip' % index)).write_bytes(b'listing fixture')
    page.goto(base + 'admin/backups')
    expect(page.locator('tbody tr[data-record-id]')).to_have_count(10)
    page.get_by_label('Next page', exact=True).click()
    expect(page.locator('.table-footer')).to_contain_text('Page 2 of 4')
    page.locator('#rowLimit').select_option('25')
    expect(page.locator('tbody tr[data-record-id]')).to_have_count(25)
    page.locator('#tableFilter [name="q"]').fill('0000000031')
    expect(page.locator('tbody tr[data-record-id]')).to_have_count(1)
    expect(page.locator('#restoreArchive option')).to_have_count(33)
    print('Backup pagination, limits, search and complete restore selection passed.', flush=True)
