<?php
require_once __DIR__ . '/../includes/auth.php';
redirect_legacy_php('admin/users');
require_admin();

$csrf = get_csrf_token();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        flash_message('error', 'Invalid session token.');
        header('Location: ' . app_path('admin/users'));
        exit;
    }

    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        if ($id === (int)current_user()['id'] && in_array($action, ['disable', 'reject'], true)) {
            flash_message('error', 'You cannot disable your own account.');
            header('Location: ' . app_path('admin/users'));
            exit;
        }

        if (in_array($action, ['approve', 'activate', 'reject', 'disable'], true)) {
            $status = match ($action) {
                'approve', 'activate' => 'active',
                'reject' => 'rejected',
                'disable' => 'disabled',
                default => 'active',
            };

            $stmt = $pdo->prepare("UPDATE users SET status = :status, approved_by = :admin_id, approved_at = CURRENT_TIMESTAMP WHERE id = :id");
            $stmt->execute([
                ':status' => $status,
                ':admin_id' => current_user()['id'],
                ':id' => $id,
            ]);

            flash_message('success', 'User status updated.');
            header('Location: ' . app_path('admin/users'));
            exit;
        }
    }
}

$stmt = $pdo->query("
    SELECT u.id, u.name, u.email, u.role, u.created_at, u.profile_image_path, u.status, u.email_verified_at,
           up.reg_no, up.enrollment_year, up.staff_id
    FROM users u
    LEFT JOIN user_profiles up ON u.id = up.user_id
    ORDER BY u.created_at DESC
");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

$meta_title = 'Manage Users - Admin | ' . $APP_NAME;
$meta_description = 'View and manage user accounts in the ' . $APP_NAME . ' admin panel.';
include __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h2 class="fw-bold mb-2 page-title">
                <i class="fas fa-users me-3"></i>Manage Users
            </h2>
            <p class="text-muted mb-0">View and manage all user accounts in the system</p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= h(app_path('admin')) ?>" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-2"></i>Back</a>
            <a href="<?= h(app_path('admin/user/add')) ?>" class="btn btn-success"><i class="fas fa-user-plus me-2"></i>Add User</a>
        </div>
    </div>
</div>

<?php
$adminCount = count(array_filter($users, fn($u) => $u['role'] === 'admin'));
$studentCount = count(array_filter($users, fn($u) => $u['role'] === 'student'));
$staffCount = count(array_filter($users, fn($u) => $u['role'] === 'staff'));
?>
<div class="stats-bar">
    <div class="d-flex flex-wrap gap-3 justify-content-center">
        <div class="stat-item"><i class="fas fa-users text-primary"></i><div><div class="stat-number"><?= count($users) ?></div><div class="stat-label">Total</div></div></div>
        <div class="stat-item"><i class="fas fa-user-shield icon-danger"></i><div><div class="stat-number"><?= $adminCount ?></div><div class="stat-label">Admins</div></div></div>
        <div class="stat-item"><i class="fas fa-user-graduate icon-primary"></i><div><div class="stat-number"><?= $studentCount ?></div><div class="stat-label">Students</div></div></div>
        <div class="stat-item"><i class="fas fa-chalkboard-teacher icon-success"></i><div><div class="stat-number"><?= $staffCount ?></div><div class="stat-label">Staff</div></div></div>
    </div>
</div>

<div class="users-table-card">
    <?php if (empty($users)): ?>
        <div class="empty-state">
            <h3 class="fw-bold mb-3">No Users Yet</h3>
            <p class="text-muted mb-4">Get started by adding your first user account.</p>
            <a href="<?= h(app_path('admin/user/add')) ?>" class="btn btn-primary btn-lg"><i class="fas fa-user-plus me-2"></i>Add First User</a>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th class="table-col-avatar">Avatar</th>
                        <th>User Information</th>
                        <th class="table-col-role">Role</th>
                        <th>Status</th>
                        <th class="table-col-joined">Joined</th>
                        <th class="table-col-actions-sm text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td>
                                <?php if (!empty($u['profile_image_path'])): ?>
                                    <img src="<?= h(app_path($u['profile_image_path'])) ?>" alt="Avatar" class="user-avatar">
                                <?php else: ?>
                                    <div class="user-initial-avatar"><?= strtoupper(substr($u['name'], 0, 1)) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="user-name"><?= h($u['name']) ?></div>
                                <div class="user-email"><i class="fas fa-envelope me-1"></i><?= h($u['email']) ?></div>
                                <?php if ($u['role'] === 'student' && $u['reg_no']): ?>
                                    <small class="text-muted d-block mt-1">Reg: <?= h($u['reg_no']) ?> <?= $u['enrollment_year'] ? '(' . $u['enrollment_year'] . ')' : '' ?></small>
                                <?php elseif ($u['role'] === 'staff' && $u['staff_id']): ?>
                                    <small class="text-muted d-block mt-1">Staff ID: <?= h($u['staff_id']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="role-badge role-<?= h($u['role']) ?>">
                                    <i class="fas fa-<?= $u['role']==='admin'?'shield-alt':($u['role']==='staff'?'chalkboard-teacher':'user-graduate') ?> me-1"></i>
                                    <?= h(ucfirst($u['role'])) ?>
                                </span>
                            </td>
                            <td>
                                <?php $status = $u['status'] ?? 'active'; ?>
                                <span class="status-badge status-<?= h($status) ?>">
                                    <?= h(ucwords(str_replace('_', ' ', $status))) ?>
                                </span>
                                <?php if (empty($u['email_verified_at'])): ?>
                                    <div class="text-muted small mt-1">Email not verified</div>
                                <?php endif; ?>
                            </td>
                            <td><small class="text-muted"><i class="fas fa-calendar-alt me-1"></i><?= date('M d, Y', strtotime($u['created_at'])) ?></small></td>
                            <td class="text-end">
                                <a href="<?= h(app_path('admin/user/edit/' . $u['id'])) ?>" class="btn btn-sm btn-outline-secondary btn-action"><i class="fas fa-edit"></i></a>
                                <?php if (($u['status'] ?? 'active') === 'pending'): ?>
                                    <form method="post" class="d-inline">
                                        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                                        <input type="hidden" name="action" value="approve">
                                        <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-success btn-action" title="Approve">
                                            <i class="fas fa-check"></i>
                                        </button>
                                    </form>
                                    <form method="post" class="d-inline">
                                        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                                        <input type="hidden" name="action" value="reject">
                                        <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger btn-action" title="Reject">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </form>
                                <?php elseif (($u['status'] ?? 'active') === 'active'): ?>
                                    <form method="post" class="d-inline">
                                        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                                        <input type="hidden" name="action" value="disable">
                                        <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-warning btn-action" title="Disable">
                                            <i class="fas fa-ban"></i>
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <form method="post" class="d-inline">
                                        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                                        <input type="hidden" name="action" value="activate">
                                        <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-success btn-action" title="Activate">
                                            <i class="fas fa-check-circle"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                                <form method="post" action="<?= h(app_path('admin/user/delete/' . $u['id'])) ?>" class="d-inline">
                                    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                                    <button type="submit" class="btn btn-sm btn-danger btn-action"
                                            onclick="return confirm('Are you sure you want to delete this user?');">
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
