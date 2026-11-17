# Scanned documents

Users upload files scanned outside this application. There is no scanner or camera integration.

Use the shared **Table / Folder** switcher to browse records in a table or the expandable category tree. Selecting a folder includes its nested categories; search, pagination, status filters and access rules apply in both layouts.

1. Open **Scanned Documents → Upload / Request Category**.
2. To add a folder, choose **Create Category / Folder**, enter its name, select an approved parent (or no parent for a root folder), and give a reason. Save the draft, then submit it.
3. A Document Control Officer approves the category in **My Tasks → Scanned Document Request**. Only approved folders appear in upload forms. Nested folders show their full paths.
4. Choose **Upload Scanned Document**, enter a title, approved folder, retention start/end dates and a reason. Attach up to 20 files, 15 MB each. Supported formats: PDF, PNG, JPEG, TXT, DOCX and XLSX. Save, then submit for approval.
5. To change or delete an approved record, use **Request Update** or **Request Deletion**. The existing record stays available until final DCO approval. Updating without attachments keeps current files; selecting attachments replaces the entire current set after approval. Deletion hides the record and its files; request history and reasons remain available.

All proposals and approval decisions require remarks. Created and updated timestamps appear in the register. The retention countdown shows days and hours remaining; the end date includes the entire day in Philippine time. A popup appears after expiration, checked approximately once per minute while the application is open. Expiration does not automatically delete a document.

At **4:00 PM Asia/Manila**, users with upload permission receive a daily reminder to upload outstanding scanned files. An optional **Next Scanned File Upload Due** date adds document-specific reminders. Returning after 4:00 PM catches up the day's reminder. Daily reminders are acknowledged per user in the current browser. Click **Enable reminder sound** to allow an audible alarm; browser interaction is required for sound. Popups and sound require an open, signed-in application; this is not an operating-system background alarm.

## Permissions and workflow setup

**Roles & Permissions** includes Scanned Documents: View, Upload, Request Update and Request Deletion, plus Scanned Categories: Request Category. General Requests permissions are also needed to create, edit and submit drafts. Existing softcopy view/request grants inherit scanned view/upload and category requests during migration. Administrators assign update/delete permissions separately.

**Workflow Builder** includes upload, update, deletion and category creation routes. They are initially published with a DCO approval step if an active Document Control Officer role exists. All four request types go to DCO by default and take effect only after the configured approvals finish. Administrators can edit approvers and add steps in Workflow Builder; published workflow settings determine the route, without a hardcoded DCO role check. If a configured approver role is missing or has no active member, requests cannot proceed until configured. Administrators cannot directly bypass scanned approval.

For an existing installation, apply `database/migrations/20261117_scanned_documents.sql` once (reruns are safe). The repository's database import also includes this migration. Existing document tables and records are preserved.

## Verification

Run `php tools/tests/scanned_policy.php` and `php tools/tests/scanned_integration.php`. The integration test clones only table definitions from the configured database, creates synthetic fixtures in a randomly named `scanned_*_test` database, tests approval and reminder boundaries, then removes its fixtures. It needs local MySQL credentials with create/drop database privileges.

For browser checks, use `python tools/tests/scanned_browser.py` with Playwright and Microsoft Edge installed. It creates its own fixture database and PHP server, tests multi-file upload, approver review, file access, mobile layout and reminder popups, then removes the fixtures.
