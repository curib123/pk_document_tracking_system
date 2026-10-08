"""Real browser + real CI3 + real MySQL. Run after native_routes.py in test CI."""
import json,os,secrets
from playwright.sync_api import sync_playwright,expect
if os.environ.get('PK_TEST_DB')!='1' or not os.environ.get('DB_DATABASE','').endswith('_test'):
    raise SystemExit('Requires a dedicated *_test database.')
base=os.environ['APP_URL'].rstrip('/')
with sync_playwright() as p:
    browser=p.chromium.launch(executable_path=os.environ.get('CHROMIUM_PATH','/usr/bin/chromium'),headless=True,args=['--no-sandbox'])
    page=browser.new_page();page.set_default_timeout(10000)
    errors=[];paths=[];css_responses=[]
    page.on('pageerror',lambda error:errors.append(str(error)))
    page.on('request',lambda request:paths.append(request.url))
    page.on('response',lambda response:css_responses.append(response.status) if '/assets/css/app.css' in response.url else None)
    response=page.goto(base+'/index.php/areas')
    policy=response.headers.get('content-security-policy','')
    assert "style-src 'self'" in policy, 'Locally compiled CSS must be permitted by the real response policy'
    assert "script-src 'self'" in policy and "object-src 'none'" in policy
    assert 'unsafe-inline' not in policy and 'unsafe-eval' not in policy
    config=json.loads(page.locator('meta[name="pk-styling"]').get_attribute('content'))
    area_styled=config.get('enabled') is True and config.get('modules',{}).get('areas',config.get('default_enabled')) is True
    dialog=page.get_by_role('dialog',name='Sign in',exact=True)
    dialog.get_by_label('Username',exact=True).fill('admin')
    dialog.get_by_label('Password',exact=True).fill(os.environ['CI_ROUTE_PASSWORD'])
    dialog.get_by_role('button',name='Sign in',exact=True).click()
    expect(page.locator('main h2')).to_have_text('Areas')
    page.get_by_role('button',name='Add area',exact=True).click()
    create=page.get_by_role('dialog',name='Add area',exact=True)
    name='Live modal '+secrets.token_hex(4)
    create.get_by_label('Name',exact=True).fill(name)
    create.get_by_role('button',name='Save',exact=True).click()
    expect(create.get_by_role('heading',name='Result',exact=True)).to_be_visible()
    create.get_by_role('button',name='Close',exact=True).click()
    expect(page.get_by_role('cell',name=name,exact=True)).to_be_visible()
    page.get_by_role('button',name='Add area',exact=True).click()
    create=page.get_by_role('dialog',name='Add area',exact=True)
    create.get_by_label('Name',exact=True).fill(name)
    create.get_by_role('button',name='Save',exact=True).click()
    expect(create.get_by_role('alert')).to_contain_text('duplicate')
    expect(create.get_by_label('Name',exact=True)).to_have_value(name)
    expect(create.get_by_role('button',name='Save',exact=True)).to_be_enabled()
    page.keyboard.press('Escape')
    expect(page.get_by_role('button',name='Add area',exact=True)).to_be_focused()
    if area_styled:
        expect(page.locator('main')).to_have_class('pk-ui')
        expect(page.locator('link[data-pk-styles]')).to_have_count(1)
        page.wait_for_function("getComputedStyle(document.querySelector('main')).fontFamily.includes('Segoe')")
        assert 200 in css_responses, 'A real local stylesheet response must load, not just a CSS class'
    else:
        expect(page.locator('main.pk-ui')).to_have_count(0)
        expect(page.locator('link[data-pk-styles]')).to_have_count(0)
    page.goto(base+'/index.php/softcopy')
    expect(page.locator('main h2')).to_have_text('Softcopy documents')
    expect(page.locator('main')).to_have_attribute('data-pk-module','softcopy')
    assert any('/index.php/areas/save' in url for url in paths)
    assert not errors,errors
    browser.close()
print('PASS real native-route modal login, configured styling, CSP-safe local CSS, deep links, persistence, duplicate-save recovery and focus.')
