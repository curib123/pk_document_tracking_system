<div class="row justify-content-center py-4">
    <div class="col-12 col-md-8 col-xl-5">
        <div class="card">
            <div class="card-body p-4">
                <h1 class="h4 fw-bold mb-2">Change password</h1>
                <?php if (!empty($viewer['require_password_change'])): ?>
                    <div class="alert alert-warning">Update your initial password to access your workspace.</div>
                <?php else: ?>
                    <p class="text-body-secondary">Choose a new password to secure your account.</p>
                <?php endif; ?>
                <form method="post" action="<?= site_url('web/change-password') ?>">
                    <input type="hidden" name="csrf" value="<?= ui_escape($csrf) ?>">
                    <?php foreach ([
                        'current_password' => 'Current password',
                        'new_password' => 'New password',
                        'confirm_password' => 'Confirm new password',
                    ] as $field => $label): ?>
                        <div class="mb-3">
                            <label class="form-label" for="<?= $field ?>"><?= $label ?></label>
                            <div class="input-group">
                                <input class="form-control" type="password" id="<?= $field ?>" name="<?= $field ?>"
                                       required autocomplete="<?= $field === 'current_password' ? 'current-password' : 'new-password' ?>">
                                <button class="btn btn-outline-secondary" type="button"
                                        data-pk-password-toggle="<?= $field ?>" aria-label="Show <?= $label ?>" aria-pressed="false">Show</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <div class="d-flex flex-wrap gap-2 mt-4">
                        <button type="submit" class="btn btn-primary">Update password</button>
                        <?php if (empty($viewer['require_password_change'])): ?>
                            <a class="btn btn-outline-secondary" href="<?= site_url('web') ?>">Cancel</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
