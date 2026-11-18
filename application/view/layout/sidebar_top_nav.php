
<?php

$visible = function ($module, $action = 'view') use ($permissions) {
    return isset($permissions['*']) || !empty($permissions[$module][$action]);
};

$path = uri_string();

$active = function ($prefix) use ($path) {
    return strpos($path, $prefix) === 0 ? ' active' : '';
};

$requestRoutes = [
    'softcopy' => 'Softcopy Request',
    'external' => 'External Document Request',
    'scanned' => 'Scanned Document Request',
    'hardcopy' => 'Hardcopy Request',
    'hardcopy-transfer' => 'Hardcopy Transfer Request',
    'hardcopy-disposal' => 'Disposal Request',
    'access-grant' => 'Document Access Request',
    'document-assign' => 'Document Assignment Request'
];

// Show Master Data only when at least one place module is accessible.
$anyPlace = FALSE;

foreach (['areas', 'specifics', 'assets', 'locations', 'categories', 'external_categories', 'scanned_categories', 'subsidiaries'] as $placeModule) {
    if ($visible($placeModule)) {
        $anyPlace = TRUE;
        break;
    }
}

// Determine the current topbar section.
$navModule = 'Overview';

foreach ([
    'documents/'   => 'Document Management',
    'places'       => 'Master Data',
    'my-requests/' => 'Requests & Workflow',
    'my-tasks/'    => 'Requests & Workflow',
    'admin/'       => 'System Administration',
] as $prefix => $label) {
    if (strpos($path, $prefix) === 0) {
        $navModule = $label;
        break;
    }
}

$navParts = explode(' · ', $title);
$navPage = end($navParts);

?>

<div class="app-shell">

    <aside class="app-sidebar" id="appSidebar" aria-label="Primary navigation">

        <a href="<?= site_url('dashboard') ?>" class="brand">
            <img
                src="<?= base_url('assets/images/peanut-kisses.png') ?>"
                alt="Peanut Kisses"
            >

            <span>
                <strong>PK Document Control</strong>
                <small>Record Workspace</small>
            </span>
        </a>

        <nav class="side-links" aria-label="Main navigation">

            <!-- Dashboard -->
            <?php if ($visible('dashboard')): ?>
                <a
                    class="side-link<?= $active('dashboard') ?>"
                    <?= $active('dashboard') === ' active' ? 'aria-current="page"' : '' ?>
                    href="<?= site_url('dashboard') ?>"
                >
                    <i class="fa-solid fa-chart-pie" aria-hidden="true"></i>
                    Overview
                </a>
            <?php endif; ?>

            <!-- Document Management -->
            <?php if ($visible('hardcopy') || $visible('softcopy') || $visible('external_documents') || $visible('scanned_documents')): ?>
                <div class="side-heading">Document Management</div>

                <?php if ($visible('hardcopy')): ?>
                    <a
                        class="side-link<?= $active('documents/hardcopy') ?>"
                        <?= $active('documents/hardcopy') === ' active' ? 'aria-current="page"' : '' ?>
                        href="<?= site_url('documents/hardcopy') ?>"
                    >
                        <i class="fa-regular fa-folder" aria-hidden="true"></i>
                        Hardcopy Documents
                    </a>
                <?php endif; ?>

                <?php if ($visible('softcopy')): ?>
                    <a
                        class="side-link<?= $active('documents/softcopy') ?>"
                        <?= $active('documents/softcopy') === ' active' ? 'aria-current="page"' : '' ?>
                        href="<?= site_url('documents/softcopy') ?>"
                    >
                        <i class="fa-regular fa-file-lines" aria-hidden="true"></i>
                        Softcopy Documents
                    </a>
                <?php endif; ?>

                    <?php if ($visible('scanned_documents')): ?>
                <a class="side-link<?= $active('documents/scanned') ?>" href="<?= site_url('documents/scanned') ?>">
                    <i class="fa-solid fa-file-image" aria-hidden="true"></i> Scanned Documents
                </a>
            <?php endif; ?>
            
                <?php if ($visible('external_documents')): ?>
                    <a
                        class="side-link<?= $active('documents/external') ?>"
                        <?= $active('documents/external') === ' active' ? 'aria-current="page"' : '' ?>
                        href="<?= site_url('documents/external') ?>"
                    >
                        <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i>
                        External Documents
                    </a>
                <?php endif; ?>
            <?php endif; ?>


            <!-- Document Requests -->
            <?php if ($visible('requests') || $user): ?>
                <div class="side-heading">Document Requests</div>

                <?php foreach ([
                    'requests' => ['My Requests', 'fa-paper-plane', 'draft or returned requests'],
                    'tasks' => ['My Tasks', 'fa-list-check', 'pending tasks']
                ] as $section => [$sectionLabel, $sectionIcon, $countLabel]): ?>
                    <?php
                    if ($section === 'requests' && !$visible('requests')) continue;
                    $sectionPrefix = 'my-' . $section . '/';
                    $sectionActive = $active($sectionPrefix) !== '';
                    $sectionCount = (int) ($navigation_counts[$section . '_total'] ?? 0);
                    ?>
                    <details class="side-group" <?= $sectionActive ? 'open' : '' ?>>
                        <summary class="side-link side-group-toggle<?= $sectionActive ? ' active' : '' ?>"
                            aria-controls="sidebar-<?= $section ?>-routes">
                            <i class="fa-solid <?= $sectionIcon ?>" aria-hidden="true"></i>
                            <span><?= $sectionLabel ?></span>
                            <span class="nav-count<?= $sectionCount === 0 ? ' is-zero' : '' ?>"
                                aria-label="<?= $sectionCount ?> <?= $countLabel ?>"><?= $sectionCount ?></span>
                            <i class="fa-solid fa-chevron-down side-group-chevron" aria-hidden="true"></i>
                        </summary>
                        <ul class="side-submenu" id="sidebar-<?= $section ?>-routes" aria-label="<?= $sectionLabel ?> categories">
                            <?php foreach ($requestRoutes as $slug => $routeLabel): ?>
                                <?php
                                if ($section === 'requests' && $slug === 'hardcopy') continue;
                                $route = $sectionPrefix . $slug;
                                $routeActive = $path === $route || strpos($path, $route . '/') === 0;
                                $routeCount = (int) ($navigation_counts[$section][$slug] ?? 0);
                                ?>
                                <li>
                                    <a class="side-link side-sublink<?= $routeActive ? ' active' : '' ?>"
                                        href="<?= site_url($route) ?>" <?= $routeActive ? 'aria-current="page"' : '' ?>>
                                        <span class="side-sublink-label"><?= html_escape($routeLabel) ?></span>
                                        <span class="nav-count<?= $routeCount === 0 ? ' is-zero' : '' ?>"
                                            aria-label="<?= $routeCount ?> <?= $countLabel ?>"><?= $routeCount ?></span>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </details>
                <?php endforeach; ?>
            <?php endif; ?>

            <!-- Document Control -->
            <?php if ($visible('assignment', 'direct') || $visible('workflows') || $anyPlace): ?>
                <div class="side-heading">Document Control</div>

                <?php if ($visible('assignment', 'direct')): ?>
                    <a
                        class="side-link<?= $active('admin/document-assignments') ?>"
                        <?= $active('admin/document-assignments') === ' active' ? 'aria-current="page"' : '' ?>
                        href="<?= site_url('admin/document-assignments') ?>"
                    >
                        <i class="fa-solid fa-file-circle-check" aria-hidden="true"></i>
                        Document Assignments
                    </a>
                <?php endif; ?>

                <?php if ($visible('workflows')): ?>
                    <a
                        class="side-link<?= $active('admin/workflows') ?>"
                        <?= $active('admin/workflows') === ' active' ? 'aria-current="page"' : '' ?>
                        href="<?= site_url('admin/workflows') ?>"
                    >
                        <i class="fa-solid fa-diagram-project" aria-hidden="true"></i>
                        Workflow Builder
                    </a>
                <?php endif; ?>

                <?php if ($anyPlace): ?>
                    <a
                        class="side-link<?= $active('places') ?>"
                        <?= strpos($path, 'places') === 0 ? 'aria-current="page"' : '' ?>
                        href="<?= site_url('places') ?>"
                    >
                        <i class="fa-solid fa-map-location-dot" aria-hidden="true"></i>
                        Master Data
                    </a>
                <?php endif; ?>
            <?php endif; ?>

            <!-- System Administration -->
            <?php if (
                $visible('users')
                || $visible('roles')
                || $visible('audit')
                || $visible('backups')
            ): ?>
                <div class="side-heading">System Administration</div>

                <?php if ($visible('users')): ?>
                    <a
                        class="side-link<?= $active('admin/users') ?>"
                        <?= $active('admin/users') === ' active' ? 'aria-current="page"' : '' ?>
                        href="<?= site_url('admin/users') ?>"
                    >
                        <i class="fa-solid fa-users" aria-hidden="true"></i>
                        User Management
                    </a>
                <?php endif; ?>

                <?php if ($visible('roles')): ?>
                    <a
                        class="side-link<?= $active('admin/roles') ?>"
                        <?= $active('admin/roles') === ' active' ? 'aria-current="page"' : '' ?>
                        href="<?= site_url('admin/roles') ?>"
                    >
                        <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
                        Roles &amp; Permissions
                    </a>
                <?php endif; ?>

                <?php if ($visible('audit')): ?>
                    <a
                        class="side-link<?= $active('admin/audit') ?>"
                        <?= $active('admin/audit') === ' active' ? 'aria-current="page"' : '' ?>
                        href="<?= site_url('admin/audit') ?>"
                    >
                        <i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i>
                        Internal Audit Trail
                    </a>
                <?php endif; ?>

                <?php if ($visible('backups')): ?>
                    <a
                        class="side-link<?= $active('admin/backups') ?>"
                        <?= $active('admin/backups') === ' active' ? 'aria-current="page"' : '' ?>
                        href="<?= site_url('admin/backups') ?>"
                    >
                        <i class="fa-solid fa-box-archive" aria-hidden="true"></i>
                        Backup &amp; Restore
                    </a>
                <?php endif; ?>
            <?php endif; ?>

        </nav>

        <div class="sidebar-bottom">
            <span class="status-dot"></span>
            Document Control System
        </div>

    </aside>

    <div
        id="sidebarBackdrop"
        class="sidebar-backdrop"
        hidden
        aria-hidden="true"
    ></div>

    <main class="app-main" id="mainContent" tabindex="-1">

        <header class="topbar">

            <button
                class="icon-button d-lg-none"
                type="button"
                id="sidebarToggle"
                aria-label="Toggle navigation"
                aria-controls="appSidebar"
                aria-expanded="false"
            >
                <i class="fa-solid fa-bars" aria-hidden="true"></i>
            </button>

            <div class="topbar-context">
                <span class="topbar-module">
                    <?= html_escape($navModule) ?>
                </span>

                <span class="topbar-label">
                    <?= html_escape($navPage) ?>
                </span>
            </div>

            <button type="button" id="pwaInstallButton" class="pwa-install-button" hidden
                aria-label="Install PK Document Control" title="Install PK Document Control">
                <i class="fa-solid fa-download" aria-hidden="true"></i><span>Install App</span>
            </button>
             
            <button type="button" id="themeToggle" class="icon-button notification-bell-button theme-toggle" hidden
                aria-label="Switch to dark mode" title="Switch to dark mode" aria-pressed="false">
                <i class="fa-solid fa-moon" aria-hidden="true"></i>
            </button>
            <div class="topbar-notifications" id="notificationBellPanel"
                 data-notification-list="<?= html_escape(site_url('notifications/inbox')) ?>"
                 data-notification-read="<?= html_escape(site_url('notifications/inbox/read')) ?>"
                 data-notification-csrf="<?= html_escape($this->security->get_csrf_token_name()) ?>"
                 data-notification-cookie="<?= html_escape(config_item('cookie_prefix').config_item('csrf_cookie_name')) ?>">
                <button class="icon-button notification-bell-button" id="notificationBellButton"
                        type="button" aria-label="Unread notifications" aria-expanded="false"
                        aria-controls="notificationBellMenu">
                    <i class="fa-regular fa-bell" aria-hidden="true"></i>
                    <span class="notification-bell-count" id="notificationBellCount" hidden></span>
                </button>
                <section class="notification-inbox" id="notificationBellMenu" hidden
                         aria-labelledby="notificationInboxTitle">
                    <div class="notification-inbox-header">
                        <h2 id="notificationInboxTitle">Notifications</h2>
                        <button class="btn-close" type="button" data-notification-close aria-label="Close notifications"></button>
                    </div>
                    <ol class="notification-inbox-items" id="notificationInboxItems" aria-live="polite"></ol>
                    <div class="notification-inbox-footer">
                        <div class="notification-inbox-nav">
                            <button class="btn btn-light btn-sm" type="button" data-notification-previous>Previous</button>
                            <span id="notificationInboxPage">1 / 1</span>
                            <button class="btn btn-light btn-sm" type="button" data-notification-next>Next</button>
                        </div>
                        <label class="notification-inbox-limit">Rows
                            <select class="form-select form-select-sm" id="notificationInboxLimit" autocomplete="off">
                                <?php foreach ([5,10,20,50] as $perPage): ?>
                                <option value="<?= $perPage ?>" <?= $perPage===10?'selected':'' ?>><?= $perPage ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    </div>
                </section>
            </div>
            <div class="user-menu dropdown">

                <button
                    class="user-trigger dropdown-toggle"
                    type="button"
                    data-bs-toggle="dropdown"
                    aria-expanded="false"
                >
                    <span class="user-avatar">
                        <?= html_escape(strtoupper(mb_substr($user['name'], 0, 1))) ?>
                    </span>

                    <span class="user-meta">
                        <strong>
                            <?= html_escape($user['name']) ?>
                        </strong>

                        <small>
                            <?= html_escape(ucwords(str_replace(
                                '_',
                                ' ',
                                $user['roles_label'] ?? $user['role']
                            ))) ?>
                        </small>
                    </span>
                </button>

                <ul class="dropdown-menu dropdown-menu-end shadow-sm">

                    <li>
                        <button
                            class="dropdown-item"
                            type="button"
                            data-bs-toggle="modal"
                            data-bs-target="#passwordModal"
                        >
                            <i class="fa-solid fa-lock me-2" aria-hidden="true"></i>
                            Change Password
                        </button>
                    </li>

                    <li>
                        <hr class="dropdown-divider">
                    </li>

                    <li>
                        <form
                            action="<?= site_url('logout') ?>"
                            method="post"
                            data-confirm="Sign out of your account?"
                            data-logo-logout autocomplete="off"
                        >
                            <input
                                type="hidden"
                                name="<?= $this->security->get_csrf_token_name() ?>"
                                value="<?= $this->security->get_csrf_hash() ?>"
                            >

                            <input
                                type="hidden"
                                name="confirmed"
                                value="no"
                            >

                            <button
                                class="dropdown-item text-danger"
                                type="submit"
                            >
                                <i
                                    class="fa-solid fa-right-from-bracket me-2"
                                    aria-hidden="true"
                                ></i>
                                Sign Out
                            </button>
                        </form>
                    </li>

                </ul>

            </div>

        </header>

        <section class="page-content">
