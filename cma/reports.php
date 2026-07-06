<?php
/**
 * Reports page — full-width "big menu" of reports grouped by module.
 *
 * Mirrors the tools page: a slim toolbar with a "Rapportages" button plus a
 * tile menu of every report the user may see. Selecting a report loads it
 * full-width (reportdetails.php in an iframe); the toolbar button returns to
 * the menu. The former two-pane cma-tree browser is archived in
 * reports_DEPRECATED.php.
 *
 * URL Parameters:
 * - RepID: report to open full-width. Absent = show the menu.
 */

use App\Library\Request;
use App\Library\Response;
use App\Library\Server;
use Cma\SecurityHelper;
use Cma\Services\ReportsService;

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

$isNomenuMode = defined('CMA_NOMENU_MODE') && CMA_NOMENU_MODE;

// The main app sidebar lives in the shell (main.php). reports.php must render
// inside that shell — a standalone/top-level hit has no sidebar. Redirect any
// non-shell request to main.php?page=reports.php (which loads reports.php in
// nomenu mode, where this guard is skipped), preserving the requested report.
if (!$isNomenuMode) {
    $repReq = Request::query('RepID', '');
    $target = '/cma/main.php?page=reports.php' . ($repReq !== '' ? '&RepID=' . urlencode($repReq) : '');
    header('Location: ' . $target, true, 302);
    exit;
}

$repId = (int) Request::query('RepID', 0);

// Build the rights-checked report menu, grouped by module. Same data source as
// the old tree (ReportsService::getGroupedByModule), rendered as tiles.
$groups = [];
foreach (ReportsService::getGroupedByModule(true) as $moduleName => $reports) {
    $items = [];
    foreach ($reports as $report) {
        $rid = (int) ($report['id'] ?? 0);
        if ($rid > 0 && SecurityHelper::checkRights(SecurityHelper::TYPE_REPORT, $rid)) {
            $items[] = ['id' => $rid, 'title' => (string) ($report['title'] ?? '')];
        }
    }
    if (!empty($items)) {
        $groups[ucwords(strtolower($moduleName))] = $items;
    }
}

echo '<base href="/cma/">';
echo '<div class="tools-page">';
cma_script('webcomponents/cma-toolbar.js');
?>

<cma-toolbar variant="list" class="tools-toolbar">
    <left>
        <button type="button" class="cma-launcher-btn" id="reportsMenuBtn" title="Alle rapportages">
            <span class="lnr lnr-menu"></span><span class="cma-launcher-btn__label">Alle rapporten</span>
        </button>
    </left>
</cma-toolbar>

<div class="cma-reports-menu" id="reportsMenu"<?= $repId > 0 ? ' hidden' : '' ?>>
<?php if (empty($groups)): ?>
    <div class="tools-empty">Geen rapportages beschikbaar.</div>
<?php else: foreach ($groups as $groupTitle => $items): ?>
    <section class="cma-launcher__group">
        <h3 class="cma-launcher__group-title"><span class="lnr lnr-chart-bars"></span> <?= Server::htmlEncode($groupTitle) ?></h3>
        <div class="cma-launcher__items">
        <?php foreach ($items as $it): ?>
            <a class="cma-launcher__item" href="reports.php?RepID=<?= $it['id'] ?>" data-repid="<?= $it['id'] ?>">
                <span class="cma-launcher__item-icon lnr lnr-file-empty"></span>
                <span class="cma-launcher__item-label"><?= Server::htmlEncode($it['title']) ?></span>
            </a>
        <?php endforeach; ?>
        </div>
    </section>
<?php endforeach; endif; ?>
</div>

<?php if (!empty($repId)): ?>
    <iframe name="R" id="reports-content" class="tools-content-area"
        src="<?= $repId > 0 ? 'reportdetails.php?RepID=' . $repId : 'about:blank' ?>"
        frameborder="0"<?= $repId > 0 ? '' : ' hidden' ?>></iframe>
<?php else: ?>
    <div class="tools-empty" id="toolsEmpty" hidden>Klik op <span class="cma-page__strong"> alle rapporten </span> om een rapport te kiezen.</div>
<?php endif; ?>

<script>
(function () {
    'use strict';
    var menu = document.getElementById('reportsMenu');
    var frame = document.getElementById('reports-content');
    var btn = document.getElementById('reportsMenuBtn');
    var currentRepId = <?= $repId > 0 ? (int) $repId : 'null' ?>;

    // Swap between the tile menu and the full-width report iframe client-side,
    // so picking a report (or returning to the menu) never reloads the shell.
    // history.replaceState keeps the URL deep-linkable (a reload/bookmark of
    // reports.php?RepID=x re-enters via the shell guard above).
    function showReport(id) {
        currentRepId = id;
        if (frame.getAttribute('src') !== 'reportdetails.php?RepID=' + encodeURIComponent(id)) {
            frame.src = 'reportdetails.php?RepID=' + encodeURIComponent(id);
        }
        frame.hidden = false;
        menu.hidden = true;
        if (window.history && history.replaceState) {
            history.replaceState(null, '', 'reports.php?RepID=' + encodeURIComponent(id));
        }
    }
    function showMenu() {
        menu.hidden = false;
        frame.hidden = true;
        if (window.history && history.replaceState) {
            history.replaceState(null, '', 'reports.php');
        }
    }

    menu.addEventListener('click', function (e) {
        var a = e.target.closest('.cma-launcher__item');
        if (!a) return;
        e.preventDefault();
        showReport(a.getAttribute('data-repid'));
    });
    // Toolbar button toggles: menu open -> back to the report (if one is loaded);
    // report showing -> open the menu. With no report chosen yet it's a no-op.
    if (btn) btn.addEventListener('click', function () {
        if (menu.hidden) {
            showMenu();
        } else if (currentRepId !== null) {
            showReport(currentRepId);
        }
    });
})();
</script>

<?php
echo '</div>'; // close .tools-page
