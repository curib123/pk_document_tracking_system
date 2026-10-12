# Issue 017: Growing daily JSON audit files become increasingly expensive to append

Severity: MEDIUM (performance/availability) | Status: Confirmed algorithmic behavior | Implemented: NO

Affected: application/libraries/Audit_file.php::append().

Every audited action loads the entire daily JSON array into PHP memory, decodes it, appends one item, then rewrites the whole file. That is O(n) bytes per write and approximately O(n²) total work as the day grows. Large audit days may become slow or memory-heavy. The user requirement is still one JSON file per day, so replacing it with database rows is not appropriate.

## Copyable minimum defensive fix

Just after acquiring the exclusive audit lock, before json_decode:

~~~php
$maxDailyAuditBytes = 16 * 1024 * 1024;
clearstatcache(TRUE, $path);
if (is_file($path) && filesize($path) > $maxDailyAuditBytes) {
    log_message('error', 'Daily JSON audit file exceeded safe memory limit.');
    return FALSE;
}
~~~

This is a guard, not a scalable long-term solution. It can result in an unavailable audit append after 16 MiB. For full scalability, retain the daily JSON-array file format but use a tested streaming writer with an append-safe journal and atomic rotation; do not truncate an existing JSON array without a rollback strategy.

## Verify
Generate 100, 1,000 and 10,000 audit entries on a clone and measure append latency and memory. Concurrent appends must always leave valid JSON and no lost records. Test corrupt-file behavior and audit retries. Do not weaken existing file locks.
