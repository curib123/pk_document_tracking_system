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
| A view of who does what, from start to finish | [Process Flow](docs/system-documentation/PK_DCS_Process_Flow.pdf) |
| Help choosing the next step | [Flowchart](docs/system-documentation/PK_DCS_Flowchart.pdf) |
| An overview of people, information and records | [Data Flow Diagram](docs/system-documentation/PK_DCS_DFD.pdf) |

The documentation folder also includes editable PowerPoint and Visio copies. The Process Flow PDF has
an editable PowerPoint copy.
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
| `application/controllers/` | HTTP controllers and permission checks |
| `application/models/` | Database queries and listings |
| `application/services/` | Document, workflow, file, security, and backup operations |
| `application/view/` | Page templates and shared UI components |
| `public/assets/` | Styles, JavaScript, fonts, and images |
| `database/` | Database export and incremental SQL migrations |
| `storage/` | Private files, sessions, logs, audits, and generated artifacts |
| `tools/tests/` | PHP and browser regression checks |
| `tools/` | Administrator, backup, asset, and local synchronization utilities |

Shared colors are defined in [public/assets/css/colors.css](public/assets/css/colors.css). Theme behavior lives in [public/assets/js/theme.js](public/assets/js/theme.js), and document View rendering lives in [public/assets/js/document-view.js](public/assets/js/document-view.js).

## Verification

Run the PHP checks with Composer. The database checks use disposable fixtures and require a running MySQL server with permission to create and drop test databases.

```powershell
composer test
npm.cmd run test:assets
```

The browser checks require Python, Playwright, Microsoft Edge, MySQL, and the PHP dependencies. Run them from the repository root and set `TEST_MYSQL` if MySQL is missing from PATH.

```powershell
python -m pip install playwright
$env:TEST_MYSQL = 'C:\xampp\mysql\bin\mysql.exe'
python tools/tests/dashboard_scope_browser.py
python tools/tests/revision_template_browser.py
python tools/tests/scanned_browser.py
python tools/tests/softcopy_manual_number_browser.py
```

`npm.cmd run audit:css` reports CSS selectors for manual review.

## Sync a development checkout to local XAMPP

When editing a separate development checkout, run this from that checkout:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File tools/sync_local_xampp.ps1
```

The script defaults to `C:\xampp\htdocs\pk_document_tracking_system` and `http://localhost/pk_document_tracking_system/`. Both checkouts must have clean working trees, and the served checkout must be able to fast-forward to the development branch. The script updates tracked files and verifies HTTP asset hashes. It does not import database changes or replace private storage. Reload the browser after syncing.

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
php tools/backup.php auto
php tools/backup.php manual
php tools/backup.php list
php tools/backup.php verify <archive-name.zip>
```

Replace `<archive-name.zip>` with the intended archive name.
The shorter `php tools/backup auto` command also works. For a device that stores
its PHP backup environment outside the checkout, an ignored `.env.backup.php`
in the project root can require that private file for CLI runs. An explicit
database host, port, name, or backup directory selects the process environment.

[install_backup_schedule.ps1](tools/install_backup_schedule.ps1) registers a daily
4:45 PM maintenance task and 5:00 PM backup task on a Windows host using UTC+08:00.
Automatic and manual backups include the application, database, stored documents
and their versions, audit history, and published guides. They omit the PDF preview
cache, local documentation review and Visio repair work folders, the generated
documentation ZIP, documentation screenshots and preview renders, and test PNGs
at the root of `storage/`. The pre-restore recovery archive includes these files.
Once a new backup is verified and published, the job keeps the two newest
archives of that type and removes any older ones.
A failed run keeps the existing archives. The three-month policy applies to
imported backups, pre-restore recovery snapshots, and logs.
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
and VSDX. The Process Flow ships as PDF and PowerPoint, exported by PowerPoint
itself, so building it needs Windows with PowerPoint. The User Guide ships as
an editable PowerPoint.
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

# Screens for the Process Flow (it also uses the User Guide screens), then the
# diagrams and the Process Flow. Add --out FOLDER to build a Process Flow draft.
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

Use the [application test commands](#verification) to check affected workflows
with disposable test data.

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
