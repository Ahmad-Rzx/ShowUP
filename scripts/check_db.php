<?php
/**
 * Quick health check:  php scripts/check_db.php
 * Confirms the PHP driver, the connection and the schema are all working.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit('Run this from the command line.');
}

function fail(string $msg): never
{
    fwrite(STDERR, "FAIL: {$msg}\n");
    exit(1);
}

if (!extension_loaded('pdo_mysql')) {
    fail('PHP extension pdo_mysql is missing. Install it with: sudo apt install php-mysql');
}

$config = __DIR__ . '/../config/db.php';
if (!is_file($config)) {
    fail('config/db.php not found. Run ./scripts/setup.sh first.');
}
require $config;   // exits with an error by itself if it cannot connect

$version = $pdo->query('SELECT VERSION()')->fetchColumn();
$tables  = $pdo->query('SHOW FULL TABLES')->fetchAll(PDO::FETCH_NUM);
$expected = ['users', 'categories', 'events', 'registrations', 'event_reviews', 'user_reliability'];
$found = array_column($tables, 0);
$missing = array_diff($expected, $found);

echo "PHP         : " . PHP_VERSION . "\n";
echo "MySQL       : {$version}\n";
echo "Tables/views: " . implode(', ', $found) . "\n";

if ($missing) {
    fail('Missing from the database: ' . implode(', ', $missing) . '. Run ./scripts/setup.sh --reset-db');
}

$cats = (int) $pdo->query('SELECT COUNT(*) FROM categories')->fetchColumn();
echo "Categories  : {$cats} seeded\n";
echo "OK: PHP can talk to MySQL and the schema is in place.\n";
