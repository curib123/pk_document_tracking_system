# 01 — How to Recreate a Bug Manually Before Fixing It

## Start from a disposable QA environment

1. Make an isolated local clone. In a terminal:

       git fetch origin
       git checkout development
       git pull --ff-only origin development
       git rev-parse HEAD
       git status --short

2. Record branch and SHA. Audit baseline was 29e7be5dd74977f930a7e7cefab13d0338256333. Any newer SHA changes your test baseline.
3. Use **local XAMPP/PHP 8.0 and a dedicated disposable MariaDB/MySQL database**, with configured CodeIgniter paths. Never connect this training exercise to your production database.
4. Check php -v, node -v and database version. Run composer install with a supported PHP version. Never run database dump imports on company data.
5. Create **synthetic** active users: A requester, B current holder, C destination recipient, D unrelated requester, and E administrator/approver. Use separate browser profiles, not shared sessions.
6. Create hardcopy H with distinctive title QA-HARDCOPY-ALPHA held by B at L1; create valid alternate location L2. Create softcopy S titled QA-SOFTCOPY-OMEGA. Publish appropriate approval workflows in QA. Keep every account and document fictional.
7. Back up the disposable QA database. Restore the same initial state between unrelated tests.

**Danger:** tests/smoke.sh, tests/schema_install.py, database migrations, restores and SQL dumps create/change/drop database objects. Confirm test host/database and backup before any such command. The checked-in SQL dump includes populated records and is NOT a safe blank test fixture.

## The manual seven-stage method

1. **Preconditions:** identify user role, document title/ID, request status, browser and exact branch SHA.
2. **Expected result:** write what should happen according to the specified process, before touching the button.
3. **Actions:** number the exact clicks and entered values needed to trigger the issue. Prefer a fresh login.
4. **Actual result:** capture exact alert, row, modal, HTTP code, first Console error and related PHP log.
5. **Repeat:** run identical sequence again after restoring QA. If it does not reproduce, record PASS or BLOCKED, not FAIL.
6. **Root cause:** only now inspect UI → controller → service → model → database. Tie code line to evidence.
7. **Fix and retest:** add failing regression, smallest safe code change, rerun same steps and negative tests, record commit.

## How to capture errors with browser developer tools

- Chrome/Edge desktop: F12 → Console, tick Preserve log; reproduce and copy the FIRST relevant exception.
- F12 → Network, tick Preserve log; re-run form. Record method, local path, status (200/302/403/500) and sanitized response; do not publish cookies, CSRF tokens or private records.
- Open the application/logs log for that day (if enabled), then Apache/PHP error logs. Redact credentials and personal information.
- In QA database, run SELECT-only before/after queries. Never use UPDATE/DELETE/DROP just to discover a bug.
- Take screenshots before and after; give them ID-based names (e.g., TR-01-before.png and TR-01-after.png).
- Distinguish "button hidden" from "server correctly rejected action": use only your authorized test account and compare HTTP response.

## Example exercise

A creates transfer request with subject "Move for storage" referencing H named QA-HARDCOPY-ALPHA. Go to My Requests → Hardcopy Transfer. First clear all filters and verify the row exists. Next search for the unique ALPHA title. Record result count. Clear and search for "Move for storage"; compare. This isolates title-search behavior from missing permissions or rows.

**Rule:** record expected-vs-actual and evidence before applying any proposed fix. Do not mark code-only risks as confirmed browser failures.
