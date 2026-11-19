"""Build the Step-by-Step User Guide PowerPoint from the current application screens.

Run (after capture_guide.py): python -X utf8 tools/documentation/build_user_guide.py
Inputs:  user_guide_content.py                  sections, tasks and steps (the words)
         screenshots/guide/manifest.json        screens, callout boxes and captured UI text
         templates/user_guide_template.pptx     theme, master and layouts (no slides)
Output:  docs/system-documentation/PK_DCS_User_Guide_Step_by_Step.pptx (and the copy in docs/)

The build stops when a bold UI label in the guide is not on its screenshot, when a numbered
instruction has no marker, or when text does not fit its box (measured with the Calibri fonts).
"""
from copy import deepcopy
from datetime import date
import json
from pathlib import Path
import re
import shutil
import sys

from lxml import etree
from PIL import ImageFont
from pptx import Presentation
from pptx.dml.color import RGBColor
from pptx.enum.shapes import MSO_CONNECTOR, MSO_SHAPE
from pptx.enum.text import MSO_ANCHOR, MSO_AUTO_SIZE, PP_ALIGN
from pptx.oxml.ns import qn
from pptx.util import Pt

HERE = Path(__file__).resolve().parent
ROOT = HERE.parents[1]
sys.path.insert(0, str(HERE))
import user_guide_content as content  # noqa: E402

SHOTS = HERE / 'screenshots' / 'guide'
TEMPLATE = HERE / 'templates' / 'user_guide_template.pptx'
OUTPUTS = [ROOT / 'docs/system-documentation/PK_DCS_User_Guide_Step_by_Step.pptx',
           ROOT / 'docs/PK_DCS_User_Guide_Step_by_Step.pptx']
EDITION = date.today().strftime('%d %B %Y').lstrip('0')

INK, RUBY, MAROON, GREY = '241B22', '99062F', '590018', '635361'
PALE, LINE, PINK, ROSE = 'F6F2F4', 'E6DDE3', 'F1A6B6', 'FFF0F3'
BOXES = {'next': ('WHAT HAPPENS NEXT', 'E8F3EF', '1A685B'), 'keep': ('KEEP IN MIND', 'FDF3E1', '7A4F00'),
         'good': ('GOOD TO KNOW', 'EAF1F8', '2E5E8C'), 'important': ('IMPORTANT', 'FDEBEE', RUBY)}
FONT_DIR = Path(r'C:\Windows\Fonts')
_fonts = {}


# ---------------------------------------------------------------------------- text measuring
def font(size, bold=False, italic=False):
    key = (round(size * 10), bold, italic)
    if key not in _fonts:
        name = 'calibri' + ('b' if bold else '') + ('i' if italic else '') + '.ttf'
        name = {'calibribi.ttf': 'calibriz.ttf'}.get(name, name)
        _fonts[key] = ImageFont.truetype(str(FONT_DIR / name), key[0])
    return _fonts[key]


def segments(text):
    """'plain **bold** plain' -> [(text, bold)]."""
    return [(part[2:-2], True) if part.startswith('**') else (part, False)
            for part in re.split(r'(\*\*.+?\*\*)', text) if part]


def width_of(text, size, bold=False, italic=False):
    return font(size, bold, italic).getlength(text) / 10


def wrap(text, size, width, bold=False, italic=False):
    """Number of lines PowerPoint needs for rich text in a box of this width (pt)."""
    lines = 0
    for paragraph in text.split('\n'):
        words = []
        for value, strong in segments(paragraph):
            for word in re.findall(r'\S+\s*', value):
                words.append((word, strong or bold))
        lines += 1
        used = 0.0
        for word, strong in words:
            length = width_of(word.rstrip(), size, strong, italic)
            space = width_of(' ', size, strong, italic) if word.endswith(' ') else 0
            if used and used + length > width:
                lines += 1
                used = 0.0
            used += length + space
    return lines


def text_height(text, size, width, bold=False, italic=False, spacing=1.2):
    return wrap(text, size, width, bold, italic) * size * spacing


# ---------------------------------------------------------------------------- drawing helpers
def rgb(value):
    return RGBColor.from_string(value)


def box(slide, x, y, w, h, fill=None, line=None, shape=MSO_SHAPE.RECTANGLE, radius=None, weight=0.75):
    item = slide.shapes.add_shape(shape, Pt(x), Pt(y), Pt(w), Pt(h))
    item.shadow.inherit = False
    if radius is not None:
        item.adjustments[0] = radius
    if fill:
        item.fill.solid()
        item.fill.fore_color.rgb = rgb(fill)
    else:
        item.fill.background()
    if line:
        item.line.color.rgb = rgb(line)
        item.line.width = Pt(weight)
    else:
        item.line.fill.background()
    item.text_frame.text = ''
    return item


def write(slide, x, y, w, h, text, size=12, color=INK, bold=False, italic=False, align=PP_ALIGN.LEFT,
          anchor=MSO_ANCHOR.TOP, spacing=None, caps=False, letter=None, underline=False, fit=True):
    """Text box with **bold** markup. Paragraphs are separated by newlines."""
    if fit:
        needed = text_height(text, size, w, bold, italic)
        if needed > h + 0.5:
            problems.append(f'Text does not fit ({needed:.0f} > {h:.0f} pt): {text[:80]!r}')
    shape = slide.shapes.add_textbox(Pt(x), Pt(y), Pt(w), Pt(h))
    frame = shape.text_frame
    frame.word_wrap = True
    frame.auto_size = MSO_AUTO_SIZE.NONE
    frame.margin_left = frame.margin_right = frame.margin_top = frame.margin_bottom = 0
    frame.vertical_anchor = anchor
    for index, paragraph_text in enumerate(text.split('\n')):
        paragraph = frame.paragraphs[0] if index == 0 else frame.add_paragraph()
        paragraph.alignment = align
        if spacing:
            paragraph.space_after = Pt(spacing)
        for value, strong in segments(paragraph_text.upper() if caps else paragraph_text):
            run = paragraph.add_run()
            run.text = value
            run.font.size = Pt(size)
            run.font.bold = bold or strong
            run.font.italic = italic
            run.font.underline = underline
            run.font.color.rgb = rgb(color)
            if letter:
                run.font._element.set('spc', str(int(letter * 100)))
    return shape


def eyebrow(slide, text):
    write(slide, 36, 19, 760, 16, text, 10.5, RUBY, bold=True, caps=True, letter=2, fit=False)


def title(slide, text, size=26):
    shape = slide.shapes.title
    if width_of(text, size, True) > 880:
        problems.append('Title too long: ' + text)
    shape.text_frame.text = ''
    run = shape.text_frame.paragraphs[0].add_run()
    run.text = text
    run.font.size = Pt(size)
    run.font.bold = True
    run.font.color.rgb = rgb(INK)


def pill(slide, who):
    text = 'WHO: ' + who
    w = min(560, width_of(text, 9.5, True) + 30)
    shape = box(slide, 36, 503, w, 23, ROSE, shape=MSO_SHAPE.ROUNDED_RECTANGLE, radius=0.5)
    write(slide, 36, 503, w, 23, text, 9.5, RUBY, bold=True, align=PP_ALIGN.CENTER, anchor=MSO_ANCHOR.MIDDLE, fit=False)
    return shape


def number(slide, x, y, value, size=18, fill=RUBY, color='FFFFFF', line=None):
    circle = box(slide, x, y, size, size, fill, line, shape=MSO_SHAPE.OVAL, weight=1)
    write(slide, x, y, size, size, str(value), size * 0.5, color, bold=True, align=PP_ALIGN.CENTER,
          anchor=MSO_ANCHOR.MIDDLE, fit=False)
    return circle


def note_box(slide, x, y_bottom, w, kind, text, size=11.5):
    heading, fill, color = BOXES[kind]
    body = text_height(text, size, w - 28)
    h = 14 + 13 + 6 + body + 12
    top = y_bottom - h
    box(slide, x, top, w, h, fill, shape=MSO_SHAPE.ROUNDED_RECTANGLE, radius=0.06)
    write(slide, x + 14, top + 12, w - 28, 13, heading, 9, color, bold=True, letter=1.5, fit=False)
    write(slide, x + 14, top + 31, w - 28, body + 2, text, size, INK)
    return top


def link(shape, target):
    pending_links.append((shape, target))


def slide_number(slide):
    """Live slide-number field at the layout position (bottom right)."""
    shape = slide.shapes.add_textbox(Pt(881), Pt(505), Pt(43), Pt(22))
    frame = shape.text_frame
    frame.margin_left = frame.margin_right = frame.margin_top = frame.margin_bottom = 0
    paragraph = frame.paragraphs[0]
    paragraph.alignment = PP_ALIGN.RIGHT
    field = etree.SubElement(paragraph._p, qn('a:fld'))
    field.set('id', '{B6F15528-21DE-4FAA-801E-634DDDAF4B2B}')
    field.set('type', 'slidenum')
    props = etree.SubElement(field, qn('a:rPr'))
    props.set('lang', 'en-US')
    props.set('sz', '900')
    fill = etree.SubElement(etree.SubElement(props, qn('a:solidFill')), qn('a:srgbClr'))
    fill.set('val', GREY)
    etree.SubElement(field, qn('a:t')).text = str(len(prs.slides))


# ---------------------------------------------------------------------------- checks
def bold_terms(text):
    return [value for value, strong in segments(text) if strong]


def check_terms(texts, shot_ids, where, allowed=()):
    """Every **bold** UI label must be visible text on one of the given screens."""
    haystack = ' '.join(manifest[s]['text'] for s in shot_ids).lower()
    haystack = haystack.replace('’', "'")
    for text in texts:
        for term in bold_terms(text):
            clean = term.strip().rstrip('.').replace('’', "'").lower()
            if clean in haystack or term in allowed or term in content.ALLOWED_TERMS:
                continue
            problems.append(f'{where}: "{term}" is not on its screen')


# ---------------------------------------------------------------------------- slide builders
def content_slide():
    slide = prs.slides.add_slide(layouts['GUIDE_CONTENT'])
    slide_number(slide)
    return slide


def build_cover(plan):
    slide = prs.slides.add_slide(layouts['GUIDE_COVER'])
    write(slide, 72, 72, 520, 16, 'PEANUT KISSES  ·  DOCUMENT CONTROL SYSTEM', 11, PINK, bold=True, letter=3, fit=False)
    write(slide, 72, 118, 520, 150, 'Step-by-Step\nUser Guide', 56, 'FFFFFF', bold=True, fit=False)
    write(slide, 72, 282, 480, 60, content.COVER_SUBTITLE, 17, 'F6D9E0')
    x = 72
    for label in ['Requesters', 'Approvers', 'Document Control', 'Administrators']:
        w = width_of(label, 13, True) + 34
        box(slide, x, 380, w, 30, None, PINK, MSO_SHAPE.ROUNDED_RECTANGLE, 0.5, 1)
        write(slide, x, 380, w, 30, label, 13, 'FFFFFF', bold=True, align=PP_ALIGN.CENTER, anchor=MSO_ANCHOR.MIDDLE, fit=False)
        x += w + 12
    write(slide, 72, 470, 480, 16, f'Matches the system as of {EDITION}  ·  {plan["total"]} slides', 11, 'E7C4CD', fit=False)
    for sid, left, top, width in [('overview', 585, 92, 360), ('req-register', 625, 262, 325)]:
        shot = manifest[sid]
        height = width * shot['height'] / shot['width']
        picture = slide.shapes.add_picture(str(SHOTS / shot['file']), Pt(left), Pt(top), Pt(width), Pt(height))
        picture.line.color.rgb = rgb('FFFFFF')
        picture.line.width = Pt(1.5)


def build_cards(plan):
    slide = content_slide()
    eyebrow(slide, 'Start here')
    title(slide, 'How to use this guide')
    write(slide, 36, 96, 888, 20, content.HOW_TO_INTRO, 13, GREY)
    for index, (heading, text) in enumerate(content.HOW_TO_CARDS):
        x, y = 36 + (index % 3) * 302, 136 + (index // 3) * 178
        box(slide, x, y, 286, 164, PALE, shape=MSO_SHAPE.ROUNDED_RECTANGLE, radius=0.05)
        number(slide, x + 16, y + 16, index + 1, 20)
        write(slide, x + 46, y + 17, 225, 20, heading, 13.5, RUBY, bold=True)
        write(slide, x + 16, y + 48, 254, 104, text, 11.5, INK)
    write(slide, 36, 486, 888, 14, content.HOW_TO_FOOTNOTE, 9.5, GREY, italic=True)


def build_roles(plan):
    slide = content_slide()
    eyebrow(slide, 'Start here')
    title(slide, 'Who does what')
    write(slide, 36, 96, 888, 20, content.ROLES_INTRO, 13, GREY)
    roles = manifest['_roles']  # Captured from the seeded database: production defaults plus migrations.
    every = set(roles['Administrator'])
    rows = []
    for label, keys in content.ROLE_CAPABILITIES:
        unknown = [key for key in keys if key not in every]
        if unknown:
            problems.append(f'Who does what: unknown permission {unknown}')
        rows.append([label] + ['✓' if any(key in roles.get(role, []) for key in keys) else '–'
                               for role in content.ROLE_NAMES])
    table(slide, 36, 128, 888, ['What you can do'] + content.ROLE_NAMES, rows, [288] + [120] * 5, 9.5, center_from=1)
    pill(slide, 'Everyone')


def build_role_tasks(plan):
    slide = content_slide()
    eyebrow(slide, 'Start here')
    title(slide, 'Start with the tasks for your role')
    columns = content.ROLE_TASKS
    width = (888 - 12 * (len(columns) - 1)) / len(columns)
    for index, (role, task_ids) in enumerate(columns):
        x = 36 + index * (width + 12)
        box(slide, x, 104, width, 380, PALE, shape=MSO_SHAPE.ROUNDED_RECTANGLE, radius=0.04)
        write(slide, x + 12, 116, width - 24, 18, role, 12.5, RUBY, bold=True)
        y = 142
        for task_id in task_ids:
            task = tasks[task_id]
            h = text_height(task['title'], 10.5, width - 24) + 2
            shape = write(slide, x + 12, y, width - 24, h, task['title'], 10.5, INK, underline=True)
            link(shape, task['first'])
            y += h + 7
        if y > 480:
            problems.append('Role column too long: ' + role)


def build_contents(plan, entries, part, parts):
    slide = content_slide()
    eyebrow(slide, 'Find your task')
    title(slide, 'Contents' if part == 1 else 'Contents (continued)')
    write(slide, 36, 96, 888, 20, 'Select a task to jump to it. Every task lists its steps and who can do it.', 13, GREY)
    half = (len(entries) + 1) // 2
    for column, chunk in enumerate([entries[:half], entries[half:]]):
        x, y = 36 + column * 456, 130
        for kind, label, target in chunk:
            if kind == 'section':
                write(slide, x, y + 4, 420, 14, label, 9, RUBY, bold=True, caps=True, letter=1.5, fit=False)
                y += 22
                continue
            shape = write(slide, x, y, 360, 15, label, 11, INK, underline=True)
            link(shape, target)
            write(slide, x + 368, y, 52, 15, str(target_numbers[target]), 10, GREY, align=PP_ALIGN.RIGHT, fit=False)
            y += 17.5
        if y > 495:
            problems.append('Contents column too long')


def build_section(section, index):
    slide = prs.slides.add_slide(layouts['GUIDE_SECTION'])
    slide_number(slide)
    write(slide, 65, 112, 300, 80, f'{index:02d}', 64, PINK, bold=True, fit=False)
    shape = slide.shapes.title
    shape.text_frame.text = section['title']
    write(slide, 65, 318, 460, 70, section['summary'], 15, 'F6D9E0')
    write(slide, 620, 107, 300, 14, 'In this section', 8.5, PINK, bold=True, caps=True, letter=2, fit=False)
    y = 140
    gap = min(36, (494 - 140) / len(section['tasks']))
    for task in section['tasks']:
        shape = write(slide, 620, y, 260, 16, task['title'], 12, 'FFFFFF', underline=True)
        link(shape, task['first'])
        write(slide, 884, y + 1, 40, 14, str(target_numbers[task['first']]), 9.5, PINK, align=PP_ALIGN.RIGHT, fit=False)
        line = slide.shapes.add_connector(MSO_CONNECTOR.STRAIGHT, Pt(620), Pt(y + gap - 9), Pt(924), Pt(y + gap - 9))
        line.line.color.rgb = rgb('6B2A3F')
        line.line.width = Pt(0.75)
        y += gap


def build_overview(section, task):
    slide = content_slide()
    eyebrow(slide, f'{section["title"]}  ·  Task overview')
    title(slide, task['title'])
    write(slide, 36, 94, 888, 34, task['intro'], 13, GREY, italic=True)
    y = 136
    cards = [('Who can do this', task['who'])]
    if task.get('before'):
        cards.append(('Before you start', '\n'.join(task['before'])))
    cards.append(('Where to start', task['start']))
    for heading, text in cards:
        body = text_height(text, 12, 298)
        h = 18 + 14 + 8 + body + 14
        box(slide, 36, y, 330, h, PALE, shape=MSO_SHAPE.ROUNDED_RECTANGLE, radius=0.06)
        write(slide, 52, y + 16, 298, 13, heading, 9, RUBY, bold=True, caps=True, letter=1.5, fit=False)
        write(slide, 52, y + 38, 298, body + 2, text, 12, INK, spacing=3)
        y += h + 12
    if y > 494:
        problems.append('Overview cards too long: ' + task['title'])
    write(slide, 396, 136, 300, 14, 'Step by step', 9.5, GREY, bold=True, caps=True, letter=2, fit=False)
    steps = task['steps']
    gap = min(48, (300 - 20) / max(1, len(steps)))
    y = 160
    for index, step in enumerate(steps, 1):
        if index < len(steps):
            line = slide.shapes.add_connector(MSO_CONNECTOR.STRAIGHT, Pt(407), Pt(y + 22), Pt(407), Pt(y + gap))
            line.line.color.rgb = rgb(LINE)
            line.line.width = Pt(1.5)
        number(slide, 396, y, index, 22)
        shape = write(slide, 430, y + 2, 420, 18, step['title'], 13.5, INK, underline=True)
        link(shape, step['key'])
        write(slide, 860, y + 4, 64, 14, f'slide {target_numbers[step["key"]]}', 9.5, GREY, align=PP_ALIGN.RIGHT, fit=False)
        y += gap
    result_h = text_height('Result: ' + task['result'], 12.5, 830) + 26
    top = max(y + 6, 492 - result_h)
    box(slide, 396, top, 528, result_h, 'E8F3EF', shape=MSO_SHAPE.ROUNDED_RECTANGLE, radius=0.12)
    number(slide, 408, top + result_h / 2 - 10, '✓', 20, fill='1A685B')
    write(slide, 438, top + 13, 474, result_h - 20, '**Result:** ' + task['result'], 12.5, INK)
    if top + result_h > 494:
        problems.append('Overview steps too long: ' + task['title'])
    pill(slide, task['who'])
    check_terms([task['intro'], task['start'], task['result']] + task.get('before', []),
                [s['shot'] for s in steps if s.get('shot')], task['title'], task.get('allow', ()))


def place_screen(slide, sid, marks, area=(36, 100, 560, 392)):
    shot = manifest[sid]
    ax, ay, aw, ah = area
    scale = min(aw / shot['width'], ah / shot['height'])
    w, h = shot['width'] * scale, shot['height'] * scale
    x, y = ax + (aw - w) / 2, ay
    picture = slide.shapes.add_picture(str(SHOTS / shot['file']), Pt(x), Pt(y), Pt(w), Pt(h))
    picture.line.color.rgb = rgb(LINE)
    picture.line.width = Pt(0.75)
    placed = []
    for index, name in enumerate(marks, 1):
        if not name:
            continue
        if name not in shot['marks']:
            problems.append(f'{sid}: marker "{name}" was not captured')
            continue
        m = shot['marks'][name]
        bx, by = x + m['x'] * scale - 2, y + m['y'] * scale - 2
        bw, bh = m['width'] * scale + 4, m['height'] * scale + 4
        box(slide, bx, by, bw, bh, None, RUBY, MSO_SHAPE.ROUNDED_RECTANGLE, 0.15, 1.5)
        cx, cy = bx - 9, by - 9
        cx = min(max(cx, x - 6), x + w - 12)
        cy = min(max(cy, y - 6), y + h - 12)
        for px, py in placed:  # Keep markers from covering each other.
            if abs(px - cx) < 18 and abs(py - cy) < 18:
                cx = px + 20 if px + 38 < x + w else px - 20
        placed.append((cx, cy))
        number(slide, cx, cy, index, 18, RUBY, 'FFFFFF', 'FFFFFF')
    return y + h


def build_step(section, task, step, index):
    slide = content_slide()
    total = len(task['steps'])
    if total > 1:
        eyebrow(slide, f'{task["title"]}  ·  Step {index} of {total}')
        for dot in range(1, total + 1):
            x = 924 - (total - dot + 1) * 25 + 7
            current = dot == index
            shape = number(slide, x, 17, dot, 18, RUBY if current else 'FFFFFF',
                           'FFFFFF' if current else (RUBY if dot < index else 'A99AA4'),
                           RUBY if dot <= index else 'D9CED5')
            if not current:
                link(shape, task['steps'][dot - 1]['key'])
    else:
        eyebrow(slide, f'{section["title"]}  ·  {task["kind_label"] if task.get("kind_label") else "Task"}')
    title(slide, step['title'] if total > 1 else task['title'])
    if step.get('shot'):
        place_screen(slide, step['shot'], step.get('marks', []))
    x, w = 620, 304
    write(slide, x, 104, w, 14, 'Do this', 9.5, GREY, bold=True, caps=True, letter=2, fit=False)
    marks = step.get('marks', [])
    bottom = 492
    # Largest text that fits above the note box: 13 pt, stepping down to 11 pt for long steps.
    for size, gap in [(13, 13), (12.5, 11), (12, 9), (11.5, 8), (11, 7)]:
        heights = [text_height(item, size, w - 28) for item in step['do']]
        box_h = 0
        if step.get('box'):
            box_h = 14 + 13 + 6 + text_height(step['box'][1], 11.5, w - 28) + 12
        if 128 + sum(heights) + gap * len(heights) + box_h + 4 <= bottom:
            break
    else:
        problems.append(f'Step text too long: {task["title"]} / {step["title"]}')
    y = 128
    for item_index, (item, h) in enumerate(zip(step['do'], heights), 1):
        if item_index <= len(marks) and marks[item_index - 1]:
            number(slide, x, y, item_index, 18)
        else:
            box(slide, x + 6, y + 6, 6, 6, RUBY, shape=MSO_SHAPE.OVAL)
        write(slide, x + 28, y, w - 28, h + 1, item, size, INK)
        y += h + gap
    if step.get('box'):
        kind, text = step['box']
        note_box(slide, x, bottom, w, kind, text)
    if len(marks) > len(step['do']):
        problems.append(f'{task["title"]} / {step["title"]}: more markers than instructions')
    pill(slide, step.get('who', task['who']))
    texts = step['do'] + ([step['box'][1]] if step.get('box') else [])
    if step.get('shot'):
        check_terms(texts, [step['shot']], f'{task["title"]} / {step["title"]}', step.get('allow', ()))
    return slide


def table(slide, x, y, w, header, rows, widths, size, center_from=None):
    shape = slide.shapes.add_table(len(rows) + 1, len(header), Pt(x), Pt(y), Pt(w), Pt(30 * (len(rows) + 1)))
    grid = shape.table
    tbl = grid._tbl
    style = tbl.tblPr
    for attribute in ['firstRow', 'bandRow']:
        style.set(attribute, '0')
    for index, cw in enumerate(widths):
        grid.columns[index].width = Pt(cw)
    heights = []
    for r, values in enumerate([header] + rows):
        line_count = max(wrap(str(value), size, widths[c] - 14, r == 0 or c == 0) for c, value in enumerate(values))
        heights.append(line_count * size * 1.2 + 12)
        for c, value in enumerate(values):
            cell = grid.cell(r, c)
            cell.margin_left = cell.margin_right = Pt(7)
            cell.margin_top = cell.margin_bottom = Pt(5)
            cell.vertical_anchor = MSO_ANCHOR.MIDDLE
            cell.fill.solid()
            cell.fill.fore_color.rgb = rgb(MAROON if r == 0 else ('FFFFFF' if r % 2 else 'F8F5F7'))
            frame = cell.text_frame
            frame.word_wrap = True
            paragraph = frame.paragraphs[0]
            if center_from is not None and c >= center_from:
                paragraph.alignment = PP_ALIGN.CENTER
            for text, strong in segments(str(value)):
                run = paragraph.add_run()
                run.text = text
                run.font.size = Pt(size)
                run.font.bold = strong or r == 0 or c == 0
                run.font.color.rgb = rgb('FFFFFF' if r == 0 else (('1A685B' if text == '✓' else INK)))
            props = cell._tc.get_or_add_tcPr()
            # Schema order: borders (lnL, lnR, lnT, lnB) come before the cell fill.
            for position, side in enumerate(['a:lnL', 'a:lnR', 'a:lnT', 'a:lnB']):
                edge = etree.Element(qn(side), w='9525')
                fill = etree.SubElement(edge, qn('a:solidFill'))
                etree.SubElement(fill, qn('a:srgbClr'), val=LINE)
                props.insert(position, edge)
    for r, h in enumerate(heights):
        grid.rows[r].height = Pt(h)
    shape.height = Pt(sum(heights))
    if y + sum(heights) > 497:
        problems.append(f'Table too tall ({y + sum(heights):.0f} pt): {header}')
    return shape


def build_reference(section, ref):
    slide = content_slide()
    eyebrow(slide, section['title'])
    title(slide, ref['title'])
    y = 100
    if ref.get('intro'):
        write(slide, 36, 96, 888, 20, ref['intro'], 13, GREY)
        y = 128
    table(slide, 36, y, 888, ref['header'], ref['rows'], ref['widths'], ref.get('size', 10.5))
    if ref.get('box'):
        kind, text = ref['box']
        note_box(slide, 36, 492, 888, kind, text)
    pill(slide, ref.get('who', 'Everyone'))


# ---------------------------------------------------------------------------- plan and build
def plan_slides():
    """Assign slide numbers first so contents, sections and overviews can name and link them."""
    order = [('cover', None), ('cards', None), ('roles', None), ('role-tasks', None)]
    parts = 2
    order += [('contents', part) for part in range(1, parts + 1)]
    for section_index, section in enumerate(content.SECTIONS, 1):
        order.append(('section', section_index))
        for task in section['tasks']:
            if task.get('kind') == 'reference':
                task['first'] = task['id']
                order.append(('reference', task['id']))
                continue
            for index, step in enumerate(task['steps'], 1):
                step['key'] = f'{task["id"]}#{index}'
            if len(task['steps']) > 1:
                order.append(('overview', task['id']))
                task['first'] = task['id']
            else:
                task['first'] = task['steps'][0]['key']
            for step in task['steps']:
                order.append(('step', step['key']))
    # Contents entries link to each task's first slide (its overview, or its only step).
    entries = []
    for section in content.SECTIONS:
        entries.append(('section', section['title'], None))
        for task in section['tasks']:
            entries.append(('task', task['title'], task['first']))
    numbers = {}
    for position, (kind, key) in enumerate(order, 1):
        if kind in ('overview', 'reference', 'step'):
            numbers[key] = position
    return order, entries, numbers


def main():
    global prs, layouts, manifest, problems, pending_links, tasks, target_numbers
    manifest = json.loads((SHOTS / 'manifest.json').read_text(encoding='utf-8'))
    problems, pending_links = [], []
    tasks = {task['id']: task for section in content.SECTIONS for task in section['tasks']}
    order, entries, target_numbers = plan_slides()
    for task in tasks.values():
        for step in task.get('steps', []):
            if step.get('shot') and step['shot'] not in manifest:
                problems.append(f'{task["title"]}: screenshot "{step["shot"]}" was not captured')
    if problems:
        raise SystemExit('\n'.join(['Cannot build:'] + problems))
    prs = Presentation(str(TEMPLATE))
    layouts = {layout.name: layout for layout in prs.slide_layouts}
    plan = {'total': len(order)}
    sections = {index: section for index, section in enumerate(content.SECTIONS, 1)}
    slides_by_key = {}
    contents_parts = [entry for entry in order if entry[0] == 'contents']
    half = len(entries) // 2
    split = next(i for i in range(half, len(entries)) if entries[i][0] == 'section')
    contents_chunks = [entries[:split], entries[split:]]
    for kind, key in order:
        if kind == 'cover':
            build_cover(plan)
        elif kind == 'cards':
            build_cards(plan)
        elif kind == 'roles':
            build_roles(plan)
        elif kind == 'role-tasks':
            build_role_tasks(plan)
        elif kind == 'contents':
            build_contents(plan, contents_chunks[key - 1], key, len(contents_parts))
        elif kind == 'section':
            build_section(sections[key], key)
        elif kind == 'overview':
            task = tasks[key]
            section = next(s for s in content.SECTIONS if task in s['tasks'])
            build_overview(section, task)
            slides_by_key[key] = prs.slides[-1]
        elif kind == 'reference':
            task = tasks[key]
            section = next(s for s in content.SECTIONS if task in s['tasks'])
            build_reference(section, task)
            slides_by_key[key] = prs.slides[-1]
        elif kind == 'step':
            task_id, _, step_index = key.partition('#')
            task = tasks[task_id]
            section = next(s for s in content.SECTIONS if task in s['tasks'])
            build_step(section, task, task['steps'][int(step_index) - 1], int(step_index))
            slides_by_key[key] = prs.slides[-1]
    for shape, target in pending_links:
        shape.click_action.target_slide = slides_by_key[target]
    assert len(prs.slides) == plan['total'], (len(prs.slides), plan['total'])
    if problems:
        raise SystemExit('\n'.join(['Guide text does not match the screens:'] + problems))
    prs.core_properties.title = 'PK DCS Step-by-Step User Guide'
    prs.core_properties.subject = 'Step-by-step user guide for the PK Document Control System, ' + EDITION
    prs.core_properties.author = 'PK Document Control System'
    OUTPUTS[0].parent.mkdir(parents=True, exist_ok=True)
    pending = OUTPUTS[0].with_suffix('.pptx.pending')
    prs.save(str(pending))
    for output in OUTPUTS:
        shutil.copyfile(pending, output)
    pending.unlink()
    print(f'Saved {len(prs.slides)} slides to ' + ', '.join(str(o.relative_to(ROOT)) for o in OUTPUTS))


if __name__ == '__main__':
    main()
