<?php
/**
 * Export Reports to JSON - Migration Script
 *
 * Exports all reports from tblReports to config/reports.json
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
$result = exportReportsToJson();

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
    cma_html_header('Reports exporteren');
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
 * Export all reports from tblReports to JSON
 */
function exportReportsToJson(): array
{
    $version = '1.2.0'; // Fixed: use adOpenStatic for memo fields
    $configPath = dirname(__DIR__, 2) . '/data/reports.json';

    try {
        // Check if file exists and has same version
        if (file_exists($configPath)) {
            $existing = json_decode(file_get_contents($configPath), true);
            if ($existing && ($existing['version'] ?? '') === $version) {
                return ['success' => true, 'message' => 'reports.json is al up-to-date (versie ' . $version . ')'];
            }
        }

        // Get rep database connection
        $connrep = Database::getRepConnection();

        if ($connrep === null) {
            return ['success' => false, 'message' => 'Kan geen verbinding maken met rep database'];
        }

        // Debug: check environment
        $env = \App\Library\Application::get('omgeving', 'P');
        $isDebug = in_array($env, ['O', 'T', 'A']) || php_sapi_name() === 'cli';

        // Query all reports with module info and database - use [Name] as it's a reserved word in Access
        // Available columns: ID, fkModule, fkParentReport, ParentField, Title, Query, IDField,
        // GroupField1, GroupField2, GroupField3, EditURL, EditForm, FilterIDField, FilterDisplayField,
        // FilterCaption, blnWordTextOnly, blnWordSkipEmpty, Visible
        $sql = "SELECT tblReports.*, tblModules.[Name] as ModuleName, tblModules.fkDatabase as DatabaseId " .
               "FROM tblReports LEFT JOIN tblModules ON tblReports.fkModule = tblModules.ID " .
               "ORDER BY tblModules.[Name], tblReports.Title";

        if ($isDebug) {
            echo "  Debug: SQL = " . $sql . "\n";
        }

        // Use adOpenStatic (3) for memo/text fields - adOpenForwardOnly can truncate them
        $cursorType = defined('adOpenStatic') ? adOpenStatic : 3;
        $rs = Database::openRS($sql, $connrep, $cursorType);

        if ($rs === null) {
            $error = Database::getLastError();
            if ($isDebug) {
                echo "  Debug: Query failed, trying SELECT * ...\n";
                $testRs = Database::openRS("SELECT * FROM tblReports", $connrep);
                if ($testRs !== null && !$testRs->EOF) {
                    $fields = is_array($testRs->fields) ? $testRs->fields : (array)$testRs->fields;
                    echo "  Debug: Available columns: " . implode(', ', array_keys($fields)) . "\n";
                }
            }
            return ['success' => false, 'message' => 'Kan tblReports niet lezen: ' . $error];
        }

        $reports = [];
        while (!$rs->EOF) {
            $row = $rs->fields;

            $report = [
                'id' => (int)$row['ID'],
                'title' => $row['Title'] ?? '',
                'module' => $row['ModuleName'] ?? '',
                'moduleId' => (int)($row['fkModule'] ?? 0),
                'databaseId' => (int)($row['DatabaseId'] ?? 6), // Default to database 6 (main)
                'visible' => (bool)($row['Visible'] ?? true),
            ];

            // Optional fields
            if (!empty($row['fkParentReport'])) {
                $report['parentReportId'] = (int)$row['fkParentReport'];
            }
            if (!empty($row['ParentField'])) {
                $report['parentField'] = $row['ParentField'];
            }
            if (!empty($row['Query'])) {
                $report['query'] = $row['Query'];
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
            'description' => 'Report definitions - exported from tblReports',
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
            'message' => count($reports) . ' reports geexporteerd naar config/reports.json'
        ];

    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Fout: ' . $e->getMessage()];
    }
}
