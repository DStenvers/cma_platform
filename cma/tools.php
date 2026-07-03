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

// Get initial tool to load (may be friendly name or full path)
$toolParam = Request::query('tool', '');
$initialTool = '';

if (!empty($toolParam)) {
    $toolKey = strtolower($toolParam);

    // Form-backed alias: collapse to the single canonical form route. Navigating
    // the top window (rather than rendering form.php in the tools iframe) keeps
    // /cma/form/<form> as the one route/layout for the form. The script runs in
    // whichever document tools.php is rendered into — standalone top page or
    // fetched into the SPA shell — and points the top window at the clean URL.
    if (isset($formBackedTools[$toolKey])) {
        $canonical = '/cma/form/' . $formBackedTools[$toolKey];
        header('Content-Type: text/html; charset=utf-8');
        echo '<script>(function(){var u=' . json_encode($canonical)
            . ';(window.top||window).location.replace(u);})();</script>';
        exit;
    }

    // Check if it's a friendly name
    if (isset($toolNameMap[$toolKey])) {
        $initialTool = $toolNameMap[$toolKey];
    } elseif (strpos($toolParam, '.php') !== false) {
        // It's already a file path
        $initialTool = $toolParam;
    }

    // Pass through any additional query parameters to the tool
    // (e.g., sql parameter for query tool)
    $extraParams = [];
    foreach (Request::queryAll() as $key => $value) {
        if ($key !== 'tool' && !empty($value)) {
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
            <span class="lnr lnr-menu"></span><span class="cma-launcher-btn__label">Menu</span>
        </button>
    </left>
</cma-toolbar>

<?php if (!empty($initialTool)): ?>
    <iframe name="R" id="tools-content" class="tools-content-area"
            src="<?= Server::htmlEncode($initialTool) ?>" frameborder="0"></iframe>
<?php else: ?>
    <div class="tools-empty">Klik op <span class="cma-page__strong">Menu</span> om een beheerstool te kiezen.</div>
<?php endif; ?>

<script>
(function () {
    'use strict';
    // The "Menu" button opens the single shared launcher (cma-launcher) that
    // lives in the shell — same overlay as the header Menu button. It owns its
    // own open/close state, so there is nothing to manage here.
    var btn = document.getElementById('toolsMenuBtn');
    if (!btn) return;
    btn.addEventListener('click', function () {
        var win = window.top || window;
        if (typeof win.openToolsLauncher === 'function') { win.openToolsLauncher(); }
    });
})();
</script>

<?php
echo '</div>'; // close .tools-page
