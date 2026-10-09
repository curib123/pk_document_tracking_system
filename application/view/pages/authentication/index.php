
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#131b2e">

    <title>Sign In · PK Document Control</title>

    <link rel="icon" href="<?= base_url('assets/images/peanut-kisses.jpg') ?>">

     <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">

    <link rel="stylesheet" href="<?= base_url('assets/css/app.css') ?>">
</head>

<body class="login-page"
      style="background-image: url('<?= base_url('assets/images/building.jpg') ?>')">

    <main class="login-shell">

        <!-- Left Welcome Section -->
        <section class="login-cover" aria-labelledby="loginWelcomeHeading">

            <div class="login-intro">

                <span class="login-kicker">
                    PEANUT KISSES · DOCUMENT CONTROL
                </span>

                <h1 id="loginWelcomeHeading" class="login-welcome">
                   Secure access for your document control center.
                </h1>

                <p>
                    Manage the full document lifecycle from one secure workspace.
                    This portal keeps records organized, routes access by role,
                    and brings documents, storage, users, and permissions together
                    in a clean panel experience.
                </p>

                     <div class="container-fluid px-0 mt-3">
    <div class="row g-3">

        <div class="col-4">
            <div class="h-100  text-white
                        border border-light rounded-2 p-2
                        d-flex align-items-center gap-2">

                <i class="fa-solid fa-folder-open text-light fs-6"></i>

                <div>
                    <h6 class="small fw-bold mb-1">Track records</h6>
                    <p class="mb-0" style="font-size: 10px;">Manage documents</p>
                </div>

            </div>
        </div>

        <div class="col-4">
            <div class="h-100 text-white
                        border border-light rounded-2 p-2
                        d-flex align-items-center gap-2">

                <i class="fa-solid fa-magnifying-glass-location text-light fs-6"></i>

                <div>
                    <h6 class="small fw-bold mb-1">Locate faster</h6>
                    <p class="mb-0" style="font-size: 10px;">Find files easily</p>
                </div>

            </div>
        </div>

        <div class="col-4">
            <div class="h-100  text-white
                        border border-light rounded-2 p-2
                        d-flex align-items-center gap-2">

                <i class="fa-solid fa-shield-halved text-light fs-6"></i>

                <div>
                    <h6 class="small fw-bold mb-1">Work securely</h6>
                    <p class="mb-0" style="font-size: 10px;">Secure access</p>
                </div>

            </div>
        </div>

    </div>
</div>

            </div>
        </section>

        <!-- Right Login Section -->
        <section class="login-panel" aria-labelledby="loginHeading">

            <div class="login-card">

                <img class="login-logo"
                     src="<?= base_url('assets/images/peanut-kisses.jpg') ?>"
                     alt="Peanut Kisses logo">

                <span class="eyebrow">Welcome back</span>

                <h2 id="loginHeading">
                    Sign in to your account
                </h2>

                <p class="login-subtitle">
                    Enter your credentials to continue.
                </p>

                <?php if ($this->session->flashdata('notice')): ?>
                    <div class="alert alert-danger login-error" role="alert">
                        <?= html_escape($this->session->flashdata('notice')) ?>
                    </div>
                <?php endif; ?>

                <form method="post" action="<?= site_url('login') ?>">

                    <!-- CSRF Protection -->
                    <input type="hidden"
                           name="<?= $this->security->get_csrf_token_name() ?>"
                           value="<?= $this->security->get_csrf_hash() ?>">

                    <div class="mb-3">
                        <label class="form-label" for="login">
                            Username
                        </label>

                        <input class="form-control login-input"
                               id="login"
                               name="login"
                               type="text"
                               placeholder="Enter your username"
                               autocomplete="username"
                               required
                               autofocus>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="password">
                            Password
                        </label>

                        <input class="form-control login-input"
                               id="password"
                               name="password"
                               type="password"
                               placeholder="Enter your password"
                               autocomplete="current-password"
                               required>
                    </div>

                    <button class="btn btn-primary login-submit w-100"
                            type="submit">
                        Sign In
                    </button>

                </form>

                <p class="login-footnote">
                    Account access is managed by your system administrator.
                </p>

            </div>
        </section>

    </main>

</body>
</html>