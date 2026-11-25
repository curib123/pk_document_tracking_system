<?php
/** Exercises the daily cleanup with a disposable database and storage directory. */
if (PHP_SAPI !== 'cli') exit(1);

$project = dirname(__DIR__, 2);
$suffix = bin2hex(random_bytes(5));
$name = "orphan_files_{$suffix}_test";
$root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . "pkdts-orphans-$suffix";
define('PK_ROOT', $root);
define('APPPATH', $project . '/application/');
define('BASEPATH', $project . '/vendor/codeigniter/framework/system/');
define('ENVIRONMENT', 'testing');

require BASEPATH . 'core/Common.php';
require BASEPATH . 'database/DB.php';
require APPPATH . 'services/files/file_service.php';

function &get_instance() { return $GLOBALS['ci']; }
function verify_cleanup(bool $condition, string $message): void
{
    if (!$condition)
        throw new RuntimeException($message);
    echo "PASS $message\n";
}

// Retired-file reconciliation is independent of orphan detection.
class Orphan_cleanup_fixture extends File_service
{
    public function reconcile_retired(): int { return 0; }
}

putenv('PK_TEST_DB=1');
putenv('DB_DATABASE=' . $name);
require APPPATH . 'config/database.php';
$config = $db['default'];
$server = new mysqli($config['hostname'], $config['username'], $config['password'], '', $config['port']);
$server->query("CREATE DATABASE `$name`");

try {
    require APPPATH . 'bootstrap.php';
    $ci = (object) ['db' => DB($config, TRUE)];
    $ci->db->query('CREATE TABLE files (storage_name VARCHAR(100) PRIMARY KEY, status VARCHAR(20)) ENGINE=InnoDB');

    $documents = "$root/storage/documents";
    $previews = "$root/storage/previews";
    mkdir($previews, 0700);

    $registered = str_repeat('a', 64);
    $orphan = str_repeat('b', 64);
    $fresh = str_repeat('c', 64);
    $previewOnly = str_repeat('d', 64);
    $subdir = "$documents/" . str_repeat('e', 64);
    mkdir($subdir);
    $ci->db->insert('files', ['storage_name' => $registered, 'status' => 'pending']);

    $old = ["$documents/$registered", "$previews/$registered.pdf", "$documents/$orphan",
        "$previews/$orphan.pdf", "$previews/$previewOnly.pdf", "$documents/readme.txt"];
    foreach ($old as $path) {
        file_put_contents($path, 'fixture');
        touch($path, time() - 172800);
    }
    file_put_contents("$documents/$fresh", 'recent upload');

    $service = new Orphan_cleanup_fixture();
    verify_cleanup($service->cleanup_daily() && is_file("$documents/$orphan"), 'test mode preserves shared storage');
    putenv('PK_TEST_DB');

    $ci->db->query('RENAME TABLE files TO offline_files');
    verify_cleanup(!$service->cleanup_daily() && is_file("$documents/$orphan"),
        'database read failure preserves bytes and leaves the run retryable');
    $ci->db->query('RENAME TABLE offline_files TO files');

    verify_cleanup($service->cleanup_orphans_daily(), 'orphan-only cleanup retries after the database recovers');
    verify_cleanup(!file_exists("$documents/$orphan") && !file_exists("$previews/$orphan.pdf") &&
        !file_exists("$previews/$previewOnly.pdf"), 'old unregistered uploads and previews are removed');
    verify_cleanup(is_file("$documents/$registered") && is_file("$previews/$registered.pdf"),
        'a registered pending upload and its preview are preserved');
    verify_cleanup(is_file("$documents/$fresh") && is_file("$documents/readme.txt") && is_dir($subdir),
        'recent uploads, unrelated files, and directories are preserved');
    file_put_contents("$documents/$orphan", 'later fixture');
    touch("$documents/$orphan", time() - 172800);
    verify_cleanup($service->cleanup_daily() && is_file("$documents/$orphan"), 'a successful run is recorded for the day');
} finally {
    if (isset($ci)) $ci->db->close();
    $server->query("DROP DATABASE `$name`");
    foreach (["$root/storage/documents", "$root/storage/previews", "$root/storage/logs",
        "$root/storage/sessions", "$root/storage"] as $directory) {
        if (!is_dir($directory)) continue;
        foreach (new FilesystemIterator($directory) as $entry) {
            if ($entry->isDir()) rmdir($entry->getPathname());
            else unlink($entry->getPathname());
        }
        rmdir($directory);
    }
    if (is_dir($root)) rmdir($root);
}
