<?php $prefix = $password_prefix ?? 'password'; ?>
<div data-password-fields>
    <label class="form-label" for="<?= html_escape($prefix) ?>CurrentPassword">Current or temporary password</label>
    <input class="form-control mb-3" id="<?= html_escape($prefix) ?>CurrentPassword" type="password"
           name="current_password" required autocomplete="current-password">
    <label class="form-label" for="<?= html_escape($prefix) ?>NewPassword">New password</label>
    <input class="form-control" id="<?= html_escape($prefix) ?>NewPassword" type="password" name="new_password"
           required minlength="12" maxlength="72" autocomplete="new-password"
           aria-describedby="<?= html_escape($prefix) ?>PasswordHelp">
    <div class="form-text mb-3" id="<?= html_escape($prefix) ?>PasswordHelp">Use at least 12 characters, up to 72 bytes. Do not reuse your temporary password.</div>
    <label class="form-label" for="<?= html_escape($prefix) ?>ConfirmPassword">Confirm new password</label>
    <input class="form-control" id="<?= html_escape($prefix) ?>ConfirmPassword" type="password"
           name="confirm_password" required minlength="12" maxlength="72" autocomplete="new-password">
</div>
