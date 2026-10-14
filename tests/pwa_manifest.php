<?php
declare(strict_types=1);
foreach (['/' => '/manifest.php', '/pk_document_tracking_system/public/' => '/pk_document_tracking_system/public/manifest.php'] as $scope => $script) {
    $_SERVER['SCRIPT_NAME'] = $script;
    ob_start(); require dirname(__DIR__) . '/public/manifest.php'; $manifest = json_decode(ob_get_clean(), TRUE);
    if ($manifest['id'] !== $scope . 'pk-document-control' || $manifest['start_url'] !== $scope . 'dashboard' ||
        $manifest['scope'] !== $scope || $manifest['display'] !== 'standalone')
        throw new RuntimeException('Manifest identity and launch scope must support both root and subdirectory deployments.');
    foreach ($manifest['icons'] as $icon) {
        $size = getimagesize(dirname(__DIR__) . '/public/' . substr($icon['src'], strlen($scope)));
        if ($icon['sizes'] !== $size[0] . 'x' . $size[1] || $size[0] !== $size[1])
            throw new RuntimeException('PWA icon sizes must match the actual square PNG assets.');
    }
}
echo "Stable PWA identity, root/subdirectory scope and installation icons passed.\n";
