<?php
$p=dirname(__DIR__).'/application/services/request/request_policy.php';
if (!is_file($p)) {fwrite(STDERR,"Request authorization policy missing.\n");exit(1);}
require $p;
$cases=['softcopy_create'=>'softcopy','softcopy_cancel'=>'softcopy','hardcopy_update'=>'hardcopy',
 'transfer'=>'transfer','access'=>'access','assignment'=>'assignment','disposal'=>'disposal'];
foreach ($cases as $type=>$module) if (Request_policy::module($type)!==$module) throw new RuntimeException('Wrong action permission mapping.');
try {Request_policy::module('invented');throw new RuntimeException('Unknown type accepted.');} catch (DomainException $e) {}
$row=['requested_by'=>7,'type'=>'hardcopy_update','status'=>'draft','version'=>3];
Request_policy::editable($row,7,'hardcopy_update',['version'=>3]);
foreach ([[8,'hardcopy_update',['version'=>3]], [7,'hardcopy_create',['version'=>3]], [7,'hardcopy_update',['version'=>2]]] as $bad) {
    try {Request_policy::editable($row,...$bad);throw new RuntimeException('Stale, foreign or different-type edit accepted.');} catch (DomainException $e) {}
}
$row['status']='submitted';
try {Request_policy::editable($row,7,'hardcopy_update',[]);throw new RuntimeException('Submitted request edited.');} catch (DomainException $e) {}
echo "Request module permissions, immutable type, owner and stale/submitted edit rules passed.\n";
