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
    log_warning('Resource view requested for missing resource (viewer)', ['resource_id' => $id]);
    header('Location: index.php');
    exit;
}

$title = $resource['title'];
$type = $resource['type'];
$secureToken = null;
$secureUrl = null;
$publicFileUrl = !empty($resource['file_path']) ? app_path($resource['file_path']) : null;
$fileBasedTypes = ['pdf','epub','video_file','doc','ppt','xls'];
if (!in_array($type, ['link','video_link'], true) && !empty($resource['file_path'])) {
    $secureToken = issue_resource_token((int)$resource['id'], $resource['file_path']);
    $secureUrl = app_path('secure_file.php?token=' . urlencode($secureToken));
} elseif ($type !== 'link' && $type !== 'video_link' && empty($resource['file_path'])) {
    log_warning('Viewer attempted without file path', ['resource_id' => $resource['id'], 'type' => $type]);
}
$fileUrl = $secureUrl;
$externalUrl = $resource['external_url'];
$closeUrl = app_path('index.php');

?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo h($title); ?> — Viewer</title>
  <style>
html, body, #epub-container {
  height: 100vh !important;
  width: 100vw !important;
  margin: 0;
  padding: 0;
  overflow: hidden;
}
    .viewer-wrapper {
      position: relative;
      width: 100%;
      height: 100%;
      overflow: hidden;
    }
    .viewer-content {
      width: 100%;
      height: 100%;
      background: #000;
    }
    iframe, video {
      width: 100%;
      height: 100%;
      border: 0;
      background: #000;
    }
    .close-button {
      position: fixed;
      top: 16px;
      left: 16px;
      z-index: 1000;
      background: rgba(0,0,0,0.6);
      color: #fff;
      padding: 10px 18px;
      text-decoration: none;
      border-radius: 999px;
      border: 1px solid rgba(255,255,255,0.3);
      transition: background 0.2s;
      backdrop-filter: blur(6px);
    }
    .close-button:hover {
      background: rgba(0,0,0,0.85);
    }
    .meta {
      position: fixed;
      bottom: 16px;
      left: 50%;
      transform: translateX(-50%);
      background: rgba(0,0,0,0.55);
      border-radius: 999px;
      padding: 8px 18px;
      font-size: 0.95rem;
      border: 1px solid rgba(255,255,255,0.2);
      display: flex;
      align-items: center;
      gap: 16px;
      backdrop-filter: blur(6px);
    }
    .meta span {
      color: #ddd;
    }
    .meta .title {
      font-weight: 600;
      color: #fff;
    }
    .placeholder {
      display: flex;
      align-items: center;
      justify-content: center;
      height: 100%;
      flex-direction: column;
      text-align: center;
      gap: 12px;
    }
    .placeholder a {
      color: #0d6efd;
      text-decoration: none;
    }
  </style>
</head>
<body>
  <a class="close-button" href="<?php echo h($closeUrl); ?>">&larr; Close</a>
  <div class="viewer-wrapper">
    <div class="viewer-content">
      <?php if ($type === 'pdf' && $secureToken): ?>
        <?php $pdfFrame = app_path('pdf_viewer.php?token=' . urlencode($secureToken)); ?>
        <iframe src="<?php echo h($pdfFrame); ?>" allowfullscreen sandbox="allow-scripts allow-same-origin"></iframe>
      <?php elseif ($type === 'epub' && $fileUrl): ?>
        <div id="epub-viewer" style="width:100%;height:100%;"></div>
      <?php elseif ($type === 'video_file' && $fileUrl): ?>
        <video controls controlsList="nodownload" oncontextmenu="return false;">
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
          <iframe src="<?php echo h($youtubeEmbed); ?>" allowfullscreen></iframe>
        <?php else: ?>
          <div class="placeholder">
            <p>Video link:</p>
            <a href="<?php echo h($externalUrl); ?>" target="_blank" rel="noopener">Open Video</a>
          </div>
        <?php endif; ?>
      <?php elseif (in_array($type, ['doc','ppt','xls'], true) && $fileUrl): ?>
        <iframe src="<?php echo h($fileUrl); ?>" sandbox="allow-same-origin allow-scripts"></iframe>
      <?php elseif ($type === 'link' && $externalUrl): ?>
        <iframe src="<?php echo h($externalUrl); ?>"></iframe>
      <?php else: ?>
        <div class="placeholder">
          <p>Preview not available for this resource.</p>
        </div>
      <?php endif; ?>
    </div>
  </div>
  <div class="meta">
    <span class="title"><?php echo h($title); ?></span>
    <span><?php echo h(strtoupper($type)); ?></span>
    <?php if (!empty($resource['category_name'])): ?>
      <span><?php echo h($resource['category_name']); ?></span>
    <?php endif; ?>
    <?php if (!empty($resource['creator_name'])): ?>
      <span>By <?php echo h($resource['creator_name']); ?></span>
  <?php endif; ?>
</div>

<?php if ($type === 'epub' && $fileUrl): ?>
<div id="epub-container" style="width:100vw;height:100vh;background:#000;position:relative;display:flex;flex-direction:column;">
  <!-- Top Controls Bar -->
  <div id="epub-controls" style="position:relative;background:rgba(0,0,0,0.85);padding:12px 16px;z-index:1000;display:flex;align-items:center;flex-wrap:wrap;gap:12px;border-bottom:1px solid rgba(255,255,255,0.1);backdrop-filter:blur(10px);">
    <div style="display:flex;align-items:center;gap:10px;">
      <button id="epub-prev" class="ctrl-btn" title="Previous">◀</button>
      <button id="epub-next" class="ctrl-btn" title="Next">▶</button>
      <span id="epub-page-info" style="color:#fff;font-size:14px;min-width:100px;">Loading...</span>
    </div>

    <div style="flex:1;"></div>

    <!-- Theme Toggle -->
    <button id="theme-toggle" class="ctrl-btn" title="Toggle Dark/Light Mode">🌙</button>

    <!-- Zoom & Font Controls -->
    <div style="display:flex;align-items:center;gap:8px;">
      <button id="epub-zoom-out" class="ctrl-btn">-</button>
      <span id="epub-zoom-level" style="color:#fff;font-size:13px;width:44px;text-align:center;">100%</span>
      <button id="epub-zoom-in" class="ctrl-btn">+</button>
      <button id="epub-zoom-reset" class="ctrl-btn" title="Reset Zoom">↺</button>
    </div>

    <div style="display:flex;align-items:center;gap:8px;border-left:1px solid rgba(255,255,255,0.3);padding-left:12px;">
      <button id="epub-font-decrease" class="ctrl-btn" title="Smaller Font">A-</button>
      <button id="epub-font-increase" class="ctrl-btn" title="Larger Font">A+</button>
    </div>
  </div>

  <!-- EPUB Viewer Area -->
  <div id="epub-viewer" style="flex:1;background:#fff;overflow:hidden;"></div>

  <!-- Loading -->
  <div id="epub-loading" style="position:absolute;inset:0;background:rgba(0,0,0,0.9);display:flex;align-items:center;justify-content:center;flex-direction:column;color:#fff;z-index:2000;">
    <p>Loading EPUB...</p>
    <div style="width:50px;height:50px;border:4px solid #333;border-top:4px solid #fff;border-radius:50%;animation:spin 1s linear infinite;margin-top:20px;"></div>
  </div>
</div>

<style>
  .ctrl-btn {
    background:#fff;color:#000;border:none;padding:8px 12px;border-radius:6px;cursor:pointer;font-size:14px;min-width:36px;height:36px;display:flex;align-items:center;justify-content:center;
  }
  .ctrl-btn:hover {background:#ddd;}
  @media (max-width: 640px) {
    #epub-controls {padding:10px;font-size:13px;gap:8px;}
    .ctrl-btn {padding:6px 10px;font-size:13px;height:34px;}
    #epub-page-info {font-size:13px;}
  }
  @keyframes spin {0% {transform:rotate(0deg);} 100% {transform:rotate(360deg);}}
</style>

<!-- Load Libraries -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/epubjs@0.3.93/dist/epub.min.js"></script>

<script>
(() => {
  const viewer = document.getElementById('epub-viewer');
  const loading = document.getElementById('epub-loading');
  const pageInfo = document.getElementById('epub-page-info');
  const zoomLevel = document.getElementById('epub-zoom-level');
  const themeToggle = document.getElementById('theme-toggle');

  let book, rendition;
  let currentZoom = 100;
  let currentFontSize = 100;
  let isDarkMode = localStorage.getItem('epubDarkMode') === 'true';

  // Set initial theme
  function applyTheme() {
    if (isDarkMode) {
      document.body.style.background = '#000';
      viewer.style.background = '#1a1a1a';
      rendition.themes.override('color', '#eee');
      rendition.themes.override('background', '#1a1a1a');
      themeToggle.textContent = '☀️';
    } else {
      document.body.style.background = '#fff';
      viewer.style.background = '#fff';
      rendition.themes.override('color', '#000');
      rendition.themes.override('background', '#fff');
      themeToggle.textContent = '🌙';
    }
    if (rendition) rendition.themes.apply();
  }

  themeToggle.addEventListener('click', () => {
    isDarkMode = !isDarkMode;
    localStorage.setItem('epubDarkMode', isDarkMode);
    applyTheme();
  });

  // Load EPUB
  fetch("<?php echo h($fileUrl); ?>", { credentials: 'same-origin' })
    .then(r => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.arrayBuffer(); })
    .then(buffer => {
      book = ePub(buffer);
      rendition = book.renderTo("epub-viewer", {
        width: "100%",
        height: "100%",
        spread: "none",
        flow: "paginated"
      });

      // Default themes
      rendition.themes.register({ light: { body: { color: "#000", background: "#fff" } }, dark: { body: { color: "#eee", background: "#1a1a1a" } } });
      rendition.themes.select(isDarkMode ? 'dark' : 'light');

      return rendition.display();
    })
    .then(() => {
      loading.style.display = 'none';
      applyTheme();
      updatePageInfo();
    })
    .catch(err => {
      console.error(err);
      viewer.innerHTML = `<p style="text-align:center;padding:40px;color:#000;">Failed to load EPUB: ${err.message}</p>`;
      loading.style.display = 'none';
    });

  function updatePageInfo() {
    if (!rendition?.currentLocation()) return;
    const loc = rendition.currentLocation();
    if (loc.start) {
      pageInfo.textContent = `Page ${loc.start.displayed.page} / ${loc.start.displayed.total}`;
    }
  }

  // Controls
  document.getElementById('epub-prev').onclick = () => rendition?.prev();
  document.getElementById('epub-next').onclick = () => rendition?.next();

  document.getElementById('epub-zoom-in').onclick = () => { currentZoom = Math.min(200, currentZoom + 10); applyZoom(); };
  document.getElementById('epub-zoom-out').onclick = () => { currentZoom = Math.max(60, currentZoom - 10); applyZoom(); };
  document.getElementById('epub-zoom-reset').onclick = () => { currentZoom = 100; applyZoom(); };

  function applyZoom() {
    zoomLevel.textContent = currentZoom + '%';
    if (rendition) rendition.spread(false).then(() => rendition.display());
    viewer.style.fontSize = currentZoom + '%';
  }

  document.getElementById('epub-font-increase').onclick = () => {
    currentFontSize = Math.min(200, currentFontSize + 15);
    rendition.themes.fontSize(currentFontSize + '%');
  };
  document.getElementById('epub-font-decrease').onclick = () => {
    currentFontSize = Math.max(70, currentFontSize - 15);
    rendition.themes.fontSize(currentFontSize + '%');
  };

  // Keyboard
  document.addEventListener('keydown', e => {
    if (e.key === 'ArrowLeft') rendition?.prev();
    if (e.key === 'ArrowRight') rendition?.next();
  });

  // Update page info on navigation
  rendition?.on('relocated', updatePageInfo);
})();
</script>
<?php endif; ?>
  <script>
    // document.addEventListener('contextmenu', function(e){ e.preventDefault(); });
  </script>
</body>
</html>