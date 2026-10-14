# plan.md implementation checklist — October 14, 2026

Implemented on feature branch feat/complete-plan-md-20261014. Server operations preserve CodeIgniter 3 MVC, permission checks, database source records and existing document workflows.

| Plan item | Implementation |
|---|---|
| Blur outside open modals | Centralized blurred static modal backdrop; outside clicks and Escape still cannot lose edits. |
| Automatic and manual cleanup | Existing three-month post-backup retention preserved; manual Clean up now added to Backup & Restore. |
| Assign documents by Location | New Assign by Location form; server locks/assigns all active Hardcopy documents in the chosen active Location to an active user in one transaction. |
| Current user receives Access/Assignment request | Form shows current signed-in user; server overwrites forged recipient_id, only on self-service Access/Assignment requests (admin direct assignment and physical transfer remain separate). |
| Print controlled file | Secure print preview action for approved PDF, JPEG, PNG and TXT revisions with the same permission check as Download; DOCX/XLSX must be downloaded for local native printing. |
| 12-hour time | Visible 24-hour datetime strings are reformatted into AM/PM without changing stored SQL values or user inputs. |
| Notification bell and panel | Unread-only panel beside avatar with 5/10/20/50 server page sizes, Previous/Next navigation, automatic unread polling, and read receipt only after selecting an item. No automatic interruptive popup. |
| View modal design | Standardized spacing, accessible modal geometry, header/footer surface and responsive details cards. |
| No View Details on Area, Specific and Asset | Removed action and clickable View row details from those reference screens; Edit/Deactivate retained per permission. |
| Date range dropdown | Today, This week, This month, This year, Custom range + All time. Computed in Asia/Manila by Query_state for document and request tables. |
| Password > 8 characters | 9–72 bytes enforced by server and form; initial admin creation policy aligned. |

## Setup prerequisites and limitations

- The controlled file Print action uses the browser's native print/preview UI for PDF, image and text. DOCX/XLSX require the installed application and cannot be printed reliably by a native browser preview.
- Task notification receipts must already be migrated on existing installations using database/migrations/20261013_task_notification_receipts.sql. Running current code does not execute SQL migrations automatically.
- Your existing XAMPP installation must be deployed/restarted to load new routes, JS and CSS. Merging a GitHub branch does not update the running office PC.
- UI and access controls still require a manual authenticated desktop/mobile browser pass.
- The three-month automatic/manual cleanup policy also handles daily audit JSON and log archives. It never deletes controlled documents. Verify retention requirements with the office before deploying.

## Verification

- Run bash tests/run_unit.sh (PHP and Node).
- On disposable MariaDB, run bash tests/smoke.sh and the extra integration tests in the GitHub Actions database job.
- For notification inbox, confirm unrelated users cannot see tasks, unread count agrees with server pagination, and selecting one item marks only that eligible occurrence as read.
- For by-Location assignment, confirm all active Hardcopy documents there update the intended holder and that the operation rolls back if any recipient/document validation fails.
- For date range, test week boundaries, December-to-January year transitions, invalid from/to and custom range pagination.
