<?php

namespace Cma\Services;

use App\Library\Application;
use App\Library\Arr;
use App\Library\Cache;
use App\Library\Database;
use App\Library\SQL;
use Cma\CmaRepository;
use Cma\FormDefinition;
use Cma\ListMode;
use Cma\Services\PerformanceLogger;

/**
 * List Service
 *
 * Coordinates list/tree/table display for forms.
 * Delegates to specialized services:
 * - TreeService: Tree HTML rendering
 * - TableService: Table HTML/JSON rendering, column preferences
 * - SubformService: Subform data and rendering
 * - JsonFormService: JSON-based form rendering
 *
 * This is the main entry point for list operations, maintaining backward compatibility.
 */
class ListService extends BaseFormService
{
    // ==================== Main List Data Method ====================

    /**
     * Get list data as JSON
     *
     * @param int $formId Form ID
     * @param array $options Options: search, filters, page, pageSize, sortColumn, sortDir, displayMode, userRequestedSearch
     * @return array ['success' => bool, 'items' => array, 'total' => int, 'listMode' => array]
     */
    public static function getListData(int $formId, array $options = []): array
    {
        $startTime = microtime(true);
        PerformanceLogger::startTimer('getListData');

        try {
            // Use consolidated helper - checks access and loads form in one call
            $formResult = self::requireForm($formId);
            if (isset($formResult['error'])) {
                return $formResult;
            }
            $formDef = $formResult['formDef'];
            $arrRep = $formResult['arrRep'];

            // Get display mode from client (stored in localStorage)
            // Default to tree mode (1) if not specified
            $displayMode = (int)($options['displayMode'] ?? ListMode::DISPLAY_TREE);
            if ($displayMode !== ListMode::DISPLAY_TABLE) {
                $displayMode = ListMode::DISPLAY_TREE;
            }

            // Open connection early (needed for record count check)
            CmaRepository::openConnectionById($formDef->getDatabaseId());
            global $conn;

            // Determine filter mode based on form settings and record count
            $filterMode = ListMode::FILTER_NONE;
            $search = $options['search'] ?? '';
            $userRequestedSearch = (bool)($options['userRequestedSearch'] ?? false);
            $filterFieldName = $formDef->getFilterFieldName();

            // Check if user explicitly requested search
            if ($userRequestedSearch && $search === '') {
                $filterMode = ListMode::FILTER_USER_REQUESTED;
            }
            // Check if form has forced filtering via FilterFieldName
            // But allow searching even if filter combo isn't set
            elseif ($filterFieldName !== '' && $filterFieldName !== null && $search === '') {
                // Check if filter is already applied
                $filterValue = $options['filters'][$filterFieldName] ?? '';
                if ($filterValue === '') {
                    $filterMode = ListMode::FILTER_REPOSITORY_FORCED;
                }
            }

            // Build list mode info for response
            $listModeInfo = [
                'displayMode' => $displayMode,
                'filterMode' => $filterMode,
                'showSearchForm' => $filterMode !== ListMode::FILTER_NONE && $search === '',
                'hasGrouping' => ($formDef->getGroup1Field() ?? '') !== '',
                'filterFieldName' => $filterFieldName,
            ];

            // If search form should be shown (forced filtering without search term), return early
            if ($listModeInfo['showSearchForm']) {
                return [
                    'success' => true,
                    'items' => [],
                    'total' => 0,
                    'listMode' => $listModeInfo,
                ];
            }

            // Get the list SQL from form definition
            $listSql = ListServiceHelper::getListSql($formId, $formDef, $options);
            if ($listSql === null) {
                return self::error('Geen lijst query beschikbaar');
            }

            // Apply search filter
            if ($search !== '') {
                $listSql = ListServiceHelper::applySearchFilter($listSql, $search, $formDef);
            }

            // Apply field-specific filters from search panel
            $filters = $options['filters'] ?? [];
            if (!empty($filters)) {
                $listSql = ListServiceHelper::applySearchFilters($listSql, $filters, $arrRep);
            }

            // Apply sorting
            $sortColumn = $options['sortColumn'] ?? '';
            $sortDir = strtoupper($options['sortDir'] ?? 'ASC');
            if ($sortDir !== 'DESC') {
                $sortDir = 'ASC';
            }

            // Validate sortColumn against known field names to prevent SQL injection
            if ($sortColumn !== '') {
                $validColumns = array_map('strval', $arrRep[\Q_FIELDNAME] ?? []);
                if (!in_array($sortColumn, $validColumns, true)) {
                    $sortColumn = '';
                }
            }

            // DEBUG: capture SQL before sorting
            $debugInfo = [
                'sortColumn' => $sortColumn,
                'sortDir' => $sortDir,
                'sqlBeforeSort' => $listSql,
            ];

            if ($sortColumn !== '') {
                // PERFORMANCE FIX: Check for ORDER BY first before running expensive regex
                if (stripos($listSql, 'ORDER BY') !== false) {
                    $listSql = preg_replace('/\s+ORDER\s+BY\s+.+$/i', '', $listSql);
                }
                // FIX: Qualify sort column with table name when query has JOIN to avoid ambiguity
                $tableName = $formDef->getSqlTableName();
                if ($tableName && stripos($listSql, ' JOIN ') !== false) {
                    $listSql .= " ORDER BY [$tableName].[$sortColumn] $sortDir";
                } else {
                    $listSql .= " ORDER BY [$sortColumn] $sortDir";
                }
            }

            $debugInfo['sqlAfterSort'] = $listSql;

            // Limit records: use form-specific limit, request limit, or default (800)
            $formLimit = $formDef->getListLimit();
            $limit = (int)($options['limit'] ?? $formLimit ?? ListMode::LIST_LIMIT);

            // Apply TOP for Access/SQL Server or LIMIT for SQLite - handle DISTINCT properly
            $finalSql = SQL::addTop($listSql, $limit, $conn);

            $debugInfo['finalSql'] = $finalSql;

            // Build cache key from query parameters
            $cacheParams = [
                'formId' => $formId,
                'displayMode' => $displayMode,
                'search' => $search,
                'filters' => $filters ?? [],
                'sortColumn' => $sortColumn,
                'sortDir' => $sortDir,
                'limit' => $limit,
            ];
            $cacheKey = 'list_' . md5(json_encode($cacheParams));
            $cacheTTL = (int)Application::get('list_cache_ttl', 60); // Default 60 seconds

            // Try cache first (with cross-instance invalidation support)
            $cachedItems = Cache::getWithInvalidation($cacheKey, 'lists');
            $fromCache = false;

            if ($cachedItems !== null && Arr::isArray($cachedItems)) {
                // Cache hit
                $items = $cachedItems;
                $fromCache = true;
                $debugInfo['cache'] = 'hit';
            } else {
                // Cache miss - execute query
                $debugInfo['cache'] = 'miss';

                $rs = Database::openRS($finalSql, $conn);
                if ($rs === null) {
                    return ListServiceHelper::queryError($finalSql);
                }

                $items = [];
                $idField = $formDef->getFormIdField();

                while (!$rs->EOF) {
                    $row = [];
                    foreach ($rs->fields as $key => $value) {
                        if (!is_int($key)) {
                            $row[$key] = $value;
                        }
                    }
                    // Ensure ID is always available
                    if ($idField && isset($rs->fields[$idField])) {
                        $row['_id'] = $rs->fields[$idField];
                    }
                    $items[] = $row;
                    $rs->MoveNext();
                }

                // Cache the result (with TTL)
                if (!empty($items) && $cacheTTL > 0) {
                    Cache::setWithInvalidation($cacheKey, $items, 'lists', $cacheTTL);
                }
            }

            $result = [
                'success' => true,
                'items' => $items,
                'total' => count($items),
                'listMode' => $listModeInfo,
                '_debug' => $debugInfo,
            ];

            PerformanceLogger::endTimer('getListData', [
                'formId' => $formId,
                'itemCount' => count($items),
                'displayMode' => $displayMode,
                'fromCache' => $fromCache,
            ]);

            return $result;
        } catch (\Exception $e) {
            PerformanceLogger::endTimer('getListData', ['error' => $e->getMessage()]);
            return self::error($e->getMessage());
        }
    }

    // ==================== Tree Methods (delegate to TreeService) ====================

    /**
     * Get tree HTML for list panel
     */
    public static function getTreeHtml(int $formId, ?int $activeId = null, array $options = []): array
    {
        return TreeService::getTreeHtml($formId, $activeId, $options);
    }

    /**
     * Get tree HTML for JSON form
     */
    public static function getJsonFormTreeHtml(string $formName, string|int|null $activeId = null, array $options = []): array
    {
        return TreeService::getJsonFormTreeHtml($formName, $activeId, $options);
    }

    // ==================== Table Methods (delegate to TableService) ====================

    /**
     * Get table HTML for list panel
     */
    public static function getTableHtml(int|string $formId, ?int $activeId = null, array $options = []): array
    {
        return TableService::getTableHtml($formId, $activeId, $options);
    }

    /**
     * Get table data as JSON for lib-table
     */
    public static function getTableJson(int $formId, array $options = []): array
    {
        return TableService::getTableJson($formId, $options);
    }

    /**
     * Get column preferences for a form
     */
    public static function getColumnPreferences(int|string $formId): array
    {
        return TableService::getColumnPreferences($formId);
    }

    /**
     * Save column preferences for a form
     */
    public static function saveColumnPreferences(int|string $formId, array $columns): bool
    {
        return TableService::saveColumnPreferences($formId, $columns);
    }

    /**
     * Get available columns for column selector
     */
    public static function getAvailableColumns(int $formId): array
    {
        return TableService::getAvailableColumns($formId);
    }

    // ==================== Subform Methods (delegate to SubformService) ====================

    /**
     * Get subform data
     */
    public static function getSubformData(int $formId, string|int $parentId, int $subformIndex): array
    {
        return SubformService::getSubformData($formId, $parentId, $subformIndex);
    }

    /**
     * Get subform table JSON
     */
    public static function getSubformTableJson(int|string $formId, string|int $parentId, int $subformIndex, array $options = []): array
    {
        return SubformService::getSubformTableJson($formId, $parentId, $subformIndex, $options);
    }

    /**
     * Get subform table HTML
     */
    public static function getSubformTableHtml(int|string $formId, string|int $parentId, int $subformIndex): array
    {
        return SubformService::getSubformTableHtml($formId, $parentId, $subformIndex);
    }

    // ==================== JSON Form Methods (delegate to JsonFormService) ====================

    /**
     * Get table HTML for JSON form
     */
    public static function getJsonFormTableHtml(string $formName, string|int|null $activeId = null, array $options = []): array
    {
        return JsonFormService::getTableHtml($formName, $activeId, $options);
    }

    /**
     * Get single row HTML for JSON form
     */
    public static function getJsonFormRowHtml(string $formName, string $recordId, int $displayMode = 2, array $columns = []): array
    {
        return JsonFormService::getRowHtml($formName, $recordId, $displayMode, $columns);
    }

    /**
     * Get available columns for JSON form column selector
     */
    public static function getJsonFormAvailableColumns(string $formName): array
    {
        return JsonFormService::getAvailableColumns($formName);
    }

    /**
     * Get table JSON for JSON form
     */
    public static function getJsonFormTableJson(string $formName, array $options = []): array
    {
        return JsonFormService::getTableJson($formName, $options);
    }

    // ==================== Backward Compatibility Methods ====================

    /**
     * Parse search date from dd-mm-yyyy format to yyyy-mm-dd
     * @deprecated Use ListServiceHelper::parseSearchDate() instead
     */
    protected static function parseSearchDate(string $dateStr): ?string
    {
        return ListServiceHelper::parseSearchDate($dateStr);
    }

    /**
     * Convert database value to boolean
     * @deprecated Use ListServiceHelper::toBool() instead
     */
    protected static function toBool($value): bool
    {
        return ListServiceHelper::toBool($value);
    }

    /**
     * Get database connection for JSON form
     * @deprecated Use ListServiceHelper::getJsonFormConnection() instead
     */
    protected static function getJsonFormConnection(string $database)
    {
        return ListServiceHelper::getJsonFormConnection($database);
    }

    /**
     * Get control type name from control type ID
     * @deprecated Use ListServiceHelper::getControlTypeName() instead
     */
    private static function getControlTypeName(int $controlType): string
    {
        return ListServiceHelper::getControlTypeName($controlType);
    }

    /**
     * Convert control type ID to field type name
     * @deprecated Use ListServiceHelper::controlTypeToFieldType() instead
     */
    private static function controlTypeToFieldType(int $controlType): string
    {
        return ListServiceHelper::controlTypeToFieldType($controlType);
    }

    /**
     * Apply field-specific filters for JSON forms
     * @deprecated Use ListServiceHelper::applyJsonFormFilters() instead
     */
    protected static function applyJsonFormFilters(string $sql, array $filters, array $rawFormDef): string
    {
        return ListServiceHelper::applyJsonFormFilters($sql, $filters, $rawFormDef);
    }
}
