<?php
$visible = function ($module, $action = 'view') use ($permissions) {
    return isset($permissions['*']) || !empty($permissions[$module][$action]);
};
$path = uri_string();
$active = function ($prefix) use ($path) {
    return strpos($path, $prefix) === 0 ? ' active' : '';
};
$places = ['area','specific','asset','location','sequence','softcopy-categories'];
$anyPlace = FALSE;
foreach ($places as $item) if ($visible($item)) $anyPlace = TRUE;
?>
<div class="app-shell">
    <aside class="app-sidebar" id="appSidebar">
        <a href="<?= site_url('dashboard') ?>" class="brand">
            <img src="<?= base_url('assets/images/peanut-kisses.jpg') ?>" alt="Peanut Kisses">
            <span><strong>PK Document Control</strong><small>Enterprise Workspace</small></span>
        </a>
        <nav class="side-links" aria-label="Main navigation">
            <?php if ($visible('dashboard')): ?>
                <a class="side-link<?= $active('dashboard') ?>" href="<?= site_url('dashboard') ?>"><i class="fa-solid fa-chart-pie"></i> Dashboard</a>
            <?php endif; ?>
            <?php if ($visible('hardcopy') || $visible('softcopy')): ?>
                <div class="side-heading">System Documents</div>
                <?php if ($visible('hardcopy')): ?><a class="side-link<?= $active('documents/hardcopy') ?>" href="<?= site_url('documents/hardcopy') ?>"><i class="fa-regular fa-folder"></i> Hardcopy Documents</a><?php endif; ?>
                <?php if ($visible('softcopy')): ?><a class="side-link<?= $active('documents/softcopy') ?>" href="<?= site_url('documents/softcopy') ?>"><i class="fa-regular fa-file-lines"></i> Softcopy Documents</a><?php endif; ?>
            <?php endif; ?>
            <?php if ($visible('requests') || $visible('tasks')): ?>
                <div class="side-heading">Requests</div>
                <?php if ($visible('requests')): ?><a class="side-link<?= $active('my-requests/') ?>" href="<?= site_url('my-requests/softcopy') ?>"><i class="fa-solid fa-paper-plane"></i> My Requests</a><?php endif; ?>
                <?php if ($visible('tasks')): ?><a class="side-link<?= $active('my-tasks/') ?>" href="<?= site_url('my-tasks/softcopy') ?>"><i class="fa-solid fa-list-check"></i> My Tasks</a><?php endif; ?>
            <?php endif; ?>
            <?php if ($anyPlace): ?>
                <div class="side-heading">Places</div>
                <?php foreach ($places as $p): if ($visible($p)): ?>
                    <a class="side-link<?= $active('places/' . $p) ?>" href="<?= site_url('places/' . $p) ?>"><i class="fa-solid fa-location-dot"></i> <?= html_escape(ucwords(str_replace('-', ' ', $p))) ?></a>
                <?php endif; endforeach; ?>
            <?php endif; ?>
            <?php if ($visible('users') || $visible('roles') || $visible('workflows')): ?>
                <div class="side-heading">Administration</div>
                <?php if ($visible('users')): ?><a class="side-link<?= $active('admin/users') ?>" href="<?= site_url('admin/users') ?>"><i class="fa-solid fa-users"></i> User Management</a><?php endif; ?>
                <?php if ($visible('roles')): ?><a class="side-link<?= $active('admin/roles') ?>" href="<?= site_url('admin/roles') ?>"><i class="fa-solid fa-shield-halved"></i> Roles & Permissions</a><?php endif; ?>
                <?php if ($visible('workflows')): ?><a class="side-link<?= $active('admin/workflows') ?>" href="<?= site_url('admin/workflows') ?>"><i class="fa-solid fa-diagram-project"></i> Workflow Builder</a><?php endif; ?>
            <?php endif; ?>
        </nav>
        <div class="sidebar-bottom"><span class="status-dot"></span> Document Control System</div>
    </aside>
    <main class="app-main">
        <header class="topbar">
            <button class="icon-button d-lg-none" type="button" id="sidebarToggle" aria-label="Toggle navigation"><i class="fa-solid fa-bars"></i></button>
            <span class="topbar-label">Document Management Workspace</span>
            <div class="user-menu dropdown">
                <button class="user-trigger dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <span class="user-avatar"><?= html_escape(strtoupper(mb_substr($user['name'], 0, 1))) ?></span>
                    <span class="user-meta"><strong><?= html_escape($user['name']) ?></strong><small><?= html_escape(ucwords(str_replace('_', ' ', $user['role']))) ?></small></span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                    <li><button class="dropdown-item" type="button" data-bs-toggle="modal" data-bs-target="#passwordModal"><i class="fa-solid fa-lock me-2"></i> Change Password</button></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><form action="<?= site_url('logout') ?>" method="post" data-confirm="Sign out of your account?">
                        <input type="hidden" name="<?= $this->security->get_csrf_token_name() ?>" value="<?= $this->security->get_csrf_hash() ?>">
                        <input type="hidden" name="confirmed" value="no">
                        <button class="dropdown-item text-danger" type="submit"><i class="fa-solid fa-right-from-bracket me-2"></i> Sign Out</button>
                    </form></li>
                </ul>
            </div>
        </header>
        <section class="page-content">
            <?php if ($this->session->flashdata('notice')): ?>
                <div class="alert alert-<?= html_escape($this->session->flashdata('notice_type') ?: 'success') ?> alert-dismissible fade show" role="alert">
                    <?= html_escape($this->session->flashdata('notice')) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
