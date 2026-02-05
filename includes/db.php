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

    // Create tables if they do not exist
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id $primaryKeyDef,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            profile_image_path $fileColumnType,
            role VARCHAR(50) NOT NULL DEFAULT 'student',
            created_at $createdAtColumn
        )$tableOptions;
    ");

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
