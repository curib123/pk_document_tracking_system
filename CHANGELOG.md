# Changelog

## 0.1.0 — Functional implementation candidate

Added CodeIgniter 3 application, MySQL schema/installer, transactional service layer, role-based authorization, private documents/files, preserved revisions, snapshot-based workflow execution, request/correction/approval flows, recipient-confirmed physical transfers, grants/assignments, retention/disposal, notifications and audit history.

Added unstyled native-dialog UI for record actions, shared forms/lookups/tables, CLI maintenance, XAMPP/MySQL local setup instructions and automated verification suites. The existing visual-design specification is deliberately not applied.

Removed project `.env` handling, Docker/Compose runtime files and the custom PHP development-server router. Local runtime now targets XAMPP Apache with XAMPP MySQL directly.


## PHP 8.0 / XAMPP cleanup

Retargeted the application and CI to PHP 8.0, replaced PHP 8.1-only readonly properties and first-class callable syntax, removed associative-array unpacking that PHP 8.0 cannot execute, and deleted obsolete planning/build compatibility files.

## Redundancy cleanup

Removed the legacy query-string API adapter and service aliases, eliminated duplicated endpoint-route test data, flattened one-use UI template fragments, and removed obsolete compatibility/test files. Native CodeIgniter routes remain the single HTTP interface.
