"""Exercise real font/icon assets and graceful CDN failure on an isolated CI3 app."""
import os
from playwright.sync_api import sync_playwright, expect

if os.environ.get('PK_TEST_DB') != '1' or not os.environ.get('DB_DATABASE', '').endswith('_test'):
    raise SystemExit('Requires a dedicated *_test database.')

base = os.environ['APP_URL'].rstrip('/')
with sync_playwright() as p:
    browser = p.chromium.launch(
        executable_path=os.environ.get('CHROMIUM_PATH', '/usr/bin/chromium'),
        headless=True, args=['--no-sandbox'])
    context = browser.new_context(viewport={'width': 1440, 'height': 900})
    page = context.new_page()
    page.set_default_timeout(15000)
    errors = []
    page.on('pageerror', lambda error: errors.append(str(error)))
    response = page.goto(base + '/index.php/dashboard')
    policy = response.headers.get('content-security-policy', '')
    assert "style-src 'self' https://cdnjs.cloudflare.com https://fonts.googleapis.com" in policy
    assert "font-src 'self' https://cdnjs.cloudflare.com https://fonts.gstatic.com" in policy
    assert "script-src 'self'" in policy and 'unsafe-inline' not in policy and 'unsafe-eval' not in policy
    expect(page.locator('link[data-pk-font-awesome]')).to_have_count(1)
    expect(page.locator('link[data-pk-poppins]')).to_have_count(1)
    login = page.get_by_role('dialog', name='Sign in', exact=True)
    expect(login.get_by_label('Username', exact=True)).to_be_visible()
    expect(login.locator('button[type="submit"] > .ws-icon')).to_have_count(1)
    assert page.locator('svg.ws-icon').count() == 0, 'Icon markup must use Font Awesome, not the former SVG renderer'
    page.wait_for_function("""() => [...document.fonts].some(font => font.family.includes('Poppins') && font.status === 'loaded')
        && [...document.fonts].some(font => font.family.includes('Font Awesome 6 Free') && font.status === 'loaded')""", timeout=30000)
    assert login.evaluate("node => getComputedStyle(node).fontFamily.includes('Poppins')")
    assert login.locator('.ws-icon').first.evaluate("node => getComputedStyle(node, '::before').content") not in ['none', 'normal', '""']

    # Repeated DOM changes must not insert the icon over and over.
    username = login.get_by_label('Username', exact=True)
    username.fill('admin')
    login.get_by_label('Password', exact=True).fill('Wrong-test-password')
    login.get_by_role('button', name='Sign in', exact=True).click()
    expect(login.get_by_role('alert')).to_be_visible()
    expect(login.locator('button[type="submit"] > .ws-icon')).to_have_count(1)
    login.get_by_label('Password', exact=True).fill(os.environ['CI_ROUTE_PASSWORD'])
    login.get_by_role('button', name='Sign in', exact=True).click()
    expect(page.locator('.ws-dashboard')).to_be_visible()
    toggle = page.get_by_role('button', name='Toggle dark mode', exact=True)
    for _ in range(4):
        toggle.click()
        expect(toggle.locator('.ws-icon')).to_have_count(1)
    assert toggle.locator('.ws-icon').get_attribute('class').find('fa-regular') >= 0
    assert page.locator('.ws-donut svg').count() == 1, 'The chart remains SVG; it is not a Font Awesome icon'
    page.set_viewport_size({'width': 390, 'height': 844})
    expect(page.locator('.ws-dashboard')).to_be_visible()
    assert page.evaluate('document.documentElement.scrollWidth <= innerWidth + 1'), 'Poppins must not introduce mobile overflow'
    assert not errors, errors
    context.close()

    # A CDN outage must not block the local stylesheet or real sign-in flow.
    offline = browser.new_context(viewport={'width': 390, 'height': 844})
    for pattern in ['https://cdnjs.cloudflare.com/**', 'https://fonts.googleapis.com/**', 'https://fonts.gstatic.com/**']:
        offline.route(pattern, lambda route: route.abort())
    page = offline.new_page()
    page.set_default_timeout(15000)
    errors = []
    page.on('pageerror', lambda error: errors.append(str(error)))
    page.goto(base + '/index.php/dashboard')
    login = page.get_by_role('dialog', name='Sign in', exact=True)
    expect(login.get_by_label('Username', exact=True)).to_be_visible()
    expect(login.get_by_role('button', name='Sign in', exact=True)).to_be_visible()
    login.get_by_label('Username', exact=True).fill('admin')
    login.get_by_label('Password', exact=True).fill(os.environ['CI_ROUTE_PASSWORD'])
    login.get_by_role('button', name='Sign in', exact=True).click()
    expect(page.locator('.ws-dashboard')).to_be_visible()
    assert page.evaluate('document.documentElement.scrollWidth <= innerWidth + 1')
    assert not errors, errors
    offline.close()
    browser.close()
print('PASS live Font Awesome/Poppins, CSP, view-owned icons, duplicate-icon regression, mobile layout and blocked-CDN sign-in fallback.')
