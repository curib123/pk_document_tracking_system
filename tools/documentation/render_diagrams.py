#!/usr/bin/env python3
"""Render source-authored diagram JSON as an A3 PDF and editable Visio drawing.

Requires PyMuPDF for PDF output. The VSDX writer uses only Python's standard
library and creates original native shapes, text, geometry and glued 1-D
connectors; it does not wrap PDF pages or raster images in a Visio package.

Coordinates in the model are PDF points, with the origin at the upper left.
Example: python tools/documentation/render_diagrams.py --model model.json \
    --output-base output/system-diagram --visio
"""
from __future__ import annotations

import argparse
from collections import defaultdict
import heapq
import json
import math
from pathlib import Path
import posixpath
import re
from typing import Any
from xml.etree import ElementTree as ET
from zipfile import ZIP_DEFLATED, ZipFile

import pymupdf as pdf


WIDTH, HEIGHT = 1190.0, 842.0
VISIO = "http://schemas.microsoft.com/office/visio/2012/main"
REL = "http://schemas.openxmlformats.org/officeDocument/2006/relationships"
PKGREL = "http://schemas.openxmlformats.org/package/2006/relationships"
CT = "http://schemas.openxmlformats.org/package/2006/content-types"
VREL = "http://schemas.microsoft.com/visio/2010/relationships/"
ET.register_namespace("", VISIO)
ET.register_namespace("r", REL)

INK = "#293346"
MUTED = "#657084"
LINE = "#627084"
BURGUNDY = "#A80037"
STYLES = {
    "process": ("#F9EEF2", "#A80037"),
    "entity": ("#F2F5F8", "#657084"),
    "store": ("#ECF6F6", "#087F87"),
    "decision": ("#FFF7E8", "#A97724"),
    "terminator": ("#ECF6F6", "#087F87"),
    "data": ("#F2F5F8", "#657084"),
    "document": ("#F2F5F8", "#657084"),
    "note": ("#F8F9FB", "#9BA5B2"),
    "text": ("#FFFFFF", "#FFFFFF"),
    "port": ("#F2F5F8", "#657084"),
}
ALIASES = {"start": "terminator", "end": "terminator", "terminal": "terminator",
           "input": "data", "output": "data", "io": "data", "inputoutput": "data",
           "action": "process", "activity": "process", "subprocess": "process",
           "annotation": "note", "label": "text", "actor": "entity",
           "database": "store", "datastore": "store", "storage": "store",
           "offpage": "port", "offpageconnector": "port", "reference": "port",
           "connector": "port"}


def rgb(value: str) -> tuple[float, float, float]:
    value = value.lstrip("#")
    return tuple(int(value[i:i + 2], 16) / 255 for i in (0, 2, 4))


def kind(node: dict) -> str:
    value = str(node.get("kind", "process")).lower().replace("-", "").replace("_", "")
    return ALIASES.get(value, value if value in STYLES else "process")


def font_paths() -> tuple[Path | None, Path | None]:
    for regular, bold in [
        ("C:/Windows/Fonts/arial.ttf", "C:/Windows/Fonts/arialbd.ttf"),
        ("/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf", "/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf"),
    ]:
        if Path(regular).is_file() and Path(bold).is_file():
            return Path(regular), Path(bold)
    return None, None


REGULAR_PATH, BOLD_PATH = font_paths()
FONT = pdf.Font(fontfile=str(REGULAR_PATH)) if REGULAR_PATH else pdf.Font("helv")
BOLD = pdf.Font(fontfile=str(BOLD_PATH)) if BOLD_PATH else pdf.Font("hebo")


def clean(text: Any) -> str:
    return str(text or "").replace("\r\n", "\n").replace("\r", "\n").replace("\t", "    ")


def wrap(text: str, width: float, size: float, bold: bool = False) -> list[str]:
    font = BOLD if bold else FONT
    lines: list[str] = []
    for paragraph in clean(text).split("\n"):
        if not paragraph:
            lines.append("")
            continue
        current = ""
        for word in paragraph.split():
            candidate = (current + " " + word).strip()
            if font.text_length(candidate, fontsize=size) <= width:
                current = candidate
                continue
            if current:
                lines.append(current)
                current = ""
            while font.text_length(word, fontsize=size) > width and len(word) > 1:
                stop = max(1, int(len(word) * width / font.text_length(word, fontsize=size)))
                while stop > 1 and font.text_length(word[:stop], fontsize=size) > width:
                    stop -= 1
                lines.append(word[:stop])
                word = word[stop:]
            current = word
        if current:
            lines.append(current)
    return lines or [""]


def fitted(text: str, rect: pdf.Rect, maximum: float = 11, minimum: float = 7,
           bold: bool = False, align: str = "center") -> dict:
    size = maximum
    while True:
        lines = wrap(text, max(1, rect.width), size, bold)
        line_height = size * 1.18
        if len(lines) * line_height <= rect.height or size <= minimum:
            break
        size = max(minimum, size - .25)
    return {"text": "\n".join(lines), "lines": lines, "size": size,
            "line_height": line_height, "rect": list(rect), "bold": bold,
            "align": align, "overflow": len(lines) * line_height > rect.height + .5}


def intersects(a: pdf.Rect, b: pdf.Rect) -> bool:
    return a.x0 < b.x1 and a.x1 > b.x0 and a.y0 < b.y1 and a.y1 > b.y0


def inflated(rect: pdf.Rect, by: float) -> pdf.Rect:
    return pdf.Rect(rect.x0 - by, rect.y0 - by, rect.x1 + by, rect.y1 + by)


def rect_of(n: dict) -> pdf.Rect:
    return pdf.Rect(n["x"], n["y"], n["x"] + n["w"], n["y"] + n["h"])


def point_on(n: dict, side: str, fraction: float = .5) -> tuple[float, float]:
    r = rect_of(n)
    if kind(n) == "decision":
        fraction = .5  # diamond vertices are its four unambiguous ports
    if side == "left": return r.x0, r.y0 + r.height * fraction
    if side == "right": return r.x1, r.y0 + r.height * fraction
    if side == "top": return r.x0 + r.width * fraction, r.y0
    if side == "bottom": return r.x0 + r.width * fraction, r.y1
    raise ValueError(f"Unknown connector side: {side}")


def outward(p: tuple, side: str, distance: float = 12) -> tuple:
    dx, dy = {"left": (-1, 0), "right": (1, 0), "top": (0, -1), "bottom": (0, 1)}[side]
    return round(p[0] + dx * distance, 3), round(p[1] + dy * distance, 3)


def choose_sides(a: dict, b: dict) -> tuple[str, str]:
    dx = b["x"] + b["w"] / 2 - a["x"] - a["w"] / 2
    dy = b["y"] + b["h"] / 2 - a["y"] - a["h"] / 2
    if abs(dy) > abs(dx) * .85:
        return ("bottom", "top") if dy >= 0 else ("top", "bottom")
    return ("right", "left") if dx >= 0 else ("left", "right")


def simplify(points: list[tuple]) -> list[tuple]:
    result: list[tuple] = []
    for p in points:
        p = (round(p[0], 3), round(p[1], 3))
        if result and p == result[-1]: continue
        while len(result) >= 2:
            a, b = result[-2:]
            if abs((b[0] - a[0]) * (p[1] - b[1]) - (b[1] - a[1]) * (p[0] - b[0])) > .001:
                break
            # Retain a U-turn rather than replacing it by a zero-length line.
            if (b[0]-a[0])*(p[0]-b[0]) + (b[1]-a[1])*(p[1]-b[1]) < 0: break
            result.pop()
        result.append(p)
    return result


def segment_clear(a: tuple, b: tuple, obstacles: list[pdf.Rect]) -> bool:
    if a[0] == b[0]:
        low, high = sorted((a[1], b[1]))
        return not any(r.x0 < a[0] < r.x1 and low < r.y1 and high > r.y0 for r in obstacles)
    if a[1] == b[1]:
        low, high = sorted((a[0], b[0]))
        return not any(r.y0 < a[1] < r.y1 and low < r.x1 and high > r.x0 for r in obstacles)
    return False


def orthogonal(start: tuple, end: tuple, nodes: list[dict], previous: list[list[tuple]]) -> list[tuple]:
    """Visibility-grid A* routing; node rectangles are obstacles, bends cost extra."""
    obstacles = [inflated(rect_of(n), 5) for n in nodes if kind(n) != "text"]
    xs = {30., 1160., start[0], end[0]}
    ys = {110., 705., start[1], end[1]}
    for r in obstacles:
        xs.update((round(r.x0 - 8, 3), round(r.x1 + 8, 3)))
        ys.update((round(r.y0 - 8, 3), round(r.y1 + 8, 3)))
    xs = sorted(x for x in xs if 20 <= x <= 1170)
    ys = sorted(y for y in ys if 105 <= y <= 707)
    xindex, yindex = {x: i for i, x in enumerate(xs)}, {y: i for i, y in enumerate(ys)}
    if start[0] not in xindex or start[1] not in yindex or end[0] not in xindex or end[1] not in yindex:
        return [start, (start[0], end[1]), end]
    initial = (xindex[start[0]], yindex[start[1]], 0)
    queue = [(0., 0., initial)]
    scores, parents = {initial: 0.}, {}
    clear_cache: dict = {}
    finish = None
    while queue:
        _, cost, state = heapq.heappop(queue)
        if cost > scores[state] + .001: continue
        ix, iy, direction = state
        a = xs[ix], ys[iy]
        if a == end:
            finish = state
            break
        for nx, ny, nd in [(ix - 1, iy, 1), (ix + 1, iy, 1), (ix, iy - 1, 2), (ix, iy + 1, 2)]:
            if not (0 <= nx < len(xs) and 0 <= ny < len(ys)): continue
            b = xs[nx], ys[ny]
            key = tuple(sorted((a, b)))
            if key not in clear_cache: clear_cache[key] = segment_clear(a, b, obstacles)
            if not clear_cache[key]: continue
            distance = abs(a[0] - b[0]) + abs(a[1] - b[1])
            penalty = 20 if direction and nd != direction else 0
            # Discourage long shared runs. Crossings remain allowed and are
            # visible in PDF; a congested model still requires editorial layout.
            for route in previous:
                for c, d in zip(route, route[1:]):
                    if a[0] == b[0] == c[0] == d[0] and max(min(a[1], b[1]), min(c[1], d[1])) < min(max(a[1], b[1]), max(c[1], d[1])):
                        penalty += distance * .7
                    elif a[1] == b[1] == c[1] == d[1] and max(min(a[0], b[0]), min(c[0], d[0])) < min(max(a[0], b[0]), max(c[0], d[0])):
                        penalty += distance * .7
            new = nx, ny, nd
            newcost = cost + distance + penalty
            if newcost + .001 >= scores.get(new, float("inf")): continue
            scores[new] = newcost
            parents[new] = state
            heuristic = abs(b[0] - end[0]) + abs(b[1] - end[1])
            heapq.heappush(queue, (newcost + heuristic, newcost, new))
    if finish is None:
        return [start, (start[0], end[1]), end]
    path = []
    while True:
        path.append((xs[finish[0]], ys[finish[1]]))
        if finish == initial: break
        finish = parents[finish]
    return simplify(list(reversed(path)))


def label_layout(text: str, path: list[tuple], node_rects: list[pdf.Rect], used: list[pdf.Rect],
                 specification: dict | None = None, paths: list[list[tuple]] | None = None) -> dict | None:
    if not text: return None
    specification = specification or {}
    size = 9
    longest = max(FONT.text_length(s, fontsize=size) for s in clean(text).split("\n"))
    width = max(28, min(230, longest + 10))
    lines = wrap(text, width - 8, size)
    height = len(lines) * size * 1.18 + 6
    candidates = []
    for segment, (a, b) in enumerate(zip(path, path[1:])):
        length = math.dist(a, b)
        for fraction in (.5, .3, .7):
            x, y = a[0] + (b[0] - a[0]) * fraction, a[1] + (b[1] - a[1]) * fraction
            if abs(a[1] - b[1]) < .01:
                boxes = [pdf.Rect(x-width/2, y-height-3, x+width/2, y-3), pdf.Rect(x-width/2, y+3, x+width/2, y+height+3)]
            else:
                boxes = [pdf.Rect(x+4, y-height/2, x+width+4, y+height/2), pdf.Rect(x-width-4, y-height/2, x-4, y+height/2)]
            for r in boxes:
                inside = r.x0 >= 27 and r.x1 <= 1163 and r.y0 >= 111 and r.y1 <= 705
                hits = sum(intersects(r, inflated(n, 2)) for n in node_rects)
                overlaps = sum(intersects(r, inflated(u, 2)) for u in used)
                crossings = sum(not segment_clear(c, d, [r]) for route in (paths or []) for c, d in zip(route, route[1:]))
                branch = text in ("Yes", "No", "Approve", "Reject", "Return", "Submit", "Cancel")
                # Decision labels belong near their outgoing branch. Long data
                # descriptions favor an unobstructed segment with enough room.
                score = hits * 10000 + overlaps * 1000 + crossings * 5000 + (0 if inside else 50000) - min(length, 300) + abs(fraction-.5)*10 + (segment * 24 if branch else 0)
                candidates.append((score, r, hits, overlaps, crossings, inside))
    if specification.get("labelRect"):
        box = pdf.Rect(specification["labelRect"])
        hits = sum(intersects(box, inflated(n, 2)) for n in node_rects)
        overlaps = sum(intersects(box, inflated(u, 2)) for u in used)
        crossings = sum(not segment_clear(c, d, [box]) for route in (paths or []) for c, d in zip(route, route[1:]))
        inside = box.x0 >= 27 and box.x1 <= 1163 and box.y0 >= 111 and box.y1 <= 705
    else:
        _, box, hits, overlaps, crossings, inside = min(candidates, key=lambda c: c[0])
    used.append(box)
    layout = fitted(text, inflated(box, -3), size, size, align="center")
    layout["background"] = list(box)
    layout["collision"] = bool(hits or overlaps or crossings or not inside or layout["overflow"])
    return layout


def prepare(page: dict) -> dict:
    nodes = page.get("nodes", [])
    byid = {str(n["id"]): n for n in nodes}
    if len(byid) != len(nodes): raise ValueError(f"Duplicate node ID on {page['title']}")
    catalog = not page.get("edges") and "catalog" in page.get("title", "").lower()
    result = {**page, "catalog": catalog, "prepared_nodes": [], "prepared_edges": [], "warnings": []}
    for n in nodes:
        for field in ("x", "y", "w", "h"):
            if not isinstance(n[field], (int, float)) or not math.isfinite(n[field]): raise ValueError(f"Invalid {field}: {n['id']}")
        if n["w"] <= 0 or n["h"] <= 0: raise ValueError(f"Nonpositive node size: {n['id']}")
        box = rect_of(n)
        if not (0 <= box.x0 < box.x1 <= WIDTH and 105 <= box.y0 < box.y1 <= 707):
            result["warnings"].append(f"Node {n['id']} is outside the diagram body")
        inset_x = 14 if kind(n) != "decision" else n["w"] * .22
        inset_y = 6 if kind(n) != "decision" else n["h"] * .21
        textrect = pdf.Rect(box.x0+inset_x, box.y0+inset_y, box.x1-inset_x, box.y1-inset_y)
        maximum = n.get("fontSize", 11 if catalog else (11 if kind(n) != "store" else 10))
        text = fitted(n.get("label", ""), textrect, maximum, 7, kind(n) == "process", "left" if catalog or kind(n) == "note" else "center")
        if text["overflow"]: result["warnings"].append(f"Node {n['id']} text overflows")
        result["prepared_nodes"].append({**n, "kind": kind(n), "text_layout": text})
    for index, a in enumerate(nodes):
        for b in nodes[index+1:]:
            if intersects(rect_of(a), rect_of(b)):
                result["warnings"].append(f"Nodes {a['id']} and {b['id']} overlap")
    groups: dict = defaultdict(list)
    specifications = []
    for index, e in enumerate(page.get("edges", [])):
        if str(e["from"]) not in byid or str(e["to"]) not in byid: raise ValueError(f"Unknown edge endpoint: {e}")
        a, b = byid[str(e["from"])], byid[str(e["to"])]
        sf, st = choose_sides(a, b)
        sf, st = e.get("sideFrom", sf), e.get("sideTo", st)
        for name, side in [(str(e["from"]), sf), (str(e["to"]), st)]:
            groups[(name, side)].append((index, name))
        specifications.append((e, a, b, sf, st))
    previous, labels = [], []
    for index, (e, a, b, sf, st) in enumerate(specifications):
        def fraction(n, side, endpoint):
            if endpoint in e:
                value = float(e[endpoint])
                if not 0 <= value <= 1:
                    raise ValueError(f"Connector attachment {endpoint} is outside node {n['id']}")
                return value
            group = groups[(str(n["id"]), side)]
            if e.get("via") or len(group) == 1: return .5
            position = group.index((index, str(n["id"])))
            return .24 + .52 * position / (len(group)-1)
        start = tuple(round(v, 3) for v in point_on(a, sf, fraction(a, sf, "fractionFrom")))
        end = tuple(round(v, 3) for v in point_on(b, st, fraction(b, st, "fractionTo")))
        if e.get("via"):
            path = simplify([start] + [tuple(map(float, p)) for p in e["via"]] + [end])
        else:
            first, last = outward(start, sf), outward(end, st)
            path = simplify([start] + orthogonal(first, last, nodes, previous) + [end])
        previous.append(path)
        if any(not (20 <= x <= 1170 and 105 <= y <= 707) for x, y in path):
            result["warnings"].append(f"Edge {index+1} is outside the diagram body")
        for p, q in zip(path, path[1:]):
            if p[0] != q[0] and p[1] != q[1]:
                result["warnings"].append(f"Edge {index+1} has a diagonal segment")
            if not segment_clear(p, q, [inflated(rect_of(n), -1) for n in nodes if str(n["id"]) not in (str(a["id"]), str(b["id"]))]):
                result["warnings"].append(f"Edge {index+1} crosses an unrelated node")
        result["prepared_edges"].append({**e, "path": path, "sideFrom": sf, "sideTo": st, "text_layout": None})
    for index, e in enumerate(result["prepared_edges"]):
        label = label_layout(clean(e.get("label", "")), e["path"], [rect_of(n) for n in nodes], labels, e, previous)
        if label and label["collision"]: result["warnings"].append(f"Edge {index+1} label needs layout review")
        e["text_layout"] = label
    result["notes_layout"] = fitted("\n".join("\u2022 " + clean(n) for n in page.get("notes", [])), pdf.Rect(47, 720, 1143, 801), 10, 8.5, align="left")
    if result["notes_layout"]["overflow"]: result["warnings"].append("Notes overflow")
    return result


def draw_text(page: pdf.Page, layout: dict, color: str = INK) -> None:
    rect = pdf.Rect(layout["rect"])
    size, step = layout["size"], layout["line_height"]
    font = BOLD if layout["bold"] else FONT
    name = "pkbold" if layout["bold"] else "pkregular"
    total = len(layout["lines"]) * step
    y = rect.y0 + max(0, (rect.height-total)/2) + size * .95
    for line in layout["lines"]:
        length = font.text_length(line, fontsize=size)
        x = rect.x0 if layout["align"] == "left" else rect.x0 + (rect.width-length)/2
        page.insert_text((x, y), line, fontsize=size, fontname=name, color=rgb(color))
        y += step


def polygon(node: dict) -> list[tuple]:
    r = rect_of(node)
    if kind(node) == "decision": return [(r.x0+r.width/2,r.y0),(r.x1,r.y0+r.height/2),(r.x0+r.width/2,r.y1),(r.x0,r.y0+r.height/2)]
    if kind(node) == "data": return [(r.x0+r.width*.12,r.y0),(r.x1,r.y0),(r.x1-r.width*.12,r.y1),(r.x0,r.y1)]
    if kind(node) == "document": return [(r.x0,r.y0),(r.x1,r.y0),(r.x1,r.y1-r.height*.10),(r.x0+r.width*.75,r.y1-r.height*.02),(r.x0+r.width*.5,r.y1-r.height*.12),(r.x0+r.width*.25,r.y1-r.height*.20),(r.x0,r.y1-r.height*.10)]
    if kind(node) == "port": return [(r.x0,r.y0),(r.x1,r.y0),(r.x1,r.y0+r.height*.72),(r.x0+r.width*.5,r.y1),(r.x0,r.y0+r.height*.72)]
    return [(r.x0,r.y0),(r.x1,r.y0),(r.x1,r.y1),(r.x0,r.y1)]


def pdf_node(page: pdf.Page, node: dict, catalog: bool = False) -> None:
    r, k = rect_of(node), node["kind"]
    fill, line = STYLES[k]
    if k == "store" and not catalog:
        page.draw_rect(r, color=None, fill=rgb(fill))
        page.draw_polyline([(r.x1,r.y0),(r.x0,r.y0),(r.x0,r.y1),(r.x1,r.y1)],color=rgb(line),width=1.3)
    elif k in ("decision", "data", "document", "port"):
        page.draw_polyline(polygon(node), color=rgb(line),fill=rgb(fill),width=1.3,closePath=True)
    elif k != "text":
        page.draw_rect(r, color=rgb(line), fill=rgb(fill), width=1.3,
                       radius=.45 if k == "terminator" else (.10 if k == "process" else None),
                       dashes="[4 3]" if k == "note" else None)
    draw_text(page, node["text_layout"])


def pdf_arrow(page: pdf.Page, edge: dict) -> None:
    pts = edge["path"]
    if len(pts) < 2: return
    # A white casing keeps unrelated crossing connectors visually separate.
    page.draw_polyline(pts, color=(1, 1, 1), width=3.6)
    page.draw_polyline(pts, color=rgb(LINE), width=1.15)
    a, b = pts[-2], pts[-1]
    angle = math.atan2(b[1]-a[1],b[0]-a[0])
    length, half = 6.5, 2.6
    back = b[0]-length*math.cos(angle), b[1]-length*math.sin(angle)
    tip1 = back[0]-half*math.sin(angle),back[1]+half*math.cos(angle)
    tip2 = back[0]+half*math.sin(angle),back[1]-half*math.cos(angle)
    page.draw_polyline([tip1,b,tip2],color=rgb(LINE),fill=rgb(LINE),width=.7,closePath=True)


def render_pdf(model: dict, pages: list[dict], path: Path) -> None:
    document = pdf.open()
    for index, data in enumerate(pages, 1):
        page = document.new_page(width=WIDTH, height=HEIGHT)
        page.insert_font(fontname="pkregular",fontfile=str(REGULAR_PATH) if REGULAR_PATH else None,fontbuffer=None if REGULAR_PATH else FONT.buffer)
        page.insert_font(fontname="pkbold",fontfile=str(BOLD_PATH) if BOLD_PATH else None,fontbuffer=None if BOLD_PATH else BOLD.buffer)
        page.draw_rect(pdf.Rect(0,0,WIDTH,7),color=None,fill=rgb(BURGUNDY))
        draw_text(page,fitted(model.get("title","PK DOCUMENT TRACKING SYSTEM  /  PROPOSED SYSTEM DESIGN"),pdf.Rect(45,19,1145,34),9,8,True,"left"),MUTED)
        draw_text(page,fitted(data["title"],pdf.Rect(45,39,1145,69),24,16,True,"left"))
        draw_text(page,fitted(data.get("subtitle",""),pdf.Rect(45,70,1145,90),10,8,False,"left"),MUTED)
        present = []
        for node in data["prepared_nodes"]:
            if node["kind"] not in present and node["kind"] not in ("text","note"): present.append(node["kind"])
        names={"entity":"External participant","process":"Process / activity","store":"Data store","decision":"Decision","terminator":"Start / end","data":"Input / output","document":"Document","port":"Cross-sheet reference"}
        draw_text(page,fitted("   |   ".join(names[k] for k in present) + ("   |   Arrows: " + ("data flow" if "dfd" in path.stem.lower() else "direction of activity") if data["prepared_edges"] else ""),pdf.Rect(45,92,1145,107),8.5,8,False,"left"),MUTED)
        for edge in data["prepared_edges"]: pdf_arrow(page,edge)
        for edge in data["prepared_edges"]:
            if edge["text_layout"]:
                page.draw_rect(pdf.Rect(edge["text_layout"]["background"]),color=None,fill=(1,1,1))
                draw_text(page,edge["text_layout"],MUTED)
        for node in data["prepared_nodes"]: pdf_node(page,node,data["catalog"])
        page.draw_line((45,709),(1145,709),color=rgb("#CDD8E3"),width=.7)
        draw_text(page,data["notes_layout"],MUTED)
        draw_text(page,fitted(f"{index} / {len(pages)}",pdf.Rect(1103,807,1145,828),8,7,False,"center"),MUTED)
    document.set_metadata({"title":model.get("title",path.stem),"author":"PK Document Tracking System","subject":"Proposed document control and system operations"})
    document.save(path,garbage=4,deflate=True)
    document.close()


def sub(parent: ET.Element, tag: str, **attrs) -> ET.Element:
    return ET.SubElement(parent,f"{{{VISIO}}}{tag}",{k:str(v) for k,v in attrs.items()})


def cell(parent: ET.Element, name: str, value: Any, formula: str | None = None, unit: str | None = None) -> ET.Element:
    attrs={"N":name,"V":f"{value:.10g}" if isinstance(value,(int,float)) else str(value)}
    if formula is not None: attrs["F"]=formula
    if unit is not None: attrs["U"]=unit
    return sub(parent,"Cell",**attrs)


def text_cells(shape: ET.Element, layout: dict, color: str = INK) -> None:
    sec=sub(shape,"Section",N="Character")
    row=sub(sec,"Row",IX=0)
    for name,value in [("Font","Arial"),("Size",layout["size"]/72),("Color",color),("Style",1 if layout["bold"] else 0),("LangID","en-US")]: cell(row,name,value)
    sec=sub(shape,"Section",N="Paragraph")
    row=sub(sec,"Row",IX=0)
    cell(row,"HorzAlign",0 if layout["align"]=="left" else 1)
    cell(row,"SpLine",-1.18)
    for side in ("LeftMargin","RightMargin","TopMargin","BottomMargin"): cell(shape,side,0)
    cell(shape,"VerticalAlign",1)
    text=sub(shape,"Text")
    text.text=layout["text"]


def geometry(shape: ET.Element, points: list[tuple], nofill: bool = False, relative: bool = True) -> None:
    sec=sub(shape,"Section",N="Geometry",IX=0)
    cell(sec,"NoFill",int(nofill)); cell(sec,"NoLine",0); cell(sec,"NoShow",0)
    for ix,(x,y) in enumerate(points,1):
        typ=("RelMoveTo" if ix==1 else "RelLineTo") if relative else ("MoveTo" if ix==1 else "LineTo")
        row=sub(sec,"Row",T=typ,IX=ix)
        cell(row,"X",x); cell(row,"Y",y)


def vshape(parent: ET.Element, sid: int, name: str, r: pdf.Rect, layout: dict,
           nodekind: str = "text", catalog: bool = False, ports: list[tuple] | None = None) -> ET.Element:
    shape=sub(parent,"Shape",ID=sid,NameU=name,Name=name,Type="Shape",LineStyle=0,FillStyle=0,TextStyle=0)
    w,h=r.width/72,r.height/72
    for n,v,f in [("PinX",(r.x0+r.width/2)/72,None),("PinY",(HEIGHT-r.y0-r.height/2)/72,None),("Width",w,None),("Height",h,None),("LocPinX",w/2,"Width*0.5"),("LocPinY",h/2,"Height*0.5"),("Angle",0,None)]: cell(shape,n,v,f)
    fill,line=STYLES[nodekind]
    for n,v in [("LineColor",line),("LineWeight",1.3/72),("LinePattern",0 if nodekind=="text" else (2 if nodekind=="note" else 1)),("FillForegnd",fill),("FillPattern",0 if nodekind=="text" else 1),("ShdwPattern",0),("BeginArrow",0),("EndArrow",0)]: cell(shape,n,v)
    if nodekind in ("process","terminator"): cell(shape,"Rounding",min(h/2,.15 if nodekind=="process" else h/2))
    if nodekind=="decision": points=[(.5,0),(1,.5),(.5,1),(0,.5),(.5,0)]
    elif nodekind=="data": points=[(0,0),(.88,0),(1,1),(.12,1),(0,0)]
    elif nodekind=="document": points=[(0,.1),(.25,.2),(.5,.12),(.75,.02),(1,.1),(1,1),(0,1),(0,.1)]
    elif nodekind=="port": points=[(0,1),(1,1),(1,.28),(.5,0),(0,.28),(0,1)]
    elif nodekind=="store" and not catalog: points=[(1,1),(0,1),(0,0),(1,0)]
    else: points=[(0,0),(1,0),(1,1),(0,1),(0,0)]
    geometry(shape,points,nodekind=="store" and not catalog)
    if ports:
        sec=sub(shape,"Section",N="Connection")
        for ix,(x,y) in enumerate(ports):
            row=sub(sec,"Row",T="Connection",IX=ix)
            cell(row,"X",(x-r.x0)/72,f"Width*{(x-r.x0)/r.width:.10g}")
            cell(row,"Y",(r.y1-y)/72,f"Height*{(r.y1-y)/r.height:.10g}")
            cell(row,"DirX",0); cell(row,"DirY",0); cell(row,"Type",0)
    tr=pdf.Rect(layout["rect"])
    for n,v in [("TxtPinX",(tr.x0+tr.width/2-r.x0)/72),("TxtPinY",(r.y1-tr.y0-tr.height/2)/72),("TxtWidth",tr.width/72),("TxtHeight",tr.height/72),("TxtLocPinX",tr.width/144),("TxtLocPinY",tr.height/144)]: cell(shape,n,v)
    text_cells(shape,layout)
    return shape


def connector(parent: ET.Element, sid: int, edge: dict, a: int, b: int, pa: int, pb: int) -> ET.Element:
    shape=sub(parent,"Shape",ID=sid,NameU=f"Connector.{sid}",Name=f"Connector.{sid}",Type="Shape",LineStyle=0,FillStyle=0,TextStyle=0)
    cell(shape,"ObjType",2)
    points=[(x/72,(HEIGHT-y)/72) for x,y in edge["path"]]
    begin,end=points[0],points[-1]
    dx,dy=end[0]-begin[0],end[1]-begin[1]
    formula_a=f"PAR(PNT(Sheet.{a}!Connections.X{pa+1},Sheet.{a}!Connections.Y{pa+1}))"
    formula_b=f"PAR(PNT(Sheet.{b}!Connections.X{pb+1},Sheet.{b}!Connections.Y{pb+1}))"
    for name,value,formula in [("BeginX",begin[0],formula_a),("BeginY",begin[1],formula_a),("EndX",end[0],formula_b),("EndY",end[1],formula_b),("PinX",(begin[0]+end[0])/2,"(BeginX+EndX)*0.5"),("PinY",(begin[1]+end[1])/2,"(BeginY+EndY)*0.5"),("Width",dx,"EndX-BeginX"),("Height",dy,"EndY-BeginY"),("LocPinX",dx/2,"Width*0.5"),("LocPinY",dy/2,"Height*0.5"),("Angle",0,None)]: cell(shape,name,value,formula)
    for name,value in [("LineColor",LINE),("LineWeight",1.05/72),("LinePattern",1),("FillPattern",0),("BeginArrow",0),("EndArrow",4),("EndArrowSize",1),("GlueType",2),("ConFixedCode",2),("ShapeRouteStyle",1)]: cell(shape,name,value)
    cell(shape,"BegTrigger",2,f"_XFTRIGGER(Sheet.{a}!EventXFMod)")
    cell(shape,"EndTrigger",2,f"_XFTRIGGER(Sheet.{b}!EventXFMod)")
    sec=sub(shape,"Section",N="Geometry",IX=0)
    cell(sec,"NoFill",1); cell(sec,"NoLine",0); cell(sec,"NoShow",0)
    for ix,(x,y) in enumerate(points,1):
        row=sub(sec,"Row",T="MoveTo" if ix==1 else "LineTo",IX=ix)
        cell(row,"X",x-begin[0],"Width" if ix==len(points) else None)
        cell(row,"Y",y-begin[1],"Height" if ix==len(points) else None)
    if edge.get("text_layout"):
        layout=edge["text_layout"]
        tr=pdf.Rect(layout["rect"])
        for name,value in [("TxtPinX",(tr.x0+tr.width/2)/72-begin[0]),("TxtPinY",(HEIGHT-tr.y0-tr.height/2)/72-begin[1]),("TxtWidth",tr.width/72),("TxtHeight",tr.height/72),("TxtLocPinX",tr.width/144),("TxtLocPinY",tr.height/144),("TextBkgnd",1),("TextBkgndTrans",0)]: cell(shape,name,value)
        text_cells(shape,layout,MUTED)
    return shape


def xmlbytes(root: ET.Element) -> bytes:
    # Sheet_Type cells precede its sections. Drawing helpers can add text-box
    # cells after geometry, so normalize once before serializing the package.
    # Keep the final value for a repeated cell name (e.g. inherited margins).
    for element in root.iter():
        tag=element.tag.rsplit("}",1)[-1]
        if tag not in ("Shape","StyleSheet","PageSheet"): continue
        cells={child.get("N"):child for child in element if child.tag==f"{{{VISIO}}}Cell"}
        others=[child for child in element if child.tag!=f"{{{VISIO}}}Cell"]
        order={"Trigger":0,"Section":1,"Shapes":2,"Text":3,"Data1":4,"Data2":5,"Data3":6,"ForeignData":7}
        others.sort(key=lambda child:order.get(child.tag.rsplit("}",1)[-1],8))
        element[:]=list(cells.values())+others
    return ET.tostring(root,encoding="utf-8",xml_declaration=True)


def relationship(items: list[tuple[str,str,str]]) -> bytes:
    root=ET.Element(f"{{{PKGREL}}}Relationships")
    for rid,typ,target in items: ET.SubElement(root,f"{{{PKGREL}}}Relationship",Id=rid,Type=typ,Target=target)
    return xmlbytes(root)


def render_visio(model: dict, pages: list[dict], path: Path) -> dict:
    document=ET.Element(f"{{{VISIO}}}VisioDocument",{"{http://www.w3.org/XML/1998/namespace}space":"preserve"})
    settings=sub(document,"DocumentSettings",TopPage=0,DefaultTextStyle=0,DefaultLineStyle=0,DefaultFillStyle=0)
    sub(settings,"GlueSettings").text="9"
    sub(settings,"SnapSettings").text="295"
    faces=sub(document,"FaceNames")
    sub(faces,"FaceName",NameU="Arial")
    sheets=sub(document,"StyleSheets")
    style=sub(sheets,"StyleSheet",ID=0,NameU="No Style",Name="No Style",IsCustomNameU=1,IsCustomName=1)
    for name,value in [("EnableLineProps",1),("EnableFillProps",1),("EnableTextProps",1),("LineWeight",1/72),("LineColor",INK),("LinePattern",1),("FillForegnd","#FFFFFF"),("FillBkgnd","#FFFFFF"),("FillPattern",1),("ShdwPattern",0),("BeginArrow",0),("EndArrow",0),("VerticalAlign",1)]: cell(style,name,value)
    text_cells(style,{"size":11,"bold":False,"align":"center","text":""})
    # Style sheets contain Character/Paragraph sections, but do not carry Text.
    style.remove(style.find(f"{{{VISIO}}}Text"))
    manifest=ET.Element(f"{{{CT}}}Types")
    for ext,mime in [("rels","application/vnd.openxmlformats-package.relationships+xml"),("xml","application/xml")]: ET.SubElement(manifest,f"{{{CT}}}Default",Extension=ext,ContentType=mime)
    for part,mime in [("/visio/document.xml","application/vnd.ms-visio.drawing.main+xml"),("/visio/pages/pages.xml","application/vnd.ms-visio.pages+xml"),("/docProps/core.xml","application/vnd.openxmlformats-package.core-properties+xml"),("/docProps/app.xml","application/vnd.openxmlformats-officedocument.extended-properties+xml")]+[(f"/visio/pages/page{i}.xml","application/vnd.ms-visio.page+xml") for i in range(1,len(pages)+1)]: ET.SubElement(manifest,f"{{{CT}}}Override",PartName=part,ContentType=mime)
    page_list=ET.Element(f"{{{VISIO}}}Pages")
    core_ns="http://schemas.openxmlformats.org/package/2006/metadata/core-properties"
    dc_ns="http://purl.org/dc/elements/1.1/"
    app_ns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties"
    core=ET.Element(f"{{{core_ns}}}coreProperties")
    for name,value in [("title",model.get("title",path.stem)),("creator","PK Document Tracking System"),("subject","Proposed document control and system operations")]:
        ET.SubElement(core,f"{{{dc_ns}}}{name}").text=value
    app=ET.Element(f"{{{app_ns}}}Properties")
    ET.SubElement(app,f"{{{app_ns}}}Company").text="PK Document Tracking System"
    parts={"[Content_Types].xml":xmlbytes(manifest),"_rels/.rels":relationship([("rId1",VREL+"document","visio/document.xml"),("rId2",PKGREL+"metadata/core-properties","docProps/core.xml"),("rId3",REL+"extended-properties","docProps/app.xml")]),"docProps/core.xml":xmlbytes(core),"docProps/app.xml":xmlbytes(app),"visio/document.xml":xmlbytes(document),"visio/_rels/document.xml.rels":relationship([("rId1",VREL+"pages","pages/pages.xml")]),"visio/pages/_rels/pages.xml.rels":relationship([(f"rId{i}",VREL+"page",f"page{i}.xml") for i in range(1,len(pages)+1)])}
    total_nodes,total_edges=0,0
    for index,data in enumerate(pages,1):
        pname=re.sub(r"[\\/:*?\[\]]"," ",data["title"])[:60]
        p=sub(page_list,"Page",ID=index-1,NameU=f"Page-{index}",Name=pname,IsCustomName=1,ViewScale=.7,ViewCenterX=WIDTH/144,ViewCenterY=HEIGHT/144)
        ps=sub(p,"PageSheet",LineStyle=0,FillStyle=0,TextStyle=0)
        for name,value in [("PageWidth",WIDTH/72),("PageHeight",HEIGHT/72),("PageScale",1),("DrawingScale",1),("DrawingScaleType",0),("DrawingSizeType",0),("PrintPageOrientation",2),("PaperKind",8),("PageLeftMargin",.25),("PageRightMargin",.25),("PageTopMargin",.25),("PageBottomMargin",.25)]: cell(ps,name,value)
        sub(p,"Rel",**{f"{{{REL}}}id":f"rId{index}"})
        contents=ET.Element(f"{{{VISIO}}}PageContents",{"{http://www.w3.org/XML/1998/namespace}space":"preserve"})
        shapes=sub(contents,"Shapes")
        nodes=data["prepared_nodes"]
        ids={str(n["id"]):i for i,n in enumerate(nodes,1)}
        ports: dict=defaultdict(list)
        endpoints=[]
        for edge in data["prepared_edges"]:
            a,b=str(edge["from"]),str(edge["to"])
            pa,pb=len(ports[a]),len(ports[b])
            ports[a].append(edge["path"][0]); ports[b].append(edge["path"][-1])
            endpoints.append((ids[a],ids[b],pa,pb))
        connects=sub(contents,"Connects")
        for offset,(edge,endpoint) in enumerate(zip(data["prepared_edges"],endpoints),len(nodes)+1):
            a,b,pa,pb=endpoint
            connector(shapes,offset,edge,a,b,pa,pb)
            for cellname,part,target,port in [("BeginX",9,a,pa),("EndX",12,b,pb)]: sub(connects,"Connect",FromSheet=offset,FromCell=cellname,FromPart=part,ToSheet=target,ToCell=f"Connections.X{port+1}",ToPart=100+port)
        for n in nodes:
            vshape(shapes,ids[str(n["id"])],str(n["id"]),rect_of(n),n["text_layout"],n["kind"],data["catalog"],ports[str(n["id"])])
        nextid=len(nodes)+len(data["prepared_edges"])+1
        names={"entity":"External participant","process":"Process / activity","store":"Data store","decision":"Decision","terminator":"Start / end","data":"Input / output","document":"Document","port":"Cross-sheet reference"}
        present=list(dict.fromkeys(n["kind"] for n in nodes if n["kind"] not in ("text","note")))
        legend="   |   ".join(names[k] for k in present)+( "   >   Directed connection" if data["prepared_edges"] else "")
        texts=[("Document title",fitted(model.get("title","PK DOCUMENT TRACKING SYSTEM / PROPOSED SYSTEM DESIGN"),pdf.Rect(45,19,1145,34),9,8,True,"left")),("Page title",fitted(data["title"],pdf.Rect(45,39,1145,69),24,16,True,"left")),("Subtitle",fitted(data.get("subtitle",""),pdf.Rect(45,70,1145,90),10,8,False,"left")),("Legend",fitted(legend,pdf.Rect(45,92,1145,107),8.5,8,False,"left")),("Notes",data["notes_layout"]),("Page number",fitted(f"{index} / {len(pages)}",pdf.Rect(1103,807,1145,828),8,7,False,"center"))]
        for name,layout in texts:
            vshape(shapes,nextid,name,pdf.Rect(layout["rect"]),layout)
            nextid+=1
        parts[f"visio/pages/page{index}.xml"]=xmlbytes(contents)
        total_nodes+=len(nodes); total_edges+=len(data["prepared_edges"])
    parts["visio/pages/pages.xml"]=xmlbytes(page_list)
    with ZipFile(path,"w",ZIP_DEFLATED) as archive:
        for name,data in parts.items(): archive.writestr(name,data)
    result=verify_visio(path)
    result.update({"diagram_nodes":total_nodes,"native_connectors":total_edges})
    return result


def verify_visio(path: Path) -> dict:
    """Check package graph, native geometry, shape IDs and connector glue."""
    with ZipFile(path) as z:
        if z.testzip(): raise ValueError("VSDX ZIP integrity failed")
        names=set(z.namelist())
        trees={n:ET.fromstring(z.read(n)) for n in names if n.endswith(".xml") or n.endswith(".rels")}
        for name,tree in trees.items():
            if not name.endswith(".rels"): continue
            owner_dir=posixpath.dirname(name).removesuffix("/_rels")
            if name=="_rels/.rels": owner_dir=""
            for rel in tree:
                if rel.get("TargetMode")=="External": continue
                target=posixpath.normpath(posixpath.join(owner_dir,rel.attrib["Target"]))
                if target not in names: raise ValueError(f"Missing relationship target {target}")
        page_names=sorted(n for n in names if re.fullmatch(r"visio/pages/page\d+\.xml",n))
        connectors=0
        for name in page_names:
            tree=trees[name]
            shapes={s.attrib["ID"]:s for s in tree.findall(f"{{{VISIO}}}Shapes/{{{VISIO}}}Shape")}
            if not shapes: raise ValueError(f"No native shapes in {name}")
            for sid,s in shapes.items():
                if s.find(f"{{{VISIO}}}Section[@N='Geometry']") is None: raise ValueError(f"Missing geometry {name}:{sid}")
            glue=tree.findall(f"{{{VISIO}}}Connects/{{{VISIO}}}Connect")
            for c in glue:
                if c.attrib["FromSheet"] not in shapes or c.attrib["ToSheet"] not in shapes: raise ValueError("Glue references an unknown shape")
                target=shapes[c.attrib["ToSheet"]]
                row=int(c.attrib["ToPart"])-100
                if target.find(f"{{{VISIO}}}Section[@N='Connection']/{{{VISIO}}}Row[@IX='{row}']") is None: raise ValueError("Glue references an unknown connection row")
            active=[s for s in shapes.values() if s.find(f"{{{VISIO}}}Cell[@N='BeginX']") is not None]
            for s in active:
                sid=s.attrib["ID"]
                if len([g for g in glue if g.attrib["FromSheet"]==sid])!=2: raise ValueError(f"Connector {sid} lacks two native glued endpoints")
                for field in ("BeginX","BeginY","EndX","EndY"):
                    c=s.find(f"{{{VISIO}}}Cell[@N='{field}']")
                    if c is None or "Connections." not in c.get("F",""): raise ValueError(f"Connector {sid} lacks endpoint formula")
            connectors+=len(active)
        return {"package_parts":len(names),"pages":len(page_names),"checked_glued_connectors":connectors,"package_graph_valid":True,"native_application_open_verified":False}


def main() -> None:
    parser=argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--model",type=Path,required=True)
    parser.add_argument("--output-base",type=Path,required=True)
    parser.add_argument("--visio",action="store_true",help="Also create native editable .vsdx")
    args=parser.parse_args()
    model=json.loads(args.model.read_text(encoding="utf-8-sig"))
    if not isinstance(model.get("pages"),list) or not model["pages"]: raise ValueError("Model must contain a nonempty pages list")
    pages=[prepare(p) for p in model["pages"]]
    args.output_base.parent.mkdir(parents=True,exist_ok=True)
    pdfpath=args.output_base.with_suffix(".pdf")
    render_pdf(model,pages,pdfpath)
    with pdf.open(pdfpath) as check:
        if len(check)!=len(pages): raise ValueError("PDF page count mismatch")
        if any(not p.get_text().strip() for p in check): raise ValueError("PDF contains a blank text page")
    result={"pdf":str(pdfpath),"pages":len(pages),"warnings":{p["title"]:p["warnings"] for p in pages if p["warnings"]}}
    if args.visio:
        vpath=args.output_base.with_suffix(".vsdx")
        result["vsdx"]=str(vpath)
        result["visio_validation"]=render_visio(model,pages,vpath)
    print(json.dumps(result,ensure_ascii=False,indent=2))


if __name__=="__main__": main()
