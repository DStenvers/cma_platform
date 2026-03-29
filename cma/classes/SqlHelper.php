<?php

namespace Cma;

use App\Library\Arr;
use App\Library\SQL;

/**
 * CMA SQL Helper
 *
 * Provides SQL value conversion, field name formatting, and validation.
 */
class SqlHelper
{
    /**
     * Convert a value to SQL-safe format based on schema information
     *
     * @param string $value The value to convert
     * @param object|null $rsSchema RecordSet with schema information
     * @param bool $isTimeControl Whether this is a time control field
     * @param bool $isSqlServer Whether the database is SQL Server (vs Access)
     * @return string SQL-safe value
     */
    public static function quoteValue(?string $value, $rsSchema, bool $isTimeControl = false, bool $isSqlServer = false): string
    {
        $value = str_replace('�', '', trim($value ?? ''));

        if ($rsSchema === null || (is_object($rsSchema) && $rsSchema->EOF)) {
            return SQL::postString($value);
        }

        if (!is_object($rsSchema)) {
            return SQL::postString($value);
        }

        $dataType = $rsSchema->fields['DATA_TYPE'] ?? null;

        // Boolean field
        if ($dataType == ADBOOLEAN) {
            if ($value === '') {
                return $isSqlServer ? '0' : 'False';
            }
            return $isSqlServer ? '1' : 'True';
        }

        // Empty value for non-boolean
        if ($value === '') {
            return 'Null';
        }

        // Numeric field
        if (!is_null($rsSchema->fields['NUMERIC_PRECISION'] ?? null)) {
            return str_replace(',', '.', $value);
        }

        // Date/time field
        $charMaxLength = $rsSchema->fields['CHARACTER_MAXIMUM_LENGTH'] ?? null;
        if (!is_null($rsSchema->fields['DATETIME_PRECISION'] ?? null) || $charMaxLength == 19) {
            if ($isTimeControl) {
                return $isSqlServer ? "CAST('" . $value . "' AS DATETIME)" : '#' . $value . '#';
            }

            // Try to parse date
            if (strtotime($value) !== false) {
                $dateArr = explode('-', str_ireplace('/', '-', $value));
                if (count($dateArr) >= 3) {
                    $value = $dateArr[0] . '/' . $dateArr[1] . '/' . $dateArr[2];
                }
            }
            return SQL::postDateStr($value);
        }

        // Character field
        if (!is_null($charMaxLength)) {
            return SQL::postString($value);
        }

        return SQL::postString($value);
    }

    /**
     * Format a field name for SQL queries, handling table.field notation
     *
     * @param string $fieldName Field name, possibly with table prefix
     * @return string Properly bracketed field name
     */
    public static function formatFieldName(string $fieldName): string
    {
        // Handle table.field notation
        if (stripos($fieldName, '.') !== false) {
            $parts = Arr::splitAlways($fieldName, '.');
            $fieldName = $parts[1] ?? $fieldName;
        }

        // Remove existing brackets and re-add them properly
        $cleaned = str_replace([']', '['], '', $fieldName);
        return '[' . str_replace('.', '].[', $cleaned) . ']';
    }

    /**
     * Validate a value and return error message if invalid
     *
     * @param string $value The value to validate
     * @param string $caption Field caption/label for error message
     * @param bool $isRequired Whether the field is required
     * @param bool $isHtml Whether HTML is allowed (unused for now)
     * @param object|null $rsSchema RecordSet with schema information
     * @return string Error message or empty string if valid
     */
    public static function validateValue(?string $value, string $caption, bool $isRequired, bool $isHtml, $rsSchema): string
    {
        if ($rsSchema === null || !is_object($rsSchema) || $rsSchema->EOF) {
            return '';
        }

        $value = trim($value ?? '');

        // Extract field name from caption (remove HTML like <br>)
        $fieldName = $caption;
        $brPos = stripos(strtoupper($fieldName), '<BR>');
        if ($brPos !== false) {
            $fieldName = substr($fieldName, 0, $brPos);
        }

        // Check required
        if ($isRequired && $value === '') {
            return '<li><b>' . $fieldName . '</b>&nbsp;is verplicht<br>';
        }

        // Skip further validation if empty
        if ($value === '') {
            return '';
        }

        // Check numeric
        if (!is_null($rsSchema->fields['NUMERIC_PRECISION'] ?? null) && !is_numeric($value)) {
            return '<li><b>' . $fieldName . '</b>&nbsp;mag alleen nummers bevatten';
        }

        // Check date
        if (!is_null($rsSchema->fields['DATETIME_PRECISION'] ?? null) && strtotime($value) === false) {
            return '<li><b>' . $fieldName . '</b>&nbsp;is geen geldige datum / tijd';
        }

        return '';
    }
}
