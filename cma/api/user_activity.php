<?php
/**
 * User Activity API
 *
 * Returns the current user's recent activity from tblCMAMonitoring.
 * This is user-specific (not admin-only) - each user sees their own activity.
 */

use App\Library\Cookie;
use App\Library\Database;
use App\Library\Request;
use Cma\SecurityHelper;
use Cma\Services\Logger;

require_once __DIR__ . '/../bootstrap.inc';

header('Content-Type: application/json');

// Get current user
$userId = Cookie::get(SecurityHelper::COOKIE_USERID, '');
$username = SecurityHelper::getCurrentUserName();

if (empty($userId) || !SecurityHelper::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Niet ingelogd']);
    exit;
}

$limit = min(Request::queryInt('limit', 10), 20);

try {
    // Build form title cache once (avoids N+1 query loading form defs in loop)
    static $formTitleCache = null;
    if ($formTitleCache === null) {
        $formTitleCache = [];
        $definitionsDir = __DIR__ . '/../assets/forms/definitions/';
        if (is_dir($definitionsDir)) {
            $files = glob($definitionsDir . '*.json');
            foreach ($files as $file) {
                $formName = basename($file, '.json');
                $formJson = @file_get_contents($file);
                $formDef = $formJson ? @json_decode($formJson, true) : null;
                if ($formDef && !empty($formDef['title'])) {
                    $formTitleCache[$formName] = $formDef['title'];
                }
            }
        }
    }

    $conn = Database::getConnection('data');

    // Get user's recent activity (last 10-20 items they viewed/edited)
    $usernameEscaped = str_replace("'", "''", $username);

    // Use Form (handle) preferentially, fallback to Formname for legacy records
    $sql = "SELECT TOP $limit
                ID,
                Format(datestamp, 'dd-mm hh:nn') AS timestamp,
                Actie,
                IIf(Form Is Null Or Form='', Formname, Form) AS FormHandle,
                RecordID
            FROM tblCMAMonitoring
            WHERE Username = '$usernameEscaped'
              AND (Actie = 'view' OR Actie = 'edit' OR Actie = 'add' OR Actie = 'wijzig' OR Actie = 'delete')
            ORDER BY datestamp DESC";

    $entries = [];
    $rs = Database::openRS($sql, $conn);

    if ($rs) {
        while (!$rs->EOF) {
            $formHandle = $rs->fields['FormHandle'] ?: '-';

            // Get form title from cache (built once at request start)
            $formTitle = $formTitleCache[$formHandle] ?? $formHandle;

            $entries[] = [
                'id' => (int)$rs->fields['ID'],
                'time' => $rs->fields['timestamp'],
                'action' => $rs->fields['Actie'] ?: '-',
                'form' => $formHandle,
                'formTitle' => $formTitle,
                'record' => $rs->fields['RecordID'] ? (int)$rs->fields['RecordID'] : null,
            ];
            $rs->MoveNext();
        }
    }

    echo json_encode([
        'success' => true,
        'entries' => $entries,
        'count' => count($entries),
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    Logger::error('API user_activity error', [
        'message' => $e->getMessage(),
        'userId' => $userId ?? null,
        'username' => $username ?? null,
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
