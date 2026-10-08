"""PHP view markup + real browser controllers, using an explicit simulated API.
The separate MySQL CI suites remain responsible for backend integration.
"""
import json
import os
from pathlib import Path
import subprocess
import base64
import re
from urllib.parse import urlparse, parse_qs
from playwright.sync_api import sync_playwright, expect

ROOT = Path(__file__).resolve().parents[1]
META_CODE = "require 'application/bootstrap.php'; echo json_encode(['modules'=>array_values(Pk\\Core\\UiSchema::modules()),'document_fields'=>['softcopy'=>Pk\\Core\\UiSchema::documentFields('softcopy'),'hardcopy'=>Pk\\Core\\UiSchema::documentFields('hardcopy')],'request_fields'=>Pk\\Core\\UiSchema::requestFields(),'request_types'=>Request_service::TYPES,'workflow_template'=>Pk\\Core\\WorkflowGraph::defaults()]);"
metadata = json.loads(subprocess.check_output(['php', '-r', META_CODE], cwd=ROOT))
permissions = json.loads(subprocess.check_output(['php', '-r', "require 'application/bootstrap.php'; $p=[]; foreach(Pk\\Core\\Seed::capabilities() as $m=>$actions) foreach($actions as $a) $p[]=$m.'.'.$a; echo json_encode($p);"], cwd=ROOT))
routes = json.loads(subprocess.check_output(['php', '-r', "require 'application/bootstrap.php'; echo json_encode((new Endpoint_registry())->browserRoutes());"], cwd=ROOT))
user = {'id': 1, 'username': 'admin', 'first_name': 'Admin', 'last_name': 'Demo', 'position_title': 'Administrator', 'require_password_change': 0}
state = {'signed_in': False}

def api(operation, data):
    if operation == 'session':
        result = {'user': user if state['signed_in'] else None, 'permissions': permissions if state['signed_in'] else []}
    elif operation == 'auth.login':
        state['signed_in'] = True
        result = {'user': user, 'permissions': permissions}
    elif operation == 'auth.logout':
        state['signed_in'] = False
        result = {'message': 'Signed out.'}
    elif operation == 'metadata':
        result = {**metadata, 'user': user, 'permissions': permissions}
    elif operation == 'dashboard':
        result = {
            'softcopy': [{'status': 'active', 'total': 18}, {'status': 'disposed', 'total': 2}],
            'hardcopy': [{'status': 'active', 'total': 120}],
            'my_requests': [{'status': 'pending', 'total': 4}],
            'unread_notifications': 3, 'pending_receipts': 0,
            'recent_documents': [{'id': 7, 'domain': 'softcopy', 'document_number': 'DEMO-001', 'title': 'Quality management procedure', 'status': 'active', 'created_at': '2026-10-08 09:15:00'}]
        }
    elif operation == 'list':
        result = {'rows': [], 'total': 0, 'page': 1, 'pages': 1, 'limit': 25}
    elif operation == 'lookups':
        result = {'options': [], 'more': False}
    else:
        result = {'message': 'Fixture operation recorded.'}
    return {'ok': True, 'data': result, 'csrf': 'a' * 64}


with sync_playwright() as p:
    browser = p.chromium.launch(executable_path=os.getenv('CHROMIUM_PATH', '/usr/bin/chromium'), headless=True, args=['--no-sandbox'])
    page = browser.new_page(viewport={'width': 1440, 'height': 900})
    page.set_default_timeout(8000)
    errors = []
    page.on('pageerror', lambda error: errors.append(str(error)))
    page.expose_function('view_api', api)
    render = "require 'application/bootstrap.php'; require 'application/helpers/ui_helper.php'; $initial_module=''; require 'application/views/templates/header.php'; require 'application/views/modules/index.php'; require 'application/views/templates/footer.php';"
    html = subprocess.check_output(['php', '-r', render], cwd=ROOT).decode()
    html = re.sub(r'<script[^>]*>.*?</script>', '', html, flags=re.S)
    for image in (ROOT / 'public/assets/images').glob('*.webp'):
        data_url = 'data:image/webp;base64,' + base64.b64encode(image.read_bytes()).decode()
        html = html.replace('http://localhost/assets/images/' + image.name, data_url)
    page.set_content(html)
    config = page.evaluate("JSON.parse(document.querySelector('meta[name=pk-styling]').content)")
    for key, relative in [('stylesheet', 'app.css'), ('reference_stylesheet', 'workspace.css')]:
        css = (ROOT / 'public/assets/css' / relative).read_text()
        for image in (ROOT / 'public/assets/images').glob('*.webp'):
            data_url = 'data:image/webp;base64,' + base64.b64encode(image.read_bytes()).decode()
            css = css.replace('../images/' + image.name, data_url)
        config[key] = page.evaluate("source=>URL.createObjectURL(new Blob([source],{type:'text/css'}))", css)
    page.evaluate("config=>document.querySelector('meta[name=pk-styling]').content=JSON.stringify(config)", config)
    page.evaluate("""routes => {
      window.fetch = async (url, options={}) => {
        const parsed = new URL(url);
        const path = parsed.pathname.split('/index.php/')[1];
        const operation = Object.keys(routes).find(key=>routes[key]===path)?.split('@')[0];
        const data = options.method==='POST' ? JSON.parse(options.body||'{}') : Object.fromEntries(parsed.searchParams);
        return new Response(JSON.stringify(await window.view_api(operation,data)), {status:200,headers:{'Content-Type':'application/json'}});
      };
    }""", routes)
    blobs = {}
    for name in ['views.js', 'workspace-icons.js', 'workspace-data.js', 'styling.js', 'workspace.js', 'api.js', 'components.js', 'forms.js', 'workflow-ui.js', 'app.js']:
        source = (ROOT / 'public/assets/js' / name).read_text()
        for dependency, blob in blobs.items():
            source = source.replace("'./" + dependency + "'", json.dumps(blob))
        blobs[name] = page.evaluate("source=>URL.createObjectURL(new Blob([source],{type:'text/javascript'}))", source)
    page.evaluate('url=>import(url)', blobs['workspace.js'])
    page.evaluate('url=>import(url)', blobs['app.js'])
    assert '<template id="login-workspace-template">' in html
    assert '<template id="dashboard-workspace-template">' in html
    assert 'Secure access' in html, 'The server, not JavaScript, must deliver the login markup'
    for module in metadata['modules']:
        assert ('id="page-' + module['key'] + '-template"') in html

    login = page.get_by_role('dialog', name='Sign in', exact=True)
    expect(login.locator('.ws-login-hero')).to_be_visible()
    expect(login.get_by_role('heading', name='Welcome back', exact=True)).to_be_visible()
    page.wait_for_function("[...document.querySelectorAll('.ws-login-scene img')].every(i=>i.complete&&i.naturalWidth>0)")
    login.get_by_label('Username', exact=True).fill('admin')
    login.get_by_label('Password', exact=True).fill('test-password-not-production')
    login.get_by_role('button', name='Show password', exact=True).click()
    expect(login.get_by_label('Password', exact=True)).to_have_attribute('type', 'text')
    login.get_by_role('button', name='Hide password', exact=True).click()
    expect(login.get_by_label('Password', exact=True)).to_have_attribute('type', 'password')
    page.screenshot(path=str(ROOT / 'tests/views-login-desktop.png'), full_page=True)
    page.set_viewport_size({'width': 390, 'height': 844})
    page.screenshot(path=str(ROOT / 'tests/views-login-mobile.png'), full_page=True)
    assert login.evaluate('(d) => d.scrollWidth <= innerWidth + 1')
    page.screenshot(path=str(ROOT / 'tests/views-login-mobile.png'), full_page=True)
    page.set_viewport_size({'width': 1440, 'height': 900})
    login.get_by_role('button', name='Sign in', exact=True).click()
    expect(login).not_to_be_visible()
    expect(page.locator('.ws-dashboard')).to_be_visible()
    expect(page.locator('.ws-kpi-featured [data-bind="total"]')).to_have_text('140')
    expect(page.locator('[data-bind="active"]')).to_have_text('138')
    expect(page.locator('[data-bind="pending"]')).to_have_text('4')
    expect(page.locator('.ws-document-title')).to_contain_text('Quality management procedure')
    page.screenshot(path=str(ROOT / 'tests/views-dashboard-desktop.png'), full_page=True)
    page.get_by_role('button', name='Toggle dark mode', exact=True).click()
    expect(page.locator('body')).to_have_attribute('data-ws-mode', 'dark')
    page.get_by_role('button', name='Toggle dark mode', exact=True).click()
    page.locator('#pk-account-menu > summary').click()
    page.get_by_role('button', name='My profile', exact=True).click()
    profile = page.get_by_role('dialog', name='My profile', exact=True)
    expect(profile.get_by_label('Username', exact=True)).to_have_value('admin')
    profile.get_by_role('button', name='Cancel', exact=True).click()
    page.set_viewport_size({'width': 390, 'height': 844})
    page.get_by_role('button', name='Modules', exact=True).click()
    expect(page.locator('#pk-sidebar')).to_be_visible()
    page.keyboard.press('Escape')
    expect(page.locator('#pk-sidebar')).not_to_be_visible()
    assert page.evaluate('document.documentElement.scrollWidth <= innerWidth + 1')
    page.screenshot(path=str(ROOT / 'tests/views-dashboard-mobile.png'), full_page=True)
    page.set_viewport_size({'width': 1440, 'height': 900})
    # A disabled module still uses its own view and native controls.
    for module in metadata['modules']:
        if module.get('navigation_hidden'):
            continue
        button = page.locator('#navigation button').filter(has_text=re.compile('^' + re.escape(module['label']) + '$'))
        parent = button.locator('xpath=ancestor::details[1]')
        if parent.count() and not parent.evaluate('(node) => node.open'):
            parent.locator('summary').click()
        button.click()
        expect(page.locator('main h2')).to_have_text(module['label'])
        expect(page.locator('[data-page="' + module['key'] + '"]')).to_be_visible()
    assert not errors, errors
    # Binding text must never execute server-supplied HTML.
    page.evaluate("""async url => {
      const { detailRow } = await import(url);
      const row = detailRow('Title: ', '<img src=x onerror=alert(1)>');
      document.querySelector('main').append(row);
    }""", blobs['views.js'])
    expect(page.locator('main img')).to_have_count(0)
    expect(page.locator('main')).to_contain_text('<img src=x onerror=alert(1)>')
    browser.close()
print('PASS PHP-owned pages/components, safe text binding, login, dashboard, profile, themes, mobile layout and all module views. API responses were simulated.')
