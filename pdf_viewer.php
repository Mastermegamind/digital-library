<?php
require_once __DIR__ . '/includes/auth.php';
$legacyToken = $_GET['token'] ?? '';
if ($legacyToken !== '') {
    redirect_legacy_php('pdf/' . $legacyToken, ['token' => null]);
}
require_login();

$token = $legacyToken;
$tokenData = $token ? get_resource_token($token) : null;

if (!$tokenData) {
    log_warning('PDF viewer invalid token', ['token' => $token]);
    http_response_code(403);
    exit('Invalid or expired token.');
}

$secureUrl = app_path('secure/' . urlencode($token));
$pdfModule = app_path('assets/pdfjs/pdf.mjs');
$pdfWorker = app_path('assets/pdfjs/pdf.worker.mjs');

?><!doctype html>
<html lang="en" class="pdf-viewer-page">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>PDF Viewer</title>
  <link rel="stylesheet" href="<?php echo h(app_path('assets/css/components.css')); ?>">
</head>
<body class="pdf-viewer-page">
  <div id="viewer" class="pdf-viewer"></div>
  <script type="module">
    import * as pdfjsLib from "<?php echo h($pdfModule); ?>";
    pdfjsLib.GlobalWorkerOptions.workerSrc = "<?php echo h($pdfWorker); ?>";

    const container = document.getElementById('viewer');
    const secureUrl = "<?php echo h($secureUrl); ?>";

    const renderPage = async (pdf, pageNumber) => {
      try {
        const page = await pdf.getPage(pageNumber);
        const viewport = page.getViewport({ scale: 1.3 });
        const canvas = document.createElement('canvas');
        const context = canvas.getContext('2d');
        canvas.width = viewport.width;
        canvas.height = viewport.height;
        container.appendChild(canvas);
        await page.render({ canvasContext: context, viewport }).promise;
      } catch (err) {
        console.error('PDF render error', err);
        container.innerHTML = '<p>Unable to render PDF page.</p>';
        throw err;
      }
    };

    document.addEventListener('contextmenu', e => e.preventDefault());

    let totalPages = 0;
    let currentPage = 1;

    // Track scroll position to determine current page
    const trackProgress = () => {
      const canvases = container.querySelectorAll('canvas');
      if (canvases.length === 0) return;

      const containerRect = container.getBoundingClientRect();
      const viewportMiddle = containerRect.top + containerRect.height / 2;

      for (let i = 0; i < canvases.length; i++) {
        const rect = canvases[i].getBoundingClientRect();
        if (rect.top <= viewportMiddle && rect.bottom >= viewportMiddle) {
          currentPage = i + 1;
          break;
        }
      }

      // Send progress to parent frame
      if (window.parent !== window) {
        const percent = (currentPage / totalPages) * 100;
        window.parent.postMessage({
          type: 'pdfProgress',
          page: currentPage,
          totalPages: totalPages,
          percent: percent
        }, '*');
      }
    };

    // Debounced scroll handler
    let scrollTimeout;
    container.addEventListener('scroll', () => {
      clearTimeout(scrollTimeout);
      scrollTimeout = setTimeout(trackProgress, 200);
    });

    // Also track on window scroll (some layouts)
    window.addEventListener('scroll', () => {
      clearTimeout(scrollTimeout);
      scrollTimeout = setTimeout(trackProgress, 200);
    });

    pdfjsLib.getDocument({ url: secureUrl, withCredentials: true }).promise
      .then(async pdf => {
        totalPages = pdf.numPages;
        for (let num = 1; num <= pdf.numPages; num++) {
          await renderPage(pdf, num);
        }
        // Initial progress report
        setTimeout(trackProgress, 500);
      })
      .catch(err => {
        console.error('PDF load error', err);
        container.innerHTML = '<p>Unable to display PDF document.</p>';
      });

    // Listen for scroll commands from parent frame
    window.addEventListener('message', function(e) {
      if (e.data && e.data.type === 'scroll') {
        const scrollAmount = 300;
        if (e.data.direction === 'up') {
          window.scrollBy({ top: -scrollAmount, behavior: 'smooth' });
        } else if (e.data.direction === 'down') {
          window.scrollBy({ top: scrollAmount, behavior: 'smooth' });
        }
      }
    });

    // Keyboard navigation within PDF viewer
    document.addEventListener('keydown', function(e) {
      const scrollAmount = 200;
      const pageAmount = window.innerHeight * 0.9;

      switch(e.key) {
        case 'ArrowUp':
          e.preventDefault();
          window.scrollBy({ top: -scrollAmount, behavior: 'smooth' });
          break;
        case 'ArrowDown':
          e.preventDefault();
          window.scrollBy({ top: scrollAmount, behavior: 'smooth' });
          break;
        case 'ArrowLeft':
          e.preventDefault();
          window.scrollBy({ top: -pageAmount, behavior: 'smooth' });
          break;
        case 'ArrowRight':
          e.preventDefault();
          window.scrollBy({ top: pageAmount, behavior: 'smooth' });
          break;
        case ' ':
          e.preventDefault();
          window.scrollBy({ top: pageAmount, behavior: 'smooth' });
          break;
        case 'Home':
          e.preventDefault();
          window.scrollTo({ top: 0, behavior: 'smooth' });
          break;
        case 'End':
          e.preventDefault();
          window.scrollTo({ top: document.body.scrollHeight, behavior: 'smooth' });
          break;
      }
      // Trigger progress tracking after scroll
      setTimeout(trackProgress, 300);
    });
  </script>
</body>
</html>

