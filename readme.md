# PK Document Tracking System

A PHP application for managing physical and digital documents, custody, assignments, and approval workflows. Built with CodeIgniter 3 and MySQL/MariaDB, with a server-rendered interface and shared document viewers.

## Features

- Hardcopy documents organized by Area, Specific, Asset, and Location, with holder and retention information.
- Softcopy documents with controlled files, revisions, file history, authorized downloads, and printing.
- Requests for document creation, updates, cancellation, transfers, disposal, access, and assignments.
- Approval tasks, workflow history, and notifications.
- Individual document assignments and bulk assignment by Location or Category, including subcategories.
- Role-based permissions, user administration, and filesystem audit logs.
- Dashboard summaries, searchable registers, responsive modals, and persistent light/dark mode.
- Backup and restore tools, plus a browser installation option when PWA installation is available.

## Requirements

- PHP 8.x with `mysqli`, `fileinfo`, `mbstring`, and `zip` enabled.
- Composer for the PHP dependencies in [composer.json](composer.json).
- MySQL/MariaDB compatible with the supplied database schema. The supplied SQL export identifies MariaDB 10.4.32 as its source server.
- Apache with `mod_rewrite` and permission to use the repository's `.htaccess` files.
- Write access for PHP to `storage/` and its subdirectories.

XAMPP is the local Windows setup used by the included helper scripts. Frontend CSS and JavaScript are checked in; a frontend build is not required to run the PHP application.

## Local setup with XAMPP

1. Place the checkout at `C:\xampp\htdocs\pk_document_tracking_system`, or configure Apache to serve your chosen checkout.
2. Start Apache and MySQL from the XAMPP Control Panel.
3. Install PHP dependencies from the repository root:

   ```powershell
   composer install
   ```

4. Create an empty `pk_dts` database using `utf8mb4`. Import [database/pk_dts.sql](database/pk_dts.sql) into that database using phpMyAdmin or the MySQL client. This file contains schema **and existing data**; review it before importing and do not import it over an existing installation. Review [database/migrations](database/migrations) against the installed schema when upgrading; there is no automatic migration runner.
5. Configure the database environment variables described below if your connection differs from the local defaults.
6. Create your own administrator account if needed:

   ```powershell
   C:\xampp\php\php.exe tools/create_admin.php your_admin_username
   ```

   The interactive command asks for a name, position, and password. Password input is visible. The database must already contain an active Administrator role.

7. Open [the local dashboard](http://localhost/pk_document_tracking_system/dashboard) and sign in. Unauthenticated requests redirect to the login page.

The repository-root [index.php](index.php) forwards to `public/index.php`. The root `.htaccess` maps public assets and blocks private application directories. Application dates use `Asia/Manila`.

## Configuration

Database settings come from [application/config/database.php](application/config/database.php):

| Variable | Local default | Purpose |
| --- | --- | --- |
| `DB_HOST` | `127.0.0.1` | Database host |
| `DB_PORT` | `3306` | Database port |
| `DB_DATABASE` | `pk_dts` | Database name |
| `DB_USERNAME` | `root` | Database user |
| `DB_PASSWORD` | Empty | Database password |
| `CI_ENV` | `production` | Application environment; `development` enables displayed PHP errors |
| `PK_ENCRYPTION_KEY` | Empty | CodeIgniter encryption key |

Set environment variables for the process that runs PHP. Variables set in a PowerShell window apply to commands launched from that window; they do not automatically reach an already-running XAMPP Apache process. The application does not automatically load `.env` files. Keep credentials and private keys outside version control.

## Project layout

| Path | Contents |
| --- | --- |
| `application/controllers/` | HTTP controllers and permission checks |
| `application/models/` | Database queries and listings |
| `application/services/` | Document, workflow, file, security, and backup operations |
| `application/view/` | Page templates and shared UI components |
| `public/assets/` | Styles, JavaScript, fonts, and images |
| `database/` | Database export and incremental SQL migrations |
| `storage/` | Private files, sessions, logs, audits, and generated artifacts |
| `tests/` | Focused browser and HTTP regression checks |
| `tools/` | Administrator, backup, asset, and local synchronization utilities |

Shared colors are defined in [public/assets/css/colors.css](public/assets/css/colors.css). Theme behavior lives in [public/assets/js/theme.js](public/assets/js/theme.js), and document View rendering lives in [public/assets/js/document-view.js](public/assets/js/document-view.js).

## Verification

The available browser checks require Python, Playwright, Microsoft Edge, running MySQL, and the PHP dependencies:

```powershell
python -m pip install playwright
python tests/cancelled_draft_delete.py
python tests/document_assignments.py
python tests/document_header.py
python tests/theme_toggle.py
python tests/dark_mode_feedback.py
python tests/modal_close_buttons.py
```

These checks clone the source database into a generated database ending in `_test`, run a temporary application, and drop that test database afterward. The source database is read for the clone; test mutations apply to the disposable copy. The database account needs permission to create and drop test databases. Generated screenshots are written under ignored `storage/` directories.

The fixture defaults to `C:\xampp\php\php.exe`, `C:\xampp\mysql\bin\mysql.exe`, and source database `pk_dts`. Override these with `TEST_PHP`, `TEST_MYSQL`, and `TEST_SOURCE_DATABASE`; `mysqldump.exe` must be beside the configured MySQL executable. Database credentials use `DB_HOST`, `DB_USERNAME`, and `DB_PASSWORD`. The fixture currently connects through the client's default MySQL port.

External CDN assets are blocked by default to exercise the fallback interface. To also check Bootstrap and other CDN assets:

```powershell
$env:TEST_EXTERNAL_ASSETS = '1'
python tests/document_assignments.py
python tests/modal_close_buttons.py
Remove-Item Env:TEST_EXTERNAL_ASSETS
```

The current `composer test` and npm build/test scripts reference runner files that are absent from this checkout. Use the focused checks above until those runners are restored.

## Sync a development checkout to local XAMPP

When editing a separate development checkout, run this from that checkout:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File tools/sync_local_xampp.ps1
```

The script defaults to `C:\xampp\htdocs\pk_document_tracking_system` and `http://localhost/pk_document_tracking_system/`. Both checkouts must have clean working trees, and the served checkout must be able to fast-forward to the development branch. The script updates tracked files and verifies HTTP asset hashes. It does not import database changes or replace private storage. Reload the browser after syncing.

## Share the XAMPP site through ngrok

Use this when a computer outside the local network needs access. Apache and
MySQL must remain running on the host PC. The launcher uses XAMPP's existing
HTTPS listener on port 443, so application links and secure cookies keep HTTPS.

1. Install the Windows agent from [ngrok's official download page](https://ngrok.com/download/windows).
   The launcher checks `%LOCALAPPDATA%\Programs\ngrok\ngrok.exe`, then `PATH`;
   pass `-NgrokPath` if installed elsewhere.
2. Sign into [your ngrok account](https://dashboard.ngrok.com/get-started/your-authtoken)
   and configure its authtoken **locally**, using the installed executable:

   ```powershell
   & "$env:LOCALAPPDATA\Programs\ngrok\ngrok.exe" config add-authtoken "YOUR_AUTHTOKEN"
   ```

   Replace the placeholder with your own token. It belongs in ngrok's private
   user configuration, outside the repository. Do not put it in chat or commit it.
3. From this checkout, run:

   ```powershell
   powershell.exe -NoProfile -ExecutionPolicy Bypass -File tools/start_ngrok.ps1
   ```

4. Open the HTTPS **Forwarding** URL shown by ngrok. Its root redirects to
   `/pk_document_tracking_system/`; sign in with an existing application account.
   Keep the terminal open. Press **Ctrl+C** to stop the public endpoint.

The checked-in traffic policy forwards only the PK DTS path and returns 404 for
other XAMPP sites. Request inspection is disabled. The agent connects to
`https://127.0.0.1:443` without checking XAMPP's self-signed certificate; public
HTTPS remains provided by ngrok. Router forwarding and database port changes
are unnecessary. Localhost and LAN URLs continue to work as before.

## Backups

[tools/backup.php](tools/backup.php) provides CLI backup, listing, verification, retention, and restore commands. Configure `DB_*`, `PK_BACKUP_PASSWORD` (at least 12 characters), and `PK_BACKUP_SIGNING_KEY` (at least 32 characters) in the PHP process environment. `PK_MYSQL` and `PK_MYSQLDUMP` can override the database tool paths; `PK_BACKUP_DIR` can select a private backup directory.

```powershell
php tools/backup.php manual
php tools/backup.php list
php tools/backup.php verify <archive-name.zip>
```

[tools/install_backup_schedule.ps1](tools/install_backup_schedule.ps1) can register a daily 5 PM task on a Windows host configured for UTC+08:00. It requires an elevated PowerShell session and configuration available to the SYSTEM account. Restore replaces application data and requires the CLI's explicit `--confirm` flag; verify the intended archive and target database first.
