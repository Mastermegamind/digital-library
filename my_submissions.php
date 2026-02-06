<?php
require_once __DIR__ . '/includes/auth.php';
redirect_legacy_php('my-submissions');
require_login();

$user = current_user();

$stmt = $pdo->prepare("SELECT r.*, c.name AS category_name
                       FROM resources r
                       LEFT JOIN categories c ON r.category_id = c.id
                       WHERE r.created_by = :user_id
                       ORDER BY r.created_at DESC");
$stmt->execute([':user_id' => $user['id']]);
$resources = $stmt->fetchAll(PDO::FETCH_ASSOC);

$meta_title = 'My Submissions - ' . $APP_NAME;
include __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h2 class="fw-bold mb-2 page-title">
                <i class="fas fa-list me-2"></i>My Submissions
            </h2>
            <p class="text-muted mb-0">Track the status of your submitted resources.</p>
        </div>
        <a href="<?= h(app_path('submit')) ?>" class="btn btn-success">
            <i class="fas fa-plus-circle me-2"></i>Submit New
        </a>
    </div>
</div>

<div class="resources-table-card">
    <?php if (empty($resources)): ?>
        <div class="empty-state">
            <div class="empty-icon">
                <i class="fas fa-folder-open"></i>
            </div>
            <h3 class="fw-bold mb-3">No Submissions Yet</h3>
            <p class="text-muted mb-4">Submit a resource and it will appear here.</p>
            <a href="<?= h(app_path('submit')) ?>" class="btn btn-primary btn-lg">
                <i class="fas fa-upload me-2"></i>Submit Resource
            </a>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Category</th>
                        <th>Status</th>
                        <th>Submitted</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($resources as $r): ?>
                        <tr>
                            <td>
                                <?= h($r['title']) ?>
                                <?php if (can_view_resource_file_size()): ?>
                                    <div class="small text-muted">
                                        <i class="fas fa-hdd me-1"></i>File size: <?= h(get_resource_file_size_label($r)) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td><?= h($r['category_name'] ?? '-') ?></td>
                            <td>
                                <?php $status = $r['status'] ?? 'approved'; ?>
                                <span class="status-badge status-<?= h($status) ?>">
                                    <?= h(ucwords(str_replace('_', ' ', $status))) ?>
                                </span>
                            </td>
                            <td><?= date('M d, Y', strtotime($r['created_at'])) ?></td>
                            <td class="text-end">
                                <?php if (($r['status'] ?? 'approved') === 'approved'): ?>
                                    <a href="<?= h(app_path('viewer/' . $r['id'])) ?>" class="btn btn-sm btn-primary">
                                        <i class="fas fa-eye me-1"></i>View
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted">Awaiting review</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php if (!empty($r['review_notes'])): ?>
                            <tr>
                                <td colspan="5" class="text-muted">Notes: <?= h($r['review_notes']) ?></td>
                            </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
