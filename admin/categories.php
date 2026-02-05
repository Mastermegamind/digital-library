<?php
require_once __DIR__ . '/../includes/auth.php';
redirect_legacy_php('admin/categories');
require_admin();

$editCategory = null;

if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = :id");
    $stmt->execute([':id' => $editId]);
    $editCategory = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$editCategory) {
        log_warning('Category edit requested but not found', ['category_id' => $editId]);
        flash_message('error', 'Category not found.');
        header('Location: ' . app_path('admin/categories'));
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf)) {
        log_warning('Category form CSRF validation failed');
        flash_message('error', 'Invalid session token.');
        header('Location: ' . app_path('admin/categories'));
        exit;
    }

    $action = $_POST['action'] ?? 'create';
    $categoryId = (int)($_POST['category_id'] ?? 0);

    if ($action === 'delete') {
        if ($categoryId <= 0) {
            flash_message('error', 'Invalid category selection.');
            header('Location: ' . app_path('admin/categories'));
            exit;
        }
        $stmt = $pdo->prepare("SELECT cover_image_path FROM categories WHERE id = :id");
        $stmt->execute([':id' => $categoryId]);
        $cat = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($cat && $coverPath = $cat['cover_image_path'] ?? null) {
            delete_uploaded_file($coverPath);
        }
        $stmt = $pdo->prepare("DELETE FROM categories WHERE id = :id");
        $stmt->execute([':id' => $categoryId]);
        log_info('Category deleted', ['category_id' => $categoryId]);
        flash_message('success', 'Category deleted.');
        header('Location: ' . app_path('admin/categories'));
        exit;
    }

    $name = trim($_POST['name'] ?? '');
    if ($name === '') {
        flash_message('error', 'Category name is required.');
        header('Location: ' . app_path('admin/categories') . (!empty($_POST['category_id']) ? '?edit=' . (int)$_POST['category_id'] : ''));
        exit;
    }

    $coverUpload = handle_file_upload(
        $_FILES['cover_image'] ?? null,
        ['jpg','jpeg','png','gif','webp'],
        __DIR__ . '/../uploads/category_covers',
        'uploads/category_covers',
        10 * 1024 * 1024,
        ['max_width' => 1600, 'max_height' => 1600, 'max_pixels' => 12000000, 'quality' => 85]
    );
    if ($coverUpload['error']) {
        flash_message('error', $coverUpload['error']);
        header('Location: ' . app_path('admin/categories') . ($categoryId ? '?edit=' . $categoryId : ''));
        exit;
    }

    $existingCategory = null;
    if ($action === 'update' && $categoryId > 0) {
        $stmt = $pdo->prepare("SELECT cover_image_path FROM categories WHERE id = :id");
        $stmt->execute([':id' => $categoryId]);
        $existingCategory = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    $coverPath = $existingCategory['cover_image_path'] ?? null;

    if ($coverUpload['path']) {
        if ($coverPath) delete_uploaded_file($coverPath);
        $coverPath = $coverUpload['path'];
    } elseif (isset($_POST['remove_cover']) && $coverPath) {
        delete_uploaded_file($coverPath);
        $coverPath = null;
    }

    if ($action === 'update') {
        $stmt = $pdo->prepare("UPDATE categories SET name = :name, cover_image_path = :cover WHERE id = :id");
        $stmt->execute([':name' => $name, ':cover' => $coverPath, ':id' => $categoryId]);
        log_info('Category updated', ['category_id' => $categoryId, 'name' => $name]);
        flash_message('success', 'Category updated successfully!');
    } else {
        $stmt = $pdo->prepare("INSERT INTO categories (name, cover_image_path) VALUES (:n, :cover)");
        $stmt->execute([':n' => $name, ':cover' => $coverPath]);
        log_info('Category created', ['category_id' => $pdo->lastInsertId(), 'name' => $name]);
        flash_message('success', 'Category added successfully!');
    }

    header('Location: ' . app_path('admin/categories'));
    exit;
}

if (isset($_GET['delete'])) {
    $csrf = $_GET['csrf'] ?? '';
    if (!verify_csrf_token($csrf)) {
        flash_message('error', 'Invalid session token.');
        header('Location: ' . app_path('admin/categories'));
        exit;
    }
    $id = (int)$_GET['delete'];
    $stmt = $pdo->prepare("SELECT cover_image_path FROM categories WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $cat = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($cat && $coverPath = $cat['cover_image_path'] ?? null) {
        delete_uploaded_file($coverPath);
    }
    $stmt = $pdo->prepare("DELETE FROM categories WHERE id = :id");
    $stmt->execute([':id' => $id]);
    log_info('Category deleted', ['category_id' => $id]);
    flash_message('success', 'Category deleted.');
    header('Location: ' . app_path('admin/categories'));
    exit;
}

$stmt = $pdo->query("SELECT * FROM categories ORDER BY name ASC");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

$csrf = get_csrf_token();
$meta_title = 'Manage Categories - Admin | ' . $APP_NAME;
$meta_description = 'Create and manage resource categories in the ' . $APP_NAME . ' admin panel.';
include __DIR__ . '/../includes/header.php';
?>
<!-- Page Header -->
<div class="page-header">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h2 class="fw-bold mb-2 page-title">
                <i class="fas fa-folder-open me-3"></i>Manage Categories
            </h2>
            <p class="text-muted mb-0">Organize your resources with beautiful categorized covers</p>
        </div>
        <a href="<?= h(app_path('admin')) ?>" class="btn btn-outline-primary">
            <i class="fas fa-arrow-left me-2"></i>Back to Admin
        </a>
    </div>
</div>

<div class="row g-4">
    <!-- Form Column -->
    <div class="col-lg-5">
        <div class="form-card">
            <h5 class="section-title">
                <i class="fas fa-<?= $editCategory ? 'edit' : 'plus-circle' ?>"></i>
                <?= $editCategory ? 'Edit Category' : 'Add New Category' ?>
            </h5>

            <form method="post" enctype="multipart/form-data" id="categoryForm">
                <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                <input type="hidden" name="action" value="<?= $editCategory ? 'update' : 'create' ?>">
                <input type="hidden" name="category_id" value="<?= $editCategory['id'] ?? '' ?>">

                <div class="mb-4">
                    <label class="form-label">
                        <i class="fas fa-tag"></i> Category Name <span class="required">*</span>
                    </label>
                    <input type="text" name="name" class="form-control" required placeholder="e.g., Computer Science, Mathematics..."
                           value="<?= h($editCategory['name'] ?? '') ?>">
                </div>

                <div class="mb-4">
                    <label class="form-label">
                        <i class="fas fa-image"></i> Cover Image
                    </label>

                    <div class="file-upload-area" id="coverDropZone">
                        <i class="fas fa-image file-upload-icon"></i>
                        <p class="file-upload-text">
                            <strong>Drag & Drop</strong> image here or <strong>click to browse</strong>
                        </p>
                        <small class="text-muted">JPG, PNG, GIF, WEBP (Max 10MB)</small>
                    </div>
                    <input type="file" name="cover_image" id="coverInput" class="hidden-input" accept="image/*">

                    <!-- New uploaded preview -->
                    <div class="image-preview" id="imagePreview">
                        <img src="" alt="Preview" id="previewImg">
                        <button type="button" class="image-preview-remove" id="removeNewCover">Remove</button>
                    </div>

                    <!-- Current cover (only in edit mode) -->
                    <?php if ($editCategory && !empty($editCategory['cover_image_path'])): ?>
                        <?php $currentCoverUrl = app_path($editCategory['cover_image_path']); ?>
                        <div class="current-cover" id="currentCover">
                            <img src="<?= h($currentCoverUrl) ?>" alt="Current cover">
                            <button type="button" class="current-cover-remove" id="removeCurrentCoverBtn">Remove Cover</button>
                            <input type="hidden" name="remove_cover" id="removeCoverFlag" value="0">
                        </div>
                        <div class="form-text mt-3">
                            <i class="fas fa-info-circle"></i>
                            Upload a new image to replace, or remove the current one
                        </div>
                    <?php endif; ?>
                </div>

                <div class="action-buttons">
                    <a href="<?= h(app_path('admin/categories')) ?>" class="btn btn-outline-secondary btn-lg">
                        <i class="fas fa-times me-2"></i>Cancel
                    </a>
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fas fa-<?= $editCategory ? 'save' : 'plus' ?> me-2"></i>
                        <?= $editCategory ? 'Update Category' : 'Add Category' ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Categories List -->
    <div class="col-lg-7">
        <div class="categories-table-card">
            <?php if (empty($categories)): ?>
                <div class="empty-state">
                    <div class="empty-icon"><i class="fas fa-folder-open"></i></div>
                    <h5 class="fw-bold mb-3">No Categories Yet</h5>
                    <p>Create your first category using the form on the left!</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>Cover</th>
                                <th>Name</th>
                                <th>Created</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categories as $c): ?>
                                <tr>
                                    <td>
                                        <?php if (!empty($c['cover_image_path'])): ?>
                                    <img src="<?= h(app_path($c['cover_image_path'])) ?>" alt="Cover" class="category-cover-thumb">
                                        <?php else: ?>
                                            <div class="category-cover-thumb category-cover-placeholder d-flex align-items-center justify-content-center">
                                                <i class="fas fa-folder text-white category-cover-placeholder-icon"></i>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="fw-bold"><?= h($c['name']) ?></td>
                                    <td><small class="text-muted"><?= date('M d, Y', strtotime($c['created_at'])) ?></small></td>
                                    <td class="text-end">
                                        <a href="<?= h(app_path('admin/categories')) ?>?edit=<?= $c['id'] ?>" class="btn btn-sm btn-outline-secondary">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <form method="post" action="<?= h(app_path('admin/categories')) ?>" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="category_id" value="<?= (int)$c['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-danger ms-1"
                                                    onclick="return confirm('Delete this category? This cannot be undone.');">
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
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const coverDropZone = document.getElementById('coverDropZone');
    const coverInput = document.getElementById('coverInput');
    const imagePreview = document.getElementById('imagePreview');
    const previewImg = document.getElementById('previewImg');
    const removeNewCover = document.getElementById('removeNewCover');
    const removeCurrentCoverBtn = document.getElementById('removeCurrentCoverBtn');
    const currentCover = document.getElementById('currentCover');
    const removeCoverFlag = document.getElementById('removeCoverFlag');

    // Drag & Drop + Click
    coverDropZone.addEventListener('click', () => coverInput.click());

    ['dragover', 'dragenter'].forEach(e => coverDropZone.addEventListener(e, ev => {
        ev.preventDefault();
        coverDropZone.classList.add('dragover');
    }));
    ['dragleave', 'drop'].forEach(e => coverDropZone.addEventListener(e, ev => {
        ev.preventDefault();
        coverDropZone.classList.remove('dragover');
    }));

    coverDropZone.addEventListener('drop', e => {
        const file = e.dataTransfer.files[0];
        if (file && file.type.startsWith('image/')) {
            coverInput.files = e.dataTransfer.files;
            handleFileSelect(file);
        }
    });

    coverInput.addEventListener('change', () => {
        if (coverInput.files[0]) handleFileSelect(coverInput.files[0]);
    });

    function handleFileSelect(file) {
        const reader = new FileReader();
        reader.onload = e => {
            previewImg.src = e.target.result;
            imagePreview.classList.add('active');
            coverDropZone.style.display = 'none';
            if (currentCover) currentCover.style.display = 'none'; // hide current when new is selected
        };
        reader.readAsDataURL(file);
    }

    if (removeNewCover) {
        removeNewCover.addEventListener('click', () => {
            coverInput.value = '';
            imagePreview.classList.remove('active');
            previewImg.src = '';
            coverDropZone.style.display = 'block';
            if (currentCover) currentCover.style.display = 'block';
        });
    }

    if (removeCurrentCoverBtn) {
        removeCurrentCoverBtn.addEventListener('click', () => {
            currentCover.remove();
            if (removeCoverFlag) removeCoverFlag.value = '1';
        });
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
