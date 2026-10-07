- Issues found in Testing Phases — functionality

#1 [FIXED] Workflow Builder publish version
- Fixed the database error path after submitting the publish remark.
- Publishing/default switching no longer performs an unrelated workflow-route sequence write.
- Added regression coverage for publishing the first usable workflow version.

#2 [FIXED] Workflow Builder draft delete/remove
- Added "Remove draft version".
- Requires a reason.
- Only unpublished drafts can be removed.
- Published, default, or request-linked versions remain protected.

#3 [FIXED] Raw JSON metadata in frontend
- Removed raw JSON/metadata dumps from record and file dialogs.
- Frontend now renders readable scalar details only.
- Internal graph, payload, snapshot, result, IDs, assignments, and audit state objects stay hidden.

#4
