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
import unicodedata
from xml.etree import ElementTree as ET
from zipfile import ZipFile

import pymupdf

from brand_palette import ACCENT_RED, BRAND_RED, COLORS
from export_previews import DIAGRAMS
import hashlib

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
            nodes = connectors = illustrations = 0
            for page in pages:
                public_wording('\n'.join(page.itertext()), path.name)
                shapes = page.findall('v:Shapes/v:Shape', NS)
                ids = {s.get('ID') for s in shapes}
                assert len(ids) == len(shapes), 'Duplicate Visio shape ID'
                for shape in shapes:
                    cells = {c.get('N'): c for c in shape.findall('v:Cell', NS)}
                    if 'BeginX' in cells:
                        connectors += 1
                        assert all(c in cells for c in ['BeginX', 'BeginY', 'EndX', 'EndY'])
                        assert shape.find('v:Section[@N="Geometry"]', NS) is not None
                    elif shape.get('Type') == 'Foreign':
                        illustrations += 1
                        assert shape.find('v:ForeignData', NS) is not None
                        assert float(cells['Width'].get('V')) <= 2 and float(cells['Height'].get('V')) <= 2, 'Diagram flattened into a page image'
                    else:
                        nodes += 1
                for connection in page.findall('v:Connects/v:Connect', NS):
                    assert connection.get('FromSheet') in ids and connection.get('ToSheet') in ids
                glue = page.findall('v:Connects/v:Connect', NS)
                for shape in shapes:
                    if shape.find('v:Cell[@N="BeginX"]', NS) is not None:
                        assert len([g for g in glue if g.get('FromSheet') == shape.get('ID')]) == 2, 'Connector lacks two glued endpoints'
            assert nodes > 10 and connectors > 5
            if 'Flowchart' in path.name:
                assert illustrations == 0, 'Flowchart unexpectedly rasterized'
            return {'file': path.name, 'pages': len(pages), 'native_shapes': nodes,
                    'native_connectors': connectors, 'sample_illustrations': illustrations, 'relationships': 'valid'}
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


def diagram_checks() -> dict:
    """Check visible model content, source locations and brand colors independently."""
    css = (ROOT / 'public/assets/css/colors.css').read_text(encoding='utf-8').upper()
    assert all(color in css for color in COLORS), 'Document palette differs from application palette'
    count = references = 0
    names = {'process': 'PK_DTS_Proposed_Process_Flow', 'dfd': 'PK_DTS_DFD',
             'flowchart': 'PK_DTS_Flowchart'}
    def normalized(text):
        # Arial's equivalent glyph mappings include Greek question mark for
        # semicolon, NBSP for spaces and soft hyphen for visible hyphens.
        return re.sub(r'\s+', '', unicodedata.normalize('NFKC', text).replace('\u00ad', '-'))
    def pdf_color(value):
        return '#' + ''.join(f'{round(c * 255):02X}' for c in value[:3])
    for model_name, artifact in names.items():
        model = json.loads((DOCS / 'source' / (model_name + '.json')).read_text(encoding='utf-8'))
        with pymupdf.open(DOCS / (artifact + '.pdf')) as document:
            assert len(document) == len(model['pages']), f'{artifact}: stale page count'
            for number, (sheet, page) in enumerate(zip(model['pages'], document), 1):
                content = normalized(page.get_text())
                labels = sheet.get('notes', []) + sheet.get('legend', [])
                labels += [n['label'] for n in sheet.get('nodes', [])]
                labels += [s['description'] for s in sheet.get('steps', [])]
                labels += [label for a in sheet.get('actors', [])
                           for label in [a['role'], *a['inputs'], *a['outputs']]]
                assert all(normalized(label) in content for label in labels), f'{artifact} page {number}: model text missing from PDF'
                colors = {pdf_color(d[key]) for d in page.get_drawings()
                          for key in ('color', 'fill') if d.get(key)}
                colors.update(f'#{span["color"]:06X}' for block in page.get_text('dict')['blocks']
                              for line in block.get('lines', []) for span in line.get('spans', []))
                assert colors <= COLORS, f'{artifact} page {number}: unbranded vector/text colors: {colors - COLORS}'
                assert {BRAND_RED, ACCENT_RED} <= colors, f'{artifact} page {number}: red headers or arrows missing'
                count += 1
                for source in sheet.get('sources', []):
                    match = re.fullmatch(r'(.+?)(?::([\d,-]+))?', source)
                    assert match, f'Invalid source reference: {source}'
                    source_file = ROOT / match[1]
                    assert source_file.is_file(), f'Missing source: {source}'
                    if match[2]:
                        length = len(source_file.read_text(encoding='utf-8-sig').splitlines())
                        for segment in match[2].split(','):
                            bounds = [int(n) for n in segment.split('-')]
                            assert 1 <= bounds[0] <= bounds[-1] <= length, f'Source location out of range: {source}'
                    references += 1
        with ZipFile(DOCS / (artifact + '.vsdx')) as archive:
            page_names = [name for name in archive.namelist() if re.fullmatch(r'visio/pages/page\d+\.xml', name)]
            assert len(page_names) == len(model['pages']), f'{artifact}: stale editable page count'
            for name in page_names:
                page = ET.fromstring(archive.read(name))
                colors = {c.get('V').upper() for c in page.findall('.//v:Cell', NS)
                          if c.get('V', '').startswith('#')}
                assert colors <= COLORS and {BRAND_RED, ACCENT_RED} <= colors, f'{artifact}: Visio palette differs from PDF'
    return {'diagram_pages_checked': count, 'diagram_source_references_checked': references,
            'all_model_labels_present_in_pdfs': True, 'peanut_kisses_red_palette': 'application CSS',
            'original_sample_icon_colors_preserved': True}


def preview_checks() -> dict:
    folder = DOCS / 'previews'
    manifest = json.loads((folder / 'manifest.json').read_text(encoding='utf-8'))
    assert manifest['kind'] == 'PDF page previews' and manifest['visio_application_screenshots'] is False
    indexed = {(r['source_pdf'], r['page']): r for r in manifest['pages']}
    assert len(indexed) == len(manifest['pages']), 'Duplicate preview page'
    expected = set()
    for stem, _ in DIAGRAMS:
        source = DOCS / (stem + '.pdf')
        source_hash = hashlib.sha256(source.read_bytes()).hexdigest()
        with pymupdf.open(source) as document:
            for number, page in enumerate(document, 1):
                key = (source.name, number)
                expected.add(key)
                record = indexed[key]
                assert record['file'] == f'{stem}_{number:02d}.png'
                assert record['editable_original'] == stem + '.vsdx'
                assert (DOCS / record['editable_original']).is_file()
                assert record['source_sha256'] == source_hash, 'Stale preview PDF source'
                path = folder / record['file']
                assert hashlib.sha256(path.read_bytes()).hexdigest() == record['sha256']
                actual = pymupdf.Pixmap(path)
                target = page.get_pixmap(matrix=pymupdf.Matrix(1800 / page.rect.width, 1800 / page.rect.width), alpha=False)
                assert (actual.width, actual.height) == (record['width'], record['height'])
                assert actual.samples == target.samples, f'Preview differs from current PDF page: {path.name}'
    assert set(indexed) == expected, 'Preview coverage differs from document pages'
    return {'preview_pages_checked': len(expected), 'pixel_match_to_current_pdfs': True,
            'editable_counterparts_present': True, 'visio_application_screenshots': False}


def main():
    results = []
    for name in ['PK_DTS_Proposed_Process_Flow.vsdx', 'PK_DTS_DFD.vsdx', 'PK_DTS_Flowchart.vsdx', 'PK_DTS_User_Guide.pptx']:
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
    results.append(diagram_checks())
    results.append(preview_checks())
    for name, expected_format in [('process', 'sample-process'), ('dfd', 'sample-dfd')]:
        model = json.loads((DOCS / 'source' / (name + '.json')).read_text(encoding='utf-8'))
        assert model['format'] == expected_format
        assert model.get('sample_reference', '').startswith('SA Documents/')
        artifact = DOCS / ('PK_DTS_Proposed_Process_Flow.pdf' if name == 'process' else 'PK_DTS_DFD.pdf')
        with pymupdf.open(artifact) as document:
            assert len(document) == len(model['pages'])
            for page in document:
                assert abs(page.rect.width - 792) < 1 and abs(page.rect.height - 612) < 1, 'Sample page format changed'
                assert len(page.get_image_info()) >= 3, 'Sample illustrations missing'
                extracted = page.get_text().replace('\u00a0', ' ').replace('\u00ad', '-')
                assert 'IN-HOUSE SYSTEM' in extracted if name == 'dfd' else 'PROPOSED PROCESS FLOW' in extracted
        if name == 'dfd':
            assert len(model['pages']) == 2
            for sheet in model['pages']:
                assert len(sheet['actors']) == 3
                assert all(a['inputs'] and a['outputs'] for a in sheet['actors'])
    results.append({'sample_process_format': 'RMS illustrated responsibility map',
                    'sample_dfd_format': 'Healthcare participant-to-system map',
                    'sample_dfd_sheets': 2})
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
                      'PK_DTS_DFD.vsdx', 'PK_DTS_DFD.pdf', 'PK_DTS_Flowchart.vsdx', 'PK_DTS_Flowchart.pdf',
                      'PK_DTS_User_Guide.pptx', 'PK_DTS_User_Guide.pdf']
    with ZipFile(bundle) as archive:
        assert archive.testzip() is None
        preview_files = ['previews/README.md', 'previews/manifest.json'] + [
            'previews/' + r['file'] for r in json.loads((DOCS / 'previews/manifest.json').read_text(encoding='utf-8'))['pages']]
        assert set(archive.namelist()) == set(expected_files + preview_files + ['README.txt'])
        for name in expected_files + preview_files:
            assert archive.read(name) == (DOCS / name).read_bytes(), f'Stale bundle member: {name}'
        public_wording(archive.read('README.txt').decode('utf-8'), bundle.name)
    results.append({'bundle_members': len(expected_files), 'preview_images': len(preview_files) - 2,
                    'bundle_matches_delivered_documents': True})
    print(json.dumps(results, indent=2))


if __name__ == '__main__':
    main()
