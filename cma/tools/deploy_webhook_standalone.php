<?php
/**
 * deploy_webhook_standalone.php — single-file GitHub deploy webhook.
 *
 * Drop this file into the repo root of any site you want auto-deployed
 * from a GitHub push. No composer, no framework, no autoloader required.
 *
 * GitHub setup (Repo → Settings → Webhooks → Add webhook):
 *   Payload URL  : https://<host>/deploy_webhook_standalone.php
 *   Content type : application/json
 *   Secret       : same value as DEPLOY_SECRET on the server
 *   Events       : "Just the push event"
 *
 * Server setup (in the repo root, next to this script):
 *   1. Drop a `.env` (or `.env.production`) next to this file with:
 *        DEPLOY_SECRET=<copy-from-github-webhook-settings>
 *        DEPLOY_BRANCH=main           # optional, default "main"
 *        DEPLOY_PIPELINE=git pull --ff-only origin {branch}
 *      A few example pipelines per stack:
 *        Static / WP / plain PHP : git pull --ff-only origin {branch}
 *        Composer-based PHP      : git pull --ff-only origin {branch}; composer install --no-dev --optimize-autoloader
 *        Node frontend           : git pull --ff-only origin {branch}; npm ci; npm run build
 *      Separate commands with `;` — pipeline aborts on the first non-zero exit.
 *   2. (IIS only) set DEPLOY_RECYCLE_TOUCH=web.config if not already the
 *      default — touching the file triggers an app-pool recycle so PHP
 *      picks up the new code. Set to "-" to skip recycle on stacks that
 *      don't need it (static sites, nginx + non-PHP).
 *   3. Make sure `git` (and any pipeline commands you use, e.g. composer
 *      or npm) are on the PATH of the user running the web server.
 *
 * Auth: HMAC-SHA256 of the request body using DEPLOY_SECRET, verified
 * against the X-Hub-Signature-256 header GitHub sends. Bad signature
 * → 403. Non-push events / wrong branch → 204 (logged-and-skipped, so
 * a webhook fanning out to several boxes / branches doesn't deploy
 * everywhere on every push).
 *
 * Output goes to <site_root>/logs/deploy.log. Each deploy is bracketed
 * by banner lines so scrolling the log finds run boundaries instantly:
 *   ------------------------------------------------------------
 *   deploy started: 2026-05-19 14:23:01 main (commit abc1234 by ...)
 *   ------------------------------------------------------------
 *     [timestamped per-command log lines]
 *   ------------------------------------------------------------
 *   deploy ended:   2026-05-19 14:23:14 main (commit abc1234, OK)
 *   ------------------------------------------------------------
 *
 * The script returns 202 to GitHub immediately, then runs the pipeline
 * async via fastcgi_finish_request() so GitHub doesn't record the
 * delivery as "EOF / failed" when composer takes 30+ seconds.
 *
 * Config (env or .env keys, in priority order):
 *   DEPLOY_SECRET         GitHub-webhook secret (required)
 *   DEPLOY_BRANCH         Branch to deploy (default: main)
 *   DEPLOY_SITE_ROOT      Repo root (default: dir containing this file)
 *   DEPLOY_PIPELINE       ; -separated commands (default: git pull only)
 *   DEPLOY_RECYCLE_TOUCH  File to touch on success — defaults to
 *                         <site_root>/web.config. Set "-" to skip.
 *   DEPLOY_LOG_FILE       Log path (default: <site_root>/logs/deploy.log)
 *
 * Single file. No external dependencies. Copy anywhere.
 */
declare(strict_types=1);

// ---------------------------------------------------------------------------
// 1. Site root + .env resolver — works without any framework / autoloader.
// ---------------------------------------------------------------------------
$siteRoot = trim((string)(getenv('DEPLOY_SITE_ROOT') ?: ($_ENV['DEPLOY_SITE_ROOT'] ?? '')));
if ($siteRoot === '' || !is_dir($siteRoot)) {
    $siteRoot = __DIR__;
}
$siteRoot = rtrim((string)(realpath($siteRoot) ?: $siteRoot), '/\\');

$envRead = static function (string $key, string $default = '') use ($siteRoot): string {
    $v = getenv($key);
    if ($v !== false && $v !== '') return $v;
    if (!empty($_ENV[$key])) return (string)$_ENV[$key];
    foreach (['.env.production', '.env.acceptance', '.env.test', '.env.local', '.env'] as $f) {
        $path = $siteRoot . '/' . $f;
        if (!is_file($path)) continue;
        foreach ((array)file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            if (preg_match('/^\s*' . preg_quote($key, '/') . '\s*=\s*["\']?([^"\'\r\n#]+)/', $line, $m)) {
                return trim($m[1]);
            }
        }
    }
    return $default;
};

// ---------------------------------------------------------------------------
// 2. Logging helpers — line-timestamped writes for body, raw writes for the
//    deploy-bracket banners (so banner reads as a section header).
// ---------------------------------------------------------------------------
$logFile = $envRead('DEPLOY_LOG_FILE', $siteRoot . '/logs/deploy.log');
$logDir  = dirname($logFile);
if (!is_dir($logDir)) { @mkdir($logDir, 0755, true); }

$log = static function (string $msg) use ($logFile): void {
    @file_put_contents($logFile, '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n", FILE_APPEND);
};
$logRaw = static function (string $text) use ($logFile): void {
    @file_put_contents($logFile, $text, FILE_APPEND);
};

// ---------------------------------------------------------------------------
// 3. Auth — DEPLOY_SECRET resolution + HMAC verify on the raw POST body.
// ---------------------------------------------------------------------------
$secret = $envRead('DEPLOY_SECRET');
if ($secret === '') {
    http_response_code(503);
    $log('REJECT: DEPLOY_SECRET not configured');
    echo "Deploy not configured.\n";
    return;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo "Method not allowed.\n";
    return;
}

$payload   = (string)file_get_contents('php://input');
$signature = (string)($_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '');
$expected  = 'sha256=' . hash_hmac('sha256', $payload, $secret);
if (!hash_equals($expected, $signature)) {
    http_response_code(403);
    $log('REJECT: signature mismatch from ' . ($_SERVER['REMOTE_ADDR'] ?? '?'));
    echo "Bad signature.\n";
    return;
}

// ---------------------------------------------------------------------------
// 4. Event + branch gate. Many webhooks fan out to multiple boxes /
//    branches, so a non-push event or non-target branch is a no-op
//    (logged so it's still auditable).
// ---------------------------------------------------------------------------
$event = (string)($_SERVER['HTTP_X_GITHUB_EVENT'] ?? '');
if ($event !== 'push') {
    http_response_code(204);
    $log("SKIP: event=$event (only 'push' deploys)");
    echo "Skipped: $event\n";
    return;
}

$data    = json_decode($payload, true) ?: [];
$ref     = (string)($data['ref'] ?? '');
$branch  = $envRead('DEPLOY_BRANCH', 'main');
$wantRef = 'refs/heads/' . $branch;
if ($ref !== $wantRef) {
    http_response_code(204);
    $log("SKIP: ref=$ref (only $wantRef)");
    echo "Skipped: $ref\n";
    return;
}

$commit = substr((string)($data['after'] ?? ''), 0, 7);
$pusher = (string)($data['pusher']['name'] ?? '?');

// ---------------------------------------------------------------------------
// 5. Banner + early 202. The deploy runs async via fastcgi_finish_request
//    so GitHub doesn't time out waiting for composer/npm to finish.
// ---------------------------------------------------------------------------
$bannerLine  = str_repeat('-', 60) . "\n";
$startBanner = "\n" . $bannerLine
             . 'deploy started: ' . date('Y-m-d H:i:s') . ' ' . $branch
             . " (commit $commit by $pusher)\n"
             . $bannerLine;
$logRaw($startBanner);
$log("ACCEPTED: deploy $commit by $pusher (running async)");

http_response_code(202);
header('Content-Type: text/plain; charset=utf-8');
$earlyMsg = "Accepted ($commit by $pusher). Running async — see logs/deploy.log.\n";
header('Content-Length: ' . strlen($earlyMsg));
echo $earlyMsg;

if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
} else {
    while (ob_get_level() > 0) { @ob_end_flush(); }
    @flush();
}
ignore_user_abort(true);
@set_time_limit(300);

// ---------------------------------------------------------------------------
// 6. Pipeline. Commands are ; -separated and run in $siteRoot. {branch}
//    is substituted before execution so the operator doesn't have to
//    repeat the branch name in the pipeline string.
// ---------------------------------------------------------------------------
$isWin    = stripos(PHP_OS, 'WIN') === 0;
$pipeline = $envRead('DEPLOY_PIPELINE', 'git pull --ff-only origin {branch}');
$pipeline = strtr($pipeline, ['{branch}' => $branch]);

$commands = [];
foreach (explode(';', $pipeline) as $part) {
    $part = trim($part);
    if ($part !== '') { $commands[] = $part; }
}

$failed = false;
foreach ($commands as $cmd) {
    $log("RUN: $cmd");
    $output = [];
    $exit   = 0;
    if ($isWin) {
        exec('cmd /c "cd /d "' . $siteRoot . '" && ' . $cmd . ' 2>&1"', $output, $exit);
    } else {
        exec('cd ' . escapeshellarg($siteRoot) . ' && ' . $cmd . ' 2>&1', $output, $exit);
    }
    $text = implode("\n", $output);
    $log("EXIT: $exit\n--- output ---\n$text\n--- end ---");
    if ($exit !== 0) {
        $failed = true;
        break; // abort pipeline on first failure
    }
}

// ---------------------------------------------------------------------------
// 7. Recycle target — touch a file to trigger pool reload (IIS web.config,
//    or any tmpfile your stack watches). "-" opts out for stacks that
//    don't need it (static sites, nginx + serve-from-disk PHP).
// ---------------------------------------------------------------------------
$recycle = $envRead('DEPLOY_RECYCLE_TOUCH', $siteRoot . '/web.config');
if (!$failed && $recycle !== '-' && $recycle !== '' && is_file($recycle)) {
    @touch($recycle);
    $log("RECYCLE: touched $recycle");
}

// ---------------------------------------------------------------------------
// 8. Footer banner — mirrors the start banner so each deploy block is
//    self-bracketed and self-describing (branch + commit + status).
// ---------------------------------------------------------------------------
$status = $failed ? 'FAILED' : 'OK';
$log("$status: deploy $commit");
$logRaw($bannerLine
      . 'deploy ended:   ' . date('Y-m-d H:i:s') . ' ' . $branch
      . " (commit $commit, $status)\n"
      . $bannerLine . "\n");
