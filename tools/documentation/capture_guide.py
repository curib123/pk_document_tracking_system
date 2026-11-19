"""Capture the Step-by-Step User Guide screens from the current application.

Run: python -X utf8 tools/documentation/capture_guide.py
Output: tools/documentation/screenshots/guide/<shot>.png and guide/manifest.json

Uses the disposable demo site from capture_screenshots.py (synthetic people and records only),
drives real flows (submit, approve, return, upload) and records, for every shot, the boxes of its
named callouts and the visible text of the captured region. build_user_guide.py places the
numbered markers from those boxes and checks every bold UI label in the guide against that text.
"""
from datetime import date, timedelta
import faulthandler
import json
from pathlib import Path
import subprocess
import sys
import time

from playwright.sync_api import expect

sys.path.insert(0, str(Path(__file__).resolve().parent))
import capture_screenshots as cs  # noqa: E402

OUT = Path(__file__).resolve().parent / 'screenshots' / 'guide'
VIEWPORT = {'width': 1440, 'height': 900}
_pdf = cs.pymupdf.open()
_pdf.new_page().insert_text((60, 80), 'Sample Visitor Log', fontsize=22)
SAMPLE_PDF = {'name': 'Sample Visitor Log.pdf', 'mimeType': 'application/pdf', 'buffer': _pdf.tobytes()}
EDIT_MODAL = '#editModal .modal-content'
VIEW_MODAL = '#viewModal .modal-content'
ACTION_MODAL = '#actionModal .modal-content'
CONFIRM_MODAL = '#confirmModal .modal-content'
ALERT_MODAL = '#appAlertModal .modal-content'
REQUEST_FORM = '#editModal form:not([hidden])'
manifest, failures = {}, []


def normalize(text):
    return ' '.join((text or '').split())


class Screen:
    """One signed-in browser page plus the shot helper."""
    FIELD = ':is(.col-md-6,.col-md-4,.col-md-3,.col-lg-6,.col-sm-6,.col-12,.mb-3)'

    def __init__(self, browser, base, user, viewport=VIEWPORT):
        self.base = base
        self.context = browser.new_context(viewport=viewport, device_scale_factor=1,
                                           service_workers='block', reduced_motion='reduce')
        self.context.set_default_timeout(30000)
        self.page = self.context.new_page()
        self.load(base + 'login')
        self.page.wait_for_load_state('networkidle')
        if user:
            self.page.locator('[name="login"]').fill('demo-' + user)
            self.page.locator('[name="password"]').fill(cs.PASSWORD)
            # Enter submits without waiting for the animated Sign In button to settle.
            self.page.locator('[name="password"]').press('Enter')
            self.page.wait_for_url(lambda url: '/login' not in url, timeout=20000)
            self.page.wait_for_load_state('networkidle')

    def load(self, url):
        """Navigate, retrying once: the single-threaded PHP test server can be briefly slow."""
        for attempt_number in (1, 2):
            try:
                return self.page.goto(url)
            except Exception:
                if attempt_number == 2:
                    raise
                self.page.wait_for_timeout(2000)

    def go(self, route):
        response = self.load(self.base + route)
        assert response and response.status == 200, (route, response.status if response else None)
        self.settle()

    def settle(self):
        """Close notification pop-ups. They can stack, so hide them through Bootstrap, not by clicking."""
        page = self.page
        page.wait_for_load_state('networkidle')
        page.wait_for_timeout(300)
        for selector in ['#retentionNotificationModal', '#taskNotificationModal', '#appAlertModal']:
            modal = page.locator(selector)
            if modal.count() and modal.is_visible():
                page.evaluate('(sel) => bootstrap.Modal.getOrCreateInstance(document.querySelector(sel)).hide()', selector)
                expect(modal).to_be_hidden()
                page.wait_for_timeout(300)
        assert not page.get_by_text('A Database Error Occurred', exact=True).is_visible()

    def click(self, spec):
        locator = self.find(self.page, spec)
        for index in range(locator.count()):
            if locator.nth(index).is_visible():
                locator.nth(index).click()
                self.page.wait_for_timeout(400)
                return
        raise AssertionError('Nothing visible to click: ' + spec)

    def find(self, scope, spec):
        """Locator from a short spec. Field kinds box the whole column: label plus control."""
        kind, _, value = spec.partition(':')
        if kind == 'dt':
            return self.page.locator(f'[data-title="{value}"]')
        if kind == 'field':
            return self.page.locator(f'{self.FIELD}:has({value})')
        if kind == 'fieldlabel':
            return self.page.locator(f'{self.FIELD}:has(> label:has-text("{value}"))')
        if kind == 'label':
            return scope.get_by_label(value, exact=True)
        if kind in ('button', 'link', 'tab', 'checkbox', 'combobox', 'textbox', 'row'):
            return scope.get_by_role(kind, name=value, exact=True)
        if kind == 'text':
            return scope.get_by_text(value, exact=True)
        return self.page.locator(spec)

    def shot(self, sid, region=None, marks=None, scroll=None):
        """Screenshot the viewport (region None) or an element, and record named callout boxes."""
        page = self.page
        target = page.locator(region).first if region else None
        if target is not None:
            expect(target).to_be_visible()
        if scroll:
            page.locator(scroll).first.evaluate('(el) => el.scrollIntoView({block: "center"})')
        page.wait_for_timeout(350)
        bounds = target.bounding_box() if target is not None else dict(x=0, y=0, **page.viewport_size)
        scope = target if target is not None else page
        boxes, missing = {}, []
        for name, spec in (marks or {}).items():
            matches, box = self.find(scope, spec), None
            try:
                matches.first.wait_for(state='attached', timeout=4000)
                count = min(matches.count(), 40)
                # Field containers nest; the innermost (last in document order) is the field itself.
                order = range(count - 1, -1, -1) if spec.startswith(('field:', 'fieldlabel:')) else range(count)
                for index in order:
                    if matches.nth(index).is_visible():
                        box = matches.nth(index).bounding_box()
                        break
            except Exception:  # Recorded below; the screenshot is still useful for fixing.
                box = None
            if not box:
                missing.append(f'{name} ({spec})')
                continue
            x0, y0 = max(box['x'], bounds['x']), max(box['y'], bounds['y'])
            x1 = min(box['x'] + box['width'], bounds['x'] + bounds['width'])
            y1 = min(box['y'] + box['height'], bounds['y'] + bounds['height'])
            if x1 - x0 < 4 or y1 - y0 < 4:
                missing.append(f'{name} ({spec}) outside the shot')
                continue
            boxes[name] = dict(x=round(x0 - bounds['x'], 1), y=round(y0 - bounds['y'], 1),
                               width=round(x1 - x0, 1), height=round(y1 - y0, 1))
        path = OUT / (sid + '.png')
        (target if target is not None else page).screenshot(path=str(path), animations='disabled')
        image = cs.pymupdf.Pixmap(str(path))
        text = normalize((target if target is not None else page.locator('body')).inner_text())
        # Field values are not innerText; keep them so a chosen action or typed value can be checked.
        values = page.evaluate('''(sel) => {
            const root = sel ? document.querySelector(sel) : document.body;
            return [...root.querySelectorAll('input, textarea, select')].filter(e => e.offsetParent)
              .map(e => e.tagName === 'SELECT' ? (e.selectedOptions[0]?.text || '') : (e.value || e.placeholder || ''));
        }''', region)
        manifest[sid] = dict(file=path.name, width=image.width, height=image.height, marks=boxes,
                             text=text + ' ' + normalize(' '.join(values)))
        if missing:
            failures.append(f'{sid}: missing {missing}')
        print(('PARTIAL ' if missing else 'Captured ') + sid, flush=True)

    def close(self):
        """Contexts stay open until the browser closes: closing one mid-run once stalled the driver."""


def attempt(sid, action):
    print(time.strftime('%H:%M:%S'), 'start', sid, flush=True)
    # Restart the stall watchdog: it only reports when one step takes over three minutes.
    faulthandler.dump_traceback_later(180, repeat=True, file=sys.stderr)
    try:
        action()
    except Exception as error:  # Keep capturing; report everything at the end.
        failures.append(f'{sid}: {type(error).__name__}: {str(error).splitlines()[0][:220]}')
        print('FAILED ' + sid, flush=True)


def extra_seed():
    hashed = subprocess.run([cs.PHP, '-r', "echo password_hash(getenv('PK_CAPTURE_PASSWORD'), PASSWORD_DEFAULT);"],
                            env=cs.ENV, capture_output=True, check=True).stdout.decode()
    cs.insert('users', username='demo-newstaff', first_name='Riley', last_name='Newstaff',
              position_title='Documentation Example', role_id=5, password_hash=hashed, require_password_change=1)
    # The final controlled file is uploaded at the Document Control Officer step, as in production.
    cs.sql("UPDATE workflow_versions v JOIN workflows w ON w.id=v.workflow_id "
           "SET v.graph=JSON_SET(v.graph,'$.finalize_step_key','step_3') "
           "WHERE w.request_type IN ('softcopy_create','softcopy_revise')")
    cs.sql("UPDATE requests SET snapshot=JSON_SET(snapshot,'$.finalize_step_key','step_3') "
           "WHERE type IN ('softcopy_create','softcopy_revise') AND snapshot IS NOT NULL")
    # One hardcopy past its retention end, so the reminder and filters have something to show.
    overdue = (date.today() - timedelta(days=3)).isoformat()
    cs.sql(f"UPDATE hardcopy_documents SET retention_end_date='{overdue}' WHERE id=1")
    # A disposed hardcopy for the permanent delete procedure.
    cs.insert('hardcopy_documents', title='Sample Obsolete Record', area_id=1, specific_id=1, location_id=1,
              holder_id=1, created_by=1, creation_source='direct', creation_reason='Synthetic documentation example',
              status='disposed', previous_status='active')


def capture(browser, base):
    OUT.mkdir(parents=True, exist_ok=True)
    for old in OUT.glob('*.png'):
        old.unlink()

    # ---------------------------------------------------------------- sign in
    anonymous = Screen(browser, base, None)
    attempt('signin', lambda: anonymous.shot('signin', marks={
        'username': '[name="login"]', 'password': '[name="password"]', 'signin': 'button[type="submit"]'}))
    anonymous.close()
    newcomer = Screen(browser, base, 'newstaff')
    attempt('first-password', lambda: newcomer.shot('first-password', '#requiredPasswordModal .modal-content', marks={
        'current': '#requiredPasswordModal input[type="password"] >> nth=0',
        'new': '#requiredPasswordModal input[type="password"] >> nth=1',
        'confirm': '#requiredPasswordModal input[type="password"] >> nth=2',
        'save': 'button:Set permanent password'}))
    newcomer.close()

    admin = Screen(browser, base, 'admin')

    def retention_popup():
        # The reminder opens on top of any approval alert; its own region is captured either way.
        expect(admin.page.locator('#retentionNotificationModal')).to_be_visible(timeout=20000)
        admin.shot('retention-popup', '#retentionNotificationModal .modal-content', marks={
            'list': '#retentionNotificationList, #retentionNotificationModal ol, #retentionNotificationModal ul',
            'sound': 'button:Enable reminder sound', 'done': '#retentionNotificationModal button.btn-primary'})
    attempt('retention-popup', retention_popup)
    admin.settle()

    # ---------------------------------------------------------------- overview and navigation
    admin.go('dashboard')
    attempt('overview', lambda: admin.shot('overview', marks={
        'hero': '.dashboard-welcome', 'cards': 'div.row:has(> .col-6 > .dashboard-stat-card)',
        'register': 'section[aria-labelledby="documentChartTitle"]',
        'status': 'section[aria-labelledby="requestChartTitle"]'}))
    attempt('menu', lambda: admin.shot('menu', marks={
        'documents': 'text:Document Management', 'requests': 'text:Document Requests',
        'control': 'text:Document Control', 'admin': 'text:System Administration'}))
    attempt('topbar', lambda: admin.shot('topbar', marks={
        'theme': '[data-theme-toggle], #themeToggle, .theme-toggle', 'bell': '#notificationBellButton',
        'account': '.user-menu, .topbar-user'}))

    def account_menu():
        admin.go('dashboard')
        admin.page.locator('.user-menu, .topbar-user').first.click()
        admin.shot('account-menu', marks={'password': 'text:Change Password', 'signout': 'text:Sign Out'})
        admin.page.get_by_text('Change Password', exact=True).first.click()
        admin.shot('password-modal', '#passwordModal .modal-content', marks={
            'current': '#passwordModal input[type="password"] >> nth=0',
            'new': '#passwordModal input[type="password"] >> nth=1',
            'confirm': '#passwordModal input[type="password"] >> nth=2', 'save': 'button:Update Password'})
        admin.page.locator('#passwordModal .btn-close').click()
    attempt('account-menu', account_menu)

    def bell():
        admin.go('dashboard')
        admin.page.locator('#notificationBellButton').click()
        admin.shot('bell', marks={'bell': '#notificationBellButton', 'panel': 'text:Notifications'})
    attempt('bell', bell)

    def mobile():
        phone = Screen(browser, base, 'staff', viewport={'width': 430, 'height': 900})
        phone.go('dashboard')
        phone.shot('mobile', marks={'toggle': '#sidebarToggle'})
        phone.page.locator('#sidebarToggle').click()
        phone.shot('mobile-menu', marks={'menu': '#appSidebar'})
        phone.close()
    attempt('mobile', mobile)

    # ---------------------------------------------------------------- registers and details
    admin.go('documents/hardcopy?layout=table&folder=all')
    attempt('list-tools', lambda: admin.shot('list-tools', marks={
        'search': '.table-search input, input[type="search"]', 'filters': 'select[name="status"]',
        'clear': 'text:Clear', 'layout': 'text:Folder', 'pages': 'text:Rows per page'}))
    attempt('hc-register', lambda: admin.shot('hc-register', marks={
        'add': 'dt:Add New Hardcopy Document', 'view': 'button:View', 'edit': 'button:Edit', 'dispose': 'button:Dispose'}))
    admin.go('documents/hardcopy?layout=table&folder=all&retention=overdue')
    attempt('hc-retention', lambda: admin.shot('hc-retention', marks={
        'filter': 'select[name="retention"]', 'row': 'text:Sample Inspection Record'}))
    admin.go('documents/softcopy?layout=table&folder=all')
    attempt('sc-register', lambda: admin.shot('sc-register', marks={
        'nav': '#appSidebar >> text=Softcopy Documents', 'search': '.table-search input, input[type="search"]',
        'add': 'dt:Add New Softcopy Document', 'view': 'button:View'}))
    attempt('sc-row-actions', lambda: admin.shot('sc-row-actions', marks={
        'view': 'button:View', 'edit': 'button:Edit', 'signatories': 'button:Signatories',
        'template': 'link:Request Template for Revision', 'dispose': 'button:Dispose'}))
    admin.go('documents/softcopy?layout=folder')
    attempt('sc-folder', lambda: admin.shot('sc-folder', marks={
        'layout': 'text:Folder', 'expand': 'text:Expand all', 'folder': 'text:Quality Procedures'}))

    def view_details():
        page = admin.page
        admin.go('documents/softcopy?layout=table&folder=all')
        page.get_by_role('button', name='View', exact=True).first.click()
        page.wait_for_timeout(800)
        admin.shot('view-details', VIEW_MODAL, marks={
            'header': '#viewModal .modal-header', 'tabs': 'tab:Related Requests', 'close': 'button:Close'})
        page.get_by_role('tab', name='Document Files', exact=True).click()
        page.wait_for_timeout(2000)
        admin.shot('view-files', VIEW_MODAL, marks={
            'files': 'tab:Document Files', 'fullscreen': 'text:Fullscreen', 'download': 'text:Download'})
        page.get_by_role('tab', name='Approval History', exact=True).click()
        page.wait_for_timeout(500)
        admin.shot('view-history', VIEW_MODAL, marks={'history': 'tab:Approval History'})
    attempt('view-details', view_details)

    def fullscreen():
        page = admin.page
        admin.go('documents/softcopy?layout=table&folder=all')
        page.get_by_role('button', name='View', exact=True).first.click()
        page.get_by_role('tab', name='Document Files', exact=True).click()
        page.wait_for_timeout(1500)
        admin.click('text:Fullscreen')
        page.wait_for_timeout(1800)
        admin.shot('fullscreen', marks={'zoom': '[aria-label*="Zoom in" i], [data-zoom="in"], [data-zoom-in]',
                                        'close': '[aria-label*="Exit" i], [aria-label*="Close full" i]'})
    attempt('fullscreen', fullscreen)

    # ---------------------------------------------------------------- hardcopy
    def hardcopy_form():
        page = admin.page
        admin.go('documents/hardcopy?layout=table&folder=all')
        admin.click('dt:Add New Hardcopy Document')
        expect(page.locator('#editModal')).to_be_visible()
        admin.shot('hc-form-place', EDIT_MODAL, marks={
            'title': 'fieldlabel:Document Title', 'area': 'fieldlabel:Area', 'specific': 'fieldlabel:Specific',
            'location': 'fieldlabel:Location'})
        admin.shot('hc-form-holder', EDIT_MODAL, marks={
            'holder': 'fieldlabel:Current Holder', 'sequence': 'fieldlabel:Sequence', 'notes': 'fieldlabel:Reason / Notes'},
            scroll='#editModal [name="holder_id"]')
        page.locator('[name="retention_enabled"]').check()
        admin.shot('hc-form-retention', EDIT_MODAL, marks={
            'track': 'field:[name="retention_enabled"]', 'start': 'field:[name="retention_start_date"]',
            'end': 'field:[name="retention_end_date"]', 'save': 'button:Save Document'},
            scroll='#editModal [name="retention_end_date"]')
    attempt('hc-form', hardcopy_form)

    def hardcopy_edit():
        admin.go('documents/hardcopy?layout=table&folder=all')
        admin.page.get_by_role('button', name='Edit', exact=True).first.click()
        admin.shot('hc-edit', EDIT_MODAL, marks={'title': 'fieldlabel:Document Title', 'location': 'fieldlabel:Location',
                                                 'save': 'button:Save Document'})
    attempt('hc-edit', hardcopy_edit)

    def hardcopy_view():
        admin.go('documents/hardcopy?layout=table&folder=all')
        admin.page.get_by_role('button', name='View', exact=True).first.click()
        admin.page.wait_for_timeout(600)
        admin.shot('hc-view', VIEW_MODAL, marks={'holder': 'text:Current Holder', 'history': 'tab:Transfer History'})
    attempt('hc-view', hardcopy_view)

    # ---------------------------------------------------------------- softcopy direct
    def softcopy_create():
        page = admin.page
        admin.go('documents/softcopy?layout=table&folder=all')
        admin.click('dt:Add New Softcopy Document')
        expect(page.locator('#editModal')).to_be_visible()
        page.locator('#reqSubject').fill('New visitor log for the front desk')
        page.locator('#reqTitle').fill('Visitor Log Template')
        page.locator('#reqCategory').select_option('1', force=True)
        admin.shot('sc-add-top', EDIT_MODAL, marks={
            'action': 'field:#reqType', 'subject': 'field:#reqSubject', 'title': 'field:#reqTitle',
            'category': 'field:#reqCategory', 'number': 'field:#generatedDocumentNumber'})
        page.locator('#revisionFile').set_input_files(SAMPLE_PDF)
        page.wait_for_timeout(800)
        admin.shot('sc-add-bottom', EDIT_MODAL, marks={
            'revision': 'field:#directRevisionLevel', 'effective': 'field:#revisionEffective', 'file': 'field:#revisionFile',
            'signatory': 'button:Add Signatory', 'approve': 'button:Approve Directly'},
            scroll='#editModal [data-signatory-add]')
        page.locator('#editModal [data-signatory-add]').click()
        page.wait_for_timeout(400)
        row = page.locator('#editModal [data-signatory-row]').first
        row.locator('[name="signatory_title[]"]').fill('Prepared by')
        row.locator('[name="signatory_full_name[]"]').fill('Casey Controller')
        row.locator('[name="signatory_position[]"]').fill('Document Control Officer')
        page.locator('#editModal .modal-title').click()
        admin.shot('sc-signatory-row', EDIT_MODAL, marks={
            'title': 'field:[name="signatory_title[]"]', 'name': 'field:[name="signatory_full_name[]"]',
            'position': 'field:[name="signatory_position[]"]', 'remove': '#editModal [data-signatory-remove]'},
            scroll='#editModal [data-signatory-row]')
        page.get_by_role('button', name='Approve Directly', exact=True).click()
        admin.shot('confirm', CONFIRM_MODAL, marks={'message': '#confirmMessage', 'confirm': '#confirmProceed'})
        page.locator('#confirmProceed').click()
        page.wait_for_load_state('networkidle')
        admin.shot('result', ALERT_MODAL, marks={'message': '#appAlertMessage', 'done': 'button:Got it'})
        admin.settle()
    attempt('sc-create', softcopy_create)

    def softcopy_series():
        page = admin.page
        admin.go('documents/softcopy?layout=table&folder=all')
        admin.click('dt:Add New Softcopy Document')
        page.locator('#reqCategory').select_option('1', force=True)
        page.locator('[name="series_number_checker"]').check()
        page.locator('#reqNumber').select_option('QP-001', force=True)
        page.locator('#reqSeries').fill('SER-002')
        admin.shot('sc-series', EDIT_MODAL, marks={
            'checker': 'field:[name="series_number_checker"]', 'number': 'field:#reqNumber', 'series': 'field:#reqSeries'})
    attempt('sc-series', softcopy_series)

    def softcopy_revise():
        page = admin.page
        admin.go('documents/softcopy?layout=table&folder=all&q=Sample%20Quality')
        page.get_by_role('button', name='Edit', exact=True).first.click()
        expect(page.locator('#editModal')).to_be_visible()
        admin.shot('sc-revise', EDIT_MODAL, marks={
            'document': 'field:#reqSoftcopy', 'revision': 'field:#directRevisionLevel', 'title': 'field:#reqTitle',
            'pages': 'field:#revisionPages', 'file': 'field:#revisionFile'}, scroll='#editModal #revisionPages')
        page.locator('#reqType').select_option('softcopy_correct', force=True)
        page.wait_for_timeout(400)
        admin.shot('sc-correct', EDIT_MODAL, marks={
            'action': 'field:#reqType', 'title': 'field:#reqTitle', 'series': 'field:#directSeriesNumber',
            'revision': 'field:#directRevisionLevel', 'pages': 'field:#revisionPages'})
    attempt('sc-revise', softcopy_revise)

    def signatories():
        admin.go('documents/softcopy?layout=table&folder=all')
        admin.page.get_by_role('button', name='Signatories', exact=True).first.click()
        admin.page.wait_for_timeout(500)
        admin.shot('sc-signatories', '.modal.show .modal-content', marks={
            'add': 'button:Add Signatory', 'save': '.modal.show button[type="submit"]'})
    attempt('sc-signatories', signatories)

    def template_request():
        page = admin.page
        admin.go('documents/softcopy?layout=table&folder=all')
        page.get_by_role('link', name='Request Template for Revision', exact=True).first.click()
        expect(page.locator('#editModal')).to_be_visible()
        admin.shot('sc-template-request', EDIT_MODAL, marks={
            'subject': f'field:{REQUEST_FORM} #reqSubject', 'draft': '#requestFormSaveDraft', 'submit': '#requestFormSubmit'})
        admin.go('my-requests?request_category=softcopy&type=softcopy_revision_template')
        admin.shot('sc-template-download', marks={'download': 'text:Download Template', 'status': 'text:Approved'})
    attempt('sc-template', template_request)

    # ---------------------------------------------------------------- external and scanned
    def external():
        page = admin.page
        admin.go('documents/external?layout=table&folder=all')
        admin.shot('ext-register', marks={'add': 'dt:Add New External Document', 'download': 'text:Download All',
                                          'edit': 'button:Edit'})
        admin.click('dt:Add New External Document')
        expect(page.locator('#editModal')).to_be_visible()
        admin.shot('ext-add', EDIT_MODAL, marks={
            'title': 'fieldlabel:Document Title', 'number': 'fieldlabel:Document No.', 'subsidiary': 'fieldlabel:Subsidiary',
            'category': 'fieldlabel:Category', 'files': 'fieldlabel:Attach File', 'save': 'button:Save Directly'})
        admin.go('documents/external?layout=table&folder=all')
        page.get_by_role('button', name='Edit', exact=True).first.click()
        expect(page.locator('#editModal')).to_be_visible()
        admin.shot('ext-edit', EDIT_MODAL, marks={'title': 'fieldlabel:Document Title', 'files': 'fieldlabel:Attach File',
                                                  'save': 'button:Save Directly'})
    attempt('external', external)

    def scanned():
        admin.go('documents/scanned')
        admin.shot('scan-register', marks={
            'upload': 'text:Upload / Request Category', 'mine': 'text:My Scanned Requests',
            'sound': 'text:Enable reminder sound', 'layout': 'text:Folder'})
    attempt('scan-register', scanned)

    # ---------------------------------------------------------------- document control and administration
    def assignments():
        admin.go('admin/document-assignments')
        admin.shot('assign-page', marks={
            'tabs': 'text:Softcopy / Categories', 'single': 'dt:Assign Document', 'all': 'text:Assign All'})
        admin.click('dt:Assign Document')
        admin.shot('assign-modal', '#assignmentModal .modal-content', marks={
            'document': 'fieldlabel:Hardcopy Document', 'user': 'fieldlabel:Assigned User', 'save': 'text:Save Assignment'})
    attempt('assignments', assignments)

    def master_data():
        admin.go('places/location')
        admin.shot('places-page', marks={'tabs': 'text:Specific', 'add': 'dt:Add Locations'})
        admin.click('dt:Add Locations')
        admin.shot('places-modal', EDIT_MODAL, marks={
            'name': 'fieldlabel:Location Name', 'area': 'fieldlabel:Area', 'specific': 'fieldlabel:Specific',
            'save': 'text:Save Record'})
        admin.go('places/softcopy-categories')
        admin.click('dt:Add Softcopy Categories')
        admin.shot('category-modal', EDIT_MODAL, marks={
            'type': 'field:[name="category_type"]', 'pattern': 'field:[name="code_pattern"]',
            'parent': 'field:[name="parent_id"]', 'save': '#editModal button[type="submit"]'})
        admin.go('places/external-categories')
        admin.shot('external-categories', marks={'tabs': 'text:External Categories'})
    attempt('master-data', master_data)

    def administration():
        page = admin.page
        admin.go('admin/users')
        admin.shot('users-page', marks={'add': 'dt:Add User', 'assigned': 'text:Assigned Documents',
                                        'deactivate': 'text:Deactivate'})
        admin.click('dt:Add User')
        admin.shot('user-modal', EDIT_MODAL, marks={
            'username': 'field:[name="username"]', 'role': 'field:[name="role_id"], [name="role_ids[]"]',
            'leader': 'field:[name="leader_id"]', 'save': '#editModal button[type="submit"]'})
        admin.go('admin/roles')
        admin.shot('roles-page', marks={'add': 'dt:Add Role', 'edit': 'button:Edit'})
        page.get_by_role('button', name='Edit', exact=True).first.click()
        admin.shot('role-modal', EDIT_MODAL, marks={'name': '#editModal input[type="text"] >> nth=0',
                                                    'permissions': '#editModal input[name="permissions[]"] >> nth=0',
                                                    'save': '#editModal button[type="submit"]'})
        admin.go('admin/workflows')
        admin.shot('workflows-page', marks={'steps': 'text:Step Actions', 'view': 'button:View'})
        page.locator('[data-bs-target^="#workflowStepsModal-"]').first.click()
        page.wait_for_timeout(500)
        admin.shot('workflow-modal', '.modal.show .modal-content', marks={})
        admin.go('admin/audit')
        admin.shot('audit-page', marks={'search': '.table-search input, input[type="search"]',
                                        'view': 'button:View'})
        admin.go('admin/backups')
        admin.shot('backups-page', marks={'now': 'text:Backup now', 'import': 'text:Import backup',
                                          'cleanup': 'text:Clean up', 'restore': 'text:Restore system data'})
        page.locator('[data-bs-target="#backupRestoreModal"]').click()
        admin.shot('restore-modal', '#backupRestoreModal .modal-content', marks={})
    attempt('administration', administration)

    # ---------------------------------------------------------------- requests (Sam)
    staff = Screen(browser, base, 'staff')

    def open_request(action):
        page = staff.page
        staff.go('my-requests')
        page.get_by_role('button', name='New Request').first.click()
        expect(page.locator('#requestActionPicker')).to_be_visible()
        if action:
            page.locator('#requestActionPicker').select_option(action)
            page.wait_for_timeout(500)

    def requests():
        staff.go('my-requests')
        staff.shot('req-register', marks={
            'new': 'text:New Request', 'category': 'select[name="request_category"]',
            'status': 'select[name="status"]', 'view': 'button:View'})
        open_request(None)
        staff.shot('req-picker', EDIT_MODAL, marks={
            'action': '#requestActionPicker', 'draft': '#requestFormSaveDraft', 'submit': '#requestFormSubmit'})
        forms = {
            'req-softcopy-create': ('softcopy_create', ['#reqSubject', '#reqTitle', '#reqCategory', '#revisionFile']),
            'req-softcopy-revise': ('softcopy_revise', ['#reqSoftcopy', '#reqTitle', '#revisionPages', '#revisionFile']),
            'req-external-create': ('external_create', []), 'req-external-revise': ('external_revise', []),
            'req-transfer': ('transfer', []), 'req-access': ('access', []), 'req-assignment': ('assignment', []),
            'req-disposal': ('disposal', []), 'req-scanned': ('scanned_create', [])}
        for sid, (action, fields) in forms.items():
            def one(sid=sid, action=action, fields=fields):
                open_request(action)
                marks = {'action': '#requestActionPicker'}
                marks.update({f'f{i}': f'field:{REQUEST_FORM} {sel}' for i, sel in enumerate(fields, 1)})
                marks['submit'] = '#requestFormSubmit'
                staff.shot(sid, EDIT_MODAL, marks=marks)
            attempt(sid, one)
        # Submit a real softcopy request; Alex returns it later.
        page = staff.page
        open_request('softcopy_create')
        page.locator(f'{REQUEST_FORM} #reqSubject').fill('Add a visitor log for the warehouse')
        page.locator(f'{REQUEST_FORM} #reqTitle').fill('Warehouse Visitor Log')
        page.locator(f'{REQUEST_FORM} #reqCategory').select_option('1', force=True)
        page.locator(f'{REQUEST_FORM} #revisionFile').set_input_files(SAMPLE_PDF)
        page.locator('#requestFormSubmit').click()
        staff.shot('req-confirm', CONFIRM_MODAL, marks={'message': '#confirmMessage', 'confirm': '#confirmProceed'})
        page.locator('#confirmProceed').click()
        page.wait_for_load_state('networkidle')
        staff.shot('req-result', ALERT_MODAL, marks={'message': '#appAlertMessage'})
        staff.settle()
        staff.go('my-requests')
        page.get_by_role('row').filter(has_text='Warehouse Visitor Log').get_by_role('button', name='View', exact=True).click()
        page.wait_for_timeout(700)
        page.get_by_role('tab', name='Approval Process Timeline', exact=True).click()
        staff.shot('req-timeline', VIEW_MODAL, marks={'tab': 'tab:Approval Process Timeline'})
        page.get_by_role('tab', name='Workflow Activity Log', exact=True).click()
        staff.shot('req-log', VIEW_MODAL, marks={'tab': 'tab:Workflow Activity Log'})
    attempt('requests', requests)

    # ---------------------------------------------------------------- decisions (Alex, Morgan, Casey)
    def decisions():
        page = admin.page
        admin.go('my-tasks')
        admin.shot('task-register', marks={'review': 'button:Review request', 'dispatch': 'button:Dispatch',
                                           'accept': 'button:Accept'})
        page.get_by_role('row').filter(has_text='DEMO-2026-002').get_by_role('button', name='Review request', exact=True).click()
        expect(page.locator('#viewModal')).to_be_visible()
        page.wait_for_timeout(1200)
        admin.shot('task-review', VIEW_MODAL, marks={'file': 'tab:Document File', 'timeline': 'tab:Approval Process Timeline',
                                                     'approve': 'button:Approve', 'reject': 'button:Reject', 'return': 'button:Return'})
        # Approve goes through the shared confirmation; Return and Reject ask for a remark instead.
        page.get_by_role('button', name='Approve', exact=True).click()
        admin.shot('task-confirm', CONFIRM_MODAL, marks={'message': '#confirmMessage', 'confirm': '#confirmProceed'})
        page.locator('#confirmProceed').click()
        page.wait_for_load_state('networkidle')
        admin.shot('task-result', ALERT_MODAL, marks={'message': '#appAlertMessage'})
        admin.settle()
        admin.go('my-tasks')
        page.get_by_role('row').filter(has_text='Warehouse Visitor Log').get_by_role('button', name='Review request', exact=True).click()
        page.get_by_role('button', name='Return', exact=True).click()
        expect(page.locator('#actionModal')).to_be_visible()
        page.locator('#actionRemark').fill('Please attach the signed version.')
        admin.shot('task-return', ACTION_MODAL, marks={'remark': 'field:#actionRemark', 'continue': '#actionContinue'})
        page.locator('#actionContinue').click()
        page.wait_for_load_state('networkidle')
        admin.settle()
    attempt('decisions', decisions)

    def handoffs():
        for sid, button in [('handoff-dispatch', 'Dispatch'), ('handoff-accept', 'Accept')]:
            admin.go('my-tasks')
            admin.page.get_by_role('button', name=button, exact=True).first.click()
            admin.page.wait_for_timeout(500)
            admin.shot(sid, '.modal.show .modal-content', marks={'message': '.modal.show .modal-body',
                                                                 'confirm': '#confirmProceed'})
    attempt('handoffs', handoffs)

    def manager_and_controller():
        manager = Screen(browser, base, 'manager')
        manager.go('my-tasks')
        page = manager.page
        page.get_by_role('row').filter(has_text='DEMO-2026-002').get_by_role('button', name='Review request', exact=True).click()
        page.get_by_role('button', name='Approve', exact=True).click()
        page.locator('#confirmProceed').click()
        page.wait_for_load_state('networkidle')
        manager.close()
        controller = Screen(browser, base, 'controller')
        controller.page.wait_for_timeout(1500)
        attempt('task-popup', lambda: controller.shot('task-popup', '#taskNotificationModal .modal-content', marks={
            'list': '#taskNotificationList', 'done': '#taskNotificationModal .btn-primary'}))
        controller.settle()
        controller.go('my-tasks')
        page = controller.page
        page.get_by_role('row').filter(has_text='DEMO-2026-002').get_by_role('button', name='Review request', exact=True).click()
        page.get_by_role('button', name='Approve', exact=True).click()
        expect(page.locator('#actionFinalFile')).to_be_visible()
        controller.shot('task-final-file', ACTION_MODAL, marks={
            'download': '#actionFinalDownload', 'file': 'field:#actionFinalFile', 'continue': '#actionContinue'})
        controller.go('my-tasks?request_category=softcopy&type=softcopy_revision_template')
        page.get_by_role('button', name='Review request', exact=True).first.click()
        page.get_by_role('button', name='Approve', exact=True).click()
        controller.shot('task-template', ACTION_MODAL, marks={
            'file': 'field:#actionFinalFile', 'expiry': 'field:#actionTemplateExpiry', 'continue': '#actionContinue'})
        controller.close()
    attempt('controller', manager_and_controller)

    def returned():
        page = staff.page
        staff.go('my-requests')
        staff.shot('req-returned', marks={'status': 'text:Returned', 'edit': 'button:Edit'})
        page.get_by_role('row').filter(has_text='Warehouse Visitor Log').get_by_role('button', name='Edit', exact=True).click()
        expect(page.locator('#editModal')).to_be_visible()
        staff.shot('req-returned-edit', EDIT_MODAL, marks={'file': f'field:{REQUEST_FORM} #revisionFile',
                                                          'submit': '#requestFormSubmit'})
    attempt('returned', returned)

    def drafts():
        admin.go('my-requests?status=draft')
        admin.shot('req-draft', marks={'edit': 'button:Edit', 'submit': 'button:Submit', 'cancel': 'button:Cancel'})
    attempt('drafts', drafts)

    def disposal():
        page = admin.page
        admin.go('documents/hardcopy?layout=table&folder=all')
        page.get_by_role('row').filter(has_text='Sample Dispatch Record').get_by_role('button', name='Dispose', exact=True).click()
        expect(page.locator('#actionModal')).to_be_visible()
        admin.shot('dispose-modal', ACTION_MODAL, marks={'reason': 'field:#actionModal [data-disposal-reason]',
                                                         'continue': '#actionContinue'})
        admin.go('documents/hardcopy?layout=table&folder=all&status=disposed')
        page.get_by_role('button', name='Hard Delete', exact=True).first.click()
        expect(page.locator('#actionModal')).to_be_visible()
        admin.shot('hard-delete', ACTION_MODAL, marks={
            'typed': '#actionConfirmPhrase', 'password': '#actionConfirmPassword', 'continue': '#actionContinue'})
    attempt('disposal', disposal)
    # Standard role permissions (production seed plus migrations) for the "Who does what" matrix.
    roles = {}
    for line in cs.sql("SELECT r.name, CONCAT(p.module_key,'.',p.action_key) FROM role_permissions rp "
                       "JOIN roles r ON r.id=rp.role_id JOIN permissions p ON p.id=rp.permission_id").splitlines():
        name, key = line.split('\t')
        roles.setdefault(name, []).append(key)
    manifest['_roles'] = {name: sorted(keys) for name, keys in roles.items()}
    # Written before shutdown so a slow browser exit cannot lose the captured shots.
    (OUT / 'manifest.json').write_text(json.dumps(manifest, indent=1, sort_keys=True) + '\n', encoding='utf-8')


def main():
    # If a browser call ever stalls, print where every two minutes instead of hanging silently.
    faulthandler.dump_traceback_later(120, repeat=True, file=sys.stderr)
    with cs.demo_site() as (browser, base):
        extra_seed()
        capture(browser, base)
    faulthandler.cancel_dump_traceback_later()
    print(f'{len(manifest)} shots captured.')
    if failures:
        print('\n'.join(['', 'ISSUES:'] + failures))
        sys.exit(1)


if __name__ == '__main__':
    main()
