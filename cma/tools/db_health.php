<?php
/**
 * db_health.php — self-contained database-verbinding health check.
 *
 * Test elke named DB-connectie (data / rep / users) APART, los van de platform
 * bootstrap. Daardoor werkt deze tool juist wél als `initConnections` / het
 * bootstrappen zelf zou crashen op een kapotte verbinding (klassiek: de Access
 * "volatile Ace DSN" registry-fout op 'rep'/repository.mdb onder IIS).
 *
 * URL:    /cma/tools/db_health.php   (optioneel ?format=json)
 * Auth:   geen — leest alleen verbindingsstatus, geen data, geen secrets in output.
 * Output: text/plain (default) of application/json.
 *
 * Geen Composer-autoload, geen platform-bootstrap, geen .env-reader van de
 * library — net als deploy_status.php, zodat het blijft werken als vendor/.env
 * stuk is.
 */

declare(strict_types=1);

$siteRoot = dirname(__DIR__, 2);

/** Minimal .env reader (KEY=VALUE, eerste match wint). */
$envRead = static function (string $key, string $default = '') use ($siteRoot): string {
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
    return $default;
};

$dbType = strtolower($envRead('DB_TYPE', 'access'));

// Bouw dezelfde DSNs als app.php.
$connections = [];
if ($dbType === 'access') {
    $driver = $envRead('DB_DRIVER', 'Microsoft Access Driver (*.mdb, *.accdb)');
    $base = $siteRoot . DIRECTORY_SEPARATOR . 'db' . DIRECTORY_SEPARATOR;
    $connections = [
        'data'  => 'odbc:Driver={' . $driver . '};DBQ=' . $base . 'main.mdb',
        'rep'   => 'odbc:Driver={' . $driver . '};DBQ=' . $base . 'repository.mdb',
        'users' => 'odbc:Driver={' . $driver . '};DBQ=' . $base . 'CMAusers.mdb',
    ];
} else {
    $host = $envRead('DB_HOST', 'localhost');
    $name = $envRead('DB_NAME', '');
    $charset = $envRead('DB_CHARSET', 'utf8mb4');
    $dsn = $dbType . ':host=' . $host . ($name ? ';dbname=' . $name : '') . ($charset ? ';charset=' . $charset : '');
    $connections = ['data' => $dsn, 'rep' => $dsn, 'users' => $dsn];
}

/** Detecteer de bekende Access "volatile DSN" registry-fout en geef remediatie. */
$diagnose = static function (string $err): ?string {
    if (stripos($err, 'volatile') !== false
        || (stripos($err, 'Ace DSN') !== false)
        || stripos($err, 'registersleutel') !== false
        || stripos($err, 'registry key') !== false) {
        return 'Access ODBC "volatile Ace DSN" registry-probleem. Fix (IIS): '
            . '1) App-pool Advanced Settings -> Load User Profile = True, daarna recyclen. '
            . '2) Registry Full Control voor de app-pool identity op '
            . 'HKLM\\SOFTWARE\\Microsoft\\Office\\<ver>\\Access Connectivity Engine\\Engines (incl. ODBC). '
            . '3) Controleer 32/64-bit ACE-driver matcht de app-pool bitness. '
            . 'Zie platform_install.md > Veelgestelde vragen.';
    }
    return null;
};

$results = [];
$allOk = true;
foreach ($connections as $cname => $dsn) {
    if (extension_loaded('pdo') === false) {
        $results[$cname] = ['ok' => false, 'error' => 'PDO-extensie niet geladen'];
        $allOk = false;
        continue;
    }
    if (DIRECTORY_SEPARATOR === '\\' && stripos($dsn, 'odbc:') === 0) {
        $dsn = str_replace('/', '\\', $dsn);
    }
    $t0 = microtime(true);
    try {
        $pdo = new PDO($dsn, null, null, [PDO::ATTR_TIMEOUT => 5]);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $ms = (int)round((microtime(true) - $t0) * 1000);
        $results[$cname] = ['ok' => true, 'connect_ms' => $ms];
        $pdo = null;
    } catch (\Throwable $e) {
        $allOk = false;
        $entry = ['ok' => false, 'error' => $e->getMessage()];
        $hint = $diagnose($e->getMessage());
        if ($hint !== null) { $entry['remediation'] = $hint; }
        $results[$cname] = $entry;
    }
}

$payload = [
    'ok' => $allOk,
    'db_type' => $dbType,
    'site_root' => $siteRoot,
    'connections' => $results,
];

if (($_GET['format'] ?? '') === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    return;
}

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');
echo "=== DB health check ===\n\n";
echo "db_type : {$dbType}\n";
echo "overall : " . ($allOk ? 'OK' : 'PROBLEM') . "\n\n";
foreach ($results as $cname => $r) {
    if ($r['ok']) {
        echo sprintf("  [OK]   %-6s (%d ms)\n", $cname, $r['connect_ms'] ?? 0);
    } else {
        echo sprintf("  [FAIL] %-6s %s\n", $cname, $r['error']);
        if (!empty($r['remediation'])) {
            echo "         -> " . $r['remediation'] . "\n";
        }
    }
}
echo "\nNB: de webshop gebruikt alleen 'data'. Sinds platform 1.20.x opent de\n";
echo "bootstrap connecties lazy, dus een kapotte 'rep' breekt webshop-pagina's niet meer.\n";
