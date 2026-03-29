<?php
/**
 * Export Repository Database to JSON Configuration Files (CLI Version)
 *
 * This is a standalone CLI script that exports repository data to JSON.
 * Run from command line: php tools_export_repository_cli.php
 */

// Define paths
$siteRoot = dirname(__DIR__);
$basePath = '/db/';
$configPath = __DIR__ . '/config/';

// Ensure config directory exists
if (!is_dir($configPath)) {
    mkdir($configPath, 0755, true);
}
if (!is_dir($configPath . 'schema/')) {
    mkdir($configPath . 'schema/', 0755, true);
}

// Set up database connection
$dbPath = $siteRoot . '/db/repository.mdb';
if (!file_exists($dbPath)) {
    die("Error: Repository database not found at: $dbPath\n");
}

$dsn = 'odbc:Driver={Microsoft Access Driver (*.mdb, *.accdb)};Dbq=' . $dbPath . ';Charset=UTF-8';

try {
    $connrep = new PDO($dsn);
    $connrep->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Error connecting to repository database: " . $e->getMessage() . "\n");
}

echo "=== Repository to JSON Export (CLI) ===\n\n";

// ============================================================================
// 1. Export tblDatabases
// ============================================================================
echo "Exporting tblDatabases...\n";

$sql = "SELECT ID, Title, ConnectionString FROM tblDatabases ORDER BY ID";
$stmt = $connrep->query($sql);

$databases = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
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
// 2. Export tblModules
// ============================================================================
echo "Exporting tblModules...\n";

$sql = "SELECT ID, Name, fkDatabase, blnActive, ExecutionOrder, Cache_Prefix FROM tblModules ORDER BY ExecutionOrder, Name";
$stmt = $connrep->query($sql);

$modules = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    // Find database name
    $dbId = (int)($row['fkDatabase'] ?? 0);
    $dbName = 'data';
    foreach ($databases as $db) {
        if ($db['id'] === $dbId) {
            $dbName = $db['name'];
            break;
        }
    }

    $modules[] = [
        'id' => (int)$row['ID'],
        'name' => $row['Name'] ?? '',
        'database' => $dbName,
        'active' => (bool)($row['blnActive'] ?? true),
        'order' => (int)($row['ExecutionOrder'] ?? 0),
        'cachePrefix' => $row['Cache_Prefix'] ?? '',
    ];
}

// Export module parameters
$sql = "SELECT ModuleNaam, ParamType, Caption, Name, Waarde, PostCaption, Sortorder FROM tblModuleParameters ORDER BY ModuleNaam, Sortorder";
$stmt = $connrep->query($sql);

$moduleParams = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $moduleName = $row['ModuleNaam'] ?? '';
    if (!isset($moduleParams[$moduleName])) {
        $moduleParams[$moduleName] = [];
    }
    $moduleParams[$moduleName][] = [
        'name' => $row['Name'] ?? '',
        'type' => $row['ParamType'] ?? 'text',
        'caption' => $row['Caption'] ?? '',
        'value' => $row['Waarde'] ?? '',
        'postCaption' => $row['PostCaption'] ?? '',
    ];
}

// Attach parameters to modules
foreach ($modules as &$module) {
    $name = $module['name'];
    if (isset($moduleParams[$name])) {
        $module['parameters'] = $moduleParams[$name];
    }
}
unset($module);

$modulesJson = [
    '$schema' => './schema/modules.schema.json',
    'modules' => $modules
];

file_put_contents($configPath . 'modules.json',
    json_encode($modulesJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n"
);
echo "  -> Exported " . count($modules) . " modules\n";

// ============================================================================
// 3. Export tblMenu and tblMenuItems
// ============================================================================
echo "Exporting tblMenu and tblMenuItems...\n";

$sql = "SELECT ID, Name, ExecutionOrder FROM tblMenu ORDER BY ExecutionOrder, Name";
$stmt = $connrep->query($sql);

$menus = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $menuId = (int)$row['ID'];
    $menus[$menuId] = [
        'id' => $menuId,
        'name' => $row['Name'] ?? '',
        'order' => (int)($row['ExecutionOrder'] ?? 0),
        'items' => [],
    ];
}

// Get menu items
$sql = "SELECT tblMenuItems.ID, tblMenuItems.fkMenuID, tblMenuItems.Name, tblMenuItems.Href, " .
       "tblMenuItems.ExecutionOrder, tblForms.ID as FormID, tblForms.FormName " .
       "FROM tblMenuItems LEFT JOIN tblForms ON tblMenuItems.fkFormID = tblForms.ID " .
       "ORDER BY tblMenuItems.fkMenuID, tblMenuItems.ExecutionOrder";
$stmt = $connrep->query($sql);

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
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
$stmt = $connrep->query($sql);

$controlTypes = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
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
$stmt = $connrep->query($sql);

$appConfig = [
    '$schema' => './schema/app.schema.json',
    'company' => [
        'logo' => '',
        'logoWidth' => 200,
        'logoHeight' => 50,
        'url' => '',
    ],
    'settings' => [
        'language' => 'NL',
        'dateFormat' => 'DD-MM-YYYY',
        'timezone' => 'Europe/Amsterdam',
    ],
];

if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
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

$sql = "SELECT tblReports.ID, tblReports.Title, tblReports.Query, tblReports.IDField, " .
       "tblReports.fkModule, tblReports.fkParentReport, tblReports.Visible, tblReports.Href, " .
       "tblReports.GroupField1, tblReports.GroupField2, tblReports.GroupField3, " .
       "tblReports.FilterIDField, tblReports.FilterDisplayField, tblReports.filterCaption, " .
       "tblReports.EditForm, tblReports.EditURL, " .
       "tblReports.blnWordTextOnly, tblReports.blnWordSkipEmpty, " .
       "tblModules.Name as ModuleName, tblModules.fkDatabase " .
       "FROM tblReports LEFT JOIN tblModules ON tblReports.fkModule = tblModules.ID " .
       "ORDER BY tblModules.Name, tblReports.Title";
$stmt = $connrep->query($sql);

$reports = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $report = [
        'id' => (int)$row['ID'],
        'title' => $row['Title'] ?? '',
        'module' => $row['ModuleName'] ?? '',
        'moduleId' => (int)($row['fkModule'] ?? 0),
        'databaseId' => (int)($row['fkDatabase'] ?? 6),
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

$sql = "SELECT tblXMLStore.ID, tblXMLStore.Name, tblXMLStore.Query, tblXMLStore.blnSelectable, " .
       "tblModules.Name as ModuleName, tblModules.fkDatabase " .
       "FROM tblXMLStore INNER JOIN tblModules ON tblXMLStore.fkModule = tblModules.ID " .
       "WHERE tblModules.blnActive = True " .
       "ORDER BY tblXMLStore.Name";
$stmt = $connrep->query($sql);

$dataSources = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    // Find database name
    $dbId = (int)($row['fkDatabase'] ?? 0);
    $dbName = 'data';
    foreach ($databases as $db) {
        if ($db['id'] === $dbId) {
            $dbName = $db['name'];
            break;
        }
    }

    $dataSources[] = [
        'id' => (int)$row['ID'],
        'name' => $row['Name'] ?? '',
        'module' => $row['ModuleName'] ?? '',
        'query' => $row['Query'] ?? '',
        'selectable' => (bool)($row['blnSelectable'] ?? true),
        'database' => $dbName,
    ];
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
echo "2. Update code to use ConfigLoader\n";
echo "3. Test functionality\n";
