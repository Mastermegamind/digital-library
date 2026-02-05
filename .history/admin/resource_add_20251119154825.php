<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$catStmt = $pdo->query("SELECT * FROM categories ORDER BY name ASC");
$categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf)) {
        log_warning('Resource add CSRF validation failed');
        flash_message('error', 'Invalid session token.');
        header('Location: resource_add.php');
        exit;
    }

    $title = trim($_POST['title'] ?? '');
    $type = $_POST['type'] ?? '';
    $description = trim($_POST['description'] ?? '');
    $category_id = $_POST['category_id'] !== '' ? (int)$_POST['category_id'] : null;
    $external_url = trim($_POST['external_url'] ?? '');

    if ($title === '' || $type === '') {
        log_warning('Resource add validation failed', ['title' => $title, 'type' => $type]);
        flash_message('error', 'Title and type are required.');
        header('Location: resource_add.php');
        exit;
    }

    $allowedExt = ['pdf','epub','mp4','webm','doc','docx','ppt','pptx','xls','xlsx'];
    $maxUploadBytes = 50 * 1024 * 1024; // 50MB
    $uploadResult = handle_file_upload($_FILES['file'] ?? null, $allowedExt, __DIR__ . '/../uploads', 'uploads', $maxUploadBytes);
    if ($uploadResult['error']) {
        log_error('Resource add upload failed', ['error' => $uploadResult['error']]);
        flash_message('error', $uploadResult['error']);
        header('Location: resource_add.php');
        exit;
    }
    $filePath = $uploadResult['path'];

    $coverResult = handle_file_upload(
        $_FILES['cover_image'] ?? null,
        ['jpg','jpeg','png','gif','webp'],
        __DIR__ . '/../uploads/covers',
        'uploads/covers',
        10 * 1024 * 1024
    );
    if ($coverResult['error']) {
        log_error('Resource cover upload failed', ['error' => $coverResult['error']]);
        flash_message('error', $coverResult['error']);
        header('Location: resource_add.php');
        exit;
    }
    $coverPath = $coverResult['path'];

    $stmt = $pdo->prepare("INSERT INTO resources
        (title, description, type, category_id, file_path, cover_image_path, external_url, created_by)
        VALUES (:title, :description, :type, :category_id, :file_path, :cover, :external_url, :created_by)");
    $stmt->execute([
        ':title' => $title,
        ':description' => $description,
        ':type' => $type,
        ':category_id' => $category_id,
        ':file_path' => $filePath,
        ':cover' => $coverPath,
        ':external_url' => $external_url ?: null,
        ':created_by' => current_user()['id'],
    ]);

    log_info('Resource created', [
        'resource_id' => $pdo->lastInsertId(),
        'title' => $title,
        'type' => $type,
    ]);

    flash_message('success', 'Resource added.');
    header('Location: resources.php');
    exit;
}

$csrf = get_csrf_token();
include __DIR__ . '/../includes/header.php';
?>
<h4 class="mb-3">Add Resource</h4>
<div class="card">
  <div class="card-body">
    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?php echo h($csrf); ?>">
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">Title</label>
          <input type="text" name="title" class="form-control" required>
        </div>
        <div class="col-md-6">
          <label class="form-label">Type</label>
          <select name="type" class="form-select" required>
            <option value="">Select type</option>
            <option value="pdf">PDF</option>
            <option value="epub">EPUB</option>
            <option value="video_file">Video File (mp4/webm)</option>
            <option value="video_link">Video Link (YouTube, etc.)</option>
            <option value="doc">Word (DOC/DOCX)</option>
            <option value="ppt">PowerPoint (PPT/PPTX)</option>
            <option value="xls">Excel (XLS/XLSX)</option>
            <option value="link">Web Link</option>
          </select>
        </div>
        <div class="col-md-12">
          <label class="form-label">Description</label>
          <textarea name="description" class="form-control" rows="2"></textarea>
        </div>
        <div class="col-md-6">
          <label class="form-label">Category</label>
          <select name="category_id" class="form-select">
            <option value="">-- None --</option>
            <?php foreach ($categories as $c): ?>
              <option value="<?php echo $c['id']; ?>"><?php echo h($c['name']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label">Upload File (if applicable)</label>
          <input type="file" name="file" class="form-control">
        </div>
        <div class="col-md-6">
          <label class="form-label">Cover Image</label>
          <input type="file" name="cover_image" class="form-control" accept="image/*">
          <div class="form-text">JPG, PNG, GIF, or WEBP up to 10MB.</div>
        </div>
        <div class="col-md-12">
          <label class="form-label">External URL (if link or video link)</label>
          <input type="url" name="external_url" class="form-control" placeholder="https://...">
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
