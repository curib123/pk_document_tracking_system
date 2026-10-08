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

#9 [Not fixed] have 3 layouts datatable which is the taable , grid layout in card layout and by folder like first area folder inside is specific then asset then location but in softcopy its category and sub category folder nested category folder

#10 [Not Fixed] remove files and attachment in sidebar layout and make the sidebar route have main route like Dashboard ,System Document inside of its dropdown option is softcopy documents and hardcopy doxuments and so on organized it properly but dont design style it yet

#11  [Not Fixed] fixed html is app.js which is not recommended make sure its in view as skeletal html tags no design ,each components that is centralized in view to easy edit design and track it easily

#12  [Not Fixed] Audit log must be json file to save not in table to make more faster and dont consume to much storage in database

#13 [Not Fixed] Workflow history there no step name in user requester so just put the requester action this text.

latest issues

#14 [FIXED] Account profile
- Added My profile in the signed-in account actions.
- Users can view/edit their own username and name without user-management permission.
- Position, role, leader and account status remain administrator-managed.

#15 [FIXED] Assignment request vs direct assignment
- Assignment request stays on the workflow/request lifecycle.
- Added Direct assign document for assignment managers; it does not create a request.

#16 [FIXED] Request type preset synchronization
- Transfer, Access, Assignment and Disposal pages now open their own locked request type.
- Contextual document request buttons keep the selected document/domain synchronized.

#17 [FIXED] Created By raw ID
- Raw created_by IDs are hidden from frontend details.
- Created By now resolves to the readable user name and position.

#18 [FIXED] Optional remarks
- Approval, return, reject, publish/default, reassignment, transfer, access, assignment, file and catalog action remarks can be left empty.
- Business request reasons remain separate from optional action remarks.

