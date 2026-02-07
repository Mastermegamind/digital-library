<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/ai.php';
redirect_legacy_php('');
require_login();

$currentUser = current_user();
$isAdmin = is_admin();
$aiAvailable = function_exists('ai_is_configured') && ai_is_configured();

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

if (!$isAdmin) {
    $whereConditions[] = "r.status = 'approved'";
}

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

if ($page === 1) {
    $logFilters = [
        'category' => $category,
        'type' => $type,
        'sort' => $sort,
    ];
    log_search_query($currentUser['id'] ?? null, $search, $logFilters, $totalResources);
}

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
$typeCountsSql = "SELECT type, COUNT(*) as count FROM resources";
if (!$isAdmin) {
    $typeCountsSql .= " WHERE status = 'approved'";
}
$typeCountsSql .= " GROUP BY type";
$typeCounts = $pdo->query($typeCountsSql)->fetchAll(PDO::FETCH_ASSOC);
$typeCountMap = [];
foreach ($typeCounts as $tc) {
    $typeCountMap[$tc['type']] = $tc['count'];
}

// Get user's bookmarks and progress for display
$userBookmarks = [];
$userProgress = [];
$currentUser = current_user();
if ($currentUser) {
    $userBookmarks = get_user_bookmarks($currentUser['id']);
    $userProgress = get_user_progress($currentUser['id']);
}

$featuredSections = [
    'featured' => ['title' => 'Featured', 'icon' => 'star', 'limit' => 4],
    'editors_picks' => ['title' => "Editor's Picks", 'icon' => 'award', 'limit' => 4],
    'new_this_week' => ['title' => 'New This Week', 'icon' => 'calendar-alt', 'limit' => 4],
];

$featuredResources = [];
foreach ($featuredSections as $sectionKey => $sectionConfig) {
    $sectionSql = "SELECT r.*, c.name AS category_name
                   FROM featured_resources fr
                   JOIN resources r ON fr.resource_id = r.id
                   LEFT JOIN categories c ON r.category_id = c.id
                   WHERE fr.section = :section
                     AND (fr.starts_at IS NULL OR fr.starts_at <= CURRENT_TIMESTAMP)
                     AND (fr.ends_at IS NULL OR fr.ends_at >= CURRENT_TIMESTAMP)";
    if (!$isAdmin) {
        $sectionSql .= " AND r.status = 'approved'";
    }
    $sectionSql .= " ORDER BY fr.sort_order ASC, fr.created_at DESC LIMIT :limit";

    $stmt = $pdo->prepare($sectionSql);
    $stmt->bindValue(':section', $sectionKey);
    $stmt->bindValue(':limit', (int)$sectionConfig['limit'], PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!empty($rows)) {
        $featuredResources[$sectionKey] = $rows;
    }
}

$trendingResources = get_trending_resources(6, 14);
if (!empty($featuredResources) && !empty($trendingResources)) {
    $featuredIds = [];
    foreach ($featuredResources as $sectionItems) {
        foreach ($sectionItems as $item) {
            $featuredIds[(int)$item['id']] = true;
        }
    }
    $trendingResources = array_values(array_filter($trendingResources, function ($row) use ($featuredIds) {
        return empty($featuredIds[(int)$row['id']]);
    }));
    $trendingResources = array_slice($trendingResources, 0, 6);
}

$resourceIds = [];
foreach ($resources as $row) {
    $resourceIds[] = (int)$row['id'];
}
foreach ($featuredResources as $sectionItems) {
    foreach ($sectionItems as $row) {
        $resourceIds[] = (int)$row['id'];
    }
}
foreach ($trendingResources as $row) {
    $resourceIds[] = (int)$row['id'];
}
$tagsByResource = get_tags_for_resources($resourceIds);

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

function render_resource_card(array $r, array $userBookmarks, array $userProgress, string $context = 'main', array $tagsByResource = []): void {
    $contextSafe = preg_replace('/[^a-zA-Z0-9_-]/', '', $context);
    if ($contextSafe === '') {
        $contextSafe = 'main';
    }
    $coverData = get_resource_cover_data($r);
    $cover = $coverData['url'];
    $creditText = $coverData['credit'] ?? null;
    $creditLink = $coverData['credit_link'] ?? null;
    $coverIsPlaceholder = ($coverData['source'] ?? '') === 'placeholder';
    $typeColors = [
        'pdf' => 'danger',
        'document' => 'primary',
        'video' => 'warning',
        'link' => 'info',
        'image' => 'success'
    ];
    $badgeColor = $typeColors[$r['type']] ?? 'secondary';
    $isBookmarked = in_array($r['id'], $userBookmarks);
    $progress = $userProgress[$r['id']] ?? null;
    $progressPercent = $progress ? (float)$progress['percent'] : 0;
    $tags = $tagsByResource[$r['id']] ?? [];
    $displayTags = array_slice($tags, 0, 3);
    $extraTags = max(0, count($tags) - count($displayTags));
    $modalId = 'coverModal' . $contextSafe . $r['id'];
    ?>
    <div class="col-12 col-sm-6 col-md-4 col-lg-3">
        <div class="resource-card">
            <div class="resource-image-wrapper">
                <img src="<?= h($cover) ?>" class="resource-image resource-zoomable" alt="<?= h($r['title']) ?>" loading="lazy"
                     data-resource-image="1" <?= $coverIsPlaceholder ? 'data-fallback="1"' : '' ?>
                     data-bs-toggle="modal" data-bs-target="#<?= h($modalId) ?>"
                >
                <span class="resource-badge text-<?= h($badgeColor) ?>">
                    <i class="fas fa-<?= $r['type'] === 'pdf' ? 'file-pdf' : ($r['type'] === 'video' ? 'video' : ($r['type'] === 'link' ? 'link' : 'file-alt')) ?> me-1"></i>
                    <?= strtoupper($r['type']) ?>
                </span>
                <?php if ($creditText && $creditLink): ?>
                    <div class="image-credit">
                        <a href="<?= h($creditLink) ?>" target="_blank" rel="noopener"><?= h($creditText) ?></a>
                    </div>
                <?php endif; ?>
                <?php if ($progressPercent > 0): ?>
                    <div class="progress-indicator">
                        <div class="progress-indicator-bar" style="width: <?= min(100, $progressPercent) ?>%"></div>
                    </div>
                    <span class="progress-badge">
                        <i class="fas fa-book-reader"></i>
                        <?= round($progressPercent) ?>%
                    </span>
                <?php endif; ?>
            </div>

            <div class="resource-body">
                <h5 class="resource-title"><?= h($r['title']) ?></h5>

                <?php if (!empty($r['category_name'])): ?>
                    <span class="resource-category">
                        <i class="fas fa-folder"></i>
                        <?= h($r['category_name']) ?>
                    </span>
                <?php endif; ?>

                <?php if (can_view_resource_file_size()): ?>
                    <div class="small text-muted mb-1">
                        <i class="fas fa-hdd me-1"></i>File size: <?= h(get_resource_file_size_label($r)) ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($r['description'])): ?>
                    <p class="resource-description"><?= h($r['description']) ?></p>
                <?php endif; ?>

                <?php if (!empty($displayTags)): ?>
                    <div class="d-flex flex-wrap gap-1 mb-2">
                        <?php foreach ($displayTags as $tag): ?>
                            <span class="badge bg-light text-muted">#<?= h($tag) ?></span>
                        <?php endforeach; ?>
                        <?php if ($extraTags > 0): ?>
                            <span class="badge bg-secondary">+<?= $extraTags ?></span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div class="resource-actions">
                    <a href="<?= h(app_path('viewer/' . $r['id'])) ?>" class="btn btn-primary flex-grow-1">
                        <i class="fas fa-eye me-2"></i><?= $progressPercent > 0 ? 'Continue' : 'Open' ?>
                    </a>
                    <button class="bookmark-btn <?= $isBookmarked ? 'bookmarked' : '' ?>"
                            data-resource-id="<?= (int)$r['id'] ?>"
                            data-bookmarked="<?= $isBookmarked ? '1' : '0' ?>"
                            title="<?= $isBookmarked ? 'Remove bookmark' : 'Add bookmark' ?>">
                        <i class="<?= $isBookmarked ? 'fas' : 'far' ?> fa-bookmark"></i>
                    </button>
                    <?php if (is_logged_in()): ?>
                        <button class="btn btn-outline-secondary add-to-collection-btn"
                                data-resource-id="<?= (int)$r['id'] ?>"
                                title="Add to Collection">
                            <i class="fas fa-folder-plus"></i>
                        </button>
                    <?php endif; ?>
                    <?php if (is_admin()): ?>
                        <a href="<?= h(app_path('admin/resource/edit/' . $r['id'])) ?>" class="btn btn-outline-secondary">
                            <i class="fas fa-edit"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade zoom-modal" id="<?= h($modalId) ?>" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 m-3 modal-close-overlay" data-bs-dismiss="modal"></button>
                <img src="<?= h($cover) ?>" class="img-fluid" alt="<?= h($r['title']) ?>" data-resource-image="1" <?= $coverIsPlaceholder ? 'data-fallback="1"' : '' ?>>
                <?php if ($creditText && $creditLink): ?>
                    <div class="modal-credit small text-muted p-2 text-center">
                        <a href="<?= h($creditLink) ?>" target="_blank" rel="noopener"><?= h($creditText) ?></a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
}

$meta_title = $APP_NAME . ' - Library';
$meta_description = 'Browse educational resources from ' . ($FULL_APP_NAME ?? $APP_NAME) . '.';
include __DIR__ . '/includes/header.php';
?>
<!-- Hero Section -->
<div class="hero-section">
    <div class="row align-items-center">
        <div class="col-lg-8">
            <h1 class="display-4 fw-bold mb-3 page-title">
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
            <div class="stat-icon stat-icon-success">
                <i class="fas fa-folder-open"></i>
            </div>
            <div class="stat-number"><?= count($categories) ?></div>
            <div class="stat-label">Categories</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon stat-icon-warning">
                <i class="fas fa-file-pdf"></i>
            </div>
            <div class="stat-number"><?= $typeCountMap['pdf'] ?? 0 ?></div>
            <div class="stat-label">PDF Files</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon stat-icon-purple">
                <i class="fas fa-file-alt"></i>
            </div>
            <div class="stat-number"><?= $typeCountMap['document'] ?? 0 ?></div>
            <div class="stat-label">Documents</div>
        </div>
    </div>
</div>

<?php if (!empty($featuredResources)): ?>
    <?php foreach ($featuredResources as $sectionKey => $sectionItems): ?>
        <?php $sectionMeta = $featuredSections[$sectionKey] ?? ['title' => ucfirst($sectionKey), 'icon' => 'star']; ?>
        <div class="dashboard-section">
            <div class="dashboard-section-header">
                <h2>
                    <i class="fas fa-<?= h($sectionMeta['icon']) ?> text-warning me-2"></i>
                    <?= h($sectionMeta['title']) ?>
                </h2>
            </div>
            <div class="row g-4 mb-4">
                <?php foreach ($sectionItems as $r): ?>
                    <?php render_resource_card($r, $userBookmarks, $userProgress, 'section-' . $sectionKey, $tagsByResource); ?>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php if (!empty($trendingResources)): ?>
    <div class="dashboard-section">
        <div class="dashboard-section-header">
            <h2>
                <i class="fas fa-fire text-danger me-2"></i>
                Trending Now
            </h2>
        </div>
        <div class="row g-4 mb-4">
            <?php foreach ($trendingResources as $r): ?>
                <?php render_resource_card($r, $userBookmarks, $userProgress, 'trending', $tagsByResource); ?>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<!-- Search & Filter Section -->
<div class="search-container">
    <form method="get" action="" id="searchForm">
        <div class="row g-3">
            <div class="col-12 col-lg-6">
                <div class="input-group input-group-lg">
                    <span class="input-group-text bg-white border-end-0 search-input-addon">
                        <i class="fas fa-search text-primary"></i>
                    </span>
                    <input type="text" name="q" class="search-input border-start-0 search-input-field" placeholder="Search resources by title or description..." value="<?= h($search) ?>">
                </div>
                <?php if ($aiAvailable): ?>
                    <div class="form-check form-switch mt-2">
                        <input class="form-check-input" type="checkbox" id="smartSearchToggle">
                        <label class="form-check-label text-muted" for="smartSearchToggle">
                            Smart Search (AI)
                        </label>
                    </div>
                <?php endif; ?>
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
            <?php render_resource_card($r, $userBookmarks, $userProgress, 'main', $tagsByResource); ?>
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

    const smartToggle = document.getElementById('smartSearchToggle');
    const searchForm = document.getElementById('searchForm');
    if (smartToggle && searchForm) {
        searchForm.addEventListener('submit', function(e) {
            if (!smartToggle.checked) return;
            const input = searchForm.querySelector('input[name=\"q\"]');
            const query = input ? input.value.trim() : '';
            if (query === '') return;

            e.preventDefault();
            fetch(appPath + 'api/smart-search', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ csrf_token: csrfToken, query: query })
            })
            .then(res => res.json())
            .then(data => {
                if (data.error || !data.data) {
                    searchForm.submit();
                    return;
                }
                const keywords = data.data.keywords || [];
                if (keywords.length > 0 && input) {
                    input.value = keywords.join(' ');
                }
                searchForm.submit();
            })
            .catch(() => searchForm.submit());
        });
    }
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
