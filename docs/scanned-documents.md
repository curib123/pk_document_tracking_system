# Scanned documents

Users upload files scanned outside this application. There is no scanner or camera integration.

Use the shared **Table / Folder** switcher to browse records in a table or the expandable category tree. Selecting a folder includes its nested categories; search, pagination, status filters and access rules apply in both layouts.

1. Open **Scanned Documents → Upload / Request Category**.
2. To add a folder, choose **Create Category / Folder**, enter its name, select an approved parent (or no parent for a root folder). Save the draft, then submit it.
3. Choose **Submit Request** for approval or **Save Draft** to continue later in **My Requests**. The assigned approver opens **My Tasks**, filters by **Scanned Documents**, and selects **Review request**. Only approved folders appear in upload forms. Nested folders show their full paths.
4. Choose **Upload Scanned Document**, then enter the subject, title, optional description of up to 2,000 characters, approved folder, and retention dates. Attach up to 20 PDF, PNG, JPEG, TXT, DOCX or XLSX files, up to 15 MB each. Choose **Submit Request** or **Save Draft**.
5. To change or delete an approved record, choose **Update** or **Remove** on its row. The request form opens with the action and document selected. The existing record stays available until final approval. Updating without attachments keeps the current files. Attaching files replaces the current set after approval. Deletion hides the record and its files while keeping the request history.

Scanned requests and approval decisions do not ask for remarks or reasons. File details use the shared inline preview and Fullscreen controls for PDF, images and text; Word/Excel are converted to a preview PDF using LibreOffice, as with other document types. Downloads retain the original file. Preview and download access are checked for the requester or current approver while pending, and against document access after approval. Created and updated timestamps appear in the register. The retention countdown shows days and hours remaining; the end date includes the entire day in Philippine time. A popup appears after expiration, checked approximately once per minute while the application is open. Expiration does not automatically delete a document.

At **4:00 PM Asia/Manila**, users with upload permission receive one daily alarm reminding them to check whether they have scanned documents to upload as a backup. There is no per-document upload due date. Returning after 4:00 PM catches up the day's reminder. Daily reminders are acknowledged per user in the current browser. Click **Enable reminder sound** to allow an audible alarm; browser interaction is required for sound. Popups and sound require an open, signed-in application; this is not an operating-system background alarm.

## Permissions and workflow setup

**Roles & Permissions** includes Scanned Documents: View, Upload, Request Update and Request Deletion, plus Scanned Categories: Request Category. General Requests permissions are also needed to create, edit and submit drafts. Existing softcopy view/request grants inherit scanned view/upload and category requests during migration. Administrators assign update/delete permissions separately.

**Workflow Builder** includes upload, update, deletion and category creation routes. They are initially published with a DCO approval step if an active Document Control Officer role exists. All four request types go to DCO by default and take effect only after the configured approvals finish. Administrators can edit approvers and add steps in Workflow Builder; published workflow settings determine the route, without a hardcoded DCO role check. If a configured approver role is missing or has no active member, requests cannot proceed until configured. Administrators cannot directly bypass scanned approval.

**Master Data > Scanned Categories** lets users with View, Create, Edit and Allocate to Users permissions manage approved folders directly. These permissions are initially granted to the DCO role and remain editable in Roles & Permissions. Choose no parent for a root folder, or an existing folder to create a nested folder. Table and Folder views show the structure.

Allocate a folder to one or more users to control their upload choices. Descendants inherit the nearest allocation; a nested folder can have its own allocation for different users. Clearing a nested allocation restores inheritance; clearing a root allocation makes that branch shared. Existing unallocated folders remain shared. Approved category requests start allocated to their requester. Allocation does not grant access to another user's documents. Folder selection is checked when saving, submitting and approving a request.

For an existing installation, apply `database/migrations/20261117_scanned_documents.sql` once (reruns are safe). Then apply `database/migrations/20261117_scanned_category_allocations.sql` for Master Data folders and user allocations. Apply `database/migrations/20261117_scanned_document_descriptions.sql` to add optional document descriptions. The repository's database import includes these migrations. Existing document tables and records are preserved.

## Verification

Run `php tools/tests/scanned_policy.php`, `php tools/tests/scanned_category_policy.php` and `php tools/tests/scanned_integration.php`. The integration test clones only table definitions from the configured database, creates synthetic fixtures in a randomly named `scanned_*_test` database, tests approval and reminder boundaries, then removes its fixtures. It needs local MySQL credentials with create/drop database privileges.

For browser checks, use `python tools/tests/scanned_browser.py` with Playwright and Microsoft Edge installed. It creates its own fixture database and PHP server, tests multi-file upload, approver review, file access, mobile layout and reminder popups, then removes the fixtures.
