<?php

namespace Cma;

use App\Library\Application;
use App\Library\Arr;
use App\Library\Cache;
use App\Library\Database;
use App\Library\Error;
use App\Library\Response;
use App\Library\Server;
use App\Library\SQL;
use PDO;

require_once __DIR__ . '/ConfigLoader.php';

// ADO field type constants (if not already defined)
if (!defined('ADINTEGER')) define('ADINTEGER', 3);
if (!defined('ADDATE')) define('ADDATE', 7);
if (!defined('ADBOOLEAN')) define('ADBOOLEAN', 11);
if (!defined('adVarWChar')) define('adVarWChar', 202);
if (!defined('adLongVarWChar')) define('adLongVarWChar', 203);

/**
 * CMA Repository Helper Class
 *
 * Provides utility methods for CMA admin interface:
 * - Form/module/database selection dropdowns
 * - Repository queries
 * - Page caching control
 */
class CmaRepository
{
    /**
     * @var bool Track if caching has been set
     */
    private static bool $listCachedSet = false;

    /**
     * Set page caching on or off
     *
     * @param bool $enabled Enable caching
     */
    public static function setCaching(bool $enabled): void
    {
        if (self::$listCachedSet) {
            return;
        }

        // Only set HTTP headers - don't echo meta tags as this is called after <head> is closed
        if ($enabled) {
            Response::cacheExpires(24);
        } else {
            Response::noCache();
        }

        self::$listCachedSet = true;
    }

    /**
     * Check if a form has a filter field
     *
     * @param int|string $formId Form ID
     * @param \PDO $connection Repository connection (unused - kept for API compatibility)
     * @return bool True if form has filter field
     */
    public static function formIsFiltered($formId, \PDO $connection): bool
    {
        // Use JSON definition instead of repository query
        $filterField = JsonFormLoader::getFilterFieldByFormId((int)$formId);
        return $filterField !== null && $filterField !== '';
    }

    /**
     * Get record description based on list view from repository
     *
     * @param int|string $formId Form ID or form name
     * @param int|string $recordId Record ID
     * @param \PDO|null $repConnection Repository connection (unused - kept for API compatibility)
     * @param \PDO|null $dataConnection Data connection (will be opened based on form config)
     * @return string Record description
     */
    public static function getRecordDescription($formId, $recordId, ?\PDO $repConnection, ?\PDO &$dataConnection = null): string
    {
        if (($recordId === '' || $recordId === null) || empty($formId)) {
            return '';
        }

        // Load form definition from JSON
        $formDef = null;
        if (is_numeric($formId)) {
            // Lookup by source form ID
            $formDef = \Cma\JsonFormLoader::getFormDefBySourceId((int)$formId);
        } else {
            // Lookup by form name
            $formDef = \Cma\JsonFormLoader::loadFormDefinition((string)$formId);
        }

        if ($formDef === null) {
            return '';
        }

        // Get form configuration from JSON
        $idField = $formDef['idField'] ?? '';
        $baseQuery = $formDef['listQuery'] ?? '';
        $group1Field = $formDef['group1Field'] ?? '';
        $group2Field = $formDef['group2Field'] ?? '';
        $group3Field = $formDef['group3Field'] ?? '';
        $detailField = $formDef['detailField'] ?? '';

        if (empty($baseQuery) || empty($idField)) {
            return '';
        }

        // Open connection to data database
        $databaseId = $formDef['database'] ?? '';
        if (is_numeric($databaseId)) {
            self::openConnectionById((int)$databaseId);
        } else {
            $conn = Database::getConnection($databaseId ?: 'data');
        }

        global $conn;
        $baseQuery = str_ireplace(chr(34), "'", str_ireplace('&', '+', $baseQuery));

        // PERFORMANCE FIX: Wrap query with WHERE clause to fetch only the needed record
        $escapedId = is_numeric($recordId) ? intval($recordId) : SQL::postString($recordId);
        $descrSQL = "SELECT * FROM (" . $baseQuery . ") AS _subq WHERE [" . $idField . "] = " . $escapedId;

        $dataRs = null;
        $dataRs = Database::openRS($descrSQL, $conn, adOpenForwardOnly);

        // Fallback to original method if subquery fails (some older Access databases may not support this)
        if ($dataRs === null) {
            // PERFORMANCE FIX: Add TOP limit to prevent loading entire table in fallback
            $limitedQuery = preg_replace('/^\s*SELECT\s+/i', 'SELECT TOP 10000 ', $baseQuery);
            $dataRs = Database::openRS($limitedQuery, $conn, adOpenForwardOnly);
            if ($dataRs === null) {
                // Try original query if TOP syntax fails
                $dataRs = Database::openRS($baseQuery, $conn, adOpenForwardOnly);
                if ($dataRs === null) {
                    return '';
                }
            }
            // Original loop as fallback (now limited to 10000 records max)
            while (($dataRow = $dataRs->fetch(PDO::FETCH_ASSOC)) !== false) {
                if (strval($dataRow[$idField] ?? '') === strval($recordId)) {
                    $repRow = [
                        'Group1Field' => $group1Field,
                        'Group2Field' => $group2Field,
                        'Group3Field' => $group3Field,
                        'DetailField' => $detailField
                    ];
                    return self::buildRecordDescription($dataRow, $repRow);
                }
            }
            return '';
        }

        $dataRow = $dataRs->fetch(PDO::FETCH_ASSOC);
        if ($dataRow === false) {
            return '';
        }

        $repRow = [
            'Group1Field' => $group1Field,
            'Group2Field' => $group2Field,
            'Group3Field' => $group3Field,
            'DetailField' => $detailField
        ];
        return self::buildRecordDescription($dataRow, $repRow);
    }

    /**
     * Build a record description string from data row and form config
     *
     * @param array $dataRow The data row
     * @param array $repRow The form configuration row
     * @return string The formatted description
     */
    private static function buildRecordDescription(array $dataRow, array $repRow): string
    {
        $result = '';
        if (!empty($repRow['Group1Field'])) {
            $result .= ($dataRow[$repRow['Group1Field']] ?? '') . ' | ';
        }
        if (!empty($repRow['Group2Field'])) {
            $result .= ($dataRow[$repRow['Group2Field']] ?? '') . ' | ';
        }
        if (!empty($repRow['Group3Field'])) {
            $result .= ($dataRow[$repRow['Group3Field']] ?? '') . ' | ';
        }
        $result .= $dataRow[$repRow['DetailField']] ?? '';
        return $result;
    }

    /**
     * Render a database selection dropdown
     *
     * @param int|string $defaultId Default selected database ID
     * @param bool $enabled Whether the select is enabled
     * @param bool $includeRepository Include repository database option
     * @param bool $autoSubmit Auto-submit form on change
     * @param \PDO $connection Repository connection (unused - kept for API compatibility)
     */
    /**
     * Get list of selectable databases (excludes repository, users database only for developers)
     *
     * @return array Array of ['id' => int, 'title' => string, 'isData' => bool]
     */
    public static function getSelectableDatabases(): array
    {
        $options = [];
        $isDeveloper = SecurityHelper::isDeveloper();

        if (ConfigLoader::exists('databases')) {
            $databases = ConfigLoader::getDatabases();
            foreach ($databases as $db) {
                $connStr = strtolower($db['connectionString'] ?? '');

                // Skip repository database always
                if (strpos($connStr, 'repository') !== false) {
                    continue;
                }

                // Skip users database unless user is a developer
                if (strpos($connStr, 'cmausers') !== false && !$isDeveloper) {
                    continue;
                }

                // Check if this is a data database by name or known data file patterns
                $dbName = strtolower($db['name'] ?? '');
                $isDataDb = ($dbName === 'data' ||
                            strpos($connStr, 'main.mdb') !== false ||
                            strpos($connStr, 'webdata.mdb') !== false ||
                            strpos($connStr, 'pdodomain.mdb') !== false ||
                            strpos($connStr, 'pdodomein.mdb') !== false);

                $options[] = [
                    'id' => $db['id'],
                    'title' => $db['name'] ?? ('Database ' . $db['id']),
                    'isData' => $isDataDb
                ];
            }
        }

        return $options;
    }

    /**
     * Get default database ID (first data database, or first database if no data db)
     *
     * @return int|null Database ID or null if none available
     */
    public static function getDefaultDatabaseId(): ?int
    {
        $databases = self::getSelectableDatabases();

        // First try to find a data database
        foreach ($databases as $db) {
            if ($db['isData']) {
                return $db['id'];
            }
        }

        // Otherwise return first database
        if (!empty($databases)) {
            return $databases[0]['id'];
        }

        return null;
    }

    /**
     * Render database selection dropdown
     *
     * @param mixed $defaultId Default selected database ID
     * @param bool $enabled Whether the select is enabled
     * @param bool $includeRepository Include repository database option
     * @param bool $autoSubmit Auto-submit form on change
     * @param \PDO $connection Repository connection (unused - kept for API compatibility)
     */
    public static function renderDatabaseSelect($defaultId, bool $enabled, bool $includeRepository, bool $autoSubmit, ?\PDO $connection = null): string
    {
        $disabledAttr = $enabled ? '' : '_disabled disabled ';
        $onChangeAttr = $autoSubmit ? ' onchange="document.forms.main.submit()" ' : '';
        $name = $enabled ? 'database' : 'database_disabled';

        $options = self::getSelectableDatabases();
        $dataDbId = self::getDefaultDatabaseId();

        // Determine effective default: use provided default, or data db, or first option
        $effectiveDefault = $defaultId;
        if ($effectiveDefault === '' && $dataDbId !== null) {
            $effectiveDefault = $dataDbId;
        }

        // Validate that effectiveDefault exists in available options
        $defaultExists = false;
        $firstOptionId = null;
        foreach ($options as $opt) {
            if ($firstOptionId === null) {
                $firstOptionId = $opt['id'];
            }
            if (strval($effectiveDefault) === strval($opt['id'])) {
                $defaultExists = true;
                break;
            }
        }
        // Fall back to first option if default doesn't exist
        if (!$defaultExists && $firstOptionId !== null) {
            $effectiveDefault = $firstOptionId;
        }

        $html = '<SELECT style="width:150px" id="database" name="' . $name . '"' . $disabledAttr . $onChangeAttr . '>';

        // Render data databases first
        foreach ($options as $opt) {
            if ($opt['isData']) {
                $selected = (strval($effectiveDefault) === strval($opt['id'])) ? ' selected' : '';
                $html .= '<OPTION VALUE="' . $opt['id'] . '"' . $selected . '>' .
                     Server::htmlEncode($opt['title']) . '</OPTION>';
            }
        }

        // Then render other databases
        foreach ($options as $opt) {
            if (!$opt['isData']) {
                $selected = (strval($effectiveDefault) === strval($opt['id'])) ? ' selected' : '';
                $html .= '<OPTION VALUE="' . $opt['id'] . '"' . $selected . '>' .
                     Server::htmlEncode($opt['title']) . '</OPTION>';
            }
        }

        // Repository option last
        if ($includeRepository) {
            $selected = (strval($effectiveDefault) === '999') ? ' selected' : '';
            $html .= '<OPTION VALUE="999"' . $selected . '>CMA definitie</OPTION>';
        }

        $html .= '</SELECT>';

        if (!$enabled) {
            $html .= '<input type="hidden" value="' . htmlspecialchars($defaultId) . '" name="database">';
        }

        return $html;
    }

    /**
     * Render a table selection dropdown from database schema
     *
     * @param string $name Field name
     * @param \PDO $connection Database connection
     * @param bool $enabled Whether the select is enabled
     * @param string $defaultTable Default selected table
     */
    public static function renderTableSelect(string $name, \PDO $connection, bool $enabled, string $defaultTable): void
    {
        $disabledAttr = $enabled ? '' : '_disabled disabled ';
        $fieldName = $enabled ? $name : $name . '_disabled';

        echo '<select style="width:150px" id="table" name="' . $fieldName . '"' . $disabledAttr . '>';

        // Get tables via centralized SchemaHelper (handles filtering of system/hidden tables)
        try {
            $tables = SchemaHelper::getTables($connection);
            foreach ($tables as $table) {
                $tableName = $table['name'];
                $selected = (strtolower($tableName) === strtolower($defaultTable)) ? ' selected' : '';
                echo '<option value="' . htmlspecialchars($tableName) . '"' . $selected . '>' .
                     htmlspecialchars($tableName) . '</option>';
            }
        } catch (\Exception $e) {
            // Silently fail - no tables shown
        }

        echo '</select>';

        if (!$enabled) {
            echo '<input type="hidden" value="' . htmlspecialchars($defaultTable) . '" name="' . $name . '">';
        }
    }

    /**
     * Render a module selection dropdown
     *
     * @param int|string $databaseId Database ID to filter modules
     * @param string $defaultModule Default selected module ID
     * @param \PDO $connection Repository connection (unused - kept for compatibility)
     * @deprecated Modules are no longer used - forms have databaseId directly
     */
    public static function renderModuleSelect($databaseId, string $defaultModule, \PDO $connection): void
    {
        echo '<SELECT style="width:150px" NAME="Module" ID="module">';
        echo '<OPTION VALUE=""' . ($defaultModule === '' ? ' selected' : '') . '></OPTION>';

        // Modules are deprecated - forms now have databaseId directly in JSON definitions
        // If modules.json exists (for legacy support), use it
        if (ConfigLoader::isEnabled() && ConfigLoader::exists('modules')) {
            $modules = ConfigLoader::getModulesForDatabase($databaseId);
            usort($modules, fn($a, $b) => strcasecmp($a['name'] ?? '', $b['name'] ?? ''));

            foreach ($modules as $module) {
                $selected = ($defaultModule === strval($module['id'])) ? ' selected' : '';
                echo '<OPTION VALUE="' . $module['id'] . '"' . $selected . '>' .
                     htmlspecialchars($module['name']) . '</OPTION>';
            }
        }
        // Note: tblModules has been removed - no database fallback

        echo '</SELECT>';
    }

    /**
     * Render a control type selection dropdown
     *
     * @param string $name Field name
     * @param string $controlIdList Comma-separated list of control type IDs
     * @param bool $enabled Whether the select is enabled
     * @param string $default Default selected value
     * @param \PDO $connection Repository connection (only used as fallback if JSON not available)
     */
    public static function renderControlSelect(string $name, string $controlIdList, bool $enabled, string $default, \PDO $connection): void
    {
        $disabledAttr = $enabled ? '' : '_disabled disabled ';
        $fieldName = $enabled ? $name : $name . '_disabled';
        $allowedIds = array_map('intval', explode(',', $controlIdList));

        echo '<SELECT style="width:100px" ID="' . $name . '" NAME="' . $fieldName . '"' . $disabledAttr . '>';

        // Try ConfigLoader first (JSON config)
        if (ConfigLoader::isEnabled() && ConfigLoader::exists('control-types')) {
            $controlTypes = ConfigLoader::getControlTypes();

            // Filter to allowed IDs and sort by description
            $filtered = array_filter($controlTypes, fn($ct) => in_array($ct['id'], $allowedIds));
            usort($filtered, fn($a, $b) => strcasecmp($a['description'] ?? '', $b['description'] ?? ''));

            foreach ($filtered as $ct) {
                $selected = (strtolower($default) === strtolower(strval($ct['id']))) ? ' selected' : '';
                // Remove first 3 characters from description (e.g., "01_" prefix)
                $label = preg_match('/^\d+_(.+)$/', $ct['description'], $m) ? $m[1] : $ct['description'];
                echo '<OPTION VALUE="' . $ct['id'] . '"' . $selected . '>' .
                     htmlspecialchars($label) . '</OPTION>';
            }
        } else {
            // Fallback to database query
            $sql = 'SELECT ID, Description FROM tblControlTypes WHERE ID IN (' . $controlIdList . ') ORDER BY Description';
            $rs = Database::openRS($sql, $connection, adOpenForwardOnly);

            if ($rs !== null) {
                while (($row = $rs->fetch(PDO::FETCH_ASSOC)) !== false) {
                    $selected = (strtolower($default) === strtolower(strval($row['ID']))) ? ' selected' : '';
                    // Remove first 3 characters from description (e.g., "01_" prefix)
                    $label = substr($row['Description'], 3);
                    echo '<OPTION VALUE="' . $row['ID'] . '"' . $selected . '>' .
                         htmlspecialchars($label) . '</OPTION>';
                }
            }
        }

        echo '</SELECT>';

        if (!$enabled) {
            echo '<input type="hidden" value="' . htmlspecialchars($default) . '" name="' . $name . '">';
        }
    }

    /**
     * Render a field type selection dropdown (MS Access/database field types)
     *
     * @param string $name Field name
     * @param string $default Default selected value
     */
    public static function renderFieldTypeSelect(string $name, string $default): void
    {
        $types = [
            ADINTEGER => 'Nummer (integer)',
            ADDATE => 'Datum',
            adVarWChar => 'String',
            ADBOOLEAN => 'Ja/Nee veld',
            adLongVarWChar => 'Memo',
        ];

        echo '<SELECT style="width:150px" NAME="' . htmlspecialchars($name) . '">';

        foreach ($types as $value => $label) {
            $selected = (strval($value) === $default) ? ' SELECTED' : '';
            echo '<OPTION VALUE="' . $value . '"' . $selected . '>' . $label . '</OPTION>';
        }

        echo '</SELECT>';
    }

    /**
     * Get connection string by database ID from repository
     *
     * @param int $databaseId Database ID
     * @return string Connection string
     */
    /**
     * Static cache for connection strings by database ID (faster than Application)
     * @var array<int, string>
     */
    private static array $connStringByIdCache = [];

    /**
     * Cache group for connection strings
     */
    private const CACHE_GROUP_CONNSTR = 'connstrings';

    public static function getConnectionStringById($databaseId): string
    {
        // Default to data connection if ID is empty/0
        $databaseId = intval($databaseId);
        if ($databaseId <= 0) {
            return Application::get('data_conn', 'data');
        }

        // Level 1: Request-level memory cache (fastest)
        if (isset(self::$connStringByIdCache[$databaseId])) {
            return self::$connStringByIdCache[$databaseId];
        }

        // Level 2: Persistent cache (file-based, survives across requests)
        $cacheKey = 'connstr_' . $databaseId;
        $cached = Cache::get($cacheKey);
        if ($cached !== null && $cached !== '') {
            self::$connStringByIdCache[$databaseId] = $cached;
            return $cached;
        }

        $connString = null;

        // Load from JSON config (databases.json) - this is the only source
        if (ConfigLoader::exists('databases')) {
            $connString = ConfigLoader::getConnectionString($databaseId);
        }

        // Empty connection string means use default data connection
        if ($connString === '' || $connString === null) {
            $connString = Application::get('data_conn', 'data');
        }

        // Store in persistent cache (24 hour TTL - connection strings rarely change)
        Cache::set($cacheKey, $connString, 86400);

        // Store in memory cache for this request
        self::$connStringByIdCache[$databaseId] = $connString;

        return $connString;
    }

    /**
     * In-request cache for resolved connection strings
     * @var array<string, string>
     */
    private static array $connectionStringCache = [];

    /**
     * Cache mapping databaseId to connection name (data/rep/custom)
     * @var array<int, string>
     */
    private static array $dbIdToConnName = [];

    /**
     * Get the connection name for a database ID
     * Returns the cached connection name after openConnectionById has been called,
     * or determines it from the database config.
     *
     * @param int $databaseId Database ID
     * @return string|null Connection name or null if not found
     */
    public static function getConnectionNameById(int $databaseId): ?string
    {
        // Return cached value if available
        if (isset(self::$dbIdToConnName[$databaseId])) {
            return self::$dbIdToConnName[$databaseId];
        }

        // Try to get from config
        $dbConfig = ConfigLoader::getDatabase($databaseId);
        if ($dbConfig && !empty($dbConfig['name'])) {
            return $dbConfig['name'];
        }

        return null;
    }

    /**
     * Resolve path placeholders in connection string
     * Replaces [path] with actual server path based on base_path setting
     *
     * @param string $connectionString Raw connection string with placeholders
     * @return string Fully resolved connection string
     */
    public static function resolveConnectionString(string $connectionString): string
    {
        // Check cache first
        if (isset(self::$connectionStringCache[$connectionString])) {
            return self::$connectionStringCache[$connectionString];
        }

        $result = $connectionString;
        $startPos = stripos($connectionString, '[');

        if ($startPos !== false) {
            $endPos = stripos($connectionString, ']');
            if ($endPos !== false) {
                $path = substr($connectionString, $startPos + 1, $endPos - $startPos - 1);
                $fullPath = Server::mapPath(Application::get('base_path', '') . $path);
                $result = substr($connectionString, 0, $startPos) . $fullPath . substr($connectionString, $endPos + 1);
            }
        }

        // Store in cache
        self::$connectionStringCache[$connectionString] = $result;

        return $result;
    }

    /**
     * Debug info from last openConnectionById call
     * @var array
     */
    public static array $lastConnectionDebug = [];

    /**
     * Open a database connection by database ID
     * Sets global $conn variable
     *
     * @param int $databaseId Database ID
     */
    public static function openConnectionById($databaseId): void
    {
        $databaseId = (int)$databaseId;
        self::$lastConnectionDebug = ['databaseId' => $databaseId];

        // For databaseId 0 or empty, use the pre-initialized 'data' connection
        if ($databaseId <= 0) {
            self::$lastConnectionDebug['result'] = 'data (id<=0)';
            $conn = Database::getConnection('data');
            return;
        }

        // Check if we've already determined the connection name for this databaseId
        if (isset(self::$dbIdToConnName[$databaseId])) {
            self::$lastConnectionDebug['result'] = 'cached: ' . self::$dbIdToConnName[$databaseId];
            $conn = Database::getConnection(self::$dbIdToConnName[$databaseId]);
            return;
        }

        $connectionString = self::getResolvedConnectionString($databaseId);
        self::$lastConnectionDebug['connStr'] = $connectionString;

        // Check if this connection string matches pre-initialized connections
        // Compare the resolved paths to determine if we can use pooled connections
        $dataConn = Application::get('data_conn', '');
        $dataConnResolved = $dataConn ? self::resolveConnectionString($dataConn) : '';
        self::$lastConnectionDebug['dataConnResolved'] = $dataConnResolved;

        if ($dataConnResolved !== '' && strcasecmp((string)$connectionString, (string)$dataConnResolved) === 0) {
            self::$dbIdToConnName[$databaseId] = 'data';
            self::$lastConnectionDebug['result'] = 'matched data';
            $conn = Database::getConnection('data');
            return;
        }

        $repConn = Application::get('conn_rep', '');
        $repConnResolved = $repConn ? self::resolveConnectionString($repConn) : '';
        self::$lastConnectionDebug['repConnResolved'] = $repConnResolved;

        if ($repConnResolved !== '' && strcasecmp((string)$connectionString, (string)$repConnResolved) === 0) {
            self::$dbIdToConnName[$databaseId] = 'rep';
            self::$lastConnectionDebug['result'] = 'matched rep';
            $conn = Database::getConnection('rep');
            return;
        }

        // For other connections, use the connection string directly
        // (this will still create new connections per request for non-standard DBs)
        self::$dbIdToConnName[$databaseId] = $connectionString;
        self::$lastConnectionDebug['result'] = 'new connection';
        $conn = Database::getConnection($connectionString);
    }

    /**
     * Get fully resolved connection string by database ID
     * Combines getConnectionStringById and resolveConnectionString
     *
     * @param int $databaseId Database ID
     * @return string Fully resolved connection string
     */
    public static function getResolvedConnectionString(int $databaseId): string
    {
        return self::resolveConnectionString(self::getConnectionStringById($databaseId));
    }

    /**
     * Find a form ID that edits records for a given source table
     *
     * Used for the plus icon next to comboboxes to allow adding related records.
     *
     * @param string $sourceTable Source table name (e.g., 'tblCategories')
     * @param int|null $currentFormId Current form ID to exclude from results
     * @param int|null $userId User ID for access rights check (uses cookie if null)
     * @return int|null Form ID or null if no suitable form found
     */
    public static function getFormIdBySourceTable(string $sourceTable, ?int $currentFormId = null, ?int $userId = null): ?int
    {
        if (empty($sourceTable)) {
            return null;
        }

        // Use JSON-based table-to-form mapping instead of repository query
        $tableToFormMap = JsonFormLoader::getTableToFormMap();
        $tableKey = strtoupper($sourceTable);

        if (!isset($tableToFormMap[$tableKey])) {
            return null;
        }

        $formId = $tableToFormMap[$tableKey];

        // Don't return the same form as the current form
        if ($currentFormId !== null && $formId === $currentFormId) {
            return null;
        }

        // Check user access rights
        if ($userId === null) {
            $userId = (int)\App\Library\Cookie::get(SecurityHelper::COOKIE_USERID, '0');
        }

        if (!SecurityHelper::checkFormRights($userId, $formId)) {
            return null;
        }

        return $formId;
    }

    /**
     * Get cached record count for a table
     *
     * Used to determine if a form should force filtering (too many records).
     * Based on list.asp logic for LIST_LIMIT check.
     *
     * @param string $tableName Table name
     * @param \PDO|null $connection Database connection (uses global $conn if null)
     * @return int Record count
     */
    public static function getTableRecordCountCached(string $tableName, ?\PDO $connection = null): int
    {
        if ($tableName === '') {
            return 0;
        }

        // Use provided connection or global
        if ($connection === null) {
            global $conn;
            $connection = $conn;
        }

        if ($connection === null) {
            return 0;
        }

        // Cache key for this table count
        $cacheKey = 'CMA_table_count_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $tableName);

        $sql = 'SELECT COUNT(*) AS cnt FROM ' . $tableName;
        $result = Cache::retrieve($cacheKey, $connection, $sql);

        if (Arr::isArray($result) && isset($result[0][0])) {
            return (int)$result[0][0];
        }

        // Fallback: direct query
        try {
            $count = Database::getFieldValue($connection, $sql, 'cnt');
            return (int)$count;
        } catch (\Exception $e) {
            return 0;
        }
    }
}
