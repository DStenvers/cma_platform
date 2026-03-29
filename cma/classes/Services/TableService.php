<?php

namespace Cma\Services;

use App\Library\Arr;
use App\Library\Cookie;
use App\Library\Database;
use App\Library\Server;
use App\Library\SQL;
use App\Library\Str;
use Cma\CmaRepository;
use Cma\FormControlHelper;
use Cma\FormDefinition;
use Cma\JsonFormLoader;
use Cma\ListMode;

/**
 * Table Service
 *
 * Handles table/grid display for forms.
 * Contains all table-related methods extracted from ListService.
 */
class TableService extends BaseFormService
{
    /**
     * Get table HTML for list panel
     *
     * @param int|string $formId Form ID (int) or JSON form name (string)
     * @param int|null $activeId Currently active record ID
     * @param array $options Options: search, filters, maxColumns, limit, lastId
     * @return array ['success' => bool, 'html' => string, 'count' => int, 'fields' => array, 'hasMore' => bool, 'lastId' => mixed]
     */
    public static function getTableHtml(int|string $formId, ?int $activeId = null, array $options = []): array
    {
        // If string (JSON form name), delegate to JSON form handler
        if (is_string($formId)) {
            return JsonFormService::getTableHtml($formId, $activeId, $options);
        }

        $startTime = microtime(true);
        $timing = [];
        PerformanceLogger::startTimer('getTableHtml');

        try {
            // Use consolidated helper - checks access and loads form in one call
            $formResult = self::requireForm($formId);
            if (isset($formResult['error'])) {
                return $formResult;
            }
            $formDef = $formResult['formDef'];
            $arrRep = $formResult['arrRep'];
            $rights = $formResult['rights'];

            // Use FormDefinition getters
            $formName = $formDef->getFormName() ?? '';
            $idField = $formDef->getFormIdField() ?? 'ID';
            $tableName = $formDef->getSqlTableName() ?? '';
            $detailField = $formDef->getDetailField() ?? '';
            $connectionString = $formDef->getConnectionString();
            $canAdd = $formDef->hasMenuNew();
            $canEdit = $formDef->allowEdit();
            $canCopy = $formDef->hasMenuCopy();
            $canDelete = $formDef->hasMenuDelete();
            $afterPostUrl = $formDef->getAfterPostUrl() ?? '';

            // Open data connection
            \OpenConnection($connectionString);
            global $conn;

            // Get filter settings
            $search = $options['search'] ?? '';
            $filters = $options['filters'] ?? [];
            $filterFieldName = $formDef->getFilterFieldName();

            // Pagination settings for infinite scroll
            $pageSize = (int)($options['limit'] ?? 500);
            $lastId = $options['lastId'] ?? null;
            $isLoadMore = $lastId !== null;

            // Check if filtering is required
            $filterMode = ListMode::FILTER_NONE;

            if ($filterFieldName !== '' && $filterFieldName !== null && $search === '') {
                $filterValue = $filters[$filterFieldName] ?? '';
                if ($filterValue === '') {
                    $filterMode = ListMode::FILTER_REPOSITORY_FORCED;
                }
            }

            // If filtering is required but not provided, return empty table with message
            if ($filterMode !== ListMode::FILTER_NONE) {
                $requiredFilterMissing = false;
                if ($filterFieldName !== '' && $filterFieldName !== null && $search === '') {
                    $filterValue = $filters[$filterFieldName] ?? '';
                    if ($filterValue === '') {
                        $requiredFilterMissing = true;
                    }
                } elseif ($search === '' && empty($filters)) {
                    $requiredFilterMissing = true;
                }

                if ($requiredFilterMissing) {
                    $displayFormName = str_replace('_', ' ', $formName);
                    $filterCaption = $formDef->getFilterCaption() ?? '';

                    if ($filterCaption === '' && $filterFieldName !== '') {
                        $rowCount = $formDef->getRowCount();
                        for ($i = 0; $i < $rowCount; $i++) {
                            if (strtolower($formDef->getFieldName($i) ?? '') === strtolower($filterFieldName ?? '')) {
                                $filterCaption = $formDef->getCaption($i);
                                break;
                            }
                        }
                    }

                    if ($filterMode === ListMode::FILTER_REPOSITORY_FORCED && $filterCaption !== '') {
                        $cleanCaption = preg_replace('/^selecteer\s+de\s+/i', '', $filterCaption);
                        $cleanCaption = ucfirst($cleanCaption);
                        $message = 'Selecteer een ' . lcfirst($cleanCaption);
                    } elseif ($filterMode === ListMode::FILTER_REPOSITORY_FORCED && $filterFieldName !== '') {
                        $message = 'Selecteer ' . str_replace('_', ' ', $filterFieldName);
                    } else {
                        $message = 'Teveel gegevens om allemaal te tonen, gebruik zoeken om gegevens te filteren';
                    }

                    return [
                        'success' => true,
                        'html' => '<div class="filter-required-table">' . htmlspecialchars($message) . '</div>',
                        'count' => 0,
                        'filterMode' => $filterMode,
                        'filterFieldName' => $filterFieldName,
                        'requiresFilter' => true,
                    ];
                }
            }

            // Build columns from form definition fields
            $selectedColumns = $options['columns'] ?? null;
            if ($selectedColumns === null) {
                $selectedColumns = self::getColumnPreferences($formId);
            }

            $maxColumns = (int)($options['maxColumns'] ?? 999);
            $allColumns = [];
            $columns = [];
            $fieldDefs = [];
            $fieldCaptions = [];
            $fieldTypes = [];

            $rowCount = $formDef->getRowCount();

            $skipControlTypes = [
                FormControlHelper::TYPE_GROUPSEPARATOR,
                FormControlHelper::TYPE_LABEL,
                FormControlHelper::TYPE_CHECKLIST,
                FormControlHelper::TYPE_SORTLIST,
                FormControlHelper::TYPE_IMAGE,
                FormControlHelper::TYPE_FILE,
                FormControlHelper::TYPE_THUMBNAIL,
                FormControlHelper::TYPE_DIRECTORY,
            ];

            // Build lookup of all available columns
            for ($i = 0; $i < $rowCount; $i++) {
                $fieldName = $formDef->getFieldName($i);
                $caption = $formDef->getCaption($i);
                $controlType = $formDef->getControlTypeId($i);

                if (empty($fieldName)) continue;
                if (in_array($controlType, $skipControlTypes)) continue;
                if (strtolower($fieldName) === strtolower($idField)) continue;

                $schemaDataType = $arrRep[\Q_SCHEMA_DATATYPE][$i] ?? '';
                if (strtolower((string)$schemaDataType) === 'guid' || $schemaDataType === '72' || (int)$schemaDataType === 72) continue;

                $allColumns[$fieldName] = [
                    'caption' => $caption ?: $fieldName,
                    'controlType' => $controlType,
                    'index' => $i,
                ];
            }

            // If user has selected columns, use those (in order)
            if (!empty($selectedColumns)) {
                foreach ($selectedColumns as $colName) {
                    if (isset($allColumns[$colName])) {
                        $columns[] = $colName;
                        $fieldCaptions[$colName] = $allColumns[$colName]['caption'];
                        $fieldTypes[$colName] = $allColumns[$colName]['controlType'];

                        $idx = $allColumns[$colName]['index'];
                        $maxChars = $formDef->get('fieldMaxChars', $idx);
                        $isDate = $formDef->isDateField($idx);
                        $isNumber = $formDef->isNumericField($idx);
                        $fieldDefs[] = [
                            'name' => $colName,
                            'caption' => $allColumns[$colName]['caption'],
                            'type' => $isDate ? 'date' : ($isNumber ? 'number' : ListServiceHelper::getFieldTypeName($allColumns[$colName]['controlType'])),
                            'required' => $formDef->isRequired($idx),
                            'readonly' => $formDef->isReadOnly($idx),
                            'maxLength' => $maxChars ? (int)$maxChars : 255,
                        ];
                    }
                }
            } else {
                // Default: first N columns (excluding lookup fields for cleaner default view)
                $defaultSkipTypes = [
                    FormControlHelper::TYPE_COMBOBOX,
                    FormControlHelper::TYPE_USERLIST,
                    FormControlHelper::TYPE_XMLSTORE,
                    FormControlHelper::TYPE_MEMO,
                ];

                foreach ($allColumns as $fieldName => $colInfo) {
                    if (count($columns) >= $maxColumns) break;
                    if (in_array($colInfo['controlType'], $defaultSkipTypes)) continue;
                    if ($filterFieldName !== '' && $filterFieldName !== null && strtolower($fieldName) === strtolower($filterFieldName)) continue;

                    $columns[] = $fieldName;
                    $fieldCaptions[$fieldName] = $colInfo['caption'];
                    $fieldTypes[$fieldName] = $colInfo['controlType'];

                    $idx = $colInfo['index'];
                    $maxChars = $formDef->get('fieldMaxChars', $idx);
                    $isDate = $formDef->isDateField($idx);
                    $isNumber = $formDef->isNumericField($idx);
                    $fieldDefs[] = [
                        'name' => $fieldName,
                        'caption' => $colInfo['caption'],
                        'type' => $isDate ? 'date' : ($isNumber ? 'number' : ListServiceHelper::getFieldTypeName($colInfo['controlType'])),
                        'required' => $formDef->isRequired($idx),
                        'readonly' => $formDef->isReadOnly($idx),
                        'maxLength' => $maxChars ? (int)$maxChars : 255,
                    ];
                }
            }

            // Build SQL query - select specific fields
            $selectFields = [$idField];
            foreach ($columns as $col) {
                if (!in_array($col, $selectFields)) {
                    $selectFields[] = "[$col]";
                }
            }

            $sql = 'SELECT ' . implode(', ', $selectFields) . ' FROM [' . $tableName . ']';

            // Apply search filter
            if ($search !== '') {
                $searchConditions = [];
                foreach ($columns as $col) {
                    $searchConditions[] = "[$col] LIKE " . SQL::postString('%' . $search . '%');
                }
                if (!empty($searchConditions)) {
                    $sql .= ' WHERE (' . implode(' OR ', $searchConditions) . ')';
                }
            }

            // Apply field-specific filters from search panel
            if (!empty($filters)) {
                $sql = ListServiceHelper::applySearchFilters($sql, $filters, $arrRep);
            }

            // Get order direction from form definition
            $orderDir = $formDef->getOrderDirection();

            // Apply keyset pagination for infinite scroll
            if ($lastId !== null) {
                $hasWhere = stripos($sql, 'WHERE') !== false;
                $lastIdEscaped = is_numeric($lastId) ? (int)$lastId : SQL::postString($lastId);
                $comparison = $orderDir === 'DESC' ? '<' : '>';
                $paginationCondition = "[$idField] $comparison $lastIdEscaped";
                $sql .= $hasWhere ? " AND $paginationCondition" : " WHERE $paginationCondition";
            }

            // Add ORDER BY for consistent pagination
            $sql .= " ORDER BY [$idField] $orderDir";

            // Apply TOP limit - fetch one extra to detect if there are more rows
            $sql = SQL::addTop($sql, $pageSize + 1, $conn);

            $timing['prepare'] = round((microtime(true) - $startTime) * 1000, 1);
            $queryStart = microtime(true);

            $rs = Database::openRS($sql, $conn);
            if ($rs === null) {
                return ListServiceHelper::queryError($sql);
            }

            $timing['query'] = round((microtime(true) - $queryStart) * 1000, 1);
            $timing['conn_status'] = Database::getLastConnectionStatus();
            $renderStart = microtime(true);

            // Build table HTML with inline editing support
            $displayFormName = str_replace('_', ' ', $formName);
            $html = '';

            // Only output table wrapper and header on initial load
            if (!$isLoadMore) {
                $html = '<lib-table><table class="listtable inline-editable filtering sorttable" id="listTable" data-form-id="' . $formId . '" data-name="' . Server::htmlEncode($displayFormName) . '" cellspacing="0" cellpadding="0">';

                // Header row
                $html .= '<thead><tr class="listheader">';
                $isFirstCol = true;
                foreach ($columns as $col) {
                    $caption = $fieldCaptions[$col] ?? $col;
                    $controlType = $fieldTypes[$col] ?? 0;
                    $dataType = self::getColumnDataType($controlType, $allColumns[$col]['index'] ?? null, $arrRep);
                    $filterAttr = $isFirstCol ? ' data-filter="N"' : '';
                    $html .= '<th data-field="' . Server::htmlEncode($col) . '" data-type="' . $dataType . '"' . $filterAttr . '>' . Server::htmlEncode($caption) . '</th>';
                    $isFirstCol = false;
                }
                $html .= '</tr></thead>';
            }

            // Collect FK values for batch lookup
            $comboColumns = [];
            $fkValues = [];
            foreach ($columns as $col) {
                $controlType = $fieldTypes[$col] ?? 0;
                if ($controlType == FormControlHelper::TYPE_COMBOBOX || $controlType == FormControlHelper::TYPE_USERLIST) {
                    $comboColumns[$col] = true;
                    $fkValues[$col] = [];
                }
            }

            // First pass: collect all rows and FK values
            $rows = [];
            $fkValuesSet = [];
            foreach ($comboColumns as $col => $flag) {
                $fkValuesSet[$col] = [];
            }

            while (!$rs->EOF) {
                $row = $rs->fields;
                $rowLower = array_change_key_case($row, CASE_LOWER);
                $rowLower = Str::toUtf8($rowLower);
                $rows[] = $rowLower;

                // Collect FK values using set for O(1) duplicate check
                foreach ($comboColumns as $col => $flag) {
                    $value = trim((string)($rowLower[strtolower($col)] ?? ''));
                    if ($value !== '' && !isset($fkValuesSet[$col][$value])) {
                        $fkValuesSet[$col][$value] = true;
                        $fkValues[$col][] = $value;
                    }
                }
                $rs->MoveNext();
            }

            // Batch load combo options
            $comboOptions = [];
            if (!empty($fkValues)) {
                $comboOptions = OptionsService::getComboOptionsForFields($formId, array_keys($fkValues), $arrRep);

                // Fetch missing IDs directly from source tables
                foreach ($fkValues as $fieldName => $ids) {
                    $comboOptions = self::fetchMissingComboIds($fieldName, $ids, $comboOptions, $arrRep, $conn);
                }
            }

            // Build lookup maps
            $fkLookup = [];
            foreach ($comboOptions as $fieldName => $options) {
                $fkLookup[$fieldName] = [];
                $fkLookup[strtolower($fieldName)] = [];
                if (Arr::isArray($options)) {
                    foreach ($options as $opt) {
                        $id = (string)($opt['id'] ?? $opt['value'] ?? '');
                        $text = $opt['text'] ?? $opt['label'] ?? $id;
                        if ($id !== '') {
                            $fkLookup[$fieldName][$id] = $text;
                            $fkLookup[strtolower($fieldName)][$id] = $text;
                        }
                    }
                }
            }

            // Detect if there are more rows
            $hasMore = count($rows) > $pageSize;
            if ($hasMore) {
                array_pop($rows);
            }

            // Data rows
            if (!$isLoadMore) {
                $html .= '<tbody>';
            }
            $count = 0;
            $lastRowId = null;
            foreach ($rows as $rowLower) {
                $recordId = $rowLower[strtolower($idField)] ?? '';
                $lastRowId = $recordId;

                $activeClass = ($activeId !== null && $activeId == $recordId) ? ' active' : '';
                $html .= '<tr class="listrow' . $activeClass . '" data-id="' . Server::htmlEncode($recordId) . '">';

                foreach ($columns as $col) {
                    $value = $rowLower[strtolower($col)] ?? '';
                    $controlType = $fieldTypes[$col] ?? 0;
                    $html .= self::renderTableCell($col, $value, $controlType, $comboColumns, $fkLookup, $rights);
                }

                $html .= '</tr>';
                $count++;
            }

            // Close table structure only on initial load
            if (!$isLoadMore) {
                $html .= '</tbody></table></lib-table>';
            }

            if ($count === 0 && !$isLoadMore) {
                $filterDesc = self::buildFilterDescription($search, $filters, $allColumns, $fieldCaptions, $comboOptions);
                $html = '<div class="no-data">Geen gegevens om weer te geven' . $filterDesc . '</div>';
            }

            $timing['render'] = round((microtime(true) - $renderStart) * 1000, 1);
            $timing['total'] = round((microtime(true) - $startTime) * 1000, 1);
            $timing['rows'] = $count;

            PerformanceLogger::endTimer('getTableHtml', [
                'formId' => $formId,
                'count' => $count,
                'hasMore' => $hasMore,
                'isLoadMore' => $isLoadMore,
            ]);

            // Build addRelatedForms mapping for inline edit plus buttons
            $addRelatedForms = [];
            if ($rights >= SecurityHelper::ACCESS_FULL) {
                $rowCount = count($arrRep[\Q_FIELDNAME] ?? []);
                for ($i = 0; $i < $rowCount; $i++) {
                    $fName = $arrRep[\Q_FIELDNAME][$i] ?? '';
                    $ctrlType = (int)($arrRep[\Q_CONTROLTYPEID][$i] ?? 0);
                    $srcTable = $arrRep[\Q_SOURCETABLE][$i] ?? '';
                    if ($fName && $srcTable && in_array($ctrlType, [FormControlHelper::TYPE_COMBOBOX, FormControlHelper::TYPE_USERLIST])) {
                        $addFormId = CmaRepository::getFormIdBySourceTable($srcTable, $formId);
                        if ($addFormId !== null) {
                            $addFormName = JsonFormLoader::getFormNameBySourceId($addFormId);
                            if ($addFormName !== null) {
                                $addRelatedForms[$fName] = $addFormName;
                            }
                        }
                    }
                }
            }

            $result = [
                'success' => true,
                'html' => $html,
                'count' => $count,
                'displayMode' => ListMode::DISPLAY_TABLE,
                'hasGrouping' => ($formDef->getGroup1Field() ?? '') !== '',
                'fields' => $fieldDefs,
                'permissions' => [
                    'canAdd' => $canAdd && $rights >= SecurityHelper::ACCESS_FULL,
                    'canEdit' => $canEdit && $rights >= SecurityHelper::ACCESS_FULL,
                    'canCopy' => $canCopy && $rights >= SecurityHelper::ACCESS_FULL,
                    'canDelete' => $canDelete && $rights >= SecurityHelper::ACCESS_FULL,
                ],
                'afterPostUrl' => $afterPostUrl,
                'comboOptions' => $comboOptions,
                '_timing' => $timing,
                'hasMore' => $hasMore,
                'lastId' => $lastRowId,
                'pageSize' => $pageSize,
            ];

            if (!empty($addRelatedForms)) {
                $result['addRelatedForms'] = $addRelatedForms;
            }

            if ($isLoadMore) {
                unset($result['fields']);
                unset($result['permissions']);
                unset($result['comboOptions']);
                unset($result['addRelatedForms']);
            }

            return $result;
        } catch (\Exception $e) {
            PerformanceLogger::endTimer('getTableHtml', ['error' => $e->getMessage()]);
            return self::error($e->getMessage());
        }
    }

    /**
     * Get table data as JSON for lib-table web component
     *
     * @param int $formId Form ID
     * @param array $options Options: search, filters, lastId, limit, columns, sortColumn, sortDir
     * @return array ['success' => bool, 'rows' => array, 'columns' => array, 'hasMore' => bool, 'lastId' => mixed]
     */
    public static function getTableJson(int $formId, array $options = []): array
    {
        try {
            $formResult = self::requireForm($formId);
            if (isset($formResult['error'])) {
                return $formResult;
            }
            $formDef = $formResult['formDef'];
            $arrRep = $formResult['arrRep'];
            $rights = $formResult['rights'];

            $formName = $formDef->getFormName() ?? '';
            $idField = $formDef->getFormIdField() ?? 'ID';
            $tableName = $formDef->getSqlTableName() ?? '';
            $filterFieldName = $formDef->getFilterFieldName();
            $connectionString = $formDef->getConnectionString();

            \OpenConnection($connectionString);
            global $conn;

            // Get pagination and filter options
            $search = $options['search'] ?? '';
            $filters = $options['filters'] ?? [];
            $pageSize = (int)($options['limit'] ?? 500);
            $lastId = $options['lastId'] ?? null;
            $sortColumn = $options['sortColumn'] ?? '';
            $sortDir = strtoupper($options['sortDir'] ?? 'ASC');
            if ($sortDir !== 'DESC') {
                $sortDir = 'ASC';
            }

            // Check if filtering is required
            $filterMode = ListMode::FILTER_NONE;
            if ($filterFieldName !== '' && $filterFieldName !== null) {
                $filterValue = $filters[$filterFieldName] ?? '';
                if ($filterValue === '') {
                    $filterMode = ListMode::FILTER_REPOSITORY_FORCED;
                }
            }

            if ($filterMode !== ListMode::FILTER_NONE) {
                $requiredFilterMissing = false;
                if ($filterFieldName !== '' && $filterFieldName !== null) {
                    if (($filters[$filterFieldName] ?? '') === '') {
                        $requiredFilterMissing = true;
                    }
                }

                if ($requiredFilterMissing) {
                    $filterCaption = $formDef->getFilterCaption() ?? '';
                    if ($filterMode === ListMode::FILTER_REPOSITORY_FORCED && $filterCaption !== '') {
                        $cleanCaption = preg_replace('/^selecteer\s+de\s+/i', '', $filterCaption);
                        $message = 'Selecteer een ' . lcfirst(ucfirst($cleanCaption));
                    } else {
                        $message = 'Teveel gegevens om allemaal te tonen, gebruik zoeken om gegevens te filteren';
                    }

                    return [
                        'success' => true,
                        'rows' => [],
                        'columns' => [],
                        'hasMore' => false,
                        'lastId' => null,
                        'filterMode' => $filterMode,
                        'filterRequired' => true,
                        'filterMessage' => $message,
                    ];
                }
            }

            // Build columns from form definition
            $selectedColumns = $options['columns'] ?? null;
            if ($selectedColumns === null) {
                $selectedColumns = self::getColumnPreferences($formId);
            }

            $maxColumns = (int)($options['maxColumns'] ?? 999);
            $allColumns = [];
            $columns = [];
            $fieldTypes = [];

            $rowCount = $formDef->getRowCount();
            $skipControlTypes = [
                FormControlHelper::TYPE_GROUPSEPARATOR,
                FormControlHelper::TYPE_LABEL,
                FormControlHelper::TYPE_CHECKLIST,
                FormControlHelper::TYPE_SORTLIST,
                FormControlHelper::TYPE_IMAGE,
                FormControlHelper::TYPE_FILE,
                FormControlHelper::TYPE_THUMBNAIL,
                FormControlHelper::TYPE_DIRECTORY,
            ];

            for ($i = 0; $i < $rowCount; $i++) {
                $fieldName = $formDef->getFieldName($i);
                $caption = $formDef->getCaption($i);
                $controlType = $formDef->getControlTypeId($i);

                if (empty($fieldName)) continue;
                if (in_array($controlType, $skipControlTypes)) continue;
                if (strtolower($fieldName) === strtolower($idField)) continue;

                $schemaDataType = $arrRep[\Q_SCHEMA_DATATYPE][$i] ?? '';
                if (strtolower((string)$schemaDataType) === 'guid' || $schemaDataType === '72' || (int)$schemaDataType === 72) continue;

                $isDate = $formDef->isDateField($i);

                $allColumns[$fieldName] = [
                    'caption' => $caption ?: $fieldName,
                    'controlType' => $controlType,
                    'index' => $i,
                    'isDate' => $isDate,
                ];
            }

            // Determine which columns to include
            if (!empty($selectedColumns)) {
                foreach ($selectedColumns as $colName) {
                    if (isset($allColumns[$colName])) {
                        $columns[] = $colName;
                        $fieldTypes[$colName] = $allColumns[$colName]['controlType'];
                    }
                }
            } else {
                $defaultSkipTypes = [
                    FormControlHelper::TYPE_COMBOBOX,
                    FormControlHelper::TYPE_USERLIST,
                    FormControlHelper::TYPE_XMLSTORE,
                    FormControlHelper::TYPE_MEMO,
                ];

                foreach ($allColumns as $fieldName => $colInfo) {
                    if (count($columns) >= $maxColumns) break;
                    if (in_array($colInfo['controlType'], $defaultSkipTypes)) continue;
                    if ($filterFieldName !== '' && $filterFieldName !== null && strtolower($fieldName) === strtolower($filterFieldName)) continue;
                    $columns[] = $fieldName;
                    $fieldTypes[$fieldName] = $colInfo['controlType'];
                }
            }

            // Build column definitions for web component
            $columnDefs = [];
            foreach ($columns as $col) {
                $colInfo = $allColumns[$col] ?? null;
                if (!$colInfo) continue;

                $dataType = 'text';
                $controlType = $colInfo['controlType'];
                if ($controlType == FormControlHelper::TYPE_CHECKBOX) {
                    $dataType = 'boolean';
                } elseif ($controlType == FormControlHelper::TYPE_COMBOBOX || $controlType == FormControlHelper::TYPE_USERLIST) {
                    $dataType = 'select';
                } elseif ($colInfo['isDate']) {
                    $dataType = 'date';
                }

                $columnDefs[] = [
                    'field' => $col,
                    'label' => $colInfo['caption'],
                    'type' => $dataType,
                    'sortable' => true,
                    'filterable' => true,
                    'resizable' => true,
                ];
            }

            // Build SQL query
            $selectFields = ["[$idField]"];
            foreach ($columns as $col) {
                $selectFields[] = "[$col]";
            }

            $sql = 'SELECT ' . implode(', ', $selectFields) . ' FROM [' . $tableName . ']';

            // Apply search filter
            if ($search !== '') {
                $searchConditions = [];
                foreach ($columns as $col) {
                    $searchConditions[] = "[$col] LIKE " . SQL::postString('%' . $search . '%');
                }
                if (!empty($searchConditions)) {
                    $sql .= ' WHERE (' . implode(' OR ', $searchConditions) . ')';
                }
            }

            // Apply field-specific filters
            if (!empty($filters)) {
                $sql = ListServiceHelper::applySearchFilters($sql, $filters, $arrRep);
            }

            // Apply keyset pagination
            if ($lastId !== null) {
                $hasWhere = stripos($sql, 'WHERE') !== false;
                $lastIdEscaped = is_numeric($lastId) ? (int)$lastId : SQL::postString($lastId);
                $comparison = $sortDir === 'DESC' ? '<' : '>';
                $paginationCondition = "[$idField] $comparison $lastIdEscaped";
                $sql .= $hasWhere ? " AND $paginationCondition" : " WHERE $paginationCondition";
            }

            // Add ORDER BY
            if ($sortColumn !== '' && $sortColumn !== $idField) {
                $sql .= " ORDER BY [$sortColumn] $sortDir, [$idField] $sortDir";
            } else {
                $sql .= " ORDER BY [$idField] " . ($sortDir === 'DESC' ? 'DESC' : 'ASC');
            }

            $sql = SQL::addTop($sql, $pageSize + 1, $conn);

            $rs = Database::openRS($sql, $conn);
            if ($rs === null) {
                return ListServiceHelper::queryError($sql);
            }

            // Collect FK values for batch lookup
            $comboColumns = [];
            $fkValues = [];
            foreach ($columns as $col) {
                $controlType = $fieldTypes[$col] ?? 0;
                if ($controlType == FormControlHelper::TYPE_COMBOBOX || $controlType == FormControlHelper::TYPE_USERLIST) {
                    $comboColumns[$col] = true;
                    $fkValues[$col] = [];
                }
            }

            // First pass: collect rows and FK values
            $rows = [];
            $rowCount = 0;
            $newLastId = null;
            $fkValuesSet = [];
            foreach ($comboColumns as $col => $flag) {
                $fkValuesSet[$col] = [];
            }

            while (!$rs->EOF) {
                $rowCount++;
                if ($rowCount > $pageSize) {
                    break;
                }

                $row = $rs->fields;
                $rowLower = array_change_key_case($row, CASE_LOWER);
                $rowLower = Str::toUtf8($rowLower);

                $newLastId = $rowLower[strtolower($idField)] ?? null;

                $cleanRow = [
                    '_id' => $newLastId,
                ];

                foreach ($columns as $col) {
                    $value = $rowLower[strtolower($col)] ?? '';
                    $cleanRow[$col] = $value;

                    if (isset($comboColumns[$col]) && trim((string)$value) !== '') {
                        if (!isset($fkValuesSet[$col][$value])) {
                            $fkValuesSet[$col][$value] = true;
                            $fkValues[$col][] = $value;
                        }
                    }
                }

                $rows[] = $cleanRow;
                $rs->MoveNext();
            }

            $hasMore = $rowCount > $pageSize;

            // Batch load combo options
            $comboLookups = [];
            if (!empty($fkValues)) {
                $comboOptions = OptionsService::getComboOptionsForFields($formId, array_keys($fkValues), $arrRep);

                foreach ($comboOptions as $fieldName => $options) {
                    $comboLookups[$fieldName] = [];
                    foreach ($options as $opt) {
                        $comboLookups[$fieldName][(string)$opt['id']] = $opt['text'];
                    }
                }

                foreach ($rows as &$row) {
                    foreach ($comboLookups as $fieldName => $lookup) {
                        $value = (string)($row[$fieldName] ?? '');
                        if ($value !== '' && isset($lookup[$value])) {
                            $row[$fieldName . '_display'] = $lookup[$value];
                        }
                    }
                }
                unset($row);
            }

            return [
                'success' => true,
                'rows' => $rows,
                'columns' => $columnDefs,
                'allColumns' => array_map(function($col, $info) {
                    return [
                        'field' => $col,
                        'label' => $info['caption'],
                    ];
                }, array_keys($allColumns), $allColumns),
                'hasMore' => $hasMore,
                'lastId' => $newLastId,
                'total' => count($rows),
                'idField' => $idField,
                'formName' => str_replace('_', ' ', $formName),
                'canAdd' => $formDef->hasMenuNew() && $rights >= SecurityHelper::ACCESS_FULL,
                'canEdit' => $formDef->allowEdit() && $rights >= SecurityHelper::ACCESS_FULL,
                'canDelete' => $formDef->hasMenuDelete() && $rights >= SecurityHelper::ACCESS_FULL,
            ];
        } catch (\Exception $e) {
            return self::error($e->getMessage());
        }
    }

    /**
     * Get user's column preferences
     *
     * @param int|string $formId Form ID or JSON form name
     * @return array Selected column names, empty if using defaults
     */
    public static function getColumnPreferences(int|string $formId): array
    {
        $cookieName = 'cma_cols_' . $formId;
        $cookieValue = Cookie::get($cookieName, '');

        if ($cookieValue === '') {
            return [];
        }

        return array_filter(explode(',', $cookieValue));
    }

    /**
     * Save column preferences
     *
     * @param int|string $formId Form ID or JSON form name
     * @param array $columns Column names to save
     * @return bool Success
     */
    public static function saveColumnPreferences(int|string $formId, array $columns): bool
    {
        $cookieName = 'cma_cols_' . $formId;
        $cookieValue = implode(',', $columns);

        // Save for 1 year
        Cookie::set($cookieName, $cookieValue, 365 * 24 * 60 * 60);
        return true;
    }

    /**
     * Get all available columns for a form (for column selector UI)
     *
     * @param int $formId Form ID
     * @return array ['success' => bool, 'columns' => array, 'selected' => array]
     */
    public static function getAvailableColumns(int $formId): array
    {
        try {
            $formResult = self::requireForm($formId);
            if (isset($formResult['error'])) {
                return $formResult;
            }
            $formDef = $formResult['formDef'];
            $arrRep = $formResult['arrRep'];

            $idField = $formDef->getFormIdField() ?? 'ID';

            $columns = [];
            $skipControlTypes = [
                FormControlHelper::TYPE_GROUPSEPARATOR,
                FormControlHelper::TYPE_LABEL,
                FormControlHelper::TYPE_CHECKLIST,
                FormControlHelper::TYPE_SORTLIST,
                FormControlHelper::TYPE_IMAGE,
                FormControlHelper::TYPE_FILE,
                FormControlHelper::TYPE_THUMBNAIL,
                FormControlHelper::TYPE_DIRECTORY,
                FormControlHelper::TYPE_XMLSTORE,
                FormControlHelper::TYPE_PASSWORD,
            ];

            $rowCount = $formDef->getRowCount();
            for ($i = 0; $i < $rowCount; $i++) {
                $fieldName = $formDef->getFieldName($i);
                $caption = $formDef->getCaption($i);
                $controlType = $formDef->getControlTypeId($i);

                if (empty($fieldName)) continue;
                if (in_array($controlType, $skipControlTypes)) continue;
                if (strtolower($fieldName) === strtolower($idField)) continue;

                $schemaDataType = $arrRep[\Q_SCHEMA_DATATYPE][$i] ?? '';
                if (strtolower((string)$schemaDataType) === 'guid' || $schemaDataType === '72' || (int)$schemaDataType === 72) continue;

                $isDate = $formDef->isDateField($i);
                $isNumber = $formDef->isNumericField($i);
                $fieldType = $isDate ? 'date' : ($isNumber ? 'number' : ListServiceHelper::getFieldTypeName($controlType));

                $columns[] = [
                    'name' => $fieldName,
                    'caption' => $caption ?: $fieldName,
                    'type' => $fieldType,
                ];
            }

            $selected = self::getColumnPreferences($formId);
            if (empty($selected)) {
                $selected = array_column($columns, 'name');
            }

            return [
                'success' => true,
                'columns' => $columns,
                'selected' => $selected,
            ];
        } catch (\Exception $e) {
            return self::error($e->getMessage());
        }
    }

    // =========================================================================
    // Private helper methods
    // =========================================================================

    /**
     * Get column data type for table header
     */
    private static function getColumnDataType(int $controlType, ?int $fieldIndex, array $arrRep): string
    {
        $dataType = 'text';
        if ($controlType == FormControlHelper::TYPE_CHECKBOX) {
            $dataType = 'boolean';
        } elseif ($controlType == FormControlHelper::TYPE_COMBOBOX || $controlType == FormControlHelper::TYPE_USERLIST) {
            $dataType = 'combobox';
        }

        if ($fieldIndex !== null) {
            $schemaType = $arrRep[\Q_SCHEMA_DATATYPE][$fieldIndex] ?? '';
            $schemaTypeLower = strtolower((string)$schemaType);
            $isTimeField = false;
            $isDateField = false;
            $isNumberField = false;

            if (is_numeric($schemaType) && (int)$schemaType === 134) {
                $isTimeField = true;
            } elseif ($schemaTypeLower === 'time') {
                $isTimeField = true;
            }

            if (!$isTimeField && is_numeric($schemaType) && in_array((int)$schemaType, [7, 133, 135])) {
                $isDateField = true;
            } elseif (!$isTimeField && in_array($schemaTypeLower, ['date', 'datetime', 'datetime2', 'smalldatetime', 'datetimeoffset'])) {
                $isDateField = true;
            }

            if (!$isDateField && !$isTimeField && is_numeric($schemaType) && in_array((int)$schemaType, [2, 3, 4, 5, 6, 14, 17, 18, 19, 20, 21, 131])) {
                $isNumberField = true;
            } elseif (in_array($schemaTypeLower, ['int', 'integer', 'bigint', 'smallint', 'tinyint', 'decimal', 'numeric', 'float', 'real', 'money', 'number'])) {
                $isNumberField = true;
            }

            if ($isTimeField) {
                $dataType = 'time';
            } elseif ($isDateField) {
                $dataType = 'date';
            } elseif ($isNumberField) {
                $dataType = 'number';
            }
        }

        return $dataType;
    }

    /**
     * Render a table cell
     */
    private static function renderTableCell(string $col, $value, int $controlType, array $comboColumns, array $fkLookup, int $rights): string
    {
        $dataType = 'text';

        if ($controlType == FormControlHelper::TYPE_CHECKBOX) {
            $boolVal = is_bool($value) ? $value : (
                $value === 1 || $value === '1' || $value === -1 || $value === '-1' ||
                strtolower((string)$value) === 'true'
            );
            $dataType = 'boolean';
            $canEditField = $rights >= SecurityHelper::ACCESS_FULL;
            if ($canEditField) {
                $switchHtml = '<lib-switch data-field="' . Server::htmlEncode($col) . '"' .
                    ($boolVal ? ' checked' : '') . '></lib-switch>';
                return '<td data-field="' . Server::htmlEncode($col) . '" data-type="' . $dataType . '" data-value="' . ($boolVal ? '1' : '0') . '">' . $switchHtml . '</td>';
            } else {
                $displayValue = $boolVal ? 'Ja' : 'Nee';
                return '<td data-field="' . Server::htmlEncode($col) . '" data-type="' . $dataType . '">' . Server::htmlEncode($displayValue) . '</td>';
            }
        } elseif (isset($comboColumns[$col])) {
            $fkValue = trim((string)$value);
            $dataType = 'combobox';
            if ($fkValue !== '') {
                $displayValue = $fkLookup[$col][$fkValue]
                    ?? $fkLookup[strtolower($col)][$fkValue]
                    ?? $fkValue;
                return '<td data-field="' . Server::htmlEncode($col) . '" data-type="' . $dataType . '" data-fk-value="' . Server::htmlEncode($fkValue) . '">' . Server::htmlEncode($displayValue) . '</td>';
            } else {
                return '<td data-field="' . Server::htmlEncode($col) . '" data-type="' . $dataType . '"></td>';
            }
        } else {
            $origValue = $value;
            $displayValue = \App\Library\Date::fixValue($value);
            if ($displayValue !== $origValue && $displayValue !== '' && preg_match('/^\d{2}-\d{2}-\d{4}/', $displayValue)) {
                $dataType = 'date';
            }
            if (strlen($displayValue) > 50) {
                $displayValue = substr($displayValue, 0, 47) . '...';
            }
            return '<td data-field="' . Server::htmlEncode($col) . '" data-type="' . $dataType . '">' . Server::htmlEncode($displayValue) . '</td>';
        }
    }

    /**
     * Fetch missing combo IDs from source tables
     */
    private static function fetchMissingComboIds(string $fieldName, array $ids, array $comboOptions, array $arrRep, $conn): array
    {
        $optCount = isset($comboOptions[$fieldName]) ? count($comboOptions[$fieldName]) : 0;
        $missingIds = [];

        if ($optCount > 0 && isset($comboOptions[$fieldName])) {
            $loadedIds = array_column($comboOptions[$fieldName], 'id');
            $loadedIdsLookup = array_flip(array_map('strval', $loadedIds));
            foreach ($ids as $id) {
                if (!isset($loadedIdsLookup[(string)$id])) {
                    $missingIds[] = $id;
                }
            }
        } elseif ($optCount === 0) {
            $missingIds = $ids;
        }

        if (!empty($missingIds)) {
            $fieldIndex = OptionsService::findFieldIndex($arrRep, $fieldName);
            if ($fieldIndex !== -1) {
                $sourceTable = $arrRep[\Q_SOURCETABLE][$fieldIndex] ?? '';
                $idField = $arrRep[\Q_CTRLIDFIELD][$fieldIndex] ?? 'ID';
                $displayField = $arrRep[\Q_FOREIGNIDFIELD][$fieldIndex] ?? '';

                if (!empty($displayField) && !empty($sourceTable)) {
                    $sqlList = $arrRep[\Q_SQLLIST][$fieldIndex] ?? '';
                    $isAlias = !empty($sqlList) && preg_match('/\s+as\s+' . preg_quote($displayField, '/') . '\b/i', $sqlList);
                    $safeDisplayField = $displayField;

                    if ($isAlias) {
                        $commonNameFields = ['Naam', 'Name', 'Omschrijving', 'Description', 'Descr', 'Title', 'Titel', 'Label'];
                        $safeDisplayField = $idField;
                        foreach ($commonNameFields as $nameField) {
                            if (stripos($sqlList, $sourceTable . '.' . $nameField) !== false ||
                                stripos($sqlList, '[' . $nameField . ']') !== false) {
                                $safeDisplayField = $nameField;
                                break;
                            }
                        }
                    }

                    $missingIdsStr = implode(',', array_map('intval', $missingIds));
                    $missingSQL = "SELECT [$idField], [$safeDisplayField] FROM [$sourceTable] WHERE [$idField] IN ($missingIdsStr)";

                    $missingRs = Database::openRS($missingSQL, $conn);
                    if ($missingRs !== null) {
                        while (!$missingRs->EOF) {
                            $row = $missingRs->fields;
                            $row = Str::toUtf8($row);
                            $rowValues = array_values($row);
                            $mId = $rowValues[0] ?? '';
                            $mText = $rowValues[1] ?? $rowValues[0] ?? '';
                            if ($mId !== '') {
                                $comboOptions[$fieldName][] = [
                                    'id' => $mId,
                                    'text' => trim($mText),
                                ];
                            }
                            $missingRs->MoveNext();
                        }
                    }
                }
            }
        }

        return $comboOptions;
    }

    /**
     * Build filter description for "no data" message
     */
    private static function buildFilterDescription(string $search, array $filters, array $allColumns, array $fieldCaptions, array $comboOptions): string
    {
        $filterDesc = '';
        if (!empty($search)) {
            $filterDesc = ' voor zoekopdracht \'' . Server::htmlEncode($search) . '\'';
        } elseif (!empty($filters)) {
            $filterParts = [];
            foreach ($filters as $fieldName => $value) {
                if ($value !== '' && $value !== null) {
                    $caption = $allColumns[$fieldName]['caption'] ?? $fieldCaptions[$fieldName] ?? $fieldName;
                    $displayValue = (string)$value;

                    if (isset($comboOptions[$fieldName]) && Arr::isArray($comboOptions[$fieldName])) {
                        foreach ($comboOptions[$fieldName] as $opt) {
                            if ((string)($opt['id'] ?? '') === (string)$value) {
                                $displayValue = $opt['text'] ?? $displayValue;
                                break;
                            }
                        }
                    }

                    $filterParts[] = $caption . ' = \'' . Server::htmlEncode($displayValue) . '\'';
                }
            }
            if (!empty($filterParts)) {
                $filterDesc = ' voor filter: ' . implode(', ', $filterParts);
            }
        }
        return $filterDesc;
    }
}
