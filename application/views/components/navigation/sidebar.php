<aside id="pk-sidebar">
    <div class="ws-sidebar-brand" data-pk-decoration hidden>
        <img src="<?= ui_escape(ui_url('assets/images/pk-mark.webp')) ?>" alt="PK">
        <span><small>Records workspace</small><strong>DTS</strong></span>
    </div>
    <p class="ws-nav-label" data-pk-decoration hidden>Workspace</p>
    <nav id="navigation" aria-label="Modules"></nav>
    <div class="ws-sidebar-footer" data-pk-decoration hidden>
        <div class="ws-signed-in">
            <span class="ws-user-avatar" aria-hidden="true"><?php $iconName = 'user'; require __DIR__ . '/../workspace/icon.php'; ?></span>
            <span>
                <small>Signed in as</small>
                <strong id="ws-sidebar-name"></strong>
                <span id="ws-sidebar-role"></span>
            </span>
        </div>
        <button id="ws-sign-out" type="button">
            <?php $iconName = 'logout'; require __DIR__ . '/../workspace/icon.php'; ?>
            Sign out
        </button>
    </div>
</aside>
