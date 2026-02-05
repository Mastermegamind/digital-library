<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$token = $_GET['token'] ?? '';
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
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>PDF Viewer</title>
  <style>
    html, body {
      margin: 0;
      height: 100%;
      background: #111;
      color: #fff;
      font-family: "Segoe UI", Arial, sans-serif;
    }
    #viewer {
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      overflow-y: auto;
      padding: 24px;
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 24px;
    }
    canvas {
      max-width: 100%;
      box-shadow: 0 12px 32px rgba(0,0,0,0.45);
      border-radius: 6px;
      background: #222;
    }
  </style>
</head>
<body>
  <div id="viewer"></div>
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

    pdfjsLib.getDocument({ url: secureUrl, withCredentials: true }).promise
      .then(async pdf => {
        for (let num = 1; num <= pdf.numPages; num++) {
          await renderPage(pdf, num);
        }
      })
      .catch(err => {
        console.error('PDF load error', err);
        container.innerHTML = '<p>Unable to display PDF document.</p>';
      });
  </script>
</body>
</html>
