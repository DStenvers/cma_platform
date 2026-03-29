<?php
/**
 * Set migration status to a specific version
 *
 * Inserts version records for all migrations up to the specified version in the 'data' database.
 * Usage: /cma/tools/set_migration_version.php?version=6.4.0
 */

require_once dirname(__DIR__) . '/bootstrap.inc';

use App\Library\Database;
use App\Library\Request;
use App\Library\Response;
use Cma\SecurityHelper;

// Security check
if (!SecurityHelper::isDeveloper()) {
    http_response_code(403);
    die("Toegang geweigerd - alleen developers");
}

Response::noCache();

$targetVersion = Request::query('version', '6.4.0');

echo "<pre>\n";
echo "Setting migration status to version $targetVersion\n";
echo str_repeat("=", 50) . "\n\n";

// Load migrations.json to get all versions
$migrationsFile = dirname(__DIR__) . '/config/migrations.json';
$migrationsData = json_decode(file_get_contents($migrationsFile), true);
$migrations = $migrationsData['migrations'] ?? [];

// Get versions up to target
$versionsToApply = [];
foreach ($migrations as $m) {
    if (version_compare($m['version'], $targetVersion, '<=')) {
        $versionsToApply[] = [
            'version' => $m['version'],
            'description' => $m['description'] ?? ''
        ];
    }
}

echo "Migrations to mark as applied: " . count($versionsToApply) . "\n";
foreach ($versionsToApply as $v) {
    echo "  - {$v['version']}: {$v['description']}\n";
}
echo "\n";

$now = date('Y-m-d H:i:s');

try {
    $conn = Database::getConnection('data');
    if (!$conn) {
        echo "ERROR: No connection to 'data' database\n";
        exit(1);
    }

    $driver = $conn->getAttribute(PDO::ATTR_DRIVER_NAME);
    echo "Driver: $driver\n";

    // Check if version table exists
    $hasTable = false;
    if ($driver === 'odbc') {
        try {
            $conn->query("SELECT TOP 1 version FROM _cma_version");
            $hasTable = true;
        } catch (Exception $e) {
            $hasTable = false;
        }
    }

    if (!$hasTable) {
        // Create the table
        echo "Creating _cma_version table...\n";
        if ($driver === 'odbc') {
            $conn->exec("CREATE TABLE _cma_version (
                id AUTOINCREMENT PRIMARY KEY,
                version VARCHAR(20) NOT NULL,
                applied_at DATETIME,
                description VARCHAR(255)
            )");
        }
        echo "Table created\n";
    }

    // Check current version
    $stmt = $conn->query("SELECT TOP 1 version FROM _cma_version ORDER BY applied_at DESC");
    $currentRow = $stmt->fetch(PDO::FETCH_ASSOC);
    $currentVersion = $currentRow['version'] ?? '0.0.0';
    echo "Current version: $currentVersion\n";

    // Insert missing versions
    $inserted = 0;
    foreach ($versionsToApply as $v) {
        // Check if version already exists
        $checkSql = "SELECT COUNT(*) FROM _cma_version WHERE version = ?";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->execute([$v['version']]);
        $exists = (int)$checkStmt->fetchColumn() > 0;

        if (!$exists) {
            $sql = "INSERT INTO _cma_version (version, applied_at, description) VALUES ('" .
                   $v['version'] . "', #" . $now . "#, '" .
                   addslashes($v['description']) . "')";
            $conn->exec($sql);
            $inserted++;
            echo "  + Inserted: {$v['version']}\n";
        }
    }

    echo "\nInserted $inserted version records\n";

    // Show final state
    $stmt = $conn->query("SELECT TOP 5 version FROM _cma_version ORDER BY applied_at DESC");
    $versions = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Latest versions: " . implode(', ', $versions) . "\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "Done! Refresh the migrations page to verify.\n";
echo "</pre>\n";
