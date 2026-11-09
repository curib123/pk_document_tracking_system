# PK Document Tracking System

Keep documents easy to find, track who holds the originals, and guide changes
through the right approvals.

PK DTS brings physical records, approved electronic files and external documents
into one workspace. Each person sees the documents and actions their account
allows.

## Start here

| What you need | Where to go |
| --- | --- |
| A quick explanation of everyday tasks | [Everyday reference](docs/system-documentation/PK_DTS_System_Reference.md) |
| Instructions with screen examples | [User Guide](docs/system-documentation/PK_DTS_User_Guide.pdf) |
| A view of who does what, from start to finish | [Process Flow](docs/system-documentation/PK_DTS_Process_Flow.pdf) |
| Help choosing the next step | [Flowchart](docs/system-documentation/PK_DTS_Flowchart.pdf) |
| An overview of people, information and records | [Data Flow Diagram](docs/system-documentation/PK_DTS_DFD.pdf) |

The documentation folder also includes editable PowerPoint and Visio copies.
Use the local [dashboard](http://localhost/pk_document_tracking_system/dashboard)
when the application is running; you will be asked to sign in if needed.

## What you can do

| Area | Main tasks |
| --- | --- |
| **Hardcopy Documents** | Register physical records, track their holder and storage location, and review retention dates. |
| **Softcopy Documents** | Manage approved electronic files, revisions, categories, downloads and printing. |
| **External Documents** | Manage documents received from outside the organization, using separate categories, revisions and downloads. |
| **My Requests** | Request Softcopy or External creation and revision, Hardcopy transfer, document access, assignment, or eligible disposal. |
| **My Tasks** | Review assigned requests and complete transfer dispatch or receipt. |
| **Document Assignments** | Assign individual documents, hardcopies at a Location, or softcopies in a category and its subcategories. |
| **Administration** | Manage accounts, permissions, approval routes, audit records, backups and recovery. |

Search, Table and Folder views, filters, notifications, and light/dark mode help
with daily work. A browser installation option is available when supported;
the system still needs a network connection.

Hardcopy registration and editing use direct actions. New hardcopy creation or
update requests and new softcopy cancellation requests are unavailable. Eligible
unsubmitted drafts can be cancelled; older request histories remain readable.

## Set up a local copy

The following sections are for the person installing or maintaining PK DTS.
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
The PHP server and MySQL still need LAN connectivity: PK DTS is not a
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
| Workflow names matching My Requests tabs | [Request workflow names](database/migrations/20261104_request_workflow_names.sql) |
| The same document number with distinct series | [Softcopy series number index](database/migrations/20261104_softcopy_series_number_index.sql), when category numbering is already installed |
| Folder-only vs numbered Softcopy Categories | [Softcopy Category Types migration](database/migrations/20261107_softcopy_category_kinds.sql), after category numbering |
| Multiple folder-only Softcopy Categories | [Folder category pattern migration](database/migrations/20261107_softcopy_folder_category_patterns.sql), after Softcopy Category Types |
| First-time Series No. pattern for each Document No. | [Document Series Pattern migration](database/migrations/20261107_softcopy_document_series_patterns.sql), after category numbering and the series-number index |

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

### Automatic softcopy series numbers (per Document No.)

In **Places → Softcopy Categories**, configure only **Document No. Code Pattern**
(for example `PPM-001`). **Series No. Code Pattern is NOT set on a category.**

To create a new document, select its category and leave **Need Series Number?**
unchecked. The usual next Document No. is generated, without a Series No.

To create the **first series** for an existing document:

1. Check **Need Series Number?** on Softcopy Direct or My Requests.
2. Select the existing **Document No.** (for example, `PPM-001`).
3. If that Document No. has never been configured for series numbering, enter its
   one-time **Series No. Code Pattern**, for example `001` or `SER-001`.
4. The form previews both the **Original Document Series No.** and the new
   **Generated Series Number**. With `001`, the original becomes **`000`**
   (one less) and the newly created record becomes **`001`**. With `SER-005`,
   the original becomes `SER-004` and the new record `SER-005`.
5. Save/submit and approve. The pattern and both series assignments are saved
   atomically at approval, **not** while saving a draft.

The first series may have a multi-part dash prefix. For example,
`sample-series-002` assigns **`sample-series-001` to the original** and
**`sample-series-002` to the new record**; subsequent records with the same
Document No. become `sample-series-003`, `sample-series-004`, and so on.
Only the numeric segment after the **final dash** changes.

For the same Document No., future series automatically increment (`002`, `003`, ...).
The setup is no longer shown once that document has a saved series pattern.
Other Document Nos. may have entirely different patterns or none at all.
If an existing number has historical series already, the configured pattern
continues after the largest matching series. Regular revisions **do not**
generate a new series; they retain the existing one.

Historic softcopy numbers and revisions remain unchanged except for the
original unnumbered document, which is explicitly assigned the preceding
series on first-time setup. That change updates its revision metadata and
adds an audit event. Concurrent approvals are serialized through the category
lock and database unique index; the displayed series is only a preview.

**Database upgrade:** Back up the database, then ensure the
[document-series unique index](database/migrations/20261104_softcopy_series_number_index.sql)
is installed before running the
[per-document Series No. pattern migration](database/migrations/20261107_softcopy_document_series_patterns.sql),
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
5:00 PM task on a Windows host using UTC+08:00. Run it from an elevated PowerShell
session with the required configuration available to the SYSTEM account. Check
that a scheduled run succeeds.

Restoring replaces application data. Confirm the archive and target database
before restoring; the command-line tool requires its explicit `--confirm` flag.

## Keep the documentation current

The four content files in [tools/documentation](tools/documentation) supply the
User Guide and diagrams. The generator creates the PDF, editable PowerPoint,
and both Visio formats. Guide screen examples and callouts are kept alongside
the content. Python needs `pymupdf` and `python-pptx` installed.

All four documents cover the eight current request types and their approval,
return and rejection outcomes. The Process Flow owns the shared default workflow
examples; the other books reuse their operator names and decision details.
Related procedures link to the matching examples through the PDF footer.
The Flowchart keeps its existing procedures and adds a connected approval flow
for each request, with decision diamonds, Return loops and separate Reject ends.
The DFD uses external entities, numbered processes, record stores and labeled
information flows. Its symbols and text remain editable in both Visio formats.

```powershell
# Check source coverage without generating files.
python -X utf8 tools/documentation/refresh_documents.py --check-source

# Rebuild and verify the exported documents.
python -X utf8 tools/documentation/refresh_documents.py

# Verify existing exports.
python -X utf8 tools/documentation/refresh_documents.py --verify
```

These checks cover the documented routes, request actions, content and exported
packages. They do not prove how the files open in desktop PowerPoint or Visio.

On Windows with Microsoft Visio Viewer installed, also open and render every page
of the six Visio exports:

```powershell
powershell.exe -NoProfile -STA -ExecutionPolicy Bypass -File tools/documentation/verify_visio.ps1
```

This read-only check fails if a drawing cannot load or render, a page or shape is
missing, or Viewer reports an error. Its report is saved under
`storage/visio-verification/`. Add `-RenderPages` to save page images as well.
Viewer checks do not certify editing and saving in every desktop Visio version.
See the [format guidance](docs/system-documentation/PK_DTS_System_Reference.md#edit-the-documentation)
when choosing between the VSDX and older VDX copies.

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
