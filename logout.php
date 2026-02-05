<?php
require_once __DIR__ . '/includes/auth.php';
redirect_legacy_php('logout');
logout_user();
flash_message('success', 'You have been logged out.');
header('Location: ' . app_path('login'));
exit;
