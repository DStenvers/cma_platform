<?php
/**
 * Report Query API
 *
 * Executes queries and returns preview data for the query designer.
 *
 * Endpoints:
 * - POST ?action=preview - Execute query with limit and return preview data
 * - POST ?action=getSql - Get generated SQL without executing
 * - POST ?action=getOperators - Get available filter operators for a type
 * - POST ?action=executeRawSql - Execute a raw SQL SELECT query (SQL mode)
 * - POST ?action=parseSql - Parse SQL and extract tables, fields, sorting
 */

// Set JSON header immediately
header('Content-Type: application/json; charset=utf-8');

// Disable output buffering for this script
while (ob_get_level() > 0) {
    ob_end_clean();
}

// DEBUG: Test if script is even executing
// Note: Pre-bootstrap debug checks below use $_GET directly because
// the Request class is not yet available before bootstrap loads.
if (isset($_GET['test'])) {
    echo json_encode(['success' => true, 'message' => 'Script is executing']);
    exit;
}

// DEBUG step 1
if (isset($_GET['step']) && $_GET['step'] == '1') {
    echo json_encode(['step' => 1, 'message' => 'Before bootstrap']);
    exit;
}

require_once __DIR__ . '/../bootstrap.inc';

// DEBUG step 2
if (isset($_GET['step']) && $_GET['step'] == '2') {
    echo json_encode(['step' => 2, 'message' => 'After bootstrap']);
    exit;
}

// DEBUG: Immediate test after bootstrap for POST requests
if (isset($_GET['posttest'])) {
    while (ob_get_level() > 0) ob_end_clean();
    header('Content-Type: application/json');
    $raw = file_get_contents('php://input');
    $out = json_encode(['posttest' => true, 'inputLen' => strlen($raw), 'input' => substr($raw, 0, 200)]);
    header('Content-Length: ' . strlen($out));
    echo $out;
    exit;
}

use App\Library\Arr;
use App\Library\Response;
use App\Library\Request;
use App\Library\Database;
use Cma\SecurityHelper;
use Cma\QueryBuilder;
use Cma\CmaRepository;
use Cma\ConfigLoader;
use Cma\SqlParser;

// Helper to send JSON response
function sendJson($data, $status = 200) {
    // Clear any buffered output
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');

    // Ensure data is UTF-8 encoded
    $data = convertToUtf8($data);

    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    header('Content-Length: ' . strlen($json));

    echo $json;
    flush();
    exit;
}

// Convert data to UTF-8 recursively
function convertToUtf8($data) {
    if (is_string($data)) {
        // Detect encoding and convert to UTF-8
        $encoding = mb_detect_encoding($data, ['UTF-8', 'Windows-1252', 'ISO-8859-1', 'ASCII'], true);
        if ($encoding && $encoding !== 'UTF-8') {
            return mb_convert_encoding($data, 'UTF-8', $encoding);
        }
        // If detection fails, assume Windows-1252 (common for MS Access)
        if (!$encoding || !mb_check_encoding($data, 'UTF-8')) {
            return mb_convert_encoding($data, 'UTF-8', 'Windows-1252');
        }
        return $data;
    }
    if (Arr::isArray($data)) {
        return array_map('convertToUtf8', $data);
    }
    return $data;
}

/**
 * Sanitize an alias - removes spaces and invalid SQL identifier characters
 * Mirrors CMA.sanitizeAlias from shared-icons.js for consistency
 * @param string $alias The input alias
 * @return string The sanitized alias
 */
function sanitizeAlias(string $alias): string {
    if (empty($alias)) return '';
    // Replace spaces with underscores and keep only valid SQL identifier characters
    $alias = preg_replace('/\s+/', '_', $alias);
    $alias = preg_replace('/[^a-zA-Z0-9_]/', '', $alias);
    return $alias;
}

// DEBUG step 3
if (Request::hasQuery('step') && Request::query('step') == '3') {
    sendJson(['step' => 3, 'message' => 'sendJson works']);
}

// DEBUG: Log all requests (use debugRaw to avoid conflict with handlePreview debug)
if (Request::hasQuery('debugRaw')) {
    $rawInput = file_get_contents('php://input');
    sendJson([
        'debug' => true,
        'method' => Request::server('REQUEST_METHOD', 'unknown'),
        'contentType' => Request::server('CONTENT_TYPE', 'none'),
        'contentLength' => Request::server('CONTENT_LENGTH', 0),
        'rawInputLength' => strlen($rawInput),
        'rawInputPreview' => substr($rawInput, 0, 500),
        'action' => Request::query('action', Request::post('action', 'none'))
    ]);
}

// Catch all PHP errors and convert to JSON response
set_error_handler(function($severity, $message, $file, $line) {
    sendJson(['success' => false, 'error' => "PHP Error: $message in $file:$line"], 500);
});

// Catch fatal errors
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        // Can't use sendJson here as it might have caused the error
        echo json_encode([
            'success' => false,
            'error' => 'Fatal error: ' . $error['message']
        ]);
    }
});

try {
    // DEBUG: trace each step
    if (Request::hasQuery('trace')) {
        $traceStep = Request::queryInt('trace');
        if ($traceStep === 1) { sendJson(['trace' => 1, 'msg' => 'Before security check']); }
    }

    // Security check - require valid login
    if (!SecurityHelper::isLoggedIn()) {
        sendJson(['success' => false, 'error' => 'Niet ingelogd'], 401);
    }

    if (Request::hasQuery('trace') && Request::queryInt('trace') === 2) {
        sendJson(['trace' => 2, 'msg' => 'After security, before noCache']);
    }

    Response::noCache();

    if (Request::hasQuery('trace') && Request::queryInt('trace') === 3) {
        sendJson(['trace' => 3, 'msg' => 'After noCache']);
    }

    $action = Request::post('action', Request::query('action', ''));

    if (Request::hasQuery('trace') && Request::queryInt('trace') === 4) {
        sendJson(['trace' => 4, 'msg' => 'Action resolved', 'action' => $action]);
    }

    switch ($action) {
        case 'preview':
            if (Request::hasQuery('trace') && Request::queryInt('trace') === 5) {
                sendJson(['trace' => 5, 'msg' => 'Entering handlePreview']);
            }
            handlePreview();
            // Fallback if handlePreview didn't exit
            sendJson(['success' => false, 'error' => 'handlePreview returned without response']);
            break;

        case 'getSql':
            handleGetSql();
            break;

        case 'getOperators':
            handleGetOperators();
            break;

        case 'getWhere':
            handleGetWhere();
            break;

        case 'executeRawSql':
            handleExecuteRawSql();
            break;

        case 'parseSql':
            handleParseSql();
            break;

        default:
            sendJson(['success' => false, 'error' => 'Onbekende actie: ' . $action], 400);
    }
} catch (\Exception $e) {
    sendJson(['success' => false, 'error' => 'Server error: ' . Database::cleanErrorMessage($e->getMessage())], 500);
}

// Final fallback - should never reach here
sendJson(['success' => false, 'error' => 'Onverwacht einde van script'], 500);

/**
 * Execute query with limit and return preview data
 */
function handlePreview(): void
{
    $trace = Request::queryInt('trace');

    if ($trace === 10) { sendJson(['trace' => 10, 'msg' => 'Inside handlePreview']); return; }

    $definition = getDefinitionFromRequest();

    if ($trace === 11) { sendJson(['trace' => 11, 'msg' => 'After getDefinition', 'hasDefinition' => $definition !== null]); return; }

    if ($definition === null) {
        sendJson(['success' => false, 'error' => 'Rapport definitie is verplicht'], 400);
        return;
    }

    if ($trace === 12) { sendJson(['trace' => 12, 'msg' => 'Definition valid', 'tables' => count($definition['tables'] ?? [])]); return; }

    // Get database ID
    $databaseId = $definition['database'] ?? 0;
    if (!is_numeric($databaseId) || (int)$databaseId <= 0) {
        sendJson(['success' => false, 'error' => 'Database ID is verplicht'], 400);
        return;
    }

    if ($trace === 13) { sendJson(['trace' => 13, 'msg' => 'Database ID valid', 'dbId' => $databaseId]); return; }

    // Get limit (default 100)
    $limit = Request::postInt('limit', 100);
    if ($limit <= 0) {
        $limit = 100;
    }
    if ($limit > 1000) {
        $limit = 1000; // Max limit for preview
    }

    // Get parameters
    $parameters = [];
    if (isset($definition['parameters'])) {
        $parameters = getParameterValues($definition['parameters']);
    }

    if ($trace === 14) { sendJson(['trace' => 14, 'msg' => 'Before try block']); return; }

    try {
        // Get connection using connection name for proper pooling
        $dbConfig = ConfigLoader::getDatabase((int)$databaseId);

        if ($trace === 15) { sendJson(['trace' => 15, 'msg' => 'Got dbConfig', 'config' => $dbConfig]); return; }

        $connName = $dbConfig['name'] ?? null;

        if ($connName) {
            $conn = Database::getConnection($connName);
        } else {
            $resolvedConnStr = CmaRepository::getResolvedConnectionString((int)$databaseId);
            $conn = Database::getConnection($resolvedConnStr);
        }

        if ($trace === 16) { sendJson(['trace' => 16, 'msg' => 'Got connection', 'isNull' => $conn === null]); return; }

        if ($conn === null) {
            sendJson([
                'success' => false,
                'error' => 'Kan geen verbinding maken met database'
            ], 500);
            return;
        }

        if ($trace === 17) { sendJson(['trace' => 17, 'msg' => 'Before QueryBuilder']); return; }

        // Build and execute query
        $builder = new QueryBuilder($definition, $conn);
        $builder->setParameters($parameters);

        // Comprehensive debug mode
        $debug = Request::hasQuery('debug');

        // Step 1: Generate SQL
        $sql = $builder->toSql();
        $builderErrors = $builder->getErrors();

        // Step 2: Execute query
        $result = $builder->executePreview($limit);

        // Step 3: Build response
        // Include builder errors (e.g., missing tables in JOIN ON clauses)
        $errorMessage = $result['error'] ?? null;
        if (!empty($builderErrors)) {
            $builderErrorMsg = implode(' ', $builderErrors);
            $errorMessage = $errorMessage ? "$errorMessage. $builderErrorMsg" : $builderErrorMsg;
        }

        $response = [
            'success' => $result['success'] ?? false,
            'data' => $result['data'] ?? [],
            'columns' => $result['columns'] ?? [],
            'rowCount' => $result['rowCount'] ?? 0,
            'sql' => $result['sql'] ?? $sql,
            'error' => $errorMessage,
            'warnings' => $builderErrors,
            'limit' => $limit
        ];

        // Add debug info if requested
        if ($debug) {
            $response['_debug'] = [
                'definitionTables' => count($definition['tables'] ?? []),
                'definitionFields' => count($definition['fields'] ?? []),
                'databaseId' => $databaseId,
                'connectionType' => $connName ? 'named' : 'resolved',
                'connectionName' => $connName ?? $resolvedConnStr ?? 'unknown',
                'sqlLength' => strlen($sql),
                'builderErrors' => $builderErrors,
                'resultKeys' => array_keys($result),
                'dataRowCount' => count($result['data'] ?? []),
                'dataFirstRow' => !empty($result['data']) ? array_slice($result['data'][0] ?? [], 0, 5, true) : null,
                'memoryUsage' => memory_get_usage(true),
                'peakMemory' => memory_get_peak_usage(true)
            ];
        }

        // Step 4: Convert to UTF-8 and send
        sendJson($response);
        return;

        sendJson([
            'success' => $result['success'],
            'data' => $result['data'] ?? [],
            'columns' => $result['columns'] ?? [],
            'rowCount' => $result['rowCount'] ?? 0,
            'sql' => $result['sql'] ?? '',
            'error' => $result['error'] ?? null,
            'limit' => $limit
        ]);
        return;

    } catch (\Exception $e) {
        sendJson([
            'success' => false,
            'error' => 'Fout bij uitvoeren query: ' . Database::cleanErrorMessage($e->getMessage())
        ], 500);
        return;
    }
}

/**
 * Get generated SQL without executing
 */
function handleGetSql(): void
{
    // Get report definition from POST body
    $definition = getDefinitionFromRequest();
    if ($definition === null) {
        sendJson(['success' => false, 'error' => 'Rapport definitie is verplicht'], 400);
        exit;
    }

    // Get parameters
    $parameters = [];
    if (isset($definition['parameters'])) {
        $parameters = getParameterValues($definition['parameters']);
    }

    try {
        $builder = new QueryBuilder($definition);
        $builder->setParameters($parameters);

        $sql = $builder->toSql();
        $errors = $builder->getErrors();

        sendJson([
            'success' => empty($errors),
            'sql' => $sql,
            'errors' => $errors
        ]);

    } catch (\Exception $e) {
        sendJson([
            'success' => false,
            'error' => 'Fout bij genereren SQL: ' . Database::cleanErrorMessage($e->getMessage())
        ], 500);
    }
}

/**
 * Get available filter operators for a type category
 */
function handleGetOperators(): void
{
    // Try JSON body first (consistent with other handlers)
    $typeCategory = null;
    $rawInput = file_get_contents('php://input');
    if (!empty($rawInput)) {
        $json = json_decode($rawInput, true);
        if (Arr::isArray($json) && isset($json['type'])) {
            $typeCategory = $json['type'];
        }
    }

    // Fallback to POST/query parameters
    if ($typeCategory === null) {
        $typeCategory = Request::post('type', Request::query('type', 'text'));
    }

    $operators = QueryBuilder::getOperatorsForType($typeCategory);

    sendJson([
        'success' => true,
        'type' => $typeCategory,
        'operators' => $operators
    ]);
}

/**
 * Get WHERE clause preview from conditions
 * Returns the actual SQL that will be generated, eliminating client/server discrepancies
 */
function handleGetWhere(): void
{
    $definition = getDefinitionFromRequest();
    if ($definition === null) {
        sendJson(['success' => false, 'error' => 'Definitie is verplicht'], 400);
        exit;
    }

    // Get parameters
    $parameters = [];
    if (isset($definition['parameters'])) {
        $parameters = getParameterValues($definition['parameters']);
    }

    try {
        $builder = new QueryBuilder($definition);
        $builder->setParameters($parameters);

        $whereClause = $builder->buildWhere();

        sendJson([
            'success' => true,
            'where' => $whereClause
        ]);

    } catch (\Exception $e) {
        sendJson([
            'success' => false,
            'error' => 'Fout bij genereren WHERE clause: ' . Database::cleanErrorMessage($e->getMessage())
        ], 500);
    }
}

/**
 * Get report definition from request body
 */
function getDefinitionFromRequest(): ?array
{
    // Try JSON body first
    $rawInput = file_get_contents('php://input');
    if (!empty($rawInput)) {
        $json = json_decode($rawInput, true);
        if (Arr::isArray($json)) {
            // Definition might be the whole body or in a 'definition' key
            if (isset($json['definition']) && Arr::isArray($json['definition'])) {
                return $json['definition'];
            }
            return $json;
        }
    }

    // Try POST parameter
    $definitionJson = Request::post('definition', '');
    if (!empty($definitionJson)) {
        $definition = json_decode($definitionJson, true);
        if (Arr::isArray($definition)) {
            return $definition;
        }
    }

    return null;
}

/**
 * Extract parameter values from runtime input or default values
 * @param array $parameterDefinitions Parameter definitions from report
 * @param array $runtimeValues Runtime values from request (optional)
 */
function getParameterValues(array $parameterDefinitions, array $runtimeValues = []): array
{
    $values = [];

    // If runtime values not provided, try to get from request
    if (empty($runtimeValues)) {
        // Try JSON body first
        $rawInput = file_get_contents('php://input');
        if (!empty($rawInput)) {
            $json = json_decode($rawInput, true);
            if (Arr::isArray($json) && isset($json['parameterValues'])) {
                $runtimeValues = $json['parameterValues'];
            }
        }
        // Fallback to POST parameter
        if (empty($runtimeValues)) {
            $runtimeJson = Request::post('parameterValues', '');
            if (!empty($runtimeJson)) {
                $runtimeValues = json_decode($runtimeJson, true) ?? [];
            }
        }
    }

    foreach ($parameterDefinitions as $param) {
        $name = $param['name'] ?? '';
        if (empty($name)) {
            continue;
        }

        // Remove @ prefix if present for lookup
        $lookupName = ltrim($name, '@');

        // Use runtime value if provided, otherwise use default
        if (isset($runtimeValues[$lookupName])) {
            $values[$lookupName] = $runtimeValues[$lookupName];
        } elseif (isset($runtimeValues[$name])) {
            $values[$lookupName] = $runtimeValues[$name];
        } elseif (isset($param['default'])) {
            $values[$lookupName] = resolveDefaultValue($param['default']);
        } else {
            $values[$lookupName] = '';
        }
    }

    return $values;
}

/**
 * Resolve default value placeholders
 */
function resolveDefaultValue(string $default): string
{
    // Handle date placeholders
    switch (strtolower($default)) {
        case 'vandaag':
        case 'today':
            return date('Y-m-d');

        case 'vandaag-7':
            return date('Y-m-d', strtotime('-7 days'));

        case 'vandaag-30':
            return date('Y-m-d', strtotime('-30 days'));

        case 'vandaag-90':
            return date('Y-m-d', strtotime('-90 days'));

        case 'vandaag+7':
            return date('Y-m-d', strtotime('+7 days'));

        case 'vandaag+30':
            return date('Y-m-d', strtotime('+30 days'));

        case 'eerstevandemaand':
        case 'firstofmonth':
            return date('Y-m-01');

        case 'laatstevandemaand':
        case 'lastofmonth':
            return date('Y-m-t');

        default:
            return $default;
    }
}

/**
 * Execute raw SQL query (SQL mode)
 *
 * This handler allows advanced users to run custom SELECT queries.
 * For security, only SELECT statements are allowed and dangerous keywords are blocked.
 */
function handleExecuteRawSql(): void
{
    // Get request body
    $rawInput = file_get_contents('php://input');
    $json = json_decode($rawInput, true);

    $sql = $json['sql'] ?? '';
    $databaseId = $json['database'] ?? 0;
    $limit = $json['limit'] ?? 100;

    // Validate SQL is provided
    if (empty(trim($sql))) {
        sendJson(['success' => false, 'error' => 'SQL-query is verplicht'], 400);
        return;
    }

    // Validate database ID
    if (!is_numeric($databaseId) || (int)$databaseId <= 0) {
        sendJson(['success' => false, 'error' => 'Database ID is verplicht'], 400);
        return;
    }

    // Validate limit
    $limit = min(max((int)$limit, 1), 1000);

    // Security: Only allow SELECT statements
    $trimmedSql = strtoupper(trim($sql));
    if (!str_starts_with($trimmedSql, 'SELECT')) {
        sendJson(['success' => false, 'error' => 'Alleen SELECT-queries zijn toegestaan'], 400);
        return;
    }

    // Block dangerous keywords
    $blocked = ['INSERT', 'UPDATE', 'DELETE', 'DROP', 'TRUNCATE', 'ALTER', 'CREATE', 'EXEC', 'EXECUTE', 'GRANT', 'REVOKE'];
    foreach ($blocked as $keyword) {
        if (preg_match('/\b' . $keyword . '\b/i', $sql)) {
            sendJson(['success' => false, 'error' => "Query bevat niet-toegestaan keyword: $keyword"], 400);
            return;
        }
    }

    // Add TOP limit if not present (MS Access syntax)
    $processedSql = $sql;
    if (!preg_match('/\bTOP\s+\d+\b/i', $sql)) {
        $processedSql = preg_replace('/^SELECT\s+/i', "SELECT TOP $limit ", $sql);
    }

    try {
        // Get database connection
        $dbConfig = ConfigLoader::getDatabase((int)$databaseId);
        $connName = $dbConfig['name'] ?? null;

        if ($connName) {
            $conn = Database::getConnection($connName);
        } else {
            $resolvedConnStr = CmaRepository::getResolvedConnectionString((int)$databaseId);
            $conn = Database::getConnection($resolvedConnStr);
        }

        if ($conn === null) {
            sendJson(['success' => false, 'error' => 'Kan geen verbinding maken met database'], 500);
            return;
        }

        // Execute query
        $stmt = $conn->query($processedSql);
        $data = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Get column names
        $columns = [];
        if (!empty($data)) {
            $columns = array_keys($data[0]);
        }

        // Convert to UTF-8
        $data = convertToUtf8($data);

        sendJson([
            'success' => true,
            'data' => $data,
            'columns' => $columns,
            'rowCount' => count($data),
            'sql' => $processedSql,
            'limit' => $limit,
            'error' => null
        ]);

    } catch (\Exception $e) {
        sendJson([
            'success' => false,
            'error' => 'Fout bij uitvoeren query: ' . Database::cleanErrorMessage($e->getMessage())
        ], 500);
    }
}

/**
 * Parse SQL query and extract structure
 *
 * Attempts to parse a SELECT statement and extract:
 * - Tables (from FROM and JOIN clauses)
 * - Fields (from SELECT clause)
 * - Sorting (from ORDER BY clause)
 *
 * Returns success=true with parsed structure, or success=false with error message
 * if the SQL cannot be reliably parsed.
 */
function handleParseSql(): void
{
    // Get request body
    $rawInput = file_get_contents('php://input');
    $json = json_decode($rawInput, true);

    $sql = $json['sql'] ?? '';
    $databaseId = $json['database'] ?? 0;

    // Validate SQL is provided
    if (empty(trim($sql))) {
        sendJson(['success' => false, 'error' => 'SQL-query is verplicht'], 400);
        return;
    }

    // Basic validation - must be SELECT
    $trimmedSql = strtoupper(trim($sql));
    if (!str_starts_with($trimmedSql, 'SELECT')) {
        sendJson(['success' => false, 'error' => 'Alleen SELECT-queries kunnen worden geparsed'], 400);
        return;
    }

    try {
        $parsed = SqlParser::parse($sql);

        if ($parsed === null) {
            sendJson([
                'success' => false,
                'error' => 'De SQL-query is te complex om automatisch te parsen. Gebruik de visuele editor of bewerk de SQL handmatig.'
            ]);
            return;
        }

        sendJson([
            'success' => true,
            'parsed' => $parsed
        ]);

    } catch (\Exception $e) {
        sendJson([
            'success' => false,
            'error' => 'Fout bij parsen van SQL: ' . Database::cleanErrorMessage($e->getMessage())
        ]);
    }
}

// SQL parser functions have been moved to Cma\SqlParser class.
// See classes/SqlParser.php for the shared implementation.
// REMOVED: parseSqlQuery, extractTables, extractJoins, extractFields,
//          parseFieldExpression, extractSorting, extractGrouping, filterToTableNames


