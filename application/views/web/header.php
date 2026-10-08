<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= ui_escape($page_title ?? 'PK Document Tracking System') ?> | PK DTS</title>
    <!-- Bootstrap is the sole stylesheet dependency; no custom CSS or JS bundles. -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css">
</head>
<body class="bg-body-tertiary">
<header class="navbar navbar-dark bg-dark">
    <div class="container-fluid">
        <a class="navbar-brand fw-semibold" href="<?= site_url('web') ?>">PK Document Tracking System</a>
        <?php if ($viewer): ?>
            <div class="d-flex align-items-center gap-3 text-white">
                <span class="small"><?= ui_escape(trim(($viewer['first_name'] ?? '') . ' ' . ($viewer['last_name'] ?? '')) ?: ($viewer['username'] ?? 'User')) ?></span>
                <form action="<?= site_url('web/logout') ?>" method="post" class="m-0">
                    <input type="hidden" name="csrf" value="<?= ui_escape($csrf) ?>">
                    <button type="submit" class="btn btn-outline-light btn-sm">Sign out</button>
                </form>
            </div>
        <?php endif; ?>
    </div>
</header>
<div class="container-fluid">
    <div class="row g-0">
        <?php if ($viewer): ?>
        <aside class="col-12 col-lg-3 col-xl-2 bg-white border-end p-3">
            <nav aria-label="Main navigation">
                <a class="btn btn-outline-primary w-100 mb-3" href="<?= site_url('web') ?>">Dashboard</a>
                <?php
                $groups = [];
                foreach ($navigation as $item) {
                    $groups[$item['navigation_group']][] = $item;
                }
                $catalogModules = ['areas', 'specifics', 'assets', 'locations', 'categories'];
                ?>
                <?php foreach ($groups as $group => $items): ?>
                    <h2 class="h6 text-body-secondary mt-3 mb-2"><?= ui_escape($group) ?></h2>
                    <div class="nav flex-column gap-1">
                        <?php foreach ($items as $item): ?>
                            <?php $href = in_array($item['key'], $catalogModules, true)
                                ? 'web/catalog/' . $item['key']
                                : 'web/records/' . $item['key']; ?>
                            <a class="nav-link py-1 px-2" href="<?= site_url($href) ?>">
                                <?= ui_escape($item['label']) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
                <hr>
                <a href="<?= site_url('web/password') ?>" class="nav-link px-2">Change password</a>
                <a href="<?= site_url('app') ?>" class="nav-link px-2">Existing workspace (legacy)</a>
            </nav>
        </aside>
        <?php endif; ?>
        <main class="<?= $viewer ? 'col-12 col-lg-9 col-xl-10' : 'col-12' ?> p-3 p-md-4" id="main-content">
            <?php if (is_array($flash) && !empty($flash['message'])): ?>
                <div class="alert alert-<?= ($flash['type'] ?? '') === 'danger' ? 'danger' : 'success' ?>" role="alert">
                    <?= ui_escape($flash['message']) ?>
                </div>
            <?php endif; ?>
