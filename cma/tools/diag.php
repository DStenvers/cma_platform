<?php
/**
 * diag.php - Server-side diagnostic dump for cma_platform consumers.
 *
 * Designed to work even when site bootstrap is partly broken — does not
 * depend on cma_platform's Bootstrap, just on PHP being able to start
 * and find this file. If autoload is available it uses it; otherwise it
 * degrades gracefully and reports the missing pieces.
 *
 * URL on consumer sites: https://<host>/cma/tools/diag.php?key=<DEPLOY_SECRET>
 *
 * Auth: requires the same secret as the deploy webhook (DEPLOY_SECRET in
 * .env). Without the right key, returns 403. We deliberately reuse
 * DEPLOY_SECRET so there's no extra env var to manage.
 *
 * Output: plain text, designed to be pasted back as-is for analysis.
 * No secrets are echoed (DEPLOY_SECRET, API keys etc. are masked).
 */

declare(strict_types=1);

// =========================================================================
// Auth — read DEPLOY_SECRET from .env directly so this works even when
// dotenv loading via cma_platform Bootstrap has failed.
// =========================================================================
$siteRoot = dirname(__DIR__, 2);

$resolveSecret = static function (string $siteRoot): string {
    foreach (['DEPLOY_SECRET'] as $key) {
        $v = getenv($key);
        if ($v !== false && $v !== '') return $v;
        if (!empty($_ENV[$key])) return (string)$_ENV[$key];
    }
    // Manual .env scan — order matches the platform's auto-detect.
    $candidates = ['.env.production', '.env.acceptance', '.env.test', '.env.development', '.env.local', '.env'];
    foreach ($candidates as $f) {
        $path = $siteRoot . '/' . $f;
        if (!is_file($path)) continue;
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            if (preg_match('/^\s*DEPLOY_SECRET\s*=\s*["\']?([^"\'\r\n#]+)/', $line, $m)) {
                return trim($m[1]);
            }
        }
    }
    return '';
};

$secret = $resolveSecret($siteRoot);
$key    = (string)($_GET['key'] ?? '');

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');

if ($secret === '') {
    http_response_code(503);
    echo "diag.php: DEPLOY_SECRET not configured in any .env file.\n";
    echo "Set DEPLOY_SECRET in {$siteRoot}/.env.production (or appropriate .env).\n";
    return;
}

if (!hash_equals($secret, $key)) {
    http_response_code(403);
    echo "diag.php: bad or missing ?key=. Use ?key=<DEPLOY_SECRET>.\n";
    return;
}

// =========================================================================
// Helpers
// =========================================================================
$h = static function (string $title): void {
    echo "\n" . str_repeat('=', 70) . "\n=== $title\n" . str_repeat('=', 70) . "\n";
};
$kv = static function (string $k, $v): void {
    if (is_bool($v))   $v = $v ? 'true' : 'false';
    if (is_array($v))  $v = json_encode($v, JSON_UNESCAPED_SLASHES);
    if ($v === null)   $v = '(null)';
    if ($v === '')     $v = '(empty)';
    printf("  %-32s %s\n", $k . ':', (string)$v);
};
$mask = static function (?string $v): string {
    if ($v === null || $v === '') return '(empty)';
    if (strlen($v) <= 8) return '***';
    return substr($v, 0, 4) . '…' . substr($v, -4) . ' (len=' . strlen($v) . ')';
};
$tail = static function (string $path, int $lines = 30): string {
    if (!is_file($path)) return "(file does not exist: $path)";
    $size = filesize($path);
    if ($size === 0) return '(empty)';
    $f = @fopen($path, 'rb');
    if (!$f) return '(unreadable)';
    $buf = '';
    $chunk = 4096;
    $pos = $size;
    $found = 0;
    while ($pos > 0 && $found <= $lines) {
        $read = (int)min($chunk, $pos);
        $pos -= $read;
        fseek($f, $pos);
        $buf = fread($f, $read) . $buf;
        $found = substr_count($buf, "\n");
    }
    fclose($f);
    $rows = explode("\n", $buf);
    return implode("\n", array_slice($rows, -$lines));
};

// =========================================================================
// Sections
// =========================================================================

$h('REQUEST CONTEXT');
$kv('Date', date('Y-m-d H:i:s T'));
$kv('Site root', $siteRoot);
$kv('SAPI', PHP_SAPI);
$kv('PHP version', PHP_VERSION);
$kv('PHP_INT_SIZE', PHP_INT_SIZE . ' (' . (PHP_INT_SIZE === 8 ? '64-bit' : '32-bit') . ')');
$kv('OS', PHP_OS_FAMILY . ' / ' . php_uname());
$kv('Server software', $_SERVER['SERVER_SOFTWARE'] ?? '(none)');
$kv('Request method', $_SERVER['REQUEST_METHOD'] ?? '(none)');
$kv('Host header', $_SERVER['HTTP_HOST'] ?? '(none)');
$kv('Remote addr', $_SERVER['REMOTE_ADDR'] ?? '(none)');
$kv('Server addr', $_SERVER['SERVER_ADDR'] ?? '(none)');

$h('PHP CONFIG');
$kv('Loaded php.ini', php_ini_loaded_file() ?: '(none)');
$kv('Additional .ini files', php_ini_scanned_files() ?: '(none)');
$kv('error_log', ini_get('error_log'));
$kv('error_log writable', is_writable(ini_get('error_log') ?: '/dev/null') ? 'yes' : 'NO');
$kv('log_errors', ini_get('log_errors'));
$kv('display_errors', ini_get('display_errors'));
$kv('error_reporting', error_reporting());
$kv('memory_limit', ini_get('memory_limit'));
$kv('max_execution_time', ini_get('max_execution_time'));
$kv('extension_dir', ini_get('extension_dir'));
$kv('extension_dir exists', is_dir(ini_get('extension_dir')) ? 'yes' : 'NO');
$kv('include_path', ini_get('include_path'));
$kv('opcache.enable', ini_get('opcache.enable') ?: '(unset)');
$kv('opcache loaded', extension_loaded('Zend OPcache') ? 'yes' : 'no');

$h('LOADED EXTENSIONS');
$exts = get_loaded_extensions();
sort($exts);
foreach (array_chunk($exts, 4) as $row) {
    echo '  ' . implode('  ', array_map(fn($x) => str_pad($x, 16), $row)) . "\n";
}
$wantExt = ['curl', 'gd', 'mbstring', 'openssl', 'pdo_odbc', 'pdo_sqlite', 'odbc', 'sodium', 'fileinfo', 'intl', 'exif', 'zip'];
echo "\n  Project-required check:\n";
foreach ($wantExt as $e) {
    $kv('  ' . $e, extension_loaded($e) ? 'OK' : 'MISSING');
}

$h('FILES & PATHS');
$paths = [
    'composer.json'        => $siteRoot . '/composer.json',
    'composer.lock'        => $siteRoot . '/composer.lock',
    'vendor/autoload.php'  => $siteRoot . '/vendor/autoload.php',
    'vendor/installed.json' => $siteRoot . '/vendor/composer/installed.json',
    '_bootstrap.php'       => $siteRoot . '/_bootstrap.php',
    '_bootstrap_wrapper.php' => $siteRoot . '/_bootstrap_wrapper.php',
    'index.php'            => $siteRoot . '/index.php',
    'web.config'           => $siteRoot . '/web.config',
    '.env'                 => $siteRoot . '/.env',
    '.env.production'      => $siteRoot . '/.env.production',
    '.env.local'           => $siteRoot . '/.env.local',
    'logs/'                => $siteRoot . '/logs',
    'logs/php_errors.log'  => $siteRoot . '/logs/php_errors.log',
    'logs/deploy.log'      => $siteRoot . '/logs/deploy.log',
    'cache/'               => $siteRoot . '/cache',
    'sessions/'            => $siteRoot . '/sessions',
    'db/'                  => $siteRoot . '/db',
    'cma/'                 => $siteRoot . '/cma',
    'library/'             => $siteRoot . '/library',
    'module/'              => $siteRoot . '/module',
];
foreach ($paths as $label => $p) {
    $exists = file_exists($p);
    $type   = $exists ? (is_dir($p) ? 'dir' : 'file') : '-';
    $size   = ($exists && is_file($p)) ? filesize($p) : '';
    $writable = $exists ? (is_writable($p) ? 'rw' : 'r-') : '-';
    printf("  %-26s %-7s %-3s %s\n", $label, $type, $writable, $size);
}

$h('PLATFORM PACKAGE (composer)');
$instJson = $siteRoot . '/vendor/composer/installed.json';
if (is_file($instJson)) {
    $data = json_decode(file_get_contents($instJson), true);
    $packages = $data['packages'] ?? $data;
    foreach ($packages as $pkg) {
        if (($pkg['name'] ?? '') === 'stenversonline/platform') {
            $kv('Platform version', $pkg['version'] ?? '(unset)');
            $kv('Platform reference', $pkg['source']['reference'] ?? '(unset)');
            $kv('Install time', $pkg['install-path'] ?? '');
            break;
        }
    }
} else {
    echo "  vendor/composer/installed.json not present — composer install never ran.\n";
}

$h('GIT STATE');
chdir($siteRoot);
$gitOk = is_dir($siteRoot . '/.git');
$kv('.git/ exists', $gitOk ? 'yes' : 'NO');
if ($gitOk) {
    $cmd = 'cmd /c "cd /d ' . escapeshellarg($siteRoot) . ' && git rev-parse --abbrev-ref HEAD 2>&1"';
    $branch = trim((string)shell_exec($cmd));
    $kv('Current branch', $branch);
    $cmd = 'cmd /c "cd /d ' . escapeshellarg($siteRoot) . ' && git log --oneline -3 2>&1"';
    echo "  Recent commits:\n";
    foreach (explode("\n", (string)shell_exec($cmd)) as $line) echo "    $line\n";
    $cmd = 'cmd /c "cd /d ' . escapeshellarg($siteRoot) . ' && git remote -v 2>&1"';
    echo "  Remotes:\n";
    foreach (explode("\n", (string)shell_exec($cmd)) as $line) echo "    " . preg_replace('/(github_pat_[A-Za-z0-9_]+)/', '***PAT***', $line) . "\n";
}

$h('ENV VARS (relevant + masked)');
$envKeys = ['APP_ENVIRONMENT', 'CLOSED_SITE', 'DB_TYPE', 'DEPLOY_BRANCH', 'DEPLOY_SECRET',
            'MAIL_FROM', 'MAIL_FROM_NAME', 'SMTP_HOST', 'SMTP_PORT', 'SMTP_USER',
            'EMAIL_LOG_ENABLED', 'COOKING_DEMO', 'LLM_URL', 'LLM_MODEL',
            'GOOGLE_CSE_KEY', 'GOOGLE_CSE_CX', 'PEXELS_API_KEY', 'PIXABAY_API_KEY'];
$secretLike = ['DEPLOY_SECRET', 'SMTP_PASS', 'GOOGLE_CSE_KEY', 'PEXELS_API_KEY', 'PIXABAY_API_KEY', 'SSO_CLIENT_SECRET'];
foreach ($envKeys as $k) {
    $v = $_ENV[$k] ?? getenv($k);
    if ($v === false) $v = '';
    if (in_array($k, $secretLike, true)) $v = $mask((string)$v);
    $kv($k, $v);
}

$h('LOGS — logs/php_errors.log (last 30 lines)');
echo $tail($siteRoot . '/logs/php_errors.log', 30) . "\n";

$h('LOGS — logs/deploy.log (last 30 lines)');
echo $tail($siteRoot . '/logs/deploy.log', 30) . "\n";

$h('SERVER VARS ($_SERVER, filtered)');
$srvKeys = ['SERVER_SOFTWARE', 'SERVER_NAME', 'SERVER_PORT', 'HTTPS', 'REQUEST_SCHEME',
            'DOCUMENT_ROOT', 'SCRIPT_FILENAME', 'SCRIPT_NAME', 'PHP_SELF',
            'HTTP_HOST', 'HTTP_X_ROUTE_PATH', 'HTTP_X_ORIGINAL_FILE',
            'GATEWAY_INTERFACE', 'WINDIR', 'SystemRoot'];
foreach ($srvKeys as $k) $kv($k, $_SERVER[$k] ?? '(unset)');

$h('END');
echo "Diagnostic complete. Paste this entire output back for analysis.\n";
