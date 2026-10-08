<?php
// Only an allowlisted icon class reaches HTML; unknown names use the file icon.
$pkIconMap ??= require dirname(__DIR__, 3) . '/config/icons.php';
$pkIconClass = $pkIconMap[$iconName ?? 'file'] ?? $pkIconMap['file'];
?>
<i class="ws-icon <?= htmlspecialchars($pkIconClass, ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true"></i>
