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

if ($requestPath === '/forgot-password') {
    require __DIR__ . '/forgot_password.php';
    exit;
}

if ($requestPath === '/reset-password') {
    require __DIR__ . '/reset_password.php';
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

if (preg_match('#^/download/([0-9]+)/?$#', $requestPath, $matches)) {
    $_GET['id'] = (int)$matches[1];
    require __DIR__ . '/download_resource.php';
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

if ($requestPath === '/notifications') {
    require __DIR__ . '/notifications.php';
    exit;
}

if ($requestPath === '/collections') {
    require __DIR__ . '/collections.php';
    exit;
}

if (preg_match('#^/collection/([0-9]+)/?$#', $requestPath, $matches)) {
    $_GET['id'] = (int)$matches[1];
    require __DIR__ . '/collection.php';
    exit;
}

if ($requestPath === '/groups') {
    require __DIR__ . '/groups.php';
    exit;
}

if (preg_match('#^/group/([0-9]+)/?$#', $requestPath, $matches)) {
    $_GET['id'] = (int)$matches[1];
    require __DIR__ . '/group.php';
    exit;
}

if (preg_match('#^/quiz/([0-9]+)/?$#', $requestPath, $matches)) {
    $_GET['id'] = (int)$matches[1];
    require __DIR__ . '/quiz.php';
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

if ($requestPath === '/api/collection') {
    require __DIR__ . '/api/collection.php';
    exit;
}

if ($requestPath === '/api/group') {
    require __DIR__ . '/api/group.php';
    exit;
}

if ($requestPath === '/api/quiz') {
    require __DIR__ . '/api/quiz.php';
    exit;
}

if ($requestPath === '/api/chat') {
    require __DIR__ . '/api/chat.php';
    exit;
}

if ($requestPath === '/api/chatbot') {
    require __DIR__ . '/api/chatbot.php';
    exit;
}

if ($requestPath === '/api/summarize') {
    require __DIR__ . '/api/summarize.php';
    exit;
}

if ($requestPath === '/api/suggest-tags') {
    require __DIR__ . '/api/suggest-tags.php';
    exit;
}

if ($requestPath === '/api/generate-quiz') {
    require __DIR__ . '/api/generate-quiz.php';
    exit;
}

if ($requestPath === '/api/smart-search') {
    require __DIR__ . '/api/smart-search.php';
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

if ($requestPath === '/admin/featured') {
    require __DIR__ . '/admin/featured.php';
    exit;
}

if ($requestPath === '/admin/reports') {
    require __DIR__ . '/admin/reports.php';
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

if ($requestPath === '/admin/groups') {
    require __DIR__ . '/admin/groups.php';
    exit;
}

if (preg_match('#^/admin/quiz/add/([0-9]+)/?$#', $requestPath, $matches)) {
    $_GET['resource_id'] = (int)$matches[1];
    require __DIR__ . '/admin/quiz_edit.php';
    exit;
}

if (preg_match('#^/admin/quiz/edit/([0-9]+)/?$#', $requestPath, $matches)) {
    $_GET['id'] = (int)$matches[1];
    require __DIR__ . '/admin/quiz_edit.php';
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
