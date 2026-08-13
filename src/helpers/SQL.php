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
     * Normalise a (possibly locale-formatted) number string to canonical
     * dot-decimal form for SQL binding / floatval.
     *
     * Rules:
     *  - Both '.' and ',' present: the RIGHTMOST separator is the decimal sign,
     *    the other is a thousands grouping that gets stripped
     *    ("1.234,56" -> "1234.56", "1,234.56" -> "1234.56").
     *  - Only ',' present: it is the decimal sign ("12,5" -> "12.5").
     *  - Only '.' present (or no separator): the '.' is already the decimal sign
     *    and is kept as-is ("12.5" -> "12.5"). Per the CMA convention a lone '.'
     *    is ALWAYS the decimal separator, never a thousands grouping — so a
     *    value typed with a dot (no comma) is interpreted as a decimal number.
     *
     * Non-numeric input is returned trimmed but otherwise unchanged so callers
     * can still validate with is_numeric().
     *
     * @param mixed $value
     * @return string
     */
    public static function normalizeDecimal($value): string
    {
        $s = trim((string)$value);
        if ($s === '') {
            return $s;
        }
        $hasComma = strpos($s, ',') !== false;
        $hasDot   = strpos($s, '.') !== false;

        if ($hasComma && $hasDot) {
            if (strrpos($s, ',') > strrpos($s, '.')) {
                // comma is the decimal sign; dots are thousands groupings
                $s = str_replace(['.', ','], ['', '.'], $s);
            } else {
                // dot is the decimal sign; commas are thousands groupings
                $s = str_replace(',', '', $s);
            }
        } elseif ($hasComma) {
            // lone comma -> decimal sign
            $s = str_replace(',', '.', $s);
        }
        // lone dot / no separator: already canonical
        return $s;
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
        }
        $normalized = self::normalizeDecimal($strRetval);
        // Injection guard: this value is inlined UNQUOTED into SQL, so it must be
        // a genuine numeric literal. A non-numeric value (e.g. an injection
        // attempt like "1 OR 1=1", or stray text) collapses to 0 rather than
        // being emitted raw. Callers wanting NULL for invalid input pass '' above.
        if (!is_numeric($normalized)) {
            return '0';
        }
        return $normalized;
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
            // Treat "0", "false", "no", "n" as falsy (not just empty string)
            $bTmp = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? ($value !== '' && $value !== null);
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
        $cleanGuid = str_replace('}', '', str_replace('{', '', $guid));

        // Only Access needs the LIKE: it is a workaround for the Jet/ACE ODBC driver,
        // and everywhere else it costs a full table scan for a comparison '=' handles.
        if (self::dialect($connectionString) === self::DIALECT_ACCESS) {
            return $column . " LIKE '%" . $cleanGuid . "%'";
        }
        return $column . " = '" . $cleanGuid . "'";
    }

    /**
     * Rewrite raw `<col>guid = '<guid>'` occurrences in a SQL string to
     * `<col>guid LIKE '%<guid>%'` — the Access ODBC Replication-ID fix applied
     * to already-built SQL (e.g. converted VBScript `WHERE guid = lib_PostGuid(x)`
     * that never went through guidEquals()). Guarded to a GUID-shaped literal on
     * a column whose name ends in "guid", so ordinary '=' filters are untouched.
     * Callers must scope this to Access — see processSQL(). Companion to
     * guidEquals().
     *
     * @param string $sql
     * @return string
     */
    public static function guidEqualsToLike(string $sql): string
    {
        return preg_replace_callback(
            "/([\\w.]*guid)\\s*=\\s*'([0-9A-Fa-f]{8}-[0-9A-Fa-f]{4}-[0-9A-Fa-f]{4}-[0-9A-Fa-f]{4}-[0-9A-Fa-f]{12})'/i",
            static fn($m) => $m[1] . " LIKE '%" . $m[2] . "%'",
            $sql
        ) ?? $sql;
    }

    /**
     * Strip self-referential column aliases (`Field AS FIELD`, `t.Field AS FIELD`)
     * where the alias equals the column name case-insensitively. Access/Jet is
     * case-insensitive and rejects these as a circular reference; the alias adds
     * nothing, so it is removed. Only a bare identifier immediately before `AS`
     * qualifies — expression aliases (`IIF(...) AS x`, `count(*) AS n`) never
     * match because the character before `AS` is not a word character.
     */
    public static function stripSelfAlias(string $sql): string
    {
        return preg_replace_callback(
            '/([A-Za-z_]\w*(?:\.[A-Za-z_]\w*)?)\s+AS\s+([A-Za-z_]\w*)/i',
            static function ($m) {
                $field = $m[1];
                $dot = strrpos($field, '.');
                $last = $dot === false ? $field : substr($field, $dot + 1);
                return strcasecmp($last, $m[2]) === 0 ? $field : $m[0];
            },
            $sql
        ) ?? $sql;
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

        // strtotime() alone accepts what is merely parseable, not what is a real date:
        // it happily reads a year 0001 or a value that only looks like a date. Let
        // Date::normalize() decide first, so an unusable value becomes NULL here rather
        // than a nonsense literal in the query.
        if (Date::normalize($dateValue) === null) {
            return 'NULL';
        }

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
        // Same gate as postDateOnly(): parseable is not the same as real. The time part
        // is kept as-is — normalize() only rules on the date.
        if (Date::normalize($dateValue) === null) {
            return 'NULL';
        }

        $ts = is_null($dateValue) ? false : strtotime($dateValue);
        if ($ts !== false) {
            // ISO 8601 with seconds — unambiguous (no US m/d guesswork) and
            // never truncates to the minute. JET accepts #YYYY-MM-DD HH:MM:SS#
            // and SQL Server casts the same literal cleanly.
            $iso = date('Y-m-d H:i:s', $ts);
            return self::isSQLServer($connectionString)
                ? "CAST('" . $iso . "' AS DATETIME)"
                : '#' . $iso . '#';
        }

        return 'NULL';
    }

    /**
     * Parse a date string and format for SQL
     * Accepts DD-MM-YYYY / DD/MM/YYYY and YYYY-MM-DD.
     *
     * The ISO form is not a convenience: <lib-datepicker> submits its value as
     * YYYY-MM-DD (that is the component's documented value format), while every
     * converted page hands whatever it posted straight to this method. Parsed as
     * day-first, "2026-04-01" became day 2026 / year 01, postDate() could not make a
     * date of that and returned NULL — so the query read `datestamp >= NULL`, which
     * matches nothing. A report would then show zero rows without a single error.
     *
     * Date::normalize() decides what a date is: which written form, whether it exists at
     * all (31-02 does not) and whether the year is plausible. Anything it refuses becomes
     * the literal NULL here — the same outcome as before for garbage input, but now the
     * refusal is deliberate instead of an accident of string splitting.
     *
     * @param string $dateStr The date string to parse
     * @param string|null $connectionString Connection string to determine database type
     * @return string Formatted SQL date or "NULL"
     */
    public static function postDateStr(string $dateStr, ?string $connectionString = null): string
    {
        $iso = Date::normalize($dateStr);
        if ($iso === null) {
            return 'NULL';
        }

        [$year, $month, $day] = explode('-', $iso);

        return self::postDate($day, $month, $year, $connectionString);
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

    /** The dialect an unrecognised connection is assumed to speak. */
    public const DIALECT_ACCESS = 'access';
    public const DIALECT_SQLSERVER = 'sqlserver';
    public const DIALECT_SQLITE = 'sqlite';

    /**
     * Which SQL dialect a connection speaks.
     *
     * The platform writes its SQL in the Access dialect it was converted from, and
     * every other backend is reached by translating that. Naming the dialect in one
     * place is what keeps the translation honest: the old boolean "SQL Server, or
     * else Access" put SQLite in the Access branch, where `DELETE FROM` was rewritten
     * to `DELETE * FROM` and `= True` to `= -1` — statements SQLite either rejects or,
     * worse, accepts while matching nothing.
     *
     * MySQL and PostgreSQL still resolve to `access`: nothing translates for them yet,
     * and pretending otherwise here would only move the surprise.
     *
     * @param \PDO|string|null $connection PDO instance, connection string, or null for the default.
     */
    public static function dialect($connection = null): string
    {
        if ($connection instanceof \PDO) {
            try {
                $driver = strtolower((string) $connection->getAttribute(\PDO::ATTR_DRIVER_NAME));
            } catch (\Throwable $e) {
                return self::DIALECT_ACCESS;
            }
            if ($driver === 'sqlite') {
                return self::DIALECT_SQLITE;
            }
            if ($driver === 'sqlsrv' || $driver === 'mssql' || $driver === 'dblib') {
                return self::DIALECT_SQLSERVER;
            }
            // ODBC is Access here; a SQL Server reached over ODBC is configured
            // with a DSN= string and is caught by the string branch below.
            return self::DIALECT_ACCESS;
        }

        $connectionString = is_string($connection) ? $connection : Database::getConfiguredDsn('data');
        if (stripos($connectionString, 'sqlite:') === 0) {
            return self::DIALECT_SQLITE;
        }
        return Database::isSQLServer($connectionString) ? self::DIALECT_SQLSERVER : self::DIALECT_ACCESS;
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
    public static function processSQL($connection, ?string $sql): string
    {
        // Tolerate a null SQL accumulator (converted VBScript seeds it with an
        // uninitialised Empty variant -> null); treat it as an empty string.
        $sql = $sql ?? '';
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

        $dialect = self::dialect($connection);
        $isSQLServer = $dialect === self::DIALECT_SQLSERVER;

        // Access ODBC only: Replication-ID (GUID) columns return NO rows when
        // compared with '=' through the Jet/ACE ODBC driver — rewrite
        // `guid = '<guid>'` to `guid LIKE '%<guid>%'`. This breaks raw
        // `WHERE guid = '<guid>'` (assumeidentity / session / password-reset
        // logins) on converted sites. Access-only: LIKE on a full GUID would
        // table-scan on SQL Server, and '=' works fine on SQLite/MySQL. See
        // SQL::guidEquals() for the query-builder side of the same quirk.
        if ($dialect === self::DIALECT_ACCESS
            && ($connectionString === 'ACCESS_VIA_ODBC' || Database::isODBC($connectionString))) {
            $sql = self::guidEqualsToLike($sql);
            // Access is case-insensitive, so `Field AS FIELD` (alias == the field
            // name) is a self-referential/circular alias and Jet rejects it
            // ("de alias X ... veroorzaakt een kringverwijzing"). The alias is
            // redundant there anyway — strip it. Only simple `<col> AS <alias>`
            // (or `<tbl>.<col> AS <alias>`) is touched; expression aliases like
            // `IIF(...) AS x` never match (the char before AS isn't a word char).
            $sql = self::stripSelfAlias($sql);
        }

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

        } elseif ($dialect === self::DIALECT_SQLITE) {
            $sql = self::toSqlite($sql);
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

            // ODBC workaround: normalise whitespace around comparison operators (ODBC
            // can treat patterns like "<date()" as parameter markers) — but ONLY outside
            // string literals. Otherwise `<`/`>`/`=` inside quoted values (e.g. the
            // '<html>...' raw-cell and '<sort:...>' sort-key markers the reports embed)
            // get corrupted with spaces and stop matching downstream.
            $sql = preg_replace_callback(
                "/'(?:[^']|'')*'|\\s*([<>=!]+)\\s*/",
                static function ($m) {
                    return (isset($m[1]) && $m[1] !== '') ? ' ' . $m[1] . ' ' : $m[0];
                },
                $sql
            ) ?? $sql;
            // Collapse runs of whitespace, again only outside string literals.
            $sql = preg_replace_callback(
                "/'(?:[^']|'')*'|\\s+/",
                static function ($m) {
                    return ($m[0] !== '' && $m[0][0] === "'") ? $m[0] : ' ';
                },
                $sql
            ) ?? $sql;

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
     * Access column types, and what each one is called in the other dialects.
     *
     * Types absent from a map are passed through: VARCHAR(n), DATETIME and INTEGER
     * mean the same thing everywhere the platform runs. SQLite accepts any type name
     * at all, which is exactly why it needs this map — MEMO and YESNO would be taken
     * without complaint and given NUMERIC affinity, so a note would sort as if it
     * were a number.
     *
     * @var array<string, array<string, string>>
     */
    private const DDL_TYPE_MAP = [
        self::DIALECT_SQLITE => [
            'MEMO' => 'TEXT', 'LONGTEXT' => 'TEXT', 'NTEXT' => 'TEXT', 'GUID' => 'TEXT',
            'UNIQUEIDENTIFIER' => 'TEXT',
            'LONG' => 'INTEGER', 'BYTE' => 'INTEGER', 'YESNO' => 'INTEGER', 'BIT' => 'INTEGER',
            'CURRENCY' => 'REAL', 'DOUBLE' => 'REAL', 'SINGLE' => 'REAL',
        ],
        self::DIALECT_SQLSERVER => [
            'MEMO' => 'NVARCHAR(MAX)', 'GUID' => 'UNIQUEIDENTIFIER',
            'LONG' => 'INT', 'INTEGER' => 'INT', 'BYTE' => 'TINYINT', 'YESNO' => 'BIT',
            'CURRENCY' => 'MONEY', 'DOUBLE' => 'FLOAT', 'SINGLE' => 'REAL',
        ],
    ];

    /**
     * Translate a DDL statement (CREATE TABLE, ALTER TABLE, CREATE/DROP INDEX)
     * from the platform's Access dialect to the dialect the connection speaks.
     *
     * Schema statements are the one place where the dialects differ in a way no
     * amount of query rewriting reaches: `ID AUTOINCREMENT PRIMARY KEY` is a syntax
     * error on SQLite, which wants `ID INTEGER PRIMARY KEY AUTOINCREMENT` — that is
     * the whole reason a fresh site could not run migration 1.0.1. Keeping it here
     * means a migration writes ONE portable statement and every backend gets a
     * correct one.
     *
     * @param \PDO|string|null $connection
     */
    public static function processDdl($connection, string $sql): string
    {
        $dialect = self::dialect($connection);
        if ($dialect === self::DIALECT_ACCESS) {
            return $sql;   // the dialect the platform's DDL is already written in
        }

        $types = self::DDL_TYPE_MAP[$dialect] ?? [];
        $identity = $dialect === self::DIALECT_SQLITE
            ? 'INTEGER PRIMARY KEY AUTOINCREMENT'
            : 'INT IDENTITY(1,1) PRIMARY KEY';

        return self::mapOutsideLiterals($sql, static function (string $s) use ($dialect, $types, $identity): string {
            // The counter column. Access writes the type first and the constraint
            // after it; SQLite demands the exact phrase "INTEGER PRIMARY KEY
            // AUTOINCREMENT", in that order. A bare AUTOINCREMENT (Access' COUNTER)
            // becomes a primary key too — SQLite has no other form of it.
            $s = preg_replace('/\bAUTOINCREMENT\b(\s+PRIMARY\s+KEY)?/i', $identity, $s) ?? $s;

            // Column types. Anchored on "<column name> <TYPE>" so a column that is
            // itself called memo or long keeps its name and only its type changes.
            foreach ($types as $from => $to) {
                $s = preg_replace(
                    '/(\[?\w+\]?\s+)\b' . preg_quote($from, '/') . '\b/i',
                    '$1' . $to,
                    $s
                ) ?? $s;
            }

            // SQLite indexes live in one namespace per database, so they are dropped
            // by name alone; naming the table is a syntax error there.
            if ($dialect === self::DIALECT_SQLITE) {
                $s = preg_replace('/\b(DROP\s+INDEX\s+\[?\w+\]?)\s+ON\s+\[?\w+\]?/i', '$1', $s) ?? $s;
            }

            return $s;
        });
    }

    /**
     * Apply $fn to every part of $sql that is NOT inside a string literal.
     *
     * Every rewrite below — `&` to `||`, `date()` to `date('now')`, the boolean
     * literals — is a rewrite of SQL syntax, and none of it may reach the data.
     * A report that stores '<sort:naam>' or 'Jansen & Zn' in a WHERE clause would
     * otherwise come out corrupted, and that corruption is silent: the query still
     * runs, it just stops matching.
     *
     * Literals are single-quoted by the time this runs (convertDoubleQuotesToSingle
     * goes first), with '' as the embedded quote.
     */
    private static function mapOutsideLiterals(string $sql, callable $fn): string
    {
        $parts = preg_split("/('(?:[^']|'')*')/", $sql, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false) {
            return $fn($sql);
        }
        foreach ($parts as $i => $part) {
            if ($i % 2 === 0) {
                $parts[$i] = $fn($part);
            }
        }
        return implode('', $parts);
    }

    /**
     * Translate the platform's Access-dialect SQL to SQLite.
     *
     * The platform's queries are written in the dialect it was converted from, so
     * SQLite support is a translation, not a mode. What is handled here is what the
     * platform itself emits (SQL::post*, SQL::addTop, the CMA's query builders) plus
     * the Access idioms that hand-written site queries use.
     *
     * Deliberately NOT translated: `DateAdd`/`DateDiff` and `Format`, whose argument
     * shapes differ too much to rewrite safely with a regular expression. SQLite
     * answers those with "no such function", which is a visible failure — far better
     * than a half-rewritten expression that runs and returns the wrong rows. Use
     * SQLite's own `date(x, '+1 day')` in site SQL that must run on both.
     *
     * `IIF(a,b,c)` is left alone: SQLite has had iif() since 3.32.
     */
    private static function toSqlite(string $sql): string
    {
        // SQLite reads "x" as an IDENTIFIER first and only falls back to a string
        // when no such column exists — so a double-quoted value silently becomes a
        // column reference. The platform means them as strings.
        $sql = self::convertDoubleQuotesToSingle($sql);

        // Access date/time literals (#...#) — emitted by SQL::postDate*, postTimeStr
        // and by hand-written Access SQL. SQLite has no date literal syntax; it
        // compares ISO strings. Doing it here rather than in each post* helper keeps
        // one rule for both generated and hand-written SQL.
        $sql = self::mapOutsideLiterals($sql, static function (string $s): string {
            return preg_replace_callback('/#([^#]+)#/', static function ($m) {
                $raw = trim($m[1]);
                // Time-only (#HH:MM#): keep it a time, a date would be invented.
                if (preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $raw)) {
                    return "'" . (strlen($raw) === 5 ? $raw . ':00' : $raw) . "'";
                }
                $ts = strtotime($raw);
                if ($ts === false) {
                    return $m[0];
                }
                $hasTime = (bool) preg_match('/\d:\d/', $raw);
                return "'" . date($hasTime ? 'Y-m-d H:i:s' : 'Y-m-d', $ts) . "'";
            }, $s) ?? $s;
        });

        $sql = self::mapOutsideLiterals($sql, static function (string $s): string {
            // TOP n -> LIMIT n. Only the outermost SELECT: a TOP in a subquery would
            // need its LIMIT inside that subquery's parentheses, and guessing where
            // those end is how a rewrite starts returning the wrong rows. One that is
            // left behind fails loudly on the next run.
            if (preg_match('/^(\s*SELECT\s+(?:DISTINCT\s+)?)TOP\s+(\d+)\s+/i', $s, $m)) {
                $s = $m[1] . substr($s, strlen($m[0]));
                if (!preg_match('/\bLIMIT\s+\d+/i', $s)) {
                    $s = rtrim(rtrim($s), ';') . ' LIMIT ' . $m[2];
                }
            }

            // Access' DELETE * FROM — the star is Access-only syntax.
            $s = preg_replace('/\bDELETE\s+\*\s+FROM\b/i', 'DELETE FROM', $s) ?? $s;

            // Booleans. Access stores True as -1; SQLite stores 1, so `= -1` matches
            // nothing at all there — the silent kind of wrong.
            $s = preg_replace('/=\s*-1\b/', '= 1', $s) ?? $s;
            $s = preg_replace('/=\s*True\b/i', '= 1', $s) ?? $s;
            $s = preg_replace('/=\s*False\b/i', '= 0', $s) ?? $s;

            // Current date/time.
            $s = preg_replace('/\bnow\s*\(\s*\)/i', "datetime('now','localtime')", $s) ?? $s;
            $s = preg_replace('/\bgetdate\s*\(\s*\)/i', "datetime('now','localtime')", $s) ?? $s;
            $s = preg_replace('/\bdate\s*\(\s*\)/i', "date('now','localtime')", $s) ?? $s;
            $s = str_ireplace('CURRENT_TIMESTAMP', "datetime('now','localtime')", $s);

            // String functions.
            $s = str_ireplace('ucase(', 'upper(', $s);
            $s = str_ireplace('lcase(', 'lower(', $s);
            $s = str_ireplace('dbo.instr(', 'instr(', $s);
            $s = str_ireplace('SUBSTRING(', 'substr(', $s);
            $s = str_ireplace('mid(', 'substr(', $s);
            $s = preg_replace('/\blen\s*\(/i', 'length(', $s) ?? $s;
            $s = preg_replace('/\bnz\s*\(/i', 'ifnull(', $s) ?? $s;
            $s = preg_replace('/\bchr\s*\(/i', 'char(', $s) ?? $s;

            // Domain aggregates (Access) map straight onto the plain aggregates.
            $s = preg_replace('/\bD(Avg|Sum|Count|Max|Min)\s*\(/i', '$1(', $s) ?? $s;
            $s = str_ireplace(' distinctrow ', ' DISTINCT ', $s);

            // Concatenation: Access uses &, SQLite uses ||. This is also what makes
            // SQL::postString()'s "' & chr(39) & '" escaping land correctly.
            return str_replace('&', '||', $s);
        });

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
    public static function addWhere(?string $sql, ?string $whereClause): string
    {
        $sql = $sql ?? '';
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
    public static function addWhereOR(?string $sql, ?string $whereClause): string
    {
        $sql = $sql ?? '';
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
    public static function addHaving(?string $sql, ?string $havingClause): string
    {
        $sql = $sql ?? '';
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
    public static function addInClause(?string $sql, ?string $field, ?string $values, int $minInValues = 5): string
    {
        $sql = $sql ?? '';
        $field = $field ?? '';
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

    /**
     * The column a query is sorted on, for showing that sorting in the interface.
     *
     * A list arrives in the order its ORDER BY dictates, but a table header cannot know
     * that by itself — the indicator would only appear once the user sorted manually.
     * `<th data-sorted="asc|desc">` closes that gap; this method supplies the value.
     *
     * Only the FIRST term is returned: that is the one a reader perceives as "the
     * sorting". Secondary terms merely break ties and marking them would suggest the
     * list is sorted on something it visibly is not.
     *
     * Returns null when there is nothing honest to show: no ORDER BY, a term that is an
     * expression or a column ordinal rather than a plain column, or a sort that only
     * exists to make paging deterministic. Callers must treat null as "show nothing" —
     * a wrong indicator is worse than none.
     *
     * @param string|null $sql The query the rows came from
     * @return array{field:string,direction:string}|null Column name (without table prefix
     *                                                   or brackets) and 'asc'|'desc'
     */
    public static function sortedColumn(?string $sql): ?array
    {
        if ($sql === null || $sql === '') {
            return null;
        }
        // Stop at the clauses that may follow ORDER BY, so their contents cannot be read
        // as sort terms.
        if (!preg_match('/\bORDER\s+BY\s+(.*?)(?:$|\bLIMIT\b|\bOFFSET\b|\bFOR\s+UPDATE\b|\)\s*$)/is', $sql, $m)) {
            return null;
        }
        // Only the first term; a comma inside brackets (a function call) is not a separator.
        $parts = preg_split('/,(?![^()]*\))/', trim($m[1]));
        $term = trim((string)($parts[0] ?? ''));
        if ($term === '') {
            return null;
        }

        $direction = 'asc';
        if (preg_match('/\bDESC\s*$/i', $term)) {
            $direction = 'desc';
            $term = trim((string)preg_replace('/\bDESC\s*$/i', '', $term));
        } elseif (preg_match('/\bASC\s*$/i', $term)) {
            $term = trim((string)preg_replace('/\bASC\s*$/i', '', $term));
        }

        // table.field, [table].[field] or a bare column. Anything else — IIf(), a
        // concatenation, "ORDER BY 2" — has no column to point at.
        if (preg_match('/^\[?([\w]+)\]?\.\[?([\w]+)\]?$/', $term, $mv)) {
            $field = $mv[2];
        } elseif (preg_match('/^\[?([A-Za-z_][\w]*)\]?$/', $term, $mv)) {
            $field = $mv[1];
        } else {
            return null;
        }

        // De ORDER BY noemt de bronkolom (tblOpleidingen.code), terwijl de lijst de alias
        // toont (AS Opleiding) — en onder die naam komt de kolom ook uit de recordset.
        // Zonder deze vertaling wijst de sortering naar een naam die in de kop niet bestaat.
        $alias = self::selectAliasFor($sql, $term, $field);

        return ['field' => $alias ?? $field, 'direction' => $direction];
    }

    /**
     * De alias waaronder $term in de SELECT-lijst staat, of null als die er niet is.
     *
     * @param string $sql   De volledige query
     * @param string $term  De sorteerterm zoals hij in de ORDER BY staat (evt. met tabelprefix)
     * @param string $field Diezelfde term zonder tabelprefix en haken
     */
    private static function selectAliasFor(string $sql, string $term, string $field): ?string
    {
        if (!preg_match('/^\s*SELECT\s+(?:DISTINCTROW\s+|DISTINCT\s+)?(?:TOP\s+\d+\s+(?:PERCENT\s+)?)?/is', $sql, $kop)) {
            return null;
        }
        $start = strlen($kop[0]);

        // Tot de FROM van dit SELECT-niveau: een FROM binnen haakjes hoort bij een subquery.
        $diepte = 0;
        $einde = null;
        for ($i = $start, $n = strlen($sql); $i < $n; $i++) {
            $c = $sql[$i];
            if ($c === '(') { $diepte++; continue; }
            if ($c === ')') { $diepte--; continue; }
            if ($diepte === 0 && ($c === 'f' || $c === 'F') && preg_match('/\GFROM\b/i', $sql, $x, 0, $i)) {
                $einde = $i;
                break;
            }
        }
        if ($einde === null) {
            return null;
        }

        // Splitsen op komma's buiten haakjes: left(veld, 80) is één kolom, geen twee.
        $items = [];
        $huidig = '';
        $diepte = 0;
        for ($i = $start; $i < $einde; $i++) {
            $c = $sql[$i];
            if ($c === '(') { $diepte++; }
            if ($c === ')') { $diepte--; }
            if ($c === ',' && $diepte === 0) { $items[] = $huidig; $huidig = ''; continue; }
            $huidig .= $c;
        }
        $items[] = $huidig;

        $normaliseer = static fn(string $s): string => strtolower(str_replace(['[', ']', ' '], '', trim($s)));
        $gezochtVol  = $normaliseer($term);
        $gezochtKaal = $normaliseer($field);

        $treffers = [];
        foreach ($items as $item) {
            if (!preg_match('/^(.*?)\s+AS\s+(\[[^\]]+\]|[\w]+)\s*$/is', trim($item), $m)) {
                continue;
            }
            $expressie = $normaliseer($m[1]);
            $alias = trim($m[2], '[] ');
            if ($expressie === $gezochtVol) {
                return $alias;
            }
            // Een kale sorteerterm mag alleen een tabelkolom aanwijzen als er precies
            // één kandidaat is; bij twee tabellen met dezelfde kolomnaam is het gokken.
            if ($expressie === $gezochtKaal || str_ends_with($expressie, '.' . $gezochtKaal)) {
                $treffers[] = $alias;
            }
        }
        return count($treffers) === 1 ? $treffers[0] : null;
    }
}
