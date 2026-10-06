<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
require dirname(__DIR__).'/application/bootstrap.php';
use Pk\Core\{Database,Seed,Rules};
try {
    foreach(['pdo_mysql','fileinfo','mbstring','zip'] as $extension) if (!extension_loaded($extension)) throw new RuntimeException("PHP extension $extension is required.");
    if (!is_file(PK_ROOT.'/vendor/codeigniter/framework/system/core/CodeIgniter.php')) throw new RuntimeException('Run composer install first.');
    $username=getenv('PK_ADMIN_USERNAME') ?: 'admin';
    if (!preg_match('/^[a-zA-Z0-9_.-]{3,80}$/',$username)) throw new RuntimeException('Invalid PK_ADMIN_USERNAME.');
    $password=getenv('PK_ADMIN_PASSWORD') ?: bin2hex(random_bytes(12)); Rules::password($password,$password);
    $db=Database::connect();
    $exists=$db->one('SELECT COUNT(*) n FROM information_schema.tables WHERE table_schema=DATABASE()');
    if ((int)$exists['n']>0) throw new RuntimeException('Database is not empty. Installer will not alter existing data. Use a new empty database or an explicit reviewed migration.');
    foreach(explode(';',file_get_contents(PK_ROOT.'/database/schema.sql')) as $statement) if (trim($statement)!=='') $db->pdo->exec($statement);
    Seed::run($db,$username,$password);
    foreach(['files','conversions','logs'] as $folder) if (!is_dir(PK_ROOT.'/storage/'.$folder)) mkdir(PK_ROOT.'/storage/'.$folder,0700,true);
    echo "Installed schema version 1.\nUsername: $username\nInitial password (shown once): $password\nChange this password at first login. Create another approver before using request workflows.\n";
} catch(Throwable $e) { fwrite(STDERR,"Installation failed: {$e->getMessage()}\n"); exit(1); }
