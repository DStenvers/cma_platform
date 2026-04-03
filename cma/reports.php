<?php
use App\Library\Application;
use App\Library\Cookie;
use App\Library\Database;
use App\Library\Debug;
use App\Library\Profiler;
use App\Library\Request;
use App\Library\Response;
use App\Library\SQL;
use App\Library\Server;
use App\Library\Str;
use Cma\SecurityHelper;
use Cma\Services\ReportsService;
use Cma\ToolbarHelper;

require_once __DIR__ . '/bootstrap.inc';

// Check access
if (!SecurityHelper::isLoggedIn()) {
    if (defined('CMA_NOMENU_MODE') && CMA_NOMENU_MODE) {
        echo '<lib-message type="error">Sessie verlopen</lib-message>';
        exit;
    }
    header('Location: login.php');
    exit;
}

Response::noCache();
Debug::setActive(false);

// Determine if we're in AJAX/nomenu mode
$isNomenuMode = defined('CMA_NOMENU_MODE') && CMA_NOMENU_MODE;
$parSearchFor = (Request::query('SearchFor', '') != '' ? Request::query('SearchFor', '') : Request::post('SearchFor', '')) . '';

// Get tree data as JSON for cma-tree component
$treeJson = getTreeData($parSearchFor);

if (!$isNomenuMode) {
    // Standalone mode - output full HTML structure
    $extraHead = '<script>
// dummy voor sync na edit, niet nodig
function event_received_invalidate( rec, action) {}
document.addEventListener("DOMContentLoaded", function() {
    var tree = document.getElementById("reports-tree");
    if (tree) {
        tree.expandAll();
        // Global functions for toolbar
        window.fExpandAll = function() { tree.expandAll(); };
        window.fCollapseAll = function() { tree.collapseAll(); };
        // Auto-click if single result
        var items = tree.shadowRoot ? tree.shadowRoot.querySelectorAll("a.t[href]") : [];
        if (items.length === 1) items[0].click();
    }
    var searchField = document.getElementById("searchfor");
    if (searchField) searchField.focus();
});
</script>';
    cma_html_header('Rapportages', $extraHead, false);
    cma_script('webcomponents/cma-tree.js');
    cma_script('webcomponents/cma-fold.js');
    ToolbarHelper::writeJS();
    echo '</head>';
    echo '<body class="listbody tools-layout">';
} else {
    // AJAX/nomenu mode - just output the content
    echo '<div class="tools-ajax-container">';
    cma_script('webcomponents/cma-tree.js');
    cma_script('webcomponents/cma-fold.js');
}
?>

<div id="leftlist" class="tools-sidebar">
    <?php ToolbarHelper::start(false); ?>
    <?php if ($parSearchFor != ''): ?>
    <?php ToolbarHelper::linearButton('<a href=' . Request::currentDomain() . Request::getOriginalScript() . '>', '<span class="lnr lnr-back" data-tooltip="Terug"></span>', true, ''); ?>
    <?php endif; ?>
    <?php ToolbarHelper::treeButtons(); ?>
    <td width="99%">
        <form name="Snel" id="Snel" method="get" style="margin:0px;width:100%">
            <lib-search-input id="searchfor" name="searchfor" autofocus value="<?= Server::htmlEncode($parSearchFor) ?>" placeholder="Zoek naar ..."></lib-search-input>
        </form>
        <script>
            document.getElementById('searchfor').addEventListener('input', function() { searchasyoutype(); });
            document.getElementById('searchfor').addEventListener('search', function() { document.getElementById('Snel').submit(); });
        </script>
    </td>
    <?php ToolbarHelper::end(true); ?>
    <div id="c" class="listcontent blockselect" onselectstart="return false">
        <cma-tree
            id="reports-tree"
            storage-key="tree_reports"
            item-icon="report"
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
    storage-key="reports_fold">
</cma-fold>
<iframe name="R" id="details_iframe" src="about:blank" frameborder="0" border="0"></iframe>
<style>
/* Reports layout for proper flex resizing */
body.tools-layout {
    display: flex;
    flex-direction: row;
    height: 100vh;
    overflow: hidden;
}
body.tools-layout #leftlist {
    flex: 0 0 280px;
    min-width: 150px;
    max-width: 500px;
    height: 100%;
    overflow: auto;
    display: flex;
    flex-direction: column;
}
body.tools-layout #leftlist #c {
    flex: 1;
    overflow: auto;
    padding: 8px !important;
}
body.tools-layout #details_iframe {
    flex: 1;
    height: 100%;
    border: none;
}
body.tools-layout cma-fold {
    flex: 0 0 8px;
    height: 100%;
}
</style>
<?php else: ?>
<!-- Fold bar for resizing (cma-fold web component) -->
<cma-fold
    orientation="vertical"
    target="#leftlist"
    min-size="150"
    max-size="500"
    storage-key="reports_nomenu_fold">
</cma-fold>

<!-- Content area -->
<main id="reports-content" class="detail-panel">
    <div class="empty-state">Selecteer een rapportage uit het menu links.</div>
</main>

<script>
// In AJAX mode, report item clicks load content in the reports-content area
(function() {
    function setupReportsNavigation() {
        var contentArea = document.getElementById('reports-content');
        var tree = document.getElementById('reports-tree');
        if (!contentArea || !tree) return;

        // Initialize tree expand/collapse
        tree.expandAll();
        window.fExpandAll = function() { tree.expandAll(); };
        window.fCollapseAll = function() { tree.collapseAll(); };

        // Handle tree item clicks via custom event
        tree.addEventListener('item-click', function(e) {
            var href = e.detail.href;
            if (!href || href === '#' || href === 'about:blank') return;

            // Check if this is a report link (php file in current directory)
            if (href.match(/^(report|tools_)[a-z0-9_]+\.php/i) || href.match(/^[a-z0-9_]+\.php/i)) {
                // Show loading
                contentArea.innerHTML = '<div class="tools-loading">Laden...</div>';

                // Fetch the report page
                fetch('main.php?nomenu&page=' + encodeURIComponent(href))
                    .then(function(response) {
                        if (!response.ok) {
                            return response.text().then(function(text) {
                                var phpError = window.cmaErrorParser ? window.cmaErrorParser.extract(text) : null;
                                var error = new Error('HTTP ' + response.status);
                                error.statusCode = response.status;
                                error.phpError = phpError;
                                error.responseText = text;
                                throw error;
                            });
                        }
                        return response.text();
                    })
                    .then(function(html) {
                        var phpError = window.cmaErrorParser ? window.cmaErrorParser.extract(html) : null;
                        if (phpError) {
                            var error = new Error(phpError.message);
                            error.phpError = phpError;
                            throw error;
                        }

                        contentArea.innerHTML = html;
                        // Execute any scripts
                        var scripts = contentArea.querySelectorAll('script');
                        scripts.forEach(function(oldScript) {
                            var newScript = document.createElement('script');
                            if (oldScript.src) {
                                newScript.src = oldScript.src;
                            } else {
                                newScript.textContent = oldScript.textContent;
                            }
                            oldScript.parentNode.replaceChild(newScript, oldScript);
                        });
                    })
                    .catch(function(error) {
                        var errorContent = '';
                        if (error.phpError && window.cmaErrorParser) {
                            errorContent = window.cmaErrorParser.format(error.phpError);
                        } else {
                            var escapeHtml = window.cmaErrorParser ? window.cmaErrorParser.escapeHtml : function(s) {
                                var div = document.createElement('div'); div.textContent = s || ''; return div.innerHTML;
                            };
                            errorContent = escapeHtml(error.message);
                        }

                        var errorHtml = '<lib-message type="error">' +
                            '<strong>Fout bij laden rapportage</strong><br>' +
                            errorContent +
                            '</lib-message>';
                        contentArea.innerHTML = errorHtml;
                    });
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', setupReportsNavigation);
    } else {
        setupReportsNavigation();
    }
})();
</script>
<style>
/* Reports layout - similar to form.php */
.tools-ajax-container {
    display: flex;
    flex-direction: row; /* Explicit row direction for horizontal layout */
    flex: 1;
    height: 100%; /* Required for cma-fold height: 100% to work */
    min-height: 0;
    overflow: hidden;
}

/* Tree panel (left side) */
.tools-ajax-container .tools-sidebar {
    flex: 0 0 280px;
    min-width: 150px;
    max-width: 500px;
    height: 100%; /* Explicit height for children using percentage heights */
    display: flex;
    flex-direction: column;
    background: var(--bg-surface);
    border-right: 1px solid var(--border-color);
    overflow: hidden;
}
.tools-ajax-container .tools-sidebar .listcontent {
    flex: 1;
    overflow: auto;
    padding: 8px !important;
}
.tools-ajax-container .tools-sidebar .toolbar-right {
    justify-content: flex-end;
}

/* cma-fold in nomenu mode */
.tools-ajax-container cma-fold {
    flex: 0 0 8px;
    height: 100%;
}

/* Content area (right side) */
.tools-ajax-container .detail-panel {
    flex: 1;
    overflow: auto;
    background: var(--bg-surface);
    padding: 0;
}
.tools-ajax-container .detail-panel > #c {
    padding: 20px;
}

.tools-loading {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 100%;
    color: var(--text-muted);
    text-align: center;
    padding: 40px;
}

/* Tree active/selected item styling (consistent with form.php) */
.tools-sidebar .complextree li a.active,
.tools-sidebar .complextree li a.active::before,
.tools-sidebar #c a.active,
.tools-sidebar #c a.active::before {
    background-color: var(--color-primary) !important;
    color: #fff !important;
}

/* Error message styling */
.error-message {
    padding: 20px;
    margin: 20px;
    background: var(--bg-error, #fff5f5);
    border: 1px solid var(--border-error, #fc8181);
    border-radius: 6px;
    color: var(--text-error, #c53030);
}
.error-message h3 {
    margin: 0 0 10px 0;
    color: var(--text-error, #c53030);
}
.error-message p {
    margin: 5px 0;
}
.error-message .error-location {
    font-size: 0.9em;
    color: var(--text-muted);
    font-family: monospace;
}
</style>
<?php endif; ?>

<?php
if (!$isNomenuMode) {
    echo '</body></html>';
    if (Application::get('performance_log', '')) {
        Profiler::log('CMA', (string)floor(Profiler::getElapsed()), 'Tonen lijst rapportages', '', '');
    }
} else {
    echo '</div>'; // close tools-ajax-container
}

/**
 * Generate the reports tree data as JSON for cma-tree component
 * Uses ReportsService to load reports from JSON config
 * @param string $searchFor Optional search filter
 * @return string JSON string for cma-tree data attribute
 */
function getTreeData($searchFor = '')
{
    global $lang_reports;

    // Get reports grouped by module from JSON config
    $reportsByModule = ReportsService::getGroupedByModule(true);

    // Build tree structure for cma-tree component
    $rootNode = [
        'type' => 'folder',
        'label' => $lang_reports ?? 'Rapportages',
        'children' => []
    ];

    foreach ($reportsByModule as $moduleName => $reports) {
        // Apply search filter if set
        if ($searchFor != '') {
            $searchLower = strtolower($searchFor);
            // Check if module name matches
            $moduleMatches = stripos($moduleName, $searchLower) !== false;
            // Filter reports by title
            $filteredReports = array_filter($reports, function($r) use ($searchLower) {
                return stripos($r['title'] ?? '', $searchLower) !== false;
            });
            // If module doesn't match and no reports match, skip
            if (!$moduleMatches && empty($filteredReports)) {
                continue;
            }
            // Use filtered reports if module doesn't match
            if (!$moduleMatches) {
                $reports = $filteredReports;
            }
        }

        // Build module folder
        $displayModuleName = ucwords(strtolower($moduleName));
        $moduleNode = [
            'type' => 'folder',
            'label' => $displayModuleName,
            'children' => []
        ];

        foreach ($reports as $report) {
            $reportId = $report['id'] ?? 0;
            $reportTitle = $report['title'] ?? '';

            if (SecurityHelper::checkRights(SecurityHelper::TYPE_REPORT, $reportId)) {
                $moduleNode['children'][] = [
                    'type' => 'item',
                    'label' => $reportTitle,
                    'href' => 'reportdetails.php?RepID=' . $reportId,
                    'target' => 'R'
                ];
            }
        }

        // Only add module if it has visible reports
        if (!empty($moduleNode['children'])) {
            $rootNode['children'][] = $moduleNode;
        }
    }

    return json_encode([$rootNode], JSON_HEX_APOS | JSON_HEX_QUOT);
}
?>
