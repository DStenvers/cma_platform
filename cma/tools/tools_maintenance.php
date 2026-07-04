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

$flagPath = dirname(__DIR__, 2) . '/maintenance.flag';
$notice   = null;

// ---- actions ------------------------------------------------------------
$action = Request::post('action', '');
if ($action === 'on') {
    $message = trim((string) Request::post('message', ''));
    if ($message === '') {
        $notice = ['error', 'Vul een bericht in — een leeg bericht is niet toegestaan.'];
    } else {
        $payload = ['manual' => true, 'since' => time(), 'message' => $message];
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

// Turn ON (with message)
echo '<form method="post" style="margin:1rem 0;max-width:40rem;">';
echo '<input type="hidden" name="action" value="on">';
echo '<label for="maintmsg" style="display:block;font-weight:600;margin-bottom:.35rem;">Bericht voor bezoekers</label>';
echo '<textarea id="maintmsg" name="message" rows="3" required style="width:100%;padding:.6rem;border:1px solid #ccc;border-radius:4px;font:inherit;margin-bottom:.75rem;">'
   . htmlspecialchars($msgValue) . '</textarea>';
echo '<button type="submit" class="btn btn-primary"><span class="lnr lnr-warning"></span> '
   . ($isOn && $isManual ? 'Bericht bijwerken' : 'Onderhoud AANzetten') . '</button>';
echo '</form>';

// Turn OFF
if ($isOn) {
    echo '<form method="post" style="margin:.5rem 0;">';
    echo '<input type="hidden" name="action" value="off">';
    echo '<button type="submit" class="btn"><span class="lnr lnr-checkmark-circle"></span> Onderhoud UITzetten</button>';
    echo '</form>';
}

echo '</div>';
?>
