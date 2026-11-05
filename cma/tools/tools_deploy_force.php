<?php
/**
 * Deployment forceren — start a deploy by hand, without a git push.
 *
 * Sends the same HMAC-signed push payload GitHub sends to this site's own
 * /deploy.php, so every gate and step of the normal deploy (branch check, pull,
 * composer, test gate, migrations, recycle, log) runs unchanged. The secret is
 * read server-side from the active env file and never reaches the browser.
 * deploy.php answers immediately and finishes in the background; progress is
 * read from the public /deploy_status.php.
 */

use App\Library\Request;
use App\Library\Response;
use App\Library\Server;
use Cma\SecurityHelper;
use Cma\ToolbarHelper;

require_once __DIR__ . '/../bootstrap.inc';

if (!SecurityHelper::isDeveloper()) {
    echo '<lib-message type="error">Geen toegang</lib-message>';
    exit;
}

$siteRoot = dirname(__DIR__, 2);

$env = [];
foreach (['.env', '.env.production', '.env.acceptance', '.env.test', '.env.local'] as $cand) {
    if (!is_file($siteRoot . '/' . $cand)) {
        continue;
    }
    foreach ((array) file($siteRoot . '/' . $cand, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
            continue;
        }
        [$k, $v] = explode('=', $line, 2);
        $env[trim($k)] = trim($v, " \t\"'");
    }
    break;
}
$secret = $env['DEPLOY_SECRET'] ?? '';
$branch = ($env['DEPLOY_BRANCH'] ?? '') !== '' ? $env['DEPLOY_BRANCH'] : 'main';
$deployUrl = (Server::getVar('HTTPS', 'off') !== 'off' ? 'https://' : 'http://') . Server::getVar('HTTP_HOST', '') . '/deploy.php';

if (Request::method() === 'POST' && Request::post('action', '') === 'run') {
    Response::noCache();
    if ($secret === '') {
        Response::json(['success' => false, 'error' => 'DEPLOY_SECRET is niet ingesteld (Deploy setup).']);
    }
    $query = [];
    if (Request::post('forcerefresh', '') === 'Y') {
        $query['forcerefresh'] = 'Y';
    }
    if (Request::post('nocomposer', '') === 'Y') {
        $query['nocomposer'] = 'Y';
    }
    $payload = json_encode(['ref' => 'refs/heads/' . $branch, 'forced_by' => SecurityHelper::getCurrentUserId()]);
    $ch = curl_init($deployUrl . ($query ? '?' . http_build_query($query) : ''));
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'X-Hub-Signature-256: sha256=' . hash_hmac('sha256', $payload, $secret),
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
    ]);
    $body = (string) curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    Response::json([
        'success' => $err === '' && $code >= 200 && $code < 300,
        'error'   => $err !== '' ? $err : ($code >= 300 ? 'deploy.php antwoordde HTTP ' . $code . ': ' . trim($body) : ''),
    ]);
}

cma_html_header('Deployment forceren');
echo '<body class="contentbody tools tool-deploy-force">';
ToolbarHelper::report('Deployment forceren', false, false, false, false, 'Start nu een deploy, zonder git push');
echo '<div id="c" class="tools">';

if ($secret === '') {
    echo '<lib-message type="error">DEPLOY_SECRET is niet ingesteld; stel die eerst in via Deploy setup.</lib-message>';
} else {
    echo '<p>Haalt branch <code>' . Server::htmlEncode($branch) . '</code> binnen en doorloopt dezelfde stappen als een deploy na een push '
       . '(pull, composer, tests, migraties, herstart). De site is tijdens de herstart even niet bereikbaar.</p>';
    echo '<p><label><input type="checkbox" id="forcerefresh"> Composer-cache eerst legen (een zojuist gepushte tag wordt anders gemist)</label></p>';
    echo '<p><label><input type="checkbox" id="nocomposer"> Composer overslaan (alleen git pull en herstart)</label></p>';
    echo '<p><button type="button" class="btn btn-primary" id="deployStart"><span class="lnr lnr-rocket"></span> Deployment starten</button></p>';
    echo '<div id="deployStatus"></div>';
}
echo '<p>Het volledige verloop staat in <a href="logreader.php?log=deploy">het deploy-log</a>.</p>';
?>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var knop = document.getElementById('deployStart');
    if (!knop) { return; }
    var uit = document.getElementById('deployStatus');
    function toon(type, tekst) {
        uit.innerHTML = '<lib-message type="' + type + '"></lib-message>';
        uit.firstChild.textContent = tekst;
    }
    function volg() {
        fetch('/deploy_status.php?format=json', { cache: 'no-store' })
            .then(function (r) { return r.json(); })
            .then(function (s) {
                toon(s.status === 'FAILED' ? 'error' : 'info', 'Status: ' + (s.status || 'onbekend') + (s.commit ? ' (' + s.commit + ')' : ''));
                if (s.status === 'RUNNING') { setTimeout(volg, 3000); }
            })
            .catch(function () { setTimeout(volg, 3000); });
    }
    knop.addEventListener('click', async function () {
        var ok = await libConfirm('Nu een deployment starten? De site wordt aan het eind herstart.', {
            title: 'Deployment forceren', confirmText: 'Starten', cancelText: 'Annuleren'
        });
        if (!ok) { return; }
        knop.disabled = true;
        var body = new URLSearchParams({ action: 'run' });
        if (document.getElementById('forcerefresh').checked) { body.set('forcerefresh', 'Y'); }
        if (document.getElementById('nocomposer').checked) { body.set('nocomposer', 'Y'); }
        fetch(location.pathname, { method: 'POST', body: body })
            .then(function (r) { return r.json(); })
            .then(function (a) {
                if (a.success) { toon('info', 'Deployment gestart.'); setTimeout(volg, 3000); }
                else { toon('error', a.error || 'Starten mislukt'); knop.disabled = false; }
            })
            .catch(function () { toon('error', 'Starten mislukt'); knop.disabled = false; });
    });
});
</script>
