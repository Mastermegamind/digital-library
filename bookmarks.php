<?php
// bookmarks.php - User Bookmarks Page
require_once __DIR__ . '/includes/auth.php';
redirect_legacy_php('bookmarks');
require_login();

$user = current_user();

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Count total bookmarks
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM user_bookmarks WHERE user_id = ?");
$countStmt->execute([$user['id']]);
$totalBookmarks = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalBookmarks / $perPage));
$page = min($page, $totalPages);

// Get bookmarks with pagination
$stmt = $pdo->prepare("
    SELECT r.*, c.name AS category_name, ub.created_at AS bookmarked_at
    FROM user_bookmarks ub
    JOIN resources r ON ub.resource_id = r.id
    LEFT JOIN categories c ON r.category_id = c.id
    WHERE ub.user_id = ?
    ORDER BY ub.created_at DESC
    LIMIT ? OFFSET ?
");
$stmt->bindValue(1, $user['id'], PDO::PARAM_INT);
$stmt->bindValue(2, $perPage, PDO::PARAM_INT);
$stmt->bindValue(3, $offset, PDO::PARAM_INT);
$stmt->execute();
$bookmarks = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get user's progress for display
$userProgress = get_user_progress($user['id']);
$tagsByResource = get_tags_for_resources(array_column($bookmarks, 'id'));

$meta_title = 'My Bookmarks - ' . $APP_NAME;
include __DIR__ . '/includes/header.php';
?>

<!-- Page Header -->
<div class="page-header mb-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h1 class="page-title mb-1">
                <i class="fas fa-bookmark me-2"></i>My Bookmarks
            </h1>
            <p class="text-muted mb-0"><?= $totalBookmarks ?> saved resource<?= $totalBookmarks !== 1 ? 's' : '' ?></p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= h(app_path('dashboard')) ?>" class="btn btn-outline-secondary">
                <i class="fas fa-tachometer-alt me-2"></i>Dashboard
            </a>
            <a href="<?= h(app_path('')) ?>" class="btn btn-primary">
                <i class="fas fa-books me-2"></i>Browse Library
            </a>
        </div>
    </div>
</div>

<?php if (empty($bookmarks)): ?>
    <!-- Empty State -->
    <div class="empty-state py-5">
        <div class="empty-icon mb-3">
            <i class="fas fa-bookmark"></i>
        </div>
        <h3 class="text-muted mb-3">No Bookmarks Yet</h3>
        <p class="text-muted mb-4">Save your favorite resources by clicking the bookmark icon on any resource card.</p>
        <a href="<?= h(app_path('')) ?>" class="btn btn-primary">
            <i class="fas fa-search me-2"></i>Browse Library
        </a>
    </div>
<?php else: ?>
    <!-- Bookmarks Grid -->
    <div class="row g-4 mb-4">
        <?php foreach ($bookmarks as $r): ?>
            <?php
                $cover = !empty($r['cover_image_path']) ? app_path($r['cover_image_path']) : 'https://via.placeholder.com/400x280/667eea/ffffff?text=' . urlencode($r['title']);
                $typeColors = ['pdf' => 'danger', 'document' => 'primary', 'video' => 'warning', 'link' => 'info', 'image' => 'success'];
                $badgeColor = $typeColors[$r['type']] ?? 'secondary';
                $progress = $userProgress[$r['id']] ?? null;
                $progressPercent = $progress ? (float)$progress['percent'] : 0;
            ?>
            <div class="col-12 col-sm-6 col-md-4 col-lg-3" id="bookmark-card-<?= $r['id'] ?>">
                <div class="resource-card">
                    <div class="resource-image-wrapper">
                        <img src="<?= h($cover) ?>" class="resource-image" alt="<?= h($r['title']) ?>" loading="lazy">
                        <span class="resource-badge text-<?= $badgeColor ?>">
                            <i class="fas fa-<?= $r['type'] === 'pdf' ? 'file-pdf' : ($r['type'] === 'video' ? 'video' : ($r['type'] === 'link' ? 'link' : 'file-alt')) ?> me-1"></i>
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

                        <?php if (!empty($r['description'])): ?>
                            <p class="resource-description"><?= h($r['description']) ?></p>
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
                            <button class="bookmark-btn bookmarked"
                                    data-resource-id="<?= $r['id'] ?>"
                                    data-bookmarked="1"
                                    title="Remove bookmark"
                                    onclick="handleBookmarkRemove(this, <?= $r['id'] ?>)">
                                <i class="fas fa-bookmark"></i>
                            </button>
                        </div>
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
                            <a class="page-link" href="?page=1">
                                <i class="fas fa-angle-double-left"></i>
                            </a>
                        </li>

                        <!-- Previous Page -->
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= max(1, $page - 1) ?>">
                                <i class="fas fa-angle-left"></i>
                            </a>
                        </li>

                        <?php
                        $range = 2;
                        $start = max(1, $page - $range);
                        $end = min($totalPages, $page + $range);

                        if ($start > 1) {
                            echo '<li class="page-item"><a class="page-link" href="?page=1">1</a></li>';
                            if ($start > 2) {
                                echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                            }
                        }

                        for ($i = $start; $i <= $end; $i++): ?>
                            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                            </li>
                        <?php endfor;

                        if ($end < $totalPages) {
                            if ($end < $totalPages - 1) {
                                echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                            }
                            echo '<li class="page-item"><a class="page-link" href="?page=' . $totalPages . '">' . $totalPages . '</a></li>';
                        }
                        ?>

                        <!-- Next Page -->
                        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= min($totalPages, $page + 1) ?>">
                                <i class="fas fa-angle-right"></i>
                            </a>
                        </li>

                        <!-- Last Page -->
                        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $totalPages ?>">
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
// Handle bookmark removal with card fade-out animation
function handleBookmarkRemove(btn, resourceId) {
    const card = document.getElementById('bookmark-card-' + resourceId);
    if (!card) return;

    // The default bookmark handler will toggle the bookmark
    // After successful removal, fade out the card
    const originalHandler = () => {
        setTimeout(() => {
            // Check if bookmark was removed (button no longer has 'bookmarked' class)
            if (!btn.classList.contains('bookmarked')) {
                card.style.transition = 'opacity 0.3s, transform 0.3s';
                card.style.opacity = '0';
                card.style.transform = 'scale(0.9)';
                setTimeout(() => {
                    card.remove();
                    // Check if page is now empty
                    const remainingCards = document.querySelectorAll('[id^="bookmark-card-"]');
                    if (remainingCards.length === 0) {
                        location.reload();
                    }
                }, 300);
            }
        }, 500);
    };

    // Add one-time listener for the click completion
    btn.addEventListener('click', originalHandler, { once: true });
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
