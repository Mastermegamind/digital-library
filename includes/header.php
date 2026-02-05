<?php
// includes/header.php
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

$headerScheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$headerHost = $_SERVER['HTTP_HOST'] ?? 'localhost';
$headerCurrentUri = $_SERVER['REQUEST_URI'] ?? app_path('');

$toAbsoluteUrl = static function (?string $value) use ($headerScheme, $headerHost): string {
    $value = trim((string)$value);
    if ($value === '') {
        return $headerScheme . '://' . $headerHost . '/';
    }
    if (preg_match('#^https?://#i', $value)) {
        return $value;
    }
    if ($value[0] !== '/') {
        $value = '/' . $value;
    }
    return $headerScheme . '://' . $headerHost . $value;
};

$headerMetaTitle = trim((string)($meta_title ?? $APP_NAME));
if ($headerMetaTitle === '') {
    $headerMetaTitle = $APP_NAME;
}

$headerMetaSiteName = trim((string)($meta_site_name ?? ($FULL_APP_NAME ?? $APP_NAME)));
if ($headerMetaSiteName === '') {
    $headerMetaSiteName = $APP_NAME;
}

$headerMetaDescription = trim((string)($meta_description ?? ('Explore educational resources from ' . $headerMetaSiteName . '.')));
if ($headerMetaDescription === '') {
    $headerMetaDescription = 'Explore educational resources from ' . $headerMetaSiteName . '.';
}

$headerMetaType = trim((string)($meta_type ?? 'website'));
if ($headerMetaType === '') {
    $headerMetaType = 'website';
}

$headerMetaUrl = trim((string)($meta_url ?? ($headerScheme . '://' . $headerHost . $headerCurrentUri)));
if ($headerMetaUrl === '') {
    $headerMetaUrl = $headerScheme . '://' . $headerHost . '/';
}
$headerMetaUrl = $toAbsoluteUrl($headerMetaUrl);

$headerMetaImage = trim((string)($meta_image ?? app_path('assets/images/seo.png')));
if ($headerMetaImage === '') {
    $headerMetaImage = app_path('assets/images/seo.png');
}
$headerMetaImage = $toAbsoluteUrl($headerMetaImage);

$headerFavicon = trim((string)($favicon_path ?? app_path('assets/images/favicon.png')));
if ($headerFavicon === '') {
    $headerFavicon = app_path('assets/images/favicon.png');
}
$headerFavicon = $toAbsoluteUrl($headerFavicon);

$body_class = trim((string)($body_class ?? ''));
$body_class_attr = 'd-flex flex-column min-vh-100';
if ($body_class !== '') {
    $body_class_attr .= ' ' . $body_class;
}

// Get user's dark mode preference
$headerDarkMode = false;
if (is_logged_in()) {
    $headerUser = current_user();
    if ($headerUser) {
        $headerDarkMode = get_user_dark_mode($headerUser['id']);
    }
}
?>
<!doctype html>
<html lang="en" data-theme="<?= $headerDarkMode ? 'dark' : 'light' ?>">
<head>
    <meta charset="utf-8">
    <title><?php echo h($headerMetaTitle); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?php echo h($headerMetaDescription); ?>">
    <link rel="canonical" href="<?php echo h($headerMetaUrl); ?>">

    <meta property="og:type" content="<?php echo h($headerMetaType); ?>">
    <meta property="og:site_name" content="<?php echo h($headerMetaSiteName); ?>">
    <meta property="og:title" content="<?php echo h($headerMetaTitle); ?>">
    <meta property="og:description" content="<?php echo h($headerMetaDescription); ?>">
    <meta property="og:url" content="<?php echo h($headerMetaUrl); ?>">
    <meta property="og:image" content="<?php echo h($headerMetaImage); ?>">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?php echo h($headerMetaTitle); ?>">
    <meta name="twitter:description" content="<?php echo h($headerMetaDescription); ?>">
    <meta name="twitter:image" content="<?php echo h($headerMetaImage); ?>">

    <link rel="icon" type="image/png" href="<?php echo h($headerFavicon); ?>">
    <link rel="shortcut icon" href="<?php echo h($headerFavicon); ?>">
    <link rel="apple-touch-icon" href="<?php echo h($headerFavicon); ?>">

    <link href="<?php echo h(app_path('assets/css/bootstrap.min.css')); ?>" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo h(app_path('assets/css/all.min.css')); ?>">
    <link rel="stylesheet" href="<?php echo h(app_path('assets/css/inter.css')); ?>">
    <link rel="stylesheet" href="<?php echo h(app_path('assets/css/components.css')); ?>">
</head>
<body class="<?php echo h($body_class_attr); ?>">

<!-- Toast Container -->
<div class="toast-container position-fixed top-0 end-0 p-3">
    <?php $flash = flash_message(); if ($flash): ?>
    <div class="toast align-items-center text-bg-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?> border-0 show" role="alert">
        <div class="d-flex">
            <div class="toast-body">
                <i class="fas fa-<?= $flash['type'] === 'success' ? 'check-circle' : 'exclamation-circle' ?> me-2"></i>
                <strong><?= $flash['type'] === 'success' ? 'Success' : 'Error' ?>!</strong> <?= h($flash['message']) ?>
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Modern Navbar -->
<nav class="navbar navbar-expand-lg sticky-top">
    <div class="container">
        <a class="navbar-brand" href="<?= h(app_path('')) ?>">
            <img src="<?= app_path('assets/images/logo.png') ?>" alt="Logo" height="48" onerror="this.style.display='none'">
            <!-- <span><?= h($APP_NAME) ?></span> -->
        </a>
        
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav mx-auto">
                <?php if (is_logged_in()): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= h(app_path('')) ?>">
                            <i class="fas fa-books me-2"></i>Library
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= h(app_path('dashboard')) ?>">
                            <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= h(app_path('bookmarks')) ?>">
                            <i class="fas fa-bookmark me-2"></i>Bookmarks
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= h(app_path('submit')) ?>">
                            <i class="fas fa-upload me-2"></i>Submit
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= h(app_path('my-submissions')) ?>">
                            <i class="fas fa-list me-2"></i>My Submissions
                        </a>
                    </li>
                    <?php if (is_admin()): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= h(app_path('admin')) ?>">
                                <i class="fas fa-shield-alt me-2"></i>Admin
                            </a>
                        </li>
                    <?php endif; ?>
                <?php endif; ?>
            </ul>
            
            <ul class="navbar-nav ms-auto align-items-center">
                <!-- Dark Mode Toggle -->
                <li class="nav-item me-2">
                    <button class="dark-mode-toggle" id="darkModeToggle" title="Toggle dark mode">
                        <i class="fas <?= $headerDarkMode ? 'fa-sun' : 'fa-moon' ?>" id="darkModeIcon"></i>
                    </button>
                </li>
                <?php if (is_logged_in()): $u = current_user(); ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle d-flex align-items-center gap-2" href="#" role="button" data-bs-toggle="dropdown">
                            <?php $avatar = !empty($u['profile_image_path']) ? app_path($u['profile_image_path']) : null; ?>
                            <?php if ($avatar): ?>
                                <img src="<?= h($avatar) ?>" class="user-avatar" alt="Avatar">
                            <?php else: ?>
                                <div class="user-initial"><?= strtoupper(substr($u['name'], 0, 1)) ?></div>
                            <?php endif; ?>
                            <div class="d-none d-md-block">
                                <div class="user-name"><?= h($u['name']) ?></div>
                                <span class="user-role"><?= h($u['role']) ?></span>
                            </div>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="<?= h(app_path('logout')) ?>">
                                <i class="fas fa-sign-out-alt me-2"></i>Logout
                            </a></li>
                        </ul>
                    </li>
                <?php else: ?>
                    <?php if (is_registration_enabled()): ?>
                        <li class="nav-item me-2">
                            <a class="btn btn-outline-primary" href="<?= h(app_path('register')) ?>">
                                <i class="fas fa-user-plus me-2"></i>Register
                            </a>
                        </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a class="btn btn-primary" href="<?= h(app_path('login')) ?>">
                            <i class="fas fa-sign-in-alt me-2"></i>Login
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<div class="main-container flex-grow-1">
    <div class="container">
<?php
if ($flash) echo "<script>document.addEventListener('DOMContentLoaded',()=>{var t=document.querySelector('.toast');if(t){new bootstrap.Toast(t,{autohide:true,delay:4000}).show()}});</script>";
?>
