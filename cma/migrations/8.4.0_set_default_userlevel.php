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

// Ensure userLevel column exists (addColumnPDO is driver-aware — raw
// "ADD COLUMN" is invalid on SQL Server — and idempotent).
$add = Database::addColumnPDO($conn, 'tblUsers', 'userLevel', 'INTEGER');
if (!empty($add['sql'])) {
    echo "Added userLevel column\n";
}

// Users without a level get 0 (Gebruiker). userLevel is an INTEGER column: comparing it
// with '' fails on Access ("Data type mismatch in criteria expression", -3030), so only
// NULL counts as "no level". One statement without parameters: the ODBC driver binds a
// parameter as text, and Access refuses that against the numeric ID with the same -3030.
$updated = Database::executeOn($conn, "UPDATE tblUsers SET userLevel = 0 WHERE userLevel IS NULL");
echo $updated > 0
    ? "Updated $updated users with default userLevel (Gebruiker)\n"
    : "All users already have a userLevel\n";
return true;
