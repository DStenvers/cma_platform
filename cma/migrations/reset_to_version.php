<?php
/**
 * Reset migration status to a specific version
 *
 * Usage: /cma/migrations/reset_to_version.php?version=4.0.20
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

$targetVersion = Request::query('version', '4.0.20');

echo "<pre>\n";
echo "Resetting migration status to before version $targetVersion\n";
echo str_repeat("=", 50) . "\n\n";

$databases = ['rep', 'users', 'data'];

foreach ($databases as $db) {
    try {
        $conn = Database::getConnection($db);
        if ($conn) {
            // Get driver info
            $driver = $conn->getAttribute(PDO::ATTR_DRIVER_NAME);
            echo "[$db] Driver: $driver\n";

            // Check if version table exists
            try {
                $check = $conn->query("SELECT COUNT(*) FROM _cma_version");
                $count = $check->fetchColumn();
                echo "[$db] Found $count version records\n";
            } catch (Exception $e) {
                echo "[$db] No version table found, skipping\n\n";
                continue;
            }

            // Delete versions >= target
            $sql = "DELETE FROM _cma_version WHERE version >= ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$targetVersion]);
            $affected = $stmt->rowCount();
            echo "[$db] Deleted $affected version records >= $targetVersion\n";

            // Show remaining versions
            $stmt = $conn->query('SELECT version FROM _cma_version ORDER BY version DESC');
            $versions = $stmt->fetchAll(PDO::FETCH_COLUMN);
            echo "[$db] Remaining versions: " . (empty($versions) ? "(none)" : implode(', ', $versions)) . "\n\n";
        }
    } catch (Exception $e) {
        echo "[$db] Error: " . $e->getMessage() . "\n\n";
    }
}

echo str_repeat("=", 50) . "\n";
echo "Done! Now run migrations again.\n";
echo "</pre>\n";
