<?php
/**
 * Report Schema API
 *
 * Provides database schema introspection for the query designer.
 *
 * Endpoints:
 * - GET ?action=getTables&database=ID - Get list of tables
 * - GET ?action=getColumns&database=ID&table=NAME - Get columns for a table
 * - GET ?action=getRelationships&database=ID&table=NAME - Get relationships for a table
 * - GET ?action=getTableSchema&database=ID&table=NAME - Get complete table schema
 * - GET ?action=getDatabases - Get available databases
 */

// Start output buffering to capture any warnings/errors
ob_start();

require_once __DIR__ . '/../bootstrap.inc';

use App\Library\Response;
use App\Library\Request;
use App\Library\Session;
use App\Library\Database;
use Cma\SecurityHelper;
use Cma\SchemaHelper;
use Cma\CmaRepository;


// Set Content-Type early to prevent debug/profiler output from corrupting JSON response
Response::noCache();
header('Content-Type: application/json; charset=utf-8');

// Security check - require valid login
if (!SecurityHelper::isLoggedIn()) {
    Response::json(['success' => false, 'error' => 'Niet ingelogd'], 401);
    exit;
}

$action = Request::query('action', '');
$databaseId = Request::queryInt('database');
$tableName = Request::query('table', '');

// Clear any buffered output before sending JSON
ob_end_clean();

try {
    switch ($action) {
        case 'getDatabases':
            handleGetDatabases();
            break;

        case 'getTables':
            handleGetTables($databaseId);
            break;

        case 'getColumns':
            handleGetColumns($databaseId, $tableName);
            break;

        case 'getRelationships':
            handleGetRelationships($databaseId, $tableName);
            break;

        case 'getTableSchema':
            handleGetTableSchema($databaseId, $tableName);
            break;

        case 'searchFields':
            handleSearchFields($databaseId);
            break;

        case 'getFormMetadata':
            handleGetFormMetadata($databaseId, $tableName);
            break;

        case 'buildFieldCache':
            handleBuildFieldCache($databaseId);
            break;

        case 'getFieldCache':
            handleGetFieldCache($databaseId);
            break;

        default:
            Response::json(['success' => false, 'error' => 'Onbekende actie: ' . $action], 400);
    }
} catch (\Throwable $e) {
    Response::json([
        'success' => false,
        'error' => 'Interne fout: ' . Database::cleanErrorMessage($e->getMessage())
    ], 500);
}

/**
 * Get available databases
 */
function handleGetDatabases(): void
{
    try {
        $databases = CmaRepository::getSelectableDatabases();

        Response::json([
            'success' => true,
            'databases' => $databases
        ]);
    } catch (\Exception $e) {
        Response::json([
            'success' => false,
            'error' => 'Fout bij ophalen databases: ' . Database::cleanErrorMessage($e->getMessage())
        ], 500);
    }
}

/**
 * Get list of tables for a database
 */
function handleGetTables(int $databaseId): void
{
    if ($databaseId <= 0) {
        Response::json(['success' => false, 'error' => 'Database ID is verplicht'], 400);
        exit;
    }

    try {
        $connection = getConnectionForDatabase($databaseId);

        $filter = Request::query('filter', null);
        $tables = SchemaHelper::getTables($connection, $filter);

        Response::json([
            'success' => true,
            'database' => $databaseId,
            'tables' => $tables,
            'count' => count($tables)
        ]);
    } catch (\Exception $e) {
        Response::json([
            'success' => false,
            'error' => 'Fout bij ophalen tabellen: ' . Database::cleanErrorMessage($e->getMessage())
        ], 500);
    }
}

/**
 * Get columns for a specific table
 */
function handleGetColumns(int $databaseId, string $tableName): void
{
    if ($databaseId <= 0) {
        Response::json(['success' => false, 'error' => 'Database ID is verplicht'], 400);
        exit;
    }

    if (empty($tableName)) {
        Response::json(['success' => false, 'error' => 'Tabelnaam is verplicht'], 400);
        exit;
    }

    try {
        $connection = getConnectionNameForDatabase($databaseId);
        $columns = SchemaHelper::getColumns($connection, $tableName);

        if (empty($columns)) {
            // Return specific error when no columns found
            Response::json([
                'success' => false,
                'error' => 'Geen kolommen gevonden voor tabel: ' . $tableName
            ], 404);
            exit;
        }

        // Get form metadata for this database
        $formMetadata = getFormMetadataForDatabase($databaseId);
        $tableMetadata = $formMetadata[$tableName] ?? null;
        $importantFields = $tableMetadata['importantFields'] ?? [];

        // Add type category for each column
        foreach ($columns as &$col) {
            $col['typeCategory'] = SchemaHelper::categorizeType((int)$col['dataType']);
            // Mark as important if in the important fields list
            $colNameLower = strtolower($col['name']);
            $col['isImportant'] = in_array($colNameLower, $importantFields);
        }

        Response::json([
            'success' => true,
            'database' => $databaseId,
            'table' => $tableName,
            'columns' => $columns,
            'count' => count($columns),
            'importantFields' => $importantFields
        ]);
    } catch (\Exception $e) {
        Response::json([
            'success' => false,
            'error' => 'Fout bij ophalen kolommen: ' . Database::cleanErrorMessage($e->getMessage())
        ], 500);
    }
}

/**
 * Get relationships for a specific table
 */
function handleGetRelationships(int $databaseId, string $tableName): void
{
    if ($databaseId <= 0) {
        Response::json(['success' => false, 'error' => 'Database ID is verplicht'], 400);
        exit;
    }

    if (empty($tableName)) {
        Response::json(['success' => false, 'error' => 'Tabelnaam is verplicht'], 400);
        exit;
    }

    try {
        $connection = getConnectionForDatabase($databaseId);
        $relationships = SchemaHelper::getRelationships($connection, $tableName);

        Response::json([
            'success' => true,
            'database' => $databaseId,
            'table' => $tableName,
            'relationships' => $relationships,
            'count' => count($relationships)
        ]);
    } catch (\Exception $e) {
        Response::json([
            'success' => false,
            'error' => 'Fout bij ophalen relaties: ' . Database::cleanErrorMessage($e->getMessage())
        ], 500);
    }
}

/**
 * Get complete schema for a table
 */
function handleGetTableSchema(int $databaseId, string $tableName): void
{
    if ($databaseId <= 0) {
        Response::json(['success' => false, 'error' => 'Database ID is verplicht'], 400);
        exit;
    }

    if (empty($tableName)) {
        Response::json(['success' => false, 'error' => 'Tabelnaam is verplicht'], 400);
        exit;
    }

    // Session cache key for this table schema
    $cacheKey = 'schema_' . $databaseId . '_' . md5($tableName);
    $noCache = Request::query('nocache', '') === '1';

    // Check session cache first (skip if nocache requested)
    if (!$noCache && Session::has($cacheKey)) {
        Response::json([
            'success' => true,
            'database' => $databaseId,
            'schema' => Session::get($cacheKey),
            'cached' => true
        ]);
        return;
    }

    try {
        $connection = getConnectionForDatabase($databaseId);

        // Get columns first to check for errors
        $columns = SchemaHelper::getColumns($connection, $tableName);

        if (empty($columns)) {
            // Try to get more detail about why columns are empty
            Response::json([
                'success' => false,
                'error' => 'Geen kolommen gevonden voor tabel: ' . $tableName . '. Mogelijk bestaat de tabel niet of is er geen toegang.'
            ], 404);
            exit;
        }

        // Add type category for each column
        foreach ($columns as &$col) {
            $col['typeCategory'] = SchemaHelper::categorizeType((int)$col['dataType']);
        }

        $schema = [
            'name' => $tableName,
            'columns' => $columns,
            'primaryKeys' => SchemaHelper::getPrimaryKeys($connection, $tableName),
            'relationships' => SchemaHelper::getRelationships($connection, $tableName)
        ];

        // Store in session cache
        Session::set($cacheKey, $schema);

        Response::json([
            'success' => true,
            'database' => $databaseId,
            'schema' => $schema,
            'cached' => false
        ]);
    } catch (\Exception $e) {
        Response::json([
            'success' => false,
            'error' => 'Fout bij ophalen tabelschema: ' . Database::cleanErrorMessage($e->getMessage())
        ], 500);
    }
}

/**
 * Search for fields across all tables by name or description
 * Uses the pre-built field cache for fast searching
 */
function handleSearchFields(int $databaseId): void
{
    if ($databaseId <= 0) {
        Response::json(['success' => false, 'error' => 'Database ID is verplicht'], 400);
        exit;
    }

    $query = trim(Request::query('q', ''));
    if (strlen($query) < 2) {
        Response::json(['success' => false, 'error' => 'Zoekterm moet minimaal 2 tekens zijn'], 400);
        exit;
    }

    try {
        // Use the existing file-based field cache
        $cachePath = getFieldCachePath($databaseId);
        $allFields = [];
        $fromCache = false;

        if (file_exists($cachePath)) {
            $cacheData = json_decode(file_get_contents($cachePath), true);
            if ($cacheData && isset($cacheData['fields'])) {
                $allFields = $cacheData['fields'];
                $fromCache = true;
            }
        }

        // If no cache, build on demand (slower, but works)
        if (empty($allFields)) {
            $connection = getConnectionForDatabase($databaseId);
            $tables = SchemaHelper::getTables($connection);
            $formMetadata = getFormMetadataForDatabase($databaseId);

            foreach ($tables as $table) {
                $tableName = $table['name'];
                $columns = SchemaHelper::getColumns($connection, $tableName);

                foreach ($columns as $col) {
                    $colName = $col['name'];
                    $caption = '';
                    $description = '';
                    if (isset($formMetadata[$tableName][$colName])) {
                        $fieldMeta = $formMetadata[$tableName][$colName];
                        $caption = $fieldMeta['caption'] ?? '';
                        $description = $fieldMeta['description'] ?? '';
                    }

                    $allFields[] = [
                        'table' => $tableName,
                        'field' => $colName,
                        'caption' => $caption,
                        'description' => $description,
                        'dataType' => $col['dataType'] ?? 0,
                        'typeCategory' => SchemaHelper::categorizeType((int)($col['dataType'] ?? 0))
                    ];
                }
            }
        }

        // Filter fields (fast in-memory search)
        $queryLower = strtolower($query);
        $results = [];

        foreach ($allFields as $field) {
            $searchable = strtolower($field['field'] . ' ' . ($field['caption'] ?? '') . ' ' . ($field['description'] ?? '') . ' ' . $field['table']);
            if (strpos($searchable, $queryLower) !== false) {
                $results[] = $field;
            }
        }

        // Sort by relevance (exact match first, then starts with, then contains)
        usort($results, function($a, $b) use ($queryLower) {
            $aName = strtolower($a['field']);
            $bName = strtolower($b['field']);

            // Exact match
            $aExact = ($aName === $queryLower) ? 0 : 1;
            $bExact = ($bName === $queryLower) ? 0 : 1;
            if ($aExact !== $bExact) return $aExact - $bExact;

            // Starts with
            $aStarts = (strpos($aName, $queryLower) === 0) ? 0 : 1;
            $bStarts = (strpos($bName, $queryLower) === 0) ? 0 : 1;
            if ($aStarts !== $bStarts) return $aStarts - $bStarts;

            // Alphabetical
            return strcmp($aName, $bName);
        });

        Response::json([
            'success' => true,
            'query' => $query,
            'results' => array_slice($results, 0, 100), // Limit to 100 results
            'count' => count($results),
            'cached' => $fromCache
        ]);
    } catch (\Exception $e) {
        Response::json([
            'success' => false,
            'error' => 'Fout bij zoeken: ' . Database::cleanErrorMessage($e->getMessage())
        ], 500);
    }
}

/**
 * Get form metadata for a specific table
 */
function handleGetFormMetadata(int $databaseId, string $tableName): void
{
    if ($databaseId <= 0) {
        Response::json(['success' => false, 'error' => 'Database ID is verplicht'], 400);
        exit;
    }

    if (empty($tableName)) {
        Response::json(['success' => false, 'error' => 'Tabelnaam is verplicht'], 400);
        exit;
    }

    try {
        $formMetadata = getFormMetadataForDatabase($databaseId);
        $tableMetadata = $formMetadata[$tableName] ?? [];

        Response::json([
            'success' => true,
            'table' => $tableName,
            'fields' => $tableMetadata
        ]);
    } catch (\Exception $e) {
        Response::json([
            'success' => false,
            'error' => 'Fout bij ophalen metadata: ' . Database::cleanErrorMessage($e->getMessage())
        ], 500);
    }
}

/**
 * Get form metadata for all tables in a database
 * Reads form definitions and extracts field captions/descriptions
 * @return array [tableName => ['fields' => [...], 'importantFields' => [...], 'idField' => '', 'detailField' => '']]
 */
function getFormMetadataForDatabase(int $databaseId): array
{
    static $cache = [];

    if (isset($cache[$databaseId])) {
        return $cache[$databaseId];
    }

    $metadata = [];

    // Get database config to find associated forms
    $dbConfig = \Cma\ConfigLoader::getDatabase($databaseId);
    $dbName = $dbConfig['name'] ?? '';

    // Scan form definitions directory - check both cma and site directories
    $formsDirs = [
        __DIR__ . '/../../assets/forms/definitions',  // site/assets/forms/definitions
        __DIR__ . '/../assets/forms/definitions',     // site/cma/assets/forms/definitions
        __DIR__ . '/../../assets/forms'               // site/assets/forms (main forms without definitions subdir)
    ];

    foreach ($formsDirs as $formsDir) {
        if (!is_dir($formsDir)) continue;

        $files = glob($formsDir . '/*.json');
        foreach ($files as $file) {
            $formDef = json_decode(file_get_contents($file), true);
            if (!$formDef) continue;

            // Check if form uses this database
            $formDb = $formDef['database'] ?? $formDef['connection'] ?? '';
            // Match by database ID or name
            if ($formDb != $databaseId && $formDb !== $dbName) {
                continue;
            }

            // Get table name from form
            $tableName = $formDef['table'] ?? $formDef['tableName'] ?? '';
            if (empty($tableName)) continue;

            // Initialize table metadata if not exists
            if (!isset($metadata[$tableName])) {
                $metadata[$tableName] = [
                    'fields' => [],
                    'importantFields' => [],
                    'idField' => '',
                    'detailField' => ''
                ];
            }

            // Store table-level properties
            $idField = $formDef['idField'] ?? 'ID';
            $detailField = $formDef['detailField'] ?? '';
            $quickSearchFields = $formDef['quickSearchFields'] ?? '';

            $metadata[$tableName]['idField'] = $idField;
            $metadata[$tableName]['detailField'] = $detailField;

            // Build important fields list
            $importantFields = [];

            // Add ID field
            if (!empty($idField)) {
                $importantFields[] = strtolower($idField);
            }

            // Add detail field (main display field)
            if (!empty($detailField)) {
                $importantFields[] = strtolower($detailField);
            }

            // Add quick search fields
            if (!empty($quickSearchFields)) {
                $searchFields = array_map('trim', explode(',', $quickSearchFields));
                foreach ($searchFields as $sf) {
                    if (!empty($sf)) {
                        $importantFields[] = strtolower($sf);
                    }
                }
            }

            // Extract field metadata
            $fields = $formDef['fields'] ?? [];
            foreach ($fields as $field) {
                $fieldName = $field['name'] ?? $field['field'] ?? '';
                if (empty($fieldName)) continue;

                $metadata[$tableName]['fields'][$fieldName] = [
                    'caption' => $field['caption'] ?? $field['label'] ?? '',
                    'description' => $field['description'] ?? $field['tooltip'] ?? '',
                    'required' => $field['required'] ?? false,
                    'type' => $field['type'] ?? ''
                ];

                // Also add required fields to important list
                if (!empty($field['required'])) {
                    $importantFields[] = strtolower($fieldName);
                }
            }

            // Deduplicate and store important fields
            $metadata[$tableName]['importantFields'] = array_values(array_unique($importantFields));
        }
    }

    $cache[$databaseId] = $metadata;
    return $metadata;
}

/**
 * Get database connection for a database ID
 * Uses connection names when available for proper pooling (avoids MS Access ODBC issues)
 */
/**
 * Get connection name (string) for a database ID.
 * Returns the name so SchemaHelper can use it for both PDO and native ODBC access.
 */
function getConnectionNameForDatabase(int $databaseId): string
{
    $dbConfig = \Cma\ConfigLoader::getDatabase($databaseId);
    $connName = $dbConfig['name'] ?? null;
    if ($connName) {
        return $connName;
    }
    return CmaRepository::getResolvedConnectionString($databaseId);
}

function getConnectionForDatabase(int $databaseId): \PDO
{
    return Database::getConnection(getConnectionNameForDatabase($databaseId));
}

/**
 * Get the cache file path for field descriptions
 */
function getFieldCachePath(int $databaseId): string
{
    $cacheDir = dirname(__DIR__, 2) . '/cache/cma';
    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0755, true);
    }
    return $cacheDir . '/field_descriptions_db' . $databaseId . '.cache';
}

/**
 * Build and cache field descriptions for all tables in a database
 * This is called in the background when the report designer loads
 */
function handleBuildFieldCache(int $databaseId): void
{
    if ($databaseId <= 0) {
        Response::json(['success' => false, 'error' => 'Database ID is verplicht'], 400);
        exit;
    }

    try {
        $connection = getConnectionForDatabase($databaseId);
        $tables = SchemaHelper::getTables($connection);
        $formMetadata = getFormMetadataForDatabase($databaseId);

        $fieldData = [];
        $totalFields = 0;

        foreach ($tables as $table) {
            $tableName = $table['name'];
            $columns = SchemaHelper::getColumns($connection, $tableName);

            foreach ($columns as $col) {
                $colName = $col['name'];

                // Get caption from form metadata if available
                $caption = '';
                $description = '';
                if (isset($formMetadata[$tableName][$colName])) {
                    $fieldMeta = $formMetadata[$tableName][$colName];
                    $caption = $fieldMeta['caption'] ?? '';
                    $description = $fieldMeta['description'] ?? '';
                }

                $fieldData[] = [
                    'table' => $tableName,
                    'field' => $colName,
                    'caption' => $caption,
                    'description' => $description,
                    'dataType' => $col['dataType'] ?? 0,
                    'typeCategory' => SchemaHelper::categorizeType((int)($col['dataType'] ?? 0))
                ];
                $totalFields++;
            }
        }

        // Save to cache file
        $cachePath = getFieldCachePath($databaseId);
        $cacheData = [
            'databaseId' => $databaseId,
            'generatedAt' => date('Y-m-d H:i:s'),
            'tableCount' => count($tables),
            'fieldCount' => $totalFields,
            'fields' => $fieldData
        ];

        file_put_contents($cachePath, json_encode($cacheData, JSON_UNESCAPED_UNICODE));

        Response::json([
            'success' => true,
            'cached' => true,
            'tableCount' => count($tables),
            'fieldCount' => $totalFields
        ]);
    } catch (\Exception $e) {
        Response::json([
            'success' => false,
            'error' => 'Fout bij bouwen cache: ' . Database::cleanErrorMessage($e->getMessage())
        ], 500);
    }
}

/**
 * Get cached field descriptions, or return empty if not cached
 */
function handleGetFieldCache(int $databaseId): void
{
    if ($databaseId <= 0) {
        Response::json(['success' => false, 'error' => 'Database ID is verplicht'], 400);
        exit;
    }

    $cachePath = getFieldCachePath($databaseId);

    if (!file_exists($cachePath)) {
        Response::json([
            'success' => true,
            'cached' => false,
            'fields' => []
        ]);
        return;
    }

    $cacheData = json_decode(file_get_contents($cachePath), true);

    if (!$cacheData) {
        Response::json([
            'success' => true,
            'cached' => false,
            'fields' => []
        ]);
        return;
    }

    Response::json([
        'success' => true,
        'cached' => true,
        'generatedAt' => $cacheData['generatedAt'] ?? null,
        'tableCount' => $cacheData['tableCount'] ?? 0,
        'fieldCount' => $cacheData['fieldCount'] ?? 0,
        'fields' => $cacheData['fields'] ?? []
    ]);
}
