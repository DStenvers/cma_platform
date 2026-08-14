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
        echo '<lib-message type="success"><span class="cma-migration__strong">Databases succesvol geexporteerd!</span><br>' . htmlspecialchars($result['message']) . '</lib-message>';
    } else {
        echo '<lib-message type="error"><span class="cma-migration__strong">Export mislukt!</span><br>' . htmlspecialchars($result['message']) . '</lib-message>';
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
        // A databases.json that is already there wins, whatever its version.
        //
        // This is a ONE-TIME export: it lifts the connections out of the legacy
        // Access tblDatabases for a site that has no JSON yet. Once the file
        // exists it IS the source of truth — Bootstrap reads it, the Installer
        // lists it as a protected config, and operators edit it by hand.
        //
        // Matching on `version === '1.0.0'` meant a NEWER file counted as "not
        // up to date" and was rebuilt into the older 1.0.0 shape, throwing away
        // the fields that shape does not carry (`type`, `path`, `default`) and
        // replacing hand-configured connections with whatever the Access table
        // still held. A migration that silently downgrades live configuration
        // is worse than one that does nothing.
        if (file_exists($configPath)) {
            $existing = json_decode((string) file_get_contents($configPath), true);
            if (is_array($existing) && !empty($existing['databases']) && is_array($existing['databases'])) {
                return ['success' => true, 'message' => 'databases.json bestaat al (versie '
                    . ($existing['version'] ?? 'onbekend') . ', ' . count($existing['databases'])
                    . ' connecties) — ongemoeid gelaten'];
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
