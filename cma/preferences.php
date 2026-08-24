<?php
/**
 * User Preferences Page
 * Allows users to customize their CMA experience
 * Uses standard CMA controls and styling
 *
 * Preferences are stored in the database (tblUsers) and synced to cookies for JavaScript access.
 * UI state (tree expand, table columns, menu collapse) stays in localStorage per browser.
 */
use App\Library\Application;
use App\Library\Database;
use App\Library\Request;
use App\Library\Response;
use App\Library\Server;
use App\Library\Cookie;
use Cma\SecurityHelper;
use Cma\ToolbarHelper;
use Cma\Services\SystemSettings;
use Cma\Services\PerformanceLogger;

require_once __DIR__ . '/bootstrap.inc';

// Prevent caching - this page shows real-time settings
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Check user is logged in
if (!SecurityHelper::isLoggedIn()) {
    if (defined('CMA_NOMENU_MODE') && CMA_NOMENU_MODE) {
        echo '<lib-message type="error">Sessie verlopen. Ververs de pagina.</lib-message>';
        exit;
    }
    Response::redirect('login.php');
    exit;
}

$language = Application::get('cma_language', 'NL');
$userName = SecurityHelper::getCurrentUserName();
$userId = (int)SecurityHelper::getCurrentUserId();
$message = '';
$messageType = '';

/**
 * Get user preferences from database
 * Falls back to cookie values for migration compatibility
 */
function getUserPreferences(int $userId): array {
    $defaults = [
        'prefTheme' => 'light',
        'prefMenuStyle' => 'sidebar',
        'prefPopupStyle' => 'sidepanel',
        'prefDebugMode' => false,
        'prefDebugOverlay' => false,
        'prefSqlThreshold' => -1,
    ];

    if ($userId <= 0) {
        return $defaults;
    }

    $usersConn = Database::getConnection('users');
    if (!$usersConn) {
        // No database connection, fall back to cookies
        return [
            'prefTheme' => Cookie::get('cma_theme', $defaults['prefTheme']),
            'prefMenuStyle' => Cookie::get('cma_menu_style', $defaults['prefMenuStyle']),
            'prefPopupStyle' => Cookie::get('cma_popup_style', $defaults['prefPopupStyle']),
            'prefDebugMode' => Cookie::get('cma_debug_mode', 'N') === 'J',
            'prefDebugOverlay' => Cookie::get('cma_debug_overlay', 'N') === 'J',
            'prefSqlThreshold' => (int)Cookie::get('cma_sql_threshold', '-1'),
        ];
    }

    // Try to load from database
    $sql = "SELECT prefTheme, prefMenuStyle, prefPopupStyle, prefDebugMode, prefDebugOverlay, prefSqlThreshold FROM tblUsers WHERE ID = $userId";
    try {
        $rs = Database::openRS($sql, $usersConn);
        if ($rs && !$rs->EOF) {
            $row = $rs->fields;
            // Merge database values with defaults (handle NULL values)
            return [
                'prefTheme' => $row['prefTheme'] ?? Cookie::get('cma_theme', $defaults['prefTheme']),
                'prefMenuStyle' => $row['prefMenuStyle'] ?? Cookie::get('cma_menu_style', $defaults['prefMenuStyle']),
                'prefPopupStyle' => $row['prefPopupStyle'] ?? Cookie::get('cma_popup_style', $defaults['prefPopupStyle']),
                'prefDebugMode' => ($row['prefDebugMode'] ?? false) || Cookie::get('cma_debug_mode', 'N') === 'J',
                'prefDebugOverlay' => ($row['prefDebugOverlay'] ?? false) || Cookie::get('cma_debug_overlay', 'N') === 'J',
                'prefSqlThreshold' => (int)($row['prefSqlThreshold'] ?? Cookie::get('cma_sql_threshold', $defaults['prefSqlThreshold'])),
            ];
        }
    } catch (\Exception $e) {
        // Column doesn't exist yet, fall back to cookies
    }

    // Migration: read from cookies if database columns don't exist yet
    return [
        'prefTheme' => Cookie::get('cma_theme', $defaults['prefTheme']),
        'prefMenuStyle' => Cookie::get('cma_menu_style', $defaults['prefMenuStyle']),
        'prefPopupStyle' => Cookie::get('cma_popup_style', $defaults['prefPopupStyle']),
        'prefDebugMode' => Cookie::get('cma_debug_mode', 'N') === 'J',
        'prefDebugOverlay' => Cookie::get('cma_debug_overlay', 'N') === 'J',
        'prefSqlThreshold' => (int)Cookie::get('cma_sql_threshold', '-1'),
    ];
}

/**
 * Save user preferences to database and sync to cookies
 */
function saveUserPreferences(int $userId, array $prefs): bool {
    if ($userId <= 0) {
        return false;
    }

    $usersConn = Database::getConnection('users');
    if (!$usersConn) {
        return false;
    }

    // Build update SQL
    $theme = Database::escape($prefs['prefTheme'] ?? 'light');
    $menuStyle = Database::escape($prefs['prefMenuStyle'] ?? 'sidebar');
    $popupStyle = Database::escape($prefs['prefPopupStyle'] ?? 'sidepanel');
    $debugMode = ($prefs['prefDebugMode'] ?? false) ? 1 : 0;
    $debugOverlay = ($prefs['prefDebugOverlay'] ?? false) ? 1 : 0;
    $sqlThreshold = (int)($prefs['prefSqlThreshold'] ?? 0);

    $sql = "UPDATE tblUsers SET
        prefTheme = '$theme',
        prefMenuStyle = '$menuStyle',
        prefPopupStyle = '$popupStyle',
        prefDebugMode = $debugMode,
        prefDebugOverlay = $debugOverlay,
        prefSqlThreshold = $sqlThreshold
        WHERE ID = $userId";

    try {
        $usersConn->exec($sql);

        // Sync to cookies for JavaScript access and initial page load
        $expires = time() + (365 * 24 * 60 * 60);
        Cookie::set('cma_theme', $prefs['prefTheme'] ?? 'light', $expires);
        Cookie::set('cma_menu_style', $prefs['prefMenuStyle'] ?? 'sidebar', $expires);
        Cookie::set('cma_popup_style', $prefs['prefPopupStyle'] ?? 'sidepanel', $expires);
        // Delete old debug cookie first (may have been httponly), then set new one as non-httponly
        Cookie::delete('cma_debug_mode');
        Cookie::set('cma_debug_mode', ($prefs['prefDebugMode'] ?? false) ? 'J' : 'N', $expires, '/', '', false, false);
        Cookie::set('cma_debug_overlay', ($prefs['prefDebugOverlay'] ?? false) ? 'J' : 'N', $expires);
        Cookie::set('cma_sql_threshold', (string)($prefs['prefSqlThreshold'] ?? 0), $expires);

        return true;
    } catch (\Exception $e) {
        // Column doesn't exist yet - save to cookies only
        $expires = time() + (365 * 24 * 60 * 60);
        Cookie::set('cma_theme', $prefs['prefTheme'] ?? 'light', $expires);
        Cookie::set('cma_menu_style', $prefs['prefMenuStyle'] ?? 'sidebar', $expires);
        Cookie::set('cma_popup_style', $prefs['prefPopupStyle'] ?? 'sidepanel', $expires);
        // Delete old debug cookie first (may have been httponly), then set new one as non-httponly
        Cookie::delete('cma_debug_mode');
        Cookie::set('cma_debug_mode', ($prefs['prefDebugMode'] ?? false) ? 'J' : 'N', $expires, '/', '', false, false);
        Cookie::set('cma_debug_overlay', ($prefs['prefDebugOverlay'] ?? false) ? 'J' : 'N', $expires);
        Cookie::set('cma_sql_threshold', (string)($prefs['prefSqlThreshold'] ?? 0), $expires);
        return true;
    }
}

// Handle GET actions (view/clear log)
$getAction = Request::query('action', '');
if ($getAction !== '' && SecurityHelper::isAdmin()) {
    $logDir = dirname(__DIR__) . '/.logs/perf';
    $todayLog = $logDir . '/perf_' . date('Y-m-d') . '.log';

    if ($getAction === 'viewLog') {
        header('Content-Type: application/json');
        if (file_exists($todayLog)) {
            echo json_encode([
                'success' => true,
                'content' => file_get_contents($todayLog)
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => $language === 'UK' ? 'Log file not found' : 'Logbestand niet gevonden'
            ]);
        }
        exit;
    }

    if ($getAction === 'clearLog') {
        header('Content-Type: application/json');
        // Delete all performance log files
        $deleted = 0;
        $files = glob($logDir . '/perf_*.log');
        foreach ($files as $file) {
            if (unlink($file)) {
                $deleted++;
            }
        }
        echo json_encode([
            'success' => true,
            'message' => $language === 'UK'
                ? "Cleared $deleted log file(s)"
                : "$deleted logbestand(en) gewist"
        ]);
        exit;
    }
}

// Handle form submission
if (Request::method() === 'POST') {
    $action = Request::post('action', '');

    // Handle system settings save (admin only)
    if ($action === 'saveSystemSettings' && SecurityHelper::isAdmin()) {
        $settings = [
            'perf_log_enabled' => Request::post('perfLogEnabled', '') === 'J',
            'cache_log_enabled' => Request::post('cacheLogEnabled', '') === 'J',
            'debug_log_enabled' => Request::post('debugLogEnabled', '') === 'J',
        ];

        $success = SystemSettings::saveSettings($settings);

        // Clear the PerformanceLogger's cached enabled state
        PerformanceLogger::clearEnabledCache();

        if (Request::post('ajax', '') === '1') {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => $success,
                'message' => $success
                    ? ($language === 'UK' ? 'System settings saved.' : 'Systeeminstellingen opgeslagen.')
                    : ($language === 'UK' ? 'Error saving system settings.' : 'Fout bij opslaan systeeminstellingen.')
            ]);
            exit;
        }
    }

    if ($action === 'savePreferences') {
        // Get old values to detect changes
        $oldPrefs = getUserPreferences($userId);
        $oldTheme = $oldPrefs['prefTheme'];
        $oldMenuStyle = $oldPrefs['prefMenuStyle'];

        // Collect new preferences
        $theme = Request::post('theme', 'light');
        // Menu style is no longer user-configurable — the sidebar shell is the
        // only layout. Always persist 'sidebar' (normalising any legacy 'classic'
        // value on the next save).
        $menuStyle = 'sidebar';
        $popupStyle = Request::post('popupStyle', 'sidepanel');
        $showDebugOverlay = Request::post('showDebugOverlay', '') === 'J';
        $debugMode = Request::post('debugMode', '') === 'J';
        $sqlThreshold = Request::postInt('sqlThreshold');

        // Validate sqlThreshold (-1 = off, 0 = all, or one of the allowed values)
        if (!in_array($sqlThreshold, [-1, 0, 50, 100, 250])) {
            $sqlThreshold = -1;
        }

        // Save to database and cookies
        $newPrefs = [
            'prefTheme' => $theme,
            'prefMenuStyle' => $menuStyle,
            'prefPopupStyle' => $popupStyle,
            'prefDebugMode' => $debugMode,
            'prefDebugOverlay' => $showDebugOverlay,
            'prefSqlThreshold' => $sqlThreshold,
        ];
        saveUserPreferences($userId, $newPrefs);

        // Check if theme or menu style changed (requires refresh)
        $themeChanged = ($oldTheme !== $theme);
        $menuStyleChanged = ($oldMenuStyle !== $menuStyle);
        $needsRefresh = $themeChanged || $menuStyleChanged;

        $message = $language === 'UK' ? 'Preferences saved successfully.' : 'Voorkeuren succesvol opgeslagen.';
        $messageType = 'success';

        // Handle AJAX POST - return JSON and exit before any HTML output
        if (Request::post('ajax', '') === '1') {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => $message,
                'themeChanged' => $themeChanged,
                'menuStyleChanged' => $menuStyleChanged,
                'needsRefresh' => $needsRefresh
            ]);
            exit;
        }
    }
}

// Get current preferences from database (with cookie fallback)
$prefs = getUserPreferences($userId);
$currentTheme = $prefs['prefTheme'];
$popupStyle = $prefs['prefPopupStyle'];
$showDebugOverlay = $prefs['prefDebugOverlay'] ? 'J' : 'N';
$debugMode = $prefs['prefDebugMode'] ? 'J' : 'N';
$sqlThreshold = $prefs['prefSqlThreshold'];

// Check if user is admin or developer (for showing debug options)
$isDevOrAdmin = SecurityHelper::isAdmin() || SecurityHelper::isDeveloper();
$isAdmin = SecurityHelper::isAdmin();

// Get current system settings (admin only)
$sysSettings = $isAdmin ? SystemSettings::getAll() : [];
$perfLogEnabled = $sysSettings['perf_log_enabled'] ?? true;
$cacheLogEnabled = $sysSettings['cache_log_enabled'] ?? true;
$debugLogEnabled = $sysSettings['debug_log_enabled'] ?? true;
$envFileName = $isAdmin ? SystemSettings::getEnvFileName() : '';

// Page title
$pageTitle = $language === 'UK' ? 'Preferences' : 'Voorkeuren';

// Determine if we're in AJAX/nomenu mode
$isNomenuMode = defined('CMA_NOMENU_MODE') && CMA_NOMENU_MODE;

if (!$isNomenuMode) {
    // Standalone mode - output full HTML structure
    $extraHead = cma_script('../library/webcomponents/lib-switch.js', true);
    $extraHead .= cma_script('../library/webcomponents/lib-toaster.js', true);
    cma_html_header($pageTitle, $extraHead);
    echo '<body class="contentbody">';
}

// Toolbar - autosave status instead of a save button, title goes to breadcrumb via loadPage.
// Geen begeleidende tekst: het scherm heeft geen opslaan-knop, dus er valt niets te
// verwarren, en een regel die op elk bezoek hetzelfde zegt is na de eerste keer ruis. Wat
// er WEL toe doet - er wordt op dit moment iets bewaard - laat de spinner zien.
ToolbarHelper::start();
echo '<span class="cma-page__autosave" id="autosaveStatus">' . PHP_EOL;
echo '  <lib-loader id="autosaveSpinner" size="small" delay="0" class="cma-page__autosave-spinner"></lib-loader>' . PHP_EOL;
echo '</span>' . PHP_EOL;
ToolbarHelper::end();
?>
<title><?= Server::htmlEncode($pageTitle) ?></title>

<div id="c" class="tools">
    <form id="preferencesForm">
        <input type="hidden" name="action" value="savePreferences">

        <table class="form-table preferences-table">
            <!-- Display Group -->
            <tr class="groupbox-row">
                <td colspan="3">
                    <cma-groupbox group-id="1" form-id="0" caption="<?= $language === 'UK' ? 'Display' : 'Weergave' ?>"></cma-groupbox>
                </td>
            </tr>
            <tr id="_g1_1">
                <td class="label-cell">
                    <label for="theme"><?= $language === 'UK' ? 'Theme' : 'Thema' ?></label>
                </td>
                <td class="input-cell">
                    <select name="theme" id="theme" class="form-control" style="width:200px;">
                        <option value="light" <?= $currentTheme === 'light' ? 'selected' : '' ?>><?= $language === 'UK' ? 'Light' : 'Licht' ?></option>
                        <option value="dark" <?= $currentTheme === 'dark' ? 'selected' : '' ?>><?= $language === 'UK' ? 'Dark' : 'Donker' ?></option>
                        <option value="system" <?= $currentTheme === 'system' ? 'selected' : '' ?>><?= $language === 'UK' ? 'System' : 'Systeem' ?></option>
                    </select>
                </td>
                <td class="hint-cell"></td>
            </tr>
            <tr id="_g1_3" class="groupbox_end">
                <td class="label-cell">
                    <label for="popupStyle"><?= $language === 'UK' ? 'Popup Style' : 'Popup stijl' ?></label>
                </td>
                <td class="input-cell">
                    <select name="popupStyle" id="popupStyle" class="form-control" style="width:200px;">
                        <option value="sidepanel" <?= $popupStyle === 'sidepanel' ? 'selected' : '' ?>><?= $language === 'UK' ? 'Side Panel' : 'Zijpaneel' ?></option>
                        <option value="popup" <?= $popupStyle === 'popup' ? 'selected' : '' ?>><?= $language === 'UK' ? 'Popup Window' : 'Popup venster' ?></option>
                    </select>
                </td>
                <td class="hint-cell">
                    <?= $language === 'UK' ? 'Choose how subforms and dialogs open' : 'Kies hoe subformulieren en dialogen openen' ?>
                </td>
            </tr>

<?php if ($isDevOrAdmin): ?>
            <!-- Developer Group -->
            <tr class="groupbox-row">
                <td colspan="3">
                    <cma-groupbox group-id="2" form-id="0" caption="<?= $language === 'UK' ? 'Developer' : 'Ontwikkelaar' ?>"></cma-groupbox>
                </td>
            </tr>
            <tr id="_g2_1">
                <td class="label-cell">
                    <label for="debugMode"><?= $language === 'UK' ? 'Console Logging' : 'Console logging' ?></label>
                </td>
                <td class="input-cell">
                    <lib-switch name="debugMode" id="debugMode" <?= $debugMode === 'J' ? 'checked' : '' ?>></lib-switch>
                </td>
                <td class="hint-cell">
                    <?= $language === 'UK' ? 'Enable console.log output (disable for performance testing)' : 'Schakel console.log output in (uitschakelen voor snelheidstests)' ?>
                </td>
            </tr>
            <tr id="_g2_2">
                <td class="label-cell">
                    <label for="showDebugOverlay"><?= $language === 'UK' ? 'Show Debug Overlay' : 'Debug overlay tonen' ?></label>
                </td>
                <td class="input-cell">
                    <lib-switch name="showDebugOverlay" id="showDebugOverlay" <?= $showDebugOverlay === 'J' ? 'checked' : '' ?>></lib-switch>
                </td>
                <td class="hint-cell">
                    <?= $language === 'UK' ? 'Shows form state info overlay on all forms' : 'Toont formulier status info op alle formulieren' ?>
                </td>
            </tr>
            <tr id="_g2_3">
                <td class="label-cell">
                    <label for="sqlThreshold"><?= $language === 'UK' ? 'SQL Log Threshold' : 'SQL log drempelwaarde' ?></label>
                </td>
                <td class="input-cell">
                    <select name="sqlThreshold" id="sqlThreshold" class="form-control" style="width:200px;">
                        <option value="-1" <?= $sqlThreshold === -1 ? 'selected' : '' ?>><?= $language === 'UK' ? 'Off' : 'Uit' ?></option>
                        <option value="0" <?= $sqlThreshold === 0 ? 'selected' : '' ?>><?= $language === 'UK' ? 'All queries' : 'Alle queries' ?></option>
                        <option value="50" <?= $sqlThreshold === 50 ? 'selected' : '' ?>><?= $language === 'UK' ? 'Longer than 50ms' : 'Langer dan 50ms' ?></option>
                        <option value="100" <?= $sqlThreshold === 100 ? 'selected' : '' ?>><?= $language === 'UK' ? 'Longer than 100ms' : 'Langer dan 100ms' ?></option>
                        <option value="250" <?= $sqlThreshold === 250 ? 'selected' : '' ?>><?= $language === 'UK' ? 'Longer than 250ms' : 'Langer dan 250ms' ?></option>
                    </select>
                </td>
                <td class="hint-cell">
                    <?= $language === 'UK' ? 'Filter SQL queries in the Log Reader' : 'Filtert SQL queries in de Log Reader' ?>
                </td>
            </tr>
            <tr id="_g2_4" class="groupbox_end" >
                <td class="label-cell">
                    <label><?= $language === 'UK' ? 'Local Storage' : 'Lokale opslag' ?></label>
                </td>
                <td class="input-cell">
                    <button type="button" class="btn btn-primary" onclick="clearLocalStorage()"><?= $language === 'UK' ? 'Delete localStorage' : 'Verwijder localStorage' ?></button>
                </td>
                <td class="hint-cell">
                    <?= $language === 'UK' ? 'Clears all saved preferences (menu state, table columns, etc.)' : 'Wist alle opgeslagen voorkeuren (menu status, tabelkolommen, etc.)' ?>
                </td>
            </tr>
<?php endif; ?>

<?php if ($isAdmin): ?>
            <!-- System Settings Group -->
            <tr class="groupbox-row">
                <td colspan="3">
                    <cma-groupbox group-id="3" form-id="0" caption="<?= $language === 'UK' ? 'System Settings' : 'Systeeminstellingen' ?>"></cma-groupbox>
                </td>
            </tr>
            <tr id="_g3_1">
                <td colspan="3" class="hint-cell" style="padding:8px 12px;">
                    <?= $language === 'UK'
                        ? 'These settings affect all users and are persisted in the ' . $envFileName . ' file.'
                        : 'Deze instellingen gelden voor alle gebruikers en worden opgeslagen in het ' . $envFileName . ' bestand.' ?>
                </td>
            </tr>
            <tr id="_g3_2">
                <td class="label-cell">
                    <label for="perfLogEnabled"><?= $language === 'UK' ? 'Performance Logging' : 'Performance logging' ?></label>
                </td>
                <td class="input-cell">
                    <lib-switch name="perfLogEnabled" id="perfLogEnabled" <?= $perfLogEnabled ? 'checked' : '' ?> data-system="1"></lib-switch>
                </td>
                <td class="hint-cell">
                    <?= $language === 'UK' ? 'Log API calls, queries, and page load times' : 'Log API-aanroepen, queries en laadtijden' ?>
                </td>
            </tr>
            <tr id="_g3_3">
                <td class="label-cell">
                    <label for="cacheLogEnabled"><?= $language === 'UK' ? 'Cache Logging' : 'Cache logging' ?></label>
                </td>
                <td class="input-cell">
                    <lib-switch name="cacheLogEnabled" id="cacheLogEnabled" <?= $cacheLogEnabled ? 'checked' : '' ?> data-system="1"></lib-switch>
                </td>
                <td class="hint-cell">
                    <?= $language === 'UK' ? 'Log cache hits and misses' : 'Log cache hits en misses' ?>
                </td>
            </tr>
            <tr id="_g3_4"  class="groupbox_end">
                <td class="label-cell">
                    <label for="debugLogEnabled"><?= $language === 'UK' ? 'Debug Logging' : 'Debug logging' ?></label>
                </td>
                <td class="input-cell">
                    <lib-switch name="debugLogEnabled" id="debugLogEnabled" <?= $debugLogEnabled ? 'checked' : '' ?> data-system="1"></lib-switch>
                </td>
                <td class="hint-cell">
                    <?= $language === 'UK' ? 'Log debug information' : 'Log debug informatie' ?>
                </td>
            </tr>
<?php endif; ?>
        </table>
    </form>
</div>

<script>
// Autosave state: every change saves itself, so there is no dirty flag and no
// save button. A change during an in-flight save queues one follow-up save.
var prefsSaveTimer = null;
var prefsSaveInFlight = false;
var prefsSaveQueued = false;

function setPrefsSaving(saving) {
    var spinner = document.getElementById('autosaveSpinner');
    if (!spinner) return;
    // Toggle the attribute rather than calling show()/hide(): this also works
    // before the custom element has upgraded.
    if (saving) {
        spinner.setAttribute('active', '');
    } else {
        spinner.removeAttribute('active');
    }
}

function clearLocalStorage() {
    var count = localStorage.length;
    localStorage.clear();
    libToast.success('<?= $language === 'UK' ? 'localStorage cleared' : 'localStorage gewist' ?> (' + count + ' <?= $language === 'UK' ? 'items' : 'items' ?>)');
}

// Collect rapid changes (e.g. toggling a switch twice) into one request
function schedulePreferencesSave() {
    clearTimeout(prefsSaveTimer);
    prefsSaveTimer = setTimeout(savePreferences, 300);
}

function savePreferences() {
    if (prefsSaveInFlight) {
        prefsSaveQueued = true;
        return;
    }
    prefsSaveInFlight = true;
    setPrefsSaving(true);

    var form = document.getElementById('preferencesForm');
    var formData = new FormData(form);
    formData.append('action', 'savePreferences');
    formData.append('ajax', '1');

    // Get lib-switch values (use .checked property on the web component)
    var switches = form.querySelectorAll('lib-switch');
    switches.forEach(function(sw) {
        var name = sw.getAttribute('name');
        formData.set(name, sw.checked ? 'J' : 'N');
    });

    // Collect promises for saving
    var savePromises = [];

    // Save user preferences
    savePromises.push(
        fetch('/cma/preferences.php', {
            method: 'POST',
            body: formData
        })
        .then(function(response) { return response.json(); })
    );

    // Also save system settings if admin
    <?php if ($isAdmin): ?>
    var sysFormData = new FormData();
    sysFormData.append('action', 'saveSystemSettings');
    sysFormData.append('ajax', '1');

    var perfLog = document.getElementById('perfLogEnabled');
    var cacheLog = document.getElementById('cacheLogEnabled');
    var debugLog = document.getElementById('debugLogEnabled');

    sysFormData.append('perfLogEnabled', perfLog && perfLog.checked ? 'J' : 'N');
    sysFormData.append('cacheLogEnabled', cacheLog && cacheLog.checked ? 'J' : 'N');
    sysFormData.append('debugLogEnabled', debugLog && debugLog.checked ? 'J' : 'N');

    savePromises.push(
        fetch('/cma/preferences.php', {
            method: 'POST',
            body: sysFormData
        })
        .then(function(response) { return response.json(); })
    );
    <?php endif; ?>

    // Wait for all saves to complete
    var reloading = false;
    Promise.all(savePromises)
    .then(function(results) {
        var userResult = results[0];
        var allSuccess = results.every(function(r) { return r.success; });

        if (allSuccess) {
            // Update LibLog runtime config from cookie (debug mode preference may have changed)
            if (typeof LibLog !== 'undefined' && LibLog.refreshFromCookie) {
                LibLog.refreshFromCookie();
            }

            if (userResult.needsRefresh) {
                // A new theme only takes effect on a fresh render of the shell
                reloading = true;
                libToast.info('<?= $language === 'UK' ? 'Refreshing...' : 'Pagina wordt ververst...' ?>');
                setTimeout(function() {
                    if (userResult.menuStyleChanged) {
                        window.top.location.href = 'default.php';
                    } else {
                        window.location.reload();
                    }
                }, 600);
            }
        } else {
            var failedResults = results.filter(function(r) { return !r.success; });
            libToast.error(failedResults[0].message || '<?= $language === 'UK' ? 'Error saving' : 'Fout bij opslaan' ?>');
        }
    })
    .catch(function(error) {
        libToast.error('<?= $language === 'UK' ? 'Error saving preferences' : 'Fout bij opslaan voorkeuren' ?>: ' + error.message);
    })
    .then(function() {
        prefsSaveInFlight = false;
        if (reloading) return;
        setPrefsSaving(false);
        if (prefsSaveQueued) {
            prefsSaveQueued = false;
            savePreferences();
        }
    });
}

// Initialize autosave - run immediately since page may be loaded via AJAX
(function() {
    function initAutosave() {
        var form = document.getElementById('preferencesForm');
        if (!form) return;

        // Sync popup style from cookie to localStorage for library.js
        var popupStyleSelect = document.getElementById('popupStyle');
        if (popupStyleSelect) {
            try {
                localStorage.setItem('cma_popup_style', popupStyleSelect.value);
            } catch(e) {}
        }

        // Sync debug overlay from cookie to localStorage
        var debugSwitch = document.getElementById('showDebugOverlay');
        if (debugSwitch) {
            try {
                localStorage.setItem('cma_debug_overlay', debugSwitch.checked ? 'J' : 'N');
            } catch(e) {}
        }

        // Every change saves itself. lib-switch fires a bubbling 'change' too,
        // so selects and switches both arrive here.
        form.addEventListener('change', function(e) {
            // Sync popup style to localStorage immediately for preview
            if (e.target.id === 'popupStyle') {
                try {
                    localStorage.setItem('cma_popup_style', e.target.value);
                } catch(ex) {}
            }

            // Sync debug overlay to localStorage
            if (e.target.id === 'showDebugOverlay') {
                try {
                    localStorage.setItem('cma_debug_overlay', e.target.checked ? 'J' : 'N');
                } catch(ex) {}
            }

            schedulePreferencesSave();
        });
    }

    // Run immediately if DOM is ready, otherwise wait
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAutosave);
    } else {
        initAutosave();
    }
})();
</script>

<?php
if (!$isNomenuMode) {
    cma_body_end();
}
?>
