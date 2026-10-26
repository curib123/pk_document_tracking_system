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
            'PK DOCUMENT TRACKING SYSTEM - PROPOSED DOCUMENTATION\n'
            '26 OCTOBER 2026 | REVISION 02 | PROPOSAL\n\n'
            'Proposed Process Flow: editable Visio (.vsdx) and PDF, five sheets.\n'
            'Data Flow Diagram: PDF, two participant and system sheets.\n'
            'Flowchart: editable Visio (.vsdx) and PDF, six sheets.\n'
            'Proposed User Guide: PowerPoint (.pptx) and PDF, with screen\n'
            'illustrations, sample data and nine request scenarios.\n\n'
            'The documents define intended processes and user procedures.\n'
            'Diagram headings, cards, arrows and notes use Peanut Kisses red.\n'
            'Screen illustrations use sample accounts, records and transactions.\n'
            'The published workflow will determine each request\'s approvers;\n'
            'available actions will depend on assigned permissions and access.\n')
    with ZipFile(path) as archive:
        assert archive.testzip() is None
        assert set(archive.namelist()) == set(FILES + ['README.txt'])
    print(f'Created and checked {path.name}: {len(FILES)} documents plus README.')


if __name__ == '__main__':
    main()
