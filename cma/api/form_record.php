<?php
/**
 * API Endpoint: Form Record Data
 *
 * GET    /cma/api/form_record.php?formId=X&id=Y  - Get record
 * POST   /cma/api/form_record.php                - Create/update record
 * DELETE /cma/api/form_record.php                - Delete record
 *
 * POST/DELETE expect JSON body with formId, id, and data.
 */

use App\Library\Arr;
use App\Library\Request;
use App\Library\Response;
use Cma\FormDataProvider;
use Cma\Services\ConfigFormService;
use Cma\Services\JsonFormService;
use Cma\Services\Logger;

require_once __DIR__ . '/../bootstrap.inc';

// Set JSON response headers
Response::noCache();
header('Content-Type: application/json; charset=utf-8');

try {
    $method = Request::server('REQUEST_METHOD');

    switch ($method) {
        case 'GET':
            // Get record data
            $formId = Request::queryInt('formId');
            // Support both 'form' and 'formName' parameters for backwards compatibility
            $formName = Request::query('formName', '') ?: Request::query('form', '');
            $rawRecordId = Request::query('id', '');  // Raw ID for JSON config forms (may have letters like "B99")
            $recordId = Request::queryIntAndGuid('id');  // Numeric/GUID ID for database forms
            $filter = Request::query('filter', '');  // JSON filter like {"Titel":"Tab"}

            if (empty($formId) && empty($formName)) {
                echo json_encode(['success' => false, 'error' => 'formId or formName is required']);
                exit;
            }

            // Resolve form from formName if provided
            if (!empty($formName)) {
                $formDef = \Cma\JsonFormLoader::load($formName);
                if ($formDef) {
                    // Check if this is a JSON config form
                    if (ConfigFormService::isJsonConfigForm($formDef)) {
                        // Use ConfigFormService for JSON config forms
                        // Use raw ID since JSON config forms can have alphanumeric IDs like "B99"
                        if (!empty($filter)) {
                            // Parse JSON filter
                            $filterCriteria = json_decode($filter, true);
                            if (!Arr::isArray($filterCriteria)) {
                                echo json_encode(['success' => false, 'error' => 'Invalid filter JSON']);
                                exit;
                            }
                            $result = ConfigFormService::getRecordByFilter($formName, $filterCriteria);
                        } else {
                            $result = ConfigFormService::getRecord($formName, $rawRecordId);
                        }
                        echo json_encode($result, JSON_UNESCAPED_UNICODE);
                        exit;
                    }

                    // Use JsonFormService for database-backed JSON forms
                    $result = JsonFormService::getRecord($formName, $rawRecordId ?: $recordId);
                    echo json_encode($result, JSON_UNESCAPED_UNICODE);
                    exit;
                }
            }

            // Standard database form handling (numeric formId)
            if (empty($formId)) {
                echo json_encode(['success' => false, 'error' => 'Form not found: ' . $formName]);
                exit;
            }

            if (empty($recordId)) {
                echo json_encode(['success' => false, 'error' => 'Record ID is required']);
                exit;
            }

            $result = FormDataProvider::getRecordData($formId, $recordId);

            // Debug: Log field lengths before JSON encoding
            if (isset($result['record']['Inhoud']) && strlen($result['record']['Inhoud']) > 1000) {
                $inhoud = $result['record']['Inhoud'];
                Logger::debug("API: Inhoud before json_encode", ['bytes' => strlen($inhoud), 'last50' => substr($inhoud, -50)]);
            }

            // Use JSON_INVALID_UTF8_SUBSTITUTE to handle corrupted ODBC encoding
            $json = json_encode($result, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

            // Debug: Check for json_encode errors
            if ($json === false) {
                Logger::error("API: json_encode FAILED", ['error' => json_last_error_msg()]);
                echo json_encode(['success' => false, 'error' => 'JSON encoding failed: ' . json_last_error_msg()]);
                exit;
            }

            // Debug: Log JSON output length
            if (strlen($json) > 1000) {
                Logger::debug("API: JSON output", ['bytes' => strlen($json)]);
            }

            echo $json;
            break;

        case 'POST':
            // Create or update record
            $input = json_decode(file_get_contents('php://input'), true);

            if ($input === null) {
                echo json_encode(['success' => false, 'error' => 'Invalid JSON input']);
                exit;
            }

            $formId = (int)($input['formId'] ?? 0);
            $recordId = $input['id'] ?? null;
            $data = $input['data'] ?? [];

            if (empty($formId)) {
                echo json_encode(['success' => false, 'error' => 'FormID is required']);
                exit;
            }

            if (empty($data)) {
                echo json_encode(['success' => false, 'error' => 'No data provided']);
                exit;
            }

            $result = FormDataProvider::saveRecord($formId, $recordId, $data);
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
            break;

        case 'DELETE':
            // Delete record
            $input = json_decode(file_get_contents('php://input'), true);

            if ($input === null) {
                echo json_encode(['success' => false, 'error' => 'Invalid JSON input']);
                exit;
            }

            $formId = (int)($input['formId'] ?? 0);
            $recordId = $input['id'] ?? null;

            if (empty($formId)) {
                echo json_encode(['success' => false, 'error' => 'FormID is required']);
                exit;
            }

            if (empty($recordId)) {
                echo json_encode(['success' => false, 'error' => 'Record ID is required']);
                exit;
            }

            $result = FormDataProvider::deleteRecord($formId, $recordId);
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            http_response_code(405);
    }
} catch (Exception $e) {
    Logger::error('API form_record error', [
        'message' => $e->getMessage(),
        'method' => $method ?? 'unknown',
        'formId' => $formId ?? null,
        'formName' => $formName ?? null,
        'recordId' => $recordId ?? null,
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
    ]);
}
