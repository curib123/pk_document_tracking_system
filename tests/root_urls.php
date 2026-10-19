<?php
$root = sys_get_temp_dir() . '/pk-url-test-' . bin2hex(random_bytes(6));
mkdir($root, 0700, TRUE);
define('PK_ROOT', $root);
require_once __DIR__ . '/../application/bootstrap.php';
register_shutdown_function(static function () use ($root) {
    // Only the owned fixture root is removed; never run retention on app data.
    $resolved = realpath($root);
    if (!$resolved || dirname($resolved) !== realpath(sys_get_temp_dir()) || strpos(basename($resolved), 'pk-url-test-') !== 0)
        throw new RuntimeException('Refusing cleanup outside the owned URL fixture.');
    $entries = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($entries as $entry) $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
    rmdir($root);
});
$_SERVER['HTTP_HOST'] = 'localhost';
foreach (['/pk_document_tracking_system/index.php', '/pk_document_tracking_system/public/index.php'] as $script) {
    $_SERVER['SCRIPT_NAME'] = $script;
    if (pk_base_url() !== 'http://localhost/pk_document_tracking_system/')
        throw new RuntimeException('Generated application URLs must omit public for both entry points.');
}
echo "Canonical application base URL passed.\n";
