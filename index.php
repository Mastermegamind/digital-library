<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

// Get filter parameters
$search = trim($_GET['q'] ?? '');
$category = trim($_GET['category'] ?? '');
$type = trim($_GET['type'] ?? '');
$sort = trim($_GET['sort'] ?? 'newest');
$perPage = (int)($_GET['per_page'] ?? 20);
$page = max(1, (int)($_GET['page'] ?? 1));

// Validate per page value
if (!in_array($perPage, [20, 50, 100])) {
    $perPage = 20;
}

// Build WHERE clause
$params = [];
$whereConditions = [];

if ($search !== '') {
    $whereConditions[] = "(r.title LIKE :q OR r.description LIKE :q)";
    $params[':q'] = '%' . $search . '%';
}

if ($category !== '') {
    $whereConditions[] = "r.category_id = :cat";
    $params[':cat'] = $category;
}

if ($type !== '') {
    $whereConditions[] = "r.type = :type";
    $params[':type'] = $type;
}

$whereClause = '';
if (!empty($whereConditions)) {
    $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
}

// Determine ORDER BY clause
$orderBy = match($sort) {
    'oldest' => 'r.created_at ASC',
    'title_asc' => 'r.title ASC',
    'title_desc' => 'r.title DESC',
    'type' => 'r.type ASC, r.created_at DESC',
    default => 'r.created_at DESC', // newest
};

// Count total resources
$countSql = "SELECT COUNT(*) as total FROM resources r $whereClause";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalResources = (int)$countStmt->fetch(PDO::FETCH_ASSOC)['total'];

// Calculate pagination
$totalPages = max(1, (int)ceil($totalResources / $perPage));
$page = min($page, $totalPages); // Ensure page doesn't exceed total pages
$offset = ($page - 1) * $perPage;

// Fetch resources with pagination
$sql = "SELECT r.*, c.name AS category_name 
        FROM resources r
        LEFT JOIN categories c ON r.category_id = c.id
        $whereClause
        ORDER BY $orderBy
        LIMIT :limit OFFSET :offset";

$stmt = $pdo->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$resources = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get categories for filter
$categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Get resource type counts
$typeCounts = $pdo->query("SELECT type, COUNT(*) as count FROM resources GROUP BY type")->fetchAll(PDO::FETCH_ASSOC);
$typeCountMap = [];
foreach ($typeCounts as $tc) {
    $typeCountMap[$tc['type']] = $tc['count'];
}

// Build query string for pagination links
function buildQueryString($overrides = []) {
    $params = [
        'q' => $_GET['q'] ?? '',
        'category' => $_GET['category'] ?? '',
        'type' => $_GET['type'] ?? '',
        'sort' => $_GET['sort'] ?? 'newest',
        'per_page' => $_GET['per_page'] ?? 20,
    ];
    $params = array_merge($params, $overrides);
    $params = array_filter($params, fn($v) => $v !== '');
    return http_build_query($params);
}

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

    .search-input, .form-select {
        border: 2px solid var(--border-color);
        border-radius: 12px;
        padding: 0.875rem 1.25rem;
        font-size: 1rem;
        transition: all 0.3s ease;
    }

    .search-input:focus, .form-select:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1);
    }

    .filter-chip {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.5rem 1rem;
        border: 2px solid var(--border-color);
        background: white;
        border-radius: 20px;
        font-weight: 600;
        font-size: 0.875rem;
        transition: all 0.3s ease;
        text-decoration: none;
        color: var(--text-primary);
    }

    .filter-chip:hover, .filter-chip.active {
        border-color: var(--primary-color);
        background: var(--primary-color);
        color: white;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
    }

    .filter-chip .badge {
        background: rgba(0, 0, 0, 0.1);
        padding: 0.25rem 0.5rem;
        border-radius: 10px;
        font-size: 0.75rem;
    }

    .filter-chip.active .badge {
        background: rgba(255, 255, 255, 0.3);
    }

    .toolbar {
        background: white;
        border-radius: 16px;
        padding: 1.25rem;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
        margin-bottom: 2rem;
    }

    .results-info {
        font-weight: 600;
        color: var(--text-secondary);
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

    .pagination-container {
        background: white;
        border-radius: 16px;
        padding: 1.5rem;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
        margin-top: 3rem;
    }

    .pagination {
        margin: 0;
    }

    .page-link {
        border: 2px solid var(--border-color);
        color: var(--text-primary);
        font-weight: 600;
        padding: 0.625rem 1rem;
        margin: 0 0.25rem;
        border-radius: 10px;
        transition: all 0.3s ease;
    }

    .page-link:hover {
        background: var(--primary-color);
        border-color: var(--primary-color);
        color: white;
        transform: translateY(-2px);
    }

    .page-item.active .page-link {
        background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
        border-color: var(--primary-color);
        box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
    }

    .page-item.disabled .page-link {
        background: var(--light-bg);
        border-color: var(--border-color);
        opacity: 0.5;
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

        .toolbar {
            flex-direction: column;
            gap: 1rem;
        }
    }
</style>

<!-- Hero Section -->
<div class="hero-section">
    <div class="row align-items-center">
        <div class="col-lg-8">
            <h1 class="display-4 fw-bold mb-3" style="color: var(--primary-color);">
                <i class="fas fa-book-reader me-3"></i>Welcome to CONS-UNTH E-Library
            </h1>
            <p class="lead text-muted mb-0">
                Discover thousands of educational resources, books, and materials to enhance your learning journey.
            </p>
        </div>
        <div class="col-lg-4 mt-4 mt-lg-0">
            <?php if (is_admin()): ?>
                <a href="<?= h(app_path('admin/resource/add')) ?>" class="btn btn-success btn-lg w-100">
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
            <div class="stat-number"><?= $totalResources ?></div>
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
            <div class="stat-number"><?= $typeCountMap['pdf'] ?? 0 ?></div>
            <div class="stat-label">PDF Files</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #8b5cf6, #7c3aed);">
                <i class="fas fa-file-alt"></i>
            </div>
            <div class="stat-number"><?= $typeCountMap['document'] ?? 0 ?></div>
            <div class="stat-label">Documents</div>
        </div>
    </div>
</div>

<!-- Search & Filter Section -->
<div class="search-container">
    <form method="get" action="" id="searchForm">
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
        <a href="?<?= buildQueryString(['type' => '', 'page' => 1]) ?>" class="filter-chip <?= $type === '' ? 'active' : '' ?>">
            <i class="fas fa-layer-group"></i>
            <span>All Types</span>
            <span class="badge"><?= $totalResources ?></span>
        </a>
        <a href="?<?= buildQueryString(['type' => 'pdf', 'page' => 1]) ?>" class="filter-chip <?= $type === 'pdf' ? 'active' : '' ?>">
            <i class="fas fa-file-pdf"></i>
            <span>PDF</span>
            <span class="badge"><?= $typeCountMap['pdf'] ?? 0 ?></span>
        </a>
        <a href="?<?= buildQueryString(['type' => 'document', 'page' => 1]) ?>" class="filter-chip <?= $type === 'document' ? 'active' : '' ?>">
            <i class="fas fa-file-word"></i>
            <span>Documents</span>
            <span class="badge"><?= $typeCountMap['document'] ?? 0 ?></span>
        </a>
        <a href="?<?= buildQueryString(['type' => 'video', 'page' => 1]) ?>" class="filter-chip <?= $type === 'video' ? 'active' : '' ?>">
            <i class="fas fa-video"></i>
            <span>Videos</span>
            <span class="badge"><?= $typeCountMap['video'] ?? 0 ?></span>
        </a>
        <a href="?<?= buildQueryString(['type' => 'link', 'page' => 1]) ?>" class="filter-chip <?= $type === 'link' ? 'active' : '' ?>">
            <i class="fas fa-link"></i>
            <span>Links</span>
            <span class="badge"><?= $typeCountMap['link'] ?? 0 ?></span>
        </a>
    </div>
</div>

<!-- Toolbar with Sort and Per Page -->
<div class="toolbar">
    <div class="row align-items-center g-3">
        <div class="col-md-4">
            <div class="results-info">
                <i class="fas fa-info-circle me-2"></i>
                Showing <?= $totalResources > 0 ? $offset + 1 : 0 ?> - <?= min($offset + $perPage, $totalResources) ?> of <?= $totalResources ?> resources
            </div>
        </div>
        <div class="col-md-4">
            <select class="form-select" name="sort" onchange="updateSort(this.value)">
                <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>
                    <i class="fas fa-clock"></i> Newest First
                </option>
                <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>Oldest First</option>
                <option value="title_asc" <?= $sort === 'title_asc' ? 'selected' : '' ?>>Title (A-Z)</option>
                <option value="title_desc" <?= $sort === 'title_desc' ? 'selected' : '' ?>>Title (Z-A)</option>
                <option value="type" <?= $sort === 'type' ? 'selected' : '' ?>>Type</option>
            </select>
        </div>
        <div class="col-md-4">
            <div class="input-group">
                <span class="input-group-text bg-white">
                    <i class="fas fa-list"></i>
                </span>
                <select class="form-select" name="per_page" onchange="updatePerPage(this.value)">
                    <option value="20" <?= $perPage === 20 ? 'selected' : '' ?>>20 per page</option>
                    <option value="50" <?= $perPage === 50 ? 'selected' : '' ?>>50 per page</option>
                    <option value="100" <?= $perPage === 100 ? 'selected' : '' ?>>100 per page</option>
                </select>
            </div>
        </div>
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
        <a href="<?= h(app_path('')) ?>" class="btn btn-primary mt-3">
            <i class="fas fa-redo me-2"></i>Clear All Filters
        </a>
    </div>
<?php else: ?>
    <div class="row g-4 mb-4">
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
                            <a href="<?= h(app_path('viewer/' . $r['id'])) ?>" class="btn btn-primary flex-grow-1">
                                <i class="fas fa-eye me-2"></i>Open
                            </a>
                            <?php if (is_admin()): ?>
                                <a href="<?= h(app_path('admin/resource/edit/' . $r['id'])) ?>" class="btn btn-outline-secondary">
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

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="pagination-container">
        <div class="row align-items-center">
            <div class="col-md-6 mb-3 mb-md-0">
                <p class="mb-0 text-muted">
                    Page <strong><?= $page ?></strong> of <strong><?= $totalPages ?></strong>
                </p>
            </div>
            <div class="col-md-6">
                <nav>
                    <ul class="pagination justify-content-md-end justify-content-center mb-0">
                        <!-- First Page -->
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="?<?= buildQueryString(['page' => 1]) ?>">
                                <i class="fas fa-angle-double-left"></i>
                            </a>
                        </li>
                        
                        <!-- Previous Page -->
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="?<?= buildQueryString(['page' => max(1, $page - 1)]) ?>">
                                <i class="fas fa-angle-left"></i>
                            </a>
                        </li>

                        <?php
                        // Calculate page range to show
                        $range = 2;
                        $start = max(1, $page - $range);
                        $end = min($totalPages, $page + $range);

                        // Show first page if not in range
                        if ($start > 1) {
                            echo '<li class="page-item"><a class="page-link" href="?' . buildQueryString(['page' => 1]) . '">1</a></li>';
                            if ($start > 2) {
                                echo '<li class="page-item disabled"><a class="page-link">...</a></li>';
                            }
                        }

                        // Show page numbers
                        for ($i = $start; $i <= $end; $i++): ?>
                            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                <a class="page-link" href="?<?= buildQueryString(['page' => $i]) ?>">
                                    <?= $i ?>
                                </a>
                            </li>
                        <?php endfor;

                        // Show last page if not in range
                        if ($end < $totalPages) {
                            if ($end < $totalPages - 1) {
                                echo '<li class="page-item disabled"><a class="page-link">...</a></li>';
                            }
                            echo '<li class="page-item"><a class="page-link" href="?' . buildQueryString(['page' => $totalPages]) . '">' . $totalPages . '</a></li>';
                        }
                        ?>

                        <!-- Next Page -->
                        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                            <a class="page-link" href="?<?= buildQueryString(['page' => min($totalPages, $page + 1)]) ?>">
                                <i class="fas fa-angle-right"></i>
                            </a>
                        </li>

                        <!-- Last Page -->
                        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                            <a class="page-link" href="?<?= buildQueryString(['page' => $totalPages]) ?>">
                                <i class="fas fa-angle-double-right"></i>
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>
        </div>
    </div>
    <?php endif; ?>
<?php endif; ?>

<script>
function updateSort(value) {
    const url = new URL(window.location.href);
    url.searchParams.set('sort', value);
    url.searchParams.set('page', '1'); // Reset to first page
    window.location.href = url.toString();
}

function updatePerPage(value) {
    const url = new URL(window.location.href);
    url.searchParams.set('per_page', value);
    url.searchParams.set('page', '1'); // Reset to first page
    window.location.href = url.toString();
}

// Smooth scroll to top when changing pages
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('page')) {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
