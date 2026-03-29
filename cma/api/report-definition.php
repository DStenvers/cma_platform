<?php
/**
 * Report Definition API - Developer only
 *
 * Allows developers to update report definitions (JSON/SQL) in reports.json
 * and download the reports.json file for local development.
 *
 * Endpoints:
 * - POST ?action=update - Update a report definition (JSON or SQL)
 * - GET ?action=download - Download reports.json file
 * - GET ?action=get&id=ID - Get a single report definition
 */

require_once __DIR__ . '/../bootstrap.inc';

use App\Library\Arr;
use App\Library\Database;
use App\Library\Response;
use App\Library\Request;
use Cma\SecurityHelper;
use Cma\Services\ReportsService;

Response::noCache();

// Security: require developer role
if (!SecurityHelper::isLoggedIn()) {
    header('Content-Type: application/json; charset=utf-8');
    Response::json(['success' => false, 'error' => 'Niet ingelogd'], 401);
    exit;
}

if (!SecurityHelper::isDeveloper()) {
    header('Content-Type: application/json; charset=utf-8');
    Response::json(['success' => false, 'error' => 'Alleen developers hebben toegang tot deze functie'], 403);
    exit;
}

$action = Request::query('action', '');
if (empty($action) && Request::server('REQUEST_METHOD') === 'POST') {
    $rawBody = file_get_contents('php://input');
    $jsonInput = json_decode($rawBody, true);
    $action = $jsonInput['action'] ?? '';
} else {
    $rawBody = null;
    $jsonInput = null;
}

switch ($action) {
    case 'update':
        handleUpdate($jsonInput, $rawBody);
        break;

    case 'download':
        handleDownload();
        break;

    case 'get':
        handleGet();
        break;

    default:
        header('Content-Type: application/json; charset=utf-8');
        Response::json(['success' => false, 'error' => 'Onbekende actie: ' . $action], 400);
}

/**
 * Update a report definition
 */
function handleUpdate(?array $jsonInput, ?string $rawBody): void
{
    header('Content-Type: application/json; charset=utf-8');

    if ($jsonInput === null && $rawBody !== null) {
        $jsonInput = json_decode($rawBody, true);
    }
    if ($jsonInput === null) {
        $rawBody = file_get_contents('php://input');
        $jsonInput = json_decode($rawBody, true);
    }

    if (!$jsonInput || !isset($jsonInput['id'])) {
        Response::json(['success' => false, 'error' => 'Report ID is verplicht'], 400);
        exit;
    }

    $reportId = (int)$jsonInput['id'];
    $updates = [];

    // Update SQL query if provided
    if (isset($jsonInput['query'])) {
        $sql = trim($jsonInput['query']);

        // Security: only allow SELECT queries
        if (!empty($sql)) {
            $trimmedSql = strtoupper(trim($sql));
            if (!str_starts_with($trimmedSql, 'SELECT')) {
                Response::json(['success' => false, 'error' => 'Alleen SELECT-queries zijn toegestaan'], 400);
                exit;
            }

            $blocked = ['INSERT', 'UPDATE', 'DELETE', 'DROP', 'TRUNCATE', 'ALTER', 'CREATE', 'EXEC', 'EXECUTE', 'GRANT', 'REVOKE'];
            foreach ($blocked as $keyword) {
                if (preg_match('/\b' . $keyword . '\b/i', $sql)) {
                    Response::json(['success' => false, 'error' => "Query bevat niet-toegestaan keyword: $keyword"], 400);
                    exit;
                }
            }
        }

        $updates['query'] = $sql;
    }

    // Update full JSON definition if provided
    if (isset($jsonInput['definition']) && Arr::isArray($jsonInput['definition'])) {
        $def = $jsonInput['definition'];
        // Don't allow changing the ID
        unset($def['id']);
        // Merge all definition fields
        foreach ($def as $key => $value) {
            $updates[$key] = $value;
        }
    }

    if (empty($updates)) {
        Response::json(['success' => false, 'error' => 'Geen wijzigingen opgegeven'], 400);
        exit;
    }

    $success = ReportsService::updateById($reportId, $updates);

    if ($success) {
        Response::json([
            'success' => true,
            'message' => 'Rapport definitie bijgewerkt',
            'reportId' => $reportId
        ]);
    } else {
        Response::json([
            'success' => false,
            'error' => 'Kon rapport niet bijwerken. Controleer of het rapport bestaat.'
        ], 500);
    }
}

/**
 * Download the reports.json file
 */
function handleDownload(): void
{
    $json = ReportsService::getRawJson();

    if ($json === false) {
        header('Content-Type: application/json; charset=utf-8');
        Response::json(['success' => false, 'error' => 'Kon reports.json niet laden'], 500);
        exit;
    }

    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="reports.json"');
    header('Content-Length: ' . strlen($json));
    echo $json;
    exit;
}

/**
 * Get a single report definition
 */
function handleGet(): void
{
    header('Content-Type: application/json; charset=utf-8');

    $reportId = Request::queryInt('id');
    if (empty($reportId)) {
        Response::json(['success' => false, 'error' => 'Report ID is verplicht'], 400);
        exit;
    }

    $report = ReportsService::getById($reportId);
    if ($report === null) {
        Response::json(['success' => false, 'error' => 'Rapport niet gevonden'], 404);
        exit;
    }

    Response::json([
        'success' => true,
        'report' => $report
    ]);
}
