<?php
/**
 * Migration 8.4.0: Set default userLevel for existing users
 *
 * Ensures all users have a userLevel. Users without a userLevel
 * are set to 0 (Gebruiker).
 */

use App\Library\Database;

// Get connection
$conn = Database::getConnection('users');
if (!$conn) {
    echo "Could not connect to users database\n";
    return false;
}

// Find users without userLevel
$result = Database::fetchAll(
    "SELECT ID, userFullName FROM tblUsers WHERE userLevel IS NULL OR userLevel = ''",
    [],
    $conn
);

if (empty($result)) {
    echo "All users already have a userLevel\n";
    return true;
}

$updated = 0;
foreach ($result as $row) {
    $userId = $row['ID'];
    $userName = $row['userFullName'];

    $success = Database::query(
        "UPDATE tblUsers SET userLevel = 0 WHERE ID = ?",
        [$userId],
        $conn
    );

    if ($success) {
        echo "  Set userLevel=0 (Gebruiker) for: $userName\n";
        $updated++;
    }
}

echo "\nUpdated $updated users with default userLevel (Gebruiker)\n";
return true;
