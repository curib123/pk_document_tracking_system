# ISSUE 012 — Access expiry validation uses a different timezone from date-range UI

**Severity:** Medium (date correctness)  
**Type:** Confirmed source inconsistency, impact depends on XAMPP host timezone  
**Status:** Proposed fix only; NOT applied

## Evidence
- Request_service::save_draft() sets date_received in Asia/Manila.
- Its Access validation compares expiry <= date('Y-m-d') using the PHP default timezone.
- Document_access_service::apply() checks strtotime($expires) < time(), again under the server default timezone.
- Other system date computations explicitly use Asia/Manila. At UTC/Manila midnight boundaries, the expiry validation may accept or reject a date incorrectly.

## Copyable fix A
In application/services/request/request_service.php::save_draft(), immediately before the expiry comparison, calculate:

~~~php
$todayManila = (new DateTimeImmutable(
    'today',new DateTimeZone('Asia/Manila')
))->format('Y-m-d');
~~~

Then replace ONLY expiry <= date('Y-m-d') with expiry <= $todayManila. Keep the existing strict YYYY-MM-DD validation.

## Copyable fix B
In application/services/documents/document_access_service.php::apply(), replace the strtotime expiry check with:

~~~php
$endOfDay = new DateTimeImmutable(
    $date.' 23:59:59',
    new DateTimeZone('Asia/Manila')
);
$now = new DateTimeImmutable('now',new DateTimeZone('Asia/Manila'));
if ($endOfDay <= $now) {
    throw new DomainException('Access expiry must be in the future.');
}
~~~

If the database stores UTC DATETIME values, convert endOfDay to UTC before inserting into expires_at; otherwise the current date string remains a Manila-local value and the database comparison must use the same policy.

## Verify
With the PHP default timezone set to UTC in a test environment, submit expiry dates at 00:05 and 23:55 Philippine time. Compare outcomes with a Manila-configured host; they must agree. Verify grant expiration in SQL and that documents become unavailable at the intended midnight.
