<div class="logout-transition" id="logoutTransition" aria-hidden="true"
     data-login-url="<?= html_escape(site_url('login')) ?>"
     data-status-url="<?= html_escape(site_url('login').'?session_status=1') ?>"
     data-expiration="<?= (int)config_item('sess_expiration') ?>">
    <span class="logout-transition-veil" id="logoutTransitionVeil"></span>
    <span class="logout-transition-glow" id="logoutTransitionGlow"></span>
    <?php for ($i=0;$i<4;$i++): ?><span class="logout-shape"></span><?php endfor; ?>
    <img class="logout-transition-logo" id="logoutTransitionLogo"
         src="<?= base_url('assets/images/peanut-kisses.png') ?>" width="452" height="195" alt="" decoding="async">
</div>
