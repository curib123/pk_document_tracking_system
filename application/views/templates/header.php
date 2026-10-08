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
<?php require __DIR__ . '/../components/navigation/header.php'; ?>
<p id="global-status" role="status" aria-live="polite">Loading the system…</p>
<div id="pk-workspace">
<?php require __DIR__ . '/../components/navigation/sidebar.php'; ?>
