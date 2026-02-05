<?php
require_once __DIR__ . '/includes/auth.php';
logout_user();
flash_message('success', 'You have been logged out.');
header('Location: ' . app_path('login'));
exit;
