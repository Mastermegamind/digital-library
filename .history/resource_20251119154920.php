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
    header('Location: index.php');
    exit;
}

$title       = $resource['title'];
$type        = $resource['type'];
$filePath    = $resource['file_path'];
$externalUrl = $resource['external_url'];

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
  <a href="index.php" class="btn btn-secondary">Back to list</a>
</div>

<?php if ($type === 'epub' && $fileUrl): ?>
<script src="https://cdn.jsdelivr.net/npm/epubjs/dist/epub.min.js"></script>
<script>
    const book = ePub("<?php echo h($fileUrl); ?>");
    const rendition = book.renderTo("epub-reader", { width: "100%", height: "100%" });
    rendition.display();
</script>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
