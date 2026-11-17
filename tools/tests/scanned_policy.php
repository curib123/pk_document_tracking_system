<?php
define('BASEPATH', __DIR__);
require dirname(__DIR__, 2) . '/application/services/documents/scanned_policy.php';
$checks = 0;
function check(bool $ok, string $label): void {
    global $checks; if (!$ok) throw new RuntimeException($label); $checks++; echo "PASS $label\n";
}
function refuses(callable $fn, string $label): void {
    try { $fn(); } catch (DomainException $e) { check(TRUE, $label); return; }
    check(FALSE, $label);
}
$p = ['title' => 'Invoices', 'remarks' => 'Archive for audit', 'retention_start_date' => '2026-11-17', 'retention_end_date' => '2026-11-18'];
Scanned_policy::metadata('scanned_create', $p);
check(TRUE, 'valid upload metadata accepted');
refuses(fn() => Scanned_policy::metadata('scanned_create', array_replace($p, ['remarks' => '  '])), 'blank reason refused');
refuses(fn() => Scanned_policy::metadata('scanned_delete', ['remarks' => '']), 'deletion requires reason');
refuses(fn() => Scanned_policy::metadata('scanned_category_create', ['title' => 'Root', 'remarks' => '']), 'root category requires reason');
refuses(fn() => Scanned_policy::metadata('scanned_create', array_replace($p, ['retention_end_date' => '2026-11-16'])), 'reversed retention refused');
refuses(fn() => Scanned_policy::date('2026-02-30'), 'impossible date refused');
refuses(fn() => Scanned_policy::date('2026-1-1'), 'noncanonical date refused');
check(Scanned_policy::action('scanned_update') === 'edit' && Scanned_policy::action('scanned_delete') === 'delete', 'update and delete require distinct permissions');
Scanned_policy::metadata('scanned_create', array_replace($p, ['description' => str_repeat('a', 2000)]));
check(TRUE, '2000 character description accepted');
refuses(fn() => Scanned_policy::metadata('scanned_update', array_replace($p, ['description' => str_repeat('a', 2001)])), 'overlong description refused');
$zone = new DateTimeZone('Asia/Manila');
check(Scanned_policy::countdown('2026-11-17', new DateTimeImmutable('2026-11-17 16:00', $zone)) === '0 days 8 hours remaining', 'retention includes full final day');
check(Scanned_policy::countdown('2026-11-17', new DateTimeImmutable('2026-11-18 00:00', $zone)) === 'Expired: 0 days 0 hours overdue', 'expiry begins at Manila midnight');
echo "$checks checks passed\n";
