<?php
/**
 * Systeeminstellingen — the site-wide settings an administrator changes from
 * the CMA: mail notifications, logging, error display — plus, in the last
 * group, the developer switches that belong to the current user only.
 *
 * Every site-wide control maps to one .env variable through the registry in
 * Cma\Services\SystemSettings::DEFINITIONS; this page only groups and labels
 * them. Saving validates first and writes nothing when a value is rejected.
 * The developer switches go through Cma\Services\UserPreferences (tblUsers +
 * cookies), the same store preferences.php uses for theme and popup style.
 */

use App\Library\Request;
use App\Library\Response;
use App\Library\Server;
use Cma\SecurityHelper;
use Cma\ToolbarHelper;
use Cma\Services\PerformanceLogger;
use Cma\Services\SystemSettings;
use Cma\Services\UserPreferences;

require_once __DIR__ . '/../bootstrap.inc';
require_once __DIR__ . '/../classes/Services/UserPreferences.php';

if (!SecurityHelper::isAdmin()) {
    echo '<lib-message type="error">Geen toegang</lib-message>';
    exit;
}

Response::noCache();

$userId = (int) SecurityHelper::getCurrentUserId();

// ---- Save ----------------------------------------------------------------------
if (Request::method() === 'POST' && Request::post('action', '') === 'save') {
    $input = [];
    foreach (array_keys(SystemSettings::DEFINITIONS) as $key) {
        if (isset($_POST[$key])) {
            $input[$key] = Request::post($key, '');
        }
    }

    // A notification that is switched on needs somewhere to go.
    $errors = [];
    foreach (['error_mail' => 'Fouten mailen', 'notfound_mail' => '404-overzicht mailen'] as $prefix => $label) {
        $on = strtoupper((string) ($input[$prefix . '_enabled'] ?? 'N')) === 'J';
        if ($on && trim((string) ($input[$prefix . '_to'] ?? '')) === '') {
            $errors[$prefix . '_to'] = $label . ' staat aan, maar er is geen ontvanger ingevuld.';
        }
    }
    $sqlThreshold = Request::postInt('sqlThreshold', -1);
    if (!in_array($sqlThreshold, UserPreferences::SQL_THRESHOLDS, true)) {
        $errors['sqlThreshold'] = 'Ongeldige SQL-drempelwaarde.';
    }
    if ($errors === []) {
        $errors = SystemSettings::save($input);
    }
    if ($errors === []) {
        // Loggers memoise their switch per request; the save must be visible now.
        PerformanceLogger::clearEnabledCache();
        UserPreferences::save($userId, array_merge(UserPreferences::load($userId), [
            'prefDebugMode'    => Request::post('debugMode', '') === 'J',
            'prefDebugOverlay' => Request::post('showDebugOverlay', '') === 'J',
            'prefSqlThreshold' => $sqlThreshold,
        ]));
    }
    Response::json(['success' => $errors === [], 'errors' => $errors]);
    exit;
}

// ---- Render --------------------------------------------------------------------
$values      = SystemSettings::getAll();
$prefs       = UserPreferences::load($userId);
$pageTitle   = 'Systeeminstellingen';

$groups = [
    ['id' => 1, 'caption' => 'Meldingen per e-mail', 'rows' => [
        ['key' => 'error_mail_enabled',    'label' => 'Fouten mailen',
         'hint' => 'Elke niet-afgevangen fout (foutpagina 500) wordt gemaild. Dezelfde fout gaat hoogstens één keer per uur de deur uit.'],
        ['key' => 'error_mail_to',         'label' => 'Ontvanger foutmeldingen',
         'hint' => 'Eén of meer adressen, gescheiden door komma\'s.'],
        ['key' => 'notfound_mail_enabled', 'label' => '404-overzicht mailen',
         'hint' => 'Elke dag één mail met de niet-gevonden pagina\'s van gisteren. Zoekmachines en scanners worden apart geteld en niet uitgesplitst.'],
        ['key' => 'notfound_mail_to',      'label' => 'Ontvanger 404-overzicht',
         'hint' => 'Eén of meer adressen, gescheiden door komma\'s.'],
        ['key' => 'deploy_alert_email',    'label' => 'Ontvanger deploy-alarm',
         'hint' => 'Krijgt een mail als een automatische deploy mislukt. Leeg = geen mail.'],
    ]],
    ['id' => 2, 'caption' => 'Logging', 'rows' => [
        ['key' => 'perf_log_enabled',   'label' => 'Performance logging', 'hint' => 'Log API-aanroepen, queries en laadtijden.'],
        ['key' => 'cache_log_enabled',  'label' => 'Cache logging',       'hint' => 'Log cache hits en misses.'],
        ['key' => 'debug_log_enabled',  'label' => 'Debug logging',       'hint' => 'Log debug-informatie vanuit de browser (libLog).'],
        ['key' => 'email_log_enabled',  'label' => 'E-mail log',          'hint' => 'Bewaar elke verzonden e-mail in de e-mail log (Site gezondheid → E-mail log).'],
        ['key' => 'sql_log_enabled',    'label' => 'SQL logging',         'hint' => 'Log elke query met duur. Niet standaard aan: het is één schrijfactie per query.'],
        ['key' => 'error_log_retention_days', 'label' => 'Bewaartermijn foutlog',
         'hint' => 'Dagen dat de dagelijkse PHP-foutlogs bewaard blijven (1 t/m 365).'],
    ]],
    ['id' => 3, 'caption' => 'Foutweergave', 'rows' => [
        ['key' => 'force_debug', 'label' => 'Fouten tonen op productie',
         'hint' => 'Toont foutdetails aan iedere bezoeker, ook op productie. Alleen tijdelijk aanzetten; beheerders zien de details altijd al.'],
        ['key' => 'cma_debug',   'label' => 'CMA-debugmodus voor iedereen',
         'hint' => 'Zet de debugmodus van het CMA voor alle gebruikers aan (console-logging, debug-overlay). Alleen voor jezelf: groep Ontwikkelaar hieronder.'],
    ]],
];

cma_html_header($pageTitle);
echo '<body class="contentbody tools tool-settings">';

ToolbarHelper::start();
ToolbarHelper::button('#', 'lnr-checkmark', true, 'Opslaan', 'Instellingen opslaan', 'btnSaveSettings', 'save');
ToolbarHelper::end();
?>
<title><?= Server::htmlEncode($pageTitle) ?></title>

<div id="c">
    <form id="settingsForm" autocomplete="off">
        <table class="form-table preferences-table">
<?php foreach ($groups as $group): ?>
            <tr class="groupbox-row">
                <td colspan="3">
                    <cma-groupbox group-id="<?= $group['id'] ?>" form-id="0" caption="<?= Server::htmlEncode($group['caption']) ?>"></cma-groupbox>
                </td>
            </tr>
<?php $last = count($group['rows']); foreach ($group['rows'] as $i => $row):
        $key  = $row['key'];
        $def  = SystemSettings::DEFINITIONS[$key];
        $val  = $values[$key];
        $rowClass = ($i + 1 === $last) ? ' class="groupbox_end"' : '';
?>
            <tr id="_g<?= $group['id'] ?>_<?= $i + 1 ?>"<?= $rowClass ?>>
                <td class="label-cell"><label for="<?= $key ?>"><?= Server::htmlEncode($row['label']) ?></label></td>
                <td class="input-cell">
<?php if ($def['type'] === 'bool' || $def['type'] === 'flag'): ?>
                    <lib-switch name="<?= $key ?>" id="<?= $key ?>" <?= $val ? 'checked' : '' ?>></lib-switch>
<?php elseif ($def['type'] === 'int'): ?>
                    <input type="number" name="<?= $key ?>" id="<?= $key ?>" class="form-control cma-tool__settings-number"
                           min="<?= $def['min'] ?>" max="<?= $def['max'] ?>" value="<?= (int) $val ?>">
<?php else: ?>
                    <input type="text" name="<?= $key ?>" id="<?= $key ?>" class="form-control cma-tool__settings-text"
                           value="<?= Server::htmlEncode((string) $val) ?>" placeholder="naam@voorbeeld.nl">
<?php endif; ?>
                </td>
                <td class="hint-cell"><?= Server::htmlEncode($row['hint']) ?></td>
            </tr>
<?php endforeach; endforeach; ?>
            <tr class="groupbox-row">
                <td colspan="3">
                    <cma-groupbox group-id="4" form-id="0" caption="Ontwikkelaar (alleen voor jou)"></cma-groupbox>
                </td>
            </tr>
            <tr id="_g4_1">
                <td class="label-cell"><label for="debugMode">Console logging</label></td>
                <td class="input-cell"><lib-switch name="debugMode" id="debugMode" <?= $prefs['prefDebugMode'] ? 'checked' : '' ?>></lib-switch></td>
                <td class="hint-cell">Schakel console.log-output in (uitschakelen voor snelheidstests).</td>
            </tr>
            <tr id="_g4_2">
                <td class="label-cell"><label for="showDebugOverlay">Debug overlay tonen</label></td>
                <td class="input-cell"><lib-switch name="showDebugOverlay" id="showDebugOverlay" <?= $prefs['prefDebugOverlay'] ? 'checked' : '' ?>></lib-switch></td>
                <td class="hint-cell">Toont formulierstatus-informatie op alle formulieren.</td>
            </tr>
            <tr id="_g4_3" class="groupbox_end">
                <td class="label-cell"><label for="sqlThreshold">SQL log drempelwaarde</label></td>
                <td class="input-cell">
                    <select name="sqlThreshold" id="sqlThreshold" class="form-control cma-tool__settings-select">
<?php foreach ([-1 => 'Uit', 0 => 'Alle queries', 50 => 'Langer dan 50ms', 100 => 'Langer dan 100ms', 250 => 'Langer dan 250ms'] as $ms => $label): ?>
                        <option value="<?= $ms ?>" <?= (int) $prefs['prefSqlThreshold'] === $ms ? 'selected' : '' ?>><?= $label ?></option>
<?php endforeach; ?>
                    </select>
                </td>
                <td class="hint-cell">Filtert SQL-queries in de logreader.</td>
            </tr>
        </table>
    </form>
</div>

<script>
(function () {
    var form = document.getElementById('settingsForm');
    var button = document.getElementById('btnSaveSettings');

    function collect() {
        var data = new FormData();
        data.append('action', 'save');
        form.querySelectorAll('lib-switch').forEach(function (sw) {
            data.append(sw.getAttribute('name'), sw.checked ? 'J' : 'N');
        });
        form.querySelectorAll('input, select').forEach(function (field) {
            data.append(field.name, field.value);
        });
        return data;
    }

    function markErrors(errors) {
        form.querySelectorAll('.cma-tool__settings-invalid').forEach(function (el) {
            el.classList.remove('cma-tool__settings-invalid');
        });
        Object.keys(errors).forEach(function (key) {
            var el = document.getElementById(key);
            if (el) el.classList.add('cma-tool__settings-invalid');
        });
    }

    function save() {
        button.classList.add('disabled');
        // Absolute: inside the shell the document address is /cma/tools?tool=settings,
        // so a relative URL would resolve to /cma/tools_settings.php and 404.
        fetch('/cma/tools/tools_settings.php', { method: 'POST', body: collect() })
            .then(function (r) { return r.json(); })
            .then(function (result) {
                var errors = result.errors || {};
                markErrors(errors);
                if (result.success) {
                    libToast.success('Instellingen opgeslagen');
                    // The console-logging switch is read from its cookie by libLog
                    if (window.libLog && typeof window.libLog.refreshFromCookie === 'function') {
                        window.libLog.refreshFromCookie();
                    }
                } else {
                    var messages = Object.keys(errors).map(function (k) { return errors[k]; });
                    libToast.error(messages.join(' ') || 'Opslaan mislukt');
                    var first = document.getElementById(Object.keys(errors)[0]);
                    if (first && typeof first.focus === 'function') first.focus();
                }
            })
            .catch(function (e) {
                libToast.error('Opslaan mislukt: ' + e.message);
            })
            .finally(function () {
                button.classList.remove('disabled');
            });
    }

    button.addEventListener('click', function (e) {
        e.preventDefault();
        save();
    });
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        save();
    });
})();
</script>
