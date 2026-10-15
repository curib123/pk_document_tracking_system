"""Focused register regressions; called only inside the disposable browser harness."""
from pathlib import Path
from playwright.sync_api import expect


def run_checks(page, context, base, app_root, manifest, sql, browser, password):
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
    expect(page.locator('[data-live-table]')).not_to_have_attribute('aria-busy', 'true')
    page.evaluate('''() => {
        const normalFetch = window.fetch;
        window.normalRegisterFetch = normalFetch;
        window.fetch = async (url, options) => {
            const response = await normalFetch(url, options);
            if (new URL(url).searchParams.get('q') === 'BROWSER-PNG') {
                const text = await response.text();
                window.staleResponseWaiting = true;
                await new Promise(resolve => window.releaseStaleResponse = resolve);
                window.staleResponseReturned = true;
                return new Response(text, {status: response.status, headers: response.headers});
            }
            return response;
        };
    }''')
    search.fill('BROWSER-PNG')
    page.wait_for_function('window.staleResponseWaiting === true')
    search.fill('BROWSER-TXT')
    expect(page.locator('tbody tr[data-record-id]')).to_have_count(1)
    expect(page.locator('tbody')).to_contain_text('BROWSER-TXT')
    page.evaluate('() => window.releaseStaleResponse()')
    page.wait_for_function('window.staleResponseReturned === true')
    expect(page.locator('tbody')).to_contain_text('BROWSER-TXT')
    page.evaluate('() => window.fetch = window.normalRegisterFetch')
    search.fill('')
    expect(page.locator('tbody tr[data-record-id]')).to_have_count(4)
    expect(page.locator('[data-live-table]')).not_to_have_attribute('aria-busy', 'true')
    page.evaluate('''() => {
        window.normalRegisterFetch = window.fetch;
        window.fetch = () => Promise.reject(new Error('Connection unavailable'));
    }''')
    search.fill('BROWSER-PNG')
    expect(page.locator('[data-table-status]')).to_contain_text('Connection unavailable')
    expect(page.locator('tbody tr[data-record-id]')).to_have_count(4)
    page.evaluate('() => window.fetch = window.normalRegisterFetch')
    page.get_by_role('button', name='Retry', exact=True).click()
    expect(page.locator('tbody tr[data-record-id]')).to_have_count(1)
    expect(page.locator('tbody')).to_contain_text('BROWSER-PNG')
    search.fill('')
    expect(page.locator('tbody tr[data-record-id]')).to_have_count(4)
    expect(page.locator('[data-live-table]')).not_to_have_attribute('aria-busy', 'true')
    page.locator('.document-layout-choice').filter(has_text='Folder').click()
    expect(page.locator('.doc-folder-tree-mode')).to_be_visible()
    expect(page.locator('[data-live-table]')).to_have_attribute('data-live-ready', 'yes')
    search.fill('BROWSER-PNG')
    expect(page.locator('.doc-tree-file')).to_have_count(1)
    search.fill('')
    expect(page.locator('.doc-tree-file')).to_have_count(4)
    print('Live table/folder search, empty states and focus passed.', flush=True)
    period = page.locator('[data-date-period]')
    period.select_option('custom')
    page.locator('[name="from"]').fill('2025-01-01')
    page.locator('[name="to"]').fill('2025-01-02')
    page.locator('[name="to"]').press('Tab')
    expect(page.locator('[data-live-table]')).not_to_have_attribute('aria-busy', 'true')
    page.locator('[data-table-clear]').click()
    expect(period).to_have_value('all')
    expect(page.locator('[name="from"]')).to_be_disabled()
    search.fill('BROWSER-PNG')
    expect(page.locator('.doc-tree-file')).to_have_count(1)

    for route in ['my-requests/softcopy', 'my-tasks/softcopy']:
        page.goto(base + route)
        # Viewing must not depend on Bootstrap's separate declarative toggle handler.
        page.locator('.js-view').first.evaluate('(e) => e.removeAttribute("data-bs-toggle")')
        page.locator('.js-view').first.click()
        viewer = page.locator('#viewModal')
        expect(viewer).to_be_visible()
        expect(viewer.locator('#viewDocumentSummary')).not_to_be_empty()
        tabs = viewer.locator('[role="tab"]')
        tabs.last.click()
        expect(tabs.last).to_have_attribute('aria-selected', 'true')
        viewer.locator('[data-bs-dismiss="modal"]').last.click()
        expect(viewer).to_be_hidden()
        page.locator('.js-view').first.evaluate('(e) => e.setAttribute("data-display", "{invalid")')
        page.locator('.js-view').first.click()
        expect(viewer).to_be_visible()
        expect(viewer.get_by_role('alert')).to_contain_text('Unable to display this record')
        viewer.locator('[data-bs-dismiss="modal"]').last.click()
    print('Request and task modal opening, tabs and closing passed.', flush=True)

    backups = Path(app_root) / 'storage/backups'
    backups.mkdir(exist_ok=True)
    for index in range(32):
        (backups / ('pk-dts-manual-20991015-170000-%010d.zip' % index)).write_bytes(b'listing fixture')
    page.goto(base + 'admin/backups')
    expect(page.locator('tbody tr[data-record-id]')).to_have_count(10)
    page.get_by_label('Next page', exact=True).click()
    expect(page.locator('.table-footer')).to_contain_text('Page 2 of 4')
    page.go_back()
    expect(page.locator('.table-footer')).to_contain_text('Page 1 of 4')
    page.go_forward()
    expect(page.locator('.table-footer')).to_contain_text('Page 2 of 4')
    page.locator('#rowLimit').select_option('25')
    expect(page.locator('tbody tr[data-record-id]')).to_have_count(25)
    page.locator('#tableFilter [name="q"]').fill('0000000031')
    expect(page.locator('tbody tr[data-record-id]')).to_have_count(1)
    expect(page.locator('#restoreArchive option')).to_have_count(33)
    print('Backup pagination, limits, search and complete restore selection passed.', flush=True)
    for width in [1440, 390]:
        page.set_viewport_size({'width': width, 'height': 950})
        page.wait_for_timeout(350)  # Let the existing sidebar resize transition finish.
        page.locator('.backup-tool').evaluate_all('(items) => items.forEach(item => item.open = true)')
        page.screenshot(path=str(Path(__file__).parent / ('backup-register-' + str(width) + '.png')))
        assert page.locator('body').evaluate('(e) => e.scrollWidth <= innerWidth + 1'), page.evaluate('''() =>
            Array.from(document.querySelectorAll('body *')).filter(e => e.getBoundingClientRect().right > innerWidth + 1)
                .slice(0, 15).map(e => [e.tagName, e.className, e.getBoundingClientRect().width])''')

    # Scope and action permissions must both pass. The staff member owns this
    # document so these denials cannot be accidentally satisfied by scope alone.
    document_id = manifest['txt']['document_id']
    file_id = manifest['txt']['file_id']
    sql('UPDATE softcopy_documents SET created_by=2 WHERE id=' + str(document_id))
    sql("DELETE rp FROM role_permissions rp JOIN permissions p ON p.id=rp.permission_id WHERE rp.role_id=5 AND p.module_key='files' AND p.action_key IN ('history','print','download')")
    staff = browser.new_context()
    staff.route('https://**', lambda route: route.abort())
    staff_page = staff.new_page()
    staff_page.goto(base + 'login')
    staff_page.locator('[name="login"]').fill('browser-staff')
    staff_page.locator('[name="password"]').fill(password)
    staff_page.locator('button[type="submit"]').click()
    staff_page.wait_for_url('**/dashboard')
    staff_page.goto(base + 'documents/softcopy')
    row = staff_page.locator('tbody tr').filter(has_text='BROWSER-TXT')
    expect(row).to_have_count(1)
    expect(row.locator('.record-action-history, .record-action-print, .record-action-download')).to_have_count(0)
    for endpoint in ['files/print/', 'files/print-content/', 'files/download/']:
        assert staff.request.get(base + endpoint + str(file_id)).status == 403
    row.locator('.js-view').click()
    expect(staff_page.locator('#viewModal a[href*="files/download/"], #viewModal a[href*="files/print/"]')).to_have_count(0)
    staff.close()
    print('File actions and direct URLs deny an owner without action permissions.', flush=True)
