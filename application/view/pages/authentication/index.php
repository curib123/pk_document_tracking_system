<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#131b2e">
    <title>Sign In · PK Document Control</title>
    <link rel="icon" href="<?= base_url('assets/images/peanut-kisses.jpg') ?>">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= base_url('assets/css/app.css') ?>">
</head>
<body class="login-page" style="background-image: url('<?= base_url('assets/images/building.jpg') ?>')">
    <main class="login-shell">
        <section class="login-cover" aria-labelledby="loginWelcomeHeading">
            <div class="login-intro">
                <span class="login-kicker">PEANUT KISSES · DOCUMENT CONTROL</span>
                <h1 id="loginWelcomeHeading">Secure, organized document workflows.</h1>
                <p>One workspace for tracking records, approvals, and accountability.</p>
            </div>
        </section>

        <section class="login-panel" aria-labelledby="loginHeading">
            <div class="login-card">
                <img class="login-logo"
                     src="<?= base_url('assets/images/peanut-kisses.jpg') ?>"
                     alt="Peanut Kisses logo">
                <span class="eyebrow">Welcome back</span>
                <h2 id="loginHeading">Sign in to your account</h2>
                <p class="login-subtitle">Enter your credentials to continue.</p>

                <?php if ($this->session->flashdata('notice')): ?>
                    <div class="alert alert-danger login-error" role="alert">
                        <?= html_escape($this->session->flashdata('notice')) ?>
                    </div>
                <?php endif; ?>

                <form method="post" action="<?= site_url('login') ?>">
                    <input type="hidden"
                           name="<?= $this->security->get_csrf_token_name() ?>"
                           value="<?= $this->security->get_csrf_hash() ?>">

                    <label class="form-label" for="login">Username</label>
                    <input class="form-control login-input"
                           id="login" name="login" type="text"
                           placeholder="Enter your username"
                           autocomplete="username" required autofocus>

                    <label class="form-label" for="password">Password</label>
                    <input class="form-control login-input"
                           id="password" name="password" type="password"
                           placeholder="Enter your password"
                           autocomplete="current-password" required>

                    <button class="btn btn-primary login-submit" type="submit">Sign In</button>
                </form>

                <p class="login-footnote">
                    Account access is managed by your system administrator.
                </p>
            </div>
        </section>
    </main>
</body>
</html>
