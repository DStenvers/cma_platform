<?php

namespace Cma\Services;

/**
 * Single source of truth for mapping a database/ADO schema data-type to the
 * coarse list-column category ('date' / 'time' / 'number') used for the
 * data-type attribute on rendered cells.
 *
 * Before this class the mapping was copy-pasted into
 * JsonFormService::detectColumnType() and TableService::getColumnDataType()
 * and had drifted: the JSON path was missing ADO time code 134 (rendered as
 * text) and several numeric codes (14,17-21). This method is the UNION of what
 * both paths recognised, so both callers strictly improve to the same answer.
 */
class FieldType
{
    /**
     * @param int|string $schemaType ADO/schema type code (e.g. 5, 134) or name
     *                               (e.g. 'datetime', 'decimal').
     * @return string|null 'date' | 'time' | 'number', or null when the type is
     *                     not one of those coarse categories (caller decides,
     *                     usually 'text').
     */
    public static function categoryForSchemaType($schemaType): ?string
    {
        $s = strtolower(trim((string)$schemaType));
        if ($s === '') {
            return null;
        }
        $num = is_numeric($s) ? (int)$s : null;

        // Time (ADO adDBTime = 134). Checked first: a time column often also
        // looks date-ish to downstream code.
        if ($num === 134 || $s === 'time') {
            return 'time';
        }

        // Date/datetime (ADO adDBDate=133, adDate=7, adDBTimeStamp=135).
        if (($num !== null && in_array($num, [7, 133, 135], true))
            || in_array($s, ['date', 'datetime', 'datetime2', 'smalldatetime', 'datetimeoffset'], true)) {
            return 'date';
        }

        // Numeric (ADO 2=smallint,3=integer,4=single,5=double,6=currency,
        // 14=decimal,17-21=various int widths,131=numeric).
        if (($num !== null && in_array($num, [2, 3, 4, 5, 6, 14, 17, 18, 19, 20, 21, 131], true))
            || in_array($s, ['int', 'integer', 'bigint', 'smallint', 'tinyint',
                'decimal', 'numeric', 'float', 'double', 'real', 'money', 'number'], true)) {
            return 'number';
        }

        return null;
    }
}
