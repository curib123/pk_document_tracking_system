"""Exercise saved physical routes inside the disposable browser database."""
from pathlib import Path
from playwright.sync_api import expect


def run_checks(page, base, sql):
    shots = Path(__file__).parent
    sql("INSERT INTO assets (id,specific_id,asset_number) VALUES (1,1,'ASSET-04')")

    def open_document():
        page.goto(base + 'documents/hardcopy')
        page.locator('tbody tr').filter(has_text='BROWSER-HC').locator('.js-view').click()
        modal = page.locator('#viewModal')
        expect(modal).to_be_visible()
        return modal, modal.locator('.doc-storage-route')

    for with_asset in [False, True]:
        sql('UPDATE hardcopy_documents SET asset_id=' + ('1' if with_asset else 'NULL') +
            " WHERE sequence_number='BROWSER-HC'")
        labels = ['Area', 'Specific'] + (['Asset'] if with_asset else []) + ['Location']
        values = ['Browser Test Area', 'Browser Test Specific'] + (['ASSET-04'] if with_asset else []) + ['Browser Test Location']
        for width in [1440, 390, 320]:
            page.set_viewport_size({'width': width, 'height': 950})
            modal, route = open_document()
            expect(route.locator('.doc-storage-route-label')).to_have_text(labels)
            expect(route.locator('.doc-storage-route-value')).to_have_text(values)
            expect(route.locator('.doc-storage-route-marker')).to_have_text([str(i + 1) for i in range(len(labels))])
            expect(route.locator('li').last).to_have_attribute('aria-current', 'location')
            expect(route.locator('.doc-storage-route-marker').last).to_have_css('color', 'rgb(255, 255, 255)')
            assert route.locator('li').first.evaluate('(e) => getComputedStyle(e, "::after").animationName') == 'doc-route-connect'
            route.evaluate('(e) => Promise.all(e.getAnimations({subtree:true}).map(a => a.finished))')
            markers = route.locator('.doc-storage-route-marker').all()
            boxes = [marker.bounding_box() for marker in markers]
            assert max(box['y'] for box in boxes) - min(box['y'] for box in boxes) < 2, 'Route must stay horizontal'
            assert all(boxes[i]['x'] < boxes[i + 1]['x'] for i in range(len(boxes) - 1))
            assert modal.locator('.modal-body').evaluate('(e) => e.scrollWidth <= e.clientWidth + 1')
            assert route.evaluate('(e) => e.scrollWidth <= e.clientWidth + 1')
            expect(modal.get_by_role('tab', name='Approval History', exact=True)).to_be_visible()
            if width in [1440, 390]:
                modal.locator('.modal-body').evaluate('(e) => e.scrollTop = 0')
                page.screenshot(path=str(shots / ('storage-route-' + ('asset-' if with_asset else 'direct-') + str(width) + '.png')))
            modal.get_by_role('button', name='Close', exact=True).last.click()
            expect(modal).to_be_hidden()
            expect(modal.locator('.doc-storage-route')).to_have_count(0)

    sql("UPDATE assets SET asset_number='ASSET-VERY-LONG-IDENTIFIER-012345678901234567890123456789' WHERE id=1")
    page.set_viewport_size({'width': 320, 'height': 950})
    modal, route = open_document()
    assert route.evaluate('(e) => e.scrollWidth <= e.clientWidth + 1'), 'Long route names must wrap'
    modal.get_by_role('button', name='Close', exact=True).last.click()

    page.emulate_media(reduced_motion='reduce')
    modal, route = open_document()
    expect(route.locator('.doc-storage-route-marker').first).to_have_css('animation-name', 'none')
    assert route.locator('li').first.evaluate('(e) => getComputedStyle(e, "::after").animationName') == 'none'
    expect(route.locator('.doc-storage-route-value').last).to_be_visible()
    modal.get_by_role('button', name='Close', exact=True).last.click()
    page.emulate_media(reduced_motion='no-preference')
    page.set_viewport_size({'width': 1440, 'height': 950})
    print('Saved storage routes passed: animated horizontal layout, optional Asset, final Location, long names, reopening, and reduced motion at 1440/390/320 pixels.', flush=True)
