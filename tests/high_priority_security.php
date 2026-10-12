<?php
declare(strict_types=1);
define('BASEPATH', __DIR__);
define('APPPATH', dirname(__DIR__).'/application/');
require_once APPPATH.'services/presentation/folder_tree.php';
require_once APPPATH.'services/presentation/folder_visibility.php';
require_once APPPATH.'services/backup/backup_service.php';
require_once APPPATH.'services/transfer/transfer_destination_policy.php';

function hp_check(bool $condition,string $message): void {
    if (!$condition) throw new RuntimeException($message);
}
function hp_reject(callable $fn,string $message):void {
    try {$fn();} catch(DomainException $e) {return;}
    throw new RuntimeException($message);
}

$tree=Folder_tree::build([
    ['key'=>'area:1','parent'=>'','name'=>'Authorized Area','kind'=>'area'],
    ['key'=>'specific:1','parent'=>'area:1','name'=>'Authorized Specific','kind'=>'specific'],
    ['key'=>'asset:1','parent'=>'specific:1','name'=>'Asset 1','kind'=>'asset'],
    ['key'=>'location:1','parent'=>'asset:1','name'=>'Authorized Location','kind'=>'location'],
    ['key'=>'area:2','parent'=>'','name'=>'Private Area','kind'=>'area'],
    ['key'=>'specific:2','parent'=>'area:2','name'=>'Private Specific','kind'=>'specific'],
    ['key'=>'asset:2','parent'=>'specific:2','name'=>'Asset 2','kind'=>'asset'],
    ['key'=>'location:2','parent'=>'asset:2','name'=>'Private Location','kind'=>'location']
]);
$visible=Folder_visibility::restrict($tree,[
    ['area_id'=>1,'specific_id'=>1,'asset_id'=>1,'location_id'=>1]
],'hardcopy');
hp_check($visible['roots']===['area:1'],'Unauthorized roots must be hidden.');
hp_check(isset($visible['nodes']['location:1']) && !isset($visible['nodes']['area:2']),
    'Only ancestors of authorized documents may be visible.');
hp_check(!isset($visible['nodes']['location:2']),
    'Unrelated Location names must not appear.');
$noDocuments=Folder_visibility::restrict($tree,[],'hardcopy');
hp_check($noDocuments['roots']===[],'No documents must mean no folder names.');
$soft=Folder_tree::build([
    ['key'=>'category:9','parent'=>'','name'=>'Public','kind'=>'category'],
    ['key'=>'category:10','parent'=>'category:9','name'=>'Nested','kind'=>'category'],
    ['key'=>'category:99','parent'=>'','name'=>'Private','kind'=>'category']
]);
$softRestricted=Folder_visibility::restrict($soft,[['category_id'=>10]],'softcopy');
hp_check($softRestricted['roots']===['category:9'] &&
    count($softRestricted['nodes'])===2,'Nested Softcopy categories must respect document scope.');

$root=sys_get_temp_dir().'/pk-issue-'.bin2hex(random_bytes(6));
mkdir($root.'/public',0700,TRUE);
mkdir($root.'/storage/documents',0700,TRUE);
mkdir($root.'/storage/audit',0700,TRUE);
putenv('PK_BACKUP_DIR='.$root.'/storage/documents');
hp_reject(static function()use($root) {new Backup_service($root);},
    'Overlapping private backup path must fail.');
putenv('PK_BACKUP_DIR='.$root.'/storage');
hp_reject(static function()use($root) {new Backup_service($root);},
    'Parent of storage/documents must fail.');
putenv('PK_BACKUP_DIR='.$root);
hp_reject(static function()use($root) {new Backup_service($root);},
    'Source root must never be backup directory.');
putenv('PK_BACKUP_DIR');
$backup=new Backup_service($root);
hp_check(strpos($backup->directory(),'/storage/backups')!==FALSE,
    'Default private backup directory must be allowed.');

$reflection=new ReflectionMethod(Backup_service::class,'safeEntry');
$reflection->setAccessible(TRUE); // Needed for private method invocation on PHP 8.0.
foreach ([
    '../passwords','files//storage','files/storage/a.','files/storage/CON.txt',
    'files/storage/nul','files/storage/backup:stream','files/storage/a'.chr(92).'b',
    'files/storage/a'.chr(0).'b'
] as $unsafe) {
    hp_check(!$reflection->invoke(NULL,$unsafe),'Unsafe ZIP path accepted.');
}
foreach (['database.sql','manifest.json','manifest.sig',
 'files/storage/documents/abcdef','files/application/controllers/Docs.php'] as $safe) {
    hp_check($reflection->invoke(NULL,$safe),'Normal ZIP entry rejected: '.$safe);
}

$base=dirname(__DIR__);
$controller=file_get_contents($base.'/application/controllers/Documents.php');
$baseController=file_get_contents($base.'/application/core/MY_Controller.php');
$transfer=file_get_contents($base.'/application/services/transfer/transfer_service.php');
$request=file_get_contents($base.'/application/services/request/request_service.php');
$document=file_get_contents($base.'/application/services/documents/document_service.php');
$schema=file_get_contents($base.'/database/pk_dts.sql');
$recovery=file_get_contents($base.'/application/services/backup/backup_service.php');
hp_check(strpos($controller,'Folder_visibility::restrict')!==FALSE &&
    strpos($controller,'outside your document access')!==FALSE,
    'Controller must deny unauthorized folders.');
hp_check(strpos($baseController,"is_file(PK_ROOT.'/storage/.restore-in-progress')") <
    strpos($baseController, '$this->load->model(\'Identity_model\')'),
    'Maintenance guard must run before loading user identity.');
hp_check(strpos($transfer,'Transfer_destination_policy::require_approved_parents')!==FALSE,
    'Approved destination must match current parents on acceptance.');
Transfer_destination_policy::require_approved_parents(
    ['area_id'=>1,'specific_id'=>2,'asset_id'=>NULL],
    ['area_id'=>1,'specific_id'=>2,'asset_id'=>0]);
foreach ([
    ['area_id'=>3,'specific_id'=>2,'asset_id'=>NULL],
    ['area_id'=>1,'specific_id'=>4,'asset_id'=>NULL],
    ['area_id'=>1,'specific_id'=>2,'asset_id'=>8]
] as $changed) {
    hp_reject(static function() use($changed) {
        Transfer_destination_policy::require_approved_parents(
            ['area_id'=>1,'specific_id'=>2,'asset_id'=>NULL],$changed);
    },'Changed parent must reject transfer acceptance.');
}
hp_check(strpos($request,'destination is already occupied')===FALSE &&
    strpos($document,'selected Location is already assigned')===FALSE,
    'Location occupancy must no longer block several documents.');
hp_check(strpos($schema,'idx_hardcopy_documents_location')!==FALSE,
    'New schema must use a named Location lookup index.');
hp_check(strpos($recovery,'if ($recoverStage!==NULL) $this->removeDirectory($recoverStage)')!==FALSE,
    'Failed rollback must clean decrypted staging in finally.');

foreach (glob($root.'/storage/backups/.*')?:[] as $leftover) {
    if (is_file($leftover)) unlink($leftover);
}
rmdir($root.'/storage/backups');
rmdir($root.'/storage/audit');
rmdir($root.'/storage/documents');
rmdir($root.'/public');
rmdir($root.'/storage');
rmdir($root);
echo "High-priority folder privacy, archive paths, maintenance, and custody contracts passed.\n";
