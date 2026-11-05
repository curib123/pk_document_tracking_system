"""Build the current illustrated PDF, Visio and PowerPoint documentation.

Content lives in the four adjacent JSON files. --verify checks existing exports;
--check-source checks the route, request and content inventory without exporting.
Requires the existing pymupdf and python-pptx packages. No application data is read.
Visio exports include VSDX and VDX copies for older desktop versions.
Guide screenshots and control positions come from capture_screenshots.py's
isolated demonstration site. Both guide exports must retain every screenshot.
"""
from pathlib import Path
import argparse
import base64
import hashlib
import io
import json
import math
import posixpath
import re
import zipfile
import xml.etree.ElementTree as ET

import pymupdf as pdf
from pptx import Presentation
from pptx.dml.color import RGBColor
from pptx.enum.shapes import MSO_SHAPE, MSO_CONNECTOR
from pptx.util import Inches, Pt
from visio_legacy import legacy_visio, VDX_NS

ROOT = Path(__file__).resolve().parents[2]
SOURCE = Path(__file__).resolve().parent
SCREENSHOTS = SOURCE / 'screenshots'
ICONS = SOURCE / 'icons'
DOCS = ROOT / 'docs/system-documentation'
W, H = 1190, 842
MARKER = 'CURRENT USER PROCEDURES | 05 NOVEMBER 2026'
NS = 'http://schemas.microsoft.com/office/visio/2012/main'
REL = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships'
PKG = 'http://schemas.openxmlformats.org/package/2006/relationships'
CT = 'http://schemas.openxmlformats.org/package/2006/content-types'
ET.register_namespace('', NS)
ET.register_namespace('r', REL)
INK = (35/255, 50/255, 71/255)
ACCENT = (146/255, 40/255, 74/255)
PALE = (246/255, 248/255, 251/255)
GOLD = (1, .973, .917)
GREY = (92/255, 112/255, 140/255)
RULE = (213/255, 222/255, 232/255)
WHITE = (1, 1, 1)

# Implementation identifiers stay here, outside the reader's documents. New
# endpoints require an explicit review and a link to a user-guide procedure.
GROUPS = [
 ('sign-in', ['login', 'logout', 'change-password']),
 ('navigation', ['dashboard']),
 ('notifications', ['notifications/tasks', 'notifications/inbox', 'notifications/tasks/claim', 'notifications/inbox/read', 'notifications/retention']),
 ('find-documents', ['documents/(:any)', 'files/download/(:num)', 'files/print/(:num)', 'files/print-content/(:num)', 'files/review/(:num)', 'files/review-content/(:num)/(:any)/(:any)', 'files/office-setup']),
 ('hardcopy-create', ['documents/(:any)/save']),
 ('disposal-direct', ['documents/(:any)/dispose', 'documents/external/dispose']),
 ('softcopy-direct-create', ['documents/softcopy/direct']),
 ('external-direct-create', ['documents/external', 'documents/external/direct']),
 ('request-lifecycle', ['my-requests/(:any)', 'my-requests/(:any)/save', 'my-requests/(:any)/submit', 'my-requests/(:any)/cancel', 'my-requests/(:any)/delete', 'files/upload']),
 ('approvals', ['my-tasks/(:any)', 'my-tasks/(:any)/decide']),
 ('transfer-dispatch', ['my-tasks/hardcopy-transfer/dispatch']),
 ('transfer-accept', ['my-tasks/hardcopy-transfer/accept']),
 ('direct-assignments', ['admin/document-assignments', 'admin/document-assignments/save', 'admin/document-assignments/location', 'admin/document-assignments/category', 'admin/users/(:num)/assigned-documents']),
 ('places', ['places', 'places/(:any)', 'places/(:any)/save', 'places/(:any)/deactivate']),
 ('users', ['admin/users', 'admin/users/save', 'admin/users/deactivate']),
 ('permissions', ['admin/roles', 'admin/roles/save', 'admin/roles/deactivate', 'admin/roles/remove']),
 ('workflows', ['admin/workflows', 'admin/workflows/step/save', 'admin/workflows/step/remove', 'admin/workflows/step/move', 'admin/workflows/publish', 'admin/workflows/clone']),
 ('audit', ['admin/audit']),
 ('backups', ['admin/backups', 'admin/backups/create', 'admin/backups/cleanup', 'admin/backups/import', 'admin/backups/download/(:any)']),
 ('restore', ['admin/backups/restore']),
]
REQUESTS = {
 'softcopy_create': 'softcopy-request-create',
 'softcopy_revise': 'softcopy-request-revise',
 'external_create': 'external-request-create',
 'external_revise': 'external-request-revise',
 'transfer': 'transfer-request',
 'access': 'access-request',
 'assignment': 'assignment-request',
 'disposal': 'disposal-request',
}
LEGACY = {'hardcopy_create', 'hardcopy_update', 'softcopy_cancel'}
ROLES = {'Administrator', 'Document Control Officer', 'Plant Manager', 'Internal Auditor', 'Staff'}
REQUIRED_GUIDE = {
 'welcome', 'roles', 'sign-in', 'navigation', 'find-documents',
 'hardcopy-create', 'hardcopy-edit', 'hardcopy-track',
 'softcopy-direct-create', 'softcopy-direct-revise',
 'external-direct-create', 'external-direct-revise', 'request-lifecycle',
 'approvals', 'transfer-dispatch', 'transfer-accept', 'disposal-direct',
 'direct-assignments', 'places', 'categories', 'users', 'permissions',
 'workflows', 'notifications', 'audit', 'backups', 'restore', 'help',
} | set(REQUESTS.values())
SPECS = [
 ('dfd', 'PK_DTS_DFD', 'Data Flow Diagram'),
 ('flowchart', 'PK_DTS_Flowchart', 'Flowchart'),
 ('process', 'PK_DTS_Process_Flow', 'Process Flow'),
 ('guide', 'PK_DTS_User_Guide', 'User Guide'),
]
BOOKS = {
 'dfd': ('See the whole system', 'Roles, information and records',
         'Understand who provides information, how it moves through each activity, and where the result is recorded.',
         ['All five roles', 'Document information', 'Approvals and tracking']),
 'flowchart': ('Choose the next step', 'Decisions made clear',
               'Follow one activity at a time. Each question shows the next action and what happens when a check fails.',
               ['Clear decisions', 'Yes and No paths', 'Practical outcomes']),
 'process': ('Understand the journey', 'People, actions and outcomes',
             'Explain each procedure from start to finish: who acts, what they do, and what happens next.',
             ['Direct document actions', 'Requests and approvals', 'Physical handoffs']),
 'guide': ('Complete everyday tasks', 'A practical guide for every role',
           'Find the task you need, follow the numbered instructions, and check the result before continuing.',
           ['Find and manage documents', 'Request, review and receive', 'Set up and maintain PK DTS']),
}


def chapter(key, identifier):
    if identifier in {'account-entry', 'sign-in-and-find-documents', 'context-all-roles', 'overview'}:
        return 'Getting started'
    if identifier == 'help':
        return 'Help and reference'
    if key == 'guide' and identifier in {'roles', 'welcome', 'navigation', 'sign-in', 'help'}:
        return 'Getting started'
    if any(word in identifier for word in ['backup', 'restore', 'audit', 'account', 'users', 'permissions', 'roles', 'workflows', 'places', 'categories', 'setup']):
        return 'Setup and administration'
    if 'softcopy' in identifier or identifier == 'electronic-direct':
        return 'Softcopy documents'
    if 'external' in identifier:
        return 'External documents'
    if any(word in identifier for word in ['transfer', 'handoff', 'dispatch', 'accept', 'access', 'assignment', 'disposal', 'request', 'approval', 'approve', 'return', 'decisions', 'draft']):
        return 'Requests and tracking'
    if 'hardcopy' in identifier or 'retention' in identifier:
        return 'Hardcopy documents'
    if any(word in identifier for word in ['file', 'browse', 'find']):
        return 'Finding documents and files'
    return 'Getting started'


def document_items(key, data):
    body = entries(key, data)
    contents_count = math.ceil(len(body)/24)
    offset = 1+contents_count
    front = [{'id': 'book-cover', 'kind': 'cover', 'title': dict((k, title) for k, _, title in SPECS)[key]}]
    for index in range(contents_count):
        listing = [{'title': item['title'], 'page': position+offset+1}
                   for position, item in enumerate(body) if index*24 <= position < (index+1)*24]
        front.append({'id': f'contents-{index+1}', 'kind': 'contents',
                      'title': 'Contents' if index == 0 else 'Contents - continued', 'listing': listing})
    return front+body


def plain(value):
    return value.translate(str.maketrans({
        '\u2019': "'", '\u2018': "'", '\u201c': '"', '\u201d': '"',
        '\u2013': '-', '\u2014': '-', '\u2192': ' > ', '\u2022': '-',
        '\u00a0': ' ', '\u2264': '<=', '\u2265': '>=',
    }))


def normalized(value):
    return ' '.join(plain(value).split())


def load_content():
    return {key: json.loads((SOURCE / (key + '.json')).read_text(encoding='utf-8'))
            for key, _, _ in SPECS}


def guide_screens():
    return json.loads((SCREENSHOTS / 'manifest.json').read_text(encoding='utf-8'))


def entries(key, data):
    return data['sections' if key == 'guide' else 'pages']


def check_source(content):
    routes = set(re.findall(r"\$route\['([^']+)'\]", (ROOT / 'application/config/routes.php').read_text()))
    routes -= {'default_controller', '404_override', 'translate_uri_dashes'}
    mapped = {route for _, values in GROUPS for route in values}
    assert routes == mapped, f'Route coverage drift: missing={routes-mapped}, stale={mapped-routes}'
    policy = (ROOT / 'application/services/request/request_policy.php').read_text()
    types = set(re.findall(r"'([^']+)'\s*=>", policy))
    assert types == set(REQUESTS) | LEGACY, f'Request coverage drift: {types ^ (set(REQUESTS) | LEGACY)}'
    service = (ROOT / 'application/services/request/request_service.php').read_text()
    assert 'Hardcopy create and update requests are no longer available.' in service
    assert 'Softcopy cancellation requests are no longer available.' in service
    sections = {section['id'] for section in content['guide']['sections']}
    assert REQUIRED_GUIDE <= sections, f'Missing user procedures: {REQUIRED_GUIDE-sections}'
    assert all(section in sections for section, _ in GROUPS)
    screens = guide_screens()
    assert set(screens) == sections, f'Screenshot coverage drift: {set(screens) ^ sections}'
    for identifier, screen in screens.items():
        path = SCREENSHOTS / screen['file']
        assert path.parent == SCREENSHOTS and path.suffix == '.png', identifier
        image = pdf.Pixmap(path)
        assert (image.width, image.height) == (screen['width'], screen['height']), identifier
        assert screen['caption'], identifier
        assert 1 <= len(screen['controls']) <= 3, f'Missing or excess guide callouts: {identifier}'
        labels = {''.join(control['label'].lower().split()) for control in screen['controls']}
        assert len(labels) == len(screen['controls']), f'Duplicate guide callouts: {identifier}'
        for control in screen['controls']:
            assert control['label'] and control['width'] > 0 and control['height'] > 0, identifier
            assert 0 <= control['x'] < control['x']+control['width'] <= image.width+1, identifier
            assert 0 <= control['y'] < control['y']+control['height'] <= image.height+1, identifier
    dfd_text = json.dumps(content['dfd'])
    assert all(role in dfd_text for role in ROLES), 'DFD must name every supplied role'
    for key, _, _ in SPECS:
        items = entries(key, content[key])
        ids = [item['id'] for item in items]
        assert len(ids) == len(set(ids)), f'Duplicate {key} page identifiers'
        for item in items:
            assert item['title'] and item['id']
            assert 'proposed' not in item['title'].lower(), item['title']
            if key == 'process':
                assert 3 <= len(item['steps']) <= 6, item['id']
            if key == 'guide':
                assert 1 <= len(item['steps']) <= 5, item['id']
            if key == 'flowchart':
                assert all(item.get(field) for field in ['start', 'action', 'question', 'yes', 'no', 'result']), item['id']
    print(f'Source coverage: {len(routes)} route patterns; {len(REQUESTS)} current request actions; {len(LEGACY)} historical actions distinguished.')


def text(page, rect, value, size=16, bold=False, color=INK, align=0, minimum=12):
    """Fail on overflow; never silently omit instructions or shrink to tiny type."""
    rect = pdf.Rect(rect)
    value = plain(value)
    font = 'hebo' if bold else 'helv'
    while size >= minimum:
        # A Shape lets us discard an overflowing attempt before committing it.
        shape = page.new_shape()
        if shape.insert_textbox(rect, value, fontsize=size, fontname=font,
                                color=color, align=align) >= 0:
            shape.commit()
            return
        size -= .5
    raise AssertionError(f'Text overflow on {page.number+1}: {value[:120]}')


def panel(page, rect, fill=PALE, border=RULE):
    page.draw_rect(rect, color=border, fill=fill, width=.65)


def arrow(page, points, label=None, label_rect=None, color=ACCENT):
    page.draw_polyline(points, color=color, width=1.4)
    end, previous = pdf.Point(points[-1]), pdf.Point(points[-2])
    angle = math.atan2(end.y-previous.y, end.x-previous.x)
    wings = [pdf.Point(end.x-8*math.cos(angle+offset), end.y-8*math.sin(angle+offset)) for offset in (-.45, .45)]
    page.draw_polyline([wings[0], end, wings[1]], color=color, width=1.4)
    if label:
        text(page, label_rect, label, 12, color=color, align=1, minimum=11)


def header(doc, kind, title, number, total, subtitle='', section=''):
    page = doc.new_page(width=W, height=H)
    page.draw_rect((0, 0, W, 7), fill=ACCENT, color=ACCENT)
    text(page, (42, 25, 795, 48), 'PK DOCUMENT TRACKING SYSTEM / ' + kind.upper(), 11, True, ACCENT, minimum=11)
    if section:
        text(page, (800, 36, 1148, 62), section.upper(), 10, True, GREY, align=2, minimum=10)
    text(page, (42, 66, 1148, 110), title, 27, True, minimum=20)
    if subtitle:
        text(page, (42, 118, 1148, 157), subtitle, 14, color=GREY, minimum=12)
    page.draw_line((42, 787), (1148, 787), color=RULE, width=.7)
    text(page, (42, 802, 980, 825), MARKER, 10, color=GREY, minimum=10)
    text(page, (903, 802, 980, 825), 'Contents', 10, True, ACCENT, align=2, minimum=10)
    text(page, (1020, 802, 1148, 825), f'{number} / {total}', 10, color=GREY, align=2, minimum=10)
    return page


def notes(page, values, rect, title='Remember'):
    rect = pdf.Rect(rect)
    panel(page, rect, PALE)
    if rect.height < 150:
        width = (rect.width-20) / len(values)
        for index, value in enumerate(values):
            x = rect.x0+10+index*width
            text(page, (x+7, rect.y0+9, x+width-7, rect.y1-6), value, 13, minimum=12)
        return
    page.draw_rect((rect.x0, rect.y0, rect.x0+3, rect.y1), color=ACCENT, fill=ACCENT)
    text(page, (rect.x0+17, rect.y0+15, rect.x1-17, rect.y0+42), title, 15, True, ACCENT)
    y = rect.y0 + 53
    height = (rect.y1-y-10) / max(1, len(values))
    for index, value in enumerate(values):
        text(page, (rect.x0+17, y, rect.x1-17, y+height-9), value, 14, minimum=12)
        if index < len(values)-1:
            page.draw_line((rect.x0+17, y+height-14), (rect.x1-17, y+height-14), color=RULE, width=.5)
        y += height


def process_page(doc, item, number, total):
    page = header(doc, 'Process Flow', item['title'], number, total, item['summary'], chapter('process', item['id']))
    steps = item['steps']
    gap = 18
    height = min(101, (584-gap*(len(steps)-1))/len(steps))
    y = 176
    for index, step in enumerate(steps, 1):
        panel(page, (42, y, 830, y+height), WHITE)
        panel(page, (42, y, 97, y+height), PALE)
        text(page, (54, y+13, 88, y+height-10), f'{index:02}', 19, True, ACCENT)
        text(page, (115, y+10, 810, y+33), step['actor'], 11, True, ACCENT, minimum=11)
        text(page, (115, y+34, 810, y+height-7), step['text'], 16, minimum=13)
        if index != len(steps):
            arrow(page, [(436, y+height), (436, y+height+gap)])
        y += height+gap
    notes(page, item['notes'], (858, 176, 1148, 760))


def flowchart_page(doc, item, number, total):
    page = header(doc, 'Flowchart', item['title'], number, total,
                  'Start at the top. Follow Yes or No at the question to see what happens next.', chapter('flowchart', item['id']))
    for rect, label, fill in [
        ((268, 168, 750, 234), item['start'], INK),
        ((268, 251, 750, 317), item['action'], PALE),
        ((268, 506, 750, 578), item['yes'], PALE),
        ((855, 355, 1148, 478), item['no'], GOLD),
        ((268, 609, 750, 677), item['result'], INK),
    ]:
        r = pdf.Rect(rect)
        if fill == INK:
            # Straight segments retain editable geometry in both Visio formats.
            radius = r.height/2
            points = []
            for center, first in [(pdf.Point(r.x1-radius, r.y0+radius), -90),
                                  (pdf.Point(r.x0+radius, r.y0+radius), 90)]:
                for angle in range(first, first+181, 15):
                    a = math.radians(angle)
                    points.append((center.x+radius*math.cos(a), center.y+radius*math.sin(a)))
            page.draw_polyline(points+[points[0]], color=INK, fill=INK, width=.7)
        else:
            panel(page, rect, fill, RULE)
        text(page, r+(25, 10, -25, -7), label, 16, color=WHITE if fill == INK else INK, align=1, minimum=13)
    diamond = [(509, 345), (710, 410), (509, 475), (308, 410), (509, 345)]
    page.draw_polyline(diamond, color=(.70, .42, .08), fill=GOLD, width=1)
    text(page, (396, 381, 622, 442), item['question'], 16, True, align=1, minimum=13)
    arrow(page, [(509, 234), (509, 251)], color=GREY)
    arrow(page, [(509, 317), (509, 345)], color=GREY)
    arrow(page, [(509, 475), (509, 506)], 'Yes', (524, 478, 569, 501), color=GREY)
    arrow(page, [(710, 410), (855, 410)], 'No', (753, 381, 805, 404), color=GREY)
    arrow(page, [(509, 578), (509, 609)], color=GREY)
    notes(page, item['notes'], (42, 701, 1148, 774), 'Keep in mind')


def guide_page(doc, item, number, total):
    page = header(doc, 'User Guide', item['title'], number, total, 'For: ' + item['audience'], chapter('guide', item['id']))
    screen = guide_screens()[item['id']]
    # An unfilled frame keeps the separate screenshot and callouts editable in PowerPoint.
    page.draw_rect((42, 176, 818, 594), color=RULE, width=.7)
    text(page, (57, 183, 803, 205), screen['caption'], 11, True, GREY, minimum=10)
    scale = min(746/screen['width'], 373/screen['height'])
    width, height = screen['width']*scale, screen['height']*scale
    image_rect = pdf.Rect(430-width/2, 397-height/2, 430+width/2, 397+height/2)
    page.insert_image(image_rect, filename=str(SCREENSHOTS/screen['file']))
    for index, control in enumerate(screen['controls']):
        letter = chr(65+index)
        x, y = image_rect.x0+control['x']*scale, image_rect.y0+control['y']*scale
        region = pdf.Rect(x, y, x+control['width']*scale, y+control['height']*scale)
        page.draw_rect(region, color=ACCENT, width=.8)
        badge_x = max(image_rect.x0, x-23)
        badge_y = max(image_rect.y0, min(y, image_rect.y1-20))
        panel(page, (badge_x, badge_y, badge_x+20, badge_y+20), ACCENT, ACCENT)
        text(page, (badge_x, badge_y+2, badge_x+20, badge_y+20), letter, 9, True, WHITE, align=1, minimum=9)
        legend_x = 42+index*262
        text(page, (legend_x, 600, legend_x+251, 626), letter+'  '+control['label'], 11, True, ACCENT, minimum=10)

    text(page, (847, 178, 1148, 205), 'WHAT TO DO', 12, True, ACCENT)
    weights = []
    for value in item['steps']:
        lines, current = 1, ''
        for word in plain(value).split():
            candidate = (current+' '+word).strip()
            if pdf.get_text_length(candidate, fontsize=15) > 256:
                lines, current = lines+1, word
            else:
                current = candidate
        weights.append(max(2, lines))
    spare = (550-sum(weights)*18)/len(weights)
    y = 218
    for index, (value, weight) in enumerate(zip(item['steps'], weights), 1):
        height = weight*18+spare
        text(page, (847, y+2, 878, y+24), f'{index:02}', 11, True, ACCENT, minimum=11)
        text(page, (892, y, 1148, y+height-12), value, 15, minimum=13.5)
        y += height

    panel(page, (42, 632, 818, 774), PALE)
    page.draw_line((290, 644), (290, 761), color=RULE, width=.7)
    text(page, (57, 643, 275, 665), 'EXPECTED RESULT', 11, True, ACCENT, minimum=11)
    text(page, (57, 670, 275, 766), item['result'], 13, minimum=12)
    text(page, (307, 643, 803, 665), 'REMEMBER', 11, True, ACCENT, minimum=11)
    text(page, (307, 670, 803, 766), '\n'.join(item.get('notes', [])), 12.5, minimum=11)


def diagram_icon(page, rect, kind):
    # Reuse the original illustrations from the 680275d documentation package.
    suffix = '.jpeg' if kind == 'database' else '.png'
    page.insert_image(pdf.Rect(rect), filename=str(ICONS/('icon-'+kind+suffix)))


def dfd_page(doc, item, number, total):
    page = header(doc, 'Data Flow Diagram', item['title'], number, total, item['summary'], chapter('dfd', item['id']))
    if item.get('kind') == 'context':
        # Level 0 has one system and five external roles; records stay on detail pages.
        panel(page, (435, 180, 755, 681), PALE)
        panel(page, (435, 180, 755, 226), ACCENT, ACCENT)
        text(page, (451, 190, 739, 224), '0. PK DTS', 23, True, WHITE, align=1)
        diagram_icon(page, (507, 258, 683, 410), 'account')
        text(page, (457, 444, 733, 620), 'Find and track documents\nManage requests and approvals\nRecord handoffs and history', 18, align=1)
        positions = [(42, 180, 298, 329), (42, 356, 298, 505), (42, 532, 298, 681),
                     (892, 268, 1148, 417), (892, 532, 1148, 681)]
        for actor, rect in zip(item['actors'], positions):
            r = pdf.Rect(rect)
            panel(page, r+(5, 5, 5, 5), PALE, PALE)
            panel(page, r, WHITE)
            kind = 'review' if actor['role'] in {'Plant Manager', 'Internal Auditor'} else 'account' if actor['role'] == 'Administrator' else 'person'
            diagram_icon(page, (r.x0+100, r.y0+8, r.x0+156, r.y0+62), kind)
            panel(page, (r.x0, r.y0+69, r.x1, r.y0+105), ACCENT, ACCENT)
            text(page, (r.x0+9, r.y0+74, r.x1-9, r.y0+105), actor['role'], 14, True, WHITE, align=1, minimum=12)
            text(page, (r.x0+10, r.y0+112, r.x1-10, r.y1-3), actor['responsibility'], 11, align=1, minimum=10)
            cy = (r.y0+r.y1)/2
            if r.x0 < 400:
                left, right = r.x1, 435
                arrow(page, [(left, cy-16), (right, cy-16)], color=GREY)
                arrow(page, [(right, cy+17), (left, cy+17)], color=GREY)
                text(page, (left+4, cy-60, right-4, cy-19), actor['sends'], 11, align=1, minimum=10)
                text(page, (left+4, cy+23, right-4, cy+66), actor['receives'], 11, align=1, minimum=10)
            else:
                left, right = 755, r.x0
                arrow(page, [(right, cy-16), (left, cy-16)], color=GREY)
                arrow(page, [(left, cy+17), (right, cy+17)], color=GREY)
                text(page, (left+4, cy-60, right-4, cy-19), actor['sends'], 11, align=1, minimum=10)
                text(page, (left+4, cy+23, right-4, cy+66), actor['receives'], 11, align=1, minimum=10)
        panel(page, (42, 716, 1148, 774), PALE)
        text(page, (57, 728, 1133, 768), item['note'], 13, minimum=12)
        return
    rows = item['flows']
    for index, row in enumerate(rows):
        y = 176+index*108
        for x0, x1, field, kind in [(42, 286, 'from', row['from_kind']), (467, 735, 'process', 'process'), (918, 1148, 'to', row['to_kind'])]:
            if kind == 'store':
                # Keep the open-ended data-store symbol distinct from actor cards.
                page.draw_polyline([(x1, y), (x0, y), (x0, y+96), (x1, y+96)], color=GREY, width=1)
                icon = 'database'
            else:
                panel(page, (x0+4, y+4, x1+4, y+100), PALE, PALE)
                panel(page, (x0, y, x1, y+96), WHITE)
                icon = 'account' if kind == 'process' else 'review' if any(word in row[field].lower() for word in ['approv', 'review', 'audit']) else 'person'
            diagram_icon(page, ((x0+x1)/2-32, y+4, (x0+x1)/2+32, y+50), icon)
            if kind != 'store':
                panel(page, (x0, y+55, x1, y+96), ACCENT, ACCENT)
            text(page, (x0+10, y+59, x1-10, y+94), row[field], 13.5, bold=kind == 'process', color=INK if kind == 'store' else WHITE, align=1, minimum=12)
        for x0, x1, field in [(286, 467, 'input'), (735, 918, 'output')]:
            arrow(page, [(x0+4, y+54), (x1, y+54)], color=GREY)
            text(page, (x0+8, y+3, x1-8, y+47), row[field], 12, align=1, minimum=11)
    panel(page, (42, 727, 1148, 776), PALE)
    text(page, (57, 737, 1133, 773), item['note'], 13, minimum=12)


def cover_page(doc, key, item, number, total):
    page = header(doc, item['title'], '', number, total, section='Everyday procedures')
    lead, subtitle, description, topics = BOOKS[key]
    text(page, (42, 174, 775, 211), lead.upper(), 13, True, ACCENT)
    text(page, (42, 228, 790, 372), item['title'], 56, True, minimum=43)
    text(page, (42, 390, 785, 439), subtitle, 24, True, minimum=20)
    text(page, (42, 460, 770, 576), description, 21, minimum=18)
    panel(page, (42, 655, 783, 750), PALE)
    text(page, (62, 672, 763, 730), 'Start with the contents page. Choose a topic, follow its explanation, and check the result.', 19, minimum=16)
    panel(page, (835, 174, 1148, 750), INK, INK)
    text(page, (859, 201, 1124, 243), 'INSIDE THIS DOCUMENT', 13, True, WHITE)
    for index, topic in enumerate(topics):
        y = 285+index*139
        text(page, (859, y, 1124, y+31), f'0{index+1}', 17, True, (.37, .86, .80))
        text(page, (859, y+40, 1124, y+116), topic, 22, True, WHITE, minimum=18)
        if index < len(topics)-1:
            page.draw_line((859, y+124), (1124, y+124), color=(.28, .37, .46), width=.6)


def contents_rows(item):
    for index, entry in enumerate(item['listing']):
        column, row = divmod(index, 12)
        x, y = 42+column*568, 190+row*46
        yield entry, pdf.Rect(x, y, x+538, y+40)


def contents_page(doc, key, item, number, total):
    kind = next(title for value, _, title in SPECS if value == key)
    page = header(doc, kind, item['title'], number, total,
                  'Choose a topic to jump to its page in the PDF. Page numbers also help you find it in the editable copy.', 'Find your task')
    for entry, rect in contents_rows(item):
        page.draw_line((rect.x0, rect.y1), (rect.x1, rect.y1), color=RULE, width=.5)
        text(page, (rect.x0+1, rect.y0+3, rect.x1-58, rect.y1-2), entry['title'], 14, minimum=12)
        text(page, (rect.x1-47, rect.y0+3, rect.x1-2, rect.y1-2), str(entry['page']), 15, True, ACCENT, align=2)


def rgb(value):
    return '#' + ''.join(f'{round(v*255):02X}' for v in value)


def xml(element):
    return ET.tostring(element, encoding='utf-8', xml_declaration=True)


def cell(parent, name, value):
    return ET.SubElement(parent, '{'+NS+'}Cell', N=name, V=str(value))


def verify_diagram_images(doc, parts, legacy):
    """Check every placed picture's pixels, size and position in both Visio exports."""
    legacy_pages = legacy.findall('{'+VDX_NS+'}Pages/{'+VDX_NS+'}Page')
    assert len(legacy_pages) == len(doc), 'Legacy diagram page count mismatch'
    for index, (page, compatible) in enumerate(zip(doc, legacy_pages), 1):
        expected = diagram_images(page)
        original = ET.fromstring(parts[f'visio/pages/page{index}.xml'])
        original_shapes = [shape for shape in original.iter('{'+NS+'}Shape')
                           if shape.find('{'+NS+'}ForeignData') is not None]
        compatible_shapes = [shape for shape in compatible.iter('{'+VDX_NS+'}Shape')
                             if shape.find('{'+VDX_NS+'}ForeignData') is not None]
        assert len(original_shapes) == len(expected), f'Missing VSDX diagram pictures: page {index}'
        assert len(compatible_shapes) == len(expected), f'Missing VDX diagram pictures: page {index}'
        if not expected:
            continue
        relationships = ET.fromstring(parts[f'visio/pages/_rels/page{index}.xml.rels'])
        targets = {relationship.get('Id'): posixpath.normpath(posixpath.join(
            'visio/pages', relationship.get('Target'))) for relationship in relationships}
        for image, native, old in zip(expected, original_shapes, compatible_shapes):
            foreign = native.find('{'+NS+'}ForeignData')
            relationship_id = foreign.find('{'+NS+'}Rel').get('{'+REL+'}id')
            payloads = [parts[targets[relationship_id]], base64.b64decode(old.find('{'+VDX_NS+'}ForeignData').text, validate=True)]
            expected_pixels = pdf.Pixmap(image['data'])
            for payload in payloads:
                pixels = pdf.Pixmap(payload)
                assert (pixels.width, pixels.height, pixels.n, pixels.digest) == (
                    expected_pixels.width, expected_pixels.height, expected_pixels.n, expected_pixels.digest), f'Diagram picture pixels changed: page {index}'
            cells = {entry.get('N'): float(entry.get('V')) for entry in native.findall('{'+NS+'}Cell')}
            old_cells = {entry.tag.rsplit('}', 1)[-1]: float(entry.text) for entry in old.find('{'+VDX_NS+'}XForm')}
            for values in [cells, old_cells]:
                width, height = values['Width']*72, values['Height']*72
                left, top = values['PinX']*72-width/2, page.rect.height-values['PinY']*72-height/2
                actual = (left, top, left+width, top+height)
                assert max(abs(a-b) for a, b in zip(actual, image['rect'])) < .01, f'Diagram picture moved or resized: page {index}'
            assert foreign.get('CompressionType') == image['compression'], f'Picture compression changed: page {index}'
            assert old.find('{'+VDX_NS+'}ForeignData').get('CompressionType') == image['compression'], f'Legacy picture compression changed: page {index}'


def diagram_images(page):
    """Return every placed picture, retaining JPEG bytes or PNG transparency."""
    images = []
    for info in page.get_image_info(xrefs=True):
        assert info['xref'] > 0, 'Inline diagram pictures need explicit export support'
        image = page.parent.extract_image(info['xref'])
        data, extension = image['image'], image['ext']
        if image['smask']:
            pixels = pdf.Pixmap(page.parent, info['xref'])
            alpha = pdf.Pixmap(page.parent, image['smask'])
            data, extension = pdf.Pixmap(pixels, alpha).tobytes('png'), 'png'
        assert extension in {'png', 'jpg', 'jpeg'}, 'Unsupported diagram picture: '+extension
        images.append({'rect': pdf.Rect(info['bbox']), 'data': data, 'extension': extension,
                       'compression': 'PNG' if extension == 'png' else 'JPEG'})
    return images


def native_page(page, image_relationships=None):
    """Native geometry, editable text and embedded pictures matching the PDF."""
    tag = lambda name: '{'+NS+'}'+name
    root = ET.Element(tag('PageContents'))
    shapes = ET.SubElement(root, tag('Shapes'))
    def shape(rect):
        r = pdf.Rect(rect)
        width, height = max(r.width, .01), max(r.height, .01)
        node = ET.SubElement(shapes, tag('Shape'), ID=str(len(shapes)+1), Type='Shape', LineStyle='0', FillStyle='0', TextStyle='0')
        for name, value in [('PinX', (r.x0+width/2)/72), ('PinY', (page.rect.height-r.y0-height/2)/72), ('Width', width/72), ('Height', height/72), ('LocPinX', width/144), ('LocPinY', height/144), ('FillPattern', 0), ('LinePattern', 0)]:
            cell(node, name, value)
        return node
    if image_relationships is None:
        assert not page.get_image_info(), 'Image relationships are required for editable diagram pictures'
        image_relationships = []
    for drawing in page.get_drawings():
        r = drawing['rect']
        node = shape(r)
        if drawing['color']:
            node.find(tag('Cell')+"[@N='LinePattern']").set('V', '1')
            cell(node, 'LineColor', rgb(drawing['color']))
            cell(node, 'LineWeight', drawing['width']/72)
        if drawing['fill']:
            node.find(tag('Cell')+"[@N='FillPattern']").set('V', '1')
            cell(node, 'FillForegnd', rgb(drawing['fill']))
        geometry = ET.SubElement(node, tag('Section'), N='Geometry', IX='0')
        points = []
        for part in drawing['items']:
            if part[0] == 're':
                q = part[1]
                points = [q.tl, q.tr, q.br, q.bl, q.tl]
            elif part[0] == 'qu':
                q = part[1]
                points = [q.ul, q.ll, q.lr, q.ur, q.ul]
            elif part[0] == 'l':
                if not points:
                    points.append(part[1])
                points.append(part[2])
            else:
                raise AssertionError(f'Unsupported editable geometry: {part[0]}')
        for index, point in enumerate(points):
            row = ET.SubElement(geometry, tag('Row'), T='MoveTo' if index == 0 else 'LineTo', IX=str(index+1))
            cell(row, 'X', (point.x-r.x0)/72)
            cell(row, 'Y', (r.y1-point.y)/72)
    for image, relationship_id in image_relationships:
        r = image['rect']
        node = shape(r)
        node.set('Type', 'Foreign')
        for name, value in [('ImgOffsetX', 0), ('ImgOffsetY', 0),
                            ('ImgWidth', r.width/72), ('ImgHeight', r.height/72)]:
            cell(node, name, value)
        geometry = ET.SubElement(node, tag('Section'), N='Geometry', IX='0')
        for index, point in enumerate([r.bl, r.br, r.tr, r.tl, r.bl], 1):
            row = ET.SubElement(geometry, tag('Row'), T='MoveTo' if index == 1 else 'LineTo', IX=str(index))
            cell(row, 'X', (point.x-r.x0)/72)
            cell(row, 'Y', (r.y1-point.y)/72)
        foreign = ET.SubElement(node, tag('ForeignData'), ForeignType='Bitmap', CompressionType=image['compression'])
        ET.SubElement(foreign, tag('Rel'), {'{'+REL+'}id': relationship_id})
    for block in page.get_text('dict')['blocks']:
        for line in block.get('lines', []):
            for span in line['spans']:
                r = pdf.Rect(span['bbox'])
                node = shape((r.x0, r.y0-1, r.x1+4, r.y1+4))
                for name in ['LeftMargin', 'RightMargin', 'TopMargin', 'BottomMargin']:
                    cell(node, name, 0)
                cell(node, 'VerticalAlign', 0)
                section = ET.SubElement(node, tag('Section'), N='Character')
                row = ET.SubElement(section, tag('Row'), IX='0')
                cell(row, 'Font', 'Arial')
                cell(row, 'Size', span['size']/72)
                cell(row, 'Style', 1 if 'bold' in span['font'].lower() else 0)
                cell(row, 'Color', f'#{span["color"]:06X}')
                paragraph = ET.SubElement(node, tag('Section'), N='Paragraph')
                cell(ET.SubElement(paragraph, tag('Row'), IX='0'), 'HorzAlign', 0)
                ET.SubElement(node, tag('Text')).text = span['text']
    return xml(root)


def visio(doc, items, title):
    # Self-contained OPC package: rebuilding does not retain obsolete pages,
    # illustrations or supplement text from a previous export.
    tag = lambda name: '{'+NS+'}'+name
    document = ET.Element(tag('VisioDocument'))
    ET.SubElement(document, tag('DocumentSettings'), TopPage='0', DefaultTextStyle='0', DefaultLineStyle='0', DefaultFillStyle='0')
    faces = ET.SubElement(document, tag('FaceNames'))
    # VSDX names fonts; numeric font IDs belong to the older VDX format.
    ET.SubElement(faces, tag('FaceName'), NameU='Arial')
    style = ET.SubElement(ET.SubElement(document, tag('StyleSheets')), tag('StyleSheet'), ID='0', NameU='No Style')
    for name, value in [('EnableLineProps', 1), ('EnableFillProps', 1), ('EnableTextProps', 1), ('ShdwPattern', 0)]:
        cell(style, name, value)
    pages, relationships = ET.Element(tag('Pages')), ET.Element('{'+PKG+'}Relationships')
    content = ET.Element('{'+CT+'}Types')
    ET.SubElement(content, '{'+CT+'}Default', Extension='rels', ContentType='application/vnd.openxmlformats-package.relationships+xml')
    ET.SubElement(content, '{'+CT+'}Default', Extension='xml', ContentType='application/xml')
    for extension, mime in [('png', 'image/png'), ('jpeg', 'image/jpeg'), ('jpg', 'image/jpeg')]:
        ET.SubElement(content, '{'+CT+'}Default', Extension=extension, ContentType=mime)
    parts = {'visio/document.xml': xml(document)}
    media = {}
    overrides = [('visio/document.xml', 'application/vnd.ms-visio.drawing.main+xml'), ('visio/pages/pages.xml', 'application/vnd.ms-visio.pages+xml')]
    for index, (page, item) in enumerate(zip(doc, items), 1):
        node = ET.SubElement(pages, tag('Page'), ID=str(index-1), NameU=f'Page-{index}', Name=plain(item['title']), IsCustomName='1')
        sheet = ET.SubElement(node, tag('PageSheet'), LineStyle='0', FillStyle='0', TextStyle='0')
        for name, value in [('PageWidth', page.rect.width/72), ('PageHeight', page.rect.height/72), ('PageScale', 1), ('DrawingScale', 1), ('PrintPageOrientation', 2)]:
            cell(sheet, name, value)
        ET.SubElement(node, tag('Rel'), {'{'+REL+'}id': f'rId{index}'})
        ET.SubElement(relationships, '{'+PKG+'}Relationship', Id=f'rId{index}', Type='http://schemas.microsoft.com/visio/2010/relationships/page', Target=f'page{index}.xml')
        name = f'visio/pages/page{index}.xml'
        page_relationships = ET.Element('{'+PKG+'}Relationships')
        images = []
        for image in diagram_images(page):
            digest = hashlib.sha256(image['data']).hexdigest()
            if digest not in media:
                media[digest] = 'visio/media/image'+str(len(media)+1)+'.'+image['extension']
                parts[media[digest]] = image['data']
            relationship_id = 'rIdImage'+str(len(images)+1)
            ET.SubElement(page_relationships, '{'+PKG+'}Relationship', Id=relationship_id,
                          Type=REL+'/image', Target='../media/'+posixpath.basename(media[digest]))
            images.append((image, relationship_id))
        parts[name] = native_page(page, images)
        if images:
            parts[f'visio/pages/_rels/page{index}.xml.rels'] = xml(page_relationships)
        overrides.append((name, 'application/vnd.ms-visio.page+xml'))
    parts['visio/pages/pages.xml'] = xml(pages)
    parts['visio/pages/_rels/pages.xml.rels'] = xml(relationships)
    for name, value in overrides:
        ET.SubElement(content, '{'+CT+'}Override', PartName='/'+name, ContentType=value)
    parts['[Content_Types].xml'] = xml(content)
    for filename, relationships_ in [('_rels/.rels', [('http://schemas.microsoft.com/visio/2010/relationships/document', 'visio/document.xml')]), ('visio/_rels/document.xml.rels', [('http://schemas.microsoft.com/visio/2010/relationships/pages', 'pages/pages.xml')])]:
        root = ET.Element('{'+PKG+'}Relationships')
        for index, (type_, target) in enumerate(relationships_, 1):
            ET.SubElement(root, '{'+PKG+'}Relationship', Id=f'rId{index}', Type=type_, Target=target)
        parts[filename] = xml(root)
    return parts


def powerpoint(doc):
    prs = Presentation()
    prs.slide_width, prs.slide_height = Inches(W/72), Inches(H/72)
    prs.core_properties.title = 'PK DTS - User Guide'
    prs.core_properties.subject = 'Current plain-language user procedures, 5 November 2026'
    for page in doc:
        slide = prs.slides.add_slide(prs.slide_layouts[6])
        for block in page.get_text('dict')['blocks']:
            if block['type'] == 1:
                r = pdf.Rect(block['bbox'])
                slide.shapes.add_picture(io.BytesIO(block['image']), Pt(r.x0), Pt(r.y0), Pt(r.width), Pt(r.height))
        for drawing in page.get_drawings():
            parts = drawing['items']
            if len(parts) == 1 and parts[0][0] == 're':
                r = parts[0][1]
                node = slide.shapes.add_shape(MSO_SHAPE.RECTANGLE, Pt(r.x0), Pt(r.y0), Pt(r.width), Pt(r.height))
            else:
                assert not drawing['fill'], 'Filled PowerPoint polygon needs explicit support'
                for part in parts:
                    assert part[0] == 'l', part[0]
                    node = slide.shapes.add_connector(MSO_CONNECTOR.STRAIGHT, Pt(part[1].x), Pt(part[1].y), Pt(part[2].x), Pt(part[2].y))
                    node.line.color.rgb = RGBColor.from_string(rgb(drawing['color'])[1:])
                    node.line.width = Pt(drawing['width'])
                continue
            if drawing['fill']:
                node.fill.solid()
                node.fill.fore_color.rgb = RGBColor.from_string(rgb(drawing['fill'])[1:])
            else:
                node.fill.background()
            if drawing['color']:
                node.line.color.rgb = RGBColor.from_string(rgb(drawing['color'])[1:])
                node.line.width = Pt(drawing['width'])
            else:
                node.line.fill.background()
        for block in page.get_text('dict')['blocks']:
            for line in block.get('lines', []):
                for span in line['spans']:
                    r = pdf.Rect(span['bbox'])
                    node = slide.shapes.add_textbox(Pt(r.x0), Pt(r.y0-1), Pt(r.width+5), Pt(r.height+5))
                    frame = node.text_frame
                    frame.margin_left = frame.margin_right = frame.margin_top = frame.margin_bottom = 0
                    frame.word_wrap = False
                    run = frame.paragraphs[0].add_run()
                    run.text, run.font.name, run.font.size = span['text'], 'Arial', Pt(span['size'])
                    run.font.bold = 'bold' in span['font'].lower()
                    run.font.color.rgb = RGBColor.from_string(f'{span["color"]:06X}')
    return prs


def refresh(content):
    renderers = {'process': process_page, 'guide': guide_page, 'flowchart': flowchart_page, 'dfd': dfd_page}
    # Render and validate the complete set before replacing any published file.
    pending = []
    try:
        for key, base, kind in SPECS:
            doc = pdf.open()
            items = document_items(key, content[key])
            for index, item in enumerate(items, 1):
                if item.get('kind') == 'cover':
                    cover_page(doc, key, item, index, len(items))
                elif item.get('kind') == 'contents':
                    contents_page(doc, key, item, index, len(items))
                else:
                    renderers[key](doc, item, index, len(items))
            for index, item in enumerate(items):
                doc[index].insert_link({'kind': pdf.LINK_GOTO, 'from': pdf.Rect(903, 800, 985, 828), 'page': 1})
                if item.get('kind') == 'contents':
                    for entry, rect in contents_rows(item):
                        doc[index].insert_link({'kind': pdf.LINK_GOTO, 'from': rect, 'page': entry['page']-1})
            doc.set_toc([[1, plain(item['title']), index] for index, item in enumerate(items, 1)])
            doc.set_metadata({'title': 'PK DTS - '+kind, 'subject': 'Current plain-language procedures, 5 November 2026', 'author': 'PK Document Tracking System'})
            pdf_path = DOCS / (base+'.pdf.pending')
            pending.append((pdf_path, DOCS/(base+'.pdf')))
            doc.save(pdf_path, garbage=4, deflate=True)
            extension = '.pptx' if key == 'guide' else '.vsdx'
            editable_path = DOCS / (base+extension+'.pending')
            pending.append((editable_path, DOCS/(base+extension)))
            if key == 'guide':
                powerpoint(doc).save(editable_path)
            else:
                parts = visio(doc, items, kind)
                with zipfile.ZipFile(editable_path, 'w', zipfile.ZIP_DEFLATED) as archive:
                    for name, data in parts.items():
                        archive.writestr(name, data)
                legacy_path = DOCS / (base+'.vdx.pending')
                pending.append((legacy_path, DOCS/(base+'.vdx')))
                legacy_path.write_bytes(legacy_visio(parts, kind))
            doc.close()
        verify(content, pending=True)
        for temporary, destination in pending:
            temporary.replace(destination)
    finally:
        for temporary, _ in pending:
            temporary.unlink(missing_ok=True)


def check_package(path):
    with zipfile.ZipFile(path) as archive:
        assert archive.testzip() is None, path
        assert len(archive.namelist()) == len(set(archive.namelist())), f'Duplicate parts: {path}'
        fonts = None
        if '.vsdx' in path.suffixes:
            document = ET.fromstring(archive.read('visio/document.xml'))
            faces = document.findall('{'+NS+'}FaceNames/{'+NS+'}FaceName')
            fonts = {face.get('NameU') for face in faces}
            assert fonts and None not in fonts, f'Missing named Visio fonts: {path}'
            for face in faces:
                assert set(face.attrib) <= {'NameU', 'UnicodeRanges', 'CharSets', 'Panos', 'Panose', 'Flags'}, f'Invalid VSDX font attributes in {path}: {face.attrib}'
        for name in archive.namelist():
            if name.endswith(('.xml', '.rels')):
                root = ET.fromstring(archive.read(name))
                if fonts is not None:
                    for value in root.iter('{'+NS+'}Cell'):
                        if value.get('N') == 'Font':
                            assert value.get('V') in fonts, f'Unknown VSDX font in {path}: {value.attrib}'
                if name.endswith('.rels'):
                    base = '' if name == '_rels/.rels' else posixpath.dirname(posixpath.dirname(name))
                    for relationship in root:
                        if relationship.get('TargetMode') == 'External':
                            continue
                        target = relationship.get('Target')
                        resolved = posixpath.normpath(posixpath.join(base, target)) if not target.startswith('/') else target.lstrip('/')
                        assert resolved in archive.namelist(), f'Broken relationship in {path}: {name} -> {resolved}'


def source_strings(item):
    # Include every reader-facing value, excluding identifiers and symbol types.
    if isinstance(item, str):
        yield item
    elif isinstance(item, list):
        for value in item:
            yield from source_strings(value)
    elif isinstance(item, dict):
        for key, value in item.items():
            if key not in {'id', 'kind', 'from_kind', 'to_kind'}:
                yield from source_strings(value)


def verify(content, pending=False):
    suffix = '.pending' if pending else ''
    for key, base, _ in SPECS:
        path = DOCS / (base+'.pdf'+suffix)
        items = document_items(key, content[key])
        with pdf.open(path) as doc:
            assert len(doc) == len(items), f'Page count mismatch: {path}'
            all_text = []
            for page, item in zip(doc, items):
                page_text = normalized(page.get_text())
                assert MARKER in page_text
                if item.get('kind') in {'cover', 'contents'}:
                    assert normalized(item['title']) in page_text
                    if item['kind'] == 'contents':
                        assert len(page.get_links()) == len(item['listing'])+1, f'Missing PDF contents links: {path}'
                        assert {link['page'] for link in page.get_links()} == {1} | {entry['page']-1 for entry in item['listing']}, f'Incorrect PDF contents destinations: {path}'
                        for entry in item['listing']:
                            assert normalized(entry['title']) in page_text
                else:
                    for value in source_strings(item):
                        assert normalized(value) in page_text, f'Missing or altered instructions in {path}, page {page.number+1}: {value}'
                    if key == 'dfd':
                        expected_icons = 6 if item.get('kind') == 'context' else 3*len(item['flows'])
                        assert len(page.get_image_info()) == expected_icons, f'Missing DFD illustrations: {item["id"]}'
                    if key == 'guide':
                        screen = guide_screens()[item['id']]
                        assert normalized(screen['caption']) in page_text, f'Missing screen caption: {item["id"]}'
                        for control in screen['controls']:
                            assert normalized(control['label']) in page_text, f'Missing guide callout: {item["id"]}: {control["label"]}'
                        images = page.get_image_info(hashes=True)
                        assert len(images) == 1, f'Missing guide screenshot: {item["id"]}'
                        assert images[0]['digest'] == pdf.Pixmap(SCREENSHOTS/screen['file']).digest, f'Stale guide screenshot: {item["id"]}'
                for word in ['workflow_steps', 'payload', 'my-requests/', 'softcopy_create', 'external_create']:
                    assert word not in page_text, f'Technical content in {path}: {word}'
                for block in page.get_text('dict')['blocks']:
                    for line in block.get('lines', []):
                        for span in line['spans']:
                            assert page.rect.contains(pdf.Rect(span['bbox'])), f'Text outside page: {path}: {span["text"]}'
                all_text.append(page_text)
            editable = DOCS / (base+('.pptx' if key == 'guide' else '.vsdx')+suffix)
            check_package(editable)
            if key == 'guide':
                prs = Presentation(editable)
                assert len(prs.slides) == len(doc)
                for slide, page, item in zip(prs.slides, doc, items):
                    pictures = [shape for shape in slide.shapes if shape.shape_type == 13]
                    assert len(pictures) == len(page.get_image_info()), f'PowerPoint screenshot mismatch: {item["id"]}'
                    if pictures:
                        screen = guide_screens()[item['id']]
                        assert pdf.Pixmap(pictures[0].image.blob).digest == pdf.Pixmap(SCREENSHOTS/screen['file']).digest, f'Stale PowerPoint screenshot: {item["id"]}'
                native_text = ['\n'.join(s.text for s in slide.shapes if s.has_text_frame) for slide in prs.slides]
            else:
                with zipfile.ZipFile(editable) as archive:
                    visio_parts = {name: archive.read(name) for name in archive.namelist()}
                    pages = ET.fromstring(archive.read('visio/pages/pages.xml'))
                    assert len(pages) == len(doc)
                    page_parts = [ET.fromstring(archive.read(f'visio/pages/page{i+1}.xml')) for i in range(len(pages))]
                    native_text = ['\n'.join(''.join(t.itertext()) for t in page.iter('{'+NS+'}Text')) for page in page_parts]
                legacy = ET.parse(DOCS / (base+'.vdx'+suffix)).getroot()
                assert legacy.tag == '{'+VDX_NS+'}VisioDocument', f'Invalid legacy Visio namespace: {base}'
                legacy_pages = legacy.findall('{'+VDX_NS+'}Pages/{'+VDX_NS+'}Page')
                assert len(legacy_pages) == len(doc), f'Legacy Visio page count mismatch: {base}'
                assert [page.get('Name') for page in legacy_pages] == [page.get('Name') for page in pages], f'Legacy Visio page names changed: {base}'
                for original, compatible in zip(page_parts, legacy_pages):
                    assert [s.get('ID') for s in original.iter('{'+NS+'}Shape')] == [s.get('ID') for s in compatible.iter('{'+VDX_NS+'}Shape')], f'Legacy Visio shapes changed: {base}'
                legacy_text = ['\n'.join(''.join(t.itertext()) for t in page.iter('{'+VDX_NS+'}Text')) for page in legacy_pages]
                assert [normalized(t) for t in legacy_text] == all_text, f'Legacy Visio/PDF text mismatch: {base}'
                verify_diagram_images(doc, visio_parts, legacy)
            assert [normalized(t) for t in native_text] == all_text, f'Editable/PDF text mismatch: {base}'
        illustrations = f'; {len(guide_screens())} screenshots and their callouts match' if key == 'guide' else ''
        print(f'{base}: {len(items)} pages; all source instructions and editable text match; package links valid{illustrations}.')
    if not pending:
        expected = {base+ext for key, base, _ in SPECS for ext in ['.pdf', '.pptx' if key == 'guide' else '.vsdx']}
        expected.update(base+'.vdx' for key, base, _ in SPECS if key != 'guide')
        actual = {path.name for path in DOCS.iterdir() if path.suffix in {'.pdf', '.pptx', '.vsdx', '.vdx'} and not path.name.startswith('~$')}
        assert actual == expected, f'Unexpected exports: {actual ^ expected}'
        print('Verified all 8 PDF/editable exports and 3 legacy Visio copies. Reference notes and Office lock files are not treated as exports.')


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--verify', action='store_true')
    parser.add_argument('--check-source', action='store_true')
    args = parser.parse_args()
    content = load_content()
    check_source(content)
    if not args.check_source:
        if not args.verify:
            refresh(content)
        verify(content)
