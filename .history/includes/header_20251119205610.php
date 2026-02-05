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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2563eb;
            --secondary-color: #1e40af;
            --accent-color: #3b82f6;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --light-bg: #f8fafc;
            --card-bg: #ffffff;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --border-color: #e2e8f0;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            background-attachment: fixed;
            color: var(--text-primary);
            min-height: 100vh;
        }

        /* Modern Navbar */
        .navbar {
            background: rgba(255, 255, 255, 0.98) !important;
            backdrop-filter: blur(20px);
            box-shadow: 0 4px 30px rgba(0, 0, 0, 0.08);
            border-bottom: 1px solid rgba(255, 255, 255, 0.3);
            padding: 1rem 0;
        }

        .navbar-brand {
            font-weight: 700;
            font-size: 1.5rem;
            color: var(--primary-color) !important;
            letter-spacing: -0.5px;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            transition: all 0.3s ease;
        }

        .navbar-brand:hover {
            transform: translateY(-2px);
        }

        .navbar-brand img {
            border-radius: 12px;
            box-shadow: var(--shadow);
        }

        .nav-link {
            color: var(--text-primary) !important;
            font-weight: 500;
            padding: 0.5rem 1rem !important;
            border-radius: 8px;
            transition: all 0.3s ease;
            position: relative;
        }

        .nav-link:hover {
            background: var(--light-bg);
            color: var(--primary-color) !important;
        }

        .nav-link.active {
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
            color: white !important;
        }

        /* User Avatar */
        .user-avatar {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid white;
            box-shadow: var(--shadow);
            transition: all 0.3s ease;
        }

        .user-avatar:hover {
            transform: scale(1.1);
            box-shadow: var(--shadow-lg);
        }

        .user-initial {
            width: 42px;
            height: 42px;
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.1rem;
            box-shadow: var(--shadow);
            transition: all 0.3s ease;
        }

        .user-initial:hover {
            transform: scale(1.1);
            box-shadow: var(--shadow-lg);
        }

        .user-name {
            font-weight: 600;
            color: var(--text-primary);
        }

        .user-role {
            font-size: 0.75rem;
            color: var(--text-secondary);
            background: var(--light-bg);
            padding: 0.125rem 0.5rem;
            border-radius: 12px;
        }

        /* Toast Notifications */
        .toast-container {
            z-index: 9999;
        }

        .toast {
            backdrop-filter: blur(10px);
            border: none;
            border-radius: 12px;
            box-shadow: var(--shadow-xl);
        }

        .toast-body {
            padding: 1rem 1.5rem;
            font-weight: 500;
        }

        /* Buttons */
        .btn {
            font-weight: 600;
            padding: 0.625rem 1.5rem;
            border-radius: 10px;
            transition: all 0.3s ease;
            border: none;
            text-transform: none;
            letter-spacing: 0.3px;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
            box-shadow: 0 4px 15px rgba(37, 99, 235, 0.3);
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, var(--secondary-color), var(--primary-color));
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(37, 99, 235, 0.4);
        }

        .btn-success {
            background: linear-gradient(135deg, #10b981, #059669);
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
        }

        .btn-success:hover {
            background: linear-gradient(135deg, #059669, #047857);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(16, 185, 129, 0.4);
        }

        .btn-outline-primary {
            border: 2px solid var(--primary-color);
            color: var(--primary-color);
            background: transparent;
        }

        .btn-outline-primary:hover {
            background: var(--primary-color);
            color: white;
            transform: translateY(-2px);
        }

        /* Main Container */
        .main-container {
            background: var(--light-bg);
            min-height: calc(100vh - 200px);
            padding: 2rem 0;
            border-radius: 30px 30px 0 0;
            margin-top: 2rem;
            box-shadow: 0 -10px 40px rgba(0, 0, 0, 0.1);
        }

        /* Cards */
        .card {
            border: none;
            border-radius: 16px;
            overflow: hidden;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            background: var(--card-bg);
            box-shadow: var(--shadow);
        }

        .card:hover {
            transform: translateY(-12px);
            box-shadow: var(--shadow-xl);
        }

        /* Cover Image */
        .cover-img {
            width: 100%;
            height: 280px;
            object-fit: cover;
            transition: all 0.4s ease;
        }

        .card:hover .cover-img {
            transform: scale(1.05);
        }

        /* Footer */
        footer {
            background: linear-gradient(135deg, #1e293b, #334155);
            color: white;
            padding: 2rem 0;
            margin-top: auto;
            box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.1);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .navbar-brand {
                font-size: 1.25rem;
            }
            
            .main-container {
                border-radius: 20px 20px 0 0;
                margin-top: 1rem;
            }
        }

        /* Loading Animation */
        @keyframes shimmer {
            0% { background-position: -1000px 0; }
            100% { background-position: 1000px 0; }
        }

        .skeleton {
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 1000px 100%;
            animation: shimmer 2s infinite;
        }
    </style>
</head>
<body class="d-flex flex-column min-vh-100">

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
        <a class="navbar-brand" href="<?= h(app_path('index.php')) ?>">
            <img src="<?= app_path('assets/logo.png') ?>" alt="Logo" height="48" onerror="this.style.display='none'">
            <span><?= h($APP_NAME) ?></span>
        </a>
        
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav mx-auto">
                <?php if (is_logged_in()): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= h(app_path('index.php')) ?>">
                            <i class="fas fa-books me-2"></i>Library
                        </a>
                    </li>
                    <?php if (is_admin()): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= h(app_path('admin/index.php')) ?>">
                                <i class="fas fa-shield-alt me-2"></i>Admin
                            </a>
                        </li>
                    <?php endif; ?>
                <?php endif; ?>
            </ul>
            
            <ul class="navbar-nav ms-auto align-items-center">
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
                            <li><a class="dropdown-item" href="<?= h(app_path('logout.php')) ?>">
                                <i class="fas fa-sign-out-alt me-2"></i>Logout
                            </a></li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="btn btn-primary" href="<?= h(app_path('login.php')) ?>">
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