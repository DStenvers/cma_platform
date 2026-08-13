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

    if (Database::tableExistsPDO($conn, 'tblCMAMonitoring')) {
        echo "✓ tblCMAMonitoring bestaat al\n";
        if (defined('MIGRATION_RUNNING')) return true;
        exit(0);
    }

    // Written in the platform's Access dialect; Database::executeDdl() turns it
    // into what the connected backend accepts (SQLite spells the counter column
    // "INTEGER PRIMARY KEY AUTOINCREMENT", and has no MEMO or LONG at all).
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

    Database::executeDdl($conn, $sql);

    // Index on Formid. Optional: without it the monitoring list is slower, not
    // wrong, so a driver that refuses the index must not fail the migration.
    try {
        Database::executeDdl($conn, "CREATE INDEX tblCMAMonitoring_formid ON tblCMAMonitoring (Formid)");
    } catch (\Exception $e) {
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
