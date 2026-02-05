<?php
require_once __DIR__ . '/../includes/auth.php';
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
];

include __DIR__ . '/../includes/header.php';
?>

<style>
    .hero-section {
        background: linear-gradient(135deg, rgba(37, 99, 235, 0.1), rgba(59, 130, 246, 0.05));
        border-radius: 20px;
        padding: 3rem 2rem;
        margin-bottom: 3rem;
        border: 1px solid rgba(37, 99, 235, 0.1);
        position: relative;
        overflow: hidden;
    }

    .hero-section::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -10%;
        width: 400px;
        height: 400px;
        background: radial-gradient(circle, rgba(37, 99, 235, 0.1) 0%, transparent 70%);
        border-radius: 50%;
    }

    .section-card {
        background: white;
        border-radius: 16px;
        padding: 2rem;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07);
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        height: 100%;
        display: flex;
        flex-direction: column;
        position: relative;
        overflow: hidden;
    }

    .section-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, var(--card-color), var(--card-color-light));
        opacity: 0;
        transition: opacity 0.3s ease;
    }

    .section-card:hover {
        transform: translateY(-12px);
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.12);
    }

    .section-card:hover::before {
        opacity: 1;
    }

    .section-icon {
        width: 80px;
        height: 80px;
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2.25rem;
        color: white;
        margin-bottom: 1.5rem;
        background: linear-gradient(135deg, var(--card-color), var(--card-color-light));
        box-shadow: 0 8px 16px rgba(0, 0, 0, 0.15);
        transition: all 0.3s ease;
    }

    .section-card:hover .section-icon {
        transform: scale(1.1) rotate(5deg);
        box-shadow: 0 12px 24px rgba(0, 0, 0, 0.2);
    }

    .section-title {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--text-primary);
        margin-bottom: 0.75rem;
    }

    .section-description {
        color: var(--text-secondary);
        line-height: 1.6;
        flex-grow: 1;
        margin-bottom: 1.5rem;
    }

    .section-btn {
        padding: 0.875rem 1.75rem;
        border-radius: 12px;
        font-weight: 600;
        transition: all 0.3s ease;
        text-decoration: none;
        display: inline-block;
        text-align: center;
    }

    .btn-primary {
        background: linear-gradient(135deg, #2563eb, #3b82f6);
    }

    .btn-success {
        background: linear-gradient(135deg, #10b981, #059669);
    }

    .btn-warning {
        background: linear-gradient(135deg, #f59e0b, #d97706);
    }

    .btn-info {
        background: linear-gradient(135deg, #06b6d4, #0891b2);
    }

    .section-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
    }

    .color-primary {
        --card-color: #2563eb;
        --card-color-light: #3b82f6;
    }

    .color-success {
        --card-color: #10b981;
        --card-color-light: #059669;
    }

    .color-warning {
        --card-color: #f59e0b;
        --card-color-light: #d97706;
    }

    .color-info {
        --card-color: #06b6d4;
        --card-color-light: #0891b2;
    }

    .quick-stats {
        background: white;
        border-radius: 16px;
        padding: 1.5rem;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07);
        margin-bottom: 2rem;
    }

    .stat-item {
        text-align: center;
        padding: 1rem;
    }

    .stat-value {
        font-size: 2rem;
        font-weight: 800;
        color: var(--primary-color);
        margin-bottom: 0.25rem;
    }

    .stat-label {
        font-size: 0.875rem;
        color: var(--text-secondary);
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    @media (max-width: 768px) {
        .hero-section {
            padding: 2rem 1.5rem;
        }

        .section-icon {
            width: 70px;
            height: 70px;
            font-size: 2rem;
        }
    }
</style>

<!-- Hero Section -->
<div class="hero-section">
    <div class="row align-items-center position-relative">
        <div class="col-lg-8">
            <h1 class="display-4 fw-bold mb-3" style="color: var(--primary-color);">
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
            <div class="section-card" style="--card-color: #8b5cf6; --card-color-light: #a78bfa;">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <div style="width: 60px; height: 60px; background: linear-gradient(135deg, #8b5cf6, #a78bfa); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-info-circle text-white" style="font-size: 1.75rem;"></i>
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
            <div class="section-card" style="--card-color: #ec4899; --card-color-light: #f472b6;">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <div style="width: 60px; height: 60px; background: linear-gradient(135deg, #ec4899, #f472b6); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-life-ring text-white" style="font-size: 1.75rem;"></i>
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
