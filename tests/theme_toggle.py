"""Verify the topbar theme control using the disposable site/browser fixture."""
import subprocess

from playwright.sync_api import expect
from cancelled_draft_delete import PHP, PASSWORD, ENV, ROOT, sql, main


def login(page, base):
    hashed = subprocess.run([PHP, '-r', "echo password_hash(getenv('PK_TEST_PASSWORD'), PASSWORD_DEFAULT);"],
                            env=ENV, capture_output=True, check=True).stdout.hex()
    role = sql("SELECT id FROM roles WHERE name='Administrator' LIMIT 1")
    sql("INSERT INTO users(username,first_name,last_name,position_title,role_id,password_hash,require_password_change) "
        "VALUES('theme-test','Theme','Test','Tester'," + role + ",CONVERT(0x" + hashed + " USING utf8mb4),0)")
    page.emulate_media(color_scheme='light', reduced_motion='reduce')
    page.goto(base + 'login')
    page.locator('[name="login"]').fill('theme-test')
    page.locator('[name="password"]').fill(PASSWORD)
    page.locator('button[type="submit"]').click()


def run(page, base, site):
    login(page, base)
    toggle = page.get_by_role('button', name='Switch to dark mode', exact=True)
    expect(toggle).to_be_visible()
    expect(toggle.locator('i')).to_have_class('fa-solid fa-moon')
    assert page.evaluate('document.fonts.load(\'900 16px "PK Theme Icons"\', "\\uf186").then(fonts => fonts.length > 0)'), 'Local Font Awesome font must load'
    assert toggle.evaluate('(e) => e.nextElementSibling.id') == 'notificationBellPanel'
    before = page.locator('body').evaluate('(e) => getComputedStyle(e).backgroundColor')
    toggle.click()
    expect(page.locator('html')).to_have_attribute('data-theme', 'dark')
    expect(page.get_by_role('button', name='Switch to light mode').locator('i')).to_have_class('fa-solid fa-sun')
    after = page.locator('body').evaluate('(e) => getComputedStyle(e).backgroundColor')
    assert before != after
    assert page.locator('body').evaluate('(e) => getComputedStyle(e).color') != after
    page.reload()
    expect(page.locator('html')).to_have_attribute('data-theme', 'dark')
    for route in ['dashboard', 'documents/hardcopy', 'documents/softcopy', 'my-requests/softcopy', 'admin/users', 'admin/roles']:
        page.goto(base + route + '?period=all')
        expect(page.locator('html')).to_have_attribute('data-theme', 'dark')
        expect(page.locator('.page-content')).to_be_visible()
        assert 'Database Error' not in page.locator('body').inner_text()
        if route == 'documents/softcopy':
            create = page.locator('.table-toolbar .btn-primary')
            create.hover()
            expect(create).to_have_css('color', 'rgb(40, 21, 31)')
            page.mouse.move(0, 0)
        if route == 'dashboard':
            # Banner copy must stay light against its retained dark gradient.
            color = page.locator('.dashboard-welcome p').evaluate('(e) => getComputedStyle(e).color')
            channels = [int(c.strip()) for c in color.removeprefix('rgb(').removesuffix(')').split(',')]
            assert min(channels) > 180, color
    evidence = ROOT / 'storage/theme-checks'
    evidence.mkdir(parents=True, exist_ok=True)
    for width in [1440, 390, 320]:
        page.set_viewport_size({'width': width, 'height': 950})
        page.goto(base + 'documents/softcopy?period=all')
        expect(page.get_by_role('button', name='Switch to light mode')).to_be_visible()
        assert page.locator('.topbar').evaluate('(e) => e.scrollWidth <= e.clientWidth + 1'), width
        page.screenshot(path=str(evidence / ('dark-' + str(width) + '.png')), full_page=True)
    page.get_by_role('button', name='Switch to light mode').focus()
    page.keyboard.press('Enter')
    expect(page.locator('html')).to_have_attribute('data-theme', 'light')
    page.reload()
    expect(page.locator('html')).to_have_attribute('data-theme', 'light')
    # An explicit choice remains in effect when the OS theme changes.
    page.emulate_media(color_scheme='dark')
    expect(page.locator('html')).to_have_attribute('data-theme', 'light')
    page.evaluate('localStorage.removeItem("pk-dts-theme")')
    page.reload()
    expect(page.locator('html')).to_have_attribute('data-theme', 'dark')
    # Storage may be blocked by browser policy; the toggle still works.
    page.add_init_script('Object.defineProperty(window, "localStorage", {get() {throw new Error("Blocked");}})')
    page.reload()
    page.get_by_role('button', name='Switch to light mode').click()
    expect(page.locator('html')).to_have_attribute('data-theme', 'light')
    print('PASS: sun/moon toggle, theme persistence, keyboard, system preference, blocked storage, six pages and 1440/390/320 layouts')


if __name__ == '__main__':
    main(checks=run)
