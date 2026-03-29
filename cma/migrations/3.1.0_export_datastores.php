<?php
/**
 * Export DataStores to JSON - Migration Script
 *
 * Exports all data stores from tblXMLStore to config/data-sources.json
 * (Renamed from XMLStore to DataSource for clarity)
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

// Export data stores
$result = exportDataStoresToJson();

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
    cma_html_header('DataStores exporteren');
    echo '<body class="contentbody tools">';
    echo '<div id="c">';

    if ($result['success']) {
        echo '<lib-message type="success"><strong>DataStores succesvol geexporteerd!</strong><br>' . htmlspecialchars($result['message']) . '</lib-message>';
    } else {
        echo '<lib-message type="error"><strong>Export mislukt!</strong><br>' . htmlspecialchars($result['message']) . '</lib-message>';
    }

    echo '</div></body></html>';
}

/**
 * Export all data stores from tblXMLStore to JSON
 */
function exportDataStoresToJson(): array
{
    global $connrep;

    $version = '1.0.2'; // Consolidated to data-sources.json
    $configPath = dirname(__DIR__, 2) . '/assets/datastores/data-sources.json';

    try {
        // Check if file exists and has same version
        if (file_exists($configPath)) {
            $existing = json_decode(file_get_contents($configPath), true);
            if ($existing && ($existing['version'] ?? '') === $version) {
                return ['success' => true, 'message' => 'data-sources.json is al up-to-date (versie ' . $version . ')'];
            }
        }

        // First load databases.json for database name mapping
        $databasesPath = dirname(__DIR__, 2) . '/data/databases.json';
        $databases = [];
        if (file_exists($databasesPath)) {
            $dbConfig = json_decode(file_get_contents($databasesPath), true);
            $databases = $dbConfig['databases'] ?? [];
        }

        // Query all data stores with module info
        $sql = "SELECT tblXMLStore.ID, tblXMLStore.Name, tblXMLStore.Query, tblXMLStore.blnSelectable, " .
               "tblXMLStore.fkModule, tblModules.Name as ModuleName, tblModules.fkDatabase " .
               "FROM tblXMLStore LEFT JOIN tblModules ON tblXMLStore.fkModule = tblModules.ID " .
               "ORDER BY tblXMLStore.Name";
        // Use adOpenStatic (3) for memo/text fields - adOpenForwardOnly can truncate them
        $cursorType = defined('adOpenStatic') ? adOpenStatic : 3;
        $rs = Database::openRS($sql, $connrep, $cursorType);

        if ($rs === null) {
            return ['success' => false, 'message' => 'Kan tblXMLStore niet lezen: ' . Database::getLastError()];
        }

        $dataStores = [];
        while (!$rs->EOF) {
            $row = $rs->fields;

            // Find database name from ID
            $dbId = (int)($row['fkDatabase'] ?? 0);
            $dbName = 'data';
            foreach ($databases as $db) {
                if (($db['id'] ?? 0) === $dbId) {
                    $dbName = $db['name'] ?? 'data';
                    break;
                }
            }

            $dataStores[] = [
                'id' => (int)$row['ID'],
                'name' => $row['Name'] ?? '',
                'query' => $row['Query'] ?? '',
                'selectable' => (bool)($row['blnSelectable'] ?? true),
                'module' => $row['ModuleName'] ?? '',
                'moduleId' => (int)($row['fkModule'] ?? 0),
                'database' => $dbName,
            ];

            $rs->MoveNext();
        }

        // Build JSON structure
        $json = [
            '$schema' => './schema/data-sources.schema.json',
            'version' => $version,
            'description' => 'Data source definitions (formerly XMLStore) - exported from tblXMLStore',
            'dataSources' => $dataStores,
        ];

        // Ensure config directory exists
        $configDir = dirname($configPath);
        if (!is_dir($configDir)) {
            mkdir($configDir, 0755, true);
        }

        // Save to file
        $content = json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (file_put_contents($configPath, $content) === false) {
            return ['success' => false, 'message' => 'Kan data-sources.json niet opslaan'];
        }

        return [
            'success' => true,
            'message' => count($dataStores) . ' data sources geexporteerd naar config/data-sources.json'
        ];

    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Fout: ' . $e->getMessage()];
    }
}
