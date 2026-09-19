<?php

namespace Cma;

use App\Library\Application;
use App\Library\Arr;
use App\Library\Cache;
use App\Library\Cookie;
use App\Library\Database;
use App\Library\Request;
use App\Library\Server;
use App\Library\SQL;
use App\Library\Str;
use Cma\Services\ConfigFormService;
use Cma\Services\ListServiceHelper;
use Cma\Services\Logger;
use Cma\Services\OptionsService;
use Cma\Services\RecordService;
use PDO;

require_once __DIR__ . '/Services/ConfigFormService.php';

/**
 * List Mode Constants
 *
 * Determines how the list is displayed and whether filtering is required.
 * Based on original list.asp logic.
 */
class ListMode
{
    // Display modes
    public const DISPLAY_TREE = 1;      // Tree/grouped display (LIST_TREE)
    public const DISPLAY_TABLE = 2;     // Table/grid display (LIST_TABLE)

    // Filter modes - why filtering is shown
    public const FILTER_NONE = 0;           // No filtering required, show full list
    public const FILTER_USER_REQUESTED = 1; // User clicked search button
    public const FILTER_TOO_MANY_RECORDS = 2; // Record count exceeds LIST_LIMIT
    public const FILTER_REPOSITORY_FORCED = 3; // Form definition has FilterFieldName set

    public const FLUSH_LIMIT = 200;     // Flush output buffer if more than this

    /** Rows above which a list demands a search filter (LIST_LIMIT setting). */
    public static function listLimit(): int
    {
        return (int) \App\Library\Settings::get('list_limit');
    }
}

/**
 * ADO Data Type Constants
 *
 * Standard ADO data type enumeration values used for schema detection.
 * See: https://docs.microsoft.com/en-us/sql/ado/reference/ado-api/datatypeenum
 */
class AdoType
{
    // Date/Time types
    public const DATE = 7;              // adDate
    public const DB_DATE = 133;         // adDBDate
    public const DB_TIME = 134;         // adDBTime
    public const DB_TIMESTAMP = 135;    // adDBTimeStamp

    // Boolean type
    public const BOOLEAN = 11;          // adBoolean

    // Integer types
    public const TINY_INT = 16;         // adTinyInt
    public const SMALL_INT = 2;         // adSmallInt
    public const INTEGER = 3;           // adInteger
    public const BIG_INT = 20;          // adBigInt
    public const UNSIGNED_TINY_INT = 17;  // adUnsignedTinyInt
    public const UNSIGNED_SMALL_INT = 18; // adUnsignedSmallInt
    public const UNSIGNED_INT = 19;       // adUnsignedInt
    public const UNSIGNED_BIG_INT = 21;   // adUnsignedBigInt

    // Decimal types
    public const CURRENCY = 6;          // adCurrency
    public const DECIMAL = 14;          // adDecimal
    public const NUMERIC = 131;         // adNumeric
    public const SINGLE = 4;            // adSingle
    public const DOUBLE = 5;            // adDouble

    // Helper arrays for type checking
    public const DATE_TYPES = [self::DATE, self::DB_DATE, self::DB_TIME, self::DB_TIMESTAMP];
    public const INTEGER_TYPES = [
        self::TINY_INT, self::SMALL_INT, self::INTEGER, self::BIG_INT,
        self::UNSIGNED_TINY_INT, self::UNSIGNED_SMALL_INT, self::UNSIGNED_INT, self::UNSIGNED_BIG_INT
    ];
    public const DECIMAL_TYPES = [self::CURRENCY, self::DECIMAL, self::NUMERIC, self::SINGLE, self::DOUBLE];
    public const BOOLEAN_TYPES = [self::BOOLEAN];
}

/**
 * CMA Form Data Provider
 *
 * Provides JSON data for AJAX form operations:
 * - List data with search/filter/pagination
 * - Record data for form population
 * - Subform data
 * - Combo box options
 * - Checklist options
 *
 * All methods return arrays suitable for json_encode().
 */
class FormDataProvider
{
    /**
     * Get list data for a form
     * @deprecated Use Services\ListService::getListData() directly
     */
    public static function getListData(int $formId, array $options = []): array
    {
        return Services\ListService::getListData($formId, $options);
    }

    /**
     * Get record data for a specific ID
     * @deprecated Use Services\RecordService::getRecord() directly
     */
    public static function getRecordData(int $formId, string|int $recordId): array
    {
        return Services\RecordService::getRecord($formId, $recordId);
    }

    /**
     * Get subform data
     * @deprecated Use Services\RecordService::getSubformData() directly
     */
    public static function getSubformData(int $formId, string|int $parentId, int $subformIndex): array
    {
        return Services\RecordService::getSubformData($formId, $parentId, $subformIndex);
    }

    /**
     * Get combo box options
     * @deprecated Use Services\OptionsService::getComboOptions() directly
     */
    public static function getComboOptions(int $formId, string $fieldName, string $search = ''): array
    {
        return Services\OptionsService::getComboOptions($formId, $fieldName, $search);
    }

    /**
     * Get combo box options for multiple fields in one batch (more efficient)
     * Uses single form definition load and connection for all fields
     *
     * @param int $formId Form ID
     * @param array $fieldNames List of field names
     * @param array $recordContext Optional record context for parameter replacement in SQL (e.g., ['fkOpleiding' => 123])
     */
    public static function getComboOptionsBatch(int $formId, array $fieldNames, array $recordContext = []): array
    {
        return Services\OptionsService::getComboOptionsBatch($formId, $fieldNames, $recordContext);
    }

    /**
     * Get checklist options
     * @deprecated Use Services\OptionsService::getChecklistOptions() directly
     */
    public static function getChecklistOptions(int $formId, int $controlId, string|int $recordId): array
    {
        return Services\OptionsService::getChecklistOptions($formId, $controlId, $recordId);
    }

    /**
     * Save record data
     * @deprecated Use Services\RecordService::save() directly
     */
    public static function saveRecord(int $formId, string|int|null $recordId, array $data): array
    {
        return Services\RecordService::save($formId, $recordId, $data);
    }

    /**
     * Delete a record
     * @deprecated Use Services\RecordService::delete() directly
     */
    public static function deleteRecord(int $formId, string|int $recordId): array
    {
        return Services\RecordService::delete($formId, $recordId);
    }

    // =========================================================================
    /**
     * Build SELECT fields from form definition
     */
    private static function buildSelectFields(array|\ArrayAccess $arrRep, string $tableName, FormDefinition $formDef): string
    {
        $fields = [];

        $rowCount = count($arrRep[\Q_FIELDNAME] ?? []);
        for ($i = 0; $i < $rowCount; $i++) {
            $fieldName = $arrRep[\Q_FIELDNAME][$i] ?? null;
            if ($fieldName === null) {
                continue;
            }

            $controlType = (int)($arrRep[\Q_CONTROLTYPEID][$i] ?? 0);
            if (in_array($controlType, [
                FormRenderer::TYPE_GROUPSEPARATOR,
                FormRenderer::TYPE_CHECKLIST,
                FormRenderer::TYPE_SORTLIST,
                FormRenderer::TYPE_HTMLSTRIP,
                FormRenderer::TYPE_THUMBNAIL,
            ])) {
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
     * Get list SQL for a form
     */
    private static function getListSql(int $formId, FormDefinition $formDef, array $options): ?string
    {
        // Get list SQL from JSON definition first
        $listSql = $formDef->getListQuery();
        if ($listSql !== '' && $listSql !== null) {
            return $listSql;
        }

        // Build default list SQL as fallback
        $tableName = $formDef->getSqlTableName();
        $idField = $formDef->getFormIdField();

        if ($tableName === '' || $idField === '') {
            return null;
        }

        // Build efficient SELECT with only needed columns
        $columns = [$idField];
        $listColumns = $formDef->getListColumns();
        foreach ($listColumns as $col) {
            $fieldName = $col['field'] ?? '';
            if ($fieldName !== '' && !in_array($fieldName, $columns)) {
                $columns[] = $fieldName;
            }
        }

        // Fallback to SELECT * if no columns found
        if (count($columns) <= 1) {
            return "SELECT * FROM $tableName";
        }

        $columnList = implode(', ', array_map(fn($c) => "[$c]", $columns));
        return "SELECT $columnList FROM $tableName";
    }

    /**
     * Apply search filter to SQL
     */
    private static function applySearchFilter(string $sql, string $search, FormDefinition $formDef): string
    {
        return ListServiceHelper::applySearchFilter($sql, $search, $formDef);
    }

    /**
     * Apply pagination to SQL
     * Returns array with 'sql' and 'php_offset' (for Access manual offset handling)
     */
    private static function applyPagination(string $sql, int $offset, int $limit, string $sortColumn, string $sortDir): array
    {
        $phpOffset = 0;

        if (Database::isSQLServer()) {
            // SQL Server 2012+ syntax with OFFSET/FETCH
            if ($sortColumn !== '') {
                $sql .= " ORDER BY [$sortColumn] $sortDir";
            } else {
                // Need ORDER BY for OFFSET
                $sql .= " ORDER BY 1";
            }
            $sql .= " OFFSET $offset ROWS FETCH NEXT $limit ROWS ONLY";
        } else {
            // Access: Use TOP and handle offset in PHP
            // Handle DISTINCT properly when adding TOP
            $topCount = $offset + $limit;
            $sql = SQL::addTop($sql, $topCount);

            if ($sortColumn !== '') {
                $sql .= " ORDER BY [$sortColumn] $sortDir";
            }

            // Tell caller to skip $offset rows in PHP
            $phpOffset = $offset;
        }

        return ['sql' => $sql, 'php_offset' => $phpOffset];
    }

    /**
     * Find field index by name
     */
    private static function findFieldIndex(array|\ArrayAccess $arrRep, string $fieldName): int
    {
        $rowCount = count($arrRep[\Q_FIELDNAME] ?? []);
        for ($i = 0; $i < $rowCount; $i++) {
            if (strtolower($arrRep[\Q_FIELDNAME][$i] ?? '') === strtolower($fieldName)) {
                return $i;
            }
        }
        return -1;
    }

    /**
     * Format field value for JSON output
     */
    private static function formatFieldValue(string $fieldName, $value, array|\ArrayAccess $arrRep): mixed
    {
        if ($value === null) {
            return null;
        }

        // Find field index
        $fieldIndex = self::findFieldIndex($arrRep, $fieldName);
        if ($fieldIndex === -1) {
            return $value;
        }

        $controlType = (int)($arrRep[\Q_CONTROLTYPEID][$fieldIndex] ?? 0);

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
                // Parse datetime and extract time
                $timestamp = strtotime($value);
                if ($timestamp !== false) {
                    return date('H:i', $timestamp);
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
                $timestamp = strtotime($value);
                if ($timestamp !== false) {
                    // Filter out Access "zero date" (1899-12-30) and invalid dates
                    $year = (int)date('Y', $timestamp);
                    if ($year < 1900 || $year > 2100) {
                        return null;
                    }
                    return date('d-m-Y', $timestamp);
                }
            }
            return null;
        }

        return $value;
    }

    /**
     * Format value for SQL
     */
    private static function formatForSql($value, array|\ArrayAccess $arrRep, int $fieldIndex): string
    {
        if ($value === null || $value === '') {
            return 'NULL';
        }

        $controlType = (int)($arrRep[\Q_CONTROLTYPEID][$fieldIndex] ?? 0);

        // Checkboxes
        if ($controlType === FormRenderer::TYPE_CHECKBOX) {
            $boolVal = $value === true || $value === 'true' || $value === '1' || $value === 1;
            return $boolVal ? '1' : '0';
        }

        // Dates - check ADO type codes (7=Date, 133=DBDate, 135=DBTimeStamp) or string type names
        $schemaType = $arrRep[\Q_SCHEMA_DATATYPE][$fieldIndex] ?? '';
        $isDateField = false;
        if (is_numeric($schemaType) && in_array((int)$schemaType, [7, 133, 135])) {
            $isDateField = true;
        } elseif (in_array(strtolower((string)$schemaType), ['date', 'datetime', 'datetime2', 'smalldatetime'])) {
            $isDateField = true;
        }

        if ($isDateField) {
            $timestamp = strtotime($value);
            if ($timestamp !== false) {
                return "'" . date('Y-m-d H:i:s', $timestamp) . "'";
            }
        }

        // Numbers
        if (($arrRep[\Q_SCHEMA_NUM_PREC][$fieldIndex] ?? '') !== '') {
            if (is_numeric($value)) {
                return str_replace(',', '.', (string)$value);
            }
        }

        // Default: string
        return SQL::postString($value);
    }

    /**
     * Get checklist values for a record
     */
    private static function getChecklistValues(array|\ArrayAccess $arrRep, string|int $recordId, $conn): array
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
                    $chkRow = $rs->fetchAssoc();
                    if ($chkRow['selected'] ?? $chkRow['Selected'] ?? false) {
                        $selected[] = $chkRow['ID'] ?? '';
                    }
                    $rs->MoveNext();
                }
                $values['chklst_' . $controlId] = $selected;
            } catch (\Exception $e) {
                // The form still opens, but with an empty checklist a save
                // would wipe the selection — the admin has to know.
                \App\Library\ErrorHandler::report($e, 'Checklist-waarden niet geladen (' . $controlId . ')');
            }
        }

        return $values;
    }

    /**
     * Get sortlist values for a record
     */
    private static function getSortlistValues(array|\ArrayAccess $arrRep, string|int $recordId, $conn): array
    {
        // Similar to checklist but for sortable lists
        return [];
    }

    /**
     * Save checklist values
     */
    private static function saveChecklistValues(array|\ArrayAccess $arrRep, string|int $recordId, array $data, $conn): void
    {
        // Implementation for saving checklist relations
    }

    /**
     * Save sortlist values
     */
    private static function saveSortlistValues(array|\ArrayAccess $arrRep, string|int $recordId, array $data, $conn): void
    {
        // Implementation for saving sortlist order
    }

    /**
     * Delete checklist values
     */
    private static function deleteChecklistValues(array|\ArrayAccess $arrRep, string|int $recordId, $conn): void
    {
        // Implementation for deleting checklist relations
    }

    /**
     * Delete sortlist values
     */
    private static function deleteSortlistValues(array|\ArrayAccess $arrRep, string|int $recordId, $conn): void
    {
        // Implementation for deleting sortlist order
    }

    /**
     * Get tree HTML for list panel
     * @deprecated Use Services\ListService::getTreeHtml() directly
     */
    public static function getTreeHtml(int $formId, ?int $activeId = null, array $options = []): array
    {
        return Services\ListService::getTreeHtml($formId, $activeId, $options);
    }

    /**
     * Get table HTML for list panel
     * @deprecated Use Services\ListService::getTableHtml() directly
     */
    public static function getTableHtml(int $formId, ?int $activeId = null, array $options = []): array
    {
        return Services\ListService::getTableHtml($formId, $activeId, $options);
    }

    /**
     * Get single row HTML for targeted refresh after popup save
     * Returns rowHtml that can be used to replace a single row in the table
     */
    public static function getRowHtml(int $formId, string $recordId, int $displayMode = 2): array
    {
        // TODO: Implement targeted row refresh for better performance
        // For now, return empty to trigger fallback to full list reload
        return ['success' => false, 'error' => 'Not implemented - use full list reload'];
    }

    /**
     * Get single row HTML for JSON form targeted refresh after popup save
     */
    public static function getJsonFormRowHtml(string $formName, string $recordId, int $displayMode = 2, array $columns = []): array
    {
        return Services\ListService::getJsonFormRowHtml($formName, $recordId, $displayMode, $columns);
    }

    // ========================================================================
    // JSON Form Support Methods - delegated to ListService
    // ========================================================================

    /**
     * Get tree HTML for a JSON-defined form
     * @deprecated Use Services\ListService::getJsonFormTreeHtml() directly
     */
    public static function getJsonFormTreeHtml(string $formName, string|int|null $activeId = null, array $options = []): array
    {
        return Services\ListService::getJsonFormTreeHtml($formName, $activeId, $options);
    }

    /**
     * Get table HTML for a JSON-defined form
     * @deprecated Use Services\ListService::getJsonFormTableHtml() directly
     */
    public static function getJsonFormTableHtml(string $formName, string|int|null $activeId = null, array $options = []): array
    {
        return Services\ListService::getJsonFormTableHtml($formName, $activeId, $options);
    }

    /**
     * Get record data for a JSON-defined form
     *
     * @param string $formName Form name
     * @param string $recordId Record ID
     * @return array Response with record data
     */
    public static function getJsonFormRecordData(string $formName, string $recordId): array
    {
        try {
            $formDef = JsonFormLoader::load($formName);
            if ($formDef === null) {
                return self::error("Formulier '$formName' niet gevonden");
            }

            // Check access rights via sourceFormId or admin status
            $jsonData = $formDef['_json'] ?? [];
            $sourceFormId = $jsonData['sourceFormId'] ?? 0;
            $userId = (int)Cookie::get(SecurityHelper::COOKIE_USERID, '0');

            if ($sourceFormId > 0) {
                $accessLevel = SecurityHelper::checkFormRights($userId, $sourceFormId);
            } else {
                // No sourceFormId - require admin for security
                $accessLevel = SecurityHelper::isAdmin() ? SecurityHelper::ACCESS_FULL_BEHEER : SecurityHelper::ACCESS_NONE;
            }

            if ($accessLevel == SecurityHelper::ACCESS_NONE) {
                return self::error('Geen toegang tot dit formulier');
            }

            $database = $jsonData['database'] ?? '';

            // Determine edit permissions based on access level AND form settings
            $canWrite = SecurityHelper::canWriteAtLevel($accessLevel, !empty($jsonData['securityByUser']));
            $canEdit = $canWrite && ($jsonData['allowEdit'] ?? true);
            $canAdd = $canWrite && ($jsonData['allowAdd'] ?? true);
            $canDelete = $canWrite && ($jsonData['allowDelete'] ?? true);

            // Check if this is a JSON config form
            if ($database === 'json') {
                $result = ConfigFormService::getRecord($formName, $recordId);
                if (!$result['success']) {
                    return $result;
                }

                // Load combo options for fields with optionsSource.type = "jsonConfig"
                $comboOptions = [];
                $fields = $jsonData['fields'] ?? [];
                foreach ($fields as $fieldDef) {
                    $optionsSource = $fieldDef['optionsSource'] ?? null;
                    if ($optionsSource && ($optionsSource['type'] ?? '') === 'jsonConfig') {
                        $fieldName = $fieldDef['name'] ?? '';
                        $configFile = $optionsSource['configFile'] ?? '';
                        $configArrayKey = $optionsSource['configArrayKey'] ?? '';
                        $valueField = $optionsSource['valueField'] ?? 'id';
                        $labelField = $optionsSource['labelField'] ?? 'name';

                        if ($fieldName && $configFile && $configArrayKey) {
                            $options = ConfigFormService::getOptionsFromConfig($configFile, $configArrayKey, $valueField, $labelField);
                            if (!empty($options)) {
                                $comboOptions[$fieldName] = $options;
                            }
                        }
                    }
                }

                $response = [
                    'success' => true,
                    'fields' => $result['data'],
                    'meta' => [
                        'id' => $recordId,
                        'accessLevel' => $accessLevel,
                        'canEdit' => $canEdit,
                        'canAdd' => $canAdd,
                        'canDelete' => $canDelete,
                    ],
                ];

                if (!empty($comboOptions)) {
                    $response['comboOptions'] = $comboOptions;
                }

                return $response;
            }

            $tableName = $jsonData['table'] ?? '';
            $idField = $jsonData['idField'] ?? 'ID';

            $conn = self::getJsonFormConnection($database);
            if ($conn === null) {
                return self::error('Database connectie mislukt');
            }

            // Determine if SQLite for proper identifier quoting
            $isSqlite = Database::isSQLite($conn);

            // Handle both numeric and GUID IDs - use string quoting for non-numeric IDs
            $idValue = is_numeric($recordId) ? SQL::postNumber($recordId) : SQL::postString($recordId);
            $sql = "SELECT * FROM " . self::quoteIdentifier($tableName, $isSqlite) . " WHERE " . self::quoteIdentifier($idField, $isSqlite) . " = " . $idValue;

            // Use PDO directly (not Database::openRS which uses native ODBC)
            // Native ODBC's odbc_result() cannot read MEMO/LONGCHAR fields reliably
            $stmt = $conn->query($sql);
            $rowData = $stmt ? $stmt->fetch(\PDO::FETCH_ASSOC) : false;

            if (!$rowData) {
                return self::error('Record niet gevonden');
            }

            // "Alleen eigen records": another user's record is view-only
            if ($accessLevel === SecurityHelper::ACCESS_CHANGE_OWN_DATA && !empty($jsonData['securityByUser'])) {
                $ownerId = null;
                foreach ($rowData as $key => $value) {
                    if (strtolower((string)$key) === 'userid') {
                        $ownerId = (int)$value;
                        break;
                    }
                }
                if ($ownerId !== (int)((SecurityHelper::getCurrentUserData()['ID'] ?? $userId) ?: 0)) {
                    $canEdit = false;
                    $canDelete = false;
                }
            }

            // Build record data - use field names from JSON definition for case-sensitivity
            $record = [];
            $fieldDefs = $jsonData['fields'] ?? [];

            // Create lookup maps from JSON field definitions
            $fieldNameMap = [];
            $fieldDataTypes = [];
            foreach ($fieldDefs as $fieldDef) {
                $fieldName = $fieldDef['name'] ?? '';
                if ($fieldName) {
                    $fieldNameMap[strtolower($fieldName)] = $fieldName;
                    $fieldDataTypes[strtolower($fieldName)] = $fieldDef['dataType'] ?? '';
                }
            }

            foreach ($rowData as $key => $value) {
                if (!is_numeric($key)) {
                    // Use JSON field name case if available, otherwise use DB column name
                    $normalizedKey = $fieldNameMap[strtolower($key)] ?? $key;
                    $dataType = $fieldDataTypes[strtolower($key)] ?? '';

                    // Format date/datetime values to European format (dd-mm-yyyy)
                    if ($value !== null && $value !== '' && in_array($dataType, ['date', 'datetime'])) {
                        $record[$normalizedKey] = \App\Library\Date::fixValue($value);
                    } else {
                        // Sanitize string values to valid UTF-8 (database may contain Windows-1252)
                        $record[$normalizedKey] = is_string($value) ? Str::toUtf8($value) : $value;
                    }
                }
            }

            // Debug: Log large text fields after building record
            foreach ($record as $key => $value) {
                if (is_string($value) && strlen($value) > 1000) {
                    Logger::debug("FormDataProvider: Record", ['field' => $key, 'bytes' => strlen($value), 'last30hex' => bin2hex(substr($value, -30))]);
                }
            }

            // Resolve FK display labels for combobox fields
            // This allows the UI to immediately display the correct text for FK fields
            // even when the combo options haven't been fully loaded yet (lazy loading)
            $fkLabels = self::resolveJsonFormFkLabels($jsonData, $record, $conn);
            $record = array_merge($record, $fkLabels);

            // Strip sensitive fields (passwords, ignorefields) - never send to client
            // For password fields, we only want the value visible when adding new records
            foreach ($fieldDefs as $fieldDef) {
                $fieldName = $fieldDef['name'] ?? '';
                $fieldType = $fieldDef['type'] ?? '';

                // Password fields: clear value (placeholder will show hint)
                if ($fieldType === 'password' && isset($record[$fieldName])) {
                    $record[$fieldName] = '';
                }

                // Ignorefield: remove from record entirely (not shown, not sent)
                if ($fieldType === 'ignorefield' && isset($record[$fieldName])) {
                    unset($record[$fieldName]);
                }
            }

            return [
                'success' => true,
                'fields' => $record,
                'meta' => array_merge([
                    'id' => $recordId,
                    'accessLevel' => $accessLevel,
                    'canEdit' => $canEdit,
                    'canAdd' => $canAdd,
                    'canDelete' => $canDelete,
                ], self::lastModifiedMeta($jsonData, is_array($rowData ?? null) ? $rowData : [])),
            ];

        } catch (\Exception $e) {
            return self::error($e->getMessage());
        }
    }

    /**
     * Save record for a JSON-defined form
     *
     * @param string $formName Form name
     * @param string|null $recordId Record ID (null for new record)
     * @param array $data Field values
     * @param array $changelog Optional changelog data from form (detailed field changes)
     * @return array Response with success/error
     */
    public static function saveJsonFormRecord(string $formName, ?string $recordId, array $data, array $changelog = []): array
    {
        try {
            if (!SecurityHelper::isLoggedIn()) {
                return self::error('Geen toegang tot dit formulier');
            }

            $formDef = JsonFormLoader::load($formName);
            if ($formDef === null) {
                return self::error("Formulier '$formName' niet gevonden");
            }

            $jsonData = $formDef['_json'] ?? [];
            $database = $jsonData['database'] ?? '';

            // Check if this is a JSON config form
            if ($database === 'json') {
                if (!SecurityHelper::isAdmin()) {
                    return self::error('Geen toegang tot dit formulier');
                }
                $data['id'] = $recordId;
                $isNew = $recordId === null || $recordId === '';
                $result = ConfigFormService::saveRecord($formName, $data);
                if ($result['success']) {
                    $result['isNew'] = $isNew;
                    // Log to CMA Monitoring with changelog
                    $formTitle = $jsonData['title'] ?? $formName;
                    $action = $isNew ? 'add' : 'edit';
                    self::logMonitoring($formName, $formTitle, $result['id'] ?? $recordId, $action, $changelog);
                }
                return $result;
            }

            $tableName = $jsonData['table'] ?? '';
            $idField = $jsonData['idField'] ?? 'ID';
            $postHandler = $jsonData['postHandler'] ?? '';

            $conn = self::getJsonFormConnection($database);
            if ($conn === null) {
                return self::error('Database connectie mislukt');
            }

            // Determine if SQLite for proper identifier quoting
            $isSqlite = Database::isSQLite($conn);

            // Write access follows the form's group rights ("Volledig", or "Alleen eigen
            // records" for the owner), not the user level — as in the classic CMA.
            $accessError = self::writeAccessError($formName, $jsonData, $conn, $tableName, $idField, $recordId, $isSqlite);
            if ($accessError !== null) {
                return self::error($accessError);
            }

            // Build list of valid database fields from form definition
            // Keys are lowercase for case-insensitive lookup, values are original field names
            $validFields = [];
            $fieldTypeMap = [];
            $numericFields = [];
            // ADO numeric type codes (smallint/int/single/double/currency/decimal/numeric/bigint…)
            $numericAdoTypes = ['2','3','4','5','6','14','16','17','18','19','20','21','131','139'];
            $checklistFields = []; // many-to-many (checklist/sortlist) fields -> own junction table
            foreach ($jsonData['fields'] ?? [] as $fieldDef) {
                $fieldName = $fieldDef['name'] ?? '';
                $fieldType = $fieldDef['type'] ?? '';
                // Checklist/sortlist fields are many-to-many relations stored in their OWN
                // junction table (sourceTable/idField/displayField), not columns on the main
                // table. Collect them for separate persistence and keep them OUT of
                // validFields, so the column INSERT/UPDATE + missing-column check skip them
                // (2+ selected values arrive as an array and would trip "Array to string").
                if ($fieldName && ($fieldType === 'checklist' || $fieldType === 'sortlist')) {
                    $checklistFields[$fieldName] = $fieldDef;
                    continue;
                }
                // Skip custom renderers and non-database fields
                if ($fieldName && $fieldType !== 'custom' && $fieldType !== 'label' && $fieldType !== 'separator') {
                    $validFields[strtolower($fieldName)] = $fieldName;
                    $fieldTypeMap[strtolower($fieldName)] = strtolower($fieldType);
                    // Track numeric fields (by reported precision or numeric ADO
                    // data-type) so the INSERT/UPDATE branches below write them as
                    // INLINE bare period-decimal literals — never bound or quoted.
                    // A bound/quoted decimal is sent to Access ODBC as TEXT and the
                    // connection locale (LCID 1043) re-reads '.' as a thousands
                    // separator, mangling 10.5 -> 1050. A bare numeric literal is
                    // parsed by the Jet/ACE engine with '.' as the decimal point
                    // regardless of locale. (Verified by FormSavePipelineTest.)
                    if (($fieldDef['numericPrecision'] ?? '') !== ''
                        || in_array((string)($fieldDef['dataType'] ?? ''), $numericAdoTypes, true)) {
                        $numericFields[strtolower($fieldName)] = true;
                    }
                }
            }

            // Read-only fields are shown, not edited: never write what the client posts for
            // them (the input is `readonly`, not `disabled`, so its display-formatted value
            // does arrive). On INSERT a definition default still applies.
            $readOnlyFields = [];
            $readOnlyDefaults = [];
            foreach ($jsonData['fields'] ?? [] as $fieldDef) {
                $lcName = strtolower((string)($fieldDef['name'] ?? ''));
                if ($lcName !== '' && !empty($fieldDef['readOnly'])) {
                    $readOnlyFields[$lcName] = true;
                    if (array_key_exists('defaultValue', $fieldDef) && (string)$fieldDef['defaultValue'] !== '') {
                        $readOnlyDefaults[$lcName] = $fieldDef['defaultValue'];
                    }
                }
            }

            // Server-side validation and normalisation (required, numeric, date/time,
            // e-mail, URL, directory, length) — the client validates too, but the API is
            // callable without it. Same rules as the classic detailsRep_post.asp.
            $isNewForValidation = $recordId === null || $recordId === '';
            $validation = self::validateJsonFormData(
                $jsonData['fields'] ?? [], $data, $isNewForValidation, $conn, $tableName, $idField, $recordId, $isSqlite
            );
            if ($validation['errors'] !== []) {
                return [
                    'success' => false,
                    'error' => 'Controleer de invoer: ' . implode('; ', array_values($validation['errors'])),
                    'validation' => $validation['errors'],
                ];
            }
            $data = $validation['data'];

            // Derived columns the classic detailsRep_post.asp maintained on save:
            // image width/height fields, _tn thumbnails and HTMLStrip plain-text copies
            foreach (self::derivedColumns($jsonData['fields'] ?? [], $data) as $derivedName => $derivedValue) {
                $data[$derivedName] = $derivedValue;
                $validFields[strtolower($derivedName)] = $derivedName;
            }

            // Debug: Log valid fields
            Logger::debug("SAVE: Valid fields", ['fields' => array_keys($validFields)]);

            // Debug: Log received data keys and email value specifically
            Logger::debug("SAVE: Received data keys", ['keys' => array_keys($data)]);
            if (isset($data['userEmail'])) {
                Logger::debug("SAVE: userEmail value", ['value' => $data['userEmail']]);
            } else {
                Logger::debug("SAVE: userEmail NOT in received data");
            }

            $isNew = $recordId === null || $recordId === '';

            // Server-side changelog: fetch old record state BEFORE update so we
            // can diff old vs new and render a changelog if the client failed
            // to ship one. Pre-1.20.1 the Notificatie field in tblCMAMonitoring
            // was empty for any edit where the client-side JS that builds
            // _changelog had errored, leaving the operator with no audit
            // trail. The fetch is bounded by try/throwable so a read failure
            // never blocks the save itself.
            $oldFields = [];
            if (!$isNew) {
                $idValueQ = is_numeric($recordId) ? SQL::postNumber($recordId) : SQL::postString($recordId);
                $oldSql = "SELECT * FROM " . self::quoteIdentifier($tableName, $isSqlite)
                        . " WHERE " . self::quoteIdentifier($idField, $isSqlite) . " = " . $idValueQ;
                try {
                    $oldRs = Database::openRS($oldSql, $conn);
                    if ($oldRs && !$oldRs->EOF) {
                        $oldFields = (array)$oldRs->fields;
                    }
                } catch (\Throwable $e) {
                    // The save goes on, but without the old values there is no
                    // changelog in the audit trail.
                    \App\Library\ErrorHandler::report($e, 'Oude waarden voor de changelog niet gelezen');
                }
            }

            $params = []; // bound values for numeric fields (see numericBindValue)

            // storeLastModified: who saved this record and when (LastModifiedUser = CMA
            // user id, LastModifiedDate = today), as the classic CMA stamped it.
            $stampLastModified = !empty($jsonData['storeLastModified']);
            if ($stampLastModified) {
                $stampUser = (int)((SecurityHelper::getCurrentUserData()['ID'] ?? SecurityHelper::getCurrentUserId()) ?: 0);
                $stampDate = $isSqlite ? SQL::postString(date('Y-m-d')) : SQL::postDateOnly(date('Y-m-d'));
            }
            if ($isNew) {
                // INSERT
                $fields = [];
                $values = [];
                foreach ($data as $field => $value) {
                    // Skip virtual __label fields - they're for display only, not database columns
                    if (str_ends_with($field, '__label')) {
                        continue;
                    }
                    // Skip fields not defined in form (e.g., actie, required, user_groups[])
                    if (!isset($validFields[strtolower($field)])) {
                        continue;
                    }
                    $lc = strtolower($field);
                    if (isset($readOnlyFields[$lc])) {
                        if (!array_key_exists($lc, $readOnlyDefaults)) {
                            continue;
                        }
                        $value = $readOnlyDefaults[$lc];
                    }
                    // An empty value on a NEW record is left out of the INSERT instead of
                    // being written as NULL, so the column's own default applies — in
                    // Access that is where GenGUID(), Now(), Date()+60 and the like live
                    // (the classic CMA pre-filled those from the schema). An UPDATE still
                    // writes NULL: there the user emptied the field on purpose.
                    if ($value === null || $value === '') {
                        continue;
                    }
                    $fields[] = self::quoteIdentifier($field, $isSqlite);
                    $norm = ($value === null || $value === '') ? null : SQL::normalizeDecimal((string)$value);
                    if (($fieldTypeMap[$lc] ?? '') === 'date') {
                        $values[] = self::formatDateValueForSql((string)$value, $isSqlite);
                    } elseif ($norm !== null && is_numeric($norm) && (isset($numericFields[$lc]) || strpos($norm, '.') !== false)) {
                        // Write the number as an UNQUOTED period-decimal SQL literal.
                        // Binding or quoting sends the value to Access ODBC as TEXT,
                        // and the connection locale (LCID 1043) re-parses '.' as a
                        // thousands separator (10.5 -> 105). A bare numeric literal is
                        // parsed by the Jet/ACE query engine with '.' as the decimal
                        // point regardless of locale — the Classic-ASP-era behaviour.
                        // $norm is validated is_numeric, so inlining is injection-safe.
                        $values[] = $norm;
                    } else {
                        $values[] = self::formatValueForSql($value);
                    }
                }
                if ($stampLastModified) {
                    $fields[] = self::quoteIdentifier('LastModifiedUser', $isSqlite);
                    $values[] = (string)$stampUser;
                    $fields[] = self::quoteIdentifier('LastModifiedDate', $isSqlite);
                    $values[] = $stampDate;
                }
                if ($fields === []) {
                    // Every posted value was empty: insert the first valid column as NULL so
                    // the row still comes into being (Jet has no DEFAULT VALUES clause).
                    foreach ($data as $field => $value) {
                        if (isset($validFields[strtolower($field)]) && !str_ends_with($field, '__label')) {
                            $fields[] = self::quoteIdentifier($field, $isSqlite);
                            $values[] = 'NULL';
                            break;
                        }
                    }
                }
                $sql = "INSERT INTO " . self::quoteIdentifier($tableName, $isSqlite) . " (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $values) . ")";
            } else {
                // UPDATE
                $sets = [];
                foreach ($data as $field => $value) {
                    // Skip virtual __label fields - they're for display only, not database columns
                    if (str_ends_with($field, '__label')) {
                        continue;
                    }
                    // Skip fields not defined in form (e.g., actie, required, user_groups[])
                    if (!isset($validFields[strtolower($field)])) {
                        continue;
                    }
                    $lc = strtolower($field);
                    if (isset($readOnlyFields[$lc])) {
                        continue;
                    }
                    $norm = ($value === null || $value === '') ? null : SQL::normalizeDecimal((string)$value);
                    if (($fieldTypeMap[$lc] ?? '') === 'date') {
                        $sets[] = self::quoteIdentifier($field, $isSqlite) . " = " . self::formatDateValueForSql((string)$value, $isSqlite);
                    } elseif ($norm !== null && is_numeric($norm) && (isset($numericFields[$lc]) || strpos($norm, '.') !== false)) {
                        // Unquoted period-decimal literal (see the INSERT branch): a
                        // bound/quoted decimal is sent to Access ODBC as text and the
                        // locale (LCID 1043) mangles '.' to thousands (10.5 -> 105).
                        // $norm is validated is_numeric → injection-safe.
                        $sets[] = self::quoteIdentifier($field, $isSqlite) . " = " . $norm;
                    } else {
                        $sets[] = self::quoteIdentifier($field, $isSqlite) . " = " . self::formatValueForSql($value);
                    }
                }
                // Handle both numeric and GUID IDs - use string quoting for non-numeric IDs
                if ($stampLastModified && $sets !== []) {
                    $sets[] = self::quoteIdentifier('LastModifiedUser', $isSqlite) . ' = ' . (string)$stampUser;
                    $sets[] = self::quoteIdentifier('LastModifiedDate', $isSqlite) . ' = ' . $stampDate;
                }
                $idValue = is_numeric($recordId) ? SQL::postNumber($recordId) : SQL::postString($recordId);
                $sql = "UPDATE " . self::quoteIdentifier($tableName, $isSqlite) . " SET " . implode(', ', $sets) . " WHERE " . self::quoteIdentifier($idField, $isSqlite) . " = " . $idValue;
            }

            // Debug: Log the SQL being executed
            Logger::debug("SAVE: SQL", ['sql' => $sql]);

            // Check for empty SET clause (no valid fields to save)
            if (!$isNew && empty($sets)) {
                $errorMsg = "Geen geldige velden om op te slaan voor formulier '$formName'";
                Logger::error("SAVE: No valid fields to save", ['form' => $formName]);
                return self::error($errorMsg);
            }
            if ($isNew && empty($fields)) {
                $errorMsg = "Geen geldige velden om in te voegen voor formulier '$formName'";
                Logger::error("SAVE: No valid fields to insert", ['form' => $formName]);
                return self::error($errorMsg);
            }

            // Execute the query and CHECK FOR ERRORS
            // $params carries bound numeric values (numeric fields use '?'); all
            // other values are inlined into $sql by formatValueForSql().
            $stmt = Database::query($sql, $params, $conn);

            if ($stmt === null) {
                // Query failed - check which columns are missing
                $sqlFields = $isNew ? $fields : array_map(fn($s) => explode(' = ', $s)[0], $sets);
                $missingColumns = [];

                try {
                    $dbColumns = \Cma\SchemaHelper::getColumns($conn, $tableName);
                    $dbColumnNames = array_map(fn($c) => strtolower($c['name']), $dbColumns);
                    foreach ($sqlFields as $sqlField) {
                        $cleanField = trim($sqlField, '[]');
                        if (!in_array(strtolower($cleanField), $dbColumnNames)) {
                            $missingColumns[] = $cleanField;
                        }
                    }
                } catch (\Exception $e) {
                    // Schema check failed
                }

                // User-friendly error message
                if (!empty($missingColumns)) {
                    $errorMsg = "Kan niet opslaan: veld(en) '" . implode("', '", $missingColumns) . "' bestaan niet in de database. ";
                    $errorMsg .= "Verwijder deze velden uit het formulier of voeg ze toe aan de tabel '$tableName'.";
                } else {
                    // Surface the real DB error, cleaned of driver/SQLSTATE noise.
                    // (Database::getUserFriendlyError() never existed — calling it
                    // turned every non-missing-column save failure into a fatal
                    // "Call to undefined method" instead of an actionable message.)
                    $dbError = Database::cleanErrorMessage(Database::getLastError());
                    $errorMsg = "Kan niet opslaan: " . ($dbError !== '' ? $dbError : "onbekende databasefout (zie logs voor tabel '$tableName').");
                }

                // A failed save is the one moment the operator needs the value itself:
                // "syntax error in query expression ''" says nothing about which field
                // carried the offending text. Log the whole statement plus, per field,
                // its length and any control characters, and name the suspect field in
                // the message so the text can be recovered from the log.
                $suspects = self::controlCharDiagnostics($data);
                Logger::error("SAVE: Database error", [
                    'form' => $formName,
                    'table' => $tableName,
                    'missing_columns' => $missingColumns,
                    'sql' => $sql,
                    'field_lengths' => self::fieldLengths($data),
                    'suspect_fields' => $suspects,
                    'values' => self::loggableValues($data),
                ]);

                if (!empty($suspects)) {
                    $errorMsg .= ' Let op: ' . implode('; ', $suspects)
                        . '. De volledige tekst staat in het logboek.';
                }

                return self::error($errorMsg);
            }

            // For UPDATE, verify rows were affected
            if (!$isNew) {
                $rowCount = $stmt->rowCount();
                Logger::debug("SAVE: Rows affected", ['count' => $rowCount]);

                // Note: Some drivers don't report rowCount for UPDATE, so 0 might be valid
                // But if we expected to update and got 0, it could indicate a problem
                // We'll verify by reading back the record
            }

            // Get the new ID if inserted
            if ($isNew) {
                // Determine database type
                $dbType = Database::getDatabaseType($conn);
                Logger::debug("SAVE: Database type", ['type' => $dbType]);

                // Try PDO's lastInsertId first (works with most drivers, but not always Access)
                if ($conn instanceof \PDO && $dbType !== 'access') {
                    try {
                        $recordId = $conn->lastInsertId();
                        Logger::debug("SAVE: PDO lastInsertId", ['id' => $recordId ?: 'empty']);
                    } catch (\Exception $e) {
                        Logger::debug("SAVE: PDO lastInsertId failed", ['error' => $e->getMessage()]);
                        $recordId = '';
                    }
                }

                // Fallback to SQL query if PDO method fails
                if ($recordId === '' || $recordId === null || $recordId === false) {
                    if ($isSqlite) {
                        $idSql = "SELECT last_insert_rowid() AS NewID";
                    } else {
                        // @@IDENTITY works for both Access (JET/ACE ODBC) and SQL Server
                        $idSql = "SELECT @@IDENTITY AS NewID";
                    }
                    Logger::debug("SAVE: Getting last ID", ['sql' => $idSql]);
                    $recordId = Database::getFieldValue($conn, $idSql, 'NewID');
                    Logger::debug("SAVE: ID query returned", ['id' => $recordId ?: 'empty']);
                }

                if ($recordId === '' || $recordId === null || $recordId === false) {
                    $errorMsg = "Record ingevoegd maar kon geen ID ophalen";
                    Logger::error("SAVE: Could not get ID after insert");
                    return self::error($errorMsg);
                }
            }

            // Persist checklist (many-to-many) fields to their junction tables: clear the
            // record's existing rows, then insert the selected option IDs. sourceTable /
            // idField (record FK) / displayField (value FK) come from the form definition.
            // Values are cast to int (injection-safe); identifiers come from trusted JSON.
            foreach ($checklistFields as $clName => $clDef) {
                if (!array_key_exists($clName, $data)) { continue; }
                $junction = (string)($clDef['sourceTable'] ?? '');
                $recFk    = (string)($clDef['idField'] ?? '');
                $valFk    = (string)($clDef['displayField'] ?? '');
                if ($junction === '' || $recFk === '' || $valFk === '') { continue; }
                $raw = $data[$clName];
                $ids = is_array($raw) ? $raw : ($raw === '' || $raw === null ? [] : explode(',', (string)$raw));
                try {
                    $conn->exec("DELETE FROM " . self::quoteIdentifier($junction, $isSqlite)
                        . " WHERE " . self::quoteIdentifier($recFk, $isSqlite) . " = " . (int)$recordId);
                    foreach ($ids as $vid) {
                        $vid = trim((string)$vid);
                        if ($vid === '' || !is_numeric($vid)) { continue; }
                        $conn->exec("INSERT INTO " . self::quoteIdentifier($junction, $isSqlite)
                            . " (" . self::quoteIdentifier($recFk, $isSqlite) . ", " . self::quoteIdentifier($valFk, $isSqlite) . ")"
                            . " VALUES (" . (int)$recordId . ", " . (int)$vid . ")");
                    }
                } catch (\Throwable $e) {
                    Logger::error("SAVE: checklist persist failed for '$clName'", ['error' => $e->getMessage()]);
                }
            }

            // VERIFY the save by reading back the record using a simple COUNT query
            // Skip verification for Access databases - they have timing/locking issues with immediate read-back
            $dbType = Database::getDatabaseType($conn);
            $skipVerify = ($dbType === 'access');

            if (!$skipVerify) {
                $verifySql = "SELECT COUNT(*) AS cnt FROM " . self::quoteIdentifier($tableName, $isSqlite) .
                             " WHERE " . self::quoteIdentifier($idField, $isSqlite) . " = " .
                             (is_numeric($recordId) ? SQL::postNumber($recordId) : SQL::postString($recordId));

                $verifyRs = Database::openRS($verifySql, $conn);
                if (!$verifyRs || $verifyRs->EOF || intval($verifyRs->fields['cnt'] ?? 0) === 0) {
                    $errorMsg = "Record verificatie mislukt - record niet gevonden na opslaan (ID: $recordId)";
                    Logger::error("SAVE: Verification failed", ['id' => $recordId, 'sql' => $verifySql, 'table' => $tableName, 'idField' => $idField]);
                    return self::error($errorMsg);
                }
                Logger::debug("SAVE: Verified record exists", ['id' => $recordId]);
            } else {
                Logger::debug("SAVE: Skipping verification for Access", ['id' => $recordId]);
            }

            // Handle custom renderer fields (user_groups, form_notifications, etc.)
            // These store data in separate tables, not the main record table
            $formDefObj = FormDefinition::fromArray($formDef);
            if ($formDefObj->isValid()) {
                RecordService::saveCustomRendererValues($formDefObj, $recordId, $data);
            }

            // Server-side changelog fallback for edits. When the
            // client-side JS that builds _changelog erred, the POST arrives
            // without it; pre-1.20.1 the Notificatie ended up empty. Now we
            // diff the pre-update $oldFields vs the saved $data and render a
            // changelog table — but only when the client hasn't already shipped
            // one (so we never overwrite the operator's richer client-side
            // diff with our server-side approximation).
            if (!$isNew && empty($changelog['_changelog']) && !empty($oldFields)) {
                $generated = self::buildEditChangelog($formDef, $oldFields, $data);
                if ($generated !== '') {
                    $changelog['_changelog'] = $generated;
                }
            }

            // Log to CMA Monitoring with changelog
            $formTitle = $jsonData['title'] ?? $formName;
            $action = $isNew ? 'add' : 'edit';
            self::logMonitoring($formName, $formTitle, $recordId, $action, $changelog);

            // Declarative post-save cache invalidation (modern successor of the
            // legacy cma_afterpost.asp trigger). See clearFormCachesOnSave().
            self::clearFormCachesOnSave($jsonData, $recordId);

            $lastModified = [];
            if ($stampLastModified) {
                $lastModified = [
                    'lastModifiedUser' => (string)(SecurityHelper::getCurrentUserData()['userFullName'] ?? '') ?: ('gebruiker ' . $stampUser),
                    'lastModifiedDate' => date('d-m-Y'),
                ];
            }

            return array_merge([
                'success' => true,
                'id' => $recordId,
                'isNew' => $isNew,
                'message' => $isNew ? 'Record aangemaakt' : 'Record opgeslagen',
                'warnings' => \App\Library\ErrorHandler::getReported(),
            ], $lastModified);

        } catch (\Exception $e) {
            return self::error($e->getMessage());
        }
    }

    /**
     * Declarative post-save cache invalidation — the modern successor of the
     * legacy cma_afterpost.asp trigger. A form definition may carry a
     * "clearCache" array of glob patterns (relative to the site cache/ directory)
     * that get deleted after every successful save of that form. The tokens
     * {ID} / {id} in a pattern are replaced with the saved record id, so a form
     * can target a single record's cache file as well as wildcard lists. Example
     * on a product form:
     *
     *     "clearCache": ["prod_detail_v8_{ID}.html", "stenen_*.html"]
     *
     * When a form sets "clearCache", the data-cache layer (App\Library\Cache) is
     * flushed too, so derived caches (lists, carousels, counts) can't go stale.
     *
     * Fully best-effort and sandboxed: it never throws (a hook failure must never
     * block a save that already succeeded) and never escapes the cache/ directory
     * (".." patterns are rejected and each match is realpath-confirmed inside it).
     *
     * @param array $jsonData Decoded form definition
     * @param mixed $recordId Saved record id (used for {ID}/{id} substitution)
     */
    private static function clearFormCachesOnSave(array $jsonData, $recordId): void
    {
        $patterns = $jsonData['clearCache'] ?? null;
        if (empty($patterns)) {
            return;
        }
        if (is_string($patterns)) {
            $patterns = [$patterns];
        }
        if (!is_array($patterns)) {
            return;
        }

        try {
            $cacheDir = @realpath(\App\Library\Server::mapPath('/cache'));
            if ($cacheDir !== false && $cacheDir !== null && is_dir($cacheDir)) {
                $id = (string)$recordId;
                foreach ($patterns as $pattern) {
                    if (!is_string($pattern) || $pattern === '' || strpos($pattern, '..') !== false) {
                        continue; // reject traversal / junk
                    }
                    $pattern = str_replace(['{ID}', '{id}'], $id, ltrim($pattern, '/\\'));
                    foreach (glob($cacheDir . DIRECTORY_SEPARATOR . $pattern) ?: [] as $file) {
                        // Defence in depth: only unlink real files inside cache/.
                        $real = @realpath($file);
                        if ($real !== false && strncmp($real, $cacheDir, strlen($cacheDir)) === 0 && is_file($real)) {
                            @unlink($real);
                        }
                    }
                }
                Logger::debug('SAVE: clearCache swept', ['patterns' => $patterns, 'id' => $id]);
            }
        } catch (\Throwable $e) {
            Logger::debug('SAVE: clearCache file sweep failed', ['error' => $e->getMessage()]);
        }

        // Flush the data-cache layer (lists/carousels/counts derived from this table).
        try {
            if (class_exists('\\App\\Library\\Cache') && method_exists('\\App\\Library\\Cache', 'clear')) {
                \App\Library\Cache::clear();
            }
        } catch (\Throwable $e) {
            Logger::debug('SAVE: clearCache data-cache flush failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Get combo box options for a JSON-defined form
     *
     * Handles both static options (defined in JSON) and dynamic options (from SQL/sourceTable).
     * When lookupId is provided, returns just that single option with its label.
     *
     * @param string $formName Form name
     * @param string $fieldName Field name
     * @param string $search Optional search filter
     * @param string $lookupId Optional ID to look up a single label (bypasses search requirement)
     * @return array Response with options. When lookupId is provided, also includes 'label' key.
     */
    public static function getJsonFormComboOptions(string $formName, string $fieldName, string $search = '', string $lookupId = '', array $filterContext = []): array
    {
        try {
            // Check form access rights - user needs at least read access to load combo options
            $userId = (int) SecurityHelper::getCurrentUserId();
            if ($userId <= 0) {
                return self::error('Niet ingelogd');
            }

            $accessLevel = SecurityHelper::checkFormRightsByName($userId, $formName);
            if ($accessLevel < SecurityHelper::ACCESS_READ) {
                return self::error('Geen toegang tot dit formulier');
            }

            $formDef = JsonFormLoader::load($formName);
            if ($formDef === null) {
                return self::error("Formulier '$formName' niet gevonden");
            }

            $jsonData = $formDef['_json'] ?? [];
            $database = $jsonData['database'] ?? '';

            // Find the field by name (case-insensitive)
            $fieldDef = null;
            $fieldNameLower = strtolower($fieldName);
            foreach ($jsonData['fields'] ?? [] as $field) {
                if (strtolower($field['name'] ?? '') === $fieldNameLower) {
                    $fieldDef = $field;
                    break;
                }
            }

            if ($fieldDef === null) {
                return self::error("Veld '$fieldName' niet gevonden");
            }

            // Check for static options first (dropdown type)
            $staticOptions = $fieldDef['options'] ?? [];
            if (!empty($staticOptions)) {
                $options = [];
                foreach ($staticOptions as $opt) {
                    $value = $opt['value'] ?? '';
                    // Support both 'text' (JSON form definitions) and 'label' (legacy)
                    $label = $opt['text'] ?? $opt['label'] ?? '';

                    // If looking up specific ID, return just that label
                    if ($lookupId !== '' && (string)$value === (string)$lookupId) {
                        return [
                            'success' => true,
                            'label' => $label,
                            'options' => [['id' => $value, 'text' => $label]],
                        ];
                    }

                    // Apply search filter
                    if ($search !== '' && stripos($label, $search) === false) {
                        continue;
                    }

                    $options[] = [
                        'id' => $value,
                        'text' => $label,
                    ];
                }
                // If lookupId was specified but not found in static options
                if ($lookupId !== '') {
                    return [
                        'success' => true,
                        'label' => null,
                        'options' => [],
                    ];
                }
                return [
                    'success' => true,
                    'options' => $options,
                ];
            }

            // Check for optionsSource.type = "jsonConfig"
            $optionsSource = $fieldDef['optionsSource'] ?? null;
            if ($optionsSource && ($optionsSource['type'] ?? '') === 'jsonConfig') {
                $configFile = $optionsSource['configFile'] ?? '';
                $configArrayKey = $optionsSource['configArrayKey'] ?? '';
                $valueField = $optionsSource['valueField'] ?? 'id';
                $labelField = $optionsSource['labelField'] ?? 'name';

                if ($configFile && $configArrayKey) {
                    $options = ConfigFormService::getOptionsFromConfig($configFile, $configArrayKey, $valueField, $labelField);

                    // Apply search filter
                    if ($search !== '') {
                        $options = array_filter($options, function($opt) use ($search) {
                            return stripos($opt['text'] ?? '', $search) !== false;
                        });
                        $options = array_values($options); // Re-index
                    }

                    return [
                        'success' => true,
                        'options' => $options,
                    ];
                }
            }

            // No static options - try dynamic from SQL or sourceTable
            // Support both 'sql' and 'dataSource' properties (dataSource is used in form definitions)
            $sql = $fieldDef['sql'] ?? $fieldDef['dataSource'] ?? '';
            $sourceTable = $fieldDef['sourceTable'] ?? '';
            $idField = $fieldDef['idField'] ?? 'ID';
            $displayField = $fieldDef['displayField'] ?? '';

            // Record-dependent SQL: [ID]/[ProdID]/[recordId] is the current record.
            // For a new record "= [ID]" becomes "IS NULL" and a bare [ID] becomes -1
            // (old edit.inc/details.asp), so the query still runs.
            if (!empty($sql) && preg_match('/\[(id|prodid|recordId)\]/i', $sql)) {
                $ctxRecordId = trim((string)($filterContext['_recordId'] ?? ''));
                unset($filterContext['_recordId']);
                if ($ctxRecordId !== '' && strtolower($ctxRecordId) !== 'new') {
                    $sql = preg_replace('/\[(id|prodid|recordId)\]/i', SQL::postNumber($ctxRecordId), $sql);
                } else {
                    $sql = preg_replace('/=\s*\[(id|prodid|recordId)\]/i', ' IS NULL', $sql);
                    $sql = preg_replace('/\[(id|prodid|recordId)\]/i', '-1', $sql);
                }
            } else {
                unset($filterContext['_recordId']);
            }

            // Get the connection first to determine database type
            $fieldDatabase = $fieldDef['database'] ?? $database;
            $conn = self::getJsonFormConnection($fieldDatabase);
            if ($conn === null) {
                return self::error('Database connectie mislukt');
            }

            // Determine if SQLite for proper identifier quoting
            $isSqlite = Database::isSQLite($conn);

            // Check if this is a large table that requires dynamic loading
            $isLargeTable = false;
            if (!empty($sourceTable)) {
                $isLargeTable = OptionsService::isLargeTable($sourceTable, $conn, $fieldDatabase);
            }

            // If looking up a specific ID, return just that record's label
            if ($lookupId !== '') {
                $baseSql = $fieldDef['sql'] ?? $fieldDef['dataSource'] ?? '';

                // If we have a custom SQL, use it but filter by ID
                if (!empty($baseSql)) {
                    // Find the table name containing the idField (usually the first or primary table)
                    // The SQL has format: SELECT ... FROM table1 INNER JOIN table2 ... WHERE ...
                    // We need to add a WHERE clause for the primary table's ID
                    // HAAKJES om de ?: — zonder die haakjes bindt de puntoperator sterker, en
                    // dan leest PHP dit als ("... = " . is_numeric($id)) ? getal : tekst. Die
                    // voorwaarde is een niet-lege string en dus altijd waar, waarna alleen de
                    // WAARDE overblijft: de WHERE werd "WHERE 218" in plaats van
                    // "WHERE tbl.ID = 218". Zo'n voorwaarde levert geen rij op, dus de combo
                    // vond geen omschrijving en toonde het kale ID (gemeld op rooster 530: "218"
                    // in plaats van de bloknaam).
                    $lookupSql = SQL::addWhere($baseSql, self::quoteIdentifier($sourceTable . '.' . $idField, $isSqlite) . " = " . (is_numeric($lookupId) ? SQL::postNumber($lookupId) : SQL::postString($lookupId)));
                    $lookupSql = SQL::addTop($lookupSql, 1);
                } elseif (!empty($sourceTable) && !empty($displayField)) {
                    // Simple case: sourceTable with displayField column
                    $lookupSql = "SELECT " . self::quoteIdentifier($idField, $isSqlite) . ", " . self::quoteIdentifier($displayField, $isSqlite) .
                                 " FROM " . self::quoteIdentifier($sourceTable, $isSqlite) .
                                 " WHERE " . self::quoteIdentifier($idField, $isSqlite) . " = " . (is_numeric($lookupId) ? SQL::postNumber($lookupId) : SQL::postString($lookupId));
                } else {
                    // No lookup SQL available
                    return [
                        'success' => true,
                        'label' => null,
                        'options' => [],
                    ];
                }

                $rs = Database::openRS($lookupSql, $conn);
                if ($rs !== null && !$rs->EOF) {
                    [$optId, $labelText] = self::comboIdAndText($rs->fetchAssoc(), $idField, $displayField);
                    if ($optId !== null) {
                        return [
                            'success' => true,
                            'label' => $labelText,
                            'options' => [['id' => $optId, 'text' => $labelText]],
                        ];
                    }
                }
                return [
                    'success' => true,
                    'label' => null,
                    'options' => [],
                ];
            }

            // For large tables: require minimum search length before returning results
            if ($isLargeTable && strlen($search) < 3) {
                return [
                    'success' => true,
                    'options' => [],
                    'requires_search' => true,
                    'min_search_length' => 3,
                    'table_count' => OptionsService::getTableRecordCount($sourceTable, $conn, $fieldDatabase),
                ];
            }

            if (empty($sql) && !empty($sourceTable) && !empty($displayField)) {
                $sql = "SELECT " . self::quoteIdentifier($idField, $isSqlite) . ", " . self::quoteIdentifier($displayField, $isSqlite) .
                       " FROM " . self::quoteIdentifier($sourceTable, $isSqlite) .
                       " ORDER BY " . self::quoteIdentifier($displayField, $isSqlite);
            }

            if (empty($sql)) {
                return self::error("Geen opties geconfigureerd voor veld '$fieldName'");
            }

            // Apply filterByField: filter combo options by another field's current value
            // e.g., filterByField="fkOpleiding" adds WHERE fkOpleiding = <value> to the SQL
            $filterByField = $fieldDef['filterByField'] ?? '';
            if ($filterByField !== '' && !empty($filterContext[$filterByField])) {
                $filterValue = (int) $filterContext[$filterByField];
                $whereCol = self::quoteIdentifier($filterByField, $isSqlite);
                $sql = SQL::addWhere($sql, $whereCol . ' = ' . SQL::postNumber($filterValue));
            }

            // Apply search filter
            if ($search !== '' && !empty($displayField)) {
                // Check if displayField is an alias in the SQL (e.g., "... AS Descr")
                // If so, we can't use it in WHERE - MS Access treats unknown fields as parameters
                $isAlias = preg_match('/\bAS\s+' . preg_quote($displayField, '/') . '\b/i', $sql);

                if ($isAlias) {
                    // Extract field references from the SELECT expression before AS
                    // Look for pattern like "SELECT ..., expression AS displayField"
                    $searchFields = [];
                    if (preg_match('/SELECT\s+.+?\s+AS\s+' . preg_quote($displayField, '/') . '\b/i', $sql, $matches)) {
                        $selectPart = $matches[0];

                        // Extract qualified field references: [table].field or [table].[field]
                        // These need to be used as-is (with brackets) for MS Access
                        if (preg_match_all('/\[[^\]]+\]\.(?:\[[^\]]+\]|[a-zA-Z_][a-zA-Z0-9_]*)/', $selectPart, $qualifiedMatches)) {
                            foreach ($qualifiedMatches[0] as $qf) {
                                $searchFields[] = $qf; // Keep the full reference like [tbltoetsen].naam
                            }
                        }

                        // Extract standalone [field] references (not followed by a dot)
                        // These are unqualified field names
                        if (preg_match_all('/\[([^\]]+)\](?!\.)/', $selectPart, $standaloneMatches)) {
                            foreach ($standaloneMatches[1] as $field) {
                                $searchFields[] = self::quoteIdentifier($field, $isSqlite);
                            }
                        }
                    }

                    if (!empty($searchFields)) {
                        // Build OR conditions for each underlying field
                        $searchConditions = [];
                        foreach ($searchFields as $fieldRef) {
                            // fieldRef is already properly formatted (either [table].field or [field])
                            $searchConditions[] = $fieldRef . " LIKE " . SQL::postString('%' . $search . '%');
                        }
                        $sql = SQL::addWhere($sql, '(' . implode(' OR ', $searchConditions) . ')');
                    }
                    // If we couldn't extract fields, skip the search filter to avoid parameter errors
                } else {
                    $sql = SQL::addWhere($sql, self::quoteIdentifier($displayField, $isSqlite) . " LIKE " . SQL::postString('%' . $search . '%'));
                }
            }

            // Limit results only for large tables (small tables can load all)
            if ($isLargeTable) {
                $sql = SQL::addTop($sql, 100);
            }

            $rs = Database::openRS($sql, $conn);
            if ($rs === null) {
                return self::error('Combo query mislukt: ' . Database::getLastError());
            }

            $options = [];
            while (!$rs->EOF) {
                [$optId, $optText, $optGroup] = self::comboIdAndText($rs->fetchAssoc(), $idField, $displayField);
                if ($optId !== null) {
                    $options[] = $optGroup !== null
                        ? ['id' => $optId, 'text' => $optText, 'group' => $optGroup]
                        : ['id' => $optId, 'text' => $optText];
                }
                $rs->MoveNext();
            }

            return [
                'success' => true,
                'options' => $options,
            ];

        } catch (\Exception $e) {
            return self::error($e->getMessage());
        }
    }

    /**
     * The id and text of one option row. The definition's idField and
     * displayField name the columns (case-insensitive, as ODBC may return a
     * different case than the query wrote); only when a name is absent from
     * the row do the first two columns stand in. A query that lists an extra
     * column first — a status for sorting, say — thus still yields the right
     * pair. Returns [null, ''] for a row without an id.
     *
     * @param  array<string,mixed> $row
     * @return array{0:?string,1:string}
     */
    public static function comboIdAndText(array $row, string $idField, string $displayField): array
    {
        $named = [];
        foreach ($row as $key => $value) {
            if (!is_numeric($key)) {
                $named[strtolower((string) $key)] = $value;
            }
        }
        $positional = array_values($named);
        $id = $named[strtolower($idField)] ?? ($positional[0] ?? null);
        $text = $named[strtolower($displayField)] ?? ($positional[1] ?? $positional[0] ?? null);
        if ($id === null || (string) $id === '') {
            return [null, '', null];
        }
        $text = Str::toUtf8((string) ($text ?? ''));
        // "Groep|Item" in the display column groups the options (old edit.inc made
        // OPTGROUPs of it); a <br> in the label becomes ", "
        $group = null;
        if (strpos($text, '|') !== false) {
            [$group, $text] = explode('|', $text, 2);
            $group = trim($group);
            $text = ltrim($text);
        }
        $text = preg_replace('#<br\s*/?>#i', ', ', $text);
        return [(string) $id, $text, $group !== '' ? $group : null];
    }

    /**
     * Get checklist options for a JSON-defined form
     *
     * @param string $formName Form name
     * @param string $fieldName Field name (the checklist field)
     * @param string|int $recordId Record ID (or -1 for new)
     * @return array Response with options
     */
    public static function getJsonFormChecklistOptions(string $formName, string $fieldName, string|int $recordId): array
    {
        $debug = [];
        $debug['formName'] = $formName;
        $debug['fieldName'] = $fieldName;
        $debug['recordId'] = $recordId;

        try {
            if (!SecurityHelper::isAdmin()) {
                return self::error('Geen toegang tot dit formulier');
            }

            $formDef = JsonFormLoader::load($formName);
            if ($formDef === null) {
                return self::error("Formulier '$formName' niet gevonden");
            }

            $jsonData = $formDef['_json'] ?? [];
            $database = $jsonData['database'] ?? '';
            $debug['formDatabase'] = $database;
            $debug['fieldCount'] = count($jsonData['fields'] ?? []);

            // Find the checklist field by name
            $fieldDef = null;
            $fieldNames = [];
            foreach ($jsonData['fields'] ?? [] as $field) {
                $fieldNames[] = $field['name'] ?? '(no name)';
                if (($field['name'] ?? '') === $fieldName) {
                    $fieldDef = $field;
                    break;
                }
            }
            $debug['availableFields'] = $fieldNames;

            if ($fieldDef === null) {
                $debug['error'] = 'Field not found';
                return ['success' => false, 'error' => "Veld '$fieldName' niet gevonden", 'debug' => $debug];
            }

            $sql = $fieldDef['sql'] ?? '';
            $debug['sqlBefore'] = $sql;
            if (empty($sql)) {
                return ['success' => false, 'error' => "Geen SQL voor checklist veld '$fieldName'", 'debug' => $debug];
            }

            // Replace ID placeholders
            $sql = str_ireplace(['[ID]', '[ProdID]'], (string)$recordId, $sql);
            $debug['sqlAfter'] = $sql;

            // Get the connection for this field's database (may differ from form database)
            $fieldDatabase = $fieldDef['database'] ?? $database;
            $debug['fieldDatabase'] = $fieldDatabase;
            $conn = self::getJsonFormConnection($fieldDatabase);
            if ($conn === null) {
                return ['success' => false, 'error' => 'Database connectie mislukt', 'debug' => $debug];
            }

            $rs = Database::openRS($sql, $conn);
            if ($rs === null) {
                $debug['dbError'] = Database::getLastError();
                return ['success' => false, 'error' => 'Checklist query mislukt: ' . Database::getLastError(), 'debug' => $debug];
            }

            $options = [];
            $selected = [];

            while (!$rs->EOF) {
                $row = $rs->fetchAssoc();
                $id = $row['ID'] ?? '';
                $text = $row['DisplayName'] ?? '';
                $isSelected = (bool)($row['selected'] ?? $row['Selected'] ?? false);

                $options[] = [
                    'id' => $id,
                    'text' => $text,
                    'selected' => $isSelected,
                ];

                if ($isSelected) {
                    $selected[] = $id;
                }

                $rs->MoveNext();
            }

            $debug['optionCount'] = count($options);
            return [
                'success' => true,
                'options' => $options,
                'selected' => $selected,
                'debug' => $debug,
            ];

        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage(), 'debug' => $debug];
        }
    }

    /**
     * Delete record for a JSON-defined form
     *
     * @param string $formName Form name
     * @param string $recordId Record ID
     * @return array Response with success/error
     */
    public static function deleteJsonFormRecord(string $formName, string $recordId): array
    {
        try {
            if (!SecurityHelper::isLoggedIn()) {
                return self::error('Geen toegang tot dit formulier');
            }

            $formDef = JsonFormLoader::load($formName);
            if ($formDef === null) {
                return self::error("Formulier '$formName' niet gevonden");
            }

            $jsonData = $formDef['_json'] ?? [];
            $tableName = $jsonData['table'] ?? '';
            $idField = $jsonData['idField'] ?? 'ID';
            $database = $jsonData['database'] ?? '';
            if ($database === 'json' && !SecurityHelper::isAdmin()) {
                return self::error('Geen toegang tot dit formulier');
            }
            if ($database !== 'json') {
                $accessConn = self::getJsonFormConnection($database);
                $accessError = self::writeAccessError($formName, $jsonData, $accessConn, $tableName, $idField, $recordId,
                    $accessConn !== null && Database::isSQLite($accessConn));
                if ($accessError !== null) {
                    return self::error($accessError);
                }
            }
            $protectedRecords = $jsonData['protectedRecords'] ?? [];
            $formTitle = $jsonData['title'] ?? $formName;

            // Check if record is protected
            if (in_array((int)$recordId, $protectedRecords) || in_array($recordId, $protectedRecords)) {
                return self::error('Dit record kan niet worden verwijderd');
            }

            // Get complete record data BEFORE deleting (for audit log)
            $recordDescription = '';
            $deleteChangelog = [];
            try {
                global $connrep;
                $recordDescription = CmaRepository::getRecordDescription($formName, $recordId, $connrep);

                // Fetch complete record data for audit trail
                $recordData = self::getJsonFormRecordData($formName, $recordId);
                if ($recordData['success'] && isset($recordData['fields'])) {
                    // Build HTML table of all field values for the changelog
                    $deleteChangelog['_changelog'] = self::buildDeleteChangelog($formDef, $recordData['fields']);
                }
            } catch (\Exception $e) {
                // The delete goes on; the audit trail misses the field values.
                \App\Library\ErrorHandler::report($e, 'Recordgegevens voor de audit-trail van het verwijderen niet gelezen');
            }

            // Check if this is a JSON config form
            if ($database === 'json') {
                $result = ConfigFormService::deleteRecord($formName, $recordId);
                if ($result['success']) {
                    // Log to CMA Monitoring with complete record data
                    self::logMonitoring($formName, $formTitle, $recordId, 'delete', $deleteChangelog, $recordDescription);
                }
                return $result;
            }

            $conn = self::getJsonFormConnection($database);
            if ($conn === null) {
                return self::error('Database connectie mislukt');
            }

            // Determine if SQLite for proper identifier quoting
            $isSqlite = Database::isSQLite($conn);

            // Handle both numeric and GUID IDs - use string quoting for non-numeric IDs
            $idValue = is_numeric($recordId) ? SQL::postNumber($recordId) : SQL::postString($recordId);
            $sql = "DELETE FROM " . self::quoteIdentifier($tableName, $isSqlite) . " WHERE " . self::quoteIdentifier($idField, $isSqlite) . " = " . $idValue;
            $stmt = Database::query($sql, [], $conn);

            // Check if the query actually executed successfully
            if ($stmt === null) {
                $lastError = Database::getLastError();
                // Strip the ODBC/driver noise (SQLSTATE, error numbers, driver +
                // source references) and surface the friendly "gerelateerde
                // gegevens in tabel X" message via the shared Error formatter.
                return self::error($lastError ? \App\Library\Error::format($lastError) : 'Verwijderen mislukt');
            }

            // Verify the row was actually affected (rowCount > 0)
            $rowCount = $stmt->rowCount();
            if ($rowCount === 0) {
                return self::error('Record niet gevonden of niet verwijderd (ID: ' . $recordId . ')');
            }

            // Log to CMA Monitoring with complete record data
            self::logMonitoring($formName, $formTitle, $recordId, 'delete', $deleteChangelog, $recordDescription);

            return [
                'success' => true,
                'warnings' => \App\Library\ErrorHandler::getReported(),
            ];

        } catch (\Exception $e) {
            // The related-records / FK violation arrives here as a raw PDOException
            // ("SQLSTATE[HY000]... [ODBC Microsoft Access Driver] The record cannot
            // be deleted..."). Route it through the shared formatter so the user
            // sees only "gerelateerde gegevens in tabel X", not the driver noise.
            return self::error(\App\Library\Error::format($e->getMessage()));
        }
    }

    /**
     * Get database connection for JSON form
     *
     * @param string $database Database identifier from JSON (e.g., 'users', 'rep', 'data')
     * @return mixed Database connection or null
     */
    private static function getJsonFormConnection(string $database)
    {
        // Handle named databases
        switch (strtolower($database)) {
            case 'users':
                return Database::getConnection('users');
            case 'rep':
            case 'repository':
                return Database::getRepConnection();
            case 'json':
                // JSON-only forms don't need a real database connection
                return null;
            case 'data':
            case '':
                // Default data connection
                return Database::getConnection('data');
        }

        // Handle numeric database IDs
        if (is_numeric($database)) {
            // Get database config by ID from databases.json
            $dbConfig = ConfigLoader::getDatabase((int)$database);
            if ($dbConfig !== null && !empty($dbConfig['name'])) {
                // Use the named connection (e.g., 'data', 'users', 'rep')
                return Database::getConnection($dbConfig['name']);
            }
            // Fallback to data connection
            return Database::getConnection('data');
        }

        // Unknown - try as connection name
        return Database::getConnection($database);
    }

    /**
     * Format a value for SQL insertion
     */
    /**
     * Normalise a numeric field value for BINDING (int/float), or null if it is
     * not a usable number (empty/non-numeric → caller falls back to inline/NULL).
     * Binding avoids the Jet/ACE locale coercion that mangles inlined decimals
     * (e.g. '9.5' -> 95 under Locale Identifier=1043). Mirrors
     * RecordService::numericParam().
     *
     * @param mixed $value
     * @return int|float|null
     */
    private static function numericBindValue($value)
    {
        if ($value === null || $value === '') {
            return null;
        }
        $n = SQL::normalizeDecimal($value);
        if (!is_numeric($n)) {
            return null;
        }
        return strpos($n, '.') === false ? (int)$n : (float)$n;
    }

    /**
     * Per-field character length of everything the client posted.
     */
    private static function fieldLengths(array $data): array
    {
        $out = [];
        foreach ($data as $field => $value) {
            if (is_scalar($value) || $value === null) {
                $out[$field] = strlen((string)$value);
            }
        }
        return $out;
    }

    /**
     * Name the fields that carry a control character, and where it sits.
     *
     * Text pasted from Word or a PDF can hold a NUL or another C0 character. A NUL
     * ends the C string the ODBC driver hands to the database, so the statement is
     * cut off mid-value and the database reports a syntax error that points at
     * innocent-looking text — or at nothing at all when the NUL sits up front.
     */
    private static function controlCharDiagnostics(array $data): array
    {
        $out = [];
        foreach ($data as $field => $value) {
            if (!is_string($value) || $value === '') {
                continue;
            }
            if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $value, $m, PREG_OFFSET_CAPTURE)) {
                $out[] = sprintf(
                    "veld '%s' bevat stuurteken 0x%02X op positie %d (lengte %d)",
                    $field,
                    ord($m[0][0]),
                    $m[0][1],
                    strlen($value)
                );
            }
        }
        return $out;
    }

    /**
     * The posted values, capped per field so one memo can't flood the log.
     */
    private static function loggableValues(array $data, int $maxPerField = 4000): array
    {
        $out = [];
        foreach ($data as $field => $value) {
            if (!is_scalar($value) && $value !== null) {
                continue;
            }
            $text = (string)$value;
            $out[$field] = strlen($text) > $maxPerField
                ? substr($text, 0, $maxPerField) . '… [' . strlen($text) . ' tekens]'
                : $text;
        }
        return $out;
    }

    private static function formatValueForSql($value): string
    {
        if ($value === null || $value === '') {
            return 'NULL';
        }
        // Handle arrays - convert to JSON string or comma-separated list
        if (Arr::isArray($value)) {
            // If it's an array of simple values, join with comma
            // Otherwise, encode as JSON
            $isSimple = true;
            foreach ($value as $v) {
                if (Arr::isArray($v) || is_object($v)) {
                    $isSimple = false;
                    break;
                }
            }
            if ($isSimple && count($value) > 0) {
                $value = implode(',', $value);
            } else {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE);
            }
        }
        if (is_bool($value)) {
            return $value ? 'True' : 'False';
        }
        // Handle boolean strings from form submissions (True/False from lib-switch)
        if (is_string($value)) {
            $lower = strtolower($value);
            if ($lower === 'true' || $lower === 'false') {
                return $lower === 'true' ? 'True' : 'False';
            }
        }
        if (is_numeric($value) && strpos((string)$value, '.') === false) {
            return (string)(int)$value;
        }
        return SQL::postString($value);
    }

    /**
     * Format a `type: date` field value for SQL.
     *
     * Without this, date fields went through formatValueForSql() and were quoted
     * as plain strings ('2026-06-15'); an Access Date/Time column rejects that,
     * so the value silently never saved. JSON-form date controls post and render
     * dd-mm-yyyy (see the 'date' case in form-controller.js), with an optional
     * HH:MM[:SS] for datetime and a bare HH:MM for the time-only variant (stored
     * against the 1899 sentinel date). We route each shape through the existing
     * driver-aware SQL helpers, which emit Access #...# literals, SQL Server
     * CAST(...) literals, or — for SQLite, whose dates are ISO text — a quoted
     * yyyy-mm-dd[ HH:MM:SS] string.
     *
     * @param string $value Raw posted value (dd-mm-yyyy / dd-mm-yyyy HH:MM / HH:MM)
     * @param bool   $isSqlite Whether the target connection is SQLite
     * @return string SQL-ready literal, or "NULL"
     */
    private static function formatDateValueForSql(string $value, bool $isSqlite): string
    {
        $v = trim($value);
        if ($v === '') {
            return 'NULL';
        }
        // Time-only field (rendered HH:MM; persisted against the 1899 date sentinel).
        if (preg_match('/^(\d{1,2}):(\d{2})$/', $v)) {
            return $isSqlite ? SQL::postString($v) : SQL::postTimeStr($v);
        }
        // ISO yyyy-mm-dd[ HH:MM[:SS]] — e.g. a native <input type="date">, which
        // posts ISO regardless of the dd-mm-yyyy display convention.
        if (preg_match('#^(\d{4})-(\d{1,2})-(\d{1,2})(?:[ T](\d{1,2}):(\d{2})(?::(\d{2}))?)?$#', $v, $m)) {
            $hasTime = isset($m[4]) && $m[4] !== '';
            $iso = sprintf('%04d-%02d-%02d', (int)$m[1], (int)$m[2], (int)$m[3]);
            if ($hasTime) {
                $iso .= sprintf(' %02d:%02d:%02d', (int)$m[4], (int)$m[5], (int)($m[6] ?? 0));
            }
            if ($isSqlite) {
                return SQL::postString($iso);
            }
            return $hasTime ? SQL::postDateTime($iso) : SQL::postDateOnly($iso);
        }
        // Date or datetime posted as dd-mm-yyyy with an optional HH:MM[:SS].
        if (preg_match('#^(\d{1,2})[-/](\d{1,2})[-/](\d{4})(?:\s+(\d{1,2}):(\d{2})(?::(\d{2}))?)?$#', $v, $m)) {
            $hasTime = isset($m[4]) && $m[4] !== '';
            if ($isSqlite) {
                $iso = sprintf('%04d-%02d-%02d', (int)$m[3], (int)$m[2], (int)$m[1]);
                if ($hasTime) {
                    $iso .= sprintf(' %02d:%02d:%02d', (int)$m[4], (int)$m[5], (int)($m[6] ?? 0));
                }
                return SQL::postString($iso);
            }
            if ($hasTime) {
                $iso = sprintf('%04d-%02d-%02d %02d:%02d:%02d', (int)$m[3], (int)$m[2], (int)$m[1], (int)$m[4], (int)$m[5], (int)($m[6] ?? 0));
                return SQL::postDateTime($iso);
            }
            return SQL::postDateStr($v);
        }
        // Unrecognised format — fall back to generic handling rather than guess.
        return self::formatValueForSql($v);
    }

    /**
     * Quote identifier (table/column name) for SQL
     *
     * @param string $identifier The identifier to quote
     * @param bool $isSqlite True for SQLite, false for Access/ODBC
     * @return string Quoted identifier
     */
    private static function quoteIdentifier(string $identifier, bool $isSqlite): string
    {
        // Use square brackets for both SQLite and Access (works reliably in both)
        // Note: Double quotes in SQLite can be misinterpreted as string literals
        // due to DQS (Double Quote String) mode being enabled by default
        return '[' . str_replace(']', ']]', $identifier) . ']';
    }

    /**
     * Resolve FK display labels for combobox fields in JSON forms
     *
     * For each combobox field with a value, looks up the display text from the source table.
     * Returns array of fieldName__label => displayText pairs.
     *
     * This allows the UI to immediately display the correct text for FK fields
     * even when the combo options haven't been fully loaded yet (lazy loading / search-based combos).
     *
     * @param array $jsonData Form definition JSON data
     * @param array $record Record field values
     * @param mixed $conn Database connection
     * @return array Array of fieldName__label => displayText
     */
    private static function resolveJsonFormFkLabels(array $jsonData, array $record, $conn): array
    {
        $labels = [];
        $fieldDefs = $jsonData['fields'] ?? [];
        $formDatabase = $jsonData['database'] ?? '';

        foreach ($fieldDefs as $fieldDef) {
            $fieldName = $fieldDef['name'] ?? '';
            $fieldType = $fieldDef['type'] ?? '';

            // Only process combobox and userlist types
            if (!in_array($fieldType, ['combobox', 'userlist'], true)) {
                continue;
            }

            // Get the field value (case-insensitive lookup)
            $value = null;
            foreach ($record as $key => $val) {
                if (strcasecmp($key, $fieldName) === 0) {
                    $value = $val;
                    break;
                }
            }

            // Skip empty values
            if ($value === null || $value === '') {
                continue;
            }

            // Get lookup configuration from field definition
            $sourceTable = $fieldDef['sourceTable'] ?? '';
            $idField = $fieldDef['idField'] ?? 'ID';
            $displayField = $fieldDef['displayField'] ?? '';
            $sqlList = $fieldDef['sql'] ?? '';
            $fieldDatabase = $fieldDef['database'] ?? $formDatabase;

            if (empty($sourceTable) && empty($sqlList)) {
                continue;
            }

            // Get target connection (may be different from form's main connection)
            $targetConn = $conn;
            if ($fieldDatabase !== '' && $fieldDatabase !== $formDatabase) {
                try {
                    $targetConn = self::getJsonFormConnection($fieldDatabase);
                    if ($targetConn === null) {
                        $labels[$fieldName . '__label'] = "Kan '$value' niet opzoeken (database fout)";
                        $labels[$fieldName . '__error'] = true;
                        continue;
                    }
                } catch (\Exception $e) {
                    Logger::debug("resolveJsonFormFkLabels: could not get connection for DB", ['database' => $fieldDatabase, 'error' => $e->getMessage()]);
                    $labels[$fieldName . '__label'] = "Kan '$value' niet opzoeken (database fout)";
                    $labels[$fieldName . '__error'] = true;
                    continue;
                }
            }

            try {
                $isSqlite = Database::isSQLite($targetConn);
                $valueEscaped = is_numeric($value) ? (int)$value : SQL::postString($value, $targetConn);

                // Check if we have a custom SQL query (needed when displayField is a calculated alias)
                if (!empty($sqlList)) {
                    // Use the custom SQL - it already has the display calculation
                    // Add WHERE clause to filter by ID
                    // Qualify with sourceTable if present (to avoid ambiguity in JOINed queries)
                    if (!empty($sourceTable)) {
                        $qualifiedId = self::quoteIdentifier($sourceTable, $isSqlite) . '.' . self::quoteIdentifier($idField, $isSqlite);
                    } else {
                        $qualifiedId = self::quoteIdentifier($idField, $isSqlite);
                    }
                    $sql = SQL::addWhere($sqlList, "$qualifiedId = $valueEscaped");
                    $sql = SQL::addTop($sql, 1);
                } else {
                    // Simple lookup from source table
                    $quotedTable = self::quoteIdentifier($sourceTable, $isSqlite);
                    $quotedId = self::quoteIdentifier($idField, $isSqlite);
                    $quotedDisplay = self::quoteIdentifier($displayField, $isSqlite);
                    $sql = "SELECT $quotedId, $quotedDisplay FROM $quotedTable WHERE $quotedId = $valueEscaped";
                }

                $rs = Database::openRS($sql, $targetConn);
                if ($rs === null) {
                    $dbError = Database::getLastError();
                    Logger::debug("resolveJsonFormFkLabels: query failed", ['field' => $fieldName, 'sql' => $sql, 'error' => $dbError]);
                    // Get friendly field name (remove fk prefix if present)
                    $fieldLabel = $fieldName;
                    if (stripos($fieldLabel, 'fk') === 0) {
                        $fieldLabel = substr($fieldLabel, 2);
                    }
                    // If error contains HTML diagnostic, use it directly
                    if (strpos($dbError, '<div') !== false) {
                        $labels[$fieldName . '__label'] = $dbError;
                    } else {
                        $labels[$fieldName . '__label'] = "Kan $fieldLabel '$value' niet opzoeken (query fout)";
                    }
                    $labels[$fieldName . '__error'] = true;
                    continue;
                }

                // Get the display value from first row
                if (!$rs->EOF) {
                    // Try to get the display field by name (case-insensitive)
                    $fkRow = $rs->fetchAssoc();
                    $display = null;
                    foreach ($fkRow as $key => $val) {
                        if (!is_numeric($key) && strcasecmp($key, $displayField) === 0) {
                            $display = $val;
                            break;
                        }
                    }
                    // Fallback to second column if display field not found by name
                    if ($display === null) {
                        $vals = array_values($fkRow);
                        $display = $vals[1] ?? $fkRow[$displayField] ?? '';
                    }
                    $labels[$fieldName . '__label'] = $display;
                } else {
                    // Value not found in database - add error message
                    $fieldLabel = $fieldName;
                    if (stripos($fieldLabel, 'fk') === 0) {
                        $fieldLabel = substr($fieldLabel, 2);
                    }
                    $labels[$fieldName . '__label'] = "Kan $fieldLabel '$value' niet vinden in $sourceTable";
                    $labels[$fieldName . '__error'] = true;
                }
            } catch (\Exception $e) {
                Logger::debug("resolveJsonFormFkLabels: error looking up $fieldName", ['error' => $e->getMessage()]);
                $labels[$fieldName . '__label'] = "Fout bij opzoeken: " . $e->getMessage();
                $labels[$fieldName . '__error'] = true;
            }
        }

        return $labels;
    }

    /**
     * Convert database value to boolean
     * Delegates to ListServiceHelper::toBool() (single canonical implementation)
     */
    private static function toBool($value): bool
    {
        return ListServiceHelper::toBool($value);
    }

    /**
     * Build HTML changelog for deleted record showing all field values
     *
     * @param array $formDef Form definition with fields configuration
     * @param array $fields Record field values
     * @return string HTML table with all field values
     */
    private static function buildDeleteChangelog(array $formDef, array $fields): string
    {
        $html = '<table cellspacing="0" cellpadding="3">';
        $html .= '<tr><th style="font-size:10pt;background-color:#8B0000;color:white;text-align:left">Veld</th>';
        $html .= '<th style="font-size:10pt;background-color:#8B0000;color:white;text-align:left">Verwijderde waarde</th></tr>';

        $jsonData = $formDef['_json'] ?? $formDef;
        $fieldDefs = $jsonData['fields'] ?? [];

        // Build field label and type lookups
        $fieldLabels = [];
        $fieldTypes = [];
        foreach ($fieldDefs as $fieldDef) {
            $name = $fieldDef['name'] ?? '';
            $label = $fieldDef['label'] ?? $name;
            $dataType = $fieldDef['dataType'] ?? '';
            $controlType = $fieldDef['controlType'] ?? '';
            if ($name) {
                $fieldLabels[$name] = $label;
                $fieldLabels[strtolower($name)] = $label;
                // Track boolean fields (checkbox, switch, or boolean dataType)
                $isBoolean = $dataType === 'boolean' || $controlType === 'checkbox' || $controlType === 'switch';
                $fieldTypes[$name] = $isBoolean ? 'boolean' : $dataType;
                $fieldTypes[strtolower($name)] = $fieldTypes[$name];
            }
        }

        // Output each field
        foreach ($fields as $fieldName => $value) {
            // Skip internal fields
            if (str_starts_with($fieldName, '_') || str_ends_with($fieldName, '__label')) {
                continue;
            }

            // Get display label and field type
            $label = $fieldLabels[$fieldName] ?? $fieldLabels[strtolower($fieldName)] ?? $fieldName;
            $fieldType = $fieldTypes[$fieldName] ?? $fieldTypes[strtolower($fieldName)] ?? '';

            // Format value for display
            $displayValue = '';
            if ($value === null || $value === '') {
                // Empty value - could be NULL from outer join or just empty
                $displayValue = '<span class="cma-class__em">(leeg)</span>';
            } elseif ($fieldType === 'boolean' || is_bool($value)) {
                // Boolean field - handle various representations
                $displayValue = self::toBool($value) ? 'Ja' : 'Nee';
            } elseif (Arr::isArray($value)) {
                $displayValue = Server::htmlEncode(json_encode($value, JSON_UNESCAPED_UNICODE));
            } else {
                // Truncate very long values
                $strValue = (string)$value;
                if (strlen($strValue) > 500) {
                    $displayValue = Server::htmlEncode(substr($strValue, 0, 500)) . '...';
                } else {
                    $displayValue = Server::htmlEncode($strValue);
                }
            }

            $html .= '<tr>';
            $html .= '<td style="border-bottom:1px solid #8B0000;border-left:1px solid #8B0000">' . Server::htmlEncode($label) . '</td>';
            $html .= '<td style="border-bottom:1px solid #8B0000;border-right:1px solid #8B0000">' . $displayValue . '</td>';
            $html .= '</tr>';
        }

        $html .= '</table>';
        return $html;
    }

    /**
     * Build server-side changelog for edit operations. Mirrors the buildDelete
     * Changelog HTML style but renders three columns (Veld / Oud / Nieuw)
     * and only includes rows where the normalised value actually changed.
     * Returns '' when nothing changed — caller treats that as "skip the
     * fallback, leave _changelog empty".
     *
     * Called from saveJsonFormRecord when the client-side _changelog is
     * absent (JS error, POST size truncation, etc).
     */
    private static function buildEditChangelog(array $formDef, array $oldFields, array $newData): string
    {
        $jsonData = $formDef['_json'] ?? $formDef;
        $fieldDefs = $jsonData['fields'] ?? [];

        // Build label + type lookups (mirrors buildDeleteChangelog).
        $fieldLabels = [];
        $fieldTypes  = [];
        foreach ($fieldDefs as $fieldDef) {
            $name = $fieldDef['name'] ?? '';
            $label = $fieldDef['label'] ?? $name;
            $dataType = $fieldDef['dataType'] ?? '';
            $controlType = $fieldDef['controlType'] ?? '';
            if ($name) {
                $fieldLabels[$name] = $label;
                $fieldLabels[strtolower($name)] = $label;
                $isBoolean = $dataType === 'boolean' || $controlType === 'checkbox' || $controlType === 'switch';
                $fieldTypes[$name] = $isBoolean ? 'boolean' : $dataType;
                $fieldTypes[strtolower($name)] = $fieldTypes[$name];
            }
        }

        // Build a case-insensitive lookup over $oldFields once.
        $oldByLower = [];
        foreach ($oldFields as $k => $v) {
            $oldByLower[strtolower((string)$k)] = $v;
        }

        $changes = [];
        foreach ($newData as $fieldName => $newValue) {
            // Skip internal/virtual fields.
            if (str_starts_with((string)$fieldName, '_') || str_ends_with((string)$fieldName, '__label')) {
                continue;
            }
            // Only consider fields the form defined as DB columns.
            if (!isset($fieldLabels[strtolower((string)$fieldName)])) {
                continue;
            }

            $oldValue = $oldByLower[strtolower((string)$fieldName)] ?? null;
            $fieldType = $fieldTypes[$fieldName] ?? $fieldTypes[strtolower((string)$fieldName)] ?? '';

            // Normalise for the equality check so e.g. "1" == 1 == true.
            $oldNorm = self::normalizeChangelogValue($oldValue, $fieldType);
            $newNorm = self::normalizeChangelogValue($newValue, $fieldType);
            if ($oldNorm === $newNorm) {
                continue;
            }

            $changes[] = [
                'label' => $fieldLabels[$fieldName] ?? $fieldLabels[strtolower((string)$fieldName)] ?? $fieldName,
                'old'   => $oldValue,
                'new'   => $newValue,
                'type'  => $fieldType,
            ];
        }

        if (empty($changes)) {
            return '';
        }

        // Render in the same visual style as buildDeleteChangelog (different
        // header color so an audit reader can tell edit vs delete at a glance).
        $html  = '<table cellspacing="0" cellpadding="3">';
        $html .= '<tr>';
        $html .= '<th style="font-size:10pt;background-color:#1a4d72;color:white;text-align:left">Veld</th>';
        $html .= '<th style="font-size:10pt;background-color:#1a4d72;color:white;text-align:left">Oude waarde</th>';
        $html .= '<th style="font-size:10pt;background-color:#1a4d72;color:white;text-align:left">Nieuwe waarde</th>';
        $html .= '</tr>';
        foreach ($changes as $c) {
            $html .= '<tr>';
            $html .= '<td style="border-bottom:1px solid #1a4d72;border-left:1px solid #1a4d72">' . Server::htmlEncode((string)$c['label']) . '</td>';
            $html .= '<td style="border-bottom:1px solid #1a4d72">' . self::formatChangelogValueHtml($c['old'], $c['type']) . '</td>';
            $html .= '<td style="border-bottom:1px solid #1a4d72;border-right:1px solid #1a4d72">' . self::formatChangelogValueHtml($c['new'], $c['type']) . '</td>';
            $html .= '</tr>';
        }
        $html .= '</table>';
        return $html;
    }

    /**
     * Normalise a value to a comparable string for change detection.
     * Mirrors how toBool / Arr::isArray are used in buildDeleteChangelog
     * formatting — same rules, so old vs new compares apples to apples.
     */
    private static function normalizeChangelogValue($value, string $fieldType): string
    {
        if ($value === null) {
            return '';
        }
        if ($fieldType === 'boolean' || is_bool($value)) {
            return self::toBool($value) ? '1' : '0';
        }
        if (Arr::isArray($value)) {
            return (string)(json_encode($value, JSON_UNESCAPED_UNICODE) ?: '');
        }
        return trim((string)$value);
    }

    /**
     * Render a single changelog cell value — empty placeholder, Ja/Nee for
     * booleans, JSON for arrays, escaped + 500-char truncated otherwise.
     * Same visual contract as buildDeleteChangelog's inline formatting.
     */
    private static function formatChangelogValueHtml($value, string $fieldType): string
    {
        if ($value === null || $value === '') {
            return '<span class="cma-class__em">(leeg)</span>';
        }
        if ($fieldType === 'boolean' || is_bool($value)) {
            return self::toBool($value) ? 'Ja' : 'Nee';
        }
        if (Arr::isArray($value)) {
            return Server::htmlEncode((string)json_encode($value, JSON_UNESCAPED_UNICODE));
        }
        $strValue = (string)$value;
        if (strlen($strValue) > 500) {
            return Server::htmlEncode(substr($strValue, 0, 500)) . '...';
        }
        return Server::htmlEncode($strValue);
    }

    /**
     * Log action to CMA Monitoring table
     *
     * @param string $formName JSON form name
     * @param string $formTitle Human-readable form title
     * @param string|int|null $recordId Record ID
     * @param string $action Action type: 'add', 'edit', 'delete'
     * @param string $notification Description of what happened
     */
    /**
     * Mail a change notification to the users subscribed to the form (tblNotifications,
     * keyed on the form's sourceFormId). The acting user is left out when their profile
     * says "geen notificatie voor eigen wijzigingen". Failures are logged, never thrown:
     * the record is already saved.
     */
    private static function sendChangeNotificationMail(string $formName, string $formTitle, string $notification): void
    {
        try {
            $formId = JsonFormLoader::getFormIdByName($formName);
            if ($formId === null) {
                return;
            }
            $emails = array_values(array_filter(array_map('trim', explode(';', SecurityHelper::getNotifyEmailsForForm((int)$formId))), 'strlen'));
            if ($emails === []) {
                return;
            }
            if (SecurityHelper::skipNotifyOwnRecords()) {
                $own = strtolower(trim((string)(SecurityHelper::getCurrentUserData()['userEmail'] ?? '')));
                $emails = array_values(array_filter($emails, fn($e) => strtolower($e) !== $own));
                if ($emails === []) {
                    return;
                }
            }
            $appName = (string)(Application::get('appname_simple', '') ?: Application::get('appname', '') ?: 'CMA');
            $mail = new \App\Library\Email();
            $mail->setSubject($appName . ' | CMA notificatie (' . $formTitle . ')');
            $mail->setBody('<html><head><style>body,p,td{font-family:verdana;font-size:10px}th{text-align:left;font-family:verdana;font-size:10px;color:white;background-color:#003366}</style></head><body>'
                . $notification . '</body></html>');
            $mail->setCMATemplate(false);
            foreach (array_unique($emails) as $email) {
                $mail->addRecipient($email);
            }
            if (!$mail->send()) {
                Logger::warning('sendChangeNotificationMail: verzenden mislukt', ['form' => $formName, 'to' => $emails]);
            }
        } catch (\Throwable $e) {
            \App\Library\ErrorHandler::report($e, 'CMA-notificatiemail niet verzonden (' . $formName . ')');
        }
    }

    private static function logMonitoring(
        string $formName,
        string $formTitle,
        string|int|null $recordId,
        string $action,
        array $changelog = [],
        string $recordDescription = '',
        string $logLevel = 'info'
    ): void {
        $monitoringOn = (bool)\App\Library\Settings::get('cma_monitoring');

        Logger::debug('logMonitoring START', ['form' => $formName, 'action' => $action, 'recordId' => $recordId]);

        try {
            // Get username from changelog or database
            $username = $changelog['_changelog_user'] ?? SecurityHelper::getCurrentUserName();
            if (empty($username)) {
                $username = 'Onbekend';
            }
            Logger::debug('logMonitoring: username=' . $username);

            // Build notification message
            $actionText = match ($action) {
                'add' => 'toegevoegd',
                'edit' => 'gewijzigd',
                'delete' => 'verwijderd',
                default => $action,
            };

            // Build description part
            $descPart = '';
            if (!empty($recordDescription)) {
                $descPart = " ($recordDescription)";
            }

            $notification = "<b>$username</b> heeft in formulier <b>$formTitle</b> het record{$descPart} <b>$actionText</b> (ID: $recordId).";

            // Append detailed changelog if provided (HTML table of field changes)
            $detailedChangelog = $changelog['_changelog'] ?? '';
            Logger::debug('logMonitoring: changelog_length=' . strlen($detailedChangelog));

            if (!empty($detailedChangelog)) {
                $notification .= "<br><br>" . $detailedChangelog;
            }

            // Deep link to the record itself, in the clean-URL form the CMA
            // routes today: <base>/cma/form/<form>/<id>. A deleted record has
            // nothing left to open, so the link is omitted for that action.
            if ($action !== 'delete' && (string)($recordId ?? '') !== '') {
                $detailsUrl = Request::currentDomain()
                    . Application::get('base_path', '/')
                    . 'cma/form/' . rawurlencode(strtolower($formName))
                    . '/' . rawurlencode((string)$recordId);
                $notification .= '<div>Voor details: <a href="' . $detailsUrl . '">' . $detailsUrl . '</a></div>';
            }
            Logger::debug('logMonitoring: notification_length=' . strlen($notification));

            // E-mail to the users subscribed to this form ("Notificaties" on the users
            // form), independent of the monitoring log — as the classic detailsRep_post did.
            self::sendChangeNotificationMail($formName, $formTitle, $notification);

            if (!$monitoringOn) {
                Logger::debug('logMonitoring: Monitoring disabled, only e-mail');
                return;
            }

            // Get data connection for tblCMAMonitoring
            $conn = Database::getConnection('data');
            if ($conn === null) {
                Logger::error('logMonitoring: Could not get data connection');
                return;
            }

            // Determine log level from action
            $actionLower = strtolower($action);
            if (str_contains($actionLower, 'fail') || str_contains($actionLower, 'error') ||
                str_contains($actionLower, 'fout') || str_contains($actionLower, 'denied')) {
                $logLevel = 'error';
            } elseif (str_contains($actionLower, 'warning') || str_contains($actionLower, 'waarschuwing')) {
                $logLevel = 'warning';
            }

            // The notification is a long HTML literal. On some PHP builds the
            // ODBC layer mangles a long inlined literal ("Syntax error (missing
            // operator)", "Too few parameters") while a bound parameter arrives
            // intact — bindValue() stores an Access memo exactly, no padding. So:
            // inline first (the form that works everywhere else), bound as the
            // fallback, and the LogLevel column (migration 6.3.0) left out when
            // a site does not have it yet. Every attempt that fails is logged;
            // only when all of them fail does report() carry it to the admin.
            $columns = 'Form, Formname, RecordID, Actie, Username, Notificatie';
            $values  = SQL::postString($formName) . ',' .
                SQL::postString($formTitle) . ',' .
                SQL::postString((string)($recordId ?? '')) . ',' .
                SQL::postString($action) . ',' .
                SQL::postString($username);
            $attempts = [
                ['INSERT INTO tblCMAMonitoring (' . $columns . ', LogLevel) VALUES (' . $values . ',' . SQL::postString($notification) . ',' . SQL::postString($logLevel) . ')', []],
                ['INSERT INTO tblCMAMonitoring (' . $columns . ', LogLevel) VALUES (' . $values . ',?,' . SQL::postString($logLevel) . ')', [$notification]],
                ['INSERT INTO tblCMAMonitoring (' . $columns . ') VALUES (' . $values . ',' . SQL::postString($notification) . ')', []],
                ['INSERT INTO tblCMAMonitoring (' . $columns . ') VALUES (' . $values . ',?)', [$notification]],
            ];
            $lastError = null;
            $errorsBefore = count(Database::getErrors());
            foreach ($attempts as $i => [$sql, $params]) {
                try {
                    Database::executeOn($conn, $sql, $params);
                    if ($i > 0) {
                        // The earlier attempts failed and are in the log; the row is
                        // written, so the admin notice about them would be noise.
                        Database::forgetErrorsSince($errorsBefore);
                        Logger::warning('logMonitoring: INSERT succeeded on attempt ' . ($i + 1) . ' (bound parameter or without LogLevel)', ['form' => $formName, 'recordId' => $recordId]);
                    }
                    $lastError = null;
                    break;
                } catch (\Throwable $insertEx) {
                    $lastError = $insertEx;
                }
            }
            if ($lastError !== null) {
                throw $lastError;
            }
        } catch (\Throwable $e) {
            // The record is saved by now; a lost audit row must not undo that.
            // It must not go unnoticed either: log, mail, admin toast, and a
            // warning in the save response.
            \App\Library\ErrorHandler::report($e, 'CMA Monitoring niet weggeschreven');
        }

        Logger::debug('logMonitoring END');
    }

    /**
     * Create error response
     */
    /**
     * "Laatst gewijzigd" for the record header: the CMA user's name and the date from
     * LastModifiedUser/LastModifiedDate, on forms with storeLastModified.
     */
    private static function lastModifiedMeta(array $jsonData, array $row): array
    {
        if (empty($jsonData['storeLastModified'])) {
            return [];
        }
        $user = null; $date = null;
        foreach ($row as $k => $v) {
            if (strcasecmp((string)$k, 'LastModifiedUser') === 0) { $user = $v; }
            if (strcasecmp((string)$k, 'LastModifiedDate') === 0) { $date = $v; }
        }
        if (($user === null || $user === '') && ($date === null || $date === '')) {
            return [];
        }
        $name = '';
        if ((int)$user > 0) {
            try { $name = SecurityHelper::getUserName((int)$user); } catch (\Throwable $e) { $name = ''; }
        }
        $dateText = '';
        if ($date !== null && $date !== '') {
            $norm = \App\Library\Date::normalize($date);
            $dateText = $norm ? date('d-m-Y', strtotime($norm)) : (string)$date;
        }
        return ['lastModifiedUser' => $name !== '' ? $name : ('gebruiker ' . $user), 'lastModifiedDate' => $dateText];
    }

    /**
     * May the current user write (save/delete) this record of this form?
     *
     * Uses the form's group rights, as the classic CMA did (details.asp: rights >= Full):
     *   - ACCESS_FULL / FULL_BEHEER (admins always get FULL_BEHEER): yes
     *   - ACCESS_CHANGE_OWN_DATA on a form with securityByUser: a new record, or an
     *     existing record whose `userid` column is the current user
     *   - otherwise: no
     *
     * @return string|null null when allowed, else the error message
     */
    public static function writeAccessError(string $formName, array $jsonData, $conn, string $tableName, string $idField, $recordId, bool $isSqlite): ?string
    {
        $userData = SecurityHelper::getCurrentUserData();
        $userId = (int)($userData['ID'] ?? SecurityHelper::getCurrentUserId());
        $level = SecurityHelper::checkFormRightsByName($userId, $formName);
        if ($level >= SecurityHelper::ACCESS_FULL) {
            return null;
        }
        if ($level === SecurityHelper::ACCESS_CHANGE_OWN_DATA && !empty($jsonData['securityByUser'])) {
            if ($recordId === null || $recordId === '') {
                return null;
            }
            if ($conn === null || $tableName === '') {
                return 'Geen toegang tot dit record';
            }
            $sql = 'SELECT ' . self::quoteIdentifier('userid', $isSqlite) . ' AS Eigenaar FROM ' . self::quoteIdentifier($tableName, $isSqlite)
                . ' WHERE ' . self::quoteIdentifier($idField, $isSqlite) . ' = '
                . (is_numeric($recordId) ? SQL::postNumber($recordId) : SQL::postString($recordId));
            try {
                $owner = Database::getFieldValue($conn, $sql, 'Eigenaar');
            } catch (\Throwable $e) {
                return 'Geen toegang tot dit record';
            }
            return ((int)$owner === $userId) ? null : 'Geen toegang tot dit record (niet je eigen gegevens)';
        }
        return 'Geen toegang tot dit formulier';
    }

    /**
     * Server-side validation and normalisation of posted JSON-form data.
     *
     * Mirrors the checks the classic CMA did in detailsRep_post.asp (CheckValue + the
     * per-type block): required, numeric, date/datetime, time (HH:MM), e-mail (several,
     * `;`-separated), URL (https:// prefixed), directory (upper-case, illegal characters
     * stripped, unique in the table) and maxLength for single-line text. Read-only,
     * label/custom/separator, checklist/sortlist, checkbox, image/file/video, password
     * and hidden fields are not validated (they are set by the system or elsewhere).
     *
     * @return array{errors: array<string,string>, data: array} errors keyed by field name
     *         ("<caption> is verplicht"), data with the normalised values.
     */
    public static function validateJsonFormData(array $fieldDefs, array $data, bool $isNew, $conn, string $tableName, string $idField, $recordId, bool $isSqlite): array
    {
        $errors = [];
        $skipTypes = ['label', 'custom', 'separator', 'groupseparator', 'heading', 'hidden', 'autonumber',
            'ignorefield', 'checklist', 'sortlist', 'checklistinline', 'checklisttree', 'checkbox', 'image',
            'file', 'video', 'thumbnail', 'xmlstore', 'htmlstrip', 'password', 'radio', 'radiogroup'];
        $numericTypes = ['number', 'int', 'integer', 'bigint', 'smallint', 'tinyint', 'decimal', 'numeric',
            'float', 'double', 'real', 'currency', 'money', '2', '3', '4', '5', '6', '14', '16', '17', '18', '19', '20', '21', '131', '139'];
        $dateTypes = ['date', 'datetime', 'timestamp', 'smalldatetime', '7', '133', '134', '135'];

        foreach ($fieldDefs as $def) {
            $name = (string)($def['name'] ?? '');
            $type = strtolower((string)($def['type'] ?? ''));
            if ($name === '' || in_array($type, $skipTypes, true) || !empty($def['readOnly'])) {
                continue;
            }
            // find the posted value case-insensitively
            $key = null;
            foreach ($data as $k => $v) {
                if (strcasecmp((string)$k, $name) === 0) {
                    $key = $k;
                    break;
                }
            }
            if ($key === null) {
                // not posted at all: only "required" can fail, and only on a new record
                // (an UPDATE without the field leaves the stored value as it is)
                if (!empty($def['required']) && $isNew) {
                    $errors[$name] = self::captionOf($def) . ' is verplicht';
                }
                continue;
            }
            $raw = $data[$key];
            if (is_array($raw)) {
                continue;
            }
            $value = trim((string)$raw);
            $caption = self::captionOf($def);

            if ($value === '') {
                if (!empty($def['required'])) {
                    $errors[$name] = "$caption is verplicht";
                }
                continue;
            }

            $dataType = strtolower((string)($def['dataType'] ?? ''));
            $isNumeric = in_array($dataType, $numericTypes, true) || (string)($def['numericPrecision'] ?? '') !== '';
            $isDate = in_array($type, ['date', 'datetime'], true) || in_array($dataType, $dateTypes, true);

            switch ($type) {
                case 'email':
                    $parts = array_values(array_filter(array_map('trim', preg_split('/[;,]/', $value)), 'strlen'));
                    foreach ($parts as $addr) {
                        if (filter_var($addr, FILTER_VALIDATE_EMAIL) === false) {
                            $errors[$name] = "$caption bevat een ongeldig e-mailadres ($addr)";
                            break;
                        }
                    }
                    $value = implode('; ', $parts);
                    break;

                case 'time':
                    if (!preg_match('/^(\d{1,2}):(\d{2})$/', $value, $m) || (int)$m[1] > 23 || (int)$m[2] > 59) {
                        $errors[$name] = "$caption heeft het formaat UU:MM, bijvoorbeeld 9:15";
                    }
                    break;

                case 'url':
                    if (!preg_match('#^[a-z][a-z0-9+.-]*://#i', $value) && !str_starts_with($value, 'mailto:')) {
                        $value = 'https://' . $value;
                    }
                    break;

                case 'directory':
                    // illegal characters: / [ ] ; = " \ : | , * . and whitespace
                    $value = strtoupper(preg_replace('#[/\[\];="\\:|,*.\s]+#', '', $value));
                    if ($value === '') {
                        $errors[$name] = "$caption bevat geen toegestane tekens";
                        break;
                    }
                    if ($conn !== null && $tableName !== '') {
                        $sqlCheck = 'SELECT COUNT(*) AS Aantal FROM ' . self::quoteIdentifier($tableName, $isSqlite)
                            . ' WHERE LCASE(' . self::quoteIdentifier($name, $isSqlite) . ')=' . SQL::postString(strtolower($value));
                        if (!$isNew && $recordId !== null && $recordId !== '') {
                            $sqlCheck .= ' AND ' . self::quoteIdentifier($idField, $isSqlite) . '<>'
                                . (is_numeric($recordId) ? SQL::postNumber($recordId) : SQL::postString($recordId));
                        }
                        try {
                            if ((int)Database::getFieldValue($conn, $sqlCheck, 'Aantal') > 0) {
                                $errors[$name] = "$caption '$value' is al in gebruik; kies een andere";
                            }
                        } catch (\Throwable $e) {
                            Logger::warning('validateJsonFormData: directory-uniekheid niet gecontroleerd', ['error' => $e->getMessage()]);
                        }
                    }
                    break;

                default:
                    if (in_array('ip-address-list', (array)($def['validation'] ?? []), true)) {
                        // ;-separated IP addresses or CIDR ranges (commas accepted, stored as ';'
                        // because login.php splits on ';')
                        $parts = array_values(array_filter(array_map('trim', preg_split('/[;,\s]+/', $value)), 'strlen'));
                        foreach ($parts as $part) {
                            if (!self::isIpOrCidr($part)) {
                                $errors[$name] = "$caption: '$part' is geen geldig IP-adres of CIDR-bereik";
                                break;
                            }
                        }
                        $value = implode(';', $parts);
                    } elseif ($isNumeric) {
                        if (!is_numeric(SQL::normalizeDecimal($value))) {
                            $errors[$name] = "$caption mag alleen een getal bevatten";
                        }
                    } elseif ($isDate) {
                        if (!preg_match('/^\d{1,2}:\d{2}$/', $value) && \App\Library\Date::normalize($value) === null) {
                            $errors[$name] = "$caption is geen geldige datum (dd-mm-jjjj)";
                        }
                    }
                    break;
            }

            $maxLength = (int)($def['maxLength'] ?? 0);
            if ($maxLength > 0 && in_array($type, ['textbox', 'email', 'url', 'directory', 'userlist'], true)
                && mb_strlen($value) > $maxLength) {
                $errors[$name] = "$caption is te lang (maximaal $maxLength tekens)";
            }

            $data[$key] = $value;
        }
        return ['errors' => $errors, 'data' => $data];
    }

    /**
     * Columns filled from other fields on save (old detailsRep_post.asp):
     *  - image field with widthField/heightField: the picture's pixel size (from the
     *    posted <name>_width/_height, else measured on disk);
     *  - thumbnail field with baseField: a <base>_tn.<ext> next to the original,
     *    resized to resizeWidth x resizeHeight, its file name stored;
     *  - htmlstrip field with baseField: the plain text of the base field's HTML.
     * Returns column => value; an empty return means nothing to add.
     */
    public static function derivedColumns(array $fieldDefs, array $data): array
    {
        $derived = [];
        $lowerData = [];
        foreach ($data as $k => $v) {
            $lowerData[strtolower((string)$k)] = $v;
        }
        $get = fn(string $name) => $lowerData[strtolower($name)] ?? null;
        $siteRoot = rtrim((string)Server::mapPath(Application::get('base_path', '/')), '/\\');

        foreach ($fieldDefs as $def) {
            $name = (string)($def['name'] ?? '');
            $type = (string)($def['type'] ?? '');
            if ($name === '') {
                continue;
            }
            // image settings may sit at the top level or in the "image" sub-object
            foreach (['path', 'widthField', 'heightField', 'resizeWidth', 'resizeHeight'] as $k) {
                if (!isset($def[$k]) && isset($def['image'][$k])) {
                    $def[$k] = $def['image'][$k];
                }
            }

            if ($type === 'image' && (!empty($def['widthField']) || !empty($def['heightField']))) {
                $file = trim((string)($get($name) ?? ''));
                $w = $get($name . '_width');
                $h = $get($name . '_height');
                if (($w === null || $w === '' || $h === null || $h === '') && $file !== '' && !preg_match('#^(https?:)?//#i', $file)) {
                    $disk = $siteRoot . '/' . ltrim((string)($def['path'] ?? ''), '/\\') . '/' . ltrim($file, '/\\');
                    $size = is_file($disk) ? @getimagesize($disk) : false;
                    if ($size !== false) {
                        [$w, $h] = $size;
                    }
                }
                if ($file === '') {
                    $w = $h = null; // picture cleared: clear the sizes too
                }
                if (!empty($def['widthField'])) {
                    $derived[$def['widthField']] = ($w === null || $w === '') ? '' : (int)$w;
                }
                if (!empty($def['heightField'])) {
                    $derived[$def['heightField']] = ($h === null || $h === '') ? '' : (int)$h;
                }
            } elseif ($type === 'thumbnail' && !empty($def['baseField'])) {
                $base = trim((string)($get($def['baseField']) ?? ''));
                if ($base === '' || preg_match('#^(https?:)?//#i', $base)) {
                    $derived[$name] = '';
                    continue;
                }
                $srcDir = ltrim((string)($def['path'] ?? ''), '/\\');
                $src = $siteRoot . '/' . $srcDir . '/' . ltrim($base, '/\\');
                $info = pathinfo($base);
                $thumbName = (isset($info['dirname']) && $info['dirname'] !== '.' ? $info['dirname'] . '/' : '')
                    . $info['filename'] . '_tn' . (isset($info['extension']) ? '.' . $info['extension'] : '');
                $dest = $siteRoot . '/' . $srcDir . '/' . $thumbName;
                if (is_file($src) && class_exists(\App\Library\Image::class)) {
                    try {
                        \App\Library\Image::thumbnail($src, $dest, (int)($def['resizeHeight'] ?? 0), (int)($def['resizeWidth'] ?? 0));
                    } catch (\Throwable $e) {
                        Logger::warning('Thumbnail niet gemaakt', ['src' => $src, 'error' => $e->getMessage()]);
                    }
                }
                $derived[$name] = $thumbName;
            } elseif ($type === 'htmlstrip' && !empty($def['baseField'])) {
                $html = (string)($get($def['baseField']) ?? '');
                $text = html_entity_decode(strip_tags(preg_replace('#<br\s*/?>|</p>|</div>|</li>#i', "\n", $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $text = trim(preg_replace("/[ \t\x{00A0}]+/u", ' ', preg_replace("/\n{3,}/", "\n\n", $text)));
                $maxLen = (int)($def['maxLength'] ?? 0);
                if ($maxLen > 0 && mb_strlen($text) > $maxLen) {
                    $text = mb_substr($text, 0, $maxLen);
                }
                $derived[$name] = $text;
            }
        }
        return $derived;
    }

    private static function isIpOrCidr(string $value): bool
    {
        $bits = null;
        if (strpos($value, '/') !== false) {
            [$value, $bits] = explode('/', $value, 2);
            if (!ctype_digit($bits)) {
                return false;
            }
        }
        if (filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            return $bits === null || (int)$bits <= 32;
        }
        if (filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
            return $bits === null || (int)$bits <= 128;
        }
        return false;
    }

    private static function captionOf(array $def): string
    {
        $caption = (string)($def['caption'] ?? $def['label'] ?? $def['name'] ?? 'Veld');
        $caption = preg_replace('/<br\s*\/?>.*$/i', '', $caption);
        return trim(strip_tags($caption)) ?: (string)($def['name'] ?? 'Veld');
    }

    private static function error(string $message): array
    {
        return [
            'success' => false,
            'error' => $message,
        ];
    }

    /**
     * Create query error response with SQL included on local/test environments
     */
    private static function queryError(string $sql): array
    {
        $message = 'Query mislukt: ' . Database::getLastError();

        // Include full SQL on local/test environments for debugging
        if (Application::get('local', '') || Application::get('test', '')) {
            $message .= ' | SQL: ' . $sql;
        }

        return self::error($message);
    }
}
