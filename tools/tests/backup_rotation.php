<?php
/** Exercise backup rotation and restore with disposable database and storage fixtures. */
if (PHP_SAPI !== 'cli') exit(1);

$project = dirname(__DIR__, 2);
$suffix = bin2hex(random_bytes(5));
$database = 'pkdts_backup_' . $suffix . '_test';
$root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pkdts-backup-' . $suffix;
define('PK_ROOT', $root);
define('BASEPATH', $project . '/');

require $project . '/application/services/backup/backup_service.php';
putenv('PK_TEST_DB=1');
putenv('DB_DATABASE=' . $database);
putenv('PK_BACKUP_DIR=' . $root . '/storage/backups');
putenv('PK_BACKUP_PASSWORD=' . bin2hex(random_bytes(16)));
putenv('PK_BACKUP_SIGNING_KEY=' . bin2hex(random_bytes(32)));
require $project . '/application/config/database.php';

function check_backup(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
    echo "PASS $message\n";
}

$config = $db['default'];
$server = new mysqli($config['hostname'], $config['username'], $config['password'], '', $config['port']);
$server->query("CREATE DATABASE `$database`");
try {
    $server->select_db($database);
    $server->query('CREATE TABLE fixture (id INT PRIMARY KEY, value VARCHAR(30)) ENGINE=InnoDB');
    $server->query("INSERT INTO fixture VALUES (1, 'original')");
    $inputs = [
        'application/code.php' => '<?php return true;',
        'storage/documents/document' => 'original document',
        'storage/audit/audit.json' => '{}',
        'storage/documentation-review/draft.pptx' => 'review copy',
        'storage/visio-repair/draft.vsdx' => 'working diagram',
        'storage/previews/preview.pdf' => 'cached preview'
    ];
    foreach ($inputs as $path => $content) {
        mkdir(dirname($root . '/' . $path), 0700, TRUE);
        file_put_contents($root . '/' . $path, $content);
    }

    $service = new Backup_service($root);
    $automatic = [];
    for ($day = 1; $day <= 3; $day++) {
        $result = $service->backup('auto');
        $name = 'pk-dts-auto-2020010' . $day . '-170000-0123456789.zip';
        $destination = $service->directory() . '/' . $name;
        if (!rename($service->resolve($result['name']), $destination)) {
            throw new RuntimeException('Could not date the fixture archive.');
        }
        touch($destination, time() - $day);
        $automatic[] = $name;
    }

    $manual = $service->backup('manual');
    $manualManifest = $service->verify($service->resolve($manual['name']));
    $manualFiles = array_column($manualManifest['files'], 'path');
    $localFiles = ['files/storage/documentation-review/draft.pptx', 'files/storage/visio-repair/draft.vsdx',
        'files/storage/previews/preview.pdf'];
    check_backup(!array_diff($localFiles, $manualFiles),
        'manual backups still include local work files');
    $service->apply_retention();
    check_backup(count(glob($service->directory() . '/pk-dts-auto-*.zip')) === 3,
        'calendar cleanup preserves the three automatic archives regardless of age');

    $dump = getenv('PK_MYSQLDUMP');
    putenv('PK_MYSQLDUMP=' . $root . '/missing-mysqldump');
    $failed = FALSE;
    try {
        $service->backup('auto');
    } catch (DomainException $failure) {
        $failed = TRUE;
    } finally {
        putenv($dump === FALSE ? 'PK_MYSQLDUMP' : 'PK_MYSQLDUMP=' . $dump);
    }
    check_backup($failed && count(glob($service->directory() . '/pk-dts-auto-*.zip')) === 3 &&
        is_file($service->resolve($automatic[0])), 'failed automatic backup preserves all existing archives');

    $latest = $service->backup('auto');
    check_backup($latest['retention']['deleted']['backups'] === 1 && !$latest['retention']['failed'] &&
        !file_exists($service->directory() . '/' . $automatic[0]) &&
        is_file($service->resolve($automatic[1])) && is_file($service->resolve($automatic[2])) &&
        is_file($service->resolve($manual['name'])), 'successful fourth automatic backup deletes only the oldest');
    $manifest = $service->verify($service->resolve($latest['name']));
    $files = array_column($manifest['files'], 'path');
    $expected = ['database.sql', 'files/application/code.php', 'files/storage/audit/audit.json',
        'files/storage/documents/document'];
    check_backup(!array_diff($expected, $files) && !array_intersect($localFiles, $files),
        'automatic archive contains operational data and omits the three work folders');

    $server->query("UPDATE fixture SET value = 'changed' WHERE id = 1");
    file_put_contents($root . '/storage/documents/document', 'changed document');
    $restore = $service->restore($latest['name']);
    check_backup($server->query('SELECT value FROM fixture WHERE id = 1')->fetch_row()[0] === 'original' &&
        file_get_contents($root . '/storage/documents/document') === 'original document' &&
        is_file($service->resolve($restore['recovery'])), 'smaller archive restores the database and original document');
} finally {
    $server->query("DROP DATABASE `$database`");
    if (is_dir($root)) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $file) {
            if ($file->isDir()) rmdir($file->getPathname());
            else unlink($file->getPathname());
        }
        rmdir($root);
    }
}
