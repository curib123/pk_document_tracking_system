"""Verify dark overlays/shadows and legible notification/count badges."""
from playwright.sync_api import expect
from cancelled_draft_delete import ROOT, sql, main
from theme_toggle import login


def colors(locator):
    return locator.evaluate('''e => {
      const css = getComputedStyle(e), canvas = document.createElement('canvas');
      canvas.width = canvas.height = 1;
      const ctx = canvas.getContext('2d');
      function rgb(color) {
        ctx.clearRect(0,0,1,1); ctx.fillStyle=color; ctx.fillRect(0,0,1,1);
        return Array.from(ctx.getImageData(0,0,1,1).data);
      }
      return {text:rgb(css.color),background:rgb(css.backgroundColor),
        shadows:(css.boxShadow.match(/color\\([^)]+\\)|rgba?\\([^)]+\\)/g)||[]).map(rgb)};
    }''')


def contrast(first, second):
    def luminance(rgb):
        channels = [c / 255 for c in rgb[:3]]
        channels = [c / 12.92 if c <= .04045 else ((c + .055) / 1.055) ** 2.4 for c in channels]
        return sum(c * weight for c, weight in zip(channels, [.2126, .7152, .0722]))
    a, b = sorted([luminance(first), luminance(second)])
    return (b + .05) / (a + .05)


def run(page, base, site):
    # Keep the approval-alert popup from claiming the badge fixture while we
    # inspect inbox styling; the inbox request and count renderer stay real.
    page.route('**/assets/js/task-notifications.js', lambda route: route.abort())
    login(page, base)
    actor = sql("SELECT id FROM users WHERE username='theme-test'")
    record = sql("INSERT INTO requests(reference,type,requested_by,payload,status,current_node) "
                 "VALUES('BADGE-TEST','softcopy_create'," + actor + ",'{}','submitted','badge'); SELECT LAST_INSERT_ID()")
    sql("INSERT INTO workflow_steps(request_id,node_key,label,assignment,candidates,assigned_user_id,status) "
        "VALUES(" + record + ",'badge','Badge test','{}','[]'," + actor + ",'active')")
    for theme in ['light', 'dark']:
        page.evaluate('(theme) => localStorage.setItem("pk-dts-theme",theme)', theme)
        page.goto(base + 'documents/softcopy')
        badge = page.locator('#notificationBellCount')
        expect(badge).to_be_visible()
        expect(badge).to_have_text('1')
        for candidate in [badge] + page.locator('.nav-count').all():
            palette = colors(candidate)
            assert contrast(palette['text'], palette['background']) >= 4.5, (theme, palette)
        page.locator('.table-toolbar .js-edit').click()
        modal = page.locator('#editModal')
        expect(modal).to_be_visible()
        if theme == 'dark':
            for candidate in [modal.locator('.modal-content'), page.locator('.workspace-card').first]:
                assert all(max(shadow[:3]) < 100 for shadow in colors(candidate)['shadows']), colors(candidate)
            if page.locator('body').evaluate('(e) => e.classList.contains("offline-ui")'):
                assert max(colors(modal)['background'][:3]) < 40, 'Offline overlay must be dark'
            else:
                assert max(colors(page.locator('.modal-backdrop'))['background'][:3]) < 40
        modal.get_by_role('button', name='Cancel', exact=True).click()
    print('PASS: notification/sidebar badge contrast in both modes; dark shadows and modal overlays')


if __name__ == '__main__':
    main(checks=run)
