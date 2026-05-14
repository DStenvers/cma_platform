<?php
/**
 * Migration 9.10.0: Create tblCMAMarketingUrl
 *
 * Ensures the marketing-URL redirect table exists in the data database.
 * On legacy installs the table existed under the name tblMarketingUrl
 * and was renamed by migration 2.3.0; on a fresh install the rename
 * is a no-op and the table never gets created at all, so the
 * marketingurl form errors with "kan tabel niet vinden".
 *
 * Idempotent: skips when the table is already present (under the
 * post-2.3.0 name). The legacy pre-rename name is intentionally not
 * checked here — if someone is on a legacy install they should run
 * migration 2.3.0 first.
 */

use App\Library\Database;

$basePath = defined('MIGRATION_RUNNING') ? dirname(__DIR__) : __DIR__;
if (strpos($basePath, 'migrations') !== false) {
    $basePath = dirname($basePath);
}
require_once $basePath . '/bootstrap.inc';

try {
    $conn = Database::getConnection('data');
    if ($conn === null) {
        echo "✗ Kan geen verbinding maken met database 'data'\n";
        if (defined('MIGRATION_RUNNING')) return false;
        exit(1);
    }

    $tableExists = false;
    try {
        $conn->query("SELECT TOP 1 ID FROM tblCMAMarketingUrl");
        $tableExists = true;
    } catch (\Exception $e) {
        // Table doesn't exist
    }

    if ($tableExists) {
        echo "✓ tblCMAMarketingUrl bestaat al\n";
        if (defined('MIGRATION_RUNNING')) return true;
        exit(0);
    }

    // Schema mirrors cma/assets/forms/definitions/marketingurl.json
    $sql = "CREATE TABLE tblCMAMarketingUrl (
        ID AUTOINCREMENT PRIMARY KEY,
        Dir VARCHAR(78),
        Page VARCHAR(78),
        Opmerking VARCHAR(255),
        DATESTAMP DATETIME
    )";

    $conn->exec($sql);

    try {
        $conn->exec("CREATE UNIQUE INDEX tblCMAMarketingUrl_dir ON tblCMAMarketingUrl (Dir)");
    } catch (\Exception $e) {
        echo "  ⚠ Index tblCMAMarketingUrl_dir niet aangemaakt: " . $e->getMessage() . "\n";
    }

    echo "✓ tblCMAMarketingUrl aangemaakt\n";
    if (defined('MIGRATION_RUNNING')) return true;
    exit(0);

} catch (\Exception $e) {
    echo "✗ Fout: " . $e->getMessage() . "\n";
    if (defined('MIGRATION_RUNNING')) return false;
    exit(1);
}
