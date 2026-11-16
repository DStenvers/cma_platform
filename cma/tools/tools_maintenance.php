<?php
/**
 * Maintenance mode — manual toggle from the CMA.
 *
 * Writes/removes the site-root `maintenance.flag` that `_bootstrap.php` checks
 * before the Composer autoloader. A MANUAL flag carries `"manual": true` so it
 * (a) does NOT auto-lift after 20 min (unlike a deploy flag) and (b) can carry
 * a one-off custom message shown on the maintenance page.
 *
 * The maintenance gate exempts `/cma/`, so turning it on here never locks the
 * admin out of the CMA — public (webshop) visitors get the maintenance page,
 * admins keep working.
 */

use App\Library\Application;
use App\Library\Request;
use Cma\ToolbarHelper;
use Cma\SecurityHelper;

require_once __DIR__ . '/../bootstrap.inc';

if (!SecurityHelper::isAdmin()) {
    http_response_code(403);
    echo 'Toegang geweigerd — alleen beheerders.';
    exit;
}

// Voorbeeld (iframe onder het formulier): dezelfde pagina als bezoekers zien, met het bericht dat nu in het formulier staat; er wordt niets opgeslagen of aangezet.
if (Request::query('preview', '') === '1') {
    $message = trim((string) Request::query('message', ''));
    $cmaMaintPreview = true;
    $cmaMaintPreviewRetry = Request::query('retry', '') === '1';
    header('Content-Type: text/html; charset=utf-8');
    include dirname(__DIR__) . '/maintenance_page.inc';
    exit;
}

$flagPath = dirname(__DIR__, 2) . '/maintenance.flag';
$notice   = null;

// ---- actions ------------------------------------------------------------
$action = Request::post('action', '');
if ($action === 'on') {
    $message = trim((string) Request::post('message', ''));
    if ($message === '') {
        $notice = ['error', 'Vul een bericht in — een leeg bericht is niet toegestaan.'];
    } else {
        $payload = ['manual' => true, 'since' => time(), 'message' => $message,
                    'retry' => Request::post('retry', '') === '1'];
        $ok = @file_put_contents(
            $flagPath,
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
        $notice = $ok !== false
            ? ['success', 'Onderhoudsscherm staat AAN voor bezoekers.']
            : ['error', 'Kon het vlagbestand niet schrijven: ' . htmlspecialchars($flagPath) . ' (schrijfrechten?).'];
    }
} elseif ($action === 'off') {
    if (!is_file($flagPath) || @unlink($flagPath)) {
        $notice = ['success', 'Onderhoudsscherm staat UIT. De site is weer normaal bereikbaar.'];
    } else {
        $notice = ['error', 'Kon het vlagbestand niet verwijderen: ' . htmlspecialchars($flagPath) . '.'];
    }
}

// ---- current state ------------------------------------------------------
$isOn     = is_file($flagPath);
$flagData = [];
$isManual = false;
$since    = null;
$curMsg   = '';
if ($isOn) {
    $raw      = trim((string) @file_get_contents($flagPath));
    $flagData = $raw !== '' ? (json_decode($raw, true) ?: []) : [];
    $isManual = !empty($flagData['manual']);
    $since    = isset($flagData['since']) ? (int) $flagData['since'] : (int) @filemtime($flagPath);
    $curMsg   = is_string($flagData['message'] ?? null) ? $flagData['message'] : '';
}
$retryChecked = ($flagData['retry'] ?? true) !== false;

// The effective default message the maintenance page shows from config, in the
// same source order as maintenance.php: data/maintenance.json -> data/app.json
// "maintenance" -> built-in default. Used to prefill the textarea so it always
// holds the real text (never empty).
$siteRoot = dirname(__DIR__, 2);
$readCfgMsg = static function (string $path, bool $nested): string {
    if (is_file($path)) {
        $d = json_decode((string) @file_get_contents($path), true);
        if (is_array($d)) {
            $v = $nested ? ($d['maintenance']['message'] ?? null) : ($d['message'] ?? null);
            if (is_string($v) && trim($v) !== '') {
                return trim($v);
            }
        }
    }
    return '';
};
$defaultMsg = $readCfgMsg($siteRoot . '/data/maintenance.json', false);
if ($defaultMsg === '') {
    // data/cma_branding.json (renamed from app.json) "maintenance" block; legacy fallback.
    $defaultMsg = $readCfgMsg($siteRoot . '/data/cma_branding.json', true);
}
if ($defaultMsg === '') {
    $defaultMsg = $readCfgMsg($siteRoot . '/data/app.json', true);
}
if ($defaultMsg === '') {
    $defaultMsg = 'We zijn even bezig met de website, duurt niet lang.';
}
$msgValue = $curMsg !== '' ? $curMsg : $defaultMsg;

// ---- render -------------------------------------------------------------
cma_html_header('Onderhoudsscherm');
echo '<body class="contentbody tools tool-maintenance">';
ToolbarHelper::report('Onderhoudsscherm', false, false, false, false, 'Zet de site handmatig in onderhoud met een eigen bericht');
echo '<div id="c" class="tools">';

if ($notice !== null) {
    echo '<lib-message type="' . $notice[0] . '" closable>' . $notice[1] . '</lib-message>';
}

// Status
$statusLabel = $isOn ? ($isManual ? 'AAN (handmatig)' : 'AAN (deploy)') : 'UIT';
$statusType  = $isOn ? 'warning' : 'success';
echo '<lib-message type="' . $statusType . '" compact>';
echo '<span class="cma-tool__strong">Status: ' . $statusLabel . '</span>';
if ($isOn && $since) {
    echo '<br>Sinds ' . htmlspecialchars(date('d-m-Y H:i', $since));
    if (!$isManual) {
        echo ' — een deploy-vlag; deze verdwijnt automatisch na afloop (of na 20 min).';
    }
}
echo '</lib-message>';

echo '<p>Bezoekers van de <span class="cma-tool__strong">publieke site</span> krijgen dan de onderhoudspagina. '
   . 'De CMA (<code>/cma/</code>) blijft voor jou bereikbaar, dus je kunt het hier altijd weer uitzetten.</p>';

// Turn ON (with message): het formulier links, het voorbeeld rechts ernaast (onder elkaar op een smal scherm).
echo '<div style="display:flex;flex-wrap:wrap;gap:1.5rem;align-items:flex-start;">';
echo '<form method="post" style="margin:1rem 0;flex:1 1 24rem;max-width:40rem;">';
echo '<input type="hidden" name="action" value="on">';
echo '<label for="maintmsg" style="display:block;font-weight:600;margin-bottom:.35rem;">Bericht voor bezoekers</label>';
echo '<textarea id="maintmsg" name="message" rows="3" required style="width:100%;padding:.6rem;border:1px solid #ccc;border-radius:4px;font:inherit;margin-bottom:.75rem;">'
   . htmlspecialchars($msgValue) . '</textarea>';
echo '<label style="display:block;margin-bottom:.75rem;"><input type="checkbox" name="retry" value="1"'
   . ($retryChecked ? ' checked' : '') . '> Toon &lsquo;Probeer het over een paar minuten opnieuw&rsquo; '
   . 'en &lsquo;Blijft u deze melding langer dan 10 minuten zien?&rsquo;</label>';
echo '<button type="submit" class="btn btn-primary"><span class="lnr lnr-warning"></span> '
   . ($isOn && $isManual ? 'Bericht bijwerken' : 'Onderhoud AANzetten') . '</button>';
echo '</form>';

// Voorbeeld: zo ziet een bezoeker het scherm. Het ververst zich terwijl je typt of het vinkje wijzigt; er wordt niets aangezet.
echo '<div id="maint-preview" style="margin:1rem 0;flex:1 1 24rem;max-width:40rem;">';
echo '<div style="font-weight:600;margin-bottom:.35rem;">Voorbeeld van het onderhoudsscherm</div>';
echo '<iframe id="maint-preview-frame" title="Voorbeeld van het onderhoudsscherm" style="width:100%;height:420px;border:1px solid #ccc;border-radius:4px;background:#fff;" '
   . 'src="?preview=1&amp;message=' . rawurlencode($msgValue) . '&amp;retry=' . ($retryChecked ? '1' : '0') . '"></iframe>';
echo '</div>';
echo '</div>';   // einde van de rij formulier + voorbeeld
echo '<script>(function(){var t=document.getElementById("maintmsg"),r=document.querySelector("input[name=retry]"),f=document.getElementById("maint-preview-frame"),w;'
   . 'function u(){f.src="?preview=1&message="+encodeURIComponent(t.value)+"&retry="+(r&&r.checked?"1":"0");}'
   . 'function d(){clearTimeout(w);w=setTimeout(u,350);}'
   . 't.addEventListener("input",d);if(r)r.addEventListener("change",u);})();</script>';

// Turn OFF
if ($isOn) {
    echo '<form method="post" style="margin:.5rem 0;">';
    echo '<input type="hidden" name="action" value="off">';
    echo '<button type="submit" class="btn"><span class="lnr lnr-checkmark-circle"></span> Onderhoud UITzetten</button>';
    echo '</form>';
}

echo '</div>';
?>
