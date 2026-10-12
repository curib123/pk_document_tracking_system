issues #1 adding notification badge count in sidebar links and page tabs 

issues #2
View Details
sections
[object Object],[object Object],[object Object],[object Object],[object Object],[object Object]

issues #3
workflow builder issues 
workflow have this default seed request
('softcopy_create', 'Softcopy Create approval', 'softcopy_create', 1, 1), ('softcopy_revise', 'Softcopy Revise approval', 'softcopy_revise', 1, 1), ('softcopy_cancel', 'Softcopy Cancel approval', 'softcopy_cancel', 1, 1), ('hardcopy_create', 'Hardcopy Create approval', 'hardcopy_create', 1, 1), ('hardcopy_update', 'Hardcopy Update approval', 'hardcopy_update', 1, 1), ('transfer', 'Transfer approval', 'transfer', 1, 1), ('assignment', 'Assignment approval', 'assignment', 1, 1), ('access', 'Access approval', 'access', 1, 1), ('disposal', 'Disposal approval', 'disposal', 1, 1);

DTS Workflow Builder — Text Visual Flow
┌──────────────────────────────────┐
│          WORKFLOW BUILDER        │
│                                  │
│ Create / Edit Workflow           │
│ Select Request Type              │
└────────────────┬─────────────────┘
                 │
                 ▼
┌──────────────────────────────────┐
│           WORKFLOWS              │
│                                  │
│ softcopy_create                  │
│ softcopy_revise                  │
│ softcopy_cancel                  │
│ hardcopy_create                  │
│ hardcopy_update                  │
│ transfer                         │
│ assignment                       │
│ access                           │
│ disposal                         │
└────────────────┬─────────────────┘
                 │
                 ▼
┌──────────────────────────────────┐
│        WORKFLOW VERSIONS         │
│                                  │
│ Version 1 — Published            │
│ Version 2 — Draft                │
│ Version 3 — Draft                │
│                                  │
│ Published Default Version       │
│ is_default = 1                   │
└────────────────┬─────────────────┘
                 │
                 ▼
┌──────────────────────────────────┐
│       APPROVAL STEPS BUILDER     │
│                                  │
│ Step 1: Supervisor Approval      │
│ Step 2: Records Officer          │
│ Step 3: Administrator Approval   │
│                                  │
│ Add / Edit / Delete / Reorder    │
└────────────────┬─────────────────┘
                 │
                 ▼
┌──────────────────────────────────┐
│          APPROVER TYPE           │
│                                  │
│ 1. Role                          │
│ 2. Specific User                 │
│ 3. Requester's Leader            │
│ 4. Requester                     │
└────────────────┬─────────────────┘
                 │
                 ▼
┌──────────────────────────────────┐
│         PUBLISH VERSION          │
│                                  │
│ Validate workflow and steps      │
│ Set published status             │
│ Set default version              │
└────────────────┬─────────────────┘
                 │
                 ▼
┌──────────────────────────────────┐
│        REQUEST SUBMITTED         │
│                                  │
│ Identify request type            │
│ Load published default version   │
│ Create approval instance         │
└────────────────┬─────────────────┘
                 │
                 ▼
       ┌────────────────────┐
       │  APPROVAL STEP 1   │
       │  Assigned Approver │
       └─────────┬──────────┘
                 │
          ┌──────┴──────┐
          │             │
       Approved      Rejected
          │             │
          ▼             ▼
┌─────────────────┐  ┌──────────────────┐
│  APPROVAL STEP 2│  │ Rejection Policy │
└────────┬────────┘  │ Return / Reject  │
         │           └──────────────────┘
         ▼
┌─────────────────┐
│  APPROVAL STEP 3│
└────────┬────────┘
         │
         ▼
┌──────────────────────────────────┐
│       ALL STEPS APPROVED         │
│                                  │
│       Request Approved           │
│       Record Audit History       │
└──────────────────────────────────┘
Approval step logic
FOR EACH APPROVAL STEP
        │
        ▼
Determine Approver Type
        │
        ├── Role
        │     └── Assign to eligible role member
        │
        ├── Specific User
        │     └── Assign to selected user
        │
        ├── Requester's Leader
        │     └── Resolve requester's supervisor
        │
        └── Requester
              └── Resolve request submitter
        │
        ▼
Wait for Approval Decision
        │
        ├── Approved ──► Proceed to Next Step
        │
        ├── Rejected ──► Apply Rejection Policy
        │
        └── Returned ──► Return for Correction
                              │
                              ▼
                         Resubmit Request
Version control logic
Workflow
   │
   ├── Version 1 (Published Default)
   │       └── Existing requests continue using Version 1
   │
   └── Version 2 (Draft)
           │
           ▼
       Edit Steps
           │
           ▼
       Publish Version 2
           │
           ▼
   New requests use Version 2
   Existing requests remain on Version 1

Key rule: Every submitted request should record the exact workflow_version_id it uses. This preserves the approval history and prevents publishing a new version from unexpectedly changing approval steps for requests already in progress.
