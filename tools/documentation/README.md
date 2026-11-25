# Documentation maintenance

The public documents use a proposed-system perspective. Implementation references are retained in the source models to keep the plan consistent with supported behavior. Screen illustrations use an isolated fixture database and sample accounts; no company records or account passwords are included.

## Sources and rebuilding

Content models and original screenshots are in `docs/system-documentation/source`. Models retain application references for maintenance; the public presentation contains user procedures only. Rebuild from the repository root.

Process Flow follows the illustrated responsibility-map format in the supplied `SA Documents/RMS/process flow old.vsdx`. DFD follows the two-sheet participant/system format in `SA Documents/From Alyn/Healthcare Benefits_DFD.pdf`. Their source models select `render_sample_diagrams.py` through the existing diagram command. Original sample illustrations are retained unchanged in `source/icons`, with their source mapping recorded beside them. The separate Flowchart retains its detailed decision notation.

Diagram headers, cards, arrows and notes use the Peanut Kisses red palette from `public/assets/css/colors.css`, shared through `brand_palette.py`. Original sample illustrations retain their supplied colors. The Flowchart covers authentication, proposals, approvals, physical handoffs, document operations, file access, administration and recovery across six sheets.

From the repository root:

```powershell
python -m pip install -r tools/documentation/requirements.txt
python tools/documentation/render_diagrams.py --model docs/system-documentation/source/process.json --output-base docs/system-documentation/PK_DTS_Proposed_Process_Flow --visio
python tools/documentation/render_diagrams.py --model docs/system-documentation/source/dfd.json --output-base docs/system-documentation/PK_DTS_DFD --visio
python tools/documentation/render_diagrams.py --model docs/system-documentation/source/flowchart.json --output-base docs/system-documentation/PK_DTS_Flowchart --visio
python tools/documentation/render_guide.py --model docs/system-documentation/source/guide.json --screenshots docs/system-documentation/source/screenshots --flows docs/system-documentation/source/sample-flows.json --output docs/system-documentation/PK_DTS_User_Guide.pptx
powershell -NoProfile -ExecutionPolicy Bypass -File tools/documentation/verify_powerpoint.ps1
python tools/documentation/export_previews.py
python tools/documentation/package_documents.py
python tools/documentation/verify_documents.py
```

The PowerPoint verification/export command requires installed Microsoft PowerPoint on Windows. Other generation commands use Python. Read `source/verification.json` for the delivered snapshot's checks.

Document checks compare every diagram description, note and node label with its PDF, validate cited source-line ranges, confirm the application red palette in PDF and native Visio elements, and require two glued endpoints per Visio connector. These checks verify the documentation; application behavior still requires its own runtime validation.

Native Visio files contain editable shapes, text and glued connectors. Their ZIP/XML structure, relationships and connections were checked, and matching PDF pages were visually inspected. Opening and editing in the Visio desktop application remains unverified because Visio is unavailable in this workspace.

All three diagrams have native `.vsdx` originals; the guide has a `.pptx` original. Embedded illustration and screenshot pixels remain images, while document text, layout and arrow annotations remain editable.

Run `python tools/documentation/export_previews.py` after updating diagram PDFs to refresh all thirteen page images and their hash manifest in `previews`. These are PDF page previews, not screenshots of the Visio desktop application. The document verifier checks each image against its current PDF page.

After exporting the presentation PDF, run `python tools/documentation/package_documents.py` and rerun the document checks. Refresh `docs/system-documentation/source/verification.json` only from the completed checks.
