<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign In · PK Document Control</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= base_url('assets/css/app.css') ?>">
</head>
<body class="login-page">
    <main class="login-shell">
        <section class="login-cover" style="background-image:linear-gradient(180deg,rgba(17,25,43,.12),rgba(17,25,43,.9)),url('<?= base_url('assets/images/building.jpg') ?>')">
            <div><span class="eyebrow">PEANUT KISSES · DOCUMENT CONTROL</span><h1>Secure, organized document workflows.</h1><p>One workspace for tracking records, approvals and accountability.</p></div>
        </section>
        <section class="login-panel">
            <div class="login-card">
                <img class="login-logo" src="<?= base_url('assets/images/peanut-kisses.jpg') ?>" alt="Peanut Kisses logo">
                <span class="eyebrow text-danger">WELCOME BACK</span>
                <h2>Sign in to your account</h2>
                <p class="text-secondary mb-4">Enter your credentials to continue.</p>
                <?php if ($this->session->flashdata('notice')): ?>
                    <div class="alert alert-danger" role="alert"><?= html_escape($this->session->flashdata('notice')) ?></div>
                <?php endif; ?>
                <form method="post" action="<?= site_url('login') ?>">
                    <input type="hidden" name="<?= $this->security->get_csrf_token_name() ?>" value="<?= $this->security->get_csrf_hash() ?>">
                    <label class="form-label" for="login">Username or Email</label>
                    <input class="form-control mb-3" id="login" type="text" name="login" required autocomplete="username" autofocus>
                    <label class="form-label" for="password">Password</label>
                    <input class="form-control mb-4" id="password" type="password" name="password" required autocomplete="current-password">
                    <button class="btn btn-primary w-100 py-3" type="submit">Sign In</button>
                </form>
                <small class="text-muted d-block mt-4">Account access is managed by your system administrator.</small>
            </div>
        </section>
    </main>
</body>
</html>
