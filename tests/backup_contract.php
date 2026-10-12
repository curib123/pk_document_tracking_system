<?php
declare(strict_types=1);
define('BASEPATH', __DIR__);
require_once __DIR__.'/../application/services/backup/backup_service.php';

function backup_assert(bool $pass,string $message): void {
    if (!$pass) throw new RuntimeException($message);
}
function backup_fails(callable $callback,string $expected):void {
    try {$callback();}
    catch (DomainException $e) {
        backup_assert(strpos($e->getMessage(),$expected)!==FALSE,
            'Unexpected backup validation message: '.$e->getMessage());
        return;
    }
    throw new RuntimeException('Expected rejection: '.$expected);
}

$root=dirname(__DIR__);
$routes=file_get_contents($root.'/application/config/routes.php');
$controller=file_get_contents($root.'/application/controllers/Backups.php');
$nav=file_get_contents($root.'/application/view/layout/sidebar_top_nav.php');
$view=file_get_contents($root.'/application/view/pages/administration/backups.php');
$script=file_get_contents($root.'/tools/install_backup_schedule.ps1');
$docs=file_get_contents($root.'/docs/backup-restore.md');
$service=file_get_contents($root.'/application/services/backup/backup_service.php');

foreach (['admin/backups','admin/backups/create','admin/backups/import',
          'admin/backups/restore','admin/backups/download'] as $route)
    backup_assert(strpos($routes,$route)!==FALSE,'Missing administrator backup route.');
backup_assert(strpos($controller,"strcasecmp((string)(\$this->user['role']??''),'Administrator')")!==FALSE,
    'Backup endpoints must authorize Administrator only.');
backup_assert(strpos($controller,'$this->confirmed()')!==FALSE,
    'Backup writes must require CSRF-enabled POST confirmation.');
backup_assert(strpos($controller,'password_verify(')!==FALSE &&
    strpos($controller,"'confirm_phrase'")!==FALSE,
    'Restore requires current admin password and typed confirmation.');
backup_assert(strpos($controller,"'Content-Type: application/zip'")!==FALSE,
    'Archive downloads must be protected by a controller.');
backup_assert(strpos($nav,'Backup &amp; Restore')!==FALSE,
    'Backup and Restore must appear in Administrator navigation.');
backup_assert(strpos($view,'name="backup_zip"')!==FALSE &&
    strpos($view,'name="admin_password"')!==FALSE,
    'Backup upload and restore controls must be provided.');
backup_assert(strpos($script,"-Daily -At '5:00PM'")!==FALSE &&
    strpos($script,'StartWhenAvailable')!==FALSE,
    'Windows scheduler must run daily at 5 PM.');
backup_assert(strpos($docs,'CRON_TZ=Asia/Manila')!==FALSE,
    'Linux scheduled backup instructions must use Philippines timezone.');
backup_assert(strpos($service,'ZipArchive::EM_AES_256')!==FALSE &&
    strpos($service,"hash_hmac('sha256'")!==FALSE,
    'Every ZIP must use AES encryption and a signed manifest.');
backup_assert(strpos($service,'storage/sessions')!==FALSE &&
    strpos($service,"\\.env")!==FALSE,
    'Transient session and secrets must not be included in backups.');
backup_assert(strpos($service,"sourceFiles()")!==FALSE &&
    strpos($service,"'files/storage/'")!==FALSE,
    'Backup must cover source files and restore private controlled documents.');
backup_assert(strpos($service,"'recovery'")!==FALSE,
    'Restore must create a pre-restore recovery backup.');
backup_assert(strpos($service,'$leaveMaintenance=TRUE;')!==FALSE &&
    strpos($service,'if (!$leaveMaintenance) @unlink($maintenance);')!==FALSE,
    'Failed rollback must leave maintenance mode enabled for safe intervention.');

$testRoot=sys_get_temp_dir().'/pk-dts-backup-'.bin2hex(random_bytes(8));
@mkdir($testRoot.'/public',0700,TRUE);
$sign=str_repeat('X',40);
$pass=str_repeat('Z',16);
putenv('PK_BACKUP_SIGNING_KEY='.$sign);
putenv('PK_BACKUP_PASSWORD='.$pass);
putenv('DB_DATABASE=pk_dts_test');
$serviceInstance=new Backup_service($testRoot);
$testZip=$serviceInstance->directory().'/pk-dts-manual-20261013-170000-1234567890.zip';
if (!class_exists('ZipArchive')||!defined('ZipArchive::EM_AES_256'))
    throw new RuntimeException('PHP ZIP AES-256 extension required for backup contract tests.');
$zip=new ZipArchive();
backup_assert($zip->open($testZip,ZipArchive::CREATE|ZipArchive::EXCL)===TRUE,
    'Unable to create synthetic encrypted backup.');
$data=['database.sql'=>'SELECT 1;' ,'files/storage/documents/one.txt'=>'file sample'];
$entries=[];
foreach ($data as $path=>$bytes) {
    $zip->addFromString($path,$bytes);
    backup_assert($zip->setEncryptionName($path,ZipArchive::EM_AES_256,$pass),
        'AES-256 archive encryption unavailable.');
    $entries[]=['path'=>$path,'size'=>strlen($bytes),'sha256'=>hash('sha256',$bytes)];
}
$manifest=json_encode(['format'=>'pk-dts-full-v1','date'=>'2026-10-13T17:00:00+08:00',
    'timezone'=>'Asia/Manila','database'=>'pk_dts_test',
    'files'=>$entries,'mode'=>'full-application-and-private-data'],
    JSON_UNESCAPED_SLASHES);
$zip->addFromString('manifest.json',$manifest);
$zip->setEncryptionName('manifest.json',ZipArchive::EM_AES_256,$pass);
$zip->addFromString('manifest.sig',hash_hmac('sha256',$manifest,$sign));
$zip->setEncryptionName('manifest.sig',ZipArchive::EM_AES_256,$pass);
backup_assert($zip->close()===TRUE,'Unable to write synthetic backup archive.');

try {
    $checked=$serviceInstance->verify($testZip);
    backup_assert(count($checked['files'])===2,'Verified files must match the manifest.');
    backup_fails(static function()use($serviceInstance) {
        $serviceInstance->resolve('../.env');
    },'Invalid backup filename.');
    putenv('PK_BACKUP_SIGNING_KEY='.str_repeat('Y',40));
    backup_fails(static function()use($serviceInstance,$testZip) {
        $serviceInstance->verify($testZip);
    },'signature');
    putenv('PK_BACKUP_SIGNING_KEY='.$sign);
    putenv('PK_BACKUP_PASSWORD='.str_repeat('W',16));
    backup_fails(static function()use($serviceInstance,$testZip) {
        $serviceInstance->verify($testZip);
    },'signature');
} finally {
    @unlink($testZip);
    @unlink($serviceInstance->directory().'/.backup.lock');
    @rmdir($serviceInstance->directory());
    @rmdir($testRoot.'/storage');
    @rmdir($testRoot.'/public');
    @rmdir($testRoot);
    putenv('PK_BACKUP_SIGNING_KEY');
    putenv('PK_BACKUP_PASSWORD');
    putenv('DB_DATABASE');
}
echo "Encrypted ZIP authenticity and full backup/restore contracts passed.\n";
