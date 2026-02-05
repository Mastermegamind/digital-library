<?php
require_once __DIR__ . '/../includes/auth.php';
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

include __DIR__ . '/../includes/header.php';
?>

<style>
    .page-header {
        background: linear-gradient(135deg, rgba(37, 99, 235, 0.1), rgba(59, 130, 246, 0.05));
        border-radius: 20px;
        padding: 2.5rem;
        margin-bottom: 2rem;
        border: 1px solid rgba(37, 99, 235, 0.1);
    }

    .stat-card {
        background: white;
        border-radius: 16px;
        padding: 2rem;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07);
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        height: 100%;
        position: relative;
        overflow: hidden;
    }

    .stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, var(--gradient-from), var(--gradient-to));
    }

    .stat-card:hover {
        transform: translateY(-8px);
        box-shadow: 0 12px 24px rgba(0, 0, 0, 0.15);
    }

    .stat-icon {
        width: 70px;
        height: 70px;
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2rem;
        color: white;
        margin-bottom: 1.5rem;
        background: linear-gradient(135deg, var(--gradient-from), var(--gradient-to));
        box-shadow: 0 8px 16px rgba(0, 0, 0, 0.15);
    }

    .stat-number {
        font-size: 2.5rem;
        font-weight: 800;
        color: var(--text-primary);
        margin-bottom: 0.5rem;
        line-height: 1;
    }

    .stat-label {
        font-size: 0.875rem;
        color: var(--text-secondary);
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .quick-actions-card {
        background: white;
        border-radius: 16px;
        padding: 2rem;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07);
    }

    .quick-action-btn {
        padding: 1rem 1.5rem;
        border-radius: 12px;
        font-weight: 600;
        transition: all 0.3s ease;
        border: 2px solid var(--border-color);
        background: white;
        color: var(--text-primary);
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.75rem;
    }

    .quick-action-btn:hover {
        border-color: var(--primary-color);
        background: var(--primary-color);
        color: white;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
    }

    .quick-action-btn i {
        font-size: 1.25rem;
    }

    .recent-resources-card {
        background: white;
        border-radius: 16px;
        padding: 2rem;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07);
    }

    .resource-item {
        padding: 1rem;
        border-radius: 12px;
        transition: all 0.3s ease;
        border: 1px solid transparent;
    }

    .resource-item:hover {
        background: rgba(37, 99, 235, 0.03);
        border-color: var(--primary-color);
        transform: translateX(4px);
    }

    .resource-cover-mini {
        width: 60px;
        height: 60px;
        object-fit: cover;
        border-radius: 8px;
        border: 2px solid var(--border-color);
    }

    .type-badge {
        padding: 0.25rem 0.75rem;
        border-radius: 12px;
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .section-title {
        font-size: 1.25rem;
        font-weight: 700;
        color: var(--text-primary);
        margin-bottom: 1.5rem;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .section-title::after {
        content: '';
        flex: 1;
        height: 2px;
        background: linear-gradient(90deg, var(--border-color), transparent);
    }

    .welcome-card {
        background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
        color: white;
        border-radius: 16px;
        padding: 2rem;
        margin-bottom: 2rem;
        box-shadow: 0 8px 20px rgba(37, 99, 235, 0.3);
    }
</style>

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
        <div class="stat-card" style="--gradient-from: #2563eb; --gradient-to: #3b82f6;">
            <div class="stat-icon">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-number"><?= number_format($stats['users']) ?></div>
            <div class="stat-label">Total Users</div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="stat-card" style="--gradient-from: #10b981; --gradient-to: #059669;">
            <div class="stat-icon">
                <i class="fas fa-book"></i>
            </div>
            <div class="stat-number"><?= number_format($stats['resources']) ?></div>
            <div class="stat-label">Resources</div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="stat-card" style="--gradient-from: #f59e0b; --gradient-to: #d97706;">
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
    <h5 class="section-title">
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
    <h5 class="section-title">
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
                        <div class="fw-bold text-truncate" style="color: var(--text-primary);">
                            <?= h($resource['title']) ?>
                        </div>
                        <div class="d-flex align-items-center gap-2 mt-1">
                            <span class="type-badge" style="background: rgba(37, 99, 235, 0.1); color: var(--primary-color);">
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
