<?php
declare(strict_types=1);
$pageTitle = $pageTitle ?? APP_NAME;
$user = current_user();
$flashes = get_flashes();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> | <?= e(APP_NAME) ?></title>
    <link href="<?= e(app_url('assets/vendor/css/fonts.css')) ?>" rel="stylesheet">
    <link href="<?= e(app_url('assets/vendor/css/bootstrap.min.css')) ?>" rel="stylesheet">
    <link href="<?= e(app_url('assets/vendor/css/quill.snow.css')) ?>" rel="stylesheet">
    <link href="<?= e(app_url('assets/vendor/css/github-dark.min.css')) ?>" rel="stylesheet">
    <link href="<?= e(app_url('assets/css/app.css')) ?>" rel="stylesheet">
</head>
<body>
<div class="page-noise"></div>
<div class="app-shell">
    <nav class="app-navbar navbar navbar-expand-lg">
        <div class="container-fluid app-container">
            <a class="brand" href="<?= e(app_url('dashboard.php')) ?>">
                <span class="brand-mark">N</span>
                <span>
                    <strong><?= e(APP_NAME) ?></strong>
                    <small>Editorial note workspace</small>
                </span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navMenu">
                <div class="ms-auto d-flex align-items-center gap-2 flex-wrap nav-actions">
                    <button class="btn app-btn app-btn-ghost" type="button" data-theme-toggle>Theme</button>
                    <?php if ($user): ?>
                        <a class="btn app-btn app-btn-ghost" href="<?= e(app_url('dashboard.php')) ?>">Home</a>
                        <span class="user-chip"><?= e($user['username']) ?></span>
                        <a class="btn app-btn app-btn-ghost" href="<?= e(app_url('categories.php')) ?>">Categories</a>
                        <form method="post" action="<?= e(app_url('logout.php')) ?>" class="d-inline">
                            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                            <button class="btn app-btn app-btn-primary" type="submit">Logout</button>
                        </form>
                    <?php else: ?>
                        <a class="btn app-btn app-btn-ghost" href="<?= e(app_url('login.php')) ?>">Login</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>
    <main class="app-main app-container py-4 py-lg-5">
        <?php foreach ($flashes as $flashItem): ?>
            <div class="alert app-alert alert-<?= e($flashItem['type']) ?> alert-dismissible fade show" role="alert">
                <?= e($flashItem['message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endforeach; ?>