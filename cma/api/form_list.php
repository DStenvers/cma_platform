<?php
/**
 * API Endpoint: Form List Data
 *
 * GET /cma/api/form_list.php?formId=X&page=1&pageSize=50&search=term
 * GET /cma/api/form_list.php?formName=users&page=1&pageSize=50&search=term
 *
 * Supports both formId (numeric) and formName (string) parameters.
 * For JSON config forms (database="json"), uses ConfigFormService.
 * For subforms, add subform=name&parentId=X parameters.
 *
 * Returns JSON list data for the specified form.
 */

use App\Library\Arr;
use App\Library\Request;
use App\Library\Response;
use Cma\FormDataProvider;
use Cma\JsonFormLoader;
use Cma\Services\ConfigFormService;
use Cma\Services\JsonFormService;
use Cma\Services\Logger;

require_once __DIR__ . '/../bootstrap.inc';

// Set JSON response headers
Response::noCache();
header('Content-Type: application/json; charset=utf-8');

try {
    // Get form identifier - support both formId and formName
    $formId = Request::queryInt('formId');
    $formName = Request::query('formName', '');
    $subformName = Request::query('subform', '');
    $parentId = Request::query('parentId', '');

    // Resolve form from formName if provided
    if (empty($formId) && !empty($formName)) {
        // Try to load JSON form definition (returns array)
        $formDef = JsonFormLoader::load($formName);
        if ($formDef) {
            // Get the raw JSON data for type checking
            $jsonData = $formDef['_json'] ?? null;

            // Check if this is a JSON config form (database="json")
            if (ConfigFormService::isJsonConfigForm($formDef)) {
                // Parse filters for config forms (JSON format)
                $configFilters = [];
                $filtersJson = Request::query('filters', '');
                if ($filtersJson !== '') {
                    $configFilters = json_decode($filtersJson, true);
                    if (!Arr::isArray($configFilters)) {
                        $configFilters = [];
                    }
                }

                // Handle subform request for config forms
                if (!empty($subformName) && !empty($parentId)) {
                    // Find subform index by name
                    $subforms = $jsonData['subforms'] ?? [];
                    $subformIndex = 0;
                    foreach ($subforms as $index => $subform) {
                        if (($subform['name'] ?? '') === $subformName || ($subform['formName'] ?? '') === $subformName) {
                            $subformIndex = $index;
                            break;
                        }
                    }
                    $result = ConfigFormService::getSubformListData($formName, $parentId, $subformIndex);
                } else {
                    $result = ConfigFormService::getListData($formName, $configFilters);
                }
                $json = json_encode($result, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
                if ($json === false) {
                    echo json_encode(['success' => false, 'error' => 'JSON encoding failed: ' . json_last_error_msg()]);
                } else {
                    echo $json;
                }
                exit;
            }

            // For database-backed JSON forms, use JsonFormService
            // Build options for JSON form
            $options = [
                'page' => max(1, (int)Request::query('page', '1')),
                'pageSize' => min(100, max(10, (int)Request::query('pageSize', '50'))),
                'search' => Request::query('search', ''),
                'sortColumn' => Request::query('sortColumn', ''),
                'sortDir' => Request::query('sortDir', 'ASC'),
                'filters' => [],
            ];

            // Parse filters
            foreach (Request::queryAll() as $key => $value) {
                if (strpos($key, 'filter[') === 0 && substr($key, -1) === ']') {
                    $filterName = substr($key, 7, -1);
                    $options['filters'][$filterName] = $value;
                }
            }

            // Handle subform requests for database-backed JSON forms
            if (!empty($subformName) && !empty($parentId)) {
                $result = JsonFormService::getSubformListData($formName, $subformName, $parentId, $options);
            } else {
                $result = JsonFormService::getListData($formName, $options);
            }
            $json = json_encode($result, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
            if ($json === false) {
                echo json_encode(['success' => false, 'error' => 'JSON encoding failed: ' . json_last_error_msg()]);
            } else {
                echo $json;
            }
            exit;
        }
    }

    // Validate that we have a form identifier
    if (empty($formId) && empty($formName)) {
        echo json_encode(['success' => false, 'error' => 'formId or formName is required']);
        exit;
    }

    if (empty($formId)) {
        echo json_encode(['success' => false, 'error' => 'Form not found: ' . $formName]);
        exit;
    }

    // Build options for legacy form ID
    $options = [
        'page' => max(1, (int)Request::query('page', '1')),
        'pageSize' => min(100, max(10, (int)Request::query('pageSize', '50'))),
        'search' => Request::query('search', ''),
        'sortColumn' => Request::query('sortColumn', ''),
        'sortDir' => Request::query('sortDir', 'ASC'),
        'filters' => [],
    ];

    // Parse filters
    foreach (Request::queryAll() as $key => $value) {
        if (strpos($key, 'filter[') === 0 && substr($key, -1) === ']') {
            $filterName = substr($key, 7, -1);
            $options['filters'][$filterName] = $value;
        }
    }

    // Get list data for numeric form ID
    $result = FormDataProvider::getListData($formId, $options);

    $json = json_encode($result, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    if ($json === false) {
        echo json_encode(['success' => false, 'error' => 'JSON encoding failed: ' . json_last_error_msg()]);
    } else {
        echo $json;
    }
} catch (Exception $e) {
    Logger::error('API form_list error', [
        'message' => $e->getMessage(),
        'formId' => $formId ?? null,
        'formName' => $formName ?? null,
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
    ]);
}
