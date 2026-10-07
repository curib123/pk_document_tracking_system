<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
require dirname(__DIR__).'/application/bootstrap.php';
use Pk\Core\{Database,Seed,Rules};
try {
    foreach(['mysqli','fileinfo','mbstring','zip'] as $extension) if (!extension_loaded($extension)) throw new RuntimeException("PHP extension $extension is required.");
    if (!is_file(PK_ROOT.'/vendor/codeigniter/framework/system/core/CodeIgniter.php')) throw new RuntimeException('Run composer install first.');
    $username='admin';
    $testPassword=getenv('PK_TEST_DB')==='1' ? (string)(getenv('PK_ADMIN_PASSWORD') ?: '') : '';
    $password=$testPassword!=='' ? $testPassword : bin2hex(random_bytes(12));
    Rules::password($password,$password);
    $db=Database::connect();
    $databaseName=(string)$db->builder->database;
    $exists=$db->first($db->builder->reset_query()->select('COUNT(*) AS n',false)->where('table_schema',$databaseName)->get('information_schema.tables'));
    if ((int)$exists['n']>0) throw new RuntimeException('Database is not empty. Installer will not alter existing data. Use a new empty database or an explicit reviewed migration.');
    foreach(explode(';',file_get_contents(PK_ROOT.'/database/schema.sql')) as $statement) if (trim($statement)!=='') $db->query($statement);
    Seed::run($db,$username,$password);
    foreach(['files','conversions','logs'] as $folder) if (!is_dir(PK_ROOT.'/storage/'.$folder)) mkdir(PK_ROOT.'/storage/'.$folder,0700,true);
    echo "Installed schema version 1.\nUsername: $username\nInitial password (shown once): $password\nChange this password at first login. Create another approver before using request workflows.\n";
} catch(Throwable $e) { fwrite(STDERR,"Installation failed: {$e->getMessage()}\n"); exit(1); }
