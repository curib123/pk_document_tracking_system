"""Build the Process Flow: an editable PowerPoint that explains the three document types, walks one
request from sign-in to final approval, and shows every scenario and approval route.

Run: python -X utf8 tools/documentation/build_process_flow.py
Output: docs/system-documentation/PK_DTS_Process_Flow.pptx

Facts (routes, results, return and reject outcomes) come from process.json so the deck
cannot drift from the Process Flow. Screens come from screenshots/ (guide screens plus
screenshots/workflow/, captured with PK_CAPTURE_ONLY=workflow-tasks). Sample users are
the synthetic people of the documentation capture site. The design follows the User Guide.
"""
from pathlib import Path
import json
import sys

import pymupdf
from lxml import etree
from pptx import Presentation
from pptx.dml.color import RGBColor
from pptx.enum.shapes import MSO_SHAPE, MSO_CONNECTOR
from pptx.enum.text import PP_ALIGN, MSO_ANCHOR, MSO_AUTO_SIZE
from pptx.oxml.ns import qn
from pptx.util import Pt

sys.path.insert(0, str(Path(__file__).resolve().parent))
from workflow_document_types import TYPES, SCENARIOS, APPLIES  # noqa: E402

SOURCE = Path(__file__).resolve().parent
ROOT = SOURCE.parents[1]
OUT = ROOT / 'docs/system-documentation/PK_DTS_Process_Flow.pptx'
SHOTS = SOURCE / 'screenshots'
ICONS = SOURCE / 'icons'
W, H = 1190, 842
DATE = '11 NOVEMBER 2026'

INK, ACCENT, PALE = '1C2B3D', '92284A', 'F6F7F9'
GREY, RULE, WHITE = '506377', 'DDE3E9', 'FFFFFF'
SUCCESS, SUCCESS_PALE, GOLD = '1A685B', 'EFF7F4', 'FFF8EA'

# Synthetic people of the documentation site (capture_screenshots.py).
PEOPLE = {
    'Requester': ('Sam Staff', 'Staff', 'icon-person.png'),
    "Requester's leader": ('Alex Staff', 'Staff, Sam\'s leader (Noted By)', 'icon-review.png'),
    'Plant Manager': ('Morgan Manager', 'Plant Manager', 'icon-review.png'),
    'Document Control Officer': ('Casey Controller', 'Document Control Officer', 'icon-review.png'),
    'Requester account': ('Sam Staff', 'Staff (Requester account)', 'icon-person.png'),
    'Administrator': ('Administrator', 'Any Administrator account', 'icon-review.png'),
    'Receiving user': ('Receiving user', 'Named on the transfer', 'icon-person.png'),
    'PK DTS': ('PK DTS', 'The system', 'icon-database.jpeg'),
}
FIRST = {'Requester': 'Sam', "Requester's leader": 'Alex', 'Plant Manager': 'Morgan',
         'Document Control Officer': 'Casey', 'Requester account': 'Sam', 'Requester': 'Sam'}


def rgb(value):
    return RGBColor.from_string(value)


# ----------------------------------------------------------------- measuring
def wrap_lines(value, size, bold, width):
    font = 'hebo' if bold else 'helv'
    total = 0
    for paragraph in str(value).split('\n'):
        current = ''
        lines = 1
        for word in paragraph.split():
            candidate = (current + ' ' + word).strip()
            if pymupdf.get_text_length(candidate, fontname=font, fontsize=size) > width:
                lines += 1
                current = word
            else:
                current = candidate
        total += lines
    return total


def fit_size(value, size, bold, width, height, minimum):
    minimum = min(minimum, size)
    while size >= minimum:
        if wrap_lines(value, size, bold, width) * size * 1.2 <= height + 1:
            return size
        size -= .5
    raise AssertionError('Text does not fit (%.0fx%.0f): %s' % (width, height, str(value)[:90]))


# ------------------------------------------------------------------ drawing
def strip_style(node):
    """Drop the theme style reference so no theme shadow or effect is applied."""
    style = node._element.find(qn('p:style'))
    if style is not None:
        node._element.remove(style)


_ICON_CACHE = {}


def icon_file(name):
    """The illustrations carry a white background; make it transparent for tinted cards."""
    if name not in _ICON_CACHE:
        from PIL import Image, ImageDraw
        import tempfile
        image = Image.open(ICONS / name).convert('RGBA')
        for corner in [(0, 0), (image.width - 1, 0), (0, image.height - 1), (image.width - 1, image.height - 1)]:
            if image.getpixel(corner)[:3] != (255, 255, 255) and min(image.getpixel(corner)[:3]) < 240:
                continue
            ImageDraw.floodfill(image, corner, (255, 255, 255, 0), thresh=24)
        path = Path(tempfile.mkdtemp(prefix='pk-dts-icons-')) / (Path(name).stem + '.png')
        image.save(path)
        _ICON_CACHE[name] = path
    return _ICON_CACHE[name]


class Slide:
    def __init__(self, prs):
        self.slide = prs.slides.add_slide(prs.slide_layouts[6])

    def rect(self, x, y, w, h, fill=PALE, line=RULE, width=.65, shape=MSO_SHAPE.RECTANGLE, adj=None):
        node = self.slide.shapes.add_shape(shape, Pt(x), Pt(y), Pt(w), Pt(h))
        node.shadow.inherit = False
        strip_style(node)
        if adj is not None:
            node.adjustments[0] = adj
        if fill:
            node.fill.solid()
            node.fill.fore_color.rgb = rgb(fill)
        else:
            node.fill.background()
        if line:
            node.line.color.rgb = rgb(line)
            node.line.width = Pt(width)
        else:
            node.line.fill.background()
        return node

    def pill(self, x, y, w, h, value, fill=PALE, color=INK, size=10, line=None):
        self.rect(x, y, w, h, fill, line, shape=MSO_SHAPE.ROUNDED_RECTANGLE, adj=.5)
        self.text(x, y + 1, w, h - 2, value, size, True, color, PP_ALIGN.CENTER, MSO_ANCHOR.MIDDLE, minimum=8)

    def text(self, x, y, w, h, value, size=14, bold=False, color=INK, align=PP_ALIGN.LEFT,
             anchor=MSO_ANCHOR.TOP, minimum=9.5):
        size = fit_size(value, size, bold, w, h, minimum)
        box = self.slide.shapes.add_textbox(Pt(x), Pt(y), Pt(w), Pt(h))
        frame = box.text_frame
        frame.margin_left = frame.margin_right = frame.margin_top = frame.margin_bottom = 0
        frame.word_wrap = True
        frame.auto_size = MSO_AUTO_SIZE.NONE
        frame.vertical_anchor = anchor
        for index, paragraph in enumerate(str(value).split('\n')):
            target = frame.paragraphs[0] if index == 0 else frame.add_paragraph()
            target.alignment = align
            run = target.add_run()
            run.text = paragraph
            run.font.name, run.font.size, run.font.bold = 'Arial', Pt(size), bold
            run.font.color.rgb = rgb(color)
        return box

    def line(self, x1, y1, x2, y2, color=ACCENT, width=1.2, arrow=False):
        node = self.slide.shapes.add_connector(MSO_CONNECTOR.STRAIGHT, Pt(x1), Pt(y1), Pt(x2), Pt(y2))
        strip_style(node)
        node.line.color.rgb = rgb(color)
        node.line.width = Pt(width)
        if arrow:
            tail = etree.SubElement(node.line._get_or_add_ln(), qn('a:tailEnd'))
            tail.set('type', 'triangle')
            tail.set('w', 'med')
            tail.set('len', 'med')
        return node

    def picture(self, path, x, y, w, h):
        return self.slide.shapes.add_picture(str(path), Pt(x), Pt(y), Pt(w), Pt(h))

    def icon(self, name, x, y, size):
        from PIL import Image
        path = icon_file(name)
        iw, ih = Image.open(path).size
        scale = min(size / iw, size / ih)
        w, h = iw * scale, ih * scale
        return self.picture(path, x + (size - w) / 2, y + (size - h) / 2, w, h)

    def notes(self, value):
        self.slide.notes_slide.notes_text_frame.text = value


def chrome(s, section, title, subtitle, number, total):
    s.rect(0, 0, W, 5, INK, None)
    s.rect(0, 0, 170, 5, ACCENT, None)
    s.text(42, 27, 400, 14, 'PK DTS  /  PROCESS FLOW', 10, True, ACCENT)
    s.text(640, 28, 508, 14, section.upper(), 9.5, True, GREY, PP_ALIGN.RIGHT)
    s.text(42, 60, 1106, 40, title, 28, True, INK, minimum=22)
    if subtitle:
        s.text(42, 104, 1106, 20, subtitle, 13, False, GREY)
    s.line(42, 790, 1148, 790, RULE, .75)
    s.text(42, 802, 600, 12, 'PK DTS  /  PROCESS FLOW  /  ' + DATE, 8, False, GREY)
    s.text(1050, 802, 98, 12, '%d / %d' % (number, total), 8, False, GREY, PP_ALIGN.RIGHT)


def person_card(s, x, y, w, h, actor, step_text, status, number=None, highlight=False):
    name, role, icon = PEOPLE[actor]
    s.rect(x, y, w, h, GOLD if highlight else PALE, RULE)
    s.icon(icon, x + 12, y + 12, 38)
    if number is not None:
        s.rect(x + w - 34, y + 12, 22, 22, ACCENT, ACCENT, shape=MSO_SHAPE.OVAL)
        s.text(x + w - 34, y + 15, 22, 16, str(number), 11, True, WHITE, PP_ALIGN.CENTER)
    s.text(x + 60, y + 10, w - 100, 20, name, 14, True, INK, minimum=11)
    s.text(x + 60, y + 31, w - 100, 28, role, 10.5, False, GREY, minimum=8.5)
    s.text(x + 14, y + 68, w - 28, h - 112, step_text, 12, False, INK, minimum=9.5)
    s.pill(x + 14, y + h - 32, w - 28, 22, status, WHITE, ACCENT, 10, RULE)


# ---------------------------------------------------------------- slide kinds
def cover(prs, number, total):
    s = Slide(prs)
    chrome(s, 'Process flow series', '', '', number, total)
    s.text(42, 128, 600, 16, 'PK DOCUMENT TRACKING SYSTEM', 11, True, GREY)
    s.rect(42, 168, 88, 3, ACCENT, None)
    s.text(42, 212, 700, 80, 'Process Flow', 58, True, INK, minimum=44)
    s.text(42, 338, 700, 34, 'One request, from sign-in to final approval', 24, True, INK)
    s.text(42, 394, 680, 60,
           'Learn what softcopy, hardcopy and external documents are, follow one request through every step '
           'with sample users, then see every scenario and approval route.', 16, False, INK)
    s.text(42, 612, 500, 14, 'WHAT YOU WILL SEE', 10.5, True, ACCENT)
    s.rect(776, 120, 372, 482, INK, None)
    s.text(800, 144, 300, 14, 'FIVE PARTS', 10, True, WHITE)
    parts = [('01', 'Meet the sample users and their roles'),
             ('02', 'Learn the three document types'),
             ('03', 'Follow one softcopy request from start to finish'),
             ('04', 'See every scenario of each document type'),
             ('05', 'See every request type and its approval route')]
    for i, (n, label) in enumerate(parts):
        y = 178 + i * 83
        s.text(800, y, 40, 18, n, 14, True, '8FD3C4')
        s.text(858, y - 2, 262, 56, label, 16, True, WHITE)
        if i < 4:
            s.line(800, y + 62, 1124, y + 62, '3B4A5E', .75)
    for i, (head, body) in enumerate([('Sign in', 'Start as the requester.'),
                                      ('Follow the route', 'Each approver acts in turn.'),
                                      ('Check the result', 'See the status and outcome.')]):
        x = 42 + i * 366
        s.rect(x, 640, 350, 100, PALE, None)
        s.text(x + 17, 658, 316, 22, head, 16, True, INK)
        s.text(x + 17, 696, 316, 30, body, 13, False, GREY)
    s.notes('Introduce the deck: it follows one softcopy request and then shows every request route.')


def users_slide(prs, number, total):
    s = Slide(prs)
    chrome(s, 'Part 1  /  Sample users', 'Meet the sample users',
           'Five sample accounts play the roles used in every route. Your own people and routes come from Workflow Builder.',
           number, total)
    cast = [
        ('Sam Staff', 'Staff', 'icon-person.png', 'REQUESTER', 'Signs in, creates the request, saves the draft and submits it.'),
        ('Alex Staff', "Staff  /  Sam's leader", 'icon-review.png', 'NOTED BY LEADER',
         'First reviewer ("Noted By"). Also confirms assignment requests as Sam\'s leader.'),
        ('Morgan Manager', 'Plant Manager', 'icon-review.png', 'PLANT MANAGER',
         'Second reviewer. In document requests, this approval locks the attached file.'),
        ('Casey Controller', 'Document Control Officer', 'icon-review.png', 'DOCUMENT CONTROL',
         'Final reviewer. Approval creates or updates the document.'),
        ('Avery Auditor', 'Internal Auditor', 'icon-person.png', 'READS ONLY',
         'Reads documents, history and the audit trail. Does not approve requests.'),
    ]
    cw, gap = 206, 19
    for i, (name, role, icon, tag, body) in enumerate(cast):
        x = 42 + i * (cw + gap)
        s.rect(x, 176, cw, 330, PALE, RULE)
        s.rect(x, 176, cw, 30, ACCENT if i == 0 else INK, None)
        s.text(x, 184, cw, 14, tag, 10, True, WHITE, PP_ALIGN.CENTER)
        s.icon(icon, x + (cw - 78) / 2, 224, 78)
        s.text(x + 14, 318, cw - 28, 22, name, 16, True, INK, PP_ALIGN.CENTER, minimum=12)
        s.text(x + 14, 344, cw - 28, 34, role, 11.5, False, GREY, PP_ALIGN.CENTER, minimum=9)
        s.line(x + 24, 388, x + cw - 24, 388, RULE, .75)
        s.text(x + 16, 400, cw - 32, 96, body, 12.5, False, INK, minimum=10)
    s.rect(42, 528, 1106, 230, GOLD, RULE)
    s.text(60, 544, 400, 14, 'HOW TO READ THIS PRESENTATION', 10.5, True, ACCENT)
    points = ['Every person signs in with their own account and sees only what their role allows.',
              'Only the person or role assigned to the current approval step can decide it. Administrator status gives no bypass.',
              'The default document route is: Requester, Requester\'s leader, Plant Manager, Document Control Officer.',
              'Other request types use Plant Manager, Document Control Officer, then a final confirmation by the requester or the requester\'s leader.']
    for i, point in enumerate(points):
        y = 574 + i * 44
        s.rect(60, y + 4, 8, 8, ACCENT, None)
        s.text(80, y, 1050, 40, point, 13.5, False, INK)
    s.notes('Sample users come from the documentation demonstration site. They are synthetic.')


def journey_slide(prs, number, total):
    s = Slide(prs)
    chrome(s, 'Part 3  /  One request start to finish', 'The journey at a glance',
           'A softcopy creation request: eight stations in order. The status shows where the request stands after each one.',
           number, total)
    stations = [
        ('Requester', 'Sign in', 'Sam signs in with their own account.', 'No request yet'),
        ('Requester', 'Start a request', 'My Requests, Softcopy Request, New Request.', 'No request yet'),
        ('Requester', 'Save a draft', 'Complete the form, attach the file, Save Draft.', 'Draft'),
        ('Requester', 'Submit', 'Check View Details, then Submit and Confirm.', 'Submitted'),
        ("Requester's leader", 'Noted By', 'Alex reviews and approves.', 'Submitted'),
        ('Plant Manager', 'Plant Manager review', 'Morgan approves. The file is locked.', 'Submitted  /  file locked'),
        ('Document Control Officer', 'Final review', 'Casey approves.', 'Completed'),
        ('Requester', 'Result', 'Active document and file at revision 00.', 'Completed'),
    ]
    cw, gap = 246, 40.7
    for i, (actor, head, body, status) in enumerate(stations):
        row, col = divmod(i, 4)
        x, y = 42 + col * (cw + gap), 168 + row * 300
        name, role, icon = PEOPLE[actor]
        final = i == 7
        s.rect(x, y, cw, 240, SUCCESS_PALE if final else PALE, SUCCESS if final else RULE)
        s.rect(x + 14, y + 14, 26, 26, ACCENT, ACCENT, shape=MSO_SHAPE.OVAL)
        s.text(x + 14, y + 19, 26, 16, str(i + 1), 12, True, WHITE, PP_ALIGN.CENTER)
        s.icon(icon, x + cw - 62, y + 10, 46)
        s.text(x + 14, y + 58, cw - 28, 22, head, 16, True, INK, minimum=12)
        s.text(x + 14, y + 84, cw - 28, 16, name, 12, True, ACCENT, minimum=10)
        s.text(x + 14, y + 104, cw - 28, 54, body, 12.5, False, INK, minimum=10)
        s.text(x + 14, y + 170, cw - 28, 12, 'REQUEST STATUS', 8.5, True, GREY)
        s.pill(x + 14, y + 190, cw - 28, 28, status, WHITE, SUCCESS if final else ACCENT, 11.5, RULE)
        if col < 3:
            s.line(x + cw + 4, y + 125, x + cw + gap - 4, y + 125, ACCENT, 1.6, arrow=True)
    # Linear continuation from the end of row one to the start of row two.
    s.line(42 + 3 * (cw + gap) + cw / 2, 408, 42 + 3 * (cw + gap) + cw / 2, 438, ACCENT, 1.6)
    s.line(42 + 3 * (cw + gap) + cw / 2, 438, 42 + cw / 2, 438, ACCENT, 1.6)
    s.line(42 + cw / 2, 438, 42 + cw / 2, 466, ACCENT, 1.6, arrow=True)
    s.rect(42, 724, 1106, 48, PALE, None)
    s.text(60, 738, 1070, 24,
           'If an approver returns the request it goes back one step (or to Sam at the first step). A rejection ends the route.',
           13, False, INK)
    s.notes('Walk the eight stations left to right, then continue on the second row.')


def screen_slide(prs, number, total, section, title, subtitle, actor, status, shot, caption, steps, after, keep, controls=None):
    s = Slide(prs)
    chrome(s, section, title, subtitle, number, total)
    name, role, icon = PEOPLE[actor]
    s.icon(icon, 42, 134, 36)
    s.text(88, 135, 520, 18, name + '  /  ' + role, 13, True, INK)
    s.text(88, 154, 520, 14, 'Acts in this station', 10, False, GREY)
    s.text(760, 140, 120, 14, 'REQUEST STATUS', 9, True, GREY, PP_ALIGN.RIGHT)
    s.pill(890, 134, 258, 28, status, GOLD, ACCENT, 12, RULE)
    # Screen panel
    s.rect(42, 176, 790, 440, PALE, RULE)
    s.text(58, 186, 758, 16, caption, 11, True, GREY)
    path, iw, ih, marks = shot
    scale = min(758 / iw, 392 / ih)
    w, h = iw * scale, ih * scale
    px, py = 58 + (758 - w) / 2, 210 + (392 - h) / 2
    s.rect(px - 1, py - 1, w + 2, h + 2, RULE, None)
    s.picture(path, px, py, w, h)
    for i, (label, x, y, cw, ch) in enumerate(marks):
        rx, ry, rw, rh = px + x * scale, py + y * scale, cw * scale, ch * scale
        s.rect(rx - 2, ry - 2, rw + 4, rh + 4, None, ACCENT, 1.5)
        bx = min(max(rx + rw / 2 - 9.5, px + 2), px + w - 21)
        by = ry - 24 if ry - 24 > py else ry + rh + 5
        s.rect(bx, by, 19, 19, ACCENT, ACCENT)
        s.text(bx, by + 3, 19, 14, 'ABC'[i], 10, True, WHITE, PP_ALIGN.CENTER)
        s.text(42 + i * 262, 624, 250, 16, 'ABC'[i] + '  ' + label, 11, True, ACCENT)
    # Steps
    s.rect(850, 176, 298, 604, PALE, RULE)
    s.text(868, 188, 262, 16, 'FOLLOW THESE STEPS', 11, True, ACCENT)
    block = (604 - 52) / len(steps)
    for i, step in enumerate(steps):
        y = 224 + i * block
        s.rect(868, y + 2, 26, 26, ACCENT, ACCENT)
        s.text(868, y + 8, 26, 16, str(i + 1), 11, True, WHITE, PP_ALIGN.CENTER)
        s.text(908, y, 224, block - 14, step, 14, False, INK, minimum=11)
        if i < len(steps) - 1:
            s.line(908, y + block - 8, 1130, y + block - 8, RULE, .6)
    # After / keep
    s.rect(42, 652, 250, 128, SUCCESS_PALE, None)
    s.text(58, 664, 220, 14, 'AFTER THIS', 10.5, True, SUCCESS)
    s.text(58, 686, 220, 88, after, 13, False, INK, minimum=10)
    s.rect(304, 652, 528, 128, PALE, None)
    s.text(320, 664, 480, 14, 'KEEP IN MIND', 10.5, True, ACCENT)
    s.text(320, 686, 496, 88, keep, 12.5, False, INK, minimum=10)
    s.notes(title + ': ' + ' '.join(steps))


def matrix_slide(prs, number, total, routes):
    s = Slide(prs)
    chrome(s, 'Part 5  /  Every request type', 'Every request on one page',
           'Default routes with the sample users. All eight request types pass three approvals before the result is applied.',
           number, total)
    cols = [('Request', 170), ('Requester', 118), ('Approval 1', 148), ('Approval 2', 148), ('Approval 3', 148), ('When the last approver approves', 374)]
    x0, y0 = 42, 172
    x = x0
    for head, width in cols:
        s.rect(x, y0, width, 30, INK, INK)
        s.text(x + 10, y0 + 8, width - 20, 14, head, 10.5, True, WHITE, minimum=8.5)
        x += width
    rh = 69
    for r, route in enumerate(routes):
        y = y0 + 30 + r * rh
        cells = [route['name']] + [None] * 4 + [route['result']]
        x = x0
        for c, (head, width) in enumerate(cols):
            fill = PALE if r % 2 == 0 else WHITE
            s.rect(x, y, width, rh, fill, RULE)
            if c == 0:
                s.text(x + 10, y + 10, width - 20, rh - 20, route['name'], 12, True, INK, PP_ALIGN.LEFT, MSO_ANCHOR.MIDDLE, 9.5)
            elif c == 5:
                s.text(x + 10, y + 6, width - 20, rh - 12, route['result'], 11, False, INK, PP_ALIGN.LEFT, MSO_ANCHOR.MIDDLE, 9)
            else:
                actor = route['actors'][c - 1]
                name, role, _ = PEOPLE[actor]
                s.text(x + 8, y + 10, width - 16, 18, name, 11.5, True, ACCENT if c > 1 else INK, minimum=9)
                label = 'Requester' if actor == 'Requester' else actor if actor != 'Requester account' else 'Requester account'
                s.text(x + 8, y + 31, width - 16, 30, label, 10, False, GREY, minimum=8.5)
            x += width
    s.notes('Each row is one request type; the names are the sample users.')


def route_slide(prs, number, total, index, route, source):
    s = Slide(prs)
    chrome(s, 'Part 5  /  Request %d of 8' % index, route['name'] + ' request', route['subtitle'], number, total)
    cards = list(zip(route['actors'], [st['text'] for st in source['steps']], route['statuses']))
    count = len(cards) + (1 if route.get('handoff') else 0)
    gap = 28
    cw = (1106 - gap * (count - 1)) / count
    for i, (actor, text, status) in enumerate(cards):
        x = 42 + i * (cw + gap)
        person_card(s, x, 142, cw, 168, actor, text, status, i + 1, highlight=(i == 0))
        if i < count - 1:
            s.line(x + cw + 3, 226, x + cw + gap - 3, 226, ACCENT, 1.6, arrow=True)
    if route.get('handoff'):
        x = 42 + len(cards) * (cw + gap)
        s.rect(x, 142, cw, 168, PALE, RULE)
        s.icon('icon-person.png', x + 12, 154, 38)
        s.rect(x + cw - 34, 154, 22, 22, ACCENT, ACCENT, shape=MSO_SHAPE.OVAL)
        s.text(x + cw - 34, 157, 22, 16, str(count), 11, True, WHITE, PP_ALIGN.CENTER)
        s.text(x + 60, 152, cw - 100, 20, 'Handoff', 14, True, INK)
        s.text(x + 60, 173, cw - 72, 28, 'Sam Staff and the named receiver', 10.5, False, GREY, minimum=8.5)
        s.text(x + 14, 210, cw - 28, 58, route['handoff'], 11.5, False, INK, minimum=9)
        s.pill(x + 14, 278, cw - 28, 22, route['handoff_status'], WHITE, SUCCESS, 10, RULE)
    # Result bar
    s.rect(42, 330, 1106, 62, SUCCESS_PALE, SUCCESS)
    s.text(58, 340, 300, 14, 'WHEN THE LAST APPROVER APPROVES', 10, True, SUCCESS)
    s.text(58, 358, 1072, 30, source['paths'][0]['cases'][-1]['next'], 15, True, INK, minimum=11)
    # Approve / Return / Reject
    heads = [('Approve', SUCCESS), ('Return', ACCENT), ('Reject', INK)]
    pw, pg = 350, 28
    for i, ((label, color), path) in enumerate(zip(heads, source['paths'])):
        x = 42 + i * (pw + pg)
        s.rect(x, 412, pw, 30, color, None)
        s.text(x, 420, pw, 16, label, 13, True, WHITE, PP_ALIGN.CENTER)
        s.rect(x, 442, pw, 268, PALE, RULE)
        for j, case in enumerate(path['cases']):
            y = 452 + j * 86
            when = case['when']
            for role, first in FIRST.items():
                when = when.replace(role, '%s (%s)' % (first, role), 1) if role in when and role != 'Requester' and role != 'Requester account' else when
            if when.startswith('Requester ') and ('approves' in when or 'returns' in when):
                when = when.replace('Requester', 'Sam (Requester)', 1)
            s.text(x + 14, y, pw - 28, 28, when, 12, True, color if color != INK else INK, minimum=9.5)
            s.text(x + 14, y + 30, pw - 28, 50, case['next'], 11.5, False, INK, minimum=9.5)
            if j < 2:
                s.line(x + 14, y + 80, x + pw - 14, y + 80, RULE, .6)
    s.rect(42, 724, 1106, 50, PALE, None)
    s.text(58, 734, 1074, 32, '  '.join(source['notes']), 11.5, False, INK, minimum=9)
    s.notes(route['name'] + ': ' + route['subtitle'])


def remember_slide(prs, number, total):
    s = Slide(prs)
    chrome(s, 'Summary', 'Keep these four rules in mind', 'They apply to every request type and every sample user.', number, total)
    panels = [
        ('Status shows the stage',
         'Draft: only the requester can edit.\nSubmitted: in the approval route.\nReturned: back to the requester.\nCompleted or Approved: applied.\nRejected: closed, cannot be resubmitted.'),
        ('Only the assigned approver decides',
         'The current step names one person or role. Each reviewer chooses Approve, Return or Reject and may add a remark. Administrator status gives no bypass.'),
        ('A return goes back one step',
         'At the first step it goes to the requester. At a later step it goes to the previous approver and the status stays Submitted. The original route is kept.'),
        ('Approval can lock the file',
         'In softcopy and external requests, the approval at the document-locking step locks the attachment. A Locked badge marks that step in the timeline.'),
    ]
    for i, (head, body) in enumerate(panels):
        x, y = 42 + (i % 2) * 561, 172 + (i // 2) * 290
        s.rect(x, y, 545, 270, PALE, RULE)
        s.rect(x, y, 6, 270, ACCENT, None)
        s.text(x + 28, y + 22, 490, 24, head, 19, True, INK, minimum=15)
        s.text(x + 28, y + 66, 490, 190, body, 18, False, INK, minimum=12)
    s.rect(42, 756, 1106, 26, WHITE, None)
    s.text(42, 760, 1106, 18, 'Check View Details at any time: Document File, Approval Process Timeline and Workflow Activity Log.',
           12, False, GREY)
    s.notes('Close with the four rules.')


def types_overview_slide(prs, number, total):
    s = Slide(prs)
    chrome(s, 'Part 2  /  Document types', 'Three kinds of document, three different jobs',
           'Each register answers a different question. Pick the register that matches what you need.', number, total)
    cols = [('Softcopy', 'TEMPLATE REPOSITORY', ACCENT), ('Hardcopy', 'LOCATION TRACKER', INK), ('External', 'SCANNED BACKUP REPOSITORY', SUCCESS)]
    rows = [
        ('In one line', ['The repository of approved softcopy templates.',
                         'A tracker of where each physical document is kept.',
                         'A repository of scanned outside documents, kept only as backup.']),
        ('What it stores', ['The approved electronic file, its document number and its revisions.',
                            'No file. Only the record: place, Current Holder and optional retention dates.',
                            'Attached scans and files (up to 20 per record) with a category and description.']),
        ('Sample documents', ['Incident Report Form Template, Inspection Checklist Template',
                              'Sample Inspection Record in Records Cabinet A',
                              'Supplier Quality Certificate, Signed Delivery Receipt']),
        ('How it is used', ['Staff open the approved template and use it to prepare their own document.',
                            'Staff look up where the paper is and who holds it, then go and get it.',
                            'Kept as a backup copy of a document that came from outside. Not a template.']),
        ('How it gets in', ['An approved request, or a direct save by a controller.',
                            'Registered directly. Moving the paper needs an approved transfer.',
                            'An approved request, or a direct save by a controller.']),
    ]
    lw, cw = 150, (1106 - 150) / 3
    y0, hh, rh = 142, 62, 104
    for c, (name, tag, color) in enumerate(cols):
        x = 42 + lw + c * cw
        s.rect(x, y0, cw, hh, color, color)
        s.text(x + 16, y0 + 10, cw - 32, 24, name, 20, True, WHITE)
        s.text(x + 16, y0 + 38, cw - 32, 14, tag, 9.5, True, 'F4DCE5' if color == ACCENT else 'D4DDE6' if color == INK else 'CFE8E1')
    for r, (label, cells) in enumerate(rows):
        y = y0 + hh + r * rh
        s.rect(42, y, lw, rh, PALE, RULE)
        s.text(56, y + 14, lw - 24, rh - 28, label, 12.5, True, ACCENT, PP_ALIGN.LEFT, MSO_ANCHOR.MIDDLE, 10)
        for c, value in enumerate(cells):
            x = 42 + lw + c * cw
            s.rect(x, y, cw, rh, WHITE if r % 2 == 0 else PALE, RULE)
            s.text(x + 16, y + 12, cw - 32, rh - 24, value, 13.5 if r == 0 else 12.5, r == 0, INK, PP_ALIGN.LEFT, MSO_ANCHOR.MIDDLE, 10)
    s.notes('Softcopy is the template repository. Hardcopy only tracks where the paper is. External keeps scanned outside documents as backup.')


def type_slide(prs, number, total, kind):
    t = TYPES[kind]
    scenarios = SCENARIOS[kind]
    s = Slide(prs)
    chrome(s, 'Part 2  /  Document types', t['title'], t['subtitle'], number, total)
    s.rect(42, 140, 530, 412, PALE, RULE)
    s.rect(42, 140, 6, 412, ACCENT, None)
    s.pill(66, 158, t['tag_width'], 24, t['tag'], ACCENT, WHITE, 10)
    s.text(66, 198, 486, 14, 'WHAT IT IS', 10.5, True, ACCENT)
    s.text(66, 218, 486, 124, t['definition'], 15, False, INK, minimum=11)
    s.text(66, 356, 486, 14, 'WHAT IT HOLDS', 10.5, True, ACCENT)
    for i, item in enumerate(t['holds']):
        y = 378 + i * 41
        s.rect(66, y + 5, 8, 8, ACCENT, None)
        s.text(86, y, 466, 38, item, 12.5, False, INK, minimum=10)
    s.text(594, 140, 554, 14, 'SAMPLE DOCUMENTS  (ILLUSTRATIVE)', 10.5, True, ACCENT)
    for i, (title, meta, pill) in enumerate(t['samples']):
        y = 164 + i * 97
        s.rect(594, y, 554, 85, WHITE, RULE)
        s.rect(610, y + 16, 40, 53, PALE, RULE)
        for j in range(4):
            s.rect(617, y + 26 + j * 9, 26 if j < 3 else 15, 2.5, RULE, None)
        s.text(668, y + 14, 380, 20, title, 14, True, INK, minimum=11)
        s.text(668, y + 38, 380, 34, meta, 11, False, GREY, minimum=9)
        s.pill(1066, y + 14, 66, 22, pill, PALE, ACCENT, 10, RULE)
    s.text(42, 570, 700, 14, 'SCENARIOS COVERED IN PART 4  (%d)' % len(scenarios), 10.5, True, ACCENT)
    cw, gap = 359, 14.5
    for i, sc in enumerate(scenarios):
        row, col = divmod(i, 3)
        x, y = 42 + col * (cw + gap), 594 + row * 90
        s.rect(x, y, cw, 78, PALE, RULE)
        s.rect(x + 14, y + 26, 26, 26, ACCENT, ACCENT, shape=MSO_SHAPE.OVAL)
        s.text(x + 14, y + 31, 26, 16, str(i + 1), 12, True, WHITE, PP_ALIGN.CENTER)
        s.text(x + 54, y + 14, cw - 68, 22, sc['name'], 14, True, INK, minimum=11)
        s.text(x + 54, y + 42, cw - 68, 28, ('Approval route' if sc['kind'] == 'Request' else 'Direct action, no route') +
               '  /  %d steps' % len(sc['steps']), 11, False, GREY, minimum=9)
    s.notes(t['title'] + ': ' + t['definition'])


def applies_slide(prs, number, total):
    s = Slide(prs)
    chrome(s, 'Part 2  /  Document types', 'What you can do with each type',
           'Not every action exists for every type. Part 4 shows each action that applies, step by step.', number, total)
    cols = [('Action', 250, INK), ('Softcopy', 285, ACCENT), ('Hardcopy', 285, INK), ('External', 286, SUCCESS)]
    x = 42
    for head, width, color in cols:
        s.rect(x, 142, width, 34, color, color)
        s.text(x + 14, 151, width - 28, 16, head, 12, True, WHITE)
        x += width
    rh = 66
    for r, row in enumerate(APPLIES):
        y = 176 + r * rh
        x = 42
        for c, ((head, width, color), value) in enumerate(zip(cols, row)):
            s.rect(x, y, width, rh, PALE if r % 2 == 0 else WHITE, RULE)
            no = value.startswith('No') or value.startswith('Not')
            s.text(x + 14, y + 8, width - 28, rh - 16, value, 12.5 if c else 13, c == 0, GREY if no else INK,
                   PP_ALIGN.LEFT, MSO_ANCHOR.MIDDLE, 10)
            x += width
    s.rect(42, 716, 1106, 58, GOLD, RULE)
    s.text(58, 728, 1074, 36, 'Hardcopy has no create or revise request: register or edit it directly, and use a Hardcopy Transfer Request to move the paper. '
           'Softcopy disposal is direct only.', 12.5, False, INK, minimum=10)
    s.notes('Which actions exist for each document type.')


def scenario_slide(prs, number, total, kind, index, sc):
    s = Slide(prs)
    count = len(SCENARIOS[kind])
    chrome(s, 'Part 4  /  %s scenario %d of %d' % (TYPES[kind]['short'], index, count), sc['title'], sc['subtitle'], number, total)
    # Sample strip
    doc, facts = sc['sample']
    s.rect(42, 136, 1106, 64, GOLD, RULE)
    s.text(58, 146, 300, 12, 'SAMPLE DOCUMENT', 8.5, True, ACCENT)
    s.text(58, 163, 300, 26, doc, 15, True, INK, PP_ALIGN.LEFT, MSO_ANCHOR.TOP, 11)
    fw = (1148 - 380 - 12) / 4
    for i, (label, value) in enumerate(facts):
        x = 380 + i * fw
        s.line(x - 10, 146, x - 10, 190, RULE, .75)
        s.text(x, 146, fw - 12, 12, label.upper(), 8.5, True, GREY)
        s.text(x, 162, fw - 12, 30, value, 12, True, INK, minimum=9)
    # Linear flow
    steps = sc['steps']
    n = len(steps)
    cols = 3 if n <= 6 else 4
    gap = 40.7 if cols == 4 else 40
    cw = (1106 - gap * (cols - 1)) / cols
    ch, y0, rgap = 204, 216, 36
    rows = -(-n // cols)
    for i, (actor, head, body, status) in enumerate(steps):
        row, col = divmod(i, cols)
        x, y = 42 + col * (cw + gap), y0 + row * (ch + rgap)
        name, role, icon = PEOPLE[actor]
        final = i == n - 1
        s.rect(x, y, cw, ch, SUCCESS_PALE if final else PALE, SUCCESS if final else RULE)
        s.rect(x + 14, y + 14, 26, 26, ACCENT, ACCENT, shape=MSO_SHAPE.OVAL)
        s.text(x + 14, y + 19, 26, 16, str(i + 1), 12, True, WHITE, PP_ALIGN.CENTER)
        s.icon(icon, x + cw - 58, y + 8, 44)
        s.text(x + 14, y + 50, cw - 28, 20, head, 15, True, INK, minimum=11)
        s.text(x + 14, y + 73, cw - 28, 14, name, 11.5, True, ACCENT, minimum=10)
        s.text(x + 14, y + 92, cw - 28, 68, body, 12, False, INK, minimum=9.5)
        s.pill(x + 14, y + ch - 36, cw - 28, 24, status, WHITE, SUCCESS if final else ACCENT, 10.5, RULE)
        if col < cols - 1 and i < n - 1:
            s.line(x + cw + 4, y + ch / 2, x + cw + gap - 4, y + ch / 2, ACCENT, 1.6, arrow=True)
    if rows == 2:
        lx = 42 + (cols - 1) * (cw + gap) + cw / 2
        ymid = y0 + ch + rgap / 2
        s.line(lx, y0 + ch, lx, ymid, ACCENT, 1.6)
        s.line(lx, ymid, 42 + cw / 2, ymid, ACCENT, 1.6)
        s.line(42 + cw / 2, ymid, 42 + cw / 2, y0 + ch + rgap, ACCENT, 1.6, arrow=True)
    # Result / middle / keep panels
    py, ph, pw, pg = 676, 102, 358, 16
    panels = [(sc['result'], SUCCESS_PALE, SUCCESS), (sc['middle'], PALE, ACCENT), (sc['keep'], PALE, INK)]
    for i, ((label, text), fill, color) in enumerate(panels):
        x = 42 + i * (pw + pg)
        s.rect(x, py, pw, ph, fill, SUCCESS if i == 0 else RULE)
        s.rect(x, py, 5, ph, color, None)
        s.text(x + 18, py + 11, pw - 34, 12, label, 9.5, True, color)
        s.text(x + 18, py + 29, pw - 34, ph - 38, text, 12, False, INK, minimum=9.5)
    s.notes(sc['title'] + ': ' + ' '.join('%d. %s - %s' % (i + 1, st[1], st[2]) for i, st in enumerate(steps)))


# ------------------------------------------------------------------- content
def screen(name, folder=None, labels=None, extra=()):
    base = SHOTS / (folder or '')
    manifest = json.loads((base / 'manifest.json').read_text(encoding='utf-8'))
    item = manifest[name]
    controls = item['controls']
    if isinstance(controls, dict):
        controls = [dict(label=k, **v) for k, v in controls.items()]
    marks = []
    for label in labels or [c['label'] for c in controls][:3]:
        match = next(c for c in controls if c['label'] == label)
        marks.append((label, match['x'], match['y'], match['width'], match['height']))
    marks.extend(extra)
    return (base / item['file'], item['width'], item['height'], marks)


ROUTES = {
    'approval-return-reject': dict(
        name='Softcopy creation', subtitle='Sam asks for a new controlled document. Three reviewers approve it in turn.',
        actors=['Requester', "Requester's leader", 'Plant Manager', 'Document Control Officer'],
        statuses=['Draft, then Submitted', 'Submitted', 'Submitted, file locked', 'Completed']),
    'approval-softcopy-revise': dict(
        name='Softcopy revision', subtitle='Sam proposes a replacement file for an Active document. The document number is kept.',
        actors=['Requester', "Requester's leader", 'Plant Manager', 'Document Control Officer'],
        statuses=['Draft, then Submitted', 'Submitted', 'Submitted, file locked', 'Completed']),
    'approval-external-create': dict(
        name='External document creation', subtitle='Sam registers a document received from outside the organization.',
        actors=['Requester', "Requester's leader", 'Plant Manager', 'Document Control Officer'],
        statuses=['Draft, then Submitted', 'Submitted', 'Submitted, file locked', 'Completed']),
    'approval-external-revise': dict(
        name='External document revision', subtitle='Sam proposes replacement attachments for an Active external document.',
        actors=['Requester', "Requester's leader", 'Plant Manager', 'Document Control Officer'],
        statuses=['Draft, then Submitted', 'Submitted', 'Submitted, file locked', 'Completed']),
    'approval-transfer': dict(
        name='Hardcopy transfer', subtitle='Sam asks to move a paper document. Approval prepares the handoff; Dispatch and Accept finish it.',
        actors=['Requester', 'Plant Manager', 'Document Control Officer', 'Requester account'],
        statuses=['Draft, then Submitted', 'Submitted', 'Submitted', 'Approved, For Transfer'],
        handoff='Sam dispatches the document. The named receiver accepts it. Holder and location change after Accept.',
        handoff_status='In Transit, then Accepted'),
    'approval-access': dict(
        name='Document access', subtitle='Sam asks for temporary access to a document. Sam gives the final confirmation.',
        actors=['Requester', 'Plant Manager', 'Document Control Officer', 'Requester account'],
        statuses=['Draft, then Submitted', 'Submitted', 'Submitted', 'Completed']),
    'approval-assignment': dict(
        name='Document assignment', subtitle="Sam asks to be assigned a document. Sam's leader gives the final approval.",
        actors=['Requester', 'Plant Manager', 'Document Control Officer', "Requester's leader"],
        statuses=['Draft, then Submitted', 'Submitted', 'Submitted', 'Completed']),
    'approval-disposal': dict(
        name='Disposal', subtitle='Sam asks to dispose of a document. Sam gives the final confirmation.',
        actors=['Requester', 'Plant Manager', 'Document Control Officer', 'Requester account'],
        statuses=['Draft, then Submitted', 'Submitted', 'Submitted', 'Completed']),
}


def main():
    process = json.loads((SOURCE / 'process.json').read_text(encoding='utf-8'))
    examples = {p['id']: p for p in process['pages'] if p.get('kind') == 'approval-example'}
    assert set(ROUTES) == set(examples), 'Every approval example needs a route slide'
    for key, route in ROUTES.items():
        source = examples[key]
        assert [step['actor'] for step in source['steps']] == route['actors'], key
        route['result'] = source['paths'][0]['cases'][-1]['next']

    section = 'Part 3  /  One request start to finish'
    stations = [
        dict(title='Station 1: sign in as the requester', subtitle='Sam Staff starts with their own account.',
             actor='Requester', status='No request yet', shot=screen('sign-in', labels=['Username', 'Password', 'Sign In']),
             caption='Sign in using your own account.',
             steps=['Open PK DTS in the browser.', 'Enter Sam\'s Username and Password.', 'Select Sign In. Replace a temporary password first if asked.',
                    'The Overview page opens with totals and recent activity.'],
             after='Sam is signed in as a Staff requester. The menus show only what the Staff role may use.',
             keep='Each person uses their own account. If a menu or action is missing, the role does not have that permission.'),
        dict(title='Station 2: start a new request', subtitle='Sam opens My Requests and chooses the Softcopy Request tab.',
             actor='Requester', status='No request yet', shot=screen('request-lifecycle', labels=['New Request']),
             caption='My Requests lists your requests. Select New Request to start another.',
             steps=['Under Document Requests, open My Requests.', 'Choose the Softcopy Request tab.', 'Select New Request.',
                    'The request form opens.'],
             after='A blank New Request form is ready. Nothing is saved until Sam selects Save Draft.',
             keep='Saving a draft does not change any document, holder, access or status. Only Submit sends it for approval.'),
        dict(title='Station 3: complete the form and save a draft', subtitle='Sam describes the document and attaches the file.',
             actor='Requester', status='Draft', shot=screen('softcopy-request-create', labels=['Action', 'Document title', 'Save']),
             caption='Prepare a softcopy create request.',
             steps=['Choose Create Softcopy as the action.', 'Enter Subject / Reason and Proposed Document Title, then choose Category.',
                    'Check Generated Document Number. Attach the Document File (up to 15 MB).',
                    'Add optional Remarks. Select Save Draft and Confirm.'],
             after='The request is saved as a Draft with its own reference. Only Sam can edit it.',
             keep='The final document number is assigned at final approval and may differ from the preview.'),
        dict(title='Station 4: check the draft and submit', subtitle='Sam previews the proposal and sends it into the approval route.',
             actor='Requester', status='Draft  >  Submitted', shot=screen('review-file', labels=['Document File', 'Fullscreen', 'Download']),
             caption='View Details shows the proposal. The Document File tab previews the attachment.',
             steps=['In My Requests, select View on the draft.', 'Open the Document File tab and check the preview. Use Fullscreen to enlarge it.',
                    'Close View Details. Select Submit, then Continue and Confirm.', 'The status changes to Submitted and the first approver is notified.'],
             after='The request is Submitted. Sam can no longer edit or cancel it, but can follow it in My Requests.',
             keep='The route is fixed at submission. If the request is returned and resubmitted, it keeps the original route.'),
        dict(title="Station 5: the requester's leader reviews (Noted By)", subtitle='Alex sees the task in My Tasks and decides.',
             actor="Requester's leader", status='Submitted', shot=screen('my-tasks', folder='workflow', labels=['Approve', 'Reject', 'Return']),
             caption='My Tasks shows the request assigned to Alex. Each row offers View, Approve, Reject and Return.',
             steps=['Open My Tasks and the Softcopy Request tab. A badge counts waiting tasks.', 'Select View. Check the proposal, the file preview and the timeline.',
                    'Choose Approve, Return or Reject. Add an optional remark.', 'Select Continue and Confirm.'],
             after='Alex\'s approval moves the request to the Plant Manager. The status stays Submitted.',
             keep='Only the assigned user or role can decide. A Return sends the request back to Sam to correct and resubmit.'),
        dict(title='Station 6: the Plant Manager approves and the file locks', subtitle='Morgan reviews next. In this route, this approval locks the attachment.',
             actor='Plant Manager', status='Submitted, file locked', shot=screen('approval-routes', labels=['Approval Process Timeline'],
                         extra=[('Locks file on approval badge', 915, 569, 157, 28)]),
             caption='The Approval Process Timeline shows each step. A badge marks the step that locks the file.',
             steps=['Morgan opens My Tasks, then View on the request.', 'Open Approval Process Timeline. The step shows Locks file on approval.',
                    'Choose Approve, add a remark and Confirm.', 'The step shows Locked. The next reviewer is notified.'],
             after='The attached file is locked as read-only. It can still be previewed; downloads are limited to roles with lock access.',
             keep='A Return here goes back to Alex, the requester\'s leader. The status stays Submitted.'),
        dict(title='Station 7: Document Control gives the final approval', subtitle='Casey approves. The system applies the change.',
             actor='Document Control Officer', status='Completed', shot=screen('files', labels=['Fullscreen', 'Download']),
             caption='The new Active document opens with its approved file on the Document Files tab.',
             steps=['Casey opens My Tasks, then View on the request.', 'Check the proposal, approval history and file preview.',
                    'Choose Approve and Confirm.', 'The Active record and file are created at revision 00.'],
             after='The request is Completed. The document appears in Softcopy Documents with its number and file.',
             keep='Sam can check the Workflow Activity Log for every decision, remark and time. A rejection would have closed the request.'),
    ]
    routes = [dict(name=ROUTES[k]['name'], actors=ROUTES[k]['actors'], result=ROUTES[k]['result']) for k in ROUTES]

    scenario_plan = [(kind, i + 1, sc) for kind in ('softcopy', 'hardcopy', 'external') for i, sc in enumerate(SCENARIOS[kind])]
    plan = (['cover', 'users', 'types', 'type-softcopy', 'type-hardcopy', 'type-external', 'applies', 'journey']
            + ['station'] * len(stations) + ['scenario'] * len(scenario_plan)
            + ['matrix'] + ['route'] * len(ROUTES) + ['remember'])
    total = len(plan)
    prs = Presentation()
    prs.slide_width, prs.slide_height = Pt(W), Pt(H)
    prs.core_properties.title = 'PK DTS - Process Flow'
    prs.core_properties.subject = 'Document types, one request from sign-in to final approval, and every scenario, 11 November 2026'
    prs.core_properties.author = 'PK Document Tracking System'
    route_keys = list(ROUTES)
    number = 0
    for kind in plan:
        number += 1
        if kind == 'cover':
            cover(prs, number, total)
        elif kind == 'users':
            users_slide(prs, number, total)
        elif kind == 'types':
            types_overview_slide(prs, number, total)
        elif kind.startswith('type-'):
            type_slide(prs, number, total, kind[5:])
        elif kind == 'applies':
            applies_slide(prs, number, total)
        elif kind == 'scenario':
            scenario_slide(prs, number, total, *scenario_plan.pop(0))
        elif kind == 'journey':
            journey_slide(prs, number, total)
        elif kind == 'station':
            station = stations.pop(0)
            screen_slide(prs, number, total, section, station['title'], station['subtitle'], station['actor'],
                         station['status'], station['shot'], station['caption'], station['steps'],
                         station['after'], station['keep'])
        elif kind == 'matrix':
            matrix_slide(prs, number, total, routes)
        elif kind == 'route':
            key = route_keys.pop(0)
            route_slide(prs, number, total, len(ROUTES) - len(route_keys), ROUTES[key], examples[key])
        elif kind == 'remember':
            remember_slide(prs, number, total)
    OUT.parent.mkdir(parents=True, exist_ok=True)
    prs.save(OUT)
    print('Saved %s (%d slides)' % (OUT, len(prs.slides)))


if __name__ == '__main__':
    main()
