<?php

namespace Cma\Services;

use App\Library\Arr;
use App\Library\Cache;
use App\Library\Cookie;
use App\Library\Database;
use App\Library\SQL;
use App\Library\Str;
use Cma\CmaRepository;
use Cma\FormDefinition;
use Cma\FormRenderer;
use Cma\SecurityHelper;
use Cma\Services\PerformanceLogger;

/**
 * Record Service
 *
 * Handles CRUD operations for form records.
 * Extracted from FormDataProvider for better maintainability.
 */
class RecordService extends BaseFormService
{
    /**
     * PERFORMANCE FIX: Class constant for excluded control types
     * Avoids recreating array on each loop iteration in buildFieldList()
     */
    private const EXCLUDED_CONTROL_TYPES = [
        FormRenderer::TYPE_GROUPSEPARATOR,
        FormRenderer::TYPE_CHECKLIST,
        FormRenderer::TYPE_SORTLIST,
        FormRenderer::TYPE_HTMLSTRIP,
        FormRenderer::TYPE_THUMBNAIL,
    ];

    /**
     * PERFORMANCE FIX: Known date formats from database
     * Used by fastParseDateTime() to avoid slow strtotime() calls
     */
    private const DATE_FORMATS = [
        'Y-m-d H:i:s.u',  // SQL Server with microseconds
        'Y-m-d H:i:s',    // Standard SQL datetime
        'Y-m-d',          // ISO date only
        'd-m-Y H:i:s',    // European with time
        'd-m-Y',          // European date only
        'd/m/Y H:i:s',    // European with slashes
        'd/m/Y',          // European date only with slashes
    ];

    /**
     * PERFORMANCE FIX: Fast datetime parsing using known formats
     * Falls back to strtotime() only when known formats fail
     *
     * @param string $value Date/time string to parse
     * @return \DateTime|null Parsed DateTime or null on failure
     */
    private static function fastParseDateTime(string $value): ?\DateTime
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        // Try known formats first (much faster than strtotime)
        foreach (self::DATE_FORMATS as $format) {
            $dt = \DateTime::createFromFormat($format, $value);
            if ($dt !== false) {
                return $dt;
            }
        }

        // Fall back to strtotime for unknown formats
        // Log a warning so we can add the format to DATE_FORMATS
        $timestamp = strtotime($value);
        if ($timestamp !== false) {
            Logger::debug("RecordService: Unknown date format, using slow strtotime()", ['value' => $value]);
            $dt = new \DateTime();
            $dt->setTimestamp($timestamp);
            return $dt;
        }

        return null;
    }

    /**
     * Get record data for form population
     *
     * @param int $formId Form ID
     * @param string|int $recordId Record ID
     * @return array ['success' => bool, 'fields' => array, 'meta' => array]
     */
    public static function getRecord(int $formId, string|int $recordId): array
    {
        Logger::debug("RecordService: getRecord CALLED", ['formId' => $formId, 'recordId' => $recordId]);
        PerformanceLogger::startTimer('getRecord');

        try {
            $timing = [];
            $t0 = microtime(true);

            // Fast path for admin users - skip expensive rights lookup
            if (SecurityHelper::isAdmin()) {
                $accessLevel = SecurityHelper::ACCESS_FULL_BEHEER;
                $timing['rights'] = round((microtime(true) - $t0) * 1000, 1);
            } else {
                $accessLevel = self::getAccessLevel($formId);
                $timing['rights'] = round((microtime(true) - $t0) * 1000, 1);
            }

            if ($accessLevel == SecurityHelper::ACCESS_NONE) {
                return self::error('Geen toegang tot dit formulier');
            }

            $t1 = microtime(true);
            $arrRep = self::getFormDef($formId);
            $formDef = FormDefinition::fromArray($arrRep);
            $timing['formDef'] = round((microtime(true) - $t1) * 1000, 1);

            if (!$formDef->isValid()) {
                return self::error('Formulier niet gevonden');
            }

            $tableName = $formDef->getSqlTableName();
            $idField = $formDef->getFormIdField();

            // Open connection
            CmaRepository::openConnectionById($formDef->getDatabaseId());
            global $conn;
            $timing['connection'] = Database::getLastConnectionStatus();

            // Build SELECT list from form fields
            $selectFields = self::buildSelectFields($arrRep, $tableName, $formDef);

            // Build query
            $sql = "SELECT " . $selectFields . " FROM " . $tableName .
                " WHERE " . $idField . " = " . self::formatIdForSql($recordId);

            $t3 = microtime(true);
            $rs = Database::openRS($sql, $conn);
            $timing['mainQuery'] = round((microtime(true) - $t3) * 1000, 1);

            if ($rs === null || $rs->EOF) {
                return self::error('Record niet gevonden');
            }

            // Sanitize all values for UTF-8 (handles Windows-1252 legacy data)
            $rawFields = \App\Library\Str::toUtf8($rs->fetchAssoc());

            // Debug: After toUtf8
            foreach ($rawFields as $key => $value) {
                if (!is_int($key) && is_string($value) && strlen($value) > 1000) {
                    Logger::debug("RecordService: Stage 2 field", ['field' => $key, 'bytes' => strlen($value), 'last30hex' => bin2hex(substr($value, -30))]);
                }
            }

            $fields = [];
            foreach ($rawFields as $key => $value) {
                if (!is_int($key)) {
                    $fields[$key] = self::formatFieldValue($key, $value, $arrRep);
                }
            }

            // Debug: After formatFieldValue
            foreach ($fields as $key => $value) {
                if (is_string($value) && strlen($value) > 1000) {
                    Logger::debug("RecordService: Stage 3 field", ['field' => $key, 'bytes' => strlen($value), 'last30hex' => bin2hex(substr($value, -30))]);
                }
            }

            // Get checklist values
            $t4 = microtime(true);
            $checklistValues = self::getChecklistValues($arrRep, $recordId, $conn);
            foreach ($checklistValues as $key => $value) {
                $fields[$key] = $value;
            }
            $timing['checklists'] = round((microtime(true) - $t4) * 1000, 1);

            // Get sortlist values
            $sortlistValues = self::getSortlistValues($arrRep, $recordId, $conn);
            foreach ($sortlistValues as $key => $value) {
                $fields[$key] = $value;
            }

            // Resolve FK display labels for combobox fields
            // This allows the UI to immediately show the correct text even if
            // the combo options haven't been loaded yet
            $t6 = microtime(true);
            $fkLabels = self::resolveFkLabels($arrRep, $fields, $formDef, $conn);
            foreach ($fkLabels as $key => $value) {
                $fields[$key] = $value;
            }
            $timing['fkLabels'] = round((microtime(true) - $t6) * 1000, 1);

            // Build meta info
            $meta = [
                'id' => $recordId,
                'accessLevel' => $accessLevel,
                'canEdit' => $formDef->allowEdit() && $accessLevel >= SecurityHelper::ACCESS_FULL,
                'canDelete' => $formDef->allowDelete() && $accessLevel >= SecurityHelper::ACCESS_FULL,
            ];

            // Last modified info
            $t5 = microtime(true);
            if ($formDef->hasStoreLastModified() && isset($rawFields['LastModifiedUser'])) {
                $meta['lastModifiedUser'] = SecurityHelper::getUserName((int)$rawFields['LastModifiedUser']);
                $meta['lastModifiedDate'] = $rawFields['LastModifiedDate'] ?? null;
            }
            $timing['userName'] = round((microtime(true) - $t5) * 1000, 1);
            $timing['total'] = round((microtime(true) - $t0) * 1000, 1);

            PerformanceLogger::endTimer('getRecord', [
                'formId' => $formId,
                'recordId' => $recordId,
                'fieldCount' => count($fields),
            ]);

            return self::success([
                'fields' => $fields,
                'meta' => $meta,
                '_timing' => $timing,
            ]);
        } catch (\Exception $e) {
            PerformanceLogger::endTimer('getRecord', ['error' => $e->getMessage()]);
            return self::error('Ophalen mislukt: ' . Database::cleanErrorMessage($e->getMessage()));
        }
    }

    /**
     * Save a record (insert or update)
     *
     * @param int $formId Form ID
     * @param string|int|null $recordId Record ID (null for new)
     * @param array $data Field values
     * @return array ['success' => bool, 'id' => mixed, 'message' => string]
     */
    public static function save(int $formId, string|int|null $recordId, array $data): array
    {
        $startTime = microtime(true);
        $timing = [];
        $isInsert = empty($recordId);
        PerformanceLogger::startTimer('saveRecord');

        try {
            if (!self::canWrite($formId)) {
                PerformanceLogger::endTimer('saveRecord', ['error' => 'access_denied']);
                return self::error('Geen rechten om te wijzigen');
            }

            $timing['access'] = round((microtime(true) - $startTime) * 1000, 1);
            $t1 = microtime(true);

            $arrRep = self::getFormDef($formId);
            $formDef = FormDefinition::fromArray($arrRep);
            $timing['formDef'] = round((microtime(true) - $t1) * 1000, 1);

            if (!$formDef->isValid()) {
                return self::error('Formulier niet gevonden');
            }

            $tableName = $formDef->getSqlTableName();
            $idField = $formDef->getFormIdField();
            $isNew = $recordId === null || $recordId === '';

            // Check if adding/editing is allowed at form level
            if ($isNew && !$formDef->allowAdd()) {
                return self::error('Toevoegen niet toegestaan voor dit formulier');
            }
            if (!$isNew && !$formDef->allowEdit()) {
                return self::error('Wijzigen niet toegestaan voor dit formulier');
            }

            // Open connection
            CmaRepository::openConnectionById($formDef->getDatabaseId());
            global $conn;

            // Build field list and values
            $fields = [];
            $values = [];
            $updates = [];

            // Debug: Log incoming data keys for memo field troubleshooting
            Logger::debug("RecordService::save: Data keys received", ['keys' => array_keys($data)]);

            $rowCount = count($arrRep[\Q_FIELDNAME] ?? []);
            for ($i = 0; $i < $rowCount; $i++) {
                $fieldName = $arrRep[\Q_FIELDNAME][$i] ?? null;
                if ($fieldName === null) {
                    continue;
                }

                $controlType = (int)($arrRep[\Q_CONTROLTYPEID][$i] ?? 0);

                // Debug: Log memo fields specifically
                if ($controlType === FormRenderer::TYPE_MEMO) {
                    $inData = array_key_exists($fieldName, $data);
                    $valueLen = $inData ? strlen($data[$fieldName] ?? '') : 0;
                    Logger::debug("RecordService::save: Memo field", ['field' => $fieldName, 'inData' => $inData, 'valueLen' => $valueLen]);
                }

                // Skip non-data control types
                if (in_array($controlType, [
                    FormRenderer::TYPE_GROUPSEPARATOR,
                    FormRenderer::TYPE_LABEL,
                    FormRenderer::TYPE_HTMLSTRIP,
                    FormRenderer::TYPE_THUMBNAIL,
                ])) {
                    continue;
                }

                // Skip if not in data
                if (!array_key_exists($fieldName, $data)) {
                    continue;
                }

                $value = $data[$fieldName];
                $sqlValue = self::formatForSql($value, $arrRep, $i);

                $fields[] = "[$fieldName]";
                $values[] = $sqlValue;
                $updates[] = "[$fieldName] = $sqlValue";
            }

            // Add last modified fields
            $userId = self::getUserId();
            if ($formDef->hasStoreLastModified()) {
                $fields[] = '[LastModifiedUser]';
                $values[] = $userId;
                $updates[] = '[LastModifiedUser] = ' . $userId;

                $fields[] = '[LastModifiedDate]';
                $values[] = 'GETDATE()';
                $updates[] = '[LastModifiedDate] = GETDATE()';
            }

            $queryStart = microtime(true);

            if ($isNew) {
                // INSERT
                $sql = "INSERT INTO $tableName (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $values) . ")";
                Database::query($sql, [], $conn);

                // Get new ID
                $newId = Database::getFieldValue($conn, "SELECT @@IDENTITY AS NewID", 'NewID');
                $recordId = $newId;
                $message = 'Record toegevoegd';
            } else {
                // UPDATE
                $sql = "UPDATE $tableName SET " . implode(', ', $updates) .
                    " WHERE $idField = " . self::formatIdForSql($recordId);
                Database::query($sql, [], $conn);
                $message = 'Record bijgewerkt';
            }

            $timing['query'] = round((microtime(true) - $queryStart) * 1000, 1);
            $checklistStart = microtime(true);

            // Handle checklists
            self::saveChecklistValues($arrRep, $recordId, $data, $conn);

            // Handle sortlists
            self::saveSortlistValues($arrRep, $recordId, $data, $conn);

            // Handle custom renderers (user_groups, form_notifications, etc.)
            self::saveCustomRendererValues($formDef, $recordId, $data);

            $timing['checklists'] = round((microtime(true) - $checklistStart) * 1000, 1);

            // Invalidate related caches
            if ($formDef->getCachePrefix() !== '') {
                Cache::invalidateGroup($formDef->getCachePrefix());
            }
            // Invalidate list query caches
            Cache::invalidateGroup('lists');

            // Invalidate combobox caches in background if this table is used as combobox source
            $tableName = $formDef->getSqlTableName();
            if ($tableName !== '') {
                self::invalidateComboboxCachesAsync($tableName);
            }

            $timing['total'] = round((microtime(true) - $startTime) * 1000, 1);

            PerformanceLogger::endTimer('saveRecord', [
                'formId' => $formId,
                'recordId' => $recordId,
                'isInsert' => $isInsert,
            ]);

            return self::success([
                'id' => $recordId,
                'message' => $message,
                'isNew' => $isNew,
                '_timing' => $timing,
            ]);
        } catch (\Exception $e) {
            PerformanceLogger::endTimer('saveRecord', ['error' => $e->getMessage()]);
            return self::error('Opslaan mislukt: ' . Database::cleanErrorMessage($e->getMessage()));
        }
    }

    /**
     * Delete a record
     *
     * @param int $formId Form ID
     * @param string|int $recordId Record ID
     * @return array ['success' => bool, 'message' => string]
     */
    public static function delete(int $formId, string|int $recordId): array
    {
        PerformanceLogger::startTimer('deleteRecord');

        try {
            if (!self::canWrite($formId)) {
                PerformanceLogger::endTimer('deleteRecord', ['error' => 'access_denied']);
                return self::error('Geen rechten om te verwijderen');
            }

            $arrRep = self::getFormDef($formId);
            $formDef = FormDefinition::fromArray($arrRep);

            if (!$formDef->isValid()) {
                return self::error('Formulier niet gevonden');
            }

            if (!$formDef->hasMenuDelete()) {
                return self::error('Verwijderen niet toegestaan');
            }

            $tableName = $formDef->getSqlTableName();
            $idField = $formDef->getFormIdField();

            // Open connection
            CmaRepository::openConnectionById($formDef->getDatabaseId());
            global $conn;

            // Delete checklists first
            self::deleteChecklistValues($arrRep, $recordId, $conn);

            // Delete sortlists
            self::deleteSortlistValues($arrRep, $recordId, $conn);

            // Delete main record
            $sql = "DELETE FROM $tableName WHERE $idField = " . self::formatIdForSql($recordId);
            Database::query($sql, [], $conn);

            // Invalidate caches
            if ($formDef->getCachePrefix() !== '') {
                Cache::invalidateGroup($formDef->getCachePrefix());
            }
            // Invalidate list query caches
            Cache::invalidateGroup('lists');

            PerformanceLogger::endTimer('deleteRecord', [
                'formId' => $formId,
                'recordId' => $recordId,
            ]);

            return self::success(['message' => 'Record verwijderd']);
        } catch (\Exception $e) {
            PerformanceLogger::endTimer('deleteRecord', ['error' => $e->getMessage()]);
            return self::error('Verwijderen mislukt: ' . Database::cleanErrorMessage($e->getMessage()));
        }
    }

    /**
     * Get subform data
     *
     * @param int|string $formId Parent form ID (numeric) or form name (string)
     * @param string|int $parentId Parent record ID
     * @param int $subformIndex Subform index
     * @return array ['success' => bool, 'items' => array, 'total' => int]
     */
    public static function getSubformData(int|string $formId, string|int $parentId, int $subformIndex): array
    {
        try {
            $userId = (int)Cookie::get(SecurityHelper::COOKIE_USERID, '0');

            $arrSubForms = \SubFormGetArray($formId);
            if (!Arr::isArray($arrSubForms) && !($arrSubForms instanceof \ArrayAccess)) {
                return self::error('Geen subformulieren gevonden voor formulier: ' . $formId);
            }

            if (!isset($arrSubForms[\SUBFORM_ID][$subformIndex])) {
                return self::error('Subformulier niet gevonden');
            }

            $subformId = (int)$arrSubForms[\SUBFORM_ID][$subformIndex];
            $rights = SecurityHelper::checkFormRights($userId, $subformId);

            if ($rights == SecurityHelper::ACCESS_NONE) {
                return self::error('Geen toegang tot dit subformulier');
            }

            // Get subform query
            $sql = $arrSubForms[\SUBFORM_QUERY][$subformIndex] ?? '';
            $parentField = $arrSubForms[\SUBFORM_PARENT][$subformIndex] ?? '';
            $subformName = $arrSubForms[\SUBFORM_NAME][$subformIndex] ?? '';
            $jsonFormName = $arrSubForms[\SUBFORM_JSONFORM][$subformIndex] ?? '';

            // If SQL is empty but we have a JSON form, load the query from JSON definition
            // Use loadRaw() to get raw JSON (not legacy Q_* format)
            if (empty($sql) && !empty($jsonFormName)) {
                $subformJsonDef = JsonFormLoader::loadRaw($jsonFormName);
                if ($subformJsonDef) {
                    $sql = $subformJsonDef['listQuery'] ?? '';
                    // Also get parentField from JSON if not set
                    if (empty($parentField)) {
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

            if ($parentField === '') {
                // Check if SQL has [id] placeholder - if not and we have a parentId, config is incomplete
                if (!empty($parentId) && stripos($sql, '[id]') === false) {
                    \Cma\Services\Logger::error('Subform configuration error: parentField is empty but parentId is provided', [
                        'parentFormId' => $formId,
                        'subformName' => $subformName,
                        'subformIndex' => $subformIndex,
                        'jsonFormName' => $jsonFormName,
                        'parentId' => $parentId,
                    ]);
                    return self::error("Subformulier '$subformName' heeft geen parentField geconfigureerd. Voeg parentField toe aan de subform definitie in het parent form.");
                }
                $sql = str_ireplace('[id]', $parentId, $sql);
            } else {
                // Qualify parentField with table name to avoid MS Access treating it as a parameter
                $qualifiedParentField = $parentField;
                if (!empty($jsonFormName)) {
                    // Use loadRaw() to get raw JSON with 'table' property
                    $subformTableDef = $subformJsonDef ?? JsonFormLoader::loadRaw($jsonFormName);
                    if ($subformTableDef && !empty($subformTableDef['table'])) {
                        $qualifiedParentField = $subformTableDef['table'] . '.' . $parentField;
                    }
                }
                $sql = SQL::addWhere($sql, $qualifiedParentField . '=' . self::formatIdForSql($parentId));
            }

            // Limit subform results to prevent huge data loads
            $sql = SQL::addTop($sql, 500);

            // Get connection
            $connString = $arrSubForms[\SUBFORM_CONN][$subformIndex] ?? '';
            $subConn = CmaRepository::resolveConnectionString($connString);

            $rs = Database::openRS($sql, $subConn);
            if ($rs === null) {
                $sqlOriginal = $arrSubForms[\SUBFORM_QUERY][$subformIndex] ?? '';
                \Cma\Services\Logger::error('Subform query failed in RecordService', [
                    'error' => Database::getLastError(),
                    'sql' => $sql,
                    'sqlOriginal' => $sqlOriginal,
                    'parentFormId' => $formId,
                    'subformIndex' => $subformIndex,
                    'subformName' => $subformName,
                    'parentId' => $parentId,
                    'parentField' => $parentField,
                ]);
                return self::error('Subform query mislukt: ' . Database::getLastError() . ' (SQL: ' . $sql . ', Original: ' . $sqlOriginal . ')');
            }

            $items = [];
            $columns = [];
            $idField = $arrSubForms[\SUBFORM_IDFLD][$subformIndex] ?? 'ID';

            // Get column metadata from first row
            $firstRow = true;
            $columnMeta = [];
            while (!$rs->EOF) {
                // Sanitize all values for UTF-8 (handles Windows-1252 legacy data)
                $rawFields = \App\Library\Str::toUtf8($rs->fetchAssoc());

                $row = [];
                foreach ($rawFields as $key => $value) {
                    if (!is_int($key)) {
                        $row[$key] = $value;
                        // Collect column info from first row (excluding ID field)
                        if ($firstRow && strtolower($key) !== 'id' && $key !== $idField) {
                            $columns[] = $key;
                            // Detect column type from value
                            $type = 'text';
                            if (is_numeric($value) && !is_string($value)) {
                                $type = is_float($value) ? 'decimal' : 'integer';
                            } elseif (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}/', $value)) {
                                $type = 'date';
                            }
                            $columnMeta[$key] = [
                                'name' => $key,
                                'caption' => ucfirst(str_replace('_', ' ', $key)),
                                'type' => $type
                            ];
                        }
                    }
                }
                if (isset($rawFields[$idField])) {
                    $row['_id'] = $rawFields[$idField];
                }
                $items[] = $row;
                $rs->MoveNext();
                $firstRow = false;
            }

            // Build column definitions for lib-table
            $columnDefs = [];
            foreach ($columns as $col) {
                $columnDefs[] = $columnMeta[$col] ?? ['name' => $col, 'caption' => $col, 'type' => 'text'];
            }

            return self::success([
                'rows' => $items,
                'columns' => $columnDefs,
                'totalCount' => count($items),
                'subformId' => $subformId,
                'subformName' => $arrSubForms[\SUBFORM_NAME][$subformIndex] ?? '',
                'parentField' => $arrSubForms[\SUBFORM_PARENT][$subformIndex] ?? '',
                'fullWidth' => (bool)($arrSubForms[\SUBFORM_FULLWIDTH][$subformIndex] ?? false),
                'canAdd' => $rights >= SecurityHelper::ACCESS_FULL,
            ]);
        } catch (\Exception $e) {
            return self::error('Ophalen mislukt: ' . Database::cleanErrorMessage($e->getMessage()));
        }
    }

    // ==================== Helper Methods ====================

    /**
     * Build SELECT fields list from form definition
     */
    protected static function buildSelectFields(array|\ArrayAccess $arrRep, string $tableName, FormDefinition $formDef): string
    {
        $fields = [];

        $rowCount = count($arrRep[\Q_FIELDNAME] ?? []);
        for ($i = 0; $i < $rowCount; $i++) {
            $fieldName = $arrRep[\Q_FIELDNAME][$i] ?? null;
            if ($fieldName === null || $fieldName === '') {
                continue;
            }

            $controlType = (int)($arrRep[\Q_CONTROLTYPEID][$i] ?? 0);
            // PERFORMANCE FIX: Use class constant instead of inline array
            if (in_array($controlType, self::EXCLUDED_CONTROL_TYPES, true)) {
                continue;
            }

            $fields[] = "[$tableName].[$fieldName]";

            // Include image dimension fields
            $widthField = $arrRep[\Q_IMGWIDTHFLD][$i] ?? '';
            if ($widthField !== '') {
                $fields[] = "[$tableName].[$widthField]";
            }
            $heightField = $arrRep[\Q_IMGHEIGHTFLD][$i] ?? '';
            if ($heightField !== '') {
                $fields[] = "[$tableName].[$heightField]";
            }
        }

        // Add last modified fields
        if ($formDef->hasStoreLastModified()) {
            $fields[] = "$tableName.LastModifiedUser";
            $fields[] = "$tableName.LastModifiedDate";
        }

        return implode(', ', $fields);
    }

    /**
     * Format ID for SQL query (handles GUID vs integer)
     */
    public static function formatIdForSql($id): string
    {
        if (is_numeric($id)) {
            return (string)$id;
        }
        // GUIDs contain dashes and/or braces - need special formatting for Access
        if (str_contains((string)$id, '-') || str_contains((string)$id, '{')) {
            return SQL::postGuid((string)$id);
        }
        return SQL::postString((string)$id);
    }

    /**
     * Format field value for output
     */
    protected static function formatFieldValue(string $key, $value, array|\ArrayAccess $arrRep)
    {
        if ($value === null) {
            return null;
        }

        // Find field index
        $fieldIndex = self::findFieldIndex($arrRep, $key);
        if ($fieldIndex === -1) {
            return $value;
        }

        $controlType = (int)($arrRep[\Q_CONTROLTYPEID][$fieldIndex] ?? 0);

        // SECURITY: Never return password values - return empty string
        // The password field in the form will show placeholder text
        if ($controlType === FormRenderer::TYPE_PASSWORD) {
            return '';
        }

        // Format booleans FIRST (before any date processing)
        if ($controlType === FormRenderer::TYPE_CHECKBOX) {
            // Handle various boolean representations from database
            if (is_bool($value)) {
                return $value;
            }
            $strVal = strtolower(trim((string)$value));
            return $strVal === 'true' || $strVal === '1' || $strVal === '-1' || $value === 1 || $value === -1 || $value === true;
        }

        // Format time fields (control type TIME)
        if ($controlType === FormRenderer::TYPE_TIME) {
            if ($value !== '' && $value !== null) {
                // If it's already just a time string like "14:30", return as-is
                if (preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', trim($value))) {
                    // Return just HH:mm
                    $parts = explode(':', trim($value));
                    return sprintf('%02d:%02d', (int)$parts[0], (int)$parts[1]);
                }
                // PERFORMANCE FIX: Use fastParseDateTime instead of strtotime
                $dt = self::fastParseDateTime((string)$value);
                if ($dt !== null) {
                    return $dt->format('H:i');
                }
            }
            return null;
        }

        // Format date fields - check ADO type codes (7=Date, 133=DBDate, 135=DBTimeStamp) or string type names
        $schemaType = $arrRep[\Q_SCHEMA_DATATYPE][$fieldIndex] ?? '';
        $hasDateSchema = false;
        if (is_numeric($schemaType) && in_array((int)$schemaType, [7, 133, 135])) {
            $hasDateSchema = true;
        } elseif (in_array(strtolower((string)$schemaType), ['date', 'datetime', 'datetime2', 'smalldatetime', 'datetimeoffset'])) {
            $hasDateSchema = true;
        }

        if ($hasDateSchema) {
            if ($value !== '' && $value !== null) {
                // Don't try to parse numeric-only values as dates (likely FK values)
                if (is_numeric($value) && !str_contains((string)$value, '-') && !str_contains((string)$value, '/')) {
                    return $value;
                }
                // PERFORMANCE FIX: Use fastParseDateTime instead of strtotime
                $dt = self::fastParseDateTime((string)$value);
                if ($dt !== null) {
                    $year = (int)$dt->format('Y');
                    // Year 1899 indicates a time-only field (MS Access stores times this way)
                    if ($year === 1899) {
                        return $dt->format('H:i');
                    }
                    // Filter out invalid dates
                    if ($year < 1900 || $year > 2100) {
                        return null;
                    }
                    return $dt->format('d-m-Y');
                }
            }
            return null;
        }

        // No date schema - try to detect and format dates automatically
        // This handles cases where database returns datetime but no schema is set
        if (is_string($value) && $value !== '') {
            $formatted = \App\Library\Date::fixValue($value);
            if ($formatted !== $value) {
                // It was recognized as a date
                return $formatted;
            }
        }

        return $value;
    }

    /**
     * Format value for SQL insert/update
     */
    protected static function formatForSql($value, array|\ArrayAccess $arrRep, int $fieldIndex): string
    {
        if ($value === null || $value === '') {
            return 'NULL';
        }

        $controlType = (int)($arrRep[\Q_CONTROLTYPEID][$fieldIndex] ?? 0);

        // Checkboxes
        if ($controlType === FormRenderer::TYPE_CHECKBOX) {
            // Handle various boolean representations: true, 'true', 'True', '1', 1, etc.
            $boolVal = $value === true || strtolower((string)$value) === 'true' || $value === '1' || $value === 1;
            return $boolVal ? '1' : '0';
        }

        // Dates - check ADO type codes (7=Date, 133=DBDate, 135=DBTimeStamp) or string type names
        $schemaType = $arrRep[\Q_SCHEMA_DATATYPE][$fieldIndex] ?? '';
        $isDateField = false;
        if (is_numeric($schemaType) && in_array((int)$schemaType, [7, 133, 135])) {
            $isDateField = true;
        } elseif (in_array(strtolower((string)$schemaType), ['date', 'datetime', 'datetime2', 'smalldatetime', 'datetimeoffset'])) {
            $isDateField = true;
        }

        if ($isDateField) {
            // PERFORMANCE FIX: Use fastParseDateTime instead of strtotime
            $dt = self::fastParseDateTime((string)$value);
            if ($dt !== null) {
                return "'" . $dt->format('Y-m-d H:i:s') . "'";
            }
        }

        // Numbers
        if (($arrRep[\Q_SCHEMA_NUM_PREC][$fieldIndex] ?? '') !== '') {
            if (is_numeric($value)) {
                return str_replace(',', '.', (string)$value);
            }
        }

        // Default: string for SQL
        return SQL::postString($value);
    }

    /**
     * PERFORMANCE FIX: Cache for field index lookups to avoid O(n) search on every call
     * @var array<string, array<string, int>>
     */
    protected static array $fieldIndexCache = [];

    /**
     * Find field index by name
     * PERFORMANCE FIX: Uses cached lookup table for O(1) access instead of O(n) linear search
     */
    protected static function findFieldIndex(array|\ArrayAccess $arrRep, string $fieldName): int
    {
        // Generate a cache key based on field names (using first few field names as identifier)
        $fieldNames = $arrRep[\Q_FIELDNAME] ?? [];
        $cacheKey = count($fieldNames) . '_' . ($fieldNames[0] ?? '') . '_' . ($fieldNames[1] ?? '');

        // Build index map if not cached
        if (!isset(self::$fieldIndexCache[$cacheKey])) {
            self::$fieldIndexCache[$cacheKey] = [];
            $rowCount = count($fieldNames);
            for ($i = 0; $i < $rowCount; $i++) {
                $name = strtolower($fieldNames[$i] ?? '');
                if ($name !== '') {
                    self::$fieldIndexCache[$cacheKey][$name] = $i;
                }
            }
        }

        // O(1) lookup instead of O(n) loop
        $fieldNameLower = strtolower($fieldName);
        return self::$fieldIndexCache[$cacheKey][$fieldNameLower] ?? -1;
    }

    /**
     * Get checklist values for a record
     */
    protected static function getChecklistValues(array|\ArrayAccess $arrRep, $recordId, $conn): array
    {
        $values = [];

        $rowCount = count($arrRep[\Q_FIELDNAME] ?? []);
        for ($i = 0; $i < $rowCount; $i++) {
            $controlType = (int)($arrRep[\Q_CONTROLTYPEID][$i] ?? 0);
            if ($controlType !== FormRenderer::TYPE_CHECKLIST) {
                continue;
            }

            $controlId = $arrRep[\Q_CONTROLID][$i] ?? '';
            $sql = $arrRep[\Q_SQLLIST][$i] ?? '';

            if ($sql === '' || $controlId === '') {
                continue;
            }

            // Replace ID placeholders (single operation)
            $sql = str_ireplace(['[ID]', '[ProdID]'], (string)$recordId, $sql);

            try {
                $rs = Database::openRS($sql, $conn);
                $selected = [];
                while ($rs && !$rs->EOF) {
                    if ($rs->fields['selected'] ?? $rs->fields['Selected'] ?? false) {
                        $selected[] = $rs->fields['ID'] ?? '';
                    }
                    $rs->MoveNext();
                }
                $values['chklst_' . $controlId] = $selected;
            } catch (\Exception $e) {
                // Log but continue
            }
        }

        return $values;
    }

    /**
     * Get sortlist values for a record
     *
     * Returns the current sort order of items for sortlist controls.
     */
    protected static function getSortlistValues(array|\ArrayAccess $arrRep, $recordId, $conn): array
    {
        $values = [];

        $rowCount = count($arrRep[\Q_FIELDNAME] ?? []);
        for ($i = 0; $i < $rowCount; $i++) {
            $controlType = (int)($arrRep[\Q_CONTROLTYPEID][$i] ?? 0);
            if ($controlType !== FormRenderer::TYPE_SORTLIST) {
                continue;
            }

            $controlId = $arrRep[\Q_CONTROLID][$i] ?? '';
            $sql = $arrRep[\Q_SQLLIST][$i] ?? '';

            if ($sql === '' || $controlId === '') {
                continue;
            }

            // Replace ID placeholder
            $sql = str_ireplace(['[ID]', '[ProdID]'], (string)$recordId, $sql);

            try {
                $rs = Database::openRS($sql, $conn);
                $items = [];
                while ($rs && !$rs->EOF) {
                    $items[] = [
                        'id' => $rs->fields['ID'] ?? '',
                        'name' => $rs->fields['DisplayName'] ?? $rs->fields['Name'] ?? '',
                        'sortOrder' => $rs->fields['SortOrder'] ?? 0
                    ];
                    $rs->MoveNext();
                }
                // Sort by SortOrder
                usort($items, fn($a, $b) => ($a['sortOrder'] ?? 0) <=> ($b['sortOrder'] ?? 0));
                $values['srtlst_' . $controlId] = $items;
            } catch (\Exception $e) {
                Logger::error("SortList get error", ['error' => $e->getMessage()]);
            }
        }

        return $values;
    }

    /**
     * Resolve FK display labels for combobox fields
     *
     * For each combobox field with a value, looks up the display text from the source table.
     * Returns array of fieldName__label => displayText pairs.
     *
     * This allows the UI to immediately display the correct text for FK fields
     * even when the combo options haven't been fully loaded yet (lazy loading).
     *
     * @param array|\ArrayAccess $arrRep Form definition array
     * @param array $fields Record field values
     * @param FormDefinition $formDef Form definition object
     * @param mixed $conn Database connection
     * @return array Array of fieldName__label => displayText
     */
    protected static function resolveFkLabels(array|\ArrayAccess $arrRep, array $fields, FormDefinition $formDef, $conn): array
    {
        $labels = [];

        // Collect field lookups - now includes field index for custom SQL access
        // Structure: [ [fieldName, idField, displayField, value, databaseId, sourceTable, sqlList, fieldIndex], ... ]
        $lookups = [];

        $rowCount = count($arrRep[\Q_FIELDNAME] ?? []);
        for ($i = 0; $i < $rowCount; $i++) {
            $controlType = (int)($arrRep[\Q_CONTROLTYPEID][$i] ?? 0);

            // Only process combobox and userlist types
            if (!in_array($controlType, [FormRenderer::TYPE_COMBOBOX, FormRenderer::TYPE_USERLIST], true)) {
                continue;
            }

            $fieldName = $arrRep[\Q_FIELDNAME][$i] ?? '';
            if ($fieldName === '') {
                continue;
            }

            // Get the current value - skip if null/empty
            $value = $fields[$fieldName] ?? null;
            if ($value === null || $value === '' || $value === '0') {
                continue;
            }

            // For USERLIST, use a fixed lookup
            if ($controlType === FormRenderer::TYPE_USERLIST) {
                $lookups[] = [
                    'fieldName' => $fieldName,
                    'idField' => 'ID',
                    'displayField' => 'userFullName',
                    'value' => $value,
                    'databaseId' => '',
                    'sourceTable' => 'tblUsers',
                    'sqlList' => '',
                    'fieldIndex' => $i,
                ];
                continue;
            }

            // For COMBOBOX, get source table config
            $sourceTable = $arrRep[\Q_SOURCETABLE][$i] ?? '';
            $idField = $arrRep[\Q_CTRLIDFIELD][$i] ?? 'ID';
            $displayField = $arrRep[\Q_FOREIGNIDFIELD][$i] ?? '';
            $databaseId = $arrRep[\Q_DATABASEID][$i] ?? '';
            $sqlList = $arrRep[\Q_SQLLIST][$i] ?? '';

            // Skip if no source table or display field defined
            if ($sourceTable === '' || $displayField === '') {
                continue;
            }

            $lookups[] = [
                'fieldName' => $fieldName,
                'idField' => $idField,
                'displayField' => $displayField,
                'value' => $value,
                'databaseId' => $databaseId,
                'sourceTable' => $sourceTable,
                'sqlList' => $sqlList,
                'fieldIndex' => $i,
            ];
        }

        // Separate lookups: custom SQL (must be individual) vs simple (can be batched)
        $customSqlLookups = [];
        $simpleLookups = [];
        foreach ($lookups as $lookup) {
            if (!empty($lookup['sqlList'])) {
                $customSqlLookups[] = $lookup;
            } else {
                $simpleLookups[] = $lookup;
            }
        }

        // Batch simple lookups: group by {sourceTable, databaseId, idField, displayField}
        $batches = [];
        foreach ($simpleLookups as $lookup) {
            $batchKey = $lookup['sourceTable'] . '|' . $lookup['databaseId'] . '|' . $lookup['idField'] . '|' . $lookup['displayField'];
            if (!isset($batches[$batchKey])) {
                $batches[$batchKey] = [
                    'sourceTable' => $lookup['sourceTable'],
                    'databaseId' => $lookup['databaseId'],
                    'idField' => $lookup['idField'],
                    'displayField' => $lookup['displayField'],
                    'fields' => [], // fieldName => value
                ];
            }
            $batches[$batchKey]['fields'][$lookup['fieldName']] = $lookup['value'];
        }

        // Execute batched queries (one per group)
        foreach ($batches as $batch) {
            $targetConn = $conn;
            if ($batch['databaseId'] !== '' && is_numeric($batch['databaseId'])) {
                try {
                    $connString = CmaRepository::getResolvedConnectionString((int)$batch['databaseId']);
                    if ($connString) {
                        $targetConn = Database::getConnection($connString);
                    }
                } catch (\Exception $e) {
                    Logger::debug("resolveFkLabels: could not get connection for DB {$batch['databaseId']}", ['error' => $e->getMessage()]);
                    foreach ($batch['fields'] as $fieldName => $value) {
                        $labels[$fieldName . '__label'] = "Kan '$value' niet opzoeken (database fout)";
                        $labels[$fieldName . '__error'] = true;
                    }
                    continue;
                }
            }

            try {
                // Collect all unique values for the IN clause
                $allValues = array_unique(array_values($batch['fields']));
                $escapedValues = [];
                foreach ($allValues as $v) {
                    $escapedValues[] = is_numeric($v) ? (int)$v : SQL::postString($v);
                }
                $inClause = implode(',', $escapedValues);

                $idField = $batch['idField'];
                $displayField = $batch['displayField'];
                $sourceTable = $batch['sourceTable'];

                $sql = "SELECT [$idField], [$displayField] FROM [$sourceTable] WHERE [$idField] IN ($inClause)";
                $rs = Database::openRS($sql, $targetConn);

                // Build result map: id => display
                $resultMap = [];
                if ($rs !== null) {
                    while (!$rs->EOF) {
                        $id = (string)($rs->fields[$idField] ?? $rs->fields[strtolower($idField)] ?? $rs->fields[0] ?? '');
                        $display = $rs->fields[$displayField] ?? $rs->fields[strtolower($displayField)] ?? $rs->fields[1] ?? '';
                        $resultMap[$id] = Str::toUtf8($display);
                        $rs->MoveNext();
                    }
                }

                // Distribute results back to individual fields
                foreach ($batch['fields'] as $fieldName => $value) {
                    $valueStr = (string)$value;
                    if (isset($resultMap[$valueStr])) {
                        $labels[$fieldName . '__label'] = $resultMap[$valueStr];
                    } else {
                        $fieldLabel = $fieldName;
                        if (stripos($fieldLabel, 'fk') === 0) {
                            $fieldLabel = substr($fieldLabel, 2);
                        }
                        if ($rs === null) {
                            $dbError = Database::getLastError();
                            if (strpos($dbError, '<div') !== false) {
                                $labels[$fieldName . '__label'] = $dbError;
                            } else {
                                $labels[$fieldName . '__label'] = "Kan $fieldLabel '$value' niet opzoeken (query fout)";
                            }
                        } else {
                            $labels[$fieldName . '__label'] = "Kan $fieldLabel '$value' niet vinden in $sourceTable";
                        }
                        $labels[$fieldName . '__error'] = true;
                    }
                }
            } catch (\Exception $e) {
                Logger::debug("resolveFkLabels: batch error", ['error' => $e->getMessage(), 'table' => $batch['sourceTable']]);
                foreach ($batch['fields'] as $fieldName => $value) {
                    $labels[$fieldName . '__label'] = "Fout bij opzoeken: " . Database::cleanErrorMessage($e->getMessage());
                    $labels[$fieldName . '__error'] = true;
                }
            }
        }

        // Process custom SQL lookups individually (cannot be batched)
        foreach ($customSqlLookups as $lookup) {
            $fieldName = $lookup['fieldName'];
            $idField = $lookup['idField'];
            $displayField = $lookup['displayField'];
            $value = $lookup['value'];
            $databaseId = $lookup['databaseId'];
            $sourceTable = $lookup['sourceTable'];
            $sqlList = $lookup['sqlList'];

            $targetConn = $conn;
            if ($databaseId !== '' && is_numeric($databaseId)) {
                try {
                    $connString = CmaRepository::getResolvedConnectionString((int)$databaseId);
                    if ($connString) {
                        $targetConn = Database::getConnection($connString);
                    }
                } catch (\Exception $e) {
                    Logger::debug("resolveFkLabels: could not get connection for DB $databaseId", ['error' => $e->getMessage()]);
                    $labels[$fieldName . '__label'] = "Kan '$value' niet opzoeken (database fout)";
                    $labels[$fieldName . '__error'] = true;
                    continue;
                }
            }

            try {
                $valueEscaped = is_numeric($value) ? (int)$value : SQL::postString($value);
                $sql = SQL::addWhere($sqlList, "[$idField] = $valueEscaped");
                $sql = SQL::addTop($sql, 1);

                $rs = Database::openRS($sql, $targetConn);
                if ($rs === null) {
                    $dbError = Database::getLastError();
                    Logger::debug("resolveFkLabels: query failed", ['field' => $fieldName, 'sql' => $sql, 'error' => $dbError]);
                    $fieldLabel = $fieldName;
                    if (stripos($fieldLabel, 'fk') === 0) {
                        $fieldLabel = substr($fieldLabel, 2);
                    }
                    if (strpos($dbError, '<div') !== false) {
                        $labels[$fieldName . '__label'] = $dbError;
                    } else {
                        $labels[$fieldName . '__label'] = "Kan $fieldLabel '$value' niet opzoeken (query fout)";
                    }
                    $labels[$fieldName . '__error'] = true;
                    continue;
                }

                if (!$rs->EOF) {
                    $display = $rs->fields[$displayField] ?? $rs->fields[strtolower($displayField)] ?? $rs->fields[1] ?? '';
                    $labels[$fieldName . '__label'] = Str::toUtf8($display);
                } else {
                    $fieldLabel = $fieldName;
                    if (stripos($fieldLabel, 'fk') === 0) {
                        $fieldLabel = substr($fieldLabel, 2);
                    }
                    $labels[$fieldName . '__label'] = "Kan $fieldLabel '$value' niet vinden in $sourceTable";
                    $labels[$fieldName . '__error'] = true;
                }
            } catch (\Exception $e) {
                Logger::debug("resolveFkLabels: error looking up $fieldName", ['error' => $e->getMessage()]);
                $labels[$fieldName . '__label'] = "Fout bij opzoeken: " . Database::cleanErrorMessage($e->getMessage());
                $labels[$fieldName . '__error'] = true;
            }
        }

        return $labels;
    }

    /**
     * Save checklist values
     *
     * Checklists store many-to-many relationships in a linking table.
     * The form submits:
     * - chklst_{controlId}: comma-separated IDs of selected items
     * - chklstall_{controlId}: comma-separated IDs of all available items
     */
    protected static function saveChecklistValues(array|\ArrayAccess $arrRep, $recordId, array $data, $conn): void
    {
        $rowCount = count($arrRep[\Q_FIELDNAME] ?? []);

        for ($i = 0; $i < $rowCount; $i++) {
            $controlType = (int)($arrRep[\Q_CONTROLTYPEID][$i] ?? 0);
            if ($controlType !== FormRenderer::TYPE_CHECKLIST) {
                continue;
            }

            $controlId = $arrRep[\Q_CONTROLID][$i] ?? '';
            $sourceTable = $arrRep[\Q_SOURCETABLE][$i] ?? '';
            $idField = $arrRep[\Q_CTRLIDFIELD][$i] ?? ''; // Field linking to main record
            $foreignIdField = $arrRep[\Q_FOREIGNIDFIELD][$i] ?? ''; // Field linking to related record
            $databaseId = $arrRep[\Q_DATABASEID][$i] ?? '';

            if ($sourceTable === '' || $idField === '' || $foreignIdField === '' || $controlId === '') {
                continue;
            }

            // Get the target connection (may be different database)
            $targetConn = $conn;
            if ($databaseId !== '') {
                $connString = \CMA_connect_GetStringByID($databaseId);
                if ($connString) {
                    $targetConn = Database::getConnection($connString);
                }
            }

            // Get form data
            $selectedIdsRaw = $data['chklst_' . $controlId] ?? '';
            $allIdsRaw = $data['chklstall_' . $controlId] ?? '';

            // Parse comma-separated values, filter to integers only for safety
            $selectedIds = array_filter(
                array_map('intval', explode(',', $selectedIdsRaw)),
                fn($v) => $v > 0
            );
            $allIds = array_filter(
                array_map('intval', explode(',', $allIdsRaw)),
                fn($v) => $v > 0
            );

            // PERFORMANCE FIX: Batch operations instead of N+1 queries
            // Step 1: Delete items that are no longer selected (batch delete)
            if (!empty($allIds)) {
                $idsToDelete = array_diff($allIds, $selectedIds);
                if (!empty($idsToDelete)) {
                    $placeholders = implode(',', array_fill(0, count($idsToDelete), '?'));
                    $deleteSql = sprintf(
                        "DELETE FROM %s WHERE %s = ? AND %s IN (%s)",
                        SQL::identifier($sourceTable),
                        SQL::identifier($idField),
                        SQL::identifier($foreignIdField),
                        $placeholders
                    );

                    try {
                        $stmt = $targetConn->prepare($deleteSql);
                        $params = array_merge([$recordId], array_values($idsToDelete));
                        $stmt->execute($params);
                    } catch (\Exception $e) {
                        Logger::error("CheckList delete error", ['error' => $e->getMessage()]);
                    }
                }
            }

            // Step 2: Get existing relationships in one query (batch read)
            $existingIds = [];
            if (!empty($selectedIds)) {
                $placeholders = implode(',', array_fill(0, count($selectedIds), '?'));
                $existsSql = sprintf(
                    "SELECT %s FROM %s WHERE %s = ? AND %s IN (%s)",
                    SQL::identifier($foreignIdField),
                    SQL::identifier($sourceTable),
                    SQL::identifier($idField),
                    SQL::identifier($foreignIdField),
                    $placeholders
                );

                try {
                    $stmt = $targetConn->prepare($existsSql);
                    $params = array_merge([$recordId], $selectedIds);
                    $stmt->execute($params);
                    while ($row = $stmt->fetch(\PDO::FETCH_NUM)) {
                        $existingIds[] = (int)$row[0];
                    }
                } catch (\Exception $e) {
                    Logger::error("CheckList exists check error", ['error' => $e->getMessage()]);
                }
            }

            // Step 3: Insert only new items (batch insert)
            $newIds = array_diff($selectedIds, $existingIds);
            if (!empty($newIds)) {
                // Build batch INSERT with multiple VALUES
                $valuesSql = implode(',', array_fill(0, count($newIds), '(?, ?)'));
                $insertSql = sprintf(
                    "INSERT INTO %s (%s, %s) VALUES %s",
                    SQL::identifier($sourceTable),
                    SQL::identifier($idField),
                    SQL::identifier($foreignIdField),
                    $valuesSql
                );

                try {
                    $stmt = $targetConn->prepare($insertSql);
                    $params = [];
                    foreach ($newIds as $foreignId) {
                        $params[] = $recordId;
                        $params[] = $foreignId;
                    }
                    $stmt->execute($params);
                } catch (\Exception $e) {
                    Logger::error("CheckList batch insert error", ['error' => $e->getMessage()]);
                }
            }
        }
    }

    /**
     * Save sortlist values
     *
     * Sortlists update the SortOrder field in the linking table.
     * The form submits:
     * - srtlst_{controlId}_info: comma-separated IDs in sort order
     */
    protected static function saveSortlistValues(array|\ArrayAccess $arrRep, $recordId, array $data, $conn): void
    {
        $rowCount = count($arrRep[\Q_FIELDNAME] ?? []);

        for ($i = 0; $i < $rowCount; $i++) {
            $controlType = (int)($arrRep[\Q_CONTROLTYPEID][$i] ?? 0);
            if ($controlType !== FormRenderer::TYPE_SORTLIST) {
                continue;
            }

            $controlId = $arrRep[\Q_CONTROLID][$i] ?? '';
            $sourceTable = $arrRep[\Q_SOURCETABLE][$i] ?? '';
            $idField = $arrRep[\Q_CTRLIDFIELD][$i] ?? ''; // The ID field of the sortable items

            if ($sourceTable === '' || $idField === '' || $controlId === '') {
                continue;
            }

            // Get the sorted order from form data
            $sortOrderRaw = $data['srtlst_' . $controlId . '_info'] ?? '';
            $sortedIds = array_filter(
                array_map('intval', explode(',', $sortOrderRaw)),
                fn($v) => $v > 0
            );

            if (empty($sortedIds)) {
                continue;
            }

            // PERFORMANCE FIX: Batch UPDATE using CASE statement instead of N queries
            // Build: UPDATE table SET SortOrder = CASE id WHEN 1 THEN 0 WHEN 2 THEN 1 ... END WHERE id IN (1,2,...)
            $caseParts = [];
            $idPlaceholders = [];
            $params = [];

            foreach ($sortedIds as $position => $itemId) {
                $caseParts[] = "WHEN ? THEN ?";
                $params[] = $itemId;
                $params[] = $position;
                $idPlaceholders[] = '?';
            }

            // Add the IDs again for the WHERE IN clause
            foreach ($sortedIds as $itemId) {
                $params[] = $itemId;
            }

            $updateSql = sprintf(
                "UPDATE %s SET SortOrder = CASE %s %s END WHERE %s IN (%s)",
                SQL::identifier($sourceTable),
                SQL::identifier($idField),
                implode(' ', $caseParts),
                SQL::identifier($idField),
                implode(',', $idPlaceholders)
            );

            try {
                $stmt = $conn->prepare($updateSql);
                $stmt->execute($params);
            } catch (\Exception $e) {
                Logger::error("SortList batch save error", ['error' => $e->getMessage()]);
            }
        }
    }

    /**
     * Save values for custom renderer fields
     *
     * Handles special fields like user_groups, form_notifications, etc.
     * These fields store data in separate tables, not the main record table.
     */
    public static function saveCustomRendererValues(FormDefinition $formDef, $recordId, array $data): void
    {
        // Check if this is the users form - always save user_groups (even if empty)
        // When no checkboxes are selected, the field won't be in POST, so use empty array
        if ($formDef->getFormName() === 'users') {
            // Check if form has the user_groups field (custom renderer)
            $hasUserGroupsField = false;
            $jsonData = $formDef->getJsonData();
            $fields = $jsonData['fields'] ?? [];
            foreach ($fields as $field) {
                if (($field['name'] ?? '') === 'user_groups' && ($field['renderer'] ?? '') === 'security_groups') {
                    $hasUserGroupsField = true;
                    break;
                }
            }
            if ($hasUserGroupsField) {
                $groupIds = $data['user_groups'] ?? [];
                self::saveUserGroups($recordId, $groupIds);
            }
        }

        // Check if this is the groups form - save group menu/form rights and group members
        if ($formDef->getFormName() === 'groups') {
            self::saveGroupRights($recordId, $data);

            // Check if form has the group_members field (custom renderer)
            $hasGroupMembersField = false;
            $jsonData = $formDef->getJsonData();
            $fields = $jsonData['fields'] ?? [];
            foreach ($fields as $field) {
                if (($field['name'] ?? '') === 'group_members' && ($field['renderer'] ?? '') === 'group_members') {
                    $hasGroupMembersField = true;
                    break;
                }
            }
            if ($hasGroupMembersField) {
                $userIds = $data['group_members'] ?? [];
                self::saveGroupMembers($recordId, $userIds);
            }
        }

        // Save form notifications for users form
        if ($formDef->getFormName() === 'users') {
            $hasFormNotifications = false;
            $hasDataNotifications = false;
            $jsonData = $formDef->getJsonData();
            $fields = $jsonData['fields'] ?? [];
            foreach ($fields as $field) {
                if (($field['renderer'] ?? '') === 'form_notifications') {
                    $hasFormNotifications = true;
                }
                if (($field['renderer'] ?? '') === 'data_notifications') {
                    $hasDataNotifications = true;
                }
            }
            if ($hasFormNotifications) {
                self::saveFormNotifications($recordId, $data['form_notifications'] ?? []);
            }
            if ($hasDataNotifications) {
                self::saveDataNotifications($recordId, $data['data_notifications'] ?? []);
            }
        }
    }

    /**
     * Save user group memberships
     *
     * Updates tblGroupMembers for the given user.
     * Replaces all existing memberships with the new selection.
     *
     * @param int|string $userId User ID
     * @param array $groupIds Array of group IDs the user should belong to
     */
    protected static function saveUserGroups($userId, $groupIds): void
    {
        if (!$userId) {
            return;
        }

        // Ensure groupIds is an array
        if (!Arr::isArray($groupIds)) {
            $groupIds = [];
        }

        // Filter to valid integer IDs
        $groupIds = array_filter(
            array_map('intval', $groupIds),
            fn($v) => $v > 0
        );

        try {
            // Get users database connection
            $usersConn = Database::getConnection('users');
            if (!$usersConn) {
                Logger::error("saveUserGroups: Failed to get users database connection");
                return;
            }

            $userId = (int)$userId;

            // Delete all existing memberships for this user
            $deleteSql = "DELETE FROM tblGroupMembers WHERE fkUser = ?";
            $stmt = $usersConn->prepare($deleteSql);
            $stmt->execute([$userId]);

            // Insert new memberships
            if (!empty($groupIds)) {
                $insertSql = "INSERT INTO tblGroupMembers (fkUser, fkGroup) VALUES (?, ?)";
                $stmt = $usersConn->prepare($insertSql);

                foreach ($groupIds as $groupId) {
                    $stmt->execute([$userId, $groupId]);
                }
            }

            Logger::info("saveUserGroups: Saved group memberships", ['userId' => $userId, 'count' => count($groupIds)]);
        } catch (\Exception $e) {
            Logger::error("saveUserGroups: Error", ['error' => $e->getMessage()]);
        }
    }

    /**
     * Save group members
     *
     * Updates tblGroupMembers for the given group.
     * Replaces all existing members with the new selection.
     *
     * @param int|string $groupId Group ID
     * @param array $userIds Array of user IDs that should belong to this group
     */
    protected static function saveGroupMembers($groupId, $userIds): void
    {
        if (!$groupId) {
            return;
        }

        // Ensure userIds is an array
        if (!Arr::isArray($userIds)) {
            $userIds = [];
        }

        // Filter to valid integer IDs
        $userIds = array_filter(
            array_map('intval', $userIds),
            fn($v) => $v > 0
        );

        try {
            // Get users database connection
            $usersConn = Database::getConnection('users');
            if (!$usersConn) {
                Logger::error("saveGroupMembers: Failed to get users database connection");
                return;
            }

            $groupId = (int)$groupId;

            // Delete all existing members for this group
            $deleteSql = "DELETE FROM tblGroupMembers WHERE fkGroup = ?";
            $stmt = $usersConn->prepare($deleteSql);
            $stmt->execute([$groupId]);

            // Insert new members
            if (!empty($userIds)) {
                $insertSql = "INSERT INTO tblGroupMembers (fkUser, fkGroup) VALUES (?, ?)";
                $stmt = $usersConn->prepare($insertSql);

                foreach ($userIds as $userId) {
                    $stmt->execute([$userId, $groupId]);
                }
            }

            Logger::info("saveGroupMembers: Saved group members", ['groupId' => $groupId, 'count' => count($userIds)]);
        } catch (\Exception $e) {
            Logger::error("saveGroupMembers: Error", ['error' => $e->getMessage()]);
        }
    }

    /**
     * Save group access rights to menus and forms
     *
     * Parses POST data for group_menu_rights and group_report_rights fields
     * and saves them to tblGroupRights.
     *
     * Field name format:
     * - group_menu_rights_menu_{menuId} = access level (0, 10, 30)
     * - group_menu_rights_form_{formId} = access level (0, 10, 30)
     * - group_menu_rights_menu_{menuId}_btn1..5 = button checkbox (1 if checked)
     * - group_report_rights_report_{reportId} = access level (0, 10, 30)
     *
     * @param int|string $groupId Group ID
     * @param array $data POST data array
     */
    protected static function saveGroupRights($groupId, array $data): void
    {
        if (!$groupId) {
            Logger::debug("saveGroupRights: No groupId provided");
            return;
        }

        // Debug: Log all keys that match group_menu_rights pattern
        $rightsKeys = array_filter(array_keys($data), fn($k) => strpos($k, 'group_menu_rights') === 0 || strpos($k, 'group_report_rights') === 0);
        Logger::info("saveGroupRights: Called for group $groupId, found " . count($rightsKeys) . " rights keys");
        if (count($rightsKeys) > 0) {
            Logger::info("saveGroupRights: First 10 rights keys", ['keys' => array_slice($rightsKeys, 0, 10)]);
            // Log first 3 key-value pairs for debugging
            $samples = [];
            foreach (array_slice($rightsKeys, 0, 3) as $k) {
                $samples[$k] = $data[$k] ?? '(not set)';
            }
            Logger::info("saveGroupRights: Sample values", $samples);
        } else {
            // Log ALL keys to see what's in the data array
            Logger::info("saveGroupRights: No rights keys found. All data keys", ['allKeys' => array_slice(array_keys($data), 0, 20)]);
        }

        try {
            $usersConn = Database::getConnection('users');
            if (!$usersConn) {
                Logger::error("saveGroupRights: Failed to get users database connection");
                return;
            }

            $groupId = (int)$groupId;
            $rights = [];

            // Parse group_menu_rights fields from POST data
            foreach ($data as $key => $value) {
                // Match patterns like: group_menu_rights_menu_123 or group_menu_rights_form_456
                if (preg_match('/^group_menu_rights_(menu|form)_(\d+)$/', $key, $matches)) {
                    $type = $matches[1];
                    $objectId = (int)$matches[2];
                    $typeId = ($type === 'menu') ? 10 : 30; // 10 = menu, 30 = form
                    $accessLevel = (int)$value;

                    $rightKey = $typeId . '_' . $objectId;
                    if (!isset($rights[$rightKey])) {
                        $rights[$rightKey] = [
                            'typeId' => $typeId,
                            'objectId' => $objectId,
                            'accessLevel' => 0,
                            'buttons' => [false, false, false, false, false]
                        ];
                    }
                    $rights[$rightKey]['accessLevel'] = $accessLevel;
                }
                // Match button patterns: group_menu_rights_menu_123_btn1
                elseif (preg_match('/^group_menu_rights_(menu|form)_(\d+)_btn(\d)$/', $key, $matches)) {
                    $type = $matches[1];
                    $objectId = (int)$matches[2];
                    $buttonIdx = (int)$matches[3] - 1; // Convert 1-5 to 0-4
                    $typeId = ($type === 'menu') ? 10 : 30;

                    $rightKey = $typeId . '_' . $objectId;
                    if (!isset($rights[$rightKey])) {
                        $rights[$rightKey] = [
                            'typeId' => $typeId,
                            'objectId' => $objectId,
                            'accessLevel' => 0,
                            'buttons' => [false, false, false, false, false]
                        ];
                    }
                    if ($buttonIdx >= 0 && $buttonIdx < 5) {
                        // Accept '1', 'True', 'true', or 'on' as checked value
                        $rights[$rightKey]['buttons'][$buttonIdx] = ($value == '1' || strtolower($value) === 'true' || $value === 'on');
                    }
                }
                // Match report rights: group_report_rights_report_123
                // Report rights are checkboxes, so value is "1" or "True" when checked
                elseif (preg_match('/^group_report_rights_report_(\d+)$/', $key, $matches)) {
                    $objectId = (int)$matches[1];
                    // Checkbox value: "1", "True", "true", "on" = has access (level 10)
                    $isChecked = ($value == '1' || strtolower($value) === 'true' || $value === 'on');
                    $accessLevel = $isChecked ? 10 : 0; // 10 = read access for reports
                    $typeId = 20; // 20 = report

                    $rightKey = $typeId . '_' . $objectId;
                    $rights[$rightKey] = [
                        'typeId' => $typeId,
                        'objectId' => $objectId,
                        'accessLevel' => $accessLevel,
                        'buttons' => [false, false, false, false, false]
                    ];
                }
            }

            // Debug: Log parsed rights
            Logger::info("saveGroupRights: Parsed " . count($rights) . " unique rights from data");
            if (count($rights) > 0) {
                $sample = array_slice($rights, 0, 2, true);
                Logger::info("saveGroupRights: Sample parsed rights", ['sample' => $sample]);
            }

            // Delete existing rights for this group (menu and form types)
            $deleteSql = "DELETE FROM tblGroupRights WHERE fkGroup = ? AND secObjectType IN (10, 20, 30)";
            $stmt = $usersConn->prepare($deleteSql);
            $stmt->execute([$groupId]);

            // Insert new rights (only for non-zero access levels or with buttons)
            $insertSql = "INSERT INTO tblGroupRights (fkGroup, secObjectType, secObjectID, secAccessType, " .
                "secButton1, secButton2, secButton3, secButton4, secButton5) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $usersConn->prepare($insertSql);

            $savedCount = 0;
            foreach ($rights as $right) {
                // Only save if access level > 0 or any button is enabled
                $hasButtons = in_array(true, $right['buttons'], true);
                if ($right['accessLevel'] > 0 || $hasButtons) {
                    $stmt->execute([
                        $groupId,
                        $right['typeId'],
                        $right['objectId'],
                        $right['accessLevel'],
                        $right['buttons'][0] ? 1 : 0,
                        $right['buttons'][1] ? 1 : 0,
                        $right['buttons'][2] ? 1 : 0,
                        $right['buttons'][3] ? 1 : 0,
                        $right['buttons'][4] ? 1 : 0
                    ]);
                    $savedCount++;
                }
            }

            Logger::info("saveGroupRights: Saved rights", ['groupId' => $groupId, 'count' => $savedCount]);
        } catch (\Exception $e) {
            Logger::error("saveGroupRights: Error", ['error' => $e->getMessage()]);
        }
    }

    /**
     * Save form notification subscriptions for a user
     *
     * Updates tblNotifications: which forms this user receives email notifications for.
     * Uses delete-then-insert pattern (same as saveUserGroups).
     */
    protected static function saveFormNotifications($userId, $formIds): void
    {
        if (!$userId) return;

        if (!Arr::isArray($formIds)) {
            $formIds = [];
        }

        $formIds = array_filter(
            array_map('intval', $formIds),
            fn($v) => $v > 0
        );

        try {
            $usersConn = Database::getConnection('users');
            if (!$usersConn) {
                Logger::error("saveFormNotifications: Failed to get users database connection");
                return;
            }

            $userId = (int)$userId;

            // Delete existing subscriptions
            $stmt = $usersConn->prepare("DELETE FROM tblNotifications WHERE fkUserID = ?");
            $stmt->execute([$userId]);

            // Insert new subscriptions
            if (!empty($formIds)) {
                $stmt = $usersConn->prepare("INSERT INTO tblNotifications (fkUserID, fkFormID) VALUES (?, ?)");
                foreach ($formIds as $formId) {
                    $stmt->execute([$userId, $formId]);
                }
            }

            Logger::info("saveFormNotifications: Saved", ['userId' => $userId, 'count' => count($formIds)]);
        } catch (\Exception $e) {
            Logger::error("saveFormNotifications: Error", ['error' => $e->getMessage()]);
        }
    }

    /**
     * Save data notification settings for a user
     *
     * Updates tblUserDataNotifications: data source expiry alerts.
     * Each entry has fkStore, notAantalDagen, notParameter, notBeschrijving.
     */
    protected static function saveDataNotifications($userId, $notifications): void
    {
        if (!$userId) return;

        if (!Arr::isArray($notifications)) {
            $notifications = [];
        }

        try {
            $usersConn = Database::getConnection('users');
            if (!$usersConn) {
                Logger::error("saveDataNotifications: Failed to get users database connection");
                return;
            }

            $userId = (int)$userId;

            // Delete existing notifications
            $stmt = $usersConn->prepare("DELETE FROM tblUserDataNotifications WHERE fkUser = ?");
            $stmt->execute([$userId]);

            // Insert new notifications (each item has storeId, days, parameter, description)
            if (!empty($notifications)) {
                $stmt = $usersConn->prepare(
                    "INSERT INTO tblUserDataNotifications (fkUser, fkStore, notAantalDagen, notParameter, notBeschrijving) VALUES (?, ?, ?, ?, ?)"
                );
                foreach ($notifications as $notif) {
                    if (!empty($notif['storeId'])) {
                        $stmt->execute([
                            $userId,
                            (int)$notif['storeId'],
                            (int)($notif['days'] ?? 0),
                            $notif['parameter'] ?? '',
                            $notif['description'] ?? ''
                        ]);
                    }
                }
            }

            Logger::info("saveDataNotifications: Saved", ['userId' => $userId, 'count' => count($notifications)]);
        } catch (\Exception $e) {
            Logger::error("saveDataNotifications: Error", ['error' => $e->getMessage()]);
        }
    }

    /**
     * Delete checklist values
     *
     * Removes all relationships for the given record from checklist linking tables.
     */
    protected static function deleteChecklistValues(array|\ArrayAccess $arrRep, $recordId, $conn): void
    {
        $rowCount = count($arrRep[\Q_FIELDNAME] ?? []);

        for ($i = 0; $i < $rowCount; $i++) {
            $controlType = (int)($arrRep[\Q_CONTROLTYPEID][$i] ?? 0);
            if ($controlType !== FormRenderer::TYPE_CHECKLIST) {
                continue;
            }

            $sourceTable = $arrRep[\Q_SOURCETABLE][$i] ?? '';
            $idField = $arrRep[\Q_CTRLIDFIELD][$i] ?? '';
            $databaseId = $arrRep[\Q_DATABASEID][$i] ?? '';

            if ($sourceTable === '' || $idField === '') {
                continue;
            }

            // Get the target connection (may be different database)
            $targetConn = $conn;
            if ($databaseId !== '') {
                $connString = \CMA_connect_GetStringByID($databaseId);
                if ($connString) {
                    $targetConn = Database::getConnection($connString);
                }
            }

            // Delete all relationships for this record
            $deleteSql = sprintf(
                "DELETE FROM %s WHERE %s = ?",
                SQL::identifier($sourceTable),
                SQL::identifier($idField)
            );

            try {
                $stmt = $targetConn->prepare($deleteSql);
                $stmt->execute([$recordId]);
            } catch (\Exception $e) {
                Logger::error("CheckList delete error", ['error' => $e->getMessage()]);
            }
        }
    }

    /**
     * Delete sortlist values
     *
     * Note: Sortlists typically don't need special delete handling as the items
     * themselves are usually cascade-deleted with the parent record, or they remain
     * as orphan items. This is kept for consistency with the interface.
     */
    protected static function deleteSortlistValues(array|\ArrayAccess $arrRep, $recordId, $conn): void
    {
        // Sortlists don't typically need special delete handling
        // The items in a sortlist are usually standalone records that should
        // be handled by their own delete operations or cascade rules
        // This method is kept for API consistency
    }

    /**
     * Invalidate combobox caches asynchronously when a source table is modified.
     * Runs in background without waiting for completion.
     *
     * @param string $tableName Table name that was modified
     */
    protected static function invalidateComboboxCachesAsync(string $tableName): void
    {
        if ($tableName === '') {
            return;
        }

        // Fire-and-forget: clear ComboPlus cache for this table
        $cacheKey = 'CMA_ComboPlus_' . strtoupper($tableName);
        Cache::delete($cacheKey);

        // Find all forms that use this table as a combobox source and invalidate their caches
        // Uses JSON form definitions instead of database query
        try {
            $tableNameUpper = strtoupper($tableName);
            $formsToInvalidate = self::findFormsUsingSourceTable($tableNameUpper);

            foreach ($formsToInvalidate as $formId) {
                // Invalidate form template cache
                \Cma\FormTemplate::invalidateForForm($formId);
            }

            // Also invalidate the general forms cache group
            Cache::invalidateGroup('forms');
        } catch (\Exception $e) {
            // Log error but don't block - this is a background operation
            Logger::error('ComboboxCacheInvalidation error', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Find all forms that have fields referencing a specific source table.
     * Used for cache invalidation when a source table is modified.
     *
     * @param string $tableNameUpper Uppercase table name to search for
     * @return array List of sourceFormIds that use this table
     */
    private static function findFormsUsingSourceTable(string $tableNameUpper): array
    {
        static $sourceTableCache = null;

        // Build cache of sourceTable -> formIds mapping on first call
        if ($sourceTableCache === null) {
            $sourceTableCache = [];

            // Scan both internal and app form directories
            $directories = [
                __DIR__ . '/../../assets/forms/definitions',  // Internal/system forms
                __DIR__ . '/../../../assets/forms',           // App-specific forms
            ];

            foreach ($directories as $dir) {
                if (!is_dir($dir)) {
                    continue;
                }

                $jsonFiles = glob($dir . '/*.json');
                foreach ($jsonFiles as $file) {
                    $content = @file_get_contents($file);
                    if ($content === false) {
                        continue;
                    }

                    $formDef = json_decode($content, true);
                    if (!$formDef || !isset($formDef['sourceFormId'])) {
                        continue;
                    }

                    $formId = (int)$formDef['sourceFormId'];
                    $fields = $formDef['fields'] ?? [];

                    foreach ($fields as $field) {
                        $sourceTable = strtoupper($field['sourceTable'] ?? '');
                        if ($sourceTable !== '') {
                            if (!isset($sourceTableCache[$sourceTable])) {
                                $sourceTableCache[$sourceTable] = [];
                            }
                            if (!in_array($formId, $sourceTableCache[$sourceTable], true)) {
                                $sourceTableCache[$sourceTable][] = $formId;
                            }
                        }
                    }
                }
            }
        }

        return $sourceTableCache[$tableNameUpper] ?? [];
    }
}
