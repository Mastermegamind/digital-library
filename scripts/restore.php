<?php
if (PHP_SAPI !== 'cli') {
    echo "This script must be run from the command line." . PHP_EOL;
    exit(1);
}

require_once __DIR__ . '/../includes/config.php';

$backupDir = $argv[1] ?? '';
if ($backupDir === '' || !is_dir($backupDir)) {
    echo "Usage: php scripts/restore.php <backup-directory>" . PHP_EOL;
    exit(1);
}

$root = realpath(__DIR__ . '/..');
if ($root === false) {
    echo "Unable to locate project root." . PHP_EOL;
    exit(1);
}

$manifestPath = rtrim($backupDir, "/\\") . DIRECTORY_SEPARATOR . 'manifest.json';
$manifest = [];
if (is_file($manifestPath)) {
    $manifest = json_decode(file_get_contents($manifestPath), true) ?: [];
}

if ($DB_DRIVER === 'sqlite') {
    $dbFile = rtrim($backupDir, "/\\") . DIRECTORY_SEPARATOR . ($manifest['db_file'] ?? 'db.sqlite');
    if (!is_file($dbFile)) {
        echo "SQLite backup file not found in {$backupDir}" . PHP_EOL;
        exit(1);
    }
    $dbDir = dirname($DB_SQLITE_PATH);
    if (!is_dir($dbDir)) {
        mkdir($dbDir, 0775, true);
    }
    if (!copy($dbFile, $DB_SQLITE_PATH)) {
        echo "Failed to restore SQLite database." . PHP_EOL;
        exit(1);
    }
} elseif ($DB_DRIVER === 'mysql') {
    $sqlFile = rtrim($backupDir, "/\\") . DIRECTORY_SEPARATOR . ($manifest['db_file'] ?? 'db.sql');
    if (!is_file($sqlFile)) {
        echo "MySQL backup file not found in {$backupDir}" . PHP_EOL;
        exit(1);
    }
    $mysql = getenv('MYSQL_PATH') ?: 'mysql';
    if (!empty($DB_PASSWORD)) {
        putenv('MYSQL_PWD=' . $DB_PASSWORD);
    }
    $cmd = sprintf(
        '%s --host=%s --port=%s --user=%s %s < %s',
        escapeshellcmd($mysql),
        escapeshellarg($DB_HOST),
        escapeshellarg($DB_PORT),
        escapeshellarg($DB_USER),
        escapeshellarg($DB_NAME),
        escapeshellarg($sqlFile)
    );
    $exitCode = 0;
    system($cmd, $exitCode);
    if ($exitCode !== 0) {
        echo "mysql restore failed with exit code {$exitCode}." . PHP_EOL;
        exit(1);
    }
} else {
    echo "Unsupported DB driver: {$DB_DRIVER}" . PHP_EOL;
    exit(1);
}

$zipPath = rtrim($backupDir, "/\\") . DIRECTORY_SEPARATOR . 'uploads.zip';
if (is_file($zipPath)) {
    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) {
        echo "Failed to open uploads archive." . PHP_EOL;
        exit(1);
    }
    $zip->extractTo($root);
    $zip->close();
}

echo "Restore completed from: {$backupDir}" . PHP_EOL;
