<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$user = current_user();
$userId = $user['id'];

// Handle create collection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        flash_message('error', 'Invalid session.');
    } else {
        $name = trim($_POST['name'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $isPublic = !empty($_POST['is_public']);
        if ($name === '') {
            flash_message('error', 'Collection name is required.');
        } else {
            $id = create_collection($userId, $name, $desc, $isPublic);
            flash_message('success', 'Collection created.');
            header('Location: ' . app_path('collection/' . $id));
            exit;
        }
    }
}

// Handle delete collection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $cid = (int)($_POST['collection_id'] ?? 0);
        $col = get_collection($cid);
        if ($col && (int)$col['user_id'] === $userId) {
            delete_collection($cid);
            flash_message('success', 'Collection deleted.');
        }
    }
    header('Location: ' . app_path('collections'));
    exit;
}

$collections = get_user_collections($userId);
$csrf = get_csrf_token();
$title = 'My Collections';
$body_class = 'collections-page';
require_once __DIR__ . '/includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>My Collections</h2>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createCollectionModal">
            + New Collection
        </button>
    </div>

    <?php if (empty($collections)): ?>
        <div class="text-center py-5">
            <h5 class="text-muted">No collections yet</h5>
            <p class="text-muted">Create a collection to organize your reading lists.</p>
        </div>
    <?php else: ?>
        <div class="row g-3">
            <?php foreach ($collections as $col): ?>
                <div class="col-md-4 col-sm-6">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="card-title">
                                <a href="<?= h(app_path('collection/' . $col['id'])) ?>" class="text-decoration-none">
                                    <?= h($col['name']) ?>
                                </a>
                            </h5>
                            <?php if ($col['description']): ?>
                                <p class="card-text text-muted small"><?= h(mb_strimwidth($col['description'], 0, 100, '...')) ?></p>
                            <?php endif; ?>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="badge bg-secondary"><?= (int)$col['item_count'] ?> items</span>
                                <?php if ($col['is_public']): ?>
                                    <span class="badge bg-success">Public</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="card-footer bg-transparent d-flex justify-content-between">
                            <a href="<?= h(app_path('collection/' . $col['id'])) ?>" class="btn btn-sm btn-outline-primary">Open</a>
                            <form method="post" onsubmit="return confirm('Delete this collection?')">
                                <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="collection_id" value="<?= (int)$col['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Create Collection Modal -->
<div class="modal fade" id="createCollectionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                <input type="hidden" name="action" value="create">
                <div class="modal-header">
                    <h5 class="modal-title">New Collection</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="col-name" class="form-label">Name</label>
                        <input type="text" name="name" id="col-name" class="form-control" required maxlength="255">
                    </div>
                    <div class="mb-3">
                        <label for="col-desc" class="form-label">Description (optional)</label>
                        <textarea name="description" id="col-desc" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="form-check">
                        <input type="checkbox" name="is_public" id="col-public" class="form-check-input" value="1">
                        <label for="col-public" class="form-check-label">Make this collection public</label>
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

<?php require_once __DIR__ . '/includes/footer.php'; ?>
