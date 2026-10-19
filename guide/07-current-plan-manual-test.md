# Latest plan.md — acceptance checks (16 October 2026)

These checks apply to the `development` plan.md with five defined issues.
Use a **disposable XAMPP/PHP 8.0 + MariaDB/MySQL clone**, not production.

## Before applying the database change

1. Take a verified complete backup (database, private files, audit logs, application source).
2. Restore it to a test system. Note its PHP version, database name, and Git commit SHA.
3. On an **existing database with `locations.code`** (not a new install), execute
   `database/migrations/20261016_location_sequence_remove_code.sql` **once**.
   It drops `locations.code` and the old `sequences` table; DDL cannot be undone transactionally.
4. For a **fresh database**, import `database/pk_dts.sql` instead; do NOT rerun the upgrade migration.
5. Verify with:
   - `SHOW COLUMNS FROM locations;` — must contain `sequence_number`, not `code`
   - `SHOW INDEX FROM locations;` — should contain `idx_locations_specific_sequence`
   - `SHOW TABLES LIKE 'sequences';` — must return no results
   - `SELECT id, name, specific_id, sequence_number FROM locations ORDER BY specific_id, sequence_number;`
6. Check the automatic daily backup scheduler separately: the database migration does not create or alter a scheduled task.

## Manual test cases

| ID | Steps | Expected outcome |
| --- | --- | --- |
| PLAN-01 | As a restricted employee (A), create or hold a hardcopy. Approve transfer to employee B, dispatch, then accept as B. | After acceptance A no longer finds the hardcopy in Documents, search, folder tree or assigned documents. B sees it. The transfer remains in audit history. |
| PLAN-02 | Try the old document URL as A; try with a role that has intentionally assigned `documents.view_all`. | Restricted A is denied. A genuinely global authorized viewer still has its explicit role access. |
| PLAN-03 | Grant A temporary access before physical transfer; accept the transfer as B. | A's hardcopy grant is revoked and the URL/list is denied. Other users' unrelated grants remain unchanged. |
| PLAN-04 | In Admin → Users, open Assigned Documents on a user with zero hardcopy, five softcopy, and twelve hardcopy assignments. | Modal shows correct Total/Hardcopy/Softcopy counts, compact tabs, search, empty state, and page controls. |
| PLAN-05 | Search an assigned document, use pagination and Manage assignments, then close and open a different user. | Correct user data only, correct/manageable URLs and no stale text after reopening. |
| PLAN-06 | In Places → Locations, create A (no sequence), B (no sequence), C (sequence 3) under one Specific. | Automatic A = 1, B = 2, C = 3; duplicate sequence in the same Specific fails with a readable error. |
| PLAN-07 | Create a Location under another Specific; edit a Location with and without changing its parent. | The new Specific has its own 1-based ordering; edits retain existing sequence unless changed. |
| PLAN-08 | Check Location list/detail, the Hardcopy form, transfer destinations, folder browser and assignment Location picker. | No Location Code controls, selectors, columns or references; selected locations identify their sequence. |
| PLAN-09 | Create a new draft request of every action: softcopy create/revise/cancel, hardcopy create/update, transfer, disposal, access, assignment. Open View immediately. | Opens a readable View with request title, status, proposed fields, Approval Process Timeline and Workflow Activity Log. No stuck loading or JS errors. |
| PLAN-10 | Test View on submitted/returned/completed requests, and browse to another row after search/page change. | Correct content every time, correct scoped history; no stale details, broken modal or crashed browser. |
| PLAN-11 | With Chrome dev tools, block CDN JavaScript and test View/Assigned Documents again. | App's offline modal fallback still opens/closes correctly and preserves controls. |
| PLAN-12 | On 375px mobile width, navigate the document modal tabs, assigned-document lists and Location form. | No clipped buttons, no horizontal page overflow, focus and scrolling work. |

## Automation and release gate

- Run `composer test` or `bash tests/run_unit.sh` on PHP 8.0 with Node 22.
- Run the GitHub Actions matrix for PHP 8.0/8.2/8.4 and disposable MariaDB integration tests.
- For new database install run `python3 tests/schema_install.py` only when pointed at your **QA** server.
- Record pass/fail and evidence in `guide/05-results-template.md`.
- Any failed negative permission test, SQL migration, uncontrolled data loss, or request View crash blocks merging.

The schema migration is intentionally not run against the live user's database by this pull request.
