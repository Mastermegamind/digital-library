<?php
require_once __DIR__ . '/../includes/auth.php';
redirect_legacy_php('admin/dashboard');
require_admin();

$stats = [];
$stats['users'] = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$stats['resources'] = (int)$pdo->query("SELECT COUNT(*) FROM resources")->fetchColumn();
$stats['categories'] = (int)$pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();

// Get recent resources
$recentResources = $pdo->query("SELECT r.*, c.name AS category_name 
    FROM resources r 
    LEFT JOIN categories c ON r.category_id = c.id 
    ORDER BY r.created_at DESC 
    LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);

$meta_title = 'Admin Dashboard - ' . $APP_NAME;
$meta_description = 'Administrative dashboard overview for ' . $APP_NAME . '.';
include __DIR__ . '/../includes/header.php';
?>
<!-- Welcome Card -->
<div class="welcome-card">
    <div class="row align-items-center">
        <div class="col-lg-8">
            <h2 class="fw-bold mb-3">
                <i class="fas fa-chart-line me-3"></i>Admin Dashboard
            </h2>
            <p class="mb-0 opacity-90">
                Welcome back! Here's an overview of CONS-UNTH E-LIBRARY management system.
            </p>
        </div>
        <div class="col-lg-4 text-lg-end mt-3 mt-lg-0">
            <div class="d-flex gap-2 justify-content-lg-end">
                <a href="<?= h(app_path('')) ?>" class="btn btn-light">
                    <i class="fas fa-home me-2"></i>View Library
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row g-4 mb-4">
    <div class="col-md-4">
        <div class="stat-card admin-stat-card stat-gradient-primary">
            <div class="stat-icon">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-number"><?= number_format($stats['users']) ?></div>
            <div class="stat-label">Total Users</div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="stat-card admin-stat-card stat-gradient-success">
            <div class="stat-icon">
                <i class="fas fa-book"></i>
            </div>
            <div class="stat-number"><?= number_format($stats['resources']) ?></div>
            <div class="stat-label">Resources</div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="stat-card admin-stat-card stat-gradient-warning">
            <div class="stat-icon">
                <i class="fas fa-folder-open"></i>
            </div>
            <div class="stat-number"><?= number_format($stats['categories']) ?></div>
            <div class="stat-label">Categories</div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="quick-actions-card mb-4">
    <h5 class="section-title-divider">
        <i class="fas fa-bolt"></i>
        Quick Actions
    </h5>
    
    <div class="d-flex flex-wrap gap-3">
        <a href="<?= h(app_path('admin/resource/add')) ?>" class="quick-action-btn">
            <i class="fas fa-plus-circle"></i>
            <span>Add Resource</span>
        </a>
        
        <a href="<?= h(app_path('admin/resources')) ?>" class="quick-action-btn">
            <i class="fas fa-book-open"></i>
            <span>Manage Resources</span>
        </a>
        
        <a href="<?= h(app_path('admin/categories')) ?>" class="quick-action-btn">
            <i class="fas fa-folder"></i>
            <span>Manage Categories</span>
        </a>
        
        <a href="<?= h(app_path('admin/users')) ?>" class="quick-action-btn">
            <i class="fas fa-user-cog"></i>
            <span>View Users</span>
        </a>
    </div>
</div>

<!-- Recent Resources -->
<?php if (!empty($recentResources)): ?>
<div class="recent-resources-card">
    <h5 class="section-title-divider">
        <i class="fas fa-clock"></i>
        Recent Resources
    </h5>
    
    <div class="d-flex flex-column gap-3">
        <?php foreach ($recentResources as $resource): ?>
            <div class="resource-item">
                <div class="row align-items-center g-3">
                    <div class="col-auto">
                        <?php 
                            $cover = !empty($resource['cover_image_path']) 
                                ? app_path($resource['cover_image_path']) 
                                : 'https://via.placeholder.com/60x60/667eea/ffffff?text=' . urlencode(substr($resource['title'], 0, 1));
                        ?>
                        <img src="<?= h($cover) ?>" alt="Cover" class="resource-cover-mini">
                    </div>
                    <div class="col">
                        <div class="fw-bold text-truncate">
                            <?= h($resource['title']) ?>
                        </div>
                        <div class="d-flex align-items-center gap-2 mt-1">
                            <span class="type-badge type-badge-primary">
                                <?= strtoupper($resource['type']) ?>
                            </span>
                            <?php if ($resource['category_name']): ?>
                                <small class="text-muted">
                                    <i class="fas fa-folder me-1"></i><?= h($resource['category_name']) ?>
                                </small>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <small class="text-muted">
                            <i class="fas fa-clock me-1"></i>
                            <?= date('M d, Y', strtotime($resource['created_at'])) ?>
                        </small>
                    </div>
                    <div class="col-auto">
                        <a href="<?= h(app_path('viewer/' . $resource['id'])) ?>" class="btn btn-sm btn-primary">
                            <i class="fas fa-eye me-1"></i>View
                        </a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    
    <div class="text-center mt-4">
        <a href="<?= h(app_path('admin/resources')) ?>" class="btn btn-outline-primary">
            <i class="fas fa-list me-2"></i>View All Resources
        </a>
    </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
