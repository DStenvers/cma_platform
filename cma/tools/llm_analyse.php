<?php
/**
 * llm_analyse.php — Local-LLM status dashboard.
 *
 * What it shows:
 *   - Active LLM env config (LLM_PROVIDER / LLM_URL / LLM_MODEL).
 *   - Live reachability probe of LLM_URL (HEAD / GET /v1/models).
 *   - Whether any GGUF models are installed locally + a CTA over to
 *     LLM-management (tools_llm.php) when none are.
 *   - Anthropic fallback presence — confirms the safety net is wired.
 *   - Last few LLM-related errors from logs/php_errors.log so the
 *     operator can see what's failing without scraping the box.
 *
 * Auth: standard CMA admin login (was a DEPLOY_SECRET ?key= flow until
 * v1.13.x — refactored to match every other tools/ page now that the
 * platform's broader bootstrap is reliable enough not to need the
 * secret-door fallback).
 */

declare(strict_types=1);

use App\Library\Response;
use Cma\SecurityHelper;
use Cma\ToolbarHelper;

require_once __DIR__ . '/../bootstrap.inc';

if (!SecurityHelper::isAdmin()) {
    http_response_code(403);
    echo '<lib-message type="error">Geen toegang — alleen beheerders</lib-message>';
    exit;
}

Response::noCache();

$siteRoot = dirname(__DIR__, 2);

// ---------------------------------------------------------------------------
// Env probes — getenv() + $_ENV after bootstrap loaded phpdotenv.
// ---------------------------------------------------------------------------
$envRead = static function (string $key): string {
    $v = getenv($key);
    if ($v !== false && $v !== '') { return (string)$v; }
    return (string)($_ENV[$key] ?? '');
};

$llmUrl    = $envRead('LLM_URL');
$llmModel  = $envRead('LLM_MODEL');
$llmProv   = $envRead('LLM_PROVIDER');
$llmKey    = $envRead('LLM_KEY');
$visionKey = $envRead('OCR_VISION_KEY');

// ---------------------------------------------------------------------------
// Probe the configured LLM endpoint. /v1/models is the universal "are you
// alive?" — OpenAI, LM Studio, llama-server, and Ollama's OAI-shim all
// expose it; Ollama-native exposes /api/tags.
// ---------------------------------------------------------------------------
$probe = ['url' => '', 'http' => 0, 'err' => '', 'models' => [], 'kind' => '?'];
if ($llmUrl !== '') {
    $base = preg_replace('#/(?:api|v1)/.*$#', '', rtrim($llmUrl, '/'));
    $candidates = [
        ['kind' => 'openai', 'url' => $base . '/v1/models'],
        ['kind' => 'ollama', 'url' => $base . '/api/tags'],
    ];
    foreach ($candidates as $c) {
        $ch = curl_init($c['url']);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 5,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_HTTPHEADER     => ['accept: application/json'],
        ]);
        $body = curl_exec($ch);
        $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);
        if ($body !== false && $http >= 200 && $http < 300) {
            $probe = ['url' => $c['url'], 'http' => $http, 'err' => '', 'kind' => $c['kind'], 'models' => []];
            $j = json_decode((string)$body, true);
            if ($c['kind'] === 'openai' && is_array($j['data'] ?? null)) {
                $probe['models'] = array_map(fn($m) => (string)($m['id'] ?? ''), $j['data']);
            } elseif ($c['kind'] === 'ollama' && is_array($j['models'] ?? null)) {
                $probe['models'] = array_map(fn($m) => (string)($m['name'] ?? ''), $j['models']);
            }
            break;
        }
        $probe = ['url' => $c['url'], 'http' => $http, 'err' => $err, 'kind' => $c['kind'], 'models' => []];
    }
}

// ---------------------------------------------------------------------------
// Local models directory scan. LLM_MODELS_DIR env override; falls back to
// the same C:\llama\models / ~/llama-models defaults the rest of the app uses.
// ---------------------------------------------------------------------------
$isWin = stripos(PHP_OS_FAMILY, 'WIN') === 0;
$modelsDir = $envRead('LLM_MODELS_DIR');
if ($modelsDir === '') {
    $modelsDir = $isWin ? 'C:\\llama\\models' : ((getenv('HOME') ?: '') . '/llama-models');
}
$installed = is_dir($modelsDir) ? glob($modelsDir . DIRECTORY_SEPARATOR . '*.gguf') : [];

// ---------------------------------------------------------------------------
// Recent LLM-related errors from the app's PHP error log (best-effort).
// Filter is intentionally narrow — [Llm] is the tag App\Library\Llm emits
// on every log call, and `LLM ` catches occasional ad-hoc messages. Other
// errors stay out of view so this surface doesn't become a generic log
// reader.
// ---------------------------------------------------------------------------
$logFile = $siteRoot . '/logs/php_errors.log';
$recentErrors = [];
if (is_file($logFile)) {
    $size = filesize($logFile) ?: 0;
    $f = @fopen($logFile, 'rb');
    if ($f) {
        @fseek($f, max(0, $size - 65536)); // tail last 64KB
        $tail = (string)stream_get_contents($f);
        @fclose($f);
        foreach (preg_split('/\r?\n/', $tail) ?: [] as $line) {
            if (stripos($line, '[Llm]') !== false
             || stripos($line, 'LLM ') !== false) {
                $recentErrors[] = $line;
            }
        }
        $recentErrors = array_slice($recentErrors, -10);
    }
}

$modelsRoute = 'tools_llm.php';

// ---------------------------------------------------------------------------
// Render — standard CMA tools shell.
// ---------------------------------------------------------------------------
cma_html_header('LLM-status');
echo '<body class="contentbody tools tool-llm-analyse">';
ToolbarHelper::start(true);
ToolbarHelper::title('LLM-status');
ToolbarHelper::separator();
ToolbarHelper::status('Configuratie, endpoint-probe en recente fouten van de LLM-pipeline');
ToolbarHelper::startRight();
ToolbarHelper::button($modelsRoute, 'lnr-brain', true, 'LLM management', 'Engine-status, installatie-stappen en aanbevolen modellen');
ToolbarHelper::button('javascript:location.reload()', 'lnr-sync', true, 'Vernieuwen', 'Probe opnieuw uitvoeren');
ToolbarHelper::end();
echo '<div id="c" class="tools">';
?>

<h2><span class="lnr lnr-cog"></span> Configuratie</h2>
<table class="listtable">
    <thead>
        <tr class="listheader">
            <th style="width:280px">Variabele</th>
            <th>Waarde</th>
        </tr>
    </thead>
    <tbody>
        <tr><td><code>LLM_PROVIDER</code></td><td><?= $llmProv !== '' ? '<code>' . htmlspecialchars($llmProv) . '</code>' : '<span class="text-muted">(unset → auto)</span>' ?></td></tr>
        <tr><td><code>LLM_URL</code></td><td><?= $llmUrl !== ''  ? '<code>' . htmlspecialchars($llmUrl)  . '</code>' : '<span class="text-muted">(unset)</span>' ?></td></tr>
        <tr><td><code>LLM_MODEL</code></td><td><?= $llmModel !== '' ? '<code>' . htmlspecialchars($llmModel) . '</code>' : '<span class="text-muted">(unset → auto-pick)</span>' ?></td></tr>
        <tr><td><code>LLM_KEY</code></td><td><?= $llmKey !== '' ? '<lib-label type="success">set</lib-label>' : '<span class="text-muted">(unset)</span>' ?></td></tr>
        <tr><td><code>OCR_VISION_KEY</code> <span class="text-muted">(Anthropic-fallback)</span></td><td><?= $visionKey !== '' ? '<lib-label type="success">set</lib-label>' : '<span class="text-muted">(unset)</span>' ?></td></tr>
    </tbody>
</table>

<h2 style="margin-top:25px;"><span class="lnr lnr-database"></span> Lokale modellen <span class="text-muted" style="font-weight:normal;font-size:var(--font-size-sm);">(<?= htmlspecialchars($modelsDir) ?>)</span></h2>
<?php if (!$installed): ?>
<lib-message type="warning">
    Nog geen GGUF-modellen geïnstalleerd. Een lokale LLM heeft een model nodig om mee te starten —
    bekijk de aanbevolen modellen + installatie-stappen op de LLM-management pagina.
    <div style="margin-top:10px;"><a class="btn btn-primary" href="<?= htmlspecialchars($modelsRoute) ?>"><span class="lnr lnr-brain"></span> Naar LLM-management</a></div>
</lib-message>
<?php else: ?>
<table class="listtable">
    <thead>
        <tr class="listheader">
            <th>Bestand</th>
            <th style="width:120px;text-align:right;">Grootte</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($installed as $f): ?>
        <tr>
            <td><code><?= htmlspecialchars(basename($f)) ?></code></td>
            <td style="text-align:right;"><?= number_format((float)((filesize($f) ?: 0) / (1024 * 1024)), 0) ?> MB</td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<div style="margin-top:10px;"><a class="btn btn-secondary" href="<?= htmlspecialchars($modelsRoute) ?>"><span class="lnr lnr-plus"></span> Meer modellen</a></div>
<?php endif; ?>

<h2 style="margin-top:25px;"><span class="lnr lnr-cloud-sync"></span> Endpoint-probe</h2>
<?php if ($llmUrl === ''): ?>
<lib-message type="info">
    <code>LLM_URL</code> is niet gezet — alle LLM-calls vallen direct terug op de Anthropic-fallback.
    Status van de API-key:
    <?= $visionKey === '' && $llmKey === ''
        ? '<lib-label type="error">geen API-key gevonden</lib-label>'
        : '<lib-label type="success">API-key beschikbaar</lib-label>' ?>
</lib-message>
<?php elseif ($probe['http'] >= 200 && $probe['http'] < 300): ?>
<lib-message type="success">
    <code><?= htmlspecialchars($probe['url']) ?></code>
    <lib-label type="success">HTTP <?= (int)$probe['http'] ?></lib-label>
    <span class="text-muted">(<?= htmlspecialchars($probe['kind']) ?>-shape)</span>
    <?php if ($probe['models']): ?>
    <div style="margin-top:8px;">Geladen modellen volgens de server:</div>
    <ul style="margin:4px 0 0 1.2rem;padding:0;">
        <?php foreach ($probe['models'] as $m): ?>
        <li><code><?= htmlspecialchars($m) ?></code><?= $llmModel !== '' && stripos($m, $llmModel) !== false ? ' <lib-label type="success">matches LLM_MODEL</lib-label>' : '' ?></li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
</lib-message>
<?php else: ?>
<lib-message type="error">
    Endpoint onbereikbaar — HTTP <?= (int)$probe['http'] ?>.
    <?php if ($probe['err'] !== ''): ?><br><code><?= htmlspecialchars($probe['err']) ?></code><?php endif; ?>
    <div style="margin-top:8px;" class="text-muted">Het Anthropic-vangnet werkt nog wel zolang <code>OCR_VISION_KEY</code> of <code>LLM_KEY</code> is gezet.</div>
</lib-message>
<?php endif; ?>

<h2 style="margin-top:25px;"><span class="lnr lnr-bug"></span> Recente fouten in <code>logs/php_errors.log</code></h2>
<?php if ($recentErrors): ?>
<pre class="log-output" style="max-height:18rem;overflow-y:auto;color:var(--color-error,#c0392b);"><?= htmlspecialchars(implode("\n", $recentErrors)) ?></pre>
<?php else: ?>
<lib-message type="info" compact>Geen recente <code>[Llm]</code> regels in de laatste 64KB van het logbestand.</lib-message>
<?php endif; ?>

<h2 style="margin-top:25px;"><span class="lnr lnr-list"></span> Volgende stappen</h2>
<ol>
    <li>Geen model geïnstalleerd? <a href="<?= htmlspecialchars($modelsRoute) ?>">Open LLM-management</a> voor aanbevolen modellen + <code>ollama pull</code> commando's.</li>
    <li>Na install: zet <code>LLM_PROVIDER</code>, <code>LLM_URL</code> en <code>LLM_MODEL</code> in <code>.env</code>.</li>
    <li>Touch <code>web.config</code> (<code>copy /b web.config +,,</code>) om de IIS-app-pool te recyclen zodat de nieuwe env wordt opgepakt.</li>
    <li>Klik <span class="cma-tool__em">Vernieuwen</span> in de toolbar — endpoint-probe en model-list moeten kloppen.</li>
</ol>

<?php
echo '</div></body>';
