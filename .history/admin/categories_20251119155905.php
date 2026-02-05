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
<h4 class="mb-3">Manage Categories</h4>
<div class="row g-3">
  <div class="col-md-4">
    <div class="card">
      <div class="card-body">
        <form method="post" enctype="multipart/form-data">
          <input type="hidden" name="csrf_token" value="<?php echo h($csrf); ?>">
          <input type="hidden" name="action" value="<?php echo $editCategory ? 'update' : 'create'; ?>">
          <input type="hidden" name="category_id" value="<?php echo $editCategory['id'] ?? ''; ?>">
          <div class="mb-3">
            <label class="form-label"><?php echo $editCategory ? 'Edit Category' : 'New Category Name'; ?></label>
            <input type="text" name="name" class="form-control" required
                   value="<?php echo h($editCategory['name'] ?? ''); ?>">
          </div>
          <div class="mb-3">
            <label class="form-label">Cover Image</label>
            <input type="file" name="cover_image" class="form-control" accept="image/*">
            <div class="form-text">JPG, PNG, GIF, or WEBP up to 10MB.</div>
            <?php if ($editCategory && !empty($editCategory['cover_image_path'])): ?>
              <?php $currentCover = app_path($editCategory['cover_image_path']); ?>
              <div class="mt-2">
                <img src="<?php echo h($currentCover); ?>" alt="Category cover" class="img-fluid rounded border">
              </div>
              <div class="form-check mt-2">
                <input class="form-check-input" type="checkbox" name="remove_cover" id="remove_cover">
                <label class="form-check-label" for="remove_cover">Remove current cover</label>
              </div>
            <?php endif; ?>
          </div>
          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary flex-fill">
              <?php echo $editCategory ? 'Update' : 'Add Category'; ?>
            </button>
            <?php if ($editCategory): ?>
              <a href="categories.php" class="btn btn-secondary">Cancel</a>
            <?php endif; ?>
          </div>
        </form>
      </div>
    </div>
  </div>
  <div class="col-md-8">
    <div class="card">
      <div class="card-body p-0">
        <?php if (empty($categories)): ?>
          <p class="p-3 mb-0">No categories yet.</p>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-striped mb-0 align-middle">
              <thead class="table-light">
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
                  <td style="width:80px;">
                    <?php if (!empty($c['cover_image_path'])): ?>
                      <?php $coverUrl = app_path($c['cover_image_path']); ?>
                      <img src="<?php echo h($coverUrl); ?>" alt="Cover" style="width:60px;height:60px;object-fit:cover;" class="rounded border">
                    <?php else: ?>
                      <span class="text-muted">—</span>
                    <?php endif; ?>
                  </td>
                  <td><?php echo h($c['name']); ?></td>
                  <td><?php echo h($c['created_at']); ?></td>
                  <td class="text-end">
                    <a href="categories.php?edit=<?php echo $c['id']; ?>" class="btn btn-sm btn-outline-secondary me-1">Edit</a>
                    <a href="categories.php?delete=<?php echo $c['id']; ?>&csrf=<?php echo h($csrf); ?>"
                       class="btn btn-sm btn-danger"
                       onclick="return confirm('Delete this category?');">Delete</a>
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
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
