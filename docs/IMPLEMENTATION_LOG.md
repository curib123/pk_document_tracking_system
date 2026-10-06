# Implementation log

- Baseline: remote master ddc9f418a46f9eaacea4872e7515fa1a1616a83a contains two Markdown files and no application/tests.
- Ruling: latest user instruction disables visual styling; preserve appearance configuration but do not apply CSS.
- Ruling: central dependency-free table/dialog replaces Bootstrap/jQuery/DataTables; preserves their required behavior without relying on a design framework.
- Environment: local PHP 8.4 is available, but PDO database drivers, Composer and outbound network access are not. Domain tests/lint/browser tests can run locally; MySQL/CI3 integration needs a configured runner. Do not claim that unavailable tests passed.
- Core domain suite: 17/17 passing locally; all new PHP files pass syntax checks.
- Integration test command was executed and explicitly blocked by the missing PDO MySQL driver (not a passing test).
- Ruling: changing an existing hardcopy's location or holder always uses a transfer; general edits cannot bypass recipient acceptance.
- Ruling: request correction retains the original workflow snapshot; historical steps are appended, not overwritten.
- Modal/browser contract: real JS executed in Chromium with simulated API responses; modal operation without CSS, focus/Escape, validation recovery and all 24 module navigation verified.
- Database/HTTP/concurrency suites added; unavailable local MySQL driver remains explicitly recorded rather than marked passed.
- Ruling: additional workflow definitions start inactive; publication atomically selects the single active definition for that request type.
- Ruling: a disposed hardcopy releases its current location; a complete immutable disposal snapshot retains the old physical coordinates.
- Review fix: condition values and workflow keys reject malformed array input as validation failures; graph traversal memoizes completed states.
- Review fix: superseded controlled artifacts cannot be downloaded as controlled copies; PDF stamps use an added footer area rather than covering source content.
- Review fix: inactive-role users cannot be selected as new holders/recipients.
- Regression fix: role-permission changes now require an audit reason in the modal; browser test first failed, then passed after the missing field was added.
- Publishing limitation: the connector blocked the extended `tests/http.py` upload. Keep it in the source archive; do not claim it ran in repository CI when the file is absent.
- Observed GitHub Actions success for commit `762056bae51d5625eb0827d0eb31f07e9867c18e`: 27 rules, 35 MySQL integration assertions, 80 unique identifiers from eight writers, actual CI3 session endpoint, and native-dialog browser contracts. Extended HTTP suite explicitly unrun.
