<?php
/**
 * Export Databases to JSON - Migration Script
 *
 * Exports all database connections from tblDatabases to config/databases.json
 * This allows the system to work without querying tblDatabases for connection strings.
 */

use App\Library\Database;
use App\Library\Response;
use Cma\SecurityHelper;

// Fix path when running as migration
$basePath = defined('MIGRATION_RUNNING') ? dirname(__DIR__) : __DIR__;
if (strpos($basePath, 'migrations') !== false) {
    $basePath = dirname($basePath);
}
require_once $basePath . '/bootstrap.inc';

// Check if running as migration
$isMigration = defined('MIGRATION_RUNNING') || php_sapi_name() === 'cli' || \App\Library\Request::hasQuery('migration');

if (!$isMigration) {
    if (!SecurityHelper::isDeveloper()) {
        http_response_code(403);
        echo "Toegang geweigerd - alleen developers";
        exit(1);
    }
    Response::noCache();
}

// Export databases
$result = exportDatabasesToJson();

if ($isMigration) {
    if ($result['success']) {
        echo "✓ " . $result['message'] . "\n";
        if (defined('MIGRATION_RUNNING')) {
            return true;
        }
        exit(0);
    } else {
        echo "✗ " . $result['message'] . "\n";
        if (defined('MIGRATION_RUNNING')) {
            return false;
        }
        exit(1);
    }
} else {
    cma_html_header('Databases exporteren');
    echo '<body class="contentbody tools">';
    echo '<div id="c">';

    if ($result['success']) {
        echo '<lib-message type="success"><strong>Databases succesvol geexporteerd!</strong><br>' . htmlspecialchars($result['message']) . '</lib-message>';
    } else {
        echo '<lib-message type="error"><strong>Export mislukt!</strong><br>' . htmlspecialchars($result['message']) . '</lib-message>';
    }

    echo '</div></body></html>';
}

/**
 * Export all databases from tblDatabases to JSON
 */
function exportDatabasesToJson(): array
{
    $version = '1.0.0';
    $configPath = dirname(__DIR__, 2) . '/data/databases.json';

    try {
        // Check if file exists and has same version
        if (file_exists($configPath)) {
            $existing = json_decode(file_get_contents($configPath), true);
            if ($existing && ($existing['version'] ?? '') === $version) {
                return ['success' => true, 'message' => 'databases.json is al up-to-date (versie ' . $version . ')'];
            }
        }

        // Get rep database connection
        $connrep = Database::getRepConnection();

        if ($connrep === null) {
            return ['success' => false, 'message' => 'Kan geen verbinding maken met rep database'];
        }

        // Debug: check environment and show more info in dev/test
        $env = \App\Library\Application::get('omgeving', 'P');
        $isDebug = in_array($env, ['O', 'T', 'A']) || php_sapi_name() === 'cli';

        // Query all databases - use SELECT * to avoid column name issues with Access reserved words
        $sql = "SELECT * FROM tblDatabases ORDER BY ID";

        if ($isDebug) {
            echo "  Debug: SQL = " . $sql . "\n";
            echo "  Debug: Connection type = " . get_class($connrep) . "\n";
        }

        $rs = Database::openRS($sql, $connrep);

        if ($rs === null) {
            $error = Database::getLastError();
            if ($isDebug) {
                echo "  Debug: Query failed\n";
                echo "  Debug: Error = " . $error . "\n";
            }
            return ['success' => false, 'message' => 'Kan tblDatabases niet lezen: ' . $error];
        }

        // Debug: show available columns
        if ($isDebug && !$rs->EOF) {
            $fields = is_array($rs->fields) ? $rs->fields : (array)$rs->fields;
            echo "  Debug: Available columns: " . implode(', ', array_keys($fields)) . "\n";
        }

        $databases = [];
        while (!$rs->EOF) {
            $id = (int)$rs->fields['ID'];
            $databases[] = [
                'id' => $id,
                'name' => $rs->fields['Title'] ?? 'database_' . $id,
                'connectionString' => $rs->fields['ConnectionString'] ?? '',
                'description' => $rs->fields['Description'] ?? '',
            ];
            $rs->MoveNext();
        }

        // Build JSON structure
        $json = [
            '$schema' => './schema/databases.schema.json',
            'version' => $version,
            'description' => 'Database connection mappings - exported from tblDatabases',
            'databases' => $databases,
        ];

        // Save to file
        $content = json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (file_put_contents($configPath, $content) === false) {
            return ['success' => false, 'message' => 'Kan databases.json niet opslaan'];
        }

        return [
            'success' => true,
            'message' => count($databases) . ' databases geexporteerd naar config/databases.json'
        ];

    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Fout: ' . $e->getMessage()];
    }
}
