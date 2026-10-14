# ISSUE 014 — Audit log append rewrites the entire daily JSON file each time

**Severity:** Medium (performance/concurrency at scale)  
**Type:** Confirmed algorithmic scalability issue  
**Status:** Proposed diagnostic/guard only; NOT applied

## Evidence
Audit_file::append() takes an exclusive lock, reads and json_decodes the entire YYYY-MM-DD.json array, appends one row and rewrites the whole file. A long day can make each write increasingly expensive, and 2,000-document bulk reassignment could add further audit pressure if per-document writes are introduced.

## Copyable benchmark (disposable environment only)
Create a standalone CLI script in a TEST clone after initializing PK_ROOT/APPPATH and CodeIgniter, then run:

~~~php
$log = new Audit_file();
$start = microtime(TRUE);
for ($i=0;$i<1000;$i++) {
    if (!$log->append(1,'audit-benchmark','fixture-'.$i,[])) {
        throw new RuntimeException('Append failed at '.$i);
    }
}
printf("1000 appends: %.3f seconds\n",microtime(TRUE)-$start);
~~~

Do NOT run a 1,000-append benchmark on a live office audit directory. For a defensive upper bound, inside append(), under the existing exclusive lock, before reading the file:

~~~php
clearstatcache(TRUE,$path);
if (is_file($path) && filesize($path) > 64*1024*1024) {
    error_log('Audit daily JSON exceeds configured safe size.');
    return FALSE;
}
~~~

This guard deliberately fails the append; decide how to alert operators and preserve a mandatory audit trail before enabling it. A long-term fix is a streaming JSONL journal + tested daily JSON export or an atomic journaling strategy that keeps the requested daily JSON contract. Do not remove locks or silently discard audit writes.

## Verify
Compare append durations at 100, 1,000 and 10,000 events on a clone. Run simultaneous writers and ensure no events are lost, files remain valid JSON, and failed writes are reported.
