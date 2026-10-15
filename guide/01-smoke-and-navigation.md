# 01 — Environment, navigation and error handling

For each case: perform the actions with test data, compare with the expected result, and write PASS/FAIL in the results template.

| ID | Manual steps | Expected result |
| --- | --- | --- |
| ENV-01 | Run `php -v`; inspect extension list and `composer validate`. | PHP 8.0-compatible dependencies; mysqli, mbstring, fileinfo and zip available. |
| ENV-02 | Start Apache/MariaDB; visit login and sign in as Administrator. | No PHP fatal error, blank page, warning or redirect loop. |
| ENV-03 | Log out; use Back button, then navigate to a previously protected URL. | Authenticated information is not served from cache. Protected action requires login. |
| ENV-04 | Sign in as restricted employee. Compare sidebar to their allowed permissions. | Unauthorized modules and actions are not exposed. |
| ENV-05 | Test each route from dashboard navigation (documents, requests, tasks, folders, admin where permitted). | Correct view, page title, active nav, content and safe empty state. |
| ENV-06 | On each register set filters, sort order, page and 10/25/50/100 limits. | Stable order, correct counts, only requested page; URL retains filters. |
| ENV-07 | Submit blank, invalid and expired forms; try malformed GET `page=abc` and `limit=bad`. | Clear 400/validation response; no 500 or leaked SQL/PHP internals. |
| ENV-08 | Attempt a state-changing operation twice and trigger browser reload/back. | No unexpected duplicate writes; optimistic version and confirmations protect edits. |
| ENV-09 | Disconnect internet while keeping the local application online; open modals/dropdowns. | Offline Bootstrap fallback works without CDN; close buttons restore focus. |
| ENV-10 | Run a keyboard-only pass (Tab, Shift+Tab, Enter, Space) and use 375px mobile viewport. | Focus indicators visible, no unusable navigation, modal controls accessible. |
| ENV-11 | Temporarily simulate a read-only private storage folder in QA. | Operation fails clearly and logs a server-side error; no partial record success. |
| ENV-12 | Inspect Apache/PHP logs and browser console following tests. | No uncaught JS exception or sensitive stack trace sent to end users. |

Do not run storage permission or duplicate-write tests against production records.
