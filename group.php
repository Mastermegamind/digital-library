<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$user = current_user();
$userId = (int)$user['id'];
$groupId = (int)($_GET['id'] ?? 0);
$group = $groupId > 0 ? get_group($groupId) : null;

if (!$group) {
    flash_message('error', 'Group not found.');
    header('Location: ' . app_path('groups'));
    exit;
}

if (!is_group_member($userId, $groupId) && !is_admin()) {
    flash_message('error', 'You are not a member of this group.');
    header('Location: ' . app_path('groups'));
    exit;
}

$isGroupAdmin = is_group_admin($userId, $groupId) || is_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        flash_message('error', 'Invalid session token.');
        header('Location: ' . app_path('group/' . $groupId));
        exit;
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'leave') {
        leave_group($userId, $groupId);
        flash_message('success', 'You left the group.');
        header('Location: ' . app_path('groups'));
        exit;
    }

    if ($action === 'add_resource' && $isGroupAdmin) {
        $rid = (int)($_POST['resource_id'] ?? 0);
        $due = trim($_POST['due_date'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        if ($rid > 0) {
            add_resource_to_group($groupId, $rid, $userId, $due ?: null, $notes);
            flash_message('success', 'Resource added to group.');
        }
    }

    if ($action === 'remove_resource' && $isGroupAdmin) {
        $rid = (int)($_POST['resource_id'] ?? 0);
        if ($rid > 0) {
            remove_resource_from_group($groupId, $rid);
            flash_message('success', 'Resource removed.');
        }
    }

    if ($action === 'regenerate_code' && $isGroupAdmin) {
        $newCode = regenerate_join_code($groupId);
        flash_message('success', 'New join code generated: ' . $newCode);
    }

    if ($action === 'remove_member' && $isGroupAdmin) {
        $memberId = (int)($_POST['member_id'] ?? 0);
        if ($memberId > 0 && $memberId !== $userId) {
            $pdo->prepare("DELETE FROM group_members WHERE group_id = :gid AND user_id = :uid")
                ->execute([':gid' => $groupId, ':uid' => $memberId]);
            flash_message('success', 'Member removed.');
        }
    }

    header('Location: ' . app_path('group/' . $groupId));
    exit;
}

$members = get_group_members($groupId);
$resources = get_group_resources($groupId);

// Resource search for add modal
$searchResults = [];
if ($isGroupAdmin && isset($_GET['search_resource'])) {
    $q = trim($_GET['search_resource']);
    if ($q !== '') {
        $stmt = $pdo->prepare("SELECT id, title, type FROM resources WHERE COALESCE(status,'approved')='approved' AND (title LIKE :q OR description LIKE :q) LIMIT 10");
        $stmt->execute([':q' => '%' . $q . '%']);
        $searchResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

$csrf = get_csrf_token();
$meta_title = $group['name'] . ' - Group';
$meta_description = $group['description'] ?: 'Group resources and members.';
include __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <a href="<?= h(app_path('groups')) ?>" class="text-decoration-none small">&larr; All Groups</a>
            <h2 class="fw-bold mb-2 page-title">
                <i class="fas fa-users me-2"></i><?= h($group['name']) ?>
            </h2>
            <?php if (!empty($group['description'])): ?>
                <p class="text-muted mb-0"><?= h($group['description']) ?></p>
            <?php endif; ?>
            <div class="d-flex gap-2 mt-2">
                <span class="badge bg-secondary"><?= (int)$group['member_count'] ?> members</span>
                <?php if ($isGroupAdmin): ?>
                    <span class="badge bg-info text-dark">Join Code: <?= h($group['join_code']) ?></span>
                <?php endif; ?>
            </div>
        </div>
        <div class="d-flex gap-2">
            <?php if ($isGroupAdmin): ?>
                <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#addResourceModal">
                    <i class="fas fa-plus me-2"></i>Add Resource
                </button>
                <form method="post" class="d-inline">
                    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                    <input type="hidden" name="action" value="regenerate_code">
                    <button type="submit" class="btn btn-outline-secondary">Regenerate Code</button>
                </form>
            <?php endif; ?>
            <form method="post" onsubmit="return confirm('Leave this group?')">
                <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                <input type="hidden" name="action" value="leave">
                <button type="submit" class="btn btn-outline-danger">Leave</button>
            </form>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-book me-2"></i>Group Resources</h5>
            </div>
            <div class="card-body">
                <?php if (empty($resources)): ?>
                    <p class="text-muted mb-0">No resources added yet.</p>
                <?php else: ?>
                    <div class="list-group">
                        <?php foreach ($resources as $res): ?>
                            <div class="list-group-item d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="fw-bold">
                                        <a href="<?= h(app_path('resource/' . $res['id'])) ?>" class="text-decoration-none"><?= h($res['title']) ?></a>
                                    </div>
                                    <div class="small text-muted">Added by <?= h($res['added_by_name']) ?> • <?= date('M d, Y', strtotime($res['added_at'])) ?></div>
                                    <?php if (!empty($res['due_date'])): ?>
                                        <div class="small">Due: <strong><?= date('M d, Y', strtotime($res['due_date'])) ?></strong></div>
                                    <?php endif; ?>
                                    <?php if (!empty($res['group_notes'])): ?>
                                        <div class="small text-muted"><?= h($res['group_notes']) ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="d-flex gap-2">
                                    <a href="<?= h(app_path('viewer/' . $res['id'])) ?>" class="btn btn-sm btn-outline-primary">Open</a>
                                    <?php if ($isGroupAdmin): ?>
                                        <form method="post">
                                            <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                                            <input type="hidden" name="action" value="remove_resource">
                                            <input type="hidden" name="resource_id" value="<?= (int)$res['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">Remove</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-user-friends me-2"></i>Members</h5>
            </div>
            <div class="card-body">
                <?php if (empty($members)): ?>
                    <p class="text-muted mb-0">No members found.</p>
                <?php else: ?>
                    <div class="list-group">
                        <?php foreach ($members as $m): ?>
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="fw-bold"><?= h($m['name']) ?></div>
                                    <div class="small text-muted"><?= h($m['group_role']) ?> • Joined <?= date('M d, Y', strtotime($m['joined_at'])) ?></div>
                                </div>
                                <?php if ($isGroupAdmin && (int)$m['id'] !== $userId): ?>
                                    <form method="post">
                                        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                                        <input type="hidden" name="action" value="remove_member">
                                        <input type="hidden" name="member_id" value="<?= (int)$m['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Remove</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php if ($isGroupAdmin): ?>
<!-- Add Resource Modal -->
<div class="modal fade" id="addResourceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Resource to Group</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="get" class="mb-3">
                    <input type="hidden" name="id" value="<?= $groupId ?>">
                    <div class="input-group">
                        <input type="text" name="search_resource" class="form-control" placeholder="Search resources..." value="<?= h($_GET['search_resource'] ?? '') ?>">
                        <button class="btn btn-outline-primary" type="submit">Search</button>
                    </div>
                </form>

                <?php if (!empty($searchResults)): ?>
                    <div class="list-group">
                        <?php foreach ($searchResults as $sr): ?>
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <strong><?= h($sr['title']) ?></strong>
                                        <span class="badge bg-secondary ms-1"><?= h(strtoupper($sr['type'])) ?></span>
                                    </div>
                                </div>
                                <form method="post" class="mt-2">
                                    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                                    <input type="hidden" name="action" value="add_resource">
                                    <input type="hidden" name="resource_id" value="<?= (int)$sr['id'] ?>">
                                    <div class="row g-2">
                                        <div class="col-6">
                                            <input type="date" name="due_date" class="form-control form-control-sm">
                                        </div>
                                        <div class="col-6">
                                            <input type="text" name="notes" class="form-control form-control-sm" placeholder="Notes">
                                        </div>
                                    </div>
                                    <button type="submit" class="btn btn-sm btn-primary mt-2">Add</button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php elseif (isset($_GET['search_resource'])): ?>
                    <p class="text-muted">No resources found.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
