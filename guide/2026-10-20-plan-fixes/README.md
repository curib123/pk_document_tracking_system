# PK Document Tracking System — 20 October 2026 Fix Guide

Repository: curib123/pk_document_tracking_system  
Branch: development  
Requirements: root plan.md (three issues), plus missing Document Title in My Requests/My Tasks datatables.

These are **implemented code fixes**, not only suggested changes. No database schema migration is required. Verify them in a disposable test environment before releasing or merging elsewhere.

## Issue-to-code map

| Item | Root cause | Implemented fix |
| --- | --- | --- |
| PLAN-01: overlapping submitted requests | No guard for an access, assignment, or transfer request already submitted by the same requester against the same document. | Request_model::has_submitted_related_request checks exact requester, submitted status, related types, and **only the matching document domain**. Request_service::assert_no_submitted_related_request uses a document row lock and runs during draft saving and again during submission. |
| PLAN-03: transfer dispatch ownership | The approved transfer's Dispatch action checked current_holder_id instead of the requester who initiated the transfer. | Transfer_service::dispatch checks the linked transfer request's requested_by. Transfer_model::pending scopes for_transfer to that requester and in_transit to the intended recipient. Routes authenticate the user; service authorizes the actor. Transfer_service::accept continues validating recipient_id and updates custody only when accepted. |
| PLAN-DISPOSAL: hidden title | The disposal datatable used only the often-blank proposal title. | Request_title::resolve displays the linked hardcopy's existing title. |
| TITLE-ALL: missing titles in other requests/tasks | Access, assignment, transfer and other actions often do not propose a title, but the shared table rendered payload.title, not the already-retrieved source_title. | The shared Requests/Tasks datatable and its View modal use one title resolver. Creation/update proposal titles still take priority where appropriate. |

## Code changed

- application/models/Request_model.php — exact submitted-request match by user, type and softcopy/hardcopy document.
- application/services/request/request_service.php — transactional document lock and validation at draft save and submit.
- application/services/transfer/transfer_service.php — requester-only dispatch; destination recipient-only acceptance.
- application/models/Transfer_model.php — stage-aware handoff listing; requester and recipient metadata.
- application/controllers/Requests.php — show authorized user-specific handoff tasks without generic transfer-manager privileges; service validates exact actor.
- application/services/presentation/request_title.php — reusable, pure title resolver.
- application/view/pages/request/shared_index.php — consistent Document Title in both tables and modal; requester dispatch UI.
- tests/plan_20261020_regressions.php — focused title and query-scope test, plus code-path regression contracts.
- tests/run_unit.sh — includes the focused test automatically.

## Business behavior

A **submitted** access, assignment or transfer request for a selected document blocks another of those three actions **by the same requester for the same document**. Different requesters and different document domains must not collide even when IDs happen to match. Draft-only, completed, cancelled, rejected and returned requests are not included in this submitted-only conflict check; the rest of the app's permission and document validations still apply.

The guard is checked at **draft save and submission**, with a SELECT ... FOR UPDATE document-row lock within the existing request transaction. This serializes distinct request IDs aimed at the same document. The request service should remain the only write path for these request types.

For approved hardcopy transfers, the **requester dispatches** the transfer even if another person currently holds the document. The **destination recipient accepts** it. Physical holder and location changes happen on acceptance, not at Dispatch. Nonrequesters (including holders and administrators) must not dispatch merely by role; nonrecipients must not Accept.

For preexisting documents, the datatable displays the selected document's title. For creation/revision/update proposals, an explicit proposed title is shown. If neither exists, fall back to the request subject and finally Request #ID. The edit form still uses the original payload data, not the synthesized display title.

## Verification commands and release gate

On a disposable PHP/CodeIgniter/MariaDB clone, run:

    php tests/plan_20261020_regressions.php
    bash tests/run_unit.sh

Or run composer test with its configured scripts. Inspect GitHub Actions for this exact commit and complete the browser/multi-user cases in manual-tests.md. Existing cases are documented under guide/02-permissions-and-requests.md and guide/07-current-plan-manual-test.md; the latter covers older historical plan items and does not replace the current checklist.

Record evidence in guide/05-results-template.md. **Do not claim these bugs are fully verified in production until the integration and manual cases pass.** Any unauthorized Dispatch/Accept, overlapping submission, wrong-user/domain blocking, missing title, lost history or failed CI is a release blocker.
