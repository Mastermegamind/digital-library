<?php
if (PHP_SAPI !== 'cli') {
    echo "This script must be run from the command line." . PHP_EOL;
    exit(1);
}

require_once __DIR__ . '/../includes/config.php';

$root = realpath(__DIR__ . '/..');
if ($root === false) {
    echo "Unable to locate project root." . PHP_EOL;
    exit(1);
}

$timestamp = date('Ymd_His');
$backupDir = $root . DIRECTORY_SEPARATOR . 'backups' . DIRECTORY_SEPARATOR . 'backup_' . $timestamp;
if (!is_dir($backupDir) && !mkdir($backupDir, 0775, true)) {
    echo "Unable to create backup directory." . PHP_EOL;
    exit(1);
}

$manifest = [
    'created_at' => date('c'),
    'driver' => $DB_DRIVER,
    'uploads_zip' => 'uploads.zip',
];

if ($DB_DRIVER === 'sqlite') {
    $dbFile = $backupDir . DIRECTORY_SEPARATOR . 'db.sqlite';
    if (!is_file($DB_SQLITE_PATH)) {
        echo "SQLite database file not found: {$DB_SQLITE_PATH}" . PHP_EOL;
        exit(1);
    }
    if (!copy($DB_SQLITE_PATH, $dbFile)) {
        echo "Failed to copy SQLite database." . PHP_EOL;
        exit(1);
    }
    $manifest['db_file'] = 'db.sqlite';
} elseif ($DB_DRIVER === 'mysql') {
    $dumpFile = $backupDir . DIRECTORY_SEPARATOR . 'db.sql';
    $mysqldump = getenv('MYSQLDUMP_PATH') ?: 'mysqldump';
    if (!empty($DB_PASSWORD)) {
        putenv('MYSQL_PWD=' . $DB_PASSWORD);
    }
    $cmd = sprintf(
        '%s --host=%s --port=%s --user=%s %s > %s',
        escapeshellcmd($mysqldump),
        escapeshellarg($DB_HOST),
        escapeshellarg($DB_PORT),
        escapeshellarg($DB_USER),
        escapeshellarg($DB_NAME),
        escapeshellarg($dumpFile)
    );
    $exitCode = 0;
    system($cmd, $exitCode);
    if ($exitCode !== 0) {
        echo "mysqldump failed with exit code {$exitCode}." . PHP_EOL;
        exit(1);
    }
    $manifest['db_file'] = 'db.sql';
} else {
    echo "Unsupported DB driver: {$DB_DRIVER}" . PHP_EOL;
    exit(1);
}

$uploadsDir = $root . DIRECTORY_SEPARATOR . 'uploads';
$zipPath = $backupDir . DIRECTORY_SEPARATOR . 'uploads.zip';
$zip = new ZipArchive();
if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    echo "Failed to create uploads zip." . PHP_EOL;
    exit(1);
}

if (is_dir($uploadsDir)) {
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($uploadsDir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($files as $file) {
        $filePath = $file->getRealPath();
        if ($filePath === false) {
            continue;
        }
        $relativePath = substr($filePath, strlen($uploadsDir) + 1);
        if ($file->isDir()) {
            $zip->addEmptyDir('uploads/' . $relativePath);
        } else {
            $zip->addFile($filePath, 'uploads/' . $relativePath);
        }
    }
}
$zip->close();

file_put_contents($backupDir . DIRECTORY_SEPARATOR . 'manifest.json', json_encode($manifest, JSON_PRETTY_PRINT));

echo "Backup created at: {$backupDir}" . PHP_EOL;
