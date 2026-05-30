<?php
/**
 * deploy_status.php — read-only JSON endpoint that summarises the last
 * deploy run from logs/deploy.log. Designed to be polled remotely
 * (curl, monitoring, AI assistant) to confirm whether a deploy actually
 * succeeded without SSH-ing to the box.
 *
 * URL:    https://<host>/cma/tools/deploy_status.php?key=<DEPLOY_SECRET>
 * Auth:   DEPLOY_SECRET (?key= query parameter, timing-safe compare).
 *         Same secret as deploy_webhook_standalone.php — no extra config.
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
 * Sample in-progress response (deploy started, not yet ended):
 *   {"ok": true, "status": "RUNNING", "branch":"main", "commit":"...",
 *    "started_at":"...", "age_seconds":18, "running": true}
 *
 * Sample failure modes:
 *   401  — bad / missing ?key
 *   404  — deploy.log not found
 *   200  — but {"ok": false, "error": "no completed deploy in log"}
 */

declare(strict_types=1);

// Self-contained (no platform bootstrap) so this works even when CMA is
// half-broken — same rationale as deploy_webhook_standalone.php.

$siteRoot = dirname(__DIR__, 2);

$envRead = static function (string $key) use ($siteRoot): string {
    $v = getenv($key);
    if ($v !== false && $v !== '') { return (string)$v; }
    if (!empty($_ENV[$key])) { return (string)$_ENV[$key]; }
    foreach (['.env.production', '.env.acceptance', '.env.test', '.env.development', '.env.local', '.env'] as $f) {
        $path = $siteRoot . '/' . $f;
        if (!is_file($path)) { continue; }
        foreach ((array)file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            if (preg_match('/^\s*' . preg_quote($key, '/') . '\s*=\s*["\']?([^"\'\r\n#]+)/', $line, $m)) {
                return trim($m[1]);
            }
        }
    }
    return '';
};

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$secret = $envRead('DEPLOY_SECRET');
$given  = (string)($_GET['key'] ?? '');
if ($secret === '' || !hash_equals($secret, $given)) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'invalid or missing ?key']);
    return;
}

$logFile = $envRead('DEPLOY_LOG_FILE');
if ($logFile === '') {
    $logFile = $siteRoot . '/logs/deploy.log';
}

if (!is_file($logFile)) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'deploy.log not found', 'path' => $logFile]);
    return;
}

// Read last ~16KB — far more than one banner-bracketed deploy needs,
// but plenty to find the last "deploy ended" line and capture a useful
// tail for the operator.
$size      = filesize($logFile) ?: 0;
$chunkSize = (int)min($size, 16 * 1024);
$tail      = '';
if ($chunkSize > 0) {
    $fh = @fopen($logFile, 'rb');
    if ($fh) {
        @fseek($fh, -$chunkSize, SEEK_END);
        $tail = (string)stream_get_contents($fh);
        @fclose($fh);
    }
}
$lines = preg_split('/\r?\n/', $tail) ?: [];

// Walk backwards looking for the most recent banner lines.
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
        // Stop once we've found a started-banner OLDER-or-equal than the
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
    http_response_code(200);
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
