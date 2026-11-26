<?php
/**
 * Migration 9.26.0: inloggen met een e-mailcode als de SSO down is (Cma\Services\LoginCodeService)
 *
 * Voegt aan tblUsers toe: login_code_hash, login_code_verstuurd, login_code_gebruikt, login_code_fout.
 */

use App\Library\Database;

$conn = Database::getConnection('users');
if (!$conn) {
    return ['success' => false, 'error' => 'Kan geen verbinding maken met users database'];
}

$kolommen = [
    ['login_code_hash', 'VARCHAR(64)', null],
    ['login_code_verstuurd', 'DATETIME', null],
    ['login_code_gebruikt', 'INTEGER', '0'],
    ['login_code_fout', 'INTEGER', '0'],
];
$messages = [];
foreach ($kolommen as [$naam, $type, $default]) {
    $result = Database::addColumnPDO($conn, 'tblUsers', $naam, $type, $default);
    if (!$result['success']) {
        return ['success' => false, 'error' => 'Kan ' . $naam . ' niet toevoegen: ' . ($result['error'] ?? '')];
    }
    $messages[] = $result['message'] ?? 'Kolom ' . $naam . ' toegevoegd';
}
return ['success' => true, 'message' => implode('; ', $messages)];
