<?php
/**
 * Log Reader Tool
 *
 * Reads and displays various log files from the CMA application.
 * Supports PHP error logs, performance logs, and custom logs.
 */

use App\Library\Arr;
use App\Library\Request;
use App\Library\Server;
use App\Library\Cookie;
use Cma\SecurityHelper;
use Cma\ToolbarHelper;
use Cma\Services\SystemSettings;

require_once __DIR__ . '/../bootstrap.inc';

// Check developer access
if (!SecurityHelper::isDeveloper()) {
    echo '<lib-message type="error">Geen toegang - alleen developers</lib-message>';
    exit;
}

// Cache directory is in site root: /site/cache/
$siteRoot = dirname(__DIR__, 2);
$cacheDir = $siteRoot . '/cache';

// CMA logs directory (created by api/log.php)
$cmaLogsDir = dirname(__DIR__) . '/logs';

// Get request parameters
$selectedLog = Request::query('log', 'perf');
$lines = min(Request::queryInt('lines') ?: 100, 500); // Hard cap at 500 lines
$filter = Request::query('filter', '');
$selectedDate = Request::query('date', date('Y-m-d'));
$deleteAction = Request::query('action') === 'delete';
$deleteMessage = null;

// Get SQL threshold preference (-1 = off, 0 = all, 50/100/250 = filter by ms)
$sqlThreshold = (int)Cookie::get('cma_sql_threshold', '-1');

// Handle delete action
if ($deleteAction) {
    $deleteResult = false;

    switch ($selectedLog) {
        case 'jserrors':
            // Delete all JavaScript error logs from database (tblCMAJavascriptErrors)
            try {
                $dataConn = \App\Library\Database::getConnection('data');
                \App\Library\Database::query("DELETE FROM tblCMAJavascriptErrors", [], $dataConn);
                $deleteResult = true;
                $deleteMessage = 'Alle JavaScript errors verwijderd';
            } catch (\Exception $e) {
                $deleteResult = false;
                $deleteMessage = 'Kon JavaScript errors niet verwijderen: ' . $e->getMessage();
            }
            break;

        case 'perf':
            // Delete specific performance log file
            $perfLogFile = $cacheDir . '/perf_logs/perf_' . $selectedDate . '.log';
            if (file_exists($perfLogFile)) {
                $deleteResult = @unlink($perfLogFile);
                $deleteMessage = $deleteResult
                    ? 'Performance log van ' . $selectedDate . ' verwijderd'
                    : 'Kon performance log niet verwijderen';
            } else {
                $deleteMessage = 'Log bestand niet gevonden';
            }
            break;

        case 'debug':
            // Delete specific debug log file
            $debugLogFile = $cmaLogsDir . '/debug_' . $selectedDate . '.log';
            if (file_exists($debugLogFile)) {
                $deleteResult = @unlink($debugLogFile);
                $deleteMessage = $deleteResult
                    ? 'Debug log van ' . $selectedDate . ' verwijderd'
                    : 'Kon debug log niet verwijderen';
            } else {
                $deleteMessage = 'Log bestand niet gevonden';
            }
            break;

        case 'php':
            // Truncate PHP error log (don't delete, just empty it)
            $phpLogFile = ini_get('error_log');
            if (!empty($phpLogFile) && file_exists($phpLogFile)) {
                $deleteResult = @file_put_contents($phpLogFile, '') !== false;
                $deleteMessage = $deleteResult
                    ? 'PHP error log geleegd'
                    : 'Kon PHP error log niet legen';
            } else {
                $deleteMessage = 'PHP error log niet gevonden';
            }
            break;

        case 'cache':
            // Delete cache log file
            $cacheLogFile = $cacheDir . '/cache.log';
            if (file_exists($cacheLogFile)) {
                $deleteResult = @unlink($cacheLogFile);
                $deleteMessage = $deleteResult
                    ? 'Cache log verwijderd'
                    : 'Kon cache log niet verwijderen';
            } else {
                $deleteMessage = 'Cache log niet gevonden';
            }
            break;

        case '404':
            // Delete specific 404 log file
            $notFoundLogFile = $cmaLogsDir . '/404_' . $selectedDate . '.log';
            if (file_exists($notFoundLogFile)) {
                $deleteResult = @unlink($notFoundLogFile);
                $deleteMessage = $deleteResult
                    ? '404 log van ' . $selectedDate . ' verwijderd'
                    : 'Kon 404 log niet verwijderen';
            } else {
                $deleteMessage = 'Log bestand niet gevonden';
            }
            break;
    }

    // Redirect to remove action from URL (prevents re-delete on refresh)
    if ($deleteResult) {
        $redirectUrl = 'logreader.php?log=' . urlencode($selectedLog);
        if (in_array($selectedLog, ['perf', 'debug', '404']) && $selectedDate !== date('Y-m-d')) {
            // If deleted current date's log, go to today
            $redirectUrl .= '&date=' . date('Y-m-d');
        }
        header('Location: ' . $redirectUrl . '&msg=' . urlencode($deleteMessage));
        exit;
    }
}

// Check for message from redirect
$flashMessage = Request::query('msg', '');

// Get available performance log dates
$perfLogDates = [];
$perfLogDir = $cacheDir . '/perf_logs';
if (is_dir($perfLogDir)) {
    $files = glob($perfLogDir . '/perf_*.log');
    foreach ($files as $file) {
        if (preg_match('/perf_(\d{4}-\d{2}-\d{2})\.log$/', $file, $matches)) {
            $perfLogDates[] = $matches[1];
        }
    }
    rsort($perfLogDates); // Most recent first
}

// Get available debug log dates
$debugLogDates = [];
if (is_dir($cmaLogsDir)) {
    $files = glob($cmaLogsDir . '/debug_*.log');
    foreach ($files as $file) {
        if (preg_match('/debug_(\d{4}-\d{2}-\d{2})\.log$/', $file, $matches)) {
            $debugLogDates[] = $matches[1];
        }
    }
    rsort($debugLogDates); // Most recent first
}

// Get available 404 log dates
$notFoundLogDates = [];
if (is_dir($cmaLogsDir)) {
    $files = glob($cmaLogsDir . '/404_*.log');
    foreach ($files as $file) {
        if (preg_match('/404_(\d{4}-\d{2}-\d{2})\.log$/', $file, $matches)) {
            $notFoundLogDates[] = $matches[1];
        }
    }
    rsort($notFoundLogDates); // Most recent first
}

// Define available log sources
$logSources = [
    'jserrors' => [
        'name' => 'JavaScript Errors (7 dagen)',
        'path' => null,  // Database table (tblCMAJavascriptErrors), not file
        'pattern' => null,
        'hasDateSelect' => false,
        'isDatabase' => true
    ],
    'perf' => [
        'name' => 'Performance Log',
        'path' => $cacheDir . '/perf_logs/perf_' . $selectedDate . '.log',
        'pattern' => '/^{.*}$/m',
        'hasDateSelect' => true
    ],
    'debug' => [
        'name' => 'Debug Log',
        'path' => $cmaLogsDir . '/debug_' . $selectedDate . '.log',
        'pattern' => null,
        'hasDateSelect' => true
    ],
    'php' => [
        'name' => 'PHP Error Log',
        'path' => ini_get('error_log'),
        'pattern' => null,
        'hasDateSelect' => false
    ],
    'cache' => [
        'name' => 'Cache Log',
        'path' => $cacheDir . '/cache.log',
        'pattern' => null,
        'hasDateSelect' => false
    ],
    '404' => [
        'name' => '404 Errors',
        'path' => $cmaLogsDir . '/404_' . $selectedDate . '.log',
        'pattern' => '/^{.*}$/m',
        'hasDateSelect' => true
    ]
];

$currentLog = $logSources[$selectedLog] ?? $logSources['perf'];
$logContent = [];
$jsErrorsData = [];
$error = null;

// Handle JavaScript errors from database (tblCMAJavascriptErrors - captured by error-handler.js)
if ($selectedLog === 'jserrors') {
    try {
        // Use 'data' connection - same as dashboard_stats.php
        $dataConn = \App\Library\Database::getConnection('data');

        // Filter to past 7 days by default
        $sql = "SELECT TOP " . (int)$lines . " ID, error_message, error_stack, page_url, user_agent, datestamp
                FROM tblCMAJavascriptErrors
                WHERE datestamp >= DateAdd('d', -7, Now())
                ORDER BY datestamp DESC";
        $rs = \App\Library\Database::openRS($sql, $dataConn);
        while ($rs && !$rs->EOF) {
            $jsErrorsData[] = [
                'id' => $rs->fields['ID'] ?? '',
                'datestamp' => $rs->fields['datestamp'] ?? '',
                'level' => 'error',  // All entries in this table are errors
                'source' => $rs->fields['page_url'] ?? '',
                'message' => $rs->fields['error_message'] ?? '',
                'requestId' => '',  // Not stored in this table
                'user' => '',  // Not stored in this table
                'stackTrace' => $rs->fields['error_stack'] ?? '',
                'userAgent' => $rs->fields['user_agent'] ?? '',
            ];
            $rs->MoveNext();
        }
    } catch (\Exception $e) {
        $error = 'Kon JavaScript errors niet laden: ' . $e->getMessage();
    }
} elseif (!empty($currentLog['path']) && file_exists($currentLog['path']) && is_readable($currentLog['path'])) {
    // Read last N lines efficiently using tail-like approach
    try {
        $fileSize = filesize($currentLog['path']);
        $fileSizeMB = round($fileSize / 1024 / 1024, 1);

        // Hard limit: max 2MB chunk to read (prevents memory issues)
        $maxChunkSize = 2 * 1024 * 1024; // 2MB
        $chunkSize = min($fileSize, $maxChunkSize);

        $handle = fopen($currentLog['path'], 'r');

        if ($handle) {
            // Seek to near end of file
            if ($fileSize > $chunkSize) {
                fseek($handle, -$chunkSize, SEEK_END);
                // Skip partial first line
                fgets($handle);
            }

            // Read lines with a hard limit to prevent memory issues
            $rawLines = [];
            $maxRawLines = $lines * 3; // Read up to 3x requested in case of filtering
            $lineCount = 0;
            while (($line = fgets($handle)) !== false && $lineCount < $maxRawLines) {
                $trimmed = trim($line);
                if (!empty($trimmed)) {
                    $rawLines[] = $line;
                    $lineCount++;
                }
            }
            fclose($handle);

            // Take only the last N lines
            if (count($rawLines) > $lines) {
                $rawLines = array_slice($rawLines, -$lines);
            }

            // Process lines (filter, parse JSON, etc.)
            foreach ($rawLines as $line) {
                // Apply filter if set
                if (empty($filter) || stripos($line, $filter) !== false) {
                    // Try to parse as JSON for performance logs and 404 logs
                    if ($selectedLog === 'perf' || $selectedLog === '404') {
                        $json = json_decode($line, true);
                        if ($json) {
                            // Apply SQL threshold filter for query entries (perf log only)
                            if ($selectedLog === 'perf' && ($json['type'] ?? '') === 'query') {
                                if ($sqlThreshold === -1) {
                                    continue; // SQL logging is off
                                }
                                if ($sqlThreshold > 0 && ($json['ms'] ?? 0) < $sqlThreshold) {
                                    continue; // Below threshold
                                }
                            }
                            $logContent[] = $json;
                        }
                    } else {
                        $logContent[] = $line;
                    }
                }
            }

            // Free memory
            unset($rawLines);

            // Add file size info if large
            if ($fileSize > 10 * 1024 * 1024) {
                $error = "Logbestand is {$fileSizeMB} MB - alleen laatste 2MB wordt gelezen. Overweeg het bestand te legen.";
            }
        }

        // Reverse to show newest first
        $logContent = array_reverse($logContent);
    } catch (\Exception $e) {
        $error = 'Kon logbestand niet lezen: ' . $e->getMessage();
    }
} elseif (empty($flashMessage)) {
    // Only show error if we didn't just delete the file
    if (empty($currentLog['path'])) {
        $error = 'Log pad niet geconfigureerd';
    } elseif ($selectedLog === 'perf' || $selectedLog === 'debug' || $selectedLog === '404') {
        // Date-based logs: show friendly message if no log exists yet (first use)
        $error = null; // Will show "Geen log entries gevonden" instead
    } else {
        $error = 'Log bestand niet gevonden: ' . str_replace('\\', '/', $currentLog['path']);
    }
}

// Get log settings for info panel
$sysSettings = SystemSettings::getAll();
$logSettings = [
    'perf' => [
        'label' => 'Performance logging',
        'enabled' => $sysSettings['perf_log_enabled'] ?? false,
        'path' => $cacheDir . '/perf_logs/perf_' . date('Y-m-d') . '.log',
    ],
    'cache' => [
        'label' => 'Cache logging',
        'enabled' => $sysSettings['cache_log_enabled'] ?? false,
        'path' => $cacheDir . '/cache.log',
    ],
    'debug' => [
        'label' => 'Debug logging',
        'enabled' => $sysSettings['debug_log_enabled'] ?? false,
        'path' => $cmaLogsDir . '/debug_' . date('Y-m-d') . '.log',
    ],
    'php' => [
        'label' => 'PHP error log',
        'enabled' => true,
        'path' => ini_get('error_log'),
    ],
];
// Check if files exist
foreach ($logSettings as $key => &$setting) {
    $setting['exists'] = !empty($setting['path']) && file_exists($setting['path']);
}

// Build table data for lib-table
$tableData = [];
if ($selectedLog === 'perf' && !empty($logContent)) {
    foreach ($logContent as $entry) {
        $ms = $entry['ms'] ?? 0;
        $msFormatted = isset($entry['ms']) ? number_format($entry['ms'], 1) : '-';

        // Add color styling based on ms value
        if ($ms > 500) {
            $msFormatted = '<span style="color: var(--color-error); font-weight: bold;">' . $msFormatted . '</span>';
        } elseif ($ms > 100) {
            $msFormatted = '<span style="color: var(--color-warning);">' . $msFormatted . '</span>';
        }

        $ctx = '';
        if (isset($entry['ctx']) && Arr::isArray($entry['ctx'])) {
            $ctx = '<code style="font-size: var(--font-size-xs);">' . Server::htmlEncode(json_encode($entry['ctx'], JSON_UNESCAPED_UNICODE)) . '</code>';
        }

        $tableData[] = [
            'Tijd' => $selectedDate . ' ' . ($entry['ts'] ?? '-'),
            'Type' => '<code>' . Server::htmlEncode($entry['type'] ?? '-') . '</code>',
            'Naam' => Server::htmlEncode($entry['name'] ?? '-'),
            'ms' => $msFormatted,
            'Context' => $ctx
        ];
    }
}

cma_html_header('Logbestanden lezen');
echo '<body class="contentbody tools">';

// Toolbar with title, filters and refresh button
ToolbarHelper::start(true);
ToolbarHelper::title('Logbestanden lezen');
?>
<form method="get" class="toolbar-filters" id="logFilterForm">
    <select name="log" onchange="submitLogFilter()" class="form-control">
        <?php foreach ($logSources as $key => $source): ?>
        <option value="<?= $key ?>" <?= $selectedLog === $key ? 'selected' : '' ?>><?= Server::htmlEncode($source['name']) ?></option>
        <?php endforeach; ?>
    </select>
    <?php
    // Determine which date list to show based on log type
    $datesToShow = [];
    if ($selectedLog === 'perf' && !empty($perfLogDates)) {
        $datesToShow = $perfLogDates;
    } elseif ($selectedLog === 'debug' && !empty($debugLogDates)) {
        $datesToShow = $debugLogDates;
    } elseif ($selectedLog === '404' && !empty($notFoundLogDates)) {
        $datesToShow = $notFoundLogDates;
    }
    if (!empty($datesToShow)):
    ?>
    <select name="date" onchange="submitLogFilter()" class="form-control" style="width: 100px;">
        <?php foreach ($datesToShow as $logDate): ?>
        <option value="<?= $logDate ?>" <?= $selectedDate === $logDate ? 'selected' : '' ?>><?= $logDate ?></option>
        <?php endforeach; ?>
    </select>
    <?php endif; ?>
    <input type="hidden" name="lines" value="999999">
    <input type="text" name="filter" value="<?= Server::htmlEncode($filter) ?>" class="form-control" style="width: 150px;" placeholder="Filter...">
    <button type="button" onclick="submitLogFilter()" class="btn btn-primary btn-sm">Laden</button>
    <?php if ($selectedLog === 'perf' && $sqlThreshold > 0): ?>
    <span class="sql-threshold-indicator" title="SQL queries worden gefilterd op ≥ <?= $sqlThreshold ?>ms. Wijzig via Instellingen.">
        SQL ≥ <?= $sqlThreshold ?>ms
    </span>
    <?php endif; ?>
</form>
<script>
function submitLogFilter() {
    var form = document.getElementById('logFilterForm');
    var params = new URLSearchParams(new FormData(form));
    // Stay within current frame - logreader is loaded inside tools.php iframe
    window.location.href = 'logreader.php?' + params.toString();
}
</script>
<?php
ToolbarHelper::button('javascript:location.reload()', 'lnr-sync', true, 'Vernieuwen');

// Build delete URL with current parameters
$deleteUrl = 'logreader.php?log=' . urlencode($selectedLog) . '&action=delete';
if ($selectedLog === 'perf' || $selectedLog === 'debug') {
    $deleteUrl .= '&date=' . urlencode($selectedDate);
}
$deleteConfirm = 'Weet je zeker dat je deze log wilt leegmaken?';

ToolbarHelper::button('javascript:confirmDelete()', 'lnr-trash', true, 'Log leegmaken');
ToolbarHelper::button('javascript:toggleLogSettingsInfo()', 'lnr-cog', true, 'Instellingen');
ToolbarHelper::end(true);

?>
<script>
async function confirmDelete() {
    var confirmed = await libConfirm('<?= $deleteConfirm ?>', {
        title: 'Log leegmaken',
        confirmText: 'Leegmaken',
        cancelText: 'Annuleren'
    });
    if (confirmed) {
        window.location.href = '<?= $deleteUrl ?>';
    }
}
</script>
<?php if (!empty($flashMessage)): ?>
<lib-message type="success" style="margin: 20px 20px 0;"><?= Server::htmlEncode($flashMessage) ?></lib-message>
<?php endif; ?>
<?php if (!empty($deleteMessage) && empty($deleteResult)): ?>
<lib-message type="warning" style="margin: 20px 20px 0;"><?= Server::htmlEncode($deleteMessage) ?></lib-message>
<?php endif;
?>
<div id="c" class="tools">

    <?php if ($error): ?>
    <lib-message type="warning" style="margin-bottom: 15px;">
        <?= Server::htmlEncode($error) ?>
    </lib-message>
    <?php endif; ?>

    <div class="log-settings-info" id="logSettingsInfo" style="display: none;">
        <p style="margin: 0 0 10px;"><strong>Log instellingen</strong> (via Voorkeuren → Systeeminstellingen)</p>
        <table class="listtable" style="width: auto;">
            <thead>
                <tr>
                    <th>Log type</th>
                    <th>Status</th>
                    <th>Bestand</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($logSettings as $key => $setting): ?>
                <tr>
                    <td><?= Server::htmlEncode($setting['label']) ?></td>
                    <td><?= $setting['enabled'] ? '<span style="color: var(--color-success);">Actief</span>' : '<span style="color: var(--text-muted);">Uit</span>' ?></td>
                    <td><?php if ($setting['enabled']): ?>
                        <?= $setting['exists'] ? '<span style="color: var(--color-success);">Aanwezig</span>' : '<span style="color: var(--color-warning);">Niet gevonden</span>' ?>
                    <?php else: ?>
                        -
                    <?php endif; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <p style="margin: 10px 0 0;"><button type="button" class="btn btn-primary" onclick="navigateToPreferences()">Instellingen wijzigen</button></p>
    </div>
    <script>
    function toggleLogSettingsInfo() {
        var panel = document.getElementById('logSettingsInfo');
        panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
    }
    function navigateToPreferences() {
        if (window.parent && window.parent.CMA && window.parent.CMA.loadPage) {
            window.parent.CMA.loadPage('preferences.php', 'Voorkeuren');
            return false;
        }
        return true;
    }
    </script>

    <?php if ($selectedLog === 'jserrors'): ?>
    <?php if (!empty($jsErrorsData)): ?>
    <lib-table
        id="jsErrorsTable"
        filterable
        sortable
        resizable
        paginate="50"
        export-filename="javascript-errors"
        storage-key="logreader_jserrors"
    >
        <table class="listtable filtering" cellspacing="0" cellpadding="0">
            <thead>
                <tr class="listheader">
                    <th data-type="string" data-field="datestamp" style="width: 140px;">Datum/tijd</th>
                    <th data-type="string" data-field="message">Foutmelding</th>
                    <th data-type="string" data-field="source" style="width: 200px;">Pagina</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($jsErrorsData as $index => $row): ?>
                <tr class="jserror-row" data-index="<?= $index ?>">
                    <td data-field="datestamp" style="white-space: nowrap; font-size: var(--font-size-xs);"><?= Server::htmlEncode($row['datestamp']) ?></td>
                    <td data-field="message" style="max-width: 500px; overflow: hidden; text-overflow: ellipsis; color: var(--color-error, #e01f3d);" title="<?= Server::htmlEncode($row['message']) ?>"><?php
                        if (!empty($row['message'])) {
                            echo Server::htmlEncode(substr($row['message'], 0, 150));
                            if (strlen($row['message']) > 150) echo '...';
                        } else {
                            echo '<span class="empty-value">(leeg)</span>';
                        }
                    ?></td>
                    <td data-field="source" style="font-size: var(--font-size-xs);"><?= Server::htmlEncode($row['source'] ?: '-') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </lib-table>

    <lib-dialog id="jsErrorDetailDialog" heading="JavaScript Error Details" size="large">
        <div id="jsErrorDetailContent"></div>
    </lib-dialog>
    <script>
    (function() {
        var jsErrorData = <?= json_encode($jsErrorsData, JSON_UNESCAPED_UNICODE) ?>;

        document.getElementById('jsErrorsTable').addEventListener('click', function(e) {
            // Ignore clicks on resize handles
            if (e.target.closest('.col-resize-handle')) return;

            var row = e.target.closest('tr.jserror-row');
            if (!row) return;

            var index = parseInt(row.dataset.index, 10);
            var entry = jsErrorData[index];
            if (!entry) return;

            var html = '<table class="log-detail-table">';
            html += '<tr><th>Datum/tijd</th><td>' + escapeHtml(entry.datestamp) + '</td></tr>';
            html += '<tr><th>Foutmelding</th><td style="color: var(--color-error); font-weight: 600;">' + escapeHtml(entry.message) + '</td></tr>';
            html += '<tr><th>Pagina</th><td>' + escapeHtml(entry.source || '-') + '</td></tr>';
            if (entry.stackTrace) {
                html += '<tr><th>Stack trace</th><td><pre class="log-detail-json" style="font-size: var(--font-size-xs);">' + escapeHtml(entry.stackTrace) + '</pre></td></tr>';
            }
            if (entry.userAgent) {
                html += '<tr><th>Browser</th><td style="font-size: var(--font-size-xs);">' + escapeHtml(entry.userAgent) + '</td></tr>';
            }
            html += '</table>';

            document.getElementById('jsErrorDetailContent').innerHTML = html;
            document.getElementById('jsErrorDetailDialog').open();
        });

        // escapeHtml() provided by cma-utils.js
    })();
    </script>
    <?php else: ?>
    <lib-message type="info">Geen JavaScript errors gevonden in de afgelopen 7 dagen.</lib-message>
    <?php endif; ?>

    <?php elseif ($selectedLog === 'perf' && !empty($logContent)): ?>
    <lib-table
        id="perfLogTable"
        filterable
        sortable
        resizable
        paginate="50"
        export-filename="performance-log"
        storage-key="logreader_perf"
    >
        <table class="listtable filtering" cellspacing="0" cellpadding="0">
            <thead>
                <tr class="listheader">
                    <th data-type="string" data-field="tijd" style="width: 180px;">Datum/tijd</th>
                    <th data-type="string" data-field="type" style="width: 100px;">Type</th>
                    <th data-type="string" data-field="naam">Naam</th>
                    <th data-type="number" data-field="ms" style="width: 80px; text-align: right;">ms</th>
                    <th data-type="string" data-field="context" data-no-filter>Context</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tableData as $index => $row): ?>
                <tr class="log-row" data-index="<?= $index ?>">
                    <td data-field="tijd" style="white-space: nowrap; font-size: var(--font-size-xs);"><?= $row['Tijd'] ?></td>
                    <td data-field="type"><?= $row['Type'] ?></td>
                    <td data-field="naam"><?= $row['Naam'] ?></td>
                    <td data-field="ms" style="text-align: right;"><?= $row['ms'] ?></td>
                    <td data-field="context" style="max-width: 400px; overflow: hidden; text-overflow: ellipsis;"><?= $row['Context'] ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </lib-table>
    <?php elseif ($selectedLog === '404' && !empty($logContent)): ?>
    <lib-table
        id="notFoundLogTable"
        filterable
        sortable
        resizable
        paginate="50"
        export-filename="404-errors"
        storage-key="logreader_404"
    >
        <table class="listtable filtering" cellspacing="0" cellpadding="0">
            <thead>
                <tr class="listheader">
                    <th data-type="string" data-field="tijd" style="width: 140px;">Datum/tijd</th>
                    <th data-type="string" data-field="type" style="width: 100px;">Type</th>
                    <th data-type="string" data-field="url">Gevraagde URL</th>
                    <th data-type="string" data-field="referer" style="width: 200px;">Referer</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logContent as $index => $row): ?>
                <tr class="notfound-row" data-index="<?= $index ?>">
                    <td data-field="tijd" style="white-space: nowrap; font-size: var(--font-size-xs);"><?= Server::htmlEncode($row['ts'] ?? '-') ?></td>
                    <td data-field="type"><?php
                        $type = $row['type'] ?? 'not_found';
                        $typeLabel = $type === 'icon_redirect' ? '<span style="color: var(--color-success);">redirect</span>' : '<span style="color: var(--color-error);">404</span>';
                        echo $typeLabel;
                    ?></td>
                    <td data-field="url" style="font-family: monospace; font-size: var(--font-size-xs);" title="<?= Server::htmlEncode($row['url'] ?? '') ?>"><?= Server::htmlEncode($row['url'] ?? '-') ?></td>
                    <td data-field="referer" style="font-size: var(--font-size-xs); max-width: 200px; overflow: hidden; text-overflow: ellipsis;" title="<?= Server::htmlEncode($row['referer'] ?? '') ?>"><?= Server::htmlEncode($row['referer'] ?: '-') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </lib-table>

    <lib-dialog id="notFoundDetailDialog" heading="404 Error Details" size="large">
        <div id="notFoundDetailContent"></div>
    </lib-dialog>
    <script>
    (function() {
        var notFoundData = <?= json_encode($logContent, JSON_UNESCAPED_UNICODE) ?>;

        document.getElementById('notFoundLogTable').addEventListener('click', function(e) {
            // Ignore clicks on resize handles
            if (e.target.closest('.col-resize-handle')) return;

            var row = e.target.closest('tr.notfound-row');
            if (!row) return;

            var index = parseInt(row.dataset.index, 10);
            var entry = notFoundData[index];
            if (!entry) return;

            var html = '<table class="log-detail-table">';
            html += '<tr><th>Datum/tijd</th><td>' + escapeHtml(entry.ts || '-') + '</td></tr>';
            html += '<tr><th>Type</th><td>' + (entry.type === 'icon_redirect' ? '<span style="color: var(--color-success);">Redirect naar juiste locatie</span>' : '<span style="color: var(--color-error);">Niet gevonden (404)</span>') + '</td></tr>';
            html += '<tr><th>URL</th><td style="font-family: monospace; word-break: break-all;">' + escapeHtml(entry.url || '-') + '</td></tr>';
            if (entry.redirect) {
                html += '<tr><th>Redirect naar</th><td style="font-family: monospace; word-break: break-all; color: var(--color-success);">' + escapeHtml(entry.redirect) + '</td></tr>';
            }
            if (entry.referer) {
                html += '<tr><th>Referer</th><td style="word-break: break-all;">' + escapeHtml(entry.referer) + '</td></tr>';
            }
            html += '<tr><th>Methode</th><td>' + escapeHtml(entry.method || 'GET') + '</td></tr>';
            html += '<tr><th>IP</th><td>' + escapeHtml(entry.ip || '-') + '</td></tr>';
            if (entry.ua) {
                html += '<tr><th>Browser</th><td style="font-size: var(--font-size-xs);">' + escapeHtml(entry.ua) + '</td></tr>';
            }
            html += '</table>';

            document.getElementById('notFoundDetailContent').innerHTML = html;
            document.getElementById('notFoundDetailDialog').open();
        });

        // escapeHtml() provided by cma-utils.js
    })();
    </script>
    <?php elseif (!empty($logContent)): ?>
    <div class="log-output"><?php
        $isFirst = true;
        $isPhpLog = ($selectedLog === 'php');
        foreach ($logContent as $line) {
            // For PHP error log, add separator before each new error entry
            if ($isPhpLog && preg_match('/^\[\d{2}-\w{3}-\d{4}\s+\d{2}:\d{2}:\d{2}/', $line)) {
                if (!$isFirst) {
                    echo '<hr class="log-separator">';
                }
                $isFirst = false;
            }
            echo Server::htmlEncode($line);
        }
    ?></div>
    <?php elseif (empty($error) && empty($flashMessage)): ?>
    <lib-message type="info">Geen log entries gevonden</lib-message>
    <?php endif; ?>
</div>

<?php if ($selectedLog === 'perf' && !empty($logContent)): ?>
<lib-dialog id="logDetailDialog" heading="Log details" size="large">
    <div id="logDetailContent"></div>
</lib-dialog>
<script>
(function() {
    var logData = <?= json_encode($logContent, JSON_UNESCAPED_UNICODE) ?>;
    var selectedDate = <?= json_encode($selectedDate) ?>;

    document.getElementById('perfLogTable').addEventListener('click', function(e) {
        // Ignore clicks on resize handles
        if (e.target.closest('.col-resize-handle')) return;

        var row = e.target.closest('tr.log-row');
        if (!row) return;

        var index = parseInt(row.dataset.index, 10);
        var entry = logData[index];
        if (!entry) return;

        var html = '<table class="log-detail-table">';
        html += '<tr><th>Datum/tijd</th><td>' + escapeHtml(selectedDate + ' ' + (entry.ts || '-')) + '</td></tr>';
        html += '<tr><th>Type</th><td><code>' + escapeHtml(entry.type || '-') + '</code></td></tr>';
        html += '<tr><th>Naam</th><td>' + escapeHtml(entry.name || '-') + '</td></tr>';
        html += '<tr><th>ms</th><td>' + (entry.ms !== undefined ? entry.ms.toFixed(1) : '-') + '</td></tr>';
        if (entry.ctx) {
            html += '<tr><th>Context</th><td><pre class="log-detail-json">' + escapeHtml(JSON.stringify(entry.ctx, null, 2)) + '</pre></td></tr>';
        }
        html += '</table>';

        document.getElementById('logDetailContent').innerHTML = html;
        document.getElementById('logDetailDialog').open();
    });

    // escapeHtml() provided by cma-utils.js
})();
</script>
<?php endif; ?>

<style>
.toolbar-filters {
    display: flex;
    gap: 8px;
    align-items: center;
    margin-left: 20px;
}
.toolbar-filters .form-control {
    height: 28px;
    padding: 2px 8px;
    font-size: var(--font-size-sm);
}
.toolbar-filters .btn-sm {
    height: 28px;
    padding: 2px 12px !important;
    font-size: var(--font-size-sm);
}
.sql-threshold-indicator {
    font-size: var(--font-size-xs);
    color: var(--color-primary, #007bff);
    background: var(--bg-hover);
    padding: 4px 8px;
    border-radius: 4px;
    cursor: help;
    white-space: nowrap;
}
.log-output {
    background: var(--bg-surface);
    padding: 15px;
    border-radius: 8px;
    border: 1px solid var(--border-color);
    flex: 1;
    overflow: auto;
    font-family: monospace;
    font-size: var(--font-size-sm);
    white-space: pre-wrap;
    word-break: break-all;
    color: var(--text-primary);
}
.log-separator {
    border: none;
    border-top: 1px solid var(--border-color, #ccc);
    margin: 12px 0;
}
#c.tools:has(.log-output) {
    height: 100%;
}
/* Force overflow visible on #c to allow dropdown to show */
#c.tools {
    overflow: visible !important;
}
lib-table {
    flex: 1;
    display: flex;
    flex-direction: column;
    overflow: visible;
}
lib-table table {
    overflow: visible;
}
/* Ensure filter dropdowns are visible above all other content */
lib-table thead {
    position: relative;
    z-index: 100;
}
lib-table thead tr {
    position: relative;
}
lib-table th {
    position: relative;
    overflow: visible;
}
lib-table .dropdown-filter-content {
    position: fixed !important;
    z-index: 10000 !important;
}
tr.log-row,
tr.jserror-row,
tr.notfound-row {
    cursor: pointer;
}
tr.log-row:hover td,
tr.jserror-row:hover td,
tr.notfound-row:hover td {
    background: var(--bg-hover);
}
.log-detail-table {
    width: 100%;
    border-collapse: collapse;
}
.log-detail-table th {
    text-align: left;
    vertical-align: top;
    padding: 8px 12px;
    width: 80px;
    background: var(--bg-surface, #f5f5f5);
    border-bottom: 1px solid var(--border-color, #ddd);
}
.log-detail-table td {
    padding: 8px 12px;
    border-bottom: 1px solid var(--border-color, #ddd);
}
.log-detail-json {
    margin: 0;
    padding: 10px;
    background: var(--bg-surface, #f5f5f5);
    border-radius: 4px;
    font-size: var(--font-size-sm);
    max-height: 300px;
    overflow: auto;
    white-space: pre-wrap;
    word-break: break-word;
}
.empty-value {
    color: var(--text-muted, #999);
    font-style: italic;
}
</style>

</body>
</html>
