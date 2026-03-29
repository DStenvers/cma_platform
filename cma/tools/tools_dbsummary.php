<?php
/**
 * Database Summary Tool
 * Shows database structure using PDO with Access support via Database helper.
 */
use App\Library\Database;
use App\Library\Request;
use App\Library\Response;
use Cma\CmaRepository;
use Cma\SchemaHelper;
use Cma\ToolbarHelper;

require_once __DIR__ . '/../bootstrap.inc';
Response::noCache();

$intDatabase = Request::post('Database', Request::query('Database', ''));
$bJSON = Request::query('JSON', '') !== '';
$isAjax = Request::query('ajax', '') === '1';
$exportFormat = Request::query('export', '');

// Auto-select if only one database available
$databases = CmaRepository::getSelectableDatabases();
if ($intDatabase === '' && count($databases) === 1) {
    $intDatabase = (string)$databases[0]['id'];
}

// Export request - return schema as JSON/XML/TXT download
if ($exportFormat !== '' && $intDatabase !== '') {
    $dbConfig = \Cma\ConfigLoader::getDatabase((int)$intDatabase);
    $dbTitle = $dbConfig['title'] ?? $dbConfig['name'] ?? ('Database ' . $intDatabase);
    $connName = $dbConfig['name'] ?? null;
    $schemaConn = $connName ?: CmaRepository::getResolvedConnectionString((int)$intDatabase);

    // Build schema data
    $schema = ['database' => $dbTitle, 'tables' => []];
    $tablesData = SchemaHelper::getTables($schemaConn);
    $conn = Database::getConnection($schemaConn);

    foreach ($tablesData as $tbl) {
        $tableName = $tbl['name'];
        $columns = SchemaHelper::getColumns($schemaConn, $tableName, true);
        $count = Database::getFieldValue($conn, 'SELECT COUNT(*) AS cnt FROM [' . $tableName . ']', 'cnt') ?? 0;
        $schema['tables'][] = [
            'name' => $tableName,
            'recordCount' => (int)$count,
            'columns' => array_map(function($c) {
                return [
                    'name' => $c['name'],
                    'type' => $c['dataTypeName'],
                    'sqlType' => $c['sqlTypeName'] ?? '',
                    'length' => $c['length'],
                    'nullable' => $c['nullable'],
                    'description' => $c['description'] ?? ''
                ];
            }, $columns)
        ];
    }

    $filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $dbTitle) . '_schema';

    if ($exportFormat === 'json') {
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.json"');
        echo json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    } elseif ($exportFormat === 'xml') {
        header('Content-Type: application/xml; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.xml"');
        $xml = new \SimpleXMLElement('<database/>');
        $xml->addAttribute('name', $schema['database']);
        foreach ($schema['tables'] as $tbl) {
            $tblEl = $xml->addChild('table');
            $tblEl->addAttribute('name', $tbl['name']);
            $tblEl->addAttribute('recordCount', $tbl['recordCount']);
            foreach ($tbl['columns'] as $col) {
                $colEl = $tblEl->addChild('column');
                $colEl->addAttribute('name', $col['name']);
                $colEl->addAttribute('type', $col['type']);
                if ($col['sqlType']) $colEl->addAttribute('sqlType', $col['sqlType']);
                if ($col['length']) $colEl->addAttribute('length', $col['length']);
                $colEl->addAttribute('nullable', $col['nullable'] ? 'true' : 'false');
                if ($col['description']) $colEl->addChild('description', htmlspecialchars($col['description']));
            }
        }
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        $dom->loadXML($xml->asXML());
        echo $dom->saveXML();
    } elseif ($exportFormat === 'txt') {
        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.txt"');
        $out = "Database: {$schema['database']}\n";
        $out .= str_repeat('=', 60) . "\n\n";
        foreach ($schema['tables'] as $tbl) {
            $out .= "{$tbl['name']} ({$tbl['recordCount']} records)\n";
            $out .= str_repeat('-', 60) . "\n";
            foreach ($tbl['columns'] as $col) {
                $type = $col['type'];
                if ($col['length']) $type .= '(' . $col['length'] . ')';
                $nullable = $col['nullable'] ? 'NULL' : 'NOT NULL';
                $desc = $col['description'] ? '  // ' . $col['description'] : '';
                $out .= sprintf("  %-30s %-20s %s%s\n", $col['name'], $type, $nullable, $desc);
            }
            $out .= "\n";
        }
        echo $out;
    }
    exit;
}

// AJAX request - return just the data
if ($isAjax && $intDatabase !== '') {
    header('Content-Type: text/html; charset=utf-8');

    // Get database info for context
    $dbConfig = \Cma\ConfigLoader::getDatabase((int)$intDatabase);
    $dbTitle = $dbConfig['title'] ?? $dbConfig['name'] ?? ('Database ' . $intDatabase);
    $connName = $dbConfig['name'] ?? null;

    try {
        // Use connection name if available, otherwise resolved connection string
        $schemaConn = $connName ?: CmaRepository::getResolvedConnectionString((int)$intDatabase);

        // Get PDO connection for queries
        $conn = Database::getConnection($schemaConn);

        // Get tables via centralized SchemaHelper (handles all filtering)
        $tablesData = SchemaHelper::getTables($schemaConn);
        $tables = array_column($tablesData, 'name');

        if (empty($tables)) {
            $result = 'Geen tabellen gevonden';
        } else {
            // Check if this is an Access database for enhanced info
            $isAccess = Database::getDatabaseType($conn) === 'access';

            // For Access databases, get native ODBC connection for enhanced column info
            $nativeOdbc = null;
            if ($isAccess && function_exists('odbc_columns')) {
                // Get DSN using the connection name (not hardcoded conn_data)
                $configKey = 'conn_' . ($connName ?? 'data');
                $dsn = \App\Library\Application::get($configKey, '');
                // Fallback to conn_data if specific key not found
                if (empty($dsn)) {
                    $dsn = \App\Library\Application::get('conn_data', '');
                }
                if (!empty($dsn)) {
                    $nativeDsn = preg_replace('/^odbc:/i', '', $dsn);
                    $nativeOdbc = @odbc_connect($nativeDsn, '', '', SQL_CUR_USE_ODBC);
                }
            }

            // Build detailed HTML output
            $colCount = $isAccess && $nativeOdbc ? 6 : 3;
            $output = '<table cellpadding="6" cellspacing="0" class="db-summary-table">';

            foreach ($tables as $tableName) {
                // Get record count
                $count = Database::getFieldValue($conn, 'SELECT COUNT(*) AS cnt FROM [' . $tableName . ']', 'cnt') ?? 0;

                $output .= '<tr><th colspan="' . $colCount . '" style="background:#e8e8e8;padding:4px;">' . htmlspecialchars($tableName) . ' | ' . $count . ' record(s)</th></tr>';

                if ($isAccess && $nativeOdbc) {
                    // Enhanced Access view with native ODBC
                    $output .= '<tr><th>Naam</th><th>Type</th><th>Grootte</th><th>Decimalen</th><th>Nullable</th><th>Beschrijving</th></tr>';

                    $colResult = @odbc_columns($nativeOdbc, null, null, $tableName);
                    if ($colResult) {
                        while ($row = odbc_fetch_array($colResult)) {
                            $colName = $row['COLUMN_NAME'] ?? '';
                            $typeName = $row['TYPE_NAME'] ?? 'UNKNOWN';
                            $colSize = $row['COLUMN_SIZE'] ?? '';
                            $decimals = $row['DECIMAL_DIGITS'] ?? '';
                            // NULLABLE: 0=NO, 1=YES, 2=UNKNOWN - cast to int as ODBC may return string
                            $nullableVal = (int)($row['NULLABLE'] ?? 1);
                            $nullable = $nullableVal === 0 ? 'Nee' : 'Ja';
                            // REMARKS may be in Windows-1252 encoding, convert to UTF-8
                            $remarks = $row['REMARKS'] ?? '';
                            if ($remarks !== '') {
                                if (!mb_check_encoding($remarks, 'UTF-8')) {
                                    $remarks = mb_convert_encoding($remarks, 'UTF-8', 'Windows-1252');
                                } else {
                                    $cleaned = @iconv('UTF-8', 'UTF-8//IGNORE', $remarks);
                                    if ($cleaned !== false) $remarks = $cleaned;
                                }
                                $remarks = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $remarks);
                            }

                            // Format type with size for string types
                            $typeDisplay = $typeName;
                            if (in_array(strtoupper($typeName), ['VARCHAR', 'CHAR', 'TEXT', 'LONGCHAR'])) {
                                if ($colSize && $colSize != 0) {
                                    $typeDisplay .= '(' . $colSize . ')';
                                }
                            }

                            $output .= '<tr>';
                            $output .= '<td>' . htmlspecialchars($colName) . '</td>';
                            $output .= '<td nowrap>' . htmlspecialchars($typeDisplay) . '</td>';
                            $output .= '<td align="right">' . htmlspecialchars($colSize) . '</td>';
                            $output .= '<td align="right">' . htmlspecialchars($decimals) . '</td>';
                            $output .= '<td align="center">' . $nullable . '</td>';
                            $output .= '<td style="color:#666;font-size:var(--font-size-xs);">' . htmlspecialchars($remarks) . '</td>';
                            $output .= '</tr>';
                        }
                        @odbc_free_result($colResult);
                    } else {
                        $output .= '<tr><td colspan="6" style="color:#999;">Kan kolommen niet ophalen</td></tr>';
                    }
                } else {
                    // Standard view using SchemaHelper (include hidden columns for full inspection)
                    $output .= '<tr><th>Naam</th><th>Type</th><th>Nullable</th></tr>';

                    // Get columns via centralized SchemaHelper (includeHidden=true for full inspection)
                    $columns = SchemaHelper::getColumns($schemaConn, $tableName, true);

                    if (!empty($columns)) {
                        foreach ($columns as $col) {
                            $typeName = $col['dataTypeName'];
                            if ($col['length']) {
                                $typeName .= '(' . $col['length'] . ')';
                            }

                            $checked = $col['nullable'] ? ' checked' : '';

                            $output .= '<tr><td>' . htmlspecialchars($col['name']) . '</td>';
                            $output .= '<td nowrap>' . htmlspecialchars($typeName) . '</td>';
                            $output .= '<td align="center"><input type="checkbox" onclick="return false"' . $checked . '></td></tr>';
                        }
                    } else {
                        $output .= '<tr><td colspan="3" style="color:#999;">Kan kolommen niet ophalen</td></tr>';
                    }
                }
            }

            // Close native ODBC connection
            if ($nativeOdbc) {
                @odbc_close($nativeOdbc);
            }

            $output .= '</table>';
            $result = $output;
        }
    } catch (\Exception $e) {
        $result = '<p style="color:red">Fout: ' . htmlspecialchars($e->getMessage()) . '</p>';
    }

    // Check for error messages and wrap in lib-message
    if (strpos($result, 'Geen tabellen gevonden') !== false) {
        echo '<lib-message type="warning">Geen tabellen gevonden in database: ' . htmlspecialchars($dbTitle) . '</lib-message>';
    } elseif (strpos($result, 'niet gevonden') !== false || strpos($result, 'niet geconfigureerd') !== false) {
        echo '<lib-message type="error">' . strip_tags($result) . ' (' . htmlspecialchars($dbTitle) . ')</lib-message>';
    } elseif (strpos($result, 'Fout:') !== false || strpos($result, 'color:red') !== false) {
        echo '<lib-message type="error">' . strip_tags($result) . '</lib-message>';
    } else {
        echo $result;
    }
    exit;
}

if (!$bJSON) {
    // Get database title for toolbar if database is selected
    $toolbarTitle = 'Database structuur';
    if ($intDatabase !== '') {
        $dbConfig = \Cma\ConfigLoader::getDatabase((int)$intDatabase);
        $dbName = $dbConfig['title'] ?? $dbConfig['name'] ?? ('Database ' . $intDatabase);
        $toolbarTitle = 'Database structuur | ' . $dbName;
    }

    cma_html_header('CMA - Database Summary', '', false);
    ToolbarHelper::writeJS();
    echo '</HEAD><BODY class="contentbody tools tool-dbsummary">';
    ToolbarHelper::start(true);
    ToolbarHelper::title($toolbarTitle);
    ToolbarHelper::separator();
    ToolbarHelper::status('Tabellen, kolommen en recordaantallen');
    if ($intDatabase !== '') {
        ToolbarHelper::startRight();
        $exportBase = 'tools_dbsummary.php?Database=' . urlencode($intDatabase) . '&export=';
        ToolbarHelper::button($exportBase . 'json', '', true, 'JSON', 'Exporteer als JSON');
        ToolbarHelper::button($exportBase . 'xml', '', true, 'XML', 'Exporteer als XML');
        ToolbarHelper::button($exportBase . 'txt', '', true, 'TXT', 'Exporteer als tekst');
    }
    ToolbarHelper::end();
    echo '<div id="c" class="tools">';
}

if ($intDatabase === '') {
    // Show database selection form (only if multiple databases)
    echo '<FORM action="tools_dbsummary.php" method=POST id=main name=main>';
    echo '<table><tr><td><B>Database:</B> ';
    echo ToolsDatabaseSelect($intDatabase, true, false, true);
    echo '</td></tr></table><br><br>';
    echo '<button class="btn btn-primary" onclick="this.disabled=true;this.form.submit()">Toon structuur</button></form>';
} else {
    // Show spinner and load data via AJAX (using lib-loader component)
    echo '<lib-loader id="loading-spinner" delay="0" size="large" text="Database structuur laden..." active></lib-loader>';
    echo '<div id="db-content"></div>';
    echo '<script>
        (function() {
            fetch("tools_dbsummary.php?ajax=1&Database=' . urlencode($intDatabase) . '")
                .then(response => response.text())
                .then(html => {
                    const loader = document.getElementById("loading-spinner");
                    if (loader && loader.hide) loader.hide();
                    document.getElementById("db-content").innerHTML = html;
                })
                .catch(error => {
                    const loader = document.getElementById("loading-spinner");
                    if (loader && loader.hide) loader.hide();
                    document.getElementById("db-content").innerHTML =
                        "<p style=\"color:red\">Fout bij laden: " + error.message + "</p>";
                });
        })();
    </script>';
}

if (!$bJSON) {
    echo '</div></body></html>';
}
