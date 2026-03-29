<?php
/**
 * API endpoint for changing user password
 */

use App\Library\Cookie;
use App\Library\Database;
use App\Library\Request;
use App\Library\SQL;
use Cma\SecurityHelper;

require_once __DIR__ . '/../bootstrap.inc';

header('Content-Type: application/json');

// Check if logged in
$userId = Cookie::get(SecurityHelper::COOKIE_USERID, '');
if (empty($userId)) {
    echo json_encode(['success' => false, 'message' => 'Niet ingelogd']);
    exit;
}

// Get POST data
$oldPwd = Request::post('old_password', '');
$newPwd = Request::post('new_password', '');

if (empty($oldPwd)) {
    echo json_encode(['success' => false, 'message' => 'Vul het oude wachtwoord in']);
    exit;
}

if (empty($newPwd)) {
    echo json_encode(['success' => false, 'message' => 'Vul het nieuwe wachtwoord in']);
    exit;
}

// Update password (only if old password matches)
// Use UPPER() for case-insensitive comparison (UCASE is Access-only)
$sql = 'UPDATE tblUsers SET userPassword = ' . SQL::String($newPwd) .
       ' WHERE ID = ' . (int)$userId . ' AND UPPER(userPassword) = ' . SQL::String(strtoupper($oldPwd));

if (Database::execute($sql, [], 'users')) {
    echo json_encode(['success' => true, 'message' => 'Wachtwoord is gewijzigd']);
} else {
    echo json_encode(['success' => false, 'message' => 'Wachtwoord kon niet worden gewijzigd (controleer het oude wachtwoord)']);
}
