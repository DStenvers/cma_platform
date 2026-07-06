<?php
/**
 * Reports Catalog API
 *
 * Returns the rights-checked reports catalog as JSON for <cma-launcher> running
 * in nav-mode="iframe" on reports.php — the reports counterpart of
 * tools-catalog.php. Same node shape the launcher consumes
 * ({type:folder,label,icon,children:[{type:item,label,href,url,icon}]}), so the
 * one generalized component drives both menus.
 *
 * Each item's `href` is the iframe src (reportdetails.php?RepID=X); `url` is the
 * clean deep-link the launcher writes to the address bar on selection.
 *
 * Auth: any logged-in user (reports are per-report rights-checked below, unlike
 * the admin-only tools catalog).
 */

use App\Library\Response;
use Cma\SecurityHelper;
use Cma\Services\ReportsService;

require_once __DIR__ . '/../bootstrap.inc';

Response::noCache();
header('Content-Type: application/json; charset=utf-8');

if (!SecurityHelper::isLoggedIn()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Geen toegang']);
    exit;
}

// Same data source + rights check as the reports page itself: one folder per
// module, holding only the reports this user may open.
$groups = [];
foreach (ReportsService::getGroupedByModule(true) as $moduleName => $reports) {
    $items = [];
    foreach ($reports as $report) {
        $rid = (int) ($report['id'] ?? 0);
        if ($rid > 0 && SecurityHelper::checkRights(SecurityHelper::TYPE_REPORT, $rid)) {
            $items[] = [
                'type'  => 'item',
                'label' => (string) ($report['title'] ?? ''),
                'href'  => 'reportdetails.php?RepID=' . $rid,
                'url'   => 'reports.php?RepID=' . $rid,
                'icon'  => 'lnr-file-empty',
            ];
        }
    }
    if (!empty($items)) {
        $groups[] = [
            'type'     => 'folder',
            'label'    => ucwords(strtolower((string) $moduleName)),
            'icon'     => 'lnr-chart-bars',
            'children' => $items,
        ];
    }
}

echo json_encode(['success' => true, 'groups' => $groups]);
