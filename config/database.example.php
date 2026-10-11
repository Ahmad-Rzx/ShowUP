<?php
/**
 * Database connection (template), MySQLi version as taught in the course guide.
 *
 * scripts/setup.sh creates config/database.php from this file automatically.
 * To do it by hand: copy this file to config/database.php and fill in your values.
 * config/database.php is gitignored, so never commit real passwords.
 *
 * Usage in any page:
 *     require_once __DIR__ . '/../config/database.php';   // gives you $conn
 *     $stmt = $conn->prepare('SELECT user_id FROM users WHERE email = ?');
 *     $stmt->bind_param('s', $email);
 *     $stmt->execute();
 */
declare(strict_types=1);

$host     = '127.0.0.1';   // use 127.0.0.1, not "localhost" (localhost means a Unix socket)
$port     = 3307;          // the Docker container publishes MySQL on 3307
$username = 'showup';
$password = 'CHANGE_ME';
$database = 'event_platform';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);   // errors become exceptions

try {
    $conn = new mysqli($host, $username, $password, $database, $port);
    $conn->set_charset('utf8mb4');
} catch (mysqli_sql_exception $e) {
    error_log('DB connection failed: ' . $e->getMessage());   // details go to the log, not the browser
    http_response_code(500);
    die('Database connection failed.');
}
