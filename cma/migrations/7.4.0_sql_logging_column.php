<?php
/**
 * Migration 7.4.0: SQL drempelwaarde vervangen door SQL logging schakelaar
 *
 * - Voegt prefSqlLogging kolom toe aan tblUsers (INTEGER, default 0)
 * - Verwijdert prefSqlThreshold kolom uit tblUsers
 */

use App\Library\Database;

$conn = Database::getConnection('users');
if (!$conn) {
    return ['success' => false, 'error' => 'Kan geen verbinding maken met users database'];
}

$messages = [];

try {
    // Add prefSqlLogging column (addColumnPDO handles "already exists" gracefully)
    $result = Database::addColumnPDO($conn, 'tblUsers', 'prefSqlLogging', 'INTEGER', '0');
    if ($result['success']) {
        $messages[] = $result['message'] ?? 'Kolom prefSqlLogging toegevoegd';
    } else {
        return ['success' => false, 'error' => 'Kan prefSqlLogging niet toevoegen: ' . ($result['error'] ?? '')];
    }

    // Drop prefSqlThreshold column (dropColumnPDO handles "not exists" gracefully)
    $result = Database::dropColumnPDO($conn, 'tblUsers', 'prefSqlThreshold');
    if ($result['success']) {
        $messages[] = $result['message'] ?? 'Kolom prefSqlThreshold verwijderd';
    } else {
        // Not critical - column just won't be used anymore
        $messages[] = 'Kolom prefSqlThreshold niet verwijderd: ' . ($result['error'] ?? '');
    }

    return [
        'success' => true,
        'message' => implode('; ', $messages)
    ];

} catch (\PDOException $e) {
    return [
        'success' => false,
        'error' => 'Database fout: ' . $e->getMessage()
    ];
}
