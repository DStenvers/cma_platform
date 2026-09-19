<?php
/**
 * Systeeminstellingen — the site-wide settings an administrator changes from
 * the CMA, plus, in the last group, the developer switches that belong to the
 * current user only.
 *
 * Every site-wide control is one entry in the registry (App\Library\Settings,
 * extended by a site through app.php settings_extra): group, label, hint and
 * type come from there, so this page adds nothing per setting. Saving
 * validates first (SystemSettings::normalize) and writes nothing when a value
 * is rejected. The developer switches go through UserPreferences (tblUsers +
 * cookies), the same store preferences.php uses for theme and popup style.
 */

use App\Library\Request;
use App\Library\Response;
use App\Library\Server;
use App\Library\Settings;
use Cma\SecurityHelper;
use Cma\ToolbarHelper;
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
    foreach (array_keys(SystemSettings::definitions()) as $key) {
        if (isset($_POST[$key])) {
            $input[$key] = Request::post($key, '');
        }
    }
    $errors = [];
    $sqlThreshold = Request::postInt('sqlThreshold', -1);
    if (!in_array($sqlThreshold, UserPreferences::SQL_THRESHOLDS, true)) {
        $errors['sqlThreshold'] = 'Ongeldige SQL-drempelwaarde.';
    }
    if ($errors === []) {
        $errors = SystemSettings::save($input);
    }
    if ($errors === []) {
        SystemSettings::afterSave();
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

/**
 * The control for one setting. The id and name equal the setting key: the
 * save script collects by name and marks validation errors by id.
 */
function cma_settings_control(string $key, array $def, $value, bool $isSet, string $placeholder): string
{
    $id = Server::htmlEncode($key);
    $ph = Server::htmlEncode($placeholder);
    $confirm = !empty($def['confirm']) ? ' data-confirm="' . Server::htmlEncode((string) $def['confirm']) . '"' : '';
    switch ($def['type']) {
        case 'bool':
        case 'flag':
            return '<lib-switch name="' . $id . '" id="' . $id . '"' . ($value ? ' checked' : '') . $confirm . '></lib-switch>';
        case 'int':
        case 'float':
            // A stored 0 is a value ("uit"), not an empty box; only an unset
            // optional number renders blank.
            $shown = (!$isSet && !empty($def['optional'])) ? '' : (string) $value;
            $step = $def['type'] === 'float' ? ' step="any"' : '';
            return '<input type="number" name="' . $id . '" id="' . $id . '" class="form-control cma-tool__settings-number"'
                . ' min="' . $def['min'] . '" max="' . $def['max'] . '"' . $step . ' value="' . Server::htmlEncode($shown) . '" placeholder="' . $ph . '"' . $confirm . '>';
        case 'secret':
            $has = (string) $value !== '';
            return '<input type="password" name="' . $id . '" id="' . $id . '" class="form-control cma-tool__settings-text" autocomplete="new-password"'
                . ' value="" placeholder="' . ($has ? '•••••••• (ingesteld)' : 'niet ingesteld') . '"' . $confirm . '>';
        case 'select':
            $html = '<select name="' . $id . '" id="' . $id . '" class="form-control cma-tool__settings-select"' . $confirm . '>';
            if (!empty($def['optional']) || (string) $def['default'] === '') {
                $html .= '<option value=""' . ((string) $value === '' ? ' selected' : '') . '>' . Server::htmlEncode($placeholder !== '' ? $placeholder : 'Automatisch') . '</option>';
            }
            foreach ((array) ($def['options'] ?? []) as $optValue => $optLabel) {
                $html .= '<option value="' . Server::htmlEncode((string) $optValue) . '"' . ((string) $value === (string) $optValue ? ' selected' : '') . '>'
                    . Server::htmlEncode((string) $optLabel) . '</option>';
            }
            return $html . '</select>';
        case 'list':
            $shown = is_array($value) ? implode(', ', $value) : (string) $value;
            return '<input type="text" name="' . $id . '" id="' . $id . '" class="form-control cma-tool__settings-text"'
                . ' value="' . Server::htmlEncode($shown) . '" placeholder="' . ($ph !== '' ? $ph : 'waarde, waarde, …') . '"' . $confirm . '>';
        case 'email':
            return '<input type="text" name="' . $id . '" id="' . $id . '" class="form-control cma-tool__settings-text"'
                . ' value="' . Server::htmlEncode((string) $value) . '" placeholder="' . ($ph !== '' ? $ph : 'naam@voorbeeld.nl') . '"' . $confirm . '>';
        default: // text
            return '<input type="text" name="' . $id . '" id="' . $id . '" class="form-control cma-tool__settings-text"'
                . ' value="' . Server::htmlEncode((string) $value) . '" placeholder="' . $ph . '"' . $confirm . '>';
    }
}

$values    = SystemSettings::getAll();
$groups    = SystemSettings::groupedDefinitions();
$prefs     = UserPreferences::load($userId);
$pageTitle = 'Systeeminstellingen';

cma_html_header($pageTitle);
echo '<body class="contentbody tools tool-settings">';

ToolbarHelper::start();
ToolbarHelper::button('#', 'lnr-save', true, 'Opslaan', 'Instellingen opslaan', 'btnSaveSettings', 'save');
ToolbarHelper::end();
?>
<title><?= Server::htmlEncode($pageTitle) ?></title>

<div id="c" class="tools">
    <div class="cma-tool__settings-filterbar">
        <input type="search" id="settingsFilter" class="form-control cma-tool__settings-filter" placeholder="Zoek instelling…" autocomplete="off">
    </div>
    <form id="settingsForm" autocomplete="off">
        <table class="form-table preferences-table">
<?php $groupNo = 0; foreach ($groups as $slug => $group): $groupNo++; $rowNo = 0; $last = count($group['rows']); ?>
            <tr class="groupbox-row" data-group="<?= $groupNo ?>">
                <td colspan="3">
                    <cma-groupbox group-id="<?= $groupNo ?>" form-id="0" storage-key="cma_settings_<?= Server::htmlEncode($slug) ?>"
                        caption="<?= Server::htmlEncode($group['caption']) ?>" count="<?= $last ?>"<?= $slug === 'notifications' ? '' : ' collapsed' ?>></cma-groupbox>
                </td>
            </tr>
<?php foreach ($group['rows'] as $key => $def):
        $rowNo++;
        $val    = $values[$key];
        $isSet  = Settings::isSet($key);
        $source = Settings::source($key);
        $placeholder = (string) ($def['placeholder'] ?? '');
        if ($source === 'app' && $def['type'] !== 'secret' && !in_array($def['type'], ['bool', 'flag'], true)) {
            // The box shows the variable, which is empty; the value in use comes
            // from app.php and belongs in the placeholder.
            if ($placeholder === '') {
                $placeholder = is_array($val) ? implode(', ', $val) : (string) $val;
            }
            $val = '';
        }
        $search = strtolower($def['label'] . ' ' . $def['hint'] . ' ' . $def['env'] . ' ' . $key);
?>
            <tr id="_g<?= $groupNo ?>_<?= $rowNo ?>" data-group-row="<?= $groupNo ?>" data-search="<?= Server::htmlEncode($search) ?>"<?= $rowNo === $last ? ' class="groupbox_end"' : '' ?>>
                <td class="label-cell"><label for="<?= Server::htmlEncode($key) ?>"><?= Server::htmlEncode($def['label']) ?></label></td>
                <td class="input-cell"><?= cma_settings_control($key, $def, $val, $isSet, $placeholder) ?></td>
                <td class="hint-cell"><?= Server::htmlEncode($def['hint']) ?>
<?php if ($source === 'app'): ?>
                    <lib-label type="information" title="Deze waarde komt uit app.php; een waarde hier gaat voor.">app.php</lib-label>
<?php endif; ?>
                </td>
            </tr>
<?php endforeach; endforeach; $devNo = $groupNo + 1; ?>
            <tr class="groupbox-row" data-group="<?= $devNo ?>">
                <td colspan="3">
                    <cma-groupbox group-id="<?= $devNo ?>" form-id="0" storage-key="cma_settings_developer" caption="Ontwikkelaar (alleen voor jou)" count="3"></cma-groupbox>
                </td>
            </tr>
            <tr id="_g<?= $devNo ?>_1" data-group-row="<?= $devNo ?>" data-search="console logging debugmode">
                <td class="label-cell"><label for="debugMode">Console logging</label></td>
                <td class="input-cell"><lib-switch name="debugMode" id="debugMode" <?= $prefs['prefDebugMode'] ? 'checked' : '' ?>></lib-switch></td>
                <td class="hint-cell">Schakel console.log-output in (uitschakelen voor snelheidstests).</td>
            </tr>
            <tr id="_g<?= $devNo ?>_2" data-group-row="<?= $devNo ?>" data-search="debug overlay tonen showdebugoverlay">
                <td class="label-cell"><label for="showDebugOverlay">Debug overlay tonen</label></td>
                <td class="input-cell"><lib-switch name="showDebugOverlay" id="showDebugOverlay" <?= $prefs['prefDebugOverlay'] ? 'checked' : '' ?>></lib-switch></td>
                <td class="hint-cell">Toont formulierstatus-informatie op alle formulieren.</td>
            </tr>
            <tr id="_g<?= $devNo ?>_3" data-group-row="<?= $devNo ?>" data-search="sql log drempelwaarde sqlthreshold" class="groupbox_end">
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
/* settings-filter:start */
// Filter box: hides rows whose label, hint, variable or key do not contain the
// text, hides groups without a visible row, and opens groups that match so a
// hit inside a collapsed group is not invisible.
function cmaSettingsFilter(root, text) {
    var needle = String(text || '').trim().toLowerCase();
    var groups = {};
    root.querySelectorAll('tr[data-group-row]').forEach(function (row) {
        var hit = needle === '' || (row.getAttribute('data-search') || '').indexOf(needle) !== -1;
        row.classList.toggle('cma-tool__settings-hidden', !hit);
        var g = row.getAttribute('data-group-row');
        groups[g] = (groups[g] || 0) + (hit ? 1 : 0);
    });
    root.querySelectorAll('tr.groupbox-row').forEach(function (head) {
        var g = head.getAttribute('data-group');
        var visible = (groups[g] || 0) > 0;
        head.classList.toggle('cma-tool__settings-hidden', !visible);
        var box = head.querySelector('cma-groupbox');
        if (box && visible && needle !== '' && typeof box.open === 'function') {
            box.open(false);
        }
    });
}
/* settings-filter:end */
(function () {
    var form = document.getElementById('settingsForm');
    var button = document.getElementById('btnSaveSettings');
    var filter = document.getElementById('settingsFilter');
    var initial = {};

    form.querySelectorAll('[data-confirm]').forEach(function (el) {
        initial[el.id] = el.tagName === 'LIB-SWITCH' ? String(!!el.checked) : el.value;
    });

    filter.addEventListener('input', function () { cmaSettingsFilter(form, filter.value); });

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

    function confirmed() {
        var els = form.querySelectorAll('[data-confirm]');
        for (var i = 0; i < els.length; i++) {
            var el = els[i];
            var now = el.tagName === 'LIB-SWITCH' ? String(!!el.checked) : el.value;
            if (now !== initial[el.id] && !window.confirm(el.getAttribute('data-confirm'))) {
                return false;
            }
        }
        return true;
    }

    function save() {
        if (!confirmed()) return;
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
