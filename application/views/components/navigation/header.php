<header id="pk-header">
    <button id="pk-menu-toggle" type="button" hidden aria-controls="navigation" aria-expanded="false" aria-label="Modules" title="Modules">
        <?php $iconName = 'menu'; require __DIR__ . '/../workspace/icon.php'; ?>
    </button>
    <div class="ws-heading-icon" data-pk-decoration hidden aria-hidden="true">
        <?php $iconName = 'file'; require __DIR__ . '/../workspace/icon.php'; ?>
    </div>
    <div class="ws-heading">
        <span data-pk-decoration hidden>Document workspace</span>
        <h1 id="ws-page-title">PK Document Tracking System</h1>
        <p id="ws-page-description" data-pk-decoration hidden>Track the system summary, recent activity, and key counts at a glance.</p>
        <p id="pk-context" data-pk-decoration hidden>Document control <strong id="pk-current-section"></strong></p>
    </div>
    <div class="ws-header-tools">
        <button class="ws-tool" id="ws-notifications" type="button" data-pk-decoration hidden aria-label="Notifications" title="Notifications">
            <?php $iconName = 'bell'; require __DIR__ . '/../workspace/icon.php'; ?>
            <span id="ws-notification-count"></span>
        </button>
        <button class="ws-tool" id="ws-mode-toggle" type="button" data-pk-decoration hidden aria-label="Toggle dark mode" aria-pressed="false" title="Toggle dark mode">
            <span id="ws-mode-label">Dark mode</span>
        </button>
        <details id="pk-account-menu" open>
            <summary aria-label="Account menu">
                <span class="ws-user-avatar" aria-hidden="true"><?php $iconName = 'user'; require __DIR__ . '/../workspace/icon.php'; ?></span>
                <span><strong id="ws-account-name">Account</strong><small id="ws-account-role"></small></span>
            </summary>
            <p id="account"></p>
            <div id="account-actions"></div>
        </details>
    </div>
</header>
