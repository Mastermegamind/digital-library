<?php
require_once __DIR__ . '/../includes/auth.php';
redirect_legacy_php('admin/featured');
require_admin();

$sections = [
    'featured' => 'Featured',
    'editors_picks' => "Editor's Picks",
    'new_this_week' => 'New This Week',
];

$editFeature = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $stmt = $pdo->prepare("SELECT fr.*, r.title AS resource_title
                           FROM featured_resources fr
                           JOIN resources r ON fr.resource_id = r.id
                           WHERE fr.id = :id");
    $stmt->execute([':id' => $editId]);
    $editFeature = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$editFeature) {
        log_warning('Featured edit requested but not found', ['featured_id' => $editId]);
        flash_message('error', 'Featured entry not found.');
        header('Location: ' . app_path('admin/featured'));
        exit;
    }
}

function normalize_feature_datetime(?string $value, string $label, array &$errors): ?string {
    $value = trim((string)$value);
    if ($value === '') {
        return null;
    }
    $ts = strtotime($value);
    if ($ts === false) {
        $errors[] = $label . ' must be a valid date/time.';
        return null;
    }
    return date('Y-m-d H:i:s', $ts);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf)) {
        log_warning('Featured form CSRF validation failed');
        flash_message('error', 'Invalid session token.');
        header('Location: ' . app_path('admin/featured'));
        exit;
    }

    $action = $_POST['action'] ?? 'create';
    $featureId = (int)($_POST['feature_id'] ?? 0);
    $resourceId = (int)($_POST['resource_id'] ?? 0);
    $section = trim($_POST['section'] ?? 'featured');
    $errors = [];

    if (!isset($sections[$section])) {
        $errors[] = 'Invalid section selection.';
    }
    if ($resourceId <= 0) {
        $errors[] = 'Please select a resource.';
    } else {
        $check = $pdo->prepare("SELECT id FROM resources WHERE id = :id");
        $check->execute([':id' => $resourceId]);
        if (!$check->fetch()) {
            $errors[] = 'Selected resource does not exist.';
        }
    }

    $sortOrderRaw = trim((string)($_POST['sort_order'] ?? ''));
    if ($sortOrderRaw === '') {
        $orderStmt = $pdo->prepare("SELECT COALESCE(MAX(sort_order), 0) FROM featured_resources WHERE section = :section");
        $orderStmt->execute([':section' => $section]);
        $sortOrder = (int)$orderStmt->fetchColumn() + 1;
    } else {
        $sortOrder = max(0, (int)$sortOrderRaw);
    }

    $startsAt = normalize_feature_datetime($_POST['starts_at'] ?? null, 'Start time', $errors);
    $endsAt = normalize_feature_datetime($_POST['ends_at'] ?? null, 'End time', $errors);
    if ($startsAt && $endsAt && strtotime($endsAt) < strtotime($startsAt)) {
        $errors[] = 'End time must be after start time.';
    }

    $duplicateCheckSql = "SELECT id FROM featured_resources WHERE resource_id = :rid AND section = :section";
    $duplicateParams = [':rid' => $resourceId, ':section' => $section];
    if ($action === 'update' && $featureId > 0) {
        $duplicateCheckSql .= " AND id <> :id";
        $duplicateParams[':id'] = $featureId;
    }
    $dupStmt = $pdo->prepare($duplicateCheckSql);
    $dupStmt->execute($duplicateParams);
    if ($dupStmt->fetch()) {
        $errors[] = 'This resource is already in the selected section.';
    }

    if (!empty($errors)) {
        flash_message('error', implode(' ', $errors));
        $redirect = app_path('admin/featured') . ($featureId ? '?edit=' . $featureId : '');
        header('Location: ' . $redirect);
        exit;
    }

    if ($action === 'update' && $featureId > 0) {
        $stmt = $pdo->prepare("UPDATE featured_resources
                               SET resource_id = :rid,
                                   section = :section,
                                   sort_order = :sort_order,
                                   starts_at = :starts_at,
                                   ends_at = :ends_at,
                                   updated_at = CURRENT_TIMESTAMP
                               WHERE id = :id");
        $stmt->execute([
            ':rid' => $resourceId,
            ':section' => $section,
            ':sort_order' => $sortOrder,
            ':starts_at' => $startsAt,
            ':ends_at' => $endsAt,
            ':id' => $featureId,
        ]);
        log_info('Featured entry updated', ['featured_id' => $featureId]);
        flash_message('success', 'Featured entry updated.');
    } else {
        $creatorId = current_user()['id'] ?? null;
        $stmt = $pdo->prepare("INSERT INTO featured_resources
                               (resource_id, section, sort_order, starts_at, ends_at, created_by)
                               VALUES (:rid, :section, :sort_order, :starts_at, :ends_at, :created_by)");
        $stmt->execute([
            ':rid' => $resourceId,
            ':section' => $section,
            ':sort_order' => $sortOrder,
            ':starts_at' => $startsAt,
            ':ends_at' => $endsAt,
            ':created_by' => $creatorId,
        ]);
        log_info('Featured entry created', ['featured_id' => $pdo->lastInsertId()]);
        flash_message('success', 'Featured entry added.');
    }

    header('Location: ' . app_path('admin/featured'));
    exit;
}

if (isset($_GET['delete'])) {
    $csrf = $_GET['csrf'] ?? '';
    if (!verify_csrf_token($csrf)) {
        flash_message('error', 'Invalid session token.');
        header('Location: ' . app_path('admin/featured'));
        exit;
    }
    $deleteId = (int)$_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM featured_resources WHERE id = :id");
    $stmt->execute([':id' => $deleteId]);
    log_info('Featured entry deleted', ['featured_id' => $deleteId]);
    flash_message('success', 'Featured entry removed.');
    header('Location: ' . app_path('admin/featured'));
    exit;
}

$resourcesStmt = $pdo->query("SELECT id, title, type, status FROM resources ORDER BY created_at DESC");
$resourceOptions = $resourcesStmt->fetchAll(PDO::FETCH_ASSOC);

$featuredStmt = $pdo->query("SELECT fr.*, r.title, r.type, r.status, r.file_path, r.file_size, c.name AS category_name
                             FROM featured_resources fr
                             JOIN resources r ON fr.resource_id = r.id
                             LEFT JOIN categories c ON r.category_id = c.id
                             ORDER BY fr.section ASC, fr.sort_order ASC, fr.created_at DESC");
$featuredItems = $featuredStmt->fetchAll(PDO::FETCH_ASSOC);

$csrf = get_csrf_token();
$meta_title = 'Featured Sections - Admin | ' . $APP_NAME;
$meta_description = 'Curate homepage featured sections for ' . $APP_NAME . '.';
include __DIR__ . '/../includes/header.php';
?>
<!-- Page Header -->
<div class="page-header">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h2 class="fw-bold mb-2 page-title">
                <i class="fas fa-star me-3"></i>Featured Sections
            </h2>
            <p class="text-muted mb-0">Curate what appears on the library homepage</p>
        </div>
        <a href="<?= h(app_path('admin')) ?>" class="btn btn-outline-primary">
            <i class="fas fa-arrow-left me-2"></i>Back to Admin
        </a>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="form-card">
            <h5 class="section-title">
                <i class="fas fa-<?= $editFeature ? 'edit' : 'plus-circle' ?>"></i>
                <?= $editFeature ? 'Edit Featured Entry' : 'Add Featured Entry' ?>
            </h5>

            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                <input type="hidden" name="action" value="<?= $editFeature ? 'update' : 'create' ?>">
                <input type="hidden" name="feature_id" value="<?= (int)($editFeature['id'] ?? 0) ?>">

                <div class="mb-4">
                    <label class="form-label">
                        <i class="fas fa-book"></i> Resource <span class="required">*</span>
                    </label>
                    <select name="resource_id" class="form-select" required>
                        <option value="">Select a resource...</option>
                        <?php foreach ($resourceOptions as $option): ?>
                            <?php
                                $label = $option['title'] . ' (' . strtoupper($option['type']) . ')';
                                if (!empty($option['status']) && $option['status'] !== 'approved') {
                                    $label .= ' - ' . strtoupper($option['status']);
                                }
                            ?>
                            <option value="<?= (int)$option['id'] ?>" <?= ($editFeature && (int)$editFeature['resource_id'] === (int)$option['id']) ? 'selected' : '' ?>>
                                <?= h($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-4">
                    <label class="form-label">
                        <i class="fas fa-layer-group"></i> Section <span class="required">*</span>
                    </label>
                    <select name="section" class="form-select" required>
                        <?php foreach ($sections as $key => $label): ?>
                            <option value="<?= h($key) ?>" <?= ($editFeature && $editFeature['section'] === $key) ? 'selected' : '' ?>>
                                <?= h($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-4">
                    <label class="form-label">
                        <i class="fas fa-sort"></i> Sort Order
                    </label>
                    <input type="number" name="sort_order" class="form-control" min="0" step="1"
                           value="<?= $editFeature ? (int)$editFeature['sort_order'] : '' ?>"
                           placeholder="Leave blank to auto-append">
                    <div class="form-text">
                        <i class="fas fa-info-circle"></i>Lower numbers appear first in a section.
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label">
                        <i class="fas fa-clock"></i> Schedule (Optional)
                    </label>
                    <?php
                        $startValue = '';
                        $endValue = '';
                        if ($editFeature && !empty($editFeature['starts_at'])) {
                            $startValue = date('Y-m-d\TH:i', strtotime($editFeature['starts_at']));
                        }
                        if ($editFeature && !empty($editFeature['ends_at'])) {
                            $endValue = date('Y-m-d\TH:i', strtotime($editFeature['ends_at']));
                        }
                    ?>
                    <div class="row g-2">
                        <div class="col-12 col-md-6">
                            <input type="datetime-local" name="starts_at" class="form-control" value="<?= h($startValue) ?>">
                            <div class="form-text">Start time</div>
                        </div>
                        <div class="col-12 col-md-6">
                            <input type="datetime-local" name="ends_at" class="form-control" value="<?= h($endValue) ?>">
                            <div class="form-text">End time</div>
                        </div>
                    </div>
                </div>

                <div class="action-buttons">
                    <a href="<?= h(app_path('admin/featured')) ?>" class="btn btn-outline-secondary btn-lg">
                        <i class="fas fa-times me-2"></i>Cancel
                    </a>
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fas fa-<?= $editFeature ? 'save' : 'plus' ?> me-2"></i>
                        <?= $editFeature ? 'Update Entry' : 'Add Entry' ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="resources-table-card">
            <div class="dashboard-section-header">
                <h2>Current Featured Items</h2>
            </div>

            <?php if (empty($featuredItems)): ?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="fas fa-star"></i>
                    </div>
                    <h3 class="fw-bold mb-3">No Featured Items</h3>
                    <p class="text-muted mb-0">Add resources to highlight them on the homepage.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Resource</th>
                                <th>Section</th>
                                <th>Order</th>
                                <th>Schedule</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($featuredItems as $item): ?>
                                <?php
                                    $scheduleParts = [];
                                    if (!empty($item['starts_at'])) {
                                        $scheduleParts[] = 'From ' . date('M d, Y H:i', strtotime($item['starts_at']));
                                    }
                                    if (!empty($item['ends_at'])) {
                                        $scheduleParts[] = 'Until ' . date('M d, Y H:i', strtotime($item['ends_at']));
                                    }
                                    $scheduleText = empty($scheduleParts) ? 'Always' : implode(' | ', $scheduleParts);
                                ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold"><?= h($item['title']) ?></div>
                                        <?php if (can_view_resource_file_size()): ?>
                                            <small class="text-muted d-block mt-1">
                                                <i class="fas fa-hdd me-1"></i>File size: <?= h(get_resource_file_size_label($item)) ?>
                                            </small>
                                        <?php endif; ?>
                                        <?php if (!empty($item['category_name'])): ?>
                                            <small class="text-muted">
                                                <i class="fas fa-folder me-1"></i><?= h($item['category_name']) ?>
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= h($sections[$item['section']] ?? $item['section']) ?></td>
                                    <td><?= (int)$item['sort_order'] ?></td>
                                    <td>
                                        <small class="text-muted"><?= h($scheduleText) ?></small>
                                    </td>
                                    <td>
                                        <?php $status = $item['status'] ?? 'approved'; ?>
                                        <span class="status-badge status-<?= h($status) ?>">
                                            <?= h(ucwords(str_replace('_', ' ', $status))) ?>
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <a href="<?= h(app_path('admin/featured')) ?>?edit=<?= (int)$item['id'] ?>" class="btn btn-sm btn-outline-primary me-2">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="<?= h(app_path('admin/featured')) ?>?delete=<?= (int)$item['id'] ?>&csrf=<?= h($csrf) ?>"
                                           class="btn btn-sm btn-outline-danger"
                                           onclick="return confirm('Remove this featured entry?');">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
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
