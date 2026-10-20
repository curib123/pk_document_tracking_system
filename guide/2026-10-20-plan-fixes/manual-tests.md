# Manual QA checklist — October 20, 2026

**Environment:** disposable PHP 8.0, CodeIgniter 3, MariaDB/MySQL, separate QA accounts, normal permissions and workflow. Back up the QA database before testing. Do not use real production documents.

Actors: A = request initiator, B = existing hardcopy holder (different from A), C = transfer destination recipient, D = unrelated requester. Approvers can be separate test accounts.

| Case | Reproduction steps | Expected result |
| --- | --- | --- |
| CON-01 | A submits access request for hardcopy H; before approval A creates an assignment request for H. | Save rejected with a readable conflict; original request and workflow remain unchanged. |
| CON-02 | A saves an assignment draft for H; A submits access for H; try submitting earlier assignment draft. | Draft can exist, but second submission is blocked. |
| CON-03 | A submits a transfer request for H; A tries access and assignment requests for H. | Both are blocked while the first request is submitted. |
| CON-04 | D requests H while A has a submitted request for H. | D is not blocked **by A's request**; ordinary document and action permissions still apply. |
| CON-05 | A requests softcopy ID N then hardcopy ID N (same numerical ID, separate records). | No false conflict across softcopy and hardcopy domains. |
| CON-06 | Resolve the first request (approved/completed/rejected); A tries again. | Submitted-only conflict no longer blocks a new request. |
| CON-07 | Use two authenticated A sessions to submit access and assignment for H at the same instant. | At most one reaches submitted state. No duplicated active workflow for this combination. |
| TR-01 | A requests H transfer from holder B to recipient C; approve it. | My Tasks > Hardcopy Transfer shows the for_transfer handoff to A, not to B unless B is the requester. |
| TR-02 | As B and D attempt direct HTTP POST to Dispatch using the transfer ID. | Rejected; status, custody and workflow history unchanged. |
| TR-03 | As A, dispatch approved transfer. | Status becomes in_transit, transferred_by is A; holder/location not yet changed. |
| TR-04 | As B or D try Accept, then C accepts. | Unauthorized attempts rejected; C can accept; transfer completed, holder/location updated, prior holder grants revoked when applicable, history correct. |
| TITLE-01 | Open access, assignment and transfer records in both My Requests and My Tasks. | Document Title column and viewer display the exact source document title. |
| TITLE-02 | Open disposal request before and after approval, including a disposed hardcopy. | Document Title persists in both datatable and viewer. |
| TITLE-03 | Open creation and update/revision requests with a proposed title. | Proposed title appears; source record is not treated as renamed before approval. |
| TITLE-04 | In disposable fixtures, create orphaned request with subject, and one with no title/subject. | Falls back to subject then Request #ID; never blank or Not Found. |
| UI-01 | Search, paginate, change tabs, use View and action buttons at 375px and desktop widths. | No stale row, broken action, clipped controls, or JS error. |
| SEC-01 | Test all handoff endpoints with another user, including an administrator who is neither requester nor destination. | Exact actor ownership checked server-side, independent of button visibility. |

Use guide/05-results-template.md to log each case with status, actor, request/transfer ID, screenshot, SQL snapshot and server/browser logs. Run the automated suite, observe the GitHub Actions result for the tested commit, and block release if a negative permission/concurrency test fails.
