<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
require dirname(__DIR__).'/application/bootstrap.php';
use Pk\Core\{Database,Context};
try {
    $db=Database::connect(); $ctx=new Context($db);
    $count=$db->transaction(function() use($db,$ctx) {
        $grants=$db->all("SELECT * FROM access_grants WHERE status='access_granted' AND expires_at<NOW() FOR UPDATE");
        foreach($grants as $grant) {
            $db->update('access_grants',(int)$grant['id'],['status'=>'expired']);
            $ctx->audit('access','expired',(int)$grant['id'],$grant,['status'=>'expired'],'Scheduled expiry',(int)$grant['request_id']);
            $ctx->notify((int)$grant['user_id'],'Document access expired','Your approved access period ended.',(int)$grant['request_id']);
        }
        $db->query('DELETE FROM login_attempts WHERE window_started < DATE_SUB(NOW(),INTERVAL 2 DAY)');
        return count($grants);
    });
    $root=PK_ROOT.'/storage/conversions';
    foreach(glob($root.'/*',GLOB_ONLYDIR) ?: [] as $dir) {
        if (is_link($dir) || filemtime($dir)>time()-86400) continue;
        $iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
        foreach($iterator as $file) { if ($file->isLink() || $file->isFile()) unlink($file->getPathname()); elseif ($file->isDir()) rmdir($file->getPathname()); }
        rmdir($dir);
    }
    echo "$count access grants expired. Old conversion workspaces cleaned.\n";
} catch(Throwable $e) { fwrite(STDERR,"Maintenance failed: {$e->getMessage()}\n"); exit(1); }
