<div class="row justify-content-center py-5">
    <div class="col-12 col-sm-9 col-md-6 col-lg-4">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h1 class="h4 mb-2">Sign in</h1>
                <p class="text-body-secondary">Use your PK DTS account to continue.</p>
                <?php if (!empty($message)): ?>
                    <div class="alert alert-danger" role="alert"><?= ui_escape($message) ?></div>
                <?php endif; ?>
                <form action="<?= site_url('web/sign-in') ?>" method="post">
                    <input type="hidden" name="csrf" value="<?= ui_escape($csrf) ?>">
                    <div class="mb-3">
                        <label class="form-label" for="username">Username</label>
                        <input class="form-control" id="username" name="username" maxlength="80" required autocomplete="username" value="<?= ui_escape($username ?? '') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="password">Password</label>
                        <input class="form-control" type="password" id="password" name="password" required autocomplete="current-password">
                    </div>
                    <button class="btn btn-primary w-100" type="submit">Sign in</button>
                </form>
            </div>
        </div>
    </div>
</div>
