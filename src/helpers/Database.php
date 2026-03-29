<?php

namespace App\Library;

use PDO;
use PDOException;
use PDOStatement;

/**
 * Database Helper Class
 *
 * Provides centralized database connection management with connection pooling.
 * Matches ASP's ADODB behavior with singleton connections for performance.
 *
 * Supports two connections:
 * - Primary connection (conn_data) for read/write operations
 * - Repository connection (conn_rep) for read-only operations
 *
 * Usage:
 *   $conn = Database::getConnection();
 *   $row = Database::executeSingleRecord($sql, $params);
 *   $rows = Database::executeQuery($sql, $params);
 *   $affected = Database::execute($sql, $params);
 *   $id = Database::getLastInsertId();
 *
 * Transactions:
 *   Database::beginTransaction();
 *   Database::execute($sql1, $params1);
 *   Database::execute($sql2, $params2);
 *   Database::commit(); // or Database::rollback();
 *
 * SQL Debugging:
 *   Enable SQL debug logging by setting Application variable:
 *     Application::set('sql_debug', true);
 *
 *   Or via query parameter in your page (e.g., list.php):
 *     if (Request::query('sqldebug', '') !== '') {
 *         Application::set('sql_debug', true);
 *     }
 *
 *   Then access: http://example.com/page.php?sqldebug=Y
 *
 *   Debug log will be written to: sql_debug.log
 *   Log includes:
 *   - SQL before/after processing by SQL::processSQL()
 *   - Database driver type (odbc, sqlsrv, mysql, etc.)
 *   - Execution method used (query() vs prepare())
 *   - Full SQL queries (first 500 chars)
 *
 * ODBC/Access Database Handling:
 *   Two modes are available for ODBC connections (Microsoft Access):
 *
 *   - 'native' (default): Uses native odbc_* functions. Better for MS Access with JOINs.
 *   - 'pdo': Uses PDO ODBC driver. May have issues with complex Access queries.
 *
 *   Set mode via:
 *     Database::setOdbcMode('pdo');     // Switch to PDO
 *     Database::setOdbcMode('native');  // Switch back to native ODBC (default)
 *
 *   Or via Application setting:
 *     Application::set('odbc_mode', 'pdo');
 */
class Database
{
    /**
     * Primary database connection (singleton)
     * @var PDO|null
     */
    private static ?PDO $connData = null;

    /**
     * Replication/read-only database connection (singleton)
     * @var PDO|null
     */
    private static ?PDO $connRep = null;

    /**
     * Named connection pool (key = connection name, value = PDO object)
     * @var array<string, PDO>
     */
    private static array $namedConnections = [];

    /**
     * Last database error message
     * @var string
     */
    private static string $lastError = '';

    /**
     * Last SQL query that caused an error
     * @var string
     */
    private static string $lastSQL = '';

    /**
     * Connection timeout in seconds
     */
    private const CONN_TIMEOUT = 10;

    /**
     * Command timeout in seconds
     */
    private const CMD_TIMEOUT = 1000;

    /**
     * Enable connection pooling (matches ASP behavior)
     */
    private static bool $enablePooling = true;

    /**
     * Enable SQL debug logging
     * Set via Application::set('sql_debug', true)
     */
    private static ?bool $sqlDebugEnabled = null;

    /**
     * Enable SQL query logging to file for performance analysis.
     * Set via SQL_LOG_ENABLED=true in .env
     * Logs: timestamp, duration_ms, connection, page, full SQL
     * Output: SQL_LOG_FILE (default: sql_queries.log in site root)
     */
    private static ?bool $sqlLogEnabled = null;
    private static ?string $sqlLogFile = null;

    /**
     * ODBC execution mode: 'pdo' or 'native'
     * - 'native': Use native odbc_* functions (default, better for MS Access with complex JOINs)
     * - 'pdo': Use PDO ODBC driver (may have issues with nested JOINs in Access)
     *
     * Set via Database::setOdbcMode('pdo') or Application::set('odbc_mode', 'pdo')
     */
    private static string $odbcMode = 'native';

    /**
     * Native ODBC connection pool (key = connection name, value = odbc resource)
     * Caches native ODBC connections to avoid repeated connection overhead.
     * @var array<string, resource>
     */
    private static array $nativeOdbcConnections = [];

    /**
     * Initialize named connection pool
     *
     * Called from _bootstrap.php to set up named connections.
     * Maps connection names to Application variable names.
     *
     * Usage:
     *   Database::initConnections([
     *       'data' => Application::get('data_conn', ''),
     *       'rep' => Application::get('conn_rep', ''),
     *       'users' => Application::get('conn_users', '')
     *   ]);
     *
     * @param array<string, string> $config Mapping of connection name => DSN string
     * @return void
     */
    public static function initConnections(array $config): void
    {
        foreach ($config as $name => $dsn) {
            if (empty($dsn)) {
                continue; // Skip empty DSNs
            }

            $driver = \App\Library\Application::get('pdo_driver', 'auto');

            // Extract driver from DSN if not explicitly set
            if ($driver === 'auto') {
                if (preg_match('/^(\w+):/', $dsn, $matches)) {
                    $driver = $matches[1];
                }
            }

            // ODBC drivers require backslashes on Windows
            if ($driver === 'odbc' && DIRECTORY_SEPARATOR === '\\') {
                $dsn = str_replace('/', '\\', $dsn);
            }

            try {
                // Use persistent connections to avoid ODBC connection overhead on each request
                $conn = new PDO($dsn, null, null, [
                    PDO::ATTR_PERSISTENT => true
                ]);
                $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                $conn->setAttribute(PDO::ATTR_TIMEOUT, self::CONN_TIMEOUT);

                // Set statement timeout (MySQL/MSSQL specific)
                $actualDriver = $conn->getAttribute(PDO::ATTR_DRIVER_NAME);

                // For ODBC (Access), use emulated prepares to avoid parameter parsing issues
                // For other drivers, use native prepares for better performance
                if ($actualDriver === 'odbc') {
                    // Try to set emulated prepares - ODBC may not support this
                    try {
                        $conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
                    } catch (\PDOException $e) {
                        // ODBC driver doesn't support this attribute, continue anyway
                    }
                } else {
                    $conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
                }
                if ($actualDriver === 'mysql') {
                    $conn->exec('SET SESSION max_execution_time = ' . (self::CMD_TIMEOUT * 1000));
                } elseif ($actualDriver === 'sqlsrv') {
                    $conn->setAttribute(PDO::SQLSRV_ATTR_QUERY_TIMEOUT, self::CMD_TIMEOUT);
                }

                // Store in named pool
                self::$namedConnections[$name] = $conn;
            } catch (PDOException $e) {
                self::$lastError = $e->getMessage();

                // Enhance error message with DSN details
                $detectedDriver = preg_match('/^(\w+):/', $dsn, $m) ? $m[1] : 'unknown';
                throw new PDOException(
                    "Named connection '$name' failed.\n" .
                    "Driver: $detectedDriver\n" .
                    "DSN: $dsn\n" .
                    "Error: " . $e->getMessage(),
                    (int)$e->getCode(),
                    $e
                );
            }
        }
    }

    /**
     * Connection name aliases (for backwards compatibility)
     * Currently no aliases - 'data', 'rep', and 'users' are all separate databases
     */
    private static array $connectionAliases = [];

    /**
     * Connection name to Application variable name mapping
     * Also maps full config key names (e.g., 'conn_users') to themselves
     */
    private static array $connectionConfigKeys = [
        'data' => 'conn_data',
        'rep' => 'conn_rep',
        'users' => 'conn_users',
        'conn_data' => 'conn_data',
        'conn_rep' => 'conn_rep',
        'conn_users' => 'conn_users',
    ];

    /**
     * Get a database connection by name
     *
     * This is the single unified method for getting database connections.
     * All connection logic is centralized here.
     *
     * Connection names:
     * - 'data' (default): Primary database for read/write operations
     * - 'rep': Repository database for CMA metadata
     * - 'users': Users database (tblUsers, tblGroups, tblGroupMembers, etc.)
     *
     * Also accepts:
     * - PDO object: Pass-through, returns as-is
     * - ODBC resource: Pass-through, returns as-is
     * - null/empty: Returns 'data' connection
     *
     * @param string|PDO|resource|null $name Connection name or existing connection
     * @return PDO|resource Database connection
     * @throws PDOException If connection fails
     */
    public static function getConnection($name = 'data')
    {
        // Pass-through for existing connections
        if ($name instanceof PDO || is_resource($name)) {
            return $name;
        }

        // Default to 'data' if empty
        if (empty($name)) {
            $name = 'data';
        }

        // Detect and convert OLEDB connection strings to ODBC
        if (self::isOleDbConnectionString($name)) {
            return self::createConnectionFromOleDb($name);
        }

        // Normalize connection name
        $name = strtolower((string)$name);

        // Resolve aliases
        if (isset(self::$connectionAliases[$name])) {
            $name = self::$connectionAliases[$name];
        }

        // Check connection pool first
        if (isset(self::$namedConnections[$name]) && self::$enablePooling) {
            return self::$namedConnections[$name];
        }

        // Legacy compatibility: check old static properties
        if ($name === 'data' && self::$connData !== null && self::$enablePooling) {
            return self::$connData;
        }
        if ($name === 'rep' && self::$connRep !== null && self::$enablePooling) {
            return self::$connRep;
        }

        // Get DSN from Application config
        $configKey = self::$connectionConfigKeys[$name] ?? $name;
        $dsn = \App\Library\Application::get($configKey, '');

        // If DSN is empty, try to build from separate PATH/DRIVER env vars
        // This handles the CONN_USERS_PATH + CONN_USERS_DRIVER pattern
        if (empty($dsn)) {
            $dsn = self::buildDsnFromEnv($configKey);
        }

        // Fallback: 'rep' falls back to 'data' if not configured
        if (empty($dsn) && $name === 'rep') {
            return self::getConnection('data');
        }

        if (empty($dsn)) {
            throw new PDOException("Database connection '$name' not configured (missing '$configKey' or '{$configKey}_path' in Application settings)");
        }

        // Create and configure the connection
        $conn = self::createPDOConnection($dsn, $name);

        // Store in pool
        self::$namedConnections[$name] = $conn;

        // Also update legacy static properties for backwards compatibility
        if ($name === 'data') {
            self::$connData = $conn;
        } elseif ($name === 'rep') {
            self::$connRep = $conn;
        }

        return $conn;
    }

    /**
     * Build DSN from separate PATH and DRIVER environment variables
     *
     * Handles patterns like:
     *   CONN_USERS_PATH=/db/cmausers.mdb
     *   CONN_USERS_DRIVER=Microsoft Access Driver (*.mdb, *.accdb)
     *
     * @param string $configKey Base config key (e.g., 'conn_users')
     * @return string DSN string or empty if not configured
     */
    private static function buildDsnFromEnv(string $configKey): string
    {
        // Try uppercase version (CONN_USERS_PATH) as environment variables are typically uppercase
        $envPrefix = strtoupper($configKey);
        $path = \App\Library\Application::get($envPrefix . '_path', '');

        if (empty($path)) {
            return '';
        }

        $driver = \App\Library\Application::get(
            $envPrefix . '_driver',
            'Microsoft Access Driver (*.mdb, *.accdb)'
        );

        // Resolve relative path to absolute path
        $documentRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
        if (!empty($documentRoot) && strpos($path, '/') === 0 && !file_exists($path)) {
            $resolvedPath = rtrim($documentRoot, '/\\') . $path;
            if (file_exists($resolvedPath)) {
                $path = $resolvedPath;
            }
        }

        // Build ODBC DSN
        return "odbc:Driver={{$driver}};DBQ={$path}";
    }

    /**
     * Create and configure a PDO connection
     *
     * @param string $dsn Database connection string
     * @param string $name Connection name (for error messages)
     * @return PDO Configured PDO connection
     * @throws PDOException If connection fails
     */
    private static function createPDOConnection(string $dsn, string $name): PDO
    {
        // Detect driver from DSN
        $driver = 'unknown';
        if (preg_match('/^(\w+):/', $dsn, $matches)) {
            $driver = $matches[1];
        }

        // Fix #336: ODBC drivers require backslashes on Windows
        if ($driver === 'odbc' && DIRECTORY_SEPARATOR === '\\') {
            $dsn = str_replace('/', '\\', $dsn);
        }

        try {
            // Use persistent connections to avoid ODBC connection overhead on each request
            $conn = new PDO($dsn, null, null, [
                PDO::ATTR_PERSISTENT => true
            ]);
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $conn->setAttribute(PDO::ATTR_TIMEOUT, self::CONN_TIMEOUT);

            // Get actual driver (may differ from DSN prefix)
            $actualDriver = $conn->getAttribute(PDO::ATTR_DRIVER_NAME);

            // Configure prepared statements based on driver
            if ($actualDriver === 'odbc') {
                try {
                    $conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
                } catch (\PDOException $e) {
                    // ODBC may not support this attribute
                }
            } else {
                $conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
            }

            // Configure timeouts based on driver
            if ($actualDriver === 'mysql') {
                $conn->exec('SET SESSION max_execution_time = ' . (self::CMD_TIMEOUT * 1000));
            } elseif ($actualDriver === 'sqlsrv') {
                $conn->setAttribute(PDO::SQLSRV_ATTR_QUERY_TIMEOUT, self::CMD_TIMEOUT);
            }

            return $conn;

        } catch (PDOException $e) {
            self::$lastError = $e->getMessage();
            throw new PDOException(
                "Database connection '$name' failed.\n" .
                "Driver: $driver\n" .
                "DSN: $dsn\n" .
                "Error: " . $e->getMessage(),
                (int)$e->getCode(),
                $e
            );
        }
    }

    /**
     * Get repository/read-only database connection
     *
     * @deprecated Use getConnection('rep') instead
     * @return PDO The repository database connection
     */
    public static function getRepConnection(): PDO
    {
        return self::getConnection('rep');
    }

    /**
     * Get a named database connection
     *
     * @deprecated Use getConnection($name) instead
     * @param string|PDO|resource|null $connection Connection name or existing connection
     * @return PDO|resource Database connection
     */
    public static function getNamedConnection($connection)
    {
        return self::getConnection($connection);
    }

    /**
     * Execute query and return single record
     *
     * Equivalent to ASP's lib_executeSingleRecordSQL for SELECT queries
     *
     * @param string $sql SQL query
     * @param array $params Query parameters (for prepared statements)
     * @param bool $useRepConnection Use replication connection (default: false)
     * @return array|null Associative array of column => value, or null if no record
     */
    public static function executeSingleRecord(string $sql, array $params = [], bool $useRepConnection = false): ?array
    {
        try {
            $conn = $useRepConnection ? self::getRepConnection() : self::getConnection();
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetch();

            return $result !== false ? self::convertRowEncoding($conn, $result) : null;
        } catch (PDOException $e) {
            self::$lastError = $e->getMessage();
            self::logError($sql, $params, $e);
            return null;
        }
    }

    /**
     * Execute query and return all records
     *
     * @param string $sql SQL query
     * @param array $params Query parameters (for prepared statements)
     * @param bool $useRepConnection Use replication connection (default: false)
     * @return array Array of associative arrays (rows)
     */
    public static function executeQuery(string $sql, array $params = [], bool $useRepConnection = false): array
    {
        try {
            $conn = $useRepConnection ? self::getRepConnection() : self::getConnection();
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            return self::convertRowsEncoding($conn, $stmt->fetchAll());
        } catch (PDOException $e) {
            self::$lastError = $e->getMessage();
            self::logError($sql, $params, $e);
            return [];
        }
    }

    /**
     * Log SQL debug information
     *
     * @param string $message Debug message
     * @param string $sql Optional SQL query to log
     * @return void
     */
    private static function debugSQL(string $message, string $sql = ''): void
    {
        // Check if debug is enabled (cached for performance)
        if (self::$sqlDebugEnabled === null) {
            self::$sqlDebugEnabled = Application::get('sql_debug', false);
        }

        if (!self::$sqlDebugEnabled) {
            return;
        }

        // Initialize log file if not already done
        static $logInitialized = false;
        if (!$logInitialized) {
            try {
                Log::init('logs/sql_debug.log');
                $logInitialized = true;
            } catch (\Exception $e) {
                // If log init fails, write directly to file
                error_log("SQL Debug (log init failed): " . $e->getMessage());
            }
        }

        // Write log entry
        try {
            Log::writeLine(date('Y-m-d H:i:s') . ' - ' . $message);
            if ($sql !== '') {
                Log::writeLine('SQL: ' . $sql);  // Log full SQL, not truncated
            }
        } catch (\Exception $e) {
            // Fallback to direct file write
            $logPath = __DIR__ . '/../../logs/sql_debug.log';
            @mkdir(dirname($logPath), 0755, true);  // Create logs directory if needed
            file_put_contents($logPath, date('Y-m-d H:i:s') . ' - ' . $message . "\n" . ($sql ? 'SQL: ' . $sql . "\n" : ''), FILE_APPEND);
        }
    }

    /**
     * Log SQL query to file for performance analysis.
     * Enable via SQL_LOG_ENABLED=true in .env
     * Output file: SQL_LOG_FILE (default: sql_queries.log in site root)
     * Format: CSV with timestamp, duration_ms, connection, page, SQL
     */
    private static function logSQL(?float $startTime, string $sql, $connection = null, bool $isError = false): void
    {
        // Lazy-init from .env on first call
        if (self::$sqlLogEnabled === null) {
            $env = $_ENV['SQL_LOG_ENABLED'] ?? getenv('SQL_LOG_ENABLED');
            self::$sqlLogEnabled = $env !== false && $env !== null && filter_var($env, FILTER_VALIDATE_BOOLEAN);
            if (self::$sqlLogEnabled) {
                $file = $_ENV['SQL_LOG_FILE'] ?? getenv('SQL_LOG_FILE');
                if ($file && $file !== '') {
                    self::$sqlLogFile = $file;
                } else {
                    self::$sqlLogFile = rtrim($_SERVER['DOCUMENT_ROOT'] ?? __DIR__ . '/../..', '/') . '/sql_queries.log';
                }
            }
        }

        if (!self::$sqlLogEnabled || $startTime === null) {
            return;
        }

        $durationMs = round((microtime(true) - $startTime) * 1000, 1);

        // Determine connection name
        $connName = 'data';
        if (is_string($connection) && !empty($connection)) {
            $connName = $connection;
        }

        // Normalize SQL for logging (collapse whitespace, trim)
        $logSql = trim(preg_replace('/\s+/', ' ', $sql));

        // Build CSV line: timestamp;duration_ms;connection;page;error;sql
        $page = $_SERVER['REQUEST_URI'] ?? 'CLI';
        $line = implode("\t", [
            date('Y-m-d H:i:s'),
            $durationMs,
            $connName,
            strtok($page, '?'),  // Page without query string
            $isError ? 'ERROR' : '',
            $logSql
        ]) . "\n";

        @file_put_contents(self::$sqlLogFile, $line, FILE_APPEND | LOCK_EX);
    }

    /**
     * Convert Windows-1252 encoded strings to UTF-8 in fetch results
     *
     * Access ODBC returns Windows-1252 encoded strings. This method converts
     * them to UTF-8 for consistent handling throughout the application.
     *
     * @param PDO $conn PDO connection to check driver type
     * @param array|false $row Single row or false
     * @return array|false Converted row or false
     */
    private static function convertRowEncoding(PDO $conn, $row)
    {
        if ($row === false || !is_array($row)) {
            return $row;
        }
        if ($conn->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'odbc') {
            return $row;
        }
        foreach ($row as $key => &$value) {
            if (is_string($value) && $value !== '' && !mb_check_encoding($value, 'UTF-8')) {
                $value = mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
            }
        }
        unset($value);
        return $row;
    }

    /**
     * Convert Windows-1252 encoded strings to UTF-8 in multiple rows
     *
     * @param PDO $conn PDO connection to check driver type
     * @param array $rows Array of rows
     * @return array Converted rows
     */
    private static function convertRowsEncoding(PDO $conn, array $rows): array
    {
        if (empty($rows) || $conn->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'odbc') {
            return $rows;
        }
        foreach ($rows as &$row) {
            foreach ($row as $key => &$value) {
                if (is_string($value) && $value !== '' && !mb_check_encoding($value, 'UTF-8')) {
                    $value = mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
                }
            }
            unset($value);
        }
        unset($row);
        return $rows;
    }

    /**
     * Set ODBC execution mode
     *
     * Controls how ODBC (Access) queries are executed:
     * - 'pdo': Use PDO ODBC driver (default, recommended)
     * - 'native': Use native odbc_* functions (for complex queries)
     *
     * Usage:
     *   Database::setOdbcMode('native');  // Switch to native ODBC
     *   Database::setOdbcMode('pdo');     // Switch back to PDO (default)
     *
     * Can also be set via Application::set('odbc_mode', 'native')
     *
     * @param string $mode 'pdo' or 'native'
     * @return void
     */
    public static function setOdbcMode(string $mode): void
    {
        if (!in_array($mode, ['pdo', 'native'], true)) {
            throw new \InvalidArgumentException("Invalid ODBC mode: $mode. Use 'pdo' or 'native'");
        }
        self::$odbcMode = $mode;
    }

    /**
     * Get current ODBC execution mode
     *
     * @return string Current mode: 'pdo' or 'native'
     */
    public static function getOdbcMode(): string
    {
        // Check Application setting as override
        $appMode = Application::get('odbc_mode', '');
        if ($appMode !== '' && in_array($appMode, ['pdo', 'native'], true)) {
            return $appMode;
        }
        return self::$odbcMode;
    }

    /**
     * Open a recordset for reading
     *
     * Equivalent to ASP's lib_openRS() function
     * Returns a RecordSet object populated with query results.
     *
     * ADO Cursor Types:
     * - 0 (adOpenForwardOnly): Forward-only, non-scrollable (default)
     * - 1 (adOpenKeyset): Scrollable, reflects changes
     * - 2 (adOpenDynamic): Scrollable, fully dynamic
     * - 3 (adOpenStatic): Scrollable, static snapshot
     *
     * Usage:
     *   $rs = Database::openRS('SELECT * FROM users', 'data');
     *   while (!$rs->eof()) {
     *       echo $rs->fields['name'];
     *       $rs->moveNext();
     *   }
     *
     * @param string $sql SQL query
     * @param mixed $connection Connection string or PDO object (optional)
     * @param int|null $cursorType Cursor type constant (null = forward-only)
     * @return RecordSet|null RecordSet on success, null on error
     */
    public static function openRS(string $sql, $connection = null, ?int $cursorType = null): ?RecordSet
    {
        $sqlLogStart = self::$sqlLogEnabled ? microtime(true) : null;
        $sqlSnippet = substr(preg_replace('/\s+/', ' ', $sql), 0, 100);
        try {
            // Handle native ODBC connection objects specially
            if (is_object($connection) && get_class($connection) === 'Odbc\Connection') {
                $result = self::openRSNativeODBC($sql, $connection, $cursorType);
                self::logSQL($sqlLogStart, $sql, $connection);
                return $result;
            }

            // Use unified getConnection() for everything else
            // This handles: null, '', 'data', 'rep', 'users', PDO objects, etc.
            $conn = self::getConnection($connection);

            // Track the connection name (for native ODBC fallback)
            $connectionName = 'data';
            if (is_string($connection) && !empty($connection)) {
                $connectionName = strtolower($connection);
            } elseif ($connection instanceof PDO) {
                // Detect which pooled connection this PDO object is
                if ($connection === self::$connRep || (isset(self::$namedConnections['rep']) && $connection === self::$namedConnections['rep'])) {
                    $connectionName = 'rep';
                } elseif (isset(self::$namedConnections['users']) && $connection === self::$namedConnections['users']) {
                    $connectionName = 'users';
                }
                // Check other named connections
                foreach (self::$namedConnections as $name => $pooledConn) {
                    if ($connection === $pooledConn) {
                        $connectionName = $name;
                        break;
                    }
                }
            }

            // Process SQL (handle any VBScript-style SQL modifications)
            $sqlBefore = $sql;
            $sql = SQL::processSQL($conn, $sql);

            // Debug logging
            if ($sqlBefore !== $sql) {
                self::debugSQL("SQL PROCESSED", "Before: " . $sqlBefore . "\n\nAfter: " . $sql);
            }

            // Execute query
            $driver = $conn->getAttribute(\PDO::ATTR_DRIVER_NAME);
            self::debugSQL("Executing query with driver: " . $driver, $sql);

            // Determine if scrollable cursor
            // Forward-only (0 or null) = non-scrollable
            // All other types (1=Keyset, 2=Dynamic, 3=Static) = scrollable
            $scrollable = ($cursorType !== null && $cursorType !== 0);

            // For ODBC (Access), check the odbc_mode setting to determine execution method
            // - 'pdo' (default): Use PDO ODBC driver
            // - 'native': Use native odbc_* functions (for complex queries that fail with PDO)
            $odbcMode = self::getOdbcMode();
            if ($driver === 'odbc' && $odbcMode === 'native' && extension_loaded('odbc')) {
                self::debugSQL("Using NATIVE ODBC mode (odbc_mode=$odbcMode)");

                // Get DSN for the connection
                $configKey = self::$connectionConfigKeys[$connectionName] ?? $connectionName;
                $dsn = \App\Library\Application::get($configKey, '');

                // If DSN is empty, try to build from separate PATH/DRIVER env vars
                if (empty($dsn)) {
                    $dsn = self::buildDsnFromEnv($configKey);
                }

                self::debugSQL("Using connection: $connectionName (configKey: $configKey)");

                // Remove "odbc:" prefix if present
                $odbcConnStr = preg_replace('/^odbc:/', '', $dsn);

                self::debugSQL("Native ODBC connection string: " . substr($odbcConnStr, 0, 100));

                // Check native ODBC connection pool first
                $odbcConn = null;
                if (isset(self::$nativeOdbcConnections[$connectionName]) && is_resource(self::$nativeOdbcConnections[$connectionName])) {
                    $odbcConn = self::$nativeOdbcConnections[$connectionName];
                    self::debugSQL("Reusing pooled native ODBC connection for: $connectionName");
                } else {
                    // Create native ODBC connection using the full connection string
                    $odbcConn = @odbc_connect($odbcConnStr, '', '', SQL_CUR_USE_ODBC);

                    if (!$odbcConn) {
                        // Try alternate method - use PDO's connection string directly
                        $odbcConn = @odbc_connect($dsn, '', '');
                    }

                    // Pool the connection for reuse
                    if ($odbcConn) {
                        self::$nativeOdbcConnections[$connectionName] = $odbcConn;
                        self::debugSQL("Created and pooled native ODBC connection for: $connectionName");
                    }
                }

                if ($odbcConn) {
                    self::debugSQL("Native ODBC connection ready, executing query");

                    // Execute using native ODBC
                    $odbcResult = @odbc_exec($odbcConn, $sql);

                    if ($odbcResult) {
                        self::debugSQL("Native ODBC execution successful!");

                        // Fetch all data into an array
                        $data = [];
                        $numCols = odbc_num_fields($odbcResult);

                        // Try to get field names - if this fails due to ambiguous columns,
                        // we'll fall back to numeric indices
                        $fieldNames = [];
                        $useNumericIndices = false;

                        for ($i = 1; $i <= $numCols; $i++) {
                            try {
                                $fieldName = @odbc_field_name($odbcResult, $i);
                                if ($fieldName === false) {
                                    self::debugSQL("Failed to get field name for column $i, using numeric indices");
                                    $useNumericIndices = true;
                                    break;
                                }
                                $fieldNames[] = $fieldName;
                            } catch (\Exception $e) {
                                self::debugSQL("Exception getting field names: " . $e->getMessage());
                                $useNumericIndices = true;
                                break;
                            }
                        }

                        // Fetch rows using odbc_fetch_row + odbc_result (more reliable for MS Access)
                        // odbc_fetch_into can fail with boolean/binary fields
                        $rowNum = 0;
                        while (@odbc_fetch_row($odbcResult)) {
                            $rowData = [];
                            for ($i = 1; $i <= $numCols; $i++) {
                                // Use @ to suppress warnings on problematic fields
                                $value = @odbc_result($odbcResult, $i);
                                if ($value === false) {
                                    // Check if it's an actual false value or an error
                                    $err = odbc_error();
                                    if ($err) {
                                        // Error fetching this field - use null
                                        $value = null;
                                    }
                                }
                                // Convert Windows-1252 (Access default) to UTF-8
                                if (is_string($value) && $value !== '' && !mb_check_encoding($value, 'UTF-8')) {
                                    $value = mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
                                }
                                $rowData[] = $value;
                            }

                            if ($useNumericIndices) {
                                // Use numeric indices directly
                                $data[] = $rowData;
                            } else {
                                // Map to field names
                                $row = [];
                                for ($i = 0; $i < $numCols; $i++) {
                                    $row[$fieldNames[$i]] = $rowData[$i];
                                }
                                $data[] = $row;
                            }
                            $rowNum++;
                        }

                        odbc_free_result($odbcResult);
                        // Don't close connection - it's pooled for reuse

                        // Create an ArrayIterator that RecordSet can work with
                        $stmt = new \ArrayIterator($data);
                        self::debugSQL("Converted ODBC result to array: {$rowNum} rows" . ($useNumericIndices ? " (numeric indices)" : " (named fields)"));

                        // Return RecordSet with array data
                        self::logSQL($sqlLogStart, $sql, $connection);
                        return new RecordSet($stmt, $scrollable, true); // true = array mode
                    } else {
                        $odbcError = odbc_errormsg($odbcConn);
                        // Don't close connection - it's pooled for reuse
                        self::debugSQL("Native ODBC execution failed: " . $odbcError);
                        // Store SQL for ErrorHandler display
                        self::$lastError = "Native ODBC error: " . $odbcError;
                        self::$lastSQL = $sql;
                        throw new \PDOException("Native ODBC error: " . $odbcError);
                    }
                } else {
                    self::debugSQL("Native ODBC connection failed, falling back to PDO");
                }
            }

            // Standard PDO prepare/execute (default mode, or fallback from native ODBC)
            if ($driver === 'odbc') {
                self::debugSQL("Using PDO ODBC mode (odbc_mode=" . self::getOdbcMode() . ")");
            } else {
                self::debugSQL("Using PDO::prepare() + execute()");
            }
            $stmt = $conn->prepare($sql);
            $stmt->execute();

            // Create RecordSet object with PDOStatement
            self::debugSQL("Query executed successfully");
            self::logSQL($sqlLogStart, $sql, $connection);
            return new RecordSet($stmt, $scrollable);
        } catch (\PDOException $e) {
            self::$lastError = $e->getMessage();
            self::$lastSQL = $sql;
            self::debugSQL("PDO EXCEPTION: " . $e->getMessage(), $sql);
            self::logError($sql, [], $e);
            self::logSQL($sqlLogStart, $sql, $connection, true);
            return null;
        } catch (\Exception $e) {
            self::$lastError = $e->getMessage();
            self::$lastSQL = $sql;
            self::debugSQL("EXCEPTION: " . $e->getMessage(), $sql);
            error_log('Database::openRS error: ' . $e->getMessage());
            self::logSQL($sqlLogStart, $sql, $connection, true);
            return null;
        }
    }

    /**
     * Open recordset using native ODBC connection
     *
     * @param string $sql SQL query
     * @param mixed $odbcConn Native ODBC connection (Odbc\Connection object)
     * @param int|null $cursorType Cursor type constant (null = forward-only)
     * @return RecordSet|null RecordSet object or null on error
     */
    private static function openRSNativeODBC(string $sql, $odbcConn, ?int $cursorType = null): ?RecordSet
    {
        try {
            self::debugSQL("Using native ODBC connection", $sql);

            // Determine if scrollable cursor
            $scrollable = ($cursorType !== null && $cursorType !== 0);

            // Execute using native ODBC
            $odbcResult = @odbc_exec($odbcConn, $sql);

            if ($odbcResult) {
                self::debugSQL("Native ODBC execution successful!");

                // Fetch all data into an array
                $data = [];
                $numCols = odbc_num_fields($odbcResult);

                // Get field names
                $fieldNames = [];
                $useNumericIndices = false;

                for ($i = 1; $i <= $numCols; $i++) {
                    try {
                        $fieldName = @odbc_field_name($odbcResult, $i);
                        if ($fieldName === false) {
                            self::debugSQL("Failed to get field name for column $i, using numeric indices");
                            $useNumericIndices = true;
                            break;
                        }
                        $fieldNames[] = $fieldName;
                    } catch (\Exception $e) {
                        self::debugSQL("Exception getting field names: " . $e->getMessage());
                        $useNumericIndices = true;
                        break;
                    }
                }

                // Fetch rows using odbc_fetch_row + odbc_result (more reliable for MS Access)
                // odbc_fetch_into can fail with boolean/binary fields due to casting issues
                $rowNum = 0;
                while (@odbc_fetch_row($odbcResult)) {
                    $rowData = [];
                    for ($i = 1; $i <= $numCols; $i++) {
                        $value = @odbc_result($odbcResult, $i);
                        // odbc_result returns false on error, but also for NULL/empty
                        // Check if there's an actual error
                        if ($value === false) {
                            $err = odbc_error();
                            if ($err) {
                                // Real error - use null as fallback
                                $value = null;
                            }
                        }
                        // Convert Windows-1252 (Access default) to UTF-8
                        if (is_string($value) && $value !== '' && !mb_check_encoding($value, 'UTF-8')) {
                            $value = mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
                        }
                        $rowData[] = $value;
                    }

                    if ($useNumericIndices) {
                        // Use numeric indices directly
                        $data[] = $rowData;
                    } else {
                        // Map to field names
                        $row = [];
                        for ($i = 0; $i < $numCols; $i++) {
                            $row[$fieldNames[$i]] = $rowData[$i];
                        }
                        $data[] = $row;
                    }
                    $rowNum++;
                }

                odbc_free_result($odbcResult);

                // Create an ArrayIterator that RecordSet can work with
                $stmt = new \ArrayIterator($data);
                self::debugSQL("Converted ODBC result to array: {$rowNum} rows" . ($useNumericIndices ? " (numeric indices)" : " (named fields)"));

                // Return RecordSet with array data
                return new RecordSet($stmt, $scrollable, true); // true = array mode
            } else {
                $odbcError = odbc_errormsg($odbcConn);
                self::debugSQL("Native ODBC execution failed: " . $odbcError);
                self::$lastError = "Native ODBC error: " . $odbcError;
                self::$lastSQL = $sql;
                return null;
            }
        } catch (\Exception $e) {
            self::$lastError = $e->getMessage();
            self::debugSQL("EXCEPTION in openRSNativeODBC: " . $e->getMessage(), $sql);
            return null;
        }
    }

    /**
     * Open a recordset using pass-by-reference (legacy ASP pattern)
     *
     * @param mixed &$recordset Reference to recordset variable (will be set to RecordSet object or null)
     * @param string $sql SQL query
     * @param mixed $connection Connection string or PDO object (optional)
     * @param int|null $cursorType Cursor type constant (null = forward-only)
     * @return void
     */
    public static function openRSByRef(&$recordset, string $sql, $connection = null, ?int $cursorType = null): void
    {
        $recordset = self::openRS($sql, $connection, $cursorType);
    }

    /**
     * Execute non-query SQL (INSERT, UPDATE, DELETE)
     *
     * Equivalent to ASP's lib_executeSingleRecordSQL for non-SELECT queries
     *
     * @param string $sql SQL statement
     * @param array $params Query parameters (for prepared statements)
     * @return int Number of affected rows, or 0 on error
     */
    public static function execute(string $sql, array $params = []): int
    {
        try {
            $conn = self::getConnection();
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            self::$lastError = $e->getMessage();
            self::logError($sql, $params, $e);
            return 0;
        }
    }

    /**
     * Execute SQL and return PDOStatement for advanced usage
     *
     * Use this when you need direct access to the statement object
     *
     * @param string $sql SQL query
     * @param array $params Query parameters
     * @param bool $useRepConnection Use replication connection (default: false)
     * @return PDOStatement|null Statement object or null on error
     */
    public static function executeStatement(string $sql, array $params = [], bool $useRepConnection = false): ?PDOStatement
    {
        try {
            $conn = $useRepConnection ? self::getRepConnection() : self::getConnection();
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            self::$lastError = $e->getMessage();
            self::logError($sql, $params, $e);
            return null;
        }
    }

    /**
     * Execute a query and return PDOStatement (Change #342)
     *
     * Replacement for VBScript RecordSet.Open() pattern.
     * Returns a PDOStatement that can be used with fetch() to iterate results.
     *
     * @param string $sql SQL query to execute
     * @param array $params Query parameters for prepared statement
     * @param mixed $connection PDO connection, connection string, or null (uses default)
     * @return PDOStatement|null Statement object or null on error
     *
     * Usage:
     *   $rs = Database::query('SELECT * FROM users WHERE id = ?', [123], $conn);
     *   $row = $rs->fetch(PDO::FETCH_ASSOC);
     */
    public static function query(string $sql, array $params = [], $connection = null): ?PDOStatement
    {
        try {
            // Handle connection parameter
            if ($connection === null) {
                $conn = self::getConnection();
            } elseif ($connection instanceof PDO) {
                $conn = $connection;
            } elseif (is_string($connection)) {
                // Connection string provided - get named connection
                $conn = self::getNamedConnection($connection);
            } else {
                throw new \InvalidArgumentException('Invalid connection parameter');
            }

            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            self::$lastError = $e->getMessage();
            self::logError($sql, $params, $e);
            return null;
        }
    }

    /**
     * Fetch a single row from a query (Change #428)
     *
     * Combines query execution and fetch into one call.
     * Returns the first row as an associative array, or false if no results.
     *
     * @param string $sql SQL query to execute
     * @param array $params Query parameters for prepared statement
     * @param mixed $connection PDO connection, connection string, or null (uses default)
     * @return array|false Associative array of row data, or false if no results
     *
     * Usage:
     *   $row = Database::fetchOne('SELECT * FROM users WHERE id = ?', [123], $conn);
     *   if ($row) {
     *       echo $row['name'];
     *   }
     */
    public static function fetchOne(string $sql, array $params = [], $connection = null)
    {
        // Resolve connection for encoding conversion
        if ($connection === null) {
            $conn = self::getConnection();
        } elseif ($connection instanceof PDO) {
            $conn = $connection;
        } elseif (is_string($connection)) {
            $conn = self::getNamedConnection($connection);
        } else {
            $conn = self::getConnection();
        }
        $stmt = self::query($sql, $params, $conn);
        if ($stmt === null) {
            return false;
        }
        return self::convertRowEncoding($conn, $stmt->fetch(\PDO::FETCH_ASSOC));
    }

    /**
     * Fetch all rows from a query (Change #428)
     *
     * Combines query execution and fetchAll into one call.
     * Returns all rows as an array of associative arrays, or empty array if no results.
     *
     * @param string $sql SQL query to execute
     * @param array $params Query parameters for prepared statement
     * @param mixed $connection PDO connection, connection string, or null (uses default)
     * @return array Array of associative arrays (rows), empty array if no results
     *
     * Usage:
     *   $rows = Database::fetchAll('SELECT * FROM users WHERE active = ?', [1], $conn);
     *   foreach ($rows as $row) {
     *       echo $row['name'];
     *   }
     */
    public static function fetchAll(string $sql, array $params = [], $connection = null): array
    {
        // Resolve connection for encoding conversion
        if ($connection === null) {
            $conn = self::getConnection();
        } elseif ($connection instanceof PDO) {
            $conn = $connection;
        } elseif (is_string($connection)) {
            $conn = self::getNamedConnection($connection);
        } else {
            $conn = self::getConnection();
        }
        $stmt = self::query($sql, $params, $conn);
        if ($stmt === null) {
            return [];
        }
        return self::convertRowsEncoding($conn, $stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    /**
     * Get last insert ID
     *
     * @param string|null $name Sequence name (for PostgreSQL)
     * @return int Last insert ID
     */
    public static function getLastInsertId(?string $name = null): int
    {
        try {
            $conn = self::getConnection();
            return (int)$conn->lastInsertId($name);
        } catch (PDOException $e) {
            self::$lastError = $e->getMessage();
            return 0;
        }
    }

    /**
     * Begin database transaction
     *
     * @return bool True on success, false on failure
     */
    public static function beginTransaction(): bool
    {
        try {
            $conn = self::getConnection();
            return $conn->beginTransaction();
        } catch (PDOException $e) {
            self::$lastError = $e->getMessage();
            return false;
        }
    }

    /**
     * Commit database transaction
     *
     * @return bool True on success, false on failure
     */
    public static function commit(): bool
    {
        try {
            $conn = self::getConnection();
            return $conn->commit();
        } catch (PDOException $e) {
            self::$lastError = $e->getMessage();
            return false;
        }
    }

    /**
     * Rollback database transaction
     *
     * @return bool True on success, false on failure
     */
    public static function rollback(): bool
    {
        try {
            $conn = self::getConnection();
            return $conn->rollBack();
        } catch (PDOException $e) {
            self::$lastError = $e->getMessage();
            return false;
        }
    }

    /**
     * Check if currently in a transaction
     *
     * @return bool True if in transaction
     */
    public static function inTransaction(): bool
    {
        try {
            $conn = self::getConnection();
            return $conn->inTransaction();
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Get last database error message
     *
     * @return string Error message
     */
    public static function getLastError(): string
    {
        return self::$lastError;
    }

    /**
     * Clean a database error message for user display.
     * Strips SQLSTATE codes, PDO prefixes, driver noise and connection strings.
     *
     * @param string $message Raw error message
     * @return string Cleaned message
     */
    public static function cleanErrorMessage(string $message): string
    {
        // Strip SQLSTATE prefix: "SQLSTATE[HY000]: ..." or "SQLSTATE[42S02]"
        $message = preg_replace('/^SQLSTATE\[[A-Z0-9]+\]:\s*/', '', $message);
        // Strip PDO/driver prefix: "PDO::prepare(): ..." or "ODBC: ..."
        $message = preg_replace('/^(?:PDO|ODBC|pdo_\w+)(?:::\w+\(\))?:\s*/i', '', $message);
        // Strip "[Microsoft][ODBC Driver ...]" style prefixes
        $message = preg_replace('/\[Microsoft\]\[.*?\]\s*/i', '', $message);
        // Strip connection string details (DSN paths, server names)
        $message = preg_replace('/\s*\((?:SQL|DSN|Server|DBQ)[^)]*\)/i', '', $message);
        // Strip "General error: " prefix
        $message = preg_replace('/^General error:\s*\d*\s*/i', '', $message);
        // Strip English PDO category prefixes with error codes (e.g. "Base table or view not found: -1305")
        $message = preg_replace('/^[A-Z][a-z]+(?: [a-z]+)* (?:not found|failed|denied|refused):\s*-?\d+\s*/i', '', $message);
        // Strip "De Microsoft Access-database-engine " prefix
        $message = preg_replace('/^De Microsoft Access-database-engine\s*/i', '', $message);
        // Capitalize first letter after cleanup
        $message = trim($message);
        if ($message !== '') {
            $message = mb_strtoupper(mb_substr($message, 0, 1)) . mb_substr($message, 1);
        }
        return $message;
    }

    /**
     * Get the last SQL query that caused an error
     *
     * @return string The SQL query or empty string
     */
    public static function getLastSQL(): string
    {
        return self::$lastSQL;
    }

    /**
     * Clear last error
     *
     * @return void
     */
    public static function clearLastError(): void
    {
        self::$lastError = '';
        self::$lastSQL = '';
    }

    /**
     * Close all database connections
     *
     * Useful for cleanup or forcing new connections
     *
     * @return void
     */
    public static function closeAll(): void
    {
        self::$connData = null;
        self::$connRep = null;
        self::$namedConnections = [];

        // Close native ODBC connections
        foreach (self::$nativeOdbcConnections as $conn) {
            if (is_resource($conn)) {
                @odbc_close($conn);
            }
        }
        self::$nativeOdbcConnections = [];
    }

    /**
     * Enable or disable connection pooling
     *
     * When disabled, new connection is created for each query
     *
     * @param bool $enable True to enable pooling (default ASP behavior)
     * @return void
     */
    public static function setPooling(bool $enable): void
    {
        self::$enablePooling = $enable;

        // If disabling pooling, close existing connections
        if (!$enable) {
            self::closeAll();
        }
    }

    /**
     * Check if connection pooling is enabled
     *
     * @return bool True if pooling enabled
     */
    public static function isPoolingEnabled(): bool
    {
        return self::$enablePooling;
    }

    /**
     * Execute SQL with retry logic for database lock errors
     *
     * Matches ASP behavior for handling busy databases
     *
     * @param string $sql SQL statement
     * @param array $params Query parameters
     * @param int $maxRetries Maximum retry attempts (default: 3)
     * @param int $retryDelay Delay between retries in milliseconds (default: 100)
     * @return int Number of affected rows, or 0 on error
     */
    public static function executeWithRetry(string $sql, array $params = [], int $maxRetries = 3, int $retryDelay = 100): int
    {
        $attempts = 0;

        while ($attempts < $maxRetries) {
            try {
                return self::execute($sql, $params);
            } catch (PDOException $e) {
                $attempts++;
                $errorMsg = $e->getMessage();

                // Check for lock/busy errors (matches ASP lib_db.inc logic)
                $isLockError = stripos($errorMsg, 'locked') !== false ||
                               stripos($errorMsg, 'database is locked') !== false ||
                               stripos($errorMsg, 'cannot open database') !== false ||
                               stripos($errorMsg, 'database has been placed in a state') !== false;

                if ($isLockError && $attempts < $maxRetries) {
                    // Wait before retrying
                    usleep($retryDelay * 1000);
                    continue;
                }

                // Not a lock error or max retries reached
                self::$lastError = $errorMsg;
                self::logError($sql, $params, $e);
                return 0;
            }
        }

        return 0;
    }

    /**
     * Log database errors
     *
     * @param string $sql SQL that caused the error
     * @param array $params Parameters used
     * @param PDOException $e Exception object
     * @return void
     */
    private static function logError(string $sql, array $params, PDOException $e): void
    {
        // Only log in test/debug mode
        $isDebug = \App\Library\Application::get('test', false) ||
                   in_array(strtolower(\App\Library\Application::get('omgeving', '')), ['l', 'o', 't']);

        if ($isDebug) {
            error_log(sprintf(
                "Database Error: %s\nSQL: %s\nParams: %s\nTrace: %s",
                $e->getMessage(),
                $sql,
                json_encode($params),
                $e->getTraceAsString()
            ));
        }
    }

    /**
     * Quote identifier (table/column name) for safe use in SQL
     *
     * @param string $identifier Table or column name
     * @return string Quoted identifier
     */
    public static function quoteIdentifier(string $identifier): string
    {
        try {
            $conn = self::getConnection();
            $driver = $conn->getAttribute(PDO::ATTR_DRIVER_NAME);

            // Different quote styles for different databases
            switch ($driver) {
                case 'mysql':
                    return '`' . str_replace('`', '``', $identifier) . '`';
                case 'sqlsrv':
                case 'mssql':
                    return '[' . str_replace(']', ']]', $identifier) . ']';
                case 'pgsql':
                    return '"' . str_replace('"', '""', $identifier) . '"';
                default:
                    return '"' . str_replace('"', '""', $identifier) . '"';
            }
        } catch (PDOException $e) {
            // Fallback to double quotes
            return '"' . str_replace('"', '""', $identifier) . '"';
        }
    }

    /**
     * Escape value for use in SQL (prefer prepared statements)
     *
     * @param mixed $value Value to escape
     * @return string Escaped value
     * @deprecated Use prepared statements with parameters instead
     */
    public static function escape($value): string
    {
        try {
            $conn = self::getConnection();
            return $conn->quote((string)$value);
        } catch (PDOException $e) {
            // Fallback to addslashes
            return "'" . addslashes((string)$value) . "'";
        }
    }

    /**
     * Get database driver name
     *
     * @return string Driver name (mysql, sqlsrv, pgsql, etc.)
     */
    public static function getDriverName(): string
    {
        try {
            $conn = self::getConnection();
            return $conn->getAttribute(PDO::ATTR_DRIVER_NAME);
        } catch (PDOException $e) {
            return 'unknown';
        }
    }

    /**
     * Get database type from connection
     *
     * Returns a normalized database type name for use in conditional logic.
     *
     * @param PDO|null $conn PDO connection (null = use default connection)
     * @return string Database type: 'sqlserver', 'sqlite', 'mysql', 'access', or 'unknown'
     */
    public static function getDatabaseType(?PDO $conn = null): string
    {
        try {
            if ($conn === null) {
                $conn = self::getConnection();
            }

            $driver = $conn->getAttribute(PDO::ATTR_DRIVER_NAME);

            switch (strtolower($driver)) {
                case 'sqlsrv':
                case 'mssql':
                case 'dblib':
                    return 'sqlserver';
                case 'sqlite':
                    return 'sqlite';
                case 'mysql':
                    return 'mysql';
                case 'odbc':
                    // ODBC is typically used for Access databases
                    return 'access';
                case 'pgsql':
                    return 'postgresql';
                default:
                    return $driver ?: 'unknown';
            }
        } catch (PDOException $e) {
            return 'unknown';
        }
    }

    /**
     * Check if connection string is for SQL Server
     *
     * Equivalent to ASP's lib_SQL_isSQLServer function
     *
     * @param string|null $connectionString Connection string to check (null = use conn_data)
     * @return bool True if SQL Server connection
     */
    public static function isSQLServer(?string $connectionString = null): bool
    {
        if ($connectionString === null) {
            $connectionString = \App\Library\Application::get('conn_data', '');
        }

        // Check for SQL Server patterns in connection string
        return stripos($connectionString, 'Initial Catalog=') !== false ||
               stripos($connectionString, 'DSN=') === 0 ||
               stripos($connectionString, 'MSDASQL') !== false ||
               stripos($connectionString, 'SQLOLEDB') !== false ||
               stripos($connectionString, 'SQLEXPRESS') !== false ||
               stripos($connectionString, 'SQL Server Native Client ') !== false ||
               stripos($connectionString, 'SQLNCLI') !== false ||
               stripos($connectionString, 'MSOLEDBSQL') !== false;
    }

    /**
     * Check if connection string is for ODBC
     *
     * Equivalent to ASP's lib_SQL_isODBC function
     *
     * @param string|null $connectionString Connection string to check (null = use conn_data)
     * @return bool True if ODBC connection
     */
    public static function isODBC(?string $connectionString = null): bool
    {
        if ($connectionString === null) {
            $connectionString = \App\Library\Application::get('conn_data', '');
        }

        // Check if connection string starts with DSN=
        return stripos($connectionString, 'DSN=') === 0;
    }

    /**
     * Check if connection is SQLite
     *
     * @param PDO|null $connection PDO connection to check
     * @return bool True if SQLite connection
     */
    public static function isSQLite($connection = null): bool
    {
        if ($connection === null) {
            $connection = self::getConnection();
        }

        if (!($connection instanceof \PDO)) {
            return false;
        }

        try {
            $driver = $connection->getAttribute(\PDO::ATTR_DRIVER_NAME);
            return strtolower($driver) === 'sqlite';
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Process SQL for database compatibility (deprecated - use SQL::processSQL())
     *
     * @param mixed $connection Connection object or string
     * @param string $sql SQL query to process
     * @return string Processed SQL query
     * @deprecated Use SQL::processSQL() instead
     */
    public static function processSQL($connectionOrSql, ?string $sql = null): string
    {
        // Handle deprecated single-argument call: processSQL($sql)
        if ($sql === null) {
            trigger_error('Database::processSQL() with single argument is deprecated. Use SQL::processSQL($connection, $sql) instead.', E_USER_DEPRECATED);
            $sql = $connectionOrSql;
            $connectionOrSql = null;
        }
        return SQL::processSQL($connectionOrSql, $sql);
    }

    /**
     * Add WHERE clause to SQL query (deprecated - use SQL::addWhere())
     *
     * @param string $sql Base SQL query
     * @param string $whereClause WHERE clause to add
     * @return string SQL with WHERE clause added
     * @deprecated Use SQL::addWhere() instead
     */
    public static function addWhere(string $sql, string $whereClause): string
    {
        return SQL::addWhere($sql, $whereClause);
    }

    /**
     * Add WHERE clause with OR (deprecated - use SQL::addWhereOR())
     *
     * @param string $sql Base SQL query
     * @param string $whereClause WHERE clause to add
     * @return string SQL with WHERE clause added
     * @deprecated Use SQL::addWhereOR() instead
     */
    public static function addWhereOR(string $sql, string $whereClause): string
    {
        return SQL::addWhereOR($sql, $whereClause);
    }

    /**
     * Add HAVING clause (deprecated - use SQL::addHaving())
     *
     * @param string $sql Base SQL query
     * @param string $havingClause HAVING clause to add
     * @return string SQL with HAVING clause added
     * @deprecated Use SQL::addHaving() instead
     */
    public static function addHaving(string $sql, string $havingClause): string
    {
        return SQL::addHaving($sql, $havingClause);
    }

    /**
     * Add IN clause (deprecated - use SQL::addInClause())
     *
     * @param string $sql Base SQL query
     * @param string $field Field name
     * @param string $values Comma-separated values
     * @param int $minInValues Minimum values for IN clause
     * @return string SQL with IN or OR clause added
     * @deprecated Use SQL::addInClause() instead
     */
    public static function addInClause(string $sql, string $field, string $values, int $minInValues = 5): string
    {
        return SQL::addInClause($sql, $field, $values, $minInValues);
    }

       /**
     * Check if a field exists in a table
     *
     * Equivalent to ASP's lib_dbFieldExist function
     *
     * @param string|null $connectionString Connection string (null = use conn_data)
     * @param string $table Table name
     * @param string $fieldName Field name (empty string to check table only)
     * @return bool True if field/table exists
     */
    public static function fieldExists(?string $connectionString, string $table, string $fieldName = ''): bool
    {
        if ($connectionString === null) {
            $connectionString = \App\Library\Application::get('conn_data', '');
        }

        try {
            $conn = self::getConnection();
            $driver = $conn->getAttribute(PDO::ATTR_DRIVER_NAME);

            if ($driver === 'mysql') {
                // MySQL: Use INFORMATION_SCHEMA
                $sql = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?";
                if (!empty($fieldName)) {
                    $sql .= " AND COLUMN_NAME = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([$table, $fieldName]);
                } else {
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([$table]);
                }
            } elseif ($driver === 'sqlsrv' || $driver === 'mssql') {
                // SQL Server: Use INFORMATION_SCHEMA
                $sql = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = ?";
                if (!empty($fieldName)) {
                    $sql .= " AND COLUMN_NAME = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([$table, $fieldName]);
                } else {
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([$table]);
                }
            } else {
                // Generic approach - try to query the table
                if (!empty($fieldName)) {
                    $sql = "SELECT $fieldName FROM $table WHERE 1=0";
                } else {
                    $sql = "SELECT * FROM $table WHERE 1=0";
                }
                $stmt = $conn->prepare($sql);
                $stmt->execute();
            }

            $result = $stmt->fetch();
            return $result !== false || $stmt->rowCount() >= 0;
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Check if a table exists
     *
     * Equivalent to ASP's lib_dbTableExist function
     *
     * @param string|null $connectionString Connection string (null = use conn_data)
     * @param string $table Table name
     * @return bool True if table exists
     */
    public static function tableExists(?string $connectionString, string $table): bool
    {
        return self::fieldExists($connectionString, $table, '');
    }

    /**
     * Check if a field exists in a recordset
     *
     * Equivalent to ASP's lib_dbFieldinRS function
     *
     * @param array|RecordSet $recordset Recordset (associative array or RecordSet object)
     * @param string $fieldName Field name to check
     * @return bool True if field exists
     */
    public static function fieldInRecordset($recordset, string $fieldName): bool
    {
        if (empty($recordset)) {
            return false;
        }

        // Handle RecordSet object - check the current row's fields
        if ($recordset instanceof RecordSet) {
            $row = $recordset->fields;
            if (!is_array($row) || empty($row)) {
                return false;
            }
            return array_key_exists($fieldName, $row);
        }

        // Handle array
        if (!is_array($recordset)) {
            return false;
        }

        return array_key_exists($fieldName, $recordset);
    }

    /**
     * Get field value from a single-record query
     *
     * Equivalent to ASP's lib_dbGetFieldValue function
     *
     * @param string|PDO|null $connection Connection string, PDO object, or null (use conn_data)
     * @param string $sql SQL query
     * @param string|null $fieldName Field name to retrieve (null = first field)
     * @return mixed Field value or null if not found
     */
    public static function getFieldValue($connection, string $sql, ?string $fieldName = null)
    {
        try {
            // Handle connection parameter
            if ($connection instanceof \PDO) {
                $conn = $connection;
            } elseif ($connection === null || $connection === '') {
                $conn = self::getConnection();
            } else {
                // Check if it's the repository connection string
                $repConn = \App\Library\Application::get('conn_rep', '');
                if ($connection === $repConn) {
                    $conn = self::getRepConnection();
                } else {
                    $conn = self::getConnection();
                }
            }

            $stmt = $conn->prepare($sql);
            $stmt->execute();
            $record = $stmt->fetch();

            if ($record === false) {
                return null;
            }

            if ($fieldName === null || $fieldName === '') {
                // Return first field
                return reset($record);
            }

            return $record[$fieldName] ?? null;
        } catch (\PDOException $e) {
            self::$lastError = $e->getMessage();
            self::logError($sql, [], $e);
            return null;
        }
    }

    /**
     * Get array of IDs from a query
     *
     * Equivalent to ASP's lib_dbGetIds function
     *
     * @param string|PDO|null $connection Connection string, PDO object, or null (use conn_data)
     * @param string $sql SQL query
     * @return array Array of ID values
     */
    public static function getIds($connection, string $sql): array
    {
        try {
            // Handle connection parameter
            if ($connection instanceof \PDO) {
                $conn = $connection;
            } elseif ($connection === null || $connection === '') {
                $conn = self::getConnection();
            } else {
                // Check if it's the repository connection string
                $repConn = \App\Library\Application::get('conn_rep', '');
                if ($connection === $repConn) {
                    $conn = self::getRepConnection();
                } else {
                    $conn = self::getConnection();
                }
            }

            $stmt = $conn->prepare($sql);
            $stmt->execute();
            $records = $stmt->fetchAll();
            $ids = [];

            foreach ($records as $record) {
                // Get first column value (assumed to be ID)
                $ids[] = reset($record);
            }

            return $ids;
        } catch (\PDOException $e) {
            self::$lastError = $e->getMessage();
            self::logError($sql, [], $e);
            return [];
        }
    }

    /**
     * Get all records as array
     *
     * Equivalent to ASP's lib_dbGetRecordArray function
     *
     * @param string|null $connectionString Connection string (null = use conn_data)
     * @param string $sql SQL query
     * @return array Array of records
     */
    public static function getRecordArray(?string $connectionString, string $sql): array
    {
        return self::executeQuery($sql, [], false);
    }

    /**
     * Count records in a recordset
     *
     * Equivalent to ASP's lib_countRecords function
     *
     * @param array $recordset Array of records
     * @return int Number of records
     */
    public static function countRecords(array $recordset): int
    {
        return count($recordset);
    }

    /**
     * Check if a connection is open
     *
     * Equivalent to ASP's lib_isConnectionOpen function
     *
     * @param PDO|null $connection Connection object
     * @return bool True if connection is open
     */
    public static function isConnectionOpen(?PDO $connection): bool
    {
        if ($connection === null) {
            return false;
        }

        try {
            // Try to query the connection
            $connection->query('SELECT 1');
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Get cached table record count
     *
     * Equivalent to ASP's lib_TableRecordCount_Cached function
     *
     * @param string|PDO|resource|null $connection Connection string, PDO object, or ODBC resource (null = use conn_data)
     * @param string $table Table name
     * @return int Number of records
     */
    public static function getTableRecordCount($connection, string $table): int
    {
        $sql = "SELECT COUNT(*) as count FROM $table";

        // If connection is a PDO object or ODBC resource, use openRS directly
        if ($connection instanceof \PDO || is_resource($connection)) {
            $rs = self::openRS($sql, $connection);
            if ($rs === null) {
                return 0;
            }
            return (int)($rs->fields['count'] ?? 0);
        }

        // Otherwise treat as connection string
        $result = self::getFieldValue($connection, $sql, 'count');
        return (int)$result;
    }

    /**
     * Add a field to a table
     *
     * Equivalent to ASP's lib_dbFieldAdd function
     *
     * @param string|null $connectionString Connection string (null = use conn_data)
     * @param string $table Table name
     * @param string $field Field name
     * @param string $type Field type (varchar, int, etc.)
     * @param int $length Field length
     * @return bool True on success
     */
    public static function addField(?string $connectionString, string $table, string $field, string $type, int $length): bool
    {
        if ($connectionString === null) {
            $connectionString = \App\Library\Application::get('conn_data', '');
        }

        try {
            $conn = self::getConnection();
            $driver = $conn->getAttribute(PDO::ATTR_DRIVER_NAME);

            // Build ALTER TABLE statement based on driver
            if ($driver === 'mysql') {
                if ($type === 'varchar') {
                    $sql = "ALTER TABLE $table ADD COLUMN $field VARCHAR($length)";
                } else {
                    $sql = "ALTER TABLE $table ADD COLUMN $field $type";
                }
            } elseif ($driver === 'sqlsrv' || $driver === 'mssql') {
                if ($type === 'varchar') {
                    $sql = "ALTER TABLE $table ADD $field VARCHAR($length)";
                } else {
                    $sql = "ALTER TABLE $table ADD $field $type";
                }
            } else {
                return false;
            }

            $conn->exec($sql);
            return true;
        } catch (PDOException $e) {
            self::$lastError = $e->getMessage();
            return false;
        }
    }

    /**
     * Rename a field in a table
     *
     * Equivalent to ASP's lib_dbRenameField function
     *
     * @param string|null $connectionString Connection string (null = use conn_data)
     * @param string $table Table name
     * @param string $oldName Old field name
     * @param string $newName New field name
     * @return bool True on success
     */
    public static function renameField(?string $connectionString, string $table, string $oldName, string $newName): bool
    {
        if ($connectionString === null) {
            $connectionString = \App\Library\Application::get('conn_data', '');
        }

        try {
            $conn = self::getConnection();
            $driver = $conn->getAttribute(PDO::ATTR_DRIVER_NAME);

            if ($driver === 'mysql') {
                // MySQL requires the full column definition
                $sql = "ALTER TABLE $table CHANGE $oldName $newName VARCHAR(255)";
            } elseif ($driver === 'sqlsrv' || $driver === 'mssql') {
                $sql = "EXEC sp_rename '$table.$oldName', '$newName', 'COLUMN'";
            } else {
                return false;
            }

            $conn->exec($sql);
            return true;
        } catch (PDOException $e) {
            self::$lastError = $e->getMessage();
            return false;
        }
    }

    /**
     * Set default value for a field
     *
     * Equivalent to ASP's lib_dbSetDefaultValue function
     *
     * @param string|null $connectionString Connection string (null = use conn_data)
     * @param string $table Table name
     * @param string $field Field name
     * @param mixed $defaultValue Default value
     * @return bool True on success
     */
    public static function setDefaultValue(?string $connectionString, string $table, string $field, $defaultValue): bool
    {
        if ($connectionString === null) {
            $connectionString = \App\Library\Application::get('conn_data', '');
        }

        try {
            $conn = self::getConnection();
            $driver = $conn->getAttribute(PDO::ATTR_DRIVER_NAME);

            // Quote default value if it's a string
            if (is_string($defaultValue)) {
                $defaultValue = $conn->quote($defaultValue);
            }

            if ($driver === 'mysql') {
                $sql = "ALTER TABLE $table ALTER COLUMN $field SET DEFAULT $defaultValue";
            } elseif ($driver === 'sqlsrv' || $driver === 'mssql') {
                $sql = "ALTER TABLE $table ADD DEFAULT $defaultValue FOR $field";
            } else {
                return false;
            }

            $conn->exec($sql);
            return true;
        } catch (PDOException $e) {
            self::$lastError = $e->getMessage();
            return false;
        }
    }

    /**
     * Check if a recordset (RecordSet object) is open
     *
     * Checks if the object is valid and not at EOF
     *
     * @param mixed $rsObj RecordSet object or null
     * @return bool True if recordset is open and valid
     */
    public static function isRecordsetOpen($rsObj): bool
    {
        // First check if it's a valid object
        if (!is_object($rsObj)) {
            return false;
        }

        // Check if it's a RecordSet instance
        if (!($rsObj instanceof \App\Library\RecordSet)) {
            return false;
        }

        // Check if not at EOF (recordset is open and has data)
        try {
            return !$rsObj->isEOF();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check if a string is an OLEDB connection string
     *
     * Detects legacy OLEDB connection strings from ASP like:
     * provider=microsoft.jet.oledb.4.0;data source=c:/path/file.mdb
     *
     * @param string $str String to check
     * @return bool True if it's an OLEDB connection string
     */
    private static function isOleDbConnectionString(string $str): bool
    {
        $lower = strtolower($str);
        return (
            strpos($lower, 'provider=') !== false ||
            strpos($lower, 'data source=') !== false ||
            strpos($lower, 'microsoft.jet.oledb') !== false ||
            strpos($lower, 'microsoft.ace.oledb') !== false
        );
    }

    /**
     * Create a PDO connection from an OLEDB connection string
     *
     * Converts legacy OLEDB connection strings to ODBC format:
     * Input:  provider=microsoft.jet.oledb.4.0;data source=c:/path/file.mdb
     * Output: odbc:Driver={Microsoft Access Driver (*.mdb, *.accdb)};Dbq=c:/path/file.mdb
     *
     * @param string $oleDbString OLEDB connection string
     * @return PDO Database connection
     * @throws PDOException If connection fails
     */
    private static function createConnectionFromOleDb(string $oleDbString): PDO
    {
        // Parse the OLEDB string to extract data source
        $dataSource = null;
        $parts = explode(';', $oleDbString);

        foreach ($parts as $part) {
            $part = trim($part);
            if (stripos($part, 'data source=') === 0) {
                $dataSource = substr($part, strlen('data source='));
                break;
            }
        }

        if (empty($dataSource)) {
            throw new PDOException("Could not extract data source from OLEDB string: $oleDbString");
        }

        // Normalize path separators
        $dataSource = str_replace('\\', '/', $dataSource);

        // Create cache key from the data source path
        $cacheKey = 'oledb_' . md5($dataSource);

        // Check if we already have this connection
        if (isset(self::$namedConnections[$cacheKey]) && self::$enablePooling) {
            return self::$namedConnections[$cacheKey];
        }

        // Build ODBC DSN for Access database
        $driver = 'Microsoft Access Driver (*.mdb, *.accdb)';
        $dsn = "odbc:Driver={{$driver}};Dbq={$dataSource}";

        // Create connection
        $conn = self::createPDOConnection($dsn, $cacheKey);

        // Store in pool
        self::$namedConnections[$cacheKey] = $conn;

        return $conn;
    }

    /**
     * Get table schema information
     *
     * Returns a RecordSet containing column metadata for the specified table.
     * Simulates ADO's OpenSchema method for schema discovery.
     *
     * @param mixed $connection Database connection or connection name
     * @param string $tableName Name of the table
     * @return RecordSet RecordSet with schema information
     */
    public static function getTableSchema($connection, string $tableName): RecordSet
    {
        try {
            // Get the PDO connection
            if (is_string($connection)) {
                $pdo = self::getNamedConnection($connection);
            } elseif ($connection instanceof PDO) {
                $pdo = $connection;
            } else {
                $pdo = self::getConnection();
            }

            // Get driver name to determine appropriate schema query
            $driverName = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            $columns = [];

            if (strpos($driverName, 'odbc') !== false || strpos($driverName, 'sqlsrv') !== false) {
                // For ODBC (Access) and SQL Server, use INFORMATION_SCHEMA
                $sql = "SELECT
                    COLUMN_NAME as COLUMN_NAME,
                    DATA_TYPE as DATA_TYPE,
                    CHARACTER_MAXIMUM_LENGTH as CHARACTER_MAXIMUM_LENGTH,
                    NUMERIC_PRECISION as NUMERIC_PRECISION,
                    NUMERIC_SCALE as NUMERIC_SCALE,
                    IS_NULLABLE as IS_NULLABLE,
                    COLUMN_DEFAULT as COLUMN_DEFAULT,
                    ORDINAL_POSITION as ORDINAL_POSITION,
                    DATETIME_PRECISION as DATETIME_PRECISION
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_NAME = ?
                ORDER BY ORDINAL_POSITION";

                $stmt = $pdo->prepare($sql);
                $stmt->execute([$tableName]);
                $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } elseif (strpos($driverName, 'mysql') !== false) {
                // For MySQL
                $sql = "SELECT
                    COLUMN_NAME,
                    DATA_TYPE,
                    CHARACTER_MAXIMUM_LENGTH,
                    NUMERIC_PRECISION,
                    NUMERIC_SCALE,
                    IS_NULLABLE,
                    COLUMN_DEFAULT,
                    ORDINAL_POSITION,
                    0 as DATETIME_PRECISION
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_NAME = ? AND TABLE_SCHEMA = DATABASE()
                ORDER BY ORDINAL_POSITION";

                $stmt = $pdo->prepare($sql);
                $stmt->execute([$tableName]);
                $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                // Generic fallback - empty result
                $columns = [];
            }

            // Create and populate a RecordSet with the results using ArrayIterator
            $iterator = new \ArrayIterator($columns);
            $recordset = new RecordSet($iterator, false, true);

            return $recordset;

        } catch (\Exception $e) {
            self::$lastError = $e->getMessage();
            // Return empty recordset on error
            $iterator = new \ArrayIterator([]);
            $recordset = new RecordSet($iterator, false, true);
            return $recordset;
        }
    }

    // =========================================================================
    // PDO Schema Modification Methods (used by MigrationService)
    // =========================================================================

    /**
     * Check if a table exists using a PDO connection
     *
     * @param PDO $conn PDO connection
     * @param string $table Table name
     * @return bool
     */
    public static function tableExistsPDO(PDO $conn, string $table): bool
    {
        try {
            $driver = $conn->getAttribute(PDO::ATTR_DRIVER_NAME);

            if ($driver === 'sqlite') {
                $stmt = $conn->query("SELECT name FROM sqlite_master WHERE type='table' AND name='$table'");
                return $stmt->fetch() !== false;
            } elseif ($driver === 'odbc') {
                // Access - try to select from the table
                try {
                    $conn->query("SELECT TOP 1 * FROM [$table]");
                    return true;
                } catch (\Exception $e) {
                    return false;
                }
            } else {
                $stmt = $conn->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = '$table'");
                return (int)$stmt->fetchColumn() > 0;
            }
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Add a column to a table using a PDO connection
     *
     * @param PDO $conn PDO connection
     * @param string $table Table name
     * @param string $column Column name
     * @param string $dataType Data type (e.g., 'VARCHAR(255)', 'INTEGER', 'YESNO', 'MEMO')
     * @param string|null $default Default value
     * @return array ['success' => bool, 'error' => string|null, 'sql' => string]
     */
    public static function addColumnPDO(PDO $conn, string $table, string $column, string $dataType, ?string $default = null): array
    {
        try {
            $driver = $conn->getAttribute(PDO::ATTR_DRIVER_NAME);

            // Check if column already exists
            try {
                if ($driver === 'odbc') {
                    $conn->query("SELECT TOP 1 [$column] FROM [$table]");
                } elseif ($driver === 'sqlite') {
                    $conn->query("SELECT [$column] FROM [$table] LIMIT 1");
                } else {
                    $conn->query("SELECT TOP 1 [$column] FROM [$table]");
                }
                return ['success' => true, 'error' => null, 'message' => 'Kolom bestaat al', 'sql' => ''];
            } catch (\Exception $e) {
                // Column doesn't exist - continue to add it
            }

            // Access ODBC does not support DEFAULT in ALTER TABLE ADD COLUMN
            // (except for YESNO fields). Use a separate UPDATE for defaults.
            $defaultClause = '';
            $needsDefaultUpdate = false;

            if ($default !== null && $default !== '') {
                if ($driver === 'odbc') {
                    // Access: only YESNO supports DEFAULT in ALTER TABLE
                    if (strtoupper($dataType) === 'YESNO') {
                        $defaultClause = ' DEFAULT ' . (strtoupper($default) === 'TRUE' || $default === '1' ? 'TRUE' : 'FALSE');
                    } else {
                        $needsDefaultUpdate = true;
                    }
                } else {
                    // Non-Access: build DEFAULT clause normally
                    if (strtoupper($dataType) === 'YESNO') {
                        $defaultClause = ' DEFAULT ' . (strtoupper($default) === 'TRUE' || $default === '1' ? 'TRUE' : 'FALSE');
                    } elseif (is_numeric($default)) {
                        $defaultClause = ' DEFAULT ' . $default;
                    } else {
                        $defaultClause = " DEFAULT '$default'";
                    }
                }
            }

            $sql = "ALTER TABLE [$table] ADD COLUMN [$column] $dataType$defaultClause";

            // SQL Server doesn't use COLUMN keyword
            if ($driver === 'sqlsrv' || $driver === 'mssql' || $driver === 'dblib') {
                $sql = "ALTER TABLE [$table] ADD [$column] $dataType$defaultClause";
            }

            $conn->exec($sql);

            // For Access: set default value via UPDATE on existing rows
            if ($needsDefaultUpdate) {
                $defaultVal = is_numeric($default) ? $default : "'" . str_replace("'", "''", $default) . "'";
                $conn->exec("UPDATE [$table] SET [$column] = $defaultVal WHERE [$column] IS NULL");
            }

            return ['success' => true, 'error' => null, 'sql' => $sql];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage(), 'sql' => $sql ?? ''];
        }
    }

    /**
     * Drop a column from a table using a PDO connection
     *
     * @param PDO $conn PDO connection
     * @param string $table Table name
     * @param string $column Column name
     * @return array ['success' => bool, 'error' => string|null]
     */
    public static function dropColumnPDO(PDO $conn, string $table, string $column): array
    {
        try {
            // Check if column exists first
            $columnExists = false;
            try {
                $driver = $conn->getAttribute(PDO::ATTR_DRIVER_NAME);
                if ($driver === 'odbc') {
                    $conn->query("SELECT TOP 1 [$column] FROM [$table]");
                } elseif ($driver === 'sqlite') {
                    $conn->query("SELECT [$column] FROM [$table] LIMIT 1");
                } else {
                    $conn->query("SELECT TOP 1 [$column] FROM [$table]");
                }
                $columnExists = true;
            } catch (\Exception $e) {
                // Column doesn't exist
            }

            if (!$columnExists) {
                return ['success' => true, 'error' => null, 'message' => 'Kolom bestaat niet (al verwijderd)'];
            }

            $sql = "ALTER TABLE [$table] DROP COLUMN [$column]";
            $conn->exec($sql);

            return ['success' => true, 'error' => null];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Add an index to a table using a PDO connection
     *
     * @param PDO $conn PDO connection
     * @param string $table Table name
     * @param array $columns Column names
     * @param string $indexName Index name
     * @return array ['success' => bool, 'error' => string|null]
     */
    public static function addIndexPDO(PDO $conn, string $table, array $columns, string $indexName): array
    {
        try {
            // Check if index already exists by trying to create it
            $columnList = implode(', ', array_map(fn($c) => "[$c]", $columns));
            $sql = "CREATE INDEX [$indexName] ON [$table] ($columnList)";

            $conn->exec($sql);

            return ['success' => true, 'error' => null, 'sql' => $sql];
        } catch (\Exception $e) {
            $msg = $e->getMessage();
            // If index already exists, treat as success
            // Matches: "already exists", "bestaat al", "bevat al een index", "duplicate"
            if (stripos($msg, 'already exists') !== false || stripos($msg, 'bestaat al') !== false || stripos($msg, 'bevat al') !== false || stripos($msg, 'duplicate') !== false) {
                return ['success' => true, 'error' => null, 'message' => 'Index bestaat al'];
            }
            return ['success' => false, 'error' => $msg, 'sql' => $sql];
        }
    }

    /**
     * Drop an index from a table using a PDO connection
     *
     * @param PDO $conn PDO connection
     * @param string $table Table name
     * @param string $indexName Index name
     * @return array ['success' => bool, 'error' => string|null]
     */
    public static function dropIndexPDO(PDO $conn, string $table, string $indexName): array
    {
        try {
            $driver = $conn->getAttribute(PDO::ATTR_DRIVER_NAME);

            if ($driver === 'odbc') {
                // Access syntax
                $sql = "DROP INDEX [$indexName] ON [$table]";
            } elseif ($driver === 'sqlite') {
                $sql = "DROP INDEX IF EXISTS [$indexName]";
            } else {
                // SQL Server
                $sql = "DROP INDEX [$indexName] ON [$table]";
            }

            $conn->exec($sql);

            return ['success' => true, 'error' => null, 'sql' => $sql];
        } catch (\Exception $e) {
            $msg = $e->getMessage();
            // If index doesn't exist, treat as success
            if (stripos($msg, 'not found') !== false || stripos($msg, 'does not exist') !== false ||
                stripos($msg, 'niet gevonden') !== false || stripos($msg, 'kan niet vinden') !== false) {
                return ['success' => true, 'error' => null, 'message' => 'Index bestaat niet (al verwijderd)'];
            }
            return ['success' => false, 'error' => $msg, 'sql' => $sql ?? ''];
        }
    }

    /**
     * Rename a table using a PDO connection
     *
     * @param PDO $conn PDO connection
     * @param string $oldName Old table name
     * @param string $newName New table name
     * @return array ['success' => bool, 'error' => string|null]
     */
    public static function renameTablePDO(PDO $conn, string $oldName, string $newName): array
    {
        try {
            $driver = $conn->getAttribute(PDO::ATTR_DRIVER_NAME);

            if ($driver === 'odbc') {
                // Access doesn't support ALTER TABLE RENAME directly
                // Use a workaround: SELECT INTO new, then DROP old
                $sql = "SELECT * INTO [$newName] FROM [$oldName]";
                $conn->exec($sql);
                $conn->exec("DROP TABLE [$oldName]");
            } elseif ($driver === 'sqlite') {
                $sql = "ALTER TABLE [$oldName] RENAME TO [$newName]";
                $conn->exec($sql);
            } elseif ($driver === 'mysql') {
                $sql = "RENAME TABLE [$oldName] TO [$newName]";
                $conn->exec($sql);
            } else {
                // SQL Server
                $sql = "EXEC sp_rename '$oldName', '$newName'";
                $conn->exec($sql);
            }

            return ['success' => true, 'error' => null];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
