<?php

namespace Cma\Services;

use App\Library\Arr;
use App\Library\Database;
use App\Library\Server;
use App\Library\SQL;
use Cma\CmaRepository;
use Cma\JsonFormLoader;
use Cma\ListMode;
use Cma\SecurityHelper;

/**
 * JSON Form Service
 *
 * Handles JSON-based form display and operations (database: "data", "users", etc).
 * For JSON config forms (database: "json"), delegates to ConfigFormService.
 */
class JsonFormService extends BaseFormService
{
    /**
     * Get tree HTML for JSON form
     *
     * @param string $formName JSON form name
     * @param string|int|null $activeId Active record ID (can be GUID or integer)
     * @param array $options Options
     * @return array ['success' => bool, 'html' => string, 'count' => int]
     */
    public static function getTreeHtml(string $formName, string|int|null $activeId = null, array $options = []): array
    {
        // Tree view is handled by TreeService
        return TreeService::getJsonFormTreeHtml($formName, $activeId, $options);
    }

    /**
     * Get table HTML for JSON form
     *
     * @param string $formName JSON form name
     * @param string|int|null $activeId Active record ID (can be GUID or integer)
     * @param array $options Options
     * @return array ['success' => bool, 'html' => string, 'count' => int, 'fields' => array]
     */
    public static function getTableHtml(string $formName, string|int|null $activeId = null, array $options = []): array
    {
        try {
            // Load JSON form definition (raw for fields, converted for _json)
            $formDef = JsonFormLoader::load($formName);
            if ($formDef === null) {
                return self::error("Formulier '$formName' niet gevonden");
            }

            // Also load raw JSON for field definitions (inline editing needs this)
            $rawFormDef = JsonFormLoader::loadRaw($formName);

            $jsonData = $formDef['_json'] ?? [];
            $tableName = $jsonData['table'] ?? '';
            $idField = $jsonData['idField'] ?? 'ID';
            $database = $jsonData['database'] ?? '';
            $listQuery = $jsonData['listQuery'] ?? '';
            $listColumns = $jsonData['listColumns'] ?? [];

            // Build field lookup for type detection from legacy format (has Q_SCHEMA_DATATYPE computed)
            $fieldsByName = [];
            $fieldCount = count($formDef[Q_FIELDNAME] ?? []);
            for ($i = 0; $i < $fieldCount; $i++) {
                $fieldName = $formDef[Q_FIELDNAME][$i] ?? '';
                if ($fieldName) {
                    $fieldsByName[strtolower($fieldName)] = [
                        'name' => $fieldName,
                        'type' => self::controlTypeToFieldType((int)($formDef[Q_CONTROLTYPEID][$i] ?? 0)),
                        'caption' => $formDef[Q_CAPTION][$i] ?? $fieldName,
                        'dataType' => $formDef[Q_SCHEMA_DATATYPE][$i] ?? '',
                        'dateFormat' => $formDef[Q_SCHEMA_DATE_PREC][$i] ?? '',
                        'numericPrecision' => $formDef[Q_SCHEMA_NUM_PREC][$i] ?? '',
                        'required' => (bool)($formDef[Q_ISREQUIRED][$i] ?? false),
                        'readonly' => (bool)($formDef[Q_FLDREADONLY][$i] ?? false),
                        'maxLength' => (int)($formDef[Q_SCHEMA_CHAR_MAXL][$i] ?? 255),
                        'controlType' => (int)($formDef[Q_CONTROLTYPEID][$i] ?? 0),
                    ];
                }
            }

            // Also populate fieldsByName from JSON fields (for JSON-only forms or fields not in legacy format)
            foreach ($jsonData['fields'] ?? [] as $field) {
                $fieldName = $field['name'] ?? '';
                if ($fieldName && !isset($fieldsByName[strtolower($fieldName)])) {
                    $fieldsByName[strtolower($fieldName)] = [
                        'name' => $fieldName,
                        'type' => $field['type'] ?? 'textbox',
                        'caption' => $field['caption'] ?? $fieldName,
                        'dataType' => $field['dataType'] ?? '',
                        'dateFormat' => $field['dateFormat'] ?? '',
                    ];
                }
            }

            // Table view ALWAYS uses fields array (same as detail screen)
            // listQuery is only for tree view
            // Check for user-selected columns (from field chooser)
            $selectedColumns = $options['columns'] ?? null;
            if ($selectedColumns === null) {
                $selectedColumns = TableService::getColumnPreferences($formName);
            }

            if (empty($listColumns)) {
                if (!empty($selectedColumns)) {
                    // User has selected specific columns - use those
                    // But only if they still exist in the current form definition
                    $memoSkipTypes = ['memo', 'htmlstrip'];
                    foreach ($selectedColumns as $colName) {
                        $lcName = strtolower($colName);
                        $field = $fieldsByName[$lcName] ?? null;

                        // Skip columns that no longer exist in form definition
                        // This handles removed fields (like isBeheer after migration)
                        if ($field === null) {
                            continue;
                        }

                        // Skip memo fields - can't be queried alongside other columns in Access ODBC
                        $fieldType = $field['type'] ?? 'textbox';
                        if (in_array($fieldType, $memoSkipTypes)) {
                            continue;
                        }

                        $colType = self::detectColumnType($field);

                        $listColumns[] = [
                            'field' => $colName,
                            'title' => $field['caption'] ?? ucfirst($colName),
                            'type' => $colType,
                        ];
                    }
                }

                // If no valid columns from preferences (or all were invalid), build from fields array
                if (empty($listColumns) && !empty($jsonData['fields'])) {
                    // Build from fields array (default columns)
                    $maxCols = (int)($options['maxColumns'] ?? 999);
                    $skipTypes = ['groupseparator', 'label', 'checklist', 'sortlist', 'image', 'file', 'thumbnail', 'directory', 'memo', 'xmlstore', 'custom', 'password', 'ignorefield'];
                    // Skip the filter field in default columns - when filtering is required,
                    // all rows have the same filter value so it's not useful to display
                    // Check both filterIdName (legacy) and filter.field (new format)
                    $filterIdName = strtolower($jsonData['filterIdName'] ?? '');
                    if ($filterIdName === '' && isset($jsonData['filter']['field'])) {
                        $filterIdName = strtolower($jsonData['filter']['field']);
                    }
                    $colCount = 0;
                    foreach ($jsonData['fields'] as $field) {
                        if ($colCount >= $maxCols) break;
                        $fieldType = $field['type'] ?? 'textbox';
                        $fieldName = $field['name'] ?? '';
                        if (empty($fieldName) || strpos($fieldName, '_group') === 0) continue;
                        if (in_array($fieldType, $skipTypes)) continue;
                        if (strtolower($fieldName) === strtolower($idField)) continue;
                        // Respect skipInTableView property from form definition
                        if (!empty($field['skipInTableView'])) continue;
                        // Skip filter field - not useful in table view when filtering is required
                        if ($filterIdName !== '' && strtolower($fieldName) === $filterIdName) continue;

                        $colType = self::detectColumnType($field);

                        $listColumns[] = [
                            'field' => $fieldName,
                            'title' => $field['caption'] ?? $fieldName,
                            'type' => $colType,
                        ];
                        $colCount++;
                    }
                }
            }

            // Check access rights using sourceFormId if available
            $sourceFormId = $jsonData['sourceFormId'] ?? null;
            if ($sourceFormId) {
                // Use consolidated helper for access check
                $accessError = self::checkFormAccess($sourceFormId);
                if ($accessError !== null) {
                    return $accessError;
                }
            } elseif (!SecurityHelper::isAdmin()) {
                // For system forms without sourceFormId, require admin
                return self::error('Geen toegang tot dit formulier');
            }

            // Check if this is a JSON config form
            if ($database === 'json') {
                return self::getJsonConfigTableHtml($formName, $activeId, $options, $jsonData, $rawFormDef);
            }

            if (empty($tableName) && empty($listQuery)) {
                return self::error('Formulier heeft geen tabel gedefinieerd');
            }

            // Check if filtering is required (e.g., must select opleiding first)
            // Note: filterIdName is for passing filter values TO subforms, not requiring filter on THIS form
            // Only use filter.field for explicit filter requirement
            $filterFieldName = $jsonData['filter']['field'] ?? '';
            $filterDescription = $jsonData['filter']['description'] ?? '';
            $search = $options['search'] ?? '';
            $filters = $options['filters'] ?? [];

            if ($filterFieldName !== '' && $search === '') {
                $filterValue = $filters[$filterFieldName] ?? '';
                if ($filterValue === '') {
                    // Filter required but not provided - show message instead of loading all records
                    $displayFormName = $jsonData['title'] ?? ucfirst(str_replace('_', ' ', $formName));

                    // Build message from filter description
                    if ($filterDescription !== '') {
                        $message = $filterDescription;
                    } else {
                        $message = 'Selecteer een ' . lcfirst(str_replace('fk', '', $filterFieldName));
                    }

                    return [
                        'success' => true,
                        'html' => '<div class="filter-required-table">' . Server::htmlEncode($message) . '</div>',
                        'count' => 0,
                        'filterMode' => ListMode::FILTER_REPOSITORY_FORCED,
                        'filterFieldName' => $filterFieldName,
                        'requiresFilter' => true,
                    ];
                }
            }

            // Open database connection
            $conn = ListServiceHelper::getJsonFormConnection($database);
            if ($conn === null) {
                return self::error('Database connectie mislukt');
            }

            // Pagination settings for infinite scroll
            $pageSize = (int)($options['limit'] ?? 500); // Default 500 rows per page
            $lastId = $options['lastId'] ?? null;
            $isLoadMore = $lastId !== null;

            // Build SQL: prefer table-based query, fall back to listQuery
            if (!empty($tableName)) {
                $columns = ListServiceHelper::buildJsonColumnList($listColumns, $idField);
                $sql = "SELECT $columns FROM [$tableName]";
            } elseif (!empty($listQuery)) {
                // Strip existing ORDER BY - we add our own for pagination
                $sql = preg_replace('/\s+ORDER\s+BY\s+.+$/is', '', $listQuery);
            } else {
                return self::error('Formulier heeft geen tabel of listQuery');
            }

            // Apply search filter
            $search = $options['search'] ?? '';
            if ($search !== '') {
                $searchConditions = [];
                $searchEscaped = SQL::postString('%' . $search . '%');

                // Use quickSearchFields if defined (guaranteed text-searchable fields)
                $quickSearchFields = $jsonData['quickSearchFields'] ?? '';
                if ($quickSearchFields !== '') {
                    $fields = array_filter(array_map('trim', explode(',', $quickSearchFields)));
                    foreach ($fields as $fieldName) {
                        $searchConditions[] = "[$fieldName] LIKE $searchEscaped";
                    }
                } elseif (!empty($listColumns)) {
                    // Fallback: search list columns, but skip non-text types to avoid type mismatch
                    $nonTextTypes = ['checkbox', 'radiogroup', 'number', 'integer', 'combobox', 'select', 'userlist'];
                    foreach ($listColumns as $col) {
                        $fieldName = $col['field'] ?? '';
                        if (!$fieldName) continue;

                        // Check the actual field type from form definition
                        $fieldDef = $fieldsByName[strtolower($fieldName)] ?? null;
                        if ($fieldDef) {
                            $fieldType = $fieldDef['type'] ?? 'textbox';
                            if (in_array($fieldType, $nonTextTypes)) continue;
                        }

                        $searchConditions[] = "[$fieldName] LIKE $searchEscaped";
                    }
                }

                if (!empty($searchConditions)) {
                    $sql = SQL::addWhere($sql, '(' . implode(' OR ', $searchConditions) . ')');
                }
            }

            // Apply field-specific filters from search panel
            $filters = $options['filters'] ?? [];
            if (!empty($filters) && $rawFormDef) {
                $sql = ListServiceHelper::applyJsonFormFilters($sql, $filters, $rawFormDef);
            }

            // Get order direction from form definition (default ASC)
            $orderDir = strtoupper($jsonData['orderDirection'] ?? 'ASC');
            if ($orderDir !== 'DESC') $orderDir = 'ASC';

            // FIX: Qualify idField with table name when query has JOIN to avoid ambiguous column error
            $hasJoin = stripos($sql, ' JOIN ') !== false;
            $effectiveTable = $tableName;
            if (!$effectiveTable && $hasJoin && preg_match('/\bFROM\s+\[?(\w+)\]?/i', $sql, $m)) {
                $effectiveTable = $m[1];
            }
            $qualifiedIdField = ($hasJoin && $effectiveTable) ? "[$effectiveTable].[$idField]" : "[$idField]";

            // Apply keyset pagination for infinite scroll
            if ($lastId !== null) {
                $hasWhere = stripos($sql, 'WHERE') !== false;
                $lastIdEscaped = is_numeric($lastId) ? (int)$lastId : SQL::postString($lastId);
                // For DESC order, we need ID < lastId; for ASC, ID > lastId
                $comparison = $orderDir === 'DESC' ? '<' : '>';
                $paginationCondition = "$qualifiedIdField $comparison $lastIdEscaped";
                $sql .= $hasWhere ? " AND $paginationCondition" : " WHERE $paginationCondition";
            }

            // Get total count for initial load (before adding ORDER BY and TOP)
            $totalCount = null;
            if (!$isLoadMore) {
                // Build count SQL from current query (before ORDER BY)
                $countSql = preg_replace('/^SELECT\s+.+?\s+FROM/is', 'SELECT COUNT(*) FROM', $sql);
                $countRs = Database::openRS($countSql, $conn);
                if ($countRs && !$countRs->EOF) {
                    $countRow = $countRs->fetchAssoc();
                    $totalCount = (int)(reset($countRow) ?: 0);
                }
            }

            // Add ORDER BY for consistent pagination
            $sql .= " ORDER BY $qualifiedIdField $orderDir";

            // Apply TOP limit - fetch one extra to detect if there are more rows
            $sql = SQL::addTop($sql, $pageSize + 1, $conn);

            // Execute query
            $rs = Database::openRS($sql, $conn);
            if ($rs === null) {
                return self::error('Query uitvoering mislukt: ' . Database::getLastError() . "\n" . $sql);
            }

            // Build table HTML
            $displayName = $jsonData['title'] ?? str_replace('_', ' ', $formName);
            $html = '';

            // Only output table wrapper and header on initial load (not loadMore)
            if (!$isLoadMore) {
                $html = '<lib-table><table class="listtable inline-editable filtering sorttable" id="listTable" data-json-form="' . htmlspecialchars($formName) . '" data-name="' . htmlspecialchars($displayName) . '">';

                // Header row
                $html .= '<thead><tr class="listheader">';
                foreach ($listColumns as $col) {
                    $title = $col['title'] ?? $col['field'] ?? '';
                    $width = $col['width'] ?? '';
                    $colType = $col['type'] ?? 'text';
                    $fieldName = $col['field'] ?? '';
                    $style = $width ? ' style="width:' . $width . '"' : '';
                    $html .= '<th data-field="' . htmlspecialchars($fieldName) . '" data-type="' . htmlspecialchars($colType) . '"' . $style . '>' . htmlspecialchars($title) . '</th>';
                }
                $html .= '</tr></thead>';
            }

            // Identify combo columns and collect FK values (first pass)
            $comboColumns = [];
            $fkValues = [];
            // Identify radiogroup columns and build lookup from options
            $radioColumns = [];
            $radioLookup = [];
            foreach ($listColumns as $col) {
                $fieldName = $col['field'] ?? '';
                $fieldLower = strtolower($fieldName);
                $fieldInfo = $fieldsByName[$fieldLower] ?? null;
                if ($fieldInfo && in_array($fieldInfo['type'] ?? '', ['combobox', 'userlist'])) {
                    $comboColumns[$fieldName] = true;
                    $fkValues[$fieldName] = [];
                }
                // Check for radiogroup/radio fields (both use static options)
                $fieldType = $fieldInfo['type'] ?? '';
                if ($fieldInfo && ($fieldType === 'radiogroup' || $fieldType === 'radio')) {
                    $radioColumns[$fieldName] = true;
                    // Build lookup from options in raw form definition
                    if ($rawFormDef) {
                        foreach ($rawFormDef['fields'] ?? [] as $f) {
                            if (strtolower($f['name'] ?? '') === $fieldLower) {
                                $radioLookup[$fieldName] = [];
                                foreach ($f['options'] ?? [] as $opt) {
                                    $optValue = (string)($opt['value'] ?? '');
                                    $optText = $opt['text'] ?? $opt['label'] ?? $optValue;
                                    $radioLookup[$fieldName][$optValue] = $optText;
                                }
                                break;
                            }
                        }
                    }
                }
            }

            // First pass: collect rows (limited by pageSize) and FK values
            $rows = [];
            $fkValuesSet = [];
            $rowCount = 0;
            $lastRowId = null;
            foreach ($comboColumns as $col => $flag) {
                $fkValuesSet[$col] = [];
            }

            while (!$rs->EOF) {
                $rowCount++;

                // If we fetched the extra row (pageSize + 1), we have more data - stop here
                if ($rowCount > $pageSize) {
                    break;
                }

                $fields = \App\Library\Str::toUtf8($rs->fetchAssoc());
                $rows[] = $fields;

                // Track last row ID for pagination
                $lastRowId = $fields[$idField] ?? $fields[strtolower($idField)] ?? null;

                // Collect FK values
                foreach ($comboColumns as $col => $flag) {
                    $value = trim((string)($fields[$col] ?? $fields[strtolower($col)] ?? $fields[strtoupper($col)] ?? ''));
                    if ($value !== '' && !isset($fkValuesSet[$col][$value])) {
                        $fkValuesSet[$col][$value] = true;
                        $fkValues[$col][] = $value;
                    }
                }
                $rs->MoveNext();
            }

            // Detect if there are more rows (we fetched pageSize + 1)
            $hasMore = $rowCount > $pageSize;

            // Load combo options for FK fields using JSON field definitions
            $comboOptions = [];
            $fkLookup = [];
            if (!empty($fkValues) && $rawFormDef) {
                // Get field definitions from raw JSON
                $rawFields = $rawFormDef['fields'] ?? [];
                $rawFieldsByName = [];
                foreach ($rawFields as $f) {
                    $name = $f['name'] ?? '';
                    if ($name) {
                        $rawFieldsByName[strtolower($name)] = $f;
                    }
                }

                // Query each combo field's options
                foreach ($comboColumns as $fieldName => $flag) {
                    $fieldLower = strtolower($fieldName);
                    $fieldDef = $rawFieldsByName[$fieldLower] ?? null;
                    if (!$fieldDef) continue;

                    // Check for optionsSource.type = "jsonConfig" first
                    $optionsSource = $fieldDef['optionsSource'] ?? null;
                    if ($optionsSource && ($optionsSource['type'] ?? '') === 'jsonConfig') {
                        $configFile = $optionsSource['configFile'] ?? '';
                        $configArrayKey = $optionsSource['configArrayKey'] ?? '';
                        $valueField = $optionsSource['valueField'] ?? 'id';
                        $labelField = $optionsSource['labelField'] ?? 'name';

                        if ($configFile && $configArrayKey) {
                            $options = ConfigFormService::getOptionsFromConfig($configFile, $configArrayKey, $valueField, $labelField);
                            if (!empty($options)) {
                                $comboOptions[$fieldName] = $options;
                            }
                        }
                        continue;
                    }

                    // Always extract id/display field names from definition (used for result processing)
                    $fkIdField = $fieldDef['idField'] ?? 'ID';
                    $displayField = $fieldDef['displayField'] ?? $fkIdField;

                    // Support both 'sql' and 'dataSource' properties (dataSource is used in form definitions)
                    $optSql = $fieldDef['sql'] ?? $fieldDef['dataSource'] ?? '';
                    if (empty($optSql)) {
                        // Build SQL from sourceTable, idField, displayField
                        $sourceTable = $fieldDef['sourceTable'] ?? '';
                        if ($sourceTable) {
                            $optSql = "SELECT [$fkIdField], [$displayField] FROM [$sourceTable] ORDER BY [$displayField]";
                        }
                    }

                    if (empty($optSql)) continue;

                    // Check APCu cache first (5-minute TTL)
                    $apcuKey = 'combo_opts_' . md5($optSql);
                    if (function_exists('apcu_fetch')) {
                        $cached = apcu_fetch($apcuKey);
                        if ($cached !== false) {
                            $comboOptions[$fieldName] = $cached;
                            continue;
                        }
                    }

                    try {
                        $optRs = Database::openRS($optSql, $conn);
                        if ($optRs) {
                            $comboOptions[$fieldName] = [];
                            while (!$optRs->EOF) {
                                $row = \App\Library\Str::toUtf8($optRs->fetchAssoc());

                                // Use case-insensitive field access (database may return different case)
                                $optId = self::getFieldCaseInsensitive($row, $fkIdField);
                                $optText = self::getFieldCaseInsensitive($row, $displayField);

                                // Show placeholder if text is null (can happen with NULL values in database)
                                if ($optText === null || $optText === '') {
                                    $optText = '[Geen omschrijving beschikbaar]';
                                }
                                $optId = (string)$optId;
                                if ($optId !== '') {
                                    $comboOptions[$fieldName][] = [
                                        'id' => $optId,
                                        'text' => trim($optText),
                                    ];
                                }
                                $optRs->MoveNext();
                            }

                            // Store in APCu cache (5-minute TTL)
                            if (function_exists('apcu_store')) {
                                apcu_store($apcuKey, $comboOptions[$fieldName], 300);
                            }
                        }
                    } catch (\Exception $e) {
                        Logger::debug('Combo options fetch failed', ['field' => $fieldName, 'error' => $e->getMessage()]);
                    }
                }

                // Build lookup maps
                foreach ($comboOptions as $fieldName => $comboOpts) {
                    $fkLookup[$fieldName] = [];
                    if (Arr::isArray($comboOpts)) {
                        foreach ($comboOpts as $opt) {
                            $id = (string)($opt['id'] ?? $opt['value'] ?? '');
                            $text = $opt['text'] ?? $opt['label'] ?? $id;
                            if ($id !== '') {
                                $fkLookup[$fieldName][$id] = $text;
                            }
                        }
                    }
                }
            }

            // Add radiogroup options to comboOptions for inline editing
            // Radio options are static (defined in form definition), not from database
            foreach ($radioLookup as $fieldName => $options) {
                $comboOptions[$fieldName] = [];
                foreach ($options as $value => $text) {
                    $comboOptions[$fieldName][] = [
                        'id' => $value,
                        'text' => $text,
                    ];
                }
            }

            // Data rows (second pass with resolved FK values)
            // Only output tbody wrapper on initial load (not loadMore)
            if (!$isLoadMore) {
                $html .= '<tbody>';
            }
            $count = 0;
            // Check if form is editable (for inline switch toggles)
            $hasFullAccess = SecurityHelper::isAdmin();
            foreach ($rows as $fields) {
                $recordId = $fields[$idField] ?? $fields[strtolower($idField)] ?? $fields[strtoupper($idField)] ?? '';
                $isActive = ($activeId !== null && $recordId == $activeId);
                $rowClass = 'listrow' . ($isActive ? ' active' : '');

                $html .= '<tr class="' . $rowClass . '" data-id="' . htmlspecialchars($recordId) . '">';

                // Menu trigger goes inside the first data cell
                $menuTrigger = '<span class="row-menu-trigger" data-id="' . htmlspecialchars($recordId) . '">&#8942;</span>';
                $isFirstCol = true;

                foreach ($listColumns as $col) {
                    $fieldName = $col['field'] ?? '';
                    // Get value with case-insensitive fallback (database may return different case)
                    $value = $fields[$fieldName] ?? $fields[strtolower($fieldName)] ?? $fields[strtoupper($fieldName)] ?? '';
                    $colType = $col['type'] ?? 'text';
                    $detectedType = $colType;
                    $prefix = $isFirstCol ? $menuTrigger : '';
                    $isFirstCol = false;

                    // Format value based on type
                    if ($colType === 'boolean') {
                        $boolVal = ($value === true || $value === 1 || $value === -1 ||
                            strtolower((string)$value) === 'true' || $value === '1' || $value === '-1');
                        if ($hasFullAccess) {
                            // Render as clickable toggle switch using lib-switch web component
                            $switchHtml = '<lib-switch data-field="' . htmlspecialchars($fieldName) . '"' .
                                ($boolVal ? ' checked' : '') . '></lib-switch>';
                            $html .= '<td data-field="' . htmlspecialchars($fieldName) . '" data-type="boolean" data-value="' . ($boolVal ? '1' : '0') . '">' . $prefix . $switchHtml . '</td>';
                        } else {
                            $value = $boolVal ? 'Ja' : 'Nee';
                            $html .= '<td data-field="' . htmlspecialchars($fieldName) . '" data-type="boolean">' . $prefix . htmlspecialchars((string)$value) . '</td>';
                        }
                    } elseif (isset($comboColumns[$fieldName])) {
                        // Combobox/FK field: use pre-loaded lookup
                        $fkValue = trim((string)$value);
                        $detectedType = 'combobox';
                        if ($fkValue !== '') {
                            $displayValue = $fkLookup[$fieldName][$fkValue] ?? $fkValue;
                            $html .= '<td data-field="' . htmlspecialchars($fieldName) . '" data-type="combobox" data-fk-value="' . htmlspecialchars($fkValue) . '">' . $prefix . htmlspecialchars($displayValue) . '</td>';
                        } else {
                            $html .= '<td data-field="' . htmlspecialchars($fieldName) . '" data-type="combobox">' . $prefix . '</td>';
                        }
                    } elseif (isset($radioColumns[$fieldName])) {
                        // Radiogroup field: use lookup from options
                        $radioValue = trim((string)$value);
                        $displayValue = $radioLookup[$fieldName][$radioValue] ?? $radioValue;
                        $html .= '<td data-field="' . htmlspecialchars($fieldName) . '" data-type="radiogroup">' . $prefix . htmlspecialchars($displayValue) . '</td>';
                    } elseif ($colType === 'lookup' && isset($col['lookup'])) {
                        // Use inline lookup table to translate value
                        $lookup = $col['lookup'];
                        $lookupKey = (string)$value;
                        $value = $lookup[$lookupKey] ?? $value;
                        $html .= '<td data-field="' . htmlspecialchars($fieldName) . '" data-type="' . htmlspecialchars($detectedType) . '">' . $prefix . htmlspecialchars((string)$value) . '</td>';
                    } elseif ($colType === 'date' || $colType === 'datetime') {
                        // Format dates as dd-mm-yyyy
                        $value = \App\Library\Date::fixValue($value);
                        $html .= '<td data-field="' . htmlspecialchars($fieldName) . '" data-type="' . htmlspecialchars($detectedType) . '">' . $prefix . htmlspecialchars((string)$value) . '</td>';
                    } else {
                        // Try to detect and format dates in other fields
                        $origValue = $value;
                        $value = \App\Library\Date::fixValue($value);
                        // If value was reformatted, it's a date
                        if ($value !== $origValue && $value !== '' && preg_match('/^\d{2}-\d{2}-\d{4}/', $value)) {
                            $detectedType = 'date';
                        }
                        $html .= '<td data-field="' . htmlspecialchars($fieldName) . '" data-type="' . htmlspecialchars($detectedType) . '">' . $prefix . htmlspecialchars((string)$value) . '</td>';
                    }
                }

                $html .= '</tr>';
                $count++;
            }

            // Close table structure only on initial load (not loadMore)
            if (!$isLoadMore) {
                $html .= '</tbody></table></lib-table>';
                if ($count === 0) {
                    $html .= '<div class="no-data">Geen gegevens gevonden</div>';
                }
            }

            // Build field definitions for inline editing from $fieldsByName (already built from legacy format)
            $fieldDefs = [];
            $skipTypes = ['groupseparator', 'label', 'hidden', 'password', 'image', 'file', 'checklist', 'sortlist', 'checklisttree', 'checklistinline', 'custom', 'tip', 'ignorefield'];

            // Build lookup for newOnly from JSON fields (not in legacy format)
            $newOnlyFields = [];
            foreach ($jsonData['fields'] ?? [] as $jf) {
                if (!empty($jf['newOnly'])) {
                    $newOnlyFields[strtolower($jf['name'] ?? '')] = true;
                }
            }

            foreach ($fieldsByName as $field) {
                $fieldName = $field['name'] ?? '';
                $fieldType = $field['type'] ?? 'textbox';

                if (empty($fieldName)) continue;
                if (in_array($fieldType, $skipTypes)) continue;
                if (strtolower($fieldName) === strtolower($idField)) continue;

                // Determine inline edit type - field type is primary source, dataType is fallback
                $dataType = 'text';
                if ($fieldType === 'checkbox') {
                    $dataType = 'boolean';
                } elseif ($fieldType === 'combobox' || $fieldType === 'userlist') {
                    $dataType = 'combobox';
                } elseif ($fieldType === 'radiogroup' || $fieldType === 'radio') {
                    $dataType = 'radiogroup';
                } elseif ($fieldType === 'time') {
                    // Time must be checked BEFORE date (time fields have dataType='date' but should use timepicker)
                    $dataType = 'time';
                } elseif ($fieldType === 'date' || !empty($field['dateFormat']) || in_array(strtolower($field['dataType'] ?? ''), ['date', 'datetime', 'smalldatetime', 'datetime2'])) {
                    $dataType = 'date';
                } elseif ($fieldType === 'memo') {
                    $dataType = 'memo';
                } elseif (in_array(strtolower($field['dataType'] ?? ''), ['int', 'integer', 'bigint', 'smallint', 'tinyint', 'decimal', 'numeric', 'float', 'real', 'money'])) {
                    $dataType = 'number';
                } elseif (!empty($field['numericPrecision'])) {
                    $dataType = 'number';
                }

                $fieldDefs[] = [
                    'name' => $fieldName,
                    'caption' => $field['caption'] ?? $fieldName,
                    'type' => $dataType,
                    'controlType' => $fieldType,
                    'required' => $field['required'] ?? false,
                    'readonly' => $field['readonly'] ?? false,
                    'newOnly' => isset($newOnlyFields[strtolower($fieldName)]),
                    'maxLength' => $field['maxLength'] ?? 255,
                ];
            }

            // Check permissions based on rights
            $hasFullAccess = isset($rights) ? $rights >= SecurityHelper::ACCESS_FULL : SecurityHelper::isAdmin();

            // Build base response
            $groupFields = $jsonData['groupFields'] ?? [];
            $response = [
                'success' => true,
                'html' => $html,
                'count' => $count,
                'displayMode' => ListMode::DISPLAY_TABLE,
                'hasGrouping' => !empty($groupFields),
                // Pagination data for infinite scroll
                'hasMore' => $hasMore,
                'lastId' => $lastRowId,
                'pageSize' => $pageSize,
                'totalCount' => $totalCount,
            ];

            // For loadMore requests, we only need the HTML and pagination info
            if ($isLoadMore) {
                return $response;
            }

            // Full response for initial load includes all metadata
            $response['fields'] = $fieldDefs;
            $response['permissions'] = [
                'canAdd' => ($jsonData['allowAdd'] ?? true) && $hasFullAccess,
                'canEdit' => ($jsonData['allowEdit'] ?? true) && $hasFullAccess,
                'canCopy' => ($jsonData['allowCopy'] ?? false) && $hasFullAccess,
                'canDelete' => ($jsonData['allowDelete'] ?? true) && $hasFullAccess,
            ];
            $response['comboOptions'] = $comboOptions; // For inline editing with Select2

            // Build addRelatedForms mapping: fieldName => formName for combobox plus buttons
            $addRelatedForms = [];
            if ($hasFullAccess && !empty($rawFormDef)) {
                $rawFields = $rawFormDef['fields'] ?? [];
                $currentFormId = $sourceFormId ? (int)$sourceFormId : null;
                foreach ($rawFields as $field) {
                    $fieldName = $field['name'] ?? '';
                    $fieldType = $field['type'] ?? '';
                    $sourceTable = $field['sourceTable'] ?? '';
                    if ($fieldName && $sourceTable && in_array($fieldType, ['combobox', 'userlist'])) {
                        $addFormId = CmaRepository::getFormIdBySourceTable($sourceTable, $currentFormId);
                        if ($addFormId !== null) {
                            $addFormName = JsonFormLoader::getFormNameBySourceId($addFormId);
                            if ($addFormName !== null) {
                                $addRelatedForms[$fieldName] = $addFormName;
                            }
                        }
                    }
                }
            }
            if (!empty($addRelatedForms)) {
                $response['addRelatedForms'] = $addRelatedForms;
            }

            return $response;

        } catch (\Exception $e) {
            return self::error($e->getMessage());
        }
    }

    /**
     * Get single row HTML for JSON form (for inline editing updates)
     *
     * @param string $formName JSON form name
     * @param string $recordId Record ID
     * @param int $displayMode Display mode (1=tree, 2=table)
     * @return array ['success' => bool, 'html' => string]
     */
    public static function getRowHtml(string $formName, string $recordId, int $displayMode = 2, array $columns = []): array
    {
        try {
            $formDef = JsonFormLoader::load($formName);
            if ($formDef === null) {
                return self::error("Formulier '$formName' niet gevonden");
            }

            $jsonData = $formDef['_json'] ?? [];
            $tableName = $jsonData['table'] ?? '';
            $idField = $jsonData['idField'] ?? 'ID';
            $database = $jsonData['database'] ?? '';
            $listColumns = $jsonData['listColumns'] ?? [];

            // For JSON config forms (database: "json"), use ConfigFormService
            if (strtolower($database) === 'json') {
                return self::getJsonConfigRowHtml($formName, $recordId, $displayMode, $jsonData, $formDef);
            }

            // Build field lookup for type detection
            $fieldsByName = [];
            $fieldCount = count($formDef[Q_FIELDNAME] ?? []);
            for ($i = 0; $i < $fieldCount; $i++) {
                $fieldName = $formDef[Q_FIELDNAME][$i] ?? '';
                if ($fieldName) {
                    $fieldsByName[strtolower($fieldName)] = [
                        'name' => $fieldName,
                        'type' => self::controlTypeToFieldType((int)($formDef[Q_CONTROLTYPEID][$i] ?? 0)),
                        'caption' => $formDef[Q_CAPTION][$i] ?? $fieldName,
                        'dataType' => $formDef[Q_SCHEMA_DATATYPE][$i] ?? '',
                    ];
                }
            }

            // Also populate from JSON fields (for fields not in legacy format)
            foreach ($jsonData['fields'] ?? [] as $field) {
                $fieldName = $field['name'] ?? '';
                if ($fieldName && !isset($fieldsByName[strtolower($fieldName)])) {
                    $fieldsByName[strtolower($fieldName)] = [
                        'name' => $fieldName,
                        'type' => $field['type'] ?? 'textbox',
                        'caption' => $field['caption'] ?? $fieldName,
                        'dataType' => $field['dataType'] ?? '',
                    ];
                }
            }

            // If caller passed explicit columns, use those (e.g. from inline edit refresh)
            if (!empty($columns)) {
                $listColumns = [];
                foreach ($columns as $colName) {
                    $lcName = strtolower($colName);
                    $field = $fieldsByName[$lcName] ?? null;
                    if ($field === null) {
                        continue;
                    }

                    $colType = self::detectColumnType($field);
                    $listColumns[] = [
                        'field' => $colName,
                        'title' => $field['caption'] ?? ucfirst($colName),
                        'type' => $colType,
                    ];
                }
            }

            // Get column preferences if no explicit columns and no listColumns from definition
            if (empty($listColumns)) {
                $selectedColumns = TableService::getColumnPreferences($formName);
                if (!empty($selectedColumns)) {
                    $memoSkipTypes = ['memo', 'htmlstrip'];
                    foreach ($selectedColumns as $colName) {
                        $lcName = strtolower($colName);
                        $field = $fieldsByName[$lcName] ?? null;
                        if ($field === null) continue;

                        // Skip memo fields - can't be queried alongside other columns in Access ODBC
                        $fieldType = $field['type'] ?? 'textbox';
                        if (in_array($fieldType, $memoSkipTypes)) continue;

                        $colType = self::detectColumnType($field);
                        $listColumns[] = [
                            'field' => $colName,
                            'title' => $field['caption'] ?? ucfirst($colName),
                            'type' => $colType,
                        ];
                    }
                }

                // Build from fields if no columns defined
                if (empty($listColumns) && !empty($jsonData['fields'])) {
                    $skipTypes = ['groupseparator', 'label', 'checklist', 'sortlist', 'image', 'file', 'thumbnail', 'directory', 'memo', 'xmlstore', 'custom', 'password', 'ignorefield'];
                    // Skip the filter field in default columns
                    // Check both filterIdName (legacy) and filter.field (new format)
                    $filterIdName = strtolower($jsonData['filterIdName'] ?? '');
                    if ($filterIdName === '' && isset($jsonData['filter']['field'])) {
                        $filterIdName = strtolower($jsonData['filter']['field']);
                    }
                    $colCount = 0;
                    foreach ($jsonData['fields'] as $field) {
                        $fieldType = $field['type'] ?? 'textbox';
                        $fieldName = $field['name'] ?? '';
                        if (empty($fieldName) || strpos($fieldName, '_group') === 0) continue;
                        if (in_array($fieldType, $skipTypes)) continue;
                        if (strtolower($fieldName) === strtolower($idField)) continue;
                        // Respect skipInTableView property from form definition
                        if (!empty($field['skipInTableView'])) continue;
                        // Skip filter field - not useful in table view when filtering is required
                        if ($filterIdName !== '' && strtolower($fieldName) === $filterIdName) continue;

                        $colType = self::detectColumnType($field);
                        $listColumns[] = [
                            'field' => $fieldName,
                            'title' => $field['caption'] ?? ucfirst($fieldName),
                            'type' => $colType,
                        ];
                        $colCount++;
                    }
                }
            }

            // Get database connection and fetch the record
            $conn = ListServiceHelper::getJsonFormConnection($database);
            if ($conn === null) {
                return self::error('Database connectie mislukt');
            }

            // Handle both numeric and GUID IDs - use string quoting for non-numeric IDs
            $idValue = is_numeric($recordId) ? SQL::postNumber($recordId) : SQL::postString($recordId, $conn);
            $sql = "SELECT * FROM [$tableName] WHERE [$idField] = " . $idValue;
            $rs = Database::openRS($sql, $conn);
            if (!$rs || $rs->EOF) {
                return self::error('Record niet gevonden');
            }

            $fields = $rs->fields;

            // Build case-insensitive field lookup (DB returns PascalCase like Titel, Code
            // but column names from TH data-field may be lowercase like titel, code)
            $fieldsLower = [];
            $debugFieldKeys = [];
            foreach ($fields as $key => $val) {
                if (!is_numeric($key)) {
                    $fieldsLower[strtolower($key)] = $val;
                    $debugFieldKeys[] = $key;
                }
            }

            // Build FK lookup for combobox fields
            $comboColumns = [];
            $fkLookup = [];
            // Build radiogroup lookup
            $radioColumns = [];
            $radioLookup = [];

            // Load raw form definition for radiogroup options
            $rawFormDef = JsonFormLoader::loadRaw($formName);

            foreach ($listColumns as $col) {
                $fieldName = $col['field'] ?? '';
                $fieldLower = strtolower($fieldName);
                $fieldInfo = $fieldsByName[$fieldLower] ?? null;

                if (($col['type'] ?? '') === 'combobox') {
                    $comboColumns[$fieldName] = true;
                }

                // Check for radiogroup/radio fields
                $fieldType = $fieldInfo['type'] ?? '';
                if ($fieldInfo && ($fieldType === 'radiogroup' || $fieldType === 'radio')) {
                    $radioColumns[$fieldName] = true;
                    // Build lookup from options in raw form definition
                    if ($rawFormDef) {
                        foreach ($rawFormDef['fields'] ?? [] as $f) {
                            if (strtolower($f['name'] ?? '') === $fieldLower) {
                                $radioLookup[$fieldName] = [];
                                foreach ($f['options'] ?? [] as $opt) {
                                    $optValue = (string)($opt['value'] ?? '');
                                    $optText = $opt['text'] ?? $opt['label'] ?? $optValue;
                                    $radioLookup[$fieldName][$optValue] = $optText;
                                }
                                break;
                            }
                        }
                    }
                }
            }

            // Load combo options for FK fields
            if (!empty($comboColumns)) {
                foreach ($jsonData['fields'] ?? [] as $field) {
                    $fieldName = $field['name'] ?? '';
                    if (!isset($comboColumns[$fieldName])) continue;
                    if (($field['type'] ?? '') !== 'combobox') continue;

                    $options = [];
                    // Support both valueField (legacy) and idField (JSON forms)
                    $valueField = $field['valueField'] ?? $field['idField'] ?? 'ID';
                    $displayField = $field['displayField'] ?? $valueField;

                    // Use custom SQL if provided (may contain calculated/aliased columns)
                    $optSql = $field['sql'] ?? $field['dataSource'] ?? '';
                    if (empty($optSql) && !empty($field['sourceTable'])) {
                        // Build SQL from sourceTable, idField, displayField
                        $optSql = "SELECT [$valueField], [$displayField] FROM [{$field['sourceTable']}]";
                        if (!empty($field['filter'])) $optSql .= " WHERE {$field['filter']}";
                        if (!empty($field['orderBy'])) $optSql .= " ORDER BY {$field['orderBy']}";
                    }

                    if (!empty($optSql)) {
                        try {
                            $optRs = Database::openRS($optSql, $conn);
                            while ($optRs && !$optRs->EOF) {
                                // Case-insensitive field access (database may return different case)
                                $id = (string)($optRs->fields[$valueField] ?? $optRs->fields[strtolower($valueField)] ?? $optRs->fields[strtoupper($valueField)] ?? '');
                                $text = $optRs->fields[$displayField] ?? $optRs->fields[strtolower($displayField)] ?? $optRs->fields[strtoupper($displayField)] ?? null;
                                // Show placeholder if text is null (can happen with NULL values in database)
                                if ($text === null || $text === '') {
                                    $text = '[Geen omschrijving beschikbaar]';
                                }
                                if ($id !== '') {
                                    $fkLookup[$fieldName][$id] = trim($text);
                                }
                                $optRs->MoveNext();
                            }
                        } catch (\Exception $e) {
                            Logger::debug('FK lookup fetch failed', ['field' => $fieldName, 'error' => $e->getMessage()]);
                        }
                    }
                }
            }

            // Render the row
            $hasFullAccess = SecurityHelper::isAdmin();
            $html = '<tr class="listrow" data-id="' . htmlspecialchars($recordId) . '">';

            // Menu trigger goes inside the first data cell
            $menuTrigger = '<span class="row-menu-trigger" data-id="' . htmlspecialchars($recordId) . '">&#8942;</span>';
            $isFirstCol = true;

            foreach ($listColumns as $col) {
                $fieldName = $col['field'] ?? '';
                // Get value via case-insensitive lookup (DB may return Titel for titel, Code for code, etc.)
                $value = $fieldsLower[strtolower($fieldName)] ?? '';
                $colType = $col['type'] ?? 'text';
                $prefix = $isFirstCol ? $menuTrigger : '';
                $isFirstCol = false;

                if ($colType === 'boolean') {
                    $boolVal = ($value === true || $value === 1 || $value === -1 ||
                        strtolower((string)$value) === 'true' || $value === '1' || $value === '-1');
                    if ($hasFullAccess) {
                        $switchHtml = '<lib-switch data-field="' . htmlspecialchars($fieldName) . '"' .
                            ($boolVal ? ' checked' : '') . '></lib-switch>';
                        $html .= '<td data-field="' . htmlspecialchars($fieldName) . '" data-type="boolean" data-value="' . ($boolVal ? '1' : '0') . '">' . $prefix . $switchHtml . '</td>';
                    } else {
                        $html .= '<td data-field="' . htmlspecialchars($fieldName) . '" data-type="boolean">' . $prefix . ($boolVal ? 'Ja' : 'Nee') . '</td>';
                    }
                } elseif (isset($comboColumns[$fieldName])) {
                    $fkValue = trim((string)$value);
                    $displayValue = $fkLookup[$fieldName][$fkValue] ?? $fkValue;
                    $html .= '<td data-field="' . htmlspecialchars($fieldName) . '" data-type="combobox" data-fk-value="' . htmlspecialchars($fkValue) . '">' . $prefix . htmlspecialchars($displayValue) . '</td>';
                } elseif (isset($radioColumns[$fieldName])) {
                    // Radiogroup field: use lookup from options
                    $radioValue = trim((string)$value);
                    $displayValue = $radioLookup[$fieldName][$radioValue] ?? $radioValue;
                    $html .= '<td data-field="' . htmlspecialchars($fieldName) . '" data-type="radiogroup">' . $prefix . htmlspecialchars($displayValue) . '</td>';
                } elseif ($colType === 'date' || $colType === 'datetime') {
                    $value = \App\Library\Date::fixValue($value);
                    $html .= '<td data-field="' . htmlspecialchars($fieldName) . '" data-type="' . htmlspecialchars($colType) . '">' . $prefix . htmlspecialchars((string)$value) . '</td>';
                } else {
                    $origValue = $value;
                    $value = \App\Library\Date::fixValue($value);
                    $detectedType = ($value !== $origValue && preg_match('/^\d{2}-\d{2}-\d{4}/', $value)) ? 'date' : $colType;
                    $html .= '<td data-field="' . htmlspecialchars($fieldName) . '" data-type="' . htmlspecialchars($detectedType) . '">' . $prefix . htmlspecialchars((string)$value) . '</td>';
                }
            }

            $html .= '</tr>';

            // Get display text for tree view (first text column value or detailField)
            $detailField = $jsonData['detailField'] ?? '';
            $displayText = '';
            if ($detailField && isset($fieldsLower[strtolower($detailField)])) {
                $displayText = (string)$fieldsLower[strtolower($detailField)];
            } elseif (!empty($listColumns)) {
                // Fall back to first column value
                $firstCol = $listColumns[0]['field'] ?? '';
                if ($firstCol) {
                    $displayText = (string)($fieldsLower[strtolower($firstCol)] ?? '');
                }
            }

            // Count empty TDs in generated HTML for debugging
            $emptyTdCount = 0;
            $totalTdCount = 0;
            if (preg_match_all('/<td[^>]*>(.*?)<\/td>/s', $html, $tdMatches)) {
                $totalTdCount = count($tdMatches[1]);
                foreach ($tdMatches[1] as $tdContent) {
                    $stripped = strip_tags(trim($tdContent));
                    if ($stripped === '' && strpos($tdContent, 'lib-switch') === false) {
                        $emptyTdCount++;
                    }
                }
            }
            Logger::debug("getRowHtml: RESULT", [
                'htmlLength' => strlen($html),
                'totalTds' => $totalTdCount,
                'emptyTds' => $emptyTdCount,
                'htmlPreview' => substr($html, 0, 500),
            ]);

            return [
                'success' => true,
                'html' => $html,
                'displayText' => $displayText,
                'rowHtml' => $html, // Alias for compatibility
                '_debug' => [
                    'form' => $formName,
                    'recordId' => $recordId,
                    'requestedColumns' => $columns,
                    'matchedListColumns' => count($listColumns),
                    'dbFieldNames' => $debugFieldKeys ?? [],
                    'totalTds' => $totalTdCount,
                    'emptyTds' => $emptyTdCount,
                ],
            ];

        } catch (\Exception $e) {
            return self::error($e->getMessage());
        }
    }

    /**
     * Get available columns for JSON form (for column selector UI)
     *
     * @param string $formName JSON form name
     * @return array ['success' => bool, 'columns' => array, 'selected' => array]
     */
    public static function getAvailableColumns(string $formName): array
    {
        try {
            // Load raw JSON form definition (not legacy format)
            $formDef = JsonFormLoader::loadRaw($formName);
            if ($formDef === null) {
                return self::error('JSON formulier niet gevonden: ' . $formName);
            }

            // Get fields from JSON definition
            $fields = $formDef['fields'] ?? [];
            $idField = strtolower($formDef['idField'] ?? 'ID');

            // Control types to skip (same as database forms)
            // Note: radiogroup is a simple single-value field, so it's allowed
            $skipTypes = [
                'groupseparator', 'label', 'checklist', 'sortlist',
                'image', 'file', 'thumbnail', 'directory', 'hidden',
                'tip', 'custom', 'checklisttree', 'checklistinline',
                'password', 'blockedit', 'xmlstore', 'ignorefield'
            ];

            $columns = [];
            foreach ($fields as $field) {
                $fieldName = $field['name'] ?? '';
                $fieldType = $field['type'] ?? 'textbox';
                $caption = $field['caption'] ?? $fieldName;
                $renderer = $field['renderer'] ?? '';

                // Skip fields without names or special control types
                if (empty($fieldName)) continue;
                if (in_array($fieldType, $skipTypes)) continue;
                if (strtolower($fieldName) === $idField) continue;
                if (str_starts_with($fieldName, '_')) continue; // Skip internal fields
                if (!empty($renderer)) continue; // Skip fields with custom renderers (complex fields)

                // Map JSON type to display type name
                $typeName = match($fieldType) {
                    'checkbox' => 'vinkje',
                    'combobox', 'userlist' => 'lijst',
                    'memo', 'htmlstrip' => 'lange tekst',
                    default => 'tekst'
                };

                $columns[] = [
                    'name' => $fieldName,
                    'caption' => $caption,
                    'type' => $fieldType,      // Raw field type for logic (memo detection, etc.)
                    'typeLabel' => $typeName,   // Dutch display label
                ];
            }

            // Get filter field name if defined (should be excluded from default columns)
            $filterFieldName = '';
            if (!empty($formDef['filter']['field'])) {
                $filterFieldName = strtolower($formDef['filter']['field']);
            } elseif (!empty($formDef['filterIdName'])) {
                $filterFieldName = strtolower($formDef['filterIdName']);
            }

            // Get currently selected columns (use form name as key)
            $selected = TableService::getColumnPreferences($formName);

            // Build lookup of memo field names (can't be displayed in table view)
            $memoFieldNames = [];
            foreach ($columns as $col) {
                if (in_array($col['type'], ['memo', 'htmlstrip'])) {
                    $memoFieldNames[] = strtolower($col['name']);
                }
            }

            // If no selection saved, default to available columns
            // (excluding filter field and memo fields which can't be displayed)
            if (empty($selected)) {
                $availableNames = array_column($columns, 'name');
                // Exclude filter field from default selection - when filtering is applied,
                // all rows have the same filter value, making the column redundant
                if ($filterFieldName !== '') {
                    $availableNames = array_filter($availableNames, function($name) use ($filterFieldName) {
                        return strtolower($name) !== $filterFieldName;
                    });
                    $availableNames = array_values($availableNames); // Re-index array
                }
                // Exclude memo fields from default selection
                if (!empty($memoFieldNames)) {
                    $availableNames = array_filter($availableNames, function($name) use ($memoFieldNames) {
                        return !in_array(strtolower($name), $memoFieldNames);
                    });
                    $availableNames = array_values($availableNames);
                }
                $selected = $availableNames;
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

    /**
     * Get table data as JSON for lib-table web component
     *
     * @param string $formName JSON form name
     * @param array $options Options
     * @return array ['success' => bool, 'rows' => array, 'columns' => array, ...]
     */
    public static function getTableJson(string $formName, array $options = []): array
    {
        // TODO: Implement getTableJson based on getTableHtml logic
        // For now, return an error since the web component table is not the default view
        return self::error('Web component table view is not yet supported for JSON forms');
    }

    /**
     * Get list data as JSON for JSON forms (API endpoint)
     *
     * Returns data suitable for JSON API responses.
     *
     * @param string $formName JSON form name
     * @param array $options Options: page, pageSize, search, sortColumn, sortDir, filters
     * @return array ['success' => bool, 'data' => array, 'total' => int]
     */
    public static function getListData(string $formName, array $options = []): array
    {
        try {
            // Load JSON form definition
            $formDef = JsonFormLoader::load($formName);
            if ($formDef === null) {
                return self::error("Formulier '$formName' niet gevonden");
            }

            $jsonData = $formDef['_json'] ?? [];
            $database = $jsonData['database'] ?? 'data';
            $tableName = $jsonData['table'] ?? '';
            $idField = $jsonData['idField'] ?? 'ID';
            $listQuery = $jsonData['listQuery'] ?? '';
            $listColumns = $jsonData['listColumns'] ?? [];

            // Get database connection
            $conn = ListServiceHelper::getJsonFormConnection($database);
            if (!$conn) {
                return self::error("Database connection '$database' niet beschikbaar");
            }

            // Build the query
            if (!empty($listQuery)) {
                // Strip existing ORDER BY from listQuery - we may add our own for sorting
                $sql = preg_replace('/\s+ORDER\s+BY\s+.+$/i', '', $listQuery);
            } elseif (!empty($tableName)) {
                // Build field list from listColumns or all fields
                $fields = [$idField];
                if (!empty($listColumns)) {
                    foreach ($listColumns as $col) {
                        if (!empty($col['field']) && !in_array($col['field'], $fields)) {
                            $fields[] = $col['field'];
                        }
                    }
                }
                $fieldList = implode(', ', array_map(fn($f) => "[$f]", $fields));
                $sql = "SELECT $fieldList FROM [$tableName]";
            } else {
                return self::error("Formulier '$formName' heeft geen tabel of listQuery");
            }

            // FIX: Qualify columns with table name when query has JOIN to avoid ambiguous column error
            $hasJoin = stripos($sql, ' JOIN ') !== false;

            // Add search filter if provided
            $search = $options['search'] ?? '';
            if (!empty($search)) {
                // Add WHERE clause for search
                $searchTerm = str_replace("'", "''", $search);
                $searchConditions = [];

                // Search in all text fields from listColumns
                foreach ($listColumns as $col) {
                    $colType = $col['type'] ?? 'text';
                    if ($colType === 'text' || $colType === 'lookup') {
                        $searchConditions[] = "[{$col['field']}] LIKE '%$searchTerm%'";
                    }
                }

                if (!empty($searchConditions)) {
                    if (stripos($sql, 'WHERE') !== false) {
                        $sql .= ' AND (' . implode(' OR ', $searchConditions) . ')';
                    } else {
                        $sql .= ' WHERE (' . implode(' OR ', $searchConditions) . ')';
                    }
                }
            }

            // Add sorting
            $sortColumn = $options['sortColumn'] ?? '';
            $sortDir = strtoupper($options['sortDir'] ?? 'ASC');
            if ($sortDir !== 'DESC') {
                $sortDir = 'ASC';
            }

            if (!empty($sortColumn)) {
                // Qualify sort column with table name when query has JOIN
                $qualifiedSort = ($hasJoin && $tableName) ? "[$tableName].[$sortColumn]" : "[$sortColumn]";
                $sql .= " ORDER BY $qualifiedSort $sortDir";
            } elseif (isset($jsonData['orderField'])) {
                $orderDir = strtoupper($jsonData['orderDirection'] ?? 'ASC');
                $orderField = $jsonData['orderField'];
                $qualifiedOrder = ($hasJoin && $tableName) ? "[$tableName].[$orderField]" : "[$orderField]";
                $sql .= " ORDER BY $qualifiedOrder $orderDir";
            }

            // Execute query
            $rs = Database::openRS($sql, $conn);
            $items = [];

            if ($rs) {
                while (!$rs->EOF) {
                    $item = [];
                    foreach ($rs->fields as $key => $value) {
                        if (!is_numeric($key)) {
                            $item[$key] = $value;
                        }
                    }
                    $items[] = $item;
                    $rs->MoveNext();
                }
            }

            // Apply pagination
            $page = max(1, (int)($options['page'] ?? 1));
            $pageSize = min(100, max(10, (int)($options['pageSize'] ?? 50)));
            $total = count($items);
            $offset = ($page - 1) * $pageSize;
            $pagedItems = array_slice($items, $offset, $pageSize);

            return [
                'success' => true,
                'data' => $pagedItems,
                'total' => $total,
                'page' => $page,
                'pageSize' => $pageSize,
            ];

        } catch (\Exception $e) {
            return self::error($e->getMessage());
        }
    }

    /**
     * Get subform list data as JSON for JSON forms (API endpoint)
     *
     * @param string $formName Main form name
     * @param string $subformName Subform name
     * @param string|int $parentId Parent record ID
     * @param array $options Options
     * @return array ['success' => bool, 'data' => array, 'total' => int]
     */
    public static function getSubformListData(string $formName, string $subformName, string|int $parentId, array $options = []): array
    {
        try {
            // Try to load subform as standalone definition first
            $formDef = JsonFormLoader::load($subformName);
            $jsonData = $formDef ? ($formDef['_json'] ?? []) : [];

            // If not found or missing foreignKey, look in parent form's subforms array
            if ($formDef === null || empty($jsonData['foreignKey'] ?? '')) {
                $parentFormDef = JsonFormLoader::load($formName);
                if ($parentFormDef !== null) {
                    $parentJson = $parentFormDef['_json'] ?? [];
                    $subforms = $parentJson['subforms'] ?? [];

                    foreach ($subforms as $subform) {
                        $sfName = $subform['name'] ?? ($subform['formName'] ?? '');
                        if ($sfName === $subformName) {
                            // Found inline subform definition
                            $jsonData = array_merge($jsonData, $subform);
                            // Use parent's database if not specified in subform
                            if (empty($jsonData['database'])) {
                                $jsonData['database'] = $parentJson['database'] ?? 'data';
                            }
                            break;
                        }
                    }
                }
            }

            // Check if we have required fields
            if (empty($jsonData)) {
                return self::error("Subformulier '$subformName' niet gevonden");
            }

            $database = $jsonData['database'] ?? 'data';
            $tableName = $jsonData['table'] ?? '';
            $idField = $jsonData['idField'] ?? 'ID';
            // Support both foreignKey and parentField naming conventions
            $foreignKey = $jsonData['foreignKey'] ?? ($jsonData['parentField'] ?? '');

            if (empty($foreignKey)) {
                return self::error("Subformulier '$subformName' heeft geen foreignKey gedefinieerd");
            }

            if (empty($tableName)) {
                return self::error("Subformulier '$subformName' heeft geen tabel gedefinieerd");
            }

            // Get database connection
            $conn = ListServiceHelper::getJsonFormConnection($database);
            if (!$conn) {
                return self::error("Database connection '$database' niet beschikbaar");
            }

            // Build query with parent filter
            $parentIdSafe = is_numeric($parentId) ? (int)$parentId : "'" . str_replace("'", "''", (string)$parentId) . "'";
            $sql = "SELECT * FROM [$tableName] WHERE [$foreignKey] = $parentIdSafe";

            // Add sorting
            if (isset($jsonData['orderField'])) {
                $orderDir = strtoupper($jsonData['orderDirection'] ?? 'ASC');
                $sql .= " ORDER BY [{$jsonData['orderField']}] $orderDir";
            }

            // Execute query
            $rs = Database::openRS($sql, $conn);
            $items = [];

            if ($rs) {
                while (!$rs->EOF) {
                    $item = [];
                    foreach ($rs->fields as $key => $value) {
                        if (!is_numeric($key)) {
                            $item[$key] = $value;
                        }
                    }
                    $items[] = $item;
                    $rs->MoveNext();
                }
            }

            return [
                'success' => true,
                'data' => $items,
                'total' => count($items),
            ];

        } catch (\Exception $e) {
            return self::error($e->getMessage());
        }
    }

    /**
     * Get single record data for JSON form (API endpoint)
     *
     * @param string $formName JSON form name
     * @param string|int $recordId Record ID
     * @return array ['success' => bool, 'record' => array]
     */
    public static function getRecord(string $formName, string|int $recordId): array
    {
        try {
            // Load JSON form definition
            $formDef = JsonFormLoader::load($formName);
            if ($formDef === null) {
                return self::error("Formulier '$formName' niet gevonden");
            }

            $jsonData = $formDef['_json'] ?? [];
            $database = $jsonData['database'] ?? 'data';
            $tableName = $jsonData['table'] ?? '';
            $idField = $jsonData['idField'] ?? 'ID';

            if (empty($tableName)) {
                return self::error("Formulier '$formName' heeft geen tabel");
            }

            // Get database connection
            $conn = ListServiceHelper::getJsonFormConnection($database);
            if (!$conn) {
                return self::error("Database connection '$database' niet beschikbaar");
            }

            // Build explicit column list with MEMO fields last
            // (Access ODBC returns empty MEMO values if non-memo columns follow them)
            $fields = $jsonData['fields'] ?? [];
            $normalCols = [$idField];
            $memoCols = [];
            foreach ($fields as $field) {
                $fname = $field['name'] ?? '';
                if ($fname === '' || strcasecmp($fname, $idField) === 0) continue;
                if (($field['type'] ?? '') === 'memo') {
                    $memoCols[] = $fname;
                } else {
                    $normalCols[] = $fname;
                }
            }
            $allCols = array_merge($normalCols, $memoCols);
            $colList = implode(', ', array_map(fn($c) => "[$c]", $allCols));

            $idSafe = is_numeric($recordId) ? (int)$recordId : "'" . str_replace("'", "''", (string)$recordId) . "'";
            $sql = "SELECT $colList FROM [$tableName] WHERE [$idField] = $idSafe";

            // Execute query
            $rs = Database::openRS($sql, $conn);

            if ($rs && !$rs->EOF) {
                $record = [];
                foreach ($rs->fields as $key => $value) {
                    if (!is_numeric($key)) {
                        $record[$key] = $value;
                    }
                }
                return [
                    'success' => true,
                    'record' => $record,
                ];
            }

            return self::error("Record met ID '$recordId' niet gevonden");

        } catch (\Exception $e) {
            return self::error($e->getMessage());
        }
    }

    // ==================== Private Helper Methods ====================

    /**
     * Get table HTML for JSON config form (data stored in JSON files via ConfigLoader)
     */
    private static function getJsonConfigTableHtml(string $formName, string|int|null $activeId, array $options, array $jsonData, ?array $rawFormDef): array
    {
        // Pass filters from options to ConfigFormService
        $filters = $options['filters'] ?? [];
        $result = ConfigFormService::getListData($formName, $filters);
        if (!$result['success']) {
            return $result;
        }

        $items = $result['data'] ?? [];
        $idField = $jsonData['idField'] ?? 'id';
        $listColumns = $jsonData['listColumns'] ?? [];

        // Build columns from fields if listColumns is not defined
        if (empty($listColumns) && !empty($jsonData['fields'])) {
            $skipTypes = ['groupseparator', 'label', 'checklist', 'sortlist', 'image', 'file', 'thumbnail', 'directory', 'memo', 'xmlstore', 'hidden', 'custom', 'password', 'ignorefield'];
            // Skip the filter field in default columns
            // Check both filterIdName (legacy) and filter.field (new format)
            $filterIdName = strtolower($jsonData['filterIdName'] ?? '');
            if ($filterIdName === '' && isset($jsonData['filter']['field'])) {
                $filterIdName = strtolower($jsonData['filter']['field']);
            }
            $colCount = 0;
            foreach ($jsonData['fields'] as $field) {
                $fieldType = $field['type'] ?? 'textbox';
                $fieldName = $field['name'] ?? '';
                if (empty($fieldName) || strpos($fieldName, '_group') === 0) continue;
                if (in_array($fieldType, $skipTypes)) continue;
                if (strtolower($fieldName) === strtolower($idField)) continue;
                // Respect skipInTableView property from form definition
                if (!empty($field['skipInTableView'])) continue;
                // Skip filter field - not useful in table view when filtering is required
                if ($filterIdName !== '' && strtolower($fieldName) === $filterIdName) continue;

                $colType = self::detectColumnType($field);

                $listColumns[] = [
                    'field' => $fieldName,
                    'title' => $field['caption'] ?? $fieldName,
                    'type' => $colType,
                ];
                $colCount++;
            }
        }

        // Apply search filter
        $search = strtolower($options['search'] ?? '');
        if ($search !== '') {
            $items = array_filter($items, function($item) use ($search, $idField) {
                foreach ($item as $key => $value) {
                    if (strtolower($key) !== strtolower($idField) && is_string($value)) {
                        if (stripos($value, $search) !== false) {
                            return true;
                        }
                    }
                }
                return false;
            });
        }

        // Build table HTML
        $displayName = $jsonData['title'] ?? str_replace('_', ' ', $formName);
        $html = '<lib-table><table class="listtable inline-editable filtering sorttable" id="listTable" data-json-form="' . htmlspecialchars($formName) . '" data-name="' . htmlspecialchars($displayName) . '">';

        // Header row
        $html .= '<thead><tr class="listheader">';
        foreach ($listColumns as $col) {
            $title = $col['title'] ?? $col['field'] ?? '';
            $width = $col['width'] ?? '';
            $colType = $col['type'] ?? 'text';
            $fieldName = $col['field'] ?? '';
            $style = $width ? ' style="width:' . $width . '"' : '';
            $html .= '<th data-field="' . htmlspecialchars($fieldName) . '" data-type="' . htmlspecialchars($colType) . '"' . $style . '>' . htmlspecialchars($title) . '</th>';
        }
        $html .= '</tr></thead>';

        // Data rows
        $html .= '<tbody>';
        $count = 0;
        // Check if form is editable (for inline switch toggles)
        $hasFullAccess = SecurityHelper::isAdmin();
        foreach ($items as $item) {
            $recordId = $item[$idField] ?? $item['id'] ?? '';
            $isActive = ($activeId !== null && $recordId == $activeId);
            $rowClass = 'listrow' . ($isActive ? ' active' : '');

            $html .= '<tr class="' . $rowClass . '" data-id="' . htmlspecialchars($recordId) . '">';

            // Menu trigger goes inside the first data cell
            $menuTrigger = '<span class="row-menu-trigger" data-id="' . htmlspecialchars($recordId) . '">&#8942;</span>';
            $isFirstCol = true;

            foreach ($listColumns as $col) {
                $fieldName = $col['field'] ?? '';
                $value = $item[$fieldName] ?? '';
                $colType = $col['type'] ?? 'text';
                $prefix = $isFirstCol ? $menuTrigger : '';
                $isFirstCol = false;

                // Format value based on type
                if ($colType === 'boolean') {
                    $boolVal = ($value === true || $value === 1 || $value === -1 ||
                        strtolower((string)$value) === 'true' || $value === '1' || $value === '-1');
                    if ($hasFullAccess) {
                        // Render as clickable toggle switch using lib-switch web component
                        $switchHtml = '<lib-switch data-field="' . htmlspecialchars($fieldName) . '"' .
                            ($boolVal ? ' checked' : '') . '></lib-switch>';
                        $html .= '<td data-field="' . htmlspecialchars($fieldName) . '" data-type="boolean" data-value="' . ($boolVal ? '1' : '0') . '">' . $prefix . $switchHtml . '</td>';
                    } else {
                        $value = $boolVal ? 'Ja' : 'Nee';
                        $html .= '<td data-field="' . htmlspecialchars($fieldName) . '" data-type="boolean">' . $prefix . htmlspecialchars((string)$value) . '</td>';
                    }
                } elseif ($colType === 'lookup' && isset($col['lookup'])) {
                    // Use inline lookup table to translate value
                    $lookup = $col['lookup'];
                    $lookupKey = (string)$value;
                    $value = $lookup[$lookupKey] ?? $value;
                    $html .= '<td data-field="' . htmlspecialchars($fieldName) . '" data-type="' . htmlspecialchars($colType) . '">' . $prefix . htmlspecialchars((string)$value) . '</td>';
                } elseif ($colType === 'count' && isset($item['_itemCount'])) {
                    // Special computed field for menu item counts
                    $value = $item['_itemCount'];
                    $html .= '<td data-field="' . htmlspecialchars($fieldName) . '" data-type="' . htmlspecialchars($colType) . '">' . $prefix . htmlspecialchars((string)$value) . '</td>';
                } elseif (Arr::isArray($value)) {
                    // Array values (like items in menu) - show count
                    $value = count($value) . ' items';
                    $html .= '<td data-field="' . htmlspecialchars($fieldName) . '" data-type="' . htmlspecialchars($colType) . '">' . $prefix . htmlspecialchars((string)$value) . '</td>';
                } else {
                    $html .= '<td data-field="' . htmlspecialchars($fieldName) . '" data-type="' . htmlspecialchars($colType) . '">' . $prefix . htmlspecialchars((string)$value) . '</td>';
                }
            }

            $html .= '</tr>';
            $count++;
        }

        $html .= '</tbody></table></lib-table>';

        if ($count === 0) {
            $html .= '<div class="no-data">Geen gegevens gevonden</div>';
        }

        // Build field definitions for inline editing
        $fieldDefs = [];
        $comboOptions = [];
        $rawFields = $rawFormDef['fields'] ?? [];
        $skipTypes = ['groupseparator', 'label', 'hidden', 'password', 'image', 'file', 'checklist', 'sortlist', 'checklisttree', 'checklistinline', 'custom', 'tip', 'ignorefield'];

        foreach ($rawFields as $field) {
            $fieldName = $field['name'] ?? '';
            $fieldType = $field['type'] ?? 'textbox';

            if (empty($fieldName)) continue;
            if (in_array($fieldType, $skipTypes)) continue;
            if (strtolower($fieldName) === strtolower($idField)) continue;
            if (str_starts_with($fieldName, '_')) continue;

            $fieldDefs[] = [
                'name' => $fieldName,
                'caption' => $field['caption'] ?? $fieldName,
                'type' => $fieldType,
                'required' => $field['required'] ?? false,
                'readonly' => $field['readonly'] ?? false,
                'newOnly' => $field['newOnly'] ?? false,
                'maxLength' => $field['maxLength'] ?? 255,
            ];

            // Load combo options for fields with optionsSource.type = "jsonConfig"
            if ($fieldType === 'combo' || $fieldType === 'combobox') {
                $optionsSource = $field['optionsSource'] ?? null;
                if ($optionsSource && ($optionsSource['type'] ?? '') === 'jsonConfig') {
                    $configFile = $optionsSource['configFile'] ?? '';
                    $configArrayKey = $optionsSource['configArrayKey'] ?? '';
                    $valueField = $optionsSource['valueField'] ?? 'id';
                    $labelField = $optionsSource['labelField'] ?? 'name';

                    if ($configFile && $configArrayKey) {
                        $options = ConfigFormService::getOptionsFromConfig($configFile, $configArrayKey, $valueField, $labelField);
                        if (!empty($options)) {
                            $comboOptions[$fieldName] = $options;
                        }
                    }
                }
            }
        }

        // For JSON config forms, admin users have full access
        $hasFullAccess = SecurityHelper::isAdmin();

        $response = [
            'success' => true,
            'html' => $html,
            'count' => $count,
            'displayMode' => ListMode::DISPLAY_TABLE,
            'fields' => $fieldDefs,
            'permissions' => [
                'canAdd' => ($jsonData['allowAdd'] ?? true) && $hasFullAccess,
                'canEdit' => ($jsonData['allowEdit'] ?? true) && $hasFullAccess,
                'canCopy' => ($jsonData['allowCopy'] ?? false) && $hasFullAccess,
                'canDelete' => ($jsonData['allowDelete'] ?? true) && $hasFullAccess,
            ],
        ];

        if (!empty($comboOptions)) {
            $response['comboOptions'] = $comboOptions;
        }

        return $response;
    }

    /**
     * Get single row HTML for JSON config form (data stored in JSON files via ConfigLoader)
     * Used for refreshing a row after inline edit save
     */
    private static function getJsonConfigRowHtml(string $formName, string $recordId, int $displayMode, array $jsonData, array $formDef): array
    {
        // Get the record from ConfigFormService
        $result = ConfigFormService::getRecord($formName, $recordId);
        if (!$result['success']) {
            return $result;
        }

        $item = $result['data'] ?? [];
        $idField = $jsonData['idField'] ?? 'id';
        $listColumns = $jsonData['listColumns'] ?? [];

        // Build columns from fields if listColumns is not defined
        if (empty($listColumns) && !empty($jsonData['fields'])) {
            $skipTypes = ['groupseparator', 'label', 'checklist', 'sortlist', 'image', 'file', 'thumbnail', 'directory', 'memo', 'xmlstore', 'hidden', 'custom', 'password', 'ignorefield'];
            $filterIdName = strtolower($jsonData['filterIdName'] ?? '');
            if ($filterIdName === '' && isset($jsonData['filter']['field'])) {
                $filterIdName = strtolower($jsonData['filter']['field']);
            }
            $colCount = 0;
            foreach ($jsonData['fields'] as $field) {
                $fieldType = $field['type'] ?? 'textbox';
                $fieldName = $field['name'] ?? '';
                if (empty($fieldName) || strpos($fieldName, '_group') === 0) continue;
                if (in_array($fieldType, $skipTypes)) continue;
                // Respect skipInTableView property from form definition
                if (!empty($field['skipInTableView'])) continue;
                if (strtolower($fieldName) === strtolower($idField)) continue;
                if ($filterIdName !== '' && strtolower($fieldName) === $filterIdName) continue;

                $colType = self::detectColumnType($field);

                $listColumns[] = [
                    'field' => $fieldName,
                    'title' => $field['caption'] ?? $fieldName,
                    'type' => $colType,
                ];
                $colCount++;
            }
        }

        // Check if form is editable (for inline switch toggles)
        $hasFullAccess = SecurityHelper::isAdmin();

        // Build the row HTML
        $html = '<tr class="listrow" data-id="' . htmlspecialchars($recordId) . '">';

        // Menu trigger goes inside the first data cell
        $menuTrigger = '<span class="row-menu-trigger" data-id="' . htmlspecialchars($recordId) . '">&#8942;</span>';
        $isFirstCol = true;

        foreach ($listColumns as $col) {
            $fieldName = $col['field'] ?? '';
            $value = $item[$fieldName] ?? '';
            $colType = $col['type'] ?? 'text';
            $prefix = $isFirstCol ? $menuTrigger : '';
            $isFirstCol = false;

            if ($colType === 'boolean') {
                $boolVal = ($value === true || $value === 1 || $value === -1 ||
                    strtolower((string)$value) === 'true' || $value === '1' || $value === '-1');
                if ($hasFullAccess) {
                    $switchHtml = '<lib-switch data-field="' . htmlspecialchars($fieldName) . '"' .
                        ($boolVal ? ' checked' : '') . '></lib-switch>';
                    $html .= '<td data-field="' . htmlspecialchars($fieldName) . '" data-type="boolean" data-value="' . ($boolVal ? '1' : '0') . '">' . $prefix . $switchHtml . '</td>';
                } else {
                    $value = $boolVal ? 'Ja' : 'Nee';
                    $html .= '<td data-field="' . htmlspecialchars($fieldName) . '" data-type="boolean">' . $prefix . htmlspecialchars((string)$value) . '</td>';
                }
            } elseif (Arr::isArray($value)) {
                $value = count($value) . ' items';
                $html .= '<td data-field="' . htmlspecialchars($fieldName) . '" data-type="' . htmlspecialchars($colType) . '">' . $prefix . htmlspecialchars((string)$value) . '</td>';
            } else {
                $html .= '<td data-field="' . htmlspecialchars($fieldName) . '" data-type="' . htmlspecialchars($colType) . '">' . $prefix . htmlspecialchars((string)$value) . '</td>';
            }
        }

        $html .= '</tr>';

        return [
            'success' => true,
            'html' => $html,
        ];
    }

    /**
     * Convert control type ID to field type name
     * Maps FormRenderer TYPE_* constants to string names
     *
     * @param int $controlType Control type ID
     * @return string Field type name
     */
    /**
     * Detect the column display type from field definition.
     * Reads the field type from the JSON definition. After migrations 7.5.0
     * and 8.5.0, fields have proper type values (date, time, checkbox, etc.).
     * Falls back to dataType detection for unmigrated forms.
     */
    private static function detectColumnType(array $field): string
    {
        $fieldType = $field['type'] ?? 'textbox';

        // Direct type mapping - the field type from JSON is the source of truth
        if ($fieldType === 'date') return 'date';
        if ($fieldType === 'time') return 'time';
        if ($fieldType === 'checkbox') return 'boolean';
        if ($fieldType === 'combobox' || $fieldType === 'userlist') return 'combobox';

        // Fallback: detect from dataType for unmigrated forms
        $dataType = strtolower($field['dataType'] ?? '');
        if ($dataType === 'time') return 'time';
        if (!empty($field['dateFormat']) || in_array($dataType, ['date', 'datetime', '7', '133', '135'])) return 'date';
        if (in_array($dataType, ['int', 'integer', 'smallint', 'decimal', 'numeric', 'float', 'double', 'money', 'number'])) return 'number';

        return 'text';
    }

    private static function controlTypeToFieldType(int $controlType): string
    {
        return match ($controlType) {
            \Cma\FormRenderer::TYPE_COMBOBOX => 'combobox',
            \Cma\FormRenderer::TYPE_TEXTBOX => 'textbox',
            \Cma\FormRenderer::TYPE_CHECKBOX => 'checkbox',
            \Cma\FormRenderer::TYPE_MEMO => 'memo',
            \Cma\FormRenderer::TYPE_CHECKLIST => 'checklist',
            \Cma\FormRenderer::TYPE_IMAGE => 'image',
            \Cma\FormRenderer::TYPE_URL => 'url',
            \Cma\FormRenderer::TYPE_FILE => 'file',
            \Cma\FormRenderer::TYPE_LABEL => 'label',
            \Cma\FormRenderer::TYPE_SORTLIST => 'sortlist',
            \Cma\FormRenderer::TYPE_DIRECTORY => 'directory',
            \Cma\FormRenderer::TYPE_GROUPSEPARATOR => 'groupseparator',
            \Cma\FormRenderer::TYPE_USERLIST => 'userlist',
            \Cma\FormRenderer::TYPE_EMAIL => 'email',
            \Cma\FormRenderer::TYPE_XMLSTORE => 'xmlstore',
            \Cma\FormRenderer::TYPE_HTMLSTRIP => 'htmlstrip',
            \Cma\FormRenderer::TYPE_THUMBNAIL => 'thumbnail',
            \Cma\FormRenderer::TYPE_TIME => 'time',
            \Cma\FormRenderer::TYPE_PASSWORD => 'password',
            \Cma\FormRenderer::TYPE_RADIOGROUP => 'radiogroup',
            \Cma\FormRenderer::TYPE_DATE => 'date',
            default => 'textbox',
        };
    }

    /**
     * Get a value from an associative array using case-insensitive key lookup.
     */
    private static function getFieldCaseInsensitive(array $row, string $field): mixed
    {
        if (array_key_exists($field, $row)) {
            return $row[$field];
        }
        $fieldLower = strtolower($field);
        foreach ($row as $key => $value) {
            if (strtolower($key) === $fieldLower) {
                return $value;
            }
        }
        return null;
    }
}
