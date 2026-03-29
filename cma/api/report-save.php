<?php
/**
 * Report Save API
 *
 * Manages saving, loading, and listing of report definitions.
 *
 * Endpoints:
 * - POST ?action=save - Save a report definition
 * - GET ?action=load&id=ID - Load a report definition
 * - GET ?action=list - List all available reports
 * - POST ?action=delete&id=ID - Delete a report
 * - POST ?action=duplicate&id=ID - Duplicate a report
 */

require_once __DIR__ . '/../bootstrap.inc';

use App\Library\Arr;
use App\Library\Response;
use App\Library\Request;
use Cma\SecurityHelper;
use Cma\ReportStorage;

// Set Content-Type early to prevent debug/profiler output from corrupting JSON response
Response::noCache();
header('Content-Type: application/json; charset=utf-8');

// Security check - require valid login
if (!SecurityHelper::isLoggedIn()) {
    Response::json(['success' => false, 'error' => 'Niet ingelogd'], 401);
    exit;
}

// Get current user ID
$userId = SecurityHelper::getCurrentUserId();

// Read JSON body once (php://input can only be read once)
// Store in global for use by getDefinitionFromRequest()
global $REPORT_API_JSON_INPUT;
$REPORT_API_JSON_INPUT = null;
$jsonInput = null;
if (Request::server('REQUEST_METHOD') === 'POST') {
    $rawBody = file_get_contents('php://input');
    $jsonInput = json_decode($rawBody, true);
    $REPORT_API_JSON_INPUT = $jsonInput;
}

// Get action from query string, form post, or JSON body
$action = Request::query('action', '');
if (empty($action)) {
    $action = Request::post('action', '');
}
if (empty($action) && $jsonInput && isset($jsonInput['action'])) {
    $action = $jsonInput['action'];
}

switch ($action) {
    case 'save':
        handleSave($userId);
        break;

    case 'load':
        handleLoad($userId);
        break;

    case 'list':
        handleList($userId);
        break;

    case 'delete':
        handleDelete($userId);
        break;

    case 'duplicate':
        handleDuplicate($userId);
        break;

    default:
        Response::json(['success' => false, 'error' => 'Onbekende actie: ' . $action], 400);
}

/**
 * Save a report definition
 */
function handleSave(string $userId): void
{
    // Get report definition from POST body
    $definition = getDefinitionFromRequest();
    if ($definition === null) {
        Response::json(['success' => false, 'error' => 'Rapport definitie is verplicht'], 400);
        exit;
    }

    // Validate name
    if (empty($definition['name'])) {
        Response::json(['success' => false, 'error' => 'Rapportnaam is verplicht'], 400);
        exit;
    }

    // Validate SQL mode reports
    $mode = $definition['mode'] ?? '';
    if ($mode === 'sql') {
        if (empty($definition['rawSql'])) {
            Response::json(['success' => false, 'error' => 'SQL-query is verplicht voor SQL-mode rapporten'], 400);
            exit;
        }

        // Security check: ensure only SELECT is allowed
        $trimmedSql = strtoupper(trim($definition['rawSql']));
        if (!str_starts_with($trimmedSql, 'SELECT')) {
            Response::json(['success' => false, 'error' => 'Alleen SELECT-queries zijn toegestaan'], 400);
            exit;
        }

        // Block dangerous keywords
        $blocked = ['INSERT', 'UPDATE', 'DELETE', 'DROP', 'TRUNCATE', 'ALTER', 'CREATE', 'EXEC', 'EXECUTE', 'GRANT', 'REVOKE'];
        foreach ($blocked as $keyword) {
            if (preg_match('/\b' . $keyword . '\b/i', $definition['rawSql'])) {
                Response::json(['success' => false, 'error' => "Query bevat niet-toegestaan keyword: $keyword"], 400);
                exit;
            }
        }
    }

    // Check if user can save global reports
    $isGlobal = $definition['isGlobal'] ?? false;
    if ($isGlobal && !SecurityHelper::isDeveloper()) {
        // Only developers can save global reports
        Response::json(['success' => false, 'error' => 'Alleen developers kunnen globale rapporten opslaan'], 403);
        exit;
    }

    // Save report
    $result = ReportStorage::save($definition, $userId);

    if ($result['success']) {
        Response::json([
            'success' => true,
            'id' => $result['id'],
            'message' => 'Rapport opgeslagen'
        ]);
    } else {
        Response::json([
            'success' => false,
            'error' => $result['error'] ?? 'Kon rapport niet opslaan'
        ], 500);
    }
}

/**
 * Load a report definition
 */
function handleLoad(string $userId): void
{
    $reportId = Request::query('id', Request::post('id', ''));

    if (empty($reportId)) {
        Response::json(['success' => false, 'error' => 'Rapport ID is verplicht'], 400);
        exit;
    }

    $definition = ReportStorage::load($reportId, $userId);

    if ($definition === null) {
        Response::json(['success' => false, 'error' => 'Rapport niet gevonden'], 404);
        exit;
    }

    Response::json([
        'success' => true,
        'report' => $definition
    ]);
}

/**
 * List all available reports
 */
function handleList(string $userId): void
{
    $includeGlobal = Request::query('includeGlobal', '1') !== '0';

    $reports = ReportStorage::list($userId, $includeGlobal);

    Response::json([
        'success' => true,
        'reports' => $reports,
        'count' => count($reports)
    ]);
}

/**
 * Delete a report
 */
function handleDelete(string $userId): void
{
    $reportId = Request::query('id', Request::post('id', ''));
    $isGlobal = Request::post('isGlobal', '0') === '1';

    if (empty($reportId)) {
        Response::json(['success' => false, 'error' => 'Rapport ID is verplicht'], 400);
        exit;
    }

    // Check permission for global reports
    if ($isGlobal && !SecurityHelper::isDeveloper()) {
        Response::json(['success' => false, 'error' => 'Alleen developers kunnen globale rapporten verwijderen'], 403);
        exit;
    }

    // Load report first to verify ownership
    $report = ReportStorage::load($reportId, $userId);
    if ($report === null) {
        Response::json(['success' => false, 'error' => 'Rapport niet gevonden'], 404);
        exit;
    }

    // Check if user owns the report (or is developer for global)
    $reportIsGlobal = $report['isGlobal'] ?? false;
    $reportOwner = $report['createdBy'] ?? '';

    if ($reportIsGlobal) {
        if (!SecurityHelper::isDeveloper()) {
            Response::json(['success' => false, 'error' => 'Alleen developers kunnen globale rapporten verwijderen'], 403);
            exit;
        }
    } else {
        if ($reportOwner !== $userId && !SecurityHelper::isDeveloper()) {
            Response::json(['success' => false, 'error' => 'Je kunt alleen je eigen rapporten verwijderen'], 403);
            exit;
        }
    }

    $result = ReportStorage::delete($reportId, $userId, $reportIsGlobal);

    if ($result['success']) {
        Response::json([
            'success' => true,
            'message' => 'Rapport verwijderd'
        ]);
    } else {
        Response::json([
            'success' => false,
            'error' => $result['error'] ?? 'Kon rapport niet verwijderen'
        ], 500);
    }
}

/**
 * Duplicate a report
 */
function handleDuplicate(string $userId): void
{
    $sourceId = Request::post('id', '');
    $newName = Request::post('name', '');
    $asGlobal = Request::post('isGlobal', '0') === '1';

    if (empty($sourceId)) {
        Response::json(['success' => false, 'error' => 'Bron rapport ID is verplicht'], 400);
        exit;
    }

    if (empty($newName)) {
        Response::json(['success' => false, 'error' => 'Nieuwe naam is verplicht'], 400);
        exit;
    }

    // Check permission for global reports
    if ($asGlobal && !SecurityHelper::isDeveloper()) {
        Response::json(['success' => false, 'error' => 'Alleen developers kunnen globale rapporten maken'], 403);
        exit;
    }

    $result = ReportStorage::duplicate($sourceId, $newName, $userId, $asGlobal);

    if ($result['success']) {
        Response::json([
            'success' => true,
            'id' => $result['id'],
            'message' => 'Rapport gekopieerd'
        ]);
    } else {
        Response::json([
            'success' => false,
            'error' => $result['error'] ?? 'Kon rapport niet kopiëren'
        ], 500);
    }
}

/**
 * Get report definition from request body
 */
function getDefinitionFromRequest(): ?array
{
    // Use cached JSON body (php://input can only be read once)
    global $REPORT_API_JSON_INPUT;

    if ($REPORT_API_JSON_INPUT !== null && Arr::isArray($REPORT_API_JSON_INPUT)) {
        $json = $REPORT_API_JSON_INPUT;
        // Definition might be the whole body or in a 'definition' key
        if (isset($json['definition']) && Arr::isArray($json['definition'])) {
            return $json['definition'];
        }
        // Check if it looks like a definition (has 'name' or 'tables')
        if (isset($json['name']) || isset($json['tables'])) {
            return $json;
        }
        return $json;
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
