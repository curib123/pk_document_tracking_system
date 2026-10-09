<?php
define('BASEPATH', __DIR__);
$file = dirname(__DIR__).'/application/services/administration/account_policy.php';
if (!is_file($file)) { fwrite(STDERR, "Missing role delegation policy.\n"); exit(1); }
require $file;
$cases = [
 ['Administrator','Administrator',[],[1,2],true],
 ['Staff','Administrator',[1,2],[1,2],false],
 ['Staff','administrator',[1,2],[1,2],false],
 ['Manager','Staff',[1,2],[2],true],
 ['Manager','Staff',[1,2],[3],false],
 ['Manager','Staff',[],[],true],
];
foreach ($cases as [$actor,$target,$owned,$requested,$expected]) {
    if (Account_policy::can_delegate($actor,$target,$owned,$requested) !== $expected) {
        fwrite(STDERR, "Role delegation allowed escalation or rejected a valid subset.\n"); exit(1);
    }
}
echo "Role delegation policy passed (6 privilege boundary cases).\n";
