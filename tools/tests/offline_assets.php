<?php
// Regression checks for an internet-independent office LAN frontend.
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$errors = [];
$required = [
    'public/assets/css/vendor/bootstrap.min.css',
    'public/assets/css/vendor/fontawesome.min.css',
    'public/assets/css/vendor/poppins.css',
    'public/assets/js/vendor/bootstrap.bundle.min.js',
    'public/assets/js/vendor/chart.umd.min.js',
    'public/assets/fonts/fa-solid-900.woff2',
    'public/assets/fonts/fa-regular-400.woff2',
    'public/assets/fonts/fa-brands-400.woff2',
    'public/assets/fonts/fa-v4compatibility.woff2',
    'public/assets/fonts/Poppins-Regular.ttf',
    'public/assets/fonts/Poppins-Medium.ttf',
    'public/assets/fonts/Poppins-SemiBold.ttf',
    'public/assets/fonts/Poppins-Bold.ttf',
    'public/assets/fonts/Poppins-ExtraBold.ttf',
];
foreach ($required as $path) {
    if (!is_file($root . '/' . $path) || filesize($root . '/' . $path) === 0) {
        $errors[] = 'Missing or empty local asset: ' . $path;
    }
}

foreach ([
    'application/view/layout/header.php',
    'application/view/layout/footer.php',
    'application/view/pages/authentication/index.php',
] as $path) {
    $source = file_get_contents($root . '/' . $path);
    if (preg_match('~https?://(?:cdn\\.jsdelivr\\.net|cdnjs\\.cloudflare\\.com|fonts\\.googleapis\\.com|fonts\\.gstatic\\.com)~i', $source)) {
        $errors[] = 'External runtime dependency in: ' . $path;
    }
}

$fontCss = file_get_contents($root . '/public/assets/css/vendor/fontawesome.min.css');
if (strpos($fontCss, '../webfonts/') !== false) {
    $errors[] = 'Font Awesome still uses non-existent webfonts path';
}
$worker = file_get_contents($root . '/public/service-worker.js');
if (strpos($worker, 'NETWORK_TIMEOUT_MS') !== false || strpos($worker, 'navigator.onLine') !== false) {
    $errors[] = 'Service worker still treats a slow LAN/WAN status as offline';
}

if ($errors) {
    fwrite(STDERR, implode(PHP_EOL, $errors) . PHP_EOL);
    exit(1);
}
echo "Office LAN frontend dependencies: OK" . PHP_EOL;
