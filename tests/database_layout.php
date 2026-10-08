<?php
declare(strict_types=1);
$root = dirname(__DIR__);
$checks = [];
foreach ([
    'schema.sql', 'seed.sql', 'install.php', 'migrate.php', 'seed.php',
    'maintenance.php', 'sync_document_visibility.php', 'sync_request_catalog.php'
] as $filename) {
    $checks['database/' . $filename . ' present'] = is_file($root . '/database/' . $filename);
}
$checks['bin directory removed'] = !is_dir($root . '/bin');
$checks['old company SQL export removed'] = !is_file($root . '/database/pk_dts.sql');
$schema = file_get_contents($root . '/database/schema.sql');
$seed = file_get_contents($root . '/database/seed.sql');
$install = file_get_contents($root . '/database/install.php');
$migrate = file_get_contents($root . '/database/migrate.php');
if (!is_string($schema) || !is_string($seed) || !is_string($install) || !is_string($migrate)) {
    fwrite(STDERR, "Missing schema/seed/installer/migrator.\n");
    exit(1);
}
preg_match_all('/^CREATE TABLE\s+\x60?[a-z0-9_]+\x60?\s*\(/mi', $schema, $tableMatches);
$checks['schema defines exactly 29 tables'] = count($tableMatches[0]) === 29;
$checks['schema excludes account inserts'] = stripos($schema, 'INSERT INTO') === false;
$checks['schema retains unique occupied location'] = str_contains($schema, 'UNIQUE KEY ' . chr(96) . 'location_id');
$checks['audit history remains file backed'] = !str_contains($schema, 'CREATE TABLE ' . chr(96) . 'audit_logs');
$checks['seed marks schema version 7'] = str_contains($seed, 'schema_migrations (version) VALUES (7)');
$checks['seed contains no stored user records'] = !preg_match('/INSERT\s+INTO\s+\x60?(?:users|requests|hardcopy_documents)\x60?\b/i', $seed);
$checks['installer uses local schema.sql'] = str_contains($install, '/database/schema.sql');
$checks['installer executes seed.sql'] = str_contains($install, '/database/seed.sql');
$checks['installer refuses non-empty database'] = str_contains($install, 'Database is not empty.');
$checks['admin password generated at runtime'] = str_contains($install, 'random_bytes(12)');
$checks['v1 migrator reaches version 7'] = str_contains($migrate, 'migrate_direct_disposal_v7($db)') && str_contains($migrate, 'VALUES(7)');
foreach ([
    '.github/workflows/ci.yml',
    '.github/workflows/document-visibility.yml',
    '.github/workflows/frontend-assets.yml',
    'README.md'
] as $path) {
    $contents = file_get_contents($root . '/' . $path);
    $checks[$path . ' references relocated scripts'] =
        is_string($contents)
        && !str_contains($contents, 'bin/install.php')
        && !str_contains($contents, 'bin/migrate.php')
        && !str_contains($contents, 'bin\install.php')
        && !str_contains($contents, 'bin\seed.php');
}
$failures = 0;
foreach ($checks as $name => $ok) {
    echo ($ok ? 'PASS ' : 'FAIL ') . $name . "\n";
    if (!$ok) ++$failures;
}
echo count($checks) . " database layout checks; $failures failures\n";
exit($failures ? 1 : 0);
