<?php
/**
 * Migration: Fix tblGroupRights schema
 *
 * This migration adds the missing columns to tblGroupRights:
 * - secObjectType: Type of object (10=menu, 20=report, 30=form)
 * - secAccessType: Access level (0=none, 10=read, 30=full)
 *
 * Note: The old columns (secCanRead, secCanUpdate, etc.) are kept for compatibility
 * but the new code uses secObjectType and secAccessType.
 *
 * This script is marked optional in migrations.json because the columns
 * are also added via addColumn changes. This script additionally migrates
 * existing data from old boolean columns to the new format.
 */

// Columns are added via addColumn changes in migrations.json.
// This optional script only migrates existing data values.
// If the database is fresh (no existing data), this is not needed.

echo "=== Migration: Fix tblGroupRights schema ===\n\n";
echo "Columns secObjectType and secAccessType are added via addColumn changes.\n";
echo "This optional script migrates existing data values.\n";

try {
    $conn = \App\Library\Database::getConnection('users');

    // Migrate existing data: convert old boolean columns to new access type
    echo "\nMigrating existing data to new column format...\n";

    // Update secAccessType based on old columns
    $updateSql = "
        UPDATE tblGroupRights SET
        secAccessType = IIF(secCanUpdate = True OR secCanInsert = True OR secCanDelete = True, 30,
                       IIF(secCanRead = True, 10, 0))
        WHERE secAccessType IS NULL OR secAccessType = 0
    ";
    $conn->exec($updateSql);
    echo "  Updated secAccessType values.\n";

    // Set secObjectType to 10 (menu) for all existing rows (default assumption)
    $updateTypeSql = "
        UPDATE tblGroupRights SET secObjectType = 10
        WHERE secObjectType IS NULL
    ";
    $conn->exec($updateTypeSql);
    echo "  Set default secObjectType values.\n";

    echo "\n=== Migration Complete ===\n";

} catch (Exception $e) {
    // Old columns (secCanRead, secCanUpdate, etc.) may not exist - that's OK
    echo "Overgeslagen: " . $e->getMessage() . "\n";
    echo "(Oude kolommen bestaan mogelijk niet meer - dit is normaal)\n";
    return true;
}
