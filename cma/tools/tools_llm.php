<?php
/**
 * LLM analyzer — detects local LLM engines reachable from the server,
 * lists installed models, and shows install hints when nothing is found.
 *
 * Engines probed (default ports):
 *   - Ollama                       http://localhost:11434
 *   - LM Studio                    http://localhost:1234
 *   - llama.cpp server             http://localhost:8080
 *   - text-generation-webui (ooba) http://localhost:5000
 *
 * Access: admins + developers.
 */

use App\Library\Response;
use App\Library\Server;
use Cma\SecurityHelper;
use Cma\ToolbarHelper;

require_once __DIR__ . '/../bootstrap.inc';

if (!SecurityHelper::isAdmin()) {
    if (defined('CMA_NOMENU_MODE') && CMA_NOMENU_MODE) {
        echo '<lib-message type="error">Geen toegang</lib-message>';
        exit;
    }
    header('Location: ../login.php');
    exit;
}

// =========================================================================
// Engine registry
// =========================================================================

/**
 * Each engine: where to probe, how to extract model info, how to install.
 * `extract` receives the parsed JSON body and returns a normalised model list:
 *   [['id' => string, 'size' => ?int, 'detail' => ?string], ...]
 * or null if the response was not understood.
 */
$engines = [
    'ollama' => [
        'name'         => 'Ollama',
        'default_url'  => 'http://localhost:11434',
        'env_var'      => 'LLM_URL', // project may already point here
        'probe_path'   => '/api/tags',
        'extract'      => function (array $j): ?array {
            if (!isset($j['models']) || !is_array($j['models'])) return null;
            $out = [];
            foreach ($j['models'] as $m) {
                $detail = [];
                if (!empty($m['details']['parameter_size'])) $detail[] = $m['details']['parameter_size'];
                if (!empty($m['details']['quantization_level'])) $detail[] = $m['details']['quantization_level'];
                if (!empty($m['details']['family'])) $detail[] = $m['details']['family'];
                $out[] = [
                    'id'     => (string)($m['name'] ?? ''),
                    'size'   => isset($m['size']) ? (int)$m['size'] : null,
                    'detail' => $detail ? implode(' · ', $detail) : null,
                ];
            }
            return $out;
        },
        'install' => [
            'windows' => 'winget install Ollama.Ollama   (of: download MSI van https://ollama.com/download/windows)',
            'linux'   => 'curl -fsSL https://ollama.com/install.sh | sh',
            'darwin'  => 'brew install ollama   (of: download van https://ollama.com/download/mac)',
        ],
        'first_model' => 'ollama pull llama3.2:3b   (kleine test) — daarna: ollama run llama3.2:3b',
        'docs'        => 'https://ollama.com',
    ],
    'lmstudio' => [
        'name'         => 'LM Studio',
        'default_url'  => 'http://localhost:1234',
        'env_var'      => null,
        'probe_path'   => '/v1/models',
        'extract'      => function (array $j): ?array {
            if (!isset($j['data']) || !is_array($j['data'])) return null;
            $out = [];
            foreach ($j['data'] as $m) {
                $out[] = ['id' => (string)($m['id'] ?? ''), 'size' => null, 'detail' => $m['owned_by'] ?? null];
            }
            return $out;
        },
        'install' => [
            'windows' => 'Download installer: https://lmstudio.ai/   (start de app, ga naar tab "Local Server", druk "Start Server" op poort 1234)',
            'linux'   => 'Download AppImage: https://lmstudio.ai/   (CLI: lms server start)',
            'darwin'  => 'brew install --cask lm-studio   (open de app, start de local server)',
        ],
        'first_model' => 'In LM Studio: zoek model in tab "Discover", druk Download, laad in "My Models", start de Local Server.',
        'docs'        => 'https://lmstudio.ai/',
    ],
    'llamacpp' => [
        'name'         => 'llama.cpp server',
        'default_url'  => 'http://localhost:8080',
        'env_var'      => null,
        'probe_path'   => '/v1/models',
        'extract'      => function (array $j): ?array {
            if (!isset($j['data']) || !is_array($j['data'])) return null;
            $out = [];
            foreach ($j['data'] as $m) $out[] = ['id' => (string)($m['id'] ?? ''), 'size' => null, 'detail' => null];
            return $out;
        },
        'install' => [
            'windows' => 'Bouwen vanuit source met CMake/MSVC, of pak een release uit https://github.com/ggerganov/llama.cpp/releases. Start: llama-server -m model.gguf --port 8080',
            'linux'   => 'git clone https://github.com/ggerganov/llama.cpp && cd llama.cpp && make. Start: ./llama-server -m model.gguf --port 8080',
            'darwin'  => 'brew install llama.cpp   (of bouwen). Start: llama-server -m model.gguf --port 8080',
        ],
        'first_model' => 'Download een GGUF model (bv. https://huggingface.co/TheBloke). Daarna: llama-server -m pad/naar/model.gguf --port 8080',
        'docs'        => 'https://github.com/ggerganov/llama.cpp',
    ],
    'textgen' => [
        'name'         => 'text-generation-webui (oobabooga)',
        'default_url'  => 'http://localhost:5000',
        'env_var'      => null,
        'probe_path'   => '/v1/models',
        'extract'      => function (array $j): ?array {
            if (!isset($j['data']) || !is_array($j['data'])) return null;
            $out = [];
            foreach ($j['data'] as $m) $out[] = ['id' => (string)($m['id'] ?? ''), 'size' => null, 'detail' => null];
            return $out;
        },
        'install' => [
            'windows' => 'git clone https://github.com/oobabooga/text-generation-webui en draai start_windows.bat. Start API met --api flag.',
            'linux'   => 'git clone https://github.com/oobabooga/text-generation-webui && ./start_linux.sh   (voeg --api toe in CMD_FLAGS.txt)',
            'darwin'  => 'git clone https://github.com/oobabooga/text-generation-webui && ./start_macos.sh   (voeg --api toe)',
        ],
        'first_model' => 'Open de webui (poort 7860), tab "Model", download een HuggingFace repo, druk Load. De OpenAI-API draait dan op poort 5000.',
        'docs'        => 'https://github.com/oobabooga/text-generation-webui',
    ],
];

// =========================================================================
// Probing
// =========================================================================

/**
 * Returns ['ok' => bool, 'status' => int|null, 'body' => string|null, 'error' => string|null, 'ms' => float]
 */
function llm_probe(string $url, float $timeout = 1.5): array
{
    $started = microtime(true);
    $result = ['ok' => false, 'status' => null, 'body' => null, 'error' => null, 'ms' => 0];

    if (!function_exists('curl_init')) {
        $result['error'] = 'PHP cURL-extensie ontbreekt';
        return $result;
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT_MS     => (int)($timeout * 1000),
        CURLOPT_CONNECTTIMEOUT_MS => (int)(min($timeout, 1.0) * 1000),
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_USERAGENT      => 'cma-llm-analyzer/1.0',
    ]);
    $body = curl_exec($ch);
    $err  = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    // PHP 8.0+ auto-closes the handle when it goes out of scope; curl_close
    // is a no-op and was formally deprecated in 8.5.
    unset($ch);

    $result['ms']     = round((microtime(true) - $started) * 1000, 1);
    $result['status'] = $code ?: null;
    if ($body === false || $err !== '') {
        $result['error'] = $err ?: 'onbekende fout';
        return $result;
    }
    if ($code >= 200 && $code < 300) {
        $result['ok'] = true;
        $result['body'] = $body;
    } else {
        $result['error'] = "HTTP $code";
    }
    return $result;
}

function llm_detect_os(): string
{
    if (PHP_OS_FAMILY === 'Windows') return 'windows';
    if (PHP_OS_FAMILY === 'Darwin')  return 'darwin';
    return 'linux';
}

function llm_detect_server(): array
{
    $sw = $_SERVER['SERVER_SOFTWARE'] ?? '';
    $isIIS = stripos($sw, 'iis') !== false || stripos($sw, 'microsoft') !== false;
    $isApache = stripos($sw, 'apache') !== false;
    $isNginx = stripos($sw, 'nginx') !== false;
    return [
        'software' => $sw ?: '(onbekend)',
        'iis'      => $isIIS,
        'apache'   => $isApache,
        'nginx'    => $isNginx,
    ];
}

function llm_format_bytes(?int $bytes): string
{
    if ($bytes === null || $bytes <= 0) return '—';
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = 0;
    $b = (float)$bytes;
    while ($b >= 1024 && $i < count($units) - 1) { $b /= 1024; $i++; }
    return sprintf('%.1f %s', $b, $units[$i]);
}

function llm_env(string $key, string $default = ''): string
{
    $v = getenv($key);
    if ($v !== false && $v !== '') return $v;
    if (!empty($_ENV[$key])) return (string)$_ENV[$key];
    return $default;
}

// =========================================================================
// Run probes
// =========================================================================

$configuredUrl   = llm_env('LLM_URL');
$configuredModel = llm_env('LLM_MODEL');
$os              = llm_detect_os();
$server          = llm_detect_server();

$results = [];
foreach ($engines as $key => $eng) {
    // Allow env var (e.g. Ollama via LLM_URL) to override the default URL
    $base = $eng['default_url'];
    if (!empty($eng['env_var']) && $configuredUrl !== '') {
        // Use configured URL as base if it matches the engine's expected host pattern;
        // otherwise stick with default. Crude heuristic: same port = same engine.
        $defParts = parse_url($eng['default_url']);
        $cfgParts = parse_url($configuredUrl);
        if (($cfgParts['port'] ?? null) === ($defParts['port'] ?? null)) {
            $base = (($cfgParts['scheme'] ?? 'http') . '://' . ($cfgParts['host'] ?? 'localhost') . (isset($cfgParts['port']) ? ':' . $cfgParts['port'] : ''));
        }
    }
    $url    = $base . $eng['probe_path'];
    $probe  = llm_probe($url);
    $models = null;
    if ($probe['ok']) {
        $json = json_decode((string)$probe['body'], true);
        if (is_array($json)) {
            $models = $eng['extract']($json);
        }
    }
    $results[$key] = [
        'engine' => $eng,
        'url'    => $url,
        'base'   => $base,
        'probe'  => $probe,
        'models' => $models,
    ];
}

$anyOk = false;
foreach ($results as $r) { if ($r['probe']['ok']) { $anyOk = true; break; } }

// =========================================================================
// Render
// =========================================================================

Response::noCache();
cma_html_header('CMA - LLM analyzer');
echo '<body class="contentbody tools tool-llm" style="margin:0;">';
ToolbarHelper::report('LLM analyzer', false, false, false, false, 'Detecteer lokale LLM-engines en geïnstalleerde modellen op deze server');

echo '<style>
.tool-llm .llm-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(420px,1fr)); gap:16px; padding:16px; }
.tool-llm .llm-card { border:1px solid var(--border-color,#dee2e6); border-radius:8px; padding:16px; background:var(--surface,#fff); }
.tool-llm .llm-card h3 { margin:0 0 8px 0; display:flex; align-items:center; gap:8px; }
.tool-llm .llm-card .url { font-family:Menlo,Consolas,monospace; font-size:12px; color:var(--text-muted,#6c757d); word-break:break-all; }
.tool-llm .badge-ok { background:#1b8a3a; color:#fff; padding:2px 8px; border-radius:10px; font-size:11px; font-weight:600; }
.tool-llm .badge-down { background:#888; color:#fff; padding:2px 8px; border-radius:10px; font-size:11px; font-weight:600; }
.tool-llm .models { margin-top:10px; }
.tool-llm .models table { width:100%; border-collapse:collapse; font-size:13px; }
.tool-llm .models th, .tool-llm .models td { text-align:left; padding:4px 8px; border-bottom:1px solid var(--border-color,#eee); }
.tool-llm .models th { font-weight:600; color:var(--text-muted,#6c757d); }
.tool-llm .models .size { white-space:nowrap; text-align:right; font-variant-numeric:tabular-nums; }
.tool-llm .install-tip { margin-top:10px; padding:10px 12px; background:var(--surface-alt,#f6f8fa); border-left:3px solid #c69; border-radius:4px; font-size:13px; }
.tool-llm .install-tip code { display:block; padding:6px 8px; background:#1c1c1f; color:#e6e6e6; border-radius:4px; margin-top:6px; word-break:break-all; font-family:Menlo,Consolas,monospace; font-size:12px; }
.tool-llm .install-tip a { color:#1a5dbf; }
.tool-llm .summary { padding:16px; }
.tool-llm .summary .pill { display:inline-block; padding:3px 10px; border-radius:12px; background:var(--surface-alt,#f6f8fa); font-size:12px; margin-right:8px; }
.tool-llm .summary .pill strong { font-weight:600; }
.tool-llm .err { color:#c0392b; font-size:13px; }
</style>';

echo '<div id="c" class="tools">';

// --- Summary strip ---
echo '<div class="summary">';
echo '<div class="pill">OS: <strong>' . htmlspecialchars(PHP_OS) . '</strong> (' . htmlspecialchars($os) . ')</div>';
echo '<div class="pill">Webserver: <strong>' . htmlspecialchars($server['software']) . '</strong></div>';
if ($configuredUrl !== '') {
    echo '<div class="pill">LLM_URL: <strong>' . htmlspecialchars($configuredUrl) . '</strong></div>';
}
if ($configuredModel !== '') {
    echo '<div class="pill">LLM_MODEL: <strong>' . htmlspecialchars($configuredModel) . '</strong></div>';
}
if (!$anyOk) {
    echo '<lib-message type="warning" style="margin-top:12px">Geen lokale LLM-engine bereikbaar op de standaard poorten. Zie de installatiesuggesties hieronder.</lib-message>';
}
echo '</div>';

// --- Cards ---
echo '<div class="llm-grid">';
foreach ($results as $key => $r) {
    $eng = $r['engine'];
    $ok  = $r['probe']['ok'];
    echo '<div class="llm-card">';
    echo '<h3>' . htmlspecialchars($eng['name']);
    echo ' <span class="' . ($ok ? 'badge-ok">actief' : 'badge-down">niet bereikbaar') . '</span>';
    echo '</h3>';
    echo '<div class="url">' . htmlspecialchars($r['url']) . '   (' . number_format($r['probe']['ms'], 1) . ' ms)</div>';

    if ($ok) {
        $models = $r['models'];
        if ($models === null) {
            echo '<lib-message type="warning" compact style="margin-top:8px">Antwoord ontvangen, maar geen modellen-lijst herkend.</lib-message>';
        } elseif (count($models) === 0) {
            echo '<div class="install-tip">Engine draait, maar er zijn nog geen modellen geladen.<br><strong>Eerste model:</strong><code>' . htmlspecialchars($eng['first_model']) . '</code></div>';
        } else {
            echo '<div class="models"><table>';
            echo '<thead><tr><th>Model</th><th>Detail</th><th class="size">Schijf</th></tr></thead><tbody>';
            foreach ($models as $m) {
                echo '<tr>';
                echo '<td>' . htmlspecialchars((string)$m['id']) . '</td>';
                echo '<td>' . htmlspecialchars((string)($m['detail'] ?? '—')) . '</td>';
                echo '<td class="size">' . htmlspecialchars(llm_format_bytes($m['size'] ?? null)) . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table></div>';
            echo '<div style="margin-top:8px;font-size:12px;color:var(--text-muted,#6c757d)">' . count($models) . ' model' . (count($models) === 1 ? '' : 'len') . ' geladen</div>';
        }
    } else {
        $err = $r['probe']['error'] ?: 'geen verbinding';
        echo '<div class="err" style="margin-top:8px">Probe-fout: ' . htmlspecialchars($err) . '</div>';
        $install = $eng['install'][$os] ?? $eng['install']['linux'];
        echo '<div class="install-tip">';
        echo '<strong>Installeren (' . htmlspecialchars($os) . '):</strong>';
        echo '<code>' . htmlspecialchars($install) . '</code>';
        if ($server['iis']) {
            echo '<div style="margin-top:8px;font-size:12px">';
            echo 'Op IIS: de LLM-engine draait náást IIS als losse service op een lokale poort. ';
            echo 'Sta uitgaand HTTP-verkeer richting 127.0.0.1 toe (Windows Firewall blokkeert localhost normaliter niet). ';
            echo 'Open géén externe poort — de PHP-proxy in de app praat met de engine op localhost.';
            echo '</div>';
        }
        echo '<div style="margin-top:6px;font-size:12px"><a href="' . htmlspecialchars($eng['docs']) . '" target="_blank" rel="noopener">Documentatie ↗</a></div>';
        echo '</div>';
    }
    echo '</div>';
}
echo '</div>';

// --- Refresh button at the bottom ---
echo '<div style="padding:16px; text-align:center;">';
echo '<a href="tools_llm.php" class="btn btn-primary"><span class="lnr lnr-sync"></span> Opnieuw scannen</a>';
echo '</div>';

echo '</div>';
echo '</body>';
