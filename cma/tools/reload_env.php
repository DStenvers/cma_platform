<?php
/**
 * Force reload environment variables from .env file and test connection
 */

// Don't use bootstrap - we need to reload fresh
$siteRoot = dirname(__DIR__, 2);

// Map environment codes to .env file names
$envFileMap = [
    'L' => '.env.local',
    'O' => '.env.development',
    'T' => '.env.test',
    'A' => '.env.acceptance',
    'P' => '.env.production'
];

// Determine which env file based on APP_ENVIRONMENT
$appEnv = $_ENV['APP_ENVIRONMENT'] ?? $_SERVER['APP_ENVIRONMENT'] ?? null;
$envFileName = '.env'; // Default fallback

if ($appEnv && isset($envFileMap[$appEnv])) {
    $envFileName = $envFileMap[$appEnv];
} else {
    // Auto-detect by checking which .env file exists
    foreach ($envFileMap as $code => $fileName) {
        if (file_exists($siteRoot . '/' . $fileName)) {
            $envFileName = $fileName;
            break;
        }
    }
}

$envFile = $siteRoot . '/' . $envFileName;

echo "<pre>\n";
echo "Force reloading environment from: $envFile\n\n";

if (!file_exists($envFile)) {
    die("ERROR: $envFileName not found at $envFile\n");
}

// Read and parse .env file
$lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$updated = [];

foreach ($lines as $line) {
    // Skip comments
    if (strpos(trim($line), '#') === 0) continue;

    // Parse KEY=value
    if (strpos($line, '=') !== false) {
        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value, " \t\n\r\0\x0B\"'");

        // Only update CONN_USERS related (uppercase keys only)
        if (strpos($key, 'CONN_USERS') === 0) {
            $_ENV[$key] = $value;
            putenv("$key=$value");
            $updated[$key] = $value;
        }
    }
}

echo "Updated environment variables:\n";
foreach ($updated as $key => $value) {
    echo "  $key = $value\n";
}

echo "\nCurrent CONN_USERS settings:\n";
echo "  CONN_USERS_PATH = " . ($_ENV['CONN_USERS_PATH'] ?? getenv('CONN_USERS_PATH') ?? '(not set)') . "\n";
echo "  CONN_USERS_DRIVER = " . ($_ENV['CONN_USERS_DRIVER'] ?? getenv('CONN_USERS_DRIVER') ?? '(not set)') . "\n";

// Now test the connection
echo "\n--- Testing Database Connection ---\n";

require_once dirname(__DIR__) . '/bootstrap.inc';

use App\Library\Database;

// Force reset the users connection
Database::resetConnection('users');

try {
    $conn = Database::getConnection('users');
    $driver = $conn->getAttribute(PDO::ATTR_DRIVER_NAME);
    echo "Connection driver: $driver\n";

    $stmt = $conn->query("SELECT COUNT(*) FROM tblUsers");
    $count = $stmt->fetchColumn();
    echo "User count: $count\n";

    if ($driver === 'odbc') {
        echo "\n✓ SUCCESS: Using MS Access (ODBC)!\n";
    } else {
        echo "\n✗ PROBLEM: Using $driver instead of ODBC (MS Access)\n";
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

echo "</pre>\n";
