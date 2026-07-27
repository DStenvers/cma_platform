<?php
/**
 * Date formatting helpers (Dutch locale, VBScript-era formats).
 *
 * Moved out of lib_general.inc - behavior unchanged. Output
 * formats deliberately differ from App\Library\Date (e.g. strMediumDate's
 * dd-mmm-yyyy with Dutch abbreviations), so these are NOT thin wrappers and
 * must not be blind-replaced by Date:: calls.
 */

/**
 * Lib_FixDateValue - Format date values for display
 * Wrapper for Date::fixValue()
 *
 * @param mixed $value The value to format
 * @return string Formatted value
 */
function Lib_FixDateValue($value)
{
    return \App\Library\Date::fixValue($value);
}

/**
 * lib_date_ddmmmmmyyyy - Format date as "28 november 2025"
 *
 * @param mixed $date The date value to format
 * @return string|null Formatted date string or null if invalid
 */
function lib_time($dateValue)
{
    if (empty($dateValue)) {
        return '';
    }
    $timestamp = is_numeric($dateValue) ? $dateValue : strtotime($dateValue);
    if ($timestamp === false) {
        return '';
    }
    return date('H:i', $timestamp);
}

function lib_date_ddmmmmmyyyy($date)
{
    if (empty($date)) {
        return null;
    }

    $timestamp = is_numeric($date) ? $date : strtotime($date);
    if ($timestamp === false) {
        return null;
    }

    // Dutch month names
    $months = [
        1 => 'januari', 2 => 'februari', 3 => 'maart', 4 => 'april',
        5 => 'mei', 6 => 'juni', 7 => 'juli', 8 => 'augustus',
        9 => 'september', 10 => 'oktober', 11 => 'november', 12 => 'december'
    ];

    $day = date('j', $timestamp);
    $month = (int)date('n', $timestamp);
    $year = date('Y', $timestamp);

    return $day . ' ' . $months[$month] . ' ' . $year;
}

/**
 * lib_datetime_sortable - Format datetime for sorting
 *
 * @param mixed $date The date value to format
 * @return string Sortable date string (YYYYMMDD HH:MM:SS)
 */
function lib_datetime_sortable($date)
{
    if (empty($date)) {
        return '';
    }

    $timestamp = is_numeric($date) ? $date : strtotime($date);
    if ($timestamp === false) {
        return '';
    }

    return date('Ymd H:i:s', $timestamp);
}

/**
 * lib_date_sortable - Format date for sorting
 *
 * @param mixed $date The date value to format
 * @return string Sortable date string (YYYYMMDD)
 */
function lib_date_sortable($date)
{
    if (empty($date)) {
        return '';
    }

    $timestamp = is_numeric($date) ? $date : strtotime($date);
    if ($timestamp === false) {
        return '';
    }

    return date('Ymd', $timestamp);
}

/**
 * strMediumDate - Format date in VBScript vbMediumDate style (dd-mmm-yyyy)
 * Equivalent of FormatDateTime(date, vbMediumDate) in VBScript.
 * Uses Dutch abbreviated month names.
 *
 * @param mixed $date The date value to format
 * @return string|null Formatted date string or null if invalid
 */
function strMediumDate($date)
{
    if (empty($date) && $date !== 0 && $date !== '0') {
        return null;
    }

    $timestamp = is_numeric($date) ? $date : strtotime($date);
    if ($timestamp === false) {
        return null;
    }

    // Dutch abbreviated month names
    $months = [
        1 => 'jan', 2 => 'feb', 3 => 'mrt', 4 => 'apr',
        5 => 'mei', 6 => 'jun', 7 => 'jul', 8 => 'aug',
        9 => 'sep', 10 => 'okt', 11 => 'nov', 12 => 'dec'
    ];

    $day = date('d', $timestamp);
    $month = (int)date('n', $timestamp);
    $year = date('Y', $timestamp);

    return $day . '-' . $months[$month] . '-' . $year;
}

/**
 * strWeekday - Get Dutch weekday name for a date
 *
 * @param mixed $date The date value
 * @return string|null Dutch weekday name or null if invalid
 */
function strWeekday($date)
{
    if (empty($date) && $date !== 0 && $date !== '0') {
        return null;
    }

    $timestamp = is_numeric($date) ? $date : strtotime($date);
    if ($timestamp === false) {
        return null;
    }

    $days = [
        0 => 'zondag', 1 => 'maandag', 2 => 'dinsdag', 3 => 'woensdag',
        4 => 'donderdag', 5 => 'vrijdag', 6 => 'zaterdag'
    ];

    return $days[(int)date('w', $timestamp)];
}
