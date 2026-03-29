<?php
/**
 * Performance Logger for CMA
 *
 * Logs performance metrics from both PHP and JavaScript to a daily log file.
 * Use for analyzing slow queries, page loads, and bottlenecks.
 */

namespace Cma\Services;

use Cma\Services\SystemSettings;

class PerformanceLogger
{
    private static ?string $logDir = null;
    private static ?string $requestId = null;
    private static array $timers = [];
    private static array $marks = [];
    private static float $requestStart;
    private static ?bool $enabled = null;
    private static ?bool $cacheLogEnabled = null;

    /**
     * Check if logging is enabled
     * Uses SystemSettings which checks: system_settings.json > .env > default
     */
    public static function isEnabled(): bool
    {
        if (self::$enabled === null) {
            self::$enabled = SystemSettings::isPerfLogEnabled();
        }
        return self::$enabled;
    }

    /**
     * Check if cache logging is enabled
     * Uses SystemSettings which checks: system_settings.json > .env > default
     */
    public static function isCacheLogEnabled(): bool
    {
        if (self::$cacheLogEnabled === null) {
            self::$cacheLogEnabled = SystemSettings::isCacheLogEnabled();
        }
        return self::$cacheLogEnabled;
    }

    /**
     * Clear cached enabled states (call after system settings change)
     */
    public static function clearEnabledCache(): void
    {
        self::$enabled = null;
        self::$cacheLogEnabled = null;
    }

    /**
     * Initialize the logger
     */
    public static function init(): void
    {
        if (self::$requestId === null) {
            self::$requestId = substr(md5(uniqid('', true)), 0, 8);
            self::$requestStart = \App\Library\Request::server('REQUEST_TIME_FLOAT', microtime(true));
        }
    }

    /**
     * Get the log directory, creating it if needed
     */
    private static function getLogDir(): string
    {
        if (self::$logDir === null) {
            // Use site root data directory (outside cache and CMA, survives updates)
            $siteRoot = dirname(__DIR__, 3);
            self::$logDir = $siteRoot . '/data/logs/perf';

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
        return self::getLogDir() . '/perf_' . date('Y-m-d') . '.log';
    }

    /**
     * Start a timer
     */
    public static function startTimer(string $name): void
    {
        self::init();
        self::$timers[$name] = microtime(true);
    }

    /**
     * End a timer and log the duration
     */
    public static function endTimer(string $name, array $context = []): float
    {
        self::init();

        if (!isset(self::$timers[$name])) {
            return 0.0;
        }

        $duration = (microtime(true) - self::$timers[$name]) * 1000; // ms
        unset(self::$timers[$name]);

        self::log('timer', $name, $duration, $context);

        return $duration;
    }

    /**
     * Mark a point in time
     */
    public static function mark(string $name): void
    {
        self::init();
        self::$marks[$name] = microtime(true);
    }

    /**
     * Measure time between two marks
     */
    public static function measure(string $name, string $startMark, ?string $endMark = null): float
    {
        self::init();

        $start = self::$marks[$startMark] ?? self::$requestStart;
        $end = $endMark ? (self::$marks[$endMark] ?? microtime(true)) : microtime(true);

        $duration = ($end - $start) * 1000; // ms
        self::log('measure', $name, $duration, ['from' => $startMark, 'to' => $endMark ?? 'now']);

        return $duration;
    }

    /**
     * Log a SQL query with timing
     */
    public static function logQuery(string $sql, float $durationMs, array $context = []): void
    {
        self::init();

        // Truncate long SQL for logging
        $sqlTrunc = strlen($sql) > 500 ? substr($sql, 0, 500) . '...' : $sql;
        $sqlTrunc = preg_replace('/\s+/', ' ', trim($sqlTrunc));

        $context['sql'] = $sqlTrunc;
        $context['sql_length'] = strlen($sql);

        self::log('query', 'sql', $durationMs, $context);
    }

    /**
     * Log an API call
     */
    public static function logApi(string $action, float $durationMs, array $context = []): void
    {
        self::init();
        self::log('api', $action, $durationMs, $context);
    }

    /**
     * Log page render time
     */
    public static function logRender(string $page, float $durationMs, array $context = []): void
    {
        self::init();
        self::log('render', $page, $durationMs, $context);
    }

    /**
     * Log from JavaScript (called via API)
     */
    public static function logFromJs(array $entries): void
    {
        self::init();

        foreach ($entries as $entry) {
            $type = $entry['type'] ?? 'js';
            $name = $entry['name'] ?? 'unknown';
            $duration = (float)($entry['duration'] ?? 0);
            $context = $entry['context'] ?? [];
            $context['source'] = 'javascript';

            self::log($type, $name, $duration, $context);
        }
    }

    /**
     * Log a performance entry
     */
    public static function log(string $type, string $name, float $durationMs, array $context = []): void
    {
        // Skip if logging is disabled
        if (!self::isEnabled()) {
            return;
        }

        // Skip cache logs if cache logging is disabled
        if ($type === 'cache' && !self::isCacheLogEnabled()) {
            return;
        }

        self::init();

        $entry = [
            'ts' => date('H:i:s.') . sprintf('%03d', (microtime(true) - floor(microtime(true))) * 1000),
            'req' => self::$requestId,
            'type' => $type,
            'name' => $name,
            'ms' => round($durationMs, 2),
        ];

        // Add context fields
        if (!empty($context)) {
            $entry['ctx'] = $context;
        }

        // Add request info on first log
        static $firstLog = true;
        if ($firstLog) {
            $entry['url'] = \App\Library\Request::server('REQUEST_URI');
            $entry['method'] = \App\Library\Request::server('REQUEST_METHOD');
            $firstLog = false;
        }

        // Write to log file
        $line = json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
        @file_put_contents(self::getLogFile(), $line, FILE_APPEND | LOCK_EX);
    }

    /**
     * Log memory usage
     */
    public static function logMemory(string $name = 'memory'): void
    {
        self::init();

        $context = [
            'current_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            'peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
        ];

        self::log('memory', $name, 0, $context);
    }

    /**
     * Get request ID for correlating logs
     */
    public static function getRequestId(): string
    {
        self::init();
        return self::$requestId;
    }

    /**
     * Get elapsed time since request start
     */
    public static function getElapsed(): float
    {
        self::init();
        return (microtime(true) - self::$requestStart) * 1000;
    }

    /**
     * Cleanup old log files (keep last N days)
     */
    public static function cleanup(int $keepDays = 7): int
    {
        $logDir = self::getLogDir();
        $cutoff = strtotime("-{$keepDays} days");
        $deleted = 0;

        foreach (glob($logDir . '/perf_*.log') as $file) {
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
    public static function readLogs(?string $date = null, ?string $type = null, int $limit = 1000): array
    {
        $date = $date ?? date('Y-m-d');
        $file = self::getLogDir() . '/perf_' . $date . '.log';

        if (!file_exists($file)) {
            return [];
        }

        $entries = [];
        $handle = fopen($file, 'r');

        if ($handle) {
            while (($line = fgets($handle)) !== false && count($entries) < $limit) {
                $entry = json_decode(trim($line), true);
                if ($entry && ($type === null || ($entry['type'] ?? '') === $type)) {
                    $entries[] = $entry;
                }
            }
            fclose($handle);
        }

        return $entries;
    }

    /**
     * Get summary statistics from logs
     */
    public static function getSummary(?string $date = null): array
    {
        $entries = self::readLogs($date, null, 10000);

        $stats = [
            'total_entries' => count($entries),
            'by_type' => [],
            'slow_queries' => [],
            'slow_api' => [],
        ];

        foreach ($entries as $entry) {
            $type = $entry['type'] ?? 'unknown';
            $ms = $entry['ms'] ?? 0;

            if (!isset($stats['by_type'][$type])) {
                $stats['by_type'][$type] = ['count' => 0, 'total_ms' => 0, 'max_ms' => 0];
            }

            $stats['by_type'][$type]['count']++;
            $stats['by_type'][$type]['total_ms'] += $ms;
            $stats['by_type'][$type]['max_ms'] = max($stats['by_type'][$type]['max_ms'], $ms);

            // Track slow queries (>100ms)
            if ($type === 'query' && $ms > 100) {
                $stats['slow_queries'][] = [
                    'ms' => $ms,
                    'sql' => $entry['ctx']['sql'] ?? '',
                    'req' => $entry['req'] ?? '',
                ];
            }

            // Track slow API calls (>200ms) - includes both 'api' and 'fetch' types
            if (($type === 'api' || $type === 'fetch') && $ms > 200) {
                $apiEntry = [
                    'ms' => $ms,
                    'action' => $entry['name'] ?? '',
                    'req' => $entry['req'] ?? '',
                    'ts' => $entry['ts'] ?? '',
                ];
                // Include URL and method - check both top-level and context
                $url = $entry['url'] ?? $entry['ctx']['url'] ?? '';
                $method = $entry['method'] ?? $entry['ctx']['method'] ?? '';
                if (!empty($url)) {
                    $apiEntry['url'] = $url;
                }
                if (!empty($method)) {
                    $apiEntry['method'] = $method;
                }
                // Include any context data (excluding url/method which are already extracted)
                if (!empty($entry['ctx'])) {
                    $ctx = $entry['ctx'];
                    unset($ctx['url'], $ctx['method']);
                    if (!empty($ctx)) {
                        $apiEntry['ctx'] = $ctx;
                    }
                }
                $stats['slow_api'][] = $apiEntry;
            }
        }

        // Calculate averages
        foreach ($stats['by_type'] as $type => &$data) {
            $data['avg_ms'] = $data['count'] > 0 ? round($data['total_ms'] / $data['count'], 2) : 0;
        }

        // Sort slow queries/api by duration
        usort($stats['slow_queries'], fn($a, $b) => $b['ms'] <=> $a['ms']);
        usort($stats['slow_api'], fn($a, $b) => $b['ms'] <=> $a['ms']);

        // Keep top 20
        $stats['slow_queries'] = array_slice($stats['slow_queries'], 0, 20);
        $stats['slow_api'] = array_slice($stats['slow_api'], 0, 20);

        return $stats;
    }
}
