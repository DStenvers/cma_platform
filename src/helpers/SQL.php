<?php

namespace App\Library;

/**
 * SQL Helper Class
 *
 * Provides SQL data formatting and conversion utilities for preparing values
 * to be inserted into SQL statements. Handles differences between database
 * types (Access, SQL Server, MySQL) for dates, booleans, strings, etc.
 *
 */
class SQL
{
    /**
     * Detect if using SQL Server vs Access/MySQL
     *
     * @param string|null $connectionString Connection string to check, or null to use default
     * @return bool True if SQL Server, false if Access/MySQL
     */
    private static function isSQLServer(?string $connectionString = null): bool
    {
        return Database::isSQLServer($connectionString);
    }

    /**
     * Format a number for SQL insertion
     * Converts empty values to NULL, handles decimal separators
     *
     * @param mixed $value The value to format
     * @return string Formatted SQL value (e.g., "123.45" or "null")
     */
    public static function postNumber($value): string
    {
        $strRetval = trim($value . '');
        if ($strRetval == '' || is_null($strRetval)) {
            return 'null';
        } else {
            return str_replace(',', '.', $strRetval);
        }
    }

    /**
     * Format a string for SQL insertion
     * Handles NULL values, escapes quotes based on database type
     *
     * @param mixed $value The string value to format
     * @param string|null $connectionString Connection string to determine database type
     * @return string Formatted SQL string (e.g., "'value'" or "null")
     */
    public static function postString($value, ?string $connectionString = null): string
    {
        $isSQLServer = self::isSQLServer($connectionString);
        $strRetval = trim($value . '');

        if ($strRetval != '') {
            if ($isSQLServer) {
                // SQL Server: Replace ' with '+char(39)+'
                $strRetval = str_ireplace("'", "'+char(39)+'", $strRetval);
            } else {
                // Access/MySQL: Replace ' with ' & chr(39) & '
                $strRetval = str_ireplace("'", "' & chr(39) & '", $strRetval);
            }
        }

        return $strRetval == '' ? 'null' : "'" . $strRetval . "'";
    }

    /**
     * Format a boolean for SQL insertion
     * SQL Server: 1/0, Access: True/False
     *
     * @param mixed $value The boolean value to format
     * @param string|null $connectionString Connection string to determine database type
     * @return string Formatted SQL boolean ("1"/"0" or "True"/"False")
     */
    public static function postBoolean($value, ?string $connectionString = null): string
    {
        $isSQLServer = self::isSQLServer($connectionString);

        // Check if it's a boolean type (VBScript type 11)
        if (is_bool($value)) {
            $bTmp = $value;
        } else {
            $bTmp = $value != '';
        }

        if ($isSQLServer) {
            return $bTmp ? '1' : '0';
        } else {
            return $bTmp ? 'True' : 'False';
        }
    }

    /**
     * Format a GUID for SQL insertion
     * SQL Server: strips braces and quotes. Access: plain quoted string.
     *
     * @param string $guid The GUID value to format
     * @param string|null $connectionString Connection string to determine database type
     * @return string Formatted SQL GUID
     */
    public static function postGuid(string $guid, ?string $connectionString = null): string
    {
        // Strip any existing braces
        $sTemp = str_replace('}', '', str_replace('{', '', $guid));
        return "'" . $sTemp . "'";
    }

    /**
     * Build a GUID comparison clause for WHERE conditions.
     *
     * Access Replication ID (GUID) columns cannot be compared with = through ODBC.
     * The = operator fails silently (returns no rows). LIKE forces text comparison
     * and works correctly. SQL Server handles = normally.
     *
     * Usage: $sql .= ' WHERE ' . SQL::guidEquals('Guid', $userGuid);
     *
     * @param string $column The column name
     * @param string $guid The GUID value to compare
     * @param string|null $connectionString Connection string to determine database type
     * @return string Complete comparison clause, e.g. "Guid LIKE '%...%'" or "Guid = '...'"
     */
    public static function guidEquals(string $column, string $guid, ?string $connectionString = null): string
    {
        $isSQLServer = self::isSQLServer($connectionString);
        $cleanGuid = str_replace('}', '', str_replace('{', '', $guid));

        if ($isSQLServer) {
            return $column . " = '" . $cleanGuid . "'";
        } else {
            // Access ODBC: = doesn't work for Replication ID columns, LIKE with % wildcards does
            return $column . " LIKE '%" . $cleanGuid . "%'";
        }
    }

    /**
     * Format a date from day/month/year components
     *
     * @param mixed $dayValue Day component
     * @param mixed $monthValue Month component
     * @param mixed $yearValue Year component
     * @param string|null $connectionString Connection string to determine database type
     * @return string Formatted SQL date or "NULL"
     */
    public static function postDate($dayValue, $monthValue, $yearValue, ?string $connectionString = null): string
    {
        $isSQLServer = self::isSQLServer($connectionString);

        // Note: Original code has unreachable logic after return 'NULL'
        // Keeping this behavior for compatibility
        if ($dayValue == '' || $monthValue == '' || $yearValue == '') {
            return 'NULL';
        }

        $strTmp = $monthValue . '/' . $dayValue . '/' . $yearValue;
        if (strtotime($strTmp) !== false) {
            if ($isSQLServer) {
                return "CAST('" . str_ireplace('/', '-', $strTmp) . "' AS DATE)";
            } else {
                return '#' . $strTmp . '#';
            }
        }

        return 'NULL';
    }

    /**
     * Format a date value (date only, no time)
     *
     * @param mixed $dateValue The date value to format
     * @param string|null $connectionString Connection string to determine database type
     * @return string Formatted SQL date or "NULL"
     */
    public static function postDateOnly($dateValue, ?string $connectionString = null): string
    {
        $isSQLServer = self::isSQLServer($connectionString);

        if (!is_null($dateValue) && strtotime($dateValue) !== false) {
            if ($isSQLServer) {
                $strTmp = date('Y', strtotime($dateValue)) . '/' .
                          date('n', strtotime($dateValue)) . '/' .
                          date('j', strtotime($dateValue));
                return "CAST('" . str_ireplace('/', '-', $strTmp) . "' AS DATE)";
            } else {
                $strTmp = date('n', strtotime($dateValue)) . '/' .
                          date('j', strtotime($dateValue)) . '/' .
                          date('Y', strtotime($dateValue));
                $strTmp .= ' ' . date('G', strtotime($dateValue)) . ':' .
                           intval(date('i', strtotime($dateValue)));
                return '#' . $strTmp . '#';
            }
        }

        return 'NULL';
    }

    /**
     * Format a datetime value (date and time)
     *
     * @param mixed $dateValue The datetime value to format
     * @param string|null $connectionString Connection string to determine database type
     * @return string Formatted SQL datetime or "NULL"
     */
    public static function postDateTime($dateValue, ?string $connectionString = null): string
    {
        $isSQLServer = self::isSQLServer($connectionString);

        if (!is_null($dateValue) && strtotime($dateValue) !== false) {
            if ($isSQLServer) {
                $strTmp = date('Y', strtotime($dateValue)) . '/' .
                          date('n', strtotime($dateValue)) . '/' .
                          date('j', strtotime($dateValue));
                $strTmp .= ' ' . date('G', strtotime($dateValue)) . ':' .
                           intval(date('i', strtotime($dateValue)));
                return "CAST('" . str_ireplace('/', '-', $strTmp) . "' AS DATETIME)";
            } else {
                $strTmp = date('n', strtotime($dateValue)) . '/' .
                          date('j', strtotime($dateValue)) . '/' .
                          date('Y', strtotime($dateValue));
                $strTmp .= ' ' . date('G', strtotime($dateValue)) . ':' .
                           intval(date('i', strtotime($dateValue)));
                return '#' . $strTmp . '#';
            }
        }

        return 'NULL';
    }

    /**
     * Parse a date string and format for SQL
     * Expects format: DD-MM-YYYY or DD/MM/YYYY
     *
     * @param string $dateStr The date string to parse
     * @param string|null $connectionString Connection string to determine database type
     * @return string Formatted SQL date or "NULL"
     */
    public static function postDateStr(string $dateStr, ?string $connectionString = null): string
    {
        if ($dateStr == '') {
            return 'NULL';
        }

        $dateStr = str_replace('/', '-', $dateStr);

        // Extract day, month, year from DD-MM-YYYY format
        $parts = explode('-', $dateStr);
        if (count($parts) >= 3) {
            $day = $parts[0];
            $month = $parts[1];
            $year = substr($parts[2], 0, 4);

            return self::postDate($day, $month, $year, $connectionString);
        }

        return 'NULL';
    }

    /**
     * Parse a time string and format for SQL
     * Expects format: HH:MM or HH/MM
     *
     * @param string $timeStr The time string to parse
     * @return string Formatted SQL time (Access format: #HH:MM#) or "NULL"
     */
    public static function postTimeStr(string $timeStr): string
    {
        if ($timeStr == '') {
            return 'NULL';
        }

        $timeStr = str_replace('/', ':', $timeStr);

        // Extract hours and minutes
        if (strlen($timeStr) >= 4) {
            $hours = substr($timeStr, 0, 2);
            $minutes = substr($timeStr, 3, 2);
            return '#' . $hours . ':' . $minutes . '#';
        }

        return 'NULL';
    }

    /**
     * Convert SQL date format (MMDDYYYY) to real date
     *
     * @param string $sqlDate SQL date string in MMDDYYYY format
     * @return string Formatted date string (Y-m-d H:i:s)
     * @throws \Exception If date is invalid
     */
    public static function sqlDateToRealDate(string $sqlDate): string
    {
        // Extract month (first 2 chars), day (chars 3-4), year (last 4 chars)
        if (strlen($sqlDate) >= 8) {
            $month = substr($sqlDate, 0, 2);
            $day = substr($sqlDate, 2, 2);
            $year = substr($sqlDate, -4);

            $dateString = $month . '-' . $day . '-' . $year;

            if (is_null($dateString) || $dateString === '') {
                throw new \Exception('Type mismatch');
            }

            $timestamp = strtotime($dateString);
            if ($timestamp === false) {
                throw new \Exception('Type mismatch');
            }

            return date('Y-m-d H:i:s', $timestamp);
        }

        throw new \Exception('Type mismatch');
    }

    /**
     * Debug helper - output all POST data in a table
     * Calls lib_DebugPostContent() if available
     *
     * @return void
     */
    public static function debug(): void
    {
        if (function_exists('lib_DebugPostContent')) {
            echo lib_DebugPostContent();
        }
    }

    /**
     * Process SQL for database compatibility
     *
     * Converts SQL between Access and SQL Server dialects
     * Equivalent to ASP's lib_SQL_Process function
     *
     * @param mixed $connection Connection object or string (for SQL Server detection)
     * @param string $sql SQL query to process
     * @return string Processed SQL query
     */
    public static function processSQL($connection, string $sql): string
    {
        // Get connection string for database type detection
        if (is_string($connection)) {
            $connectionString = $connection;
        } elseif ($connection instanceof \PDO) {
            // Extract driver from PDO object
            $driver = $connection->getAttribute(\PDO::ATTR_DRIVER_NAME);
            // ODBC to Access is not SQL Server
            if ($driver === 'odbc') {
                $connectionString = 'ACCESS_VIA_ODBC'; // Force Access mode
            } else {
                $connectionString = null; // Use default detection
            }
        } else {
            $connectionString = null;
        }

        $isSQLServer = Database::isSQLServer($connectionString);

        if ($isSQLServer) {
            // SQL Server fixes for Access commands
            $sql = str_replace('&#39;', "''", $sql);

            // Date and time functions
            $sql = str_ireplace('FIRST( ', 'FIRST_VALUE( ', $sql);
            $sql = str_ireplace(' distinctrow ', ' DISTINCT ', $sql);
            $sql = str_ireplace('date()', 'getdate()', $sql);
            $sql = str_ireplace('ucase(', 'upper(', $sql);
            $sql = str_ireplace('lcase(', 'lower(', $sql);
            $sql = str_ireplace('now()', 'getdate()', $sql);

            // Domain Aggregate functions
            $sql = str_ireplace('DAvg(', 'AVG(', $sql);
            $sql = str_ireplace('DSum(', 'SUM(', $sql);
            $sql = str_ireplace('DCount(', 'COUNT(', $sql);
            $sql = str_ireplace('DMax(', 'MAX(', $sql);
            $sql = str_ireplace('DMin(', 'MIN(', $sql);

            // Boolean values
            $sql = str_ireplace('delete * ', 'delete ', $sql);
            $sql = str_replace('= -1', '=1', $sql);
            $sql = str_replace('=-1', '=1', $sql);
            $sql = str_ireplace('= True', '=1', $sql);
            $sql = str_ireplace('=True', '=1', $sql);
            $sql = str_ireplace('= False', '=0', $sql);
            $sql = str_ireplace('=False', '=0', $sql);

            // String concatenation - & to +
            $sql = str_replace('&', '+', $sql);
            $sql = str_replace('+nbsp;', '&nbsp;', $sql);

            // Date literal conversions
            $sql = preg_replace('/#(\d{1,2})[-\/](\d{1,2})[-\/](\d{4})#/', "CAST('$3-$1-$2' as DATETIME)", $sql);

            // Date functions
            $sql = str_ireplace("DateDiff('d'", 'DATEDIFF(day', $sql);
            $sql = str_ireplace("DateDiff('m'", 'DATEDIFF(month', $sql);
            $sql = str_ireplace("DateDiff('y'", 'DATEDIFF(year', $sql);
            $sql = str_ireplace("DateAdd('d'", 'DATEADD(day', $sql);
            $sql = str_ireplace("DateAdd('m'", 'DATEADD(month', $sql);
            $sql = str_ireplace("DateAdd('y'", 'DATEADD(year', $sql);

            // String functions
            $sql = str_ireplace('chr(', 'CHAR(', $sql);
            $sql = str_ireplace('instr(', 'dbo.instr(', $sql);
            $sql = str_ireplace('mid(', 'SUBSTRING(', $sql);

            // Convert IIF to CASE WHEN
            $sql = preg_replace('/iif\(([^,]+),([^,]+),([^\)]+)\)/i', 'CASE WHEN $1 THEN $2 ELSE $3 END', $sql);

        } else {
            // Access/ODBC: Convert double quotes to single quotes for string literals
            // Access uses single quotes for strings, double quotes cause "too few parameters" error
            // This converts "value" to 'value' but preserves escaped quotes within strings
            $sql = self::convertDoubleQuotesToSingle($sql);

            // Access equivalents for SQL Server commands
            $sql = preg_replace('/concat\((\S+),(\S+)?\)/i', '$1 & $2', $sql);
            $sql = str_ireplace('getdate()', 'date()', $sql);
            $sql = str_ireplace('lower(', 'lcase(', $sql);
            $sql = str_ireplace('upper(', 'ucase(', $sql);
            $sql = str_ireplace('CURRENT_TIMESTAMP', 'date()', $sql);
            $sql = str_ireplace('dbo.instr(', 'instr(', $sql);
            $sql = str_ireplace('SUBSTRING(', 'mid(', $sql);

            // Date functions
            $sql = str_ireplace('dateadd(hour,', "dateadd( 'h',", $sql);
            $sql = str_ireplace('dateadd(day,', "dateadd( 'd',", $sql);
            $sql = str_ireplace('dateadd(month,', "dateadd( 'm',", $sql);

            $sql = str_ireplace('delete from', 'delete * from', $sql);

            // Boolean values - Access uses -1 for true and 0 for false
            $sql = str_ireplace('= true', '= -1', $sql);
            $sql = str_ireplace('=true', '= -1', $sql);
            $sql = str_ireplace('= false', '= 0', $sql);
            $sql = str_ireplace('=false', '= 0', $sql);

            // Normalize IIF function to uppercase to prevent ODBC parameter interpretation
            $sql = str_ireplace('iif(', 'IIF(', $sql);

            // ODBC workaround: Add spaces around comparison operators to prevent misinterpretation
            // ODBC sometimes treats patterns like "<date()" as parameter markers
            // First, ensure spaces exist around operators
            $sql = preg_replace('/\s*([<>=!]+)\s*/', ' $1 ', $sql);
            // Clean up multiple spaces
            $sql = preg_replace('/\s+/', ' ', $sql);

            // Convert Access date literals to proper format (DD-MM-YYYY -> YYYY-MM-DD)
            // But keep the # delimiters that Access requires
            // Access: #01-01-2030# -> #2030-01-01#
            $sql = preg_replace_callback('/#(\d{2})-(\d{2})-(\d{4})#/', function($matches) {
                return "#{$matches[3]}-{$matches[1]}-{$matches[2]}#";
            }, $sql);

            // Convert CASE WHEN to IIF
            $sql = preg_replace('/CASE WHEN ([^T]+) THEN ([^E]+) ELSE ([^E]+) END/i', 'IIF($1, $2, $3)', $sql);

            // ODBC ambiguous column fix disabled - we renamed tblForms.Name to tblForms.FormName
            // to avoid the ambiguity issue at the database level
        }

        return $sql;
    }

    /**
     * Convert double-quoted string literals to single-quoted for Access/ODBC
     *
     * Access/ODBC uses single quotes for string literals. Double quotes cause
     * "too few parameters" errors because Access interprets them as field names.
     *
     * Examples:
     *   WHERE name = "John"     -> WHERE name = 'John'
     *   WHERE name = "O'Brien"  -> WHERE name = 'O''Brien'
     *
     * This method carefully handles:
     * - Double-quoted strings: "value" -> 'value'
     * - Embedded single quotes: "O'Brien" -> 'O''Brien'
     * - Already single-quoted strings: left unchanged
     * - Square brackets [field]: left unchanged (Access field delimiters)
     *
     * @param string $sql SQL query
     * @return string SQL with double quotes converted to single quotes
     */
    private static function convertDoubleQuotesToSingle(string $sql): string
    {
        // Match double-quoted strings, capturing the content
        // This regex handles escaped double quotes ("") within strings
        $pattern = '/"((?:[^"\\\\]|\\\\.|"")*)"/';

        return preg_replace_callback($pattern, function($matches) {
            $content = $matches[1];

            // Unescape any escaped double quotes ("" -> ")
            $content = str_replace('""', '"', $content);

            // Escape single quotes for Access (' -> '')
            $content = str_replace("'", "''", $content);

            // Return with single quotes
            return "'" . $content . "'";
        }, $sql);
    }

    /**
     * Add WHERE clause to SQL query
     *
     * Equivalent to ASP's lib_SQL_AddWhere function
     *
     * @param string $sql Base SQL query
     * @param string $whereClause WHERE clause to add (without WHERE keyword)
     * @return string SQL with WHERE clause added
     */
    public static function addWhere(string $sql, string $whereClause): string
    {
        if (empty($whereClause)) {
            return $sql;
        }

        return self::addClause($sql, $whereClause, 'WHERE', 'AND');
    }

    /**
     * Add WHERE clause with OR to SQL query
     *
     * Equivalent to ASP's lib_SQL_AddWhereOR function
     *
     * @param string $sql Base SQL query
     * @param string $whereClause WHERE clause to add
     * @return string SQL with WHERE clause added
     */
    public static function addWhereOR(string $sql, string $whereClause): string
    {
        if (empty($whereClause)) {
            return $sql;
        }

        return self::addClause($sql, $whereClause, 'WHERE', 'OR');
    }

    /**
     * Add HAVING clause to SQL query
     *
     * Equivalent to ASP's lib_SQL_AddHaving function
     *
     * @param string $sql Base SQL query
     * @param string $havingClause HAVING clause to add
     * @return string SQL with HAVING clause added
     */
    public static function addHaving(string $sql, string $havingClause): string
    {
        if (empty($havingClause)) {
            return $sql;
        }

        return self::addClause($sql, $havingClause, 'HAVING', 'AND');
    }

    /**
     * Add IN clause to SQL query (optimizes for small value counts)
     *
     * Equivalent to ASP's lib_SQL_AddInClause function
     *
     * @param string $sql Base SQL query
     * @param string $field Field name
     * @param string $values Comma-separated values
     * @param int $minInValues Minimum values for IN clause (default: 5)
     * @return string SQL with IN or OR clause added
     */
    public static function addInClause(string $sql, string $field, string $values, int $minInValues = 5): string
    {
        if (empty($values)) {
            return $sql;
        }

        // Check if multiple values
        if (strpos($values, ',') !== false) {
            $valuesArray = explode(',', $values);
            $count = count($valuesArray);

            // Use OR for small value counts (faster than IN for few values)
            if ($count <= $minInValues) {
                $conditions = [];
                foreach ($valuesArray as $value) {
                    $conditions[] = "$field=" . trim($value);
                }
                $whereClause = implode(' OR ', $conditions);
                return self::addWhere($sql, $whereClause);
            } else {
                // Use IN clause for many values
                return self::addWhere($sql, "$field IN ($values)");
            }
        } else {
            // Single value - use equality
            return self::addWhere($sql, "$field = $values");
        }
    }

    /**
     * Internal function to add WHERE/HAVING clause
     *
     * @param string $sql Base SQL query
     * @param string $clause Clause to add
     * @param string $clauseType 'WHERE' or 'HAVING'
     * @param string $operator 'AND' or 'OR'
     * @return string Modified SQL
     */
    private static function addClause(string $sql, string $clause, string $clauseType, string $operator): string
    {
        // Remove trailing semicolon
        $sql = rtrim($sql, ';');

        // Split ORDER BY / GROUP BY from main query
        $orderByClause = '';
        $pos = stripos($sql, 'GROUP BY');
        if ($pos === false) {
            $pos = stripos($sql, 'ORDER BY');
        }
        if ($pos !== false) {
            $orderByClause = substr($sql, $pos);
            $sql = substr($sql, 0, $pos);
        }

        // Check if clause already exists
        $clausePos = stripos($sql, $clauseType);
        if ($clausePos !== false) {
            // Check if this WHERE/HAVING is after the last FROM (not in subquery)
            $wherePos = strripos($sql, $clauseType);
            $fromPos = strripos($sql, 'FROM');

            if ($wherePos > $fromPos) {
                // Wrap existing and new clause with operator
                $existingClause = trim(substr($sql, $clausePos + strlen($clauseType)));
                $sql = substr($sql, 0, $clausePos) . "$clauseType (($existingClause) $operator ($clause))";
            } else {
                // Add new clause
                $sql .= " $clauseType $clause";
            }
        } else {
            // Add new clause
            $sql .= " $clauseType $clause";
        }

        return $sql . ' ' . $orderByClause;
    }

    /**
     * Build a complex WHERE clause for searching multiple fields
     *
     * Equivalent to ASP's lib_SQL_ComplicatedWhere function
     * Used for searching across multiple columns with text or number matching
     *
     * @param string $baseSQL Base SQL query
     * @param string $entry Search term entered by user
     * @param string $fieldNames Comma-separated list of field names to search
     * @param int $searchType Search type: 1 for text search (LIKE), 2 for number search (=). Default: 1
     * @return string SQL with WHERE clause added
     */
    public static function complicatedWhere(string $baseSQL, string $entry, string $fieldNames, int $searchType = 1): string
    {
        if (empty($entry)) {
            return $baseSQL;
        }

        $sql = $baseSQL;
        $entry = strtolower($entry);
        $tmpHolder = '^^^';

        // Build condition for each field
        $fieldList = explode(',', $fieldNames);
        $conditions = [];

        foreach ($fieldList as $field) {
            $field = trim($field);
            if (empty($field)) {
                continue;
            }

            // Add brackets if not present
            if (substr($field, 0, 1) !== '[') {
                $field = '[' . $field . ']';
            }

            if ($searchType == 1) { // CONST_LIB_SQL_SEARCH_TEXT
                $conditions[] = $field . ' LIKE ' . self::postString('%' . strtoupper($tmpHolder) . '%');
            } else { // CONST_LIB_SQL_SEARCH_NUMBER
                $conditions[] = $field . '=' . $tmpHolder;
            }
        }

        if (empty($conditions)) {
            return $sql;
        }

        $conditionStr = '(' . implode(' OR ', $conditions) . ')';

        // Handle "en" (Dutch for "and") in search terms
        $entry = str_ireplace(' and ', ' en ', $entry);
        if (stripos($entry, ' en ') !== false) {
            $items = explode(' en ', $entry);
            foreach ($items as $item) {
                $item = trim($item);
                $clause = str_replace($tmpHolder, str_replace("'", "''", $item), $conditionStr);
                $sql = self::addWhere($sql, $clause);
            }
        } else {
            $clause = str_replace($tmpHolder, str_replace("'", "''", $entry), $conditionStr);
            $sql = self::addWhere($sql, $clause);
        }

        return $sql;
    }

    /**
     * Add TOP clause to limit query results (Access/SQL Server syntax)
     *
     * @param string $sql The SQL query
     * @param int $limit Maximum number of rows to return
     * @return string Modified SQL with TOP clause
     */
    public static function addTop(string $sql, int $limit): string
    {
        // Check if TOP is already present
        if (preg_match('/^SELECT\s+(DISTINCT\s+)?TOP\s+/i', $sql)) {
            return $sql;
        }

        // Check if DISTINCT is present
        if (preg_match('/^SELECT\s+DISTINCT\s+/i', $sql)) {
            // Replace "SELECT DISTINCT " with "SELECT DISTINCT TOP n "
            return preg_replace('/^SELECT\s+DISTINCT\s+/i', "SELECT DISTINCT TOP $limit ", $sql);
        }

        // No DISTINCT, just add TOP after SELECT
        return preg_replace('/^SELECT\s+/i', "SELECT TOP $limit ", $sql);
    }
}
