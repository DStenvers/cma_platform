<?php

namespace Cma\Services;

use App\Library\Arr;
use App\Library\Database;
use App\Library\Server;
use App\Library\SQL;
use App\Library\Str;
use Cma\CmaRepository;
use Cma\FormControlHelper;
use Cma\FormDefinition;
use Cma\JsonFormLoader;
use Cma\SecurityHelper;

/**
 * Subform Service
 *
 * Handles subform data display and operations.
 * Contains all subform-related methods extracted from ListService.
 */
class SubformService extends BaseFormService
{
    /**
     * Get subform data
     *
     * @param int $formId Parent form ID
     * @param string|int $parentId Parent record ID
     * @param int $subformIndex Subform index
     * @return array ['success' => bool, 'items' => array, 'total' => int]
     */
    public static function getSubformData(int $formId, string|int $parentId, int $subformIndex): array
    {
        // Delegate to FormDataProvider for now (subform logic is complex)
        return \Cma\FormDataProvider::getSubformData($formId, $parentId, $subformIndex);
    }

    /**
     * Get subform data as JSON for lib-table web component
     * Uses the same format as getTableJson for consistency
     *
     * @param int|string $formId Parent form ID (numeric) or form name (string)
     * @param string|int $parentId Parent record ID
     * @param int $subformIndex Subform index
     * @param array $options Pagination/filter options
     * @return array ['success' => bool, 'rows' => array, 'columns' => array, ...]
     */
    public static function getSubformTableJson(int|string $formId, string|int $parentId, int $subformIndex, array $options = []): array
    {
        try {
            $arrSubForms = \SubFormGetArray($formId);
            if (!Arr::isArray($arrSubForms) && !($arrSubForms instanceof \ArrayAccess)) {
                return self::error('Geen subformulieren gevonden voor formulier: ' . $formId);
            }

            if (!isset($arrSubForms[\SUBFORM_ID][$subformIndex])) {
                return self::error('Subformulier niet gevonden');
            }

            $subformId = (int)$arrSubForms[\SUBFORM_ID][$subformIndex];
            $jsonFormName = $arrSubForms[\SUBFORM_JSONFORM][$subformIndex] ?? null;
            $rights = SecurityHelper::ACCESS_FULL;

            // Handle access check
            if ($subformId > 0) {
                $accessError = self::checkFormAccess($subformId, SecurityHelper::ACCESS_READ, 'Geen toegang tot dit subformulier');
                if ($accessError !== null) {
                    return $accessError;
                }
                $rights = SecurityHelper::checkFormRights((int)SecurityHelper::getCurrentUserId(), $subformId);

                if (empty($jsonFormName)) {
                    $jsonFormName = JsonFormLoader::getFormNameBySourceId($subformId);
                }
            } elseif (!empty($jsonFormName)) {
                $rights = SecurityHelper::checkFormRightsByName(
                    (int)SecurityHelper::getCurrentUserId(),
                    $jsonFormName
                );
                if ($rights < SecurityHelper::ACCESS_READ) {
                    return self::error('Geen toegang tot dit subformulier');
                }
            }

            // Inherit parent form rights if needed
            if ($rights < SecurityHelper::ACCESS_FULL) {
                $userId = (int)SecurityHelper::getCurrentUserId();
                $parentRights = SecurityHelper::ACCESS_NONE;
                if (is_int($formId) && $formId > 0) {
                    $parentRights = SecurityHelper::checkFormRights($userId, $formId);
                } elseif (is_string($formId) && !empty($formId)) {
                    $parentRights = SecurityHelper::checkFormRightsByName($userId, $formId);
                }
                if ($parentRights >= SecurityHelper::ACCESS_FULL) {
                    $rights = $parentRights;
                }
            }

            // Check if JSON config form
            if (!empty($jsonFormName)) {
                $subformDef = JsonFormLoader::load($jsonFormName);
                if ($subformDef && ConfigFormService::isJsonConfigForm($subformDef)) {
                    $parentFormName = is_string($formId) ? $formId : JsonFormLoader::getFormNameBySourceId((int)$formId);
                    if ($parentFormName) {
                        return ConfigFormService::getSubformListData($parentFormName, $parentId, $subformIndex);
                    }
                }
            }

            // Get subform configuration
            $sql = $arrSubForms[\SUBFORM_QUERY][$subformIndex] ?? '';
            $parentField = $arrSubForms[\SUBFORM_PARENT][$subformIndex] ?? '';
            $idField = $arrSubForms[\SUBFORM_IDFLD][$subformIndex] ?? 'ID';
            $subformName = $arrSubForms[\SUBFORM_NAME][$subformIndex] ?? '';
            $fullWidth = (bool)($arrSubForms[\SUBFORM_FULLWIDTH][$subformIndex] ?? false);
            $connString = $arrSubForms[\SUBFORM_CONN][$subformIndex] ?? '';

            // Apply parent filter
            if ($parentField === '') {
                // Check if SQL has [id] placeholder - if not and we have a parentId, config is incomplete
                if (!empty($parentId) && stripos($sql, '[id]') === false) {
                    $jsonFormName = $arrSubForms[\SUBFORM_JSONFORM][$subformIndex] ?? '';
                    $parentFormName = is_string($formId) ? $formId : JsonFormLoader::getFormNameBySourceId((int)$formId);
                    Logger::error('Subform configuration error: parentField is empty but parentId is provided', [
                        'parentFormId' => $formId,
                        'parentFormName' => $parentFormName,
                        'subformName' => $subformName,
                        'subformIndex' => $subformIndex,
                        'jsonFormName' => $jsonFormName,
                        'parentId' => $parentId,
                    ]);

                    // Get candidate FK fields from the subform definition for developer fix UI
                    $candidateFields = self::getCandidateFkFields($jsonFormName);

                    return [
                        'success' => false,
                        'error' => "Subformulier '$subformName' heeft geen parentField geconfigureerd.",
                        'fixable' => true,
                        'fixType' => 'missingParentField',
                        'parentFormName' => $parentFormName,
                        'subformIndex' => $subformIndex,
                        'subformName' => $subformName,
                        'jsonFormName' => $jsonFormName,
                        'candidateFields' => $candidateFields,
                    ];
                }
                $sql = str_ireplace('[id]', $parentId, $sql);
            } else {
                $parentIdSafe = is_numeric($parentId) ? (int)$parentId : SQL::postString($parentId);
                // Qualify parentField with table name to avoid MS Access treating it as a parameter
                $qualifiedParentField = $parentField;
                $tableName = '';
                $jsonFormName = $arrSubForms[\SUBFORM_JSONFORM][$subformIndex] ?? '';

                // Try to get table name from JSON form definition (use loadRaw for raw JSON access)
                if ($jsonFormName) {
                    $subformDef = JsonFormLoader::loadRaw($jsonFormName);
                    if ($subformDef && !empty($subformDef['table'])) {
                        $tableName = $subformDef['table'];
                    }
                }

                // Fallback: extract table name from SQL query's FROM clause
                if (empty($tableName) && preg_match('/\bFROM\s+(\[?[\w]+\]?)/i', $sql, $matches)) {
                    $tableName = trim($matches[1], '[]');
                }

                if (!empty($tableName)) {
                    $qualifiedParentField = $tableName . '.' . $parentField;
                }

                $sql = SQL::addWhere($sql, $qualifiedParentField . '=' . $parentIdSafe);
            }

            // Get pagination options
            $pageSize = (int)($options['pageSize'] ?? $options['limit'] ?? 500);
            $lastId = $options['lastId'] ?? null;
            $sortColumn = $options['sortColumn'] ?? '';
            $sortDir = strtoupper($options['sortDir'] ?? 'ASC');
            if ($sortDir !== 'DESC') $sortDir = 'ASC';
            $search = $options['search'] ?? '';
            $filters = $options['filters'] ?? [];

            // Get connection
            $subConn = CmaRepository::resolveConnectionString($connString);

            // Get column info from the query
            $sampleSql = SQL::addTop($sql, 1, $subConn);
            $sampleRs = Database::openRS($sampleSql, $subConn);
            if ($sampleRs === null) {
                Logger::error('Subform query failed', [
                    'error' => Database::getLastError(),
                    'sql' => $sampleSql,
                    'parentFormId' => $formId,
                    'subformIndex' => $subformIndex,
                    'subformName' => $subformName,
                    'parentId' => $parentId,
                    'parentField' => $parentField,
                ]);
                $dbError = Database::getLastError();
                // If error contains HTML diagnostic, don't append SQL (it's already in details)
                if (strpos($dbError, '<div') !== false) {
                    return self::error($dbError);
                }
                return self::error('Subform query mislukt: ' . $dbError . ' (SQL: ' . $sampleSql . ')');
            }

            // Build column definitions from recordset metadata
            $columns = [];
            $columnDefs = [];

            if (!$sampleRs->EOF) {
                foreach ($sampleRs->fields as $key => $value) {
                    if (is_int($key)) continue;
                    if (strtolower($key) === strtolower($idField)) continue;

                    $columns[] = $key;

                    // Determine type from value
                    $type = 'text';
                    if (is_numeric($value) && !is_string($value)) {
                        $type = is_float($value) ? 'decimal' : 'integer';
                    } elseif ($value instanceof \DateTime) {
                        $type = 'date';
                    } elseif (is_bool($value)) {
                        $type = 'boolean';
                    } elseif (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}/', $value)) {
                        $type = 'date';
                    }

                    $columnDefs[] = [
                        'name' => $key,
                        'caption' => ucfirst(str_replace('_', ' ', $key)),
                        'type' => $type,
                        'sortable' => true,
                        'filterable' => true,
                        'resizable' => true,
                    ];
                }
            }

            // No column limit - show all available columns

            // Apply search filter
            if ($search !== '') {
                $searchConditions = [];
                foreach ($columns as $col) {
                    $searchConditions[] = "[$col] LIKE " . SQL::postString('%' . $search . '%');
                }
                if (!empty($searchConditions)) {
                    $hasWhere = stripos($sql, 'WHERE') !== false;
                    $sql .= ($hasWhere ? ' AND ' : ' WHERE ') . '(' . implode(' OR ', $searchConditions) . ')';
                }
            }

            // Apply field-specific filters
            if (!empty($filters)) {
                foreach ($filters as $field => $filterValue) {
                    if ($filterValue === '' || $filterValue === null) continue;
                    $hasWhere = stripos($sql, 'WHERE') !== false;
                    $sql .= ($hasWhere ? ' AND ' : ' WHERE ') . "[$field] LIKE " . SQL::postString('%' . $filterValue . '%');
                }
            }

            // Apply keyset pagination
            if ($lastId !== null) {
                $hasWhere = stripos($sql, 'WHERE') !== false;
                $lastIdEscaped = is_numeric($lastId) ? (int)$lastId : SQL::postString($lastId);
                $comparison = $sortDir === 'DESC' ? '<' : '>';
                $sql .= ($hasWhere ? ' AND ' : ' WHERE ') . "[$idField] $comparison $lastIdEscaped";
            }

            // Add ORDER BY
            $sql = preg_replace('/\s+ORDER\s+BY\s+.+$/i', '', $sql);
            if ($sortColumn !== '' && in_array($sortColumn, $columns)) {
                $sql .= " ORDER BY [$sortColumn] $sortDir, [$idField] $sortDir";
            } else {
                $sql .= " ORDER BY [$idField] " . $sortDir;
            }

            // Fetch one extra row to detect hasMore
            $sql = SQL::addTop($sql, $pageSize + 1, $subConn);

            $rs = Database::openRS($sql, $subConn);
            if ($rs === null) {
                Logger::error('Subform query failed (main query)', [
                    'error' => Database::getLastError(),
                    'sql' => $sql,
                    'parentFormId' => $formId,
                    'subformIndex' => $subformIndex,
                    'subformName' => $subformName,
                    'parentId' => $parentId,
                    'parentField' => $parentField,
                ]);
                $dbError = Database::getLastError();
                // If error contains HTML diagnostic, don't append SQL (it's already in details)
                if (strpos($dbError, '<div') !== false) {
                    return self::error($dbError);
                }
                return self::error('Subform query mislukt: ' . $dbError . ' (SQL: ' . $sql . ')');
            }

            // Collect rows
            $rows = [];
            $rowCount = 0;
            $newLastId = null;

            while (!$rs->EOF) {
                $rowCount++;

                if ($rowCount > $pageSize) {
                    break;
                }

                $rowData = [];
                $recordId = null;

                foreach ($rs->fields as $key => $value) {
                    if (is_int($key)) continue;

                    if (strtolower($key) === strtolower($idField)) {
                        $recordId = $value;
                        continue;
                    }

                    if ($value instanceof \DateTime) {
                        $value = $value->format('Y-m-d');
                    } elseif (is_string($value)) {
                        $value = Str::toUtf8($value);
                    }

                    $rowData[$key] = $value;
                }

                $rowData['_id'] = $recordId;
                $newLastId = $recordId;
                $rows[] = $rowData;
                $rs->MoveNext();
            }

            $hasMore = $rowCount > $pageSize;

            $hasValidJsonForm = !empty($jsonFormName);
            $effectiveSubformId = $jsonFormName ?? '';

            return [
                'success' => true,
                'rows' => $rows,
                'columns' => $columnDefs,
                'totalCount' => count($rows),
                'hasMore' => $hasMore,
                'lastId' => $newLastId,
                'subformId' => $effectiveSubformId,
                'subformName' => $subformName,
                'parentField' => $parentField,
                'fullWidth' => $fullWidth,
                'canAdd' => $rights >= SecurityHelper::ACCESS_FULL && $hasValidJsonForm,
            ];
        } catch (\Exception $e) {
            return self::error($e->getMessage());
        }
    }

    /**
     * Get subform table HTML (sortable/filterable table like main form table view)
     *
     * @param int|string $formId Parent form ID (numeric) or form name (string)
     * @param string|int $parentId Parent record ID
     * @param int $subformIndex Subform index
     * @return array ['success' => bool, 'html' => string, 'count' => int, ...]
     */
    public static function getSubformTableHtml(int|string $formId, string|int $parentId, int $subformIndex): array
    {
        try {
            $arrSubForms = \SubFormGetArray($formId);
            if (!Arr::isArray($arrSubForms) && !($arrSubForms instanceof \ArrayAccess)) {
                return self::error('Geen subformulieren gevonden voor formulier: ' . $formId);
            }

            if (!isset($arrSubForms[\SUBFORM_ID][$subformIndex])) {
                return self::error('Subformulier niet gevonden');
            }

            $subformId = (int)$arrSubForms[\SUBFORM_ID][$subformIndex];
            $jsonFormName = $arrSubForms[\SUBFORM_JSONFORM][$subformIndex] ?? null;
            $rights = SecurityHelper::ACCESS_FULL;

            // Handle access check
            if ($subformId > 0) {
                $accessError = self::checkFormAccess($subformId, SecurityHelper::ACCESS_READ, 'Geen toegang tot dit subformulier');
                if ($accessError !== null) {
                    return $accessError;
                }
                $rights = SecurityHelper::checkFormRights((int)SecurityHelper::getCurrentUserId(), $subformId);

                if (empty($jsonFormName)) {
                    $jsonFormName = JsonFormLoader::getFormNameBySourceId($subformId);
                }
            } elseif (!empty($jsonFormName)) {
                $rights = SecurityHelper::checkFormRightsByName(
                    (int)SecurityHelper::getCurrentUserId(),
                    $jsonFormName
                );
                if ($rights < SecurityHelper::ACCESS_READ) {
                    return self::error('Geen toegang tot dit subformulier');
                }
            }

            // Inherit parent form rights if needed
            if ($rights < SecurityHelper::ACCESS_FULL) {
                $userId = (int)SecurityHelper::getCurrentUserId();
                $parentRights = SecurityHelper::ACCESS_NONE;
                if (is_int($formId) && $formId > 0) {
                    $parentRights = SecurityHelper::checkFormRights($userId, $formId);
                } elseif (is_string($formId) && !empty($formId)) {
                    $parentRights = SecurityHelper::checkFormRightsByName($userId, $formId);
                }
                if ($parentRights >= SecurityHelper::ACCESS_FULL) {
                    $rights = $parentRights;
                }
            }

            // Check if JSON config form
            if (!empty($jsonFormName)) {
                $subformDef = JsonFormLoader::load($jsonFormName);
                if ($subformDef && ConfigFormService::isJsonConfigForm($subformDef)) {
                    $parentFormName = is_string($formId) ? $formId : JsonFormLoader::getFormNameBySourceId((int)$formId);
                    if ($parentFormName) {
                        return ConfigFormService::getSubformListData($parentFormName, $parentId, $subformIndex);
                    }
                }
            }

            // Get subform form definition for column info
            $arrRep = null;
            $formDef = null;
            if ($subformId > 0) {
                $arrRep = \GetFormDef($subformId);
                $formDef = FormDefinition::fromArray($arrRep);
            } elseif (!empty($jsonFormName)) {
                $arrRep = \GetFormDef($jsonFormName);
                $formDef = FormDefinition::fromArray($arrRep);
            }

            // Get subform query
            $sql = $arrSubForms[\SUBFORM_QUERY][$subformIndex] ?? '';
            $parentField = $arrSubForms[\SUBFORM_PARENT][$subformIndex] ?? '';
            $idField = $arrSubForms[\SUBFORM_IDFLD][$subformIndex] ?? 'ID';
            $subformName = $arrSubForms[\SUBFORM_NAME][$subformIndex] ?? '';
            $fullWidth = (bool)($arrSubForms[\SUBFORM_FULLWIDTH][$subformIndex] ?? false);

            // If SQL is empty but we have a JSON form, load the query from JSON definition
            // Use loadRaw() to get raw JSON (not legacy Q_* format)
            if (empty($sql) && !empty($jsonFormName)) {
                $subformJsonDef = JsonFormLoader::loadRaw($jsonFormName);
                if ($subformJsonDef) {
                    $sql = $subformJsonDef['listQuery'] ?? '';
                    // Also get idField and parentField from JSON if not set
                    if (empty($idField) || $idField === 'ID') {
                        $idField = $subformJsonDef['idField'] ?? 'ID';
                    }
                    if (empty($parentField)) {
                        // Try to find parentField from subform definition in parent form
                        $parentFormName = is_string($formId) ? $formId : JsonFormLoader::getFormNameBySourceId((int)$formId);
                        if ($parentFormName) {
                            $parentJsonDef = JsonFormLoader::loadRaw($parentFormName);
                            if ($parentJsonDef && isset($parentJsonDef['subforms'][$subformIndex])) {
                                $parentField = $parentJsonDef['subforms'][$subformIndex]['parentField'] ?? '';
                            }
                        }
                    }
                }
            }
            $sqlOriginal = $sql; // Store for debugging

            // Apply parent filter to SQL
            if ($parentField === '') {
                // Check if SQL has [id] placeholder
                if (stripos($sql, '[id]') !== false) {
                    // Replace [id] placeholder with parent ID
                    $sql = str_ireplace('[id]', $parentId, $sql);
                } elseif (!empty($parentId)) {
                    // No parentField AND no [id] placeholder - this is a configuration error
                    Logger::error('Subform configuration error: parentField is empty and SQL has no [id] placeholder', [
                        'parentFormId' => $formId,
                        'subformName' => $subformName,
                        'subformIndex' => $subformIndex,
                        'jsonFormName' => $jsonFormName,
                        'parentId' => $parentId,
                        'sql' => $sqlOriginal,
                    ]);

                    // Get parent form name for fixing
                    $parentFormName = is_string($formId) ? $formId : JsonFormLoader::getFormNameBySourceId((int)$formId);

                    // Get candidate FK fields if we have a subform JSON name
                    $candidateFields = [];
                    if (!empty($jsonFormName)) {
                        $candidateFields = self::getCandidateFkFields($jsonFormName);
                    }

                    // Return fixable error for developers
                    return [
                        'success' => false,
                        'error' => "Subformulier '$subformName' heeft geen parentField geconfigureerd.",
                        'fixable' => true,
                        'fixType' => 'missingParentField',
                        'parentFormName' => $parentFormName,
                        'subformIndex' => $subformIndex,
                        'subformName' => $subformName,
                        'jsonFormName' => $jsonFormName,
                        'candidateFields' => $candidateFields,
                    ];
                }
            } else {
                // Add WHERE clause for parent record
                // Qualify parentField with table name to avoid MS Access treating it as a parameter
                $qualifiedParentField = $parentField;
                $tableName = '';

                // Try to get table name from JSON form definition (use loadRaw for raw JSON access)
                if (!empty($jsonFormName)) {
                    $subformTableDef = JsonFormLoader::loadRaw($jsonFormName);
                    if ($subformTableDef && !empty($subformTableDef['table'])) {
                        $tableName = $subformTableDef['table'];
                    }
                }

                // Fallback: extract table name from SQL query's FROM clause
                if (empty($tableName) && preg_match('/\bFROM\s+(\[?[\w]+\]?)/i', $sqlOriginal, $matches)) {
                    $tableName = trim($matches[1], '[]');
                }

                if (!empty($tableName)) {
                    $qualifiedParentField = $tableName . '.' . $parentField;
                }

                $sql = SQL::addWhere($sql, $qualifiedParentField . '=' . RecordService::formatIdForSql($parentId));
            }

            // Get connection
            $connString = $arrSubForms[\SUBFORM_CONN][$subformIndex] ?? '';
            $subConn = CmaRepository::resolveConnectionString($connString);

            // Limit subform results
            $sql = SQL::addTop($sql, 500, $subConn);

            $rs = Database::openRS($sql, $subConn);
            if ($rs === null) {
                Logger::error('Subform table query failed', [
                    'error' => Database::getLastError(),
                    'sql' => $sql,
                    'sqlOriginal' => $sqlOriginal,
                    'parentFormId' => $formId,
                    'subformIndex' => $subformIndex,
                    'subformName' => $subformName,
                    'parentId' => $parentId,
                    'parentField' => $parentField,
                    'jsonFormName' => $jsonFormName ?? null,
                ]);
                $dbError = Database::getLastError();
                // If error contains HTML diagnostic, don't append SQL (it's already in details)
                if (strpos($dbError, '<div') !== false) {
                    return self::error($dbError);
                }
                return self::error('Subform query mislukt: ' . $dbError . ' (SQL: ' . $sql . ', Original: ' . $sqlOriginal . ')');
            }

            // Build columns from query result
            $columns = [];
            $fieldCaptions = [];
            $fieldTypes = [];
            $fieldInlineEdit = [];
            $maxColumns = 999;

            // Build lookup from form definition
            $formFieldInfo = [];
            if ($formDef && $formDef->isValid()) {
                $rowCount = $formDef->getRowCount();
                for ($i = 0; $i < $rowCount; $i++) {
                    $fieldName = $formDef->getFieldName($i);
                    if (!empty($fieldName)) {
                        $formFieldInfo[strtolower($fieldName)] = [
                            'caption' => $formDef->getCaption($i) ?: $fieldName,
                            'controlType' => $formDef->getControlTypeId($i),
                            'isDate' => $formDef->isDateField($i),
                            'inlineEdit' => $formDef->isInlineEdit($i),
                        ];
                    }
                }
            }

            // Get columns from query result
            // Skip columns that represent the parent relationship (redundant in subform context)
            $fieldIsDate = [];
            $parentFieldLower = strtolower($parentField);
            // Extract base name from FK field (e.g., "fkDeelname" -> "deelname", "fkDeelnemer" -> "deelnemer")
            $parentBaseName = '';
            if (strpos($parentFieldLower, 'fk') === 0) {
                $parentBaseName = substr($parentFieldLower, 2); // Remove "fk" prefix
            }

            if (!$rs->EOF) {
                foreach ($rs->fields as $key => $value) {
                    $keyLower = strtolower($key);

                    // Skip numeric keys and ID field
                    if (is_int($key) || $keyLower === strtolower($idField)) {
                        continue;
                    }

                    // Skip the parent FK field itself
                    if (!empty($parentFieldLower) && $keyLower === $parentFieldLower) {
                        continue;
                    }

                    // Skip columns that reference the parent entity
                    // (e.g., "Naam_deelnemer" when parentField is "fkDeelname" or "fkDeelnemer")
                    // Use word-boundary matching to avoid false positives
                    // (e.g., don't skip "competentiegebied" when parentBaseName is "competentie")
                    if (!empty($parentBaseName) && (
                        preg_match('/(^|[_\s])' . preg_quote($parentBaseName, '/') . '($|[_\s])/i', $keyLower) ||
                        preg_match('/(^|[_\s])' . preg_quote(rtrim($parentBaseName, 'e') . 'er', '/') . '($|[_\s])/i', $keyLower)
                    )) {
                        continue;
                    }

                    if (count($columns) < $maxColumns) {
                        $columns[] = $key;
                        $info = $formFieldInfo[$keyLower] ?? null;
                        $fieldCaptions[$key] = $info['caption'] ?? $key;
                        $fieldTypes[$key] = $info['controlType'] ?? 0;
                        $fieldIsDate[$key] = $info['isDate'] ?? false;
                        $fieldInlineEdit[$key] = $info['inlineEdit'] ?? false;
                    }
                }
            }

            // Build table HTML
            $subformIdentifier = $subformId > 0 ? (string)$subformId : ($jsonFormName ?? '0');
            $tableId = 'subformTable_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $subformIdentifier);
            $html = '<lib-table><table class="listtable subform-table filtering sorttable" id="' . $tableId . '" data-subform-id="' . Server::htmlEncode($subformIdentifier) . '" data-json-form="' . Server::htmlEncode($jsonFormName ?? '') . '" data-name="' . Server::htmlEncode($subformName) . '" cellspacing="0" cellpadding="0">';

            // Header row
            $html .= '<thead><tr class="listheader">';
            foreach ($columns as $col) {
                $caption = $fieldCaptions[$col] ?? $col;
                $controlType = $fieldTypes[$col] ?? 0;
                $isDate = $fieldIsDate[$col] ?? false;
                $dataType = 'text';
                if ($isDate) {
                    $dataType = 'date';
                } elseif ($controlType == FormControlHelper::TYPE_CHECKBOX) {
                    $dataType = 'boolean';
                } elseif ($controlType == FormControlHelper::TYPE_COMBOBOX || $controlType == FormControlHelper::TYPE_USERLIST) {
                    $dataType = 'combobox';
                }
                $html .= '<th data-field="' . Server::htmlEncode($col) . '" data-type="' . $dataType . '">' . Server::htmlEncode($caption) . '</th>';
            }
            $html .= '</tr></thead>';

            // Data rows
            $html .= '<tbody>';
            $count = 0;
            while (!$rs->EOF) {
                $row = $rs->fetchAssoc();
                $rowLower = array_change_key_case($row, CASE_LOWER);
                $recordId = $rowLower[strtolower($idField)] ?? $row[$idField] ?? '';

                $html .= '<tr class="listrow" data-id="' . Server::htmlEncode($recordId) . '">';

                // Menu trigger goes inside the first data cell
                $menuTrigger = '<span class="row-menu-trigger" data-id="' . Server::htmlEncode($recordId) . '">&#8942;</span>';
                $isFirstCol = true;

                foreach ($columns as $col) {
                    $value = $rowLower[strtolower($col)] ?? $row[$col] ?? '';
                    $controlType = $fieldTypes[$col] ?? 0;
                    $inlineEdit = $fieldInlineEdit[$col] ?? false;
                    $prefix = $isFirstCol ? $menuTrigger : '';
                    $isFirstCol = false;

                    if ($controlType == FormControlHelper::TYPE_CHECKBOX) {
                        $boolVal = is_bool($value) ? $value : (
                            $value === 1 || $value === '1' || $value === -1 || $value === '-1' ||
                            strtolower((string)$value) === 'true'
                        );

                        if ($inlineEdit) {
                            // Render as interactive switch for inline editing
                            $checked = $boolVal ? ' checked' : '';
                            $displayValue = '<lib-switch data-field="' . Server::htmlEncode($col) . '"' . $checked . '></lib-switch>';
                            $html .= '<td data-field="' . Server::htmlEncode($col) . '" data-value="' . ($boolVal ? '1' : '0') . '">' . $prefix . $displayValue . '</td>';
                        } else {
                            $displayValue = $boolVal ? 'Ja' : 'Nee';
                            $html .= '<td data-field="' . Server::htmlEncode($col) . '">' . $prefix . Server::htmlEncode($displayValue) . '</td>';
                        }
                    } else {
                        $displayValue = \App\Library\Date::fixValue($value);
                        if (strlen($displayValue) > 50) {
                            $displayValue = substr($displayValue, 0, 47) . '...';
                        }
                        $html .= '<td data-field="' . Server::htmlEncode($col) . '">' . $prefix . Server::htmlEncode($displayValue) . '</td>';
                    }
                }

                $html .= '</tr>';
                $count++;
                $rs->MoveNext();
            }
            $html .= '</tbody></table></lib-table>';

            // Determine permissions
            $canAddForm = $formDef && $formDef->isValid() ? $formDef->allowAdd() : true;
            $canEditForm = $formDef && $formDef->isValid() ? $formDef->allowEdit() : true;
            $canDeleteForm = $formDef && $formDef->isValid() ? $formDef->allowDelete() : true;

            if ($count === 0) {
                $canAdd = $canAddForm && $rights >= SecurityHelper::ACCESS_FULL;
                $message = $canAdd
                    ? 'Geen gegevens, klik op \'Toevoegen\' om een nieuw record aan te maken'
                    : 'Geen gegevens';
                $html = '<div class="no-data">' . $message . '</div>';
            }

            $hasValidJsonForm = !empty($jsonFormName);
            $effectiveSubformId = $jsonFormName ?? '';

            return [
                'success' => true,
                'html' => $html,
                'count' => $count,
                'subformId' => $effectiveSubformId,
                'subformName' => $subformName,
                'parentField' => $parentField,
                'fullWidth' => $fullWidth,
                'canAdd' => $canAddForm && $rights >= SecurityHelper::ACCESS_FULL && $hasValidJsonForm,
                'canEdit' => $canEditForm && $rights >= SecurityHelper::ACCESS_FULL && $hasValidJsonForm,
                'canDelete' => $canDeleteForm && $rights >= SecurityHelper::ACCESS_FULL,
                '_debug' => [
                    'sqlOriginal' => $sqlOriginal,
                    'sql' => $sql,
                    'parentId' => $parentId,
                    'parentField' => $parentField,
                    'subformIndex' => $subformIndex,
                ],
            ];
        } catch (\Exception $e) {
            return self::error($e->getMessage());
        }
    }

    /**
     * Get candidate FK fields from a subform definition
     * Returns fields that could be used as parentField (fields starting with 'fk' or integer types)
     *
     * @param string $jsonFormName Subform JSON form name
     * @return array List of candidate field names with their captions
     */
    private static function getCandidateFkFields(string $jsonFormName): array
    {
        if (empty($jsonFormName)) {
            return [];
        }

        $formDef = JsonFormLoader::loadRaw($jsonFormName);
        if (!$formDef || empty($formDef['fields'])) {
            return [];
        }

        $candidates = [];
        foreach ($formDef['fields'] as $field) {
            $name = $field['name'] ?? '';
            if (empty($name)) continue;

            // Skip group fields
            if (strpos($name, '_group') === 0) continue;

            // Include fields that:
            // 1. Start with 'fk' (foreign key naming convention)
            // 2. Are integer type and end with 'Id' or 'ID'
            $isFkField = stripos($name, 'fk') === 0;
            $isIdField = preg_match('/[Ii][Dd]$/', $name) && ($field['dataType'] ?? '') === 'integer';

            if ($isFkField || $isIdField) {
                $candidates[] = [
                    'name' => $name,
                    'caption' => $field['caption'] ?? $name,
                ];
            }
        }

        return $candidates;
    }
}
