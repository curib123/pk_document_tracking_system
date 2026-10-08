"""Styling isolation, accessibility, and responsive checks; no production data."""
import json, os, pathlib, subprocess
from playwright.sync_api import sync_playwright, expect

ROOT = pathlib.Path(__file__).resolve().parents[1]
assert (ROOT / 'public/assets/js/styling.js').is_file(), 'central theme controller is missing'
assert (ROOT / 'public/assets/css/app.css').is_file(), 'compiled Tailwind CSS is missing'

config = {'enabled': True, 'shell': True, 'default_enabled': False,
          'modules': {'account': True, 'softcopy': True, 'hardcopy': False, 'dashboard': True, 'workflows': True},
          'stylesheet': ''}
templates = subprocess.check_output(['php', '-r', "require 'application/bootstrap.php'; require 'application/helpers/ui_helper.php'; require 'application/views/components/registry.php';"], cwd=ROOT).decode()
fixture = '''<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="pk-styling"></head><body>
<header id="pk-header"><div><span data-pk-decoration hidden>PK / WORKSPACE</span><h1>PK Document Tracking System</h1><p id="pk-context" data-pk-decoration hidden>Document control <strong id="pk-current-section">Softcopy documents</strong></p></div><div><p id="account">Document Control Officer</p><div id="account-actions"><button type=button>My profile</button><button type=button>Change password</button><button type=button>Sign out</button></div></div><button id="pk-menu-toggle" hidden aria-controls="navigation" aria-expanded="false">Modules</button></header>
<p id="global-status" role="status">Ready.</p>
<div id="pk-workspace"><nav id="navigation" aria-label="Modules"><button type=button>Dashboard</button><details open><summary>System Documents</summary><div><button type=button>Softcopy documents</button><button type=button>Hardcopy documents</button></div></details><details open><summary>Requests</summary><div><button type=button>My requests</button><button type=button>My tasks</button></div></details><details open><summary>Document Setup</summary><div><button type=button>Areas</button><button type=button>Locations</button><button type=button>Sequences</button></div></details><details open><summary>Administration</summary><div><button type=button>Workflow builder</button><button type=button>Users</button><button type=button>Roles</button></div></details></nav>
<main id="content" tabindex="-1"><section><h2 data-module-title>Softcopy documents</h2><div data-module-content><div><button type=button>Direct create softcopy</button><button type=button>New document request</button></div><form><label for="table-search">Search</label><input id="table-search" type="search" placeholder="Search records"><button type=button>Search</button></form><div id="table-filters"><label>Sort by <select><option>Document number</option></select></label><button type=button>Reverse order</button><button type=button>Refresh records</button><label>Status <select><option>All statuses</option></select></label></div><p id="table-status" role="status"></p><div id="table-container"><table><thead><tr><th>Document number</th><th>Title</th><th>Status</th><th>Actions</th></tr></thead><tbody><tr><td>DOC-DEMO-001</td><td>Document control procedure</td><td>active</td><td><button type=button>View / actions</button></td></tr><tr><td>DOC-DEMO-002</td><td>Quality assurance checklist</td><td>pending</td><td><button type=button>View / actions</button></td></tr><tr><td>DOC-DEMO-003</td><td>Records retention schedule</td><td>approved</td><td><button type=button>View / actions</button></td></tr></tbody></table></div><div id="table-footer"><span id="page-summary">Showing 1–3 of 3 records</span><label>Rows per page <select><option>25</option></select></label><span id="pagination"><button type=button>Previous</button><button type=button>Next</button></span></div></div></section></main></div></body></html>'''

fixture = fixture.replace('</body>', templates + '</body>')

def switch(page, module):
    page.evaluate("m => { document.documentElement.dataset.pkModule = m; document.dispatchEvent(new CustomEvent('pk:screen',{detail:{module:m}})); }", module)
    page.wait_for_function("m => document.querySelector('#content').dataset.pkModule === m", arg=module)

def metrics(page, selector):
    return page.locator(selector).first.evaluate("e => {const s=getComputedStyle(e); return [s.fontFamily,s.fontSize,s.borderRadius,s.backgroundColor,s.padding,s.margin];}")

if __name__ == '__main__':
    with sync_playwright() as p:
        browser = p.chromium.launch(executable_path=os.getenv('CHROMIUM_PATH','/usr/bin/chromium'), args=['--no-sandbox'])
        page = browser.new_page(viewport={'width':1440,'height':1000})
        errors = []
        page.on('pageerror', lambda e: errors.append(str(e)))
        page.set_content(fixture)
        native_button = metrics(page, '#table-container button')
        native_heading = metrics(page, 'main h2')
        page.evaluate("c => {document.querySelector('meta[name=pk-styling]').content=JSON.stringify(c); document.documentElement.dataset.pkModule='softcopy';}", config)
        config['stylesheet'] = page.evaluate("s => URL.createObjectURL(new Blob([s],{type:'text/css'}))", (ROOT / 'public/assets/css/app.css').read_text())
        page.evaluate("c => document.querySelector('meta[name=pk-styling]').content=JSON.stringify(c)", config)
        views_url = page.evaluate("source => URL.createObjectURL(new Blob([source],{type:'text/javascript'}))", (ROOT / 'public/assets/js/views.js').read_text())
        page.add_script_tag(type='module', content=(ROOT / 'public/assets/js/styling.js').read_text().replace("'./views.js'", json.dumps(views_url)))
        expect(page.locator('#content')).to_have_class('pk-ui')
        page.wait_for_function("getComputedStyle(document.querySelector('#content')).fontFamily.includes('Segoe')")
        assert metrics(page, '#table-container button') != native_button
        switch(page, 'hardcopy')
        expect(page.locator('link[data-pk-styles]')).to_have_count(0)
        assert metrics(page, '#table-container button') == native_button, 'disabled module controls must match native HTML'
        assert metrics(page, 'main h2') == native_heading, 'disabled heading must match native HTML'
        switch(page, 'unregistered')
        assert 'pk-ui' not in page.locator('#content').get_attribute('class')
        switch(page, 'softcopy')
        expect(page.locator('#content')).to_have_class('pk-ui')
        page.evaluate("document.dispatchEvent(new CustomEvent('pk:metadata',{detail:{modules:[{key:'softcopy',label:'Softcopy documents'},{key:'hardcopy',label:'Hardcopy documents'},{key:'workflows',label:'Workflow builder'}]}}))")
        expect(page.get_by_role('button', name='Softcopy documents', exact=True)).to_have_attribute('aria-current','page')
        page.evaluate("() => {const d=document.createElement('dialog'); d.innerHTML='<h2>Review request</h2><form><fieldset><p><label>Remarks<textarea></textarea></label></p></fieldset><footer><button type=button>Cancel</button><button type=button>Confirm</button></footer></form>';document.body.append(d);d.showModal();}")
        expect(page.locator('dialog')).to_have_class('pk-ui')
        page.evaluate("document.dispatchEvent(new CustomEvent('pk:detail',{detail:{module:'hardcopy'}}))")
        expect(page.locator('dialog')).not_to_have_class('pk-ui')
        page.evaluate("document.querySelector('dialog').remove()")
        # Screenshots use clearly synthetic demonstration rows.
        page.screenshot(path=str(ROOT / 'tests/styled-desktop.png'), full_page=True)
        page.set_viewport_size({'width':390,'height':844})
        expect(page.locator('#pk-menu-toggle')).to_be_visible()
        page.locator('#pk-menu-toggle').click()
        expect(page.locator('#pk-menu-toggle')).to_have_attribute('aria-expanded','true')
        page.keyboard.press('Escape')
        expect(page.locator('#pk-menu-toggle')).to_have_attribute('aria-expanded','false')
        assert page.evaluate('document.documentElement.scrollWidth <= innerWidth'), 'page must not overflow horizontally'
        page.screenshot(path=str(ROOT / 'tests/styled-mobile.png'), full_page=True)
        switch(page, 'hardcopy')
        assert metrics(page, 'main h2') == native_heading
        page.screenshot(path=str(ROOT / 'tests/unstyled-mobile.png'), full_page=True)
        # Global false must trump a per-module true.
        page.close()
        page = browser.new_page(viewport={'width':1440,'height':1000})
        page.set_content(fixture)
        config['enabled'] = False
        page.evaluate("c => document.querySelector('meta[name=pk-styling]').content=JSON.stringify(c)", config)
        views_url = page.evaluate("source => URL.createObjectURL(new Blob([source],{type:'text/javascript'}))", (ROOT / 'public/assets/js/views.js').read_text())
        page.add_script_tag(type='module', content=(ROOT / 'public/assets/js/styling.js').read_text().replace("'./views.js'", json.dumps(views_url)))
        switch(page, 'softcopy')
        expect(page.locator('link[data-pk-styles]')).to_have_count(0)
        assert metrics(page, '#table-container button') == native_button
        assert not errors, errors
        browser.close()
    print('PASS: module isolation, native controls, global off, unknown modules, dialogs, navigation and responsive shell.')
