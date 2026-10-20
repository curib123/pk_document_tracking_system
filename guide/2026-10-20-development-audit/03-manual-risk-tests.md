# 03 — Recreate Request, Transfer and Assignment Defects in QA

Status: REQ-01/REQ-02 are code limitations; TR-01 is high-confidence code-path risk; TR-02 and ADM-01 are hypotheses. **Do not mark runtime failure until you observe it.**

## REQ-01 — My Requests/My Tasks cannot find by document title

**Setup:** A has a submitted transfer for hardcopy H titled QA-HARDCOPY-ALPHA; subject is "Move for storage", reference does not contain ALPHA.

1. A → My Requests → Hardcopy Transfer. Clear all filters, take screenshot of visible row.
2. Search QA-HARDCOPY-ALPHA. Record result count.
3. Clear search; search Move for storage. Record result count.
4. As assigned approver, repeat in My Tasks while request is submitted.
5. Compare network query parameters and logs, verify title is displayed but not searchable.

**Proposed expected:** searching by selected document title should find row. **Source:** Request_model::scope filters only request reference/type/JSON subject. Important: source document joins occur after scope() in listing(); a fix must make joins available to both COUNT and paginated select. Add escaped grouped WHERE, test permissions/pagination.

## REQ-02 — Document Title not sortable

1. Create 3 requests with distinct visible titles: AAA, MMM, ZZZ.
2. Go to My Requests/Tasks; click or keyboard-activate Document Title header if supported.
3. Inspect whether sort icons, URL and row order change.
4. Check application/view/pages/request/shared_index.php dt_sortable and Request_model sorting whitelist.
5. Record requirement decision: if title sort is desired, it is currently unsupported; if intentionally unsortable, mark UX improvement rather than bug.

## TR-01 — Single assignment during approved transfer may break Accept

**Setup:** A requester, B current hardcopy holder, C destination recipient, E admin with assignment.direct, extra QA user D. H located L1, destination L2.

1. A requests H transfer to C/L2; approver approves until transfer stage is for_transfer. Capture H holder B and transfer current_holder_id B using read-only SELECT.
2. E → Admin → Document Assignments → Hardcopy / Places → browse to H → Assign Document. Assign H to D (single document, NOT Assign All). Record exact message.
3. Recheck holder_id for H. If it changed to D, A Dispatches, then C tries Accept.
4. Record exact accept error/status; check whether transfer stays in_transit with no custody update.
5. In a restored QA scenario, E tries Assign All at Location L1 with same pending transfer. Compare the guarded bulk route with the single route.

**Expected policy to confirm:** no unilateral reassignment should strand an approved transfer. **Evidence:** Administration::assign_location rejects open transfers; save_document_assignment does not check them. Document_access_service::apply changes holder_id; Transfer_service::accept rejects holder mismatch. Runtime must still be tested.

**After confirmation:** centralize transfer-conflict validation in domain service with document/transfer locks for both single and bulk assignments. Add negative multi-user regression before fix.

## TR-02 — Different requesters transfer same physical document

1. A submits transfer of H to C/L2, still pending review.
2. D (with legitimate document permissions) submits a different transfer of H to another recipient/location.
3. If permissions prevent it, mark BLOCKED and explain why. Otherwise approve both on QA.
4. Have A and D dispatch their own approved transfers. Let destination recipients accept sequentially.
5. Compare transfer status, current holder, and workflow_history before/after.

**Existing source:** has_submitted_related_request scopes conflict to one requested_by ID. That was intentional for earlier plan.md; do not expand scope without agreeing physical custody rules. Potential second-accept failure is a hypothesis until observed.

## ADM-01 — Empty Location bulk assignment

1. E creates empty active Location L-empty with no hardcopy, verify COUNT(*) = 0 using QA SELECT.
2. E → Admin → Document Assignments → Hardcopy / Places → Location L-empty → user C → Assign All.
3. Expected: "No active Hardcopy documents in this Location" and no write.
4. Record actual HTTP/alert/log. In Administration::assign_location, the open-transfers where_in($ids) executes before the !$rows check. Check how installed CodeIgniter 3 handles empty array. Mark PASS if graceful; FAIL only on reproduced error.
5. If failing, guard empty rows before building where_in; test zero and many documents.

## REG-01 — Retest earlier patches

Follow all cases in guide/2026-10-20-plan-fixes/manual-tests.md on QA: title in every request/task type including disposal, correct modal, same-user submitted access/assignment/transfer blocking, domain ID isolation, two concurrent submissions, requester Dispatch, recipient Accept, server authorization, completed history. A green focused contract does not prove these browser cases.

## Other adjacent risks to examine

- Submit two approval decisions from separate tabs; confirm one history event only.
- Return workflow from intermediate step and inspect preserved old decisions; compare tests/request_return.php.
- Deactivate destination user during approved handoff; check clear refusal/recovery policy.
- Submit disposal/update while a transfer awaits acceptance; determine whether physical custody rules need a shared conflict guard.
- Verify title/search and permissions after filtering, pagination, status changes and fresh reload.
