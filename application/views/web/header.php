<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= ui_escape($page_title ?? 'PK Document Tracking System') ?> | PK DTS</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;600;700&amp;display=swap">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= ui_url('assets/css/enterprise-ui.css') ?>">
</head>
<body class="pk-app">
<a href="#main-content" class="visually-hidden-focusable pk-skip-link">Skip to content</a>
<header class="pk-topbar">
    <div class="pk-topbar-main">
        <a href="<?= site_url('web') ?>" class="pk-brand" aria-label="PK Document Tracking System home">
            <span class="pk-brand-mark"><?= pk_web_icon('files') ?></span>
            <span class="pk-brand-name"><strong>PK Documents</strong><small>Tracking system</small></span>
        </a>

        <?php if ($viewer): ?>
            <details class="pk-mobile-drawer d-lg-none">
                <summary class="pk-mobile-trigger" aria-label="Open navigation">
                    <?= pk_web_icon('menu') ?> <span>Menu</span>
                </summary>
                <button type="button" class="pk-mobile-backdrop" aria-label="Close navigation" data-pk-close-mobile></button>
                <div class="pk-mobile-panel">
                    <?php require __DIR__ . '/components/sidebar.php'; ?>
                </div>
            </details>
        <?php endif; ?>
    </div>

    <?php if ($viewer): ?>
        <?php
        $displayName = trim(($viewer['first_name'] ?? '') . ' ' . ($viewer['last_name'] ?? ''));
        if ($displayName === '') {
            $displayName = (string) ($viewer['username'] ?? 'Account');
        }
        $initials = strtoupper(mb_substr($displayName, 0, 1));
        ?>
        <div class="pk-topbar-account">
            <span class="pk-avatar" aria-hidden="true"><?= ui_escape($initials) ?></span>
            <span class="pk-account-name d-none d-sm-inline"><?= ui_escape($displayName) ?></span>
            <form method="post" action="<?= site_url('web/logout') ?>" class="m-0">
                <input type="hidden" name="csrf" value="<?= ui_escape($csrf) ?>">
                <button type="submit" class="pk-logout" aria-label="Sign out" title="Sign out"><?= pk_web_icon('logout') ?></button>
            </form>
        </div>
    <?php endif; ?>
</header>

<div class="pk-layout">
    <?php if ($viewer): ?>
        <aside class="pk-sidebar d-none d-lg-block">
            <?php require __DIR__ . '/components/sidebar.php'; ?>
        </aside>
    <?php endif; ?>

    <main class="pk-main <?= !$viewer ? 'pk-main-guest' : '' ?>" id="main-content" tabindex="-1">
        <?php if ($viewer): ?>
            <div class="pk-content-eyebrow">WORKSPACE <span aria-hidden="true">/</span> <?= ui_escape($page_title ?? 'Overview') ?></div>
        <?php endif; ?>

        <?php if (!empty($flash['message'])): ?>
            <?php if (($flash['type'] ?? '') === 'danger'): ?>
                <?php
                $modalId = 'pk-alert-modal';
                $modalTitle = 'Action not completed';
                $modalTone = 'danger';
                $modalAutoOpen = true;
                $modalMessage = (string) $flash['message'];
                $modalAction = null;
                $modalSubmit = '';
                $modalBack = '';
                $modalFields = [];
                require __DIR__ . '/components/modal.php';
                ?>
            <?php else: ?>
                <div class="alert alert-success pk-inline-feedback d-flex gap-2 align-items-center" role="status">
                    <?= pk_web_icon('success') ?> <span><?= ui_escape($flash['message']) ?></span>
                </div>
            <?php endif; ?>
        <?php endif; ?>
