<?php
// includes/db.php
require_once __DIR__ . '/config.php';

try {
    if ($DB_DRIVER === 'mysql') {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $DB_HOST,
            $DB_PORT,
            $DB_NAME
        );
        $pdo = new PDO($dsn, $DB_USER, $DB_PASSWORD);
    } elseif ($DB_DRIVER === 'sqlite') {
        $dataDir = dirname($DB_SQLITE_PATH);
        if (!is_dir($dataDir)) {
            mkdir($dataDir, 0775, true);
        }
        $pdo = new PDO('sqlite:' . $DB_SQLITE_PATH);
        $pdo->exec('PRAGMA foreign_keys = ON');
    } else {
        throw new PDOException('Unsupported DB driver: ' . $DB_DRIVER);
    }

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $primaryKeyDef = ($DB_DRIVER === 'mysql')
        ? 'INT UNSIGNED AUTO_INCREMENT PRIMARY KEY'
        : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    $foreignKeyType = ($DB_DRIVER === 'mysql') ? 'INT UNSIGNED' : 'INTEGER';
    $createdAtColumn = 'DATETIME DEFAULT CURRENT_TIMESTAMP';
    $tableOptions = ($DB_DRIVER === 'mysql') ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4' : '';
    $fileColumnType = ($DB_DRIVER === 'mysql') ? 'VARCHAR(255)' : 'TEXT';
    $statusColumnType = ($DB_DRIVER === 'mysql') ? 'VARCHAR(20)' : 'TEXT';
    $tinyIntType = ($DB_DRIVER === 'mysql') ? 'TINYINT(1)' : 'INTEGER';
    $uniqueReviewConstraint = ($DB_DRIVER === 'mysql')
        ? 'UNIQUE KEY unique_review (resource_id, user_id)'
        : 'UNIQUE(resource_id, user_id)';
    $uniqueFeaturedConstraint = ($DB_DRIVER === 'mysql')
        ? 'UNIQUE KEY unique_featured (resource_id, section)'
        : 'UNIQUE(resource_id, section)';
    $uniqueTagConstraint = ($DB_DRIVER === 'mysql')
        ? 'UNIQUE KEY unique_tag (slug)'
        : 'UNIQUE(slug)';
    $uniqueResourceTagConstraint = ($DB_DRIVER === 'mysql')
        ? 'UNIQUE KEY unique_resource_tag (resource_id, tag_id)'
        : 'UNIQUE(resource_id, tag_id)';

    $columnExists = function (string $table, string $column) use ($pdo, $DB_DRIVER): bool {
        if ($DB_DRIVER === 'mysql') {
            $stmt = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE :column");
            $stmt->execute([':column' => $column]);
            return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
        }
        $stmt = $pdo->prepare("PRAGMA table_info($table)");
        $stmt->execute();
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (isset($row['name']) && $row['name'] === $column) {
                return true;
            }
        }
        return false;
    };

    $ensureColumn = function (string $table, string $column, string $definition) use ($pdo, $columnExists) {
        if (!$columnExists($table, $column)) {
            $pdo->exec("ALTER TABLE $table ADD COLUMN $column $definition");
        }
    };

    // Create tables if they do not exist
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id $primaryKeyDef,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            profile_image_path $fileColumnType,
            role VARCHAR(50) NOT NULL DEFAULT 'student',
            status $statusColumnType NOT NULL DEFAULT 'active',
            email_verified_at DATETIME NULL,
            approved_by $foreignKeyType NULL,
            approved_at DATETIME NULL,
            created_at $createdAtColumn
        )$tableOptions;
    ");

    // User moderation/verification columns (for existing installs)
    $ensureColumn('users', 'status', "$statusColumnType NOT NULL DEFAULT 'active'");
    $ensureColumn('users', 'email_verified_at', "DATETIME NULL");
    $ensureColumn('users', 'approved_by', "$foreignKeyType NULL");
    $ensureColumn('users', 'approved_at', "DATETIME NULL");

    // Backfill existing users to avoid lockout when enabling verification
    try {
        $pdo->exec("UPDATE users SET status = 'active' WHERE status IS NULL OR status = ''");
        $pdo->exec("UPDATE users SET email_verified_at = created_at WHERE email_verified_at IS NULL");
    } catch (PDOException $e) { /* best effort */ }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS categories (
            id $primaryKeyDef,
            name VARCHAR(255) NOT NULL,
            cover_image_path $fileColumnType,
            created_at $createdAtColumn
        )$tableOptions;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS resources (
            id $primaryKeyDef,
            title VARCHAR(255) NOT NULL,
            description TEXT,
            type VARCHAR(50) NOT NULL,
            category_id $foreignKeyType,
            file_path $fileColumnType,
            cover_image_path $fileColumnType,
            external_url TEXT,
            created_at $createdAtColumn,
            created_by $foreignKeyType,
            FOREIGN KEY(category_id) REFERENCES categories(id),
            FOREIGN KEY(created_by) REFERENCES users(id)
        )$tableOptions;
    ");

    // Resource moderation columns
    $ensureColumn('resources', 'status', "$statusColumnType NOT NULL DEFAULT 'approved'");
    $ensureColumn('resources', 'approved_by', "$foreignKeyType NULL");
    $ensureColumn('resources', 'approved_at', "DATETIME NULL");
    $ensureColumn('resources', 'review_notes', "TEXT NULL");

    // User bookmarks table
    $uniqueConstraint = ($DB_DRIVER === 'mysql') ? 'UNIQUE KEY unique_bookmark (user_id, resource_id)' : 'UNIQUE(user_id, resource_id)';
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS user_bookmarks (
            id $primaryKeyDef,
            user_id $foreignKeyType NOT NULL,
            resource_id $foreignKeyType NOT NULL,
            created_at $createdAtColumn,
            $uniqueConstraint,
            FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY(resource_id) REFERENCES resources(id) ON DELETE CASCADE
        )$tableOptions;
    ");

    // Reading progress table
    $uniqueProgressConstraint = ($DB_DRIVER === 'mysql') ? 'UNIQUE KEY unique_progress (user_id, resource_id)' : 'UNIQUE(user_id, resource_id)';
    $decimalType = ($DB_DRIVER === 'mysql') ? 'DECIMAL(5,2)' : 'REAL';
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS reading_progress (
            id $primaryKeyDef,
            user_id $foreignKeyType NOT NULL,
            resource_id $foreignKeyType NOT NULL,
            last_position INT DEFAULT 0,
            progress_percent $decimalType DEFAULT 0,
            total_pages INT,
            last_viewed_at $createdAtColumn,
            created_at $createdAtColumn,
            $uniqueProgressConstraint,
            FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY(resource_id) REFERENCES resources(id) ON DELETE CASCADE
        )$tableOptions;
    ");

    // User settings table (for dark mode preference)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS user_settings (
            id $primaryKeyDef,
            user_id $foreignKeyType NOT NULL UNIQUE,
            dark_mode TINYINT(1) DEFAULT 0,
            created_at $createdAtColumn,
            FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
        )$tableOptions;
    ");

    // Application settings (key/value)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS app_settings (
            setting_key VARCHAR(100) NOT NULL PRIMARY KEY,
            setting_value TEXT
        )$tableOptions;
    ");

    // Email verification tokens
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS email_verification_tokens (
            id $primaryKeyDef,
            user_id $foreignKeyType NOT NULL,
            token VARCHAR(64) NOT NULL,
            expires_at DATETIME NOT NULL,
            created_at $createdAtColumn,
            FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
        )$tableOptions;
    ");

    $settingInsert = ($DB_DRIVER === 'mysql')
        ? "INSERT IGNORE INTO app_settings (setting_key, setting_value) VALUES (:k, :v)"
        : "INSERT OR IGNORE INTO app_settings (setting_key, setting_value) VALUES (:k, :v)";
    $insertSetting = $pdo->prepare($settingInsert);
    $insertSetting->execute([':k' => 'registration_enabled', ':v' => '1']);
    $insertSetting->execute([':k' => 'registration_mode', ':v' => ($STUDENT_REGISTRATION_MODE ?? 'open')]);
    $insertSetting->execute([':k' => 'require_email_verification', ':v' => '1']);
    $insertSetting->execute([':k' => 'notifications_inapp_enabled', ':v' => '1']);
    $insertSetting->execute([':k' => 'notifications_email_enabled', ':v' => '0']);
    $insertSetting->execute([':k' => 'notifications_phone_enabled', ':v' => '0']);

    // Resource reviews (ratings + optional review)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS resource_reviews (
            id $primaryKeyDef,
            resource_id $foreignKeyType NOT NULL,
            user_id $foreignKeyType NOT NULL,
            rating $tinyIntType NOT NULL,
            review TEXT,
            status $statusColumnType NOT NULL DEFAULT 'approved',
            created_at $createdAtColumn,
            updated_at $createdAtColumn,
            $uniqueReviewConstraint,
            FOREIGN KEY(resource_id) REFERENCES resources(id) ON DELETE CASCADE,
            FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
        )$tableOptions;
    ");

    // Resource comments
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS resource_comments (
            id $primaryKeyDef,
            resource_id $foreignKeyType NOT NULL,
            user_id $foreignKeyType NOT NULL,
            parent_id $foreignKeyType NULL,
            content TEXT NOT NULL,
            status $statusColumnType NOT NULL DEFAULT 'approved',
            created_at $createdAtColumn,
            FOREIGN KEY(resource_id) REFERENCES resources(id) ON DELETE CASCADE,
            FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
        )$tableOptions;
    ");

    // Tags and resource tags
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS tags (
            id $primaryKeyDef,
            name VARCHAR(100) NOT NULL,
            slug VARCHAR(120) NOT NULL,
            created_at $createdAtColumn,
            $uniqueTagConstraint
        )$tableOptions;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS resource_tags (
            id $primaryKeyDef,
            resource_id $foreignKeyType NOT NULL,
            tag_id $foreignKeyType NOT NULL,
            created_at $createdAtColumn,
            $uniqueResourceTagConstraint,
            FOREIGN KEY(resource_id) REFERENCES resources(id) ON DELETE CASCADE,
            FOREIGN KEY(tag_id) REFERENCES tags(id) ON DELETE CASCADE
        )$tableOptions;
    ");

    // Content reports (comments/reviews)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS resource_reports (
            id $primaryKeyDef,
            content_type VARCHAR(20) NOT NULL,
            content_id $foreignKeyType NOT NULL,
            reported_by $foreignKeyType NOT NULL,
            reason TEXT,
            created_at $createdAtColumn,
            FOREIGN KEY(reported_by) REFERENCES users(id) ON DELETE CASCADE
        )$tableOptions;
    ");

    // Resource view analytics
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS resource_views (
            id $primaryKeyDef,
            resource_id $foreignKeyType NOT NULL,
            user_id $foreignKeyType NULL,
            session_id VARCHAR(64) NULL,
            created_at $createdAtColumn,
            FOREIGN KEY(resource_id) REFERENCES resources(id) ON DELETE CASCADE,
            FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL
        )$tableOptions;
    ");

    // Resource download analytics
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS resource_downloads (
            id $primaryKeyDef,
            resource_id $foreignKeyType NOT NULL,
            user_id $foreignKeyType NULL,
            session_id VARCHAR(64) NULL,
            created_at $createdAtColumn,
            FOREIGN KEY(resource_id) REFERENCES resources(id) ON DELETE CASCADE,
            FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL
        )$tableOptions;
    ");

    // Search analytics
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS search_logs (
            id $primaryKeyDef,
            user_id $foreignKeyType NULL,
            query TEXT,
            filters TEXT,
            results_count INT DEFAULT 0,
            created_at $createdAtColumn,
            FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL
        )$tableOptions;
    ");

    // In-app notifications
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS notifications (
            id $primaryKeyDef,
            user_id $foreignKeyType NOT NULL,
            type VARCHAR(50) NOT NULL,
            title VARCHAR(255) NOT NULL,
            body TEXT,
            link TEXT,
            created_at $createdAtColumn,
            read_at DATETIME NULL,
            FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
        )$tableOptions;
    ");

    // Featured resources (curated homepage sections)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS featured_resources (
            id $primaryKeyDef,
            resource_id $foreignKeyType NOT NULL,
            section VARCHAR(50) NOT NULL,
            sort_order INT DEFAULT 0,
            starts_at DATETIME NULL,
            ends_at DATETIME NULL,
            created_by $foreignKeyType NULL,
            created_at $createdAtColumn,
            updated_at $createdAtColumn,
            $uniqueFeaturedConstraint,
            FOREIGN KEY(resource_id) REFERENCES resources(id) ON DELETE CASCADE,
            FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL
        )$tableOptions;
    ");

    // Add performance indexes (wrapped in try-catch to handle if already exists)
    try {
        if ($DB_DRIVER === 'mysql') {
            $pdo->exec("CREATE INDEX idx_resources_category ON resources(category_id)");
        }
    } catch (PDOException $e) { /* Index may already exist */ }

    try {
        if ($DB_DRIVER === 'mysql') {
            $pdo->exec("CREATE INDEX idx_resources_type ON resources(type)");
        }
    } catch (PDOException $e) { /* Index may already exist */ }

    try {
        if ($DB_DRIVER === 'mysql') {
            $pdo->exec("CREATE INDEX idx_resources_status ON resources(status)");
        }
    } catch (PDOException $e) { /* Index may already exist */ }

    try {
        if ($DB_DRIVER === 'mysql') {
            $pdo->exec("CREATE INDEX idx_reviews_status ON resource_reviews(status)");
        }
    } catch (PDOException $e) { /* Index may already exist */ }

    try {
        if ($DB_DRIVER === 'mysql') {
            $pdo->exec("CREATE INDEX idx_comments_status ON resource_comments(status)");
        }
    } catch (PDOException $e) { /* Index may already exist */ }

    try {
        if ($DB_DRIVER === 'mysql') {
            $pdo->exec("CREATE INDEX idx_featured_section ON featured_resources(section, sort_order)");
        }
    } catch (PDOException $e) { /* Index may already exist */ }

    try {
        if ($DB_DRIVER === 'mysql') {
            $pdo->exec("CREATE INDEX idx_resource_views_resource_date ON resource_views(resource_id, created_at)");
        }
    } catch (PDOException $e) { /* Index may already exist */ }

    try {
        if ($DB_DRIVER === 'mysql') {
            $pdo->exec("CREATE INDEX idx_resource_views_user_date ON resource_views(user_id, created_at)");
        }
    } catch (PDOException $e) { /* Index may already exist */ }

    try {
        if ($DB_DRIVER === 'mysql') {
            $pdo->exec("CREATE INDEX idx_resource_downloads_resource_date ON resource_downloads(resource_id, created_at)");
        }
    } catch (PDOException $e) { /* Index may already exist */ }

    try {
        if ($DB_DRIVER === 'mysql') {
            $pdo->exec("CREATE INDEX idx_resource_downloads_user_date ON resource_downloads(user_id, created_at)");
        }
    } catch (PDOException $e) { /* Index may already exist */ }

    try {
        if ($DB_DRIVER === 'mysql') {
            $pdo->exec("CREATE INDEX idx_search_logs_created ON search_logs(created_at)");
        }
    } catch (PDOException $e) { /* Index may already exist */ }

    try {
        if ($DB_DRIVER === 'mysql') {
            $pdo->exec("CREATE INDEX idx_notifications_user_read ON notifications(user_id, read_at, created_at)");
        }
    } catch (PDOException $e) { /* Index may already exist */ }

    try {
        if ($DB_DRIVER === 'mysql') {
            $pdo->exec("CREATE INDEX idx_tags_slug ON tags(slug)");
        }
    } catch (PDOException $e) { /* Index may already exist */ }

    try {
        if ($DB_DRIVER === 'mysql') {
            $pdo->exec("CREATE INDEX idx_resource_tags_resource ON resource_tags(resource_id)");
        }
    } catch (PDOException $e) { /* Index may already exist */ }

    try {
        if ($DB_DRIVER === 'mysql') {
            $pdo->exec("CREATE INDEX idx_resource_tags_tag ON resource_tags(tag_id)");
        }
    } catch (PDOException $e) { /* Index may already exist */ }

    try {
        if ($DB_DRIVER === 'mysql') {
            $pdo->exec("CREATE INDEX idx_users_status ON users(status)");
        }
    } catch (PDOException $e) { /* Index may already exist */ }

    try {
        if ($DB_DRIVER === 'mysql') {
            $pdo->exec("CREATE UNIQUE INDEX idx_email_verification_token ON email_verification_tokens(token)");
        }
    } catch (PDOException $e) { /* Index may already exist */ }

    // Create default admin if none
    $stmt = $pdo->query("SELECT COUNT(*) AS cnt FROM users");
    $count = (int)$stmt->fetch(PDO::FETCH_ASSOC)['cnt'];
    if ($count === 0) {
        $name = 'super-admin';
        $email = 'ishikotevu@gmail.com';
        $password = '12345678';
        $hash = password_hash($password, PASSWORD_DEFAULT);

        $ins = $pdo->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (:n, :e, :p, 'admin')");
        $ins->execute([':n' => $name, ':e' => $email, ':p' => $hash]);
    }

    // Ensure a demo student account exists for testing
    $studentEmail = 'student@example.com';
    $studentCheck = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = :email");
    $studentCheck->execute([':email' => $studentEmail]);
    if ((int)$studentCheck->fetchColumn() === 0) {
        $studentPass = password_hash('student123', PASSWORD_DEFAULT);
        $ins = $pdo->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (:n, :e, :p, 'student')");
        $ins->execute([
            ':n' => 'Demo Student',
            ':e' => $studentEmail,
            ':p' => $studentPass,
        ]);
    }
} catch (PDOException $e) {
    if (function_exists('log_error')) {
        log_error('Database error', ['exception' => $e->getMessage()]);
    }
    die('Database error: ' . htmlspecialchars($e->getMessage()));
}
