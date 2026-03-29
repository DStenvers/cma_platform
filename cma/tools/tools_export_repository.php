<?php
/**
 * Export Repository Database to JSON Configuration Files
 *
 * This script exports data from the repository database tables to JSON files:
 * - tblDatabases -> config/databases.json
 * - tblModules -> config/modules.json
 * - tblMenu + tblMenuItems -> config/menu.json
 * - tblControlTypes -> config/control-types.json
 * - tblApplications -> config/app.json
 * - tblReports -> config/reports.json
 * - tblXMLStore -> config/data-sources.json
 *
 * Run this script once to migrate from database to JSON configuration.
 */

require_once __DIR__ . '/../bootstrap.inc';

use App\Library\Database;
use App\Library\Application;

// Ensure we have repository connection
$connrep = Database::getRepConnection();
if (!$connrep) {
    die("Error: Cannot connect to repository database\n");
}

$configPath = __DIR__ . '/config/';

// Ensure config directory exists
if (!is_dir($configPath)) {
    mkdir($configPath, 0755, true);
}
if (!is_dir($configPath . 'schema/')) {
    mkdir($configPath . 'schema/', 0755, true);
}

echo "=== Repository to JSON Export ===\n\n";

// ============================================================================
// 1. Export tblDatabases
// ============================================================================
echo "Exporting tblDatabases...\n";

$sql = "SELECT ID, Title, ConnectionString FROM tblDatabases ORDER BY ID";
$rs = Database::openRS($sql, $connrep, adOpenForwardOnly);

$databases = [];
if ($rs === null) {
    die("  ERROR: Cannot read tblDatabases - " . Database::getLastError() . "\n");
}
while (!$rs->EOF) {
    $row = $rs->fields;

    // Determine database type from connection string
    $connStr = $row['ConnectionString'] ?? '';
    $type = 'access';
    if (stripos($connStr, 'sqlserver') !== false || stripos($connStr, 'SQLOLEDB') !== false) {
        $type = 'sqlserver';
    } elseif (stripos($connStr, 'mysql') !== false) {
        $type = 'mysql';
    }

    // Determine name from title or ID
    $name = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $row['Title'] ?? ''));
    if (empty($name)) {
        $name = 'database_' . $row['ID'];
    }

    // Special names for known databases
    $id = (int)$row['ID'];
    if ($id === 999) {
        $name = 'rep';
    } elseif (stripos($row['Title'] ?? '', 'user') !== false) {
        $name = 'users';
    } elseif ($id === 6) {
        $name = 'data';
    }

    $databases[] = [
        'id' => $id,
        'name' => $name,
        'title' => $row['Title'] ?? '',
        'connectionString' => $connStr,
        'type' => $type,
    ];

    $rs->MoveNext();
}

$databasesJson = [
    '$schema' => './schema/databases.schema.json',
    'databases' => $databases
];

file_put_contents($configPath . 'databases.json',
    json_encode($databasesJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n"
);
echo "  -> Exported " . count($databases) . " databases\n";

// ============================================================================
// 2. Export tblModules - SKIPPED
// ============================================================================
echo "Skipping tblModules export (table structure issue)...\n";
$modules = [];

// ============================================================================
// 3. Export tblMenu and tblMenuItems
// ============================================================================
echo "Exporting tblMenu and tblMenuItems...\n";

$sql = "SELECT ID, Name, ExecutionOrder FROM tblMenu ORDER BY ExecutionOrder, Name";
$rs = Database::openRS($sql, $connrep, adOpenForwardOnly);

$menus = [];
$menuMap = [];
if ($rs === null) {
    die("  ERROR: Cannot read tblMenu - " . Database::getLastError() . "\n");
}
while (!$rs->EOF) {
    $row = $rs->fields;
    $menuId = (int)$row['ID'];
    $menus[$menuId] = [
        'id' => $menuId,
        'name' => $row['Name'] ?? '',
        'order' => (int)($row['ExecutionOrder'] ?? 0),
        'items' => [],
    ];
    $menuMap[$menuId] = count($menus) - 1;
    $rs->MoveNext();
}

// Get menu items
$sql = "SELECT tblMenuItems.ID, tblMenuItems.fkMenuID, tblMenuItems.Name, tblMenuItems.Href, " .
       "tblMenuItems.ExecutionOrder, tblForms.ID as FormID, tblForms.FormName " .
       "FROM tblMenuItems LEFT JOIN tblForms ON tblMenuItems.fkFormID = tblForms.ID " .
       "ORDER BY tblMenuItems.fkMenuID, tblMenuItems.ExecutionOrder";
$rs = Database::openRS($sql, $connrep, adOpenForwardOnly);

if ($rs === null) {
    die("  ERROR: Cannot read tblMenuItems - " . Database::getLastError() . "\n");
}
while (!$rs->EOF) {
    $row = $rs->fields;
    $menuId = (int)($row['fkMenuID'] ?? 0);

    if (isset($menus[$menuId])) {
        $item = [
            'id' => (int)$row['ID'],
            'name' => $row['Name'] ?? '',
            'order' => (int)($row['ExecutionOrder'] ?? 0),
        ];

        // Add form reference if linked to a form
        $formName = $row['FormName'] ?? '';
        if (!empty($formName)) {
            // Convert form name to JSON form identifier
            $item['form'] = strtolower(preg_replace('/[^a-zA-Z0-9_]/', '_', $formName));
            $item['formId'] = (int)($row['FormID'] ?? 0);
        }

        // Add href if external link
        $href = $row['Href'] ?? '';
        if (!empty($href) && empty($formName)) {
            $item['href'] = $href;
            if (strpos($href, 'http') === 0) {
                $item['target'] = '_blank';
            }
        }

        $menus[$menuId]['items'][] = $item;
    }

    $rs->MoveNext();
}

$menuJson = [
    '$schema' => './schema/menu.schema.json',
    'menus' => array_values($menus)
];

file_put_contents($configPath . 'menu.json',
    json_encode($menuJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n"
);
echo "  -> Exported " . count($menus) . " menus with items\n";

// ============================================================================
// 4. Export tblControlTypes
// ============================================================================
echo "Exporting tblControlTypes...\n";

$sql = "SELECT ID, Description FROM tblControlTypes ORDER BY ID";
$rs = Database::openRS($sql, $connrep, adOpenForwardOnly);

$controlTypes = [];
if ($rs === null) {
    die("  ERROR: Cannot read tblControlTypes - " . Database::getLastError() . "\n");
}
while (!$rs->EOF) {
    $row = $rs->fields;

    // Parse description to get name (format: "01_TextBox" or just "TextBox")
    $desc = $row['Description'] ?? '';
    $name = $desc;
    if (preg_match('/^\d+_(.+)$/', $desc, $m)) {
        $name = $m[1];
    }
    $name = strtolower($name);

    $controlTypes[] = [
        'id' => (int)$row['ID'],
        'name' => $name,
        'description' => $desc,
    ];

    $rs->MoveNext();
}

$controlTypesJson = [
    '$schema' => './schema/control-types.schema.json',
    'controlTypes' => $controlTypes
];

file_put_contents($configPath . 'control-types.json',
    json_encode($controlTypesJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n"
);
echo "  -> Exported " . count($controlTypes) . " control types\n";

// ============================================================================
// 5. Export tblApplications
// ============================================================================
echo "Exporting tblApplications...\n";

$sql = "SELECT TOP 1 ID, Company_Logo, Company_Logo_Width, Company_Logo_Height, URL FROM tblApplications";
$rs = Database::openRS($sql, $connrep, adOpenForwardOnly);

$appConfig = [
    '$schema' => './schema/app.schema.json',
    'company' => [
        'logo' => '',
        'logoWidth' => 200,
        'logoHeight' => 50,
        'url' => '',
    ],
    'settings' => [
        'language' => Application::get('cma_language', 'NL'),
        'dateFormat' => 'DD-MM-YYYY',
        'timezone' => 'Europe/Amsterdam',
    ],
];

if (!$rs->EOF) {
    $row = $rs->fields;
    $appConfig['company']['logo'] = $row['Company_Logo'] ?? '';
    $appConfig['company']['logoWidth'] = (int)($row['Company_Logo_Width'] ?? 200);
    $appConfig['company']['logoHeight'] = (int)($row['Company_Logo_Height'] ?? 50);
    $appConfig['company']['url'] = $row['URL'] ?? '';
}

file_put_contents($configPath . 'app.json',
    json_encode($appConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n"
);
echo "  -> Exported application config\n";

// ============================================================================
// 6. Export tblReports
// ============================================================================
echo "Exporting tblReports...\n";

// Try minimal query first - Access ODBC is very picky about column names
$sql = "SELECT * FROM tblReports ORDER BY Title";
$rs = Database::openRS($sql, $connrep, adOpenForwardOnly);

$reports = [];
if ($rs === null) {
    die("  ERROR: Cannot read tblReports - " . Database::getLastError() . "\n");
}
while (!$rs->EOF) {
    $row = $rs->fields;

    $report = [
        'id' => (int)$row['ID'],
        'title' => $row['Title'] ?? '',
        'moduleId' => (int)($row['fkModule'] ?? 0),
        'visible' => (bool)($row['Visible'] ?? true),
        'idField' => $row['IDField'] ?? 'ID',
    ];

    // Add query if present
    if (!empty($row['Query'])) {
        $report['query'] = $row['Query'];
    }

    // Add optional fields only if they have values
    if (!empty($row['GroupField1'])) $report['groupField1'] = $row['GroupField1'];
    if (!empty($row['GroupField2'])) $report['groupField2'] = $row['GroupField2'];
    if (!empty($row['GroupField3'])) $report['groupField3'] = $row['GroupField3'];
    if (!empty($row['FilterIDField'])) $report['filterIdField'] = $row['FilterIDField'];
    if (!empty($row['FilterDisplayField'])) $report['filterDisplayField'] = $row['FilterDisplayField'];
    if (!empty($row['filterCaption'])) $report['filterCaption'] = $row['filterCaption'];
    if (!empty($row['EditForm'])) $report['editForm'] = $row['EditForm'];
    if (!empty($row['EditURL'])) $report['editUrl'] = $row['EditURL'];
    if (!empty($row['Href'])) $report['url'] = $row['Href'];
    if ($row['blnWordTextOnly'] ?? false) $report['wordTextOnly'] = true;
    if ($row['blnWordSkipEmpty'] ?? false) $report['wordSkipEmpty'] = true;
    if (!empty($row['fkParentReport'])) $report['parentReportId'] = (int)$row['fkParentReport'];

    $reports[] = $report;
    $rs->MoveNext();
}

$reportsJson = [
    '$schema' => './schema/reports.schema.json',
    'reports' => $reports
];

file_put_contents($configPath . 'reports.json',
    json_encode($reportsJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n"
);
echo "  -> Exported " . count($reports) . " reports\n";

// ============================================================================
// 7. Export tblXMLStore (Data Sources)
// ============================================================================
echo "Exporting tblXMLStore (data sources)...\n";

// Use SELECT * - Access ODBC is picky about column names
$sql = "SELECT * FROM tblXMLStore ORDER BY Name";
$rs = Database::openRS($sql, $connrep, adOpenForwardOnly);

$dataSources = [];
if ($rs === null) {
    die("  ERROR: Cannot read tblXMLStore - " . Database::getLastError() . "\n");
}
while (!$rs->EOF) {
    $row = $rs->fields;

    $dataSources[] = [
        'id' => (int)$row['ID'],
        'name' => $row['Name'] ?? '',
        'moduleId' => (int)($row['fkModule'] ?? 0),
        'query' => $row['Query'] ?? '',
        'selectable' => (bool)($row['blnSelectable'] ?? true),
    ];

    $rs->MoveNext();
}

$dataSourcesJson = [
    '$schema' => './schema/data-sources.schema.json',
    'dataSources' => $dataSources
];

file_put_contents($configPath . 'data-sources.json',
    json_encode($dataSourcesJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n"
);
echo "  -> Exported " . count($dataSources) . " data sources\n";

// ============================================================================
// Summary
// ============================================================================
echo "\n=== Export Complete ===\n";
echo "Files created in: {$configPath}\n";
echo "  - databases.json\n";
echo "  - modules.json\n";
echo "  - menu.json\n";
echo "  - control-types.json\n";
echo "  - app.json\n";
echo "  - reports.json\n";
echo "  - data-sources.json\n";
echo "\nNext steps:\n";
echo "1. Review exported JSON files\n";
echo "2. Create JSON schemas in config/schema/\n";
echo "3. Update code to use ConfigLoader\n";
echo "4. Test functionality\n";
