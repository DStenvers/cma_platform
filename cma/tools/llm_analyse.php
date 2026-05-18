<?php
/**
 * llm_analyse.php — Local-LLM status dashboard + entry point.
 *
 * URL: https://<host>/cma/tools/llm_analyse.php?key=<DEPLOY_SECRET>
 *
 * What it shows:
 *   - Active LLM env config (LLM_PROVIDER / LLM_URL / LLM_MODEL).
 *   - Live reachability probe of LLM_URL (HEAD / GET /v1/models).
 *   - Whether any GGUF models are installed locally + a one-click
 *     button to the install tool (llm_models.php) when none are.
 *   - Anthropic fallback presence — confirms the safety net is wired.
 *   - Last few /api/recipe-parse errors from logs/php_errors.log so
 *     the operator can see what's failing without scraping the box.
 *
 * "For an LLM to be able to start we need a model first" — that flow
 * is now: open this page → see no installed models → click button →
 * land on llm_models.php → install one → return here, configure
 * LLM_URL / LLM_MODEL in .env, start llama-server.
 *
 * Auth: DEPLOY_SECRET (same pattern as diag.php + deploy_webhook.php).
 * Self-contained — runs without composer autoload so the page works
 * even when the broader app is broken.
 */

declare(strict_types=1);

$siteRoot = dirname(__DIR__, 2);

$resolveEnv = static function (string $siteRoot, string $key): string {
    $v = getenv($key);
    if ($v !== false && $v !== '') return $v;
    if (!empty($_ENV[$key])) return (string)$_ENV[$key];
    foreach (['.env.production', '.env.acceptance', '.env.test', '.env.development', '.env.local', '.env'] as $f) {
        $path = $siteRoot . '/' . $f;
        if (!is_file($path)) continue;
        foreach ((array)file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            if (preg_match('/^\s*' . preg_quote($key, '/') . '\s*=\s*["\']?([^"\'\r\n#]+)/', $line, $m)) {
                return trim($m[1]);
            }
        }
    }
    return '';
};

$secret = $resolveEnv($siteRoot, 'DEPLOY_SECRET');
$given  = (string)($_GET['key'] ?? '');
if ($secret === '' || !hash_equals($secret, $given)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo "403 — pass ?key=<DEPLOY_SECRET>.\n";
    return;
}

// ---------------------------------------------------------------------------
// Probe the configured LLM endpoint.  We treat /v1/models as the
// universal "are you alive?" — OpenAI, LM Studio, llama-server, and
// Ollama's OAI-shim all expose it; Ollama-native exposes /api/tags.
// ---------------------------------------------------------------------------
$llmUrl   = $resolveEnv($siteRoot, 'LLM_URL');
$llmModel = $resolveEnv($siteRoot, 'LLM_MODEL');
$llmProv  = $resolveEnv($siteRoot, 'LLM_PROVIDER');
$llmKey   = $resolveEnv($siteRoot, 'LLM_KEY');
$visionKey= $resolveEnv($siteRoot, 'OCR_VISION_KEY');

$probe = ['url' => '', 'http' => 0, 'err' => '', 'models' => [], 'kind' => '?'];
if ($llmUrl !== '') {
    // Strip any /api/* or /v1/* suffix and try /v1/models first.
    $base = preg_replace('#/(?:api|v1)/.*$#', '', rtrim($llmUrl, '/'));
    $candidates = [
        ['kind' => 'openai',  'url' => $base . '/v1/models'],
        ['kind' => 'ollama',  'url' => $base . '/api/tags'],
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
            $probe = ['url' => $c['url'], 'http' => $http, 'err' => '', 'kind' => $c['kind']];
            $j = json_decode((string)$body, true);
            if ($c['kind'] === 'openai' && is_array($j['data'] ?? null)) {
                $probe['models'] = array_map(fn($m) => (string)($m['id'] ?? ''), $j['data']);
            } elseif ($c['kind'] === 'ollama' && is_array($j['models'] ?? null)) {
                $probe['models'] = array_map(fn($m) => (string)($m['name'] ?? ''), $j['models']);
            }
            break;
        }
        $probe = ['url' => $c['url'], 'http' => $http, 'err' => $err, 'kind' => $c['kind']];
    }
}

// ---------------------------------------------------------------------------
// Local models directory scan (same default as llm_models.php).
// ---------------------------------------------------------------------------
$isWin = stripos(PHP_OS_FAMILY, 'WIN') === 0;
$modelsDir = $resolveEnv($siteRoot, 'LLM_MODELS_DIR');
if ($modelsDir === '') {
    $modelsDir = $isWin ? 'C:\\llama\\models' : (getenv('HOME') . '/llama-models');
}
$installed = is_dir($modelsDir) ? glob($modelsDir . DIRECTORY_SEPARATOR . '*.gguf') : [];

// ---------------------------------------------------------------------------
// Recent recipe-parse errors from the app's PHP error log (best-effort).
// ---------------------------------------------------------------------------
$logFile = $siteRoot . '/logs/php_errors.log';
$recentErrors = [];
if (is_file($logFile)) {
    $size = filesize($logFile) ?: 0;
    $f = @fopen($logFile, 'rb');
    if ($f) {
        @fseek($f, max(0, $size - 65536));     // tail last 64KB
        $tail = (string)stream_get_contents($f);
        @fclose($f);
        foreach (preg_split('/\r?\n/', $tail) ?: [] as $line) {
            if (stripos($line, 'recipe_parse') !== false || stripos($line, '[Llm]') !== false || stripos($line, 'LLM ') !== false) {
                $recentErrors[] = $line;
            }
        }
        $recentErrors = array_slice($recentErrors, -10);
    }
}

$h = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
$installRoute = '?key=' . urlencode($given);
$modelsRoute  = 'llm_models.php?key=' . urlencode($given);

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="nl-NL"><head>
<meta charset="utf-8"><title>LLM-status</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
:root { color-scheme: light; --b:#e1ddd1; --bg:#f8f4ed; --ink:#1a1a1a; --mute:#5a5042; --acc:#2d4a35; --err:#7a1d1d; --warn:#7a5a00; --ok:#244c2c; }
* { box-sizing: border-box }
body { font: 14px/1.5 system-ui, sans-serif; background: var(--bg); color: var(--ink); margin: 0; padding: 2rem 1.5rem; }
main { max-width: 60rem; margin: 0 auto; }
h1 { font-size: 1.5rem; margin: 0 0 0.5rem; }
h2 { font-size: 1.1rem; margin: 1.75rem 0 0.5rem; }
.muted { color: var(--mute); font-size: 0.9rem; }
table { width: 100%; border-collapse: collapse; background: #fff; border: 1px solid var(--b); border-radius: 6px; overflow: hidden; }
th, td { padding: 0.55rem 0.8rem; text-align: left; border-bottom: 1px solid var(--b); vertical-align: top; }
th { background: #efe8d8; font-weight: 600; font-size: 0.85rem; color: var(--mute); white-space: nowrap; }
tr:last-child td { border-bottom: 0; }
.cmd { font-family: ui-monospace, Menlo, Consolas, monospace; background: #efe8d8; padding: 0.15rem 0.45rem; border-radius: 3px; font-size: 0.85rem; word-break: break-all; }
.badge { display: inline-block; padding: 0.1rem 0.55rem; border-radius: 999px; font-size: 0.75rem; font-weight: 600; }
.badge--ok   { background: #d8e8d8; color: var(--ok); }
.badge--err  { background: #f7d8d8; color: var(--err); }
.badge--warn { background: #fff0c2; color: var(--warn); }
.btn { display: inline-block; padding: 0.5rem 1rem; background: var(--acc); color: #fff; border: 0; border-radius: 4px; font: inherit; font-weight: 600; cursor: pointer; text-decoration: none; }
.btn:hover { filter: brightness(1.1); }
.btn--ghost { background: #fff; color: var(--acc); border: 1px solid var(--acc); }
.cta-row { display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap; margin: 0.5rem 0 0; }
.callout { padding: 1rem 1.2rem; border-left: 4px solid var(--acc); background: #fff; border-radius: 4px; margin: 0.5rem 0; }
.callout--warn { border-color: var(--warn); }
.callout--err  { border-color: var(--err); }
ul { margin: 0.3rem 0 0; padding-left: 1.2rem; }
li { margin: 0.15rem 0; }
.log { font-family: ui-monospace, Menlo, Consolas, monospace; font-size: 0.78rem; white-space: pre-wrap; background: #fff; border: 1px solid var(--b); padding: 0.6rem 0.8rem; border-radius: 4px; max-height: 18rem; overflow-y: auto; color: var(--err); }
</style></head><body><main>

<h1>LLM-status</h1>
<p class="muted">Snelle controle van de receptenparser-keten: lokale model + endpoint + Anthropic-vangnet.</p>

<h2>1. Configuratie</h2>
<table>
  <tr><th>LLM_PROVIDER</th><td><?= $h($llmProv ?: '(unset → auto)') ?></td></tr>
  <tr><th>LLM_URL</th><td><?= $llmUrl === '' ? '<span class="muted">(unset)</span>' : '<span class="cmd">' . $h($llmUrl) . '</span>' ?></td></tr>
  <tr><th>LLM_MODEL</th><td><?= $llmModel === '' ? '<span class="muted">(unset → auto-pick)</span>' : '<span class="cmd">' . $h($llmModel) . '</span>' ?></td></tr>
  <tr><th>LLM_KEY</th><td><?= $llmKey === '' ? '<span class="muted">(unset)</span>' : '<span class="badge badge--ok">set</span>' ?></td></tr>
  <tr><th>OCR_VISION_KEY <span class="muted">(Anthropic fallback)</span></th><td><?= $visionKey === '' ? '<span class="muted">(unset)</span>' : '<span class="badge badge--ok">set</span>' ?></td></tr>
</table>

<h2>2. Lokale modellen <span class="muted">(<?= $h($modelsDir) ?>)</span></h2>
<?php if (!$installed): ?>
  <div class="callout callout--warn">
    <strong>Nog geen GGUF-modellen geïnstalleerd.</strong>
    <p class="muted">Een lokale LLM heeft een model nodig om mee te starten. Installeer er één via de modellenbeheer-pagina.</p>
    <div class="cta-row">
      <a class="btn" href="<?= $h($modelsRoute) ?>">Installeer een model</a>
    </div>
  </div>
<?php else: ?>
  <table>
    <tr><th>Bestand</th><th>Grootte</th></tr>
    <?php foreach ($installed as $f): ?>
      <tr>
        <td><span class="cmd"><?= $h(basename($f)) ?></span></td>
        <td><?= $h(number_format(filesize($f) / (1024*1024), 0)) ?> MB</td>
      </tr>
    <?php endforeach; ?>
  </table>
  <div class="cta-row">
    <a class="btn btn--ghost" href="<?= $h($modelsRoute) ?>">+ Meer modellen…</a>
  </div>
<?php endif; ?>

<h2>3. Endpoint-probe</h2>
<?php if ($llmUrl === ''): ?>
  <div class="callout">
    <strong>LLM_URL niet gezet</strong> — recipe-parser valt direct terug op Anthropic
    <?= $visionKey === '' && $llmKey === '' ? '<span class="badge badge--err">geen API-key gevonden</span>' : '<span class="badge badge--ok">API-key beschikbaar</span>' ?>.
  </div>
<?php elseif ($probe['http'] >= 200 && $probe['http'] < 300): ?>
  <div class="callout">
    <strong><?= $h($probe['url']) ?></strong> <span class="badge badge--ok">HTTP <?= $probe['http'] ?></span> <span class="muted"><?= $h($probe['kind']) ?>-shape</span>
    <?php if ($probe['models']): ?>
      <p class="muted" style="margin:0.4rem 0 0;">Geladen modellen volgens de server:</p>
      <ul>
        <?php foreach ($probe['models'] as $m): ?>
          <li><span class="cmd"><?= $h($m) ?></span><?= $llmModel !== '' && stripos($m, $llmModel) !== false ? ' <span class="badge badge--ok">matches LLM_MODEL</span>' : '' ?></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>
<?php else: ?>
  <div class="callout callout--err">
    <strong>Endpoint onbereikbaar</strong> — HTTP <?= (int)$probe['http'] ?>
    <?php if ($probe['err'] !== ''): ?><br><span class="cmd"><?= $h($probe['err']) ?></span><?php endif; ?>
    <p class="muted">Anthropic-vangnet werkt nog wel als <span class="cmd">OCR_VISION_KEY</span> of <span class="cmd">LLM_KEY</span> is gezet.</p>
  </div>
<?php endif; ?>

<h2>4. Recente fouten in <span class="cmd">logs/php_errors.log</span></h2>
<?php if ($recentErrors): ?>
  <div class="log"><?= $h(implode("\n", $recentErrors)) ?></div>
<?php else: ?>
  <p class="muted">Geen recente recipe_parse / [Llm] regels in de laatste 64KB van het logbestand.</p>
<?php endif; ?>

<h2>5. Volgende stap</h2>
<ol>
  <li>Een model installeren? <a href="<?= $h($modelsRoute) ?>">Open LLM-modellen</a>.</li>
  <li>Daarna <span class="cmd">llama-server.exe</span> erop starten en LLM_PROVIDER / LLM_URL / LLM_MODEL in <span class="cmd">.env</span> aanpassen.</li>
  <li><span class="cmd">copy /b web.config +,,</span> — app-pool recyclen zodat de nieuwe env wordt opgepakt.</li>
  <li>Pagina hier vernieuwen — endpoint-probe en model-list moeten kloppen.</li>
</ol>
</main></body></html>
