<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$search = trim($_GET['q'] ?? '');
$category = trim($_GET['category'] ?? '');
$type = trim($_GET['type'] ?? '');

$params = [];
$sql = "SELECT r.*, c.name AS category_name FROM resources r
        LEFT JOIN categories c ON r.category_id = c.id WHERE 1=1";

if ($search !== '') {
    $sql .= " AND (r.title LIKE :q OR r.description LIKE :q)";
    $params[':q'] = '%' . $search . '%';
}

if ($category !== '') {
    $sql .= " AND r.category_id = :cat";
    $params[':cat'] = $category;
}

if ($type !== '') {
    $sql .= " AND r.type = :type";
    $params[':type'] = $type;
}

$sql .= " ORDER BY r.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$resources = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get categories for filter
$categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/includes/header.php';
?>

<style>
    .hero-section {
        background: linear-gradient(135deg, rgba(37, 99, 235, 0.1), rgba(59, 130, 246, 0.05));
        border-radius: 20px;
        padding: 3rem 2rem;
        margin-bottom: 2rem;
        border: 1px solid rgba(37, 99, 235, 0.1);
    }

    .search-container {
        background: white;
        border-radius: 16px;
        padding: 1.5rem;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
        margin-bottom: 2rem;
    }

    .search-input {
        border: 2px solid var(--border-color);
        border-radius: 12px;
        padding: 0.875rem 1.25rem;
        font-size: 1rem;
        transition: all 0.3s ease;
    }

    .search-input:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1);
    }

    .filter-btn {
        border: 2px solid var(--border-color);
        background: white;
        border-radius: 12px;
        padding: 0.875rem 1.5rem;
        font-weight: 600;
        transition: all 0.3s ease;
    }

    .filter-btn:hover, .filter-btn.active {
        border-color: var(--primary-color);
        background: var(--primary-color);
        color: white;
    }

    .resource-card {
        position: relative;
        background: white;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07);
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        height: 100%;
    }

    .resource-card:hover {
        transform: translateY(-10px);
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.12);
    }

    .resource-image-wrapper {
        position: relative;
        height: 280px;
        overflow: hidden;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }

    .resource-image {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.6s ease;
    }

    .resource-card:hover .resource-image {
        transform: scale(1.1);
    }

    .resource-badge {
        position: absolute;
        top: 12px;
        right: 12px;
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(10px);
        padding: 0.5rem 1rem;
        border-radius: 20px;
        font-weight: 700;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }

    .resource-body {
        padding: 1.5rem;
    }

    .resource-title {
        font-size: 1.125rem;
        font-weight: 700;
        color: var(--text-primary);
        margin-bottom: 0.5rem;
        line-height: 1.4;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .resource-category {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.875rem;
        color: var(--text-secondary);
        background: var(--light-bg);
        padding: 0.375rem 0.875rem;
        border-radius: 8px;
        margin-bottom: 0.75rem;
    }

    .resource-description {
        font-size: 0.875rem;
        color: var(--text-secondary);
        line-height: 1.6;
        margin-bottom: 1rem;
        display: -webkit-box;
        -webkit-line-clamp: 3;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .resource-actions {
        display: flex;
        gap: 0.5rem;
    }

    .stat-card {
        background: white;
        border-radius: 16px;
        padding: 1.5rem;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07);
        text-align: center;
        transition: all 0.3s ease;
    }

    .stat-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 16px rgba(0, 0, 0, 0.12);
    }

    .stat-icon {
        width: 60px;
        height: 60px;
        margin: 0 auto 1rem;
        background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.75rem;
        color: white;
    }

    .stat-number {
        font-size: 2rem;
        font-weight: 800;
        color: var(--text-primary);
        margin-bottom: 0.25rem;
    }

    .stat-label {
        font-size: 0.875rem;
        color: var(--text-secondary);
        font-weight: 600;
    }

    .empty-state {
        text-align: center;
        padding: 4rem 2rem;
    }

    .empty-icon {
        font-size: 5rem;
        color: var(--text-secondary);
        opacity: 0.3;
        margin-bottom: 1.5rem;
    }

    .zoom-modal .modal-content {
        background: transparent;
        border: none;
    }

    .zoom-modal img {
        border-radius: 16px;
        box-shadow: 0 25px 50px rgba(0, 0, 0, 0.3);
    }

    @media (max-width: 768px) {
        .hero-section {
            padding: 2rem 1rem;
        }
        
        .resource-image-wrapper {
            height: 220px;
        }
    }
</style>

<!-- Hero Section -->
<div class="hero-section">
    <div class="row align-items-center">
        <div class="col-lg-8">
            <h1 class="display-4 fw-bold mb-3" style="color: var(--primary-color);">
                <i class="fas fa-book-reader me-3"></i>Welcome to CONS-UNTH E-LIBRARY
            </h1>
            <p class="lead text-muted mb-0">
                Discover thousands of educational resources, books, and materials to enhance your learning journey.
            </p>
        </div>
        <div class="col-lg-4 mt-4 mt-lg-0">
            <?php if (is_admin()): ?>
                <a href="admin/resource_add.php" class="btn btn-success btn-lg w-100">
                    <i class="fas fa-plus-circle me-2"></i>Add New Resource
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Statistics Row -->
<div class="row g-4 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-book"></i>
            </div>
            <div class="stat-number"><?= count($resources) ?></div>
            <div class="stat-label">Total Resources</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #10b981, #059669);">
                <i class="fas fa-folder-open"></i>
            </div>
            <div class="stat-number"><?= count($categories) ?></div>
            <div class="stat-label">Categories</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
                <i class="fas fa-file-pdf"></i>
            </div>
            <div class="stat-number"><?= count(array_filter($resources, fn($r) => $r['type'] === 'pdf')) ?></div>
            <div class="stat-label">PDF Files</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #8b5cf6, #7c3aed);">
                <i class="fas fa-file-alt"></i>
            </div>
            <div class="stat-number"><?= count(array_filter($resources, fn($r) => $r['type'] === 'document')) ?></div>
            <div class="stat-label">Documents</div>
        </div>
    </div>
</div>

<!-- Search & Filter Section -->
<div class="search-container">
    <form method="get" action="">
        <div class="row g-3">
            <div class="col-12 col-lg-6">
                <div class="input-group input-group-lg">
                    <span class="input-group-text bg-white border-end-0" style="border-radius: 12px 0 0 12px; border: 2px solid var(--border-color); border-right: none;">
                        <i class="fas fa-search text-primary"></i>
                    </span>
                    <input type="text" name="q" class="search-input border-start-0" placeholder="Search resources by title or description..." value="<?= h($search) ?>" style="border-radius: 0 12px 12px 0;">
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <select name="category" class="form-select search-input">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= $category == $cat['id'] ? 'selected' : '' ?>>
                            <?= h($cat['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-lg-3">
                <button type="submit" class="btn btn-primary w-100 h-100">
                    <i class="fas fa-filter me-2"></i>Apply Filters
                </button>
            </div>
        </div>
    </form>
    
    <!-- Quick Type Filters -->
    <div class="d-flex gap-2 mt-3 flex-wrap">
        <a href="?type=" class="filter-btn <?= $type === '' ? 'active' : '' ?>">
            <i class="fas fa-layer-group me-2"></i>All Types
        </a>
        <a href="?type=pdf" class="filter-btn <?= $type === 'pdf' ? 'active' : '' ?>">
            <i class="fas fa-file-pdf me-2"></i>PDF
        </a>
        <a href="?type=document" class="filter-btn <?= $type === 'document' ? 'active' : '' ?>">
            <i class="fas fa-file-word me-2"></i>Documents
        </a>
        <a href="?type=video" class="filter-btn <?= $type === 'video' ? 'active' : '' ?>">
            <i class="fas fa-video me-2"></i>Videos
        </a>
        <a href="?type=link" class="filter-btn <?= $type === 'link' ? 'active' : '' ?>">
            <i class="fas fa-link me-2"></i>Links
        </a>
    </div>
</div>

<!-- Resources Grid -->
<?php if (empty($resources)): ?>
    <div class="empty-state">
        <div class="empty-icon">
            <i class="fas fa-book-open"></i>
        </div>
        <h3 class="text-muted mb-3">No Resources Found</h3>
        <p class="text-muted">Try adjusting your search or filters to find what you're looking for.</p>
        <a href="index.php" class="btn btn-primary mt-3">
            <i class="fas fa-redo me-2"></i>Clear Filters
        </a>
    </div>
<?php else: ?>
    <div class="row g-4 mb-5">
        <?php foreach ($resources as $r): ?>
            <?php 
                $cover = !empty($r['cover_image_path']) ? app_path($r['cover_image_path']) : 'https://via.placeholder.com/400x280/667eea/ffffff?text=' . urlencode($r['title']);
                $typeColors = [
                    'pdf' => 'danger',
                    'document' => 'primary',
                    'video' => 'warning',
                    'link' => 'info',
                    'image' => 'success'
                ];
                $badgeColor = $typeColors[$r['type']] ?? 'secondary';
            ?>
            <div class="col-12 col-sm-6 col-md-4 col-lg-3">
                <div class="resource-card">
                    <div class="resource-image-wrapper">
                        <img src="<?= h($cover) ?>" class="resource-image" alt="<?= h($r['title']) ?>"
                             data-bs-toggle="modal" data-bs-target="#coverModal<?= $r['id'] ?>"
                             style="cursor: zoom-in;">
                        <span class="resource-badge text-<?= $badgeColor ?>">
                            <i class="fas fa-<?= $r['type'] === 'pdf' ? 'file-pdf' : ($r['type'] === 'video' ? 'video' : ($r['type'] === 'link' ? 'link' : 'file-alt')) ?> me-1"></i>
                            <?= strtoupper($r['type']) ?>
                        </span>
                    </div>
                    
                    <div class="resource-body">
                        <h5 class="resource-title"><?= h($r['title']) ?></h5>
                        
                        <?php if ($r['category_name']): ?>
                            <span class="resource-category">
                                <i class="fas fa-folder"></i>
                                <?= h($r['category_name']) ?>
                            </span>
                        <?php endif; ?>
                        
                        <?php if (!empty($r['description'])): ?>
                            <p class="resource-description"><?= h($r['description']) ?></p>
                        <?php endif; ?>
                        
                        <div class="resource-actions">
                            <a href="viewer.php?id=<?= $r['id'] ?>" class="btn btn-primary flex-grow-1">
                                <i class="fas fa-eye me-2"></i>Open
                            </a>
                            <?php if (is_admin()): ?>
                                <a href="admin/resource_edit.php?id=<?= $r['id'] ?>" class="btn btn-outline-secondary">
                                    <i class="fas fa-edit"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Zoom Modal -->
            <div class="modal fade zoom-modal" id="coverModal<?= $r['id'] ?>" tabindex="-1">
                <div class="modal-dialog modal-dialog-centered modal-lg">
                    <div class="modal-content">
                        <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 m-3" data-bs-dismiss="modal" style="z-index: 10;"></button>
                        <img src="<?= h($cover) ?>" class="img-fluid" alt="<?= h($r['title']) ?>">
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>

