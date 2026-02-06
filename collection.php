<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$user = current_user();
$userId = $user['id'];
$collectionId = (int)($_GET['id'] ?? 0);
$collection = get_collection($collectionId);

if (!$collection || ((int)$collection['user_id'] !== $userId && !$collection['is_public'] && !is_admin())) {
    flash_message('error', 'Collection not found.');
    header('Location: ' . app_path('collections'));
    exit;
}

$isOwner = (int)$collection['user_id'] === $userId;

// Handle add resource
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add' && $isOwner) {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $rid = (int)($_POST['resource_id'] ?? 0);
        if ($rid > 0) {
            add_to_collection($collectionId, $rid);
            flash_message('success', 'Resource added to collection.');
        }
    }
    header('Location: ' . app_path('collection/' . $collectionId));
    exit;
}

// Handle remove resource
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'remove' && $isOwner) {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $rid = (int)($_POST['resource_id'] ?? 0);
        remove_from_collection($collectionId, $rid);
        flash_message('success', 'Resource removed.');
    }
    header('Location: ' . app_path('collection/' . $collectionId));
    exit;
}

// Handle update collection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update' && $isOwner) {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $name = trim($_POST['name'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $pub = !empty($_POST['is_public']);
        if ($name !== '') {
            update_collection($collectionId, $name, $desc, $pub);
            flash_message('success', 'Collection updated.');
        }
    }
    header('Location: ' . app_path('collection/' . $collectionId));
    exit;
}

$page = max(1, (int)($_GET['page'] ?? 1));
$itemsData = get_collection_items($collectionId, $page, 20);
$items = $itemsData['items'];
$total = $itemsData['total'];
$totalPages = ceil($total / $itemsData['per_page']);

// For add resource search
$searchResults = [];
if ($isOwner && isset($_GET['search_resource'])) {
    $q = trim($_GET['search_resource']);
    if ($q !== '') {
        global $pdo;
        $stmt = $pdo->prepare("SELECT id, title, type FROM resources WHERE COALESCE(status,'approved')='approved' AND (title LIKE :q OR description LIKE :q) LIMIT 10");
        $stmt->execute([':q' => '%' . $q . '%']);
        $searchResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

$csrf = get_csrf_token();
$title = $collection['name'] . ' — Collection';
require_once __DIR__ . '/includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-start mb-4">
        <div>
            <a href="<?= h(app_path('collections')) ?>" class="text-decoration-none small">&larr; All Collections</a>
            <h2 class="mt-1"><?= h($collection['name']) ?></h2>
            <?php if ($collection['description']): ?>
                <p class="text-muted"><?= h($collection['description']) ?></p>
            <?php endif; ?>
            <span class="badge bg-secondary"><?= $total ?> items</span>
            <?php if ($collection['is_public']): ?>
                <span class="badge bg-success">Public</span>
            <?php endif; ?>
        </div>
        <?php if ($isOwner): ?>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addResourceModal">+ Add Resource</button>
                <button class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#editCollectionModal">Edit</button>
            </div>
        <?php endif; ?>
    </div>

    <?php if (empty($items)): ?>
        <div class="text-center py-5">
            <p class="text-muted">This collection is empty.</p>
            <?php if ($isOwner): ?>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addResourceModal">Add Resources</button>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="row g-3">
            <?php foreach ($items as $resource): ?>
                <div class="col-md-4 col-sm-6">
                    <div class="card h-100">
                        <?php if (!empty($resource['cover_image_path'])): ?>
                            <img src="<?= h(app_path($resource['cover_image_path'])) ?>" class="card-img-top" style="height:160px;object-fit:cover" alt="">
                        <?php endif; ?>
                        <div class="card-body">
                            <span class="badge bg-info mb-1"><?= h(strtoupper($resource['type'])) ?></span>
                            <h6 class="card-title">
                                <a href="<?= h(app_path('resource/' . $resource['id'])) ?>" class="text-decoration-none"><?= h($resource['title']) ?></a>
                            </h6>
                            <?php if (!empty($resource['category_name'])): ?>
                                <small class="text-muted"><?= h($resource['category_name']) ?></small>
                            <?php endif; ?>
                        </div>
                        <div class="card-footer bg-transparent d-flex justify-content-between">
                            <a href="<?= h(app_path('viewer/' . $resource['id'])) ?>" class="btn btn-sm btn-primary">Open</a>
                            <?php if ($isOwner): ?>
                                <form method="post">
                                    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                                    <input type="hidden" name="action" value="remove">
                                    <input type="hidden" name="resource_id" value="<?= (int)$resource['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Remove</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($totalPages > 1): ?>
            <nav class="mt-4">
                <ul class="pagination justify-content-center">
                    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                        <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $p ?>"><?= $p ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php if ($isOwner): ?>
<!-- Add Resource Modal -->
<div class="modal fade" id="addResourceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Resource</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="get" class="mb-3">
                    <input type="hidden" name="id" value="<?= $collectionId ?>">
                    <div class="input-group">
                        <input type="text" name="search_resource" class="form-control" placeholder="Search resources..." value="<?= h($_GET['search_resource'] ?? '') ?>">
                        <button class="btn btn-outline-primary" type="submit">Search</button>
                    </div>
                </form>
                <?php if (!empty($searchResults)): ?>
                    <div class="list-group">
                        <?php foreach ($searchResults as $sr): ?>
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <strong><?= h($sr['title']) ?></strong>
                                    <span class="badge bg-secondary ms-1"><?= h(strtoupper($sr['type'])) ?></span>
                                </div>
                                <form method="post">
                                    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                                    <input type="hidden" name="action" value="add">
                                    <input type="hidden" name="resource_id" value="<?= (int)$sr['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-primary">Add</button>
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

<!-- Edit Collection Modal -->
<div class="modal fade" id="editCollectionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                <input type="hidden" name="action" value="update">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Collection</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Name</label>
                        <input type="text" name="name" class="form-control" required value="<?= h($collection['name']) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="3"><?= h($collection['description'] ?? '') ?></textarea>
                    </div>
                    <div class="form-check">
                        <input type="checkbox" name="is_public" class="form-check-input" value="1" <?= $collection['is_public'] ? 'checked' : '' ?>>
                        <label class="form-check-label">Public</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
