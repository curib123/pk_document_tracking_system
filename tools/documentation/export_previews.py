"""Export diagram PDF pages for viewing alongside their editable Visio originals."""
from __future__ import annotations

import hashlib
import json
from pathlib import Path

import pymupdf

ROOT = Path(__file__).resolve().parents[2]
DOCS = ROOT / 'docs/system-documentation'
DIAGRAMS = [('PK_DTS_Proposed_Process_Flow', 'Process Flow'),
            ('PK_DTS_DFD', 'Data Flow Diagram'), ('PK_DTS_Flowchart', 'Flowchart')]


def main():
    folder = DOCS / 'previews'
    folder.mkdir(exist_ok=True)
    records, rows = [], []
    for stem, title in DIAGRAMS:
        original = DOCS / (stem + '.vsdx')
        assert original.is_file(), f'Missing editable counterpart: {original.name}'
        source = DOCS / (stem + '.pdf')
        links = []
        with pymupdf.open(source) as document:
            for number, page in enumerate(document, 1):
                name = f'{stem}_{number:02d}.png'
                scale = 1800 / page.rect.width
                pixmap = page.get_pixmap(matrix=pymupdf.Matrix(scale, scale), alpha=False)
                pixmap.save(folder / name)
                records.append({'file': name, 'source_pdf': source.name,
                                'editable_original': original.name, 'page': number,
                                'width': pixmap.width, 'height': pixmap.height,
                                'sha256': hashlib.sha256((folder / name).read_bytes()).hexdigest(),
                                'source_sha256': hashlib.sha256(source.read_bytes()).hexdigest()})
                links.append(f'[Page {number}]({name})')
        rows.append(f'| {title} | [Visio](../{original.name}) | ' + ', '.join(links) + ' |')
    manifest = {'kind': 'PDF page previews', 'visio_application_screenshots': False,
                'pages': records}
    (folder / 'manifest.json').write_text(json.dumps(manifest, indent=2) + '\n', encoding='utf-8')
    (folder / 'README.md').write_text(
        '# Diagram page previews\n\n'
        'These images show the red diagram pages exported from their PDFs. '
        'They are page previews, not screenshots of the Visio application. '
        'Each drawing has an editable Visio original with separate text, shapes and connected arrows.\n\n'
        '| Document | Editable original | Preview images |\n'
        '| --- | --- | --- |\n' + '\n'.join(rows) + '\n', encoding='utf-8')
    print(f'Exported {len(records)} page previews with editable counterparts.')


if __name__ == '__main__':
    main()
