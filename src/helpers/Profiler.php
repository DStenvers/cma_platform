<?php

namespace App\Library;

/**
 * Profiler Helper Class
 *
 * Performance profiler for measuring execution time and logging user actions.
 * Based on VBScript lib_profiler.inc functionality.
 *
 * Features:
 * - Execution time tracking with start/end/tussenstand (intermediate) methods
 * - Yellow timing box display in test/local environments
 * - CSV logging with user context and action tracking
 * - Threshold-based logging (only log if execution exceeds threshold)
 */
class Profiler
{
    /**
     * @var float Script start time
     */
    private static $start = null;

    /**
     * @var float Last intermediate checkpoint time
     */
    private static $lastTussenstand = null;

    /**
     * @var bool Whether profiler is enabled (verbose mode)
     */
    private static $enabled = null;

    /**
     * @var float Minimum execution time (ms) to log
     */
    private static $threshold = 0;

    /**
     * @var string Path to CSV log file
     */
    private static $logFile = null;

    /**
     * @var string CSV separator
     */
    private static $separator = ';';

    /**
     * @var array Named marks for timing
     */
    private static $marks = [];

    /**
     * Initialize profiler
     *
     * Reads settings from .env:
     * - PROFILER_ENABLED=true|false (default: false, or auto-enabled in L/O/T environments)
     * - PROFILER_LOG_FILE=path/to/profiler.csv (default: profiler.csv in base path)
     * - PROFILER_THRESHOLD_MS=100 (default: 0, minimum ms to log)
     */
    private static function init(): void
    {
        if (self::$enabled !== null) {
            return;
        }

        // Check .env for explicit profiler setting
        $envEnabled = $_ENV['PROFILER_ENABLED'] ?? getenv('PROFILER_ENABLED');
        if ($envEnabled !== false && $envEnabled !== null) {
            self::$enabled = filter_var($envEnabled, FILTER_VALIDATE_BOOLEAN);
        } else {
            // Auto-enable in test/development environments if not explicitly set
            $env = Application::get('omgeving', 'P');
            self::$enabled = Application::get('test', false) || in_array($env, ['L', 'O', 'T']);
        }

        // Set log file path from .env or default
        $logPath = $_ENV['PROFILER_LOG_FILE'] ?? getenv('PROFILER_LOG_FILE');
        if ($logPath !== false && $logPath !== null && !empty($logPath)) {
            self::$logFile = $logPath;
        } else {
            $basePath = Application::get('base_path', '/');
            self::$logFile = rtrim($_SERVER['DOCUMENT_ROOT'] . $basePath, '/') . '/profiler.csv';
        }

        // Set threshold from .env or default
        $threshold = $_ENV['PROFILER_THRESHOLD_MS'] ?? getenv('PROFILER_THRESHOLD_MS');
        if ($threshold !== false && $threshold !== null) {
            self::$threshold = (float)$threshold;
        }

        // Initialize start time
        if (self::$start === null) {
            self::$start = microtime(true);
            self::$lastTussenstand = self::$start;
        }
    }

    /**
     * Set profiler enabled/disabled (runtime override of .env setting)
     *
     * @param bool $enabled Whether to enable the profiler
     * @return void
     */
    public static function setEnabled(bool $enabled): void
    {
        self::init();
        self::$enabled = $enabled;
    }

    /**
     * Start or restart the profiler timer
     *
     * @return void
     */
    public static function start(): void
    {
        self::init();
        self::$start = microtime(true);
        self::$lastTussenstand = self::$start;
    }

    /**
     * End profiling and display/log elapsed time
     *
     * Shows yellow box in test/local environments, logs to debug otherwise
     *
     * @return void
     */
    public static function end(): void
    {
        self::init();

        if (!self::$enabled) {
            return;
        }

        $elapsed = (microtime(true) - self::$start) * 1000; // Convert to milliseconds

        $isLocal = Application::get('local', false);
        $isTest = Application::get('test', false);

        if ($isLocal || $isTest) {
            // Show yellow timing box in bottom-left corner (same as VBScript version)
            echo '<div title="Klik om te verwijderen" onclick="this.style.display=\'none\'" class="screenonly profiler" style="position:fixed;z-index:99998;bottom:20px;left:5px;width:auto;height:22px;padding-top:3px;padding-left:5px;padding-right:5px;background-color:yellow;box-shadow:2px 2px 2px 2px #dddddd;border:1px solid #cccccc;color:#000000"><font class="small">' . (int)$elapsed . '<font style="color:#aaaaaa">&nbsp;ms</font></font></div>';
        } else {
            Debug::write('Profiler ' . (int)$elapsed . 'ms');
        }
    }

    /**
     * Record intermediate checkpoint (tussenstand)
     *
     * Logs time since last checkpoint if exceeds threshold
     *
     * @param string $text Description of checkpoint
     * @return void
     */
    public static function tussenstand(string $text = ''): void
    {
        self::init();

        if (!self::$enabled) {
            return;
        }

        $now = microtime(true);
        $sinceLast = ($now - self::$lastTussenstand) * 1000; // milliseconds
        $total = ($now - self::$start) * 1000; // milliseconds

        if ($sinceLast > self::$threshold) {
            $message = 'Profiler ' . (int)$sinceLast . 'ms';
            if ($text !== '') {
                $message .= ' -> ' . $text;
            }
            $message .= ' -> total: ' . (int)$total . ' ms';

            Debug::write($message);
        }

        self::$lastTussenstand = microtime(true);
    }

    /**
     * Set minimum threshold (in milliseconds) for logging
     *
     * @param float $minimum Minimum execution time to log
     * @return void
     */
    public static function threshold(float $minimum): void
    {
        self::init();
        self::$threshold = $minimum;
    }

    /**
     * Enable or disable verbose profiling output
     *
     * @param bool $enabled Whether to enable verbose mode
     * @return void
     */
    public static function verbose(bool $enabled): void
    {
        self::init();
        self::$enabled = $enabled;
    }

    /**
     * Log user action to CSV file
     *
     * Logs: timestamp, user_id, execution_time, area, url, action, role, details
     *
     * @param string $area Area of application (e.g., 'CMA', 'Voorkant')
     * @param string $ms Execution time in milliseconds (can be calculated automatically)
     * @param string $action Action performed
     * @param string $role User role (if relevant)
     * @param string $details Additional details
     * @return void
     */
    public static function log(string $area, string $ms, string $action, string $role = '', string $details = ''): void
    {
        self::init();

        try {
            // Get user ID from cookies (CMA user or regular user)
            $userId = '';
            if ($area === 'CMA') {
                $userId = $_COOKIE['CMAU'] ?? '';
            } else {
                $userId = $_COOKIE['USERL'] ?? '';
            }

            // Clean details (remove line breaks)
            $details = str_replace(["\r\n", "\n", "\r"], ' ', $details);

            // Build CSV line
            $timestamp = date('Y-m-d H:i:s') . '_' . microtime(true);
            $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
            $url = self::getCurrentUrl();

            $line = implode(self::$separator, [
                $timestamp,
                $userId,
                $ms,
                $area,
                $method . ' ' . $url,
                $action,
                $role,
                $details
            ]) . "\n";

            // Ensure directory exists
            $dir = dirname(self::$logFile);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            // Append to log file
            file_put_contents(self::$logFile, $line, FILE_APPEND | LOCK_EX);

        } catch (\Exception $e) {
            // Silently fail if logging fails (don't break application)
            error_log('Profiler log error: ' . $e->getMessage());
        }
    }

    /**
     * Get current URL
     *
     * @return string Current request URL
     */
    private static function getCurrentUrl(): string
    {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        return $protocol . $host . $uri;
    }

    /**
     * Check if profiler is enabled
     *
     * @return bool
     */
    public static function isEnabled(): bool
    {
        self::init();
        return self::$enabled;
    }

    /**
     * Start a named mark for timing
     *
     * @param string $name Mark name
     * @return void
     */
    public static function mark(string $name): void
    {
        self::init();
        self::$marks[$name] = microtime(true);
    }

    /**
     * End a named mark and optionally log if exceeds threshold
     *
     * @param string $name Mark name
     * @param float $thresholdMs Minimum milliseconds to log (default 0 = always log)
     * @param string $details Additional details for log
     * @return float Elapsed time in milliseconds
     */
    public static function markEnd(string $name, float $thresholdMs = 0, string $details = ''): float
    {
        self::init();

        $start = self::$marks[$name] ?? microtime(true);
        $elapsed = (microtime(true) - $start) * 1000;

        if (self::$enabled && $elapsed >= $thresholdMs && $thresholdMs > 0) {
            $message = "Profiler [{$name}] " . (int)$elapsed . 'ms';
            if ($details !== '') {
                $message .= ' - ' . $details;
            }
            Debug::write($message);
        }

        unset(self::$marks[$name]);
        return $elapsed;
    }

    /**
     * Get elapsed time since start (in milliseconds)
     *
     * @return float
     */
    public static function getElapsed(): float
    {
        self::init();
        return (microtime(true) - self::$start) * 1000;
    }
}
