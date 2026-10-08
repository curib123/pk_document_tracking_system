<template id="login-workspace-template">
    <div class="ws-login-scene">
        <div class="ws-login-grid">
            <section class="ws-login-hero">
                <span class="ws-login-pill">Document tracking system</span>
                <h2>Secure access <br>for your <br>document <br>control center.</h2>
                <p>Manage the full document lifecycle from one secure workspace. This portal keeps records organized, routes access by role, and brings documents, storage, users, and permissions together in a clean experience.</p>
                <div class="ws-login-features">
                    <div class="ws-login-feature"><span data-icon="file"></span><div><b>Track records</b><small>Softcopy and physical documents</small></div></div>
                    <div class="ws-login-feature"><span data-icon="pin"></span><div><b>Locate faster</b><small>Mapped storage and file journeys</small></div></div>
                    <div class="ws-login-feature"><span data-icon="shield"></span><div><b>Work securely</b><small>Role-based access and accountability</small></div></div>
                </div>
            </section>
            <section class="ws-login-card">
                <span data-login-title-slot></span>
                <p class="ws-eyebrow">Staff workspace</p>
                <div class="ws-login-card-heading">
                    <img src="<?= ui_escape(ui_url('assets/images/peanut-kisses.webp')) ?>" alt="Peanut Kisses">
                    <div><h3>Welcome back</h3><p>Use your username and password to continue.</p></div>
                </div>
                <div data-login-form-slot></div>
                <div class="ws-login-help">
                    <p>Need an account? <strong>Contact your Document Control Officer.</strong></p>
                    <p><span data-icon="lock"></span>Your activity is protected and recorded for document accountability.</p>
                </div>
            </section>
        </div>
        <footer class="ws-login-footer">
            <span>Document Tracking System (DTS)</span>
            <span>Created by John Paul Curib, Full-stack Developer</span>
            <span>Secure records · Clear ownership · Faster retrieval</span>
        </footer>
    </div>
</template>
<template id="password-visibility-template"><button class="ws-password-toggle" type="button" aria-label="Show password" aria-pressed="false"><span data-icon="eye"></span></button></template>
<template id="remember-username-template"><label class="ws-remember"><input type="checkbox">Remember username on this device</label></template>
