<?php
/**
 * Form API - AJAX endpoint for JSON form operations
 *
 * Provides JSON responses for:
 * - tree: Get tree/table HTML for list panel
 * - list: Get list data as JSON
 * - record: Get record data for form population
 * - save: Save form data
 * - delete: Delete a record
 * - combo: Get single combo box options
 * - combos: Get multiple combo box options (batch)
 * - checklist: Get checklist options
 * - subform: Get subform data
 * - renderer: Get custom renderer HTML
 *
 * All responses are JSON with 'success' boolean and either 'error' or data fields.
 *
 * Parameters:
 * - form: Named form identifier (e.g., 'users', 'opleidingen')
 * - action: The action to perform (required)
 * - id: Record ID (for record, delete operations). Also accepts uppercase 'ID' for legacy support.
 *
 * Cache behavior:
 * - combo, combos, checklist: Cached for 5 minutes (public)
 * - tree, list: Cached for 30 seconds (private, varies by search)
 * - record, save, delete: No cache
 */

// Start timing BEFORE any includes
$_apiStartTime = microtime(true);
// REQUEST_TIME_FLOAT is set by PHP when the request is first received
// Use FQCN because this runs before `use` aliases are resolved when loaded via IIS URL Rewrite
$_requestTimeFloat = \App\Library\Request::server('REQUEST_TIME_FLOAT', $_apiStartTime);
$_apiTimings = [
    '_php_startup' => round(($_apiStartTime - $_requestTimeFloat) * 1000, 1),  // Time from request receipt to first PHP line
    '_bootstrap' => $GLOBALS['_bootstrap_timing'] ?? null,  // Detailed bootstrap timing
    '_conn_pool_debug' => &$_conn_pool_debug,  // Connection pool debug (reference so it updates)
];

use App\Library\Application;
use App\Library\Arr;
use App\Library\Cookie;
use App\Library\File;
use App\Library\Image;
use App\Library\Request;
use App\Library\Response;
use App\Library\ResponsiveImage;
use App\Library\Server;
use App\Library\Session;
use App\Library\Str;
use Cma\FormDataProvider;
use Cma\JsonFormLoader;
use Cma\JsonFormRenderer;
use Cma\SecurityHelper;
use Cma\Services\Logger;

require_once __DIR__ . '/bootstrap.inc';
require_once __DIR__ . '/classes/Services/Logger.php';
require_once __DIR__ . '/classes/Services/PerformanceLogger.php';

// Prevent Debug::write() from outputting <script>console.log()</script> into JSON responses
\App\Library\Debug::setJsonMode(true);
$_apiTimings['include'] = round((microtime(true) - $_apiStartTime) * 1000, 1);
$_apiTimings['_connrep_debug'] = $_connrep_debug ?? null;  // Capture after all.inc loads

// Check if we're in development mode
$_isDevMode = (bool) Application::get('development', '');

// Initialize performance logging
use Cma\Services\PerformanceLogger;
PerformanceLogger::init();
PerformanceLogger::mark('api_start');

// Debug tracking - DISABLED (set to null to disable, restore to enable)
// To enable: $_debugPath = $_isDevMode ? ['START'] : null;
$_debugPath = null;

// Helper to send debug path as header - DISABLED
function sendDebugHeader($extraInfo = null) {
    return; // Disabled - remove this line to re-enable
    global $_debugPath, $_isDevMode;
    if (!$_isDevMode) return;

    header_remove('X-Debug-Path');
    $path = implode(' > ', $_debugPath);
    if ($extraInfo !== null) {
        $path .= ' [' . $extraInfo . ']';
    }
    // Remove newlines and carriage returns - headers cannot contain them
    $path = str_replace(["\r", "\n"], ' ', $path);
    header('X-Debug-Path: ' . $path);
}

// Helper to add debug info
function addDebug($info) {
    global $_debugPath, $_isDevMode;
    if ($_isDevMode && $_debugPath !== null) {
        $_debugPath[] = $info;
    }
}

// Legacy wrapper for backward compatibility - use Str::toUtf8() directly in new code
function sanitizeUtf8($data) {
    return Str::toUtf8($data);
}

// Find fields with bad UTF-8 data (returns field name => raw hex of bad bytes)
function findBadUtf8Fields($data, $prefix = '') {
    $badFields = [];
    if (!Arr::isArray($data)) {
        return $badFields;
    }
    foreach ($data as $key => $value) {
        $fieldName = $prefix ? "$prefix.$key" : $key;
        if (Arr::isArray($value)) {
            $badFields = array_merge($badFields, findBadUtf8Fields($value, $fieldName));
        } elseif (is_string($value)) {
            // Try to encode just this field
            if (json_encode($value) === false) {
                // Find the bad bytes - show first 100 chars with hex for non-UTF8
                $sample = substr($value, 0, 100);
                $hexSample = '';
                for ($i = 0; $i < strlen($sample); $i++) {
                    $ord = ord($sample[$i]);
                    if ($ord < 32 || $ord > 126) {
                        $hexSample .= '[' . dechex($ord) . ']';
                    } else {
                        $hexSample .= $sample[$i];
                    }
                }
                $badFields[$fieldName] = $hexSample;
            }
        }
    }
    return $badFields;
}

// Initial debug header
sendDebugHeader();

// Security headers for API responses
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// Determine action first for cache control
// Check both GET and POST (save/delete send via POST)
$action = Request::query('action', '');
if (empty($action)) {
    $action = Request::post('action', '');
}

// Validate action against whitelist - prevents log pollution and potential enumeration
$validActions = ['init', 'tree', 'record', 'get_form', 'save', 'delete', 'list', 'subform', 'subforms', 'combo', 'combos', 'checklist', 'renderer', 'columns', 'saveColumns', 'getRow', 'logJsError', 'tableData', 'debug_access'];
if (!empty($action) && !in_array($action, $validActions, true)) {
    // Invalid action - respond with error and exit early
    outputJson(['success' => false, 'error' => 'Ongeldige actie']);
    exit;
}

addDebug("action=$action");
sendDebugHeader();

// Cache control based on action type
switch ($action) {
    // Static lookup data - cache for 30 minutes (public, shared across users)
    // Lookup data rarely changes and can be safely cached longer
    case 'combo':
    case 'combos':
    case 'checklist':
        $cacheMaxAge = 1800; // 30 minutes
        header('Cache-Control: public, max-age=' . $cacheMaxAge . ', stale-while-revalidate=300');
        header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $cacheMaxAge) . ' GMT');
        header('Vary: Accept-Encoding');
        break;

    // Column definitions - cache for 1 hour (public, rarely change)
    case 'columns':
        $cacheMaxAge = 3600; // 1 hour
        header('Cache-Control: public, max-age=' . $cacheMaxAge . ', stale-while-revalidate=600');
        header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $cacheMaxAge) . ' GMT');
        header('Vary: Accept-Encoding');
        break;

    // List/tree data - short cache, private (user-specific due to security)
    // But don't cache when search/filters are applied (dynamic results)
    case 'tree':
    case 'list':
        // Always no-cache for tree/list after save operations
        // The _t timestamp parameter indicates a force-refresh request
        $forceRefresh = Request::query('_t', '') !== '';
        $hasSearch = Request::query('search', '') !== '';
        $hasFilters = Request::query('filters', '') !== '';
        if ($forceRefresh || $hasSearch || $hasFilters) {
            // No cache for force-refresh, search, or filter results
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
        } else {
            // Allow 60 seconds cache for unfiltered list/tree views
            header('Cache-Control: private, max-age=60, stale-while-revalidate=30');
        }
        header('Vary: Accept-Encoding');
        break;

    // Record data - no cache (always fresh)
    case 'record':
    case 'get_form':  // Alias for record
    case 'subform':
    case 'renderer':
    case 'init':  // Init includes list data which can change
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        break;

    // Write operations - never cache
    case 'save':
    case 'delete':
    case 'logJsError':
    default:
        Response::noCache();
        break;
}

// Helper to build response with optional debug info
// Note: _debugPath and _timing are DISABLED - code kept for future use
function buildResponse($data) {
    global $_debugPath, $_isDevMode, $_apiStartTime, $_apiTimings;


    // API-level timings output
    if ($_isDevMode && isset($_apiTimings)) {
        $_apiTimings['total_api'] = round((microtime(true) - $_apiStartTime) * 1000, 1);
        if (isset($data['_timing'])) {
            $data['_timing'] = array_merge($_apiTimings, $data['_timing']);
        } else {
            $data['_timing'] = $_apiTimings;
        }
    }

    // Remove _timing from service responses as well
    unset($data['_timing']);

    return $data;
}

// Helper to output JSON response
/**
 * Set HTTP cache headers for cacheable responses
 * @param int $maxAge Max age in seconds (default 5 minutes)
 * @param bool $private Whether to use private cache (default true)
 */
function setCacheHeaders(int $maxAge = 300, bool $private = true) {
    $cacheControl = $private ? 'private' : 'public';
    header("Cache-Control: {$cacheControl}, max-age={$maxAge}");
    header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $maxAge) . ' GMT');
    // Allow browser to use stale content while revalidating
    header("Cache-Control: {$cacheControl}, max-age={$maxAge}, stale-while-revalidate=60", false);
}

/**
 * Set no-cache headers for dynamic/sensitive responses
 */
function setNoCacheHeaders() {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
}

function outputJson($data) {
    global $_isDevMode, $action, $_apiStartTime;
    $data = buildResponse($data);

    // Always sanitize UTF-8 before encoding to prevent bad field issues
    // This handles legacy databases with Windows-1252 or mixed encoding
    $data = sanitizeUtf8($data);

    // Debug: Log large text fields before json_encode
    if (isset($data['fields'])) {
        foreach ($data['fields'] as $key => $value) {
            if (is_string($value) && strlen($value) > 1000) {
                Logger::debug("form_api: Before json_encode", ['field' => $key, 'bytes' => strlen($value), 'last30hex' => bin2hex(substr($value, -30))]);
            }
        }
    }

    $json = json_encode($data);

    // Debug: Log JSON output length
    if ($json !== false && strlen($json) > 1000) {
        Logger::debug("form_api: JSON output", ['bytes' => strlen($json)]);
    }

    if ($json === false) {
        // Get JSON error details
        $jsonError = json_last_error_msg();
        $badFields = findBadUtf8Fields($data);

        // Log the error with details
        Logger::error("form_api: JSON encode failed", [
            'error' => $jsonError,
            'action' => $action ?? 'unknown',
            'form' => $jsonFormName ?? 'unknown',
            'badFields' => $badFields
        ]);

        // In dev mode, expose more info
        if ($_isDevMode) {
            header('X-Debug-JsonError: ' . urlencode($jsonError));
            header('X-Debug-BadFields: ' . urlencode(json_encode($badFields)));
        }

        // Force valid JSON with error details in dev mode
        $errorMsg = $_isDevMode ? "Encoding error: $jsonError" : 'Encoding error';
        $json = json_encode(['success' => false, 'error' => $errorMsg, 'badFields' => $badFields]);
    }

    // Log API performance
    $duration = (microtime(true) - $_apiStartTime) * 1000;
    // Build URL for re-testing (exclude timing-sensitive params)
    $apiUrl = 'form_api.php?' . http_build_query(array_filter([
        'action' => $action ?? '',
        'form' => $jsonFormName ?? '',
        'id' => Request::query('id', ''),
    ], fn($v) => $v !== ''));
    PerformanceLogger::logApi($action ?? 'unknown', $duration, [
        'form' => $jsonFormName ?? '',
        'success' => $data['success'] ?? false,
        'response_size' => strlen($json),
        'url' => $apiUrl,
        'method' => Request::method(),
    ]);

    echo $json;
}

try {
    $_apiTimings['pre_parse'] = round((microtime(true) - $_apiStartTime) * 1000, 1);

    // Get form name - prefer 'jsonForm' parameter (what JS uses), fall back to 'formName' or 'form'
    // IMPORTANT: Check 'jsonForm' first because 'form' is also a common field name in form definitions
    // (e.g., _menu_items has a 'form' field) and we don't want field values overwriting the API parameter
    $jsonFormName = Request::query('jsonForm', '');
    if (empty($jsonFormName)) {
        $jsonFormName = Request::post('jsonForm', '');
    }
    // Support 'formName' as an alias
    if (empty($jsonFormName)) {
        $jsonFormName = Request::query('formName', '');
    }
    if (empty($jsonFormName)) {
        $jsonFormName = Request::post('formName', '');
    }
    // Legacy support: also accept 'form' parameter (last, to avoid field name conflicts)
    if (empty($jsonFormName)) {
        $jsonFormName = Request::query('form', '');
    }
    if (empty($jsonFormName)) {
        $jsonFormName = Request::post('form', '');
    }

    // Validate form name to prevent path traversal attacks
    // Only allow alphanumeric, underscore, and hyphen characters
    if (!empty($jsonFormName) && !preg_match('/^[a-zA-Z0-9_-]+$/', $jsonFormName)) {
        addDebug('EXIT: invalid form name format');
        sendDebugHeader();
        outputJson(['success' => false, 'error' => 'Ongeldige form naam']);
        exit;
    }

    addDebug("form=$jsonFormName");
    sendDebugHeader();

    // Some actions don't require a form name
    $noFormActions = ['logJsError', 'logPerformance'];

    // Require form name (except for utility actions)
    if (empty($jsonFormName) && !in_array($action, $noFormActions, true)) {
        addDebug('EXIT: no form');
        sendDebugHeader();
        outputJson(['success' => false, 'error' => 'form parameter is verplicht']);
        exit;
    }

    // Load JSON form definition (skip for utility actions)
    if (!in_array($action, $noFormActions, true)) {
        if (!JsonFormLoader::exists($jsonFormName)) {
            addDebug('EXIT: JSON form not found');
            sendDebugHeader();
            outputJson(['success' => false, 'error' => "JSON form '$jsonFormName' niet gevonden"]);
            exit;
        }

        addDebug("JSON form '$jsonFormName' loaded");
    }

    sendDebugHeader();
    $_apiTimings['pre_switch'] = round((microtime(true) - $_apiStartTime) * 1000, 1);

    switch ($action) {
        case 'debug_access':
            // Debug action to diagnose access rights issues
            // Call: /cma/form_api.php?form=formname&action=debug_access
            // Clear menu cache to get fresh results
            \Cma\Services\MenuService::clearCache();

            $userId = (int) \Cma\SecurityHelper::getCurrentUserId();
            $formId = JsonFormLoader::getFormIdByName($jsonFormName);
            $isAdmin = \Cma\SecurityHelper::isAdmin();
            $isDeveloper = \Cma\SecurityHelper::isDeveloper();
            $userLevel = \Cma\SecurityHelper::getUserLevel();

            // Get detailed access check with debug info
            $accessLevel = \Cma\SecurityHelper::checkFormRightsByName($userId, true);
            $debugInfo = $security_debug ?? [];

            // Get user's group memberships
            $groups = [];
            if ($userId > 0) {
                $usersConn = \Cma\CmaRepository::openConnectionById('users');
                if ($usersConn) {
                    $groupSql = "SELECT g.ID, g.grpName FROM tblGroups g
                                 INNER JOIN tblGroupMembers gm ON g.ID = gm.fkGroup
                                 WHERE gm.fkUser = " . $userId;
                    $groupRs = \App\Library\Database::openRS($groupSql, $usersConn);
                    while ($groupRs && !$groupRs->EOF) {
                        $groups[] = ['id' => $groupRs->fields['ID'], 'name' => $groupRs->fields['grpName']];
                        $groupRs->MoveNext();
                    }
                }
            }

            // Get group rights for this form
            $groupRights = [];
            if ($formId && $userId > 0) {
                $usersConn = \Cma\CmaRepository::openConnectionById('users');
                if ($usersConn) {
                    $rightsSql = "SELECT gr.fkGroup, g.grpName, gr.secAccessType
                                  FROM tblGroupRights gr
                                  INNER JOIN tblGroups g ON gr.fkGroup = g.ID
                                  INNER JOIN tblGroupMembers gm ON g.ID = gm.fkGroup
                                  WHERE gm.fkUser = " . $userId . "
                                  AND gr.secObjectType = 10
                                  AND gr.secObjectID = " . $formId;
                    $rightsRs = \App\Library\Database::openRS($rightsSql, $usersConn);
                    while ($rightsRs && !$rightsRs->EOF) {
                        $groupRights[] = [
                            'groupId' => $rightsRs->fields['fkGroup'],
                            'groupName' => $rightsRs->fields['grpName'],
                            'accessType' => $rightsRs->fields['secAccessType']
                        ];
                        $rightsRs->MoveNext();
                    }
                }
            }

            // Get menu item lookup result for debugging
            $menuItemId = \Cma\Services\MenuService::getMenuItemIdForForm($jsonFormName);

            outputJson([
                'success' => true,
                'debug' => [
                    'formName' => $jsonFormName,
                    'formId' => $formId,
                    'menuItemId' => $menuItemId,
                    'userId' => $userId,
                    'userLevel' => $userLevel,
                    'userLevelName' => \Cma\SecurityHelper::getUserLevelName($userLevel),
                    'isLoggedIn' => \Cma\SecurityHelper::isLoggedIn(),
                    'isAdmin' => $isAdmin,
                    'isDeveloper' => $isDeveloper,
                    'accessLevel' => $accessLevel,
                    'accessLevelName' => match($accessLevel) {
                        0 => 'ACCESS_NONE',
                        10 => 'ACCESS_READ',
                        20 => 'ACCESS_CHANGE_OWN_DATA',
                        30 => 'ACCESS_FULL',
                        40 => 'ACCESS_FULL_BEHEER',
                        default => 'UNKNOWN'
                    },
                    'userGroups' => $groups,
                    'formGroupRights' => $groupRights,
                    'securityDebug' => $debugInfo,
                    'cookies' => [
                        'CMAU' => Cookie::get('CMAU', ''),
                        'CMALEVEL' => Cookie::get('CMALEVEL', ''),
                        'CMAADM' => Cookie::get('CMAADM', ''),
                        'CMAU_ORIGINAL' => Cookie::get('CMAU_ORIGINAL', ''),
                    ]
                ]
            ]);
            exit;

        case 'init':
            // ═══════════════════════════════════════════════════════════════════════
            // FORM INIT - Loads list/tree + combos in ONE request for fast initial load
            // ═══════════════════════════════════════════════════════════════════════
            addDebug('case:init');
            $initStart = microtime(true);
            $initTiming = [];

            $result = ['success' => true];

            // 1. Load tree/table HTML
            // Use queryId() - handles numeric, GUID, and alphanumeric IDs like "C47"
            $activeIdRaw = Request::queryId('ID');
            $activeId = $activeIdRaw !== '' ? $activeIdRaw : null;
            $displayMode = Request::queryInt('displayMode') ?: 2; // default to table mode
            $search = Request::query('search', '');
            $filtersJson = Request::query('filters', '');
            $parentID = Request::query('parentID', '');
            $parentField = Request::query('parentField', '');
            $options = ['displayMode' => $displayMode];
            if ($search !== '') {
                $options['search'] = $search;
            }
            if ($filtersJson !== '') {
                $filters = json_decode($filtersJson, true);
                if (Arr::isArray($filters)) {
                    $options['filters'] = $filters;
                }
            }
            // Add parent filter to options
            if ($parentField !== '' && $parentID !== '') {
                $options['filters'][$parentField] = $parentID;
            }

            // Calculate initial limit based on column count
            // Fewer columns = more rows can be loaded (less data per row)
            $limit = Request::queryInt('limit');
            if (!$limit) {
                $formDef = JsonFormLoader::loadRaw($jsonFormName);
                $columnCount = count($formDef['columns'] ?? $formDef['fields'] ?? []);
                // Base: 300 rows, reduce by ~20 rows per column over 5 columns
                // Min 100, Max 500
                $limit = 200; // Smaller initial page for fast first render; background prefetch loads more
                addDebug("calculated limit=$limit based on $columnCount columns");
            }
            $options['limit'] = $limit;

            $listStart = microtime(true);
            $listResult = $displayMode == 2
                ? FormDataProvider::getJsonFormTableHtml($jsonFormName, $activeId, $options)
                : FormDataProvider::getJsonFormTreeHtml($jsonFormName, $activeId, $options);
            $result['list'] = $listResult;
            $initTiming['list'] = round((microtime(true) - $listStart) * 1000, 1);

            // 2. Load record data if ID provided
            if ($activeId) {
                $recordStart = microtime(true);
                $recordResult = FormDataProvider::getJsonFormRecordData($jsonFormName, $activeId);
                $result['record'] = $recordResult;
                $initTiming['record'] = round((microtime(true) - $recordStart) * 1000, 1);
            }

            // 3. Load combo options (only for uncached fields if specified)
            $comboFields = Request::query('comboFields', '');
            if ($comboFields !== '') {
                $comboStart = microtime(true);
                $fieldNames = array_filter(array_map('trim', explode(',', $comboFields)));

                // JSON forms: use getJsonFormComboOptions to read SQL from JSON definition
                $combos = [];
                foreach ($fieldNames as $fieldName) {
                    if (!empty($fieldName)) {
                        $combos[$fieldName] = FormDataProvider::getJsonFormComboOptions($jsonFormName, $fieldName, '');
                    }
                }
                $result['combos'] = $combos;
                $initTiming['combos'] = round((microtime(true) - $comboStart) * 1000, 1);
                $initTiming['comboCount'] = count($fieldNames);
            }

            // 4. Get search panel combo fields (separate from form combos)
            $searchComboFields = Request::query('searchComboFields', '');
            if ($searchComboFields !== '') {
                $searchComboStart = microtime(true);
                $searchFieldNames = array_filter(array_map('trim', explode(',', $searchComboFields)));
                // JSON forms: use getJsonFormComboOptions for search combos too
                $searchCombos = [];
                foreach ($searchFieldNames as $fieldName) {
                    if (!empty($fieldName)) {
                        $searchCombos[$fieldName] = FormDataProvider::getJsonFormComboOptions($jsonFormName, $fieldName, '');
                    }
                }
                $result['searchCombos'] = $searchCombos;
                $initTiming['searchCombos'] = round((microtime(true) - $searchComboStart) * 1000, 1);
            }

            $initTiming['total'] = round((microtime(true) - $initStart) * 1000, 1);
            $result['_initTiming'] = $initTiming;

            addDebug("init complete: list=" . ($listResult['success'] ?? 'null') .
                     ", record=" . (isset($result['record']) ? ($result['record']['success'] ?? 'null') : 'skipped') .
                     ", combos=" . (isset($result['combos']) ? count($result['combos']) : 0));
            sendDebugHeader();
            outputJson($result);
            break;

        case 'tree':
            addDebug('case:tree');
            // Get tree/table HTML for list panel
            // Use queryId() - handles numeric, GUID, and alphanumeric IDs like "C47"
            $activeIdRaw = Request::queryId('ID');
            $activeId = $activeIdRaw !== '' ? $activeIdRaw : null;
            $search = Request::query('search', '');
            $displayMode = Request::queryInt('displayMode') ?: 2; // 1=tree, 2=table (default to table)
            $filtersJson = Request::query('filters', '');
            $parentID = Request::query('parentID', '');
            $parentField = Request::query('parentField', '');
            $options = ['displayMode' => $displayMode];
            if ($search !== '') {
                $options['search'] = $search;
            }
            // Parse search panel filters
            if ($filtersJson !== '') {
                $filters = json_decode($filtersJson, true);
                if (Arr::isArray($filters)) {
                    $options['filters'] = $filters;
                }
            }
            // Add parent filter to options
            if ($parentField !== '' && $parentID !== '') {
                $options['filters'][$parentField] = $parentID;
            }
            // Pagination parameters for infinite scroll
            $lastId = Request::query('lastId', '');
            $limit = Request::queryInt('limit');
            if (!$limit) {
                // Calculate limit based on column count
                // Fewer columns = more rows can be loaded (less data per row)
                $formDef = JsonFormLoader::loadRaw($jsonFormName);
                $columnCount = count($formDef['columns'] ?? $formDef['fields'] ?? []);
                // Base: 300 rows, reduce by ~20 rows per column over 5 columns
                // Min 100, Max 500
                $limit = 500; // Fixed page size for infinite scroll
            }
            if ($lastId !== '') {
                $options['lastId'] = $lastId;
            }
            $options['limit'] = $limit;
            addDebug("activeId=$activeId, displayMode=$displayMode, filters=" . $filtersJson . ", lastId=$lastId, limit=$limit (cols: " . ($columnCount ?? '?') . ")");

            $method = $displayMode == 2 ? 'getJsonFormTableHtml' : 'getJsonFormTreeHtml';
            addDebug("calling $method");
            $result = $displayMode == 2
                ? FormDataProvider::getJsonFormTableHtml($jsonFormName, $activeId, $options)
                : FormDataProvider::getJsonFormTreeHtml($jsonFormName, $activeId, $options);
            addDebug('result keys: ' . implode(',', array_keys($result ?? [])));
            if (isset($result['success'])) {
                addDebug('success=' . ($result['success'] ? 'true' : 'false'));
            }
            if (isset($result['error'])) {
                addDebug('error=' . $result['error']);
            }
            if (isset($result['count'])) {
                addDebug('count=' . $result['count']);
            }
            if (isset($result['html']) && strlen($result['html']) < 100) {
                addDebug('html=' . $result['html']);
            }
            sendDebugHeader();
            outputJson($result);
            break;

        case 'tableData':
            addDebug('case:tableData');
            // Get table data as JSON for lib-table web component
            // Returns pure JSON rows with column definitions (no HTML)
            $search = Request::query('search', '');
            $filtersJson = Request::query('filters', '');
            $lastId = Request::query('lastId', '');
            $limit = Request::queryInt('limit') ?: 50;
            $sortColumn = Request::query('sortColumn', '');
            $sortDir = Request::query('sortDir', 'ASC');
            $columnsJson = Request::query('columns', '');

            $options = [
                'limit' => $limit,
            ];
            if ($search !== '') {
                $options['search'] = $search;
            }
            if ($filtersJson !== '') {
                $filters = json_decode($filtersJson, true);
                if (Arr::isArray($filters)) {
                    $options['filters'] = $filters;
                }
            }
            if ($lastId !== '') {
                $options['lastId'] = $lastId;
            }
            if ($sortColumn !== '') {
                $options['sortColumn'] = $sortColumn;
                $options['sortDir'] = $sortDir;
            }
            if ($columnsJson !== '') {
                $columns = json_decode($columnsJson, true);
                if (Arr::isArray($columns)) {
                    $options['columns'] = $columns;
                }
            }

            addDebug("search=$search, lastId=$lastId, limit=$limit, sort=$sortColumn $sortDir");

            // Note: getTableJson needs JSON form support
            $result = \Cma\Services\ListService::getJsonFormTableJson($jsonFormName, $options);

            addDebug('result: rows=' . count($result['rows'] ?? []) . ', hasMore=' . ($result['hasMore'] ?? 'null'));
            sendDebugHeader();
            outputJson($result);
            break;

        // Note: 'unifiedTable' action removed - use 'list' or 'tableData' instead
        // getTableHtml now accepts both int (form ID) and string (JSON form name)

        case 'getRow':
            addDebug('case:getRow');
            // Get single row HTML for refresh after edit (popup close)
            // Accept both 'id' (preferred) and 'ID' (legacy)
            $recordId = Request::query('id', '');
            if ($recordId === '') $recordId = Request::query('ID', '');
            $displayMode = Request::queryInt('displayMode') ?: 2; // Default to table mode
            // Accept columns parameter to ensure we use the same columns as displayed
            $columnsParam = Request::query('columns', '');
            $columns = !empty($columnsParam) ? explode(',', $columnsParam) : [];
            addDebug("recordId=$recordId, displayMode=$displayMode, columns=" . count($columns));

            if ($recordId === '') {
                addDebug('EXIT: no id');
                sendDebugHeader();
                outputJson(['success' => false, 'error' => 'id parameter is verplicht']);
                exit;
            }

            try {
                $result = FormDataProvider::getJsonFormRowHtml($jsonFormName, $recordId, $displayMode, $columns);
                addDebug('success=' . ($result['success'] ?? 'null'));
                sendDebugHeader();
                outputJson($result);
            } catch (\Throwable $e) {
                addDebug('EXCEPTION: ' . get_class($e));
                sendDebugHeader();
                outputJson(['success' => false, 'error' => $e->getMessage()]);
            }
            break;

        case 'record':
        case 'get_form':  // Alias for record
            addDebug('case:record');
            // Get record data for form population
            // Accept both 'id' (preferred) and 'ID' (legacy)
            $recordId = Request::query('id', '');
            if ($recordId === '') $recordId = Request::query('ID', '');
            addDebug("recordId=$recordId");
            // Use === '' instead of empty() because empty('0') is true in PHP
            // and 0 is a valid record ID
            if ($recordId === '') {
                addDebug('EXIT: no id');
                sendDebugHeader();
                outputJson(['success' => false, 'error' => 'id parameter is verplicht']);
                exit;
            }
            try {
                addDebug("calling getJsonFormRecordData");
                $result = FormDataProvider::getJsonFormRecordData($jsonFormName, $recordId);
                addDebug('success=' . ($result['success'] ?? 'null'));

                // Piggyback first subform data onto record response to save a round-trip
                if (!empty($result['success'])) {
                    try {
                        $subforms = JsonFormLoader::getSubforms($jsonFormName);
                        if (!empty($subforms)) {
                            addDebug('loading first subform (index 0) with record');
                            $firstSubform = \Cma\Services\ListService::getSubformTableHtml($jsonFormName, $recordId, 0);
                            $result['firstSubform'] = $firstSubform;
                        }
                    } catch (\Throwable $subEx) {
                        addDebug('first subform piggyback failed: ' . $subEx->getMessage());
                        // Non-fatal: client will fall back to separate request
                    }
                }

                sendDebugHeader();
                outputJson($result);
            } catch (\Throwable $e) {
                addDebug('EXCEPTION: ' . get_class($e) . ' at ' . basename($e->getFile()) . ':' . $e->getLine());
                sendDebugHeader();
                $result = ['success' => false, 'error' => $e->getMessage()];
                if ($_isDevMode) {
                    $result['_exception'] = [
                        'class' => get_class($e),
                        'message' => $e->getMessage(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                        'trace' => $e->getTraceAsString(),
                    ];
                }
                outputJson($result);
            }
            break;

        case 'save':
            addDebug('case:save');
            // Save form data
            $recordId = Request::post('ID', '');
            addDebug("recordId=$recordId");

            // Always log save requests for debugging switch toggle issues
            Logger::debug('SAVE REQUEST', [
                'jsonForm' => $jsonFormName,
                'recordId' => $recordId,
                'POST' => Request::postAll(),
            ]);

            if ($recordId === '') {
                $recordId = null; // New record
                addDebug('new record (null ID)');
            }

            // Debug: log all POST fields when debug mode is enabled in preferences
            $saveDebug = SecurityHelper::isDebugMode();
            if ($saveDebug) {
                Logger::debug('SAVE DEBUG - POST data:', Request::postAll());
            }

            // Collect form data from POST
            $data = [];
            $changelog = [];
            $arrayFields = []; // Track fields that are arrays

            // Debug: Log all POST keys
            $postData = Request::postAll();
            Logger::debug("SAVE: POST keys", ['keys' => array_keys($postData)]);
            if (Request::hasPost('userLevel')) {
                Logger::debug("SAVE: userLevel POST value", ['value' => Request::post('userLevel')]);
            } else {
                Logger::debug("SAVE: userLevel NOT in POST");
            }
            // Debug: Log userEmail specifically for the users form issue
            if ($jsonFormName === 'users') {
                Logger::debug("SAVE: users form - userEmail in POST?", [
                    'hasKey' => array_key_exists('userEmail', $postData),
                    'value' => $postData['userEmail'] ?? '(NOT SET)'
                ]);
            }

            foreach ($postData as $key => $value) {
                // Collect changelog fields separately
                if (strpos($key, '_changelog') === 0) {
                    $changelog[$key] = $value;
                    continue;
                }
                // Skip other internal fields
                if (strpos($key, '_') === 0 || $key === 'ID' || $key === 'id' || $key === 'FormID' || $key === 'action' || $key === 'form' || $key === 'jsonForm') {
                    continue;
                }
                // Track array fields for debugging
                if (Arr::isArray($value)) {
                    $arrayFields[$key] = count($value) . ' items';
                }
                $data[$key] = $value;
            }

            // Log array fields if any found
            if (!empty($arrayFields) && $saveDebug) {
                Logger::debug('SAVE DEBUG - Array fields:', $arrayFields);
            }

            // Debug: Log changelog data
            Logger::debug('SAVE changelog data', [
                'keys' => array_keys($changelog),
                'changelogLength' => strlen($changelog['_changelog'] ?? ''),
                'changelogPreview' => substr($changelog['_changelog'] ?? '', 0, 200)
            ]);

            // Combine date and time fields for datetime dataType
            // The form renders datetime fields as separate date + time inputs
            // e.g., datestamp (date) + datestamp_time (time) -> datestamp (combined)
            $formDef = JsonFormLoader::loadRaw($jsonFormName);
            $datetimeFields = [];
            if (isset($formDef['fields']) && Arr::isArray($formDef['fields'])) {
                foreach ($formDef['fields'] as $field) {
                    if (isset($field['dataType']) && $field['dataType'] === 'datetime' && isset($field['name'])) {
                        $datetimeFields[] = $field['name'];
                    }
                }
            }

            // Process datetime fields: combine date + time into single value
            foreach ($datetimeFields as $fieldName) {
                $timeFieldName = $fieldName . '_time';
                if (isset($data[$fieldName]) && isset($data[$timeFieldName])) {
                    $dateValue = trim($data[$fieldName]);
                    $timeValue = trim($data[$timeFieldName]);

                    if ($dateValue !== '') {
                        // Combine date and time
                        // If time is empty or 00:00, just use 00:00:00
                        if ($timeValue === '' || $timeValue === '00:00') {
                            $data[$fieldName] = $dateValue . ' 00:00:00';
                        } else {
                            // Ensure time has seconds
                            if (strlen($timeValue) === 5) {
                                $timeValue .= ':00';
                            }
                            $data[$fieldName] = $dateValue . ' ' . $timeValue;
                        }
                    }

                    // Remove the separate time field - it's not in the database
                    unset($data[$timeFieldName]);
                }
            }

            addDebug('data fields: ' . implode(',', array_keys($data)));
            addDebug('changelog fields: ' . implode(',', array_keys($changelog)));
            addDebug('_changelog value length: ' . strlen($changelog['_changelog'] ?? ''));
            addDebug('_changelog preview: ' . substr($changelog['_changelog'] ?? '(empty)', 0, 200));

            // Debug: Log rights-related keys specifically
            $rightsKeys = array_filter(array_keys($data), fn($k) => strpos($k, 'group_menu_rights') === 0 || strpos($k, 'group_report_rights') === 0);
            addDebug('rights-related keys count: ' . count($rightsKeys));
            if (count($rightsKeys) > 0) {
                addDebug('first 5 rights keys: ' . implode(', ', array_slice($rightsKeys, 0, 5)));
            }

            // Special validation for users form
            if ($jsonFormName === 'users') {
                $usersValidation = validateUsersSave($recordId, $data);
                if (!$usersValidation['success']) {
                    sendDebugHeader();
                    outputJson($usersValidation);
                    break;
                }
                // Apply any modifications from validation (e.g., password handling)
                if (isset($usersValidation['data'])) {
                    $data = $usersValidation['data'];
                }
            }

            addDebug("calling saveJsonFormRecord");
            // Debug: Log userEmail before save
            if ($jsonFormName === 'users') {
                Logger::debug("SAVE: users form - data before saveJsonFormRecord", [
                    'hasUserEmail' => array_key_exists('userEmail', $data),
                    'userEmail' => $data['userEmail'] ?? '(NOT IN DATA)',
                    'dataKeys' => array_keys($data)
                ]);
            }
            $result = FormDataProvider::saveJsonFormRecord($jsonFormName, $recordId, $data, $changelog);

            // After saving users, ensure minimum admin/developer exists
            if ($jsonFormName === 'users' && $result['success']) {
                ensureMinimumUserLevels();
            }

            sendDebugHeader();
            outputJson($result);
            break;

        case 'delete':
            addDebug('case:delete');
            // Delete a record
            // Accept from POST (JavaScript sends it here) or GET (legacy), both 'id' and 'ID'
            $recordId = Request::post('id', '');
            if ($recordId === '') $recordId = Request::post('ID', '');
            if ($recordId === '') $recordId = Request::query('id', '');
            if ($recordId === '') $recordId = Request::query('ID', '');
            addDebug("recordId=$recordId");
            if ($recordId === '') {
                addDebug('EXIT: no id');
                sendDebugHeader();
                outputJson(['success' => false, 'error' => 'id parameter is verplicht']);
                exit;
            }

            // Special validation for users form - prevent deleting last admin/developer
            if ($jsonFormName === 'users') {
                $deleteValidation = validateUsersDelete($recordId);
                if (!$deleteValidation['success']) {
                    sendDebugHeader();
                    outputJson($deleteValidation);
                    break;
                }
            }

            addDebug("calling deleteJsonFormRecord");
            $result = FormDataProvider::deleteJsonFormRecord($jsonFormName, $recordId);
            sendDebugHeader();
            outputJson($result);
            break;

        case 'subform':
            addDebug('case:subform');
            // Get subform data as table HTML (like main form table view)
            $parentID = Request::query('ParentID', '');
            $subformIndex = Request::queryInt('SubformIndex');
            addDebug("parentId=$parentID, subformIndex=$subformIndex");
            if (empty($parentID)) {
                addDebug('EXIT: no ParentID');
                sendDebugHeader();
                outputJson(['success' => false, 'error' => 'ParentID parameter is verplicht']);
                exit;
            }
            addDebug('calling getSubformTableHtml');
            $result = \Cma\Services\ListService::getSubformTableHtml($jsonFormName, $parentID, $subformIndex);
            sendDebugHeader();
            outputJson($result);
            break;

        case 'subforms':
            addDebug('case:subforms (batch)');
            // Get all subform data in a single request
            $parentID = Request::query('ParentID', '');
            $indicesParam = Request::query('indices', ''); // comma-separated list of indices
            addDebug("parentId=$parentID, indices=$indicesParam");
            if (empty($parentID)) {
                addDebug('EXIT: no ParentID');
                sendDebugHeader();
                outputJson(['success' => false, 'error' => 'ParentID parameter is verplicht']);
                exit;
            }
            $indices = array_filter(array_map('intval', explode(',', $indicesParam)));
            if (empty($indices)) {
                addDebug('EXIT: no indices');
                sendDebugHeader();
                outputJson(['success' => false, 'error' => 'indices parameter is verplicht']);
                exit;
            }
            addDebug('loading ' . count($indices) . ' subforms');
            $results = [];
            foreach ($indices as $index) {
                try {
                    $result = \Cma\Services\ListService::getSubformTableHtml($jsonFormName, $parentID, $index);
                    // Ensure we always have a response for each index
                    $results[$index] = $result ?: ['success' => false, 'error' => 'Geen resultaat', 'index' => $index];
                } catch (\Exception $e) {
                    addDebug("subform $index error: " . $e->getMessage());
                    $results[$index] = ['success' => false, 'error' => $e->getMessage(), 'index' => $index];
                }
            }
            // Convert to object to preserve numeric keys in JSON output
            $results = (object) $results;
            sendDebugHeader();
            outputJson(['success' => true, 'subforms' => $results]);
            break;

        case 'combo':
            addDebug('case:combo');
            // Get combo box options
            $fieldName = Request::query('field', '');
            $search = Request::query('search', '');
            $lookupId = Request::query('id', '');
            // Filter context for parameterized SQL queries
            $filterField = Request::query('filterField', '');
            $filterValue = Request::query('filterValue', '');
            $parentID = Request::query('parentID', '');
            $parentField = Request::query('parentField', '');
            addDebug("field=$fieldName, id=$lookupId, filterField=$filterField, filterValue=$filterValue");
            if (empty($fieldName)) {
                addDebug('EXIT: no field');
                sendDebugHeader();
                outputJson(['success' => false, 'error' => 'field parameter is verplicht']);
                exit;
            }
            addDebug('calling getJsonFormComboOptions');
            // Pass filter context to the combo options provider
            $filterContext = [];
            if ($filterField && $filterValue !== '') {
                $filterContext[$filterField] = $filterValue;
            }
            if ($parentField && $parentID !== '') {
                $filterContext[$parentField] = $parentID;
            }
            $result = FormDataProvider::getJsonFormComboOptions($jsonFormName, $fieldName, $search, $lookupId, $filterContext);
            sendDebugHeader();
            outputJson($result);
            break;

        case 'combos':
            addDebug('case:combos');
            // Batch combo box options - get multiple combos in one request
            $fieldsParam = Request::query('fields', '');
            addDebug("fields=$fieldsParam");
            if (empty($fieldsParam)) {
                addDebug('EXIT: no fields');
                sendDebugHeader();
                outputJson(['success' => false, 'error' => 'fields parameter is verplicht']);
                exit;
            }
            $fieldNames = array_filter(array_map('trim', explode(',', $fieldsParam)));
            addDebug('calling getJsonFormComboOptions for ' . count($fieldNames) . ' fields');

            $results = [];
            foreach ($fieldNames as $fieldName) {
                if (!empty($fieldName)) {
                    $results[$fieldName] = FormDataProvider::getJsonFormComboOptions($jsonFormName, $fieldName, '');
                }
            }
            sendDebugHeader();
            outputJson(['success' => true, 'combos' => $results]);
            break;

        case 'checklist':
            addDebug('case:checklist');
            // Get checklist options (controlId is the field name)
            $controlId = Request::query('controlId', '');
            // Accept both 'id' (preferred) and 'ID' (legacy)
            $recordId = Request::query('id', '');
            if ($recordId === '') $recordId = Request::query('ID', '-1');
            addDebug("controlId=$controlId, recordId=$recordId");
            if (empty($controlId)) {
                addDebug('EXIT: no controlId');
                sendDebugHeader();
                outputJson(['success' => false, 'error' => 'controlId parameter is verplicht']);
                exit;
            }
            addDebug('calling getJsonFormChecklistOptions');
            $result = FormDataProvider::getJsonFormChecklistOptions($jsonFormName, $controlId, $recordId);
            sendDebugHeader();
            outputJson($result);
            break;

        case 'renderer':
            addDebug('case:renderer');
            // Get custom renderer HTML
            $renderer = Request::query('renderer', '');
            $fieldName = Request::query('field', '');
            // Accept both 'id' (preferred) and 'ID' (legacy)
            $recordId = Request::query('id', '');
            if ($recordId === '') $recordId = Request::query('ID', '');
            $optionsJson = Request::query('options', '{}');
            addDebug("renderer=$renderer, field=$fieldName");

            if (empty($renderer)) {
                addDebug('EXIT: no renderer');
                sendDebugHeader();
                outputJson(['success' => false, 'error' => 'renderer parameter is verplicht']);
                exit;
            }

            $config = [];
            if ($optionsJson) {
                $config['options'] = json_decode($optionsJson, true) ?? [];
            }

            addDebug('calling JsonFormRenderer::render');
            $html = JsonFormRenderer::render($renderer, $fieldName, $config, null, $recordId);
            addDebug('html length: ' . strlen($html ?? ''));
            sendDebugHeader();
            outputJson(['success' => true, 'html' => $html]);
            break;

        case 'columns':
            addDebug('case:columns');
            // Get available columns for column selector
            addDebug('calling getJsonFormAvailableColumns');
            $result = \Cma\Services\ListService::getJsonFormAvailableColumns($jsonFormName);
            sendDebugHeader();
            outputJson($result);
            break;

        case 'saveColumns':
            addDebug('case:saveColumns');
            // Save column selection to cookie
            $columnsParam = Request::post('columns', '');
            addDebug("columns=$columnsParam");
            if ($columnsParam === '') {
                // Empty means reset to default
                $columns = [];
            } else {
                $columns = array_filter(explode(',', $columnsParam));
            }
            \Cma\Services\ListService::saveColumnPreferences($jsonFormName, $columns);
            addDebug('saved ' . count($columns) . ' columns for ' . $jsonFormName);
            sendDebugHeader();
            outputJson(['success' => true, 'saved' => count($columns)]);
            break;

        case 'logJsError':
            // Log JavaScript errors from client
            // No authentication required - we want to capture errors for all users
            addDebug('case:logJsError');

            $message = Request::post('message', '');
            $url = Request::post('url', '');
            $line = Request::postInt('line');
            $column = Request::postInt('column');
            $stack = Request::post('stack', '');
            $pageUrl = Request::post('pageUrl', '');
            $userAgent = Request::post('userAgent', '');
            $extraInfo = Request::post('extraInfo', '');

            // Get current user if logged in
            $userLogin = '';
            if (SecurityHelper::isLoggedIn()) {
                $userLogin = SecurityHelper::getCurrentUserName();
            }

            // Rate limit: max 100 errors per IP per hour
            $clientIp = Request::ip();
            $rateLimitKey = 'jsError_' . md5($clientIp);
            $errorCount = (int)(\App\Library\Cache::get($rateLimitKey) ?? 0);

            if ($errorCount >= 100) {
                addDebug('Rate limited');
                outputJson(['success' => false, 'error' => 'Rate limited']);
                break;
            }

            \App\Library\Cache::set($rateLimitKey, $errorCount + 1, 3600);

            try {
                $dataConn = \App\Library\Database::getConnection('data');

                // Insert error into database (tblCMAJavascriptErrors is in data database)
                $sql = "INSERT INTO tblCMAJavascriptErrors
                        (datestamp, error_message, error_url, error_line, error_column, error_stack,
                         user_login, user_agent, page_url, extra_info)
                        VALUES (Now(), ?, ?, ?, ?, ?, ?, ?, ?, ?)";

                \App\Library\Database::query($sql, [
                    substr($message, 0, 2000),
                    substr($url, 0, 500),
                    $line,
                    $column,
                    substr($stack, 0, 4000),
                    substr($userLogin, 0, 100),
                    substr($userAgent, 0, 500),
                    substr($pageUrl, 0, 500),
                    substr($extraInfo, 0, 2000)
                ], $dataConn);

                addDebug('Error logged successfully');
                outputJson(['success' => true]);
            } catch (\Exception $e) {
                addDebug('Failed to log error: ' . $e->getMessage());
                outputJson(['success' => false, 'error' => 'Database error']);
            }
            break;

        case 'logPerformance':
            // Log performance data from client for analysis
            addDebug('case:logPerformance');

            // Check if performance logging is enabled in user preferences
            $perfLoggingEnabled = Cookie::get('cma_perf_logging', 'N') === 'J';
            if (!$perfLoggingEnabled) {
                outputJson(['success' => true, 'skipped' => true, 'reason' => 'Performance logging disabled']);
                break;
            }

            $dataJson = Request::post('data', '');

            if (empty($dataJson)) {
                outputJson(['success' => false, 'error' => 'No data provided']);
                break;
            }

            $data = json_decode($dataJson, true);
            if (!$data) {
                outputJson(['success' => false, 'error' => 'Invalid JSON data']);
                break;
            }

            // Write to performance log file
            $logDir = Server::mapPath(Application::get('base_path', '') . '_logging');
            if (!is_dir($logDir)) {
                File::createFolder($logDir);
            }

            $logFile = $logDir . '/performance_' . date('Y-m-d') . '.log';

            // Format log entry
            $logEntry = [
                'timestamp' => date('Y-m-d H:i:s'),
                'page' => $data['page'] ?? 'unknown',
                'user' => Session::get('cma_login', 'anonymous'),
                'measurements' => $data['measurements'] ?? [],
                'counters' => $data['counters'] ?? [],
                'gauges' => $data['gauges'] ?? [],
                'userAgent' => substr($data['userAgent'] ?? '', 0, 200)
            ];

            // Append to log file as JSON line
            $success = file_put_contents($logFile, json_encode($logEntry) . "\n", FILE_APPEND | LOCK_EX);

            if ($success !== false) {
                addDebug('Performance data logged to: ' . basename($logFile));
                outputJson(['success' => true]);
            } else {
                addDebug('Failed to write performance log');
                outputJson(['success' => false, 'error' => 'Failed to write log']);
            }
            break;

        case 'uploadImage':
            // Upload and process image from image wizard
            addDebug('case:uploadImage');

            // Check for uploaded file
            if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
                $uploadError = $_FILES['image']['error'] ?? 'No file';
                outputJson(['success' => false, 'error' => 'Upload failed: ' . $uploadError]);
                break;
            }

            $file = $_FILES['image'];
            $path = Request::post('path', '/images/');
            $maxWidth = (int)Request::post('maxWidth', 800);
            $maxHeight = (int)Request::post('maxHeight', 600);

            // Validate file type
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($file['tmp_name']);

            if (!in_array($mimeType, $allowedTypes)) {
                outputJson(['success' => false, 'error' => 'Ongeldig bestandstype']);
                break;
            }

            // Ensure path starts and ends correctly
            $path = '/' . trim($path, '/') . '/';
            $targetDir = Server::mapPath(Application::get('base_path', '') . ltrim($path, '/'));

            // Create directory if it doesn't exist
            if (!is_dir($targetDir)) {
                if (!File::createFolder($targetDir)) {
                    outputJson(['success' => false, 'error' => 'Kan directory niet aanmaken']);
                    break;
                }
            }

            // Get filename from upload
            $filename = $file['name'];

            // Sanitize filename
            $filename = preg_replace('/[^a-zA-Z0-9_.-]/', '_', $filename);
            $filename = strtolower($filename);

            // Determine output format: WebP if supported, JPEG as fallback
            $useWebP = Image::isWebPSupported();
            $outputExt = $useWebP ? '.webp' : '.jpg';

            $filename = preg_replace('/\.[^.]+$/', $outputExt, $filename);
            $targetPath = $targetDir . $filename;

            // Check if file exists, add suffix if needed
            $baseName = pathinfo($filename, PATHINFO_FILENAME);
            $counter = 1;
            while (file_exists($targetPath)) {
                $filename = $baseName . '_' . $counter . $outputExt;
                $targetPath = $targetDir . $filename;
                $counter++;
            }

            // Load and process image
            $image = null;
            switch ($mimeType) {
                case 'image/jpeg':
                    $image = imagecreatefromjpeg($file['tmp_name']);
                    break;
                case 'image/png':
                    $image = imagecreatefrompng($file['tmp_name']);
                    break;
                case 'image/gif':
                    $image = imagecreatefromgif($file['tmp_name']);
                    break;
                case 'image/webp':
                    $image = imagecreatefromwebp($file['tmp_name']);
                    break;
            }

            if (!$image) {
                outputJson(['success' => false, 'error' => 'Kan afbeelding niet laden']);
                break;
            }

            // Get dimensions
            $width = imagesx($image);
            $height = imagesy($image);

            // Calculate new dimensions if needed (scale down only)
            $newWidth = $width;
            $newHeight = $height;

            if ($width > $maxWidth || $height > $maxHeight) {
                $scale = min($maxWidth / $width, $maxHeight / $height);
                $newWidth = (int)($width * $scale);
                $newHeight = (int)($height * $scale);

                // Resize image
                $resized = imagecreatetruecolor($newWidth, $newHeight);
                // Preserve transparency for WebP/PNG output
                if ($useWebP || $mimeType === 'image/png') {
                    imagealphablending($resized, false);
                    imagesavealpha($resized, true);
                    $transparent = imagecolorallocatealpha($resized, 255, 255, 255, 127);
                    imagefilledrectangle($resized, 0, 0, $newWidth, $newHeight, $transparent);
                }
                imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
                imagedestroy($image);
                $image = $resized;
            }

            // Save as WebP or JPEG
            if ($useWebP) {
                // Preserve alpha for WebP
                imagealphablending($image, false);
                imagesavealpha($image, true);
                $saved = imagewebp($image, $targetPath, ResponsiveImage::DEFAULT_QUALITY);
            } else {
                $saved = imagejpeg($image, $targetPath, 92);
            }

            if ($saved) {
                imagedestroy($image);
                addDebug('Image saved: ' . $filename);

                // Generate responsive variants
                if ($useWebP) {
                    $respResult = ResponsiveImage::generate($targetPath);
                    addDebug('Responsive variants: ' . ($respResult['success'] ? count($respResult['variants']) . ' generated' : 'failed'));
                }

                outputJson([
                    'success' => true,
                    'filename' => $filename,
                    'path' => $path,
                    'fullPath' => $path . $filename,
                    'width' => $newWidth,
                    'height' => $newHeight
                ]);
            } else {
                imagedestroy($image);
                outputJson(['success' => false, 'error' => 'Kan afbeelding niet opslaan']);
            }
            break;

        default:
            addDebug('case:default (unknown action)');
            sendDebugHeader();
            outputJson(['success' => false, 'error' => 'Ongeldige actie: ' . htmlspecialchars($action)]);
            break;
    }
} catch (\Throwable $e) {
    // Catch both Exception and Error (TypeError, ArgumentCountError, etc.)
    addDebug('CATCH Throwable: ' . get_class($e) . ' at ' . basename($e->getFile()) . ':' . $e->getLine());
    sendDebugHeader();

    // Log using structured logger
    Logger::exception($e, 'API Error', [
        'action' => $action ?? 'unknown',
        'jsonForm' => $jsonFormName ?? null,
    ]);

    $result = ['success' => false, 'error' => $e->getMessage()];
    if ($_isDevMode) {
        $result['_exception'] = [
            'class' => get_class($e),
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ];
    }
    outputJson($result);
}

// =========================================================================
// SECTION: Users Form Validation Functions
// =========================================================================

/**
 * Validate users form save operation
 * - Prevents admin from promoting to developer (only developers can do that)
 * - Handles password: skip if empty on edit, require on new
 *
 * @param string|null $recordId Current record ID (null for new)
 * @param array $data Form data
 * @return array ['success' => bool, 'error' => string?, 'data' => array?]
 */
function validateUsersSave($recordId, $data) {
    $isNew = ($recordId === null || $recordId === '');
    $currentUserLevel = SecurityHelper::getUserLevel();

    // Check if trying to set userLevel to Developer (2)
    $newLevel = isset($data['userLevel']) ? (int)$data['userLevel'] : 0;

    if ($newLevel === 2 && $currentUserLevel < 2) {
        // Admin (level 1) trying to create/promote to Developer (level 2)
        return [
            'success' => false,
            'error' => 'Alleen developers kunnen andere gebruikers tot developer promoveren.'
        ];
    }

    // Handle password
    if (!$isNew) {
        // Editing existing user - if password is empty, don't update it
        if (isset($data['userPassword']) && trim($data['userPassword']) === '') {
            unset($data['userPassword']);
        }
    } else {
        // New user - password is required
        if (!isset($data['userPassword']) || trim($data['userPassword']) === '') {
            return [
                'success' => false,
                'error' => 'Wachtwoord is verplicht voor nieuwe gebruikers.'
            ];
        }
    }

    return ['success' => true, 'data' => $data];
}

/**
 * Ensure there is always at least 1 admin and 1 developer
 * Creates cmaadmin/cmadev if needed
 */
function ensureMinimumUserLevels() {
    try {
        $conn = \App\Library\Database::getConnection('users');
        if (!$conn) return;

        // Count admins (level >= 1)
        $adminRow = \App\Library\Database::fetchOne("SELECT COUNT(*) as cnt FROM tblUsers WHERE userLevel >= 1", [], $conn);
        $adminCount = $adminRow['cnt'] ?? 0;

        // Count developers (level = 2)
        $devRow = \App\Library\Database::fetchOne("SELECT COUNT(*) as cnt FROM tblUsers WHERE userLevel = 2", [], $conn);
        $devCount = $devRow['cnt'] ?? 0;

        // Create admin if needed
        if ((int)$adminCount === 0) {
            $password = password_hash('admin' . date('Y'), PASSWORD_DEFAULT);
            $sql = "INSERT INTO tblUsers (userLogin, userFullName, userPassword, userLevel) VALUES (?, ?, ?, ?)";
            \App\Library\Database::query($sql, ['cmaadmin', 'CMA Administrator', $password, 1], $conn);
            Logger::info('Auto-created cmaadmin user (no admins existed)');
        }

        // Create developer if needed
        if ((int)$devCount === 0) {
            $password = password_hash('dev' . date('Y'), PASSWORD_DEFAULT);
            $sql = "INSERT INTO tblUsers (userLogin, userFullName, userPassword, userLevel) VALUES (?, ?, ?, ?)";
            \App\Library\Database::query($sql, ['cmadev', 'CMA Developer', $password, 2], $conn);
            Logger::info('Auto-created cmadev user (no developers existed)');
        }
    } catch (\Exception $e) {
        Logger::error('Failed to ensure minimum user levels', ['error' => $e->getMessage()]);
    }
}

/**
 * Validate users form delete operation
 * - Prevents deleting the last admin or developer
 * - Prevents users from deleting themselves
 *
 * @param string $recordId User ID to delete
 * @return array ['success' => bool, 'error' => string?]
 */
function validateUsersDelete($recordId) {
    try {
        $conn = \App\Library\Database::getConnection('users');
        if (!$conn) {
            return ['success' => false, 'error' => 'Database connectie mislukt'];
        }

        // Prevent self-deletion
        $currentUserId = Cookie::get(SecurityHelper::COOKIE_USERID, '');
        if ($recordId === $currentUserId) {
            return ['success' => false, 'error' => 'Je kunt je eigen account niet verwijderen.'];
        }

        // Get the user's level
        $row = \App\Library\Database::fetchOne("SELECT userLevel FROM tblUsers WHERE ID = ?", [$recordId], $conn);
        $userLevel = $row ? ($row['userLevel'] ?? null) : null;

        if ($userLevel === null) {
            // User doesn't exist - let normal delete handle the error
            return ['success' => true];
        }

        $userLevel = (int)$userLevel;

        // Check if this is the last admin (level 1 or higher)
        if ($userLevel >= 1) {
            $countRow = \App\Library\Database::fetchOne("SELECT COUNT(*) as cnt FROM tblUsers WHERE userLevel >= 1", [], $conn);
            $adminCount = (int)($countRow['cnt'] ?? 0);
            if ($adminCount <= 1) {
                return [
                    'success' => false,
                    'error' => 'Deze gebruiker is de laatste administrator. Er moet altijd minimaal 1 administrator bestaan.'
                ];
            }
        }

        // Check if this is the last developer (level 2)
        if ($userLevel === 2) {
            $countRow = \App\Library\Database::fetchOne("SELECT COUNT(*) as cnt FROM tblUsers WHERE userLevel = 2", [], $conn);
            $devCount = (int)($countRow['cnt'] ?? 0);
            if ($devCount <= 1) {
                return [
                    'success' => false,
                    'error' => 'Deze gebruiker is de laatste developer. Er moet altijd minimaal 1 developer bestaan.'
                ];
            }
        }

        return ['success' => true];
    } catch (\Exception $e) {
        Logger::error('User delete validation failed', ['error' => $e->getMessage()]);
        return ['success' => false, 'error' => 'Validatie mislukt: ' . $e->getMessage()];
    }
}
