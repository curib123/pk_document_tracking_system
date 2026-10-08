<?php
// Both PHP-rendered navigation and JS-cloned controls share this icon component.
$pkIconMap ??= require dirname(__DIR__, 3) . '/config/icons.php';
foreach ($pkIconMap as $iconName => $pkIconClass):
?>
<template id="ws-icon-<?= htmlspecialchars($iconName, ENT_QUOTES, 'UTF-8') ?>"><?php require __DIR__ . '/icon.php'; ?></template>
<?php endforeach; ?>
