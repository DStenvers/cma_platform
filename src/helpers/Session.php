<?php

namespace App\Library;

/**
 * Session Helper Class
 *
 * Provides safe session management with automatic initialization
 * and type-safe access methods. Maps VBScript Session object.
 *
 * Features:
 * - Auto-start sessions when needed
 * - Safe access with default values (no "Undefined index" warnings)
 * - Type-safe getters (int, bool, string, float)
 * - Session lifecycle management
 *
 * Usage:
 *   Session::set('user_id', 123);
 *   $userId = Session::int('user_id', 0);
 *   Session::remove('user_id');
 *   Session::destroy();
 */
class Session
{
    /**
     * @var bool Whether session has been started
     */
    private static $started = false;

    /**
     * Initialize/start session if not already started
     *
     * @return bool True on success
     */
    private static function init(): bool
    {
        if (self::$started) {
            return true;
        }

        // Check if session already started by other code
        if (session_status() === PHP_SESSION_ACTIVE) {
            self::$started = true;
            return true;
        }

        // Configure session settings from Application config
        $sessionName = Application::get('session_name', 'PHPSESSID');
        $sessionLifetime = Application::get('session_lifetime', 3600);
        $sessionPath = Application::get('session_path', '/');
        $sessionDomain = Application::get('session_domain', '');
        $sessionSecure = Application::get('session_secure', false);
        $sessionHttpOnly = Application::get('session_httponly', true);

        // Set session cookie parameters
        session_name($sessionName);
        session_set_cookie_params([
            'lifetime' => $sessionLifetime,
            'path' => $sessionPath,
            'domain' => $sessionDomain,
            'secure' => $sessionSecure,
            'httponly' => $sessionHttpOnly,
            'samesite' => 'Lax'
        ]);

        // Start session
        if (@session_start()) {
            self::$started = true;
            return true;
        }

        return false;
    }

    /**
     * Get session value
     *
     * @param string $key Session key
     * @param mixed $default Default value if not found
     * @return mixed
     */
    public static function get(string $key, $default = null)
    {
        self::init();

        return $_SESSION[$key] ?? $default;
    }

    /**
     * Set session value
     *
     * @param string $key Session key
     * @param mixed $value Value to store
     * @return bool True on success
     */
    public static function set(string $key, $value): bool
    {
        self::init();

        $_SESSION[$key] = $value;
        return true;
    }

    /**
     * Check if session key exists
     *
     * @param string $key Session key
     * @return bool
     */
    public static function has(string $key): bool
    {
        self::init();

        return isset($_SESSION[$key]);
    }

    /**
     * Remove session value
     *
     * @param string $key Session key
     * @return bool True if key existed
     */
    public static function remove(string $key): bool
    {
        self::init();

        if (isset($_SESSION[$key])) {
            unset($_SESSION[$key]);
            return true;
        }

        return false;
    }

    /**
     * Get all session data
     *
     * @return array
     */
    public static function all(): array
    {
        self::init();

        return $_SESSION ?? [];
    }

    /**
     * Clear all session data (but keep session alive)
     *
     * @return bool
     */
    public static function clear(): bool
    {
        self::init();

        $_SESSION = [];
        return true;
    }

    /**
     * Destroy session completely
     *
     * @return bool
     */
    public static function destroy(): bool
    {
        self::init();

        $_SESSION = [];

        // Delete session cookie
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        // Destroy session
        return session_destroy();
    }

    /**
     * Regenerate session ID (security best practice)
     *
     * @param bool $deleteOldSession Delete old session data
     * @return bool
     */
    public static function regenerate(bool $deleteOldSession = true): bool
    {
        self::init();

        return session_regenerate_id($deleteOldSession);
    }

    /**
     * Get session ID
     *
     * @return string
     */
    public static function id(): string
    {
        self::init();

        return session_id();
    }

    /**
     * Get integer value from session
     *
     * @param string $key Session key
     * @param int $default Default value
     * @return int
     */
    public static function int(string $key, int $default = 0): int
    {
        $value = self::get($key, $default);

        return (int)$value;
    }

    /**
     * Get boolean value from session
     *
     * @param string $key Session key
     * @param bool $default Default value
     * @return bool
     */
    public static function bool(string $key, bool $default = false): bool
    {
        $value = self::get($key, $default);

        if (is_bool($value)) {
            return $value;
        }

        // Handle string boolean representations
        if (is_string($value)) {
            $value = strtolower($value);
            return in_array($value, ['1', 'true', 'yes', 'on'], true);
        }

        return (bool)$value;
    }

    /**
     * Get string value from session
     *
     * @param string $key Session key
     * @param string $default Default value
     * @return string
     */
    public static function string(string $key, string $default = ''): string
    {
        $value = self::get($key, $default);

        return (string)$value;
    }

    /**
     * Get float value from session
     *
     * @param string $key Session key
     * @param float $default Default value
     * @return float
     */
    public static function float(string $key, float $default = 0.0): float
    {
        $value = self::get($key, $default);

        return (float)$value;
    }

    /**
     * Flash message - set value for next request only
     *
     * @param string $key Flash key
     * @param mixed $value Value to flash
     * @return bool
     */
    public static function flash(string $key, $value): bool
    {
        self::init();

        if (!isset($_SESSION['__flash'])) {
            $_SESSION['__flash'] = [];
        }

        $_SESSION['__flash'][$key] = $value;
        return true;
    }

    /**
     * Get flash message (available only once)
     *
     * @param string $key Flash key
     * @param mixed $default Default value
     * @return mixed
     */
    public static function getFlash(string $key, $default = null)
    {
        self::init();

        if (isset($_SESSION['__flash'][$key])) {
            $value = $_SESSION['__flash'][$key];
            unset($_SESSION['__flash'][$key]);
            return $value;
        }

        return $default;
    }

    /**
     * Check if session is started
     *
     * @return bool
     */
    public static function isStarted(): bool
    {
        return self::$started || session_status() === PHP_SESSION_ACTIVE;
    }
}
