<?php
// dashboard.php - User Dashboard
require_once __DIR__ . '/includes/auth.php';
redirect_legacy_php('dashboard');
require_login();

$user = current_user();

// Get user statistics
$bookmarkCount = $pdo->prepare("SELECT COUNT(*) FROM user_bookmarks WHERE user_id = ?");
$bookmarkCount->execute([$user['id']]);
$stats['bookmarks'] = (int)$bookmarkCount->fetchColumn();

$viewedCount = $pdo->prepare("SELECT COUNT(*) FROM reading_progress WHERE user_id = ?");
$viewedCount->execute([$user['id']]);
$stats['viewed'] = (int)$viewedCount->fetchColumn();

$completedCount = $pdo->prepare("SELECT COUNT(*) FROM reading_progress WHERE user_id = ? AND progress_percent >= 100");
$completedCount->execute([$user['id']]);
$stats['completed'] = (int)$completedCount->fetchColumn();

// Get in-progress resources (started but not finished)
$inProgressStmt = $pdo->prepare("
    SELECT r.*, c.name AS category_name, rp.progress_percent, rp.last_position, rp.last_viewed_at
    FROM reading_progress rp
    JOIN resources r ON rp.resource_id = r.id
    LEFT JOIN categories c ON r.category_id = c.id
    WHERE rp.user_id = ? AND rp.progress_percent > 0 AND rp.progress_percent < 100
    ORDER BY rp.last_viewed_at DESC
    LIMIT 4
");
$inProgressStmt->execute([$user['id']]);
$inProgressResources = $inProgressStmt->fetchAll(PDO::FETCH_ASSOC);

// Get recently viewed resources
$recentStmt = $pdo->prepare("
    SELECT r.*, c.name AS category_name, rp.progress_percent, rp.last_position, rp.last_viewed_at
    FROM reading_progress rp
    JOIN resources r ON rp.resource_id = r.id
    LEFT JOIN categories c ON r.category_id = c.id
    WHERE rp.user_id = ?
    ORDER BY rp.last_viewed_at DESC
    LIMIT 8
");
$recentStmt->execute([$user['id']]);
$recentResources = $recentStmt->fetchAll(PDO::FETCH_ASSOC);

// Get bookmarked resources
$bookmarkStmt = $pdo->prepare("
    SELECT r.*, c.name AS category_name, ub.created_at AS bookmarked_at
    FROM user_bookmarks ub
    JOIN resources r ON ub.resource_id = r.id
    LEFT JOIN categories c ON r.category_id = c.id
    WHERE ub.user_id = ?
    ORDER BY ub.created_at DESC
    LIMIT 8
");
$bookmarkStmt->execute([$user['id']]);
$bookmarkedResources = $bookmarkStmt->fetchAll(PDO::FETCH_ASSOC);

// Get user's bookmarks for bookmark button display
$userBookmarks = get_user_bookmarks($user['id']);

$tagIds = [];
foreach ([$inProgressResources, $recentResources, $bookmarkedResources] as $group) {
    foreach ($group as $row) {
        $tagIds[] = (int)$row['id'];
    }
}
$tagsByResource = get_tags_for_resources($tagIds);

$meta_title = 'My Dashboard - ' . $APP_NAME;
include __DIR__ . '/includes/header.php';
?>

<!-- Page Header -->
<div class="page-header mb-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h1 class="page-title mb-1">
                <i class="fas fa-tachometer-alt me-2"></i>My Dashboard
            </h1>
            <p class="text-muted mb-0">Welcome back, <?= h($user['name']) ?>!</p>
        </div>
        <a href="<?= h(app_path('')) ?>" class="btn btn-primary">
            <i class="fas fa-books me-2"></i>Browse Library
        </a>
    </div>
</div>

<!-- Statistics Cards -->
<div class="dashboard-stats">
    <div class="dashboard-stat-card">
        <div class="stat-icon bookmarks">
            <i class="fas fa-bookmark"></i>
        </div>
        <div class="stat-content">
            <h3><?= $stats['bookmarks'] ?></h3>
            <p>Bookmarks</p>
        </div>
    </div>
    <div class="dashboard-stat-card">
        <div class="stat-icon viewed">
            <i class="fas fa-eye"></i>
        </div>
        <div class="stat-content">
            <h3><?= $stats['viewed'] ?></h3>
            <p>Resources Viewed</p>
        </div>
    </div>
    <div class="dashboard-stat-card">
        <div class="stat-icon completed">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="stat-content">
            <h3><?= $stats['completed'] ?></h3>
            <p>Completed</p>
        </div>
    </div>
</div>

<?php if (!empty($inProgressResources)): ?>
<!-- Continue Reading Section -->
<div class="continue-reading-section">
    <h3><i class="fas fa-book-reader me-2"></i>Continue Reading</h3>
    <div class="row g-3">
        <?php foreach ($inProgressResources as $r): ?>
            <?php $cover = !empty($r['cover_image_path']) ? app_path($r['cover_image_path']) : 'https://via.placeholder.com/60x60/667eea/ffffff?text=' . urlencode(substr($r['title'], 0, 1)); ?>
            <div class="col-md-6">
                <a href="<?= h(app_path('viewer/' . $r['id'])) ?>" class="continue-reading-card">
                    <img src="<?= h($cover) ?>" alt="<?= h($r['title']) ?>" class="resource-thumb" loading="lazy">
                    <div class="resource-info">
                        <div class="resource-title"><?= h($r['title']) ?></div>
                        <div class="resource-progress">
                            <i class="fas fa-chart-pie me-1"></i>
                            <?= round($r['progress_percent']) ?>% complete
                        </div>
                        <?php $tags = $tagsByResource[$r['id']] ?? []; ?>
                        <?php if (!empty($tags)): ?>
                            <div class="d-flex flex-wrap gap-1 mt-1">
                                <?php foreach (array_slice($tags, 0, 2) as $tag): ?>
                                    <span class="badge bg-light text-muted">#<?= h($tag) ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <i class="fas fa-chevron-right ms-auto"></i>
                </a>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Recently Viewed Section -->
<div class="dashboard-section">
    <div class="dashboard-section-header">
        <h2><i class="fas fa-history me-2"></i>Recently Viewed</h2>
        <?php if (count($recentResources) > 0): ?>
            <a href="<?= h(app_path('')) ?>">View all resources</a>
        <?php endif; ?>
    </div>

    <?php if (empty($recentResources)): ?>
        <div class="empty-state py-5">
            <div class="empty-icon mb-3">
                <i class="fas fa-book-open"></i>
            </div>
            <h4 class="text-muted">No recently viewed resources</h4>
            <p class="text-muted">Start exploring the library to see your history here.</p>
            <a href="<?= h(app_path('')) ?>" class="btn btn-primary mt-2">
                <i class="fas fa-search me-2"></i>Browse Library
            </a>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($recentResources as $r): ?>
                <?php
                    $cover = !empty($r['cover_image_path']) ? app_path($r['cover_image_path']) : 'https://via.placeholder.com/400x280/667eea/ffffff?text=' . urlencode($r['title']);
                    $typeColors = ['pdf' => 'danger', 'document' => 'primary', 'video' => 'warning', 'link' => 'info', 'image' => 'success'];
                    $badgeColor = $typeColors[$r['type']] ?? 'secondary';
                    $isBookmarked = in_array($r['id'], $userBookmarks);
                    $progressPercent = (float)$r['progress_percent'];
                ?>
                <div class="col-12 col-sm-6 col-md-4 col-lg-3">
                    <div class="resource-card">
                        <div class="resource-image-wrapper">
                            <img src="<?= h($cover) ?>" class="resource-image" alt="<?= h($r['title']) ?>" loading="lazy">
                            <span class="resource-badge text-<?= $badgeColor ?>">
                                <i class="fas fa-<?= $r['type'] === 'pdf' ? 'file-pdf' : ($r['type'] === 'video' ? 'video' : 'file-alt') ?> me-1"></i>
                                <?= strtoupper($r['type']) ?>
                            </span>
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
                            <?php if ($r['category_name']): ?>
                                <span class="resource-category">
                                    <i class="fas fa-folder"></i>
                                    <?= h($r['category_name']) ?>
                                </span>
                            <?php endif; ?>
                            <?php $tags = $tagsByResource[$r['id']] ?? []; ?>
                            <?php if (!empty($tags)): ?>
                                <div class="d-flex flex-wrap gap-1 mb-2">
                                    <?php foreach (array_slice($tags, 0, 3) as $tag): ?>
                                        <span class="badge bg-light text-muted">#<?= h($tag) ?></span>
                                    <?php endforeach; ?>
                                    <?php if (count($tags) > 3): ?>
                                        <span class="badge bg-secondary">+<?= count($tags) - 3 ?></span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            <div class="resource-actions">
                                <a href="<?= h(app_path('viewer/' . $r['id'])) ?>" class="btn btn-primary flex-grow-1">
                                    <i class="fas fa-eye me-2"></i><?= $progressPercent > 0 ? 'Continue' : 'Open' ?>
                                </a>
                                <button class="bookmark-btn <?= $isBookmarked ? 'bookmarked' : '' ?>"
                                        data-resource-id="<?= $r['id'] ?>"
                                        data-bookmarked="<?= $isBookmarked ? '1' : '0' ?>"
                                        title="<?= $isBookmarked ? 'Remove bookmark' : 'Add bookmark' ?>">
                                    <i class="<?= $isBookmarked ? 'fas' : 'far' ?> fa-bookmark"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Bookmarked Resources Section -->
<div class="dashboard-section">
    <div class="dashboard-section-header">
        <h2><i class="fas fa-bookmark me-2"></i>My Bookmarks</h2>
        <?php if (count($bookmarkedResources) > 0): ?>
            <a href="<?= h(app_path('bookmarks')) ?>">View all bookmarks</a>
        <?php endif; ?>
    </div>

    <?php if (empty($bookmarkedResources)): ?>
        <div class="empty-state py-5">
            <div class="empty-icon mb-3">
                <i class="fas fa-bookmark"></i>
            </div>
            <h4 class="text-muted">No bookmarks yet</h4>
            <p class="text-muted">Save resources for quick access by clicking the bookmark icon.</p>
            <a href="<?= h(app_path('')) ?>" class="btn btn-primary mt-2">
                <i class="fas fa-search me-2"></i>Browse Library
            </a>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($bookmarkedResources as $r): ?>
                <?php
                    $cover = !empty($r['cover_image_path']) ? app_path($r['cover_image_path']) : 'https://via.placeholder.com/400x280/667eea/ffffff?text=' . urlencode($r['title']);
                    $typeColors = ['pdf' => 'danger', 'document' => 'primary', 'video' => 'warning', 'link' => 'info', 'image' => 'success'];
                    $badgeColor = $typeColors[$r['type']] ?? 'secondary';
                ?>
                <div class="col-12 col-sm-6 col-md-4 col-lg-3">
                    <div class="resource-card">
                        <div class="resource-image-wrapper">
                            <img src="<?= h($cover) ?>" class="resource-image" alt="<?= h($r['title']) ?>" loading="lazy">
                            <span class="resource-badge text-<?= $badgeColor ?>">
                                <i class="fas fa-<?= $r['type'] === 'pdf' ? 'file-pdf' : ($r['type'] === 'video' ? 'video' : 'file-alt') ?> me-1"></i>
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
                            <?php $tags = $tagsByResource[$r['id']] ?? []; ?>
                            <?php if (!empty($tags)): ?>
                                <div class="d-flex flex-wrap gap-1 mb-2">
                                    <?php foreach (array_slice($tags, 0, 3) as $tag): ?>
                                        <span class="badge bg-light text-muted">#<?= h($tag) ?></span>
                                    <?php endforeach; ?>
                                    <?php if (count($tags) > 3): ?>
                                        <span class="badge bg-secondary">+<?= count($tags) - 3 ?></span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            <div class="resource-actions">
                                <a href="<?= h(app_path('viewer/' . $r['id'])) ?>" class="btn btn-primary flex-grow-1">
                                    <i class="fas fa-eye me-2"></i>Open
                                </a>
                                <button class="bookmark-btn bookmarked"
                                        data-resource-id="<?= $r['id'] ?>"
                                        data-bookmarked="1"
                                        title="Remove bookmark">
                                    <i class="fas fa-bookmark"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
