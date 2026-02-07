<?php
// includes/config.php
// Basic configuration for CONS-UNTH E-Library

$APP_NAME = 'CONS-UNTH';
$FULL_APP_NAME = 'COLLEGE OF NURSING SCIENCE OJI RIVER';

// Environment configuration
// Supported: 'dev', 'prod'
$APP_ENV = getenv('APP_ENV') ?: 'prod';
$APP_DEBUG = $APP_ENV === 'dev';
$APP_LOG_LEVEL = getenv('APP_LOG_LEVEL') ?: ($APP_DEBUG ? 'debug' : 'info');
$APP_CACHE_MAX_AGE = (int)(getenv('APP_CACHE_MAX_AGE') ?: ($APP_DEBUG ? 0 : 300));
$SITEMAP_PAGE_SIZE = (int)(getenv('SITEMAP_PAGE_SIZE') ?: 1000);
$SITEMAP_CACHE_TTL = (int)(getenv('SITEMAP_CACHE_TTL') ?: ($APP_DEBUG ? 0 : 21600));

// Community features & moderation
$RESOURCE_SUBMISSION_REQUIRES_APPROVAL = filter_var(getenv('RESOURCE_SUBMISSION_REQUIRES_APPROVAL') ?: '1', FILTER_VALIDATE_BOOLEAN);
$COMMENTS_REQUIRE_APPROVAL = filter_var(getenv('COMMENTS_REQUIRE_APPROVAL') ?: '1', FILTER_VALIDATE_BOOLEAN);
$REVIEWS_REQUIRE_APPROVAL = filter_var(getenv('REVIEWS_REQUIRE_APPROVAL') ?: '1', FILTER_VALIDATE_BOOLEAN);
$COMMENTS_ENABLE_REPORTING = filter_var(getenv('COMMENTS_ENABLE_REPORTING') ?: '1', FILTER_VALIDATE_BOOLEAN);
$REVIEWS_ENABLE_REPORTING = filter_var(getenv('REVIEWS_ENABLE_REPORTING') ?: '1', FILTER_VALIDATE_BOOLEAN);

// Student registration mode: 'open' or 'admin_approved'
$STUDENT_REGISTRATION_MODE = getenv('STUDENT_REGISTRATION_MODE') ?: 'open';

// User-submitted uploads (kept separate from admin uploads)
$USER_RESOURCE_UPLOAD_DIR = __DIR__ . '/../uploads/user_resources';
$USER_RESOURCE_UPLOAD_PREFIX = 'uploads/user_resources';
$USER_RESOURCE_COVER_DIR = __DIR__ . '/../uploads/user_resource_covers';
$USER_RESOURCE_COVER_PREFIX = 'uploads/user_resource_covers';

// Mailer settings (Symfony Mailer)
$MAILER_DSN = getenv('MAILER_DSN') ?: '';
$MAIL_FROM_ADDRESS = getenv('MAIL_FROM_ADDRESS') ?: '';
$MAIL_FROM_NAME = getenv('MAIL_FROM_NAME') ?: ($APP_NAME ?? 'E-Library');
$MAIL_ADMIN_ADDRESS = getenv('MAIL_ADMIN_ADDRESS') ?: '';

// Security settings
$LOGIN_RATE_LIMIT = (int)(getenv('LOGIN_RATE_LIMIT') ?: 8);
$LOGIN_RATE_WINDOW = (int)(getenv('LOGIN_RATE_WINDOW') ?: 900);
$LOGIN_LOCK_SECONDS = (int)(getenv('LOGIN_LOCK_SECONDS') ?: 900);

// DeepSeek AI API key (for AI-powered features)
$DEEPSEEK_API_KEY = getenv('DEEPSEEK_API_KEY') ?: 'sk-d50ffeec8a1f4fb585e3b7e6de6fb865';

// Remote cover image providers (used when a resource has no cover image)
// Supported providers: 'pexels', 'pixabay', 'unsplash'
$IMAGE_PROVIDER = getenv('IMAGE_PROVIDER') ?: 'pexels';
$PEXELS_API_KEY = getenv('PEXELS_API_KEY') ?: '';
$PIXABAY_API_KEY = getenv('PIXABAY_API_KEY') ?: '';
$UNSPLASH_ACCESS_KEY = getenv('UNSPLASH_ACCESS_KEY') ?: '';
$IMAGE_FALLBACK_QUERY = getenv('IMAGE_FALLBACK_QUERY') ?: 'medical education';

// Optional: ONLYOFFICE Document Server base URL (no trailing slash)
// Example: 'https://office.yourdomain.com'
$ONLYOFFICE_BASE_URL = '';

// Database configuration
// Supported drivers: 'sqlite' (default) or 'mysql'
$DB_DRIVER = getenv('DB_DRIVER') ?: 'mysql';

// MySQL settings (used when $DB_DRIVER === 'mysql')
$DB_HOST = getenv('DB_HOST') ?: '127.0.0.1';
$DB_PORT = getenv('DB_PORT') ?: '3319';
$DB_NAME = getenv('DB_NAME') ?: 'elibrary';
$DB_USER = getenv('DB_USER') ?: 'root';
$DB_PASSWORD = getenv('DB_PASSWORD') ?: '';

// SQLite file path (used when $DB_DRIVER === 'sqlite')
$DB_SQLITE_PATH = __DIR__ . '/../data/library.sqlite';
