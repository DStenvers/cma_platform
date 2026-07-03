<?php
/**
 * Unified Tools Page — BIG inline menu.
 *
 * Landing (no ?tool): renders the admin tools/forms catalog as a searchable,
 * grouped grid directly in the shell content area — the "big menu". Selecting
 * a tool re-loads this page with ?tool=<...> and shows that tool full-width.
 * The tree-based two-pane predecessor is archived in tools_DEPRECATED.php.
 *
 * The catalog comes from buildToolsTreeData() in tools_catalog.inc — the single
 * source shared with the header launcher (cma-launcher / api/tools-catalog.php).
 *
 * URL Parameters:
 * - tool: Tool page to load full-width (friendly name or path). Absent = menu.
 */

use App\Library\Application;
use App\Library\Request;
use App\Library\Response;
use Cma\Services\Logger;
use App\Library\Server;
use Cma\CmaRepository;
use Cma\SecurityHelper;

require_once __DIR__ . '/bootstrap.inc';
require_once __DIR__ . '/tools_catalog.inc'; // buildToolsTreeData() — shared with the header launcher (api/tools-catalog.php)

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

$isDeveloper = SecurityHelper::isDeveloper();
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
// below and the menu items (which link to form.php?form=<form>) both derive the
// form from here, so the alias and the clean form URL can't drift into two
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

if (!empty($initialTool)) {
    // A tool is selected: show it full-width. Getting back to the menu is the
    // header "Menu" launcher (or navigating to /cma/tools with no ?tool).
    echo '<iframe name="R" id="tools-content" class="tools-content-area" src="'
        . Server::htmlEncode($initialTool) . '" frameborder="0"></iframe>';
    echo '</div>';
    return;
}

// No tool selected: render the BIG inline menu from the shared catalog.
try {
    $treeData = buildToolsTreeData($isDeveloper);
} catch (Exception $e) {
    $treeData = [];
    Logger::error('tools.php: Error building tools catalog', ['error' => $e->getMessage()]);
}

/**
 * Flatten one level of sub-folders into their own sections (e.g. Developer ->
 * Testen), mirroring cma-launcher._collectGroups() so the inline menu and the
 * header launcher stay visually identical.
 */
function tools_menu_collect_groups(array $nodes, string $prefix, array &$out): void
{
    foreach ($nodes as $node) {
        if (($node['type'] ?? '') !== 'folder') {
            continue;
        }
        $title = $prefix !== '' ? $prefix . ' · ' . ($node['label'] ?? '') : ($node['label'] ?? '');
        $items = array_values(array_filter($node['children'] ?? [], fn($c) => ($c['type'] ?? '') === 'item'));
        if ($items) {
            $out[] = ['title' => $title, 'icon' => $node['icon'] ?? '', 'items' => $items];
        }
        $subs = array_values(array_filter($node['children'] ?? [], fn($c) => ($c['type'] ?? '') === 'folder'));
        if ($subs) {
            tools_menu_collect_groups($subs, $title, $out);
        }
    }
}

/**
 * Resolve a catalog href into how it navigates in the shell — mirrors
 * cma-launcher.resolveNav(). Form-backed items are canonical /cma/form/<form>
 * routes loaded in the top window; every other tool page is passed to
 * tools.php?tool=<path> (the full .php path, which tools.php resolves) and gets
 * a pretty /cma/tools?tool=<name> address for the URL bar.
 *
 * Returns ['page' => loadPage target, 'url' => clean URL, 'form' => bool].
 */
function tools_menu_nav(string $href): array
{
    if (strpos($href, 'form.php?form=') === 0) {
        preg_match('/form=([^&]+)/', $href, $m);
        return ['page' => $href, 'url' => $m ? '/cma/form/' . $m[1] : $href, 'form' => true];
    }
    $base = explode('?', $href)[0];
    $query = strpos($href, '?') !== false ? '&' . substr($href, strpos($href, '?') + 1) : '';
    // Pass the full .php path as the tool param — tools.php's path branch resolves
    // it even for nested paths that no friendly short-name covers.
    $page = 'tools.php?tool=' . $base . $query;
    // Pretty short name for the URL bar when the file follows the tools_X / X
    // convention with no extra path segment; otherwise fall back to the path.
    if (preg_match('#^tools/tools_([^./]+)\.php$#', $base, $m) || preg_match('#^tools/([^./]+)\.php$#', $base, $m)) {
        $short = $m[1];
    } else {
        $short = $base;
    }
    // $query already begins with '&' (e.g. "&tab=manage"), so append as-is.
    $url = '/cma/tools?tool=' . rawurlencode($short) . $query;
    return ['page' => $page, 'url' => $url, 'form' => false];
}

$groups = [];
tools_menu_collect_groups($treeData, '', $groups);
?>

<div class="tools-menu" id="toolsMenu">
    <div class="tools-menu__head">
        <h2 class="tools-menu__title">Alle beheerstools</h2>
        <input type="search" class="tools-menu__search" id="toolsMenuSearch"
               placeholder="Zoek een tool…" aria-label="Zoek een tool" autocomplete="off">
    </div>
    <div class="tools-menu__grid">
        <?php foreach ($groups as $g): ?>
            <section class="tools-menu__group">
                <h3 class="tools-menu__group-title">
                    <?php if (!empty($g['icon'])): ?><span class="lnr <?= Server::htmlEncode($g['icon']) ?>"></span><?php endif; ?>
                    <?= Server::htmlEncode($g['title']) ?>
                </h3>
                <div class="tools-menu__items">
                    <?php foreach ($g['items'] as $it):
                        $nav = tools_menu_nav($it['href'] ?? '#'); ?>
                        <a class="tools-menu__item"
                           href="<?= Server::htmlEncode($nav['url']) ?>"
                           data-page="<?= Server::htmlEncode($nav['page']) ?>"
                           data-url="<?= Server::htmlEncode($nav['url']) ?>"
                           data-form="<?= $nav['form'] ? '1' : '0' ?>"
                           data-search="<?= Server::htmlEncode(strtolower($it['label'] ?? '')) ?>">
                            <?php if (!empty($it['icon'])): ?><span class="tools-menu__item-icon lnr <?= Server::htmlEncode($it['icon']) ?>"></span><?php endif; ?>
                            <span class="tools-menu__item-label"><?= Server::htmlEncode($it['label'] ?? '') ?></span>
                            <?php if (!empty($it['badge'])): ?><span class="tools-menu__badge" title="Toegangsniveau"><?= Server::htmlEncode($it['badge']) ?></span><?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>
        <?php if (empty($groups)): ?>
            <div class="tools-menu__error">Geen tools beschikbaar.</div>
        <?php endif; ?>
    </div>
</div>

<script>
(function () {
    'use strict';

    var menu = document.getElementById('toolsMenu');
    if (!menu) return;

    // Search-as-you-type: hide non-matching items; hide a group with no matches.
    var search = document.getElementById('toolsMenuSearch');
    if (search) {
        search.addEventListener('input', function () {
            var term = this.value.trim().toLowerCase();
            menu.querySelectorAll('.tools-menu__group').forEach(function (group) {
                var any = false;
                group.querySelectorAll('.tools-menu__item').forEach(function (item) {
                    var match = !term || (item.getAttribute('data-search') || '').indexOf(term) !== -1;
                    item.hidden = !match;
                    if (match) any = true;
                });
                group.hidden = !any;
            });
        });
        search.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                var first = menu.querySelector('.tools-menu__item:not([hidden])');
                if (first) { e.preventDefault(); first.click(); }
            }
        });
    }

    // Selecting an item loads it through the shell (window.loadPage), keeping the
    // header + sidebar in place. Form-backed items navigate the top window to the
    // canonical /cma/form/<form> route; tool pages re-load tools.php?tool=<...>
    // (which then shows the tool full-width).
    menu.querySelectorAll('.tools-menu__item').forEach(function (a) {
        a.addEventListener('click', function (e) {
            e.preventDefault();
            var page = a.getAttribute('data-page');
            var url = a.getAttribute('data-url');
            var win = window.top || window;
            if (page && typeof win.loadPage === 'function') {
                win.loadPage(page);
                if (url) { try { win.history.pushState(null, '', url); } catch (err) {} }
            } else if (url) {
                win.location.href = url;
            }
        });
    });
})();
</script>

<?php
echo '</div>'; // close .tools-page
