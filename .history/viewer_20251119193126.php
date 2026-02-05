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
<div id="epub-container" style="width:100vw; height:100vh; margin:0; padding:0; display:flex; background:#000; position:fixed; top:0; left:0;">
  <div id="epub-controls" style="position:relative; background:rgba(0,0,0,0.9); padding:12px 16px; display:flex; flex-wrap:wrap; align-items:center; gap:12px; border-bottom:1px solid #444; backdrop-filter:blur(10px); z-index:1000;">
    <div style="display:flex; align-items:center; gap:10px;">
      <button id="epub-prev" class="btn">◀</button>
      <button id="epub-next" class="btn">▶</button>
      <span id="epub-page-info" style="color:#fff; font-size:14px; min-width:110px;">Loading…</span>
    </div>

    <div style="flex:1;"></div>

    <button id="theme-toggle" class="btn" title="Toggle Dark/Light">🌙</button>

    <div style="display:flex; align-items:center; gap:8px;">
      <button id="epub-zoom-out" class="btn">−</button>
      <span id="epub-zoom-level" style="color:#fff; width:50px; text-align:center;">100%</span>
      <button id="epub-zoom-in" class="btn">+</button>
    </div>

    <div style="border-left:1px solid #555; height:24px; margin:0 10px;"></div>

    <button id="epub-font-decrease" class="btn">A-</button>
    <button id="epub-font-increase" class="btn">A+</button>
  </div>

  <div id="epub-viewer" style="width:100%; height:calc(100vh - 64px); background:#fff;"></div>

  <div id="epub-loading" style="position:absolute; inset:0; background:#000; color:#fff; display:flex; flex-direction:column; align-items:center; justify-content:center; font-size:18px; z-index:2000;">
    <p>Loading EPUB…</p>
    <div style="width:50px; height:50px; border:5px solid #333; border-top:5px solid #fff; border-radius:50%; animation:s 1s linear infinite; margin-top:20px;"></div>
  </div>
</div>

<style>
  .btn {background:#fff; color:#000; border:none; border-radius:6px; width:40px; height:40px; cursor:pointer; font-size:16px;}
  .btn:hover {background:#ddd;}
  @keyframes s {to{transform:rotate(360deg)}}
  @media (max-width:640px){.btn{width:36px;height:36px;font-size:14px;}}
</style>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/epubjs@0.3.93/dist/epub.min.js"></script>

<script>
(() => {
  const viewer = document.getElementById('epub-viewer');
  const loading = document.getElementById('epub-loading');
  const pageInfo = document.getElementById('epub-page-info');
  const zoomLevel = document.getElementById('epub-zoom-level');
  const themeBtn = document.getElementById('theme-toggle');

  let book, rendition;
  let currentZoom = 100;
  let currentFontSize = 100;
  let darkMode = localStorage.getItem('epubDarkMode') === 'true';

  // Apply correct theme on load / toggle
  function applyTheme() {
    if (!rendition) return;
    if (darkMode) {
      rendition.themes.override("color", "#ddd");
      rendition.themes.override("background", "#1a1a1a");
      themeBtn.textContent = '☀️';
      viewer.style.background = '#1a1a1a';
    } else {
      rendition.themes.override("color", "#000");
      rendition.themes.override("background", "#fff");
      themeBtn.textContent = '🌙';
      viewer.style.background = '#fff';
    }
    // This is the correct method in epub.js 0.3
    rendition.themes.update();  // ← FIXED LINE
  }

  themeBtn.addEventListener('click', () => {
    darkMode = !darkMode;
    localStorage.setItem('epubDarkMode', darkMode);
    applyTheme();
  });

  // Load the EPUB
  fetch("<?php echo h($fileUrl); ?>", {credentials: 'same-origin'})
    .then(r => {
      if (!r.ok) throw new Error('HTTP ' + r.status);
      return r.arrayBuffer();
    })
    .then(buffer => {
      book = ePub(buffer);

      rendition = book.renderTo("epub-viewer", {
        width: "100%",
        height: "100%",
        spread: "none",
        flow: "paginated"
      });

      // Register default themes (optional but clean)
      rendition.themes.register("light", { "body": { "color": "#000", "background": "#fff" } });
      rendition.themes.register("dark",  { "body": { "color": "#ddd", "background": "#1a1a1a" } });
      rendition.themes.select(darkMode ? "dark" : "light");

      return rendition.display();
    })
    .then(() => {
      loading.style.display = 'none';
      applyTheme();
      updatePageInfo();
    })
    .catch(err => {
      console.error(err);
      viewer.innerHTML = `<div style="padding:40px;text-align:center;color:#c00;">Failed to load EPUB:<br>${err.message}</div>`;
      loading.style.display = 'none';
    });

  function updatePageInfo() {
    const loc = rendition.currentLocation();
    if (loc && loc.start && loc.start.displayed) {
      pageInfo.textContent = `Page ${loc.start.displayed.page} / ${loc.start.displayed.total}`;
    }
  }

  // Navigation
  document.getElementById('epub-prev').onclick = () => rendition?.prev();
  document.getElementById('epub-next').onclick = () => rendition?.next();

  // Zoom (simple & reliable way)
  document.getElementById('epub-zoom-in').onclick = () => {
    currentZoom = Math.min(200, currentZoom + 10);
    viewer.style.fontSize = currentZoom + '%';
    zoomLevel.textContent = currentZoom + '%';
  };
  document.getElementById('epub-zoom-out').onclick = () => {
    currentZoom = Math.max(50, currentZoom - 10);
    viewer.style.fontSize = currentZoom + '%';
    zoomLevel.textContent = currentZoom + '%';
  };

  // Font size
  document.getElementById('epub-font-increase').onclick = () => {
    currentFontSize += 15;
    rendition.themes.fontSize(currentFontSize + '%');
  };
  document.getElementById('epub-font-decrease').onclick = () => {
    currentFontSize = Math.max(70, currentFontSize - 15);
    rendition.themes.fontSize(currentFontSize + '%');
  };

  // Keyboard navigation
  document.addEventListener('keydown', e => {
    if (e.key === 'ArrowLeft') rendition?.prev();
    if (e.key === 'ArrowRight') rendition?.next();
  });

  // Update page info when turning pages
  rendition?.on("relocated", updatePageInfo);
})();
</script>
<?php endif; ?>
  <script>
    // document.addEventListener('contextmenu', function(e){ e.preventDefault(); });
  </script>
</body>
</html>