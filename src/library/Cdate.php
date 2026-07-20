<?php

namespace App\Library;

/**
 * VBScript runtime-semantics helpers for converted ASP code.
 *
 * Platform-owned since v1.29.57 (formerly a converter-synced app/library copy;
 * that sync chain — converter/templates/library + sync-helpers.sh — no longer
 * exists). Deliberately separate from the clean Date/Str helpers: these
 * functions reproduce VBScript quirks (locale-dependent parsing, Empty
 * coercion) that a modern PHP API should not carry — same category as the
 * ADO-emulating RecordSet. Use only from converted ASP code.
 */
class Cdate
{
    /**
     * VBScript CDate() equivalent.
     *
     * Two deliberate divergences from a bare strtotime():
     * - Day-first Dutch dates (dd-mm-yyyy, dd/mm/yyyy, optional time) parse the
     *   way CDate did under the nl-NL locale; strtotime() reads slash dates
     *   month-first and rejects a day > 12 ("17/12/2015").
     * - Null/'' (VBScript Empty) returns the VBScript zero-date instead of
     *   throwing, matching CDate(Empty) = 1899-12-30.
     *
     * @param mixed $value Date string, timestamp, or DateTime object
     * @return string Y-m-d H:i:s
     * @throws \Exception "Type mismatch" when the value cannot be parsed
     */
    public static function cDate(mixed $value): string
    {
        if ($value === null || $value === '' || $value === false) {
            return '1899-12-30 00:00:00';
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }
        if (is_int($value) || is_float($value)) {
            return date('Y-m-d H:i:s', (int)$value);
        }
        $s = trim((string)$value);
        if ($s === '') {
            return '1899-12-30 00:00:00';
        }
        $norm = str_replace('/', '-', $s);
        foreach (['d-m-Y H:i:s', 'd-m-Y H:i', 'd-m-Y'] as $fmt) {
            $dt = \DateTime::createFromFormat('!' . $fmt, $norm);
            $err = \DateTime::getLastErrors();
            $clean = $err === false
                || ($err['warning_count'] === 0 && $err['error_count'] === 0);
            if ($dt !== false && $clean) {
                return $dt->format('Y-m-d H:i:s');
            }
        }
        $ts = strtotime($s);
        if ($ts === false) {
            throw new \Exception('Type mismatch');
        }
        return date('Y-m-d H:i:s', $ts);
    }
}
