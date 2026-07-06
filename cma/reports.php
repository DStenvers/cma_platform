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
use Cma\SecurityHelper;

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

echo '<base href="/cma/">';
echo '<div class="tools-page">';
cma_script('webcomponents/cma-toolbar.js');
?>

<cma-toolbar variant="list" class="tools-toolbar">
    <left>
        <button type="button" class="cma-launcher-btn" id="reportsMenuBtn" aria-haspopup="dialog" title="Alle rapportages">
            <span class="lnr lnr-menu"></span><span class="cma-launcher-btn__label">Alle rapporten</span>
        </button>
    </left>
</cma-toolbar>

<!-- #reports is "either empty or a menu": the embedded launcher fills it with
     the searchable report menu; picking a report collapses the launcher and
     shows the report full-width in the same box. Both are driven by the shared
     <cma-launcher> component (nav-mode="iframe"), fed the reports catalog. -->
<div id="reports" class="launcher-host">
    <cma-launcher
        catalog-url="/cma/api/reports-catalog.php"
        nav-mode="iframe"
        target="#reports-content"
        search-placeholder="Zoek een rapport…"
        aria-label="Alle rapportages"
        empty-text="Geen rapportages beschikbaar."></cma-launcher>
    <iframe name="R" id="reports-content" class="tools-content-area"
        src="<?= $repId > 0 ? 'reportdetails.php?RepID=' . $repId : 'about:blank' ?>"
        frameborder="0"<?= $repId > 0 ? '' : ' hidden' ?>></iframe>
</div>

<script>
(function () {
    'use strict';
    var launcher = document.querySelector('#reports cma-launcher');
    var btn = document.getElementById('reportsMenuBtn');
    var hasReport = <?= $repId > 0 ? 'true' : 'false' ?>;

    // The custom element may not be upgraded yet when this inline script runs.
    function whenReady(cb) {
        var tries = 0;
        (function w() {
            if (launcher && typeof launcher.open === 'function') { cb(); return; }
            if (++tries < 40) { setTimeout(w, 25); }
        })();
    }

    // No report chosen: open the menu straight away (host shows the menu). With
    // one already selected, the iframe is showing — leave it, the button reopens
    // the menu on demand.
    if (!hasReport) {
        whenReady(function () { launcher.open(); });
    }

    // Toolbar button toggles the menu. Closing it reveals the report iframe when
    // one is loaded, or an empty host when none is.
    if (btn) btn.addEventListener('click', function () {
        whenReady(function () { launcher.toggle(); });
    });
})();
</script>

<?php
echo '</div>'; // close .tools-page
