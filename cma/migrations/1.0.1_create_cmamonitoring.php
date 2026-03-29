<?php
/**
 * Migration 1.0.1: Create tblCMAMonitoring
 *
 * Creates the CMA monitoring/audit log table in the data database
 * if it doesn't already exist.
 */

use App\Library\Database;

// Fix path when running as migration
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

    // Check if table already exists
    $tableExists = false;
    try {
        $conn->query("SELECT TOP 1 ID FROM tblCMAMonitoring");
        $tableExists = true;
    } catch (\Exception $e) {
        // Table doesn't exist
    }

    if ($tableExists) {
        echo "✓ tblCMAMonitoring bestaat al\n";
        if (defined('MIGRATION_RUNNING')) return true;
        exit(0);
    }

    // Create the table (Access syntax)
    $sql = "CREATE TABLE tblCMAMonitoring (
        ID AUTOINCREMENT PRIMARY KEY,
        datestamp DATETIME,
        Username VARCHAR(78),
        Formname VARCHAR(78),
        Formid INTEGER,
        RecordID LONG,
        Actie VARCHAR(78),
        Notificatie MEMO
    )";

    $conn->exec($sql);

    // Create index on Formid (will be replaced by Form in migration 5.6.0+)
    try {
        $conn->exec("CREATE INDEX tblCMAMonitoring_formid ON tblCMAMonitoring (Formid)");
    } catch (\Exception $e) {
        // Index creation is optional
        echo "  ⚠ Index tblCMAMonitoring_formid niet aangemaakt: " . $e->getMessage() . "\n";
    }

    echo "✓ tblCMAMonitoring aangemaakt\n";
    if (defined('MIGRATION_RUNNING')) return true;
    exit(0);

} catch (\Exception $e) {
    echo "✗ Fout: " . $e->getMessage() . "\n";
    if (defined('MIGRATION_RUNNING')) return false;
    exit(1);
}
