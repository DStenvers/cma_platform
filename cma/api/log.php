<?php
/**
 * Unified Log API
 *
 * Handles both performance logging and debug logging.
 * Writes to server log files and PerformanceLogger.
 *
 * POST /api/log.php - Submit log entries (performance or debug)
 * POST /api/log.php?type=debug - Submit debug log entries
 * GET /api/log.php?action=summary - Get performance summary statistics
 * GET /api/log.php?action=read&date=YYYY-MM-DD - Read performance log entries
 * GET /api/log.php?action=debug&date=YYYY-MM-DD - Read debug log entries
 * GET /api/log.php?action=cleanup - Cleanup old logs
 *
 * Note: Debug logs are written to files only. For JavaScript errors captured
 * by the error handler, see tblCMAJavascriptErrors and dashboard_stats.php.
 */

use App\Library\Arr;
use Cma\Services\PerformanceLogger;
use Cma\Services\Logger;
use App\Library\Application;
use App\Library\Request;

// Minimal bootstrap - no session needed for logging
require_once dirname(__DIR__) . '/../_bootstrap.php';
require_once dirname(__DIR__) . '/classes/Services/PerformanceLogger.php';

header('Content-Type: application/json');

// CORS for local development
if (Request::server('HTTP_ORIGIN') !== '') {
    header('Access-Control-Allow-Origin: ' . Request::server('HTTP_ORIGIN'));
    header('Access-Control-Allow-Credentials: true');
}

if (Request::server('REQUEST_METHOD') === 'OPTIONS') {
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    exit(0);
}

// Log directories
$logsDir = dirname(__DIR__, 2) . '/data/logs';
if (!is_dir($logsDir)) {
    mkdir($logsDir, 0755, true);
}

/**
 * Write debug log entry to file
 */
function writeDebugLog(string $source, $info, ?string $level = 'debug', ?string $requestId = null): bool
{
    global $logsDir;

    $date = date('Y-m-d');
    $logFile = $logsDir . "/debug_{$date}.log";

    $timestamp = date('Y-m-d H:i:s.') . substr(microtime(), 2, 3);
    $infoStr = Arr::isArray($info) || is_object($info) ? json_encode($info, JSON_UNESCAPED_UNICODE) : $info;
    $logEntry = sprintf("[%s] [%s] %s | %s\n", $timestamp, strtoupper($level), $source, $infoStr);

    return file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX) !== false;
}

/**
 * Write performance log entry to file
 */
function writePerfLog(array $entries): bool
{
    global $logsDir;

    $date = date('Y-m-d');
    $logFile = $logsDir . "/perf_{$date}.log";

    $output = '';
    foreach ($entries as $entry) {
        $timestamp = $entry['timestamp'] ?? date('Y-m-d H:i:s');
        $type = $entry['type'] ?? 'unknown';
        $data = $entry['data'] ?? $entry;
        $dataStr = Arr::isArray($data) || is_object($data) ? json_encode($data, JSON_UNESCAPED_UNICODE) : $data;
        $output .= sprintf("[%s] [%s] %s\n", $timestamp, strtoupper($type), $dataStr);
    }

    return file_put_contents($logFile, $output, FILE_APPEND | LOCK_EX) !== false;
}

/**
 * Read log file entries efficiently using streaming
 * Uses SplFileObject to avoid loading entire file into memory
 */
function readLogFile(string $type, string $date, int $limit = 1000): array
{
    global $logsDir;

    $logFile = $logsDir . "/{$type}_{$date}.log";
    if (!file_exists($logFile) || !is_readable($logFile)) {
        return [];
    }

    try {
        $file = new SplFileObject($logFile, 'r');
        $file->seek(PHP_INT_MAX);
        $totalLines = $file->key();

        if ($totalLines === 0) {
            return [];
        }

        // Calculate start position to only read last N lines
        $startLine = max(0, $totalLines - $limit);
        $file->seek($startLine);

        $lines = [];
        while (!$file->eof()) {
            $line = trim($file->fgets());
            if ($line !== '') {
                $lines[] = $line;
            }
        }

        // Reverse to show newest first
        $lines = array_reverse($lines);
    } catch (Exception $e) {
        Logger::error('Failed to read log file', ['file' => $logFile, 'error' => $e->getMessage()]);
        return [];
    }

    return array_map(function($line) {
        // Parse log line format: [timestamp] [level] source | data
        if (preg_match('/^\[([^\]]+)\] \[([^\]]+)\] (.+)$/', $line, $m)) {
            $parts = explode(' | ', $m[3], 2);
            return [
                'timestamp' => $m[1],
                'level' => $m[2],
                'source' => $parts[0],
                'data' => isset($parts[1]) ? $parts[1] : null,
            ];
        }
        return ['raw' => $line];
    }, $lines);
}

/**
 * Cleanup old log files
 */
function cleanupLogs(int $daysToKeep = 7): int
{
    global $logsDir;

    $cutoff = strtotime("-{$daysToKeep} days");
    $deleted = 0;

    foreach (glob($logsDir . '/*.log') as $file) {
        if (filemtime($file) < $cutoff) {
            if (unlink($file)) {
                $deleted++;
            }
        }
    }

    return $deleted;
}

try {
    $action = Request::query('action', '');
    $type = Request::query('type', '');

    if (Request::server('REQUEST_METHOD') === 'POST') {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);

        if (!$data) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid JSON body']);
            exit;
        }

        // Debug logging - handle LibLog batch entries
        if ($type === 'debug' || isset($data['source'])) {
            $source = $data['source'] ?? 'unknown';

            // Check if debug logging is enabled in system settings
            // Errors are always logged, other levels respect the setting
            $debugEnabled = \Cma\Services\SystemSettings::isDebugLogEnabled();

            // Handle batched entries from LibLog
            if (isset($data['entries']) && Arr::isArray($data['entries'])) {
                $logged = 0;
                foreach ($data['entries'] as $entry) {
                    $entryLevel = $entry['level'] ?? 'debug';

                    // Always log errors, skip others if debug logging disabled
                    if (!$debugEnabled && $entryLevel !== 'error') {
                        continue;
                    }

                    $entrySource = $entry['source'] ?? $source;
                    $entryData = $entry['data'] ?? $entry;
                    $requestId = $entry['requestId'] ?? null;

                    writeDebugLog($entrySource, $entryData, $entryLevel, $requestId);
                    $logged++;
                }

                echo json_encode([
                    'success' => true,
                    'type' => 'debug',
                    'source' => $source,
                    'logged' => $logged,
                    'debugEnabled' => $debugEnabled,
                ]);
                exit;
            }

            // Handle single entry
            $info = $data['info'] ?? $data['data'] ?? $data;
            $level = $data['level'] ?? 'debug';
            $requestId = $data['requestId'] ?? null;

            // Always log errors, skip others if debug logging disabled
            if ($debugEnabled || $level === 'error') {
                writeDebugLog($source, $info, $level, $requestId);
            }

            echo json_encode([
                'success' => true,
                'type' => 'debug',
                'source' => $source,
                'debugEnabled' => $debugEnabled,
            ]);
            exit;
        }

        // Performance logging
        if (isset($data['entries']) && Arr::isArray($data['entries'])) {
            // Write to file
            writePerfLog($data['entries']);

            // Also send to PerformanceLogger for in-memory analysis
            PerformanceLogger::logFromJs($data['entries']);

            echo json_encode([
                'success' => true,
                'type' => 'perf',
                'logged' => count($data['entries']),
                'requestId' => PerformanceLogger::getRequestId(),
            ]);
            exit;
        }

        // Single entry
        if (isset($data['type'])) {
            writePerfLog([$data]);

            echo json_encode([
                'success' => true,
                'type' => 'perf',
                'logged' => 1,
            ]);
            exit;
        }

        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid request body']);

    } elseif ($action === 'summary') {
        // Get performance summary statistics
        $date = Request::query('date', date('Y-m-d'));
        $summary = PerformanceLogger::getSummary($date);

        echo json_encode([
            'success' => true,
            'date' => $date,
            'summary' => $summary,
        ]);

    } elseif ($action === 'read') {
        // Read performance log entries (from file)
        $date = Request::query('date', date('Y-m-d'));
        $limit = min((int)Request::query('limit', 1000), 10000);

        $entries = readLogFile('perf', $date, $limit);

        echo json_encode([
            'success' => true,
            'date' => $date,
            'type' => 'perf',
            'count' => count($entries),
            'entries' => $entries,
        ]);

    } elseif ($action === 'debug') {
        // Read debug log entries from file
        $date = Request::query('date', date('Y-m-d'));
        $limit = min((int)Request::query('limit', 1000), 10000);

        $entries = readLogFile('debug', $date, $limit);

        echo json_encode([
            'success' => true,
            'date' => $date,
            'type' => 'debug',
            'count' => count($entries),
            'entries' => $entries,
        ]);

    } elseif ($action === 'cleanup') {
        // Cleanup old logs
        $days = max(1, min((int)Request::query('days', 7), 30));

        // Cleanup file-based logs
        $deletedFiles = cleanupLogs($days);

        // Cleanup PerformanceLogger logs
        $deletedPerf = PerformanceLogger::cleanup($days);

        echo json_encode([
            'success' => true,
            'deletedFiles' => $deletedFiles,
            'deletedPerfEntries' => $deletedPerf,
        ]);

    } else {
        // Return request ID for correlation
        echo json_encode([
            'success' => true,
            'requestId' => PerformanceLogger::getRequestId(),
            'elapsed' => PerformanceLogger::getElapsed(),
            'logsDir' => is_dir($logsDir) ? 'ok' : 'missing',
        ]);
    }

} catch (Exception $e) {
    Logger::error('API log error', [
        'message' => $e->getMessage(),
        'action' => $action ?? null,
        'type' => $type ?? null,
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
    ]);
}
