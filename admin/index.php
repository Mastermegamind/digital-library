<?php
require_once __DIR__ . '/../includes/auth.php';
redirect_legacy_php('admin');
require_admin();

$sections = [
    [
        'title' => 'Dashboard',
        'text' => 'View quick stats and recent activity.',
        'link' => app_path('admin/dashboard'),
        'btn'  => 'Go to Dashboard',
        'icon' => 'chart-line',
        'color' => 'primary',
    ],
    [
        'title' => 'Resources',
        'text' => 'Manage all uploaded learning resources.',
        'link' => app_path('admin/resources'),
        'btn'  => 'Manage Resources',
        'icon' => 'book',
        'color' => 'success',
    ],
    [
        'title' => 'Categories',
        'text' => 'Create or edit resource categories.',
        'link' => app_path('admin/categories'),
        'btn'  => 'Manage Categories',
        'icon' => 'folder-open',
        'color' => 'warning',
    ],
    [
        'title' => 'Users',
        'text' => 'View and manage user accounts.',
        'link' => app_path('admin/users'),
        'btn'  => 'Manage Users',
        'icon' => 'users',
        'color' => 'info',
    ],
    [
        'title' => 'Settings',
        'text' => 'Control registration and verification.',
        'link' => app_path('admin/settings'),
        'btn'  => 'Open Settings',
        'icon' => 'sliders-h',
        'color' => 'purple',
    ],
];

$meta_title = 'Admin Panel - ' . $APP_NAME;
$meta_description = 'Administrative overview and management tools for ' . $APP_NAME . '.';
include __DIR__ . '/../includes/header.php';
?>
<!-- Hero Section -->
<div class="hero-section admin-hero">
    <div class="row align-items-center position-relative">
        <div class="col-lg-8">
            <h1 class="display-4 fw-bold mb-3 page-title">
                <i class="fas fa-shield-alt me-3"></i>Admin Control Panel
            </h1>
            <p class="lead text-muted mb-0">
                Manage your educational library with powerful administrative tools. Control resources, categories, and users all in one place.
            </p>
        </div>
        <div class="col-lg-4 mt-4 mt-lg-0 text-lg-end">
            <a href="<?= h(app_path('')) ?>" class="btn btn-outline-primary btn-lg">
                <i class="fas fa-home me-2"></i>Back to Library
            </a>
        </div>
    </div>
</div>

<!-- Quick Stats -->
<?php
$userCount = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$resourceCount = (int)$pdo->query("SELECT COUNT(*) FROM resources")->fetchColumn();
$categoryCount = (int)$pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
?>

<div class="quick-stats">
    <div class="row g-3">
        <div class="col-md-4">
            <div class="stat-item">
                <div class="stat-value"><?= number_format($userCount) ?></div>
                <div class="stat-label">
                    <i class="fas fa-users me-1"></i>Users
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-item">
                <div class="stat-value"><?= number_format($resourceCount) ?></div>
                <div class="stat-label">
                    <i class="fas fa-book me-1"></i>Resources
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-item">
                <div class="stat-value"><?= number_format($categoryCount) ?></div>
                <div class="stat-label">
                    <i class="fas fa-folder me-1"></i>Categories
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Admin Sections Grid -->
<div class="row g-4">
    <?php foreach ($sections as $section): ?>
        <div class="col-md-6 col-lg-3">
            <div class="section-card color-<?= $section['color'] ?>">
                <div class="section-icon">
                    <i class="fas fa-<?= $section['icon'] ?>"></i>
                </div>
                
                <h5 class="section-title"><?= h($section['title']) ?></h5>
                
                <p class="section-description">
                    <?= h($section['text']) ?>
                </p>
                
                <a href="<?= h($section['link']) ?>" class="btn btn-<?= $section['color'] ?> section-btn w-100">
                    <i class="fas fa-arrow-right me-2"></i><?= h($section['btn']) ?>
                </a>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Additional Info Section -->
<div class="mt-5">
    <div class="row g-4">
        <div class="col-md-6">
            <div class="section-card color-purple">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <div class="section-card-badge">
                        <i class="fas fa-info-circle text-white section-card-badge-icon"></i>
                    </div>
                    <div>
                        <h5 class="mb-1 fw-bold">System Information</h5>
                        <p class="mb-0 text-muted small">Current system status</p>
                    </div>
                </div>
                <div class="mt-3">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">PHP Version:</span>
                        <span class="fw-bold"><?= phpversion() ?></span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted">Database:</span>
                        <span class="fw-bold">MySQL/MariaDB</span>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="section-card color-pink">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <div class="section-card-badge">
                        <i class="fas fa-life-ring text-white section-card-badge-icon"></i>
                    </div>
                    <div>
                        <h5 class="mb-1 fw-bold">Need Help?</h5>
                        <p class="mb-0 text-muted small">Get support and guidance</p>
                    </div>
                </div>
                <div class="mt-3">
                    <p class="text-muted mb-3">
                        Having issues or questions? Check out our documentation or contact support.
                    </p>
                    <a href="#" class="btn btn-outline-secondary w-100">
                        <i class="fas fa-book me-2"></i>View Documentation
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
