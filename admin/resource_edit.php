<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/ai.php';
$legacyId = (int)($_GET['id'] ?? 0);
if ($legacyId > 0) {
    redirect_legacy_php('admin/resource/edit/' . $legacyId, ['id' => null]);
}
require_admin();

$id = $legacyId;
$stmt = $pdo->prepare("SELECT * FROM resources WHERE id = :id");
$stmt->execute([':id' => $id]);
$resource = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$resource) {
    flash_message('error', 'Resource not found.');
    log_warning('Resource edit attempted on missing resource', ['resource_id' => $id]);
    header('Location: ' . app_path('admin/resources'));
    exit;
}

$catStmt = $pdo->query("SELECT * FROM categories ORDER BY name ASC");
$categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);
$resourceTags = get_resource_tags($id);
$resourceTagsInput = implode(', ', $resourceTags);
$aiAvailable = function_exists('ai_is_configured') && ai_is_configured();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf)) {
        log_warning('Resource edit CSRF validation failed', ['resource_id' => $id]);
        flash_message('error', 'Invalid session token.');
        header('Location: ' . app_path('admin/resource/edit/' . $id));
        exit;
    }

    $title = trim($_POST['title'] ?? '');
    $type = $_POST['type'] ?? '';
    $description = trim($_POST['description'] ?? '');
    $category_id = $_POST['category_id'] !== '' ? (int)$_POST['category_id'] : null;
    $external_url = trim($_POST['external_url'] ?? '');
    $tagsInput = trim($_POST['tags'] ?? '');

    if ($title === '' || $type === '') {
        flash_message('error', 'Title and type are required.');
        header('Location: ' . app_path('admin/resource/edit/' . $id));
        exit;
    }

    // Handle file replacement
    $allowedExt = ['pdf','epub','mp4','webm','doc','docx','ppt','pptx','xls','xlsx'];
    $maxUploadBytes = 50 * 1024 * 1024;
    $uploadResult = handle_file_upload($_FILES['file'] ?? null, $allowedExt, __DIR__ . '/../uploads', 'uploads', $maxUploadBytes);
    if ($uploadResult['error']) {
        flash_message('error', $uploadResult['error']);
        header('Location: ' . app_path('admin/resource/edit/' . $id));
        exit;
    }

    $filePath = $resource['file_path'];
    $fileSize = $resource['file_size'] ?? null;
    if ($uploadResult['path']) {
        delete_uploaded_file($resource['file_path']);
        $filePath = $uploadResult['path'];
        $fileSize = get_resource_file_size_bytes($filePath);
    }

    // Handle cover image
    $coverResult = handle_file_upload(
        $_FILES['cover_image'] ?? null,
        ['jpg','jpeg','png','gif','webp'],
        __DIR__ . '/../uploads/covers',
        'uploads/covers',
        10 * 1024 * 1024,
        ['max_width' => 1600, 'max_height' => 1600, 'max_pixels' => 12000000, 'quality' => 85]
    );
    if ($coverResult['error']) {
        flash_message('error', $coverResult['error']);
        header('Location: ' . app_path('admin/resource/edit/' . $id));
        exit;
    }

    $coverPath = $resource['cover_image_path'];
    if ($coverResult['path']) {
        delete_uploaded_file($resource['cover_image_path']);
        $coverPath = $coverResult['path'];
    } elseif (isset($_POST['remove_cover'])) {
        delete_uploaded_file($resource['cover_image_path']);
        $coverPath = null;
    }

    $stmt = $pdo->prepare("UPDATE resources SET
        title = :title, description = :description, type = :type, category_id = :category_id,
        file_path = :file_path, file_size = :file_size, cover_image_path = :cover, external_url = :external_url
        WHERE id = :id");
    $stmt->execute([
        ':title' => $title,
        ':description' => $description,
        ':type' => $type,
        ':category_id' => $category_id,
        ':file_path' => $filePath,
        ':file_size' => $fileSize,
        ':cover' => $coverPath,
        ':external_url' => $external_url ?: null,
        ':id' => $id,
    ]);

    $tags = parse_tag_list($tagsInput);
    set_resource_tags($id, $tags);

    if (($resource['status'] ?? 'approved') === 'approved') {
        notify_all_users(
            'resource_updated',
            'Resource updated',
            $title,
            app_path('resource/' . $id),
            current_user()['id'] ?? null
        );

        $resourceLink = app_url('resource/' . $id);
        $emailSubject = 'Resource updated - ' . $APP_NAME;
        $emailHtml = '<p>A resource has been updated.</p>'
            . '<p><strong>' . h($title) . '</strong></p>'
            . '<p><a href="' . h($resourceLink) . '">View resource</a></p>';
        $emailText = "A resource has been updated.\n\n{$title}\n{$resourceLink}";
        notify_all_users_email($emailSubject, $emailHtml, $emailText, current_user()['id'] ?? null);
    }

    log_info('Resource updated', ['resource_id' => $id, 'title' => $title]);
    flash_message('success', 'Resource updated successfully!');
    header('Location: ' . app_path('admin/resources'));
    exit;
}

$csrf = get_csrf_token();
$meta_title = 'Edit Resource - ' . $resource['title'] . ' | ' . $APP_NAME;
$meta_description = 'Edit resource details in the ' . $APP_NAME . ' admin panel.';
include __DIR__ . '/../includes/header.php';
?>


<!-- Page Header -->
<div class="page-header">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h2 class="fw-bold mb-2 page-title">
                <i class="fas fa-edit me-3"></i>Edit Resource
            </h2>
            <p class="text-muted mb-0">Modify details for: <strong><?= h($resource['title']) ?></strong></p>
        </div>
        <div>
            <a href="<?= h(app_path('viewer/' . $id)) ?>" class="btn btn-outline-primary" target="_blank">
                <i class="fas fa-eye me-2"></i>Preview
            </a>
            <a href="<?= h(app_path('admin/resources')) ?>" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i>Back to Resources
            </a>
        </div>
    </div>
</div>

<!-- Form Card -->
<div class="form-card">
    <div class="info-box">
        <div class="info-box-title">
            <i class="fas fa-info-circle"></i>
            Editing Resource
        </div>
        <p class="info-box-text">
            You can update any field. Drag & drop new files to replace existing ones. Leave file fields empty to keep current files.
        </p>
    </div>

    <form method="post" enctype="multipart/form-data" id="resourceForm">
        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">

        <!-- Basic Information -->
        <div class="form-section">
            <h5 class="section-title"><i class="fas fa-info-circle"></i> Basic Information</h5>
            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label"><i class="fas fa-heading"></i> Title <span class="required">*</span></label>
                    <input type="text" name="title" class="form-control" value="<?= h($resource['title']) ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label"><i class="fas fa-tag"></i> Resource Type <span class="required">*</span></label>
                    <select name="type" class="form-select" required>
                        <option value="">Select type...</option>
                        <option value="pdf" <?= $resource['type']=='pdf'?'selected':'' ?>>📄 PDF Document</option>
                        <option value="epub" <?= $resource['type']=='epub'?'selected':'' ?>>📚 EPUB Book</option>
                        <option value="video_file" <?= $resource['type']=='video_file'?'selected':'' ?>>🎥 Video File (MP4/WebM)</option>
                        <option value="video_link" <?= $resource['type']=='video_link'?'selected':'' ?>>🔗 Video Link (YouTube, etc.)</option>
                        <option value="doc" <?= $resource['type']=='doc'?'selected':'' ?>>📝 Word Document (DOC/DOCX)</option>
                        <option value="ppt" <?= $resource['type']=='ppt'?'selected':'' ?>>📊 PowerPoint (PPT/PPTX)</option>
                        <option value="xls" <?= $resource['type']=='xls'?'selected':'' ?>>📈 Excel (XLS/XLSX)</option>
                        <option value="link" <?= $resource['type']=='link'?'selected':'' ?>>🌐 Web Link</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label"><i class="fas fa-folder"></i> Category</label>
                    <select name="category_id" class="form-select">
                        <option value="">-- No Category --</option>
                        <?php foreach ($categories as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= $resource['category_id']==$c['id']?'selected':'' ?>><?= h($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-12">
                    <label class="form-label"><i class="fas fa-align-left"></i> Description</label>
                    <textarea name="description" class="form-control" rows="4"><?= h($resource['description'] ?? '') ?></textarea>
                </div>
                <?php if ($aiAvailable): ?>
                <div class="col-md-12">
                    <label class="form-label"><i class="fas fa-robot"></i> AI Summary</label>
                    <?php if (!empty($resource['ai_summary'])): ?>
                        <div class="alert alert-info small" id="aiSummaryPreview"><?= nl2br(h($resource['ai_summary'])) ?></div>
                    <?php else: ?>
                        <div class="text-muted small mb-2" id="aiSummaryPreview">No summary yet.</div>
                    <?php endif; ?>
                    <button type="button" class="btn btn-outline-primary btn-sm" id="generateSummaryBtn">
                        <i class="fas fa-magic me-1"></i>Generate AI Summary
                    </button>
                </div>
                <?php endif; ?>
                <div class="col-md-12">
                    <label class="form-label"><i class="fas fa-tags"></i> Tags</label>
                    <div class="input-group">
                        <input type="text" name="tags" class="form-control" value="<?= h($resourceTagsInput) ?>"
                               placeholder="e.g., nursing, pediatrics, pharmacology">
                        <button type="button" class="btn btn-outline-secondary" id="aiSuggestTagsBtn" <?= $aiAvailable ? '' : 'disabled' ?>>
                            <i class="fas fa-robot me-1"></i>Suggest
                        </button>
                    </div>
                    <div class="form-text">
                        <i class="fas fa-info-circle"></i>Separate tags with commas.
                        <?php if (!$aiAvailable): ?> AI suggestions are unavailable until an API key is configured.<?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- File Upload Section -->
        <div class="form-section">
            <h5 class="section-title"><i class="fas fa-cloud-upload-alt"></i> File & Cover</h5>
            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label"><i class="fas fa-file"></i> Replace Resource File</label>
                    <div class="file-upload-area" id="fileDropZone">
                        <i class="fas fa-cloud-upload-alt file-upload-icon"></i>
                        <p class="file-upload-text"><strong>Drag & Drop</strong> new file or <strong>click to browse</strong></p>
                        <small class="text-muted">PDF, EPUB, Video, Office files (Max 50MB)</small>
                    </div>
                    <input type="file" name="file" id="fileInput" class="hidden-input">

                    <div class="file-preview" id="filePreview">
                        <i class="fas fa-file-alt file-preview-icon" id="fileIcon"></i>
                        <div class="file-preview-info">
                            <div class="file-preview-name" id="fileName"></div>
                            <div class="file-preview-size" id="fileSize"></div>
                        </div>
                        <button type="button" class="file-preview-remove" id="removeFile">Remove</button>
                    </div>

                    <?php if (!empty($resource['file_path'])): ?>
                        <div class="current-file-info">
                            <i class="fas fa-check-circle text-success icon-xl"></i>
                            <div>
                                <strong>Current file:</strong> <?= h(basename($resource['file_path'])) ?><br>
                                <?php if (can_view_resource_file_size()): ?>
                                    <small class="text-muted d-block">File size: <?= h(get_resource_file_size_label($resource)) ?></small>
                                <?php endif; ?>
                                <small class="text-success">Will be kept if no new file is uploaded</small>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="col-md-6">
                    <label class="form-label"><i class="fas fa-image"></i> Replace Cover Image</label>
                    <div class="file-upload-area" id="coverDropZone">
                        <i class="fas fa-image file-upload-icon"></i>
                        <p class="file-upload-text"><strong>Drag & Drop</strong> image or <strong>click</strong></p>
                        <small class="text-muted">JPG, PNG, GIF, WEBP (Max 10MB)</small>
                    </div>
                    <input type="file" name="cover_image" id="coverInput" class="hidden-input" accept="image/*">

                    <div class="image-preview" id="imagePreview">
                        <img src="" alt="New cover preview" id="previewImg">
                        <button type="button" class="image-preview-remove" id="removeCover">Remove</button>
                    </div>

                    <?php if (!empty($resource['cover_image_path'])): ?>
                        <div class="current-cover">
                            <img src="<?= h(app_path($resource['cover_image_path'])) ?>" alt="Current cover">
                            <button type="button" class="image-preview-remove" id="removeCurrentCover">Remove Current Cover</button>
                            <input type="hidden" name="remove_cover" id="removeCoverFlag" value="0">
                        </div>
                    <?php endif; ?>
                </div>

                <div class="col-md-12">
                    <label class="form-label"><i class="fas fa-link"></i> External URL</label>
                    <input type="url" name="external_url" class="form-control" value="<?= h($resource['external_url'] ?? '') ?>" placeholder="https://example.com/resource">
                    <div class="form-text"><i class="fas fa-info-circle"></i> Optional link (e.g., YouTube, Google Drive)</div>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="action-buttons">
            <a href="<?= h(app_path('admin/resources')) ?>" class="btn btn-outline-secondary btn-lg">
                <i class="fas fa-times me-2"></i>Cancel
            </a>
            <button type="submit" class="btn btn-primary btn-lg">
                <i class="fas fa-save me-2"></i>Update Resource
            </button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const fileDropZone = document.getElementById('fileDropZone');
    const fileInput = document.getElementById('fileInput');
    const filePreview = document.getElementById('filePreview');
    const fileName = document.getElementById('fileName');
    const fileSize = document.getElementById('fileSize');
    const fileIcon = document.getElementById('fileIcon');
    const removeFile = document.getElementById('removeFile');

    const coverDropZone = document.getElementById('coverDropZone');
    const coverInput = document.getElementById('coverInput');
    const imagePreview = document.getElementById('imagePreview');
    const previewImg = document.getElementById('previewImg');
    const removeCover = document.getElementById('removeCover');
    const removeCurrentCover = document.getElementById('removeCurrentCover');
    const removeCoverFlag = document.getElementById('removeCoverFlag');

    const fileIcons = { 'pdf': 'fa-file-pdf', 'epub': 'fa-book', 'mp4': 'fa-file-video', 'webm': 'fa-file-video',
        'doc': 'fa-file-word', 'docx': 'fa-file-word', 'ppt': 'fa-file-powerpoint', 'pptx': 'fa-file-powerpoint',
        'xls': 'fa-file-excel', 'xlsx': 'fa-file-excel', default: 'fa-file' };

    function getFileIcon(filename) {
        const ext = filename.split('.').pop().toLowerCase();
        return fileIcons[ext] || fileIcons.default;
    }

    function formatFileSize(bytes) {
        if (!bytes) return '';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    // === Resource File Handling ===
    fileDropZone.addEventListener('click', () => fileInput.click());
    ['dragover', 'dragenter'].forEach(e => fileDropZone.addEventListener(e, ev => { ev.preventDefault(); fileDropZone.classList.add('dragover'); }));
    ['dragleave', 'drop'].forEach(e => fileDropZone.addEventListener(e, ev => { ev.preventDefault(); fileDropZone.classList.remove('dragover'); }));
    fileDropZone.addEventListener('drop', e => { if (e.dataTransfer.files[0]) { fileInput.files = e.dataTransfer.files; handleFileSelect(e.dataTransfer.files[0]); } });
    fileInput.addEventListener('change', () => { if (fileInput.files[0]) handleFileSelect(fileInput.files[0]); });

    function handleFileSelect(file) {
        fileName.textContent = file.name;
        fileSize.textContent = formatFileSize(file.size);
        fileIcon.className = 'fas ' + getFileIcon(file.name) + ' file-preview-icon';
        filePreview.classList.add('active');
        fileDropZone.style.display = 'none';
    }

    removeFile.addEventListener('click', () => {
        fileInput.value = '';
        filePreview.classList.remove('active');
        fileDropZone.style.display = 'block';
    });

    // === Cover Image Handling ===
    coverDropZone.addEventListener('click', () => coverInput.click());
    ['dragover', 'dragenter'].forEach(e => coverDropZone.addEventListener(e, ev => { ev.preventDefault(); coverDropZone.classList.add('dragover'); }));
    ['dragleave', 'drop'].forEach(e => coverDropZone.addEventListener(e, ev => { ev.preventDefault(); coverDropZone.classList.remove('dragover'); }));
    coverDropZone.addEventListener('drop', e => { const file = e.dataTransfer.files[0]; if (file && file.type.startsWith('image/')) { coverInput.files = e.dataTransfer.files; handleCoverSelect(file); } });
    coverInput.addEventListener('change', () => { if (coverInput.files[0]) handleCoverSelect(coverInput.files[0]); });

    function handleCoverSelect(file) {
        const reader = new FileReader();
        reader.onload = e => {
            previewImg.src = e.target.result;
            imagePreview.classList.add('active');
            coverDropZone.style.display = 'none';
        };
        reader.readAsDataURL(file);
    }

    if (removeCover) removeCover.addEventListener('click', () => { coverInput.value = ''; imagePreview.classList.remove('active'); previewImg.src = ''; coverDropZone.style.display = 'block'; });
    if (removeCurrentCover) {
        removeCurrentCover.addEventListener('click', () => {
            document.querySelector('.current-cover').remove();
            if (removeCoverFlag) removeCoverFlag.value = '1';
        });
    }

    const suggestBtn = document.getElementById('aiSuggestTagsBtn');
    if (suggestBtn) {
        suggestBtn.addEventListener('click', () => {
            const title = document.querySelector('input[name="title"]').value.trim();
            const desc = document.querySelector('textarea[name="description"]').value.trim();
            if (!title && !desc) {
                showToast('Add a title or description first', 'error');
                return;
            }
            suggestBtn.disabled = true;
            fetch(appPath + 'api/suggest-tags', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    csrf_token: csrfToken,
                    title: title,
                    description: desc
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.error) {
                    showToast(data.error, 'error');
                    return;
                }
                const tags = data.tags || [];
                document.querySelector('input[name="tags"]').value = tags.join(', ');
            })
            .catch(() => showToast('Failed to suggest tags', 'error'))
            .finally(() => { suggestBtn.disabled = false; });
        });
    }

    const summaryBtn = document.getElementById('generateSummaryBtn');
    if (summaryBtn) {
        summaryBtn.addEventListener('click', () => {
            summaryBtn.disabled = true;
            fetch(appPath + 'api/summarize', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    csrf_token: csrfToken,
                    resource_id: '<?= (int)$id ?>'
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.error) {
                    showToast(data.error, 'error');
                    return;
                }
                const preview = document.getElementById('aiSummaryPreview');
                if (preview) {
                    preview.classList.remove('text-muted');
                    preview.classList.add('alert', 'alert-info', 'small');
                    preview.textContent = data.summary || '';
                }
                showToast('Summary generated', 'success');
            })
            .catch(() => showToast('Failed to generate summary', 'error'))
            .finally(() => { summaryBtn.disabled = false; });
        });
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
