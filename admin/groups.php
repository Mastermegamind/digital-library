<?php
require_once __DIR__ . '/../includes/auth.php';
redirect_legacy_php('admin/groups');
require_admin();

$stmt = $pdo->query("SELECT g.*, u.name AS creator_name, COUNT(gm.id) AS member_count
                     FROM `groups` g
                     JOIN users u ON g.created_by = u.id
                     LEFT JOIN group_members gm ON gm.group_id = g.id
                     GROUP BY g.id
                     ORDER BY g.created_at DESC");
$groups = $stmt->fetchAll(PDO::FETCH_ASSOC);

$meta_title = 'Groups - Admin | ' . $APP_NAME;
$meta_description = 'Manage user groups and classes.';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h2 class="fw-bold mb-2 page-title">
                <i class="fas fa-users me-2"></i>Groups
            </h2>
            <p class="text-muted mb-0">Overview of all groups and classes.</p>
        </div>
        <a href="<?= h(app_path('groups')) ?>" class="btn btn-outline-primary">
            <i class="fas fa-external-link-alt me-2"></i>View as User
        </a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <?php if (empty($groups)): ?>
            <p class="text-muted mb-0">No groups created yet.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Group</th>
                            <th>Creator</th>
                            <th>Join Code</th>
                            <th>Members</th>
                            <th>Created</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($groups as $g): ?>
                            <tr>
                                <td>
                                    <strong><?= h($g['name']) ?></strong>
                                    <?php if (!empty($g['description'])): ?>
                                        <div class="text-muted small"><?= h(mb_strimwidth($g['description'], 0, 80, '...')) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><?= h($g['creator_name']) ?></td>
                                <td><span class="badge bg-secondary"><?= h($g['join_code']) ?></span></td>
                                <td><?= (int)$g['member_count'] ?></td>
                                <td><?= date('M d, Y', strtotime($g['created_at'])) ?></td>
                                <td>
                                    <a href="<?= h(app_path('group/' . $g['id'])) ?>" class="btn btn-sm btn-outline-primary">Open</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
