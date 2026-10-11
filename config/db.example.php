<?php
/**
 * Database connection (template).
 *
 * scripts/setup.sh creates config/db.php from this file automatically.
 * To do it by hand: copy this file to config/db.php and fill in your values.
 * config/db.php is gitignored, so never commit real passwords.
 *
 * Usage in any page:
 *     require_once __DIR__ . '/../config/db.php';   // gives you $pdo
 *     $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
 *     $stmt->execute([$email]);
 */
declare(strict_types=1);

$dbHost = '127.0.0.1';   // use 127.0.0.1, not "localhost" (localhost means a Unix socket)
$dbPort = 3307;          // the Docker container publishes MySQL on 3307
$dbName = 'event_platform';
$dbUser = 'showup';
$dbPass = 'CHANGE_ME';

try {
    $pdo = new PDO(
        "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4",
        $dbUser,
        $dbPass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,   // real prepared statements
        ]
    );
} catch (PDOException $e) {
    error_log('DB connection failed: ' . $e->getMessage());
    http_response_code(500);
    echo 'Database connection failed.';
    exit(1);
}
