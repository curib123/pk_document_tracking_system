"""Read-only structural and content checks for the generated documentation.

Run from the repository root after building the files. These checks complement
visual inspection and native PowerPoint opening; they do not substitute for
opening Visio in an installed Visio application.
"""
from __future__ import annotations

import json
from pathlib import Path
import posixpath
import re
from xml.etree import ElementTree as ET
from zipfile import ZipFile

import pymupdf

ROOT = Path(__file__).resolve().parents[2]
DOCS = ROOT / 'docs/system-documentation'
NS = {'p': 'http://schemas.openxmlformats.org/presentationml/2006/main',
      'a': 'http://schemas.openxmlformats.org/drawingml/2006/main',
      'v': 'http://schemas.microsoft.com/office/visio/2012/main'}


def public_wording(text, name):
    text = text.replace('\u00a0', ' ').replace('\u00ad', '-')
    assert not re.search(r'\b(OpenAI|ChatGPT|Codex|python-pptx|Steve Canny|AI[- ]generated)\b', text, re.I), f'{name}: inherited attribution'
    for phrase in ['application/', 'public/assets/', 'Source references:', 'Interface evidence',
                   'implemented system', 'actual application', 'captured request status', 'Source-backed']:
        assert phrase.lower() not in text.lower(), f'{name}: internal or retrospective wording: {phrase}'


def package(path: Path) -> dict:
    with ZipFile(path) as z:
        assert z.testzip() is None, f'Corrupt ZIP: {path.name}'
        names = set(z.namelist())
        xml = {name: ET.fromstring(z.read(name)) for name in names
               if name.endswith(('.xml', '.rels'))}
        metadata = '\n'.join(ET.tostring(root, encoding='unicode') for name, root in xml.items() if name.startswith('docProps/'))
        public_wording(metadata, path.name + ' metadata')
        for name, root in xml.items():
            if not name.endswith('.rels'):
                continue
            parent = '' if name == '_rels/.rels' else str(Path(name).parent.parent).replace('\\', '/')
            for rel in root:
                if rel.get('TargetMode') == 'External':
                    continue
                target = rel.get('Target', '')
                resolved = posixpath.normpath(posixpath.join(parent, target)).lstrip('/')
                assert resolved in names, f'{path.name}: missing target {name} -> {resolved}'
        if path.suffix == '.vsdx':
            pages = [r for n, r in xml.items() if re.fullmatch(r'visio/pages/page\d+\.xml', n)]
            nodes = connectors = 0
            for page in pages:
                shapes = page.findall('v:Shapes/v:Shape', NS)
                ids = {s.get('ID') for s in shapes}
                assert len(ids) == len(shapes), 'Duplicate Visio shape ID'
                for shape in shapes:
                    cells = {c.get('N'): c for c in shape.findall('v:Cell', NS)}
                    if 'BeginX' in cells:
                        connectors += 1
                        assert all(c in cells for c in ['BeginX', 'BeginY', 'EndX', 'EndY'])
                        assert shape.find('v:Section[@N="Geometry"]', NS) is not None
                    else:
                        nodes += 1
                for connection in page.findall('v:Connects/v:Connect', NS):
                    assert connection.get('FromSheet') in ids and connection.get('ToSheet') in ids
            assert nodes > 10 and connectors > 5
            assert not any('/media/' in name for name in names), 'Diagram unexpectedly rasterized'
            return {'file': path.name, 'pages': len(pages), 'native_shapes': nodes,
                    'native_connectors': connectors, 'relationships': 'valid'}
        slides = [r for n, r in xml.items() if re.fullmatch(r'ppt/slides/slide\d+\.xml', n)]
        pics = sum(len(s.findall('.//p:pic', NS)) for s in slides)
        arrows = sum(len(s.findall('.//a:headEnd', NS)) + len(s.findall('.//a:tailEnd', NS)) for s in slides)
        text = '\n'.join(t.text or '' for s in slides for t in s.findall('.//a:t', NS))
        notes = '\n'.join(t.text or '' for n, s in xml.items() if re.fullmatch(r'ppt/notesSlides/notesSlide\d+\.xml', n) for t in s.findall('.//a:t', NS))
        public_wording(text + '\n' + notes, path.name)
        assert 'PROPOSED USER GUIDE' in text
        assert 'EXPECTED:' in text and 'PLANNED REQUEST SCENARIOS' in text
        app = xml['docProps/app.xml']
        app_ns = '{http://schemas.openxmlformats.org/officeDocument/2006/extended-properties}'
        assert app.find(app_ns + 'Slides').text == str(len(slides))
        assert app.find(app_ns + 'PresentationFormat').text == 'Widescreen (16:9)'
        assert not any(marker in text for marker in ['\u00e2\u2020', '\u00e2\u20ac', '\u00c3\u2014']), 'Corrupted text encoding in PowerPoint'
        assert '\ufffd' not in text and ' ? ' not in text, 'Broken presentation character'
        assert len(slides) >= 30, 'User guide is missing expected walkthrough coverage'
        assert pics >= 25 and arrows >= 25, 'User guide needs screenshots and arrow annotations'
        for word in ['Softcopy', 'Hardcopy', 'Transfer', 'Access', 'Assign', 'Return', 'Reject']:
            assert word.lower() in text.lower(), f'Missing guide topic: {word}'
        assert 'Synthetic' in text or 'sample' in text.lower()
        return {'file': path.name, 'slides': len(slides), 'pictures': pics,
                'editable_arrow_elements': arrows, 'relationships': 'valid'}


def pdf(path: Path) -> dict:
    with pymupdf.open(path) as doc:
        public_wording('\n'.join(p.get_text() for p in doc) + '\n' + str(doc.metadata), path.name)
        for i, page in enumerate(doc):
            assert page.get_text().strip(), f'Blank PDF page: {path.name} {i + 1}'
            for block in page.get_text('dict')['blocks']:
                for line in block.get('lines', []):
                    for span in line.get('spans', []):
                        box = pymupdf.Rect(span['bbox'])
                        assert box.x0 >= -1 and box.y0 >= -1
                        assert box.x1 <= page.rect.width + 1 and box.y1 <= page.rect.height + 1
        return {'file': path.name, 'pages': len(doc), 'page_text_bounds': 'valid'}


def sources() -> dict:
    checked = set()
    for path in (DOCS / 'source').glob('*.json'):
        model = json.loads(path.read_text(encoding='utf-8'))
        def walk(value):
            if isinstance(value, dict):
                for key, item in value.items():
                    if key == 'sources':
                        for source in item:
                            name = re.split(r':\d', source, maxsplit=1)[0]
                            if name.startswith(('application/', 'public/', 'database/', 'tools/')):
                                assert (ROOT / name).is_file(), f'Source missing: {source}'
                                checked.add(name)
                    else:
                        walk(item)
            elif isinstance(value, list):
                for item in value:
                    walk(item)
        walk(model)
    return {'source_files_checked': len(checked)}


def main():
    results = []
    for name in ['PK_DTS_Proposed_Process_Flow.vsdx', 'PK_DTS_Flowchart.vsdx', 'PK_DTS_User_Guide.pptx']:
        path = DOCS / name
        assert path.is_file(), f'Missing deliverable: {name}'
        results.append(package(path))
    for name in ['PK_DTS_Proposed_Process_Flow.pdf', 'PK_DTS_DFD.pdf', 'PK_DTS_Flowchart.pdf']:
        path = DOCS / name
        assert path.is_file(), f'Missing deliverable: {name}'
        results.append(pdf(path))
    guide_pdf = DOCS / 'PK_DTS_User_Guide.pdf'
    results.append(pdf(guide_pdf))
    with pymupdf.open(guide_pdf) as guide:
        assert len(guide) == next(r['slides'] for r in results if 'slides' in r), 'Presentation/PDF page mismatch'
    manifest = json.loads((DOCS / 'PK_DTS_User_Guide.slides.json').read_text(encoding='utf-8'))
    callouts = 0
    for slide in manifest['slides']:
        if 'image_box' not in slide:
            continue
        ix, iy, iw, ih = slide['image_box']
        for marker in slide['callouts']:
            lx, ly, lw, lh = marker['label_box']
            assert lx + lw < ix, f'Label covers screenshot: {slide["title"]}'
            tx, ty = marker['target']
            assert ix - .001 <= tx <= ix + iw + .001 and iy - .001 <= ty <= iy + ih + .001
            mx, my = marker['marker']
            assert 0 <= mx - .11 and mx + .11 <= 13.3334 and 1.10 <= my - .11 and my + .11 <= 7.0
            for control in slide['callouts']:
                bx, by, bw, bh = control['control_box']
                assert not (mx - .11 < bx + bw and mx + .11 > bx and my - .11 < by + bh and my + .11 > by), f'Marker covers control: {slide["title"]}'
                if control is marker:
                    continue
                sx, sy = marker['arrow_start']
                for fraction in (n / 10 for n in range(11)):
                    px, py = sx + (tx - sx) * fraction, sy + (ty - sy) * fraction
                    assert not (bx + .002 < px < bx + bw - .002 and by + .002 < py < by + bh - .002), f'Arrow crosses another control: {slide["title"]}'
            callouts += 1
    results.append({'guide_callouts_checked': callouts, 'labels_outside_screenshots': True,
                    'screen_pointer_targets_in_crops': True, 'screen_pointers_clear_other_annotated_controls': True})
    results.append(sources())
    flows = json.loads((DOCS / 'source/sample-flows.json').read_text(encoding='utf-8'))['flows']
    expected = {'softcopy_create', 'softcopy_revise', 'softcopy_cancel',
                'hardcopy_create', 'hardcopy_update', 'access', 'assignment', 'transfer', 'disposal'}
    assert len(flows) == 9 and {f['type'] for f in flows} == expected
    assert all(f.get('final_status') == 'completed' for f in flows)
    transfer = next(f for f in flows if f['type'] == 'transfer')
    assert transfer['status'] == 'approved', 'Transfer approval screenshot must precede physical receipt'
    for flow in flows:
        for key in ['form', 'save', 'draft', 'result']:
            assert (DOCS / 'source/screenshots' / (flow[key] + '.png')).is_file()
    results.append({'sample_request_flows': 9, 'scenario_screenshots': 36,
                    'transfer_approval_and_receipt_stages': 'distinct'})
    bundle = DOCS / 'PK_DTS_Documentation.zip'
    expected_files = ['PK_DTS_Proposed_Process_Flow.vsdx', 'PK_DTS_Proposed_Process_Flow.pdf',
                      'PK_DTS_DFD.pdf', 'PK_DTS_Flowchart.vsdx', 'PK_DTS_Flowchart.pdf',
                      'PK_DTS_User_Guide.pptx', 'PK_DTS_User_Guide.pdf']
    with ZipFile(bundle) as archive:
        assert archive.testzip() is None
        assert set(archive.namelist()) == set(expected_files + ['README.txt'])
        for name in expected_files:
            assert archive.read(name) == (DOCS / name).read_bytes(), f'Stale bundle member: {name}'
        public_wording(archive.read('README.txt').decode('utf-8'), bundle.name)
    results.append({'bundle_members': len(expected_files), 'bundle_matches_delivered_documents': True})
    print(json.dumps(results, indent=2))


if __name__ == '__main__':
    main()
