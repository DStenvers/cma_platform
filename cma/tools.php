<?php
/**
 * Unified Tools Page — toolbar + shared launcher.
 *
 * The tools page content is a slim toolbar with a single "Menu" button that
 * opens the shared header launcher (cma-launcher) — the one and only tools
 * menu, so there is no second inline menu with its own visibility state to
 * manage. Selecting a tool (from the launcher) loads it full-width here.
 * The former two-pane cma-tree view is archived in tools_DEPRECATED.php.
 *
 * URL Parameters:
 * - tool: Tool page to load full-width (friendly name or path). Absent = empty
 *   state prompting the user to open the Menu.
 */

use App\Library\Request;
use App\Library\Response;
use App\Library\Server;
use Cma\CmaRepository;
use Cma\SecurityHelper;

require_once __DIR__ . '/bootstrap.inc';

// Check access - minimum admin level required for tools
if (!SecurityHelper::isAdmin()) {
    if (defined('CMA_NOMENU_MODE') && CMA_NOMENU_MODE) {
        echo '<lib-message type="error">Geen toegang - alleen beheerders</lib-message>';
        exit;
    }
    header('Location: login.php');
    exit;
}

Response::noCache();
CmaRepository::setCaching(true);

$isNomenuMode = defined('CMA_NOMENU_MODE') && CMA_NOMENU_MODE;

// The main app sidebar lives in the shell (main.php). tools.php must therefore
// ALWAYS render inside that shell — a standalone/top-level hit (a direct
// /cma/tools.php?tool=… or the /cma/tools/<name> friendly URL) has no sidebar.
// Redirect any non-shell request to the clean /cma/tools route, which loads
// tools.php into the shell content area (sidebar present). The requested tool
// is preserved; the shell then fetches tools.php in nomenu mode (no redirect).
if (!$isNomenuMode) {
    // Target the shell explicitly via main.php (not the clean /cma/tools URL):
    // main.php never re-enters tools.php server-side, so this can't loop even if
    // the /cma/tools rewrite rule is missing on a site. main.php then loads
    // tools.php in nomenu mode (where this guard is skipped).
    $toolReq = Request::query('tool', '');
    $target = '/cma/main.php?page=tools.php' . ($toolReq !== '' ? '&tool=' . urlencode($toolReq) : '');
    header('Location: ' . $target, true, 302);
    exit;
}

// Friendly name -> tool file mapping (real tools rendered in the tools iframe).
// Form-backed admin entities (users, groups, …) are NOT here — they are
// canonical FORM routes (/cma/form/<form>) and live in $formBackedTools below,
// so the same form can't be reached under two different routes/layouts.
$toolNameMap = [
    'serverinfo' => 'tools/tools_serverinfo.php',
    'clearcache' => 'tools/tools_clearcache.php',
    'clear_cache' => 'tools/tools_clearcache.php',
    'maintenance' => 'tools/tools_maintenance.php',
    'onderhoud' => 'tools/tools_maintenance.php',
    'logreader' => 'tools/logreader.php',
    'logs' => 'tools/logreader.php',
    'dbsummary' => 'tools/tools_dbsummary.php',
    'db_summary' => 'tools/tools_dbsummary.php',
    'consistency' => 'tools/tools_db_consistency.php',
    'db_consistency' => 'tools/tools_db_consistency.php',
    'backup' => 'tools/tools_backup.php',
    'restore' => 'tools/tools_backup.php?tab=manage',
    'migrations' => 'tools/tools_migrations.php',
    'deploy_setup' => 'tools/tools_deploy_setup.php',
    'deploysetup' => 'tools/tools_deploy_setup.php',
    'query' => 'tools/tools_query.php',
    'sql' => 'tools/tools_query.php',
    'formwiz' => 'tools/tools_formwiz.php',
    'formedit' => 'tools/tools_formedit.php',
    'form_editor' => 'tools/tools_formedit.php',
    'db_sync' => 'tools/tools_db_sync.php',
    'sync' => 'tools/tools_db_sync.php',
    'copymod' => 'tools/tools_dev_copymod.php',
    'tests' => 'tools/tools_testrunner.php',
    'testrunner' => 'tools/tools_testrunner.php',
    'cypress' => 'tools/tools_testrunner.php',
    'phpunit' => 'tools/tools_phpunit.php',
    'unittests' => 'tools/tools_phpunit.php',
    'endpoints' => 'tools/tools_endpoint_tester.php',
    'endpoint_tester' => 'tools/tools_endpoint_tester.php',
    'report-designer' => 'report-designer.php',
    'report_designer' => 'report-designer.php',
    'rapport' => 'report-designer.php',
    'storybook' => 'tools/storybook.php',
    'components' => 'tools/storybook.php',
    'docs' => 'tools/documentation.php',
    'documentation' => 'tools/documentation.php',
    'deployment' => 'tools/documentation.php?topic=deployment',
    'formdefinitions' => 'tools/tools_formedit.php',
    'webp' => 'tools/tools_webp_convert.php',
    'webp_convert' => 'tools/tools_webp_convert.php',
    'llm' => 'tools/tools_llm.php',
    'llm_management' => 'tools/tools_llm.php',
    'llm_analyzer' => 'tools/tools_llm.php', // legacy alias (was the original name)
    // Surfaced in the tools menu in v1.28.45 (previously reachable only by URL).
    // These keys match what resolveNav() derives from the tools/<name>.php href.
    'dbcompact' => 'tools/tools_dbcompact.php',
    'create_indexes' => 'tools/tools_create_indexes.php',
    'sqlite_repair' => 'tools/tools_sqlite_repair.php',
    'db_health' => 'tools/db_health.php',
    'generate_forms' => 'tools/tools_generate_forms.php',
    'migrate_prepare' => 'tools/tools_migrate_prepare.php',
    'export_repository' => 'tools/tools_export_repository.php',
    // Nested tool: the launcher's resolveNav() derives the key from the path
    // after "tools/", which for tools/tests/*.php keeps the "tests/" segment.
    // Map that exact key so the "Endpoint foutcontrole" tile resolves instead
    // of rendering blank.
    'tests/test_all_endpoints' => 'tools/tests/test_all_endpoints.php',
];

// Form-backed admin entities: friendly alias -> JSON form name. These render as
// normal FORM routes (/cma/form/<form>) in the main shell, NOT inside the tools
// iframe. This is the single source for that mapping — the deep-link redirect
// below and the launcher items (which link to form.php?form=<form>) both derive
// the form from here, so the alias and the clean form URL can't drift into two
// separate routes for the same form.
$formBackedTools = [
    'users'         => 'users',
    'gebruikers'    => 'users',
    'groups'        => 'groups',
    'groepen'       => 'groups',
    'contentblocks' => 'contentblocks',
    'menus'         => '_menus',
    'monitoring'    => 'cmamonitoring',
    'marketingurl'  => 'marketingurl',
    'redirects'     => 'marketingurl',
    'emaillog'      => 'emaillog',
    'maillog'       => 'emaillog',
];

// Get initial tool/form to load (may be a friendly name or a full path).
$toolParam = Request::query('tool', '');
$formParam = Request::query('form', '');
$initialTool = '';

// Form-backed entities (users, groups, marketingurl, emaillog, …) load form.php
// INSIDE the tools iframe, so they carry the tools chrome (the "Menu" button).
// This is deliberately a SECOND way to reach a form: the canonical
// /cma/form/<form> route still works directly and independently — forms are
// callable both ways. A ?form=<form> OR a ?tool=<form-backed alias> lands here.
$formToLoad = '';
if ($formParam !== '') {
    $formToLoad = $formParam;
} elseif ($toolParam !== '' && isset($formBackedTools[strtolower($toolParam)])) {
    $formToLoad = $formBackedTools[strtolower($toolParam)];
}

if ($formToLoad !== '') {
    $initialTool = 'form.php?form=' . rawurlencode($formToLoad);
    $recId = Request::query('id', '');
    if ($recId !== '') {
        $initialTool .= '&id=' . rawurlencode($recId);
    }
} elseif (!empty($toolParam)) {
    $toolKey = strtolower($toolParam);

    // Friendly name -> tool file, or an explicit .php path.
    if (isset($toolNameMap[$toolKey])) {
        $initialTool = $toolNameMap[$toolKey];
    } elseif (strpos($toolParam, '.php') !== false) {
        $initialTool = $toolParam;
    }

    // Pass through any additional query parameters to the tool
    // (e.g., sql parameter for query tool).
    $extraParams = [];
    foreach (Request::queryAll() as $key => $value) {
        if ($key !== 'tool' && $key !== 'form' && $key !== 'id' && !empty($value)) {
            $extraParams[$key] = $value;
        }
    }
    if (!empty($extraParams) && !empty($initialTool)) {
        $separator = strpos($initialTool, '?') !== false ? '&' : '?';
        $initialTool .= $separator . http_build_query($extraParams);
    }
}

echo '<base href="/cma/">';
echo '<div class="tools-page">';
cma_script('webcomponents/cma-toolbar.js');
?>

<cma-toolbar variant="list" class="tools-toolbar">
    <left>
        <button type="button" class="cma-launcher-btn" id="toolsMenuBtn" aria-haspopup="dialog" title="Alle beheerstools">
            <span class="lnr lnr-menu"></span><span class="cma-launcher-btn__label">Alle tools</span>
        </button>
    </left>
</cma-toolbar>

<?php if (!empty($initialTool)): ?>
    <iframe name="R" id="tools-content" class="tools-content-area"
            src="<?= Server::htmlEncode($initialTool) ?>" frameborder="0"></iframe>
<?php else: ?>
    <div class="tools-empty" id="tools" hidden>Klik op <span class="cma-page__strong"> Alle tools </span> om een beheerstool te kiezen.</div>
<?php endif; ?>

<script>
(function () {
    'use strict';
    // The "Menu" button toggles the single shared launcher (cma-launcher) that
    // lives in the shell — same overlay as the header Menu button. It owns its
    // own open/close state, so there is nothing to manage here.
    var win = window.top || window;
    function toggleLauncher() {
        if (typeof win.toggleToolsLauncher === 'function') { win.toggleToolsLauncher(); return true; }
        return false;
    }
    function openLauncher() {
        if (typeof win.openToolsLauncher === 'function') { win.openToolsLauncher(); return true; }
        return false;
    }
    var btn = document.getElementById('toolsMenuBtn');
    if (btn) btn.addEventListener('click', toggleLauncher);
<?php if (empty($initialTool)): ?>
    // No tool selected: open the mega-menu straight away instead of showing the
    // placeholder. Retry briefly in case the shell hasn't defined the opener yet;
    // if it never appears, reveal the hint text as a fallback.
    var tries = 0;
    (function tryOpen() {
        if (openLauncher()) return;
        if (++tries < 20) { setTimeout(tryOpen, 25); return; }
        var empty = document.getElementById('tools');
        if (empty) empty.hidden = false;
    })();
<?php endif; ?>
})();
</script>

<?php
echo '</div>'; // close .tools-page
