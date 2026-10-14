<div class="login-transition" id="loginTransition" aria-hidden="true">
    <svg class="login-transition-veil" id="loginTransitionVeil" xmlns="http://www.w3.org/2000/svg"
        preserveAspectRatio="none">
        <defs>
            <mask id="loginTransitionMask" maskUnits="userSpaceOnUse" style="mask-type:luminance">
                <rect class="login-transition-mask-field" width="100%" height="100%" />
                <circle class="login-transition-aperture" id="loginTransitionAperture" r="1" />
            </mask>
        </defs>
        <rect class="login-transition-canvas" width="100%" height="100%" mask="url(#loginTransitionMask)" />
    </svg>
    <span class="login-transition-glow" id="loginTransitionGlow"></span>
    <img class="login-transition-logo" id="loginTransitionLogo" src="<?= base_url('assets/images/peanut-kisses.png') ?>"
        width="452" height="195" alt="" decoding="async" fetchpriority="high">
</div>