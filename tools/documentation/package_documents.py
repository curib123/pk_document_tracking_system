"""Bundle the finished office/PDF documents without application or company data."""
from pathlib import Path
from zipfile import ZipFile, ZIP_DEFLATED

ROOT = Path(__file__).resolve().parents[2]
DOCS = ROOT / 'docs/system-documentation'
FILES = [
    'PK_DTS_Proposed_Process_Flow.vsdx', 'PK_DTS_Proposed_Process_Flow.pdf',
    'PK_DTS_DFD.pdf', 'PK_DTS_Flowchart.vsdx', 'PK_DTS_Flowchart.pdf',
    'PK_DTS_User_Guide.pptx', 'PK_DTS_User_Guide.pdf',
]


def main():
    for name in FILES:
        assert (DOCS / name).is_file(), f'Missing deliverable: {name}'
    path = DOCS / 'PK_DTS_Documentation.zip'
    with ZipFile(path, 'w', ZIP_DEFLATED) as archive:
        for name in FILES:
            archive.write(DOCS / name, name)
        archive.writestr('README.txt',
            'PK DOCUMENT TRACKING SYSTEM - 26 OCTOBER 2026\n\n'
            'Proposed Process Flow: editable Visio (.vsdx) and PDF.\n'
            'Data Flow Diagram: PDF, eight coordinated sheets.\n'
            'Flowchart: editable Visio (.vsdx) and PDF.\n'
            'User Guide: PowerPoint (.pptx) with screenshots, editable arrows,\n'
            'sample data, all nine request walkthroughs, and a PDF reading copy.\n\n'
            'The documents describe the implemented system. Screenshots use\n'
            'synthetic examples; actual access and approvers are configured\n'
            'for each installation. No passwords are included.\n\n'
            'The Visio package structure and connectors were validated; native\n'
            'Visio opening was unavailable. PowerPoint was opened and exported\n'
            'using installed Microsoft PowerPoint.\n\n'
            'Source models, screenshots, evidence and rebuilding instructions\n'
            'are in the repository at docs/system-documentation and\n'
            'tools/documentation.\n')
    with ZipFile(path) as archive:
        assert archive.testzip() is None
        assert set(archive.namelist()) == set(FILES + ['README.txt'])
    print(f'Created and checked {path.name}: {len(FILES)} documents plus README.')


if __name__ == '__main__':
    main()
