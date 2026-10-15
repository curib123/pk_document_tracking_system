'use strict';
// Contract tests: run without a browser, so these do NOT replace manual UI testing.
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const read = path => fs.readFileSync(path, 'utf8');
const live = read('public/assets/js/live-table.js');
const app = read('public/assets/js/app.js');
const view = read('public/assets/js/document-view.js');
const table = read('application/view/reusable_components/reusable_datatable.php');
const request = read('application/view/pages/request/shared_index.php');
const footer = read('application/view/layout/footer.php');

test('live table keeps requests scoped and discards stale responses', () => {
    assert.match(live, /new AbortController\(\)/);
    assert.match(live, /if \(ticket !== version\) return;/);
    assert.match(live, /headers: \{ 'X-PK-Table': '1' \}/);
    assert.match(live, /response\.headers\.get\('X-PK-Table'\)/);
    assert.match(live, /fetch\(url\.href/);
});
test('live table preserves form, browser history, and reinitializes delegated controls', () => {
    assert.match(live, /\[data-table-results\]/);
    assert.match(live, /form\.requestSubmit\(\)/);
    assert.match(live, /window\.history\[historyMethod\]/);
    assert.match(live, /window\.addEventListener\('popstate'/);
    assert.match(live, /pk:table-updated/);
    assert.match(live, /\[data-auto-submit\]/);
});
test('live search provides loading, timeout, retry, and IME handling', () => {
    for (const term of ['Updating records', 'Retry', 'compositionstart', 'compositionend', 'aria-busy', '15000']) {
        assert.ok(live.includes(term), 'Missing: ' + term);
    }
});
test('request View uses delegated handlers and shared modal', () => {
    assert.match(request, /'type' => 'view'/);
    assert.match(app, /event\.target\.closest\('\.js-view'\)/);
    assert.match(app, /openRecordDetails\(view\.getAttribute\('data-display'\)/);
    assert.match(table, /data-row-view/);
    assert.match(footer, /id="viewModal"/);
    assert.match(footer, /id="viewDetails"/);
    assert.match(view, /window\.PKDocumentView\s*=/);
});
test('softcopy action buttons are permission-filtered in the server-rendered view', () => {
    const docs = read('application/view/pages/documents/shared_index.php');
    for (const action of ['history', 'print', 'download']) {
        assert.ok(docs.includes("canFile('" + action + "')"), action + ' must have a dedicated permission gate');
    }
    const controller = read('application/controllers/Files.php');
    assert.match(controller, /require_permission\('files', 'print'\)/);
    assert.match(controller, /require_permission\('files', 'download'\)/);
    assert.match(controller, /safe_download_name/);
});
