<?php
/**
 * Structured Logger for CMA
 *
 * Provides structured logging for application events, errors, and debugging.
 * Supports log levels, context, and daily rotation.
 *
 * Usage:
 *   Logger::info('User logged in', ['userId' => 123]);
 *   Logger::error('Failed to save', ['formId' => 45, 'error' => $e->getMessage()]);
 *   Logger::debug('Query result', ['count' => $count]);
 */

namespace Cma\Services;

use App\Library\Application;
use App\Library\Arr;

class Logger
{
    // Log levels (PSR-3 compatible)
    public const EMERGENCY = 'emergency';
    public const ALERT = 'alert';
    public const CRITICAL = 'critical';
    public const ERROR = 'error';
    public const WARNING = 'warning';
    public const NOTICE = 'notice';
    public const INFO = 'info';
    public const DEBUG = 'debug';

    // Level priorities (lower = more severe)
    private const LEVEL_PRIORITY = [
        self::EMERGENCY => 0,
        self::ALERT => 1,
        self::CRITICAL => 2,
        self::ERROR => 3,
        self::WARNING => 4,
        self::NOTICE => 5,
        self::INFO => 6,
        self::DEBUG => 7,
    ];

    private static ?string $logDir = null;
    private static ?string $requestId = null;
    private static ?string $minLevel = null;

    /**
     * Initialize the logger
     */
    private static function init(): void
    {
        if (self::$requestId === null) {
            self::$requestId = substr(md5(uniqid('', true)), 0, 8);
        }
    }

    /**
     * Get the minimum log level from environment
     * In production (P), only WARNING and above
     * In test/development (T, O), DEBUG and above
     */
    private static function getMinLevel(): string
    {
        if (self::$minLevel === null) {
            $env = Application::get('omgeving', 'P');
            // P = Production, T = Test, O = Development
            self::$minLevel = ($env === 'P') ? self::WARNING : self::DEBUG;
        }
        return self::$minLevel;
    }

    /**
     * Check if a level should be logged based on minimum level
     */
    private static function shouldLog(string $level): bool
    {
        $minPriority = self::LEVEL_PRIORITY[self::getMinLevel()] ?? 7;
        $levelPriority = self::LEVEL_PRIORITY[$level] ?? 7;
        return $levelPriority <= $minPriority;
    }

    /**
     * Get the log directory, creating it if needed
     */
    private static function getLogDir(): string
    {
        if (self::$logDir === null) {
            self::$logDir = dirname(__DIR__, 3) . '/data/logs';

            if (!is_dir(self::$logDir)) {
                @mkdir(self::$logDir, 0755, true);
            }
        }
        return self::$logDir;
    }

    /**
     * Get the current log file path (daily rotation)
     */
    private static function getLogFile(): string
    {
        return self::getLogDir() . '/app_' . date('Y-m-d') . '.log';
    }

    /**
     * Log a message with level and context
     */
    public static function log(string $level, string $message, array $context = []): void
    {
        // Check if this level should be logged
        if (!self::shouldLog($level)) {
            return;
        }

        self::init();

        $entry = [
            'ts' => date('Y-m-d\TH:i:s.') . sprintf('%03d', (microtime(true) - floor(microtime(true))) * 1000),
            'level' => $level,
            'req' => self::$requestId,
            'msg' => $message,
        ];

        // Add context if provided
        if (!empty($context)) {
            // Sanitize context to remove sensitive data
            $entry['ctx'] = self::sanitizeContext($context);
        }

        // Add request info for errors
        if (in_array($level, [self::ERROR, self::CRITICAL, self::ALERT, self::EMERGENCY])) {
            $entry['url'] = \App\Library\Request::server('REQUEST_URI');
            $entry['method'] = \App\Library\Request::server('REQUEST_METHOD');
            $entry['ip'] = \App\Library\Request::server('REMOTE_ADDR');
        }

        // Write to log file
        $line = json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
        @file_put_contents(self::getLogFile(), $line, FILE_APPEND | LOCK_EX);

        // Also write to PHP error log for severe errors
        if (in_array($level, [self::ERROR, self::CRITICAL, self::ALERT, self::EMERGENCY])) {
            error_log("[$level] $message " . json_encode($context));
        }
    }

    /**
     * Remove sensitive data from context
     */
    private static function sanitizeContext(array $context): array
    {
        $sensitiveKeys = ['password', 'token', 'secret', 'api_key', 'apikey', 'credentials'];

        foreach ($context as $key => $value) {
            $lowerKey = strtolower($key);
            foreach ($sensitiveKeys as $sensitive) {
                if (str_contains($lowerKey, $sensitive)) {
                    $context[$key] = '[REDACTED]';
                    break;
                }
            }

            // Recursively sanitize nested arrays
            if (Arr::isArray($value)) {
                $context[$key] = self::sanitizeContext($value);
            }
        }

        return $context;
    }

    /**
     * Emergency: system is unusable
     */
    public static function emergency(string $message, array $context = []): void
    {
        self::log(self::EMERGENCY, $message, $context);
    }

    /**
     * Alert: action must be taken immediately
     */
    public static function alert(string $message, array $context = []): void
    {
        self::log(self::ALERT, $message, $context);
    }

    /**
     * Critical: critical conditions
     */
    public static function critical(string $message, array $context = []): void
    {
        self::log(self::CRITICAL, $message, $context);
    }

    /**
     * Error: error conditions
     */
    public static function error(string $message, array $context = []): void
    {
        self::log(self::ERROR, $message, $context);
    }

    /**
     * Warning: warning conditions
     */
    public static function warning(string $message, array $context = []): void
    {
        self::log(self::WARNING, $message, $context);
    }

    /**
     * Notice: normal but significant condition
     */
    public static function notice(string $message, array $context = []): void
    {
        self::log(self::NOTICE, $message, $context);
    }

    /**
     * Info: informational messages
     */
    public static function info(string $message, array $context = []): void
    {
        self::log(self::INFO, $message, $context);
    }

    /**
     * Debug: debug-level messages (only in dev/test)
     */
    public static function debug(string $message, array $context = []): void
    {
        self::log(self::DEBUG, $message, $context);
    }

    /**
     * Log an exception with full trace
     */
    public static function exception(\Throwable $e, string $message = '', array $context = []): void
    {
        $context['exception'] = [
            'class' => get_class($e),
            'message' => $e->getMessage(),
            'code' => $e->getCode(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => array_slice($e->getTrace(), 0, 10), // First 10 frames
        ];

        $msg = $message ?: 'Exception: ' . get_class($e);
        self::log(self::ERROR, $msg, $context);
    }

    /**
     * Get the request ID for correlation
     */
    public static function getRequestId(): string
    {
        self::init();
        return self::$requestId;
    }

    /**
     * Cleanup old log files (keep last N days)
     */
    public static function cleanup(int $keepDays = 30): int
    {
        $logDir = self::getLogDir();
        $cutoff = strtotime("-{$keepDays} days");
        $deleted = 0;

        foreach (glob($logDir . '/app_*.log') as $file) {
            if (filemtime($file) < $cutoff) {
                @unlink($file);
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * Read log entries for analysis
     */
    public static function readLogs(?string $date = null, ?string $level = null, int $limit = 1000): array
    {
        $date = $date ?? date('Y-m-d');
        $file = self::getLogDir() . '/app_' . $date . '.log';

        if (!file_exists($file)) {
            return [];
        }

        $entries = [];
        $handle = fopen($file, 'r');

        if ($handle) {
            while (($line = fgets($handle)) !== false && count($entries) < $limit) {
                $entry = json_decode(trim($line), true);
                if ($entry && ($level === null || ($entry['level'] ?? '') === $level)) {
                    $entries[] = $entry;
                }
            }
            fclose($handle);
        }

        return $entries;
    }
}
