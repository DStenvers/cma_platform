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

    // Limits (matching list.asp)
    public const LIST_LIMIT = 800;      // Force search if more records than this
    public const FLUSH_LIMIT = 200;     // Flush output buffer if more than this
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
     * Build combo SQL
     */
    private static function buildComboSql(array|\ArrayAccess $arrRep, int $fieldIndex, int $controlType): string
    {
        if ($controlType === FormRenderer::TYPE_USERLIST) {
            return 'SELECT ID, userFullName FROM tblUsers ORDER BY userFullName';
        }

        // XMLStore is handled by OptionsService::getXmlStoreOptions() - should not reach here
        if ($controlType === FormRenderer::TYPE_XMLSTORE) {
            return '';
        }

        $sqlList = $arrRep[\Q_SQLLIST][$fieldIndex] ?? '';
        if ($sqlList !== '') {
            // Remove ID placeholders for option list
            $sqlList = str_ireplace('[ID]', 'NULL', $sqlList);
            $sqlList = str_ireplace('[ProdID]', 'NULL', $sqlList);
            return $sqlList;
        }

        // Build default combo SQL
        $displayField = $arrRep[\Q_FOREIGNIDFIELD][$fieldIndex] ?? '';
        $idField = $arrRep[\Q_CTRLIDFIELD][$fieldIndex] ?? '';
        $sourceTable = $arrRep[\Q_SOURCETABLE][$fieldIndex] ?? '';

        return "SELECT $idField, $displayField FROM $sourceTable ORDER BY $displayField";
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
                // Log but continue
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
            $canEdit = $accessLevel >= SecurityHelper::ACCESS_FULL && ($jsonData['allowEdit'] ?? true);
            $canAdd = $accessLevel >= SecurityHelper::ACCESS_FULL && ($jsonData['allowAdd'] ?? true);
            $canDelete = $accessLevel >= SecurityHelper::ACCESS_FULL && ($jsonData['allowDelete'] ?? true);

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
                'meta' => [
                    'id' => $recordId,
                    'accessLevel' => $accessLevel,
                    'canEdit' => $canEdit,
                    'canAdd' => $canAdd,
                    'canDelete' => $canDelete,
                ],
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
            if (!SecurityHelper::isAdmin()) {
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
                $data['id'] = $recordId;
                $isNew = empty($recordId);
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

            // Build list of valid database fields from form definition
            // Keys are lowercase for case-insensitive lookup, values are original field names
            $validFields = [];
            foreach ($jsonData['fields'] ?? [] as $fieldDef) {
                $fieldName = $fieldDef['name'] ?? '';
                $fieldType = $fieldDef['type'] ?? '';
                // Skip custom renderers and non-database fields
                if ($fieldName && $fieldType !== 'custom' && $fieldType !== 'label' && $fieldType !== 'separator') {
                    $validFields[strtolower($fieldName)] = $fieldName;
                }
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

            $isNew = empty($recordId);

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
                    $fields[] = self::quoteIdentifier($field, $isSqlite);
                    $values[] = self::formatValueForSql($value);
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
                    $sets[] = self::quoteIdentifier($field, $isSqlite) . " = " . self::formatValueForSql($value);
                }
                // Handle both numeric and GUID IDs - use string quoting for non-numeric IDs
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
            $stmt = Database::query($sql, [], $conn);

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
                    $errorMsg = "Kan niet opslaan: " . Database::getUserFriendlyError($tableName);
                }

                Logger::error("SAVE: Database error", [
                    'form' => $formName,
                    'table' => $tableName,
                    'missing_columns' => $missingColumns,
                    'sql' => $sql
                ]);

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
                if (empty($recordId)) {
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

                if (empty($recordId)) {
                    $errorMsg = "Record ingevoegd maar kon geen ID ophalen";
                    Logger::error("SAVE: Could not get ID after insert");
                    return self::error($errorMsg);
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

            // Log to CMA Monitoring with changelog
            $formTitle = $jsonData['title'] ?? $formName;
            $action = $isNew ? 'add' : 'edit';
            self::logMonitoring($formName, $formTitle, $recordId, $action, $changelog);

            return [
                'success' => true,
                'id' => $recordId,
                'isNew' => $isNew,
                'message' => $isNew ? 'Record aangemaakt' : 'Record opgeslagen',
            ];

        } catch (\Exception $e) {
            return self::error($e->getMessage());
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

            // Check for context-dependent SQL (contains [id] or similar placeholders)
            // These require a record context to be provided
            if (!empty($sql) && preg_match('/\[id\]|\[parentId\]|\[recordId\]/i', $sql)) {
                // SQL requires record context but no lookupId was provided
                if ($lookupId === '') {
                    return [
                        'success' => true,
                        'options' => [],
                        'requires_context' => true,
                        'message' => 'Deze combo vereist een record context (id parameter)',
                    ];
                }
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
                    $lookupSql = SQL::addWhere($baseSql, self::quoteIdentifier($sourceTable . '.' . $idField, $isSqlite) . " = " . is_numeric($lookupId) ? SQL::postNumber($lookupId) : SQL::postString($lookupId));
                    $lookupSql = SQL::addTop($lookupSql, 1);
                } elseif (!empty($sourceTable) && !empty($displayField)) {
                    // Simple case: sourceTable with displayField column
                    $lookupSql = "SELECT " . self::quoteIdentifier($idField, $isSqlite) . ", " . self::quoteIdentifier($displayField, $isSqlite) .
                                 " FROM " . self::quoteIdentifier($sourceTable, $isSqlite) .
                                 " WHERE " . self::quoteIdentifier($idField, $isSqlite) . " = " . is_numeric($lookupId) ? SQL::postNumber($lookupId) : SQL::postString($lookupId);
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
                    $row = $rs->fetchAssoc();
                    $values = [];
                    foreach ($row as $key => $value) {
                        if (!is_numeric($key)) {
                            $values[] = $value;
                        }
                    }
                    if (count($values) >= 2) {
                        $labelText = Str::toUtf8($values[1]);
                        return [
                            'success' => true,
                            'label' => $labelText,
                            'options' => [['id' => $values[0], 'text' => $labelText]],
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
                $row = $rs->fetchAssoc();
                // Get first two non-numeric fields as id and text
                $values = [];
                foreach ($row as $key => $value) {
                    if (!is_numeric($key)) {
                        $values[] = $value;
                    }
                }
                if (count($values) >= 2) {
                    $options[] = [
                        'id' => $values[0],
                        'text' => Str::toUtf8($values[1]),
                    ];
                } elseif (count($values) === 1) {
                    $options[] = [
                        'id' => $values[0],
                        'text' => Str::toUtf8($values[0]),
                    ];
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
            if (!SecurityHelper::isAdmin()) {
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
                // Non-critical: continue with delete even if we can't get full data
                Logger::debug('deleteJsonFormRecord: Could not get record data for audit', ['error' => $e->getMessage()]);
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
                return self::error('Verwijderen mislukt: ' . ($lastError ?: 'onbekende fout'));
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
            ];

        } catch (\Exception $e) {
            return self::error($e->getMessage());
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
                $displayValue = '<em>(leeg)</em>';
            } elseif ($fieldType === 'boolean' || is_bool($value)) {
                // Boolean field - handle various representations
                $displayValue = self::toBool($value) ? 'Ja' : 'Nee';
            } elseif (Arr::isArray($value)) {
                $displayValue = Server::htmlEncode(implode(', ', $value));
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
     * Log action to CMA Monitoring table
     *
     * @param string $formName JSON form name
     * @param string $formTitle Human-readable form title
     * @param string|int|null $recordId Record ID
     * @param string $action Action type: 'add', 'edit', 'delete'
     * @param string $notification Description of what happened
     */
    private static function logMonitoring(
        string $formName,
        string $formTitle,
        string|int|null $recordId,
        string $action,
        array $changelog = [],
        string $recordDescription = '',
        string $logLevel = 'info'
    ): void {
        // Check if monitoring is enabled
        if (!Application::get('cma_monitoring', '')) {
            Logger::debug('logMonitoring: Monitoring disabled');
            return;
        }

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
            Logger::debug('logMonitoring: notification_length=' . strlen($notification));

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

            // Use simple INSERT without checking column existence
            // The LogLevel column was added in migration 6.3.0 and should exist
            // If it doesn't exist, Access will error and we'll catch it
            $sql = 'INSERT INTO tblCMAMonitoring (Form, Formname, RecordID, Actie, Username, Notificatie, LogLevel) VALUES (' .
                SQL::postString($formName) . ',' .
                SQL::postString($formTitle) . ',' .
                SQL::postString((string)($recordId ?? '')) . ',' .
                SQL::postString($action) . ',' .
                SQL::postString($username) . ',' .
                SQL::postString($notification) . ',' .
                SQL::postString($logLevel) . ')';

            Logger::debug('logMonitoring: Executing INSERT', ['sqlLength' => strlen($sql)]);

            try {
                Database::execute($sql, [], $conn);
                Logger::debug('logMonitoring: INSERT successful');
            } catch (\Exception $insertEx) {
                // LogLevel column might not exist - try without it
                Logger::debug('logMonitoring: INSERT with LogLevel failed, trying without', ['error' => $insertEx->getMessage()]);

                $sqlNoLogLevel = 'INSERT INTO tblCMAMonitoring (Form, Formname, RecordID, Actie, Username, Notificatie) VALUES (' .
                    SQL::postString($formName) . ',' .
                    SQL::postString($formTitle) . ',' .
                    SQL::postString((string)($recordId ?? '')) . ',' .
                    SQL::postString($action) . ',' .
                    SQL::postString($username) . ',' .
                    SQL::postString($notification) . ')';

                Database::execute($sqlNoLogLevel, [], $conn);
                Logger::debug('logMonitoring: INSERT without LogLevel successful');
            }

        } catch (\Throwable $e) {
            // Log but don't fail the operation - catch Throwable to include Fatal errors
            Logger::exception($e, 'logMonitoring exception');
        }

        Logger::debug('logMonitoring END');
    }

    /**
     * Create error response
     */
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
