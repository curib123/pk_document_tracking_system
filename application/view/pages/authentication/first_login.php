<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<div class="modal d-block auth-required-dialog" data-bs-backdrop="static" data-bs-keyboard="false" id="requiredPasswordModal" tabindex="-1"
     role="dialog" aria-modal="true" aria-labelledby="accountSetupTitle" data-required-password>
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header"><h1 class="modal-title fs-5" id="accountSetupTitle">Change your temporary password</h1></div>
      <div class="modal-body">
        <p class="text-secondary">Set a permanent password before accessing documents, requests, or administration.</p>

        <form method="post" action="<?= site_url('change-password') ?>" id="setupPasswordForm">
          <input type="hidden" name="<?= $this->security->get_csrf_token_name() ?>" value="<?= $this->security->get_csrf_hash() ?>">
          <input type="hidden" name="confirmed" value="yes">
          <?php $this->load->view('pages/authentication/password_fields', ['password_prefix'=>'setup']); ?>
        </form>
      </div>
      <div class="modal-footer justify-content-between">
        <form method="post" action="<?= site_url('logout') ?>" data-logo-logout>
          <input type="hidden" name="<?= $this->security->get_csrf_token_name() ?>" value="<?= $this->security->get_csrf_hash() ?>">
          <input type="hidden" name="confirmed" value="yes">
          <button class="btn btn-light" type="submit">Sign out</button>
        </form>
        <button class="btn btn-primary" type="submit" form="setupPasswordForm">Set permanent password</button>
      </div>
    </div>
  </div>
</div>
<script src="<?= base_url('assets/js/auth.js') ?>"></script>
<script src="<?= base_url('assets/js/logout-transition.js') ?>"></script>
<script src="<?= base_url('assets/js/motion.js') ?>"></script>
<?php $this->load->view('reusable_components/auth_alert'); ?>
</body>
</html>
