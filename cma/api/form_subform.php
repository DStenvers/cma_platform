<?php
/**
 * API Endpoint: Subform Data (lib-table format)
 *
 * GET /cma/api/form_subform.php?formId=X&parentId=Y&subform=0
 * GET /cma/api/form_subform.php?form=formName&parentId=Y&subform=0
 *
 * Returns JSON subform data in the same format as tableData for lib-table web component.
 * Supports pagination, sorting, and filtering.
 * Supports both numeric formId (legacy) and string form names (JSON forms).
 */

use App\Library\Arr;
use App\Library\Request;
use App\Library\Response;
use Cma\Services\ListService;
use Cma\Services\ConfigFormService;
use Cma\Services\Logger;
use Cma\JsonFormLoader;

require_once __DIR__ . '/../bootstrap.inc';

// Set JSON response headers
Response::noCache();
header('Content-Type: application/json; charset=utf-8');

try {
    // Support both numeric formId and string form name
    $formId = Request::queryInt('formId');
    $formName = Request::query('form', '');
    $parentId = Request::queryIntAndGuid('parentId');
    $subformIndex = (int)Request::query('subform', '0');

    if (empty($formId) && empty($formName)) {
        echo json_encode(['success' => false, 'error' => 'FormID or form name is required']);
        exit;
    }

    if (empty($parentId)) {
        echo json_encode(['success' => false, 'error' => 'ParentID is required']);
        exit;
    }

    // If form name is provided, check if it's a JSON config form
    if (!empty($formName)) {
        Logger::debug("form_subform: Request", ['formName' => $formName, 'parentId' => $parentId, 'subformIndex' => $subformIndex]);
        $formDef = JsonFormLoader::load($formName);
        Logger::debug("form_subform: formDef loaded", ['loaded' => $formDef ? 'yes' : 'no']);
        if ($formDef) {
            $isJson = ConfigFormService::isJsonConfigForm($formDef);
            Logger::debug("form_subform: isJsonConfigForm", ['isJson' => $isJson ? 'yes' : 'no', 'database' => $formDef['_json']['database'] ?? 'not set']);
        }
        if ($formDef && ConfigFormService::isJsonConfigForm($formDef)) {
            // Use ConfigFormService for JSON config forms
            Logger::debug("form_subform: Using ConfigFormService::getSubformListData");
            $result = ConfigFormService::getSubformListData($formName, $parentId, $subformIndex);
            Logger::debug("form_subform: Result", ['result' => $result]);
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
            exit;
        }
        // Get numeric formId from form definition, or use form name directly
        $formId = $formDef['sourceFormId'] ?? 0;

        // If no sourceFormId, we can still use the form name directly
        // SubFormGetArray now supports both numeric IDs and form names
        if (empty($formId)) {
            $formId = $formName;
        }
    }

    if (empty($formId)) {
        echo json_encode(['success' => false, 'error' => 'FormID or form name is required']);
        exit;
    }

    // Build options from request parameters
    $options = [];

    // Pagination
    $pageSize = Request::queryInt('pageSize') ?: Request::queryInt('limit');
    if ($pageSize > 0) {
        $options['pageSize'] = $pageSize;
    }

    $lastId = Request::query('lastId', '');
    if ($lastId !== '') {
        $options['lastId'] = $lastId;
    }

    // Sorting
    $sortColumn = Request::query('sortColumn', '');
    if ($sortColumn !== '') {
        $options['sortColumn'] = $sortColumn;
        $options['sortDir'] = Request::query('sortDirection', Request::query('sortDir', 'ASC'));
    }

    // Search
    $search = Request::query('search', '');
    if ($search !== '') {
        $options['search'] = $search;
    }

    // Column filters
    $filtersJson = Request::query('filters', '');
    if ($filtersJson !== '') {
        $filters = json_decode($filtersJson, true);
        if (Arr::isArray($filters)) {
            $options['filters'] = $filters;
        }
    }

    // Get subform data using the new method
    $result = ListService::getSubformTableJson($formId, $parentId, $subformIndex, $options);

    echo json_encode($result, JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    Logger::error('API form_subform error', [
        'message' => $e->getMessage(),
        'formId' => $formId ?? null,
        'formName' => $formName ?? null,
        'parentId' => $parentId ?? null,
        'subformIndex' => $subformIndex ?? null,
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
    ]);
}
