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
    $kinds = ['auto' => 'automatic', 'manual' => 'manual'];
    $oldest = [];
    foreach ($kinds as $kind => $label) {
        for ($day = 1; $day <= 3; $day++) {
            $result = $service->backup($kind);
            $name = 'pk-dts-' . $kind . '-2020010' . $day . '-170000-0123456789.zip';
            $destination = $service->directory() . '/' . $name;
            if (!rename($service->resolve($result['name']), $destination)) {
                throw new RuntimeException('Could not date the fixture archive.');
            }
            touch($destination, time() - $day);
            $oldest[$kind] ??= $name;
        }
    }

    $importDate = new DateTimeImmutable('now', new DateTimeZone('Asia/Manila'));
    $import = 'pk-dts-manual-import-' . $importDate->format('Ymd-His') . '-0123456789.zip';
    if (!copy($service->resolve($oldest['manual']), $service->directory() . '/' . $import)) {
        throw new RuntimeException('Could not seed an imported archive.');
    }
    $recovery = $service->backup('recovery');
    $localFiles = ['files/storage/documentation-review/draft.pptx', 'files/storage/visio-repair/draft.vsdx',
        'files/storage/previews/preview.pdf'];
    $recoveryManifest = $service->verify($service->resolve($recovery['name']));
    check_backup(!array_diff($localFiles, array_column($recoveryManifest['files'], 'path')),
        'recovery snapshots include local work files');

    $before = array_column($service->list_backups(), 'name');
    $service->apply_retention();
    check_backup(count($before) === 8 && $before === array_column($service->list_backups(), 'name'),
        'calendar cleanup preserves the automatic and manual archives regardless of age');

    $dump = getenv('PK_MYSQLDUMP');
    putenv('PK_MYSQLDUMP=' . $root . '/missing-mysqldump');
    try {
        foreach ($kinds as $kind => $label) {
            $failed = FALSE;
            try {
                $service->backup($kind);
            } catch (DomainException $failure) {
                $failed = TRUE;
            }
            check_backup($failed && $before === array_column($service->list_backups(), 'name'),
                "failed $label backup preserves all existing archives");
        }
    } finally {
        putenv($dump === FALSE ? 'PK_MYSQLDUMP' : 'PK_MYSQLDUMP=' . $dump);
    }

    $expected = ['database.sql', 'files/application/code.php', 'files/storage/audit/audit.json',
        'files/storage/documents/document'];
    $latest = [];
    foreach ($kinds as $kind => $label) {
        $result = $service->backup($kind);
        $latest[$kind] = $result['name'];
        $after = array_column($service->list_backups(), 'name');
        check_backup($result['retention']['deleted']['backups'] === 1 && !$result['retention']['failed'] &&
            array_values(array_diff($before, $after)) === [$oldest[$kind]] &&
            array_values(array_diff($after, $before)) === [$result['name']],
            "successful fourth $label backup deletes only the oldest of its type");
        $before = $after;

        $manifest = $service->verify($service->resolve($result['name']));
        $files = array_column($manifest['files'], 'path');
        check_backup(!array_diff($expected, $files) && !array_intersect($localFiles, $files),
            "$label archive contains operational data and omits the three work folders");
    }

    $server->query("UPDATE fixture SET value = 'changed' WHERE id = 1");
    file_put_contents($root . '/storage/documents/document', 'changed document');
    $restore = $service->restore($latest['manual']);
    check_backup($server->query('SELECT value FROM fixture WHERE id = 1')->fetch_row()[0] === 'original' &&
        file_get_contents($root . '/storage/documents/document') === 'original document' &&
        is_file($service->resolve($restore['recovery'])), 'smaller manual archive restores the database and original document');
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
