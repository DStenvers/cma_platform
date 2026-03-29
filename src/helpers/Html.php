<?php

namespace App\Library;

/**
 * HTML and Unicode Helper Class
 *
 * Provides utilities for HTML manipulation and Unicode encoding fixes
 * Converted from ASP lib_html.inc library functions
 */
class Html
{
    /**
     * Fix corrupted Unicode characters from encoding issues
     *
     * Fixes malformed UTF-8 sequences that often occur when data was originally
     * in Windows-1252 encoding and got corrupted during UTF-8 conversion.
     * Replaces corrupted characters with proper HTML entities.
     *
     * @param string|null $input Input string with potential encoding issues
     * @return string Fixed string with HTML entities
     */
    public static function fixUnicode(?string $input): string
    {
        $result = $input ?? '';

        if ($result === '') {
            return '';
        }

        // Fix corrupted UTF-8 sequences - common encoding issues
        $replacements = [
            // Dashes and special punctuation
            'â€"' => '-',        // Em dash corruption
            'â€‹' => '',         // Zero-width space corruption
            '–' => '-',          // En dash

            // Accented e
            'Ã©' => '&eacute;',  // é
            'Ã«' => '&euml;',    // ë
            'Ã‹' => '&Euml;',    // Ë
            'Ã¨' => '&egrave;',  // è
            'Ãª' => '&ecirc;',   // ê
            'Ã‰' => '&Egrave;',  // É
            'ï¿½' => '&euml;',   // Replacement character

            // Accented o
            'Ã³' => '&oacute;',  // ó
            'Ã¶' => '&ouml;',    // ö
            'Åˆ' => '&ograve;',  // ò (corrupted)
            'Ã´' => '&ocirc;',   // ô

            // Accented a
            'Ã¡' => '&aacute;',  // á
            'Ã¤' => '&auml;',    // ä
            'Ã¢' => '&acirc;',   // â

            // Accented u
            'Ã¼' => '&uuml;',    // ü

            // Accented i
            'Ã¯' => '&iuml;',    // ï
            'Ã®' => '&icirc;',   // î

            // Special characters
            'â€¢' => '•',        // Bullet point
            'â‚¬' => '&euro;',   // Euro symbol
            'â€™' => "'",        // Right single quote
            'Ã§' => '&ccedil;',  // ç
            'â€' => '&lsquo;',   // Left single quote
            'â€œ' => '&lsquo;',  // Left double quote (mapped to single)
            'â€' => '&rsquo;',   // Right single quote
            'â€˜' => '&rsquo;',  // Right single quote variant
            'Â½' => '&frac12;',  // 1/2 fraction

            // Turkish dotless i
            'ı' => '&inodot;',   // ı
        ];

        // Apply all replacements
        foreach ($replacements as $search => $replace) {
            $result = str_replace($search, $replace, $result);
        }

        return $result;
    }

    /**
     * Encode special characters to HTML entities
     *
     * @param string|null $input Input string
     * @return string HTML-encoded string
     */
    public static function encode(?string $input): string
    {
        return htmlspecialchars($input ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Decode HTML entities to characters
     *
     * @param string|null $input HTML-encoded string
     * @return string Decoded string
     */
    public static function decode(?string $input): string
    {
        return html_entity_decode($input ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Strip all HTML tags from string
     *
     * @param string|null $input Input string with HTML
     * @param string|null $allowedTags Optional allowed tags (e.g., '<p><a>')
     * @return string String without HTML tags
     */
    public static function stripTags(?string $input, ?string $allowedTags = null): string
    {
        return strip_tags($input ?? '', $allowedTags);
    }

    /**
     * Check if string contains HTML tags
     *
     * @param string|null $input Input string
     * @return bool True if HTML tags detected
     */
    public static function containsHtml(?string $input): bool
    {
        $stripped = strip_tags($input ?? '');
        return $stripped !== ($input ?? '');
    }
}
