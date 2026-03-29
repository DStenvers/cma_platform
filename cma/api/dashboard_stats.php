<?php
/**
 * Dashboard Statistics API
 *
 * Returns health and cache statistics for the dashboard.
 * Developer/Admin access only.
 */

use App\Library\Cache;
use App\Library\Database;
use App\Library\Request;
use App\Library\Server;
use Cma\SecurityHelper;
use Cma\Services\Logger;
use Cma\Services\MigrationService;
use Cma\Services\PerformanceLogger;

require_once __DIR__ . '/../bootstrap.inc';

header('Content-Type: application/json');

// Check access
if (!SecurityHelper::isAdmin() && !SecurityHelper::isDeveloper()) {
    http_response_code(403);
    echo json_encode(['error' => 'Geen toegang']);
    exit;
}

$action = Request::query('action', 'all');
$response = [];

try {
    switch ($action) {
        case 'errors':
            $response = getErrorStats();
            break;

        case 'cache':
            $response = getCacheStats();
            break;

        case 'performance':
            $response = getPerformanceStats();
            break;

        case 'activity':
            $response = getUserActivityStats();
            break;

        case 'forms':
            $response = getMostUsedFormsStats();
            break;

        case 'recent':
            $response = getRecentActivityStats();
            break;

        case 'logins':
            $response = getFailedLoginStats();
            break;

        case 'template_cache':
            $response = getTemplateCacheStats();
            break;

        case 'jslog':
            $response = getJSLogStats();
            break;

        case 'log_settings':
            $response = getLogSettings();
            break;

        case 'all':
        default:
            $response = [
                'errors' => getErrorStats(),
                'cache' => getCacheStats(),
                'performance' => getPerformanceStats(),
                'activity' => getUserActivityStats(),
                'forms' => getMostUsedFormsStats(),
                'recent' => getRecentActivityStats(),
                'logins' => getFailedLoginStats(),
                'template_cache' => getTemplateCacheStats(),
                'jslog' => getJSLogStats(),
                'log_settings' => getLogSettings(),
            ];
            break;
    }

    echo json_encode($response, JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    Logger::error('API dashboard_stats error', [
        'message' => $e->getMessage(),
        'action' => $action ?? null,
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

/**
 * Get PHP error log statistics - daily breakdown for last 7 days
 */
function getErrorStats(): array
{
    $errorLog = ini_get('error_log');
    $stats = [
        'path' => $errorLog,
        'exists' => false,
        'today' => 0,
        'week' => 0,
        'daily' => [],
        'by_type' => [
            'error' => 0,
            'warning' => 0,
            'notice' => 0,
            'other' => 0,
        ],
        'by_type_week' => [
            'error' => 0,
            'warning' => 0,
            'notice' => 0,
            'other' => 0,
        ],
        'last_errors' => [],
    ];

    if (empty($errorLog) || !file_exists($errorLog) || !is_readable($errorLog)) {
        return $stats;
    }

    $stats['exists'] = true;
    $stats['size'] = @filesize($errorLog) ?: 0;

    // Read last portion of the log file (last 500KB for 7 days of data)
    $maxRead = 500 * 1024;
    $fileSize = $stats['size'];
    $startPos = max(0, $fileSize - $maxRead);

    try {
        $handle = @fopen($errorLog, 'r');
        if (!$handle) {
            return $stats;
        }
    } catch (Exception $e) {
        return $stats;
    }

    @fseek($handle, $startPos);

    // Skip partial line if we didn't start at the beginning
    if ($startPos > 0) {
        @fgets($handle);
    }

    $today = date('d-M-Y');
    $errors = [];

    // Initialize daily counts for last 7 days - tracking by type
    $dailyByType = [];
    $dateLabels = [];
    for ($d = 6; $d >= 0; $d--) {
        $date = date('d-M-Y', strtotime("-$d days"));
        $dailyByType[$date] = ['error' => 0, 'warning' => 0, 'notice' => 0, 'other' => 0];
        $dateLabels[] = $date;
    }

    while (($line = @fgets($handle)) !== false) {
        $line = trim($line);
        if (empty($line)) continue;

        // Parse PHP error log format: [DD-Mon-YYYY HH:MM:SS ...] Type: Message
        if (preg_match('/^\[(\d{2}-\w{3}-\d{4})\s+(\d{2}):\d{2}:\d{2}/', $line, $matches)) {
            $logDate = $matches[1];

            // Only count if within last 7 days
            if (isset($dailyByType[$logDate])) {
                // Count today's errors separately
                if ($logDate === $today) {
                    $stats['today']++;
                    // Also count in by_type for today
                    if (stripos($line, 'Fatal error') !== false || stripos($line, 'PHP Fatal') !== false) {
                        $stats['by_type']['error']++;
                    } elseif (stripos($line, 'Warning') !== false || stripos($line, 'PHP Warning') !== false) {
                        $stats['by_type']['warning']++;
                    } elseif (stripos($line, 'Notice') !== false || stripos($line, 'PHP Notice') !== false) {
                        $stats['by_type']['notice']++;
                    } else {
                        $stats['by_type']['other']++;
                    }
                }

                // Categorize by type for daily tracking AND week totals
                $stats['week']++;
                if (stripos($line, 'Fatal error') !== false || stripos($line, 'PHP Fatal') !== false) {
                    $dailyByType[$logDate]['error']++;
                    $stats['by_type_week']['error']++;
                } elseif (stripos($line, 'Warning') !== false || stripos($line, 'PHP Warning') !== false) {
                    $dailyByType[$logDate]['warning']++;
                    $stats['by_type_week']['warning']++;
                } elseif (stripos($line, 'Notice') !== false || stripos($line, 'PHP Notice') !== false) {
                    $dailyByType[$logDate]['notice']++;
                    $stats['by_type_week']['notice']++;
                } else {
                    $dailyByType[$logDate]['other']++;
                    $stats['by_type_week']['other']++;
                }

                // Keep last 5 errors (from today)
                if ($logDate === $today && count($errors) < 5) {
                    $errors[] = [
                        'time' => substr($line, 1, 20),
                        'message' => Server::htmlEncode(substr($line, 0, 200)),
                    ];
                }
            }
        }
    }

    fclose($handle);

    // Reverse to show newest first
    $stats['last_errors'] = array_reverse($errors);

    // Build daily display array (oldest to newest)
    $dailyDisplay = [];
    foreach ($dateLabels as $date) {
        $types = $dailyByType[$date];
        $total = $types['error'] + $types['warning'] + $types['notice'] + $types['other'];
        // Format as short day name
        $dayLabel = date('D', strtotime($date)); // Mon, Tue, etc.
        $dailyDisplay[] = [
            'date' => $date,
            'day' => $dayLabel,
            'count' => $total,
            'error' => $types['error'],
            'warning' => $types['warning'],
            'notice' => $types['notice'],
            'other' => $types['other'],
        ];
    }
    $stats['daily'] = $dailyDisplay;

    return $stats;
}

/**
 * Get cache statistics
 */
function getCacheStats(): array
{
    $stats = Cache::getStats();
    $status = Cache::getStatus();

    // Get recent cache misses from log
    $recentMisses = getRecentCacheMisses();

    return [
        'hits' => $stats['hits'],
        'misses' => $stats['misses'],
        'total' => $stats['total'],
        'hit_ratio' => $stats['hit_ratio'],
        'backend' => $status['backend'],
        'enabled' => $status['enabled'],
        'opcache' => $status['opcache'] ?? null,
        'apcu' => $status['apcu'] ?? null,
        'recent_misses' => $recentMisses,
    ];
}

/**
 * Get recent cache misses from cache.log
 */
function getRecentCacheMisses(int $limit = 10): array
{
    $siteRoot = dirname(__DIR__, 2);
    $cacheLogFile = $siteRoot . '/cache/cache.log';

    if (!file_exists($cacheLogFile) || !is_readable($cacheLogFile)) {
        return [];
    }

    $misses = [];
    $seenKeys = [];

    // Read last 200 lines of log file
    $lines = [];
    try {
        $file = new SplFileObject($cacheLogFile, 'r');
        $file->seek(PHP_INT_MAX);
        $totalLines = $file->key();
        $startLine = max(0, $totalLines - 200);
        $file->seek($startLine);

        while (!$file->eof()) {
            $line = $file->fgets();
            if (strpos($line, 'CACHE MISS') !== false) {
                $lines[] = trim($line);
            }
        }
    } catch (Exception $e) {
        // File read error (permission denied, etc.) - return empty
        return [];
    }

    // Process from newest to oldest
    $lines = array_reverse($lines);

    foreach ($lines as $line) {
        // Parse: [2025-12-27 14:13:01] CACHE MISS [apcu] key=CMA_form_template_json_opleidingen_40 (hits=0, misses=1)
        if (preg_match('/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] CACHE MISS \[(\w+)\] key=(\S+)/', $line, $matches)) {
            $key = $matches[3];

            // Skip duplicates (only show unique keys)
            if (isset($seenKeys[$key])) {
                continue;
            }
            $seenKeys[$key] = true;

            $misses[] = [
                'time' => $matches[1],
                'backend' => $matches[2],
                'key' => $key,
            ];

            if (count($misses) >= $limit) {
                break;
            }
        }
    }

    return $misses;
}

/**
 * Get performance statistics from PerformanceLogger
 */
function getPerformanceStats(): array
{
    $summary = PerformanceLogger::getSummary();

    return [
        'total_entries' => $summary['total_entries'],
        'by_type' => $summary['by_type'],
        'slow_queries' => array_slice($summary['slow_queries'], 0, 5),
        'slow_api' => array_slice($summary['slow_api'], 0, 5),
    ];
}

/**
 * Get user activity statistics (last 7 days)
 * From tblCMAMonitoring
 */
function getUserActivityStats(): array
{
    $stats = [
        'daily' => [],
        'by_action' => [],
        'total_actions' => 0,
        'unique_users' => 0,
    ];

    try {
        $conn = Database::getConnection('data');

        // Get daily activity for last 7 days
        $sql = "SELECT Format(datestamp, 'yyyy-mm-dd') AS day, Count(*) AS cnt
                FROM tblCMAMonitoring
                WHERE datestamp >= DateAdd('d', -7, Now())
                GROUP BY Format(datestamp, 'yyyy-mm-dd')
                ORDER BY Format(datestamp, 'yyyy-mm-dd')";

        $rs = Database::openRS($sql, $conn);
        if ($rs) {
            while (!$rs->EOF) {
                $stats['daily'][] = [
                    'date' => $rs->fields['day'],
                    'count' => (int)$rs->fields['cnt'],
                ];
                $stats['total_actions'] += (int)$rs->fields['cnt'];
                $rs->MoveNext();
            }
        }
        // Skip $rs->Close() - ODBC recordsets don't support closeCursor()

        // Get breakdown by action type
        $sql = "SELECT Actie, Count(*) AS cnt
                FROM tblCMAMonitoring
                WHERE datestamp >= DateAdd('d', -7, Now())
                GROUP BY Actie
                ORDER BY Count(*) DESC";

        $rs = Database::openRS($sql, $conn);
        if ($rs) {
            while (!$rs->EOF) {
                $action = $rs->fields['Actie'] ?: 'Onbekend';
                $stats['by_action'][$action] = (int)$rs->fields['cnt'];
                $rs->MoveNext();
            }
        }
        // Skip $rs->Close() - ODBC recordsets don't support closeCursor()

        // Count unique users
        $sql = "SELECT Count(*) AS cnt FROM (
                    SELECT DISTINCT Username FROM tblCMAMonitoring
                    WHERE datestamp >= DateAdd('d', -7, Now())
                )";

        $rs = Database::openRS($sql, $conn);
        if ($rs && !$rs->EOF) {
            $stats['unique_users'] = (int)$rs->fields['cnt'];
        }
        // Skip $rs->Close() - ODBC recordsets don't support closeCursor()

    } catch (Exception $e) {
        $stats['error'] = $e->getMessage();
    }

    return $stats;
}

/**
 * Get most used forms statistics (last 7 days)
 */
function getMostUsedFormsStats(): array
{
    $stats = [
        'forms' => [],
        'total' => 0,
    ];

    // Build form definitions cache and title-to-name mapping (single pass, reused across calls)
    static $formDefCache = null;
    static $titleToNameMap = null;
    if ($formDefCache === null) {
        $formDefCache = [];
        $titleToNameMap = [];
        $formDirs = [
            __DIR__ . '/../../assets/forms/',
            __DIR__ . '/../assets/forms/definitions/',
        ];
        foreach ($formDirs as $definitionsDir) {
            if (!is_dir($definitionsDir)) continue;
            $files = glob($definitionsDir . '*.json');
            foreach ($files as $file) {
                $formName = basename($file, '.json');
                if (str_starts_with($formName, '_')) continue;
                $formJson = @file_get_contents($file);
                $formDef = $formJson ? @json_decode($formJson, true) : null;
                if ($formDef) {
                    // Cache the full definition by name
                    $formDefCache[$formName] = $formDef;
                    if (!empty($formDef['title'])) {
                        // Map title (lowercased) to form name
                        $titleToNameMap[strtolower($formDef['title'])] = $formName;
                    }
                }
            }
        }
    }

    try {
        $conn = Database::getConnection('data');

        // Get top 10 most used forms - prefer Form (handle) over Formname (title)
        $sql = "SELECT TOP 10 IIf(Form Is Null Or Form='', Formname, Form) AS FormHandle, Count(*) AS cnt
                FROM tblCMAMonitoring
                WHERE datestamp >= DateAdd('d', -7, Now())
                  AND (Form Is Not Null Or Formname Is Not Null)
                  AND IIf(Form Is Null Or Form='', Formname, Form) <> ''
                GROUP BY IIf(Form Is Null Or Form='', Formname, Form)
                ORDER BY Count(*) DESC";

        $rs = Database::openRS($sql, $conn);
        if ($rs) {
            while (!$rs->EOF) {
                $formHandle = $rs->fields['FormHandle'];
                $count = (int)$rs->fields['cnt'];

                // Try to find form definition from cache by handle
                $formDef = $formDefCache[$formHandle] ?? null;

                if ($formDef && !empty($formDef['name'])) {
                    // Found by name - use the definition's name and title
                    $formName = $formDef['name'];
                    $title = $formDef['title'] ?? $formName;
                } else {
                    // Not found by handle - maybe it's stored as a title (legacy)
                    // Try reverse lookup from title to name
                    $formNameLookup = $titleToNameMap[strtolower($formHandle)] ?? null;
                    if ($formNameLookup) {
                        $formName = $formNameLookup;
                        $formDef = $formDefCache[$formName] ?? null;
                        $title = $formDef['title'] ?? $formHandle;
                    } else {
                        // Fallback: use what we have
                        $formName = $formHandle;
                        $title = $formHandle;
                    }
                }

                $stats['forms'][] = [
                    'name' => $formName,
                    'title' => $title,
                    'count' => $count,
                ];
                $stats['total'] += $count;
                $rs->MoveNext();
            }
        }
        // Skip $rs->Close() - ODBC recordsets don't support closeCursor()

    } catch (Exception $e) {
        $stats['error'] = $e->getMessage();
    }

    return $stats;
}

/**
 * Get recent system activity (last 10 entries)
 */
function getRecentActivityStats(): array
{
    $stats = [
        'entries' => [],
    ];

    try {
        $conn = Database::getConnection('data');

        // Use Form (handle) preferentially, fallback to Formname for legacy records
        $sql = "SELECT TOP 10 ID, Format(datestamp, 'dd-mm hh:nn') AS timestamp,
                       Username, Actie, IIf(Form Is Null Or Form='', Formname, Form) AS FormHandle, RecordID
                FROM tblCMAMonitoring
                ORDER BY datestamp DESC";

        $rs = Database::openRS($sql, $conn);
        if ($rs) {
            while (!$rs->EOF) {
                $stats['entries'][] = [
                    'id' => (int)$rs->fields['ID'],
                    'time' => $rs->fields['timestamp'],
                    'user' => $rs->fields['Username'] ?: 'Onbekend',
                    'action' => $rs->fields['Actie'] ?: '-',
                    'form' => $rs->fields['FormHandle'] ?: '-',
                    'record' => $rs->fields['RecordID'] ? (int)$rs->fields['RecordID'] : null,
                ];
                $rs->MoveNext();
            }
        }
        // Skip $rs->Close() - ODBC recordsets don't support closeCursor()

    } catch (Exception $e) {
        $stats['error'] = $e->getMessage();
    }

    return $stats;
}

/**
 * Get failed login attempts statistics
 * Tracks failed logins from tblCMAMonitoring where Actie = 'login_failed' or similar
 */
function getFailedLoginStats(): array
{
    $stats = [
        'today' => 0,
        'week' => 0,
        'by_user' => [],
        'recent' => [],
    ];

    try {
        $conn = Database::getConnection('data');

        // Check if LogLevel column exists (added by migration 6.3.0)
        // Use cached helper - result is cached for request lifetime
        $hasLogLevel = MigrationService::columnExists('tblCMAMonitoring', 'LogLevel', 'data');

        // Error filter - use LogLevel column if available (10x faster)
        $errorFilter = $hasLogLevel
            ? "LogLevel = 'error'"
            : "(Actie LIKE '%fail%' OR Actie LIKE '%fout%' OR Actie LIKE '%error%')";

        // Count failed logins today
        $sql = "SELECT Count(*) AS cnt FROM tblCMAMonitoring
                WHERE datestamp >= DateValue(Now())
                  AND $errorFilter";

        $rs = Database::openRS($sql, $conn);
        if ($rs && !$rs->EOF) {
            $stats['today'] = (int)$rs->fields['cnt'];
        }
        // Skip $rs->Close() - ODBC recordsets don't support closeCursor()

        // Count failed logins this week
        $sql = "SELECT Count(*) AS cnt FROM tblCMAMonitoring
                WHERE datestamp >= DateAdd('d', -7, Now())
                  AND $errorFilter";

        $rs = Database::openRS($sql, $conn);
        if ($rs && !$rs->EOF) {
            $stats['week'] = (int)$rs->fields['cnt'];
        }
        // Skip $rs->Close() - ODBC recordsets don't support closeCursor()

        // Get failed attempts by user (top 5)
        $sql = "SELECT TOP 5 Username, Count(*) AS cnt
                FROM tblCMAMonitoring
                WHERE datestamp >= DateAdd('d', -7, Now())
                  AND $errorFilter
                  AND Username Is Not Null AND Username <> ''
                GROUP BY Username
                ORDER BY Count(*) DESC";

        $rs = Database::openRS($sql, $conn);
        if ($rs) {
            while (!$rs->EOF) {
                $stats['by_user'][] = [
                    'user' => $rs->fields['Username'],
                    'count' => (int)$rs->fields['cnt'],
                ];
                $rs->MoveNext();
            }
        }
        // Skip $rs->Close() - ODBC recordsets don't support closeCursor()

        // Get recent failed attempts
        $sql = "SELECT TOP 5 Format(datestamp, 'dd-mm hh:nn') AS timestamp,
                       Username, Actie, Notificatie
                FROM tblCMAMonitoring
                WHERE $errorFilter
                ORDER BY datestamp DESC";

        $rs = Database::openRS($sql, $conn);
        if ($rs) {
            while (!$rs->EOF) {
                $stats['recent'][] = [
                    'time' => $rs->fields['timestamp'],
                    'user' => $rs->fields['Username'] ?: 'Onbekend',
                    'action' => $rs->fields['Actie'],
                    'message' => substr($rs->fields['Notificatie'] ?? '', 0, 100),
                ];
                $rs->MoveNext();
            }
        }
        // Skip $rs->Close() - ODBC recordsets don't support closeCursor()

    } catch (Exception $e) {
        $stats['error'] = $e->getMessage();
    }

    return $stats;
}

/**
 * Get form template cache statistics
 * Shows which forms are cached in APCu/file cache
 */
function getTemplateCacheStats(): array
{
    $stats = [
        'total_forms' => 0,
        'cached_forms' => 0,
        'cache_ratio' => 0,
        'cached_list' => [],
        'uncached_list' => [],
        'cache_size' => 0,
    ];

    try {
        // Check file-based cache in assets/forms/
        $cacheDir = __DIR__ . '/../assets/forms/';
        $cachedFiles = glob($cacheDir . 'form_*.html');
        $stats['cached_forms'] = count($cachedFiles);

        // Calculate total cache size
        foreach ($cachedFiles as $file) {
            $stats['cache_size'] += filesize($file);
        }

        // Get list of all JSON form definitions
        $definitionsDir = __DIR__ . '/../assets/forms/definitions/';
        $jsonForms = glob($definitionsDir . '*.json');
        $stats['total_forms'] = count($jsonForms);

        // Build lists of cached/uncached forms
        $cachedFormNames = [];
        foreach ($cachedFiles as $file) {
            // Extract form name from filename: form_{name}_{level}.html
            $basename = basename($file, '.html');
            if (preg_match('/^form_(.+)_\d+$/', $basename, $m)) {
                $cachedFormNames[$m[1]] = true;
                if (count($stats['cached_list']) < 10) {
                    $stats['cached_list'][] = [
                        'name' => $m[1],
                        'size' => filesize($file),
                        'modified' => date('d-m H:i', filemtime($file)),
                    ];
                }
            }
        }

        // Find uncached forms
        foreach ($jsonForms as $file) {
            $formName = basename($file, '.json');
            if (!isset($cachedFormNames[$formName])) {
                if (count($stats['uncached_list']) < 5) {
                    $stats['uncached_list'][] = $formName;
                }
            }
        }

        // Calculate ratio
        if ($stats['total_forms'] > 0) {
            // Note: cached_forms might be higher due to multiple access levels per form
            $uniqueCached = count($cachedFormNames);
            $stats['cache_ratio'] = round(($uniqueCached / $stats['total_forms']) * 100);
        }

        // Check APCu if available
        if (function_exists('apcu_cache_info')) {
            $apcuInfo = @apcu_cache_info();
            if ($apcuInfo) {
                $formCacheCount = 0;
                foreach ($apcuInfo['cache_list'] ?? [] as $entry) {
                    if (strpos($entry['info'] ?? '', 'form_template_') === 0) {
                        $formCacheCount++;
                    }
                }
                $stats['apcu_cached'] = $formCacheCount;
            }
        }

    } catch (Exception $e) {
        $stats['error'] = $e->getMessage();
    }

    return $stats;
}

/**
 * Get current log settings status
 * Shows which logs are enabled and their file paths
 */
function getLogSettings(): array
{
    $settings = [
        'perf_log' => [
            'label' => 'Performance logging',
            'enabled' => false,
            'path' => '',
            'exists' => false,
        ],
        'cache_log' => [
            'label' => 'Cache logging',
            'enabled' => false,
            'path' => '',
            'exists' => false,
        ],
        'debug_log' => [
            'label' => 'Debug logging',
            'enabled' => false,
            'path' => '',
            'exists' => false,
        ],
        'php_error_log' => [
            'label' => 'PHP error log',
            'enabled' => true,
            'path' => ini_get('error_log'),
            'exists' => false,
        ],
    ];

    // Get system settings
    $sysSettings = \Cma\Services\SystemSettings::getAll();

    // Performance log
    $perfEnabled = $sysSettings['perf_log_enabled'] ?? false;
    $settings['perf_log']['enabled'] = $perfEnabled;
    if ($perfEnabled) {
        $perfLogDir = dirname(__DIR__, 2) . '/cache/perf_logs';
        $perfLogFile = $perfLogDir . '/perf_' . date('Y-m-d') . '.log';
        $settings['perf_log']['path'] = $perfLogFile;
        $settings['perf_log']['exists'] = file_exists($perfLogFile);
    }

    // Cache log
    $cacheEnabled = $sysSettings['cache_log_enabled'] ?? false;
    $settings['cache_log']['enabled'] = $cacheEnabled;
    if ($cacheEnabled) {
        $cacheLogFile = dirname(__DIR__, 2) . '/cache/cache.log';
        $settings['cache_log']['path'] = $cacheLogFile;
        $settings['cache_log']['exists'] = file_exists($cacheLogFile);
    }

    // Debug log
    $debugEnabled = $sysSettings['debug_log_enabled'] ?? false;
    $settings['debug_log']['enabled'] = $debugEnabled;
    if ($debugEnabled) {
        $debugLogFile = dirname(__DIR__, 2) . '/cache/debug.log';
        $settings['debug_log']['path'] = $debugLogFile;
        $settings['debug_log']['exists'] = file_exists($debugLogFile);
    }

    // PHP error log
    $phpErrorLog = ini_get('error_log');
    $settings['php_error_log']['path'] = $phpErrorLog;
    $settings['php_error_log']['exists'] = !empty($phpErrorLog) && file_exists($phpErrorLog);

    return $settings;
}

/**
 * Get JavaScript error statistics from tblCMAJavascriptErrors
 * Shows error counts and recent errors captured by error-handler.js
 */
function getJSLogStats(): array
{
    $stats = [
        'today' => ['error' => 0, 'warning' => 0, 'info' => 0],
        'week' => ['error' => 0, 'warning' => 0, 'info' => 0],
        'daily' => [],
        'last_errors' => [],
    ];

    try {
        // Use data database (cma) where tblCMAJavascriptErrors is stored
        $conn = Database::getConnection('data');

        // Check if table exists first
        try {
            $sql = "SELECT TOP 1 ID FROM tblCMAJavascriptErrors";
            @$conn->query($sql);
        } catch (Exception $e) {
            // Table doesn't exist yet - return empty stats
            return $stats;
        }

        // Count today's errors (all JS errors are 'error' level)
        $sql = "SELECT Count(*) AS cnt
                FROM tblCMAJavascriptErrors
                WHERE datestamp >= DateValue(Now())";

        $rs = Database::openRS($sql, $conn);
        if ($rs && !$rs->EOF) {
            $stats['today']['error'] = (int)$rs->fields['cnt'];
        }

        // Count this week's errors
        $sql = "SELECT Count(*) AS cnt
                FROM tblCMAJavascriptErrors
                WHERE datestamp >= DateAdd('d', -7, Now())";

        $rs = Database::openRS($sql, $conn);
        if ($rs && !$rs->EOF) {
            $stats['week']['error'] = (int)$rs->fields['cnt'];
        }

        // Get daily breakdown for last 7 days
        $sql = "SELECT Format(datestamp, 'yyyy-mm-dd') AS day, Count(*) AS cnt
                FROM tblCMAJavascriptErrors
                WHERE datestamp >= DateAdd('d', -7, Now())
                GROUP BY Format(datestamp, 'yyyy-mm-dd')
                ORDER BY Format(datestamp, 'yyyy-mm-dd')";

        $rs = Database::openRS($sql, $conn);
        if ($rs) {
            while (!$rs->EOF) {
                $stats['daily'][] = [
                    'date' => $rs->fields['day'],
                    'count' => (int)$rs->fields['cnt'],
                ];
                $rs->MoveNext();
            }
        }

        // Get last 5 errors
        $sql = "SELECT TOP 5 ID, error_url, error_message,
                       Format(datestamp, 'dd-mm hh:nn') AS CreatedAtStr
                FROM tblCMAJavascriptErrors
                ORDER BY datestamp DESC";

        $rs = Database::openRS($sql, $conn);
        if ($rs) {
            while (!$rs->EOF) {
                $stats['last_errors'][] = [
                    'source' => $rs->fields['error_url'] ?? '',
                    'message' => Server::htmlEncode(substr($rs->fields['error_message'] ?? '', 0, 200)),
                    'time' => $rs->fields['CreatedAtStr'],
                ];
                $rs->MoveNext();
            }
        }
    } catch (Exception $e) {
        $stats['error'] = $e->getMessage();
    }

    return $stats;
}
