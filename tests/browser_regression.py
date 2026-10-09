#!/usr/bin/env python3
"""Chromium rendering/interaction tests of actual authenticated server responses.

The environment blocks browser localhost navigation. Server requests use urllib;
HTML/CSS/scripts are rendered in an offline Chromium document. Native submissions
and access controls are tested separately in plan_integration.py / smoke.sh.
"""
import base64
import http.cookiejar
import json
import os
from pathlib import Path
import re
import subprocess
import urllib.parse
import urllib.request
from playwright.sync_api import sync_playwright

if os.environ.get('PK_TEST_DB')!='1' or os.environ.get('DB_DATABASE')!='pk_dts_test':
    raise SystemExit('Browser tests require the disposable pk_dts_test database.')
BASE='http://127.0.0.1:8089/'
ROOT=Path(__file__).resolve().parents[1]
ASSETS=Path(os.environ.get('PK_BROWSER_ASSETS','/mnt/data/pk-browser-assets'))
OUT=Path(os.environ.get('PK_BROWSER_RESULTS','/tmp/pk-browser-results'))
OUT.mkdir(parents=True,exist_ok=True)
checks=[]
client=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
def fetch(path,data=None):
    response=client.open(BASE+path,urllib.parse.urlencode(data).encode() if data else None,timeout=20)
    return response.status,response.read().decode()
def check(value,label):
    if not value: raise AssertionError(label)
    checks.append(label);print('PASS:',label,flush=True)
def offline_document(text):
    def style(match):
        url=match.group(1);name=url.rsplit('/',1)[-1]
        path=ROOT/'public/assets/css'/name if '/assets/css/' in url else ASSETS/name
        css=path.read_text() if path.is_file() else ''
        css=re.sub(r'@import\s+url\([^)]*\)\s*;','',css)
        return '<style>'+css+'</style>'
    text=re.sub(r'<link\b[^>]*href="([^"]+)"[^>]*>',style,text)
    def script(match):
        url=match.group(1);name=url.rsplit('/',1)[-1]
        path=ROOT/'public/assets/js'/name if '/assets/js/' in url else ASSETS/name
        return '<script>'+path.read_text().replace('</script','<\\/script')+'</script>' if path.is_file() else ''
    text=re.sub(r'<script\b[^>]*src="([^"]+)"[^>]*>\s*</script>',script,text)
    def image(match):
        rel=match.group(1);path=ROOT/'public'/rel
        if not path.is_file():return match.group(0)
        mime='image/jpeg' if path.suffix.lower() in ['.jpg','.jpeg'] else 'image/png'
        return 'src="data:'+mime+';base64,'+base64.b64encode(path.read_bytes()).decode()+'"'
    return re.sub(r'src="'+re.escape(BASE)+r'(assets/images/[^"]+)"',image,text)
ROUTES=['dashboard','documents/hardcopy','documents/softcopy','places/area','places/specific',
'places/asset','places/location','places/sequence','places/softcopy-categories','my-requests/softcopy',
'my-requests/hardcopy','my-requests/hardcopy-transfer','my-requests/access-grant','my-requests/document-assign',
'my-tasks/softcopy','my-tasks/hardcopy','my-tasks/hardcopy-transfer','my-tasks/access-grant',
'my-tasks/document-assign','admin/users','admin/roles','admin/workflows','admin/document-assignments',
'admin/workflows?publication=draft','admin/workflows?publication=published']
_,login=fetch('login');token=re.search(r'name="pk_csrf_token"\s+value="([^"]+)"',login).group(1)
fetch('login',{'pk_csrf_token':token,'login':'test_admin','password':'TemporaryTestPassword2026!'})
with sync_playwright() as pw:
    executable=os.environ.get('PK_CHROMIUM')
    browser=pw.chromium.launch(headless=True,**({'executable_path':executable} if executable else {}),args=['--no-sandbox'])
    context=browser.new_context(viewport={'width':1440,'height':1000},reduced_motion='reduce')
    context.route('**/*',lambda route:route.abort())
    errors=[]
    def render(route,width=1440):
        page=context.new_page();page.set_viewport_size({'width':width,'height':900})
        page.on('pageerror',lambda error:errors.append(str(error)))
        status,text=fetch(route);check(status==200,f'{width}px {route} returns HTTP 200')
        check('A PHP Error was encountered' not in text,f'{width}px {route} has no PHP warning output')
        page.set_content(offline_document(text),wait_until='load');page.wait_for_timeout(50)
        return page
    for width in ([int(x) for x in os.environ.get('PK_BROWSER_WIDTHS','1440,768,390,320').split(',') if x]):
        for route in ROUTES:
            page=render(route,width)
            overflow=page.evaluate('document.documentElement.scrollWidth > window.innerWidth + 1')
            if overflow: page.screenshot(path=str(OUT/f'overflow-{width}-{route.replace("/","-").replace("?","-")}.png'),full_page=True)
            check(not overflow,f'{width}px {route} has no page-level horizontal overflow')
            if route=='dashboard':
                check(page.evaluate("!!Chart.getChart(document.getElementById('documentTypeChart'))"),f'{width}px chart initializes with actual data')
                page.screenshot(path=str(OUT/f'dashboard-{width}.png'),full_page=True)
                if width<992:
                    page.click('#sidebarToggle');check(page.locator('#sidebarToggle').get_attribute('aria-expanded')=='true',f'{width}px mobile menu opens')
                    page.keyboard.press('Escape');check(page.locator('#sidebarToggle').get_attribute('aria-expanded')=='false',f'{width}px Escape closes mobile menu')
            page.close()
    for width in [1440,390]:
        page=render('documents/softcopy?q=Plan&layout=table',width)
        views=page.locator('.js-view')
        check(views.count()>=2,f'{width}px document fixtures available')
        for index in [0,1]:
            expected=json.loads(views.nth(index).get_attribute('data-display'))
            views.nth(index).click();page.locator('#viewModal').wait_for(state='visible')
            for section in expected['sections']:
                check(section['title'] in page.locator('#viewDetails').inner_text(),f'{width}px selected document shows '+section['title'])
            page.screenshot(path=str(OUT/f'document-details-{width}-{index}.png'),full_page=True)
            box=page.locator('#viewModal .modal-dialog').bounding_box()
            check(box['x']>=-1 and box['x']+box['width']<=width+1,f'{width}px document modal fits horizontally')
            page.locator('#viewModal .modal-footer button').click();page.locator('#viewModal').wait_for(state='hidden')
        page.close()
        page=render('admin/workflows',width)
        workflow=page.locator('.workflow-steps-modal').filter(has=page.locator('.js-workflow-step')).first
        modal_id=workflow.get_attribute('id')
        page.locator('[data-bs-target="#'+modal_id+'"]').click();workflow.wait_for(state='visible')
        workflow.get_by_role('button',name='Create New Step').click()
        page.locator('#stepModal').wait_for(state='visible')
        check(not workflow.is_visible(),f'{width}px workflow parent and step editor do not stack')
        for kind in ['user','role','requester_leader','requester']:
            page.select_option('#stepApproverType',kind)
            check(page.locator('#stepUserField').is_visible()==(kind=='user'),f'{width}px approver user selector follows '+kind)
            check(page.locator('#stepRoleField').is_visible()==(kind=='role'),f'{width}px approver role selector follows '+kind)
        page.fill('#stepName','Browser verified step')
        page.screenshot(path=str(OUT/f'workflow-step-{width}.png'),full_page=True)
        page.locator('#stepModal .modal-footer [data-bs-dismiss]').click();workflow.wait_for(state='visible')
        selector=workflow.locator('[data-workflow-version]')
        choices=selector.locator('option').evaluate_all('(options)=>options.map(o=>({value:o.value,label:o.textContent}))')
        published=next(item['value'] for item in choices if 'Published' in item['label'])
        selector.select_option(published)
        check(workflow.locator('[data-workflow-version-panel]:visible .js-workflow-step').count()==0,f'{width}px published steps are read-only')
        page.close()
        page=render('my-requests/hardcopy-transfer',width)
        page.locator('.js-edit[data-record="{}"]').first.click();page.locator('#editModal').wait_for(state='visible')
        source=page.locator('#transferSource')
        options=source.locator('option[data-transfer-doc]').evaluate_all('(options)=>options.map(o=>({value:o.value,data:JSON.parse(o.dataset.transferDoc)}))')
        check(len(options)>1,f'{width}px multiple hardcopy transfer fixtures available')
        for index,option in enumerate(options[:2]):
            source.select_option(option['value'])
            check(page.input_value('#original-title')==option['data']['title'],f'{width}px transfer selection populates the exact source title')
            check(page.input_value('#original-holder_name')==option['data']['holder_name'],f'{width}px transfer selection populates the exact current custodian')
            if index==0:
                page.select_option('#transferRecipient',index=1)
                locations=page.locator('#transfer-location option').evaluate_all('(options)=>options.filter(o=>o.value&&!o.disabled).map(o=>o.value)')
                check(bool(locations),f'{width}px a valid destination is available')
                page.select_option('#transfer-location',locations[0])
            else:
                check(page.input_value('#transferRecipient')=='' and page.input_value('#transfer-location')=='',f'{width}px switching documents clears stale recipient and destination')
        source.select_option('')
        check(page.input_value('#original-title')=='' and page.input_value('#original-holder_name')=='',f'{width}px clearing document selection clears original metadata')
        page.screenshot(path=str(OUT/f'transfer-modal-{width}.png'),full_page=True)
        page.close()
    # Persistent first-login screen: no application data or navigation behind the dialog.
    subprocess.run(['mysql','-h127.0.0.1','-uroot','-prootpass','pk_dts_test','-e',
        "UPDATE users SET require_password_change=1 WHERE username='plan_first_login'"],check=True)
    client=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
    _,login=fetch('login');token=re.search(r'name="pk_csrf_token"\s+value="([^"]+)"',login).group(1)
    fetch('login',{'pk_csrf_token':token,'login':'plan_first_login','password':'PermanentPlanPassword2026!'})
    for width in [1440,390,320]:
        page=render('change-password',width)
        check(page.locator('#requiredPasswordModal').is_visible(),f'{width}px mandatory first-login dialog is visible')
        check(page.locator('#appSidebar').count()==0 and page.locator('#requestChart').count()==0,
              f'{width}px onboarding does not render protected navigation or analytics')
        page.keyboard.press('Escape')
        check(page.locator('#requiredPasswordModal').is_visible(),f'{width}px Escape cannot dismiss required password setup')
        page.fill('#setupNewPassword','NewBrowserPassword2026!');page.fill('#setupConfirmPassword','Mismatch2026!')
        check(not page.locator('#setupConfirmPassword').evaluate('(input)=>input.checkValidity()'),f'{width}px password confirmation has an accessible validation state')
        page.screenshot(path=str(OUT/f'first-login-{width}.png'),full_page=True);page.close()
    check(not errors,'No uncaught JavaScript errors across desktop/tablet/mobile module responses: '+str(errors))
    (OUT/'results.json').write_text(json.dumps({'passed':len(checks),'checks':checks,'errors':errors,'mode':'offline Chromium render of live authenticated PHP responses'},indent=2))
    browser.close()
