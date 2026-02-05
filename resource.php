<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$stmt = $pdo->prepare("SELECT r.*, c.name AS category_name, u.name AS creator_name
                       FROM resources r
                       LEFT JOIN categories c ON r.category_id = c.id
                       LEFT JOIN users u ON r.created_by = u.id
                       WHERE r.id = :id");
$stmt->execute([':id' => $id]);
$resource = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$resource) {
    flash_message('error', 'Resource not found.');
    log_warning('Resource view requested for missing resource', ['resource_id' => $id]);
    header('Location: ' . app_path(''));
    exit;
}

$title       = $resource['title'];
$type        = $resource['type'];
$filePath    = $resource['file_path'];
$externalUrl = $resource['external_url'];

$meta_title = $title . ' - ' . $APP_NAME;
$meta_description = !empty($resource['description'])
    ? substr(trim(strip_tags($resource['description'])), 0, 160)
    : 'View resource details in ' . ($FULL_APP_NAME ?? $APP_NAME) . '.';

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');
$baseUrl = $scheme . '://' . $host . $basePath . '/';

$fileUrl = $filePath ? $baseUrl . $filePath : null;

include __DIR__ . '/includes/header.php';
?>
<h3 class="mb-1"><?php echo h($title); ?></h3>
<span class="badge bg-info text-dark"><?php echo h(strtoupper($type)); ?></span>
<?php if (!empty($resource['category_name'])): ?>
  <span class="badge bg-secondary"><?php echo h($resource['category_name']); ?></span>
<?php endif; ?>
<?php if (!empty($resource['creator_name'])): ?>
  <span class="badge bg-light text-muted">by <?php echo h($resource['creator_name']); ?></span>
<?php endif; ?>

<?php if (!empty($resource['description'])): ?>
  <p class="mt-3"><?php echo nl2br(h($resource['description'])); ?></p>
<?php endif; ?>

<?php if (!empty($resource['cover_image_path'])): ?>
  <div class="my-3">
    <img src="<?php echo h(app_path($resource['cover_image_path'])); ?>" alt="Cover image" class="img-fluid rounded shadow-sm border">
  </div>
<?php endif; ?>

<div class="viewer-container mt-3 rounded shadow-sm" style="height:80vh;background:#000;">
<?php if ($type === 'pdf' && $fileUrl): ?>

    <iframe src="<?php echo h($fileUrl); ?>#toolbar=0&navpanes=0"
            allowfullscreen style="width:100%;height:100%;border:0;"></iframe>

<?php elseif ($type === 'epub' && $fileUrl): ?>

    <div id="epub-reader" style="width:100%;height:100%;background:#fff;"></div>

<?php elseif ($type === 'video_file' && $fileUrl): ?>

    <video controls controlsList="nodownload" oncontextmenu="return false;" style="width:100%;height:100%;">
        <source src="<?php echo h($fileUrl); ?>" type="video/mp4">
        Your browser does not support the video tag.
    </video>

<?php elseif ($type === 'video_link' && $externalUrl): ?>

    <?php
    $youtubeEmbed = null;
    if (strpos($externalUrl, 'youtube.com/watch') !== false || strpos($externalUrl, 'youtu.be') !== false) {
        if (preg_match('~(?:v=|youtu\.be/)([A-Za-z0-9_-]{11})~', $externalUrl, $m)) {
            $youtubeEmbed = 'https://www.youtube.com/embed/' . $m[1];
        }
    }
    ?>
    <?php if ($youtubeEmbed): ?>
        <iframe src="<?php echo h($youtubeEmbed); ?>" allowfullscreen
                style="width:100%;height:100%;border:0;"></iframe>
    <?php else: ?>
        <div class="p-3 text-white">
            <p>Video link:</p>
            <a href="<?php echo h($externalUrl); ?>" target="_blank" class="btn btn-light">Open Video</a>
        </div>
    <?php endif; ?>

<?php elseif (in_array($type, ['doc','ppt','xls'], true) && $fileUrl): ?>

    <?php
    if (!empty($ONLYOFFICE_BASE_URL)) {
        $encoded = urlencode($fileUrl);
        $viewerUrl = rtrim($ONLYOFFICE_BASE_URL, '/') . "/web-apps/apps/documenteditor/main/index.html?fileID=" . $encoded;
    } else {
        $encoded = urlencode($fileUrl);
        $viewerUrl = "https://view.officeapps.live.com/op/embed.aspx?src=" . $encoded;
    }
    ?>
    <iframe src="<?php echo h($viewerUrl); ?>" style="width:100%;height:100%;border:0;"></iframe>

<?php elseif ($type === 'link' && $externalUrl): ?>

    <iframe src="<?php echo h($externalUrl); ?>" style="width:100%;height:100%;border:0;"></iframe>

<?php else: ?>

    <div class="p-3 text-white">
        <p>Preview not available for this resource.</p>
    </div>

<?php endif; ?>
</div>

<div class="mt-3">
  <a href="<?= h(app_path('')) ?>" class="btn btn-secondary">Back to list</a>
</div>

<?php if ($type === 'epub' && $fileUrl): ?>
<!-- Load JSZip first (required for EPUB) -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<!-- Load epub.js -->
<script src="https://cdn.jsdelivr.net/npm/epubjs@0.3.93/dist/epub.min.js"></script>
<script>
(function() {
    // Wait for libraries to load
    if (typeof ePub === 'undefined' || typeof JSZip === 'undefined') {
        console.error('Required libraries failed to load');
        document.getElementById('epub-reader').innerHTML = '<p class="text-center p-3">Failed to load EPUB viewer libraries.</p>';
        return;
    }

    const container = document.getElementById('epub-reader');
    const loading = document.getElementById('epub-loading');
    
    try {
        // Initialize the book
        const book = ePub("<?php echo h($fileUrl); ?>");
        
        // Render to the container
        const rendition = book.renderTo("epub-reader", {
            width: "100%",
            height: "100%",
            spread: "none"
        });
        
        // Display the book
        rendition.display().then(function() {
            if (loading) loading.style.display = 'none';
            console.log('EPUB loaded successfully');
        }).catch(function(err) {
            console.error('EPUB display error:', err);
            container.innerHTML = '<p class="text-center p-3 text-danger">Failed to display EPUB: ' + err.message + '</p>';
        });
        
        // Add navigation controls
        const prevBtn = document.createElement('button');
        prevBtn.textContent = '← Previous';
        prevBtn.className = 'btn btn-sm btn-primary';
        prevBtn.style.cssText = 'position:absolute;bottom:20px;left:20px;z-index:100;';
        prevBtn.onclick = function() { rendition.prev(); };
        
        const nextBtn = document.createElement('button');
        nextBtn.textContent = 'Next →';
        nextBtn.className = 'btn btn-sm btn-primary';
        nextBtn.style.cssText = 'position:absolute;bottom:20px;right:20px;z-index:100;';
        nextBtn.onclick = function() { rendition.next(); };
        
        container.appendChild(prevBtn);
        container.appendChild(nextBtn);
        
    } catch (err) {
        console.error('EPUB initialization error:', err);
        container.innerHTML = '<p class="text-center p-3 text-danger">Failed to initialize EPUB viewer: ' + err.message + '</p>';
    }
})();
</script>
<?php endif; ?>
</div>

<div class="mt-3">
  <a href="<?= h(app_path('')) ?>" class="btn btn-secondary">Back to list</a>
</div>



<?php include __DIR__ . '/includes/footer.php'; ?>
