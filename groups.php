<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$user = current_user();
$userId = (int)$user['id'];
$role = $user['role'] ?? 'student';
$canCreate = in_array($role, ['admin', 'staff'], true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        flash_message('error', 'Invalid session token.');
        header('Location: ' . app_path('groups'));
        exit;
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'create' && $canCreate) {
        $name = trim($_POST['name'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        if ($name === '') {
            flash_message('error', 'Group name is required.');
        } else {
            $groupId = create_group($userId, $name, $desc);
            flash_message('success', 'Group created.');
            header('Location: ' . app_path('group/' . $groupId));
            exit;
        }
    }

    if ($action === 'join') {
        $code = trim($_POST['code'] ?? '');
        if ($code === '') {
            flash_message('error', 'Join code is required.');
        } else {
            $result = join_group_by_code($userId, $code);
            flash_message($result['success'] ? 'success' : 'error', $result['message']);
            if (!empty($result['success']) && !empty($result['group_id'])) {
                header('Location: ' . app_path('group/' . (int)$result['group_id']));
                exit;
            }
        }
    }

    if ($action === 'leave') {
        $groupId = (int)($_POST['group_id'] ?? 0);
        if ($groupId > 0) {
            leave_group($userId, $groupId);
            flash_message('success', 'You left the group.');
        }
    }

    header('Location: ' . app_path('groups'));
    exit;
}

$groups = get_user_groups($userId);
$csrf = get_csrf_token();
$meta_title = 'Groups - ' . $APP_NAME;
$meta_description = 'Manage your study groups and classes.';
include __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h2 class="fw-bold mb-2 page-title">
                <i class="fas fa-users me-2"></i>My Groups
            </h2>
            <p class="text-muted mb-0">Join classes or manage your study groups.</p>
        </div>
        <?php if ($canCreate): ?>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createGroupModal">
                <i class="fas fa-plus me-2"></i>Create Group
            </button>
        <?php endif; ?>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-body">
                <h5 class="card-title"><i class="fas fa-key me-2"></i>Join a Group</h5>
                <form method="post" class="mt-3">
                    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                    <input type="hidden" name="action" value="join">
                    <div class="input-group">
                        <input type="text" name="code" class="form-control" placeholder="Enter join code" required>
                        <button class="btn btn-outline-primary" type="submit">Join</button>
                    </div>
                    <div class="form-text">Ask your instructor for the group code.</div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-body">
                <h5 class="card-title"><i class="fas fa-info-circle me-2"></i>How Groups Work</h5>
                <p class="text-muted mb-0">Groups let instructors share resources with a class, assign due dates, and keep everyone organized.</p>
            </div>
        </div>
    </div>
</div>

<?php if (empty($groups)): ?>
    <div class="text-center py-5">
        <h5 class="text-muted">You are not in any groups yet.</h5>
        <p class="text-muted">Join a group using a code or create one if you are staff/admin.</p>
    </div>
<?php else: ?>
    <div class="row g-3">
        <?php foreach ($groups as $g): ?>
            <div class="col-md-4 col-sm-6">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="card-title">
                            <a href="<?= h(app_path('group/' . $g['id'])) ?>" class="text-decoration-none">
                                <?= h($g['name']) ?>
                            </a>
                        </h5>
                        <?php if (!empty($g['description'])): ?>
                            <p class="card-text text-muted small">
                                <?= h(mb_strimwidth($g['description'], 0, 100, '...')) ?>
                            </p>
                        <?php endif; ?>
                        <div class="d-flex flex-wrap gap-2 align-items-center">
                            <span class="badge bg-secondary"><?= (int)$g['member_count'] ?> members</span>
                            <span class="badge bg-info text-dark">Role: <?= h($g['my_role'] ?? 'member') ?></span>
                        </div>
                    </div>
                    <div class="card-footer bg-transparent d-flex justify-content-between">
                        <a href="<?= h(app_path('group/' . $g['id'])) ?>" class="btn btn-sm btn-outline-primary">Open</a>
                        <form method="post" onsubmit="return confirm('Leave this group?')">
                            <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                            <input type="hidden" name="action" value="leave">
                            <input type="hidden" name="group_id" value="<?= (int)$g['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger">Leave</button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if ($canCreate): ?>
<!-- Create Group Modal -->
<div class="modal fade" id="createGroupModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                <input type="hidden" name="action" value="create">
                <div class="modal-header">
                    <h5 class="modal-title">Create Group</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Group Name</label>
                        <input type="text" name="name" class="form-control" required maxlength="255">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
