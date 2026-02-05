<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$search = trim($_GET['q'] ?? '');
$params = [];
$sql = "SELECT r.*, c.name AS category_name FROM resources r
        LEFT JOIN categories c ON r.category_id = c.id";

if ($search !== '') {
    $sql .= " WHERE r.title LIKE :q OR r.description LIKE :q";
    $params[':q'] = '%' . $search . '%';
}
$sql .= " ORDER BY r.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$resources = $stmt->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
    <h2 class="h4 fw-bold text-primary mb-0">Library Resources</h2>
    <div class="d-flex gap-2">
        <?php if (is_admin()): ?>
            <a href="admin/resource_add.php" class="btn btn-success">
                <i class="fas fa-plus"></i> Add Resource
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- Search Bar -->
<form class="mb-4" method="get">
    <div class="input-group input-group-lg">
        <input type="text" name="q" class="form-control" placeholder="Search titles or descriptions..." value="<?= h($search) ?>">
        <button class="btn btn-outline-primary" type="submit"><i class="fas fa-search"></i></button>
    </div>
</form>

<?php if (empty($resources)): ?>
    <div class="text-center py-5">
        <i class="fas fa-book-open fa-5x text-muted mb-3"></i>
        <p class="lead text-muted">No resources found.</p>
    </div>
<?php else: ?>
    <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 row-cols-xl-5 g-4">
        <?php foreach ($resources as $r): ?>
            <?php $cover = !empty($r['cover_image_path']) ? app_path($r['cover_image_path']) : ''; ?>
            <div class="col">
                <div class="card h-100 shadow-sm border-0 overflow-hidden">
                    <div class="position-relative">
                        <img src="<?= h($cover) ?>" class="card-img-top cover-img" alt="<?= h($r['title']) ?>"
                             data-bs-toggle="modal" data-bs-target="#coverModal<?= $r['id'] ?>">
                        <div class="position-absolute top-0 end-0 p-2">
                            <span class="badge bg-info text-dark"><?= strtoupper($r['type']) ?></span>
                        </div>
                    </div>
                    <div class="card-body d-flex flex-column">
                        <h5 class="card-title mb-1"><?= h($r['title']) ?></h5>
                        <?php if ($r['category_name']): ?>
                            <p class="text-muted small mb-2"><i class="fas fa-folder"></i> <?= h($r['category_name']) ?></p>
                        <?php endif; ?>
                        <p class="text-muted small flex-grow-1"><?= h(substr($r['description'],0,100)) ?><?= strlen($r['description'])>100?'...':'' ?></p>
                        <div class="mt-auto">
                            <a href="viewer.php?id=<?= $r['id'] ?>" class="btn btn-primary w-100">
                                <i class="fas fa-eye"></i> Open
                            </a>
                            <?php if (is_admin()): ?>
                                <a href="admin/resource_edit.php?id=<?= $r['id'] ?>" class="btn btn-outline-secondary btn-sm w-100 mt-2">
                                    <i class="fas fa-edit"></i> Edit
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Zoom Modal -->
            <div class="modal fade" id="coverModal<?= $r['id'] ?>" tabindex="-1">
                <div class="modal-dialog modal-dialog-centered modal-lg">
                    <div class="modal-content bg-transparent border-0">
                        <img src="<?= h($cover) ?>" class="img-fluid rounded shadow" alt="<?= h($r['title']) ?>">
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>