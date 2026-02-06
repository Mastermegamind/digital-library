<?php
require_once __DIR__ . '/../includes/auth.php';
redirect_legacy_php('admin/reports');
require_admin();

$ranges = [7, 30, 90, 180];
$range = (int)($_GET['range'] ?? 30);
if (!in_array($range, $ranges, true)) {
    $range = 30;
}
$since = date('Y-m-d H:i:s', time() - ($range * 86400));

$totalViewsStmt = $pdo->prepare("SELECT COUNT(*) FROM resource_views WHERE created_at >= :since");
$totalViewsStmt->execute([':since' => $since]);
$totalViews = (int)$totalViewsStmt->fetchColumn();

$totalDownloadsStmt = $pdo->prepare("SELECT COUNT(*) FROM resource_downloads WHERE created_at >= :since");
$totalDownloadsStmt->execute([':since' => $since]);
$totalDownloads = (int)$totalDownloadsStmt->fetchColumn();

$totalSearchesStmt = $pdo->prepare("SELECT COUNT(*) FROM search_logs WHERE created_at >= :since");
$totalSearchesStmt->execute([':since' => $since]);
$totalSearches = (int)$totalSearchesStmt->fetchColumn();

$topViewedStmt = $pdo->prepare("SELECT r.id, r.title, r.type, COUNT(*) AS view_count
                               FROM resource_views rv
                               JOIN resources r ON rv.resource_id = r.id
                               WHERE rv.created_at >= :since
                               GROUP BY r.id
                               ORDER BY view_count DESC, r.created_at DESC
                               LIMIT 10");
$topViewedStmt->execute([':since' => $since]);
$topViewed = $topViewedStmt->fetchAll(PDO::FETCH_ASSOC);

$topDownloadedStmt = $pdo->prepare("SELECT r.id, r.title, r.type, COUNT(*) AS download_count
                                   FROM resource_downloads rd
                                   JOIN resources r ON rd.resource_id = r.id
                                   WHERE rd.created_at >= :since
                                   GROUP BY r.id
                                   ORDER BY download_count DESC, r.created_at DESC
                                   LIMIT 10");
$topDownloadedStmt->execute([':since' => $since]);
$topDownloaded = $topDownloadedStmt->fetchAll(PDO::FETCH_ASSOC);

$topSearchesStmt = $pdo->prepare("SELECT query, COUNT(*) AS search_count
                                 FROM search_logs
                                 WHERE created_at >= :since
                                   AND query IS NOT NULL
                                   AND query <> ''
                                 GROUP BY query
                                 ORDER BY search_count DESC
                                 LIMIT 10");
$topSearchesStmt->execute([':since' => $since]);
$topSearches = $topSearchesStmt->fetchAll(PDO::FETCH_ASSOC);

$zeroSearchesStmt = $pdo->prepare("SELECT query, COUNT(*) AS search_count
                                  FROM search_logs
                                  WHERE created_at >= :since
                                    AND results_count = 0
                                    AND query IS NOT NULL
                                    AND query <> ''
                                  GROUP BY query
                                  ORDER BY search_count DESC
                                  LIMIT 10");
$zeroSearchesStmt->execute([':since' => $since]);
$zeroSearches = $zeroSearchesStmt->fetchAll(PDO::FETCH_ASSOC);

$meta_title = 'Reports - Admin | ' . $APP_NAME;
$meta_description = 'Analytics and insights for ' . $APP_NAME . '.';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h2 class="fw-bold mb-2 page-title">
                <i class="fas fa-chart-bar me-2"></i>Reports
            </h2>
            <p class="text-muted mb-0">Analytics from the last <?= (int)$range ?> days.</p>
        </div>
        <a href="<?= h(app_path('admin')) ?>" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i>Back
        </a>
    </div>
</div>

<div class="d-flex flex-wrap gap-2 mb-4">
    <?php foreach ($ranges as $days): ?>
        <a href="<?= h(app_path('admin/reports')) ?>?range=<?= (int)$days ?>" class="btn btn-sm <?= $days === $range ? 'btn-primary' : 'btn-outline-secondary' ?>">
            Last <?= (int)$days ?> days
        </a>
    <?php endforeach; ?>
</div>

<div class="stats-bar mb-4">
    <div class="d-flex flex-wrap gap-3 justify-content-center">
        <div class="stat-item">
            <i class="fas fa-eye text-primary"></i>
            <div>
                <div class="stat-number"><?= number_format($totalViews) ?></div>
                <div class="stat-label">Total Views</div>
            </div>
        </div>
        <div class="stat-item">
            <i class="fas fa-download icon-success"></i>
            <div>
                <div class="stat-number"><?= number_format($totalDownloads) ?></div>
                <div class="stat-label">Total Downloads</div>
            </div>
        </div>
        <div class="stat-item">
            <i class="fas fa-search icon-warning"></i>
            <div>
                <div class="stat-number"><?= number_format($totalSearches) ?></div>
                <div class="stat-label">Searches</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="form-card h-100">
            <h4 class="mb-3">Most Viewed Resources</h4>
            <?php if (empty($topViewed)): ?>
                <p class="text-muted mb-0">No view data yet.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Type</th>
                                <th class="text-end">Views</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($topViewed as $row): ?>
                                <tr>
                                    <td>
                                        <a href="<?= h(app_path('resource/' . $row['id'])) ?>" class="text-decoration-none">
                                            <?= h($row['title']) ?>
                                        </a>
                                    </td>
                                    <td><?= h(strtoupper($row['type'])) ?></td>
                                    <td class="text-end"><?= number_format($row['view_count']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="form-card h-100">
            <h4 class="mb-3">Most Downloaded Resources</h4>
            <?php if (empty($topDownloaded)): ?>
                <p class="text-muted mb-0">No download data yet.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Type</th>
                                <th class="text-end">Downloads</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($topDownloaded as $row): ?>
                                <tr>
                                    <td>
                                        <a href="<?= h(app_path('resource/' . $row['id'])) ?>" class="text-decoration-none">
                                            <?= h($row['title']) ?>
                                        </a>
                                    </td>
                                    <td><?= h(strtoupper($row['type'])) ?></td>
                                    <td class="text-end"><?= number_format($row['download_count']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="row g-4 mt-1">
    <div class="col-lg-6">
        <div class="form-card h-100">
            <h4 class="mb-3">Top Search Terms</h4>
            <?php if (empty($topSearches)): ?>
                <p class="text-muted mb-0">No search data yet.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Query</th>
                                <th class="text-end">Count</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($topSearches as $row): ?>
                                <tr>
                                    <td><?= h($row['query']) ?></td>
                                    <td class="text-end"><?= number_format($row['search_count']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="form-card h-100">
            <h4 class="mb-3">Zero-Result Searches</h4>
            <?php if (empty($zeroSearches)): ?>
                <p class="text-muted mb-0">No zero-result searches in this range.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Query</th>
                                <th class="text-end">Count</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($zeroSearches as $row): ?>
                                <tr>
                                    <td><?= h($row['query']) ?></td>
                                    <td class="text-end"><?= number_format($row['search_count']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
