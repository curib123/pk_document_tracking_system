"""Build the current plain-language PDF, Visio and PowerPoint documentation.

Content lives in the four adjacent JSON files. --verify checks existing exports;
--check-source checks the route, request and content inventory without exporting.
Requires the existing pymupdf and python-pptx packages. No application data is read.
Visio exports include VSDX and VDX copies for older desktop versions.
"""
from pathlib import Path
import argparse
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
DOCS = ROOT / 'docs/system-documentation'
W, H = 1190, 842
MARKER = 'CURRENT USER PROCEDURES | 05 NOVEMBER 2026'
NS = 'http://schemas.microsoft.com/office/visio/2012/main'
REL = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships'
PKG = 'http://schemas.openxmlformats.org/package/2006/relationships'
CT = 'http://schemas.openxmlformats.org/package/2006/content-types'
ET.register_namespace('', NS)
ET.register_namespace('r', REL)
INK = (.10, .18, .22)
TEAL = (.04, .36, .39)
PALE = (.92, .97, .97)
GOLD = (.99, .95, .85)
GREY = (.43, .49, .53)

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


def panel(page, rect, fill=PALE, border=TEAL):
    page.draw_rect(rect, color=border, fill=fill, width=.8)


def arrow(page, points, label=None, label_rect=None):
    page.draw_polyline(points, color=TEAL, width=1.4)
    end, previous = pdf.Point(points[-1]), pdf.Point(points[-2])
    angle = math.atan2(end.y-previous.y, end.x-previous.x)
    wings = [pdf.Point(end.x-8*math.cos(angle+offset), end.y-8*math.sin(angle+offset)) for offset in (-.45, .45)]
    page.draw_polyline([wings[0], end, wings[1]], color=TEAL, width=1.4)
    if label:
        text(page, label_rect, label, 12, color=TEAL, align=1, minimum=11)


def header(doc, kind, title, number, total, subtitle=''):
    page = doc.new_page(width=W, height=H)
    page.draw_rect((0, 0, W, 17), fill=TEAL, color=TEAL)
    text(page, (42, 33, 1148, 55), 'PK DOCUMENT TRACKING SYSTEM  /  ' + kind.upper(), 12, True, TEAL)
    text(page, (42, 69, 1148, 111), title, 26, True, minimum=19)
    if subtitle:
        text(page, (42, 116, 1148, 157), subtitle, 14, color=GREY, minimum=12)
    page.draw_line((42, 787), (1148, 787), color=(.80, .86, .87), width=.7)
    text(page, (42, 802, 980, 825), MARKER, 10, color=GREY, minimum=10)
    text(page, (1020, 802, 1148, 825), f'{number} / {total}', 10, color=GREY, align=2, minimum=10)
    return page


def notes(page, values, rect, title='Remember'):
    rect = pdf.Rect(rect)
    panel(page, rect, GOLD, (.84, .72, .43))
    if rect.height < 150:
        width = (rect.width-20) / len(values)
        for index, value in enumerate(values):
            x = rect.x0+10+index*width
            text(page, (x+7, rect.y0+9, x+width-7, rect.y1-6), value, 13, minimum=12)
        return
    text(page, (rect.x0+17, rect.y0+15, rect.x1-17, rect.y0+42), title, 15, True)
    y = rect.y0 + 53
    height = (rect.y1-y-10) / max(1, len(values))
    for value in values:
        text(page, (rect.x0+17, y, rect.x1-17, y+height-9), value, 14, minimum=12)
        y += height


def process_page(doc, item, number, total):
    page = header(doc, 'Process Flow', item['title'], number, total, item['summary'])
    steps = item['steps']
    gap = 18
    height = min(101, (584-gap*(len(steps)-1))/len(steps))
    y = 176
    for index, step in enumerate(steps, 1):
        panel(page, (42, y, 830, y+height))
        text(page, (58, y+13, 96, y+height-10), str(index), 24, True, TEAL)
        text(page, (110, y+11, 325, y+height-9), step['actor'], 15, True, TEAL)
        text(page, (345, y+11, 810, y+height-9), step['text'], 17, minimum=14)
        if index != len(steps):
            arrow(page, [(436, y+height), (436, y+height+gap)])
        y += height+gap
    notes(page, item['notes'], (858, 176, 1148, 760))


def flowchart_page(doc, item, number, total):
    page = header(doc, 'Flowchart', item['title'], number, total,
                  'Follow the arrows. At the question, choose the path that matches your situation.')
    for rect, label, fill in [
        ((268, 168, 750, 234), item['start'], PALE),
        ((268, 251, 750, 317), item['action'], PALE),
        ((268, 506, 750, 578), item['yes'], PALE),
        ((855, 355, 1148, 478), item['no'], GOLD),
        ((268, 609, 750, 677), item['result'], PALE),
    ]:
        panel(page, rect, fill)
        r = pdf.Rect(rect)
        text(page, r+(14, 10, -14, -7), label, 16, align=1, minimum=13)
    diamond = [(509, 345), (710, 410), (509, 475), (308, 410), (509, 345)]
    page.draw_polyline(diamond, color=TEAL, fill=GOLD, width=1)
    text(page, (396, 381, 622, 442), item['question'], 16, True, align=1, minimum=13)
    arrow(page, [(509, 234), (509, 251)])
    arrow(page, [(509, 317), (509, 345)])
    arrow(page, [(509, 475), (509, 506)], 'Yes', (524, 478, 569, 501))
    arrow(page, [(710, 410), (855, 410)], 'No', (753, 381, 805, 404))
    arrow(page, [(509, 578), (509, 609)])
    notes(page, item['notes'], (42, 701, 1148, 774), 'Keep in mind')


def guide_page(doc, item, number, total):
    page = header(doc, 'User Guide', item['title'], number, total, 'Who uses this: ' + item['audience'])
    y = 178
    height = min(103, 526/len(item['steps']))
    for index, value in enumerate(item['steps'], 1):
        panel(page, (42, y, 97, y+49), TEAL)
        text(page, (49, y+10, 90, y+43), str(index), 22, True, (1, 1, 1), align=1)
        text(page, (117, y+4, 808, y+height-13), value, 18, minimum=15)
        if index < len(item['steps']):
            page.draw_line((117, y+height-10), (808, y+height-10), color=(.86, .90, .91), width=.5)
        y += height
    panel(page, (847, 176, 1148, 371))
    text(page, (864, 194, 1130, 224), 'What happens next', 16, True, TEAL)
    text(page, (864, 239, 1130, 352), item['result'], 16, minimum=14)
    if item.get('notes'):
        notes(page, item['notes'], (847, 392, 1148, 759))
    text(page, (42, 731, 810, 769), 'Use your own account. Available actions depend on your permissions and document access.', 12, color=GREY)


def dfd_page(doc, item, number, total):
    page = header(doc, 'Data Flow Diagram', item['title'], number, total, item['summary'])
    if item.get('kind') == 'context':
        # External actors exchange named information with the single system.
        # Internal records belong on the following detail pages, not Level 0.
        panel(page, (435, 177, 755, 686), PALE)
        text(page, (455, 307, 735, 350), '0. PK DTS', 27, True, TEAL, align=1)
        text(page, (455, 367, 735, 501), 'Find and track documents\nManage requests and approvals\nRecord handoffs and history', 18, align=1)
        positions = [(42, 177, 308, 310), (42, 365, 308, 498), (42, 553, 308, 686),
                     (882, 235, 1148, 368), (882, 501, 1148, 634)]
        for actor, rect in zip(item['actors'], positions):
            panel(page, rect, GOLD, (.70, .62, .43))
            r = pdf.Rect(rect)
            text(page, r+(12, 13, -12, -72), actor['role'], 17, True, align=1)
            text(page, (r.x0+12, r.y0+64, r.x1-12, r.y1-9), actor['responsibility'], 13, align=1)
            cy = (r.y0+r.y1)/2
            if r.x0 < 400:
                left, right = r.x1, 435
                endy = cy
                arrow(page, [(left, cy-16), (right, endy-16)])
                arrow(page, [(right, endy+17), (left, cy+17)])
                text(page, (left+4, cy-60, right-4, cy-19), actor['sends'], 11, align=1, minimum=10)
                text(page, (left+4, cy+23, right-4, cy+66), actor['receives'], 11, align=1, minimum=10)
            else:
                left, right = 755, r.x0
                endy = cy
                arrow(page, [(right, cy-16), (left, endy-16)])
                arrow(page, [(left, endy+17), (right, cy+17)])
                text(page, (left+4, cy-60, right-4, cy-19), actor['sends'], 11, align=1, minimum=10)
                text(page, (left+4, cy+23, right-4, cy+66), actor['receives'], 11, align=1, minimum=10)
        text(page, (42, 723, 1148, 773), item['note'], 14)
        return
    rows = item['flows']
    for index, row in enumerate(rows):
        y = 181+index*108
        for x0, x1, field, kind in [(42, 286, 'from', row['from_kind']), (467, 735, 'process', 'process'), (918, 1148, 'to', row['to_kind'])]:
            if kind == 'store':
                # Open-ended record symbol; readable business record names.
                page.draw_polyline([(x1, y), (x0, y), (x0, y+78), (x1, y+78)], color=TEAL, width=1)
            else:
                panel(page, (x0, y, x1, y+78), PALE if kind == 'process' else GOLD)
            text(page, (x0+12, y+12, x1-12, y+69), row[field], 15, bold=kind == 'process', align=1, minimum=12)
        for x0, x1, field in [(286, 467, 'input'), (735, 918, 'output')]:
            arrow(page, [(x0, y+50), (x1, y+50)])
            text(page, (x0+5, y-3, x1-5, y+43), row[field], 12, align=1, minimum=11)
    text(page, (42, 735, 1148, 776), item['note'], 13, minimum=12)


def rgb(value):
    return '#' + ''.join(f'{round(v*255):02X}' for v in value)


def xml(element):
    return ET.tostring(element, encoding='utf-8', xml_declaration=True)


def cell(parent, name, value):
    return ET.SubElement(parent, '{'+NS+'}Cell', N=name, V=str(value))


def native_page(page):
    """Native vector geometry and editable text, matching every PDF page."""
    tag = lambda name: '{'+NS+'}'+name
    root = ET.Element(tag('PageContents'))
    shapes = ET.SubElement(root, tag('Shapes'))
    def shape(rect):
        r = pdf.Rect(rect)
        width, height = max(r.width, .01), max(r.height, .01)
        node = ET.SubElement(shapes, tag('Shape'), ID=str(len(shapes)+1), Type='Shape', LineStyle='0', FillStyle='0', TextStyle='0')
        for name, value in [('PinX', (r.x0+width/2)/72), ('PinY', (H-r.y0-height/2)/72), ('Width', width/72), ('Height', height/72), ('LocPinX', width/144), ('LocPinY', height/144), ('FillPattern', 0), ('LinePattern', 0)]:
            cell(node, name, value)
        return node
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
    parts = {'visio/document.xml': xml(document)}
    overrides = [('visio/document.xml', 'application/vnd.ms-visio.drawing.main+xml'), ('visio/pages/pages.xml', 'application/vnd.ms-visio.pages+xml')]
    for index, (page, item) in enumerate(zip(doc, items), 1):
        node = ET.SubElement(pages, tag('Page'), ID=str(index-1), NameU=f'Page-{index}', Name=plain(item['title']), IsCustomName='1')
        sheet = ET.SubElement(node, tag('PageSheet'), LineStyle='0', FillStyle='0', TextStyle='0')
        for name, value in [('PageWidth', W/72), ('PageHeight', H/72), ('PageScale', 1), ('DrawingScale', 1), ('PrintPageOrientation', 2)]:
            cell(sheet, name, value)
        ET.SubElement(node, tag('Rel'), {'{'+REL+'}id': f'rId{index}'})
        ET.SubElement(relationships, '{'+PKG+'}Relationship', Id=f'rId{index}', Type='http://schemas.microsoft.com/visio/2010/relationships/page', Target=f'page{index}.xml')
        name = f'visio/pages/page{index}.xml'
        parts[name] = native_page(page)
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
            items = entries(key, content[key])
            for index, item in enumerate(items, 1):
                renderers[key](doc, item, index, len(items))
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
        items = entries(key, content[key])
        with pdf.open(path) as doc:
            assert len(doc) == len(items), f'Page count mismatch: {path}'
            all_text = []
            for page, item in zip(doc, items):
                page_text = normalized(page.get_text())
                assert MARKER in page_text
                for value in source_strings(item):
                    assert normalized(value) in page_text, f'Missing or altered instructions in {path}, page {page.number+1}: {value}'
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
                native_text = ['\n'.join(s.text for s in slide.shapes if s.has_text_frame) for slide in prs.slides]
            else:
                with zipfile.ZipFile(editable) as archive:
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
            assert [normalized(t) for t in native_text] == all_text, f'Editable/PDF text mismatch: {base}'
        print(f'{base}: {len(items)} pages; all source instructions and editable text match; package links valid.')
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
