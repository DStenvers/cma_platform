<?php

namespace App\Library;

/**
 * Array Helper Class
 *
 * Provides safe array access and manipulation utilities.
 * Prevents common errors like undefined keys, empty arrays, etc.
 * Maps VBScript array handling patterns and lib_array_* functions to PHP.
 *
 * Key Features:
 * - Safe array access with null/empty checking
 * - Array searching and filtering
 * - Sorting and joining
 * - Multi-dimensional array operations
 * - VBScript equivalents (Split, Join, UBound, InArray)
 *
 * Usage:
 *   $value = Arr::get($array, 'key', 'default');
 *   $nested = Arr::getNested($array, [0, 'name'], 'default');
 *   $found = Arr::find($array, 'value');
 *   $joined = Arr::join($array, ', ');
 */
class Arr
{
    // ============================================================================
    // TYPE CHECKING METHODS
    // ============================================================================

    /**
     * Check if a value is array-like (array or ArrayObject/ColumnMajorArray)
     *
     * VBScript IsArray() needs to work with both native arrays and
     * ArrayObject instances returned by Cache::retrieve().
     *
     * @param mixed $value Value to check
     * @return bool True if array-like
     */
    public static function isArray($value): bool
    {
        return is_array($value) || $value instanceof \ArrayObject;
    }

    // ============================================================================
    // RECORDSET FIELD ACCESS (case-insensitive)
    // ============================================================================

    /**
     * Get a field value from a recordset row with case-insensitive fallback
     *
     * ASP/VBScript recordset field access is case-insensitive. After conversion
     * to PHP, plain array access is case-sensitive, causing errors when ODBC
     * returns columns with different casing than expected. This method provides
     * a case-insensitive fallback for safe field access.
     *
     * @param array|null $row The recordset row (associative array)
     * @param string $key The field name to retrieve
     * @param mixed $default Default value if field doesn't exist
     * @return mixed The field value or default
     */
    public static function field($row, string $key, $default = null)
    {
        if (!is_array($row)) {
            return $default;
        }
        if (array_key_exists($key, $row)) {
            return $row[$key];
        }
        // Case-insensitive fallback
        $lowerKey = strtolower($key);
        foreach ($row as $k => $v) {
            if (strtolower($k) === $lowerKey) {
                return $v;
            }
        }
        return $default;
    }

    // ============================================================================
    // SAFE ACCESS METHODS (with null/empty checking)
    // ============================================================================

    /**
     * Safely get a value from an array with optional default
     *
     * Supports dot notation for nested access: "user.profile.name"
     *
     * @param array|null $array The array to access
     * @param string|int $key The key to retrieve
     * @param mixed $default Default value if key doesn't exist
     * @return mixed The value or default
     */
    public static function get($array, $key, $default = null)
    {
        if (!is_array($array)) {
            return $default;
        }

        // Direct key access
        if (isset($array[$key])) {
            return $array[$key];
        }

        // Support dot notation for nested access
        if (is_string($key) && strpos($key, '.') !== false) {
            foreach (explode('.', $key) as $segment) {
                if (is_array($array) && isset($array[$segment])) {
                    $array = $array[$segment];
                } else {
                    return $default;
                }
            }
            return $array;
        }

        return $array[$key] ?? $default;
    }

    /**
     * Safely get a nested value from a multi-dimensional array
     *
     * Example: Arr::getNested($arr, [0, 'name']) for $arr[0]['name']
     *
     * @param array|null $array The array to access
     * @param array $keys Array of keys to traverse
     * @param mixed $default Default value if path doesn't exist
     * @return mixed The value or default
     */
    public static function getNested($array, array $keys, $default = null)
    {
        if (!is_array($array)) {
            return $default;
        }

        $current = $array;

        foreach ($keys as $key) {
            if (!is_array($current) || !isset($current[$key])) {
                return $default;
            }
            $current = $current[$key];
        }

        return $current;
    }

    /**
     * Check if array has a specific key
     *
     * @param array|null $array The array to check
     * @param mixed $key The key to check for
     * @return bool True if key exists
     */
    public static function has($array, $key): bool
    {
        return is_array($array) && isset($array[$key]);
    }

    /**
     * Check if array has a nested key path
     *
     * @param array|null $array The array to check
     * @param array $keys Array of keys to check
     * @return bool True if all keys exist
     */
    public static function hasNested($array, array $keys): bool
    {
        if (!is_array($array)) {
            return false;
        }

        $current = $array;

        foreach ($keys as $key) {
            if (!is_array($current) || !isset($current[$key])) {
                return false;
            }
            $current = $current[$key];
        }

        return true;
    }

    /**
     * Check if array is not empty
     *
     * @param mixed $array The value to check
     * @return bool True if array and not empty
     */
    public static function isNotEmpty($array): bool
    {
        return is_array($array) && !empty($array);
    }

    // ============================================================================
    // ARRAY ELEMENT ACCESS
    // ============================================================================

    /**
     * Get first element of array
     *
     * @param array|null $array The array
     * @param mixed $default Default if empty
     * @return mixed First element or default
     */
    public static function first($array, $default = null)
    {
        if (!self::isNotEmpty($array)) {
            return $default;
        }

        return reset($array);
    }

    /**
     * Get last element of array
     *
     * @param array|null $array The array
     * @param mixed $default Default if empty
     * @return mixed Last element or default
     */
    public static function last($array, $default = null)
    {
        if (!self::isNotEmpty($array)) {
            return $default;
        }

        return end($array);
    }

    // ============================================================================
    // VBSCRIPT EQUIVALENTS
    // ============================================================================

    /**
     * Convert VBScript array to PHP array (Split equivalent)
     *
     * VBScript uses Split() which returns array with UBound
     * PHP arrays are zero-indexed and work differently
     *
     * @param string $string String to split
     * @param string $delimiter Delimiter (default: comma)
     * @return array Array of values
     */
    public static function split(string $string, string $delimiter = ','): array
    {
        if (empty($string)) {
            return [];
        }

        return array_map('trim', explode($delimiter, $string));
    }

    /**
     * Join array elements (VBScript Join equivalent)
     *
     * @param array $array Array to join
     * @param string $separator Separator string (default: '')
     * @return string Joined string
     */
    public static function join(array $array, string $separator = ''): string
    {
        return implode($separator, $array);
    }

    /**
     * Get array size (VBScript Count equivalent)
     *
     * @param array|null $array The array
     * @return int Count of elements
     */
    public static function count($array): int
    {
        return is_array($array) ? count($array) : 0;
    }

    /**
     * Get highest index (VBScript UBound exact equivalent)
     *
     * VBScript UBound returns the highest index (count - 1)
     *
     * @param array|null $array The array
     * @return int Highest index or -1 if empty
     */
    public static function ubound($array): int
    {
        if (!self::isNotEmpty($array)) {
            return -1;
        }

        return count($array) - 1;
    }

    /**
     * Check if value exists in array (VBScript InArray equivalent)
     *
     * @param mixed $needle Value to search for (can be array or single value)
     * @param array|null $haystack Array to search in
     * @param bool $strict Use strict comparison
     * @return bool True if found
     */
    public static function contains($needle, $haystack, bool $strict = false): bool
    {
        if (!is_array($haystack)) {
            return false;
        }

        // Handle both parameter orders for compatibility
        if (is_array($needle)) {
            // Arr::contains($array, $value) - swap parameters
            return in_array($needle, $haystack, $strict);
        }

        return in_array($needle, $haystack, $strict);
    }

    // ============================================================================
    // LIB_ARRAY.INC EQUIVALENTS
    // ============================================================================

    /**
     * Enhanced split that always returns array (Lib_Array_Split equivalent)
     *
     * Returns array even if delimiter not found (single-element array)
     *
     * @param string $string String to split
     * @param string $delimiter Delimiter (default: ',')
     * @return array Array of values (never null, always array)
     */
    public static function splitAlways(string $string, string $delimiter = ','): array
    {
        if (empty($string)) {
            return [$string];
        }

        if (strpos($string, $delimiter) !== false) {
            return array_map('trim', explode($delimiter, $string));
        } else {
            return [$string];
        }
    }

    /**
     * Find value with case-insensitive trim comparison (Lib_Array_Find equivalent)
     *
     * Returns index if found, -1 if not found
     *
     * @param array|null $array Array to search
     * @param mixed $searchFor Value to find
     * @return int Index if found, -1 if not found
     */
    public static function findTrimmed($array, $searchFor): int
    {
        if (!is_array($array)) {
            return -1;
        }

        $searchTrimmed = strtolower(trim((string)$searchFor));

        foreach ($array as $index => $value) {
            if (strtolower(trim((string)$value)) === $searchTrimmed) {
                return $index;
            }
        }

        return -1;
    }

    /**
     * Find using partial match (Lib_Array_Find_Instr equivalent)
     *
     * Checks if $searchFor contains any array element (case-insensitive)
     *
     * @param array|null $array Array to search in
     * @param string $searchFor String to search within
     * @return int Index if found, -1 if not found
     */
    public static function findInstr($array, string $searchFor): int
    {
        if (!is_array($array)) {
            return -1;
        }

        $searchLower = strtolower($searchFor);

        foreach ($array as $index => $value) {
            if (stripos($searchFor, (string)$value) !== false) {
                return $index;
            }
        }

        return -1;
    }

    /**
     * Join array skipping empty values (Lib_Array_Join equivalent)
     *
     * Unlike standard join, this skips empty string values
     *
     * @param array|null $array Array to join
     * @param string $separator Separator string
     * @return string Joined string (empty values skipped)
     */
    public static function joinSkipEmpty($array, string $separator = ''): string
    {
        if (!is_array($array)) {
            return '';
        }

        $result = '';

        foreach ($array as $value) {
            if ($value !== '' && $value !== null) {
                $result .= ($result !== '' ? $separator : '') . $value;
            }
        }

        return $result;
    }

    /**
     * Random shuffle (lib_Array_ReOrderRandom equivalent)
     *
     * Returns array with elements in random order
     *
     * @param array|null $array Array to shuffle
     * @return array Shuffled array
     */
    public static function shuffle($array): array
    {
        if (!is_array($array)) {
            return [];
        }

        $result = $array;
        shuffle($result);
        return $result;
    }

    /**
     * Find in nested array by field (lib_array_find_nested equivalent)
     *
     * Searches nested array like $arr[0][elementID], $arr[1][elementID], etc.
     *
     * @param array|null $array Nested array to search
     * @param int $elementID Element index to search in
     * @param mixed $searchFor Value to find
     * @return int Index if found, -1 if not found
     */
    public static function findNestedByIndex($array, int $elementID, $searchFor): int
    {
        if (!is_array($array)) {
            return -1;
        }

        $searchTrimmed = strtolower(trim((string)$searchFor));

        foreach ($array as $index => $row) {
            if (is_array($row) && isset($row[$elementID])) {
                if (strtolower(trim((string)$row[$elementID])) === $searchTrimmed) {
                    return $index;
                }
            }
        }

        return -1;
    }

    /**
     * Join 2D array by row index (lib_array_join_2dim equivalent)
     *
     * For VBScript-style 2D arrays stored as $arr[elementID][row]
     *
     * @param array|null $array 2D array
     * @param int $elementID Element index to join
     * @param string $separator Separator string
     * @return string Joined string
     */
    public static function join2DByRow($array, int $elementID, string $separator = ''): string
    {
        if (!is_array($array) || !isset($array[$elementID])) {
            return '';
        }

        if (!is_array($array[$elementID])) {
            return (string)$array[$elementID];
        }

        return implode($separator, $array[$elementID]);
    }

    /**
     * Find in 2D array by row index (lib_array_find_2dim equivalent)
     *
     * For VBScript-style 2D arrays stored as $arr[elementID][row]
     *
     * @param array|null $array 2D array
     * @param int $elementID Element index to search in
     * @param mixed $searchFor Value to find
     * @return int Index if found, -1 if not found
     */
    public static function find2DByRow($array, int $elementID, $searchFor): int
    {
        if (!is_array($array) || !isset($array[$elementID]) || !is_array($array[$elementID])) {
            return -1;
        }

        $searchTrimmed = strtolower(trim((string)$searchFor));

        foreach ($array[$elementID] as $index => $value) {
            if (strtolower(trim((string)$value)) === $searchTrimmed) {
                return $index;
            }
        }

        return -1;
    }

    // ============================================================================
    // SEARCH AND FIND
    // ============================================================================

    /**
     * Find value in array (returns key or false)
     *
     * @param array $array Array to search
     * @param mixed $value Value to find
     * @param bool $strict Strict comparison (default: false)
     * @return int|string|false Key if found, false otherwise
     */
    public static function find(array $array, $value, bool $strict = false)
    {
        return array_search($value, $array, $strict);
    }

    /**
     * Find value in nested array
     *
     * Searches for a value in a multi-dimensional array
     *
     * @param array $array Array to search
     * @param string $field Field name to search in
     * @param mixed $value Value to find
     * @return array|null Found element or null
     */
    public static function findNested(array $array, string $field, $value): ?array
    {
        foreach ($array as $element) {
            if (is_array($element) && isset($element[$field]) && $element[$field] == $value) {
                return $element;
            }
        }

        return null;
    }

    /**
     * Find value in 2D array (alias for findNested)
     *
     * @param array $array Array to search
     * @param string $field Field name to search in
     * @param mixed $value Value to find
     * @return array|null Found element or null
     */
    public static function find2D(array $array, string $field, $value): ?array
    {
        return self::findNested($array, $field, $value);
    }

    // ============================================================================
    // SORTING
    // ============================================================================

    /**
     * Sort array
     *
     * @param array|null $array Array to sort
     * @param int $flags Sorting flags (SORT_REGULAR, SORT_NUMERIC, SORT_STRING, etc.)
     * @return array Sorted array
     */
    public static function sort($array, int $flags = SORT_REGULAR): array
    {
        if (!is_array($array)) {
            return [];
        }

        $result = $array;
        sort($result, $flags);

        return $result;
    }

    /**
     * Sort multi-dimensional array by specific field
     *
     * @param array $array Array to sort
     * @param string $field Field name to sort by
     * @param int $direction SORT_ASC or SORT_DESC
     * @return array Sorted array
     */
    public static function sortBy(array $array, string $field, int $direction = SORT_ASC): array
    {
        usort($array, function($a, $b) use ($field, $direction) {
            $aVal = is_array($a) ? ($a[$field] ?? null) : (is_object($a) ? ($a->$field ?? null) : null);
            $bVal = is_array($b) ? ($b[$field] ?? null) : (is_object($b) ? ($b->$field ?? null) : null);

            if ($aVal == $bVal) {
                return 0;
            }

            $result = ($aVal < $bVal) ? -1 : 1;
            return ($direction === SORT_DESC) ? -$result : $result;
        });

        return $array;
    }

    // ============================================================================
    // FILTERING AND TRANSFORMATION
    // ============================================================================

    /**
     * Filter array by callback
     *
     * @param array $array Array to filter
     * @param callable $callback Filter function
     * @return array Filtered array
     */
    public static function filter(array $array, callable $callback): array
    {
        return array_filter($array, $callback);
    }

    /**
     * Filter array to remove empty values
     *
     * @param array|null $array The array to filter
     * @return array Filtered array
     */
    public static function removeEmpty($array): array
    {
        if (!is_array($array)) {
            return [];
        }

        return array_filter($array, function ($value) {
            return !empty($value) || $value === 0 || $value === '0';
        });
    }

    /**
     * Map array with callback
     *
     * @param array $array Array to map
     * @param callable $callback Map function
     * @return array Mapped array
     */
    public static function map(array $array, callable $callback): array
    {
        return array_map($callback, $array);
    }

    /**
     * Pluck values from array of arrays/objects
     *
     * Extract a single column from multi-dimensional array
     *
     * @param array|null $array Array of arrays or objects
     * @param string|int $field Field name to extract
     * @return array Array of extracted values
     */
    public static function pluck($array, $field): array
    {
        if (!is_array($array)) {
            return [];
        }

        $values = [];

        foreach ($array as $element) {
            if (is_array($element) && isset($element[$field])) {
                $values[] = $element[$field];
            } elseif (is_object($element) && isset($element->$field)) {
                $values[] = $element->$field;
            }
        }

        return $values;
    }

    /**
     * Remove duplicates from array
     *
     * @param array|null $array Array
     * @param int $flags Comparison flags (default: SORT_STRING)
     * @return array Array without duplicates
     */
    public static function unique($array, int $flags = SORT_STRING): array
    {
        if (!is_array($array)) {
            return [];
        }

        return array_unique($array, $flags);
    }

    /**
     * Reverse array
     *
     * @param array|null $array The array
     * @param bool $preserveKeys Preserve array keys
     * @return array Reversed array
     */
    public static function reverse($array, bool $preserveKeys = false): array
    {
        if (!is_array($array)) {
            return [];
        }

        return array_reverse($array, $preserveKeys);
    }

    // ============================================================================
    // ARRAY STRUCTURE MANIPULATION
    // ============================================================================

    /**
     * Flatten multi-dimensional array to single dimension
     *
     * @param array|null $array Array to flatten
     * @param int $depth Maximum depth to flatten (default: INF)
     * @return array Flattened array
     */
    public static function flatten($array, int $depth = PHP_INT_MAX): array
    {
        if (!is_array($array)) {
            return [];
        }

        $result = [];

        foreach ($array as $item) {
            if (!is_array($item)) {
                $result[] = $item;
            } else {
                $values = $depth === 1 ? array_values($item) : self::flatten($item, $depth - 1);

                foreach ($values as $value) {
                    $result[] = $value;
                }
            }
        }

        return $result;
    }

    /**
     * Chunk array into smaller arrays
     *
     * @param array|null $array Array to chunk
     * @param int $size Chunk size
     * @param bool $preserveKeys Preserve array keys (default: false)
     * @return array Array of chunks
     */
    public static function chunk($array, int $size, bool $preserveKeys = false): array
    {
        if (!is_array($array) || $size <= 0) {
            return [];
        }

        return array_chunk($array, $size, $preserveKeys);
    }

    /**
     * Safely merge multiple arrays
     *
     * @param array ...$arrays Arrays to merge
     * @return array Merged array
     */
    public static function merge(...$arrays): array
    {
        $result = [];

        foreach ($arrays as $array) {
            if (is_array($array)) {
                $result = array_merge($result, $array);
            }
        }

        return $result;
    }

    /**
     * Create array filled with value
     *
     * @param int $count Number of elements
     * @param mixed $value Value to fill with
     * @return array Filled array
     */
    public static function fill(int $count, $value): array
    {
        if ($count <= 0) {
            return [];
        }

        return array_fill(0, $count, $value);
    }

    /**
     * Check if array is associative
     *
     * @param array|null $array The array to check
     * @return bool True if associative
     */
    public static function isAssociative($array): bool
    {
        if (!self::isNotEmpty($array)) {
            return false;
        }

        return array_keys($array) !== range(0, count($array) - 1);
    }

    // ============================================================================
    // 2D ARRAY OPERATIONS
    // ============================================================================

    /**
     * Join 2D array by specific field
     *
     * @param array $array 2D array
     * @param string $field Field name to join
     * @param string $separator Separator string
     * @return string Joined string
     */
    public static function join2D(array $array, string $field, string $separator = ''): string
    {
        $values = [];

        foreach ($array as $element) {
            if (is_array($element) && isset($element[$field])) {
                $values[] = $element[$field];
            }
        }

        return implode($separator, $values);
    }

    // ============================================================================
    // DELIMITED STRING OPERATIONS (CSV-like)
    // ============================================================================

    /**
     * Add item to delimited string array (CSV-like string)
     *
     * @param string $stringArray Delimited string (e.g., "a,b,c")
     * @param string $item Item to add
     * @param string $delimiter Delimiter (default: ',')
     * @return string Updated delimited string
     */
    public static function addItem(string $stringArray, string $item, string $delimiter = ','): string
    {
        if (empty($stringArray)) {
            return $item;
        }

        return $stringArray . $delimiter . $item;
    }

    /**
     * Remove item from delimited string array
     *
     * @param string $stringArray Delimited string
     * @param string $item Item to remove
     * @param string $delimiter Delimiter (default: ',')
     * @return string Updated delimited string
     */
    public static function removeItem(string $stringArray, string $item, string $delimiter = ','): string
    {
        if (empty($stringArray)) {
            return '';
        }

        $items = explode($delimiter, $stringArray);
        $items = array_filter($items, function($value) use ($item) {
            return $value !== $item;
        });

        return implode($delimiter, $items);
    }

    /**
     * Find item in delimited string array
     *
     * @param string $stringArray Delimited string
     * @param string $item Item to find
     * @param string $delimiter Delimiter (default: ',')
     * @return bool True if found
     */
    public static function hasItem(string $stringArray, string $item, string $delimiter = ','): bool
    {
        if (empty($stringArray)) {
            return false;
        }

        $items = explode($delimiter, $stringArray);
        return in_array($item, $items, false);
    }

    // ============================================================================
    // UTILITY METHODS
    // ============================================================================

    /**
     * Wrap value in array if not already an array
     *
     * @param mixed $value Value to wrap
     * @return array
     */
    public static function wrap($value): array
    {
        if (is_null($value)) {
            return [];
        }

        return is_array($value) ? $value : [$value];
    }
}
