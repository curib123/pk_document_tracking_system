"""Real browser + real CI3 + real MySQL. Run after native_routes.py in test CI."""
import os,secrets
from playwright.sync_api import sync_playwright,expect
if os.environ.get('PK_TEST_DB')!='1' or not os.environ.get('DB_DATABASE','').endswith('_test'):
    raise SystemExit('Requires a dedicated *_test database.')
base=os.environ['APP_URL'].rstrip('/')
with sync_playwright() as p:
    browser=p.chromium.launch(executable_path=os.environ.get('CHROMIUM_PATH','/usr/bin/chromium'),headless=True,args=['--no-sandbox'])
    page=browser.new_page();page.set_default_timeout(10000)
    errors=[];paths=[]
    page.on('pageerror',lambda error:errors.append(str(error)))
    page.on('request',lambda request:paths.append(request.url))
    page.goto(base+'/index.php/areas')
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
    assert page.evaluate("document.querySelectorAll('style,link[rel=stylesheet],[style]').length")==0
    page.goto(base+'/index.php/softcopy')
    expect(page.locator('main h2')).to_have_text('Softcopy documents')
    assert any('/index.php/areas/save' in url for url in paths)
    assert not errors,errors
    browser.close()
print('PASS real native-route modal login, module deep links, persistence, duplicate-save recovery, focus, and no CSS.')
