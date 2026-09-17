<?php

namespace App\Library;

/**
 * The one size rule for every upload sink (LibUpload, the image wizard, the
 * crop handler, the file browser): UPLOAD_MAX_MB from the settings, never
 * above what php.ini allows (upload_max_filesize and post_max_size). A file
 * that exceeds it is refused with a message that names the limit.
 */
final class Upload
{
    /** Effective limit in bytes: the setting when it is set and lower than php.ini, else php.ini. */
    public static function maxBytes(): int
    {
        $ini = min(self::iniBytes((string) ini_get('upload_max_filesize')), self::iniBytes((string) ini_get('post_max_size')));
        $setting = (int) Settings::get('upload_max_mb') * 1024 * 1024;
        if ($setting > 0 && ($ini <= 0 || $setting < $ini)) {
            return $setting;
        }
        return $ini > 0 ? $ini : PHP_INT_MAX;
    }

    /** The message for a file that is too large, or null when it fits. */
    public static function sizeError(array $file): ?string
    {
        $size = (int) ($file['size'] ?? 0);
        $max = self::maxBytes();
        if ($size > $max) {
            return 'Bestand te groot (' . self::mb($size) . ' MB, maximaal ' . self::mb($max) . ' MB).';
        }
        return null;
    }

    /** "128M", "2G", "512K" or a plain byte count → bytes. */
    public static function iniBytes(string $value): int
    {
        $value = trim($value);
        if ($value === '' || $value === '-1') {
            return 0;
        }
        $unit = strtolower(substr($value, -1));
        $number = (float) $value;
        switch ($unit) {
            case 'g': return (int) ($number * 1024 * 1024 * 1024);
            case 'm': return (int) ($number * 1024 * 1024);
            case 'k': return (int) ($number * 1024);
            default:  return (int) $number;
        }
    }

    private static function mb(int $bytes): string
    {
        return rtrim(rtrim(number_format($bytes / 1048576, 1, '.', ''), '0'), '.');
    }
}
