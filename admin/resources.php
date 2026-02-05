<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$stmt = $pdo->query("SELECT r.*, c.name AS category_name FROM resources r
                     LEFT JOIN categories c ON r.category_id = c.id
                     ORDER BY r.created_at DESC");
$resources = $stmt->fetchAll(PDO::FETCH_ASSOC);

$meta_title = 'Manage Resources - Admin | ' . $APP_NAME;
$meta_description = 'Manage uploaded learning resources in the ' . $APP_NAME . ' admin panel.';
include __DIR__ . '/../includes/header.php';
?>

<style>
    .page-header {
        background: linear-gradient(135deg, rgba(37, 99, 235, 0.1), rgba(59, 130, 246, 0.05));
        border-radius: 20px;
        padding: 2rem;
        margin-bottom: 2rem;
        border: 1px solid rgba(37, 99, 235, 0.1);
    }

    .resources-table-card {
        background: white;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07);
    }

    .table {
        margin-bottom: 0;
    }

    .table thead th {
        background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
        color: white;
        font-weight: 700;
        text-transform: uppercase;
        font-size: 0.75rem;
        letter-spacing: 1px;
        padding: 1.25rem 1rem;
        border: none;
    }

    .table tbody td {
        padding: 1.25rem 1rem;
        vertical-align: middle;
        border-bottom: 1px solid var(--border-color);
    }

    .table tbody tr {
        transition: all 0.3s ease;
    }

    .table tbody tr:hover {
        background: rgba(37, 99, 235, 0.03);
        transform: scale(1.005);
    }

    .resource-cover-thumb {
        width: 80px;
        height: 80px;
        object-fit: cover;
        border-radius: 12px;
        border: 2px solid var(--border-color);
        transition: all 0.3s ease;
        cursor: pointer;
    }

    .resource-cover-thumb:hover {
        transform: scale(1.2);
        border-color: var(--primary-color);
        box-shadow: 0 8px 16px rgba(37, 99, 235, 0.3);
        z-index: 10;
    }

    .type-badge {
        padding: 0.375rem 0.875rem;
        border-radius: 12px;
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        display: inline-block;
    }

    .badge-pdf {
        background: rgba(239, 68, 68, 0.1);
        color: #dc2626;
    }

    .badge-video {
        background: rgba(245, 158, 11, 0.1);
        color: #d97706;
    }

    .badge-doc {
        background: rgba(37, 99, 235, 0.1);
        color: #2563eb;
    }

    .badge-link {
        background: rgba(6, 182, 212, 0.1);
        color: #0891b2;
    }

    .badge-default {
        background: rgba(100, 116, 139, 0.1);
        color: #64748b;
    }

    .btn-action {
        padding: 0.5rem 1rem;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.875rem;
        transition: all 0.3s ease;
        margin: 0 0.125rem;
    }

    .btn-action:hover {
        transform: translateY(-2px);
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

    .stats-bar {
        background: white;
        border-radius: 12px;
        padding: 1rem 1.5rem;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        margin-bottom: 1.5rem;
    }

    .stat-item {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.5rem 1rem;
        border-radius: 8px;
        background: rgba(37, 99, 235, 0.05);
    }

    .stat-number {
        font-weight: 700;
        color: var(--primary-color);
        font-size: 1.25rem;
    }

    .stat-label {
        font-size: 0.875rem;
        color: var(--text-secondary);
    }
</style>

<!-- Page Header -->
<div class="page-header">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h2 class="fw-bold mb-2" style="color: var(--primary-color);">
                <i class="fas fa-book-open me-3"></i>Manage Resources
            </h2>
            <p class="text-muted mb-0">
                View and manage all learning resources in CONS-UNTH E-LIBRARY
            </p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= h(app_path('admin')) ?>" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i>Back
            </a>
            <a href="<?= h(app_path('admin/resource/add')) ?>" class="btn btn-success">
                <i class="fas fa-plus-circle me-2"></i>Add Resource
            </a>
        </div>
    </div>
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
        <?php if (isset($typeCounts['pdf'])): ?>
            <div class="stat-item">
                <i class="fas fa-file-pdf" style="color: #dc2626;"></i>
                <div>
                    <div class="stat-number"><?= $typeCounts['pdf'] ?></div>
                    <div class="stat-label">PDFs</div>
                </div>
            </div>
        <?php endif; ?>
        <?php if (isset($typeCounts['video_file']) || isset($typeCounts['video_link'])): ?>
            <div class="stat-item">
                <i class="fas fa-video" style="color: #d97706;"></i>
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
                        <th style="width: 100px;">Cover</th>
                        <th>Title</th>
                        <th style="width: 120px;">Type</th>
                        <th style="width: 150px;">Category</th>
                        <th style="width: 140px;">Created</th>
                        <th style="width: 280px;" class="text-end">Actions</th>
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
                                    <div class="resource-cover-thumb d-flex align-items-center justify-content-center" style="background: linear-gradient(135deg, var(--primary-color), var(--accent-color));">
                                        <i class="fas fa-book text-white" style="font-size: 2rem;"></i>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="fw-bold" style="color: var(--text-primary);">
                                    <?= h($r['title']) ?>
                                </div>
                                <?php if (!empty($r['description'])): ?>
                                    <small class="text-muted d-block mt-1" style="max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                        <?= h($r['description']) ?>
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
