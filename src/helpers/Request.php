<?php

namespace App\Library;

/**
 * Request Helper Class
 *
 * Provides safe access to HTTP request data ($_GET, $_POST, $_REQUEST, $_SERVER)
 * with default values and type coercion.
 *
 * Prevents "Undefined array key" warnings and provides consistent API
 * for accessing request parameters.
 *
 * Usage:
 *   $id = Request::query('id', 0);              // Get from $_GET with default 0
 *   $name = Request::post('username', '');      // Get from $_POST with default ''
 *   $value = Request::get('key', 'default');    // Get from any source
 *   $count = Request::int('count', 10);         // Type-safe integer
 *   $enabled = Request::bool('enabled', false); // Type-safe boolean
 */
class Request
{
    /**
     * Get value from any source ($_GET, $_POST, $_COOKIE)
     *
     * @param string $key The parameter name
     * @param mixed $default Default value if key doesn't exist
     * @return mixed The parameter value or default
     */
    public static function get(string $key, $default = '')
    {
        return $_REQUEST[$key] ?? $default;
    }

    /**
     * Get value from query string ($_GET)
     *
     * Case-insensitive to match ASP Request.QueryString behavior
     *
     * @param string $key The parameter name
     * @param mixed $default Default value if key doesn't exist
     * @return mixed The parameter value or default
     */
    public static function query(string $key, $default = '')
    {
        // Case-insensitive lookup (ASP compatibility)
        if (isset($_GET[$key])) {
            return $_GET[$key];
        }

        // Try case-insensitive match
        foreach ($_GET as $getKey => $value) {
            if (strcasecmp($getKey, $key) === 0) {
                return $value;
            }
        }

        return $default;
    }

    /**
     * Get value from POST data ($_POST)
     *
     * Case-insensitive to match ASP Request.Form behavior
     *
     * @param string $key The parameter name
     * @param mixed $default Default value if key doesn't exist
     * @return mixed The parameter value or default
     */
    public static function post(string $key, $default = '')
    {
        // Case-insensitive lookup (ASP compatibility)
        if (isset($_POST[$key])) {
            return $_POST[$key];
        }

        // Try case-insensitive match
        foreach ($_POST as $postKey => $value) {
            if (strcasecmp($postKey, $key) === 0) {
                return $value;
            }
        }

        return $default;
    }

    /**
     * Get value from server variables ($_SERVER)
     *
     * @param string $key The server variable name
     * @param mixed $default Default value if key doesn't exist
     * @return mixed The server variable value or default
     */
    public static function server(string $key, $default = '')
    {
        return $_SERVER[$key] ?? $default;
    }

    /**
     * Get the original script name (before URL rewrite)
     *
     * When using URL rewriting (e.g., via _bootstrap_wrapper.php),
     * PHP_SELF returns the wrapper script. This method returns the
     * original requested script from HTTP_X_ORIGINAL_FILE header
     * set by IIS URL Rewrite rules.
     *
     * @return string The original script path (e.g., /cma/details.php)
     */
    public static function scriptName(): string
    {
        // First check for X-Original-File header set by URL rewrite
        $originalFile = $_SERVER['HTTP_X_ORIGINAL_FILE'] ?? '';
        if (!empty($originalFile)) {
            return $originalFile;
        }

        // Fall back to PHP_SELF
        return $_SERVER['PHP_SELF'] ?? '';
    }

    /**
     * Get the original script name (alias for scriptName)
     *
     * @return string The original script path
     */
    public static function getOriginalScript(): string
    {
        return self::scriptName();
    }

    /**
     * Get all query string parameters
     *
     * @return array All $_GET parameters
     */
    public static function queryAll(): array
    {
        return $_GET;
    }

    /**
     * Get all POST parameters
     *
     * @return array All $_POST parameters
     */
    public static function postAll(): array
    {
        return $_POST;
    }

    /**
     * Get all request parameters
     *
     * @return array All $_REQUEST parameters
     */
    public static function all(): array
    {
        return $_REQUEST;
    }

    /**
     * Check if query parameter exists
     *
     * @param string $key The parameter name
     * @return bool True if parameter exists
     */
    public static function hasQuery(string $key): bool
    {
        return isset($_GET[$key]);
    }

    /**
     * Check if POST parameter exists
     *
     * @param string $key The parameter name
     * @return bool True if parameter exists
     */
    public static function hasPost(?string $key = null): bool
    {
        if ($key === null) {
            return !empty($_POST);
        }
        return isset($_POST[$key]);
    }

    /**
     * Check if request parameter exists
     *
     * @param string $key The parameter name
     * @return bool True if parameter exists
     */
    public static function has(string $key): bool
    {
        return isset($_REQUEST[$key]);
    }

    /**
     * Get integer value from request
     *
     * @param string $key The parameter name
     * @param int $default Default value if key doesn't exist
     * @return int The integer value
     */
    public static function int(string $key, int $default = 0): int
    {
        $value = self::get($key, $default);
        return (int)$value;
    }

    /**
     * Get integer value from query string ($_GET)
     *
     * Strips all non-numeric characters before returning.
     * Safe for untrusted input.
     *
     * @param string $key The parameter name
     * @param string $default Default value if key doesn't exist
     * @return string Only numeric characters from the parameter
     */
    public static function queryInt(string $key, string $default = ''): string
    {
        return Str::numbersOnly(self::query($key, $default));
    }

    /**
     * Get integer and comma value from query string ($_GET)
     *
     * Strips all characters except numbers and commas.
     * Useful for comma-separated ID lists like "1,2,3,4"
     *
     * @param string $key The parameter name
     * @return string Only numeric characters and commas
     */
    public static function queryIntAndComma(string $key): string
    {
        return Str::numbersOnlyAndComma(self::query($key, ''));
    }

    /**
     * Get integer or GUID value from query string ($_GET)
     *
     * If value starts with '{', returns as-is (GUID format).
     * Otherwise strips to numbers only.
     *
     * @param string $key The parameter name
     * @return string Numbers only, or GUID if starts with '{'
     */
    public static function queryIntAndGuid(string $key): string
    {
        $value = self::query($key, '');
        // If it's a GUID (with or without braces) - return as-is
        if (preg_match('/^\{?[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\}?$/i', $value)) {
            return $value;
        }
        return Str::numbersOnly($value);
    }

    /**
     * Get ID parameter from query string - handles numeric, GUID, and alphanumeric IDs
     *
     * Allows: digits, letters, hyphens, underscores, and GUIDs with braces
     * This is safe for use in database queries when properly escaped.
     *
     * @param string $key The parameter name
     * @param string $default Default value if key doesn't exist
     * @return string Sanitized ID value
     */
    public static function queryId(string $key, string $default = ''): string
    {
        $value = self::query($key, $default);
        // Allow alphanumeric, hyphens, underscores, and GUID braces
        return preg_replace('/[^a-zA-Z0-9\-_{}]/', '', $value);
    }

    /**
     * Get integer value from POST data ($_POST)
     *
     * @param string $key The parameter name
     * @param int $default Default value if key doesn't exist
     * @return int The integer value
     */
    public static function postInt(string $key, int $default = 0): int
    {
        $value = self::post($key, $default);
        return (int)$value;
    }

    /**
     * Get boolean value from request
     *
     * Converts various truthy values to boolean:
     * - "1", "true", "yes", "on" -> true
     * - "0", "false", "no", "off", "" -> false
     *
     * @param string $key The parameter name
     * @param bool $default Default value if key doesn't exist
     * @return bool The boolean value
     */
    public static function bool(string $key, bool $default = false): bool
    {
        if (!self::has($key)) {
            return $default;
        }

        $value = self::get($key, '');

        // Handle common boolean representations
        if (is_bool($value)) {
            return $value;
        }

        $normalized = strtolower(trim((string)$value));

        if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
            return true;
        }

        if (in_array($normalized, ['0', 'false', 'no', 'off', ''], true)) {
            return false;
        }

        // Use PHP's filter_var as fallback
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Get string value from request
     *
     * @param string $key The parameter name
     * @param string $default Default value if key doesn't exist
     * @return string The string value
     */
    public static function string(string $key, string $default = ''): string
    {
        return (string)self::get($key, $default);
    }

    /**
     * Get float value from request
     *
     * @param string $key The parameter name
     * @param float $default Default value if key doesn't exist
     * @return float The float value
     */
    public static function float(string $key, float $default = 0.0): float
    {
        $value = self::get($key, $default);
        return (float)$value;
    }

    /**
     * Get array value from request
     *
     * Useful for multi-select inputs and checkboxes
     *
     * @param string $key The parameter name
     * @param array $default Default value if key doesn't exist
     * @return array The array value
     */
    public static function array(string $key, array $default = []): array
    {
        $value = self::get($key, $default);
        return is_array($value) ? $value : $default;
    }

    /**
     * Get HTTP request method (GET, POST, PUT, DELETE, etc.)
     *
     * @return string The request method in uppercase
     */
    public static function method(): string
    {
        return strtoupper(self::server('REQUEST_METHOD', 'GET'));
    }

    /**
     * Check if request is GET
     *
     * @return bool True if request method is GET
     */
    public static function isGet(): bool
    {
        return self::method() === 'GET';
    }

    /**
     * Check if request is POST
     *
     * @return bool True if request method is POST
     */
    public static function isPost(): bool
    {
        return self::method() === 'POST';
    }

    /**
     * Check if request is AJAX
     *
     * @return bool True if request is AJAX
     */
    public static function isAjax(): bool
    {
        return strtolower(self::server('HTTP_X_REQUESTED_WITH', '')) === 'xmlhttprequest';
    }

    /**
     * Get client IP address
     *
     * @return string The client IP address
     */
    public static function ip(): string
    {
        // Check for forwarded IP (behind proxy/load balancer)
        $ip = self::server('HTTP_X_FORWARDED_FOR', '');
        if (!empty($ip)) {
            $ips = explode(',', $ip);
            return trim($ips[0]);
        }

        return self::server('REMOTE_ADDR', '');
    }

    /**
     * Get user agent string
     *
     * @return string The user agent
     */
    public static function userAgent(): string
    {
        return self::server('HTTP_USER_AGENT', '');
    }

    /**
     * Get request URI
     *
     * @return string The request URI
     */
    public static function uri(): string
    {
        return self::server('REQUEST_URI', '');
    }

    /**
     * Get full URL of current request
     *
     * @return string The full URL
     */
    public static function url(): string
    {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = self::server('HTTP_HOST', 'localhost');
        $uri = self::uri();

        return "$protocol://$host$uri";
    }

    /**
     * Add or update a parameter in a URL
     *
     * Equivalent to ASP's AddToURL function from lib_urlparam.inc
     *
     * @param string $url URL to modify (empty uses current URL)
     * @param string $name Parameter name
     * @param string $value Parameter value (empty to remove parameter)
     * @return string Modified URL
     */
    public static function addToURL(string $url, string $name, string $value): string
    {
        // If URL is empty, use current URL
        if (empty($url)) {
            $url = self::url();
        }

        // Split URL into base and query string
        $parts = explode('?', $url, 2);
        $base = $parts[0];
        $queryString = $parts[1] ?? '';

        // No parameters yet
        if (empty($queryString)) {
            if (!empty($value)) {
                return $base . '?' . $name . '=' . $value;
            }
            return $base;
        }

        // Parse existing parameters
        $params = [];
        parse_str($queryString, $params);

        // Update or remove the parameter
        if (!empty($value)) {
            $params[$name] = $value;
        } else {
            // Also check case-insensitive
            unset($params[$name]);
            foreach (array_keys($params) as $key) {
                if (strcasecmp($key, $name) === 0) {
                    unset($params[$key]);
                }
            }
        }

        // Rebuild query string
        if (empty($params)) {
            return $base;
        }

        return $base . '?' . http_build_query($params);
    }

    /**
     * Delete a parameter from a URL
     *
     * Equivalent to ASP's lib_urlDeleteParam function
     *
     * @param string $url URL to modify
     * @param string $name Parameter name to remove
     * @return string Modified URL
     */
    public static function deleteParam(string $url, string $name): string
    {
        return self::addToURL($url, $name, '');
    }

    /**
     * Add all current query parameters to a URL
     *
     * Equivalent to ASP's AddAllToURL function
     *
     * @param string $url URL to modify
     * @return string Modified URL with all current query parameters
     */
    public static function addAllToURL(string $url): string
    {
        foreach ($_GET as $key => $value) {
            $url = self::addToURL($url, $key, $value);
        }
        return $url;
    }

    /**
     * Get current domain with protocol
     *
     * Equivalent to ASP's lib_CurrentDomain function
     *
     * @return string Current domain (e.g., "https://example.com")
     */
    public static function currentDomain(): string
    {
        $protocol = (!empty($_SERVER['HTTPS']) && strtoupper($_SERVER['HTTPS']) === 'ON') ? 'https://' : 'http://';
        return $protocol . self::server('SERVER_NAME', 'localhost');
    }
}
