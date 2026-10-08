<?php
declare(strict_types=1);

$root = dirname(__DIR__);
function asset_check(bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
    echo "PASS $message\n";
}
$package = json_decode(file_get_contents($root . '/package.json'), true, 512, JSON_THROW_ON_ERROR);
asset_check(($package['devDependencies']['tailwindcss'] ?? null) === '4.3.1', 'Tailwind is pinned to the 4.3.1 patch release');
$lock = json_decode(file_get_contents($root . '/package-lock.json'), true, 512, JSON_THROW_ON_ERROR);
asset_check(($lock['packages']['node_modules/tailwindcss']['version'] ?? null) === '4.3.1', 'The lockfile resolves the same Tailwind version');

$partial = $root . '/application/views/components/assets/styles.php';
asset_check(is_file($partial), 'External CSS links belong to a shared PHP view');
$renderAssets = static function (bool $enabled) use ($partial): string {
    $pkStyling = ['enabled' => $enabled];
    ob_start(); require $partial; return (string)ob_get_clean();
};
$html = $renderAssets(true);
asset_check(substr_count($html, 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css') === 1, 'Font Awesome 6.7.2 is included once');
asset_check(str_contains($html, 'family=Poppins:wght@400;500;600;700'), 'Poppins loads exactly the requested weights');
asset_check(str_contains($html, 'display=swap'), 'Poppins uses a non-blocking font display strategy');
asset_check(substr_count($html, 'rel="preconnect"') === 2 && str_contains($html, 'crossorigin'), 'Google font connections are declared in the view');
asset_check(trim($renderAssets(false)) === '', 'The master styling switch disables external font and icon requests');
asset_check(!str_contains($html, '<script') && !str_contains($html, '<style'), 'No CDN JavaScript compiler or inline styling was added');

$icons = require $root . '/application/config/icons.php';
asset_check(count($icons) >= 26, 'All shared workspace icon names are mapped');
foreach ($icons as $iconName => $className) {
    ob_start(); require $root . '/application/views/components/workspace/icon.php'; $icon = (string)ob_get_clean();
    asset_check(str_contains($icon, '<i ') && str_contains($icon, $className) && str_contains($icon, 'aria-hidden="true"'), 'View renders a decorative Font Awesome icon: ' . $iconName);
}
$iconName = '<script>bad()</script>';
ob_start(); require $root . '/application/views/components/workspace/icon.php'; $fallback = (string)ob_get_clean();
asset_check(!str_contains($fallback, '<script>') && str_contains($fallback, 'fa-file-lines'), 'Unknown icon names safely fall back to a predefined icon');

$security = file_get_contents($root . '/application/libraries/support/Security.php');
asset_check(str_contains($security, "style-src 'self' https://cdnjs.cloudflare.com https://fonts.googleapis.com"), 'CSP permits only the requested external stylesheet providers');
asset_check(str_contains($security, "font-src 'self' https://cdnjs.cloudflare.com https://fonts.gstatic.com"), 'CSP explicitly allows the required font providers');
asset_check(str_contains($security, "script-src 'self'") && !str_contains($security, 'unsafe-inline') && !str_contains($security, 'unsafe-eval'), 'Script and inline security restrictions remain intact');

$js = file_get_contents($root . '/public/assets/js/workspace-icons.js');
asset_check(str_contains($js, 'cloneView') && !str_contains($js, 'createElement') && !str_contains($js, 'innerHTML'), 'JavaScript binds existing icon views instead of creating HTML');
asset_check(!str_contains(file_get_contents($root . '/public/assets/js/workspace.js'), "querySelector('svg')"), 'Icon updates no longer depend on the old SVG renderer');
echo "Frontend dependency, view ownership and security checks passed.\n";
