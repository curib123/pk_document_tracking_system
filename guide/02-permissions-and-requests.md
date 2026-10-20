# 02 — Permissions, document files and request workflows

Use three QA identities: Administrator (A), document owner/requester (B) and unrelated employee (C).
First configure C without `files.history`, `files.print`, `files.download`, or backup-edit permission.
Use an additional approver assigned to exactly one pending step.

| ID | Manual steps | Expected result |
| --- | --- | --- |
| ACL-01 | Sign in as C, open Softcopy document list. | Only documents C is authorized to see appear, including search and folder layouts. |
| ACL-02 | On an authorized document, inspect File History / Print / Download buttons as C. | Each action appears only when C has its independent permission. |
| ACL-03 | Copy permitted download/print URLs as A; open directly as C without permission. | HTTP 403 and no file bytes. A may access only authorized current versions. |
| ACL-04 | Grant only `files.download` to C and retest. | Download works where document scope permits; Print and File History remain denied. |
| ACL-05 | Grant `files.print` only, then only `files.history`. | Individual actions become available independently; history never grants download of superseded files. |
| ACL-06 | As C, attempt different document ID and unknown file ID. | Unauthorized document is rejected; no existence leak through content. |
| ACL-07 | Open original creation/revision attachment as owner B and active assigned approver. | Both can review pending attachments; unrelated C is denied. |
| ACL-08 | Reassign or complete the approval step; repeat review URL as former approver. | Former approver no longer gets active-step access. |
| ACL-09 | Upload a revised document, then open old revision history and current file. | Previous version remains historical metadata only; current approved file is downloadable if authorized. |
| ACL-10 | Test filenames with quotes, newline, Unicode, Windows separators in QA. | Download response has a safe filename; no extra header, path disclosure or server error. |
| ACL-11 | Attempt `admin/backups/download/{archive}` as viewer without backup edit. | HTTP 403; only authorized backup editors receive the archive. |
| REQ-01 | Create draft softcopy and hardcopy requests as allowed user; open Request View. | Correct document metadata, status, workflow route and Workflow Activity Log in read-only modal. |
| REQ-02 | Submit each request to approver; approve, reject and return from My Tasks. | Only active assignee can decide; after return, requester can edit and resubmit. |
| REQ-03 | Attempt to approve same request twice in separate tabs or after reassignment. | Second stale decision rejected; no repeated workflow history or duplicate transition. |
| REQ-04 | Test disposal and physical hardcopy transfer (dispatch, receipt, custody update). | Only the transfer requester may Dispatch and only the destination recipient may Accept; custody and history must be correct. |
| REQ-05 | Remove permission to create one request type but retain another type. | Disallowed action hidden and server refuses direct POST. No undefined permission warning. |
| REQ-06 | Open request modal, switch all approval/history tabs and close/reopen another row. | Previous request never flashes, tabs respond, no blank panel or JS console exception. |

All permission checks must be verified at both UI and direct-route levels.
