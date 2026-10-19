<?php
// Public installation metadata; no session, document or database access.
$scope = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/manifest.php')), '/') . '/';
if ($scope === './')
    $scope = '/';
$identity = $scope . 'pk-document-control';
$scope = preg_replace('~/public/$~', '/', $scope);
header('Content-Type: application/manifest+json; charset=utf-8');
header('Cache-Control: no-cache');
echo json_encode([
    'id' => $identity,
    'name' => 'PK Document Control',
    'short_name' => 'PK DTS',
    'description' => 'Manage your documents, requests and approval tasks.',
    'start_url' => $scope . 'dashboard',
    'scope' => $scope,
    'display' => 'standalone',
    'background_color' => '#f3f5f8',
    'theme_color' => '#cb2035',
    'icons' => array_map(static function ($size) use ($scope) {
        return [
            'src' => $scope . 'assets/images/pwa-' . $size . '.png',
            'sizes' => $size . 'x' . $size,
            'type' => 'image/png',
            'purpose' => 'any maskable'
        ];
    }, [192, 512]),
    'prefer_related_applications' => FALSE,
    'related_applications' => [
        [
            'platform' => 'webapp',
            'url' => $scope . 'manifest.php',
            'id' => $identity
        ]
    ]
], JSON_UNESCAPED_SLASHES);
