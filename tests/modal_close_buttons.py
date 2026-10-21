"""Dark-mode close controls must stay dark and still dismiss their modal."""
from playwright.sync_api import expect
from cancelled_draft_delete import main
from theme_toggle import login
from dark_mode_feedback import colors


def run(page, base, site):
    login(page, base)
    page.evaluate('localStorage.setItem("pk-dts-theme", "dark")')
    checked = 0
    for route in ['dashboard', 'documents/softcopy', 'admin/users', 'admin/workflows', 'admin/document-assignments']:
        page.goto(base + route)
        for button in page.locator('.modal .btn-close').all():
            background = colors(button)['background']
            assert background[3] == 0 or max(background[:3]) < 120, (route, background)
            assert button.evaluate('(e) => getComputedStyle(e).filter') == 'none', route + ': inversion makes dark buttons white'
            checked += 1
    page.goto(base + 'documents/softcopy')
    page.locator('tbody .js-view').first.click()
    view = page.locator('#viewModal')
    expect(view).to_be_visible()
    close = view.locator('.modal-header .btn-close')
    assert max(colors(close)['background'][:3]) < 120
    close.click()
    expect(view).to_be_hidden()
    page.locator('#passwordModal').evaluate('(e) => window.bootstrap.Modal.getOrCreateInstance(e).show()')
    password = page.locator('#passwordModal')
    expect(password).to_be_visible()
    password.locator('.btn-close').click()
    expect(password).to_be_hidden()
    print('PASS: dark close backgrounds/icons across ' + str(checked) + ' controls; View and Password dismissal')


if __name__ == '__main__':
    main(checks=run)
