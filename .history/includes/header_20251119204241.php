<?php
// includes/header.php
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title><?php echo h($APP_NAME); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root {
            --primary-blue: #284eb9;
            --secondary-blue: #4456a6;
            --accent-blue: #3a8ed4;
            --light-blue: #E6F0FA;
        }
        body { background: var(--light-blue); }
        .navbar { background: var(--primary-blue) !important; }
        .btn-primary { background: var(--primary-blue); border-color: var(--primary-blue); }
        .btn-primary:hover { background: var(--secondary-blue); border-color: var(--secondary-blue); }
        .btn-outline-primary { border-color: var(--accent-blue); color: var(--accent-blue); }
        .btn-outline-primary:hover { background: var(--accent-blue); color: white; }
        .card { transition: transform .3s, box-shadow .3s; }
        .card:hover { transform: translateY(-8px); box-shadow: 0 20px 30px rgba(0,0,0,0.15) !important; }
        .cover-img { object-fit: cover; height: 280px; cursor: zoom-in; }
        .toast-container { z-index: 1100; }
    </style>
</head>
<body class="min-vh-100 d-flex flex-column">

<!-- Toast Container (replaces SweetAlert2) -->
<div class="toast-container position-fixed top-0 end-0 p-3">
    <?php $flash = flash_message(); if ($flash): ?>
    <div class="toast align-items-center text-bg-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?> border-0" role="alert" aria-live="assertive" aria-atomic="true" data-bs-autohide="true" data-bs-delay="4000">
        <div class="d-flex">
            <div class="toast-body">
                <strong><?= $flash['type'] === 'success' ? 'Success' : 'Error' ?>!</strong> <?= h($flash['message']) ?>
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    </div>
    <?php endif; ?>
</div>

<nav class="navbar navbar-expand-lg navbar-dark shadow-sm">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center gap-3" href="<?= h(app_path('index.php')) ?>">
            <!-- Logo Space -->
            <img src="<?= app_path('assets/logo.png') ?>" alt="Logo" height="45">
            <span class="fw-bold"><?= h($APP_NAME) ?></span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <?php if (is_logged_in()): ?>
                    <li class="nav-item"><a class="nav-link" href="<?= h(app_path('index.php')) ?>">Library</a></li>
                    <?php if (is_admin()): ?>
                        <li class="nav-item"><a class="nav-link" href="<?= h(app_path('admin/index.php')) ?>">Admin</a></li>
                    <?php endif; ?>
                <?php endif; ?>
            </ul>
            <ul class="navbar-nav ms-auto align-items-center">
                <?php if (is_logged_in()): $u = current_user(); ?>
                    <?php $avatar = !empty($u['profile_image_path']) ? app_path($u['profile_image_path']) : null; ?>
                    <li class="nav-item d-flex align-items-center gap-2">
                        <?php if ($avatar): ?>
                            <img src="<?= h($avatar) ?>" class="rounded-circle border border-light" width="38" height="38" style="object-fit:cover;">
                        <?php else: ?>
                            <div class="bg-light text-primary rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width:38px;height:38px;">
                                <?= strtoupper(substr($u['name'],0,1)) ?>
                            </div>
                        <?php endif; ?>
                        <span class="text-white small d-none d-md-inline"><?= h($u['name']) ?> <em class="text-light">(<?= h($u['role']) ?>)</em></span>
                    </li>
                    <li class="nav-item ms-2">
                        <a class="nav-link" href="<?= h(app_path('logout.php')) ?>">Logout</a>
                    </li>
                <?php else: ?>
                    <li class="nav-item"><a class="nav-link" href="<?= h(app_path('login.php')) ?>">Login</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<div class="container flex-grow-1 py-4">
<?php
// Auto-show toasts on page load
if ($flash) echo "<script>document.addEventListener('DOMContentLoaded',()=>{new bootstrap.Toast(document.querySelector('.toast')).show()});</script>";
?>