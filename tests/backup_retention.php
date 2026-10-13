<?php
declare(strict_types=1);
define('BASEPATH', __DIR__);
require_once __DIR__.'/../application/services/backup/backup_service.php';

function retention_check(bool $pass,string $message): void {
    if (!$pass) throw new RuntimeException($message);
}
function retention_fixture(string $root,string $relative): string {
    $path=$root.'/'.$relative;
    if (!is_dir(dirname($path))) mkdir(dirname($path),0700,TRUE);
    file_put_contents($path,'retention fixture');
    return $path;
}
function retention_remove(string $path): void {
    if (is_link($path)) {
        if (DIRECTORY_SEPARATOR==='\\' && is_dir($path)) rmdir($path);
        else unlink($path);
        return;
    }
    if (is_file($path)) {unlink($path);return;}
    if (!is_dir($path)) return;
    foreach (new DirectoryIterator($path) as $entry) {
        if (!$entry->isDot()) retention_remove($entry->getPathname());
    }
    rmdir($path);
}
function retention_directory_link(string $target,string $link): bool {
    if (@symlink($target,$link)) return TRUE;
    if (DIRECTORY_SEPARATOR!=='\\') return FALSE;
    // Windows junctions do not require the symbolic-link privilege.
    $command="New-Item -ItemType Junction -Path '".str_replace("'","''",$link).
        "' -Target '".str_replace("'","''",$target)."' -ErrorAction Stop | Out-Null";
    $process=proc_open(['powershell.exe','-NoProfile','-NonInteractive','-Command',$command],
        [1=>['pipe','w'],2=>['pipe','w']],$pipes);
    if (!is_resource($process)) return FALSE;
    stream_get_contents($pipes[1]);stream_get_contents($pipes[2]);
    fclose($pipes[1]);fclose($pipes[2]);
    return proc_close($process)===0 && is_dir($link);
}
function retention_rejected(callable $callback): void {
    try {$callback();}
    catch (DomainException $error) {return;}
    throw new RuntimeException('Unsafe or busy retention must be rejected.');
}

$originalDirectory=getenv('PK_BACKUP_DIR');
$originalPassword=getenv('PK_BACKUP_PASSWORD');
$originalSigning=getenv('PK_BACKUP_SIGNING_KEY');
$root=sys_get_temp_dir().'/pk-dts-retention-'.bin2hex(random_bytes(8));
$outside=$root.'-outside';
mkdir($root.'/public',0700,TRUE);
mkdir($outside,0700,TRUE);
putenv('PK_BACKUP_DIR='.$root.'/storage/backups');
$timezone=new DateTimeZone('Asia/Manila');
$now=new DateTimeImmutable('2026-10-13 17:00:00',$timezone);
$symlinks=0;

try {
    $service=new Backup_service($root);
    retention_check(method_exists($service,'apply_retention'),
        'Backup service must implement three-month retention.');

    // Missing cleanup or a cutoff based on 90 days leaves expired snapshots.
    foreach (['auto','manual','manual-import','recovery'] as $kind) {
        retention_fixture($root,'storage/backups/pk-dts-'.$kind.'-20260713-165959-1234567890.zip');
    }
    $boundary=retention_fixture($root,'storage/backups/pk-dts-auto-20260713-170000-1234567890.zip');
    $recent=retention_fixture($root,'storage/backups/pk-dts-auto-20261013-170000-1234567890.zip');
    $logOld=retention_fixture($root,'storage/logs/log-2026-07-12.log');
    $auditOld=retention_fixture($root,'storage/audit/2026-07-12.json');
    $logBoundary=retention_fixture($root,'storage/logs/log-2026-07-13.log');
    $auditBoundary=retention_fixture($root,'storage/audit/2026-07-13.json');
    $preserved=[];
    foreach ([
        'storage/backups/pk-dts-auto-20260230-000000-1234567890.zip',
        'storage/backups/pk-dts-auto-20260101-250000-1234567890.zip',
        'storage/backups/pk-dts-other-20260101-000000-1234567890.zip',
        'storage/backups/unrelated.zip',
        'storage/backups/nested/pk-dts-auto-20260101-000000-1234567890.zip',
        'storage/backups/.stage-example/database.sql',
        'storage/logs/log-2026-02-30.log',
        'storage/logs/server.log',
        'storage/logs/nested/log-2026-01-01.log',
        'storage/audit/2026-02-30.json',
        'storage/audit/custom.json',
        'storage/documents/log-2026-01-01.log',
        'storage/documents/pk-dts-auto-20260101-000000-1234567890.zip',
        'storage/sessions/session-old'
    ] as $path) $preserved[]=retention_fixture($root,$path);
    $result=$service->apply_retention($now);
    retention_check($result['cutoff']==='2026-07-13T17:00:00+08:00','Retention must use three calendar months in Asia/Manila.');
    retention_check($result['deleted']===['backups'=>4,'audit'=>1,'logs'=>1],
        'Expired managed snapshots, audit files and application logs must be deleted.');
    retention_check(!file_exists($logOld) && !file_exists($auditOld),'Expired daily logs must be removed.');
    foreach (array_merge($preserved,[$boundary,$recent,$logBoundary,$auditBoundary]) as $path)
        retention_check(is_file($path),'Retention must preserve boundary, live and unmanaged files: '.basename($path));
    retention_check($service->apply_retention($now)['deleted']===['backups'=>0,'audit'=>0,'logs'=>0],
        'Repeated cleanup must be idempotent.');

    // A direct DateTime -3 months subtraction can overflow February into March.
    foreach ([
        ['2026-05-31 17:00:00','2026-02-28T17:00:00+08:00','20260228','2026-02-28'],
        ['2024-05-31 17:00:00','2024-02-29T17:00:00+08:00','20240229','2024-02-29']
    ] as [$date,$cutoff,$stamp,$day]) {
        $expired=retention_fixture($root,'storage/backups/pk-dts-auto-'.$stamp.'-165959-aaaaaaaaaa.zip');
        $kept=retention_fixture($root,'storage/backups/pk-dts-auto-'.$stamp.'-170000-bbbbbbbbbb.zip');
        $daily=retention_fixture($root,'storage/audit/'.$day.'.json');
        $result=$service->apply_retention(new DateTimeImmutable($date,$timezone));
        retention_check($result['cutoff']===$cutoff,'Month ends must clamp to the last day of the target month.');
        retention_check(!file_exists($expired) && is_file($kept) && is_file($daily),
            'Month-end retention must preserve its exact boundary and complete log day.');
    }

    // A failed replacement backup must leave all retained snapshots in place.
    $locked=retention_fixture($root,'storage/backups/pk-dts-auto-20260101-000000-cccccccccc.zip');
    putenv('PK_BACKUP_PASSWORD');
    putenv('PK_BACKUP_SIGNING_KEY');
    retention_rejected(static function()use($service){$service->backup('auto');});
    retention_check(is_file($locked),'A failed new backup must not remove an older snapshot.');

    // Cleanup must use the same backup lock as backup creation and restore.
    $lock=fopen($service->directory().'/.backup.lock','c');
    flock($lock,LOCK_EX);
    try {retention_rejected(static function()use($service,$now){$service->apply_retention($now);});}
    finally {flock($lock,LOCK_UN);fclose($lock);}
    retention_check(is_file($locked),'A running backup or restore must prevent deletion.');

    $audit=retention_fixture($root,'storage/audit/2026-01-01.json');
    $lock=fopen($root.'/storage/audit/.audit.lock','c');
    flock($lock,LOCK_EX);
    try {
        $result=$service->apply_retention($now);
        retention_check(is_file($audit) && count($result['failed'])>0,
            'Audit retention must respect concurrent audit writes and report skipped cleanup.');
    } finally {flock($lock,LOCK_UN);fclose($lock);}
    $service->apply_retention($now);
    retention_check(!file_exists($audit),'Audit files must be pruned after their writer releases the lock.');

    $outsideFile=retention_fixture($outside,'protected.txt');
    $linked=$root.'/storage/backups/pk-dts-auto-20260101-000000-dddddddddd.zip';
    if (@symlink($outsideFile,$linked)) {
        $symlinks++;
        $service->apply_retention($now);
        retention_check(is_link($linked) && is_file($outsideFile),'Retention must never follow a linked backup.');
        unlink($linked);
    }
    retention_remove($root.'/storage/logs');
    $outsideLog=retention_fixture($outside,'log-2026-01-01.log');
    if (retention_directory_link($outside,$root.'/storage/logs')) {
        $symlinks++;
        retention_rejected(static function()use($service,$now){$service->apply_retention($now);});
        retention_check(is_file($outsideLog),'A redirected log directory must not delete external data.');
        retention_remove($root.'/storage/logs');
    }
    mkdir($root.'/storage/logs',0700);

    $dailyExpired=retention_fixture($root,'storage/audit/2026-01-01.json');
    $first=$service->apply_retention_daily($now);
    retention_check(is_array($first) && !file_exists($dailyExpired),'First daily cleanup must remove expired logs.');
    $sameDay=retention_fixture($root,'storage/audit/2026-01-02.json');
    retention_check($service->apply_retention_daily($now)===NULL && is_file($sameDay),
        'Daily cleanup must avoid another scan after a successful run.');
    $service->apply_retention_daily(new DateTimeImmutable('2026-10-14 17:00:00',$timezone));
    retention_check(!file_exists($sameDay),'Expired files must be picked up on the next daily run.');

    retention_fixture($root,'storage/.restore-in-progress');
    $maintenance=retention_fixture($root,'storage/audit/2026-01-03.json');
    retention_check($service->apply_retention_daily(new DateTimeImmutable('2026-10-15 17:00:00',$timezone))===NULL,
        'Automatic cleanup must stop during restore maintenance.');
    $directRetention=new Retention_service($root,$service->directory());
    retention_rejected(static function()use($directRetention,$now){$directRetention->cleanup($now);});
    retention_check(is_file($maintenance),'Restore maintenance must preserve files until recovery is complete.');
    unlink($root.'/storage/.restore-in-progress');

    // Run the actual CLI in a copied application rooted entirely in our fixture.
    mkdir($root.'/application/services/backup',0700,TRUE);
    mkdir($root.'/tools',0700,TRUE);
    foreach (['backup_service.php','retention_service.php'] as $source)
        copy(__DIR__.'/../application/services/backup/'.$source,$root.'/application/services/backup/'.$source);
    copy(__DIR__.'/../tools/backup.php',$root.'/tools/backup.php');
    copy(__DIR__.'/../application/bootstrap.php',$root.'/application/bootstrap.php');
    $cliExpired=retention_fixture($root,'storage/backups/pk-dts-auto-20000101-000000-eeeeeeeeee.zip');
    $process=proc_open([PHP_BINARY,$root.'/tools/backup.php','retention'],
        [1=>['pipe','w'],2=>['pipe','w']],$pipes,$root);
    retention_check(is_resource($process),'Retention CLI must start.');
    $output=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);
    fclose($pipes[1]);fclose($pipes[2]);
    $exit=proc_close($process);
    retention_check($exit===0 && !file_exists($cliExpired),
        'Retention CLI must remove expired files without DB or encryption secrets: '.$error.$output);

    // public/index.php invokes bootstrap BEFORE CodeIgniter defines BASEPATH.
    $process=proc_open([PHP_BINARY,'-r',
        'require '.var_export($root.'/application/bootstrap.php',TRUE).'; echo "bootstrap-completed";'],
        [1=>['pipe','w'],2=>['pipe','w']],$pipes,$root);
    retention_check(is_resource($process),'Standalone application bootstrap must start.');
    $output=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);
    fclose($pipes[1]);fclose($pipes[2]);
    $exit=proc_close($process);
    retention_check($exit===0 && $output==='bootstrap-completed',
        'Bootstrap must complete before CodeIgniter defines BASEPATH: '.$error.$output);

    // Exercise the real application bootstrap with only a disposable PK_ROOT.
    unlink($root.'/storage/.retention-day');
    $bootstrapExpired=retention_fixture($root,'storage/logs/log-2000-01-01.log');
    define('PK_ROOT',$root);
    require __DIR__.'/../application/bootstrap.php';
    retention_check(!file_exists($bootstrapExpired) && is_file($root.'/storage/.retention-day'),
        'Application bootstrap must automatically run daily retention.');

    echo "Three-month backup/log retention boundaries, locks, daily trigger and path safety passed.\n";
    if ($symlinks<2) echo "NOTE: ".(2-$symlinks)." symlink checks skipped because this host cannot create symbolic links.\n";
} finally {
    putenv($originalDirectory===FALSE?'PK_BACKUP_DIR':'PK_BACKUP_DIR='.$originalDirectory);
    putenv($originalPassword===FALSE?'PK_BACKUP_PASSWORD':'PK_BACKUP_PASSWORD='.$originalPassword);
    putenv($originalSigning===FALSE?'PK_BACKUP_SIGNING_KEY':'PK_BACKUP_SIGNING_KEY='.$originalSigning);
    retention_remove($root);
    retention_remove($outside);
}
