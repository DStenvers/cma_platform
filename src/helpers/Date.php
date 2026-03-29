<?php

namespace App\Library;

/**
 * Date Helper Class
 *
 * Provides date manipulation and formatting utilities.
 * Maps VBScript lib_datum_* functions to modern PHP equivalents.
 *
 * Key Features:
 * - Dutch locale support (month names, weekday names)
 * - Relative date formatting (Vandaag, Morgen, Gisteren)
 * - Multiple date format outputs
 * - Age calculation
 * - Date validation
 *
 * Usage:
 *   $age = Date::age('1990-05-15');
 *   $formatted = Date::shortDate('2024-03-15'); // 15-03-2024
 *   $dutch = Date::mediumDate('2024-03-15'); // 15 Mrt 2024
 */
class Date
{
    /**
     * Dutch short month names (1-12)
     */
    private static array $shortMonths = [
        1 => 'Jan', 2 => 'Feb', 3 => 'Mrt', 4 => 'Apr',
        5 => 'Mei', 6 => 'Jun', 7 => 'Jul', 8 => 'Aug',
        9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Dec'
    ];

    /**
     * Dutch long month names (1-12)
     */
    private static array $longMonths = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maart', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Augustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'December'
    ];

    /**
     * Dutch short weekday names (0=Sunday, 6=Saturday)
     */
    private static array $shortWeekdays = [
        0 => 'Zo', 1 => 'Ma', 2 => 'Di', 3 => 'Wo',
        4 => 'Do', 5 => 'Vr', 6 => 'Za'
    ];

    /**
     * Dutch long weekday names (0=Sunday, 6=Saturday)
     */
    private static array $longWeekdays = [
        0 => 'Zondag', 1 => 'Maandag', 2 => 'Dinsdag', 3 => 'Woensdag',
        4 => 'Donderdag', 5 => 'Vrijdag', 6 => 'Zaterdag'
    ];

    /**
     * Parse a date value to timestamp
     *
     * @param mixed $date Date string, timestamp, or DateTime object
     * @return int|false Timestamp or false on failure
     */
    private static function toTimestamp(mixed $date): int|false
    {
        if ($date === null || $date === '') {
            return false;
        }

        if ($date instanceof \DateTime || $date instanceof \DateTimeImmutable) {
            return $date->getTimestamp();
        }

        if (is_int($date)) {
            return $date;
        }

        return strtotime((string)$date);
    }

    /**
     * Calculate age in years at a reference date
     *
     * @param mixed $birthday Birthday date
     * @param mixed $referenceDate Reference date (default: today)
     * @return int|null Age in years, or null if invalid
     */
    public static function age(mixed $birthday, mixed $referenceDate = null): ?int
    {
        $birthTs = self::toTimestamp($birthday);
        if ($birthTs === false) {
            return null;
        }

        $refTs = $referenceDate !== null ? self::toTimestamp($referenceDate) : time();
        if ($refTs === false) {
            return null;
        }

        $birthDate = new \DateTime();
        $birthDate->setTimestamp($birthTs);

        $refDate = new \DateTime();
        $refDate->setTimestamp($refTs);

        $diff = $refDate->diff($birthDate);
        return $diff->y;
    }

    /**
     * Format date as dd-mm-yyyy
     *
     * @param mixed $date Date value
     * @return string Formatted date or empty string
     */
    public static function shortDate(mixed $date): string
    {
        $ts = self::toTimestamp($date);
        if ($ts === false) {
            return '';
        }

        return sprintf('%02d-%02d-%04d', date('j', $ts), date('n', $ts), date('Y', $ts));
    }

    /**
     * Format date as yyyy-mm-dd (ISO/sortable format)
     *
     * @param mixed $date Date value
     * @return string Formatted date or empty string
     */
    public static function sortable(mixed $date): string
    {
        $ts = self::toTimestamp($date);
        if ($ts === false) {
            return '';
        }

        return date('Y-m-d', $ts);
    }

    /**
     * Format datetime as yyyy-mm-dd_hh_mm (sortable format)
     *
     * @param mixed $date Date value
     * @return string Formatted datetime or empty string
     */
    public static function sortableDateTime(mixed $date): string
    {
        $ts = self::toTimestamp($date);
        if ($ts === false) {
            return '';
        }

        return sprintf('%04d-%02d-%02d_%02d_%02d',
            date('Y', $ts), date('n', $ts), date('j', $ts),
            date('G', $ts), date('i', $ts)
        );
    }

    /**
     * Format time as hh:mm
     *
     * @param mixed $date Date/time value
     * @return string Formatted time or empty string
     */
    public static function time(mixed $date): string
    {
        $ts = self::toTimestamp($date);
        if ($ts === false) {
            return '';
        }

        return date('H:i', $ts);
    }

    /**
     * Get Dutch short month name (Jan, Feb, Mrt, etc.)
     *
     * @param int $month Month number (1-12)
     * @return string Short month name or empty string
     */
    public static function shortMonth(int $month): string
    {
        return self::$shortMonths[$month] ?? '';
    }

    /**
     * Get Dutch long month name (Januari, Februari, etc.)
     *
     * @param int $month Month number (1-12)
     * @return string Long month name or empty string
     */
    public static function longMonth(int $month): string
    {
        return self::$longMonths[$month] ?? '';
    }

    /**
     * Get Dutch short weekday name (Ma, Di, Wo, etc.)
     *
     * @param mixed $date Date value
     * @return string Short weekday name or empty string
     */
    public static function shortWeekday(mixed $date): string
    {
        $ts = self::toTimestamp($date);
        if ($ts === false) {
            return '';
        }

        $dayOfWeek = (int)date('w', $ts);
        return self::$shortWeekdays[$dayOfWeek] ?? '';
    }

    /**
     * Get Dutch long weekday name (Maandag, Dinsdag, etc.)
     *
     * @param mixed $date Date value
     * @return string Long weekday name or empty string
     */
    public static function longWeekday(mixed $date): string
    {
        $ts = self::toTimestamp($date);
        if ($ts === false) {
            return '';
        }

        $dayOfWeek = (int)date('w', $ts);
        return self::$longWeekdays[$dayOfWeek] ?? '';
    }

    /**
     * Get relative date name if within range (Vandaag, Morgen, Gisteren, etc.)
     *
     * @param mixed $date Date value
     * @param bool $useRelative Whether to return relative names (default: true)
     * @return string|null Relative name or null if not in range
     */
    public static function relative(mixed $date, bool $useRelative = true): ?string
    {
        if (!$useRelative) {
            return null;
        }

        $ts = self::toTimestamp($date);
        if ($ts === false) {
            return null;
        }

        // Compare dates without time
        $dateOnly = strtotime(date('Y-m-d', $ts));
        $todayOnly = strtotime(date('Y-m-d'));

        $diff = ($dateOnly - $todayOnly) / 86400; // Days difference

        return match ((int)$diff) {
            -2 => 'Eergisteren',
            -1 => 'Gisteren',
            0 => 'Vandaag',
            1 => 'Morgen',
            2 => 'Overmorgen',
            default => null
        };
    }

    /**
     * Format as medium date: dd mmm yyyy (e.g., 15 Mrt 2024)
     * Returns relative name if within range and enabled
     *
     * @param mixed $date Date value
     * @param bool $useRelative Use relative date names (default: from Application setting)
     * @return string Formatted date or empty string
     */
    public static function mediumDate(mixed $date, ?bool $useRelative = null): string
    {
        $ts = self::toTimestamp($date);
        if ($ts === false) {
            return '';
        }

        // Check Application setting if not explicitly specified
        if ($useRelative === null) {
            $useRelative = Application::get('library_relative_dates', false) ? true : false;
        }

        $relativeName = self::relative($date, $useRelative);
        if ($relativeName !== null) {
            return $relativeName;
        }

        return sprintf('%d %s %d',
            date('j', $ts),
            self::shortMonth((int)date('n', $ts)),
            date('Y', $ts)
        );
    }

    /**
     * Format as long date: dd mmmm yyyy (e.g., 15 maart 2024)
     * Returns relative name if within range and enabled
     *
     * @param mixed $date Date value
     * @param bool $useRelative Use relative date names (default: from Application setting)
     * @return string Formatted date or empty string
     */
    public static function longDate(mixed $date, ?bool $useRelative = null): string
    {
        $ts = self::toTimestamp($date);
        if ($ts === false) {
            return '';
        }

        if ($useRelative === null) {
            $useRelative = Application::get('library_relative_dates', false) ? true : false;
        }

        $relativeName = self::relative($date, $useRelative);
        if ($relativeName !== null) {
            return $relativeName;
        }

        return sprintf('%d %s %d',
            date('j', $ts),
            strtolower(self::longMonth((int)date('n', $ts))),
            date('Y', $ts)
        );
    }

    /**
     * Format as full date: Weekday dd mmmm yyyy (e.g., Vrijdag 15 Maart 2024)
     * Returns relative name if within range and enabled
     *
     * @param mixed $date Date value
     * @param bool $useRelative Use relative date names (default: from Application setting)
     * @return string Formatted date or empty string
     */
    public static function fullDate(mixed $date, ?bool $useRelative = null): string
    {
        $ts = self::toTimestamp($date);
        if ($ts === false) {
            return '';
        }

        if ($useRelative === null) {
            $useRelative = Application::get('library_relative_dates', false) ? true : false;
        }

        $relativeName = self::relative($date, $useRelative);
        if ($relativeName !== null) {
            return $relativeName;
        }

        return sprintf('%s %d %s %d',
            self::longWeekday($ts),
            date('j', $ts),
            self::longMonth((int)date('n', $ts)),
            date('Y', $ts)
        );
    }

    /**
     * Extract day from date
     *
     * @param mixed $date Date value
     * @return string Day (1-31) or empty string
     */
    public static function day(mixed $date): string
    {
        $ts = self::toTimestamp($date);
        if ($ts === false) {
            return '';
        }

        return date('j', $ts);
    }

    /**
     * Extract month from date
     *
     * @param mixed $date Date value
     * @return string Month (1-12) or empty string
     */
    public static function month(mixed $date): string
    {
        $ts = self::toTimestamp($date);
        if ($ts === false) {
            return '';
        }

        return date('n', $ts);
    }

    /**
     * Extract year from date
     *
     * @param mixed $date Date value
     * @return string Year or empty string
     */
    public static function year(mixed $date): string
    {
        $ts = self::toTimestamp($date);
        if ($ts === false) {
            return '';
        }

        return date('Y', $ts);
    }

    /**
     * Validate date from separate day, month, year values
     * Empty values are considered valid (optional date)
     *
     * @param mixed $day Day value
     * @param mixed $month Month value
     * @param mixed $year Year value
     * @return bool True if valid or all empty
     */
    public static function isValid(mixed $day, mixed $month, mixed $year): bool
    {
        // All empty is valid (optional date field)
        if (($day === '' || $day === null) &&
            ($month === '' || $month === null) &&
            ($year === '' || $year === null)) {
            return true;
        }

        // All must be provided
        if ($day === '' || $day === null ||
            $month === '' || $month === null ||
            $year === '' || $year === null) {
            return false;
        }

        return checkdate((int)$month, (int)$day, (int)$year);
    }

    /**
     * Check if a date value is parseable
     *
     * @param mixed $date Date value
     * @return bool True if valid date
     */
    public static function isParseable(mixed $date): bool
    {
        return self::toTimestamp($date) !== false;
    }

    /**
     * Convert date to GMT string format (YYYYMMDDThhmmssZ)
     * Used for iCal and other standards
     *
     * @param mixed $date Date value
     * @return string GMT formatted string
     */
    public static function toGMT(mixed $date): string
    {
        $ts = self::toTimestamp($date);
        if ($ts === false) {
            return '';
        }

        // Convert to UTC
        $utc = gmdate('Y-m-d H:i:s', $ts);
        $utcTs = strtotime($utc . ' UTC');

        return gmdate('Ymd\THis\Z', $utcTs);
    }

    /**
     * Check if daylight saving time is active for a date
     *
     * @param mixed $date Date value
     * @return bool True if DST is active
     */
    public static function isDST(mixed $date): bool
    {
        $ts = self::toTimestamp($date);
        if ($ts === false) {
            return false;
        }

        return (bool)date('I', $ts);
    }

    /**
     * Get DST offset in hours (0 or 1)
     *
     * @param mixed $date Date value
     * @return int 1 if DST active, 0 otherwise
     */
    public static function dstOffset(mixed $date): int
    {
        return self::isDST($date) ? 1 : 0;
    }

    /**
     * Replace English month abbreviations with Dutch
     * Useful for fixing locale issues in date strings
     *
     * @param string $text Text containing English month names
     * @return string Text with Dutch month names
     */
    public static function fixMonthNames(string $text): string
    {
        $replacements = [
            ' may ' => ' mei ',
            ' May ' => ' Mei ',
            ' oct ' => ' okt ',
            ' Oct ' => ' Okt ',
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $text);
    }

    /**
     * Add interval to date
     *
     * @param mixed $date Date value
     * @param int $value Number to add
     * @param string $unit Unit: 'day', 'month', 'year', 'hour', 'minute'
     * @return string New date in Y-m-d H:i:s format, or empty on error
     */
    public static function add(mixed $date, int $value, string $unit = 'day'): string
    {
        $ts = self::toTimestamp($date);
        if ($ts === false) {
            return '';
        }

        $dateTime = new \DateTime();
        $dateTime->setTimestamp($ts);

        $intervalSpec = match ($unit) {
            'year', 'years' => "P{$value}Y",
            'month', 'months' => "P{$value}M",
            'day', 'days' => "P{$value}D",
            'hour', 'hours' => "PT{$value}H",
            'minute', 'minutes' => "PT{$value}M",
            'second', 'seconds' => "PT{$value}S",
            default => "P{$value}D"
        };

        // Handle negative values
        if ($value < 0) {
            $intervalSpec = str_replace(['P-', 'PT-'], ['P', 'PT'], $intervalSpec);
            $interval = new \DateInterval($intervalSpec);
            $interval->invert = 1;
        } else {
            $interval = new \DateInterval($intervalSpec);
        }

        $dateTime->add($interval);
        return $dateTime->format('Y-m-d H:i:s');
    }

    /**
     * Fix/format a value that might be a date for display
     *
     * If the value looks like a date, formats it as dd-mm-yyyy.
     * Otherwise returns the original value unchanged.
     * Used for displaying database values that may contain dates.
     *
     * @param mixed $value Value to fix
     * @return string Formatted value
     */
    public static function fixValue(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $strValue = (string)$value;

        // Check if it looks like a date/datetime (YYYY-MM-DD or contains date patterns)
        // Common database formats: 2024-03-15, 2024-03-15 14:30:00, etc.
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $strValue)) {
            $ts = strtotime($strValue);
            if ($ts !== false) {
                $year = (int)date('Y', $ts);

                // Year 1899 indicates a time-only field (MS Access stores times this way)
                if ($year === 1899) {
                    return sprintf('%02d:%02d', date('G', $ts), date('i', $ts));
                }

                // Format as dd-mm-yyyy, include time if present and not midnight
                $hasTime = (date('H:i:s', $ts) !== '00:00:00');
                if ($hasTime) {
                    return sprintf('%02d-%02d-%04d %02d:%02d',
                        date('j', $ts), date('n', $ts), date('Y', $ts),
                        date('G', $ts), date('i', $ts)
                    );
                }
                return sprintf('%02d-%02d-%04d', date('j', $ts), date('n', $ts), date('Y', $ts));
            }
        }

        // Not a date, return as-is
        return $strValue;
    }

    /**
     * Calculate difference between two dates
     *
     * @param mixed $date1 First date
     * @param mixed $date2 Second date
     * @param string $unit Unit: 'day', 'month', 'year', 'hour', 'minute'
     * @return int|null Difference in specified unit, or null on error
     */
    public static function diff(mixed $date1, mixed $date2, string $unit = 'day'): ?int
    {
        $ts1 = self::toTimestamp($date1);
        $ts2 = self::toTimestamp($date2);

        if ($ts1 === false || $ts2 === false) {
            return null;
        }

        $dt1 = new \DateTime();
        $dt1->setTimestamp($ts1);

        $dt2 = new \DateTime();
        $dt2->setTimestamp($ts2);

        $diff = $dt1->diff($dt2);

        return match ($unit) {
            'year', 'years' => $diff->y * ($diff->invert ? -1 : 1),
            'month', 'months' => ($diff->y * 12 + $diff->m) * ($diff->invert ? -1 : 1),
            'day', 'days' => $diff->days * ($diff->invert ? -1 : 1),
            'hour', 'hours' => (int)(($ts2 - $ts1) / 3600),
            'minute', 'minutes' => (int)(($ts2 - $ts1) / 60),
            'second', 'seconds' => $ts2 - $ts1,
            default => $diff->days * ($diff->invert ? -1 : 1)
        };
    }
}
