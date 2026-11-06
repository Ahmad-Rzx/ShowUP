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

if (!extension_loaded('mysqli')) {
    fail('PHP extension mysqli is missing. Install it with: sudo apt install php-mysql');
}

$config = __DIR__ . '/../config/database.php';
if (!is_file($config)) {
    fail('config/database.php not found. Run ./scripts/setup.sh first.');
}
require $config;   // provides $conn; exits with an error by itself if it cannot connect

$version  = $conn->query('SELECT VERSION()')->fetch_row()[0];
$found    = array_column($conn->query('SHOW FULL TABLES')->fetch_all(MYSQLI_NUM), 0);
$expected = ['users', 'categories', 'events', 'registrations', 'event_reviews', 'user_reliability'];
$missing  = array_diff($expected, $found);

echo "PHP         : " . PHP_VERSION . "\n";
echo "MySQL       : {$version}\n";
echo "Tables/views: " . implode(', ', $found) . "\n";

if ($missing) {
    fail('Missing from the database: ' . implode(', ', $missing) . '. Run ./scripts/setup.sh --reset-db');
}

$cats = (int) $conn->query('SELECT COUNT(*) FROM categories')->fetch_row()[0];
echo "Categories  : {$cats} seeded\n";

$hasEventMode = $conn->query("SHOW COLUMNS FROM events LIKE 'event_mode'")->num_rows > 0;
$hasReliabilityConfirmed = $conn->query("SHOW COLUMNS FROM user_reliability LIKE 'confirmed'")->num_rows > 0;

if (!$hasEventMode || !$hasReliabilityConfirmed) {
    fail('The event-mode migration is missing. Run database/migrations/2026_11_05_event_modes.sql');
}

echo "Event modes : ready (physical and online)\n";
echo "OK: PHP can talk to MySQL and the schema is in place.\n";
