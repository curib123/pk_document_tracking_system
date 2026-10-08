<?php
require_once dirname(__DIR__, 2) . '/helpers/styling_helper.php';
$pkStyling = pk_styling_config();
$pkCss = dirname(__DIR__, 3) . '/public/assets/css/app.css';
$pkReferenceCss = dirname(__DIR__, 3) . '/public/assets/css/workspace.css';
$pkStyling['stylesheet'] = ui_url('assets/css/app.css?v=' . (is_file($pkCss) ? filemtime($pkCss) : '1'));
$pkStyling['reference_stylesheet'] = ui_url('assets/css/workspace.css?v=' . (is_file($pkReferenceCss) ? filemtime($pkReferenceCss) : '1'));
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="api-root" content="<?= ui_escape(ui_url('index.php/')) ?>">
<meta name="endpoint-routes" content="<?= ui_escape(json_encode((new Endpoint_registry())->browserRoutes(),JSON_THROW_ON_ERROR)) ?>">
<meta name="initial-module" content="<?= ui_escape($initial_module ?? '') ?>">
<meta name="pk-styling" content="<?= ui_escape(json_encode($pkStyling, JSON_THROW_ON_ERROR)) ?>">
<title><?= ui_escape($page_title ?? 'PK Document Tracking System') ?></title>
<script type="module" src="<?= ui_escape(ui_url('assets/js/styling.js')) ?>"></script>
<script type="module" src="<?= ui_escape(ui_url('assets/js/workspace.js')) ?>"></script>
<script type="module" src="<?= ui_escape(ui_url('assets/js/app.js')) ?>"></script>
</head>
<body>
<header id="pk-header">
<button id="pk-menu-toggle" type="button" hidden aria-controls="navigation" aria-expanded="false" aria-label="Modules">☰</button>
<div class="ws-heading-icon" data-pk-decoration hidden aria-hidden="true"><svg class="ws-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z M14 2v6h6"/></svg></div>
<div class="ws-heading"><span data-pk-decoration hidden>Document workspace</span><h1 id="ws-page-title">PK Document Tracking System</h1><p id="ws-page-description" data-pk-decoration hidden>Track the system summary, recent activity, and key counts at a glance.</p><p id="pk-context" data-pk-decoration hidden>Document control <strong id="pk-current-section"></strong></p></div>
<div class="ws-header-tools">
<button class="ws-tool" id="ws-notifications" type="button" data-pk-decoration hidden aria-label="Notifications"><svg class="ws-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9 M10 21h4"/></svg><span id="ws-notification-count"></span></button>
<button class="ws-tool" id="ws-mode-toggle" type="button" data-pk-decoration hidden aria-label="Toggle dark mode" aria-pressed="false"><span id="ws-mode-label">Dark mode</span></button>
<details id="pk-account-menu" open><summary aria-label="Account menu"><span class="ws-user-avatar" aria-hidden="true"><svg class="ws-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M20 21v-2a8 8 0 0 0-16 0v2 M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8"/></svg></span><span><strong id="ws-account-name">Account</strong><small id="ws-account-role"></small></span></summary><p id="account"></p><div id="account-actions"></div></details>
</div>
</header>
<p id="global-status" role="status" aria-live="polite">Loading the system…</p>
<div id="pk-workspace">
<aside id="pk-sidebar">
<div class="ws-sidebar-brand" data-pk-decoration hidden><img src="<?= ui_escape(ui_url('assets/images/pk-mark.webp')) ?>" alt="PK"><span><small>Records workspace</small><strong>DTS</strong></span></div>
<p class="ws-nav-label" data-pk-decoration hidden>Workspace</p>
<nav id="navigation" aria-label="Modules"></nav>
<div class="ws-sidebar-footer" data-pk-decoration hidden><div class="ws-signed-in"><span class="ws-user-avatar" aria-hidden="true"><svg class="ws-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M20 21v-2a8 8 0 0 0-16 0v2 M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8"/></svg></span><span><small>Signed in as</small><strong id="ws-sidebar-name"></strong><span id="ws-sidebar-role"></span></span></div><button id="ws-sign-out" type="button"><svg class="ws-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M9 5H4v14h5 M9 12h12 m-4-4 4 4-4 4"/></svg>Sign out</button></div>
</aside>
