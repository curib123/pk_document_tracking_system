"""Check compact document metadata and red header in both themes and widths."""
import json
import os

from playwright.sync_api import expect
from cancelled_draft_delete import ROOT, sql, main
from theme_toggle import login


def run(page, base, site):
    login(page, base)
    actor = sql("SELECT id FROM users WHERE username='theme-test'")
    sql("INSERT INTO hardcopy_documents(title,created_by,holder_id,status,version,retention_enabled,retention_start_date,retention_end_date,created_at,updated_at) "
        "VALUES('HEADER-SAMPLE'," + actor + "," + actor + ",'active',2,1,CURDATE(),CURDATE(),'2026-10-21 09:59:14','2026-10-21 12:16:30')")
    evidence = ROOT / 'storage/header-checks'
    evidence.mkdir(parents=True, exist_ok=True)
    phase = os.environ.get('HEADER_PHASE', 'after')
    results = {}
    for theme in ['light', 'dark']:
        page.evaluate('(theme) => localStorage.setItem("pk-dts-theme", theme)', theme)
        for width in [1440, 390, 320]:
            page.set_viewport_size({'width': width, 'height': 950})
            page.goto(base + 'documents/hardcopy?period=all&q=HEADER-SAMPLE')
            page.locator('tbody .js-view').click()
            modal = page.locator('#viewModal')
            expect(modal).to_be_visible()
            header = modal.locator('.doc-modal-header')
            expect(header).to_contain_text('PHYSICAL DOCUMENT')
            expect(header).to_contain_text('HEADER-SAMPLE')
            for label in ['Created By', 'Created', 'Modified', 'Record Version', 'Current Holder', 'Days until disposal', 'Due today']:
                expect(header).to_contain_text(label)
            assert header.evaluate('(e) => e.scrollWidth <= e.clientWidth + 1'), (theme, width)
            expect(header.get_by_role('button', name='Close')).to_be_visible()
            results[theme + '-' + str(width)] = {
                'height': header.bounding_box()['height'],
                'text': header.inner_text(),
                'color': header.evaluate('(e) => getComputedStyle(e).backgroundColor'),
            }
            page.screenshot(path=str(evidence / (phase + '-' + theme + '-' + str(width) + '.png')))
            modal.get_by_role('button', name='Close', exact=True).last.click()
            expect(modal).to_be_hidden()
    (evidence / (phase + '.json')).write_text(json.dumps(results, indent=2), encoding='utf-8')
    assert all(r['color'] == 'rgb(153, 6, 47)' for r in results.values()), results
    if (evidence / 'before.json').exists() and phase == 'after':
        before = json.loads((evidence / 'before.json').read_text(encoding='utf-8'))
        for key, result in results.items():
            assert result['text'] == before[key]['text'], key + ': metadata changed'
            assert result['height'] < before[key]['height'] * .85, (key, result['height'], before[key]['height'])
    print('PASS: compact red document header; metadata preserved in both themes at 1440/390/320 widths')


if __name__ == '__main__':
    main(checks=run)
