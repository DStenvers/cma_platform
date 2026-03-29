<?php

namespace App\Library;

/**
 * Server Helper Class
 *
 * Provides safe server-related utilities with security protections.
 * Maps VBScript Server object methods to modern PHP equivalents.
 *
 * Key Features:
 * - Path traversal protection for mapPath()
 * - Safe URL encoding/decoding
 * - Server variable access
 * - Execution timeout management
 *
 * Usage:
 *   $path = Server::mapPath('/images/photo.jpg');
 *   $encoded = Server::urlEncode($text);
 *   $docRoot = Server::getVar('DOCUMENT_ROOT');
 */
class Server
{
    /**
     * @var string|null Document root path
     */
    private static $documentRoot = null;

    /**
     * @var array Allowed path prefixes for security
     */
    private static $allowedPaths = [];

    /**
     * Initialize server helper
     */
    private static function init(): void
    {
        if (self::$documentRoot !== null) {
            return; // Already initialized
        }

        // Get document root from Application config or $_SERVER
        self::$documentRoot = Application::get('document_root', $_SERVER['DOCUMENT_ROOT'] ?? getcwd());

        // Ensure document root ends without trailing slash
        self::$documentRoot = rtrim(self::$documentRoot, '/\\');

        // Normalize to forward slashes
        self::$documentRoot = str_replace('\\', '/', self::$documentRoot);

        // Set allowed paths (default: document root and temp directory)
        self::$allowedPaths = [
            self::$documentRoot,
            str_replace('\\', '/', sys_get_temp_dir())
        ];

        // Add script directory to allowed paths (for relative path resolution)
        if (isset($_SERVER['SCRIPT_FILENAME'])) {
            $scriptDir = dirname($_SERVER['SCRIPT_FILENAME']);
            $scriptDir = str_replace('\\', '/', $scriptDir);
            if (!in_array($scriptDir, self::$allowedPaths)) {
                self::$allowedPaths[] = $scriptDir;
            }
        }

        // Add custom allowed paths from Application config
        $customPaths = Application::get('server_allowed_paths', []);
        if (is_array($customPaths)) {
            foreach ($customPaths as $path) {
                self::$allowedPaths[] = str_replace('\\', '/', rtrim($path, '/\\'));
            }
        }
    }

    /**
     * Map virtual path to physical path (VBScript Server.MapPath equivalent)
     *
     * Security features:
     * - Prevents path traversal attacks (../)
     * - Validates against allowed path prefixes
     * - Resolves symbolic links
     * - Normalizes path separators
     *
     * Behavior:
     * - Absolute paths (starting with /) are resolved from document root
     * - Relative paths (not starting with /) are resolved from current script directory
     *
     * @param string $virtualPath Virtual path (e.g., "/images/photo.jpg" or "file.php")
     * @return string Physical path
     * @throws \RuntimeException If path is invalid or outside allowed directories
     */
    public static function mapPath(string $virtualPath): string
    {
        self::init();

        // Replace backslashes with forward slashes for consistency
        $virtualPath = str_replace('\\', '/', $virtualPath);

        // Determine base path based on whether virtual path is absolute or relative
        if (strpos($virtualPath, '/') === 0) {
            // Absolute path - resolve from document root
            $virtualPath = ltrim($virtualPath, '/');
            $fullPath = self::$documentRoot . '/' . $virtualPath;
        } else {
            // Relative path - resolve from current script directory (like ASP Server.MapPath)
            $scriptDir = dirname($_SERVER['SCRIPT_FILENAME'] ?? getcwd());
            $scriptDir = str_replace('\\', '/', $scriptDir);
            $fullPath = $scriptDir . '/' . $virtualPath;
        }

        // Resolve the real path (resolves .., symbolic links, etc.)
        $realPath = realpath($fullPath);

        // If realpath returns false, the path doesn't exist yet
        // In this case, manually resolve the path for validation
        if ($realPath === false) {
            // Normalize the path without requiring it to exist
            $realPath = self::normalizePath($fullPath);
        } else {
            // Normalize backslashes to forward slashes (Windows)
            $realPath = str_replace('\\', '/', $realPath);
        }

        // Security check: Ensure the resolved path is within allowed directories
        // On Windows, paths are case-insensitive
        $isWindows = DIRECTORY_SEPARATOR === '\\' || (PHP_OS_FAMILY ?? '') === 'Windows';
        $realPathCheck = $isWindows ? strtolower($realPath) : $realPath;

        $isAllowed = false;
        foreach (self::$allowedPaths as $allowedPath) {
            $allowedCheck = $isWindows ? strtolower($allowedPath) : $allowedPath;
            if (strpos($realPathCheck, $allowedCheck) === 0) {
                $isAllowed = true;
                break;
            }
        }

        if (!$isAllowed) {
            // If path contains '..' and resolves outside allowed directories, this is a path traversal attack
            if (strpos($virtualPath, '..') !== false) {
                throw new \RuntimeException(
                    "Path traversal detected: '$virtualPath' resolves to '$realPath' which is outside allowed directories"
                );
            }
            throw new \RuntimeException(
                "Access denied: Path '$virtualPath' resolves to '$realPath' which is outside allowed directories: " . implode(', ', self::$allowedPaths)
            );
        }

        // Note: Paths with '..' are allowed if they resolve to an allowed directory
        // The security check above ensures the final resolved path is within allowed directories

        return $realPath;
    }

    /**
     * Normalize path without requiring it to exist
     *
     * @param string $path Path to normalize
     * @return string Normalized path
     */
    private static function normalizePath(string $path): string
    {
        // Replace backslashes with forward slashes
        $path = str_replace('\\', '/', $path);

        // Remove duplicate slashes (but preserve drive letter colon on Windows)
        // First, check for Windows drive letter (e.g., C:/)
        $drivePrefix = '';
        if (preg_match('#^([A-Za-z]:)/#', $path, $matches)) {
            $drivePrefix = $matches[1];
            $path = substr($path, strlen($drivePrefix));
        }

        // Remove duplicate slashes
        $path = preg_replace('#/+#', '/', $path);

        // Split path into parts
        $parts = explode('/', $path);
        $normalized = [];

        foreach ($parts as $part) {
            if ($part === '' || $part === '.') {
                continue; // Skip empty and current directory
            }

            if ($part === '..') {
                // Go up one level if possible
                if (count($normalized) > 0) {
                    array_pop($normalized);
                }
            } else {
                $normalized[] = $part;
            }
        }

        // Rebuild path
        $result = implode('/', $normalized);

        // Add drive prefix back for Windows paths
        if ($drivePrefix !== '') {
            $result = $drivePrefix . '/' . $result;
        } elseif (strpos($path, '/') === 0) {
            // Add leading slash for Unix absolute paths
            $result = '/' . $result;
        }

        return $result;
    }

    /**
     * URL encode string (VBScript Server.URLEncode equivalent)
     *
     * @param string $text Text to encode
     * @return string URL-encoded text
     */
    public static function urlEncode(string $text): string
    {
        return urlencode($text);
    }

    /**
     * URL decode string
     *
     * @param string $text Text to decode
     * @return string URL-decoded text
     */
    public static function urlDecode(string $text): string
    {
        return urldecode($text);
    }

    /**
     * HTML encode string (VBScript Server.HTMLEncode equivalent)
     *
     * @param string $text Text to encode
     * @return string HTML-encoded text
     */
    public static function htmlEncode(?string $text): string
    {
        if ($text === null) {
            return '';
        }
        return htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * HTML decode string
     *
     * @param string $text Text to decode
     * @return string HTML-decoded text
     */
    public static function htmlDecode(string $text): string
    {
        return htmlspecialchars_decode($text, ENT_QUOTES | ENT_HTML5);
    }

    /**
     * Get server variable (equivalent to $_SERVER)
     *
     * @param string $name Variable name
     * @param mixed $default Default value if not found
     * @return mixed
     */
    public static function getVar(string $name, $default = '')
    {
        return $_SERVER[$name] ?? $default;
    }

    /**
     * Get all server variables
     *
     * @return array
     */
    public static function getAllVars(): array
    {
        return $_SERVER;
    }

    /**
     * Set script execution timeout (VBScript Server.ScriptTimeout equivalent)
     *
     * @param int $seconds Timeout in seconds (0 = unlimited)
     * @return bool True on success
     */
    public static function setScriptTimeout(int $seconds): bool
    {
        // Disable time limit if 0
        if ($seconds === 0) {
            set_time_limit(0);
            return true;
        }

        // Set timeout
        set_time_limit($seconds);
        return true;
    }

    /**
     * Get current script timeout
     *
     * @return int Timeout in seconds (0 = unlimited)
     */
    public static function getScriptTimeout(): int
    {
        return (int)ini_get('max_execution_time');
    }

    /**
     * Create object (VBScript Server.CreateObject equivalent)
     *
     * Note: PHP doesn't have COM objects like VBScript.
     * This method is provided for compatibility but will throw an exception.
     *
     * @param string $progId COM ProgID
     * @throws \RuntimeException Always throws - COM not supported in PHP
     */
    public static function createObject(string $progId): void
    {
        throw new \RuntimeException(
            "Server.CreateObject('$progId') is not supported in PHP. " .
            "COM objects must be replaced with native PHP alternatives. " .
            "See helper classes (Database, Email, Image, etc.) for replacements."
        );
    }

    /**
     * Execute server-side include (similar to SSI)
     *
     * @param string $path Path to include (relative or absolute)
     * @return bool True on success
     */
    public static function execute(string $path): bool
    {
        try {
            $realPath = self::mapPath($path);

            if (!file_exists($realPath)) {
                throw new \RuntimeException("File not found: $realPath");
            }

            require $realPath;
            return true;
        } catch (\Exception $e) {
            error_log('Server::execute error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Transfer execution to another script (similar to Server.Transfer)
     *
     * @param string $path Path to transfer to
     * @return void
     */
    public static function transfer(string $path): void
    {
        $realPath = self::mapPath($path);

        if (!file_exists($realPath)) {
            throw new \RuntimeException("File not found: $realPath");
        }

        require $realPath;
        exit; // Stop execution after transfer
    }

    /**
     * Get document root path
     *
     * @return string
     */
    public static function getDocumentRoot(): string
    {
        self::init();
        return self::$documentRoot;
    }

    /**
     * Add allowed path for mapPath security
     *
     * @param string $path Path to allow
     * @return void
     */
    public static function addAllowedPath(string $path): void
    {
        self::init();
        $path = rtrim($path, '/\\');

        if (!in_array($path, self::$allowedPaths)) {
            self::$allowedPaths[] = $path;
        }
    }

    /**
     * Get server name
     *
     * @return string
     */
    public static function getServerName(): string
    {
        return self::getVar('SERVER_NAME', 'localhost');
    }

    /**
     * Get server software
     *
     * @return string
     */
    public static function getServerSoftware(): string
    {
        return self::getVar('SERVER_SOFTWARE', 'PHP/' . PHP_VERSION);
    }

    /**
     * Get protocol (HTTP/HTTPS)
     *
     * @return string
     */
    public static function getProtocol(): string
    {
        $isHttps = self::getVar('HTTPS', '') === 'on' ||
                   self::getVar('SERVER_PORT', 80) == 443;

        return $isHttps ? 'https' : 'http';
    }

    /**
     * Get full server URL (protocol + host)
     *
     * @return string
     */
    public static function getServerUrl(): string
    {
        return self::getProtocol() . '://' . self::getServerName();
    }
}
