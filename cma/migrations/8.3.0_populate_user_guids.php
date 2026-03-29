<?php
/**
 * Migration 8.3.0: Populate userGUID for existing users
 *
 * Generates a unique GUID for each user that doesn't have one.
 */

use App\Library\Database;

// Get connection
$conn = Database::getConnection('users');
if (!$conn) {
    echo "Could not connect to users database\n";
    return false;
}

// Find users without GUID
$result = Database::fetchAll("SELECT ID FROM tblUsers WHERE userGUID IS NULL OR userGUID = ''", [], $conn);

if (empty($result)) {
    echo "All users already have GUIDs\n";
    return true;
}

$updated = 0;
foreach ($result as $row) {
    $userId = $row['ID'];
    $guid = generateGuid();

    $success = Database::query(
        "UPDATE tblUsers SET userGUID = ? WHERE ID = ?",
        [$guid, $userId],
        $conn
    );

    if ($success) {
        $updated++;
    }
}

echo "Updated $updated users with new GUIDs\n";
return true;

/**
 * Generate a UUID v4
 */
function generateGuid(): string {
    $data = random_bytes(16);

    // Set version to 0100 (UUID v4)
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    // Set bits 6-7 to 10
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}
