<?php

namespace App\Library;

use Redis;
use RedisException;

/**
 * Cache Helper Class
 *
 * Provides unified caching interface with support for multiple backends:
 * - Redis (recommended for production)
 * - File-based (fallback)
 * - APCu (in-memory, process-level)
 *
 * Maps VBScript Cache_Get/Cache_Save/Cache_Clear functions
 *
 * Basic Usage:
 *   Cache::set('key', $data, 3600);
 *   $data = Cache::get('key');
 *   Cache::delete('key');
 *   Cache::clear();
 *
 * =============================================================================
 * CROSS-INSTANCE CACHE INVALIDATION
 * =============================================================================
 *
 * Problem: Multiple PHP instances (FastCGI workers) maintain separate APCu
 * caches. When one instance invalidates cache, others don't know.
 *
 * Solution: File-based invalidation signal with optional Redis pub/sub.
 *
 * Architecture:
 * ```
 * ┌─────────────────┐     ┌─────────────────┐     ┌─────────────────┐
 * │   PHP Worker 1  │     │   PHP Worker 2  │     │   PHP Worker N  │
 * │  (APCu/memory)  │     │  (APCu/memory)  │     │  (APCu/memory)  │
 * └────────┬────────┘     └────────┬────────┘     └────────┬────────┘
 *          │                       │                       │
 *          └───────────┬───────────┴───────────┬───────────┘
 *                      │                       │
 *          ┌───────────▼───────────────────────▼───────────┐
 *          │     Shared Invalidation Signal                │
 *          │  (File: cache_invalidation.json OR Redis)     │
 *          └───────────────────────────────────────────────┘
 * ```
 *
 * Cross-Instance API:
 *
 *   // Invalidate a cache group across ALL PHP instances
 *   Cache::invalidateGroup('forms');      // Invalidate form definitions
 *   Cache::invalidateGroup('security');   // Invalidate security/permissions
 *   Cache::invalidateGroup('menu');       // Invalidate menu items
 *   Cache::invalidateAll();               // Invalidate everything
 *
 *   // Get/Set with cross-instance invalidation support
 *   $data = Cache::getWithInvalidation('key', 'forms');
 *   Cache::setWithInvalidation('key', $value, 'forms', 3600);
 *
 *   // Check invalidation status
 *   $status = Cache::getInvalidationStatus();
 *
 * How It Works:
 *   1. When invalidateGroup() is called, it writes a timestamp to a shared
 *      file (cache_invalidation.json)
 *   2. All PHP instances check this file periodically (configurable, default 1s)
 *   3. If an entry's creation time is older than the group's invalidation
 *      time, it's considered stale
 *   4. With Redis, it also publishes to a pub/sub channel for real-time
 *      notification
 *
 * Configuration (via Application):
 *   'cache_invalidation_interval' => 1,      // How often to check (seconds)
 *   'cache_invalidation_ttl' => 86400,       // How long to keep signals (24h default)
 *   'cache_directory' => '/path/to/cache',   // Where to store signal file
 *
 * Cleanup (prevents unbounded growth of cache_invalidation.json):
 *
 *   | Method      | When                              | How                                    |
 *   |-------------|-----------------------------------|----------------------------------------|
 *   | Automatic   | On every invalidateGroup() call   | Prunes entries older than TTL          |
 *   | Manual      | Cache::clearInvalidationSignals() | Deletes the signal file entirely       |
 *   | Cron        | Cache::pruneInvalidationSignals() | Removes expired entries only           |
 *
 *   Example maintenance script:
 *     // Reset all invalidation state
 *     Cache::clearInvalidationSignals();
 *
 *     // Or just prune old entries (e.g., from cron)
 *     $pruned = Cache::pruneInvalidationSignals();
 *     echo "Pruned $pruned expired entries";
 *
 * Benefits:
 *   - Works with file, APCu, or Redis backends
 *   - Minimal overhead (single file read per check interval)
 *   - Supports cache groups for selective invalidation
 *   - Backwards compatible - existing get()/set() still work
 */
class Cache
{
    /**
     * @var string Cache backend type (redis, file, apcu)
     */
    private static $backend = null;

    /**
     * @var Redis|null Redis connection
     */
    private static $redis = null;

    /**
     * @var string File cache directory
     */
    private static $cacheDir = null;

    /**
     * @var bool Whether caching is enabled
     */
    private static $enabled = true;

    /**
     * @var int Default TTL in seconds (24 hours)
     */
    private static $defaultTTL = 86400;

    /**
     * @var int Cache hit counter
     */
    private static $hits = 0;

    /**
     * @var int Cache miss counter
     */
    private static $misses = 0;

    /**
     * @var bool Whether to log cache hits/misses
     */
    private static $logEnabled = null;

    /**
     * @var string Path to invalidation signal file
     */
    private static $invalidationFile = null;

    /**
     * @var array In-memory cache of invalidation timestamps (per-request)
     */
    private static $invalidationCache = [];

    /**
     * @var int Last time we checked the invalidation file (unix timestamp)
     */
    private static $lastInvalidationCheck = 0;

    /**
     * @var int How often to check invalidation file (seconds)
     */
    private static $invalidationCheckInterval = 1;

    /**
     * @var int How long to keep invalidation signals (seconds, default 24 hours)
     */
    private static $invalidationTTL = 86400;

    /**
     * Initialize cache backend
     */
    private static function init(): void
    {
        if (self::$backend !== null) {
            return; // Already initialized
        }

        // Check if caching is enabled via Application config
        self::$enabled = Application::get('cma_caching', true);

        if (!self::$enabled) {
            self::$backend = 'none';
            return;
        }

        // Determine backend preference
        $preferredBackend = Application::get('cache_backend', 'auto');

        if ($preferredBackend === 'auto') {
            // Auto-detect best available backend
            if (self::initRedis()) {
                self::$backend = 'redis';
            } elseif (function_exists('apcu_fetch')) {
                self::$backend = 'apcu';
            } else {
                self::$backend = 'file';
            }
        } elseif ($preferredBackend === 'redis') {
            if (!self::initRedis()) {
                // Fallback to file if Redis fails
                self::$backend = 'file';
            } else {
                self::$backend = 'redis';
            }
        } elseif ($preferredBackend === 'apcu') {
            self::$backend = 'apcu';
        } else {
            self::$backend = 'file';
        }

        // Initialize file cache directory if using file backend
        if (self::$backend === 'file') {
            self::initFileCache();
        }

        // Initialize cross-instance invalidation
        self::initInvalidation();
    }

    /**
     * Initialize cross-instance invalidation system
     */
    private static function initInvalidation(): void
    {
        // Use same directory as file cache for invalidation signal
        $cacheDir = Application::get('cache_directory', sys_get_temp_dir() . '/cma_cache');

        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0755, true);
        }

        self::$invalidationFile = $cacheDir . '/cache_invalidation.json';
        self::$invalidationCheckInterval = (int)Application::get('cache_invalidation_interval', 1);
        self::$invalidationTTL = (int)Application::get('cache_invalidation_ttl', 86400); // 24 hours default
    }

    /**
     * Initialize Redis connection
     *
     * @return bool True if successful
     */
    private static function initRedis(): bool
    {
        if (self::$redis !== null) {
            return true;
        }

        if (!class_exists('Redis')) {
            return false;
        }

        try {
            $redis = new Redis();
            $host = Application::get('redis_host', '127.0.0.1');
            $port = Application::get('redis_port', 6379);
            $timeout = Application::get('redis_timeout', 2.5);

            $connected = $redis->connect($host, $port, $timeout);

            if (!$connected) {
                return false;
            }

            // Optional authentication
            $password = Application::get('redis_password', '');
            if (!empty($password)) {
                $redis->auth($password);
            }

            // Optional database selection
            $database = Application::get('redis_database', 0);
            if ($database > 0) {
                $redis->select($database);
            }

            // Set key prefix
            $prefix = Application::get('redis_prefix', 'cma_');
            $redis->setOption(Redis::OPT_PREFIX, $prefix);

            self::$redis = $redis;
            return true;
        } catch (RedisException $e) {
            error_log('Redis connection failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Initialize file-based cache directory
     */
    private static function initFileCache(): void
    {
        // Use system temp directory or custom cache directory
        $cacheDir = Application::get('cache_directory', sys_get_temp_dir() . '/cma_cache');

        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0755, true);
        }

        self::$cacheDir = $cacheDir;
    }

    /**
     * Get cached value
     *
     * @param string $key Cache key
     * @param mixed $default Default value if not found
     * @return mixed
     */
    public static function get(string $key, $default = null)
    {
        self::init();

        if (!self::$enabled) {
            self::logAccess($key, false, 'disabled');
            return $default;
        }

        try {
            $result = null;
            switch (self::$backend) {
                case 'redis':
                    $result = self::getRedis($key, $default);
                    break;

                case 'apcu':
                    $result = self::getApcu($key, $default);
                    break;

                case 'file':
                    $result = self::getFile($key, $default);
                    break;

                default:
                    self::logAccess($key, false, 'no-backend');
                    return $default;
            }

            // Check if we got a hit or miss
            $isHit = ($result !== $default);
            if ($isHit) {
                self::$hits++;
            } else {
                self::$misses++;
            }
            self::logAccess($key, $isHit, self::$backend);

            return $result;
        } catch (\Exception $e) {
            self::$misses++;
            self::logAccess($key, false, 'error');
            error_log('Cache get error: ' . $e->getMessage());
            return $default;
        }
    }

    /**
     * Set cached value
     *
     * @param string $key Cache key
     * @param mixed $value Value to cache
     * @param int|null $ttl Time to live in seconds (null = default)
     * @return bool True on success
     */
    public static function set(string $key, $value, ?int $ttl = null): bool
    {
        self::init();

        if (!self::$enabled) {
            return false;
        }

        $ttl = $ttl ?? self::$defaultTTL;

        try {
            switch (self::$backend) {
                case 'redis':
                    return self::setRedis($key, $value, $ttl);

                case 'apcu':
                    return self::setApcu($key, $value, $ttl);

                case 'file':
                    return self::setFile($key, $value, $ttl);

                default:
                    return false;
            }
        } catch (\Exception $e) {
            error_log('Cache set error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete cached value
     *
     * @param string $key Cache key
     * @return bool True on success
     */
    public static function delete(string $key): bool
    {
        self::init();

        if (!self::$enabled) {
            return false;
        }

        try {
            switch (self::$backend) {
                case 'redis':
                    return (bool)self::$redis->del($key);

                case 'apcu':
                    return apcu_delete($key);

                case 'file':
                    $filepath = self::getCacheFilepath($key);
                    if (file_exists($filepath)) {
                        return @unlink($filepath);
                    }
                    return true;

                default:
                    return false;
            }
        } catch (\Exception $e) {
            error_log('Cache delete error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Clear all cached values
     *
     * @return bool True on success
     */
    public static function clear(): bool
    {
        self::init();

        if (!self::$enabled) {
            return false;
        }

        try {
            switch (self::$backend) {
                case 'redis':
                    return self::$redis->flushDB();

                case 'apcu':
                    return apcu_clear_cache();

                case 'file':
                    return self::clearFileCache();

                default:
                    return false;
            }
        } catch (\Exception $e) {
            error_log('Cache clear error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Clear all caches including OPcache and APCu
     *
     * This is a comprehensive cache clear that clears:
     * - Application cache (Redis/File/APCu)
     * - PHP OPcache (compiled script cache)
     * - APCu user cache
     *
     * Use this when you need to ensure all caches are completely cleared,
     * such as after code deployment or configuration changes.
     *
     * @return array Status of each cache type cleared
     */
    public static function clearAll(): array
    {
        $status = [
            'application_cache' => false,
            'opcache' => false,
            'apcu' => false
        ];

        // Clear application cache
        $status['application_cache'] = self::clear();

        // Clear OPcache (compiled PHP scripts)
        if (function_exists('opcache_reset')) {
            $status['opcache'] = opcache_reset();
        }

        // Clear APCu user cache (separate from application cache backend)
        if (function_exists('apcu_clear_cache')) {
            $status['apcu'] = apcu_clear_cache();
        }

        return $status;
    }

    /**
     * Get detailed cache status information
     *
     * @return array Cache status details
     */
    public static function getStatus(): array
    {
        self::init();

        $status = [
            'enabled' => self::$enabled,
            'backend' => self::$backend,
            'opcache_enabled' => function_exists('opcache_get_status'),
            'apcu_enabled' => function_exists('apcu_cache_info'),
        ];

        // Add OPcache statistics if available
        if ($status['opcache_enabled']) {
            $opcache_status = opcache_get_status(false);
            $status['opcache'] = [
                'enabled' => $opcache_status['opcache_enabled'] ?? false,
                'cache_full' => $opcache_status['cache_full'] ?? false,
                'memory_usage' => $opcache_status['memory_usage'] ?? null,
            ];
        }

        // Add APCu statistics if available
        if ($status['apcu_enabled']) {
            try {
                $apcu_info = apcu_cache_info(true);
                $status['apcu'] = [
                    'num_entries' => $apcu_info['num_entries'] ?? 0,
                    'memory_usage' => $apcu_info['mem_size'] ?? 0,
                ];
            } catch (\Exception $e) {
                $status['apcu'] = ['error' => $e->getMessage()];
            }
        }

        return $status;
    }

    /**
     * Get cache hit/miss statistics
     *
     * @return array Cache statistics with hits, misses, and hit ratio
     */
    public static function getStats(): array
    {
        $total = self::$hits + self::$misses;
        return [
            'hits' => self::$hits,
            'misses' => self::$misses,
            'total' => $total,
            'hit_ratio' => $total > 0 ? round(self::$hits / $total * 100, 2) : 0,
        ];
    }

    /**
     * Log cache access (hit or miss)
     *
     * @param string $key The cache key accessed
     * @param bool $isHit True if cache hit, false if miss
     * @param string $backend The backend used (redis, file, apcu, disabled, error)
     */
    private static function logAccess(string $key, bool $isHit, string $backend): void
    {
        // Initialize log setting from config if not set
        if (self::$logEnabled === null) {
            self::$logEnabled = Application::get('cache_log_enabled', false);
        }

        if (!self::$logEnabled) {
            return;
        }

        $logFile = Application::get('cache_log_file', sys_get_temp_dir() . '/cache.log');
        $timestamp = date('Y-m-d H:i:s');
        $hitMiss = $isHit ? 'HIT' : 'MISS';

        $logLine = sprintf(
            "[%s] CACHE %s [%s] key=%s (hits=%d, misses=%d)\n",
            $timestamp,
            $hitMiss,
            $backend,
            substr($key, 0, 100),
            self::$hits,
            self::$misses
        );

        @file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);
    }

    /**
     * Reset cache statistics
     */
    public static function resetStats(): void
    {
        self::$hits = 0;
        self::$misses = 0;
    }

    /**
     * Check if key exists in cache
     *
     * @param string $key
     * @return bool
     */
    public static function has(string $key): bool
    {
        self::init();

        if (!self::$enabled) {
            return false;
        }

        try {
            switch (self::$backend) {
                case 'redis':
                    return (bool)self::$redis->exists($key);

                case 'apcu':
                    return apcu_exists($key);

                case 'file':
                    $filepath = self::getCacheFilepath($key);
                    if (!file_exists($filepath)) {
                        return false;
                    }
                    // Check if expired
                    $data = @unserialize(file_get_contents($filepath), ['allowed_classes' => false]);
                    if ($data === false) {
                        return false;
                    }
                    if (isset($data['expires']) && $data['expires'] < time()) {
                        @unlink($filepath);
                        return false;
                    }
                    return true;

                default:
                    return false;
            }
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get from Redis
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    private static function getRedis(string $key, $default)
    {
        $value = self::$redis->get($key);

        if ($value === false) {
            return $default;
        }

        return unserialize($value, ['allowed_classes' => false]);
    }

    /**
     * Set in Redis
     *
     * @param string $key
     * @param mixed $value
     * @param int $ttl
     * @return bool
     */
    private static function setRedis(string $key, $value, int $ttl): bool
    {
        $serialized = serialize($value);
        return self::$redis->setex($key, $ttl, $serialized);
    }

    /**
     * Get from APCu
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    private static function getApcu(string $key, $default)
    {
        $value = apcu_fetch($key, $success);

        return $success ? $value : $default;
    }

    /**
     * Set in APCu
     *
     * @param string $key
     * @param mixed $value
     * @param int $ttl
     * @return bool
     */
    private static function setApcu(string $key, $value, int $ttl): bool
    {
        return apcu_store($key, $value, $ttl);
    }

    /**
     * Get from file cache
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    private static function getFile(string $key, $default)
    {
        $filepath = self::getCacheFilepath($key);

        if (!file_exists($filepath)) {
            return $default;
        }

        $data = @unserialize(file_get_contents($filepath), ['allowed_classes' => false]);

        if ($data === false) {
            return $default;
        }

        // Check expiration
        if (isset($data['expires']) && $data['expires'] < time()) {
            @unlink($filepath);
            return $default;
        }

        return $data['value'] ?? $default;
    }

    /**
     * Set in file cache
     *
     * @param string $key
     * @param mixed $value
     * @param int $ttl
     * @return bool
     */
    private static function setFile(string $key, $value, int $ttl): bool
    {
        $filepath = self::getCacheFilepath($key);

        $data = [
            'value' => $value,
            'expires' => time() + $ttl,
            'created' => time()
        ];

        $serialized = serialize($data);

        // Write with exclusive lock
        $fp = @fopen($filepath, 'w');
        if (!$fp) {
            return false;
        }

        if (flock($fp, LOCK_EX)) {
            fwrite($fp, $serialized);
            flock($fp, LOCK_UN);
            fclose($fp);
            return true;
        }

        fclose($fp);
        return false;
    }

    /**
     * Get file cache filepath for key
     *
     * @param string $key
     * @return string
     */
    private static function getCacheFilepath(string $key): string
    {
        $hash = md5($key);
        // Create subdirectory structure to avoid too many files in one dir
        $subdir = substr($hash, 0, 2);
        $dir = self::$cacheDir . '/' . $subdir;

        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        return $dir . '/' . $hash . '.cache';
    }

    /**
     * Clear file cache directory
     *
     * @return bool
     */
    private static function clearFileCache(): bool
    {
        if (!is_dir(self::$cacheDir)) {
            return true;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(self::$cacheDir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->isDir()) {
                @rmdir($file->getPathname());
            } else {
                @unlink($file->getPathname());
            }
        }

        return true;
    }

    /**
     * Get current backend type
     *
     * @return string
     */
    public static function getBackend(): string
    {
        self::init();
        return self::$backend;
    }

    /**
     * Retrieve cached database query results
     *
     * Maps VBScript Cache_Retrieve() function - database-backed caching
     *
     * This method checks the cache first, and if not found or expired,
     * executes the SQL query and caches the results.
     *
     * Returns a ColumnMajorArray for VBScript-style column-major access:
     * $result[column][row] instead of $result[row][column]
     *
     * @param string $identifier Cache key identifier
     * @param \PDO|string $connection PDO connection or connection name
     * @param string $sql SQL query to execute if cache miss
     * @return ColumnMajorArray|null Column-major array or null if no results
     */
    public static function retrieve(string $identifier, $connection, string $sql): ?ColumnMajorArray
    {
        self::init();

        // Check if caching is enabled
        if (!self::$enabled) {
            $results = self::executeQuery($connection, $sql);
            return $results ? new ColumnMajorArray($results) : null;
        }

        // Try to get from cache first
        $cacheKey = 'cache_' . $identifier;
        $cached = self::get($cacheKey);

        if ($cached !== null) {
            // Cached data is stored as row-major, convert to column-major
            return new ColumnMajorArray($cached);
        }

        // Cache miss - execute query
        $results = self::executeQuery($connection, $sql);

        // Store in cache if we got results
        if ($results !== null) {
            self::set($cacheKey, $results);
            return new ColumnMajorArray($results);
        }

        return null;
    }

    /**
     * Execute a database query and return results as array
     *
     * @param \PDO|string $connection PDO connection or connection name
     * @param string $sql SQL query
     * @return array|null Results or null if no results
     */
    private static function executeQuery($connection, string $sql): ?array
    {
        try {
            // Get PDO connection
            if (is_string($connection)) {
                // Connection name or DSN - get from Database helper
                $conn = \App\Library\Database::getNamedConnection($connection);
            } elseif ($connection instanceof \PDO) {
                $conn = $connection;
            } else {
                return null;
            }

            // Process SQL for database compatibility (e.g., boolean values)
            $sql = \App\Library\SQL::processSQL($conn, $sql);

            // Execute query
            $stmt = $conn->prepare($sql);
            $stmt->execute();

            // Fetch all results (with Windows-1252 → UTF-8 conversion for ODBC)
            $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            if (!empty($results) && $conn->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'odbc') {
                foreach ($results as &$row) {
                    foreach ($row as $key => &$value) {
                        if (is_string($value) && $value !== '' && !mb_check_encoding($value, 'UTF-8')) {
                            $value = mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
                        }
                    }
                    unset($value);
                }
                unset($row);
            }

            return empty($results) ? null : $results;

        } catch (\PDOException $e) {
            error_log('Cache::retrieve query error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Enable or disable caching
     *
     * Maps VBScript Cache_Set_On() function
     *
     * @param bool $enabled True to enable, false to disable
     */
    public static function setEnabled(bool $enabled): void
    {
        self::init();
        self::$enabled = $enabled;
    }

    /**
     * Check if caching is enabled
     *
     * Maps VBScript Cache_Is_On() function
     *
     * @return bool True if caching is enabled
     */
    public static function isEnabled(): bool
    {
        self::init();
        return self::$enabled;
    }

    /**
     * Check if caching is enabled for specific identifier
     *
     * Maps VBScript Cache_On() function
     *
     * @param string $identifier Cache identifier
     * @return bool True if caching is enabled
     */
    public static function isEnabledFor(string $identifier): bool
    {
        self::init();
        return self::$enabled;
    }

    /**
     * Clear cache file by identifier
     *
     * Maps VBScript lib_CacheFileClear() function
     *
     * @param string $identifier Cache identifier
     * @return bool True on success
     */
    public static function clearFile(string $identifier): bool
    {
        $filename = self::getCacheFilename($identifier);

        if (file_exists($filename)) {
            return @unlink($filename);
        }

        return true;
    }

    /**
     * Load cache content from file
     *
     * Maps VBScript lib_CacheFileLoad() function
     *
     * @param string $identifier Cache identifier
     * @return string|null File content or null if not found
     */
    public static function loadFile(string $identifier): ?string
    {
        $filename = self::getCacheFilename($identifier);

        if (!file_exists($filename)) {
            return null;
        }

        $content = @file_get_contents($filename);

        if ($content === false) {
            return null;
        }

        // Remove UTF-8 BOM if present (like VBScript version)
        if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
            $content = substr($content, 3);
        }

        return $content;
    }

    /**
     * Save content to cache file
     *
     * Maps VBScript lib_CacheFileSave() function
     *
     * @param string $identifier Cache identifier
     * @param string $content Content to save
     * @return bool True on success
     */
    public static function saveFile(string $identifier, string $content): bool
    {
        $filename = self::getCacheFilename($identifier);

        // Ensure directory exists
        $dir = dirname($filename);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        return @file_put_contents($filename, $content, LOCK_EX) !== false;
    }

    /**
     * Get cache filename for identifier
     *
     * Maps VBScript lib_Cache_GetFilename() function
     *
     * @param string $identifier Cache identifier
     * @return string Full path to cache file
     */
    private static function getCacheFilename(string $identifier): string
    {
        // Get cache directory from Application config or use default
        $cacheDir = \App\Library\Application::get('cache_directory', sys_get_temp_dir() . '/cma_cache');

        // Sanitize filename (remove invalid characters)
        $safeIdentifier = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $identifier);
        $safeIdentifier = substr($safeIdentifier, 0, 240); // Limit length

        return $cacheDir . '/' . $safeIdentifier . '.cache';
    }

    /**
     * Retrieve from cache with file backing
     *
     * Maps VBScript Lib_Cache_retrieve_fromfile() function
     *
     * This is a hybrid approach that tries memory cache first,
     * then file cache, then executes the SQL query.
     *
     * Returns a ColumnMajorArray for VBScript-style column-major access:
     * $arr[column][row] or $arr['fieldname'][0]
     *
     * @param string $identifier Cache identifier
     * @param \PDO|string $connection Database connection
     * @param string $sql SQL query
     * @return ColumnMajorArray|null Column-major array or null if no results
     */
    public static function retrieveFromFile(string $identifier, $connection, string $sql): ?ColumnMajorArray
    {
        self::init();

        if (!self::$enabled) {
            $results = self::executeQuery($connection, $sql);
            return $results ? new ColumnMajorArray($results) : null;
        }

        $cacheKey = 'cache_' . $identifier;

        // Try memory cache first
        $cached = self::get($cacheKey);
        if ($cached !== null) {
            return new ColumnMajorArray($cached);
        }

        // Try file cache
        $filename = self::getCacheFilename($identifier);
        if (file_exists($filename)) {
            // Try to load from file
            $fileContent = @file_get_contents($filename);
            if ($fileContent !== false) {
                $data = @unserialize($fileContent, ['allowed_classes' => false]);
                if ($data !== false && is_array($data)) {
                    // Store in memory cache for next time
                    self::set($cacheKey, $data);
                    return new ColumnMajorArray($data);
                }
            }
        }

        // Cache miss - execute query
        $results = self::executeQuery($connection, $sql);

        if ($results !== null) {
            // Save to both memory and file cache
            self::set($cacheKey, $results);
            @file_put_contents($filename, serialize($results), LOCK_EX);
        }

        return $results ? new ColumnMajorArray($results) : null;
    }

    /**
     * Clear all cache files
     *
     * Maps VBScript Cache_ClearFiles() function
     *
     * @return bool True on success
     */
    public static function clearAllFiles(): bool
    {
        $cacheDir = \App\Library\Application::get('cache_directory', sys_get_temp_dir() . '/cma_cache');

        if (!is_dir($cacheDir)) {
            return true;
        }

        try {
            $files = glob($cacheDir . '/*.cache');
            if ($files === false) {
                return false;
            }

            foreach ($files as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }

            return true;
        } catch (\Exception $e) {
            error_log('Cache::clearAllFiles error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Retrieve database schema with caching
     *
     * Maps VBScript Cache_Schema_Retrieve() function
     *
     * Note: This caches the entire table schema. Use sparingly.
     *
     * @param \PDO $connection Database connection
     * @param string $tableName Table name
     * @return array|null Schema information or null
     */
    public static function retrieveSchema(\PDO $connection, string $tableName): ?array
    {
        self::init();

        // Schema caching is disabled by default (like VBScript version)
        // Can be enabled via Application config
        $schemaCacheEnabled = \App\Library\Application::get('schema_cache_enabled', false);

        if (!self::$enabled || !$schemaCacheEnabled) {
            return self::fetchSchema($connection, $tableName);
        }

        $cacheKey = 'cache_schema_' . $tableName;

        // Try cache first
        $cached = self::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        // Fetch from database
        $schema = self::fetchSchema($connection, $tableName);

        if ($schema !== null) {
            self::set($cacheKey, $schema);
        }

        return $schema;
    }

    /**
     * Fetch database schema information
     *
     * @param \PDO $connection Database connection
     * @param string $tableName Table name
     * @return array|null Schema information
     */
    private static function fetchSchema(\PDO $connection, string $tableName): ?array
    {
        try {
            // Query INFORMATION_SCHEMA for column information
            $sql = "SELECT
                        COLUMN_NAME,
                        DATA_TYPE,
                        CHARACTER_MAXIMUM_LENGTH,
                        IS_NULLABLE,
                        COLUMN_DEFAULT,
                        COLUMN_KEY
                    FROM INFORMATION_SCHEMA.COLUMNS
                    WHERE TABLE_NAME = :table_name
                    ORDER BY ORDINAL_POSITION";

            $stmt = $connection->prepare($sql);
            $stmt->execute(['table_name' => $tableName]);

            $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            return empty($results) ? null : $results;

        } catch (\PDOException $e) {
            error_log('Cache::fetchSchema error: ' . $e->getMessage());
            return null;
        }
    }

    // =====================================================================
    // CROSS-INSTANCE CACHE INVALIDATION
    // =====================================================================

    /**
     * Invalidate a cache group across all PHP instances
     *
     * This writes to a shared invalidation signal file that all instances check.
     * When any instance calls this method, all other instances will see the
     * invalidation on their next cache access (within invalidation_check_interval).
     *
     * @param string $group Cache group name (e.g., 'forms', 'security', 'menu', 'all')
     * @return bool True on success
     */
    public static function invalidateGroup(string $group): bool
    {
        self::init();

        // Update local invalidation cache
        $timestamp = microtime(true);
        self::$invalidationCache[$group] = $timestamp;
        self::$invalidationCache['_last_update'] = $timestamp;

        // Write to shared invalidation file
        return self::writeInvalidationSignal($group, $timestamp);
    }

    /**
     * Invalidate all cache groups across all PHP instances
     *
     * @return bool True on success
     */
    public static function invalidateAll(): bool
    {
        return self::invalidateGroup('all');
    }

    /**
     * Check if a cache entry is still valid (not invalidated by another instance)
     *
     * @param string $key Cache key
     * @param string $group Cache group (default: 'general')
     * @param float|null $createdAt When the cache entry was created (microtime)
     * @return bool True if still valid, false if invalidated
     */
    public static function isValid(string $key, string $group = 'general', ?float $createdAt = null): bool
    {
        self::init();

        // Refresh invalidation cache if needed
        self::refreshInvalidationCache();

        // Check if the group was invalidated
        $groupInvalidatedAt = self::$invalidationCache[$group] ?? 0;
        $allInvalidatedAt = self::$invalidationCache['all'] ?? 0;
        $invalidatedAt = max($groupInvalidatedAt, $allInvalidatedAt);

        // If no invalidation timestamp, entry is valid
        if ($invalidatedAt === 0) {
            return true;
        }

        // If we don't know when the entry was created, assume it's invalid if any invalidation occurred
        if ($createdAt === null) {
            return false;
        }

        // Entry is valid if it was created after the last invalidation
        return $createdAt > $invalidatedAt;
    }

    /**
     * Get cached value with cross-instance invalidation support
     *
     * This is an enhanced version of get() that checks for cross-instance invalidation.
     * Use this when you need guaranteed cache consistency across PHP workers.
     *
     * @param string $key Cache key
     * @param string $group Cache group for invalidation (default: 'general')
     * @param mixed $default Default value if not found or invalidated
     * @return mixed
     */
    public static function getWithInvalidation(string $key, string $group = 'general', $default = null)
    {
        self::init();

        if (!self::$enabled) {
            return $default;
        }

        // First, get the raw cached data (which includes metadata)
        $rawData = self::getRawWithMetadata($key);

        if ($rawData === null) {
            self::$misses++;
            return $default;
        }

        // Check if the entry is still valid
        $createdAt = $rawData['_created_at'] ?? null;
        $entryGroup = $rawData['_group'] ?? $group;

        if (!self::isValid($key, $entryGroup, $createdAt)) {
            // Entry was invalidated by another instance
            self::$misses++;
            self::delete($key);
            return $default;
        }

        self::$hits++;
        return $rawData['_value'] ?? $default;
    }

    /**
     * Set cached value with cross-instance invalidation support
     *
     * This stores metadata alongside the value to support invalidation checks.
     *
     * @param string $key Cache key
     * @param mixed $value Value to cache
     * @param string $group Cache group for invalidation (default: 'general')
     * @param int|null $ttl Time to live in seconds
     * @return bool True on success
     */
    public static function setWithInvalidation(string $key, $value, string $group = 'general', ?int $ttl = null): bool
    {
        self::init();

        if (!self::$enabled) {
            return false;
        }

        // Wrap value with metadata
        $data = [
            '_value' => $value,
            '_group' => $group,
            '_created_at' => microtime(true),
        ];

        return self::set($key, $data, $ttl);
    }

    /**
     * Get raw cached data including metadata
     *
     * @param string $key Cache key
     * @return array|null Raw data with metadata or null
     */
    private static function getRawWithMetadata(string $key): ?array
    {
        $data = self::get($key);

        // Check if this is data with invalidation metadata
        if (is_array($data) && isset($data['_value'])) {
            return $data;
        }

        // Legacy data without metadata - wrap it
        if ($data !== null) {
            return [
                '_value' => $data,
                '_group' => 'general',
                '_created_at' => null, // Unknown creation time
            ];
        }

        return null;
    }

    /**
     * Refresh the in-memory invalidation cache from the shared file
     *
     * This is called periodically to check for invalidations from other instances.
     */
    private static function refreshInvalidationCache(): void
    {
        $now = time();

        // Don't check too frequently
        if ($now - self::$lastInvalidationCheck < self::$invalidationCheckInterval) {
            return;
        }

        self::$lastInvalidationCheck = $now;

        // For Redis backend, use Redis pub/sub or direct key lookup
        if (self::$backend === 'redis' && self::$redis !== null) {
            self::refreshInvalidationFromRedis();
            return;
        }

        // For file/APCu backends, read from shared file
        self::refreshInvalidationFromFile();
    }

    /**
     * Refresh invalidation cache from shared file
     */
    private static function refreshInvalidationFromFile(): void
    {
        if (self::$invalidationFile === null || !file_exists(self::$invalidationFile)) {
            return;
        }

        $content = @file_get_contents(self::$invalidationFile);
        if ($content === false) {
            return;
        }

        $data = @json_decode($content, true);
        if (!is_array($data)) {
            return;
        }

        // Merge with local cache, keeping the newer timestamps
        foreach ($data as $group => $timestamp) {
            $localTimestamp = self::$invalidationCache[$group] ?? 0;
            if ($timestamp > $localTimestamp) {
                self::$invalidationCache[$group] = $timestamp;
            }
        }
    }

    /**
     * Refresh invalidation cache from Redis
     */
    private static function refreshInvalidationFromRedis(): void
    {
        try {
            $data = self::$redis->hGetAll('cache_invalidation');
            if (!is_array($data)) {
                return;
            }

            foreach ($data as $group => $timestamp) {
                $localTimestamp = self::$invalidationCache[$group] ?? 0;
                if ((float)$timestamp > $localTimestamp) {
                    self::$invalidationCache[$group] = (float)$timestamp;
                }
            }
        } catch (RedisException $e) {
            error_log('Cache::refreshInvalidationFromRedis error: ' . $e->getMessage());
        }
    }

    /**
     * Write invalidation signal to shared storage
     *
     * @param string $group Cache group
     * @param float $timestamp Invalidation timestamp
     * @return bool True on success
     */
    private static function writeInvalidationSignal(string $group, float $timestamp): bool
    {
        // For Redis, use Redis hash for better performance
        if (self::$backend === 'redis' && self::$redis !== null) {
            try {
                self::$redis->hSet('cache_invalidation', $group, (string)$timestamp);
                // Also publish to a channel for real-time notification (if listeners are set up)
                self::$redis->publish('cache_invalidation_channel', json_encode([
                    'group' => $group,
                    'timestamp' => $timestamp
                ]));
                return true;
            } catch (RedisException $e) {
                error_log('Cache::writeInvalidationSignal Redis error: ' . $e->getMessage());
                // Fall through to file-based
            }
        }

        // File-based invalidation signal
        return self::writeInvalidationToFile($group, $timestamp);
    }

    /**
     * Write invalidation signal to shared file
     *
     * Also prunes entries older than invalidationTTL to prevent unbounded growth.
     *
     * @param string $group Cache group
     * @param float $timestamp Invalidation timestamp
     * @return bool True on success
     */
    private static function writeInvalidationToFile(string $group, float $timestamp): bool
    {
        if (self::$invalidationFile === null) {
            return false;
        }

        // Read existing data
        $data = [];
        if (self::$invalidationFile !== null && file_exists(self::$invalidationFile)) {
            $content = @file_get_contents(self::$invalidationFile);
            if ($content !== false) {
                $data = @json_decode($content, true) ?? [];
            }
        }

        // Prune old entries (older than TTL)
        $cutoff = microtime(true) - self::$invalidationTTL;
        foreach ($data as $key => $value) {
            if ($key !== '_last_update' && is_numeric($value) && $value < $cutoff) {
                unset($data[$key]);
            }
        }

        // Update with new invalidation
        $data[$group] = $timestamp;
        $data['_last_update'] = $timestamp;

        // Write with exclusive lock
        $fp = @fopen(self::$invalidationFile, 'w');
        if (!$fp) {
            return false;
        }

        if (flock($fp, LOCK_EX)) {
            fwrite($fp, json_encode($data, JSON_PRETTY_PRINT));
            flock($fp, LOCK_UN);
            fclose($fp);
            return true;
        }

        fclose($fp);
        return false;
    }

    /**
     * Get invalidation status for all groups
     *
     * @return array Current invalidation timestamps for all groups
     */
    public static function getInvalidationStatus(): array
    {
        self::init();
        self::refreshInvalidationCache();

        return [
            'enabled' => self::$enabled,
            'backend' => self::$backend,
            'invalidation_file' => self::$invalidationFile,
            'check_interval' => self::$invalidationCheckInterval,
            'groups' => self::$invalidationCache,
        ];
    }

    /**
     * Clear local invalidation cache
     *
     * Use this when you want to force a refresh from the shared storage.
     */
    public static function clearInvalidationCache(): void
    {
        self::$invalidationCache = [];
        self::$lastInvalidationCheck = 0;
    }

    /**
     * Clear all invalidation signals (reset the signal file)
     *
     * Use this to completely reset cache invalidation state.
     * After calling this, all cached entries will be considered valid
     * until they are explicitly invalidated again.
     *
     * @return bool True on success
     */
    public static function clearInvalidationSignals(): bool
    {
        self::init();

        // Clear local cache
        self::$invalidationCache = [];
        self::$lastInvalidationCheck = 0;

        // Clear Redis hash if using Redis
        if (self::$backend === 'redis' && self::$redis !== null) {
            try {
                self::$redis->del('cache_invalidation');
            } catch (RedisException $e) {
                error_log('Cache::clearInvalidationSignals Redis error: ' . $e->getMessage());
            }
        }

        // Clear/delete the signal file
        if (self::$invalidationFile !== null && file_exists(self::$invalidationFile)) {
            return @unlink(self::$invalidationFile);
        }

        return true;
    }

    /**
     * Prune expired invalidation signals without adding new ones
     *
     * Call this periodically (e.g., via cron) to clean up old signals.
     *
     * @return int Number of entries pruned
     */
    public static function pruneInvalidationSignals(): int
    {
        self::init();

        if (!file_exists(self::$invalidationFile)) {
            return 0;
        }

        $content = @file_get_contents(self::$invalidationFile);
        if ($content === false) {
            return 0;
        }

        $data = @json_decode($content, true);
        if (!is_array($data)) {
            return 0;
        }

        $cutoff = microtime(true) - self::$invalidationTTL;
        $pruned = 0;

        foreach ($data as $key => $value) {
            if ($key !== '_last_update' && is_numeric($value) && $value < $cutoff) {
                unset($data[$key]);
                $pruned++;
            }
        }

        if ($pruned > 0) {
            $data['_last_update'] = microtime(true);
            @file_put_contents(self::$invalidationFile, json_encode($data, JSON_PRETTY_PRINT), LOCK_EX);
        }

        return $pruned;
    }
}

