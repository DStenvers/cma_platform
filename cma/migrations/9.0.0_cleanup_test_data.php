<?php
/**
 * Migration 9.0.0: Cleanup test data and restore admin user
 *
 * Fixes admin user corrupted by Cypress inline-edit tests,
 * removes orphaned test users/groups/members/rights.
 */

use App\Library\Database;

$conn = Database::getConnection('users');
if (!$conn) {
    echo "Could not connect to users database\n";
    return false;
}

// 1. Fix admin user (userLogin cleared to NULL by inline-edit test on row 0)
$adminRows = Database::fetchAll(
    "SELECT ID, userFullName, userLogin FROM tblUsers WHERE userPassword = '_rino!' AND (userLogin IS NULL OR userLogin = '')",
    [],
    $conn
);

$adminFixed = 0;
foreach ($adminRows as $row) {
    $success = Database::query(
        "UPDATE tblUsers SET userLogin = 'DiederikStenvers' WHERE ID = ?",
        [$row['ID']],
        $conn
    );
    if ($success) {
        echo "  Fixed admin user ID={$row['ID']}: set userLogin='DiederikStenvers'\n";
        $adminFixed++;
    }
}
echo "Fixed $adminFixed admin user(s)\n\n";

// 2. Delete orphaned test users matching known test patterns
$testPatterns = [
    'delete_api_%',
    'delete_detail_%',
    'delete_ctx_%',
    'sectest_%',
    'testuser_%',
    'cytest_%',
    'deldetail_%',
    'verify_%',
    'update_%',
    'pwdtest_%',
    'inlinetest_%',
];
$exactMatches = ['dd', 'ddd', 'test'];

$deletedUsers = 0;

foreach ($testPatterns as $pattern) {
    $rows = Database::fetchAll(
        "SELECT ID, userLogin FROM tblUsers WHERE userLogin LIKE ?",
        [$pattern],
        $conn
    );
    foreach ($rows as $row) {
        Database::query("DELETE FROM tblUsers WHERE ID = ?", [$row['ID']], $conn);
        echo "  Deleted test user: {$row['userLogin']} (ID={$row['ID']})\n";
        $deletedUsers++;
    }
}

foreach ($exactMatches as $login) {
    $rows = Database::fetchAll(
        "SELECT ID, userLogin FROM tblUsers WHERE userLogin = ?",
        [$login],
        $conn
    );
    foreach ($rows as $row) {
        Database::query("DELETE FROM tblUsers WHERE ID = ?", [$row['ID']], $conn);
        echo "  Deleted test user: {$row['userLogin']} (ID={$row['ID']})\n";
        $deletedUsers++;
    }
}

echo "Deleted $deletedUsers test user(s)\n\n";

// 3. Delete orphaned tblGroupMembers where fkUser not in tblUsers
$orphanedMembers = Database::fetchAll(
    "SELECT gm.ID FROM tblGroupMembers gm LEFT JOIN tblUsers u ON gm.fkUser = u.ID WHERE u.ID IS NULL",
    [],
    $conn
);
$deletedMembers = 0;
foreach ($orphanedMembers as $row) {
    Database::query("DELETE FROM tblGroupMembers WHERE ID = ?", [$row['ID']], $conn);
    $deletedMembers++;
}
echo "Deleted $deletedMembers orphaned group member(s) (missing user)\n";

// 4. Delete orphaned tblGroupMembers where fkGroup not in tblGroups
$orphanedMembers2 = Database::fetchAll(
    "SELECT gm.ID FROM tblGroupMembers gm LEFT JOIN tblGroups g ON gm.fkGroup = g.ID WHERE g.ID IS NULL",
    [],
    $conn
);
$deletedMembers2 = 0;
foreach ($orphanedMembers2 as $row) {
    Database::query("DELETE FROM tblGroupMembers WHERE ID = ?", [$row['ID']], $conn);
    $deletedMembers2++;
}
echo "Deleted $deletedMembers2 orphaned group member(s) (missing group)\n";

// 5. Delete orphaned tblGroupRights where fkGroup not in tblGroups
$orphanedRights = Database::fetchAll(
    "SELECT gr.ID FROM tblGroupRights gr LEFT JOIN tblGroups g ON gr.fkGroup = g.ID WHERE g.ID IS NULL",
    [],
    $conn
);
$deletedRights = 0;
foreach ($orphanedRights as $row) {
    Database::query("DELETE FROM tblGroupRights WHERE ID = ?", [$row['ID']], $conn);
    $deletedRights++;
}
echo "Deleted $deletedRights orphaned group right(s)\n";

// 6. Delete orphaned tblNotifications where fkUserID not in tblUsers
try {
    $orphanedNotif = Database::fetchAll(
        "SELECT n.ID FROM tblNotifications n LEFT JOIN tblUsers u ON n.fkUserID = u.ID WHERE u.ID IS NULL",
        [],
        $conn
    );
    $deletedNotif = 0;
    foreach ($orphanedNotif as $row) {
        Database::query("DELETE FROM tblNotifications WHERE ID = ?", [$row['ID']], $conn);
        $deletedNotif++;
    }
    echo "Deleted $deletedNotif orphaned notification(s)\n";
} catch (\Exception $e) {
    echo "Skipped tblNotifications cleanup (table may not exist)\n";
}

// 7. Delete orphaned tblUserDataNotifications where fkUser not in tblUsers
try {
    $orphanedDataNotif = Database::fetchAll(
        "SELECT n.ID FROM tblUserDataNotifications n LEFT JOIN tblUsers u ON n.fkUser = u.ID WHERE u.ID IS NULL",
        [],
        $conn
    );
    $deletedDataNotif = 0;
    foreach ($orphanedDataNotif as $row) {
        Database::query("DELETE FROM tblUserDataNotifications WHERE ID = ?", [$row['ID']], $conn);
        $deletedDataNotif++;
    }
    echo "Deleted $deletedDataNotif orphaned data notification(s)\n";
} catch (\Exception $e) {
    echo "Skipped tblUserDataNotifications cleanup (table may not exist)\n";
}

echo "\nCleanup complete.\n";
return true;
