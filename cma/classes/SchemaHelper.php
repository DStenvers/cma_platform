<?php

namespace Cma;

use App\Library\Database;

/**
 * CMA Schema Helper
 *
 * Provides database schema utilities including ADO type name conversion
 * and schema introspection methods.
 */
class SchemaHelper
{
    /**
     * ADO Schema Constants
     */
    public const ADO_SCHEMA_TABLES = 20;
    public const ADO_SCHEMA_COLUMNS = 4;
    public const ADO_SCHEMA_INDEXES = 12;
    public const ADO_SCHEMA_PRIMARY_KEYS = 28;
    public const ADO_SCHEMA_FOREIGN_KEYS = 27;

    /**
     * Static cache for schema data to avoid redundant DB calls
     */
    private static array $tablesCache = [];
    private static array $primaryKeysCache = [];
    private static array $foreignKeysCache = [];

    /**
     * Get connection identifier for caching
     */
    private static function getConnectionId($connection): string
    {
        if (is_string($connection)) {
            return $connection;
        }
        return spl_object_hash($connection);
    }

    /**
     * Resolve a connection to a PDO instance
     */
    private static function resolvePdo($connection): \PDO
    {
        if ($connection instanceof \PDO) {
            return $connection;
        }
        return Database::getConnection($connection);
    }

    /**
     * Get native ODBC handle for Access databases (needed for odbc_tables/odbc_columns)
     */
    private static function getNativeOdbc($connection)
    {
        if (!function_exists('odbc_connect')) return null;

        $connName = is_string($connection) ? $connection : null;
        $configKey = 'conn_' . ($connName ?? 'data');
        $dsn = \App\Library\Application::get($configKey, '');
        if (empty($dsn)) {
            $dsn = \App\Library\Application::get('conn_data', '');
        }
        if (empty($dsn)) return null;

        $nativeDsn = preg_replace('/^odbc:/i', '', $dsn);
        return @odbc_connect($nativeDsn, '', '', SQL_CUR_USE_ODBC) ?: null;
    }

    /**
     * Get list of tables from database
     *
     * @param \PDO|string $connection Database connection or connection name
     * @param string|null $filter Optional filter (table name pattern)
     * @param bool $includeHidden Include tables starting with underscore (default: false)
     * @return array Array of table info: ['name' => string, 'type' => string]
     */
    public static function getTables($connection, ?string $filter = null, bool $includeHidden = false): array
    {
        $connId = self::getConnectionId($connection);
        $cacheKey = $connId . ($includeHidden ? '|all' : '|filtered');

        // Check cache first
        if (isset(self::$tablesCache[$cacheKey])) {
            $cached = self::$tablesCache[$cacheKey];
            if ($filter !== null) {
                return array_values(array_filter($cached, fn($t) => stripos($t['name'], $filter) !== false));
            }
            return $cached;
        }

        $tables = [];

        try {
            $pdo = self::resolvePdo($connection);
            $dbType = Database::getDatabaseType($pdo);

            if ($dbType === 'access') {
                // MS Access: use native ODBC odbc_tables() — works without MSysObjects permission
                $odbc = self::getNativeOdbc($connection);
                if ($odbc && function_exists('odbc_tables')) {
                    $result = @odbc_tables($odbc, null, null, null, "'TABLE','VIEW'");
                    if ($result) {
                        while ($row = odbc_fetch_array($result)) {
                            $name = $row['TABLE_NAME'] ?? '';
                            $type = $row['TABLE_TYPE'] ?? 'TABLE';
                            if (empty($name) || substr($name, 0, 4) === 'MSys' || substr($name, 0, 4) === '~TMP') continue;
                            if (!$includeHidden && substr($name, 0, 1) === '_') continue;
                            $tables[] = ['name' => $name, 'type' => $type === 'VIEW' ? 'VIEW' : 'TABLE'];
                        }
                        @odbc_free_result($result);
                    }
                    @odbc_close($odbc);
                } else {
                    // Fallback: scan form definitions for table names
                    $formsDir = dirname(__DIR__) . '/../assets/forms';
                    $cmaFormsDir = dirname(__DIR__) . '/assets/forms/definitions';
                    foreach ([$formsDir, $cmaFormsDir] as $dir) {
                        if (!is_dir($dir)) continue;
                        foreach (glob($dir . '/*.json') as $file) {
                            $def = @json_decode(file_get_contents($file), true);
                            if (!empty($def['table'])) {
                                $tbl = $def['table'];
                                if (!in_array($tbl, array_column($tables, 'name'))) {
                                    if (!$includeHidden && substr($tbl, 0, 1) === '_') continue;
                                    $tables[] = ['name' => $tbl, 'type' => 'TABLE'];
                                }
                            }
                        }
                    }
                }
            } elseif ($dbType === 'sqlserver') {
                $stmt = $pdo->query("SELECT TABLE_NAME, TABLE_TYPE FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_TYPE IN ('BASE TABLE','VIEW') ORDER BY TABLE_NAME");
                while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                    $name = $row['TABLE_NAME'];
                    if (!$includeHidden && substr($name, 0, 1) === '_') continue;
                    $tables[] = ['name' => $name, 'type' => $row['TABLE_TYPE'] === 'VIEW' ? 'VIEW' : 'TABLE'];
                }
            } elseif ($dbType === 'sqlite') {
                $stmt = $pdo->query("SELECT name, type FROM sqlite_master WHERE type IN ('table','view') AND name NOT LIKE 'sqlite_%' ORDER BY name");
                while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                    $name = $row['name'];
                    if (!$includeHidden && substr($name, 0, 1) === '_') continue;
                    $tables[] = ['name' => $name, 'type' => strtoupper($row['type'])];
                }
            }

            usort($tables, fn($a, $b) => strcasecmp($a['name'], $b['name']));
            self::$tablesCache[$cacheKey] = $tables;

        } catch (\Exception $e) {
            // Return empty array on error
        }

        if ($filter !== null) {
            return array_values(array_filter($tables, fn($t) => stripos($t['name'], $filter) !== false));
        }

        return $tables;
    }

    /**
     * Get columns for a specific table
     *
     * @param \PDO|string $connection Database connection or connection name
     * @param string $tableName Table name
     * @param bool $includeHidden Include columns starting with underscore (default: false)
     * @return array Array of column info
     */
    public static function getColumns($connection, string $tableName, bool $includeHidden = false): array
    {
        $columns = [];

        try {
            $pdo = self::resolvePdo($connection);
            $dbType = Database::getDatabaseType($pdo);

            if ($dbType === 'sqlserver') {
                $stmt = $pdo->prepare("SELECT COLUMN_NAME, ORDINAL_POSITION, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH, NUMERIC_PRECISION, NUMERIC_SCALE, IS_NULLABLE, COLUMN_DEFAULT FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = ? ORDER BY ORDINAL_POSITION");
                $stmt->execute([$tableName]);
                while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                    $colName = $row['COLUMN_NAME'];
                    if (!$includeHidden && substr($colName, 0, 1) === '_') continue;
                    $typeName = $row['DATA_TYPE'] ?? '';
                    $length = $row['CHARACTER_MAXIMUM_LENGTH'] ?? null;
                    $dataType = self::sqlServerTypeToAdoType($typeName);
                    $columns[] = [
                        'name' => $colName,
                        'ordinal' => (int)($row['ORDINAL_POSITION'] ?? 0),
                        'dataType' => $dataType,
                        'dataTypeName' => self::getFieldTypeName($dataType),
                        'sqlTypeName' => $typeName . ($length ? "($length)" : ''),
                        'length' => $length,
                        'precision' => $row['NUMERIC_PRECISION'] ?? null,
                        'scale' => $row['NUMERIC_SCALE'] ?? null,
                        'nullable' => self::parseNullable($row['IS_NULLABLE'] ?? true),
                        'default' => $row['COLUMN_DEFAULT'] ?? null,
                        'description' => ''
                    ];
                }
            } elseif ($dbType === 'sqlite') {
                $stmt = $pdo->query("PRAGMA table_info(" . $pdo->quote($tableName) . ")");
                while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                    $colName = $row['name'];
                    if (!$includeHidden && substr($colName, 0, 1) === '_') continue;
                    $typeName = strtolower($row['type'] ?? 'text');
                    $dataType = self::sqliteTypeToAdoType($typeName);
                    $columns[] = [
                        'name' => $colName,
                        'ordinal' => (int)($row['cid'] ?? 0),
                        'dataType' => $dataType,
                        'dataTypeName' => self::getFieldTypeName($dataType),
                        'sqlTypeName' => $row['type'] ?? 'TEXT',
                        'length' => null,
                        'precision' => null,
                        'scale' => null,
                        'nullable' => !((int)($row['notnull'] ?? 0)),
                        'default' => $row['dflt_value'] ?? null,
                        'description' => ''
                    ];
                }
            } elseif ($dbType === 'access') {
                // MS Access: use native ODBC odbc_columns() for full column info
                $odbc = self::getNativeOdbc($connection);
                if ($odbc && function_exists('odbc_columns')) {
                    $colResult = @odbc_columns($odbc, null, null, $tableName);
                    if ($colResult) {
                        $ord = 0;
                        while ($row = odbc_fetch_array($colResult)) {
                            $colName = $row['COLUMN_NAME'] ?? '';
                            if (!$includeHidden && substr($colName, 0, 1) === '_') continue;
                            $typeName = $row['TYPE_NAME'] ?? 'VARCHAR';
                            $colSize = $row['COLUMN_SIZE'] ?? null;
                            $decimals = $row['DECIMAL_DIGITS'] ?? null;
                            $nullableVal = (int)($row['NULLABLE'] ?? 1);
                            $remarks = $row['REMARKS'] ?? '';
                            if ($remarks !== '') {
                                // Access REMARKS are stored in Windows-1252; always convert
                                if (!mb_check_encoding($remarks, 'UTF-8')) {
                                    $remarks = mb_convert_encoding($remarks, 'UTF-8', 'Windows-1252');
                                } else {
                                    // Even if valid UTF-8, try iconv cleanup for mojibake
                                    $cleaned = @iconv('UTF-8', 'UTF-8//IGNORE', $remarks);
                                    if ($cleaned !== false) $remarks = $cleaned;
                                }
                                // Remove invalid control characters
                                $remarks = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $remarks);
                            }
                            $dataType = self::odbcNativeTypeToAdoType($typeName);
                            $ord++;
                            $columns[] = [
                                'name' => $colName,
                                'ordinal' => (int)($row['ORDINAL_POSITION'] ?? $ord),
                                'dataType' => $dataType,
                                'dataTypeName' => self::getFieldTypeName($dataType),
                                'sqlTypeName' => $typeName . ($colSize && $colSize > 0 && $colSize < 65535 ? "($colSize)" : ''),
                                'length' => $colSize && $colSize > 0 && $colSize < 65535 ? (int)$colSize : null,
                                'precision' => $decimals !== null ? (int)$decimals : null,
                                'scale' => null,
                                'nullable' => $nullableVal !== 0,
                                'default' => $row['COLUMN_DEF'] ?? null,
                                'description' => $remarks
                            ];
                        }
                        @odbc_free_result($colResult);
                    }
                    @odbc_close($odbc);
                } else {
                    // Fallback: PDO getColumnMeta
                    $stmt = $pdo->prepare("SELECT TOP 1 * FROM [{$tableName}]");
                    $stmt->execute();
                    $colCount = $stmt->columnCount();
                    for ($i = 0; $i < $colCount; $i++) {
                        $meta = $stmt->getColumnMeta($i);
                        $colName = $meta['name'] ?? '';
                        if (!$includeHidden && substr($colName, 0, 1) === '_') continue;
                        $nativeType = $meta['native_type'] ?? '';
                        $len = $meta['len'] ?? null;
                        $dataType = self::odbcNativeTypeToAdoType($nativeType);
                        $columns[] = [
                            'name' => $colName,
                            'ordinal' => $i + 1,
                            'dataType' => $dataType,
                            'dataTypeName' => self::getFieldTypeName($dataType),
                            'sqlTypeName' => $nativeType . ($len && $len > 0 && $len < 65535 ? "($len)" : ''),
                            'length' => $len && $len > 0 && $len < 65535 ? $len : null,
                            'precision' => $meta['precision'] ?? null,
                            'scale' => null,
                            'nullable' => in_array('nullable', $meta['flags'] ?? []),
                            'default' => null,
                            'description' => ''
                        ];
                    }
                }
            }

            usort($columns, fn($a, $b) => $a['ordinal'] <=> $b['ordinal']);

        } catch (\Exception $e) {
            // Return empty array on error
        }

        return $columns;
    }

    /**
     * Get primary key columns for a table
     *
     * @param \PDO|string $connection Database connection or connection name
     * @param string $tableName Table name
     * @return array Array of primary key column names
     */
    public static function getPrimaryKeys($connection, string $tableName): array
    {
        $connId = self::getConnectionId($connection);
        $cacheKey = $connId . '|' . strtolower($tableName);

        if (isset(self::$primaryKeysCache[$cacheKey])) {
            return self::$primaryKeysCache[$cacheKey];
        }

        $keys = [];

        try {
            $pdo = self::resolvePdo($connection);
            $dbType = Database::getDatabaseType($pdo);

            if ($dbType === 'sqlserver') {
                $stmt = $pdo->prepare("SELECT kcu.COLUMN_NAME FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS tc JOIN INFORMATION_SCHEMA.KEY_COLUMN_USAGE kcu ON tc.CONSTRAINT_NAME = kcu.CONSTRAINT_NAME WHERE tc.CONSTRAINT_TYPE = 'PRIMARY KEY' AND tc.TABLE_NAME = ? ORDER BY kcu.ORDINAL_POSITION");
                $stmt->execute([$tableName]);
                while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                    $keys[] = $row['COLUMN_NAME'];
                }
            } elseif ($dbType === 'sqlite') {
                $stmt = $pdo->query("PRAGMA table_info(" . $pdo->quote($tableName) . ")");
                while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                    if ((int)($row['pk'] ?? 0) > 0) {
                        $keys[] = $row['name'];
                    }
                }
            } elseif ($dbType === 'access') {
                // ODBC: use getColumnMeta to detect autoincrement (typical PK)
                try {
                    $stmt = $pdo->prepare("SELECT TOP 1 * FROM [{$tableName}]");
                    $stmt->execute();
                    $colCount = $stmt->columnCount();
                    for ($i = 0; $i < $colCount; $i++) {
                        $meta = $stmt->getColumnMeta($i);
                        if (in_array('auto_increment', $meta['flags'] ?? []) ||
                            strcasecmp($meta['name'] ?? '', 'ID') === 0) {
                            $keys[] = $meta['name'];
                            break;
                        }
                    }
                } catch (\Exception $e) {
                    // Fallback: assume 'ID' as PK
                    $keys[] = 'ID';
                }
            }

            self::$primaryKeysCache[$cacheKey] = $keys;

        } catch (\Exception $e) {
            // Return empty array on error
        }

        return $keys;
    }

    /**
     * Get foreign key relationships for a table
     *
     * @param \PDO|string $connection Database connection or connection name
     * @param string $tableName Table name (can be FK table or PK table)
     * @return array Array of relationship info
     */
    public static function getRelationships($connection, string $tableName): array
    {
        $relationships = [];

        // Get all foreign keys (cached)
        $allFks = self::getAllForeignKeys($connection);

        if (!empty($allFks)) {
            foreach ($allFks as $fk) {
                if (strcasecmp($fk['fkTable'], $tableName) === 0 ||
                    strcasecmp($fk['pkTable'], $tableName) === 0) {
                    $relationships[] = $fk;
                }
            }
        }

        // If no FK schema available, try to infer from column naming conventions
        if (empty($relationships)) {
            $relationships = self::inferRelationships($connection, $tableName);
        }

        return $relationships;
    }

    /**
     * Get all foreign keys from database (cached)
     *
     * @param \PDO|string $connection Database connection
     * @return array All foreign key relationships
     */
    private static function getAllForeignKeys($connection): array
    {
        $connId = self::getConnectionId($connection);

        if (isset(self::$foreignKeysCache[$connId])) {
            return self::$foreignKeysCache[$connId];
        }

        $allFks = [];

        try {
            $pdo = self::resolvePdo($connection);
            $dbType = Database::getDatabaseType($pdo);

            if ($dbType === 'sqlserver') {
                $stmt = $pdo->query("SELECT fk.name AS FK_NAME, tp.name AS PK_TABLE_NAME, cp.name AS PK_COLUMN_NAME, tr.name AS FK_TABLE_NAME, cr.name AS FK_COLUMN_NAME FROM sys.foreign_keys fk JOIN sys.foreign_key_columns fkc ON fk.object_id = fkc.constraint_object_id JOIN sys.tables tp ON fkc.referenced_object_id = tp.object_id JOIN sys.columns cp ON fkc.referenced_object_id = cp.object_id AND fkc.referenced_column_id = cp.column_id JOIN sys.tables tr ON fkc.parent_object_id = tr.object_id JOIN sys.columns cr ON fkc.parent_object_id = cr.object_id AND fkc.parent_column_id = cr.column_id");
                while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                    $allFks[] = [
                        'fkTable' => $row['FK_TABLE_NAME'],
                        'fkColumn' => $row['FK_COLUMN_NAME'],
                        'pkTable' => $row['PK_TABLE_NAME'],
                        'pkColumn' => $row['PK_COLUMN_NAME'],
                        'constraintName' => $row['FK_NAME']
                    ];
                }
            } elseif ($dbType === 'sqlite') {
                // Get all tables, then PRAGMA foreign_key_list for each
                $tablesStmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
                while ($tRow = $tablesStmt->fetch(\PDO::FETCH_ASSOC)) {
                    $tblName = $tRow['name'];
                    $fkStmt = $pdo->query("PRAGMA foreign_key_list(" . $pdo->quote($tblName) . ")");
                    while ($fk = $fkStmt->fetch(\PDO::FETCH_ASSOC)) {
                        $allFks[] = [
                            'fkTable' => $tblName,
                            'fkColumn' => $fk['from'],
                            'pkTable' => $fk['table'],
                            'pkColumn' => $fk['to'],
                            'constraintName' => ''
                        ];
                    }
                }
            }
            // Access via ODBC doesn't expose foreign keys easily — skip

            self::$foreignKeysCache[$connId] = $allFks;

        } catch (\Exception $e) {
            self::$foreignKeysCache[$connId] = [];
        }

        return $allFks;
    }

    /**
     * Map SQL Server type name to ADO type code
     */
    private static function sqlServerTypeToAdoType(string $typeName): int
    {
        $map = [
            'int' => 3, 'bigint' => 20, 'smallint' => 2, 'tinyint' => 16,
            'bit' => 11, 'decimal' => 14, 'numeric' => 14, 'money' => 6, 'smallmoney' => 6,
            'float' => 5, 'real' => 4,
            'char' => 129, 'varchar' => 200, 'nchar' => 130, 'nvarchar' => 202, 'text' => 201, 'ntext' => 203,
            'date' => 7, 'time' => 134, 'datetime' => 135, 'datetime2' => 135, 'smalldatetime' => 135, 'datetimeoffset' => 135,
            'binary' => 128, 'varbinary' => 204, 'image' => 205,
            'uniqueidentifier' => 72, 'xml' => 201,
        ];
        return $map[strtolower($typeName)] ?? 200;
    }

    /**
     * Map SQLite type name to ADO type code
     */
    private static function sqliteTypeToAdoType(string $typeName): int
    {
        $t = strtolower($typeName);
        if (str_contains($t, 'int')) return 3;
        if (str_contains($t, 'text') || str_contains($t, 'char') || str_contains($t, 'clob')) return 200;
        if (str_contains($t, 'blob') || $t === '') return 128;
        if (str_contains($t, 'real') || str_contains($t, 'floa') || str_contains($t, 'doub')) return 5;
        if (str_contains($t, 'bool')) return 11;
        if (str_contains($t, 'date') || str_contains($t, 'time')) return 135;
        return 200;
    }

    /**
     * Map ODBC native type name to ADO type code
     */
    private static function odbcNativeTypeToAdoType(string $nativeType): int
    {
        $map = [
            // Integer types (Access: COUNTER/LONG/SHORT/BYTE, ACE/PDO: INTEGER/SMALLINT/TINYINT)
            'COUNTER' => 3, 'LONG' => 3, 'INTEGER' => 3, 'INT' => 3, 'AUTONUMBER' => 3,
            'SHORT' => 2, 'SMALLINT' => 2,
            'BYTE' => 16, 'TINYINT' => 16,
            'BIGINT' => 20,
            // Boolean
            'BIT' => 11, 'YESNO' => 11, 'BOOLEAN' => 11,
            // Floating point / currency
            'CURRENCY' => 6, 'MONEY' => 6,
            'DOUBLE' => 5, 'FLOAT' => 5,
            'SINGLE' => 4, 'REAL' => 4,
            'NUMERIC' => 131, 'DECIMAL' => 131, 'NUMBER' => 131,
            // Text
            'VARCHAR' => 200, 'LONGCHAR' => 201, 'CHAR' => 129, 'TEXT' => 201,
            'NVARCHAR' => 200, 'NCHAR' => 129, 'NTEXT' => 201,
            // Date/time
            'DATETIME' => 135, 'DATE' => 7, 'TIME' => 134,
            'SMALLDATETIME' => 135, 'TIMESTAMP' => 135,
            // Binary
            'LONGBINARY' => 205, 'BINARY' => 128, 'VARBINARY' => 204,
            'IMAGE' => 205, 'OLEOBJECT' => 205, 'OLE OBJECT' => 205,
            // GUID
            'GUID' => 72, 'UNIQUEIDENTIFIER' => 72,
        ];
        return $map[strtoupper($nativeType)] ?? 200;
    }

    /**
     * Infer relationships from column naming conventions
     * Looks for:
     * - Columns ending in _id or ID that match table names
     * - Columns starting with 'fk' (foreign key prefix)
     *
     * @param \PDO|string $connection Database connection
     * @param string $tableName Table name
     * @return array Inferred relationships
     */
    private static function inferRelationships($connection, string $tableName): array
    {
        $relationships = [];
        $columns = self::getColumns($connection, $tableName);
        $tables = self::getTables($connection);
        $tableNames = array_column($tables, 'name');

        foreach ($columns as $col) {
            $colName = $col['name'];
            $potentialTable = null;

            // Check for _id or ID suffix (e.g., deelnemer_id, DeelnemerID)
            if (preg_match('/^(.+?)_?[Ii][Dd]$/', $colName, $matches)) {
                $potentialTable = $matches[1];
                // Also strip 'fk' prefix if present (e.g., fkDeelnemerID -> Deelnemer)
                $potentialTable = preg_replace('/^fk/i', '', $potentialTable);
            }
            // Check for 'fk' prefix without ID suffix (e.g., fkToetsPerDeelnemer)
            elseif (preg_match('/^fk(.+)$/i', $colName, $matches)) {
                $potentialTable = $matches[1];
            }

            if ($potentialTable === null) {
                continue;
            }

            // Try to find matching table (case-insensitive)
            foreach ($tableNames as $tbl) {
                // Normalize table name: remove 'tbl' prefix
                $tblNormalized = preg_replace('/^tbl/i', '', $tbl);

                // Try various matching strategies
                if (strcasecmp($potentialTable, $tbl) === 0 ||
                    strcasecmp($potentialTable, $tblNormalized) === 0 ||
                    strcasecmp($potentialTable . 's', $tblNormalized) === 0 ||
                    strcasecmp($potentialTable . 'en', $tblNormalized) === 0 ||  // Dutch plural
                    // Also try matching without trailing 'Per' pattern (e.g., fkToetsPerDeelnemer -> tblToetsen)
                    (preg_match('/^(.+)Per.+$/i', $potentialTable, $perMatches) &&
                     (strcasecmp($perMatches[1], $tblNormalized) === 0 ||
                      strcasecmp($perMatches[1] . 'en', $tblNormalized) === 0))) {

                    // Get PK of target table
                    $pks = self::getPrimaryKeys($connection, $tbl);
                    $pkCol = !empty($pks) ? $pks[0] : 'ID';

                    $relationships[] = [
                        'fkTable' => $tableName,
                        'fkColumn' => $colName,
                        'pkTable' => $tbl,
                        'pkColumn' => $pkCol,
                        'constraintName' => '',
                        'inferred' => true
                    ];
                    break;
                }
            }
        }

        return $relationships;
    }

    /**
     * Get complete schema info for a table (columns + relationships)
     *
     * @param \PDO|string $connection Database connection
     * @param string $tableName Table name
     * @return array Table schema info
     */
    public static function getTableSchema($connection, string $tableName): array
    {
        return [
            'name' => $tableName,
            'columns' => self::getColumns($connection, $tableName),
            'primaryKeys' => self::getPrimaryKeys($connection, $tableName),
            'relationships' => self::getRelationships($connection, $tableName)
        ];
    }

    /**
     * ODBC SQL Type Constants (for MS Access ODBC driver)
     */
    public const SQL_CHAR = 1;
    public const SQL_NUMERIC = 2;
    public const SQL_DECIMAL = 3;
    public const SQL_INTEGER = 4;
    public const SQL_SMALLINT = 5;
    public const SQL_FLOAT = 6;
    public const SQL_REAL = 7;
    public const SQL_DOUBLE = 8;
    public const SQL_VARCHAR = 12;
    public const SQL_LONGVARCHAR = -1;
    public const SQL_BINARY = -2;
    public const SQL_VARBINARY = -3;
    public const SQL_LONGVARBINARY = -4;
    public const SQL_BIT = -7;
    public const SQL_TINYINT = -6;
    public const SQL_BIGINT = -5;

    // ODBC 3.x date/time types
    public const SQL_TYPE_DATE = 91;
    public const SQL_TYPE_TIME = 92;
    public const SQL_TYPE_TIMESTAMP = 93;

    // ODBC 2.x deprecated date/time types (still used by MS Access ODBC driver)
    // WARNING: SQL_TIMESTAMP_DEPRECATED (11) conflicts with ADO_BOOLEAN (11)
    public const SQL_DATE_DEPRECATED = 9;
    public const SQL_TIME_DEPRECATED = 10;
    public const SQL_TIMESTAMP_DEPRECATED = 11;

    /**
     * Categorize column type for UI purposes
     * Supports ADO types, ODBC SQL types, and string type names
     *
     * @param int|string $dataType ADO/ODBC data type code or string type name
     * @return string Category: 'text', 'number', 'date', 'boolean', 'binary'
     */
    public static function categorizeType($dataType): string
    {
        // Handle string type names (from PDO metadata fallback)
        if (is_string($dataType)) {
            return self::categorizeTypeByName($dataType);
        }

        $dataType = (int)$dataType;

        // Date/time types - ODBC 3.x
        if (in_array($dataType, [self::SQL_TYPE_DATE, self::SQL_TYPE_TIME, self::SQL_TYPE_TIMESTAMP])) {
            return 'date';
        }

        // Date/time types - ODBC 2.x deprecated (MS Access ODBC driver uses these)
        // IMPORTANT: Check BEFORE boolean because SQL_TIMESTAMP_DEPRECATED (11) conflicts with ADO_BOOLEAN (11)
        // MS Access uses SQL_BIT (-7) for Yes/No fields, not type 11
        if (in_array($dataType, [self::SQL_DATE_DEPRECATED, self::SQL_TIME_DEPRECATED, self::SQL_TIMESTAMP_DEPRECATED])) {
            return 'date';
        }

        // Date/time types - ADO
        if (in_array($dataType, [self::ADO_DATE, self::ADO_DBDATE, self::ADO_DBTIME, self::ADO_DBTIMESTAMP])) {
            return 'date';
        }

        // Boolean - ODBC only (SQL_BIT = -7)
        // Note: We don't check ADO_BOOLEAN (11) here because it conflicts with SQL_TIMESTAMP_DEPRECATED
        // MS Access Yes/No fields use SQL_BIT (-7), not type 11
        if ($dataType === self::SQL_BIT) {
            return 'boolean';
        }

        // Numeric types - ADO
        if (in_array($dataType, [
            self::ADO_TINYINT, self::ADO_SMALLINT, self::ADO_INTEGER, self::ADO_BIGINT,
            self::ADO_UNSIGNED_TINYINT, self::ADO_UNSIGNED_SMALLINT, self::ADO_UNSIGNED_INT, self::ADO_UNSIGNED_BIGINT,
            self::ADO_SINGLE, self::ADO_DOUBLE, self::ADO_CURRENCY, self::ADO_DECIMAL, self::ADO_NUMERIC
        ])) {
            return 'number';
        }

        // Numeric types - ODBC
        if (in_array($dataType, [
            self::SQL_TINYINT, self::SQL_SMALLINT, self::SQL_INTEGER, self::SQL_BIGINT,
            self::SQL_FLOAT, self::SQL_REAL, self::SQL_DOUBLE, self::SQL_NUMERIC, self::SQL_DECIMAL
        ])) {
            return 'number';
        }

        // Binary types - ADO
        if (in_array($dataType, [self::ADO_BINARY, self::ADO_VARBINARY, self::ADO_LONGVARBINARY])) {
            return 'binary';
        }

        // Binary types - ODBC
        if (in_array($dataType, [self::SQL_BINARY, self::SQL_VARBINARY, self::SQL_LONGVARBINARY])) {
            return 'binary';
        }

        // Default to text
        return 'text';
    }

    /**
     * Categorize column type by string name
     *
     * @param string $typeName Type name from PDO metadata or database
     * @return string Category: 'text', 'number', 'date', 'boolean', 'binary'
     */
    private static function categorizeTypeByName(string $typeName): string
    {
        $typeName = strtolower(trim($typeName));

        // Date/time types
        if (in_array($typeName, ['date', 'time', 'datetime', 'datetime2', 'smalldatetime', 'timestamp', 'datetimeoffset'])) {
            return 'date';
        }
        if (strpos($typeName, 'date') !== false || strpos($typeName, 'time') !== false) {
            return 'date';
        }

        // Boolean types
        if (in_array($typeName, ['bit', 'bool', 'boolean', 'yesno'])) {
            return 'boolean';
        }

        // Numeric types
        if (in_array($typeName, ['int', 'integer', 'bigint', 'smallint', 'tinyint', 'float', 'real', 'double',
                                  'decimal', 'numeric', 'money', 'smallmoney', 'number', 'long', 'short', 'byte',
                                  'currency', 'single', 'counter', 'autonumber'])) {
            return 'number';
        }
        if (preg_match('/^(int|float|decimal|numeric|double|real)/i', $typeName)) {
            return 'number';
        }

        // Binary types
        if (in_array($typeName, ['binary', 'varbinary', 'image', 'blob', 'longbinary', 'oleobject', 'ole object'])) {
            return 'binary';
        }
        if (strpos($typeName, 'binary') !== false || strpos($typeName, 'blob') !== false) {
            return 'binary';
        }

        // Default to text
        return 'text';
    }

    /**
     * Parse nullable value from schema (can be string 'YES'/'NO' or boolean)
     *
     * @param mixed $value Nullable value from schema
     * @return bool
     */
    private static function parseNullable($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_string($value)) {
            return strtoupper($value) === 'YES' || $value === '1' || strtolower($value) === 'true';
        }
        return (bool)$value;
    }

    /**
     * ADO Type Constants
     */
    public const ADO_EMPTY = 0;
    public const ADO_TINYINT = 16;
    public const ADO_SMALLINT = 2;
    public const ADO_INTEGER = 3;
    public const ADO_BIGINT = 20;
    public const ADO_UNSIGNED_TINYINT = 17;
    public const ADO_UNSIGNED_SMALLINT = 18;
    public const ADO_UNSIGNED_INT = 19;
    public const ADO_UNSIGNED_BIGINT = 21;
    public const ADO_SINGLE = 4;
    public const ADO_DOUBLE = 5;
    public const ADO_CURRENCY = 6;
    public const ADO_DECIMAL = 14;
    public const ADO_NUMERIC = 131;
    public const ADO_BOOLEAN = 11;
    public const ADO_ERROR = 10;
    public const ADO_USER_DEFINED = 132;
    public const ADO_VARIANT = 12;
    public const ADO_IDISPATCH = 9;
    public const ADO_IUNKNOWN = 13;
    public const ADO_GUID = 72;
    public const ADO_DATE = 7;
    public const ADO_DBDATE = 133;
    public const ADO_DBTIME = 134;
    public const ADO_DBTIMESTAMP = 135;
    public const ADO_BSTR = 8;
    public const ADO_CHAR = 129;
    public const ADO_VARCHAR = 200;
    public const ADO_LONGVARCHAR = 201;
    public const ADO_WCHAR = 130;
    public const ADO_VARWCHAR = 202;
    public const ADO_LONGVARWCHAR = 203;
    public const ADO_BINARY = 128;
    public const ADO_VARBINARY = 204;
    public const ADO_LONGVARBINARY = 205;

    /**
     * Convert an ADO or ODBC data type constant to its human-readable name
     *
     * @param int $dataType The ADO or ODBC type constant
     * @return string The human-readable type name
     */
    public static function getFieldTypeName(int $dataType): string
    {
        // ODBC SQL types first (negative values or specific positive values)
        switch ($dataType) {
            case self::SQL_TYPE_DATE:
                return 'Date';
            case self::SQL_TYPE_TIME:
                return 'Time';
            case self::SQL_TYPE_TIMESTAMP:
                return 'DateTime';
            case self::SQL_BIT:
                return 'Boolean';
            case self::SQL_TINYINT:
                return 'TinyInt';
            case self::SQL_SMALLINT:
                return 'SmallInt';
            case self::SQL_INTEGER:
                return 'Integer';
            case self::SQL_BIGINT:
                return 'BigInt';
            case self::SQL_FLOAT:
            case self::SQL_REAL:
            case self::SQL_DOUBLE:
                return 'Double';
            case self::SQL_NUMERIC:
            case self::SQL_DECIMAL:
                return 'Decimal';
            case self::SQL_CHAR:
                return 'Char';
            case self::SQL_VARCHAR:
                return 'VarChar';
            case self::SQL_LONGVARCHAR:
                return 'LongVarChar';
            case self::SQL_BINARY:
                return 'Binary';
            case self::SQL_VARBINARY:
                return 'VarBinary';
            case self::SQL_LONGVARBINARY:
                return 'LongVarBinary';
        }

        // ADO types
        switch ($dataType) {
            case self::ADO_EMPTY:
                return 'Empty';
            case self::ADO_TINYINT:
                return 'TinyInt';
            case self::ADO_SMALLINT:
                return 'SmallInt';
            case self::ADO_INTEGER:
                return 'Integer';
            case self::ADO_BIGINT:
                return 'BigInt';
            case self::ADO_UNSIGNED_TINYINT:
                return 'UnsignedTinyInt';
            case self::ADO_UNSIGNED_SMALLINT:
                return 'UnsignedSmallInt';
            case self::ADO_UNSIGNED_INT:
                return 'UnsignedInt';
            case self::ADO_UNSIGNED_BIGINT:
                return 'UnsignedBigInt';
            case self::ADO_SINGLE:
                return 'Single';
            case self::ADO_DOUBLE:
                return 'Double';
            case self::ADO_CURRENCY:
                return 'Currency';
            case self::ADO_DECIMAL:
                return 'Decimal';
            case self::ADO_NUMERIC:
                return 'Numeric';
            case self::ADO_BOOLEAN:
                return 'Boolean';
            case self::ADO_ERROR:
                return 'Error';
            case self::ADO_USER_DEFINED:
                return 'UserDefined';
            case self::ADO_VARIANT:
                return 'Variant';
            case self::ADO_IDISPATCH:
                return 'IDispatch';
            case self::ADO_IUNKNOWN:
                return 'IUnknown';
            case self::ADO_GUID:
                return 'GUID';
            case self::ADO_DATE:
                return 'Date';
            case self::ADO_DBDATE:
                return 'DBDate';
            case self::ADO_DBTIME:
                return 'DBTime';
            case self::ADO_DBTIMESTAMP:
                return 'DBTimeStamp';
            case self::ADO_BSTR:
                return 'BSTR';
            case self::ADO_CHAR:
                return 'Char';
            case self::ADO_VARCHAR:
                return 'VarChar';
            case self::ADO_LONGVARCHAR:
                return 'LongVarChar';
            case self::ADO_WCHAR:
                return 'WChar';
            case self::ADO_VARWCHAR:
                return 'VarWChar';
            case self::ADO_LONGVARWCHAR:
                return 'LongVarWChar';
            case self::ADO_BINARY:
                return 'Binary';
            case self::ADO_VARBINARY:
                return 'VarBinary';
            case self::ADO_LONGVARBINARY:
                return 'LongVarBinary';
            default:
                return 'Undefined by ADO';
        }
    }

    /**
     * Get the SQL Server data type name for an ADO or ODBC type
     *
     * @param int $dataType The ADO or ODBC type constant
     * @param int|null $length Optional character length
     * @return string The SQL Server type name
     */
    public static function getSqlServerTypeName(int $dataType, ?int $length = null): string
    {
        // ODBC SQL types
        switch ($dataType) {
            case self::SQL_TYPE_DATE:
                return 'date';
            case self::SQL_TYPE_TIME:
                return 'time';
            case self::SQL_TYPE_TIMESTAMP:
                return 'datetime';
            case self::SQL_BIT:
                return 'bit';
            case self::SQL_TINYINT:
                return 'tinyint';
            case self::SQL_SMALLINT:
                return 'smallint';
            case self::SQL_INTEGER:
                return 'int';
            case self::SQL_BIGINT:
                return 'bigint';
            case self::SQL_FLOAT:
            case self::SQL_REAL:
            case self::SQL_DOUBLE:
                return 'float';
            case self::SQL_NUMERIC:
            case self::SQL_DECIMAL:
                return 'decimal';
            case self::SQL_CHAR:
                return 'char' . ($length ? "($length)" : '');
            case self::SQL_VARCHAR:
            case self::SQL_LONGVARCHAR:
                return 'varchar' . ($length ? "($length)" : '');
            case self::SQL_BINARY:
            case self::SQL_VARBINARY:
            case self::SQL_LONGVARBINARY:
                return 'varbinary' . ($length ? "($length)" : '');
        }

        // ADO types
        switch ($dataType) {
            case self::ADO_TINYINT:
                return 'tinyint';
            case self::ADO_SMALLINT:
                return 'smallint';
            case self::ADO_INTEGER:
                return 'int';
            case self::ADO_BIGINT:
                return 'bigint';
            case self::ADO_SINGLE:
                return 'real';
            case self::ADO_DOUBLE:
                return 'float';
            case self::ADO_CURRENCY:
                return 'money';
            case self::ADO_DECIMAL:
            case self::ADO_NUMERIC:
                return 'decimal';
            case self::ADO_BOOLEAN:
                return 'bit';
            case self::ADO_GUID:
                return 'uniqueidentifier';
            case self::ADO_DATE:
            case self::ADO_DBTIMESTAMP:
                return 'datetime';
            case self::ADO_DBDATE:
                return 'date';
            case self::ADO_DBTIME:
                return 'time';
            case self::ADO_CHAR:
                return 'char' . ($length ? "($length)" : '');
            case self::ADO_VARCHAR:
            case self::ADO_LONGVARCHAR:
                if ($length && $length > 8000) {
                    return 'varchar(max)';
                }
                return 'varchar' . ($length ? "($length)" : '');
            case self::ADO_WCHAR:
                return 'nchar' . ($length ? "($length)" : '');
            case self::ADO_VARWCHAR:
            case self::ADO_LONGVARWCHAR:
                if ($length && $length > 4000) {
                    return 'nvarchar(max)';
                }
                return 'nvarchar' . ($length ? "($length)" : '');
            case self::ADO_BINARY:
            case self::ADO_VARBINARY:
            case self::ADO_LONGVARBINARY:
                if ($length && $length > 8000) {
                    return 'varbinary(max)';
                }
                return 'varbinary' . ($length ? "($length)" : '');
            default:
                return 'sql_variant';
        }
    }
}

/**
 * Schema RecordSet Wrapper
 *
 * Wraps a schema array to provide recordset-like access for SqlHelper compatibility
 */
class SchemaRecordSet
{
    /** @var array Schema rows */
    private array $data;

    /** @var int Current position */
    private int $position = 0;

    /** @var array|null Current row as fields */
    public ?array $fields = null;

    /** @var bool EOF indicator */
    public bool $EOF = false;

    /**
     * Create from schema array
     */
    public function __construct(array $schemaData)
    {
        $this->data = $schemaData;
        $this->updateState();
    }

    /**
     * Find and move to a specific column
     *
     * @param string $columnName Column name to find
     * @return bool True if found
     */
    public function findColumn(string $columnName): bool
    {
        foreach ($this->data as $index => $row) {
            if (strcasecmp((string)($row['COLUMN_NAME'] ?? ''), $columnName) === 0) {
                $this->position = $index;
                $this->updateState();
                return true;
            }
        }
        // Not found - set EOF
        $this->EOF = true;
        $this->fields = null;
        return false;
    }

    /**
     * Update internal state based on position
     */
    private function updateState(): void
    {
        if ($this->position < count($this->data)) {
            $this->fields = $this->data[$this->position];
            $this->EOF = false;
        } else {
            $this->fields = null;
            $this->EOF = true;
        }
    }

    /**
     * Move to next row
     */
    public function MoveNext(): void
    {
        $this->position++;
        $this->updateState();
    }

    /**
     * Move to first row
     */
    public function MoveFirst(): void
    {
        $this->position = 0;
        $this->updateState();
    }
}
