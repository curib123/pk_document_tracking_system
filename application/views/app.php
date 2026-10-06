<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="api-endpoint" content="<?= htmlspecialchars(rtrim(getenv('APP_URL') ?: 'http://localhost:8080','/').'/index.php/api',ENT_QUOTES,'UTF-8') ?>">
<title>PK Document Tracking System</title>
<script type="module" src="<?= htmlspecialchars(rtrim(getenv('APP_URL') ?: 'http://localhost:8080','/').'/assets/app.js',ENT_QUOTES,'UTF-8') ?>"></script>
</head>
<body>
<header><h1>PK Document Tracking System</h1><p id="account"></p><div id="account-actions"></div></header>
<p id="global-status" role="status" aria-live="polite">Loading the system…</p>
<nav id="navigation" aria-label="Modules"></nav>
<main id="content" tabindex="-1"></main>
<noscript>JavaScript is required for the modal forms. Enable JavaScript and reload this page.</noscript>
</body>
</html>
