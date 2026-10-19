<?php
require_once __DIR__ . '/../application/bootstrap.php';
$_SERVER['HTTP_HOST'] = 'localhost';
foreach (['/pk_document_tracking_system/index.php', '/pk_document_tracking_system/public/index.php'] as $script) {
    $_SERVER['SCRIPT_NAME'] = $script;
    if (pk_base_url() !== 'http://localhost/pk_document_tracking_system/')
        throw new RuntimeException('Generated application URLs must omit public for both entry points.');
}
echo "Canonical application base URL passed.\n";
