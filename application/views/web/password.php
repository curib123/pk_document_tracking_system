<div class="row justify-content-center">
    <div class="col-12 col-md-7 col-xl-5">
        <h1 class="h3 mb-3">Change password</h1>
        <?php if (!empty($viewer['require_password_change'])): ?>
            <div class="alert alert-warning">Change your initial password before accessing the system.</div>
        <?php endif; ?>
        <form method="post" action="<?= site_url('web/change-password') ?>" class="card card-body shadow-sm">
            <input type="hidden" name="csrf" value="<?= ui_escape($csrf) ?>">
            <div class="mb-3">
                <label class="form-label" for="current_password">Current password</label>
                <input class="form-control" type="password" name="current_password" id="current_password" required autocomplete="current-password">
            </div>
            <div class="mb-3">
                <label class="form-label" for="new_password">New password</label>
                <input class="form-control" type="password" name="new_password" id="new_password" required autocomplete="new-password">
            </div>
            <div class="mb-3">
                <label class="form-label" for="confirm_password">Confirm password</label>
                <input class="form-control" type="password" name="confirm_password" id="confirm_password" required autocomplete="new-password">
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">Update password</button>
                <?php if (empty($viewer['require_password_change'])): ?>
                    <a href="<?= site_url('web') ?>" class="btn btn-outline-secondary">Cancel</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>
