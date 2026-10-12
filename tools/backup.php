<?php
/**
 * Run on the application server, never through HTTP.
 *
 * php tools/backup.php auto
 * php tools/backup.php manual
 * php tools/backup.php list
 * php tools/backup.php verify pk-dts-auto-YYYYMMDD-HHMMSS-xxxxxxxxxx.zip
 * php tools/backup.php restore pk-dts-auto-YYYYMMDD-HHMMSS-xxxxxxxxxx.zip
 *
 * Set PK_BACKUP_PASSWORD, PK_BACKUP_SIGNING_KEY and DB_* in the scheduler
 * process environment. Check docs/backup-restore.md before enabling 5 PM.
 */
if (PHP_SAPI!=='cli') {http_response_code(403);exit("CLI only\n");}
define('BASEPATH', __DIR__.'/../');
define('PK_ROOT', dirname(__DIR__));
require_once PK_ROOT.'/application/services/backup/backup_service.php';
date_default_timezone_set('Asia/Manila');
$command=$argv[1]??'';
try {
    $service=new Backup_service(PK_ROOT);
    if ($command==='auto' || $command==='manual') {
        $result=$service->backup($command);
        echo "Created encrypted signed backup: ".$result['name']."\n";
    } elseif ($command==='list') {
        foreach ($service->list_backups() as $item)
            echo $item['name']."\t".$item['size']." bytes\n";
    } elseif ($command==='verify') {
        $path=$service->resolve((string)($argv[2]??''));
        $manifest=$service->verify($path);
        echo "Verified ".$manifest['database']." archive with ".count($manifest['files'])." files.\n";
    } elseif ($command==='restore') {
        if (($argv[3]??'')!=='--confirm') {
            fwrite(STDERR,"Restore requires an explicit --confirm flag and a maintenance window.\n");
            exit(2);
        }
        $result=$service->restore((string)($argv[2]??''));
        echo "Restored data: ".$result['restored']."\n".
             "Pre-restore recovery snapshot: ".$result['recovery']."\n";
    } else {
        fwrite(STDERR,"Usage: php tools/backup.php auto|manual|list|verify <archive>|restore <archive> --confirm\n");
        exit(2);
    }
    exit(0);
} catch (Throwable $error) {
    // Never print passwords, dumped SQL or backup contents.
    fwrite(STDERR,"Backup/restore failed: ".$error->getMessage()."\n");
    exit(1);
}
