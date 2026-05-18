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
// Link to llm_models.php — that tool uses DEPLOY_SECRET (?key=) auth so it
// also works when CMA bootstrap is half-broken. THIS page is already
// behind admin login, so we can safely pre-attach the secret server-side
// and the operator clicks straight through. Empty secret → bare link
// (will 403 with a friendly hint, same as before).
// =========================================================================
$deploySecret = trim((string)($_ENV['DEPLOY_SECRET'] ?? getenv('DEPLOY_SECRET') ?: ''));
$llmModelsUrl = 'llm_models.php' . ($deploySecret !== '' ? '?key=' . urlencode($deploySecret) : '');
$llmModelsHref = htmlspecialchars($llmModelsUrl, ENT_QUOTES, 'UTF-8');

// =========================================================================
// Recommended model — index 0 from the shared curated list. The install
// steps below interpolate these values into their `code` blocks so when
// llm_suggested_models.php changes (new SOTA release), this page follows
// without touching strings everywhere.
//   - $recGGUF       e.g. "$recGGUF"
//   - $recLlmModel   strip the quant suffix + .gguf → bare model id used
//                    in the .env LLM_MODEL value
//   - $recOllamaTag  the curated entry's ollama_tag override, or a sane
//                    fallback when the row predates that field
//   - $recLabel      human-readable label for hints in the body text
// =========================================================================
$llmSuggested = require __DIR__ . '/../data/llm_suggested_models.php';
$recPick      = is_array($llmSuggested) && isset($llmSuggested[0]) ? $llmSuggested[0] : [];
$recGGUF      = (string)($recPick['name']  ?? 'model.gguf');
$recLabel     = (string)($recPick['label'] ?? 'het curated model');
$recOllamaTag = (string)($recPick['ollama_tag'] ?? 'gemma3:4b');
// Strip ".Q4_K_M" / similar quant suffix + ".gguf" to derive the bare
// model id used as the .env LLM_MODEL value. Matches the way llama.cpp
// + text-generation-webui report the loaded model over the OpenAI-compat
// /v1/models endpoint.
$recLlmModel  = (string)preg_replace('/\.[A-Z][A-Z0-9_]*\.gguf$|\.gguf$/i', '', $recGGUF) ?: 'model';
// LM Studio CLI: bartowski packages GGUF quant collections at
// <namespace>/<bare>-GGUF — derive that pattern so the `lms get …`
// command works for whichever model is at index 0.
$recLmsRepo = 'bartowski/' . $recLlmModel . '-GGUF';

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
                ['title' => 'Pull een model',    'body' => 'Eenmalig — Ollama beheert zijn eigen model-storage. Daarna start het instant.', 'code' => "ollama pull $recOllamaTag"],
                ['title' => 'Verifieer auto-start', 'body' => 'services.msc → Ollama → Startup type moet "Automatic" zijn. Standaard zo door de installer ingesteld; controleer na een Windows-update.'],
                ['title' => 'Configureer .env',  'body' => 'Voeg toe aan .env.production op de site:', 'code' => "LLM_URL=http://localhost:11434/api/generate\nLLM_MODEL=$recOllamaTag"],
            ],
            'linux' => [
                ['title' => 'Installeer Ollama', 'body' => 'Installer maakt een systemd-unit aan en enabled hem direct — auto-start na reboot is dus al geregeld.', 'code' => 'curl -fsSL https://ollama.com/install.sh | sh'],
                ['title' => 'Pull een model',    'body' => 'Eenmalig.', 'code' => "ollama pull $recOllamaTag"],
                ['title' => 'Verifieer auto-start', 'body' => 'systemctl status ollama moet "enabled" tonen.', 'code' => 'systemctl status ollama'],
                ['title' => 'Configureer .env',  'body' => 'Voeg toe aan .env.production:', 'code' => "LLM_URL=http://localhost:11434/api/generate\nLLM_MODEL=$recOllamaTag"],
            ],
            'darwin' => [
                ['title' => 'Installeer Ollama', 'body' => 'Via brew of de installer van de site.', 'code' => 'brew install ollama'],
                ['title' => 'Auto-start aanzetten', 'body' => 'Zonder dit moet je ollama na elke login zelf opstarten.', 'code' => 'brew services start ollama'],
                ['title' => 'Pull een model',    'body' => '', 'code' => "ollama pull $recOllamaTag"],
                ['title' => 'Configureer .env',  'body' => 'Voeg toe aan .env.production:', 'code' => "LLM_URL=http://localhost:11434/api/generate\nLLM_MODEL=$recOllamaTag"],
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
                ['title' => 'Download een model',   'body' => 'In de app: tab "Discover" → zoek bv. "gemma 4 E4B-it gguf" (zelfde model als de curated #1 in <a href="' . $llmModelsHref . '" target="_blank">Local LLM models</a>) → druk Download. Modellen landen in %USERPROFILE%\\.lmstudio\\models.'],
                ['title' => 'Start de Local Server', 'body' => 'Tab "Local Server" → kies het gedownloade model → "Start Server" (poort 1234). Vink "Start server when LM Studio starts" aan zodat hij na een herstart van de app meteen draait.'],
                ['title' => 'Auto-start na Windows-reboot', 'body' => 'Settings → "Run LM Studio at Windows login" aanvinken. Zonder dit start LM Studio NIET na een server-reboot en is je LLM-API offline.'],
                ['title' => 'Configureer .env', 'body' => 'Het LLM_MODEL is de id zoals je hem in LM Studio ziet. Voorbeeld:', 'code' => "LLM_URL=http://localhost:1234/v1\nLLM_MODEL=$recLlmModel"],
            ],
            'linux' => [
                ['title' => 'Installeer LM Studio', 'body' => 'Download AppImage van https://lmstudio.ai/, maak executable.'],
                ['title' => 'Download een model',   'body' => 'In-app via Discover, of CLI:', 'code' => "lms get $recLmsRepo"],
                ['title' => 'Start de server',      'body' => 'Eénmalig handmatig om te testen:', 'code' => 'lms server start --port 1234'],
                ['title' => 'Auto-start als user-service', 'body' => 'Maak ~/.config/systemd/user/lmstudio.service met:', 'code' => "[Unit]\nDescription=LM Studio API\n[Service]\nExecStart=/pad/naar/lms server start --port 1234\nRestart=always\n[Install]\nWantedBy=default.target"],
                ['title' => 'Enable + start', 'body' => '', 'code' => "systemctl --user enable --now lmstudio\nloginctl enable-linger \$USER  # ook draaien zonder ingelogde sessie"],
                ['title' => 'Configureer .env', 'body' => '', 'code' => "LLM_URL=http://localhost:1234/v1\nLLM_MODEL=$recLlmModel"],
            ],
            'darwin' => [
                ['title' => 'Installeer LM Studio', 'body' => '', 'code' => 'brew install --cask lm-studio'],
                ['title' => 'Download een model + start server', 'body' => 'In de app: Discover → Download → Local Server → Start.'],
                ['title' => 'Auto-start na login', 'body' => 'System Settings → General → Login Items → LM Studio toevoegen. (En in LM Studio: "Start server when LM Studio starts".)'],
                ['title' => 'Configureer .env', 'body' => '', 'code' => "LLM_URL=http://localhost:1234/v1\nLLM_MODEL=$recLlmModel"],
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
                ['title' => 'Download eerst een GGUF-model', 'body' => 'llama.cpp draait niet zonder model. Open <a href="' . $llmModelsHref . '" target="_blank">Local LLM models</a> en kies een curated GGUF, of plak een eigen Hugging Face URL. Default storage: C:\\llama\\models.'],
                ['title' => 'Pak llama-server binary',  'body' => 'Download een release uit https://github.com/ggerganov/llama.cpp/releases (bv. llama-bXXXX-bin-win-cpu-x64.zip voor CPU-only, of -cuda-… als je een NVIDIA GPU hebt) en pak het zip uit naar C:\\llama\\. llama-server.exe + de DLLs landen direct in die map, niet in een bin/-submap.'],
                ['title' => 'Test handmatig', 'body' => 'Verifieer eerst dat het werkt:', 'code' => "C:\\llama\\llama-server.exe -m C:\\llama\\models\\$recGGUF --port 8080"],
                ['title' => 'Auto-start via Task Scheduler', 'body' => 'Geen extra install nodig — Task Scheduler zit in elk Windows Server. Run als SYSTEM zodat de service draait zonder ingelogde gebruiker. PowerShell as Admin:', 'code' => "\$action    = New-ScheduledTaskAction -Execute 'C:\\llama\\llama-server.exe' -Argument '-m C:\\llama\\models\\$recGGUF --port 8080 --host 0.0.0.0'\n\$trigger   = New-ScheduledTaskTrigger -AtStartup\n\$settings  = New-ScheduledTaskSettingsSet -RestartCount 5 -RestartInterval (New-TimeSpan -Minutes 1) -StartWhenAvailable\n\$principal = New-ScheduledTaskPrincipal -UserId 'SYSTEM' -RunLevel Highest\nRegister-ScheduledTask -TaskName 'LlamaServer' -Action \$action -Trigger \$trigger -Settings \$settings -Principal \$principal -Force\nStart-ScheduledTask    -TaskName 'LlamaServer'"],
                ['title' => 'Status / stop / verwijderen', 'body' => 'Het verschijnt niet in services.msc — gebruik Task Scheduler GUI of:', 'code' => "Get-ScheduledTaskInfo  -TaskName 'LlamaServer'    # laatste run + status\nStop-ScheduledTask     -TaskName 'LlamaServer'\nUnregister-ScheduledTask -TaskName 'LlamaServer' -Confirm:\$false"],
                ['title' => 'Configureer .env', 'body' => '', 'code' => "LLM_URL=http://localhost:8080/v1\nLLM_MODEL=$recLlmModel"],
            ],
            'linux' => [
                ['title' => 'Download een GGUF-model', 'body' => 'Open <a href="' . $llmModelsHref . '" target="_blank">Local LLM models</a> of curl direct uit Hugging Face. Default storage: ~/llama-models.'],
                ['title' => 'Bouw of pak llama.cpp', 'body' => '', 'code' => "git clone https://github.com/ggerganov/llama.cpp\ncd llama.cpp && make -j"],
                ['title' => 'Test handmatig', 'body' => '', 'code' => "./llama-server -m ~/llama-models/$recGGUF --port 8080"],
                ['title' => 'Systemd-unit voor auto-start', 'body' => 'Schrijf /etc/systemd/system/llama-server.service:', 'code' => "[Unit]\nDescription=llama.cpp OpenAI-compat server\nAfter=network.target\n[Service]\nExecStart=/opt/llama.cpp/llama-server -m /opt/models/$recGGUF --port 8080\nRestart=always\nUser=llama\n[Install]\nWantedBy=multi-user.target"],
                ['title' => 'Enable + start', 'body' => '', 'code' => 'sudo systemctl enable --now llama-server'],
                ['title' => 'Configureer .env', 'body' => '', 'code' => "LLM_URL=http://localhost:8080/v1\nLLM_MODEL=$recLlmModel"],
            ],
            'darwin' => [
                ['title' => 'Download een GGUF-model', 'body' => 'Via <a href="' . $llmModelsHref . '" target="_blank">Local LLM models</a> of brew/curl.'],
                ['title' => 'Installeer llama.cpp', 'body' => '', 'code' => 'brew install llama.cpp'],
                ['title' => 'Test handmatig', 'body' => '', 'code' => "llama-server -m ~/llama-models/$recGGUF --port 8080"],
                ['title' => 'Auto-start via brew services', 'body' => 'llama.cpp heeft geen native service-recept; wrap met een launchd plist of run via brew-services-template.'],
                ['title' => 'Configureer .env', 'body' => '', 'code' => "LLM_URL=http://localhost:8080/v1\nLLM_MODEL=$recLlmModel"],
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
                ['title' => 'Download een GGUF-model (optioneel — kan ook in-webui)', 'body' => 'Via <a href="' . $llmModelsHref . '" target="_blank">Local LLM models</a> of in webui later via tab Model.'],
                ['title' => 'Clone + initial setup', 'body' => 'start_windows.bat draait éénmalig de installer (Python venv, deps).', 'code' => "git clone https://github.com/oobabooga/text-generation-webui\ncd text-generation-webui\nstart_windows.bat"],
                ['title' => 'Enable API mode', 'body' => 'Bewerk CMD_FLAGS.txt — voeg toe:', 'code' => '--api --api-port 5000 --listen'],
                ['title' => 'Auto-start via Task Scheduler', 'body' => 'Built-in Windows, geen extra install. Wrap start_windows.bat als SYSTEM-task. PowerShell as Admin:', 'code' => "\$action    = New-ScheduledTaskAction -Execute 'C:\\pad\\text-generation-webui\\start_windows.bat' -WorkingDirectory 'C:\\pad\\text-generation-webui'\n\$trigger   = New-ScheduledTaskTrigger -AtStartup\n\$settings  = New-ScheduledTaskSettingsSet -RestartCount 5 -RestartInterval (New-TimeSpan -Minutes 2) -StartWhenAvailable\n\$principal = New-ScheduledTaskPrincipal -UserId 'SYSTEM' -RunLevel Highest\nRegister-ScheduledTask -TaskName 'TextGenWebui' -Action \$action -Trigger \$trigger -Settings \$settings -Principal \$principal -Force\nStart-ScheduledTask    -TaskName 'TextGenWebui'"],
                ['title' => 'Status / verwijderen', 'body' => '', 'code' => "Get-ScheduledTaskInfo  -TaskName 'TextGenWebui'\nUnregister-ScheduledTask -TaskName 'TextGenWebui' -Confirm:\$false"],
                ['title' => 'Configureer .env', 'body' => '', 'code' => "LLM_URL=http://localhost:5000/v1\nLLM_MODEL=$recLlmModel"],
            ],
            'linux' => [
                ['title' => 'Download een GGUF-model', 'body' => 'Via <a href="' . $llmModelsHref . '" target="_blank">Local LLM models</a> of in webui.'],
                ['title' => 'Clone + initial setup', 'body' => '', 'code' => "git clone https://github.com/oobabooga/text-generation-webui\ncd text-generation-webui\n./start_linux.sh"],
                ['title' => 'Enable API mode', 'body' => 'Bewerk CMD_FLAGS.txt:', 'code' => '--api --api-port 5000 --listen'],
                ['title' => 'Systemd-unit', 'body' => 'Schrijf /etc/systemd/system/textgen-webui.service:', 'code' => "[Unit]\nDescription=text-generation-webui API\nAfter=network.target\n[Service]\nWorkingDirectory=/opt/text-generation-webui\nExecStart=/opt/text-generation-webui/start_linux.sh\nRestart=always\nUser=textgen\n[Install]\nWantedBy=multi-user.target"],
                ['title' => 'Enable + start', 'body' => '', 'code' => 'sudo systemctl enable --now textgen-webui'],
                ['title' => 'Configureer .env', 'body' => '', 'code' => "LLM_URL=http://localhost:5000/v1\nLLM_MODEL=$recLlmModel"],
            ],
            'darwin' => [
                ['title' => 'Download een GGUF-model', 'body' => 'Via <a href="' . $llmModelsHref . '" target="_blank">Local LLM models</a> of in webui.'],
                ['title' => 'Clone + initial setup', 'body' => '', 'code' => "git clone https://github.com/oobabooga/text-generation-webui\ncd text-generation-webui\n./start_macos.sh"],
                ['title' => 'Enable API mode', 'body' => 'Bewerk CMD_FLAGS.txt:', 'code' => '--api --api-port 5000 --listen'],
                ['title' => 'Auto-start na login via launchd', 'body' => 'Schrijf ~/Library/LaunchAgents/com.oobabooga.textgen.plist met ProgramArguments wijzend naar start_macos.sh + RunAtLoad=true.'],
                ['title' => 'Configureer .env', 'body' => '', 'code' => "LLM_URL=http://localhost:5000/v1\nLLM_MODEL=$recLlmModel"],
            ],
        ],
        'docs'        => 'https://github.com/oobabooga/text-generation-webui',
    ],
    // Backup route — used by App\Llm::generate() when the configured
    // local Ollama can't be reached. Probed against api.anthropic.com
    // with the resolved key (LLM_KEY > OCR_VISION_KEY) so the operator
    // sees whether the fallback would actually work, side by side with
    // the local engines.
    'anthropic_fallback' => [
        'name'         => 'Anthropic Claude (fallback)',
        'default_url'  => 'https://api.anthropic.com',
        'env_var'      => null,
        'probe_path'   => '/v1/models',
        'probe_kind'   => 'anthropic',
        'extract'      => function (array $j): ?array {
            if (!isset($j['data']) || !is_array($j['data'])) return null;
            $out = [];
            foreach ($j['data'] as $m) {
                $name = (string)($m['display_name'] ?? $m['id'] ?? '');
                if ($name === '') continue;
                $out[] = [
                    'id'     => $name,
                    'size'   => null,
                    'detail' => isset($m['created_at']) ? substr((string)$m['created_at'], 0, 10) : null,
                ];
            }
            // Anthropic returns a long list; show the 6 newest to keep
            // the card compact. Caller's "show all" is a click-through
            // to docs.
            return array_slice($out, 0, 6);
        },
        'setup' => [
            'windows' => [
                ['title' => 'Maak een Anthropic-account + API-key', 'body' => 'https://console.anthropic.com → Account → API Keys → Create Key. Kopieer eenmalig (Anthropic toont hem daarna niet meer).'],
                ['title' => 'Topup credits', 'body' => 'Anthropic Console → Plans &amp; Billing → kies "Build" (pay-as-you-go) en zet €5-10 op. Recipe-parse-call kost ~€0,001-0,005.'],
                ['title' => 'Configureer .env', 'body' => 'Zet in .env.production. LLM_KEY wordt automatisch ook als OCR-vision-key gebruikt mits OCR_VISION_PROVIDER ongezet of "anthropic" is.', 'code' => "LLM_KEY=sk-ant-api03-XXXXXXXXXXXX\nLLM_FALLBACK_MODEL=claude-haiku-4-5"],
                ['title' => 'Geen Ollama? Forceer Anthropic als primair', 'body' => 'Standaard is Ollama de primary en Anthropic de fallback. Wil je vanaf nul Anthropic gebruiken, zet:', 'code' => "LLM_PROVIDER=anthropic"],
            ],
            'linux' => [
                ['title' => 'Maak een Anthropic-account + API-key', 'body' => 'https://console.anthropic.com → API Keys → Create Key.'],
                ['title' => 'Topup credits', 'body' => 'Console → Plans &amp; Billing → "Build" plan, ~€5-10 startbudget.'],
                ['title' => 'Configureer .env', 'body' => '', 'code' => "LLM_KEY=sk-ant-api03-XXXXXXXXXXXX\nLLM_FALLBACK_MODEL=claude-haiku-4-5"],
                ['title' => 'Forceer als primair (optioneel)', 'body' => '', 'code' => "LLM_PROVIDER=anthropic"],
            ],
            'darwin' => [
                ['title' => 'Maak een Anthropic-account + API-key', 'body' => 'https://console.anthropic.com → API Keys → Create Key.'],
                ['title' => 'Topup credits', 'body' => 'Console → Plans &amp; Billing.'],
                ['title' => 'Configureer .env', 'body' => '', 'code' => "LLM_KEY=sk-ant-api03-XXXXXXXXXXXX\nLLM_FALLBACK_MODEL=claude-haiku-4-5"],
            ],
        ],
        'docs' => 'https://docs.anthropic.com/en/api/getting-started',
    ],
];

// =========================================================================
// Probing
// =========================================================================

/**
 * Returns ['ok' => bool, 'status' => int|null, 'body' => string|null, 'error' => string|null, 'ms' => float]
 *
 * Optional $headers lets the caller add request headers (Anthropic
 * fallback uses this for x-api-key + anthropic-version). HTTPS callers
 * get the same two-tier TLS retry as RecipeFetcher / recipe_ocr —
 * fall back to peer-verify=off on dev/test if curl.cainfo is missing.
 */
function llm_probe(string $url, float $timeout = 1.5, array $headers = []): array
{
    $started = microtime(true);
    $result = ['ok' => false, 'status' => null, 'body' => null, 'error' => null, 'ms' => 0];

    if (!function_exists('curl_init')) {
        $result['error'] = 'PHP cURL-extensie ontbreekt';
        return $result;
    }

    $r = llm_probe_once($url, $timeout, $headers, true);

    $isCaError = $r['body'] === null && (
        stripos((string)$r['error'], 'unable to get local issuer certificate') !== false
        || stripos((string)$r['error'], 'CAfile') !== false
        || stripos((string)$r['error'], 'self-signed') !== false
    );
    if ($isCaError) {
        error_log('[tools_llm] TLS CA store missing — retrying without peer verification. '
                . 'Configure curl.cainfo in php.ini before deploying.');
        $r = llm_probe_once($url, $timeout, $headers, false);
    }

    $result['ms']     = round((microtime(true) - $started) * 1000, 1);
    $result['status'] = $r['status'] ?: null;
    if ($r['body'] === null) {
        $result['error'] = $r['error'] !== '' ? $r['error'] : 'onbekende fout';
        return $result;
    }
    $code = (int)$r['status'];
    if ($code >= 200 && $code < 300) {
        $result['ok']   = true;
        $result['body'] = $r['body'];
    } else {
        // 4xx with a JSON error body — surface the message so e.g. an
        // Anthropic 401 reads "invalid x-api-key" instead of just "HTTP 401".
        $msg = "HTTP $code";
        $j   = json_decode((string)$r['body'], true);
        if (is_array($j) && isset($j['error']['message'])) {
            $msg .= ' — ' . (string)$j['error']['message'];
        }
        $result['error'] = $msg;
    }
    return $result;
}

/**
 * Single curl attempt for llm_probe.
 *
 * @return array{body:?string, status:int, error:string}
 */
function llm_probe_once(string $url, float $timeout, array $headers, bool $verifyPeer): array
{
    $ch = curl_init($url);
    if ($ch === false) {
        return ['body' => null, 'status' => 0, 'error' => 'curl_init failed'];
    }
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT_MS     => (int)($timeout * 1000),
        CURLOPT_CONNECTTIMEOUT_MS => (int)(min($timeout, 2.0) * 1000),
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_USERAGENT      => 'cma-llm-management/1.0',
        CURLOPT_SSL_VERIFYPEER => $verifyPeer,
        CURLOPT_SSL_VERIFYHOST => $verifyPeer ? 2 : 0,
    ];
    if ($headers !== []) {
        $opts[CURLOPT_HTTPHEADER] = $headers;
    }
    curl_setopt_array($ch, $opts);
    $body   = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err    = (string)curl_error($ch);
    // PHP 8.0+ auto-closes; curl_close deprecated in 8.5.
    if ($body === false) {
        return ['body' => null, 'status' => $status, 'error' => $err];
    }
    return ['body' => (string)$body, 'status' => $status, 'error' => ''];
}

/**
 * Resolve a usable Anthropic API key — mirrors App\Llm::anthropicFallbackKey.
 * Prefers LLM_KEY, falls back to OCR_VISION_KEY when OCR_VISION_PROVIDER
 * is "anthropic" (or unset, which defaults to anthropic).
 */
function llm_anthropic_key(): string
{
    $k = trim((string)($_ENV['LLM_KEY'] ?? (getenv('LLM_KEY') ?: '')));
    if ($k !== '') { return $k; }
    $ocrKey      = trim((string)($_ENV['OCR_VISION_KEY']      ?? (getenv('OCR_VISION_KEY') ?: '')));
    $ocrProvider = strtolower(trim((string)($_ENV['OCR_VISION_PROVIDER'] ?? (getenv('OCR_VISION_PROVIDER') ?: 'anthropic'))));
    if ($ocrKey !== '' && $ocrProvider === 'anthropic') {
        return $ocrKey;
    }
    return '';
}

/**
 * Mask the visible part of an API key so the operator can confirm
 * "the right key is configured" without exposing the whole secret on
 * a page that survives in browser-history caches. sk-ant-api03-AbC…XyZ.
 */
function llm_mask_key(string $k): string
{
    $n = strlen($k);
    if ($n <= 12) return $k === '' ? '(geen)' : str_repeat('*', $n);
    return substr($k, 0, 8) . '…' . substr($k, -4);
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
        $kind = (string)($eng['probe_kind'] ?? 'local');

        // ---- Anthropic-fallback path -----------------------------------
        // No port; auth via x-api-key header from .env. If no key is
        // resolvable we surface a clear "geen API-key" state instead of
        // making a doomed 401 round-trip.
        if ($kind === 'anthropic') {
            $apiKey = llm_anthropic_key();
            $url    = $eng['default_url'] . $eng['probe_path'];
            if ($apiKey === '') {
                $probe = [
                    'ok' => false, 'status' => null, 'body' => null,
                    'error' => 'Geen API-key in .env (zet LLM_KEY of OCR_VISION_KEY)',
                    'ms' => 0.0,
                ];
                $models = null;
                $extras = ['key_masked' => '(geen)'];
            } else {
                $probe = llm_probe($url, 5.0, [
                    'x-api-key: ' . $apiKey,
                    'anthropic-version: 2023-06-01',
                    'accept: application/json',
                ]);
                $models = null;
                if ($probe['ok']) {
                    $j = json_decode((string)$probe['body'], true);
                    if (is_array($j)) {
                        $models = $eng['extract']($j);
                    }
                }
                $fbModel = trim((string)($_ENV['LLM_FALLBACK_MODEL'] ?? getenv('LLM_FALLBACK_MODEL') ?: '')) ?: 'claude-haiku-4-5';
                $extras = ['key_masked' => llm_mask_key($apiKey), 'fallback_model' => $fbModel];
            }
            $results[$key] = [
                'engine' => $eng,
                'url'    => $url,
                'base'   => $eng['default_url'],
                'probe'  => $probe,
                'models' => $models,
                'extras' => $extras,
            ];
            continue;
        }

        // ---- Local-engine path (Ollama / LM Studio / llama.cpp / textgen)
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
            'extras' => null,
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

        // Anthropic-fallback card surfaces the configured key (masked) +
        // which model the fallback would route to — same info the cook
        // gets at runtime if Ollama drops off.
        if (!empty($r['extras']['key_masked'])) {
            echo '<div class="url" style="margin-top:2px">API-key: ' . htmlspecialchars((string)$r['extras']['key_masked']) . '</div>';
        }
        if (!empty($r['extras']['fallback_model'])) {
            echo '<div class="url" style="margin-top:2px">Fallback-model: <strong>' . htmlspecialchars((string)$r['extras']['fallback_model']) . '</strong></div>';
        }

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
