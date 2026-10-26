"""Render illustrated process maps and participant DFDs in the supplied formats.

Sample illustrations are embedded unchanged. Text, cards, connectors and their
glued endpoints remain native editable Visio elements in the process drawing.
"""
from __future__ import annotations

import argparse
import json
import math
from pathlib import Path

import pymupdf as pdf
from PIL import Image

import render_diagrams as drawing
from brand_palette import ACCENT_RED, BORDER, BRAND_RED, DARK_RED, INK, PALE, SOFT, WHITE

ROOT = Path(__file__).resolve().parents[2]
ICONS = ROOT / 'docs/system-documentation/source/icons'
WIDTH, HEIGHT = 792, 612
DARK, ARROW = DARK_RED, ACCENT_RED
ICON_FILES = {'person': 'person.png', 'computer': 'account.png',
              'reviewer': 'review.png', 'document': 'review.png',
              'database': 'database.jpeg', 'admin': 'account.png',
              'upload': 'upload.png'}


class Canvas:
    def __init__(self, title):
        self.title, self.nodes, self.edges, self.warnings = title, [], [], []

    def box(self, name, x, y, w, h, fill=WHITE, line=WHITE):
        return self._node(name, x, y, w, h, '', 'entity', fill, line)

    def text(self, name, x, y, w, h, text, size=9, bold=False, color=INK, align='left', valign='center'):
        result = self._node(name, x, y, w, h, text, 'text', WHITE, WHITE, size, bold, color, align)
        self.nodes[-1]['text_layout']['valign'] = valign
        return result

    def _node(self, name, x, y, w, h, text, kind, fill, line, size=9, bold=False, color=INK, align='center'):
        if any(n['id'] == name for n in self.nodes):
            raise ValueError(f'Duplicate element: {name}')
        r = pdf.Rect(x, y, x+w, y+h)
        layout = drawing.fitted(text, r, size, size-.5, bold, align)
        if layout['overflow']:
            self.warnings.append(f'Text overflow: {name}')
        if not (x >= 0 and y >= 0 and x+w <= WIDTH and y+h <= HEIGHT):
            self.warnings.append(f'Element outside page: {name}')
        self.nodes.append({'id': name, 'x': x, 'y': y, 'w': w, 'h': h,
                           'kind': kind, 'text_layout': layout,
                           'style': {'fill': fill, 'line': line, 'text_color': color, 'line_width': .7}})
        return name

    def image(self, name, icon, x, y, w, h):
        path = ICONS / ICON_FILES[icon]
        iw, ih = Image.open(path).size
        factor = min(w/iw, h/ih)
        iw, ih = iw*factor, ih*factor
        self.box(name, x+(w-iw)/2, y+(h-ih)/2, iw, ih)
        self.nodes[-1]['image'] = str(path)

    def arrow(self, source, target, path, width=1.1, label='', label_box=None, label_size=7.2):
        edge = {'from': source, 'to': target, 'path': path, 'line_width': width,
                'color': ARROW, 'text_layout': None}
        nodes = {n['id']: n for n in self.nodes}
        for endpoint, owner in ((path[0], nodes[source]), (path[-1], nodes[target])):
            r = drawing.rect_of(owner)
            x, y = endpoint
            on_x = (abs(x-r.x0)<.01 or abs(x-r.x1)<.01) and r.y0-.01 <= y <= r.y1+.01
            on_y = (abs(y-r.y0)<.01 or abs(y-r.y1)<.01) and r.x0-.01 <= x <= r.x1+.01
            if not (on_x or on_y):
                self.warnings.append(f'Connector endpoint off boundary: {source} -> {target}')
        for a, b in zip(path, path[1:]):
            if a[0] != b[0] and a[1] != b[1]:
                self.warnings.append(f'Diagonal connector: {source} -> {target}')
            # Card and caption boundaries protect both the business meaning and
            # the legibility of the illustrated map.
            for n in self.nodes:
                protected = n.get('anchor') or n['id'].startswith(('description-box-', 'notes-panel', 'legend-panel'))
                if protected and n['id'] not in (source, target):
                    if not drawing.segment_clear(a, b, [drawing.inflated(drawing.rect_of(n), -1)]):
                        self.warnings.append(f'Connector crosses card: {source} -> {target}')
        self.edges.append(edge)
        if label and label_box:
            self.text(f'flow-label-{len(self.edges)}', *label_box, label, label_size, color=ARROW, align='center')

    def anchor(self, name):
        next(n for n in self.nodes if n['id'] == name)['anchor'] = True

    def prepared(self):
        return {'title': self.title, 'subtitle': '', 'catalog': True,
                'prepared_nodes': self.nodes, 'prepared_edges': self.edges,
                'notes_layout': drawing.fitted('', pdf.Rect(1, 1, 2, 2)),
                'warnings': self.warnings}

    def validate(self):
        for edge in self.edges:
            for node in self.nodes:
                protected = node.get('anchor') or node['id'].startswith(('description-box-', 'notes-panel', 'legend-panel'))
                if not protected or node['id'] in (edge['from'], edge['to']):
                    continue
                bounds = drawing.inflated(drawing.rect_of(node), -1)
                if any(not drawing.segment_clear(a, b, [bounds]) for a, b in zip(edge['path'], edge['path'][1:])):
                    self.warnings.append(f'Connector crosses {node["id"]}: {edge["from"]} -> {edge["to"]}')


def illustrated_card(c, ident, role, icon, x, y, w=88, h=94, dfd=False):
    if dfd:
        c.box('shadow-'+ident, x+5, y+5, w, h, SOFT, SOFT)
        c.box(ident, x, y, w, h, PALE, PALE)
        c.image(ident+'-icon', icon, x+22, y+7, w-44, h-43)
        c.box(ident+'-role-bg', x+7, y+h-35, w-14, 33, BRAND_RED, BRAND_RED)
        c.text(ident+'-role', x+10, y+h-34, w-20, 32, role, 9.0, color=WHITE, align='center')
    else:
        c.box(ident, x, y, w, h, DARK, DARK)
        c.image(ident+'-icon', icon, x+10, y+6, w-20, h-35)
        c.text(ident+'-role', x+5, y+h-29, w-10, 24, role.upper(), 8.3, True, WHITE, 'center')
    c.anchor(ident)


def process_page(data, index, total):
    if data.get('layout') == 'administration-options':
        return administration_page(data, index, total)
    c = Canvas(data['title'])
    c.text('header-title', 20, 13, 752, 21, 'PK DOCUMENT TRACKING SYSTEM', 14, True, DARK, 'center')
    c.text('header-type', 20, 35, 752, 16, 'PROPOSED PROCESS FLOW', 10, True, DARK, 'center')
    c.text('header-subprocess', 20, 53, 752, 20, data['subprocess'], 11, True, DARK, 'center')
    steps = data['steps']
    if not 2 <= len(steps) <= 6:
        raise ValueError('The illustrated process format supports two to six steps per sheet.')
    for i, step in enumerate(steps):
        x = 132 if i < 3 else 421
        y = 88 + (i % 3)*145
        illustrated_card(c, step['id'], step['actor'], step['icon'], x, y)
        dx, dw = (20, 102) if i < 3 else (519, 139)
        c.box(f'description-box-{i}', dx, y, dw, 94, PALE, BORDER)
        c.text(f'step-number-{i}', dx+7, y+5, dw-14, 14, f'STEP {i+1}', 8, True, DARK)
        c.text(f'description-{i}', dx+7, y+25, dw-14, 62, step['description'], 8.1, valign='top')
    for i in range(len(steps)-1):
        if data.get('layout') == 'independent-paths' and i == 2:
            continue
        a, b = steps[i]['id'], steps[i+1]['id']
        if i == 2:
            route = [(220, 425), (285, 425), (285, 135), (421, 135)]
        else:
            x = 176 if i < 3 else 465
            y = 88 + (i % 3)*145
            route = [(x, y+94), (x, y+145)]
        c.arrow(a, b, route, width=2.2)
    if data.get('layout') == 'independent-paths':
        c.text('path-a', 20, 76, 200, 10, 'A. DOCUMENT USE', 7.2, True, DARK)
        c.text('path-b', 421, 76, 237, 10, 'B. PERMITTED DIRECT ACTIONS', 7.2, True, DARK)
    if index == 2:
        c.box('return-outcome', 305, 335, 100, 44, PALE, BORDER)
        c.text('return-outcome-label', 310, 339, 90, 36, 'RETURN\nPrevious approver;\nrequester at first step', 7.5, color=DARK, align='center')
        c.anchor('return-outcome')
        c.box('reject-outcome', 305, 410, 100, 35, PALE, BORDER)
        c.text('reject-outcome-label', 310, 414, 90, 27, 'REJECT\nRequest stops', 7.5, color=DARK, align='center')
        c.anchor('reject-outcome')
        c.arrow(steps[4]['id'], steps[1]['id'], [(485, 327), (485, 354), (665, 354), (665, 79), (245, 79), (245, 280), (220, 280)], width=1.6)
        c.text('more-approvals', 526, 335, 132, 13, 'MORE APPROVALS', 7.4, True, BRAND_RED)
        c.text('final-only', 479, 358, 179, 13, 'FINAL APPROVAL ONLY', 7.4, True, BRAND_RED)
        c.arrow(steps[4]['id'], 'return-outcome', [(421, 288), (410, 288), (410, 357), (405, 357)], width=1.5)
        c.arrow(steps[4]['id'], 'reject-outcome', [(421, 305), (416, 305), (416, 427.5), (405, 427.5)], width=1.5)
    c.box('notes-panel', 674, 88, 98, 386, PALE, BORDER)
    c.box('notes-heading', 674, 88, 98, 24, BRAND_RED, BRAND_RED)
    c.text('notes-label', 681, 92, 84, 16, 'NOTES', 9, True, WHITE)
    c.text('notes-body', 681, 119, 84, 347, '\n\n'.join(data['notes']), 7.65, valign='top')
    c.box('legend-panel', 20, 501, 752, 83, PALE, BORDER)
    c.text('legend-heading', 29, 507, 734, 14, 'LEGEND / REQUEST COVERAGE', 8.2, True, DARK)
    c.text('legend-body', 29, 525, 734, 52, '\n'.join(data.get('legend', [])), 8.3, valign='top')
    c.text('page-number', 695, 591, 77, 11, f'Page {index} of {total}', 7.5, color=DARK, align='center')
    return c


def administration_page(data, index, total):
    c = Canvas(data['title'])
    c.text('header-title', 20, 13, 752, 21, 'PK DOCUMENT TRACKING SYSTEM', 14, True, DARK, 'center')
    c.text('header-type', 20, 35, 752, 16, 'PROPOSED PROCESS FLOW', 10, True, DARK, 'center')
    c.text('header-subprocess', 20, 53, 752, 20, data['subprocess'], 11, True, DARK, 'center')
    steps = data['steps']
    first = steps[0]
    illustrated_card(c, first['id'], first['actor'], first['icon'], 352, 88)
    c.box('description-box-entry', 456, 88, 316, 94, PALE, BORDER)
    c.text('entry-heading', 464, 95, 300, 14, 'OPEN A PERMITTED ADMINISTRATION AREA', 8.1, True, DARK)
    c.text('entry-body', 464, 119, 300, 53, first['description']+' The options below will be independent; permissions will determine access.', 8.3, valign='top')
    c.box('legend-panel', 20, 88, 311, 94, PALE, BORDER)
    c.text('legend-heading', 28, 95, 295, 14, 'LEGEND / ADMINISTRATION SCOPE', 8.1, True, DARK)
    c.text('legend-body', 28, 117, 295, 58, '\n'.join(data.get('legend', [])), 8.0, valign='top')
    for i, (step, x) in enumerate(zip(steps[1:5], [25, 205, 385, 565]), 1):
        illustrated_card(c, step['id'], step['actor'], step['icon'], x, 252)
        c.box(f'description-box-option-{i}', x, 356, 160, 81, PALE, BORDER)
        c.text(f'option-heading-{i}', x+7, 361, 146, 14, ['ACCOUNTS AND ROLES', 'PLACES AND CATEGORIES', 'APPROVAL WORKFLOWS', 'AUDIT AND BACKUPS'][i-1], 7.9, True, DARK)
        c.text(f'option-description-{i}', x+7, 380, 146, 50, step['description'], 8.2, valign='top')
        # A shared authorization hub represents choices, not a dependency
        # between administrative operations.
        c.arrow(first['id'], step['id'], [(396, 182), (396, 213), (x+44, 213), (x+44, 252)], width=1.7)
    last = steps[5]
    illustrated_card(c, last['id'], last['actor'], last['icon'], 565, 484)
    c.box('description-box-restore', 663, 484, 109, 94, PALE, BORDER)
    c.text('restore-heading', 670, 489, 95, 14, 'AUTHORIZED RESTORE', 7.8, True, DARK)
    c.text('restore-description', 670, 510, 95, 61, last['description'], 8.0, valign='top')
    # The backup operation is an explicit optional continuation of the audit /
    # backup area; audit inspection alone does not trigger restoration.
    c.arrow(steps[4]['id'], last['id'], [(653, 299), (759, 299), (759, 458), (609, 458), (609, 484)], width=1.6)
    c.text('restore-condition', 566, 441, 174, 13, 'ONLY WHEN RESTORE IS SELECTED', 7.0, True, BRAND_RED)
    c.box('notes-panel', 20, 459, 524, 127, PALE, BORDER)
    c.box('notes-heading', 20, 459, 524, 22, BRAND_RED, BRAND_RED)
    c.text('notes-label', 28, 462, 508, 15, 'NOTES / CONFIGURATION AND RECOVERY RULES', 8.4, True, WHITE)
    c.text('notes-body', 28, 487, 508, 91, '\n\n'.join(data['notes']), 8.0, valign='top')
    c.text('page-number', 695, 591, 77, 11, f'Page {index} of {total}', 7.5, color=DARK, align='center')
    return c


def dfd_page(data, index, total):
    c = Canvas(data['title'])
    c.box('header-title-panel', 22, 18, 520, 43, BRAND_RED, BRAND_RED)
    c.text('header-title', 33, 22, 498, 35, 'PK DOCUMENT TRACKING SYSTEM - PROPOSED DATA FLOW DIAGRAM\n'+data['title'], 10.1, True, WHITE, 'center')
    c.box('header-page-panel', 611, 18, 159, 43, BRAND_RED, BRAND_RED)
    c.text('header-page', 618, 25, 145, 29, f'Page {index} of {total}', 11, True, WHITE, 'center')
    actors = data['actors']
    positions = [(333, 80), (35, 274), (629, 274)]
    for actor, (x, y) in zip(actors, positions):
        illustrated_card(c, actor['id'], actor['role'], actor['icon'], x, y, 128, 114, True)
    c.box('system', 305, 270, 182, 119, SOFT, DARK_RED)
    c.anchor('system')
    c.box('system-heading', 305, 270, 182, 35, BRAND_RED, DARK_RED)
    c.text('system-title', 312, 277, 168, 21, 'IN-HOUSE SYSTEM', 10.5, True, WHITE, 'center')
    c.text('system-module', 315, 314, 162, 63, 'PK DTS\n'+data['module'], 9.5, color=DARK_RED, align='center')
    for i, actor in enumerate(actors):
        incoming, outgoing = '\n'.join(actor['inputs']), '\n'.join(actor['outputs'])
        if i == 0:
            c.arrow(actor['id'], 'system', [(359, 194), (359, 270)], label=incoming, label_box=(211, 207, 136, 49))
            c.arrow('system', actor['id'], [(435, 270), (435, 194)], label=outgoing, label_box=(447, 207, 144, 49))
        elif i == 1:
            c.arrow(actor['id'], 'system', [(163, 299), (305, 299)], label=incoming, label_box=(170, 264, 128, 32), label_size=6.9)
            c.arrow('system', actor['id'], [(305, 364), (163, 364)], label=outgoing, label_box=(170, 329, 128, 32), label_size=6.9)
        else:
            c.arrow(actor['id'], 'system', [(629, 299), (487, 299)], label=incoming, label_box=(494, 257, 128, 39), label_size=6.9)
            c.arrow('system', actor['id'], [(487, 364), (629, 364)], label=outgoing, label_box=(494, 322, 128, 39), label_size=6.9)
    c.box('legend-panel', 22, 420, 255, 171, PALE, BORDER)
    c.box('legend-heading', 22, 420, 255, 23, BRAND_RED, BRAND_RED)
    c.text('legend-title', 30, 424, 239, 15, 'LEGEND / REQUEST ACTIONS' if index == 1 else 'LEGEND', 8.6, True, WHITE)
    c.text('legend-body', 31, 451, 237, 130, '\n'.join(data.get('legend', [])), 8.2, valign='top')
    c.box('notes-heading', 299, 420, 471, 23, BRAND_RED, BRAND_RED)
    c.text('notes-title', 307, 424, 455, 15, 'OPERATING RULES', 8.6, True, WHITE)
    c.text('notes-body', 307, 451, 455, 130, '\n\n'.join(data['notes']), 8.8, valign='top')
    return c


def render_pdf(model, canvases, path):
    document = pdf.open()
    for c in canvases:
        page = document.new_page(width=WIDTH, height=HEIGHT)
        page.insert_font(fontname='pkregular', fontfile=str(drawing.REGULAR_PATH))
        page.insert_font(fontname='pkbold', fontfile=str(drawing.BOLD_PATH))
        for node in c.nodes:
            r = drawing.rect_of(node)
            if node.get('image'):
                page.insert_image(r, filename=node['image'])
            elif node['kind'] != 'text':
                style = node['style']
                page.draw_rect(r, color=drawing.rgb(style['line']), fill=drawing.rgb(style['fill']), width=.7)
        for edge in c.edges:
            pts = edge['path'];page.draw_polyline(pts, color=drawing.rgb(ARROW), width=edge['line_width'])
            a, b = pts[-2], pts[-1];angle = math.atan2(b[1]-a[1], b[0]-a[0])
            length, half = (6, 2.5) if edge['line_width'] > 1.5 else (5, 2)
            back = b[0]-length*math.cos(angle), b[1]-length*math.sin(angle)
            p = back[0]-half*math.sin(angle), back[1]+half*math.cos(angle)
            q = back[0]+half*math.sin(angle), back[1]-half*math.cos(angle)
            page.draw_polyline([p, b, q], color=drawing.rgb(ARROW), fill=drawing.rgb(ARROW), closePath=True)
        for node in c.nodes:
            if node['text_layout']['text']:
                drawing.draw_text(page, node['text_layout'], node['style']['text_color'])
    document.set_metadata({'title': model['title'], 'author': 'PK Document Tracking System',
                           'subject': 'Proposed processes and information exchanges'})
    document.save(path, garbage=4, deflate=True)
    document.close()


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--model', type=Path, required=True)
    parser.add_argument('--output-base', type=Path, required=True)
    parser.add_argument('--visio', action='store_true')
    args = parser.parse_args()
    model = json.loads(args.model.read_text(encoding='utf-8'))
    builder = {'sample-process': process_page, 'sample-dfd': dfd_page}[model['format']]
    canvases = [builder(p, i, len(model['pages'])) for i, p in enumerate(model['pages'], 1)]
    for canvas in canvases:
        canvas.validate()
    warnings = {c.title: c.warnings for c in canvases if c.warnings}
    if warnings:
        raise ValueError(json.dumps(warnings, indent=2))
    args.output_base.parent.mkdir(parents=True, exist_ok=True)
    render_pdf(model, canvases, args.output_base.with_suffix('.pdf'))
    report = {'format': model['format'], 'pages': len(canvases), 'warnings': warnings,
              'sample_reference': model['sample_reference'], 'page_size': [WIDTH, HEIGHT]}
    if args.visio:
        drawing.WIDTH, drawing.HEIGHT, drawing.LINE = WIDTH, HEIGHT, ARROW
        report['visio'] = drawing.render_visio({**model, 'custom_canvas': True, 'paper_kind': 1},
                                               [c.prepared() for c in canvases], args.output_base.with_suffix('.vsdx'))
    print(json.dumps(report, indent=2))


if __name__ == '__main__':
    main()
