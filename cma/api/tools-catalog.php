<?php
/**
 * Tools Catalog API
 *
 * Returns the admin tools/forms catalog as JSON for the header launcher
 * (cma-launcher). Same data as the tools.php two-pane tree — single source of
 * truth is buildToolsTreeData() in tools_catalog.inc. Admin-only.
 */

use App\Library\Response;
use Cma\SecurityHelper;

require_once __DIR__ . '/../bootstrap.inc';
require_once __DIR__ . '/../tools_catalog.inc';

Response::noCache();
header('Content-Type: application/json; charset=utf-8');

if (!SecurityHelper::isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Geen toegang']);
    exit;
}

echo json_encode([
    'success' => true,
    'groups'  => buildToolsTreeData(SecurityHelper::isDeveloper()),
]);
