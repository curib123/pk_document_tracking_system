# Document controls and attachment lifecycle

Controlled files have Print in the softcopy register, record viewer and file history. The protected viewer provides a Print button for PDF, images and text; PDF also retains its native browser toolbar. Text is escaped as plain content. DOCX/XLSX provide download instructions for printing in Word or Excel. Print content uses the same current-revision and document-access checks as Download.

All shared record viewers use the same responsive header, overview cards, concern tabs and accessible Close actions. File actions wrap on small screens, and opening a different record resets scrolling. The workflow viewer retains its existing operations with updated shared styling.

Created-date filters provide All time, Today, This week, This month, This year and Custom range on hardcopy, softcopy, request/task registers and other tables that enable date filtering. Calendar presets use Asia/Manila and apply immediately. Weeks start Monday. Custom dates are inclusive; both date fields are required in the UI and their order is checked. Existing links with one date endpoint still work. Search, status, ownership, sorting and folder state remain part of the same server query.

Disposing or cancelling a softcopy retires all connected file records in the same database transaction. Successful commit triggers deletion of their private stored bytes. Rejecting a creation/revision proposal or cancelling a draft retires only its pending attachment. A rejected revision preserves the active document's current approved file. Revision, approval and disposal metadata remain recorded.

The first authenticated request each Manila day reconciles legacy disposed/cancelled records, rejected/cancelled proposals and files whose document was removed. It also retries retired bytes that could not be deleted. This uses a database-specific daily marker and pauses during restore maintenance. Cleanup accepts only managed storage names, stays inside the document storage directory, avoids symlinks and preserves storage still referenced by approved or pending file records. No database migration is required.

## Verification

- `php tests/controlled_file_print.php`
- `php tests/controlled_file_replacement.php` uses real transactions and connection-local temporary tables; test blobs belong to the runner.
- `php tests/date_range.php`
- `node --test tests/document_view.test.js tests/controlled_print.test.js tests/date_controls.test.js tests/row_actions.test.js tests/action_feedback.test.js`
- `python tests/browser_document_workflows.py` requires Playwright, PHP/MySQL and Microsoft Edge. It copies the current application into a temporary directory with private storage, creates its own `pk_dts_browser_<random>_test` database, runs on port 8096, then removes its test files and database. `PK_MYSQL` can override the MySQL client path.

Browser coverage includes actual register filtering, invalid custom dates, viewers at 1440/390/320 pixels, text/image print invocation, a two-page native PDF preview, Office fallback, denied file access, and rejection/disposal through the ordinary confirmation forms. It checks database results, stored bytes and retired URL access. Physical printer output is outside this automated check.

The broad existing suite has eight PHP failures (`plan_contract`, `topnav_optional_asset`, `document_folder_layout`, `plan_implementation`, `current_plan_contract`, `backup_contract`, `backup_retention`, `dashboard_copy_contract`) and two Node failures in `dashboard_visual` and `modal_dismissal`. All were independently reproduced using the starting commit `90dcde5`; they are not introduced by these changes.
