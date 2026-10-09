<section class="workspace-card p-4" aria-labelledby="credentialTitle">
  <h1 class="h4" id="credentialTitle">Temporary account credentials</h1>
  <p>Give these credentials to the account owner through your approved private channel. This password is displayed only in this response.</p>
  <dl class="detail-grid">
    <dt>Username</dt><dd><?= html_escape($account_result['username']) ?></dd>
    <dt>Temporary password</dt><dd><code id="temporaryPassword"><?= html_escape($account_result['temporary_password']) ?></code></dd>
  </dl>
  <p class="text-secondary">The user must replace this password before accessing any protected module. Use Reset Password to generate a replacement if needed.</p>
  <a class="btn btn-primary" href="<?= site_url('admin/users') ?>">Return to User Management</a>
</section>
