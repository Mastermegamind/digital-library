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

    let pdfDocument = null;
    let totalPages = 0;
    let currentPage = 1;
    let currentScale = 1.3; // Default scale (100% = 1.3)
    const baseScale = 1.3;
    let currentZoomPercent = 100;
    const minZoom = 25;
    const maxZoom = 300;
    const zoomStep = 10;

    const renderPage = async (pdf, pageNumber, scale) => {
      try {
        const page = await pdf.getPage(pageNumber);
        const viewport = page.getViewport({ scale: scale });
        const canvas = document.createElement('canvas');
        const context = canvas.getContext('2d');
        canvas.width = viewport.width;
        canvas.height = viewport.height;
        canvas.dataset.page = pageNumber;
        container.appendChild(canvas);
        await page.render({ canvasContext: context, viewport }).promise;
      } catch (err) {
        console.error('PDF render error', err);
        throw err;
      }
    };

    const setZoom = (zoomPercent) => {
      const nextZoom = Math.max(minZoom, Math.min(maxZoom, zoomPercent));
      currentZoomPercent = nextZoom;
      container.style.zoom = nextZoom + '%';
      if (window.parent !== window) {
        window.parent.postMessage({ type: 'zoomUpdate', level: nextZoom }, '*');
      }
    };

    const fitToWidth = () => {
      if (!pdfDocument) return;

      pdfDocument.getPage(1).then(page => {
        const viewport = page.getViewport({ scale: baseScale });
        const containerWidth = window.innerWidth - 40;
        const zoomPercent = Math.round((containerWidth / viewport.width) * 100);
        setZoom(zoomPercent);
      });
    };

    document.addEventListener('contextmenu', e => e.preventDefault());

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
        pdfDocument = pdf;
        totalPages = pdf.numPages;
        for (let num = 1; num <= pdf.numPages; num++) {
          await renderPage(pdf, num, currentScale);
        }
        // Initial progress report
        setTimeout(trackProgress, 500);
        // Signal parent that PDF is ready for position restore
        if (window.parent !== window) {
          window.parent.postMessage({ type: 'pdfReady' }, '*');
        }
      })
      .catch(err => {
        console.error('PDF load error', err);
        container.innerHTML = '<p>Unable to display PDF document.</p>';
      });

    // Listen for commands from parent frame
    window.addEventListener('message', function(e) {
      if (!e.data) return;

      if (e.data.type === 'scroll') {
        const scrollAmount = 300;
        let dx = 0;
        let dy = 0;
        if (typeof e.data.dx === 'number' || typeof e.data.dy === 'number') {
          dx = Number(e.data.dx || 0);
          dy = Number(e.data.dy || 0);
        } else if (e.data.direction === 'up') {
          dy = -scrollAmount;
        } else if (e.data.direction === 'down') {
          dy = scrollAmount;
        } else if (e.data.direction === 'left') {
          dx = -scrollAmount;
        } else if (e.data.direction === 'right') {
          dx = scrollAmount;
        }
        if (dx !== 0 || dy !== 0) {
          (container || window).scrollBy({ left: dx, top: dy, behavior: 'smooth' });
        }
      } else if (e.data.type === 'restorePosition') {
        const page = parseInt(e.data.page, 10);
        if (page > 0) {
          const canvas = container.querySelector('canvas[data-page="' + page + '"]');
          if (canvas) {
            canvas.scrollIntoView({ behavior: 'smooth', block: 'start' });
          }
        }
      } else if (e.data.type === 'zoom') {
        if (e.data.fitWidth) {
          fitToWidth();
        } else if (e.data.level) {
          setZoom(e.data.level);
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
          (container || window).scrollBy({ top: -scrollAmount, behavior: 'smooth' });
          break;
        case 'ArrowDown':
          e.preventDefault();
          (container || window).scrollBy({ top: scrollAmount, behavior: 'smooth' });
          break;
        case 'ArrowLeft':
          e.preventDefault();
          setZoom(currentZoomPercent - zoomStep);
          break;
        case 'ArrowRight':
          e.preventDefault();
          setZoom(currentZoomPercent + zoomStep);
          break;
        case 'PageUp':
          e.preventDefault();
          (container || window).scrollBy({ top: -pageAmount, behavior: 'smooth' });
          break;
        case 'PageDown':
          e.preventDefault();
          (container || window).scrollBy({ top: pageAmount, behavior: 'smooth' });
          break;
        case ' ':
          e.preventDefault();
          (container || window).scrollBy({ top: pageAmount, behavior: 'smooth' });
          break;
        case 'Home':
          e.preventDefault();
          (container || window).scrollTo({ top: 0, behavior: 'smooth' });
          break;
        case 'End':
          e.preventDefault();
          const target = (container || window);
          const maxScroll = container ? container.scrollHeight : document.body.scrollHeight;
          target.scrollTo({ top: maxScroll, behavior: 'smooth' });
          break;
      }
      // Trigger progress tracking after scroll
      setTimeout(trackProgress, 300);
    });
  </script>
</body>
</html>
