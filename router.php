<?php
// Front controller for clean URLs
require_once __DIR__ . '/includes/functions.php';

$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
$basePath = app_base_path_prefix();
if ($basePath !== '' && str_starts_with($requestPath, $basePath)) {
    $requestPath = substr($requestPath, strlen($basePath));
}

$requestPath = '/' . ltrim($requestPath, '/');

// Public routes
if ($requestPath === '/' || $requestPath === '/library') {
    require __DIR__ . '/index.php';
    exit;
}

if ($requestPath === '/login') {
    require __DIR__ . '/login.php';
    exit;
}

if ($requestPath === '/register') {
    require __DIR__ . '/register.php';
    exit;
}

if ($requestPath === '/verify-email') {
    require __DIR__ . '/verify_email.php';
    exit;
}

if ($requestPath === '/resend-verification') {
    require __DIR__ . '/resend_verification.php';
    exit;
}

if ($requestPath === '/logout') {
    require __DIR__ . '/logout.php';
    exit;
}

if ($requestPath === '/submit') {
    require __DIR__ . '/submit_resource.php';
    exit;
}

if ($requestPath === '/my-submissions') {
    require __DIR__ . '/my_submissions.php';
    exit;
}

if ($requestPath === '/sitemap.xml') {
    require __DIR__ . '/sitemap.php';
    exit;
}

if (preg_match('#^/resource/([0-9]+)/?$#', $requestPath, $matches)) {
    $_GET['id'] = (int)$matches[1];
    require __DIR__ . '/resource.php';
    exit;
}

if (preg_match('#^/viewer/([0-9]+)/?$#', $requestPath, $matches)) {
    $_GET['id'] = (int)$matches[1];
    require __DIR__ . '/viewer.php';
    exit;
}

if (preg_match('#^/pdf/([A-Fa-f0-9]+)/?$#', $requestPath, $matches)) {
    $_GET['token'] = $matches[1];
    require __DIR__ . '/pdf_viewer.php';
    exit;
}

if (preg_match('#^/secure/([A-Fa-f0-9]+)/?$#', $requestPath, $matches)) {
    $_GET['token'] = $matches[1];
    require __DIR__ . '/secure_file.php';
    exit;
}

// User dashboard and bookmarks
if ($requestPath === '/dashboard') {
    require __DIR__ . '/dashboard.php';
    exit;
}

if ($requestPath === '/bookmarks') {
    require __DIR__ . '/bookmarks.php';
    exit;
}

// API routes
if ($requestPath === '/api/bookmark') {
    require __DIR__ . '/api/bookmark.php';
    exit;
}

if ($requestPath === '/api/progress') {
    require __DIR__ . '/api/progress.php';
    exit;
}

if ($requestPath === '/api/settings') {
    require __DIR__ . '/api/settings.php';
    exit;
}

// Admin routes
if ($requestPath === '/admin' || $requestPath === '/admin/') {
    require __DIR__ . '/admin/index.php';
    exit;
}

if ($requestPath === '/admin/dashboard') {
    require __DIR__ . '/admin/dashboard.php';
    exit;
}

if ($requestPath === '/admin/resources') {
    require __DIR__ . '/admin/resources.php';
    exit;
}

if ($requestPath === '/admin/settings') {
    require __DIR__ . '/admin/settings.php';
    exit;
}

if ($requestPath === '/admin/moderation') {
    require __DIR__ . '/admin/moderation.php';
    exit;
}

if ($requestPath === '/admin/resource/add') {
    require __DIR__ . '/admin/resource_add.php';
    exit;
}

if (preg_match('#^/admin/resource/edit/([0-9]+)/?$#', $requestPath, $matches)) {
    $_GET['id'] = (int)$matches[1];
    require __DIR__ . '/admin/resource_edit.php';
    exit;
}

if (preg_match('#^/admin/resource/delete/([0-9]+)/?$#', $requestPath, $matches)) {
    $_GET['id'] = (int)$matches[1];
    require __DIR__ . '/admin/resource_delete.php';
    exit;
}

if ($requestPath === '/admin/categories') {
    require __DIR__ . '/admin/categories.php';
    exit;
}

if ($requestPath === '/admin/users') {
    require __DIR__ . '/admin/users.php';
    exit;
}

if ($requestPath === '/admin/user/add') {
    require __DIR__ . '/admin/user_add.php';
    exit;
}

if (preg_match('#^/admin/user/edit/([0-9]+)/?$#', $requestPath, $matches)) {
    $_GET['id'] = (int)$matches[1];
    require __DIR__ . '/admin/user_edit.php';
    exit;
}

if (preg_match('#^/admin/user/delete/([0-9]+)/?$#', $requestPath, $matches)) {
    $_GET['id'] = (int)$matches[1];
    require __DIR__ . '/admin/user_delete.php';
    exit;
}

http_response_code(404);
render_error_page(404, 'Page Not Found', 'The page you requested could not be found.');
