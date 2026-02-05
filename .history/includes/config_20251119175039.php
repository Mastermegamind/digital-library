<?php
// includes/config.php
// Basic configuration for CONS-UNTH E-Library

$APP_NAME = 'CONS-UNTH E-Library';

// Optional: ONLYOFFICE Document Server base URL (no trailing slash)
// Example: 'https://office.yourdomain.com'
$ONLYOFFICE_BASE_URL = '';

// Database configuration
// Supported drivers: 'sqlite' (default) or 'mysql'
$DB_DRIVER = getenv('DB_DRIVER') ?: 'mysql';

// MySQL settings (used when $DB_DRIVER === 'mysql')
$DB_HOST = getenv('DB_HOST') ?: '127.0.0.1';
$DB_PORT = getenv('DB_PORT') ?: '3306';
$DB_NAME = getenv('DB_NAME') ?: 'elibrary';
$DB_USER = getenv('DB_USER') ?: 'root';
$DB_PASSWORD = getenv('DB_PASSWORD') ?: 'root';

// SQLite file path (used when $DB_DRIVER === 'sqlite')
$DB_SQLITE_PATH = __DIR__ . '/../data/library.sqlite';
