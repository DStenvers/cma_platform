<?php
/**
 * Endpoint Error Tester - Tests all CMA endpoints and reports errors
 *
 * Tests each endpoint for PHP errors, HTTP errors, and API errors.
 * Web mode: streams progress to keep the connection alive.
 * CLI mode: outputs dots/letters for progress.
 *
 * Can also run from CLI: php test_all_endpoints.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(600);

chdir(dirname(__DIR__));

$isCli = (php_sapi_name() === 'cli');
$isWeb = !$isCli;

if ($isWeb) {
    require_once __DIR__ . '/../../bootstrap.inc';
}

use App\Library\Response;
use Cma\SecurityHelper;
use Cma\ToolbarHelper;

if ($isWeb) {
    if (!SecurityHelper::isDeveloper()) {
        http_response_code(403);
        echo '<lib-message type="error">Alleen developers hebben toegang.</lib-message>';
        exit;
    }
    Response::noCache();

    // Output HTML header immediately so browser doesn't timeout
    cma_html_header('Endpoint foutcontrole');
    echo '<body class="contentbody tools">';
    ToolbarHelper::report('Endpoint foutcontrole', false, false, false, false, 'Test alle CMA endpoints op fouten');
    echo '<div id="c" class="tools">';
    echo '<div id="progress"><lib-loader active text="Endpoints testen..."></lib-loader></div>';
    // Flush to browser immediately
    if (ob_get_level()) ob_end_flush();
    flush();
}

$baseUrl = 'http://172.29.208.1/cma';

function fetchUrl($url, $timeout = 2) {
    $context = stream_context_create([
        'http' => [
            'timeout' => $timeout,
            'header' => "X-Requested-With: XMLHttpRequest\r\nX-CMA-Test-Token: cma_endpoint_test_2025\r\n",
            'ignore_errors' => true
        ]
    ]);
    return @file_get_contents($url, false, $context);
}

$formRecordIds = [];

function getRealRecordId($formName) {
    global $formRecordIds, $baseUrl;
    if (isset($formRecordIds[$formName])) return $formRecordIds[$formName];
    $listUrl = $baseUrl . '/api/form_list.php?formName=' . urlencode($formName) . '&pageSize=1';
    $response = fetchUrl($listUrl);
    if ($response !== false) {
        $json = json_decode($response, true);
        if ($json && isset($json['data']) && !empty($json['data'])) {
            $firstRecord = $json['data'][0];
            $id = $firstRecord['ID'] ?? $firstRecord['id'] ?? $firstRecord['Id'] ?? null;
            if ($id !== null) { $formRecordIds[$formName] = $id; return $id; }
        }
    }
    $formRecordIds[$formName] = null;
    return null;
}

function replaceWithRealIds($endpoint) {
    if (preg_match('/form_record\.php\?formName=([^&]+)&id=1/', $endpoint, $m)) {
        $realId = getRealRecordId($m[1]);
        if ($realId !== null) return str_replace('&id=1', '&id=' . urlencode($realId), $endpoint);
    }
    if (preg_match('/form_api\.php\?formName=([^&]+)&action=get_form&id=1/', $endpoint, $m)) {
        $realId = getRealRecordId($m[1]);
        if ($realId !== null) return str_replace('&id=1', '&id=' . urlencode($realId), $endpoint);
    }
    if (preg_match('/form_list\.php\?formName=([^&]+)&subform=[^&]+&parentId=1/', $endpoint, $m)) {
        $realId = getRealRecordId($m[1]);
        if ($realId !== null) return str_replace('&parentId=1', '&parentId=' . urlencode($realId), $endpoint);
    }
    return $endpoint;
}

// Get endpoint list via HTTP (with test token for auth bypass)
// Use short timeout and retry once — on single-threaded PHP/IIS this can deadlock
$endpointsUrl = $baseUrl . '/tools/tools_endpoint_tester.php?llm=Y';
$endpoints = fetchUrl($endpointsUrl, 15);

// If HTTP fails (self-request deadlock), build a minimal endpoint list from form definitions
if ($endpoints === false || empty($endpoints)) {
    $endpoints = '';
    // Scan form definitions for a basic list
    $formDirs = [
        dirname(__DIR__, 2) . '/assets/forms/definitions',
        dirname(__DIR__, 3) . '/assets/forms'
    ];
    foreach ($formDirs as $dir) {
        if (!is_dir($dir)) continue;
        foreach (glob($dir . '/*.json') as $file) {
            $name = basename($file, '.json');
            if (strpos($name, 'schema') !== false || strpos($name, 'old_') === 0) continue;
            $endpoints .= "/cma/api/form_list.php?formName={$name}\n";
        }
    }
    // Add root PHP files
    foreach (glob(dirname(__DIR__, 2) . '/*.php') as $file) {
        $name = basename($file);
        if (in_array($name, ['login.php', 'logout.php', 'index.php', 'minify.php', '404.php'])) continue;
        $endpoints .= "/cma/{$name}\n";
    }
}

if (empty($endpoints)) {
    if ($isWeb) {
        echo '<script>document.getElementById("progress").remove();</script>';
        echo '<lib-message type="error">Kan endpoint lijst niet ophalen van tools_endpoint_tester.php</lib-message>';
        echo '</div></body></html>';
    } else {
        echo "ERROR: Could not fetch endpoint list\n";
    }
    exit(1);
}

$endpointList = array_filter(array_map('trim', explode("\n", $endpoints)));
$endpointList = array_filter($endpointList, function($line) {
    return strpos($line, '<script>') === false && strpos($line, '</script>') === false && strpos($line, '/cma/') !== false;
});
$endpointList = array_values($endpointList);
$totalEndpoints = count($endpointList);

$skipPatterns = [
    '/default.php', '/main.php', '/dashboard.php', '/tools.php', '/preferences.php',
    '/wizards/', '/migrations/', '/tools/', 'formName=old_', 'form=old_',
    '&subform=', 'formName=aanwezigheid', 'formName=rooster_aanwezigheid',
];

$errors = [];
$tested = 0;
$skipped = 0;

// Update progress in web mode
if ($isWeb) {
    echo '<script>document.getElementById("progress").querySelector("lib-loader").setAttribute("text", "0 / ' . $totalEndpoints . ' endpoints testen...");</script>';
    flush();
}

foreach ($endpointList as $i => $endpoint) {
    $shouldSkip = false;
    foreach ($skipPatterns as $pattern) { if (strpos($endpoint, $pattern) !== false) { $shouldSkip = true; break; } }
    if ($shouldSkip) { $skipped++; continue; }

    $endpoint = replaceWithRealIds($endpoint);
    $url = 'http://172.29.208.1' . $endpoint;
    $response = fetchUrl($url, 3); // reduced timeout for faster completion
    $httpCode = 200;
    $error = '';

    // PHP 8.5 deprecated the locally-scoped $http_response_header magic
    // variable in favour of http_get_last_response_headers(). Use the
    // function when available (PHP 8.5+), fall back to the legacy
    // variable on older runtimes.
    $headers = function_exists('http_get_last_response_headers')
        ? (http_get_last_response_headers() ?: [])
        : ($http_response_header ?? []);
    foreach ($headers as $header) {
        if (preg_match('/^HTTP\/\S+\s+(\d+)/', $header, $m)) $httpCode = (int)$m[1];
    }

    if ($response === false) $error = error_get_last()['message'] ?? 'Unknown error';
    $tested++;

    if ($error) {
        $errors[] = ['url' => $endpoint, 'error' => "CURL Error: $error", 'type' => 'curl'];
        if ($isCli) echo "X";
    } elseif (strpos($response, 'forcelogin=J') !== false) {
        if ($isCli) echo "L";
    } elseif (preg_match('/(Fatal error|Parse error|Warning:|Notice:|Deprecated:)[^<]*/i', $response, $matches)) {
        $errors[] = ['url' => $endpoint, 'error' => substr($matches[0], 0, 200), 'type' => 'php'];
        if ($isCli) echo "E";
    } elseif (strpos($response, '"success":false') !== false || strpos($response, '"error"') !== false) {
        $json = json_decode($response, true);
        if ($json && isset($json['error'])) {
            $errors[] = ['url' => $endpoint, 'error' => $json['error'], 'type' => 'api'];
            if ($isCli) echo "A";
        }
    } elseif ($httpCode >= 400) {
        $errors[] = ['url' => $endpoint, 'error' => "HTTP $httpCode", 'type' => 'http'];
        if ($isCli) echo "H";
    } else {
        if ($isCli) echo ".";
    }

    // Stream progress every 10 endpoints to keep connection alive
    if ($isWeb && ($tested % 10 === 0 || $i === $totalEndpoints - 1)) {
        echo '<script>document.getElementById("progress").querySelector("lib-loader").setAttribute("text", "' . $tested . ' / ' . $totalEndpoints . ' endpoints getest... (' . count($errors) . ' fouten)");</script>' . "\n";
        flush();
    }

    usleep(5000);
}

// Group errors
$groupedErrors = [];
foreach ($errors as $err) {
    $key = $err['error'];
    if (!isset($groupedErrors[$key])) $groupedErrors[$key] = ['error' => $err['error'], 'type' => $err['type'], 'urls' => []];
    $groupedErrors[$key]['urls'][] = $err['url'];
}
usort($groupedErrors, fn($a, $b) => count($b['urls']) - count($a['urls']));

// =========================================================================
// Output results
// =========================================================================
if ($isCli) {
    echo "\n\nTested: $tested | Skipped: $skipped | Errors: " . count($errors) . "\n\n";
    if (count($errors) === 0) { echo "No errors found!\n"; exit(0); }
    echo "=== ERRORS BY OCCURRENCE ===\n\n";
    foreach ($groupedErrors as $group) {
        $count = count($group['urls']);
        echo "[$count occurrences] [{$group['type']}]\n";
        echo "Error: {$group['error']}\n";
        echo "URLs:\n";
        foreach (array_slice($group['urls'], 0, 5) as $url) echo "  - $url\n";
        if ($count > 5) echo "  ... and " . ($count - 5) . " more\n";
        echo "\n";
    }
    exit(count($errors) > 0 ? 1 : 0);
}

// Web output - replace progress with results
echo '<script>document.getElementById("progress").remove();</script>';

$errorCount = count($errors);
if ($errorCount === 0) {
    echo '<lib-message type="success">Alle ' . $tested . ' endpoints OK (' . $skipped . ' overgeslagen)</lib-message>';
} else {
    echo '<lib-message type="error">' . $errorCount . ' fouten gevonden in ' . $tested . ' endpoints (' . $skipped . ' overgeslagen)</lib-message>';
}

echo '<h3>Samenvatting</h3>';
echo '<table class="listtable"><tbody>';
echo '<tr><td><strong>Getest</strong></td><td>' . $tested . '</td></tr>';
echo '<tr><td><strong>Overgeslagen</strong></td><td>' . $skipped . '</td></tr>';
echo '<tr><td><strong>Fouten</strong></td><td>' . $errorCount . '</td></tr>';
echo '</tbody></table>';

if ($errorCount > 0) {
    echo '<h3>Fouten per type</h3>';
    echo '<table class="listtable"><thead><tr class="listheader"><th>Aantal</th><th>Type</th><th>Foutmelding</th><th>Endpoints</th></tr></thead><tbody>';
    foreach ($groupedErrors as $group) {
        $count = count($group['urls']);
        $urlList = implode('<br>', array_map('htmlspecialchars', array_slice($group['urls'], 0, 5)));
        if ($count > 5) $urlList .= '<br><em>... en ' . ($count - 5) . ' meer</em>';
        echo '<tr>';
        echo '<td>' . $count . '</td>';
        echo '<td><code>' . htmlspecialchars($group['type']) . '</code></td>';
        echo '<td>' . htmlspecialchars(substr($group['error'], 0, 150)) . '</td>';
        echo '<td style="font-size:var(--font-size-sm)">' . $urlList . '</td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
}

echo '<p style="color:var(--text-secondary); margin-top:10px"><em>Gegenereerd: ' . date('Y-m-d H:i:s') . '</em></p>';
echo '</div></body></html>';
