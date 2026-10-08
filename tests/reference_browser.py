"""Reference UI against real CI3/MySQL; run after native_routes.py on a test DB."""
import os
from playwright.sync_api import sync_playwright, expect

if os.environ.get('PK_TEST_DB') != '1' or not os.environ.get('DB_DATABASE', '').endswith('_test'):
    raise SystemExit('Requires an isolated *_test database.')
base = os.environ['APP_URL'].rstrip('/')
with sync_playwright() as p:
    browser = p.chromium.launch(executable_path=os.environ.get('CHROMIUM_PATH', '/usr/bin/chromium'), headless=True, args=['--no-sandbox'])
    context = browser.new_context(viewport={'width': 1440, 'height': 900})
    page = context.new_page()
    page.set_default_timeout(10000)
    errors = []
    page.on('pageerror', lambda error: errors.append(str(error)))
    page.goto(base + '/index.php/dashboard')
    login = page.get_by_role('dialog', name='Sign in', exact=True)
    expect(login.locator('.ws-login-hero')).to_be_visible()
    expect(login.get_by_role('heading', name='Welcome back', exact=True)).to_be_visible()
    page.wait_for_function("[...document.querySelectorAll('.ws-login-brand img')].every(image => image.complete && image.naturalWidth > 0)")
    assert page.evaluate("getComputedStyle(document.querySelector('.ws-login-scene')).backgroundImage.includes('building.webp')")
    expect(login.get_by_label('Username', exact=True)).to_have_attribute('autocomplete', 'username')
    login.get_by_label('Username', exact=True).fill('admin')
    login.get_by_label('Password', exact=True).fill(os.environ['CI_ROUTE_PASSWORD'])
    login.get_by_role('button', name='Show password', exact=True).click()
    expect(login.get_by_label('Password', exact=True)).to_have_attribute('type', 'text')
    login.get_by_role('button', name='Hide password', exact=True).click()
    expect(login.get_by_label('Password', exact=True)).to_have_attribute('type', 'password')
    login.get_by_label('Remember username on this device', exact=True).check()
    login.get_by_role('button', name='Sign in', exact=True).click()
    expect(login).not_to_be_visible()
    expect(page.locator('.ws-dashboard')).to_be_visible()
    expect(page.locator('.ws-kpis')).to_contain_text('Documents available to you')
    expect(page.locator('.ws-kpis')).to_contain_text('My requests in workflow')
    assert page.evaluate("localStorage.getItem('pk.workspace.username')") == 'admin'
    assert os.environ['CI_ROUTE_PASSWORD'] not in page.evaluate("JSON.stringify(localStorage)")
    assert page.evaluate("getComputedStyle(document.querySelector('#pk-sidebar')).backgroundColor") == 'rgb(28, 16, 21)'
    page.get_by_role('button', name='Toggle dark mode', exact=True).click()
    expect(page.locator('body')).to_have_attribute('data-ws-mode', 'dark')
    page.reload()
    expect(page.locator('.ws-dashboard')).to_be_visible()
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
    assert page.evaluate('document.documentElement.scrollWidth <= innerWidth + 1'), 'Dashboard must not overflow a narrow viewport'
    page.set_viewport_size({'width': 1440, 'height': 900})
    page.locator('#ws-sign-out').click()
    confirm = page.get_by_role('dialog', name='Sign out', exact=True)
    if confirm.count():
        confirm.get_by_role('button', name='Confirm', exact=True).click()
        close = confirm.get_by_role('button', name='Close', exact=True)
        if close.count() and close.is_visible():
            close.click()
    login = page.get_by_role('dialog', name='Sign in', exact=True)
    expect(login).to_be_visible()
    page.set_viewport_size({'width': 390, 'height': 844})
    expect(login.get_by_label('Username', exact=True)).to_have_value('admin')
    expect(login.get_by_label('Password', exact=True)).to_have_value('')
    assert login.evaluate('(node) => node.scrollWidth <= innerWidth + 1'), 'Login must not overflow a narrow viewport'
    assert not errors, errors
    browser.close()
print('PASS real reference login, local assets, password visibility, username-only remembrance, dashboard, theme persistence, profile, mobile navigation and sign-out.')
