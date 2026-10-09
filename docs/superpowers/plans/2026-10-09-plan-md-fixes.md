# plan.md System Repairs Implementation Plan

**Goal:** Repair the confirmed defects and implement the requirements in `plan.md`, preserving the original database and native CI3 form-post architecture.
**Architecture:** Shared model-level document scopes; controller authentication/permissions; reusable fieldsets and table/folder views; transactional domain services. No REST layer or destructive migration.
**Tech stack:** PHP 8 / CodeIgniter 3.1.13, existing MariaDB schema, Bootstrap 5, jQuery, Chart.js.
**Spec:** `plan.md`.

## Global constraints
- Preserve the 29 source tables, original columns, indexes, foreign keys, records and request snapshots.
- Keep direct actions separate from workflow submission and approval.
- Operational audit is daily private JSON, not a new database table.
- Treat unexecuted checks as unverified; retain an issue report with remaining limitations.

## Review focus
- First-login users without dashboard permission, direct URLs and stale sessions.
- Cross-user document IDs in lists, choices, file downloads and mutations.
- Cyclic folder parents and workflow edits racing publication.
- Returned requests, repeated submissions and transfer source changes.
- Long names, empty data, mobile modals, keyboard navigation and chart failures.

## Tasks (execute inline, with regression evidence)
1. **Runtime baseline** — Restore `services/places/place_service.php` from its last valid revision. Add `tests/required_services.php`, repair the malformed static test literal, run PHP lint and contract tests. Verify Places integration in CI.
2. **Identity and authorization** — Add password generation/validation in `services/authentication/authentication_service.php`; guard `MY_Controller`, `Auth`, user/role mutations and first-login modal. Add `tests/auth_policy.php` plus DB regressions for onboarding and privilege escalation.
3. **Document scope** — Centralize accessible-document SQL in `Document_model`; use it in listing/options, direct and request operations, file access and dashboard totals. Add two-account/expired-grant regression cases.
4. **Navigation and details** — Add synchronized folder/grid-table state, hierarchy tree, relevant filters and allowlisted sorting; keep pagination/filter query state. Add structured document/request details and fix transfer/edit population. Test invalid folders, changes between records and view switching.
5. **Workflow builder** — Show all versions in Step Actions modal, edit only drafts, publish one active version, restrict buttons and serialize mutations. Add draft/role/user/leader/self route and rollback regressions.
6. **Dashboard and layout** — Authorized analytics, request distribution/pie, document split and activity; consistent topbar; responsive shared modal/table/tree/chart styles. Exercise rendered views at phone/tablet/desktop sizes.
7. **QA and delivery** — Run lint, static/unit tests, database smoke and browser regression tests; record exact outputs, unresolved limitations, changed files and requirement coverage in `docs/PLAN_ISSUE_REPORT.md`. Update PR 16 without overwriting concurrent edits. Do not merge unverified changes.
