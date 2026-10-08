<div class="row justify-content-center py-5">
    <div class="col-12 col-sm-9 col-md-7 col-lg-5 col-xl-4">
        <div class="card shadow-sm">
            <div class="card-body p-4 p-lg-5">
                <span class="pk-brand-mark mb-3"><?= pk_web_icon('files') ?></span>
                <h1 class="h3 fw-bold mb-1">Welcome back</h1>
                <p class="text-body-secondary mb-4">Sign in to PK Document Tracking System.</p>
                <?php if (!empty($message)): ?>
                    <div class="alert alert-danger" id="pk-login-error" role="alert"><?= ui_escape($message) ?></div>
                <?php endif; ?>
                <form action="<?= site_url('web/sign-in') ?>" method="post">
                    <input type="hidden" name="csrf" value="<?= ui_escape($csrf) ?>">
                    <div class="mb-3">
                        <label class="form-label" for="username">Username</label>
                        <input class="form-control" id="username" name="username" maxlength="80" required
                               autocomplete="username" autofocus value="<?= ui_escape($username ?? '') ?>">
                    </div>
                    <div class="mb-4">
                        <label class="form-label" for="password">Password</label>
                        <div class="input-group">
                            <input class="form-control" type="password" id="password" name="password"
                                   required autocomplete="current-password">
                            <button class="btn btn-outline-secondary" type="button" data-pk-password-toggle="password"
                                    aria-label="Show password" aria-pressed="false">Show</button>
                        </div>
                    </div>
                    <button class="btn btn-primary w-100 py-2" type="submit">Sign in</button>
                </form>
            </div>
        </div>
        <p class="text-body-secondary text-center small mt-3">Document control · Secure sign-in</p>
    </div>
</div>
