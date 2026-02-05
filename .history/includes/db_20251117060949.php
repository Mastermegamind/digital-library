<?php
// includes/db.php
require_once __DIR__ . '/config.php';

$dataDir = __DIR__ . '/../data';
if (!is_dir($dataDir)) {
    mkdir($dataDir, 0775, true);
}

$dbFile = $dataDir . '/library.sqlite';

try {
    $pdo = new PDO('sqlite:' . $dbFile);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create tables
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            email TEXT NOT NULL UNIQUE,
            password_hash TEXT NOT NULL,
            role TEXT NOT NULL DEFAULT 'student',
            created_at TEXT DEFAULT CURRENT_TIMESTAMP
        );
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS categories (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP
        );
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS resources (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            description TEXT,
            type TEXT NOT NULL,
            category_id INTEGER,
            file_path TEXT,
            external_url TEXT,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            created_by INTEGER,
            FOREIGN KEY(category_id) REFERENCES categories(id),
            FOREIGN KEY(created_by) REFERENCES users(id)
        );
    ");

    // Create default admin if none
    $stmt = $pdo->query("SELECT COUNT(*) AS cnt FROM users");
    $count = (int)$stmt->fetch(PDO::FETCH_ASSOC)['cnt'];
    if ($count === 0) {
        $name = 'Default Admin';
        $email = 'admin@example.com';
        $password = 'admin123';
        $hash = password_hash($password, PASSWORD_DEFAULT);

        $ins = $pdo->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (:n, :e, :p, 'admin')");
        $ins->execute([':n' => $name, ':e' => $email, ':p' => $hash]);
    }
} catch (PDOException $e) {
    die('Database error: ' . htmlspecialchars($e->getMessage()));
}
