<!doctype html>
<html lang="en" <?= !empty($login_transition) ? ' data-login-transition' : '' ?>>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#cb2035">
    <title><?= html_escape($title) ?> · PK Document Control</title>
    <script src="<?= base_url('assets/js/theme.js') ?>"></script>
    <link rel="icon" href="<?= base_url('assets/images/peanut-kisses.png') ?>">
    <?php $this->load->view('reusable_components/pwa_head'); ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="<?= base_url('assets/css/colors.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/app.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/document-view.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/unified-ux.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/roles-permission.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/action-feedback.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/dashboard.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/motion.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/backup.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/logout-transition.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/plan-enhancements.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/view-modals.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/pwa.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/theme.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/document-header.css') ?>">
    <?php if (!empty($login_transition)): ?>
        <link rel="preload" as="image" href="<?= base_url('assets/images/peanut-kisses.png') ?>">
        <link rel="stylesheet" href="<?= base_url('assets/css/login-transition.css') ?>">
        <style>
            html[data-login-transition]:not([data-login-stage]) .app-shell {
                visibility: hidden
            }
        </style>
        <noscript>
            <style>
                html[data-login-transition] {
                    overflow: auto
                }

                html[data-login-transition] .app-shell {
                    visibility: visible !important
                }

                #loginTransition {
                    display: none
                }
            </style>
        </noscript>
        <script src="<?= base_url('assets/js/login-transition.js') ?>"></script>
    <?php endif; ?>
</head>

<body>
    <?php $this->load->view('reusable_components/logout_transition'); ?>
    <?php if (!empty($login_transition))
        $this->load->view('reusable_components/login_transition'); ?>
