<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$editCategory = null;

if (isset($_GET['edit'])) {
    $editId = (int) $_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = :id");
    $stmt->execute([':id' => $editId]);
    $editCategory = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$editCategory) {
        log_warning('Category edit requested but not found', ['category_id' => $editId]);
        flash_message('error', 'Category not found.');
        header('Location: categories.php');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf)) {
        log_warning('Category form CSRF validation failed');
        flash_message('error', 'Invalid session token.');
        header('Location: categories.php');
        exit;
    }

    $name = trim($_POST['name'] ?? '');
    if ($name === '') {
        log_warning('Category form validation failed (empty name)');
        flash_message('error', 'Category name is required.');
        header('Location: categories.php' . (!empty($_POST['category_id']) ? '?edit=' . (int)$_POST['category_id'] : ''));
        exit;
    }

    $action = $_POST['action'] ?? 'create';
    $categoryId = (int)($_POST['category_id'] ?? 0);
    $removeCover = isset($_POST['remove_cover']);
    $existingCategory = null;

    if ($action === 'update') {
        if ($categoryId <= 0) {
            log_warning('Category update missing ID');
            flash_message('error', 'Invalid category selected.');
            header('Location: categories.php');
            exit;
        }
        $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = :id");
        $stmt->execute([':id' => $categoryId]);
        $existingCategory = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$existingCategory) {
            log_warning('Category update requested but not found', ['category_id' => $categoryId]);
            flash_message('error', 'Category not found.');
            header('Location: categories.php');
            exit;
        }
    }

    $coverUpload = handle_file_upload(
        $_FILES['cover_image'] ?? null,
        ['jpg','jpeg','png','gif','webp'],
        __DIR__ . '/../uploads/category_covers',
        'uploads/category_covers',
        10 * 1024 * 1024
    );
    if ($coverUpload['error']) {
        flash_message('error', $coverUpload['error']);
        header('Location: categories.php' . ($categoryId ? '?edit=' . $categoryId : ''));
        exit;
    }

    $coverPath = $existingCategory['cover_image_path'] ?? null;
    if ($coverUpload['path']) {
        delete_uploaded_file($coverPath);
        $coverPath = $coverUpload['path'];
    } elseif ($removeCover && $coverPath) {
        delete_uploaded_file($coverPath);
        $coverPath = null;
    }

    if ($action === 'update') {
        $stmt = $pdo->prepare("UPDATE categories SET name = :name, cover_image_path = :cover WHERE id = :id");
        $stmt->execute([':name' => $name, ':cover' => $coverPath, ':id' => $categoryId]);
        log_info('Category updated', ['category_id' => $categoryId, 'name' => $name]);
        flash_message('success', 'Category updated.');
    } else {
        $stmt = $pdo->prepare("INSERT INTO categories (name, cover_image_path) VALUES (:n, :cover)");
        $stmt->execute([':n' => $name, ':cover' => $coverUpload['path']]);
        log_info('Category created', ['category_id' => $pdo->lastInsertId(), 'name' => $name]);
        flash_message('success', 'Category added.');
    }

    header('Location: categories.php');
    exit;
}

if (isset($_GET['delete'])) {
    $csrf = $_GET['csrf'] ?? '';
    if (!verify_csrf_token($csrf)) {
        log_warning('Category delete CSRF validation failed', ['category_id' => $_GET['delete'] ?? null]);
        flash_message('error', 'Invalid session token.');
        header('Location: categories.php');
        exit;
    }
    $id = (int)$_GET['delete'];
    $stmt = $pdo->prepare("SELECT cover_image_path FROM categories WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $cat = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($cat && !empty($cat['cover_image_path'])) {
        delete_uploaded_file($cat['cover_image_path']);
    }
    $stmt = $pdo->prepare("DELETE FROM categories WHERE id = :id");
    $stmt->execute([':id' => $id]);
    log_info('Category deleted', ['category_id' => $id]);
    flash_message('success', 'Category deleted.');
    header('Location: categories.php');
    exit;
}

$stmt = $pdo->query("SELECT * FROM categories ORDER BY name ASC");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

$csrf = get_csrf_token();
include __DIR__ . '/../includes/header.php';
?>

<style>
    .page-header {
        background: linear-gradient(135deg, rgba(37, 99, 235, 0.1), rgba(59, 130, 246, 0.05));
        border-radius: 20px;
        padding: 2rem;
        margin-bottom: 2rem;
        border: 1px solid rgba(37, 99, 235, 0.1);
    }

    .form-card {
        background: white;
        border-radius: 16px;
        padding: 2rem;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07);
        height: 100%;
        transition: all 0.3s ease;
    }

    .form-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 16px rgba(0, 0, 0, 0.12);
    }

    .form-label {
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 0.5rem;
        font-size: 0.875rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .form-control, .form-select {
        border: 2px solid var(--border-color);
        border-radius: 12px;
        padding: 0.875rem 1.25rem;
        font-size: 1rem;
        transition: all 0.3s ease;
    }

    .form-control:focus, .form-select:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1);
    }

    .cover-preview {
        border-radius: 12px;
        overflow: hidden;
        margin-top: 1rem;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        transition: all 0.3s ease;
    }

    .cover-preview:hover {
        transform: scale(1.02);
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
    }

    .categories-table-card {
        background: white;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07);
    }

    .table {
        margin-bottom: 0;
    }

    .table thead th {
        background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
        color: white;
        font-weight: 700;
        text-transform: uppercase;
        font-size: 0.75rem;
        letter-spacing: 1px;
        padding: 1.25rem 1rem;
        border: none;
    }

    .table tbody td {
        padding: 1.25rem 1rem;
        vertical-align: middle;
        border-bottom: 1px solid var(--border-color);
    }

    .table tbody tr {
        transition: all 0.3s ease;
    }

    .table tbody tr:hover {
        background: rgba(37, 99, 235, 0.03);
        transform: scale(1.01);
    }

    .category-cover-thumb {
        width: 80px;
        height: 80px;
        object-fit: cover;
        border-radius: 12px;
        border: 2px solid var(--border-color);
        transition: all 0.3s ease;
    }

    .category-cover-thumb:hover {
        transform: scale(1.15);
        border-color: var(--primary-color);
        box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
    }

    .empty-state {
        text-align: center;
        padding: 3rem 2rem;
        color: var(--text-secondary);
    }

    .empty-icon {
        font-size: 4rem;
        opacity: 0.3;
        margin-bottom: 1rem;
    }

    .btn-action {
        padding: 0.5rem 1rem;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.875rem;
        transition: all 0.3s ease;
    }

    .btn-action:hover {
        transform: translateY(-2px);
    }

    .form-check-input:checked {
        background-color: var(--primary-color);
        border-color: var(--primary-color);
    }

    .form-text {
        color: var(--text-secondary);
        font-size: 0.8rem;
        margin-top: 0.375rem;
    }
</style>

<!-- Page Header -->
<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h2 class="fw-bold mb-2" style="color: var(--primary-color);">
                <i class="fas fa-folder-open me-3"></i>Manage Categories
            </h2>
            <p class="text-muted mb-0">
                Organize your resources into meaningful categories
            </p>
        </div>
        <div>
            <a href="index.php" class="btn btn-outline-primary">
                <i class="fas fa-arrow-left me-2"></i>Back to Admin
            </a>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Form Column -->
    <div class="col-lg-5">
        <div class="form-card">
            <div class="mb-4">
                <h5 class="fw-bold" style="color: var(--primary-color);">
                    <i class="fas fa-<?= $editCategory ? 'edit' : 'plus-circle' ?> me-2"></i>
                    <?= $editCategory ? 'Edit Category' : 'Add New Category' ?>
                </h5>
                <p class="text-muted small mb-0">
                    <?= $editCategory ? 'Update category information below' : 'Fill in the details to create a new category' ?>
                </p>
            </div>

            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                <input type="hidden" name="action" value="<?= $editCategory ? 'update' : 'create' ?>">
                <input type="hidden" name="category_id" value="<?= $editCategory['id'] ?? '' ?>">
                
                <div class="mb-4">
                    <label class="form-label">
                        <i class="fas fa-tag me-2"></i>Category Name
                    </label>
                    <input type="text" name="name" class="form-control" required
                           placeholder="Enter category name..."
                           value="<?= h($editCategory['name'] ?? '') ?>">
                </div>
                
                <div class="mb-4">
                    <label class="form-label">
                        <i class="fas fa-image me-2"></i>Cover Image
                    </label>
                    <input type="file" name="cover_image" class="form-control" accept="image/*">
                    <div class="form-text">
                        <i class="fas fa-info-circle me-1"></i>
                        Accepted: JPG, PNG, GIF, WEBP (Max 10MB)
                    </div>
                    
                    <?php if ($editCategory && !empty($editCategory['cover_image_path'])): ?>
                        <?php $currentCover = app_path($editCategory['cover_image_path']); ?>
                        <div class="cover-preview">
                            <img src="<?= h($currentCover) ?>" alt="Category cover" class="img-fluid">
                        </div>
                        <div class="form-check mt-3">
                            <input class="form-check-input" type="checkbox" name="remove_cover" id="remove_cover">
                            <label class="form-check-label" for="remove_cover" style="text-transform: none; font-weight: 500;">
                                <i class="fas fa-trash-alt me-2"></i>Remove current cover image
                            </label>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-fill">
                        <i class="fas fa-<?= $editCategory ? 'save' : 'plus' ?> me-2"></i>
                        <?= $editCategory ? 'Update Category' : 'Add Category' ?>
                    </button>
                    <?php if ($editCategory): ?>
                        <a href="categories.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Categories List Column -->
    <div class="col-lg-7">
        <div class="categories-table-card">
            <?php if (empty($categories)): ?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="fas fa-folder-open"></i>
                    </div>
                    <h5 class="fw-bold mb-2">No Categories Yet</h5>
                    <p class="text-muted mb-0">Create your first category to get started organizing your resources.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th style="width: 100px;">Cover</th>
                                <th>Name</th>
                                <th style="width: 180px;">Created</th>
                                <th style="width: 200px;" class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categories as $c): ?>
                                <tr>
                                    <td>
                                        <?php if (!empty($c['cover_image_path'])): ?>
                                            <?php $coverUrl = app_path($c['cover_image_path']); ?>
                                            <img src="<?= h($coverUrl) ?>" alt="Cover" class="category-cover-thumb">
                                        <?php else: ?>
                                            <div class="category-cover-thumb d-flex align-items-center justify-content-center" style="background: linear-gradient(135deg, var(--primary-color), var(--accent-color));">
                                                <i class="fas fa-folder text-white" style="font-size: 2rem;"></i>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="fw-bold" style="color: var(--text-primary);">
                                            <?= h($c['name']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            <i class="fas fa-clock me-1"></i>
                                            <?= date('M d, Y', strtotime($c['created_at'])) ?>
                                        </small>
                                    </td>
                                    <td class="text-end">
                                        <a href="categories.php?edit=<?= $c['id'] ?>" class="btn btn-sm btn-outline-secondary btn-action me-1">
                                            <i class="fas fa-edit me-1"></i>Edit
                                        </a>
                                        <a href="categories.php?delete=<?= $c['id'] ?>&csrf=<?= h($csrf) ?>"
                                           class="btn btn-sm btn-danger btn-action"
                                           onclick="return confirm('Are you sure you want to delete this category?');">
                                            <i class="fas fa-trash-alt me-1"></i>Delete
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