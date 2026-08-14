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

// All logs are consolidated under the site-root .logs/ with per-type subfolders
// (perf/, debug/, 404/, cache/, deploy/, phperrors/, access/). This is the single
// base every log path below is derived from.
$logsBase = $siteRoot . '/.logs';

// Get request parameters
$logExplicit  = isset($_GET['log']) && $_GET['log'] !== '';
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
            $perfLogFile = $logsBase . '/perf/perf_' . $selectedDate . '.log';
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
            $debugLogFile = $logsBase . '/debug/debug_' . $selectedDate . '.log';
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
            $cacheLogFile = $logsBase . '/cache/cache.log';
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
            $notFoundLogFile = $logsBase . '/404/404_' . $selectedDate . '.log';
            if (file_exists($notFoundLogFile)) {
                $deleteResult = @unlink($notFoundLogFile);
                $deleteMessage = $deleteResult
                    ? '404 log van ' . $selectedDate . ' verwijderd'
                    : 'Kon 404 log niet verwijderen';
            } else {
                $deleteMessage = 'Log bestand niet gevonden';
            }
            break;

        case 'deploy':
            // Truncate (don't delete) the deploy log so the next deploy
            // can append. Same pattern as case 'php' — including the
            // @ prefix on file_put_contents: a permissions/lock warning
            // landing in the response buffer would prevent the
            // header('Location: ...') below from firing, leaving the
            // browser with a 200 OK + warning text. iOS Safari then
            // prompts to save the response as "logreader.php".
            $deployLogFile = $logsBase . '/deploy/deploy.log';
            if (file_exists($deployLogFile)) {
                $deleteResult = @file_put_contents($deployLogFile, '') !== false;
                $deleteMessage = $deleteResult
                    ? 'Deploy log geleegd'
                    : 'Kon deploy log niet legen';
            } else {
                $deleteMessage = 'Deploy log niet gevonden';
            }
            break;

        case 'unauthorized':
            // Truncate the site-level unauthorized-access log written by the front-end
            // RBAC gate (site-root /.logs/unauthorized_access.log). Same @-prefixed
            // truncate pattern as 'php'/'deploy' so a warning can't break the redirect.
            $unauthLogFile = $logsBase . '/access/unauthorized_access.log';
            if (file_exists($unauthLogFile)) {
                $deleteResult = @file_put_contents($unauthLogFile, '') !== false;
                $deleteMessage = $deleteResult
                    ? 'Log ongeautoriseerde toegang geleegd'
                    : 'Kon de log niet legen';
            } else {
                $deleteMessage = 'Log niet gevonden';
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
$perfLogDir = $logsBase . '/perf';
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
if (is_dir($logsBase)) {
    $files = glob($logsBase . '/debug/debug_*.log');
    foreach ($files as $file) {
        if (preg_match('/debug_(\d{4}-\d{2}-\d{2})\.log$/', $file, $matches)) {
            $debugLogDates[] = $matches[1];
        }
    }
    rsort($debugLogDates); // Most recent first
}

// Get available 404 log dates
$notFoundLogDates = [];
if (is_dir($logsBase)) {
    $files = glob($logsBase . '/404/404_*.log');
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
        'path' => $logsBase . '/perf/perf_' . $selectedDate . '.log',
        'pattern' => '/^{.*}$/m',
        'hasDateSelect' => true
    ],
    'debug' => [
        'name' => 'Debug Log',
        'path' => $logsBase . '/debug/debug_' . $selectedDate . '.log',
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
        'path' => $logsBase . '/cache/cache.log',
        'pattern' => null,
        'hasDateSelect' => false
    ],
    '404' => [
        'name' => '404 Errors',
        'path' => $logsBase . '/404/404_' . $selectedDate . '.log',
        'pattern' => '/^{.*}$/m',
        'hasDateSelect' => true
    ],
    'deploy' => [
        'name' => 'Deploy Log',
        // Site-level log written by the /deploy.php webhook;
        // lives outside cma/ because deploys are a site-level concern.
        'path' => $logsBase . '/deploy/deploy.log',
        'pattern' => null,
        'hasDateSelect' => false
    ],
    'unauthorized' => [
        'name' => 'Ongeautoriseerde toegang',
        // Site-level log written by the front-end RBAC gate (config/page_roles.php +
        // enforce_page_roles()). Lives outside cma/ in the site's gitignored .logs/
        // dir. One JSON object per line (ts, file, listed, reason, role, login, ip…).
        'path' => $logsBase . '/access/unauthorized_access.log',
        'pattern' => '/^{.*}$/m',
        'hasDateSelect' => false
    ]
];

require_once __DIR__ . '/logreader_bronnen.inc';

// Een onbekende ?log= valt terug op de standaardbron.
if (!isset($logSources[$selectedLog])) {
    $selectedLog = 'perf';
}

$logDatums = ['perf' => $perfLogDates, 'debug' => $debugLogDates, '404' => $notFoundLogDates];
// De JS-fouten komen uit een tabel die migratie 9.13.0 aanmaakt; op een site die
// nog niet zo ver is bestaat hij niet.
$logTabelCheck = static function (): bool {
    try {
        $conn = \App\Library\Database::getConnection('data');
        return $conn !== null
            && \App\Library\Database::tableExistsPDO($conn, 'tblCMAJavascriptErrors');
    } catch (\Throwable $e) {
        return false;
    }
};

// Alleen tonen wat er is. Een keuze die gegarandeerd "Log bestand niet gevonden"
// oplevert leest als iets dat stuk is, terwijl er niets aan de hand is.
$beschikbareLogs = cma_logreader_beschikbare_bronnen($logSources, '', $logDatums, $logTabelCheck);

// Kwam de bezoeker binnen zonder ?log=, dan is 'perf' een default en geen keuze —
// open dan de eerste bron die er wél is in plaats van een leeg scherm.
if (!$logExplicit && !isset($beschikbareLogs[$selectedLog]) && $beschikbareLogs !== []) {
    $selectedLog = (string) array_key_first($beschikbareLogs);
}

// Een expliciet gekozen bron blijft in de lijst staan, ook als het bestand er niet
// (meer) is — anders wijst de keuzelijst iets anders aan dan wat eronder staat.
$logSources = isset($beschikbareLogs[$selectedLog])
    ? $beschikbareLogs
    : cma_logreader_beschikbare_bronnen($logSources, $selectedLog, $logDatums, $logTabelCheck);

$currentLog = $logSources[$selectedLog];
$logContent = [];
$jsErrorsData = [];
$error = null;

// Handle JavaScript errors from database (tblCMAJavascriptErrors - captured by error-handler.js)
if ($selectedLog === 'jserrors') {
    try {
        // Use 'data' connection - same as dashboard_stats.php
        $dataConn = \App\Library\Database::getConnection('data');

        // Filter to past 7 days by default
        // error_url/error_line/error_column worden wél weggeschreven (zie de
        // insert in form_api.php) maar werden hier niet opgehaald — juist de
        // plek waar de fout zit stond dus niet in het detailvenster.
        $sql = "SELECT TOP " . (int)$lines . " ID, error_message, error_stack, error_url, error_line,
                       error_column, page_url, user_agent, datestamp
                FROM tblCMAJavascriptErrors
                WHERE datestamp >= DateAdd('d', -7, Now())
                ORDER BY datestamp DESC";
        $rs = \App\Library\Database::openRS($sql, $dataConn);
        while ($rs && !$rs->EOF) {
            // Access/ODBC hands datestamp back in the server locale (often US:
            // m/d/Y h:i:s AM/PM). Normalise to Dutch d-m-Y H:i:s; keep the raw
            // value if it doesn't parse.
            $rawDs = (string)($rs->fields['datestamp'] ?? '');
            $dsTime = $rawDs !== '' ? strtotime($rawDs) : false;
            $jsErrorsData[] = [
                'id' => $rs->fields['ID'] ?? '',
                'datestamp' => $dsTime !== false ? date('d-m-Y H:i:s', $dsTime) : $rawDs,
                'level' => 'error',  // All entries in this table are errors
                'source' => $rs->fields['page_url'] ?? '',
                'message' => $rs->fields['error_message'] ?? '',
                'requestId' => '',  // Not stored in this table
                'user' => '',  // Not stored in this table
                'stackTrace' => $rs->fields['error_stack'] ?? '',
                'userAgent' => $rs->fields['user_agent'] ?? '',
                'file' => $rs->fields['error_url'] ?? '',
                'line' => $rs->fields['error_line'] ?? '',
                'column' => $rs->fields['error_column'] ?? '',
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
                    // Try to parse as JSON for performance logs, 404 logs and the
                    // unauthorized-access log (all one JSON object per line).
                    if ($selectedLog === 'perf' || $selectedLog === '404' || $selectedLog === 'unauthorized') {
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
    } elseif ($selectedLog === 'perf' || $selectedLog === 'debug' || $selectedLog === '404' || $selectedLog === 'unauthorized') {
        // Date-based logs (+ the unauthorized log, which simply may not exist until
        // the first denial): show a friendly message rather than a path error.
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
        'path' => $logsBase . '/perf/perf_' . date('Y-m-d') . '.log',
    ],
    'cache' => [
        'label' => 'Cache logging',
        'enabled' => $sysSettings['cache_log_enabled'] ?? false,
        'path' => $logsBase . '/cache/cache.log',
    ],
    'debug' => [
        'label' => 'Debug logging',
        'enabled' => $sysSettings['debug_log_enabled'] ?? false,
        'path' => $logsBase . '/debug/debug_' . date('Y-m-d') . '.log',
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
unset($setting); // break the reference — otherwise a later foreach corrupts the last
                 // row (the classic PHP by-reference bug: 'php' became a copy of 'debug').

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
        <option value="<?= $key ?>" <?= (string) $key === $selectedLog ? 'selected' : '' ?>><?= Server::htmlEncode($source['name']) ?></option>
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
// Remember which log was opened last so the next /cma/tools/logreader.php
// hit (no ?log= in URL) auto-loads the same one. Storage key is namespaced
// per origin via the localStorage origin scope. Sanitised on read so a
// stale value from a removed log type can't redirect to a 404.
(function () {
    var KEY = 'cma.logreader.last';
    var current = <?= json_encode($selectedLog) ?>;
    var explicit = <?= $logExplicit ? 'true' : 'false' ?>;
    try {
        if (explicit) {
            // User landed here with ?log= in URL — remember that choice.
            localStorage.setItem(KEY, current);
        } else {
            // No ?log= → menu/direct hit. Restore the last-opened log if
            // we have one stored AND it's different from the current
            // default ('perf'), otherwise the page is already showing it.
            var last = localStorage.getItem(KEY);
            // Alleen naar een log dat er nu ook is: een onthouden keuze die
            // inmiddels verdwenen is zou je op een leeg scherm zetten.
            var beschikbaar = <?= json_encode(array_keys($logSources)) ?>;
            if (last && beschikbaar.indexOf(last) === -1) {
                localStorage.removeItem(KEY);
                last = null;
            }
            if (last && /^[a-z0-9_-]+$/i.test(last) && last !== current) {
                // Navigate on the SAME URL we were loaded from (may be
                // tools/logreader.php OR tools.php?tool=logs) — only swap ?log=.
                var url = new URL(window.location.href);
                url.searchParams.set('log', last);
                window.location.replace(url.toString());
            }
        }
    } catch (e) { /* localStorage disabled — fall back to default */ }
})();

// The delete flow redirects with ?msg=... (PRG, so a refresh doesn't re-delete). The
// server already rendered that message once; strip msg + action from the address bar
// now so it doesn't persist — switching logs won't repeat it and a refresh is clean.
(function () {
    try {
        var u = new URL(window.location.href);
        if (u.searchParams.has('msg') || u.searchParams.has('action')) {
            u.searchParams.delete('msg');
            u.searchParams.delete('action');
            history.replaceState(null, '', u.toString());
        }
    } catch (e) {}
})();

function submitLogFilter() {
    var form = document.getElementById('logFilterForm');
    var fd = new FormData(form);
    try {
        var pick = fd.get('log');
        if (pick) { localStorage.setItem('cma.logreader.last', pick); }
    } catch (e) {}
    // Navigate on the SAME path we were loaded from. The tool is reachable both
    // directly (tools/logreader.php) AND via tools.php?tool=logs; a hardcoded
    // relative 'logreader.php?' breaks the latter (wrong path → ?log= dropped →
    // the localStorage restore snaps back to the last log, e.g. jserrors). Update
    // only the form-managed params, keep the rest (e.g. tool=logs) intact.
    var url = new URL(window.location.href);
    // Also drop msg/action so a delete's one-off flash never rides along to the next log.
    ['log', 'date', 'lines', 'filter', 'msg', 'action'].forEach(function (p) { url.searchParams.delete(p); });
    new URLSearchParams(fd).forEach(function (v, k) { url.searchParams.set(k, v); });
    window.location.href = url.toString();
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

/**
 * Waarde in een detailvenster met een kopieerknop ernaast.
 *
 * Wat je uit een log meeneemt is bijna nooit de hele regel: het is één pad, één
 * foutmelding, één bestand:regel — iets om in een zoekbalk of een editor te
 * plakken. Met de hand selecteren gaat mis bij lange, afgebroken waarden, dus
 * krijgt elk veld dat je daadwerkelijk ergens anders nodig hebt zijn eigen knop.
 *
 * @param {string} waarde     wat er komt te staan
 * @param {string} [kopieer]  wat er wordt gekopieerd, als dat iets ANDERS is dan
 *                            wat er staat (het relatieve pad bij een volledige URL)
 * @param {string} [klasse]   extra klasse op de tekst, voor kleur/opmaak
 */
function logDetailWaarde(waarde, kopieer, klasse) {
    var tekst = (waarde === null || waarde === undefined || waarde === '') ? '-' : String(waarde);
    var teKopieren = (kopieer === undefined || kopieer === null || kopieer === '') ? tekst : String(kopieer);
    if (tekst === '-') {
        return '-';
    }
    return '<span class="log-detail-value">' +
        '<span class="log-detail-value__text' + (klasse ? ' ' + klasse : '') + '">' + escapeHtml(tekst) + '</span>' +
        '<button type="button" class="log-detail-copy" data-kopieer="' + escapeHtml(teKopieren) +
        '" title="Kopieer naar klembord"><span class="lnr lnr-copy"></span></button>' +
        '</span>';
}

/**
 * Het pad zonder schema en host, want dat is waarmee je verder werkt: ermee
 * zoeken in de broncode, of kijken of het bestand op de schijf staat. Wat al
 * relatief is, blijft zoals het is; wat geen leesbare URL is ook.
 */
function logDetailRelatiefPad(url) {
    if (!url) return '';
    try {
        return new URL(url, window.location.origin).pathname;
    } catch (e) {
        return String(url);
    }
}

// Eén luisteraar voor alle detailvensters: ze delen dezelfde knop, dus ook
// dezelfde afhandeling. De knop meldt zelf of het gelukt is — een kopieeractie
// zonder zichtbaar gevolg laat je twijfelen of je wel geklikt hebt.
document.addEventListener('click', function(e) {
    var knop = e.target.closest ? e.target.closest('.log-detail-copy') : null;
    if (!knop) return;
    e.preventDefault();
    e.stopPropagation();
    cmaCopyToClipboard(knop.dataset.kopieer).then(function() {
        knop.classList.add('is-copied');
        knop.innerHTML = '<span class="lnr lnr-checkmark-circle"></span>';
        setTimeout(function() {
            knop.classList.remove('is-copied');
            knop.innerHTML = '<span class="lnr lnr-copy"></span>';
        }, 1500);
    });
});
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
        <p style="margin: 0 0 10px;"><span class="cma-tool__strong">Log instellingen</span> (via Voorkeuren → Systeeminstellingen)</p>
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
        // Fallback: outside the CMA shell (or shell JS failed to load).
        // The button has no href, so just navigate the iframe directly.
        var target = (window.top || window).location;
        target.href = '/cma/main.php?page=preferences.php';
        return false;
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
            // De foutmelding is wat je in de broncode of een zoekmachine plakt.
            html += '<tr><th>Foutmelding</th><td>' + logDetailWaarde(entry.message, null, 'log-detail-fout') + '</td></tr>';
            html += '<tr><th>Pagina</th><td>' + logDetailWaarde(entry.source, logDetailRelatiefPad(entry.source)) + '</td></tr>';
            // Bestand + regelnummer als één brok: zo staat je cursor na plakken
            // in de editor meteen op de juiste regel.
            if (entry.file) {
                var plek = logDetailRelatiefPad(entry.file) + (entry.line ? ':' + entry.line : '');
                html += '<tr><th>Bestand</th><td>' + logDetailWaarde(plek, plek, 'log-detail-mono') + '</td></tr>';
            }
            if (entry.stackTrace) {
                html += '<tr><th>Stack trace</th><td>' +
                    '<div class="log-detail-value">' +
                    '<pre class="log-detail-json log-detail-trace log-detail-value__text">' + escapeHtml(entry.stackTrace) + '</pre>' +
                    '<button type="button" class="log-detail-copy" data-kopieer="' + escapeHtml(entry.stackTrace) + '" title="Kopieer naar klembord"><span class="lnr lnr-copy"></span></button>' +
                    '</div></td></tr>';
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
            // Het pad is waarmee je verder werkt: ermee zoeken in de broncode of
            // kijken of het bestand bestaat. De knop kopieert dus het pad, ook
            // als er een volledige URL staat.
            html += '<tr><th>URL</th><td>' + logDetailWaarde(entry.url, logDetailRelatiefPad(entry.url), 'log-detail-mono') + '</td></tr>';
            if (entry.redirect) {
                html += '<tr><th>Redirect naar</th><td>' + logDetailWaarde(entry.redirect, logDetailRelatiefPad(entry.redirect), 'log-detail-mono log-detail-ok') + '</td></tr>';
            }
            if (entry.referer) {
                // De verwijzende pagina kopieer je juist compleet: die plak je
                // in de adresbalk om te zien waar het misgaat.
                html += '<tr><th>Referer</th><td>' + logDetailWaarde(entry.referer) + '</td></tr>';
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
    <?php elseif ($selectedLog === 'unauthorized' && !empty($logContent)): ?>
    <lib-table
        id="unauthLogTable"
        filterable
        sortable
        resizable
        paginate="50"
        export-filename="unauthorized-access"
        storage-key="logreader_unauthorized"
    >
        <table class="listtable filtering" cellspacing="0" cellpadding="0">
            <thead>
                <tr class="listheader">
                    <th data-type="string" data-field="tijd" style="width: 140px;">Datum/tijd</th>
                    <th data-type="string" data-field="reden" style="width: 130px;">Reden</th>
                    <th data-type="string" data-field="inlijst" style="width: 90px;">In lijst?</th>
                    <th data-type="string" data-field="bestand">Bestand</th>
                    <th data-type="string" data-field="login" style="width: 80px;">Login</th>
                    <th data-type="string" data-field="rol" style="width: 90px;">Rol</th>
                    <th data-type="string" data-field="ip" style="width: 120px;">IP</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logContent as $row): ?>
                <tr>
                    <td data-field="tijd" style="white-space: nowrap; font-size: var(--font-size-xs);"><?= Server::htmlEncode($row['ts'] ?? '-') ?></td>
                    <td data-field="reden"><?php
                        $reason = (string)($row['reason'] ?? '');
                        $reasonLabels = [
                            'not_listed'    => 'niet in lijst',
                            'role_denied'   => 'rol geweigerd',
                            'not_logged_in' => 'niet ingelogd',
                        ];
                        echo '<span style="color: var(--color-error);">' . Server::htmlEncode($reasonLabels[$reason] ?? $reason) . '</span>';
                    ?></td>
                    <td data-field="inlijst"><?= !empty($row['listed'])
                        ? 'ja'
                        : '<span style="color: var(--color-warning);">nee</span>' ?></td>
                    <td data-field="bestand" style="font-family: monospace; font-size: var(--font-size-xs); word-break: break-all;" title="<?= Server::htmlEncode($row['uri'] ?? '') ?>"><?= Server::htmlEncode($row['file'] ?? '-') ?></td>
                    <td data-field="login"><?= Server::htmlEncode((string)($row['login'] ?? '-')) ?></td>
                    <td data-field="rol"><?= Server::htmlEncode((string)($row['role'] ?? '-')) ?></td>
                    <td data-field="ip" style="font-size: var(--font-size-xs);"><?= Server::htmlEncode($row['ip'] ?? '-') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </lib-table>

    <?php elseif (!empty($logContent)): ?>
    <div class="log-output"><?php
        $isPhpLog = ($selectedLog === 'php');
        if ($isPhpLog) {
            // Group the raw PHP error log into per-error entries (each starts
            // with a "[dd-Mon-yyyy hh:mm:ss ...]" timestamp) so we can classify
            // and colour each entry. Failed SQL is logged by Database::logError
            // with a leading "[SQL ERROR]" marker — those, plus PHP
            // Fatal/Parse/Uncaught errors, render as red rows.
            $entries = [];
            $current = '';
            foreach ($logContent as $line) {
                if (preg_match('/^\[\d{2}-\w{3}-\d{4}\s+\d{2}:\d{2}:\d{2}/', $line) && $current !== '') {
                    $entries[] = $current;
                    $current = '';
                }
                $current .= $line;
            }
            if ($current !== '') {
                $entries[] = $current;
            }
            foreach ($entries as $entry) {
                // Classify by the most severe keyword present.
                if (preg_match('/\[SQL ERROR\]|PHP Fatal error|PHP Parse error|Uncaught|Database Error/i', $entry)) {
                    $lvl = 'error';
                } elseif (preg_match('/\[SQL WARN\]|PHP Warning|PHP Deprecated/i', $entry)) {
                    $lvl = 'warn';
                } else {
                    $lvl = 'info';
                }
                echo '<div class="log-entry log-entry--' . $lvl . '">' . Server::htmlEncode($entry) . '</div>';
            }
        } else {
            foreach ($logContent as $line) {
                echo Server::htmlEncode($line);
            }
        }
    ?></div>
    <?php elseif (empty($error) && empty($flashMessage)): ?>
    <?php
        // Show exactly where we looked — otherwise "no entries" is indistinguishable
        // from "wrong path / file elsewhere" (e.g. after a logs-dir rename).
        $lookedIn = !empty($currentLog['isDatabase'])
            ? 'database-tabel tblCMAJavascriptErrors'
            : (!empty($currentLog['path']) ? str_replace('\\', '/', $currentLog['path']) : '(geen pad geconfigureerd)');
        $existsNote = (!empty($currentLog['path']) && !file_exists($currentLog['path'])) ? ' (bestand bestaat nog niet)' : '';
    ?>
    <lib-message type="info">Geen log entries gevonden.<br>
        <span style="font-size: var(--font-size-xs, 12px); color: var(--text-muted, #888);">Gezocht in: <code><?= Server::htmlEncode($lookedIn) ?></code><?= Server::htmlEncode($existsNote) ?></span>
    </lib-message>
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
        // De naam is bij een trage query de query zelf — precies wat je in een
        // editor of query-tool plakt om hem na te lopen.
        html += '<tr><th>Naam</th><td>' + logDetailWaarde(entry.name) + '</td></tr>';
        html += '<tr><th>ms</th><td>' + (entry.ms !== undefined ? entry.ms.toFixed(1) : '-') + '</td></tr>';
        if (entry.ctx) {
            var ctxJson = JSON.stringify(entry.ctx, null, 2);
            html += '<tr><th>Context</th><td>' +
                '<div class="log-detail-value">' +
                '<pre class="log-detail-json log-detail-value__text">' + escapeHtml(ctxJson) + '</pre>' +
                '<button type="button" class="log-detail-copy" data-kopieer="' + escapeHtml(ctxJson) + '" title="Kopieer naar klembord"><span class="lnr lnr-copy"></span></button>' +
                '</div></td></tr>';
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
.log-entry {
    padding: 6px 10px;
    margin: 0 0 6px;
    border-left: 3px solid transparent;
    border-radius: 3px;
}
.log-entry--error {
    background: var(--color-error-bg, rgba(224, 31, 61, 0.08));
    border-left-color: var(--color-error, #e01f3d);
    color: var(--color-error, #e01f3d);
}
.log-entry--warn {
    background: var(--color-warning-bg, rgba(230, 162, 60, 0.08));
    border-left-color: var(--color-warning, #e6a23c);
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
/* De detailvensters tonen stacktraces en JSON: op de standaardbreedte van
   size="large" (640px) breekt elke regel en scroll je jezelf suf. De component
   biedt --lib-dialog-max-width als haak; 1100px past op elk scherm dat de CMA
   aankan en laat een stackregel in één keer lezen. */
#jsErrorDetailDialog {
    --lib-dialog-max-width: 1100px;
}
/* En in de hoogte: het venster mag 90vh worden, dus laat de stacktrace die ruimte
   ook gebruiken in plaats van in een blok van 300px te scrollen. */
#jsErrorDetailDialog .log-detail-json {
    max-height: 60vh;
}
.log-detail-trace {
    font-size: var(--font-size-xs);
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

/* Kopieerknop bij een detailregel. Hij staat rechts van de waarde en pas als de
   regel de volle breedte heeft, zodat een lange URL niet om de knop heen hoeft
   te breken. De knop blijft altijd zichtbaar: verschijnen-bij-hover kost een
   ontdekking die deze knop juist moet besparen. */
.log-detail-value {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    justify-content: space-between;
}
.log-detail-value__text {
    min-width: 0;
    word-break: break-all;
}
.log-detail-copy {
    flex: 0 0 auto;
    padding: 2px 8px;
    border: 1px solid var(--border-color, #ddd);
    border-radius: 4px;
    background: var(--bg-surface, #f5f5f5);
    color: var(--text-muted, #777);
    font-size: var(--font-size-xs);
    line-height: 1.6;
    cursor: pointer;
    white-space: nowrap;
}
.log-detail-copy:hover {
    color: var(--text-primary, #222);
    border-color: var(--text-muted, #777);
}
.log-detail-copy.is-copied {
    color: var(--color-success, #2e7d32);
    border-color: var(--color-success, #2e7d32);
}
.log-detail-mono {
    font-family: monospace;
}
.log-detail-ok {
    color: var(--color-success, #2e7d32);
}
.log-detail-fout {
    color: var(--color-error, #c62828);
    font-weight: 600;
}
.empty-value {
    color: var(--text-muted, #999);
    font-style: italic;
}
</style>

</body>
</html>
