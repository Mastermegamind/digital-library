<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: application/xml; charset=utf-8');

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$baseUrl = $scheme . '://' . $host;

$urls = [];
$urls[] = ['loc' => $baseUrl . app_path(''), 'lastmod' => date('c')];
$urls[] = ['loc' => $baseUrl . app_path('library'), 'lastmod' => date('c')];

try {
    $stmt = $pdo->query("SELECT id, created_at FROM resources ORDER BY created_at DESC");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $lastmod = !empty($row['created_at']) ? date('c', strtotime($row['created_at'])) : date('c');
        $urls[] = [
            'loc' => $baseUrl . app_path('resource/' . (int)$row['id']),
            'lastmod' => $lastmod,
        ];
    }
} catch (Throwable $e) {
    log_warning('Sitemap generation failed', ['message' => $e->getMessage()]);
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . PHP_EOL;
foreach ($urls as $entry) {
    echo '  <url>' . PHP_EOL;
    echo '    <loc>' . htmlspecialchars($entry['loc'], ENT_QUOTES, 'UTF-8') . '</loc>' . PHP_EOL;
    if (!empty($entry['lastmod'])) {
        echo '    <lastmod>' . htmlspecialchars($entry['lastmod'], ENT_QUOTES, 'UTF-8') . '</lastmod>' . PHP_EOL;
    }
    echo '  </url>' . PHP_EOL;
}
echo '</urlset>' . PHP_EOL;
