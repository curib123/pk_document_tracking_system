<?php
defined('BASEPATH') OR exit('No direct script access allowed');
$authAlert=$this->session->flashdata('notice');
$authKind=(string)($this->session->flashdata('notice_type')?:'danger');
if (!in_array($authKind,['success','danger','warning','info'],TRUE)) $authKind='info';
?>
<?php if ($authAlert!==NULL && $authAlert!==''): ?>
<dialog class="app-auth-alert app-alert-<?= html_escape($authKind) ?>" id="authAlertDialog"
        role="alertdialog" aria-modal="true" aria-labelledby="authAlertTitle" aria-describedby="authAlertMessage">
    <div class="app-auth-alert-head">
        <span class="app-auth-alert-icon" aria-hidden="true"><?php $this->load->view('reusable_components/alert_icon',['alert_kind'=>$authKind]); ?></span>
        <button class="app-auth-alert-close" type="button" aria-label="Close notification" data-close-alert>&times;</button>
    </div>
    <h2 id="authAlertTitle"><?= $authKind==='success'?'Operation successful':($authKind==='danger'?'Unable to continue':'Notification') ?></h2>
    <p id="authAlertMessage"><?= html_escape((string)$authAlert) ?></p>
    <button type="button" class="btn btn-primary app-auth-alert-action" data-close-alert>Got it</button>
</dialog>
<script>
(function(){
    var alert=document.getElementById('authAlertDialog');
    if (!alert) return;
    alert.querySelectorAll('[data-close-alert]').forEach(function(button){
        button.addEventListener('click',function(){if(alert.open) alert.close();});
    });
    if (typeof alert.showModal==='function') alert.showModal();
    else alert.setAttribute('open','');
})();
</script>
<?php endif; ?>
