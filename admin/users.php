<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$stmt = $pdo->query("
    SELECT u.id, u.name, u.email, u.role, u.created_at, u.profile_image_path,
           up.reg_no, up.enrollment_year, up.staff_id
    FROM users u
    LEFT JOIN user_profiles up ON u.id = up.user_id
    ORDER BY u.created_at DESC
");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
$csrf = get_csrf_token();

include __DIR__ . '/../includes/header.php';
?>

<style>
    .page-header { background: linear-gradient(135deg, rgba(37, 99, 235, 0.1), rgba(59, 130, 246, 0.05)); border-radius: 20px; padding: 2rem; margin-bottom: 2rem; border: 1px solid rgba(37, 99, 235, 0.1); }
    .users-table-card { background: white; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07); }
    .table thead th { background: linear-gradient(135deg, var(--primary-color), var(--accent-color)); color: white; font-weight: 700; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 1px; padding: 1.25rem 1rem; border: none; }
    .table tbody td { padding: 1.25rem 1rem; vertical-align: middle; border-bottom: 1px solid var(--border-color); }
    .table tbody tr:hover { background: rgba(37, 99, 235, 0.03); }
    .user-avatar { width: 60px; height: 60px; object-fit: cover; border-radius: 50%; border: 3px solid var(--border-color); transition: all 0.3s ease; }
    .user-avatar:hover { transform: scale(1.2); border-color: var(--primary-color); box-shadow: 0 8px 16px rgba(37, 99, 235, 0.3); }
    .user-initial-avatar { width: 60px; height: 60px; background: linear-gradient(135deg, var(--primary-color), var(--accent-color)); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 1.5rem; border: 3px solid var(--border-color); }
    .user-name { font-weight: 700; color: var(--text-primary); margin-bottom: 0.25rem; }
    .user-email { font-size: 0.875rem; color: var(--text-secondary); }
    .role-badge { padding: 0.375rem 0.875rem; border-radius: 12px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; display: inline-block; }
    .role-admin { background: rgba(239, 68, 68, 0.1); color: #dc2626; }
    .role-student { background: rgba(37, 99, 235, 0.1); color: #2563eb; }
    .role-staff { background: rgba(34, 197, 94, 0.1); color: #16a34a; }
    .btn-action { padding: 0.5rem 1rem; border-radius: 8px; font-weight: 600; font-size: 0.875rem; transition: all 0.3s ease; margin: 0 0.125rem; }
    .btn-action:hover { transform: translateY(-2px); }
    .stats-bar { background: white; border-radius: 12px; padding: 1rem 1.5rem; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05); margin-bottom: 1.5rem; }
    .stat-item { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.5rem 1rem; border-radius: 8px; background: rgba(37, 99, 235, 0.05); }
    .stat-number { font-weight: 700; color: var(--primary-color); font-size: 1.25rem; }
    .empty-state { text-align: center; padding: 4rem 2rem; }
</style>

<div class="page-header">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h2 class="fw-bold mb-2" style="color: var(--primary-color);">
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
        <div class="stat-item"><i class="fas fa-user-shield" style="color: #dc2626;"></i><div><div class="stat-number"><?= $adminCount ?></div><div class="stat-label">Admins</div></div></div>
        <div class="stat-item"><i class="fas fa-user-graduate" style="color: #2563eb;"></i><div><div class="stat-number"><?= $studentCount ?></div><div class="stat-label">Students</div></div></div>
        <div class="stat-item"><i class="fas fa-chalkboard-teacher" style="color: #16a34a;"></i><div><div class="stat-number"><?= $staffCount ?></div><div class="stat-label">Staff</div></div></div>
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
                        <th style="width: 90px;">Avatar</th>
                        <th>User Information</th>
                        <th style="width: 120px;">Role</th>
                        <th style="width: 160px;">Joined</th>
                        <th style="width: 220px;" class="text-end">Actions</th>
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
                            <td><small class="text-muted"><i class="fas fa-calendar-alt me-1"></i><?= date('M d, Y', strtotime($u['created_at'])) ?></small></td>
                            <td class="text-end">
                                <a href="<?= h(app_path('admin/user/edit/' . $u['id'])) ?>" class="btn btn-sm btn-outline-secondary btn-action"><i class="fas fa-edit"></i></a>
                                <a href="<?= h(app_path('admin/user/delete/' . $u['id'])) ?>?csrf=<?= h($csrf) ?>"
                                   class="btn btn-sm btn-danger btn-action"
                                   onclick="return confirm('Are you sure you want to delete this user?');">
                                    <i class="fas fa-trash-alt"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
