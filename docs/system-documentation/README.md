# PK Document Tracking System documentation

Prepared on **26 October 2026**, using the implemented Desktop checkout at application commit `94b6a3efd8a5ec81784870be7939ef46b601e4d7`.

| Document | Main file | Reading copy |
| --- | --- | --- |
| Proposed Process Flow | [Visio drawing](PK_DTS_Proposed_Process_Flow.vsdx) | [PDF](PK_DTS_Proposed_Process_Flow.pdf) |
| Data Flow Diagram | [PDF](PK_DTS_DFD.pdf) | Eight coordinated context, subsystem and data-store sheets |
| Flowchart | [Visio drawing](PK_DTS_Flowchart.vsdx) | [PDF](PK_DTS_Flowchart.pdf) |
| User Guide | [PowerPoint presentation](PK_DTS_User_Guide.pptx) | [PDF](PK_DTS_User_Guide.pdf) |

The supplied `SA Documents.zip` was used for document styles and formats. The system behavior, terminology and procedures come from this repository. The User Guide follows the user's later requirement for PowerPoint, with actual screenshots, editable arrows, sample inputs and sample flows.

The process documents cover configurable approvals, all nine request actions, direct actions, reverse-step returns, request cancellation/deletion, assignments, access and physical custody. The DFD follows real SQL and private filesystem stores, including audit JSON. The guide also explains document browsing, files, notifications, places, accounts, permissions, workflow maintenance, audit and backups.

## Sample walkthroughs

Screenshots show the current application with synthetic demonstration accounts, records, places and files. Request walkthroughs use a two-step **sample** approval route; an installation's published workflow determines its actual approvers. Temporary account passwords are excluded from the documents and source assets.

The nine demonstrated requests are Create Softcopy, Revise Softcopy, Cancel Softcopy, Create Hardcopy, Update Hardcopy, Grant Access, Assign Document, Transfer Hardcopy and Dispose Hardcopy. Shared examples also show submission, approval, first-step return, later-step return, rejection, cancelled-draft deletion and transfer dispatch/acceptance. Demonstration data was isolated from the installed company database.

## Sources and rebuilding

Reviewable content models and original screenshots are in [source](source). Each model records supporting application paths; the PowerPoint also includes full procedures and source references in speaker notes. The generation tools are in [tools/documentation](../../tools/documentation).

From the repository root:

```powershell
python -m pip install -r tools/documentation/requirements.txt
python tools/documentation/render_diagrams.py --model docs/system-documentation/source/process.json --output-base docs/system-documentation/PK_DTS_Proposed_Process_Flow --visio
python tools/documentation/render_diagrams.py --model docs/system-documentation/source/dfd.json --output-base docs/system-documentation/PK_DTS_DFD
python tools/documentation/render_diagrams.py --model docs/system-documentation/source/flowchart.json --output-base docs/system-documentation/PK_DTS_Flowchart --visio
python tools/documentation/render_guide.py --model docs/system-documentation/source/guide.json --screenshots docs/system-documentation/source/screenshots --flows docs/system-documentation/source/sample-flows.json --output docs/system-documentation/PK_DTS_User_Guide.pptx
powershell -NoProfile -ExecutionPolicy Bypass -File tools/documentation/verify_powerpoint.ps1
python tools/documentation/verify_documents.py
```

The PowerPoint verification/export command requires installed Microsoft PowerPoint on Windows. Other generation commands use Python. Read `source/verification.json` for the delivered snapshot's checks.

Native Visio files contain editable shapes, text and glued connectors. Their ZIP/XML structure, relationships and connections were checked, and matching PDF pages were visually inspected. Opening and editing in the Visio desktop application remains unverified because Visio is unavailable in this workspace.
