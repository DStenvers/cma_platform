<?php
/**
 * LLM management — detects local LLM engines reachable from the server,
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
// Per-engine setup: probe info + a numbered list of steps for each
// supported OS. The steps are ordered to reflect what an operator
// actually needs to do FIRST — Ollama installs the engine before
// pulling a model, llama.cpp / text-generation-webui need the GGUF
// model on disk BEFORE the binary can serve anything. Every engine's
// step list ends with an "auto-start" step + a ".env" step, so a
// server reboot doesn't leave the site without a working LLM.
//
// Step shape:
//   ['title' => 'Korte stap-naam', 'body' => 'Uitleg', 'code' => 'optionele command'].
// Steps without `code` render as plain paragraphs.
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
        'setup' => [
            'windows' => [
                ['title' => 'Installeer Ollama', 'body' => 'De installer maakt een Windows-service ("Ollama") die bij elke reboot automatisch start — geen handwerk om het draaiend te houden.', 'code' => 'winget install Ollama.Ollama'],
                ['title' => 'Pull een model',    'body' => 'Eenmalig — Ollama beheert zijn eigen model-storage. Daarna start het instant.', 'code' => 'ollama pull qwen2.5:7b'],
                ['title' => 'Verifieer auto-start', 'body' => 'services.msc → Ollama → Startup type moet "Automatic" zijn. Standaard zo door de installer ingesteld; controleer na een Windows-update.'],
                ['title' => 'Configureer .env',  'body' => 'Voeg toe aan .env.production op de site:', 'code' => "LLM_URL=http://localhost:11434/api/generate\nLLM_MODEL=qwen2.5:7b"],
            ],
            'linux' => [
                ['title' => 'Installeer Ollama', 'body' => 'Installer maakt een systemd-unit aan en enabled hem direct — auto-start na reboot is dus al geregeld.', 'code' => 'curl -fsSL https://ollama.com/install.sh | sh'],
                ['title' => 'Pull een model',    'body' => 'Eenmalig.', 'code' => 'ollama pull qwen2.5:7b'],
                ['title' => 'Verifieer auto-start', 'body' => 'systemctl status ollama moet "enabled" tonen.', 'code' => 'systemctl status ollama'],
                ['title' => 'Configureer .env',  'body' => 'Voeg toe aan .env.production:', 'code' => "LLM_URL=http://localhost:11434/api/generate\nLLM_MODEL=qwen2.5:7b"],
            ],
            'darwin' => [
                ['title' => 'Installeer Ollama', 'body' => 'Via brew of de installer van de site.', 'code' => 'brew install ollama'],
                ['title' => 'Auto-start aanzetten', 'body' => 'Zonder dit moet je ollama na elke login zelf opstarten.', 'code' => 'brew services start ollama'],
                ['title' => 'Pull een model',    'body' => '', 'code' => 'ollama pull qwen2.5:7b'],
                ['title' => 'Configureer .env',  'body' => 'Voeg toe aan .env.production:', 'code' => "LLM_URL=http://localhost:11434/api/generate\nLLM_MODEL=qwen2.5:7b"],
            ],
        ],
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
        'setup' => [
            'windows' => [
                ['title' => 'Installeer LM Studio', 'body' => 'Download de Windows-installer van https://lmstudio.ai/. GUI-tool — geen aparte CLI nodig.'],
                ['title' => 'Download een model',   'body' => 'In de app: tab "Discover" → zoek bv. "qwen2.5 7b instruct gguf" → druk Download. Modellen landen in %USERPROFILE%\\.lmstudio\\models.'],
                ['title' => 'Start de Local Server', 'body' => 'Tab "Local Server" → kies het gedownloade model → "Start Server" (poort 1234). Vink "Start server when LM Studio starts" aan zodat hij na een herstart van de app meteen draait.'],
                ['title' => 'Auto-start na Windows-reboot', 'body' => 'Settings → "Run LM Studio at Windows login" aanvinken. Zonder dit start LM Studio NIET na een server-reboot en is je LLM-API offline.'],
                ['title' => 'Configureer .env', 'body' => 'Het LLM_MODEL is de id zoals je hem in LM Studio ziet. Voorbeeld:', 'code' => "LLM_URL=http://localhost:1234/v1\nLLM_MODEL=qwen2.5-7b-instruct"],
            ],
            'linux' => [
                ['title' => 'Installeer LM Studio', 'body' => 'Download AppImage van https://lmstudio.ai/, maak executable.'],
                ['title' => 'Download een model',   'body' => 'In-app via Discover, of CLI:', 'code' => 'lms get qwen/qwen2.5-7b-instruct'],
                ['title' => 'Start de server',      'body' => 'Eénmalig handmatig om te testen:', 'code' => 'lms server start --port 1234'],
                ['title' => 'Auto-start als user-service', 'body' => 'Maak ~/.config/systemd/user/lmstudio.service met:', 'code' => "[Unit]\nDescription=LM Studio API\n[Service]\nExecStart=/pad/naar/lms server start --port 1234\nRestart=always\n[Install]\nWantedBy=default.target"],
                ['title' => 'Enable + start', 'body' => '', 'code' => "systemctl --user enable --now lmstudio\nloginctl enable-linger \$USER  # ook draaien zonder ingelogde sessie"],
                ['title' => 'Configureer .env', 'body' => '', 'code' => "LLM_URL=http://localhost:1234/v1\nLLM_MODEL=qwen2.5-7b-instruct"],
            ],
            'darwin' => [
                ['title' => 'Installeer LM Studio', 'body' => '', 'code' => 'brew install --cask lm-studio'],
                ['title' => 'Download een model + start server', 'body' => 'In de app: Discover → Download → Local Server → Start.'],
                ['title' => 'Auto-start na login', 'body' => 'System Settings → General → Login Items → LM Studio toevoegen. (En in LM Studio: "Start server when LM Studio starts".)'],
                ['title' => 'Configureer .env', 'body' => '', 'code' => "LLM_URL=http://localhost:1234/v1\nLLM_MODEL=qwen2.5-7b-instruct"],
            ],
        ],
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
        'setup' => [
            'windows' => [
                ['title' => 'Download eerst een GGUF-model', 'body' => 'llama.cpp draait niet zonder model. Open <a href="llm_models.php" target="_blank">Local LLM models</a> (vereist ?key=&lt;DEPLOY_SECRET&gt;) en kies een curated GGUF, of plak een eigen Hugging Face URL. Default storage: C:\\llama\\models.'],
                ['title' => 'Pak llama-server binary',  'body' => 'Download een release uit https://github.com/ggerganov/llama.cpp/releases en pak uit naar C:\\llama\\bin\\.'],
                ['title' => 'Test handmatig', 'body' => 'Verifieer eerst dat het werkt:', 'code' => 'C:\\llama\\bin\\llama-server.exe -m C:\\llama\\models\\qwen2.5-7b-instruct.Q4_K_M.gguf --port 8080'],
                ['title' => 'Wrap als Windows-service met NSSM', 'body' => 'NSSM van https://nssm.cc/ download. Daarna (cmd as Admin):', 'code' => "nssm install LlamaServer C:\\llama\\bin\\llama-server.exe\nnssm set LlamaServer AppParameters \"-m C:\\llama\\models\\qwen2.5-7b-instruct.Q4_K_M.gguf --port 8080 --host 0.0.0.0\"\nnssm set LlamaServer Start SERVICE_AUTO_START\nnssm start LlamaServer"],
                ['title' => 'Configureer .env', 'body' => '', 'code' => "LLM_URL=http://localhost:8080/v1\nLLM_MODEL=qwen2.5-7b-instruct"],
            ],
            'linux' => [
                ['title' => 'Download een GGUF-model', 'body' => 'Open <a href="llm_models.php" target="_blank">Local LLM models</a> (key=DEPLOY_SECRET) of curl direct uit Hugging Face. Default storage: ~/llama-models.'],
                ['title' => 'Bouw of pak llama.cpp', 'body' => '', 'code' => "git clone https://github.com/ggerganov/llama.cpp\ncd llama.cpp && make -j"],
                ['title' => 'Test handmatig', 'body' => '', 'code' => './llama-server -m ~/llama-models/qwen2.5-7b-instruct.Q4_K_M.gguf --port 8080'],
                ['title' => 'Systemd-unit voor auto-start', 'body' => 'Schrijf /etc/systemd/system/llama-server.service:', 'code' => "[Unit]\nDescription=llama.cpp OpenAI-compat server\nAfter=network.target\n[Service]\nExecStart=/opt/llama.cpp/llama-server -m /opt/models/qwen2.5-7b-instruct.Q4_K_M.gguf --port 8080\nRestart=always\nUser=llama\n[Install]\nWantedBy=multi-user.target"],
                ['title' => 'Enable + start', 'body' => '', 'code' => 'sudo systemctl enable --now llama-server'],
                ['title' => 'Configureer .env', 'body' => '', 'code' => "LLM_URL=http://localhost:8080/v1\nLLM_MODEL=qwen2.5-7b-instruct"],
            ],
            'darwin' => [
                ['title' => 'Download een GGUF-model', 'body' => 'Via <a href="llm_models.php" target="_blank">Local LLM models</a> of brew/curl.'],
                ['title' => 'Installeer llama.cpp', 'body' => '', 'code' => 'brew install llama.cpp'],
                ['title' => 'Test handmatig', 'body' => '', 'code' => 'llama-server -m ~/llama-models/qwen2.5-7b-instruct.Q4_K_M.gguf --port 8080'],
                ['title' => 'Auto-start via brew services', 'body' => 'llama.cpp heeft geen native service-recept; wrap met een launchd plist of run via brew-services-template.'],
                ['title' => 'Configureer .env', 'body' => '', 'code' => "LLM_URL=http://localhost:8080/v1\nLLM_MODEL=qwen2.5-7b-instruct"],
            ],
        ],
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
        'setup' => [
            'windows' => [
                ['title' => 'Download een GGUF-model (optioneel — kan ook in-webui)', 'body' => 'Via <a href="llm_models.php" target="_blank">Local LLM models</a> of in webui later via tab Model.'],
                ['title' => 'Clone + initial setup', 'body' => 'start_windows.bat draait éénmalig de installer (Python venv, deps).', 'code' => "git clone https://github.com/oobabooga/text-generation-webui\ncd text-generation-webui\nstart_windows.bat"],
                ['title' => 'Enable API mode', 'body' => 'Bewerk CMD_FLAGS.txt — voeg toe:', 'code' => '--api --api-port 5000 --listen'],
                ['title' => 'Wrap als Windows-service met NSSM', 'body' => '', 'code' => "nssm install TextGenWebui C:\\pad\\start_windows.bat\nnssm set TextGenWebui AppDirectory C:\\pad\\text-generation-webui\nnssm set TextGenWebui Start SERVICE_AUTO_START\nnssm start TextGenWebui"],
                ['title' => 'Configureer .env', 'body' => '', 'code' => "LLM_URL=http://localhost:5000/v1\nLLM_MODEL=qwen2.5-7b-instruct"],
            ],
            'linux' => [
                ['title' => 'Download een GGUF-model', 'body' => 'Via <a href="llm_models.php" target="_blank">Local LLM models</a> of in webui.'],
                ['title' => 'Clone + initial setup', 'body' => '', 'code' => "git clone https://github.com/oobabooga/text-generation-webui\ncd text-generation-webui\n./start_linux.sh"],
                ['title' => 'Enable API mode', 'body' => 'Bewerk CMD_FLAGS.txt:', 'code' => '--api --api-port 5000 --listen'],
                ['title' => 'Systemd-unit', 'body' => 'Schrijf /etc/systemd/system/textgen-webui.service:', 'code' => "[Unit]\nDescription=text-generation-webui API\nAfter=network.target\n[Service]\nWorkingDirectory=/opt/text-generation-webui\nExecStart=/opt/text-generation-webui/start_linux.sh\nRestart=always\nUser=textgen\n[Install]\nWantedBy=multi-user.target"],
                ['title' => 'Enable + start', 'body' => '', 'code' => 'sudo systemctl enable --now textgen-webui'],
                ['title' => 'Configureer .env', 'body' => '', 'code' => "LLM_URL=http://localhost:5000/v1\nLLM_MODEL=qwen2.5-7b-instruct"],
            ],
            'darwin' => [
                ['title' => 'Download een GGUF-model', 'body' => 'Via <a href="llm_models.php" target="_blank">Local LLM models</a> of in webui.'],
                ['title' => 'Clone + initial setup', 'body' => '', 'code' => "git clone https://github.com/oobabooga/text-generation-webui\ncd text-generation-webui\n./start_macos.sh"],
                ['title' => 'Enable API mode', 'body' => 'Bewerk CMD_FLAGS.txt:', 'code' => '--api --api-port 5000 --listen'],
                ['title' => 'Auto-start na login via launchd', 'body' => 'Schrijf ~/Library/LaunchAgents/com.oobabooga.textgen.plist met ProgramArguments wijzend naar start_macos.sh + RunAtLoad=true.'],
                ['title' => 'Configureer .env', 'body' => '', 'code' => "LLM_URL=http://localhost:5000/v1\nLLM_MODEL=qwen2.5-7b-instruct"],
            ],
        ],
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
        CURLOPT_USERAGENT      => 'cma-llm-management/1.0',
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

/**
 * Render the ordered setup-steps + docs link for one engine on one OS.
 * Used in the install-tip block (probe-failure) and inside the
 * "Toon installatie-stappen" details on probe-success.
 *
 * Steps are written with HTML allowed in `body` (links to llm_models.php
 * etc) so we deliberately do NOT escape body. The title and code blocks
 * ARE escaped. Trust boundary: the registry is server-controlled.
 */
function llm_render_setup(array $engine, string $os, bool $iis): void
{
    $steps = $engine['setup'][$os] ?? $engine['setup']['linux'] ?? [];
    if ($steps === []) {
        return;
    }
    echo '<div class="install-tip">';
    echo '<strong>Stap-voor-stap op ' . htmlspecialchars($os) . ':</strong>';
    echo '<ol class="setup-steps">';
    foreach ($steps as $i => $step) {
        echo '<li>';
        if (!empty($step['title'])) {
            echo '<div class="setup-step__title">' . htmlspecialchars((string)$step['title']) . '</div>';
        }
        if (!empty($step['body'])) {
            // Body may contain anchor tags pointing at sibling tools
            // (llm_models.php). Server-controlled string, no user input.
            echo '<div class="setup-step__body">' . (string)$step['body'] . '</div>';
        }
        if (!empty($step['code'])) {
            echo '<code>' . htmlspecialchars((string)$step['code']) . '</code>';
        }
        echo '</li>';
    }
    echo '</ol>';
    if ($iis) {
        echo '<div class="setup-iis-note">';
        echo '<strong>IIS-context:</strong> de LLM-engine draait náást IIS als losse service op een lokale poort. ';
        echo 'Sta uitgaand HTTP-verkeer richting 127.0.0.1 toe (Windows Firewall blokkeert localhost normaliter niet). ';
        echo 'Open géén externe poort — de PHP-proxy in de app praat met de engine op localhost.';
        echo '</div>';
    }
    echo '<div style="margin-top:8px;font-size:12px"><a href="' . htmlspecialchars((string)$engine['docs']) . '" target="_blank" rel="noopener">Documentatie ↗</a></div>';
    echo '</div>';
}

// =========================================================================
// Run probes (only when invoked as ?action=scan — see Render below for
// the page-load shell that fetches this endpoint after the spinner shows)
// =========================================================================

$configuredUrl   = llm_env('LLM_URL');
$configuredModel = llm_env('LLM_MODEL');
$os              = llm_detect_os();
$server          = llm_detect_server();

$action = strtolower(trim((string)($_GET['action'] ?? '')));

if ($action === 'scan') {
    Response::noCache();
    header('Content-Type: text/html; charset=utf-8');

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
            } else {
                if (count($models) === 0) {
                    echo '<lib-message type="info" compact style="margin-top:8px">Engine draait, maar er zijn nog geen modellen geladen.</lib-message>';
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
                // Even on success, keep install steps available — useful when
                // operator wants to add a second model or rewrap as a service.
                echo '<details class="setup-toggle" style="margin-top:10px">';
                echo '<summary style="cursor:pointer;color:var(--text-muted,#6c757d);font-size:13px;">Toon installatie-stappen</summary>';
                llm_render_setup($eng, $os, $server['iis']);
                echo '</details>';
            }
        } else {
            $err = $r['probe']['error'] ?: 'geen verbinding';
            echo '<div class="err" style="margin-top:8px">Probe-fout: ' . htmlspecialchars($err) . '</div>';
            llm_render_setup($eng, $os, $server['iis']);
        }
        echo '</div>';
    }
    echo '</div>';

    // --- Refresh button at the bottom ---
    echo '<div style="text-align:center; padding:16px 0;">';
    echo '<a href="tools_llm.php" class="btn btn-primary"><span class="lnr lnr-sync"></span> Opnieuw scannen</a>';
    echo '</div>';
    exit;
}

// =========================================================================
// Render — page shell with spinner; JS calls ?action=scan after load
// =========================================================================

Response::noCache();
cma_html_header('CMA - LLM management');
echo '<body class="contentbody tools tool-llm" style="margin:0;">';
ToolbarHelper::report('LLM management', false, false, false, false, 'Detecteer lokale LLM-engines en geïnstalleerde modellen op deze server');

echo '<style>
.tool-llm .llm-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(420px,1fr)); gap:16px; }
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
.tool-llm .install-tip code { display:block; padding:6px 8px; background:#1c1c1f; color:#e6e6e6; border-radius:4px; margin-top:6px; word-break:break-all; white-space:pre-wrap; font-family:Menlo,Consolas,monospace; font-size:12px; }
.tool-llm .install-tip a { color:#1a5dbf; }
.tool-llm .setup-steps { margin:8px 0 0; padding-left:1.4rem; }
.tool-llm .setup-steps > li { margin:0 0 10px; padding-left:4px; }
.tool-llm .setup-step__title { font-weight:600; margin-bottom:2px; }
.tool-llm .setup-step__body { color:var(--text-muted,#5a5042); }
.tool-llm .setup-step__body a { color:#1a5dbf; }
.tool-llm .setup-iis-note { margin-top:8px; padding:6px 8px; background:#fffbe6; border-left:3px solid #d4a017; border-radius:3px; font-size:12px; }
.tool-llm .setup-toggle > summary { padding:4px 0; }
.tool-llm .setup-toggle > summary:hover { color:var(--text-strong,#1a1a1a); }
.tool-llm .summary .pill { display:inline-block; padding:3px 10px; border-radius:12px; background:var(--surface-alt,#f6f8fa); font-size:12px; margin-right:8px; }
.tool-llm .summary .pill strong { font-weight:600; }
.tool-llm .err { color:#c0392b; font-size:13px; }
.tool-llm .scan-state { display:flex; flex-direction:column; align-items:center; justify-content:center; gap:12px; padding:48px 16px; color:var(--text-muted,#6c757d); }
</style>';

echo '<div id="c" class="tools">';

// Spinner shown immediately while the JS below fetches the scan endpoint.
// Server-side probes take ~5-8s total (1.5s timeout × 4 engines + parsing);
// without this the page would sit blank for that whole window.
echo '<div id="llm-scan-target">';
echo   '<div class="scan-state" id="llm-scan-state">';
echo     '<lib-loader size="medium" text="Bezig met scannen van LLM-engines…"></lib-loader>';
echo   '</div>';
echo '</div>';

echo '<script>
(function () {
    var target = document.getElementById("llm-scan-target");
    if (!target) return;
    fetch("tools_llm.php?action=scan", { credentials: "same-origin" })
        .then(function (r) {
            if (!r.ok) { throw new Error("HTTP " + r.status); }
            return r.text();
        })
        .then(function (html) {
            target.innerHTML = html;
        })
        .catch(function (err) {
            target.innerHTML = \'<div class="scan-state"><lib-message type="error">Scan mislukt: \'
                + (err && err.message ? err.message : "onbekende fout")
                + \' — probeer opnieuw.</lib-message></div>\';
        });
})();
</script>';

echo '</div>';
echo '</body>';
