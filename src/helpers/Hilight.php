<?php
/**
 * Hilight Helper Class
 *
 * Provides text highlighting functionality for search results.
 *
 * @package App\Library
 */

namespace App\Library;

class Hilight
{
    /**
     * Default CSS class for highlighted text
     */
    private static string $defaultClass = 's';

    /**
     * Highlight occurrences of search text within a string
     *
     * Wraps all case-insensitive matches of $searchText within $text
     * with highlight markup.
     *
     * @param string $text The text to search within
     * @param string $searchText The text to highlight
     * @param string|null $cssClass Optional CSS class for the highlight span (default: 's')
     * @return string The text with highlighted matches
     *
     * @example
     * // Basic usage
     * $result = Hilight::text('Hello World', 'world');
     * // Returns: Hello <span class="s">World</span>
     *
     * @example
     * // Custom CSS class
     * $result = Hilight::text('Hello World', 'world', 'highlight');
     * // Returns: Hello <span class="highlight">World</span>
     */
    public static function text(string $text, string $searchText, ?string $cssClass = null): string
    {
        if ($searchText === '' || $text === '') {
            return $text;
        }

        $class = $cssClass ?? self::$defaultClass;
        $highlightPre = '<span class="' . $class . '">';
        $highlightPost = '</span>';

        // Find all occurrences case-insensitively and replace while preserving original case
        $pattern = '/' . preg_quote($searchText, '/') . '/i';

        return preg_replace_callback($pattern, function ($matches) use ($highlightPre, $highlightPost) {
            return $highlightPre . $matches[0] . $highlightPost;
        }, $text);
    }

    /**
     * Highlight multiple search terms within text
     *
     * @param string $text The text to search within
     * @param array $searchTerms Array of terms to highlight
     * @param string|null $cssClass Optional CSS class for the highlight span
     * @return string The text with highlighted matches
     */
    public static function multiple(string $text, array $searchTerms, ?string $cssClass = null): string
    {
        foreach ($searchTerms as $term) {
            if (!empty($term)) {
                $text = self::text($text, $term, $cssClass);
            }
        }
        return $text;
    }

    /**
     * Set the default CSS class for highlighting
     *
     * @param string $class The CSS class name
     */
    public static function setDefaultClass(string $class): void
    {
        self::$defaultClass = $class;
    }

    /**
     * Get the current default CSS class
     *
     * @return string The current default CSS class
     */
    public static function getDefaultClass(): string
    {
        return self::$defaultClass;
    }
}
