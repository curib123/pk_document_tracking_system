# Database directory: installer and migrations

All database CLI scripts and SQL assets are grouped here. The legacy bin/
folder was removed.

Fresh database commands from repository root:

    php database/install.php
    php database/migrate.php

The installer accepts only a NEW empty MySQL/MariaDB database, loads clean
schema.sql, generates random initial administrator credentials using the PHP
Seed service, then applies seed.sql to record schema version 7.

Additional commands:

    php database/seed.php
    php database/maintenance.php
    php database/sync_document_visibility.php
    php database/sync_request_catalog.php

seed.sql intentionally contains no password hashes or user data. Administrative
role permissions and default approval workflows are generated through
application/libraries/support/Seed.php so the initial random password is never
stored in Git.

Back up existing databases before running migrate.php. Do not run install.php
against company data. A public SQL export was removed from the current branch;
deletion does not scrub historical Git commits or previously cloned copies.
If that export contained real accounts, separately review exposure and rotate
credentials.

Run php tests/database_layout.php to verify the paths and sanitized DDL.
