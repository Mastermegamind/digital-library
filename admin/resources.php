<?php
require_once __DIR__ . '/../includes/auth.php';
redirect_legacy_php('admin/resources');
require_admin();

$statusFilter = trim($_GET['status'] ?? '');
$validStatuses = ['approved', 'pending', 'rejected', 'changes_requested', 'flagged'];
$whereClause = '';
$params = [];
if ($statusFilter !== '' && in_array($statusFilter, $validStatuses, true)) {
    $whereClause = "WHERE COALESCE(r.status, 'approved') = :status";
    $params[':status'] = $statusFilter;
}

$stmt = $pdo->prepare("SELECT r.*, COALESCE(r.status, 'approved') AS status, c.name AS category_name, u.name AS submitter_name
                     FROM resources r
                     LEFT JOIN categories c ON r.category_id = c.id
                     LEFT JOIN users u ON r.created_by = u.id
                     $whereClause
                     ORDER BY r.created_at DESC");
$stmt->execute($params);
$resources = $stmt->fetchAll(PDO::FETCH_ASSOC);

$statusCounts = [];
try {
    $statusStmt = $pdo->query("SELECT COALESCE(status, 'approved') AS status, COUNT(*) AS count FROM resources GROUP BY COALESCE(status, 'approved')");
    foreach ($statusStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $statusCounts[$row['status']] = (int)$row['count'];
    }
} catch (Throwable $e) {
    $statusCounts = [];
}

$meta_title = 'Manage Resources - Admin | ' . $APP_NAME;
$meta_description = 'Manage uploaded learning resources in the ' . $APP_NAME . ' admin panel.';
include __DIR__ . '/../includes/header.php';
?>
<!-- Page Header -->
<div class="page-header">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h2 class="fw-bold mb-2 page-title">
                <i class="fas fa-book-open me-3"></i>Manage Resources
            </h2>
            <p class="text-muted mb-0">
                View and manage all learning resources in CONS-UNTH E-LIBRARY
            </p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="<?= h(app_path('admin')) ?>" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i>Back
            </a>
            <a href="<?= h(app_path('admin/moderation')) ?>" class="btn btn-outline-primary">
                <i class="fas fa-gavel me-2"></i>Moderation
            </a>
            <a href="<?= h(app_path('admin/featured')) ?>" class="btn btn-outline-warning">
                <i class="fas fa-star me-2"></i>Featured
            </a>
            <a href="<?= h(app_path('admin/resource/add')) ?>" class="btn btn-success">
                <i class="fas fa-plus-circle me-2"></i>Add Resource
            </a>
        </div>
    </div>
</div>

<div class="d-flex flex-wrap gap-2 mb-3">
    <?php
    $filters = [
        '' => 'All',
        'approved' => 'Approved',
        'pending' => 'Pending',
        'changes_requested' => 'Changes Requested',
        'rejected' => 'Rejected',
        'flagged' => 'Flagged',
    ];
    foreach ($filters as $value => $label):
        $active = ($statusFilter === $value) || ($value === '' && $statusFilter === '');
        $url = $value === '' ? app_path('admin/resources') : app_path('admin/resources') . '?status=' . urlencode($value);
    ?>
        <a href="<?= h($url) ?>" class="btn btn-sm <?= $active ? 'btn-primary' : 'btn-outline-secondary' ?>">
            <?= h($label) ?>
            <?php if ($value !== '' && isset($statusCounts[$value])): ?>
                <span class="badge bg-light text-dark ms-1"><?= (int)$statusCounts[$value] ?></span>
            <?php endif; ?>
        </a>
    <?php endforeach; ?>
</div>

<!-- Statistics Bar -->
<?php
$typeCounts = [];
foreach ($resources as $r) {
    $typeCounts[$r['type']] = ($typeCounts[$r['type']] ?? 0) + 1;
}
?>
<div class="stats-bar">
    <div class="d-flex flex-wrap gap-3 justify-content-center">
        <div class="stat-item">
            <i class="fas fa-book text-primary"></i>
            <div>
                <div class="stat-number"><?= count($resources) ?></div>
                <div class="stat-label">Total Resources</div>
            </div>
        </div>
        <?php if (!empty($statusCounts['pending'])): ?>
            <div class="stat-item">
                <i class="fas fa-hourglass-half icon-warning"></i>
                <div>
                    <div class="stat-number"><?= (int)$statusCounts['pending'] ?></div>
                    <div class="stat-label">Pending</div>
                </div>
            </div>
        <?php endif; ?>
        <?php if (isset($typeCounts['pdf'])): ?>
            <div class="stat-item">
                <i class="fas fa-file-pdf icon-danger"></i>
                <div>
                    <div class="stat-number"><?= $typeCounts['pdf'] ?></div>
                    <div class="stat-label">PDFs</div>
                </div>
            </div>
        <?php endif; ?>
        <?php if (isset($typeCounts['video_file']) || isset($typeCounts['video_link'])): ?>
            <div class="stat-item">
                <i class="fas fa-video icon-warning"></i>
                <div>
                    <div class="stat-number"><?= ($typeCounts['video_file'] ?? 0) + ($typeCounts['video_link'] ?? 0) ?></div>
                    <div class="stat-label">Videos</div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Resources Table -->
<div class="resources-table-card">
    <?php if (empty($resources)): ?>
        <div class="empty-state">
            <div class="empty-icon">
                <i class="fas fa-book-open"></i>
            </div>
            <h3 class="fw-bold mb-3">No Resources Yet</h3>
            <p class="text-muted mb-4">Get started by adding your first learning resource.</p>
            <a href="<?= h(app_path('admin/resource/add')) ?>" class="btn btn-primary btn-lg">
                <i class="fas fa-plus-circle me-2"></i>Add First Resource
            </a>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th class="table-col-cover">Cover</th>
                        <th>Title</th>
                        <th class="table-col-type">Type</th>
                        <th class="table-col-category">Category</th>
                        <th>Status</th>
                        <th class="table-col-created">Created</th>
                        <th class="table-col-actions text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($resources as $r): ?>
                        <tr>
                            <td>
                                <?php if (!empty($r['cover_image_path'])): ?>
                                    <?php $coverUrl = app_path($r['cover_image_path']); ?>
                                    <img src="<?= h($coverUrl) ?>" alt="Cover" class="resource-cover-thumb">
                                <?php else: ?>
                                    <div class="resource-cover-thumb resource-cover-placeholder d-flex align-items-center justify-content-center">
                                        <i class="fas fa-book text-white resource-cover-placeholder-icon"></i>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="fw-bold">
                                    <?= h($r['title']) ?>
                                </div>
                                <?php if (!empty($r['description'])): ?>
                                    <small class="text-muted d-block mt-1 text-ellipsis-300">
                                        <?= h($r['description']) ?>
                                    </small>
                                <?php endif; ?>
                                <?php if (!empty($r['submitter_name'])): ?>
                                    <small class="text-muted d-block mt-1">
                                        Submitted by <?= h($r['submitter_name']) ?>
                                    </small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                    $typeClass = 'badge-default';
                                    if (strpos($r['type'], 'pdf') !== false) $typeClass = 'badge-pdf';
                                    elseif (strpos($r['type'], 'video') !== false) $typeClass = 'badge-video';
                                    elseif (strpos($r['type'], 'doc') !== false || strpos($r['type'], 'ppt') !== false) $typeClass = 'badge-doc';
                                    elseif (strpos($r['type'], 'link') !== false) $typeClass = 'badge-link';
                                ?>
                                <span class="type-badge <?= $typeClass ?>">
                                    <?= h(strtoupper($r['type'])) ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($r['category_name']): ?>
                                    <span class="d-inline-flex align-items-center gap-1">
                                        <i class="fas fa-folder text-muted"></i>
                                        <?= h($r['category_name']) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php $status = $r['status'] ?? 'approved'; ?>
                                <span class="status-badge status-<?= h($status) ?>">
                                    <?= h(ucwords(str_replace('_', ' ', $status))) ?>
                                </span>
                            </td>
                            <td>
                                <small class="text-muted">
                                    <i class="fas fa-clock me-1"></i>
                                    <?= date('M d, Y', strtotime($r['created_at'])) ?>
                                </small>
                            </td>
                            <td class="text-end">
                                <a href="<?= h(app_path('viewer/' . $r['id'])) ?>" class="btn btn-sm btn-primary btn-action" target="_blank">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="<?= h(app_path('admin/resource/edit/' . $r['id'])) ?>" class="btn btn-sm btn-outline-secondary btn-action">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <?php if (($r['status'] ?? 'approved') !== 'approved'): ?>
                                    <form method="post" action="<?= h(app_path('admin/moderation')) ?>" class="d-inline">
                                        <input type="hidden" name="csrf_token" value="<?= h(get_csrf_token()) ?>">
                                        <input type="hidden" name="action" value="resource_approve">
                                        <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                        <input type="hidden" name="return" value="admin/resources<?= $statusFilter !== '' ? '?status=' . h($statusFilter) : '' ?>">
                                        <button type="submit" class="btn btn-sm btn-success btn-action" title="Approve">
                                            <i class="fas fa-check"></i>
                                        </button>
                                    </form>
                                    <form method="post" action="<?= h(app_path('admin/moderation')) ?>" class="d-inline">
                                        <input type="hidden" name="csrf_token" value="<?= h(get_csrf_token()) ?>">
                                        <input type="hidden" name="action" value="resource_reject">
                                        <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                        <input type="hidden" name="return" value="admin/resources<?= $statusFilter !== '' ? '?status=' . h($statusFilter) : '' ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger btn-action" title="Reject">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                                <form method="post" action="<?= h(app_path('admin/resource/delete/' . $r['id'])) ?>" class="d-inline">
                                    <input type="hidden" name="csrf_token" value="<?= h(get_csrf_token()) ?>">
                                    <button type="submit" class="btn btn-sm btn-danger btn-action"
                                            onclick="return confirm('Are you sure you want to delete this resource? This action cannot be undone.');">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
