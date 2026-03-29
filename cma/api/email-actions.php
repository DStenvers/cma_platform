<?php
/**
 * Email Log Actions API
 *
 * Handles resend/delete actions for email log records.
 * Admin-only access.
 */

use App\Library\Request;
use App\Library\Response;
use Cma\SecurityHelper;
use Cma\Services\EmailLogService;

require_once __DIR__ . '/../bootstrap.inc';

Response::noCache();
header('Content-Type: application/json; charset=utf-8');

// Admin-only access
if (!SecurityHelper::isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Geen toegang']);
    exit;
}

$action = Request::post('action', '');
$id = (int)Request::post('id', 0);

if ($id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Ongeldig ID']);
    exit;
}

switch ($action) {
    case 'resend':
        $result = EmailLogService::resend($id);
        echo json_encode($result);
        break;

    case 'delete':
        $success = EmailLogService::delete($id);
        echo json_encode(['success' => $success, 'error' => $success ? '' : 'Verwijderen mislukt']);
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Onbekende actie: ' . $action]);
        break;
}
