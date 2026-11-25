<?php
define('BASEPATH', __DIR__);
require dirname(__DIR__, 2) . '/application/services/places/scanned_category_policy.php';
$rows = [['id'=>1,'parent_id'=>NULL],['id'=>2,'parent_id'=>1],['id'=>3,'parent_id'=>2],['id'=>4,'parent_id'=>NULL]];
$allocations = [['category_id'=>1,'user_id'=>10],['category_id'=>2,'user_id'=>20]];
function verify($ok, $label) { if (!$ok) throw new RuntimeException($label); echo "PASS $label\n"; }
verify(Scanned_category_policy::usable_ids($rows, [], 99) === [1,2,3,4], 'unallocated folders remain shared');
verify(Scanned_category_policy::usable_ids($rows, $allocations, 10) === [1,4], 'nested allocation overrides parent users');
verify(Scanned_category_policy::usable_ids($rows, $allocations, 20) === [2,3,4], 'allocation is inherited by descendants');
verify(Scanned_category_policy::usable_ids($rows, $allocations, 99) === [4], 'unassigned user cannot use allocated branches');
$allocations[] = ['category_id'=>2,'user_id'=>10];
verify(Scanned_category_policy::usable_ids($rows, $allocations, 10) === [1,2,3,4], 'one folder can be allocated to multiple users');
verify(Scanned_category_policy::usable_ids([['id'=>1,'parent_id'=>2],['id'=>2,'parent_id'=>1]], [], 10) === [], 'malformed cycles do not expose folders');
