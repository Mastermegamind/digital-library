<?php
require_once __DIR__ . '/includes/auth.php';
redirect_legacy_php('notifications');
require_login();

$user = current_user();
$csrf = get_csrf_token();

$readId = (int)($_GET['read'] ?? 0);
$next = trim((string)($_GET['next'] ?? ''));
if ($readId > 0 && $user) {
    mark_notification_read($user['id'], $readId);
    if ($next !== '' && !preg_match('#^https?://#i', $next) && !str_starts_with($next, '//')) {
        if ($next[0] !== '/') {
            $next = '/' . ltrim($next, '/');
        }
        header('Location: ' . $next);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        flash_message('error', 'Invalid session token.');
        header('Location: ' . app_path('notifications'));
        exit;
    }

    $action = $_POST['action'] ?? '';
    if ($action === 'mark_all') {
        mark_all_notifications_read($user['id']);
        flash_message('success', 'All notifications marked as read.');
    }
    header('Location: ' . app_path('notifications'));
    exit;
}

$status = $_GET['status'] ?? 'all';
if (!in_array($status, ['all', 'unread', 'read'], true)) {
    $status = 'all';
}
$typeFilter = trim((string)($_GET['type'] ?? ''));
$requestedPage = max(1, (int)($_GET['page'] ?? 1));
$page = $requestedPage;
$perPage = (int)($_GET['per_page'] ?? 20);
if (!in_array($perPage, [20, 50, 100], true)) {
    $perPage = 20;
}

$types = get_notification_types_for_user($user['id']);
$result = get_user_notifications_paginated($user['id'], $page, $perPage, $status, $typeFilter !== '' ? $typeFilter : null);
$notifications = $result['items'];
$total = $result['total'];
$totalPages = max(1, (int)ceil($total / $perPage));
$page = min($page, $totalPages);
if ($page !== $requestedPage) {
    $result = get_user_notifications_paginated($user['id'], $page, $perPage, $status, $typeFilter !== '' ? $typeFilter : null);
    $notifications = $result['items'];
}

function notifications_query(array $overrides = []): string {
    $params = [
        'status' => $_GET['status'] ?? 'all',
        'type' => $_GET['type'] ?? '',
        'per_page' => $_GET['per_page'] ?? 20,
    ];
    $params = array_merge($params, $overrides);
    $params = array_filter($params, fn($v) => $v !== '' && $v !== null);
    return http_build_query($params);
}
$meta_title = 'Notifications - ' . $APP_NAME;
$meta_description = 'Your latest notifications from ' . $APP_NAME . '.';
include __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h2 class="fw-bold mb-2 page-title">
                <i class="fas fa-bell me-2"></i>Notifications
            </h2>
            <p class="text-muted mb-0">Stay up to date with new and updated resources.</p>
        </div>
        <form method="post" class="d-flex gap-2">
            <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
            <button type="submit" name="action" value="mark_all" class="btn btn-outline-primary">
                <i class="fas fa-check-double me-2"></i>Mark All Read
            </button>
        </form>
    </div>
</div>

<div class="d-flex flex-wrap gap-2 mb-3">
    <?php
        $statusFilters = [
            'all' => 'All',
            'unread' => 'Unread',
            'read' => 'Read',
        ];
        foreach ($statusFilters as $key => $label):
            $active = $status === $key;
            $url = app_path('notifications') . '?' . notifications_query(['status' => $key, 'page' => 1]);
    ?>
        <a href="<?= h($url) ?>" class="btn btn-sm <?= $active ? 'btn-primary' : 'btn-outline-secondary' ?>">
            <?= h($label) ?>
        </a>
    <?php endforeach; ?>
</div>

<?php if (!empty($types)): ?>
    <div class="mb-4">
        <form method="get" class="d-flex flex-wrap gap-2 align-items-end">
            <div>
                <label class="form-label mb-1">Type</label>
                <select name="type" class="form-select">
                    <option value="">All types</option>
                    <?php foreach ($types as $typeRow): ?>
                        <option value="<?= h($typeRow['type']) ?>" <?= $typeFilter === $typeRow['type'] ? 'selected' : '' ?>>
                            <?= h($typeRow['type']) ?> (<?= (int)$typeRow['count'] ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="form-label mb-1">Per page</label>
                <select name="per_page" class="form-select">
                    <option value="20" <?= $perPage === 20 ? 'selected' : '' ?>>20</option>
                    <option value="50" <?= $perPage === 50 ? 'selected' : '' ?>>50</option>
                    <option value="100" <?= $perPage === 100 ? 'selected' : '' ?>>100</option>
                </select>
            </div>
            <input type="hidden" name="status" value="<?= h($status) ?>">
            <button type="submit" class="btn btn-outline-secondary">
                <i class="fas fa-filter me-2"></i>Apply
            </button>
        </form>
    </div>
<?php endif; ?>

<div class="form-card">
    <?php if (empty($notifications)): ?>
        <div class="empty-state">
            <div class="empty-icon">
                <i class="fas fa-bell-slash"></i>
            </div>
            <h3 class="fw-bold mb-3">No Notifications</h3>
            <p class="text-muted mb-0">You're all caught up.</p>
        </div>
    <?php else: ?>
        <div class="list-group">
            <?php foreach ($notifications as $note): ?>
                <?php
                    $isUnread = empty($note['read_at']);
                    $noteLink = $note['link'] ?: app_path('notifications');
                    $markUrl = app_path('notifications') . '?read=' . (int)$note['id'] . '&next=' . urlencode($noteLink);
                ?>
                <a href="<?= h($markUrl) ?>" class="list-group-item list-group-item-action <?= $isUnread ? 'fw-bold' : '' ?>">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div><?= h($note['title']) ?></div>
                            <?php if (!empty($note['body'])): ?>
                                <small class="text-muted d-block mt-1"><?= h($note['body']) ?></small>
                            <?php endif; ?>
                        </div>
                        <small class="text-muted"><?= date('M d, Y', strtotime($note['created_at'])) ?></small>
                    </div>
                    <?php if ($isUnread): ?>
                        <span class="badge bg-primary mt-2">New</span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php if ($totalPages > 1): ?>
    <div class="pagination-container mt-4">
        <div class="row align-items-center">
            <div class="col-md-6 mb-3 mb-md-0">
                <p class="mb-0 text-muted">
                    Page <strong><?= $page ?></strong> of <strong><?= $totalPages ?></strong>
                </p>
            </div>
            <div class="col-md-6">
                <nav>
                    <ul class="pagination justify-content-md-end justify-content-center mb-0">
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="?<?= notifications_query(['page' => 1]) ?>">
                                <i class="fas fa-angle-double-left"></i>
                            </a>
                        </li>
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="?<?= notifications_query(['page' => max(1, $page - 1)]) ?>">
                                <i class="fas fa-angle-left"></i>
                            </a>
                        </li>
                        <?php
                        $range = 2;
                        $start = max(1, $page - $range);
                        $end = min($totalPages, $page + $range);
                        if ($start > 1) {
                            echo '<li class="page-item"><a class="page-link" href="?' . notifications_query(['page' => 1]) . '">1</a></li>';
                            if ($start > 2) {
                                echo '<li class="page-item disabled"><a class="page-link">...</a></li>';
                            }
                        }
                        for ($i = $start; $i <= $end; $i++): ?>
                            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                <a class="page-link" href="?<?= notifications_query(['page' => $i]) ?>"><?= $i ?></a>
                            </li>
                        <?php endfor;
                        if ($end < $totalPages) {
                            if ($end < $totalPages - 1) {
                                echo '<li class="page-item disabled"><a class="page-link">...</a></li>';
                            }
                            echo '<li class="page-item"><a class="page-link" href="?' . notifications_query(['page' => $totalPages]) . '">' . $totalPages . '</a></li>';
                        }
                        ?>
                        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                            <a class="page-link" href="?<?= notifications_query(['page' => min($totalPages, $page + 1)]) ?>">
                                <i class="fas fa-angle-right"></i>
                            </a>
                        </li>
                        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                            <a class="page-link" href="?<?= notifications_query(['page' => $totalPages]) ?>">
                                <i class="fas fa-angle-double-right"></i>
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
