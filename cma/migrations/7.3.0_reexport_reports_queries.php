<?php
/**
 * Re-export Reports with Query field - Migration Script
 *
 * Version 3.0.0 exported reports metadata but Query fields were empty.
 * This migration forces a re-export with proper memo field handling.
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

// Export reports
$result = reexportReportsWithQueries();

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
    cma_html_header('Reports opnieuw exporteren');
    echo '<body class="contentbody tools">';
    echo '<div id="c">';

    if ($result['success']) {
        echo '<lib-message type="success"><strong>Reports succesvol geexporteerd!</strong><br>' . htmlspecialchars($result['message']) . '</lib-message>';
    } else {
        echo '<lib-message type="error"><strong>Export mislukt!</strong><br>' . htmlspecialchars($result['message']) . '</lib-message>';
    }

    echo '</div></body></html>';
}

/**
 * Re-export all reports from tblReports to JSON with Query fields
 */
function reexportReportsWithQueries(): array
{
    $version = '2.5.0'; // Simple SELECT * and read Query directly in loop
    $configPath = dirname(__DIR__, 2) . '/data/reports.json';

    try {
        // Get rep database connection
        $connrep = Database::getRepConnection();

        if ($connrep === null) {
            return ['success' => false, 'message' => 'Kan geen verbinding maken met rep database'];
        }

        // Debug: check environment
        $env = \App\Library\Application::get('omgeving', 'P');
        $isDebug = in_array($env, ['O', 'T', 'A']) || php_sapi_name() === 'cli';

        // IMPORTANT: Access ODBC has issues with memo fields in JOINs
        // So we query reports without JOIN first, then get module info separately

        // First, build a map of module info
        $moduleSql = "SELECT ID, [Name], fkDatabase FROM tblModules";
        $moduleRs = Database::openRS($moduleSql, $connrep);
        $modules = [];
        while ($moduleRs && !$moduleRs->EOF) {
            $m = $moduleRs->fields;
            $modules[(int)$m['ID']] = [
                'name' => $m['Name'] ?? '',
                'databaseId' => (int)($m['fkDatabase'] ?? 6)
            ];
            $moduleRs->MoveNext();
        }

        // Query all reports with SELECT * - read Query memo field directly
        $sql = "SELECT * FROM tblReports ORDER BY Title";

        if ($isDebug) {
            echo "  Debug: SQL = " . $sql . "\n";
        }

        $rs = Database::openRS($sql, $connrep);

        if ($rs === null) {
            $error = Database::getLastError();
            return ['success' => false, 'message' => 'Kan tblReports niet lezen: ' . $error];
        }

        // Load existing reports.json to preserve any manual additions
        $existingReports = [];
        if (file_exists($configPath)) {
            $existing = json_decode(file_get_contents($configPath), true);
            if ($existing && isset($existing['reports'])) {
                foreach ($existing['reports'] as $rep) {
                    $existingReports[$rep['id']] = $rep;
                }
            }
        }

        $reports = [];
        $queriesFound = 0;
        $queriesEmpty = 0;

        while (!$rs->EOF) {
            $row = $rs->fields;
            $id = (int)$row['ID'];
            $fkModule = (int)($row['fkModule'] ?? 0);

            // Get module info from our pre-loaded map
            $moduleInfo = $modules[$fkModule] ?? ['name' => '', 'databaseId' => 6];

            $report = [
                'id' => $id,
                'title' => $row['Title'] ?? '',
                'module' => $moduleInfo['name'],
                'databaseId' => $moduleInfo['databaseId'],
                'visible' => (bool)($row['Visible'] ?? true),
            ];

            // Get Query field directly from row
            $queryValue = $row['Query'] ?? null;

            if (!empty($queryValue)) {
                $report['query'] = trim($queryValue);
                $queriesFound++;
            } else {
                // Check if existing JSON has a query (manual addition)
                if (isset($existingReports[$id]['query']) && !empty($existingReports[$id]['query'])) {
                    $report['query'] = $existingReports[$id]['query'];
                    $queriesFound++;
                } else {
                    $queriesEmpty++;
                    if ($isDebug) {
                        echo "  Warning: Report {$id} ({$row['Title']}) has no query\n";
                    }
                }
            }

            // Optional fields
            if (!empty($row['fkParentReport'])) {
                $report['parentReportId'] = (int)$row['fkParentReport'];
            }
            if (!empty($row['ParentField'])) {
                $report['parentField'] = $row['ParentField'];
            }
            if (!empty($row['IDField'])) {
                $report['idField'] = $row['IDField'];
            }
            if (!empty($row['GroupField1'])) {
                $report['groupField1'] = $row['GroupField1'];
            }
            if (!empty($row['GroupField2'])) {
                $report['groupField2'] = $row['GroupField2'];
            }
            if (!empty($row['GroupField3'])) {
                $report['groupField3'] = $row['GroupField3'];
            }
            if (!empty($row['EditURL'])) {
                $report['editUrl'] = $row['EditURL'];
            }
            if (!empty($row['EditForm'])) {
                $report['editForm'] = $row['EditForm'];
            }
            if (!empty($row['FilterIDField'])) {
                $report['filterIdField'] = $row['FilterIDField'];
            }
            if (!empty($row['FilterDisplayField'])) {
                $report['filterDisplayField'] = $row['FilterDisplayField'];
            }
            if (!empty($row['FilterCaption'])) {
                $report['filterCaption'] = $row['FilterCaption'];
            }
            if ($row['blnWordTextOnly'] ?? false) {
                $report['wordTextOnly'] = true;
            }
            if ($row['blnWordSkipEmpty'] ?? false) {
                $report['wordSkipEmpty'] = true;
            }

            $reports[] = $report;
            $rs->MoveNext();
        }

        // Build JSON structure
        $json = [
            '$schema' => './schema/reports.schema.json',
            'version' => $version,
            'description' => 'Report definitions - exported from tblReports with queries',
            'reports' => $reports,
        ];

        // Ensure config directory exists
        $configDir = dirname($configPath);
        if (!is_dir($configDir)) {
            mkdir($configDir, 0755, true);
        }

        // Save to file
        $content = json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (file_put_contents($configPath, $content) === false) {
            return ['success' => false, 'message' => 'Kan reports.json niet opslaan'];
        }

        return [
            'success' => true,
            'message' => count($reports) . ' reports geexporteerd naar config/reports.json (' .
                         $queriesFound . ' met query, ' . $queriesEmpty . ' zonder query)'
        ];

    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Fout: ' . $e->getMessage()];
    }
}
