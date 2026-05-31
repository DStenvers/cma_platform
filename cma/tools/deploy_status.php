<?php
/**
 * deploy_status.php — read-only JSON endpoint that summarises the last
 * deploy run from logs/deploy.log. Standalone: only uses standard PHP
 * (no Composer autoload, no platform bootstrap, no .env reader). Works
 * even when CMA / vendor is completely broken.
 *
 * URL:    https://<host>/cma/tools/deploy_status.php
 * Auth:   NONE. Status + commit-SHA + branch + timestamp are not
 *         sensitive; commit-SHAs are already in public git history,
 *         branch names too, and deploy-state is observable anyway by
 *         hitting the site. log_tail contains pipeline output (git
 *         pull, composer update) which by convention contains no
 *         secrets.
 * Output: application/json; charset=utf-8
 *
 * Sample success response:
 *   {
 *     "ok":              true,
 *     "status":          "OK",
 *     "branch":          "main",
 *     "commit":          "c4ed4d5",
 *     "ended_at":        "2026-05-30 14:23:14",
 *     "duration_seconds":13,
 *     "age_seconds":     312,
 *     "running":         false,
 *     "log_tail":        "...last 40 lines of deploy.log..."
 *   }
 *
 * In-progress:
 *   {"ok": true, "status": "RUNNING", "branch":"main", "commit":"...",
 *    "started_at":"...", "age_seconds":18, "running": true}
 *
 * Failure modes:
 *   404  — {"ok": false, "error": "deploy.log not found"}
 *   500  — {"ok": false, "error": "log file unreadable"} (perms / lock)
 *   200  — {"ok": false, "error": "no completed deploy in log"}
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// Log file lives at <site-root>/logs/deploy.log. dirname(__DIR__, 2)
// resolves: __DIR__ = cma/tools, parent = cma, parent of parent = site root.
$logFile = dirname(__DIR__, 2) . '/logs/deploy.log';

if (!is_file($logFile)) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'deploy.log not found', 'path' => $logFile]);
    return;
}

// Read last ~16KB — plenty for one banner-bracketed deploy + a useful tail.
$size      = filesize($logFile) ?: 0;
$chunkSize = (int)min($size, 16 * 1024);
$tail      = '';
if ($chunkSize > 0) {
    // @ on fopen prevents a PHP warning from leaking into the JSON response,
    // but we MUST distinguish "fopen failed" from "log has no banner" — the
    // pre-v1.19.6 version returned "no completed deploy in log" in both
    // cases, masking a permissions issue as an empty-log status.
    $fh = @fopen($logFile, 'rb');
    if (!$fh) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'log file unreadable', 'path' => $logFile]);
        return;
    }
    @fseek($fh, -$chunkSize, SEEK_END);
    $tail = (string)stream_get_contents($fh);
    @fclose($fh);
}
$lines = preg_split('/\r?\n/', $tail) ?: [];

// Walk backwards looking for the most recent banner lines (format defined
// by deploy_webhook_standalone.php — kept in sync there).
$endedAt = null; $endedStatus = null; $endedBranch = null; $endedCommit = null;
$startedAt = null; $startedBranch = null; $startedCommit = null;
foreach (array_reverse($lines) as $line) {
    if ($endedAt === null
        && preg_match('/^deploy ended:\s+(\S+ \S+)\s+(\S+)\s+\(commit\s+(\S+),\s+(OK|FAILED)\)/', $line, $m)) {
        $endedAt     = $m[1];
        $endedBranch = $m[2];
        $endedCommit = $m[3];
        $endedStatus = $m[4];
        continue;
    }
    if (preg_match('/^deploy started:\s+(\S+ \S+)\s+(\S+)\s+\(commit\s+(\S+)/', $line, $m)) {
        $startedAt     = $m[1];
        $startedBranch = $m[2];
        $startedCommit = $m[3];
        // Stop once we've found a started-banner OLDER-or-equal to the
        // ended-banner we found above; for an in-progress deploy this
        // is the started-banner without a matching end.
        if ($endedAt === null || $startedCommit !== $endedCommit) {
            break;
        }
    }
}

$result = ['ok' => true, 'log_tail' => implode("\n", array_slice($lines, -40))];
$now = time();

// In-progress: started newer than ended (or no ended at all).
$isRunning = false;
if ($startedAt !== null) {
    $startedTs = strtotime($startedAt) ?: 0;
    $endedTs   = $endedAt !== null ? (strtotime($endedAt) ?: 0) : 0;
    if ($endedAt === null || $startedTs > $endedTs) {
        $isRunning = true;
    }
}

if ($isRunning) {
    $result['status']      = 'RUNNING';
    $result['branch']      = $startedBranch;
    $result['commit']      = $startedCommit;
    $result['started_at']  = $startedAt;
    $result['age_seconds'] = max(0, $now - ((int)strtotime((string)$startedAt) ?: $now));
    $result['running']     = true;
    echo json_encode($result);
    return;
}

if ($endedAt === null) {
    $result['ok']    = false;
    $result['error'] = 'no completed deploy in log';
    echo json_encode($result);
    return;
}

$endedTs   = (int)strtotime($endedAt) ?: $now;
$startedTs = $startedAt !== null ? ((int)strtotime($startedAt) ?: $endedTs) : $endedTs;

$result['status']           = $endedStatus;
$result['branch']           = $endedBranch;
$result['commit']           = $endedCommit;
$result['ended_at']         = $endedAt;
$result['duration_seconds'] = max(0, $endedTs - $startedTs);
$result['age_seconds']      = max(0, $now - $endedTs);
$result['running']          = false;

echo json_encode($result);
