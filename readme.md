# PK Document Control System

Keep documents easy to find, track who holds the originals, and guide changes
through the right approvals.

PK DCS brings physical records, approved electronic files and external documents
into one workspace. Each person sees the documents and actions their account
allows.

> **Name change:** the system was previously called PK Document Tracking System
> (PK DTS). Technical identifiers keep the old abbreviation so existing
> installations, links and backups keep working: the `pk_document_tracking_system`
> folder and URL, the `pk_dts` database, `pk-dts-*.zip` backup files, the
> `PKDTS-*` scheduled tasks and the WPS opener's `pk-dts-office` link and
> `%LOCALAPPDATA%\PK DTS` folder.

## Start here

| What you need | Where to go |
| --- | --- |
| A quick explanation of everyday tasks | [Everyday reference](docs/system-documentation/PK_DCS_System_Reference.md) |
| A short introduction: document types, My Requests and My Tasks | [Quick Guide](docs/system-documentation/PK_DCS_Quick_Guide.pptx) |
| Instructions with screen examples | [Step-by-Step User Guide](docs/system-documentation/PK_DCS_User_Guide_Step_by_Step.pptx) |
| A view of who does what, from start to finish | [Process Flow](docs/system-documentation/PK_DCS_Process_Flow.pptx) |
| Help choosing the next step | [Flowchart](docs/system-documentation/PK_DCS_Flowchart.pdf) |
| An overview of people, information and records | [Data Flow Diagram](docs/system-documentation/PK_DCS_DFD.pdf) |

The documentation folder also includes editable PowerPoint and Visio copies. The Process Flow is a
PowerPoint presentation.
Use the local [dashboard](http://localhost/pk_document_tracking_system/dashboard)
when the application is running; you will be asked to sign in if needed.

## What you can do

| Area | Main tasks |
| --- | --- |
| **Hardcopy Documents** | Register physical records, track their holder and storage location, and review retention dates. |
| **Softcopy Documents** | Manage approved electronic files and revisions. Open **View → Document Files** for authorized downloads and file history. |
| **External Documents** | Manage documents received from outside the organization, using separate categories, revisions and downloads. |
| **Scanned Documents** | Request uploads, updates, removals, or new scanned folders. See the [Scanned Documents guide](docs/scanned-documents.md). |
| **My Requests** | Submit and track document requests, including requests for editable Softcopy revision templates. |
| **My Tasks** | Review assigned requests and complete transfer dispatch or receipt. |
| **Document Assignments** | Assign individual documents, hardcopies at a Location, or softcopies in a category and its subcategories. |
| **Administration** | Manage accounts, permissions, approval routes, audit records, backups and recovery. |

Search, Table and Folder views, filters, notifications, and light/dark mode help
with daily work. A browser installation option is available when supported;
the system still needs a network connection.

Open **My Requests** or **My Tasks** in the sidebar to see all your records in one table.
Use the category, action, status, or date filters to narrow the results. Select **New Request**
and choose an action. **Save Draft** keeps the request in My Requests for later editing.
If you have submission permission, **Submit Request** sends it directly to the approval workflow.
Count badges appear only when there is at least one draft, returned request, or pending task.

To get an editable revision template, choose **Request Template for Revision** on an active softcopy.
The Document Control Officer uploads a Word, Excel, or text file and sets its download expiry at approval.
The requester can download it from **My Requests** through the end of that date in Philippine time.

Hardcopy registration and editing use direct actions. New hardcopy creation or
update requests and new softcopy cancellation requests are unavailable. Eligible
unsubmitted drafts can be cancelled; older request histories remain readable.

## Set up a local copy

The following sections are for the person installing or maintaining PK DCS.
The application uses PHP 8, CodeIgniter 3 and MySQL/MariaDB. XAMPP is the Windows
setup used by the supplied helper scripts.

### Before you start

| Requirement | Check |
| --- | --- |
| PHP 8.x | Enable `mysqli`, `fileinfo`, `mbstring` and `zip`. |
| Composer | Install the PHP packages listed in [composer.json](composer.json). |
| MySQL or MariaDB | Use a version compatible with the supplied schema. The SQL export identifies MariaDB 10.4.32 as its source server. |
| Apache | Enable `mod_rewrite` and allow the repository's `.htaccess` rules. |
| File access | Allow PHP to write to `storage/` and its subdirectories. |

The interface's CSS, JavaScript and third-party frontend dependencies are
included in the repository. No frontend build or internet access is needed
at runtime.

### Office LAN use without public internet

Install PHP/Composer dependencies **before** taking the server offline. Keep
the web server and database running and connect other computers to the same
office network (Wi-Fi or Ethernet). Access the **server's LAN IP**, e.g.
`http://192.168.1.10/pk_document_tracking_system/` (substitute its actual
address). `localhost` from another computer refers to that computer,
**not** the server.

Bootstrap 5.3.3, Font Awesome Free 6.7.2, Poppins fonts and Chart.js 4.4.7
are served from local `public/assets/` files rather than the public CDN.
Dashboard charts load only on the dashboard. The service worker no longer
aborts a slow office LAN GET request after 3 seconds; it still never caches
documents or private application responses.

Check the static dependency set with `php tools/tests/offline_assets.php`.
For an end-to-end offline-internet test, disconnect **only the public internet**
while leaving the office LAN connected, then verify sign-in, icons, modals,
dashboard charts, requests and document downloads in a client browser.
The PHP server and MySQL still need LAN connectivity: PK DCS is not a
fully offline browser database.

### Install with XAMPP

1. Put the checkout at `C:\xampp\htdocs\pk_document_tracking_system`, or configure
   Apache for your chosen folder.
2. Start Apache and MySQL in the XAMPP Control Panel.
3. From the repository root, install PHP dependencies:

   ```powershell
   composer install
   ```

4. Create an empty `pk_dts` database using `utf8mb4`. Review and import
   [database/pk_dts.sql](database/pk_dts.sql) with phpMyAdmin or the MySQL client.
   **The export includes existing data. Import it only into the intended empty
   database.** For an existing installation, use the upgrade notes below.
5. Set the database configuration below if your connection differs from the
   local defaults.
6. If needed, create an Administrator account:

   ```powershell
   C:\xampp\php\php.exe tools/create_admin.php your_admin_username
   ```

   Enter the requested name, position and password. Password input is visible,
   and the password needs at least 9 characters. An active Administrator role
   must already exist in the database.
7. Open the [local dashboard](http://localhost/pk_document_tracking_system/dashboard)
   and sign in.

The root [index.php](index.php) opens `public/index.php`. The root `.htaccess`
serves public assets and blocks private application folders. Application dates
use `Asia/Manila`.

### Configure the connection

Configuration comes from environment variables available to the PHP process.
The application does not automatically load `.env` files.

| Variable | Local default | Purpose |
| --- | --- | --- |
| `DB_HOST` | `127.0.0.1` | Database server |
| `DB_PORT` | `3306` | Database port |
| `DB_DATABASE` | `pk_dts` | Database name |
| `DB_USERNAME` | `root` | Database user |
| `DB_PASSWORD` | Empty | Database password |
| `CI_ENV` | `production` | Application mode; `development` displays PHP errors |
| `PK_ENCRYPTION_KEY` | Empty | CodeIgniter encryption key |

See [database.php](application/config/database.php) for database settings.
Variables set in a PowerShell window reach commands launched from that window;
they do not automatically reach an already-running XAMPP Apache process. Keep
credentials and private keys outside version control.

### Upgrade an existing database

Review [database/migrations](database/migrations) against the installed schema
before applying a change. There is no automatic migration runner. The November
updates address these cases:

| If the installation needs... | Review and apply as appropriate |
| --- | --- |
| Category numbering and External document permissions | [Category numbering](database/migrations/20261104_category_code_pattern.sql), then [External permissions](database/migrations/20261104_external_document_permissions.sql) |
| An External Description field | [External description update](database/migrations/20261104_external_retention_dates_description.sql), if needed |
| Removal of unused External retention fields | [Remove External retention](database/migrations/20261104_remove_external_retention.sql), after the description update if it was needed |
| Separate External Categories | [External categories](database/migrations/20261104_external_categories.sql), after the preceding External updates |
| External document numbers and Subsidiary setup | [External document subsidiaries](database/migrations/20261107_external_document_subsidiaries.sql), after External Categories |
| Multiple attachments per External revision | [External multiple attachments](database/migrations/20261107_external_multiple_attachments.sql), after External document subsidiaries |
| Workflow-triggered document locking, Plant Manager defaults, and historical approvals | [Workflow document lock fields](database/migrations/20261110_workflow_document_locks.sql), then [Designate Plant Manager](database/migrations/20261110_designate_plant_manager_document_lock.sql), then [Backfill Plant Manager locks](database/migrations/20261110_backfill_plant_manager_document_locks.sql) |
| Workflow names matching request categories | [Request workflow names](database/migrations/20261104_request_workflow_names.sql) |
| The same document number with distinct series | [Softcopy series number index](database/migrations/20261104_softcopy_series_number_index.sql), when category numbering is already installed |
| Folder-only vs numbered Softcopy Categories | [Softcopy Category Types migration](database/migrations/20261107_softcopy_category_kinds.sql), after category numbering |
| Multiple folder-only Softcopy Categories | [Folder category pattern migration](database/migrations/20261107_softcopy_folder_category_patterns.sql), after Softcopy Category Types |
| Manual Series No. (drops the per-Document No. code pattern) | [Remove series patterns migration](database/migrations/20261112_remove_softcopy_series_patterns.sql) |
| Optional Signatories References on Softcopy create/revise (editable dropdowns fed by saved values) | [Signatories References migration](database/migrations/20261113_softcopy_signatory_references.sql), after the preceding Softcopy updates |
| Request Template for Revision permission and DCO workflow | [Revision template migration](database/migrations/20261118_softcopy_revision_template.sql) |

Existing prefix-only category patterns still work. New category patterns should
include an initial sequence, such as `PPM-001`.

### Nested Softcopy Category folders and number patterns

**Places → Softcopy Categories** now supports two distinct category types:

- **Folder**: a parent or nested folder. It has no code pattern and cannot
  directly create a softcopy document. Folder children can be folders or
  Document Number Pattern categories.
- **Document Number Pattern**: a document-creation category with a required
  `Document No. Code Pattern`, e.g. `PPM-001`. It can be under a Folder
  (or at the root), and each new document increments only the numeric
  suffix following the final dash: `PPM-001 → PPM-002 → PPM-003`.

For example, `Quality (Folder) / Procedures (Folder) / Process Manuals
(Document Number Pattern: PPM-001)`. The folder tree remains nested for
browsing and assigning documents, while numbered categories are the only
ones available when creating a new softcopy.

Existing categories default to **Document Number Pattern** during migration,
preserving existing documents and links. New parent selections must be Folder
categories. An existing legacy parent relationship is preserved on edit, but
new nested categories cannot be placed beneath a numbered category. Numbered
categories holding documents cannot be converted to Folder, and Folders with
children cannot be converted to numbered categories.

### Softcopy document and series numbers

In **Places → Softcopy Categories**, configure only **Document No. Code Pattern**
(for example `PPM-001`). Series numbers have **no code pattern**; they are typed in.

To create a new document, select its category and leave **Need Series Number?**
unchecked. The usual next Document No. is generated, without a Series No.

#### Type a different Document No. (Softcopy Direct only)

On **Softcopy Documents → Add New Softcopy Document**, the **Generated Document
Number** box is filled with the category's next number and can be typed over.

- **Left unchanged**, the number is a preview. The next free number is assigned
  when the action is approved, so it can differ if another document took the
  previewed number in the meantime.
- **Typed over**, the number (up to 100 characters) is used exactly as entered.
  It is rejected with *That document number is already in use* if any softcopy
  already has that Document No.; use **Need Series Number?** to share an
  existing number instead.
- Changing **Category** refills the box with that category's next number.
- A typed number that follows the category pattern moves the sequence: after
  `PPM-050` is typed, the next automatic number is `PPM-051`.
- Requests (**My Requests → New Request**) keep the box read-only. Their final
  number is assigned at final approval.

The form sends `document_number_edited=1` only after the user types in the box,
and the server ignores the previewed value otherwise. Check this behavior with
`python tools/tests/softcopy_manual_number_browser.py` (Playwright, Microsoft
Edge, PHP and MariaDB; the test creates and drops its own database).

#### Add a series to an existing Document No.

To add a series to an existing document:

1. Check **Need Series Number?** on Softcopy Direct or My Requests.
2. Select the existing **Document No.** (for example, `PPM-001`).
3. Type the **Series Number**. Its placeholder shows the most recent previous
   series of that Document No. (for example `Previous: SER-004`) as a guide.
4. Save/submit and approve. The Series No. is required and must be unique for
   that Document No. Existing records are never renumbered. Regular revisions
   **do not** change the series; they retain the existing one.

Existing softcopy numbers, series and revisions are never renumbered. Adding a
series does not change the original document's record. Concurrent creations in
the same category are serialized through the category lock and the database
unique index on Document No. and Series No.

**Database upgrade:** Back up the database, then ensure the
[document-series unique index](database/migrations/20261104_softcopy_series_number_index.sql)
is installed before running the
[remove-series-patterns migration](database/migrations/20261112_remove_softcopy_series_patterns.sql)
(Series No. is now typed manually),
plus the [Softcopy Category Types migration](database/migrations/20261107_softcopy_category_kinds.sql)
to enable Folder and Document Number Pattern category types.
Migrations are **not run automatically**. If the former experimental category
series-pattern column was installed, the new code ignores it; the upgrade
preserves that old column rather than deleting its data.

## Maintain backups

Administrators can use **Backup & Restore** in the application. For command-line
operation, [tools/backup.php](tools/backup.php) supports creating, listing,
checking, retaining and restoring backups.

Configure these variables in the PHP process environment:

| Setting | Requirement |
| --- | --- |
| `DB_*` | Connection to the intended database |
| `PK_BACKUP_PASSWORD` | At least 12 characters |
| `PK_BACKUP_SIGNING_KEY` | At least 32 characters |
| `PK_MYSQL`, `PK_MYSQLDUMP` | Optional overrides for the database tool paths |
| `PK_BACKUP_DIR` | Optional private backup folder |

```powershell
php tools/backup.php manual
php tools/backup.php list
php tools/backup.php verify <archive-name.zip>
```

Replace `<archive-name.zip>` with the intended archive name.

[install_backup_schedule.ps1](tools/install_backup_schedule.ps1) registers a daily
4:45 PM maintenance task and 5:00 PM backup task on a Windows host using UTC+08:00.
Maintenance handles retention and controlled-file reconciliation outside of user
requests. It removes managed uploads and PDF previews at least 24 hours old
whose storage name has no row in the configured database's `files` table.
Run the installer from an elevated PowerShell session with the required
configuration available to the SYSTEM account. Check that both scheduled runs
succeed. The maintenance task can also be run manually with
`php public/index.php maintenance run`. To run only the unregistered-file cleanup,
use `php public/index.php maintenance run orphans`.

Restoring replaces application data. Confirm the archive and target database
before restoring; the command-line tool requires its explicit `--confirm` flag.

## Keep the documentation current

The content files in [tools/documentation](tools/documentation) supply the User
Guide, Process Flow, Flowchart, and Data Flow Diagram. The diagrams ship as PDF
and VSDX. The User Guide and Process Flow ship as editable PowerPoints.
The guides cover the current request actions and their approval outcomes,
including the revision template and scanned document workflows.
Python needs `pymupdf` and `python-pptx`. Screenshot capture also needs Playwright,
Microsoft Edge, local PHP, and MariaDB with permission to create a test database.

```powershell
# Check source coverage without generating files.
python -X utf8 tools/documentation/refresh_documents.py --check-source

# Step-by-Step User Guide: capture the current screens, build a draft to review,
# then publish it to docs/.
python -X utf8 tools/documentation/capture_guide.py
python -X utf8 tools/documentation/build_user_guide.py
python -X utf8 tools/documentation/build_user_guide.py --publish
# Quick Guide: the short summary built from the same screens and steps.
python -X utf8 tools/documentation/build_quick_guide.py
python -X utf8 tools/documentation/build_quick_guide.py --publish

# Screens for the Process Flow, then the diagrams and the Process Flow.
python -X utf8 tools/documentation/capture_screenshots.py
python -X utf8 tools/documentation/refresh_documents.py
python -X utf8 tools/documentation/build_process_flow.py

# Verify existing exports.
python -X utf8 tools/documentation/refresh_documents.py --verify
```

The Process Flow uses synthetic requests and sample users. Its document types
and scenarios are defined in `tools/documentation/workflow_document_types.py`.

The **Step-by-Step User Guide** is generated from the running application, so
it can be refreshed whenever the screens change. `capture_guide.py` drives the
real flows on a disposable copy of the site (sample people and records only)
and saves each screen with the positions of its numbered callouts and its visible
text. `build_user_guide.py` lays out the deck from `user_guide_content.py` with
the look of `templates/user_guide_template.pptx` and writes a draft to
`tools/documentation/build/` (not committed). Open the draft in PowerPoint; when
it is right, `--publish` writes the same deck to both `docs/system-documentation/`
and `docs/`. The build stops if a bold UI label in the
guide is not on its screenshot, a numbered step has no marker, or text does not
fit. Change the wording in `user_guide_content.py`, not in PowerPoint: edits
made in PowerPoint are replaced on the next build.

These checks cover the documented routes, request actions, content and exported
packages. They do not prove how the files open in desktop PowerPoint or Visio.

On Windows with Microsoft Visio Viewer installed, open and render every page
of the two Visio diagrams.

```powershell
powershell.exe -NoProfile -STA -ExecutionPolicy Bypass -File tools/documentation/verify_visio.ps1
```

This read-only check fails if a drawing cannot load or render, a page or shape is
missing, or Viewer reports an error. Its report is saved under
`storage/visio-verification/`. Add `-RenderPages` to save page images as well.
Viewer checks cover opening and rendering. The editable diagrams use VSDX.

## Check application changes

The application test runners referenced by `composer test` and the npm scripts
are absent from this checkout. The older browser test scripts are also absent;
do not use those commands as evidence that an application change passed.

Check the affected behavior in the running application with suitable test data.
Documentation verification above checks the documentation only.

## Sync a development checkout to XAMPP

If you edit a separate checkout, run this command from that development checkout:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File tools/sync_local_xampp.ps1
```

The defaults are `C:\xampp\htdocs\pk_document_tracking_system` and
`http://localhost/pk_document_tracking_system/`. Both working trees must be clean,
and the served checkout must be able to fast-forward to the development branch.
The script updates tracked files and checks the served assets. Database upgrades
and private storage remain separate. Reload the browser after syncing.

## Find the project files

| Folder | What it contains |
| --- | --- |
| `application/controllers/` | Page entry points and permission checks |
| `application/models/` | Database queries and document lists |
| `application/services/` | Document, approval, file, security and backup operations |
| `application/view/` | Page templates and shared interface components |
| `public/assets/` | Styles, JavaScript, fonts and images |
| `database/` | Database export and individual upgrade scripts |
| `docs/system-documentation/` | Everyday reference, guides and editable diagrams |
| `storage/` | Private files, sessions, logs, audit records and generated output |
| `tools/` | Administrator, backup, documentation and local sync utilities |

For interface maintenance, start with [shared colors](public/assets/css/colors.css),
[theme behavior](public/assets/js/theme.js), and the
[document viewer](public/assets/js/document-view.js).
