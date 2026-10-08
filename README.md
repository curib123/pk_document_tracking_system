# PK Document Tracking System

Functional CodeIgniter 3 / MySQL document control application with a **modular red Tailwind design system** and a fully usable **plain HTML fallback**. Record actions, approvals, account operations, confirmations, uploads, downloads, and workflow editing retain native HTML modal dialogs. Styling is presentation only and does not change permissions or workflow behavior.

The application is configured for **Windows XAMPP + XAMPP MySQL on PHP 8.0**. It does not use a project `.env`, Docker, Docker Compose, a custom PHP server router, or a separate application server.

## Styling configuration

Edit `application/config/styling.php` to enable or disable the design globally or by module. Current modules are enabled by default; new/unregistered modules remain plain until enabled. Use real PHP booleans, not strings:

```php
'enabled' => true,           // false disables all application styling
'shell' => true,             // style the sidebar/header on enabled screens
'default_enabled' => false,  // unregistered modules remain plain
'modules' => [
    'softcopy' => true,
    'hardcopy' => false,     // example: native HTML on hardcopy screens
    'workflows' => true,
    // Keep the other module entries from the configuration file.
],
```

A disabled module keeps working with native controls and no application design. Shared colors, controls and layout live in `resources/styles/`; module refinements live in `resources/styles/modules/`. The compiled stylesheet is committed, so XAMPP does not need Node, npm, a Tailwind CDN or internet access to display the design. See [the styling guide](docs/STYLING.md) for configuration precedence, optional emergency override, module extensions, rebuilding and tests. No database migration is required for styling.

## XAMPP requirements

- XAMPP with Apache, MySQL/MariaDB, and PHP 8.0+
- PHP extensions: `mysqli`, `fileinfo`, `mbstring`, `zip`
- Composer 2
- A current browser supporting native `<dialog>` and modern CSS

Default database configuration is in `application/config/database.php`:

- Host: `127.0.0.1`
- Port: `3306`
- Database: `pk_dts`
- Username: `root`
- Password: empty

These are the normal default XAMPP MySQL settings. If your XAMPP MySQL root account has a password or uses another port, edit that file directly. The normal application does not load database credentials from `.env`.

## Install and run with XAMPP

1. Put the repository in:

   `C:\xampp\htdocs\pk_document_tracking_system`

2. Start **Apache** and **MySQL** from the XAMPP Control Panel.

3. Open phpMyAdmin and create a new empty database named:

   `pk_dts`

   Use `utf8mb4` when choosing a character set/collation.

4. From the project folder install PHP dependencies:

```bat
composer install
```

5. Run the one-time installer with XAMPP PHP:

```bat
C:\xampp\php\php.exe database\install.php
```

The installer prints the initial administrator password once. The username is `admin`. Save that password, sign in, and change it immediately.

6. Open:

   `http://localhost/pk_document_tracking_system/public/`

No `.env` file, virtual host, Docker container, Node server, npm install, or `php -S` command is required.

The installer refuses to modify a non-empty database. It never drops tables or resets an existing administrator.

If you already have an older PK DTS database, update the code first and run:

```bat
C:\xampp\php\php.exe database\migrate.php
```

The migrator upgrades schema versions 1 through 6 to the current **schema version 7**. Version 5 exports legacy database audit rows to append-only JSONL under `storage/audit/`; version 6 enables direct Transfer/Access records and dedicated direct-action permissions; version 7 enables requestless direct Disposal records and seeds `disposal.direct` for Administrator and Document Control Officer. Existing document, request, workflow, status-history, and approval-history data are preserved. Back up the database before any schema migration.

## XAMPP notes

The app automatically derives its base URL from the Apache request, so the repository can be placed under another folder name inside `htdocs` without changing an `APP_URL`.

The public entry point is the `public/` directory. Keep `application/`, `database/`, `storage/`, and `vendor/` private and do not browse them directly.

Uploads are limited by the application to 20 MB. Make sure XAMPP's `php.ini` has `upload_max_filesize` and `post_max_size` set high enough for that limit, then restart Apache after changing PHP settings.

PDF artifact generation uses FPDI/FPDF. DOCX/XLSX conversion automatically looks for LibreOffice in the standard Windows installation paths. If LibreOffice is not installed, Office documents can still be stored and downloaded, but PDF conversion requires LibreOffice or a PDF source.

## Default seed data

A fresh installation automatically seeds:

- **Administrator** — all permissions
- **Document Control Officer** — document-control, file, transfer, access, assignment, audit, sequence, broad request visibility, and catalogue-management permissions
- **Plant Manager** — normal staff access plus request-wide visibility and document-wide access; the role can be selected as an approver in a workflow step
- **Internal Auditor** — read-focused access to documents, requests, transfers, assignments, access records, disposals, files, notifications, and audit logs
- **Staff** — standard request, upload, notification, and document-view/request permissions

The default administrator account is:

- Username: `admin`
- Name: `System Administrator`
- Role: `Administrator`
- Active: yes
- Password: randomly generated during seeding and printed once
- Forced password change: yes

The full installer creates the schema and seed data:

```bat
C:\xampp\php\php.exe database\install.php
```

If you manually imported `database/schema.sql` into an otherwise fresh database, seed the schema version and generated account defaults with `database/seed.php` (do not import a company data dump):

```bat
C:\xampp\php\php.exe database\seed.php
```

The seeder refuses to overwrite an existing user database.

## First-use setup

Sign in and change the initial password. Create real users, assign their roles, and configure each user's leader when requester-leader workflow steps will be used. A requester can approve only when a workflow step explicitly uses the **Requester** approver source; user, role, and requester-leader steps resolve their own assigned approvers.

Create the physical catalogue in order: **Area → Specific → Asset → Location**. Create softcopy categories separately. Then register documents directly using an authorized Administrator or Document Control Officer, or submit creation requests as staff.

Default roles are Administrator, Document Control Officer, Plant Manager, Internal Auditor and Staff. Authorization uses editable capabilities rather than role-name checks. New accounts receive generated passwords, forced password changes and session invalidation after administrative resets. User deletion deactivates the account so audit history remains intact. Referenced catalogue records cannot be deleted.

## Functional modules

Authentication and session protection; users, roles and permissions; areas, specifics, assets and locations; softcopy categories; separate softcopy and hardcopy records; revision history; private files and attachment approvals; controlled/uncontrolled PDF generation; requests; My requests; My tasks; versioned workflow builder; physical transfers and recipient acceptance; access grants; assignments; retention and disposal; notifications; append-only audit/status history; sequence tracking; stored system appearance preferences.

Nine request types are implemented: softcopy creation, revision and cancellation; hardcopy creation and update; transfer; assignment; access; disposal.

### Document rules

A request is not a document. Drafts and approvals do not silently create or alter controlled records. Applying final approval and its document changes occurs in one database transaction.

Softcopy revisions are preserved individually. A single current-revision foreign-key pointer prevents multiple current revisions. Direct revisions require a new private source upload. Existing controlled PDF artifacts become unavailable when their revision is superseded or the document becomes inactive.

A physical location holds at most one current hardcopy. Approval creates a transfer record; dispatch records delivery; **only the named recipient's acceptance changes the recorded holder/location**. Refusal keeps the origin unchanged and records the need to arrange physical return.

Retention dates gate disposal. Disposal stores the previous status and complete document snapshot. A disposed hardcopy releases its current location while preserving its former physical coordinates in the disposal record.

### Workflow rules

The Workflow Builder is an ordered approval sequence for each request type. A draft version can **add, edit, remove, move up, or move down approval steps**. Every step has a step name and one approver source:

- **Specific User** — one selected active account.
- **Role** — active users belonging to the selected role.
- **Requester’s Leader** — dynamically resolves the leader configured on the requester’s user account.
- **Requester** — dynamically resolves the account that submitted the request.

Draft versions can be saved with no steps while being designed, but at least one approval step is required before publishing. Published versions are immutable. Multiple versions may remain published; one published version is marked **Default** and is the version used by new requests. Changing the default never changes requests that were already submitted.

When a request is submitted, it stores the selected workflow version and an immutable snapshot of its approval sequence. Approval proceeds in order: Step 1 → Step 2 → Step 3 → completion. Reject or Return stops the sequence immediately. The request view shows the workflow name/version, approval steps, assigned approvers, decisions, comments, and workflow history.

All changes use optimistic record versions. A stale modal receives an explicit conflict instead of overwriting someone else's changes.

## Files and artifacts

Uploads are stored outside the public directory under randomized names and SHA-256 fingerprints. Supported uploads: PDF, DOCX, XLSX, TXT, CSV, PNG and JPEG. Extension/MIME checks, size limits and Office archive checks are enforced. Download permission and file integrity are checked each time.

PDF controlled/uncontrolled copies use FPDI/FPDF and add a separate footer area rather than painting over source content.

## Maintenance

The optional maintenance command expires grants, sends related notifications, prunes login attempts and cleans old conversion workspaces:

```bat
C:\xampp\php\php.exe database\maintenance.php
```

Run it manually when needed or schedule it through Windows Task Scheduler if this XAMPP installation is used continuously.

## Verification

Pure/unit checks:

```bat
C:\xampp\php\php.exe tests\run.php
C:\xampp\php\php.exe tests\architecture.php
C:\xampp\php\php.exe tests\mysqli_contract.php
C:\xampp\php\php.exe tests\native_database.php
C:\xampp\php\php.exe tests\styling.php
```

Real database integration tests are intentionally protected by test-only environment variables and must use a database whose name ends in `_test`; those variables are for automated/disposable tests only and are not application configuration.

GitHub Actions provisions its own disposable MySQL database for those checks, verifies the committed Tailwind build, and tests both styled and plain interfaces. The normal XAMPP application always uses the direct configuration in `application/config/database.php`.



Establish a bidirectional development workflow for Aevareth Monster Realm:

