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

new issues

#4 [Not Fixed] Holder select user why ? make automatic read only based current user because he/she is the requester so automaticaly he/she is the holder except if you are a admin or dco document controll officer

#5 [Not Fixed ] sequence no. make it optional and make it below to location 1:1 ration to location

#6 [Not fixed] in hardcopy document page its have no filtration like status ,the draft request did not show.

#7[Not fixed ] there rebundant request route and the my request route in sidebar route 

#8 [Not fixed] make sure dont show id in frontend throughout the system only there names or values should show

#9 [Not fixed] 
