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
    $included = [
        'application/code.php' => '<?php return true;',
        'public/assets/images/logo.png' => 'application logo',
        'docs/system-documentation/PK_DCS_User_Guide_Step_by_Step.pptx' => 'published guide',
        'docs/system-documentation/source/process.json' => '{}',
        'storage/documents/document.png' => 'original document',
        'storage/documents/previous-version.png' => 'previous document version',
        'storage/audit/audit.json' => '{}',
        'storage/private-state.json' => '{}'
    ];
    $excluded = [
        'storage/documentation-review/draft.pptx' => 'review copy',
        'storage/visio-repair/draft.vsdx' => 'working diagram',
        'storage/previews/preview.pdf' => 'cached preview',
        'docs/system-documentation/PK_DTS_Documentation.zip' => 'generated documentation bundle',
        'docs/system-documentation/previews/diagram.png' => 'rendered diagram preview',
        'docs/system-documentation/source/screenshots/requests.png' => 'documentation source screenshot',
        'tools/documentation/screenshots/guide/signin.png' => 'documentation capture',
        'storage/dashboard-test.PNG' => 'local test screenshot'
    ];
    foreach ($included + $excluded as $path => $content) {
        $directory = dirname($root . '/' . $path);
        if (!is_dir($directory)) mkdir($directory, 0700, TRUE);
        file_put_contents($root . '/' . $path, $content);
    }
    $archivePath = static fn(string $path): string => 'files/' . $path;
    $expected = array_merge(['database.sql'], array_map($archivePath, array_keys($included)));
    $localFiles = array_map($archivePath, array_keys($excluded));

    $service = new Backup_service($root);
    $auditStage = $service->directory() . '/audit-fixture';
    mkdir($auditStage, 0700);
    $snapshot = new ReflectionMethod($service, 'snapshotFiles');
    $snapshot->setAccessible(TRUE);
    $auditFiles = $snapshot->invoke($service, 'manual', $auditStage);
    $auditSource = $root . '/storage/audit/audit.json';
    file_put_contents($auditSource, '{"after":true}');
    check_backup(file_get_contents($auditFiles['files/storage/audit/audit.json']) === '{}',
        'a later audit write leaves the staged contents intact');
    file_put_contents($auditSource, '{}');

    $kinds = ['auto' => 'automatic', 'manual' => 'manual'];
    $recovery = $service->backup('recovery');
    $fixtureArchive = $service->resolve($recovery['name']);
    $existing = [];
    foreach (array_keys($kinds) as $kind) {
        for ($day = 1; $day <= 3; $day++) {
            $name = 'pk-dts-' . $kind . '-2020010' . $day . '-170000-0123456789.zip';
            $destination = $service->directory() . '/' . $name;
            if (!copy($fixtureArchive, $destination)) {
                throw new RuntimeException('Could not seed an existing archive.');
            }
            touch($destination, time() - $day);
            $existing[$kind][] = $name;
        }
    }

    $importDate = new DateTimeImmutable('now', new DateTimeZone('Asia/Manila'));
    $import = 'pk-dts-manual-import-' . $importDate->format('Ymd-His') . '-0123456789.zip';
    if (!copy($fixtureArchive, $service->directory() . '/' . $import)) {
        throw new RuntimeException('Could not seed an imported archive.');
    }

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

    $latest = [];
    foreach ($kinds as $kind => $label) {
        [$oldest, $middle, $previous] = $existing[$kind];
        $rotationCases = [
            "successful $label backup trims existing archives to the newest two of its type" => [$oldest, $middle],
            "successful third $label backup removes only the oldest of its type" => [$previous],
        ];
        foreach ($rotationCases as $message => $expectedRemoved) {
            $result = $service->backup($kind);
            $latest[$kind] = $result['name'];
            $after = array_column($service->list_backups(), 'name');
            $removed = array_values(array_diff($before, $after));
            sort($removed);
            check_backup($result['retention']['deleted']['backups'] === count($expectedRemoved) &&
                !$result['retention']['failed'] && $removed === $expectedRemoved &&
                array_values(array_diff($after, $before)) === [$result['name']], $message);
            $before = $after;
        }

        $manifest = $service->verify($service->resolve($latest[$kind]));
        $files = array_column($manifest['files'], 'path');
        check_backup(!array_diff($expected, $files) && !array_intersect($localFiles, $files),
            "$label archive keeps operational data and published guides and omits generated work files");
    }

    $server->query("UPDATE fixture SET value = 'changed' WHERE id = 1");
    $document = $root . '/storage/documents/document.png';
    file_put_contents($document, 'changed document');
    file_put_contents($auditSource, '{"after":true}');
    $restore = $service->restore($latest['manual']);
    check_backup($server->query('SELECT value FROM fixture WHERE id = 1')->fetch_row()[0] === 'original' &&
        file_get_contents($document) === 'original document' && file_get_contents($auditSource) === '{}',
        'smaller manual archive restores the database, original document, and audit history');
    $recovery = $service->verify($service->resolve($restore['recovery']));
    check_backup(!array_diff(array_merge($expected, $localFiles), array_column($recovery['files'], 'path')),
        'pre-restore recovery snapshot includes operational data and local work files');
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
