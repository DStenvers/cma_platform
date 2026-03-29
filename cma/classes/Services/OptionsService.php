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
use Cma\ConfigLoader;

/**
 * Options Service
 *
 * Handles combo box and checklist options for form fields.
 * Extracted from FormDataProvider for better maintainability.
 */
class OptionsService extends BaseFormService
{
    /**
     * SQL result cache - avoid executing same query multiple times per request
     * @var array<string, array>
     */
    private static array $sqlResultCache = [];

    /**
     * Table record count cache - avoids repeated COUNT queries
     * Structure: 'tableName_dbId' => count
     * @var array<string, int>
     */
    private static array $tableCountCache = [];

    /**
     * Cache group for combo options
     */
    private const CACHE_GROUP_COMBOS = 'combos';

    /**
     * Threshold for dynamic combos - tables with more records require search
     */
    private const DYNAMIC_COMBO_THRESHOLD = 1000;

    /**
     * Minimum search length for dynamic combos
     */
    private const DYNAMIC_COMBO_MIN_SEARCH = 3;

    /**
     * PERFORMANCE FIX: Increased combo cache TTL from 5 minutes to 30 minutes
     * Most combo data (status lists, categories, etc.) rarely changes
     * Users can refresh if they need fresh data
     */
    private const COMBO_CACHE_TTL = 1800;

    /**
     * Get combo box options for a field
     *
     * @param int $formId Form ID
     * @param string $fieldName Field name
     * @param string $search Search filter
     * @return array ['success' => bool, 'options' => array]
     */
    public static function getComboOptions(int $formId, string $fieldName, string $search = ''): array
    {
        try {
            if (!self::canRead($formId)) {
                return self::error('Geen toegang');
            }

            $arrRep = self::getFormDef($formId);
            $formDef = FormDefinition::fromArray($arrRep);

            if (!$formDef->isValid()) {
                return self::error('Formulier niet gevonden');
            }

            // Find the field in form definition
            $fieldIndex = self::findFieldIndex($arrRep, $fieldName);
            if ($fieldIndex === -1) {
                return self::error('Veld niet gevonden');
            }

            $controlType = (int)($arrRep[\Q_CONTROLTYPEID][$fieldIndex] ?? 0);
            if (!in_array($controlType, [FormRenderer::TYPE_COMBOBOX, FormRenderer::TYPE_USERLIST, FormRenderer::TYPE_XMLSTORE])) {
                return self::error('Geen combo veld');
            }

            // XMLStore: get options from JSON config instead of database
            if ($controlType === FormRenderer::TYPE_XMLSTORE) {
                return self::getXmlStoreOptions($search);
            }

            // Debug: capture field config before building SQL
            $sqlList = $arrRep[\Q_SQLLIST][$fieldIndex] ?? '';
            $sourceTable = $arrRep[\Q_SOURCETABLE][$fieldIndex] ?? '';
            $idFieldDef = $arrRep[\Q_CTRLIDFIELD][$fieldIndex] ?? '';
            $displayFieldDef = $arrRep[\Q_FOREIGNIDFIELD][$fieldIndex] ?? '';

            // Build query
            $sql = self::buildComboSql($arrRep, $fieldIndex, $controlType);

            // If no SQL could be built (e.g., record-dependent query without fallback), return empty options
            if (empty($sql)) {
                // Debug: include info about why SQL couldn't be built
                $debugInfo = [
                    'reason' => 'no_sql_generated',
                    'sqlList' => $sqlList,
                    'sourceTable' => $sourceTable,
                    'idField' => $idFieldDef,
                    'displayField' => $displayFieldDef,
                ];
                return self::success(['options' => [], '_debug' => $debugInfo]);
            }

            // Debug: include SQL info in response for troubleshooting
            $sqlDebug = [
                'generatedSql' => $sql,
                'originalSqlList' => $sqlList,
                'sourceTable' => $sourceTable,
                'idField' => $idFieldDef,
                'displayField' => $displayFieldDef,
            ];

            // Open connection
            $databaseId = $arrRep[\Q_DATABASEID][$fieldIndex] ?? '';
            if ($databaseId !== '') {
                $conn = Database::getConnection(CmaRepository::getResolvedConnectionString((int)$databaseId));
            } else {
                CmaRepository::openConnectionById($formDef->getDatabaseId());
                global $conn;
            }

            // Check if this is a large table that requires dynamic loading
            $isLargeTable = false;
            if ($sourceTable !== '' && $controlType === FormRenderer::TYPE_COMBOBOX) {
                $isLargeTable = self::isLargeTable($sourceTable, $conn, $databaseId);
            }
            // USERLIST (tblUsers) is typically small, but check anyway
            if ($controlType === FormRenderer::TYPE_USERLIST) {
                $isLargeTable = self::isLargeTable('tblUsers', $conn, $databaseId);
            }

            // For large tables: require minimum search length before returning results
            if ($isLargeTable && strlen($search) < self::DYNAMIC_COMBO_MIN_SEARCH) {
                return self::success([
                    'options' => [],
                    'requires_search' => true,
                    'min_search_length' => self::DYNAMIC_COMBO_MIN_SEARCH,
                    'table_count' => self::getTableRecordCount($sourceTable ?: 'tblUsers', $conn, $databaseId),
                ]);
            }

            // Apply search filter for dynamic combos
            if ($search !== '') {
                $displayField = $arrRep[\Q_FOREIGNIDFIELD][$fieldIndex] ?? '';
                if ($displayField !== '') {
                    $sql = SQL::addWhere($sql, $displayField . " LIKE " . SQL::postString('%' . $search . '%'));
                }
            }

            // Limit results based on table size
            // Large tables with search: limit to 100 results
            // Small tables: load all (no limit needed for <1000 records)
            if ($isLargeTable) {
                $sql = SQL::addTop($sql, 100);
            }

            // Check caches (3 levels: memory -> persistent -> database)
            $cacheKey = 'combo_' . md5($sql . ($databaseId !== '' ? '_db' . $databaseId : ''));

            // Level 1: In-memory cache (fastest, within request)
            if (isset(self::$sqlResultCache[$cacheKey])) {
                return self::success(['options' => self::$sqlResultCache[$cacheKey]]);
            }

            // Level 2: Persistent cache (file-based, survives across requests)
            // Skip persistent cache if searching (dynamic results)
            if ($search === '') {
                $cached = Cache::get($cacheKey);
                if ($cached !== null) {
                    self::$sqlResultCache[$cacheKey] = $cached;
                    return self::success(['options' => $cached]);
                }
            }

            // Level 3: Database query
            $rs = Database::openRS($sql, $conn);
            if ($rs === null) {
                // Include debug info in error response
                return [
                    'success' => false,
                    'error' => 'Lookup query mislukt: ' . Database::getLastError(),
                    '_sqlDebug' => $sqlDebug,
                ];
            }

            $options = self::parseComboResults($rs, $arrRep, $fieldIndex);

            // Store in caches
            self::$sqlResultCache[$cacheKey] = $options;
            if ($search === '') {
                Cache::set($cacheKey, $options, self::COMBO_CACHE_TTL);
            }

            return self::success(['options' => $options]);
        } catch (\Exception $e) {
            return self::error($e->getMessage());
        }
    }

    /**
     * Get checklist options with selection state
     *
     * @param int $formId Form ID
     * @param int $controlId Control ID
     * @param string|int $recordId Record ID (or -1 for new)
     * @return array ['success' => bool, 'options' => array, 'selected' => array]
     */
    public static function getChecklistOptions(int $formId, int $controlId, string|int $recordId): array
    {
        try {
            if (!self::canRead($formId)) {
                return self::error('Geen toegang');
            }

            $arrRep = self::getFormDef($formId);

            // Find the checklist field by control ID or field name
            // JSON forms may not have explicit control IDs, so also search by field name
            $fieldIndex = -1;
            $rowCount = count($arrRep[\Q_FIELDNAME] ?? []);
            for ($i = 0; $i < $rowCount; $i++) {
                $thisControlId = $arrRep[\Q_CONTROLID][$i] ?? '';
                $thisFieldName = $arrRep[\Q_FIELDNAME][$i] ?? '';
                if ($thisControlId == $controlId || $thisFieldName == $controlId) {
                    $fieldIndex = $i;
                    break;
                }
            }

            if ($fieldIndex === -1) {
                return self::error('Checklist niet gevonden: ' . $controlId);
            }

            $sql = $arrRep[\Q_SQLLIST][$fieldIndex] ?? '';
            if ($sql === '') {
                return self::error('Geen SQL voor checklist');
            }

            // Replace ID placeholders (single operation)
            $sql = str_ireplace(['[ID]', '[ProdID]'], (string)$recordId, $sql);

            // Open connection
            $databaseId = $arrRep[\Q_DATABASEID][$fieldIndex] ?? '';
            if ($databaseId !== '') {
                $conn = Database::getConnection(CmaRepository::getResolvedConnectionString((int)$databaseId));
            } else {
                global $conn;
            }

            $rs = Database::openRS($sql, $conn);
            if ($rs === null) {
                return self::error('Checklist query mislukt: ' . Database::getLastError());
            }

            $options = [];
            $selected = [];

            while (!$rs->EOF) {
                $row = $rs->fields;

                // Sanitize all values for UTF-8 (handles Windows-1252 legacy data)
                $row = Str::toUtf8($row);

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

            return self::success([
                'options' => $options,
                'selected' => $selected,
            ]);
        } catch (\Exception $e) {
            return self::error($e->getMessage());
        }
    }

    /**
     * Static cache for field index lookups - avoids O(n) search per field
     * @var array formKey => [lowercaseFieldName => index]
     */
    protected static array $fieldIndexCache = [];

    /**
     * Find field index by name (with caching)
     *
     * @param array|\ArrayAccess $arrRep Form definition array
     * @param string $fieldName Field name to find
     * @return int Field index or -1 if not found
     */
    public static function findFieldIndex(array|\ArrayAccess $arrRep, string $fieldName): int
    {
        $fieldNames = $arrRep[\Q_FIELDNAME] ?? [];
        // Handle both arrays and ArrayAccess/Countable objects (like ColumnMajorArray columns)
        if (!Arr::isArray($fieldNames) && !($fieldNames instanceof \Countable)) {
            return -1;
        }

        // Build cache key from form definition (use object hash or first field + count)
        $cacheKey = is_object($arrRep) ? spl_object_id($arrRep) : md5(serialize(array_slice((array)$fieldNames, 0, 3)));

        // Build lookup map if not cached
        if (!isset(self::$fieldIndexCache[$cacheKey])) {
            $lookup = [];
            foreach ($fieldNames as $i => $name) {
                $nameLower = strtolower((string)($name ?? ''));
                if ($nameLower !== '') {
                    $lookup[$nameLower] = $i;
                }
            }
            self::$fieldIndexCache[$cacheKey] = $lookup;
        }

        // O(1) lookup
        $fieldNameLower = strtolower((string)$fieldName);
        return self::$fieldIndexCache[$cacheKey][$fieldNameLower] ?? -1;
    }

    /**
     * Build SQL for combo options
     *
     * @param array|\ArrayAccess $arrRep Form definition
     * @param int $fieldIndex Field index
     * @param int $controlType Control type
     * @param array $recordContext Optional record context with parameters to replace
     * @return string SQL query
     */
    protected static function buildComboSql(array|\ArrayAccess $arrRep, int $fieldIndex, int $controlType, array $recordContext = []): string
    {
        if ($controlType === FormRenderer::TYPE_USERLIST) {
            return 'SELECT ID, userFullName FROM tblUsers ORDER BY userFullName';
        }

        // XMLStore is handled by getXmlStoreOptions() - should not reach here
        if ($controlType === FormRenderer::TYPE_XMLSTORE) {
            return '';
        }

        // Check for custom SQL first
        $sql = $arrRep[\Q_SQLLIST][$fieldIndex] ?? '';
        if (!empty($sql)) {
            // Check if SQL contains parameter placeholders that need record context.
            // In Access SQL, [word] can be either:
            // - Table/column identifier: [TableName].[ColumnName] or FROM [TableName]
            // - Parameter placeholder: [ID], [fkOpleiding], etc.
            // Strategy: If sourceTable is defined, check for common parameter patterns.
            // If no pattern found but SQL fails, it will return error (better than wrong data).
            $sourceTable = $arrRep[\Q_SOURCETABLE][$fieldIndex] ?? '';
            $idField = $arrRep[\Q_CTRLIDFIELD][$fieldIndex] ?? '';
            $displayField = $arrRep[\Q_FOREIGNIDFIELD][$fieldIndex] ?? '';

            // If we have a source table defined, we can use it as fallback for parameter-dependent SQL
            // Check for common parameter patterns
            $hasParameters = preg_match('/\[(?:id|fk[a-z0-9_]*|prod[a-z0-9_]*|parent[a-z0-9_]*|opleiding[a-z0-9_]*)\]/i', $sql);

            // Also check: if sourceTable is defined and SQL contains WHERE with brackets, likely has params
            if (!$hasParameters && !empty($sourceTable) && preg_match('/WHERE\s+.*\[[a-z_][a-z0-9_]*\]/i', $sql)) {
                $hasParameters = true;
            }

            // If parameter found, check if we have context to resolve it
            if ($hasParameters) {
                // Check if record context can provide the parameters
                $canResolve = false;
                if (!empty($recordContext)) {
                    // Check if any context key matches a parameter in the SQL
                    foreach ($recordContext as $param => $value) {
                        if (preg_match('/\[' . preg_quote($param, '/') . '\]/i', $sql)) {
                            $canResolve = true;
                            break;
                        }
                    }
                }

                // If we can resolve with context, return the SQL as-is (params will be replaced by caller)
                if ($canResolve) {
                    return $sql;
                }

                // No context - try to use source table as fallback
                if (!empty($sourceTable) && !empty($idField)) {
                    // Use source table instead of record-dependent SQL
                    // Note: displayField might be an alias from the original SQL, not an actual column in sourceTable
                    // Check if displayField exists in the original SQL as "AS displayField" (indicating it's an alias)
                    $isAlias = !empty($displayField) && preg_match('/\s+as\s+' . preg_quote($displayField, '/') . '\b/i', $sql);

                    if ($isAlias || empty($displayField)) {
                        // displayField is an alias or not set - try to find a real column
                        // Common name field patterns in Dutch/English databases
                        $commonNameFields = ['Naam', 'Name', 'Omschrijving', 'Description', 'Descr', 'Title', 'Titel', 'Label'];
                        $safeDisplayField = $idField; // Default fallback

                        // Try to find a common name field that might exist
                        foreach ($commonNameFields as $nameField) {
                            // Check if this field appears in original SQL (might indicate it exists)
                            if (stripos($sql, $sourceTable . '.' . $nameField) !== false ||
                                stripos($sql, '[' . $nameField . ']') !== false) {
                                $safeDisplayField = $nameField;
                                break;
                            }
                        }
                    } else {
                        $safeDisplayField = $displayField;
                    }

                    return "SELECT [$idField], [$safeDisplayField] FROM [$sourceTable] ORDER BY [$safeDisplayField]";
                }
                // No source table fallback - return empty (can't build query without record context)
                return '';
            }
            return $sql;
        }

        // Build from table/field definitions
        $sourceTable = $arrRep[\Q_SOURCETABLE][$fieldIndex] ?? '';
        $idField = $arrRep[\Q_CTRLIDFIELD][$fieldIndex] ?? '';
        $displayField = $arrRep[\Q_FOREIGNIDFIELD][$fieldIndex] ?? '';

        // If no source table or id field defined, cannot build query
        if (empty($sourceTable) || empty($idField)) {
            return '';
        }

        // Use idField as displayField if not specified
        if (empty($displayField)) {
            $displayField = $idField;
        }

        return "SELECT [$idField], [$displayField] FROM [$sourceTable] ORDER BY [$displayField]";
    }

    /**
     * Parse combo query results into options array
     *
     * @param mixed $rs Recordset
     * @param array|\ArrayAccess $arrRep Form definition
     * @param int $fieldIndex Field index
     * @return array Options array
     */
    protected static function parseComboResults($rs, array|\ArrayAccess $arrRep, int $fieldIndex): array
    {
        $options = [];
        $idField = $arrRep[\Q_CTRLIDFIELD][$fieldIndex] ?? 'ID';
        $displayField = $arrRep[\Q_FOREIGNIDFIELD][$fieldIndex] ?? '';

        while (!$rs->EOF) {
            $row = $rs->fields;

            // Sanitize all values for UTF-8 (handles Windows-1252 legacy data)
            $row = Str::toUtf8($row);

            $rowValues = array_values($row);

            // Case-insensitive field lookup (ODBC may return different case than SQL query)
            $id = self::getFieldCaseInsensitive($row, $idField) ?? ($rowValues[0] ?? '');
            $text = self::getFieldCaseInsensitive($row, $displayField) ?? ($rowValues[1] ?? $rowValues[0] ?? '');

            // Handle grouped options (pipe-separated)
            $group = '';
            if (strpos($text, '|') !== false) {
                list($group, $text) = explode('|', $text, 2);
                $group = trim($group);
            }

            // Only include group key if it has a value (saves bandwidth)
            $option = [
                'id' => $id,
                'text' => trim($text),
            ];
            if ($group !== '') {
                $option['group'] = $group;
            }
            $options[] = $option;

            $rs->MoveNext();
        }

        return $options;
    }

    /**
     * Get field value from row with case-insensitive key lookup
     *
     * ODBC may return column names with different casing than the SQL query.
     * This method handles that by trying exact match first, then case-insensitive.
     *
     * @param array $row Row data
     * @param string $fieldName Field name to find
     * @return mixed|null Field value or null if not found
     */
    protected static function getFieldCaseInsensitive(array $row, string $fieldName): mixed
    {
        if ($fieldName === '') {
            return null;
        }

        // Try exact match first (fastest)
        if (isset($row[$fieldName])) {
            return $row[$fieldName];
        }

        // Case-insensitive lookup
        $lowerFieldName = strtolower($fieldName);
        foreach ($row as $key => $value) {
            if (strtolower((string)$key) === $lowerFieldName) {
                return $value;
            }
        }

        return null;
    }

    /**
     * Get combo options for multiple fields in one efficient batch request
     *
     * This is the public API for batch combo loading - it handles form definition
     * loading and permission checks internally.
     *
     * @param int $formId Form ID
     * @param array $fieldNames List of field names to get options for
     * @param array $recordContext Optional record context for parameter replacement (e.g., ['fkOpleiding' => 123])
     * @return array ['success' => bool, 'combos' => [fieldName => options array]]
     */
    public static function getComboOptionsBatch(int $formId, array $fieldNames, array $recordContext = []): array
    {
        try {
            if (!self::canRead($formId)) {
                return self::error('Geen toegang');
            }

            $arrRep = self::getFormDef($formId);
            if ($arrRep === null) {
                return self::error('Formulier niet gevonden');
            }

            $combos = self::getComboOptionsForFields($formId, $fieldNames, $arrRep, $recordContext);
            return self::success(['combos' => $combos]);
        } catch (\Exception $e) {
            return self::error($e->getMessage());
        }
    }

    /**
     * Get combo options for multiple fields at once (batch operation)
     *
     * This is optimized for table view - loads all options for combo fields
     * so FK values can be resolved to display text and Select2 can be populated.
     * Use getComboOptionsBatch() for the public API that handles form def loading.
     *
     * @param int $formId Form ID
     * @param array $fieldNames List of field names to get options for
     * @param array|\ArrayAccess $arrRep Form definition array (to avoid re-fetching)
     * @param array $recordContext Optional record context for parameter replacement
     * @return array fieldName => [options array]
     */
    public static function getComboOptionsForFields(int $formId, array $fieldNames, array|\ArrayAccess $arrRep, array $recordContext = []): array
    {
        $result = [];

        if (empty($fieldNames)) {
            return $result;
        }

        $formDef = FormDefinition::fromArray($arrRep);
        if (!$formDef->isValid()) {
            return $result;
        }

        // Open connection once for all lookups
        CmaRepository::openConnectionById($formDef->getDatabaseId());
        global $conn;

        foreach ($fieldNames as $fieldName) {
            // Find the field in form definition
            $fieldIndex = self::findFieldIndex($arrRep, $fieldName);
            if ($fieldIndex === -1) {
                continue;
            }

            $controlType = (int)($arrRep[\Q_CONTROLTYPEID][$fieldIndex] ?? 0);
            if (!in_array($controlType, [FormRenderer::TYPE_COMBOBOX, FormRenderer::TYPE_USERLIST, FormRenderer::TYPE_XMLSTORE])) {
                continue;
            }

            // Build query - reuse existing helper
            $sql = self::buildComboSql($arrRep, $fieldIndex, $controlType, $recordContext);
            if (empty($sql)) {
                continue;
            }

            // Apply record context parameter replacement if provided
            if (!empty($recordContext)) {
                foreach ($recordContext as $param => $value) {
                    // Replace [param] with value (case-insensitive)
                    $sql = preg_replace('/\[' . preg_quote($param, '/') . '\]/i', (string)$value, $sql);
                }
            }

            // Check if field has its own database
            $databaseId = $arrRep[\Q_DATABASEID][$fieldIndex] ?? '';
            if ($databaseId !== '') {
                $fieldConn = Database::getConnection(CmaRepository::getResolvedConnectionString((int)$databaseId));
            } else {
                $fieldConn = $conn;
            }

            // Execute query with limit for performance
            $sql = SQL::addTop($sql, 2000);

            // Check caches (3 levels: memory -> persistent -> database)
            $cacheKey = 'combo_' . md5($sql . ($databaseId !== '' ? '_db' . $databaseId : ''));

            // Level 1: In-memory cache (fastest)
            if (isset(self::$sqlResultCache[$cacheKey])) {
                $result[$fieldName] = self::$sqlResultCache[$cacheKey];
                continue;
            }

            // Level 2: Persistent cache (survives across requests)
            $cached = Cache::get($cacheKey);
            if ($cached !== null) {
                self::$sqlResultCache[$cacheKey] = $cached;
                $result[$fieldName] = $cached;
                continue;
            }

            // Level 3: Database query
            $rs = Database::openRS($sql, $fieldConn);
            if ($rs === null) {
                continue;
            }

            // Parse results and store in both caches
            $options = self::parseComboResults($rs, $arrRep, $fieldIndex);
            self::$sqlResultCache[$cacheKey] = $options;
            Cache::set($cacheKey, $options, self::COMBO_CACHE_TTL);
            $result[$fieldName] = $options;
        }

        return $result;
    }

    /**
     * Get XMLStore (DataStore) options from JSON config
     *
     * @param string $search Search filter
     * @return array ['success' => bool, 'options' => array]
     */
    protected static function getXmlStoreOptions(string $search = ''): array
    {
        $names = ConfigLoader::getSelectableDataSourceNames();

        // Apply search filter if provided
        if (!empty($search)) {
            $searchLower = strtolower($search);
            $names = array_filter($names, function($name) use ($searchLower) {
                return stripos($name, $searchLower) !== false;
            });
            $names = array_values($names); // Re-index
        }

        // Build options array in the expected format (id and text both are the name)
        $options = [];
        foreach ($names as $name) {
            $options[] = [
                'id' => $name,
                'text' => $name,
            ];
        }

        return self::success(['options' => $options]);
    }

    /**
     * Get table record count with caching
     *
     * Uses memory cache first, then persistent file cache (1 hour TTL).
     * This allows efficient checking of whether a table needs dynamic combo loading.
     *
     * @param string $tableName Table name
     * @param mixed $conn Database connection
     * @param string $databaseId Database ID for cache key
     * @return int Record count
     */
    public static function getTableRecordCount(string $tableName, $conn, string $databaseId = ''): int
    {
        if (empty($tableName)) {
            return 0;
        }

        $cacheKey = 'tblcount_' . strtolower($tableName) . '_' . $databaseId;

        // Level 1: Memory cache (fastest, within request)
        if (isset(self::$tableCountCache[$cacheKey])) {
            return self::$tableCountCache[$cacheKey];
        }

        // Level 2: Persistent file cache (1 hour TTL)
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            self::$tableCountCache[$cacheKey] = (int)$cached;
            return (int)$cached;
        }

        // Level 3: Database query
        try {
            $sql = "SELECT COUNT(*) AS cnt FROM [$tableName]";
            $rs = Database::openRS($sql, $conn);

            if ($rs === null || $rs->EOF) {
                return 0;
            }

            $count = (int)($rs->fields['cnt'] ?? $rs->fields[0] ?? 0);

            // Cache for 1 hour (table sizes don't change frequently)
            self::$tableCountCache[$cacheKey] = $count;
            Cache::set($cacheKey, $count, 3600);

            return $count;
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Check if a table requires dynamic combo loading (search-based)
     *
     * @param string $tableName Table name
     * @param mixed $conn Database connection
     * @param string $databaseId Database ID
     * @return bool True if table has more than DYNAMIC_COMBO_THRESHOLD records
     */
    public static function isLargeTable(string $tableName, $conn, string $databaseId = ''): bool
    {
        $count = self::getTableRecordCount($tableName, $conn, $databaseId);
        return $count > self::DYNAMIC_COMBO_THRESHOLD;
    }
}
