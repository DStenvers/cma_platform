<?php
/**
 * Deploy setup — developer-only wizard that checks the deploy configuration
 * and fills missing values into the active .env file.
 *
 * Division of responsibility:
 *   - PUBLIC /deploy_status.php?config=1 reports WHAT is missing (read-only,
 *     booleans only, never values, never writes).
 *   - THIS authenticated tool is where the values are actually supplied
 *     (incl. the DEPLOY_SECRET) and written to disk. Developer-only because it
 *     handles the HMAC secret and mutates configuration.
 *
 * Writes to the ACTIVE env file — the first-existing of
 * .env.production / .env.acceptance / .env.test / .env.local / .env, matching
 * the precedence deploy.php and the bootstrap use. If none exists yet, it
 * creates .env. Existing keys are updated in place; missing keys appended.
 */

use App\Library\Error;
use App\Library\Request;
use App\Library\Server;
use Cma\SecurityHelper;
use Cma\ToolbarHelper;

require_once __DIR__ . '/../bootstrap.inc';

if (!SecurityHelper::isDeveloper()) {
    Error::page('Toegang geweigerd', 'Deze functie is alleen beschikbaar voor developers.', false);
    exit;
}

$siteRoot = dirname(__DIR__, 2);

// Resolve the active env file (same .env-first precedence as Bootstrap /
// deploy.php). Writing to the SAME file the app reads is the whole point —
// .env first so the secret never lands in a stale legacy .env.production while
// the app runs off .env.
$activeEnv = null;
foreach (['.env', '.env.production', '.env.acceptance', '.env.test', '.env.local'] as $cand) {
    if (is_file($siteRoot . '/' . $cand)) { $activeEnv = $cand; break; }
}
$targetEnv  = $activeEnv ?? '.env'; // create .env if the site has none yet
$targetPath = $siteRoot . '/' . $targetEnv;

$readEnv = static function (string $path): array {
    $env = [];
    if (is_file($path)) {
        foreach ((array)file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) { continue; }
            [$k, $v] = explode('=', $line, 2);
            $env[trim($k)] = trim($v, " \t\"'");
        }
    }
    return $env;
};
$writeKey = static function (string $path, string $key, string $value): bool {
    $content = is_file($path) ? (string)file_get_contents($path) : '';
    $line    = $key . '=' . $value;
    if (preg_match('/^' . preg_quote($key, '/') . '=.*$/m', $content)) {
        $content = preg_replace('/^' . preg_quote($key, '/') . '=.*$/m', $line, $content);
    } else {
        $content = ($content === '' ? '' : rtrim($content) . "\n") . $line . "\n";
    }
    return file_put_contents($path, $content) !== false;
};

// ---- Handle save -----------------------------------------------------------
$flash = null;
if (Request::post('action', '') === 'save') {
    $writableTarget = is_file($targetPath) ? is_writable($targetPath) : is_writable($siteRoot);
    if (!$writableTarget) {
        $flash = ['type' => 'error', 'msg' => 'Geen schrijfrechten op <code>' . htmlspecialchars($targetPath) . '</code> — geef de IIS-app-pool-identity schrijfrechten en probeer opnieuw.'];
    } else {
        $written = [];
        // DEPLOY_SECRET only written when a non-empty value is supplied (empty
        // = keep the current secret). The other keys: empty clears nothing,
        // it just skips — use a literal "-" to set an explicit empty/disable.
        foreach (['DEPLOY_SECRET', 'DEPLOY_BRANCH', 'DEPLOY_ALERT_EMAIL', 'DEPLOY_RUN_TESTS', 'DEPLOY_MIGRATE'] as $key) {
            $val = trim((string)Request::post($key, ''));
            if ($val === '') { continue; }
            if ($writeKey($targetPath, $key, $val)) { $written[] = $key; }
        }
        clearstatcache();
        if (!empty($written)) {
            $flash = ['type' => 'success', 'msg' => 'Opgeslagen in <code>' . htmlspecialchars($targetEnv) . '</code>: ' . htmlspecialchars(implode(', ', $written)) . '. Zet dezelfde <code>DEPLOY_SECRET</code> in de GitHub-webhook.'];
            $activeEnv = $targetEnv; // it exists now
        } else {
            $flash = ['type' => 'warning', 'msg' => 'Niets gewijzigd (geen waarden ingevuld).'];
        }
    }
}

// ---- Read current state + run checks ---------------------------------------
$env       = $readEnv($targetPath);
$secretSet = !empty($env['DEPLOY_SECRET']);
$branch    = $env['DEPLOY_BRANCH'] ?? '';
$alert     = $env['DEPLOY_ALERT_EMAIL'] ?? '';
$runTests  = $env['DEPLOY_RUN_TESTS'] ?? '';
$migrate   = $env['DEPLOY_MIGRATE'] ?? '';

$logsDir       = $siteRoot . '/.logs/deploy';
$logsWritable  = is_dir($logsDir) ? is_writable($logsDir) : is_writable($siteRoot);
$envWritable   = is_file($targetPath) ? is_writable($targetPath) : is_writable($siteRoot);
$isWin         = stripos(PHP_OS, 'WIN') === 0;
$probe = static function (string $bin) use ($isWin): bool {
    $o = []; $e = 0;
    if ($isWin) { @exec('cmd /c "' . $bin . ' --version" 2>&1', $o, $e); }
    else        { @exec($bin . ' --version 2>&1', $o, $e); }
    return $e === 0;
};
$gitOk      = $probe('git');
$composerOk = $probe('composer') || $probe('"C:\ProgramData\ComposerSetup\bin\composer.bat"')
              || (is_file($siteRoot . '/composer.phar'));

// Generate a fresh secret suggestion only when none is set yet.
$suggestedSecret = $secretSet ? '' : bin2hex(random_bytes(32));
$host = $_SERVER['HTTP_HOST'] ?? Server::getVar('SERVER_NAME', '<host>');

$checkRow = static function (string $label, bool $ok, string $detail): string {
    $lbl = $ok ? '<lib-label type="success">OK</lib-label>' : '<lib-label type="error">ontbreekt</lib-label>';
    return '<tr><td>' . htmlspecialchars($label) . '</td><td>' . $lbl . '</td><td>' . $detail . '</td></tr>';
};

cma_html_header('Deploy setup');
echo '<body class="contentbody tools tool-deploy-setup">';
ToolbarHelper::report('Deploy setup', false, false, false, false, 'Controleer en vul de deploy-configuratie aan');
echo '<div id="c" class="tools">';

if ($flash !== null) {
    echo '<lib-message type="' . $flash['type'] . '" closable>' . $flash['msg'] . '</lib-message>';
}

echo '<p>Actief configuratiebestand: <code>' . htmlspecialchars($activeEnv ?? '(nog geen — er wordt <code>.env</code> aangemaakt)') . '</code>.</p>';

// --- Status table ---
echo '<h3>Configuratie-status</h3>';
echo '<table class="listtable"><thead><tr class="listheader"><th>Item</th><th>Status</th><th>Detail</th></tr></thead><tbody>';
echo $checkRow('Env-bestand', $activeEnv !== null, $activeEnv !== null ? '<code>' . htmlspecialchars($activeEnv) . '</code>' : 'Geen <code>.env*</code> gevonden — wordt aangemaakt bij opslaan.');
echo $checkRow('DEPLOY_SECRET', $secretSet, $secretSet ? 'Ingesteld (waarde wordt nooit getoond).' : 'Verplicht — vul hieronder in of gebruik de gegenereerde suggestie.');
echo $checkRow('Env-bestand schrijfbaar', $envWritable, $envWritable ? 'OK' : 'IIS-user heeft geen schrijfrechten.');
echo $checkRow('logs/ schrijfbaar', $logsWritable, $logsWritable ? '<code>' . htmlspecialchars($logsDir) . '</code>' : 'deploy.log kan niet geschreven worden.');
echo $checkRow('git beschikbaar', $gitOk, $gitOk ? 'Op PATH van de web-user.' : 'git niet gevonden op de PATH van de app-pool-identity.');
echo $checkRow('composer beschikbaar', $composerOk, $composerOk ? 'Gevonden.' : 'composer niet gevonden (PATH / ComposerSetup / composer.phar).');
echo '</tbody></table>';

echo '<p class="cma-tool__hint">Dezelfde read-only check is publiek op te vragen via <code>' . htmlspecialchars('https://' . $host . '/deploy_status.php?config=1') . '</code> (zonder waardes).</p>';

// --- Form ---
echo '<h3>Configuratie aanvullen</h3>';
echo '<form method="post" action="/cma/tools/tools_deploy_setup.php">';
echo '<input type="hidden" name="action" value="save">';
echo '<table class="listtable"><tbody>';

echo '<tr><td><code>DEPLOY_SECRET</code></td><td>';
if ($secretSet) {
    echo '<input type="text" name="DEPLOY_SECRET" value="" placeholder="laat leeg om de huidige te behouden" size="68">';
} else {
    echo '<input type="text" name="DEPLOY_SECRET" value="' . htmlspecialchars($suggestedSecret) . '" size="68">';
}
echo '<div class="cma-tool__hint">HMAC-secret, gedeeld met de GitHub-webhook. ' . ($secretSet ? 'Al ingesteld — alleen invullen om te roteren.' : 'Gegenereerde suggestie hierboven; zet dezelfde waarde in GitHub.') . '</div>';
echo '</td></tr>';

echo '<tr><td><code>DEPLOY_BRANCH</code></td><td><input type="text" name="DEPLOY_BRANCH" value="' . htmlspecialchars($branch) . '" placeholder="main" size="24"><div class="cma-tool__hint">Branch die deze site deployt (default <code>main</code>).</div></td></tr>';

echo '<tr><td><code>DEPLOY_ALERT_EMAIL</code></td><td><input type="text" name="DEPLOY_ALERT_EMAIL" value="' . htmlspecialchars($alert) . '" placeholder="ops@example.com" size="40"><div class="cma-tool__hint">Optioneel — best-effort <code>mail()</code> bij een FAILED deploy.</div></td></tr>';

echo '<tr><td><code>DEPLOY_RUN_TESTS</code></td><td><input type="text" name="DEPLOY_RUN_TESTS" value="' . htmlspecialchars($runTests) . '" placeholder="php cma/tests/TestRunner.php" size="40"><div class="cma-tool__hint">Optioneel — test-gate vóór recycle; non-zero exit blokkeert de deploy.</div></td></tr>';

echo '<tr><td><code>DEPLOY_MIGRATE</code></td><td>' . '<select name="DEPLOY_MIGRATE">' . '<option value="1"' . ($migrate === '0' ? '' : ' selected') . '>Ja — migraties draaien bij elke deploy</option>' . '<option value="0"' . ($migrate === '0' ? ' selected' : '') . '>Nee — zelf toepassen in Beheer &rarr; Migraties</option>' . '</select>' . '<div class="cma-tool__hint">Draait <code>php cma/migrate.php</code> na de test-gate en vóór de recycle. Een mislukte migratie laat de deploy falen, zodat de pool niet omschakelt naar code die een schema verwacht dat er niet is.</div>' . '</td></tr>';

echo '</tbody></table>';
echo '<button type="submit" class="btn btn-primary">Opslaan in ' . htmlspecialchars($targetEnv) . '</button>';
echo '</form>';

echo '<h3>Stap 2 — GitHub-webhook instellen</h3>';

echo '<div class="cma-tool__callout">';
echo '<p>Een <span class="cma-tool__strong">webhook</span> laat GitHub bij elke <code>git push</code> automatisch een seintje naar deze site sturen; <code>/deploy.php</code> rolt vervolgens de nieuwe code uit. Je doet dit één keer per repository.</p>';
echo '</div>';

echo '<p>Ga in je GitHub-repo naar <span class="cma-tool__strong">Settings → Webhooks → Add webhook</span> en vul de velden in:</p>';

echo '<table class="listtable"><thead><tr class="listheader"><th>Veld in GitHub</th><th>Waarde</th><th>Waarom</th></tr></thead><tbody>';
echo '<tr><td>Payload URL</td><td><code>' . htmlspecialchars('https://' . $host . '/deploy.php') . '</code></td>'
   . '<td>Het endpoint dat de deploy uitvoert. Staat bewust in de site-root (niet onder <code>/cma/</code>) zodat het blijft werken als <code>/cma/</code> stuk is.</td></tr>';
echo '<tr><td>Content type</td><td><code>application/json</code></td>'
   . '<td><code>deploy.php</code> leest de push-payload als JSON (branch + commit-sha). De form-variant wordt ook geaccepteerd, maar stuurt het bericht als <code>payload=&lt;urlencoded json&gt;</code> — kies JSON.</td></tr>';
echo '<tr><td>Secret</td><td>dezelfde waarde als <code>DEPLOY_SECRET</code> hierboven</td>'
   . '<td>GitHub ondertekent elk bericht hiermee (HMAC-SHA256). <code>deploy.php</code> weigert alles met een verkeerde of ontbrekende handtekening, zodat niemand anders een deploy kan triggeren.</td></tr>';
echo '<tr><td>SSL verification</td><td><span class="cma-tool__strong">Enable SSL verification</span></td>'
   . '<td>Aan laten — de site draait op HTTPS.</td></tr>';
echo '<tr><td>Which events…</td><td>Just the <code>push</code> event</td>'
   . '<td>Alleen pushes rollen uit; andere events (issues, stars, …) worden genegeerd.</td></tr>';
echo '</tbody></table>';

echo '<p>Klik <span class="cma-tool__strong">Add webhook</span>. GitHub stuurt meteen een test-ping — een groen vinkje onder <span class="cma-tool__strong">Recent Deliveries</span> betekent dat het bericht de site bereikt. Response-codes: <code>202</code> = geaccepteerd (deploy draait async), <code>403</code> = secret-mismatch, <code>503</code> = <code>DEPLOY_SECRET</code> nog niet opgeslagen.</p>';

echo '<p class="cma-tool__hint">Controleer de status read-only via <code>' . htmlspecialchars('https://' . $host . '/deploy_status.php') . '</code> (en de config-check <code>?config=1</code>). Volledige uitleg: <a href="documentation.php?topic=deployment" target="_top">Deployment</a>-documentatie.</p>';

echo '</div></body></html>';
