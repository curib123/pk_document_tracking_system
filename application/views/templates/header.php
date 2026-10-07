<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="api-endpoint" content="<?= ui_escape(ui_url('index.php/api')) ?>">
<meta name="api-root" content="<?= ui_escape(ui_url('index.php/')) ?>">
<meta name="endpoint-routes" content="<?= ui_escape(json_encode((new Endpoint_registry())->browserRoutes(),JSON_THROW_ON_ERROR)) ?>">
<meta name="initial-module" content="<?= ui_escape($initial_module ?? '') ?>">
<title><?= ui_escape($page_title ?? 'PK Document Tracking System') ?></title>
<script type="module" src="<?= ui_escape(ui_url('assets/js/app.js')) ?>"></script>
</head>
<body>
<header><h1>PK Document Tracking System</h1><p id="account"></p><div id="account-actions"></div></header>
<?php require PK_ROOT.'/application/views/components/status.php'; ?>
<?php require PK_ROOT.'/application/views/templates/navigation.php'; ?>
