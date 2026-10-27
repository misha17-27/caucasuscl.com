<?php
/*
 * Ortaq PDO bağlantısı. Həm admin paneli, həm də saytın özü istifadə edir.
 * admin/config.php lazımdır (config.sample.php-dən kopyalayın).
 */
static $pdo = null;
if ($pdo instanceof PDO) return $pdo;

$cfgFile = __DIR__ . '/config.php';
if (!is_file($cfgFile)) {
    throw new RuntimeException('admin/config.php tapılmadı — config.sample.php-ni kopyalayın.');
}
$cfg = require $cfgFile;

$opts = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

if (($cfg['driver'] ?? 'sqlite') === 'mysql') {
    $dsn = "mysql:host={$cfg['db_host']};dbname={$cfg['db_name']};charset=" . ($cfg['db_charset'] ?? 'utf8mb4');
    $pdo = new PDO($dsn, $cfg['db_user'], $cfg['db_pass'], $opts);
} else {
    $path = $cfg['sqlite_path'] ?? (__DIR__ . '/../storage/admin.sqlite');
    $dir  = dirname($path);
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $pdo = new PDO('sqlite:' . $path, null, null, $opts);
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('PRAGMA journal_mode = WAL');
}
return $pdo;
