<?php
/**
 * Unified Tools Page
 *
 * Combines listtools.php and tools_dev_reports.php into a single page.
 * Uses cma-tree component with A/D access level badges.
 *
 * Features:
 * - Tree navigation with folders and items
 * - A (Admin) and D (Developer) access level badges
 * - Iframe loading for tool content
 * - Stateful tree (remembers expanded/collapsed state)
 *
 * URL Parameters:
 * - tool: Tool page to load in the iframe (optional)
 */

use App\Library\Application;
use App\Library\Request;
use App\Library\Response;
use Cma\Services\Logger;
use App\Library\Server;
use Cma\CmaRepository;
use Cma\SecurityHelper;
use Cma\ToolbarHelper;

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

$isDeveloper = SecurityHelper::isDeveloper();
$isNomenuMode = defined('CMA_NOMENU_MODE') && CMA_NOMENU_MODE;

// Friendly name to tool file mapping
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
    'query' => 'tools/tools_query.php',
    'sql' => 'tools/tools_query.php',
    'formwiz' => 'tools/tools_formwiz.php',
    'formedit' => 'tools/tools_formedit.php',
    'form_editor' => 'tools/tools_formedit.php',
    'db_sync' => 'tools/tools_db_sync.php',
    'sync' => 'tools/tools_db_sync.php',
    'contentblocks' => 'form.php?form=contentblocks',
    'copymod' => 'tools/tools_dev_copymod.php',
    'menus' => 'form.php?form=_menus',
    'monitoring' => 'form.php?form=cmamonitoring',
    'tests' => 'tools/tools_testrunner.php',
    'testrunner' => 'tools/tools_testrunner.php',
    'cypress' => 'tools/tools_testrunner.php',
    'phpunit' => 'tools/tools_phpunit.php',
    'unittests' => 'tools/tools_phpunit.php',
    'marketingurl' => 'form.php?form=marketingurl',
    'redirects' => 'form.php?form=marketingurl',
    'endpoints' => 'tools/tools_endpoint_tester.php',
    'endpoint_tester' => 'tools/tools_endpoint_tester.php',
    'report-designer' => 'report-designer.php',
    'report_designer' => 'report-designer.php',
    'rapport' => 'report-designer.php',
    'storybook' => 'tools/storybook.php',
    'components' => 'tools/storybook.php',
    'formdefinitions' => 'tools/tools_formedit.php',
    'webp' => 'tools/tools_webp_convert.php',
    'webp_convert' => 'tools/tools_webp_convert.php',
    'llm' => 'tools/tools_llm.php',
    'llm_management' => 'tools/tools_llm.php',
    'llm_analyzer' => 'tools/tools_llm.php', // legacy alias (was the original name)
];

// Get initial tool to load (may be friendly name or full path)
$toolParam = Request::query('tool', '');
$initialTool = '';

if (!empty($toolParam)) {
    // Check if it's a friendly name
    $toolKey = strtolower($toolParam);
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

// Build tree data structure with access level badges
// Wrapped in try-catch to prevent page hang on errors
try {
    $treeData = buildToolsTreeData($isDeveloper);
    $treeJson = json_encode($treeData, JSON_HEX_APOS | JSON_HEX_QUOT);
} catch (Exception $e) {
    $treeData = [];
    $treeJson = '[]';
    Logger::error('tools.php: Error building tree data', ['error' => $e->getMessage()]);
}

if (!$isNomenuMode) {
    // Standalone mode - output full HTML structure
    cma_html_header('Tools', '', false);
    ?>
    <base href="/cma/">
    <?php cma_script('webcomponents/cma-tree.js'); ?>
    <?php cma_script('webcomponents/cma-fold.js'); ?>
    <style>
    /* Tools Layout - consistent with reports.php and form.php */
    body.tools-layout {
        display: flex;
        flex-direction: row;
        height: 100vh;
        overflow: hidden;
        margin: 0;
        padding: 0;
    }

    body.tools-layout #leftlist {
        flex: 0 0 280px;
        min-width: 150px;
        max-width: 500px;
        height: 100%;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        background: var(--bg-surface);
        border-right: 1px solid var(--border-color);
    }

    body.tools-layout #leftlist #c {
        flex: 1;
        overflow: auto;
        padding: 8px;
    }

    body.tools-layout #details_iframe {
        flex: 1;
        height: 100%;
        border: none;
        background: var(--bg-body);
    }

    body.tools-layout cma-fold {
        flex: 0 0 8px;
        height: 100%;
    }

    </style>
    </head>
    <?php
    cma_body_start('listbody tools-layout');
} else {
    // AJAX/nomenu mode - add base tag for correct URL resolution with clean URLs
    echo '<base href="/cma/">';
    echo '<div class="tools-ajax-container">';
    cma_script('webcomponents/cma-tree.js');
    cma_script('webcomponents/cma-fold.js');
}
?>

<div id="leftlist">
    <?php ToolbarHelper::start(false); ?>
    <?php ToolbarHelper::treeButtons(); ?>
    <?php ToolbarHelper::end(false); ?>
    <div id="c" class="listcontent blockselect" onselectstart="return false">
        <cma-tree
            id="tools-tree"
            storage-key="tree_tools_unified"
            item-icon="tools"
            data='<?= htmlspecialchars($treeJson, ENT_QUOTES, 'UTF-8') ?>'>
        </cma-tree>
    </div>
</div>

<?php if (!$isNomenuMode): ?>
<cma-fold
    orientation="vertical"
    target="#leftlist"
    min-size="150"
    max-size="500"
    storage-key="tools_fold">
</cma-fold>
<iframe name="R" id="details_iframe" src="<?= !empty($initialTool) ? Server::htmlEncode($initialTool) : 'tools/tools_welcome.php' ?>" frameborder="0"></iframe>

<script>
(function() {
    'use strict';

    var tree = document.getElementById('tools-tree');
    if (!tree) {
        cmaLog.warn('[tools.php] Tree element not found');
        return;
    }

    // Wait for tree to initialize with timeout protection
    var initTimeout = setTimeout(function() {
        cmaLog.warn('[tools.php] Tree initialization timed out');
    }, 5000);

    setTimeout(function() {
        clearTimeout(initTimeout);
        try {
            tree.expandAll();
            // Global expand/collapse functions for toolbar buttons
            window.fExpandAll = function() { tree.expandAll(); };
            window.fCollapseAll = function() { tree.collapseAll(); };
        } catch (e) {
            cmaLog.error('[tools.php] Error expanding tree:', e);
        }
    }, 50);

    // Handle tree item clicks
    tree.addEventListener('item-click', function(e) {
        var href = e.detail.href;
        if (href && href !== '#' && href !== 'about:blank') {
            var iframe = document.getElementById('details_iframe');
            if (iframe) {
                iframe.src = href;
            }
            // Update URL to reflect selected tool
            var toolName = extractToolName(href);
            if (toolName) {
                var newUrl = window.location.pathname + '?tool=' + encodeURIComponent(toolName);
                history.pushState({ tool: toolName }, '', newUrl);
            }
        }
    });

    // Extract tool name from href for URL
    function extractToolName(href) {
        // Remove query params for cleaner URL
        var basePath = href.split('?')[0];
        // Extract filename without extension
        var match = basePath.match(/tools\/tools_([^.]+)\.php$/);
        if (match) return match[1];
        match = basePath.match(/tools\/([^.]+)\.php$/);
        if (match) return match[1];
        // For form.php?form=X, use form name
        if (href.indexOf('form.php?form=') !== -1) {
            var formMatch = href.match(/form=([^&]+)/);
            if (formMatch) return formMatch[1];
        }
        return null;
    }

    // Handle browser back/forward
    window.addEventListener('popstate', function(e) {
        if (e.state && e.state.tool) {
            var toolMap = <?= json_encode($toolNameMap) ?>;
            var toolPath = toolMap[e.state.tool] || 'tools/tools_' + e.state.tool + '.php';
            var iframe = document.getElementById('details_iframe');
            if (iframe) {
                iframe.src = toolPath;
            }
            if (tree && typeof tree.selectByHref === 'function') {
                tree.selectByHref(toolPath);
            }
        }
    });

    // Load initial tool if specified and select tree item
    <?php if (!empty($initialTool)): ?>
    (function selectInitialTool() {
        var toolHref = <?= json_encode($initialTool) ?>;
        var attempts = 0;
        var maxAttempts = 10;

        function trySelect() {
            attempts++;
            if (tree && typeof tree.selectByHref === 'function') {
                // Try with full href first, then without query params
                var selected = tree.selectByHref(toolHref);
                if (!selected) {
                    var basePath = toolHref.split('?')[0];
                    selected = tree.selectByHref(basePath);
                }
                if (selected) {
                    return; // Success
                }
            }
            // Retry if not found and attempts remaining
            if (attempts < maxAttempts) {
                setTimeout(trySelect, 100);
            }
        }

        // Start trying after tree has expanded
        setTimeout(trySelect, 200);
    })();
    <?php endif; ?>
})();
</script>

<?php else: ?>
<!-- AJAX mode - fold and iframe for tool pages -->
<cma-fold
    orientation="vertical"
    target="#leftlist"
    min-size="150"
    max-size="500"
    storage-key="tools_fold">
</cma-fold>
<iframe name="R" id="tools-content" class="tools-content-area" src="<?= !empty($initialTool) ? Server::htmlEncode($initialTool) : 'tools/tools_welcome.php' ?>" frameborder="0"></iframe>

<script>
(function() {
    'use strict';

    function setupToolsNavigation() {
        var iframe = document.getElementById('tools-content');
        var tree = document.getElementById('tools-tree');
        if (!iframe || !tree) {
            cmaLog.warn('[tools.php] Required elements not found');
            return;
        }

        // Expand all and setup global functions with timeout protection
        var initTimeout = setTimeout(function() {
            cmaLog.warn('[tools.php] AJAX mode tree initialization timed out');
        }, 5000);

        setTimeout(function() {
            clearTimeout(initTimeout);
            try {
                tree.expandAll();
                window.fExpandAll = function() { tree.expandAll(); };
                window.fCollapseAll = function() { tree.collapseAll(); };
            } catch (e) {
                cmaLog.error('[tools.php] Error in AJAX mode setup:', e);
            }
        }, 50);

        // Listen to item-click events from the tree
        tree.addEventListener('item-click', function(e) {
            var href = e.detail.href;
            if (!href || href === '#' || href === 'about:blank') return;

            // Load tool page in iframe
            iframe.src = href;

            // Update URL to reflect selected tool (for main.php wrapper)
            var toolName = extractToolName(href);
            if (toolName && window.parent === window) {
                var newUrl = window.location.pathname + '?tool=' + encodeURIComponent(toolName);
                history.pushState({ tool: toolName }, '', newUrl);
            } else if (toolName && window.parent !== window) {
                // Update parent URL when loaded in main.php
                try {
                    var parentUrl = window.parent.location.pathname + '?page=tools.php&tool=' + encodeURIComponent(toolName);
                    window.parent.history.pushState({ tool: toolName }, '', parentUrl);
                } catch (e) {
                    // Cross-origin, ignore
                }
            }
        });

        // Extract tool name from href for URL
        function extractToolName(href) {
            var basePath = href.split('?')[0];
            var match = basePath.match(/tools\/tools_([^.]+)\.php$/);
            if (match) return match[1];
            match = basePath.match(/tools\/([^.]+)\.php$/);
            if (match) return match[1];
            if (href.indexOf('form.php?form=') !== -1) {
                var formMatch = href.match(/form=([^&]+)/);
                if (formMatch) return formMatch[1];
            }
            return null;
        }

        // Select initial tool in tree if specified
        <?php if (!empty($initialTool)): ?>
        (function selectInitialTool() {
            var toolHref = <?= json_encode($initialTool) ?>;
            var attempts = 0;
            var maxAttempts = 10;

            function trySelect() {
                attempts++;
                if (tree && typeof tree.selectByHref === 'function') {
                    var basePath = toolHref.split('?')[0];
                    var selected = tree.selectByHref(basePath) || tree.selectByHref(toolHref);
                    if (selected) return;
                }
                if (attempts < maxAttempts) {
                    setTimeout(trySelect, 100);
                }
            }

            setTimeout(trySelect, 200);
        })();
        <?php endif; ?>
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', setupToolsNavigation);
    } else {
        setupToolsNavigation();
    }
})();
</script>

<style>
.tools-ajax-container {
    display: flex;
    flex-direction: row;
    flex: 1;
    height: 100%; /* Required for cma-fold height: 100% to work */
    min-height: 0;
    overflow: hidden;
}

.tools-ajax-container #leftlist {
    flex: 0 0 280px;
    min-width: 150px;
    max-width: 500px;
    height: 100%; /* Explicit height for children using percentage heights */
    overflow: hidden;
    display: flex;
    flex-direction: column;
    background: var(--bg-surface);
    border-right: 1px solid var(--border-color);
}

.tools-ajax-container #leftlist #c {
    flex: 1;
    overflow: auto;
    padding: 8px !important;
}

.tools-ajax-container cma-fold {
    flex: 0 0 8px;
    height: 100%;
}

iframe.tools-content-area {
    flex: 1;
    height: 100%;
    border: none;
    background: var(--bg-body);
}
</style>
<?php endif; ?>

<?php
if (!$isNomenuMode) {
    cma_body_end();
} else {
    echo '</div>'; // close tools-ajax-container
}

/**
 * Build the unified tools tree data structure for cma-tree component
 * Includes access level badges (A for Admin, D for Developer)
 */
function buildToolsTreeData(bool $isDeveloper): array
{
    $folders = [];

    // === STANDAARD TOOLS ===
    $standardFolder = [
        'type' => 'folder',
        'label' => 'Standaard',
        'icon' => 'lnr-layers',
        'children' => [
            ['type' => 'item', 'label' => 'Server informatie', 'href' => 'tools/tools_serverinfo.php', 'target' => 'R', 'icon' => 'lnr-laptop'],
            ['type' => 'item', 'label' => 'Cache leegmaken', 'badge' => 'A', 'href' => 'tools/tools_clearcache.php', 'target' => 'R', 'icon' => 'lnr-trash'],
        ]
    ];
    $folders[] = $standardFolder;

    // === SITE GEZONDHEID ===
    $healthFolder = [
        'type' => 'folder',
        'label' => 'Site gezondheid',
        'icon' => 'lnr-heart-pulse',
        'children' => [
            ['type' => 'item', 'label' => 'Logbestanden lezen', 'href' => 'tools/logreader.php', 'target' => 'R', 'icon' => 'lnr-list'],
            ['type' => 'item', 'label' => 'Controleer bestanden', 'badge' => 'A', 'href' => 'tools/tools_db_consistency.php', 'target' => 'R', 'icon' => 'lnr-sync'],
            ['type' => 'item', 'label' => 'LLM management', 'badge' => 'A', 'href' => 'tools/tools_llm.php', 'target' => 'R', 'icon' => 'lnr-brain'],
        ]
    ];
    $folders[] = $healthFolder;

    // === DATABASE ===
    $dbFolder = [
        'type' => 'folder',
        'label' => 'Database',
        'icon' => 'lnr-database',
        'children' => [
            ['type' => 'item', 'label' => 'Database structuur', 'badge' => 'A', 'href' => 'tools/tools_dbsummary.php', 'target' => 'R', 'icon' => 'lnr-list'],
            ['type' => 'item', 'label' => 'Database backup', 'badge' => 'A', 'href' => 'tools/tools_backup.php', 'target' => 'R', 'icon' => 'lnr-download'],
            ['type' => 'item', 'label' => 'Backups beheren', 'badge' => 'A', 'href' => 'tools/tools_backup.php?tab=manage', 'target' => 'R', 'icon' => 'lnr-undo'],
            ['type' => 'item', 'label' => 'Migraties', 'badge' => 'A', 'href' => 'tools/tools_migrations.php', 'target' => 'R', 'icon' => 'lnr-arrow-right'],
            ['type' => 'item', 'label' => 'SQL uitvoeren', 'badge' => 'D', 'href' => 'tools/tools_query.php', 'target' => 'R', 'icon' => 'lnr-code'],
        ]
    ];

    $folders[] = $dbFolder;

    // === RAPPORTAGES ===
    $reportsFolder = [
        'type' => 'folder',
        'label' => 'Rapportages',
        'icon' => 'lnr-chart-bars',
        'children' => [
            ['type' => 'item', 'label' => 'CMA Monitoring', 'badge' => 'A', 'href' => 'form.php?form=cmamonitoring', 'target' => 'R', 'icon' => 'lnr-chart-bars'],
        ]
    ];
    $folders[] = $reportsFolder;

    // === FRONT-END ===
    $frontendFolder = [
        'type' => 'folder',
        'label' => 'Front-end',
        'icon' => 'lnr-screen',
        'children' => [
            ['type' => 'item', 'label' => 'Content blocks', 'href' => 'form.php?form=contentblocks', 'target' => 'R', 'icon' => 'lnr-text-format'],
            ['type' => 'item', 'label' => 'Marketing URLs/Redirects', 'href' => 'form.php?form=marketingurl', 'target' => 'R', 'icon' => 'lnr-link'],
            ['type' => 'item', 'label' => 'WebP beeld-conversie', 'href' => 'tools/tools_webp_convert.php', 'target' => 'R', 'icon' => 'lnr-picture'],
        ]
    ];
    $folders[] = $frontendFolder;

    // === DEVELOPER HULPMIDDELEN (Developer only) ===
    if ($isDeveloper) {
        $devFolder = [
            'type' => 'folder',
            'label' => 'Developer',
            'badge' => 'D',
            'icon' => 'lnr-code',
            'children' => [
                ['type' => 'item', 'label' => 'CMA menu', 'href' => 'form.php?form=_menus', 'target' => 'R', 'icon' => 'lnr-menu'],
                ['type' => 'item', 'label' => 'Formulierdefinities', 'href' => 'tools/tools_formedit.php', 'target' => 'R', 'icon' => 'lnr-file-empty'],
                ['type' => 'item', 'label' => 'CMA definitie sync', 'href' => 'tools/tools_db_sync.php', 'target' => 'R', 'icon' => 'lnr-sync'],
                ['type' => 'item', 'label' => 'Component Storybook', 'href' => 'tools/storybook.php', 'target' => 'R', 'icon' => 'lnr-book'],
                ['type' => 'folder', 'label' => 'Testen', 'icon' => 'lnr-checkmark-circle', 'children' => [
                    ['type' => 'item', 'label' => 'Cypress browsertests', 'href' => 'tools/tools_testrunner.php', 'target' => 'R', 'icon' => 'lnr-rocket'],
                    ['type' => 'item', 'label' => 'PHP unit tests', 'href' => 'tools/tools_phpunit.php', 'target' => 'R', 'icon' => 'lnr-checkmark-circle'],
                    ['type' => 'item', 'label' => 'Endpoint interactief testen', 'href' => 'tools/tools_endpoint_tester.php', 'target' => 'R', 'icon' => 'lnr-pulse'],
                    ['type' => 'item', 'label' => 'Endpoint foutcontrole', 'href' => 'tools/tests/test_all_endpoints.php', 'target' => 'R', 'icon' => 'lnr-warning'],
                ]],
            ]
        ];

        if (Application::get('local', '')) {
            if (file_exists(Server::mapPath('tools/tools_dev_copymod.php'))) {
                $devFolder['children'][] = ['type' => 'item', 'label' => 'Module kopiëren', 'href' => 'tools/tools_dev_copymod.php', 'target' => 'R', 'icon' => 'lnr-layers'];
            }
        }

        $folders[] = $devFolder;
    }

    return $folders;
}
?>
