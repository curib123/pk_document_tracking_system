# PK DTS — Manual acceptance testing

This folder is the handoff for manual verification of the CodeIgniter 3 / PHP 8.0 PK Document Tracking System.
Testing must be performed on a **disposable clone** of the application and database. Never restore a QA archive onto production.

## Test environment

- Windows XAMPP or equivalent PHP **8.0**, CodeIgniter 3, MariaDB 10.4+ / MySQL 8
- Extensions: mysqli, mbstring, fileinfo and ZIP with AES-256 support
- Local HTTPS or local office URL; working PHP sessions and writable private `storage/`
- Browser: latest Chromium plus one Firefox/mobile-size test
- Two accounts: Administrator and a restricted employee; a third employee/approver is useful
- Seed 30+ sample documents across several categories / locations, 12+ requests, and 3+ versioned files
- Use fabricated data. Keep two separate test folders: a working database and an untouched pre-test backup

## Before testing

1. Checkout the branch you intend to evaluate; record the exact commit SHA and PHP version (`php -v`).
2. Review `plan.md`, `guide/01-smoke-and-navigation.md`, `guide/02-permissions-and-requests.md`,
   `guide/03-document-search-and-modal.md`, and `guide/04-backup-schema-and-recovery.md`.
3. Run `composer install`, `composer test` and `bash tests/run_unit.sh` where Bash, PHP and Node are available.
4. Run `node --test tests/live_table_contract.test.js` for table interaction contracts.
5. Run DB integration tests **only against an isolated test database** using `bash tests/smoke.sh`.
   The smoke script loads fixture SQL and modifies database tables.
6. Ensure scheduler and archive signing/password environment variables are configured in the test environment.
7. Record every result in `guide/05-results-template.md`; attach screenshot, console output and logs on failures.

## Acceptance rule

Each test case has an ID. Mark PASS only when the expected result is actually observed, not simply because code exists.
Any permission bypass, wrong document visibility, data loss or broken restore = BLOCK RELEASE.
A failure on mobile, keyboard or offline networks should be tracked as a regression.
Do not merge to `development` until the relevant CI run and these manual cases pass.

## Execution map

| Area | Checklist |
| --- | --- |
| Installation, login, navigation, errors | 01-smoke-and-navigation.md |
| Role permissions and request workflow | 02-permissions-and-requests.md |
| Live search, folder browsing, modals, layouts | 03-document-search-and-modal.md |
| Database, backups, restores, recovery | 04-backup-schema-and-recovery.md |
| Evidence and final sign-off | 05-results-template.md |

## Automated QA

The `.github/workflows/pk-dts-schema.yml` workflow covers PHP 8.0 / 8.2 / 8.4 lint and unit contracts, Node tests,
schema smoke tests and backup integration. Observe the run for the **exact commit under test** before trusting a green result.
Automated tests do not substitute for browser, accessibility or restore drills.
