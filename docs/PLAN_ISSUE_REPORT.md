# plan.md — System Issue and Verification Report

Date: 2026-10-09. Repository: `curib123/pk_document_tracking_system`.
Delivery: PR #16, `fix/plan-first-login-gate-20261009`, based on `master` commit `a3c35d79397d465d6e282ff8d9b219a42700e4e1`.

## Result and meaning of “verified”

The confirmed issues below have code fixes and passing listed automated checks. “Verified” means the stated reproducible cases passed; it is **not** a claim that every production environment, security threat, load pattern or historical database has been tested. No production database was imported, migrated or changed. The original `plan.md` and its requirements remain intact.

The first partial PR is superseded by this complete plan-scope implementation and regression pass. Direct actions remain native CI3 form posts and execute authorized domain operations immediately. Requests use the published workflow and immutable submitted approval snapshots. No REST/AJAX layer or replacement schema was introduced.

## Executed verification

| Area | Actual execution | Result |
| --- | --- | --- |
| PHP / source / unit | PHP 8.4.24; 97 application PHP files linted; schema, authentication, delegation, document scope, folders, categories, query state, request policy, workflow graph/view and dashboard tests | Passed |
| JavaScript / test syntax | `node --check` for every application JS file; Python test compilation | Passed |
| Database integration | Disposable MariaDB 11.8.6 `pk_dts_test`; original regression suites plus **53 additional HTTP/SQL assertions** | Passed |
| Original workflows | Login and 22 module routes; Places CRUD; direct/request hardcopy effects; softcopy create/revise/cancel; access and assignments; published two-stage approvals; physical dispatch/accept; disposal and daily JSON audit | Passed |
| Browser rendering | Real Chromium with Playwright 1.57.0; authenticated server-rendered HTML, pinned application CSS/JS, **407 rendering/interaction assertions** | Passed |
| Responsive coverage | 23 module pages plus draft/published filter states at 1440, 768, 390 and 320 pixels; modal interactions on desktop/phone; required-password dialog on desktop/phone/narrow phone | Passed |
| Schema preservation | 29 original tables, 60 foreign keys, 29 index declaration groups and original column definitions | Passed; no schema changes |
| Diff hygiene | `git diff --check` | Passed |

The browser environment blocks direct localhost navigation. Browser checks therefore render actual authenticated PHP responses offline with the production Bootstrap/jQuery/Chart.js/application code. Native submissions, authentication, CSRF-bearing posts, database effects and file downloads are separately exercised against the running HTTP server. This is **not** described as a full network-driven browser end-to-end run. External icon/web fonts are not fetched by the rendering harness; system font fallback is used.

CI reruns verification against PHP 8.2 and MariaDB 10.11. The authoritative remote run status is the PR's Checks tab; it must not be inferred from the local results in this report.

## Requirement coverage

| plan.md section | Implementation / verification |
| --- | --- |
| 1. Folder and table views | Shared relational hierarchy, breadcrumbs/selected key, cards/table using identical SQL results and filter context |
| 2. Workflow Builder | Step Actions modal, all versions, approval steps, four approver types, draft-only edits and validated publication |
| 3. Filters/search | Shared query state; module-relevant status/type/role/owner/parent/date/publication filters, sorting, pagination and reset |
| 4. Roles/runtime | Server permission and object scope, delegation restrictions, session invalidation and active-task visibility |
| 5. First-time password | Random one-time temporary credentials, hashed storage, server gate, mandatory dialog and permanent-password replacement |
| 6. User dashboard | Actual authorized document counts, own requests, assigned approvals, handoffs and scoped activity |
| 7. Navigation/modules | Concise module/page context; original hardcopy/controlled-softcopy registers preserved |
| 8. Responsiveness | Shared table/tree/card/modal/nav styles and actual four-size render checks |
| 9. Document views | Selected-record identity, location/category, revision/files, requests, workflows, transfers and authorized audit sections |
| 10. Transfer population | Fixed malformed form attribute; correct source metadata, dependent resets and destination validation |
| 11. Direct versus Request | Shared fieldsets/domain effects; direct path does not insert request/workflow rows; requests require published approvals |
| 12. Refactoring | Reusable query/folder/permission policies, document detail model, action renderer and revision domain operation |
| 13. Request/Places pages | Restored deleted dependencies, validated CRUD, readable request proposals, filters and page rendering |
| 14. Pie/visual analytics | Real scoped document doughnut, request chart, counts/percentages, empty/failure fallback and reduced motion |
| 15. Audit/testing | Prioritized issue register below, actual executed tests, implementation notes and explicit limitations |
| 16. Implementation rules | Existing schema/data model preserved; original plan retained; tested changes isolated on the development branch |

## Prioritized issue register

Each issue below is **Test Status: Passed** and **Resolution Status: Verified for the listed cases**. Reproduction refers to the pre-fix code. Expected result is the described fix operating without the observed defect. The machine-readable companion contains every requested report field.

### SYS-003 — Temporary-password gate bypass (Critical)

**Module:** Authentication. **Reproduce:** Log in with a temporary password, then request dashboard, tasks or a private file URL directly.

**Observed behavior / confirmed root cause:** authenticate() did not enforce the requirement consistently; dashboard data was permitted before onboarding.

**Expected result / implemented fix:** Central server gate with only password setup/change/logout allowlisted; standalone mandatory dialog without protected navigation or analytics.

**Verification:** tests/auth_policy.php; first-login HTTP tests; mandatory-dialog Chromium checks. **Status:** Passed / Verified for covered cases.

### SYS-005 — Delegated privilege escalation (Critical)

**Module:** Roles and permissions. **Reproduce:** Use a delegated user/role manager to assign Administrator or grant a permission outside their own role.

**Observed behavior / confirmed root cause:** Module-level edit permission alone was insufficient to constrain the privileges being delegated.

**Expected result / implemented fix:** Validate both current and proposed target roles and permission subsets on the server; reserve Administrator; prevent self-disable, last-admin removal and reporting-line cycles; invalidate affected sessions.

**Verification:** tests/account_policy.php; delegated-user and delegated-role HTTP denial checks. **Status:** Passed / Verified for covered cases.

### SYS-006 — Overbroad document data exposure (Critical)

**Module:** Documents and dashboard. **Reproduce:** Sign in as a staff account and inspect another account's document list, option payloads and dashboard.

**Observed behavior / confirmed root cause:** Document metadata queries were not consistently derived from the actor's ownership, assignment and active-grant scope.

**Expected result / implemented fix:** Shared SQL scope for registers, owner choices, related details, requests and analytics; discovery catalog does not grant register access.

**Verification:** tests/document_scope.php; two-account register/catalog/dashboard HTTP checks. **Status:** Passed / Verified for covered cases.

### SYS-007 — Metadata versus file-access mismatch (Critical)

**Module:** Private files. **Reproduce:** Attempt an approved-file download without access, with an expired grant, then after revocation.

**Observed behavior / confirmed root cause:** File permission decisions did not consistently separate metadata/catalog capabilities from permission to read private file bytes.

**Expected result / implemented fix:** Require active document scope plus file capability; honor grant status, expiry and revocation; keep review limited to the requester or exact active approver.

**Verification:** File-scope policy and private download/current grant/revoked grant HTTP checks. **Status:** Passed / Verified for covered cases.

### SYS-001 — Missing runtime service (High)

**Module:** Places. **Reproduce:** Open any Places module; submit a valid Places form.

**Observed behavior / confirmed root cause:** The controller required a service file deleted from the branch, causing a server exception.

**Expected result / implemented fix:** Restore the original service and add a required-service dependency check.

**Verification:** tests/required_services.php; six-module Places HTTP/SQL smoke. **Status:** Passed / Verified for covered cases.

### SYS-002 — Missing route view (High)

**Module:** My Requests. **Reproduce:** Open My Requests on any request tab.

**Observed behavior / confirmed root cause:** The native own-request view wrapper was missing, causing a page-render error.

**Expected result / implemented fix:** Restore the wrapper and guard required route views.

**Verification:** tests/route_views.php; all request tabs in smoke and browser checks. **Status:** Passed / Verified for covered cases.

### SYS-004 — Incomplete secure first-login lifecycle (High)

**Module:** Accounts. **Reproduce:** Create a user, reuse the temporary password or submit mismatched confirmation.

**Observed behavior / confirmed root cause:** Account creation used administrator-entered passwords; the setup flow lacked a complete mandatory confirmed-password lifecycle.

**Expected result / implemented fix:** Generate 96-bit random temporary credentials; store only password hashes; show credentials once in a no-store response; reject reuse and mismatches; rotate session version.

**Verification:** 100 random credential policy cases; account creation/password replacement/session HTTP checks. **Status:** Passed / Verified for covered cases.

### SYS-008 — Read permission treated as write authority (High)

**Module:** Direct document actions. **Reproduce:** Attempt direct changes to a document that is only readable or is held by another non-administrator.

**Observed behavior / confirmed root cause:** Document-level mutation checks were inconsistent with the list/button permission scopes.

**Expected result / implemented fix:** Separate manageable/write scope from readable scope; validate direct-action and disposal capabilities; enforce custody and active status inside domain operations.

**Verification:** Four direct/read-only policy cases; native non-admin custody/direct/disposal regression suites. **Status:** Passed / Verified for covered cases.

### SYS-010 — Cycles and invalid parent assignments (High)

**Module:** Category hierarchy. **Reproduce:** Attempt to make a category its own parent or place a parent under its descendant.

**Observed behavior / confirmed root cause:** Parent mutation did not prevent all cycles or consistently validate the active ancestry.

**Expected result / implemented fix:** Lock category changes; validate ancestors, depth, active parents and optimistic versions; safely bound malformed legacy trees.

**Verification:** tests/category_parent.php; descendant-parent HTTP rejection. **Status:** Passed / Verified for covered cases.

### SYS-013 — Source selection does not populate the form (High)

**Module:** Hardcopy transfers. **Reproduce:** Open a transfer request; select and then change its hardcopy document.

**Observed behavior / confirmed root cause:** PHP removed a newline after the conditional attribute, merging data-hardcopy-transfer with data-confirm; the JS could not find its form. Stale destination state also remained.

**Expected result / implemented fix:** Emit separated data attributes; populate exact title/reference/Places/holder; clear stale recipient and destination on source changes; revalidate destination/custody server-side.

**Verification:** Desktop/mobile transfer selection, source-change and clear-selection Chromium regressions; transfer HTTP lifecycle. **Status:** Passed / Verified for covered cases.

### SYS-015 — Unsafe draft/publication transitions (High)

**Module:** Workflow editing and publication. **Reproduce:** Clone repeatedly, edit a stale draft, move steps and publish invalid approvers/graphs.

**Observed behavior / confirmed root cause:** Workflow mutation needed a consistent transaction/lock boundary, stale-editor checks and publication validation.

**Expected result / implemented fix:** Serialize definition/version changes; graph-hash checks; validate shape/order/active targets; one editable draft and one current published default; preserve existing request snapshots.

**Verification:** tests/workflow_graph.php; clone/stale edit HTTP checks; complete published two-stage workflow smoke. **Status:** Passed / Verified for covered cases.

### SYS-016 — Submitted or stale requests can be overwritten (High)

**Module:** Request drafts. **Reproduce:** Save a request, change its type by POST, or submit it and replay an older edit form.

**Observed behavior / confirmed root cause:** Save operations lacked a single enforced owner/status/type/version boundary.

**Expected result / implemented fix:** Lock the request and enforce immutable type, draft/returned status, actor ownership and version; validate action capability and selected document again on submission.

**Verification:** tests/request_policy.php; forged target, changed type, stale edit and submitted-edit HTTP checks. **Status:** Passed / Verified for covered cases.

### SYS-018 — Duplicate unaudited revision path (High)

**Module:** Revision upload. **Reproduce:** Use Upload Revision, then repeat the same revision level.

**Observed behavior / confirmed root cause:** The upload form used a separate insert/max-revision path without the shared locked domain operation and document audit entry.

**Expected result / implemented fix:** Delegate to the same Direct service and locked revision effects; enforce scope and duplicate-level validation; clean failed staged files.

**Verification:** HTTP upload audit assertion; repeated-level rejection with unchanged file/revision counts. **Status:** Passed / Verified for covered cases.

### SYS-009 — Unsynchronized presentation/context (Medium)

**Module:** Folders and registers. **Reproduce:** Select a nested folder, apply a search/sort, and change between cards and table.

**Observed behavior / confirmed root cause:** A complete shared hierarchical presentation state was missing.

**Expected result / implemented fix:** Use one validated relational folder tree, breadcrumb and selected key; both layouts render the same paginated records and preserve filters.

**Verification:** tests/folder_tree.php; folder SQL smoke; identical record-ID integration assertion. **Status:** Passed / Verified for covered cases.

### SYS-011 — Filters/sorting not consistently applied (Medium)

**Module:** Search and filters. **Reproduce:** Use status, role, owner, parent, type, date and publication filters; combine with sorting and pagination.

**Observed behavior / confirmed root cause:** Controllers and models did not consistently share validated query state; several sort/filter controls were presentation-only or incomplete.

**Expected result / implemented fix:** Central query-state normalization, allowlisted SQL sorts, relevant filters, stable pagination and reset links.

**Verification:** tests/query_state.php; date/filter/publication/parent SQL assertions; all filtered page renders. **Status:** Passed / Verified for covered cases.

### SYS-012 — Incomplete selected-record details (Medium)

**Module:** Document view modal. **Reproduce:** Open hardcopy and softcopy details, then open a different record.

**Observed behavior / confirmed root cause:** The flat modal omitted connected revision, location, file, request, workflow and audit context.

**Expected result / implemented fix:** Build permission-scoped detail sections in bounded batch queries; reuse safe text rendering for eye and table-cell actions; expose authorized file actions only.

**Verification:** Document-detail HTTP assertions; two-record desktop/mobile modal interactions. **Status:** Passed / Verified for covered cases.

### SYS-014 — Missing Step Actions version modal (Medium)

**Module:** Workflow Builder. **Reproduce:** Open Step Actions for a workflow with both published and draft versions.

**Observed behavior / confirmed root cause:** Only the latest version was exposed and the required modal/version context was incomplete.

**Expected result / implemented fix:** Show all versions, dedicated Approval Steps, current-default availability and read-only published routes; provide Create New Step for authorized drafts.

**Verification:** tests/workflow_view.php; version filtering and desktop/mobile modal interactions. **Status:** Passed / Verified for covered cases.

### SYS-017 — Stale dependent fields and action context (Medium)

**Module:** Shared Direct/Request forms. **Reproduce:** Switch selected documents and action types, or reopen an edit form.

**Observed behavior / confirmed root cause:** Shared field groups and source values were not consistently reset or initialized.

**Expected result / implemented fix:** Central action/domain/approver visibility; load selected metadata; retain proposed draft values on edit; clear stale revision/file fields on source change; keep effective date optional.

**Verification:** Direct/request native-form smoke; workflow/transfer interactive browser checks; shared fieldset contracts. **Status:** Passed / Verified for covered cases.

### SYS-019 — Missing scoped analytics and pie chart (Medium)

**Module:** Dashboard. **Reproduce:** Open dashboards for administrator and restricted staff accounts, including an empty scope.

**Observed behavior / confirmed root cause:** The page mostly displayed a request-status bar chart without the requested document distribution or scoped operational metrics.

**Expected result / implemented fix:** Database-derived document split, created count, assigned approvals, handoffs, own request status/completion and scoped recent activity; accessible counts/percentages and empty/library-failure fallbacks.

**Verification:** tests/dashboard_view.php; exact restricted-user pie count; actual Chart.js initialization at four viewport sizes. **Status:** Passed / Verified for covered cases.

### SYS-020 — Inconsistent module context and mobile behavior (Medium)

**Module:** Navigation and responsive layout. **Reproduce:** Open registers, modals, workflow steps and dashboard at desktop/tablet/mobile widths.

**Observed behavior / confirmed root cause:** Top navigation used generic descriptive text and layout/interaction rules were not centralized for the required presentation.

**Expected result / implemented fix:** Concise right-side module/page labels, responsive cards/tree/tables/modals, mobile menu focus/Escape handling, Roboto/system fallback and reduced-motion charts.

**Verification:** 25 URL states at 1440/768/390/320 pixels; no page-level horizontal overflow; keyboard/menu/modal checks. **Status:** Passed / Verified for covered cases.

### SYS-021 — Assigned approver cannot discover My Tasks (Medium)

**Module:** Approval permissions. **Reproduce:** Assign a step to a user who lacks broad request-management permissions; open their dashboard.

**Observed behavior / confirmed root cause:** Sidebar visibility was based only on broad request/transfer capabilities, not a real active assignment.

**Expected result / implemented fix:** Include exact active user/role assignments in task visibility while preserving server-side step ownership checks; cache role grants per request.

**Verification:** Sole-approver dashboard/task HTTP checks; unauthorized requester approval remains blocked. **Status:** Passed / Verified for covered cases.

### SYS-022 — Undefined variable on zero results (Medium)

**Module:** Empty document registers. **Reproduce:** Filter the softcopy register to a date with no records.

**Observed behavior / confirmed root cause:** The upload modal used a per-row canEdit variable that was undefined when there were no rows.

**Expected result / implemented fix:** Gate the shared modal using the module capability; retain per-record edit/upload checks on its buttons.

**Verification:** Empty future-date register HTTP regression; all zero-result page warning checks. **Status:** Passed / Verified for covered cases.

### SYS-023 — Silent record cap and no shared filtering (Medium)

**Module:** Administrative assignments. **Reproduce:** Open Assign Documents and search, sort or navigate a large result set.

**Observed behavior / confirmed root cause:** The old custom table stopped after 100 assignments and did not offer the shared query controls.

**Expected result / implemented fix:** Replace with shared search/user filter/sort/pagination and layout context; validate a submitted document by ID/folder instead of trusting the first 500 selection choices.

**Verification:** Assignment search/pagination HTTP assertions; folder-assignment smoke; responsive browser route checks. **Status:** Passed / Verified for covered cases.

### SYS-024 — Missing human-readable proposal context (Medium)

**Module:** Request details. **Reproduce:** View a saved/assigned request involving Places, a recipient or a proposed revision.

**Observed behavior / confirmed root cause:** Request views lacked consistent labels for proposed values and selected source details.

**Expected result / implemented fix:** Batch-resolve source title/reference, proposed Places/category/holder/recipient and revision/retention/disposal fields without exposing raw reference IDs as labels.

**Verification:** Scoped task/detail HTTP checks; request-page native regression and render checks. **Status:** Passed / Verified for covered cases.

### SYS-025 — Insufficient runtime and contract checks (Low)

**Module:** Regression infrastructure. **Reproduce:** Run the old suite with deleted views/services or an empty register.

**Observed behavior / confirmed root cause:** Earlier static checks contained an interpolated token and did not exercise all plan-specific behaviors.

**Expected result / implemented fix:** Repair the static assertion, verify shared delegation rather than inline duplication, add policy/view/HTTP/Chromium tests and reproducible test entry points.

**Verification:** tests/run_unit.sh; 53 additional HTTP/SQL assertions; 407 Chromium rendering/interaction assertions. **Status:** Passed / Verified for covered cases.

## Changes and maintenance notes

Application changes cover controllers, models, transactional services and shared server-rendered views. New policies isolate authentication, delegation, metadata/file/write scope, query state, folder ancestry, request editing and workflow graph validation. `File_service::save_revision()` now delegates to the shared direct-revision transaction instead of duplicating file and revision persistence. Assignment lists and document cards reuse the same table/action components.

There are **no database DDL, data migration, seed, API or public-storage changes** in this repair. Existing daily operational JSON audit remains private filesystem storage. Existing `status_history`, workflow and transfer tables retain their original document-business-history purpose; no additional audit table is introduced.

Document detail history is bounded and visibly labeled (latest 25 per document; bounded approval events) to avoid unbounded per-row loading. Large choice lists remain bounded; folder navigation and assignment-page search narrow administrative choices. Measure real dataset cardinalities before increasing limits or introducing a separately approved remote-search design.

## Unresolved issues and verification limits

No known reproducible failure remains in the plan-scope fixtures after the recorded final runs. The following are **not tested or not deployed**, rather than silently marked passed:

1. The user's actual Windows/XAMPP instance, production credentials, Apache rules, filesystem ACLs, historical attachments and real company records were not accessed. No production deployment or merge is implied by this report.
2. Full browser-driven network navigation is blocked in this environment; the offline-render/real-HTTP split is explained above. Real-device Safari/Firefox and external CDN/icon-font failure behavior beyond available fallbacks still require deployment smoke checks.
3. Multi-user contention stress, production-scale query timing, malware scanning for uploaded documents, backup/restore drills and disaster recovery were not executed. Row locks and version/hash checks reduce stale-write risks but are not presented as a completed load/security certification.
4. Historical malformed/orphaned hierarchy data is handled defensively and surfaced, not destructively rewritten. Reconcile any real legacy anomalies against a backup under administrator supervision.
5. Keep bootstrap/test-runtime provisioning workflows out of the final release. Retain only the regular verification workflow and documented test tools.

Before production cutover, back up the existing database and private files, install dependencies, configure the documented environment and writable private storage, then smoke-test with administrator, staff and a specifically assigned approver. Do not import fresh-install SQL over an existing database.

## Reproducing the checks

`bash tests/run_unit.sh` runs syntax, source-schema, policy and view checks. `bash tests/smoke.sh` requires a **fresh disposable** MariaDB database named `pk_dts_test` on localhost and uses synthetic test-only credentials; it never targets the production database name.

For browser rendering, install Playwright 1.57.0 and its Chromium runtime, set `PK_BROWSER_ASSETS` to a temporary directory and run `bash tests/fetch_browser_assets.sh`. Then run the smoke suite with `PK_BROWSER_TEST=1`. Set `PK_CHROMIUM` only when using an already installed Chromium executable. Screenshots and machine-readable results go to temporary test-output directories, not the application's public file storage.
