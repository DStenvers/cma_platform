<?php
/**
 * 404 lists API — hack attempts and ignored 404s (App\Library\NotFoundLists).
 *
 *   GET  ?action=list                                  → {success, lijsten, labels}
 *   POST {action:"add",    lijst, patroon}             → {success, lijsten}
 *   POST {action:"remove", lijst, patroon}             → {success, lijsten}
 *   POST {action:"unblock", ip}                        → {success}  (HackBlocker)
 *
 * Used by the 404 log in the logreader (marking a miss from its detail window,
 * and the tabs "Patronen" and "Blokkades"). Administrators and up.
 */

use App\Library\HackBlocker;
use App\Library\NotFoundLists;
use App\Library\Request;
use App\Library\Response;
use Cma\SecurityHelper;

require_once __DIR__ . '/../bootstrap.inc';

Response::noCache();
header('Content-Type: application/json; charset=utf-8');

if (!SecurityHelper::isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Geen toegang']);
    exit;
}

$lists = NotFoundLists::forSite(dirname(__DIR__, 2));
$input = Request::server('REQUEST_METHOD') === 'POST'
    ? (json_decode((string) file_get_contents('php://input'), true) ?: [])
    : [];
$action = (string) ($input['action'] ?? Request::query('action', 'list'));

$antwoord = static function (bool $ok, string $fout = '') use ($lists): void {
    $uit = ['success' => $ok, 'lijsten' => $lists->all(), 'labels' => NotFoundLists::LABELS];
    if (!$ok) {
        $uit['error'] = $fout;
    }
    echo json_encode($uit, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
};

switch ($action) {
    case 'list':
        $antwoord(true);
        break;

    case 'add':
    case 'remove':
        if (Request::server('REQUEST_METHOD') !== 'POST') {
            http_response_code(405);
            $antwoord(false, 'Alleen POST');
            break;
        }
        $lijst = (string) ($input['lijst'] ?? '');
        $patroon = (string) ($input['patroon'] ?? '');
        if (!NotFoundLists::isList($lijst) || trim($patroon) === '') {
            http_response_code(400);
            $antwoord(false, 'Onbekende lijst of leeg patroon');
            break;
        }
        $ok = $action === 'add'
            ? $lists->add($lijst, $patroon, SecurityHelper::getCurrentUserName())
            : $lists->remove($lijst, $patroon);
        $antwoord($ok, $ok ? '' : ($action === 'add' ? 'Dit patroon staat al op de lijst, of de lijst kon niet worden opgeslagen' : 'Patroon niet gevonden'));
        break;

    case 'unblock':
        if (Request::server('REQUEST_METHOD') !== 'POST') {
            http_response_code(405);
            $antwoord(false, 'Alleen POST');
            break;
        }
        $ok = (new HackBlocker(dirname(__DIR__, 2)))->unblock((string) ($input['ip'] ?? ''));
        $antwoord($ok, $ok ? '' : 'Dit IP is niet (meer) geblokkeerd');
        break;

    default:
        http_response_code(400);
        $antwoord(false, 'Onbekende actie');
}
