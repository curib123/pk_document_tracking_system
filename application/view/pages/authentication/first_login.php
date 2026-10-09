<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<section class="container-fluid" aria-labelledby="accountSetupTitle">
  <div class="row justify-content-center py-4">
    <div class="col-12 col-md-8 col-lg-6 col-xl-5">
      <div class="card border-0 shadow-sm">
        <div class="card-body p-4 p-lg-5">
          <h1 class="h4 mb-2" id="accountSetupTitle">Set your permanent password</h1>
          <p class="text-secondary">Your temporary password must be replaced before accessing documents, requests, or administration.</p>
          <form method="post" action="<?= site_url('change-password') ?>" data-confirm="Update your permanent password?">
            <input type="hidden" name="<?= $this->security->get_csrf_token_name() ?>" value="<?= $this->security->get_csrf_hash() ?>">
            <input type="hidden" name="confirmed" value="no">
            <div class="mb-3">
              <label for="setupCurrentPassword" class="form-label">Temporary password</label>
              <input id="setupCurrentPassword" class="form-control" name="current_password" type="password" autocomplete="current-password" required>
            </div>
            <div class="mb-4">
              <label for="setupNewPassword" class="form-label">New password</label>
              <input id="setupNewPassword" class="form-control" name="new_password" type="password" autocomplete="new-password" minlength="12" required aria-describedby="setupPasswordHelp">
              <div id="setupPasswordHelp" class="form-text">Use at least 12 characters and do not reuse the temporary password.</div>
            </div>
            <button class="btn btn-primary w-100" type="submit">Set permanent password</button>
          </form>
        </div>
      </div>
    </div>
  </div>
</section>
