<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

redirect_legacy_php('sitemap.xml');

header('Content-Type: application/xml; charset=utf-8');

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$baseUrl = $scheme . '://' . $host;

$pageSize = max(1, (int)($SITEMAP_PAGE_SIZE ?? 1000));
$cacheTtl = max(0, (int)($SITEMAP_CACHE_TTL ?? 0));
$requestedPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : null;

$totalResources = 0;
try {
    $totalResources = (int)$pdo->query("SELECT COUNT(*) FROM resources WHERE COALESCE(status, 'approved') = 'approved'")->fetchColumn();
} catch (Throwable $e) {
    log_warning('Sitemap count failed', ['message' => $e->getMessage()]);
}

$totalPages = max(1, (int)ceil($totalResources / $pageSize));
if ($requestedPage !== null && $requestedPage > $totalPages) {
    http_response_code(404);
    exit;
}

$renderIndex = $totalPages > 1 && $requestedPage === null;
$pageToRender = $requestedPage ?? 1;

$cacheKey = $renderIndex ? 'index' : 'page-' . $pageToRender;
$cacheDir = __DIR__ . '/logs/sitemaps';
$cacheFile = $cacheDir . '/sitemap-' . $cacheKey . '.xml';

if ($cacheTtl > 0 && is_file($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTtl) {
    echo file_get_contents($cacheFile);
    exit;
}

$xml = '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;

if ($renderIndex) {
    $xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . PHP_EOL;
    $lastmod = date('c');
    for ($i = 1; $i <= $totalPages; $i++) {
        $loc = $baseUrl . app_path('sitemap.xml') . '?page=' . $i;
        $xml .= '  <sitemap>' . PHP_EOL;
        $xml .= '    <loc>' . htmlspecialchars($loc, ENT_QUOTES, 'UTF-8') . '</loc>' . PHP_EOL;
        $xml .= '    <lastmod>' . htmlspecialchars($lastmod, ENT_QUOTES, 'UTF-8') . '</lastmod>' . PHP_EOL;
        $xml .= '  </sitemap>' . PHP_EOL;
    }
    $xml .= '</sitemapindex>' . PHP_EOL;
} else {
    $urls = [];
    if ($pageToRender === 1) {
        $urls[] = ['loc' => $baseUrl . app_path(''), 'lastmod' => date('c')];
        $urls[] = ['loc' => $baseUrl . app_path('library'), 'lastmod' => date('c')];
    }

    try {
        if ($totalResources > 0) {
            $offset = ($pageToRender - 1) * $pageSize;
            $stmt = $pdo->prepare("SELECT id, created_at FROM resources WHERE COALESCE(status, 'approved') = 'approved' ORDER BY created_at DESC LIMIT :limit OFFSET :offset");
            $stmt->bindValue(':limit', $pageSize, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $lastmod = !empty($row['created_at']) ? date('c', strtotime($row['created_at'])) : date('c');
                $urls[] = [
                    'loc' => $baseUrl . app_path('resource/' . (int)$row['id']),
                    'lastmod' => $lastmod,
                ];
            }
        }
    } catch (Throwable $e) {
        log_warning('Sitemap generation failed', ['message' => $e->getMessage()]);
    }

    $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . PHP_EOL;
    foreach ($urls as $entry) {
        $xml .= '  <url>' . PHP_EOL;
        $xml .= '    <loc>' . htmlspecialchars($entry['loc'], ENT_QUOTES, 'UTF-8') . '</loc>' . PHP_EOL;
        if (!empty($entry['lastmod'])) {
            $xml .= '    <lastmod>' . htmlspecialchars($entry['lastmod'], ENT_QUOTES, 'UTF-8') . '</lastmod>' . PHP_EOL;
        }
        $xml .= '  </url>' . PHP_EOL;
    }
    $xml .= '</urlset>' . PHP_EOL;
}

if ($cacheTtl > 0) {
    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0775, true);
    }
    @file_put_contents($cacheFile, $xml, LOCK_EX);
}

echo $xml;
