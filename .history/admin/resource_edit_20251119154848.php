<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$stmt = $pdo->prepare("SELECT * FROM resources WHERE id = :id");
$stmt->execute([':id' => $id]);
$resource = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$resource) {
    flash_message('error', 'Resource not found.');
    log_warning('Resource edit attempted on missing resource', ['resource_id' => $id]);
    header('Location: resources.php');
    exit;
}

$catStmt = $pdo->query("SELECT * FROM categories ORDER BY name ASC");
$categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf)) {
        log_warning('Resource edit CSRF validation failed', ['resource_id' => $id]);
        flash_message('error', 'Invalid session token.');
        header('Location: resource_edit.php?id=' . $id);
        exit;
    }

    $title = trim($_POST['title'] ?? '');
    $type = $_POST['type'] ?? '';
    $description = trim($_POST['description'] ?? '');
    $category_id = $_POST['category_id'] !== '' ? (int)$_POST['category_id'] : null;
    $external_url = trim($_POST['external_url'] ?? '');
    $keep_file = isset($_POST['keep_file']);
    $remove_cover = isset($_POST['remove_cover']);

    if ($title === '' || $type === '') {
        log_warning('Resource edit validation failed', ['resource_id' => $id, 'title' => $title, 'type' => $type]);
        flash_message('error', 'Title and type are required.');
        header('Location: resource_edit.php?id=' . $id);
        exit;
    }

    $allowedExt = ['pdf','epub','mp4','webm','doc','docx','ppt','pptx','xls','xlsx'];
    $maxUploadBytes = 50 * 1024 * 1024; // 50MB
    $uploadResult = handle_file_upload($_FILES['file'] ?? null, $allowedExt, __DIR__ . '/../uploads', 'uploads', $maxUploadBytes);
    if ($uploadResult['error']) {
        log_error('Resource edit upload failed', ['resource_id' => $id, 'error' => $uploadResult['error']]);
        flash_message('error', $uploadResult['error']);
        header('Location: resource_edit.php?id=' . $id);
        exit;
    }

    $filePath = $resource['file_path'];
    if ($uploadResult['path']) {
        delete_uploaded_file($resource['file_path']);
        $filePath = $uploadResult['path'];
    } elseif (!$keep_file && $resource['file_path']) {
        delete_uploaded_file($resource['file_path']);
        $filePath = null;
    }

    $coverUpload = handle_file_upload(
        $_FILES['cover_image'] ?? null,
        ['jpg','jpeg','png','gif','webp'],
        __DIR__ . '/../uploads/covers',
        'uploads/covers',
        10 * 1024 * 1024
    );
    if ($coverUpload['error']) {
        log_error('Resource cover edit upload failed', ['resource_id' => $id, 'error' => $coverUpload['error']]);
        flash_message('error', $coverUpload['error']);
        header('Location: resource_edit.php?id=' . $id);
        exit;
    }

    $coverPath = $resource['cover_image_path'];
    if ($coverUpload['path']) {
        delete_uploaded_file($resource['cover_image_path']);
        $coverPath = $coverUpload['path'];
    } elseif ($remove_cover && $resource['cover_image_path']) {
        delete_uploaded_file($resource['cover_image_path']);
        $coverPath = null;
    }

    $stmt = $pdo->prepare("UPDATE resources
                           SET title = :title,
                               description = :description,
                               type = :type,
                               category_id = :category_id,
                               file_path = :file_path,
                               cover_image_path = :cover,
                               external_url = :external_url
                           WHERE id = :id");
    $stmt->execute([
        ':title' => $title,
        ':description' => $description,
        ':type' => $type,
        ':category_id' => $category_id,
        ':file_path' => $filePath,
        ':cover' => $coverPath,
        ':external_url' => $external_url ?: null,
        ':id' => $id,
    ]);

    log_info('Resource updated', ['resource_id' => $id, 'title' => $title]);

    flash_message('success', 'Resource updated.');
    header('Location: resources.php');
    exit;
}

$csrf = get_csrf_token();
include __DIR__ . '/../includes/header.php';
?>
<h4 class="mb-3">Edit Resource</h4>
<div class="card">
  <div class="card-body">
    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?php echo h($csrf); ?>">
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">Title</label>
          <input type="text" name="title" class="form-control" required
                 value="<?php echo h($resource['title']); ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label">Type</label>
          <select name="type" class="form-select" required>
            <?php
            $types = ['pdf','epub','video_file','video_link','doc','ppt','xls','link'];
            foreach ($types as $t):
            ?>
              <option value="<?php echo $t; ?>" <?php echo $resource['type'] === $t ? 'selected' : ''; ?>>
                <?php echo strtoupper($t); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-12">
          <label class="form-label">Description</label>
          <textarea name="description" class="form-control" rows="2"><?php echo h($resource['description']); ?></textarea>
        </div>
        <div class="col-md-6">
          <label class="form-label">Category</label>
          <select name="category_id" class="form-select">
            <option value="">-- None --</option>
            <?php foreach ($categories as $c): ?>
              <option value="<?php echo $c['id']; ?>" <?php echo $resource['category_id'] == $c['id'] ? 'selected' : ''; ?>>
                <?php echo h($c['name']); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label">Replace File</label>
          <input type="file" name="file" class="form-control">
          <?php if (!empty($resource['file_path'])): ?>
            <div class="form-check mt-1">
              <input class="form-check-input" type="checkbox" name="keep_file" id="keep_file" checked>
              <label class="form-check-label" for="keep_file">
                Keep existing file (<?php echo h(basename($resource['file_path'])); ?>)
              </label>
            </div>
          <?php endif; ?>
        </div>
        <div class="col-md-6">
          <label class="form-label">Cover Image</label>
          <input type="file" name="cover_image" class="form-control" accept="image/*">
          <div class="form-text">JPG, PNG, GIF, or WEBP up to 10MB.</div>
          <?php if (!empty($resource['cover_image_path'])): ?>
            <?php $coverUrl = app_path($resource['cover_image_path']); ?>
            <div class="mt-2">
              <img src="<?php echo h($coverUrl); ?>" alt="Cover preview" class="img-fluid rounded border">
            </div>
            <div class="form-check mt-2">
              <input class="form-check-input" type="checkbox" name="remove_cover" id="remove_cover">
              <label class="form-check-label" for="remove_cover">
                Remove existing cover
              </label>
            </div>
          <?php endif; ?>
        </div>
        <div class="col-md-12">
          <label class="form-label">External URL (if link or video link)</label>
          <input type="url" name="external_url" class="form-control"
                 value="<?php echo h($resource['external_url']); ?>" placeholder="https://...">
        </div>
      </div>
      <div class="mt-3 text-end">
        <button type="submit" class="btn btn-primary">Save</button>
        <a href="resources.php" class="btn btn-secondary">Cancel</a>
      </div>
    </form>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
