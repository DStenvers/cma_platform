<?php
/**
 * Migration 9.8.0: Create tblEmailLog
 *
 * Creates the email log table in the data database for tracking all sent emails.
 * Records are automatically cleaned up after 30 days.
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
        $conn->query("SELECT TOP 1 ID FROM tblEmailLog");
        $tableExists = true;
    } catch (\Exception $e) {
        // Table doesn't exist
    }

    if ($tableExists) {
        echo "✓ tblEmailLog bestaat al\n";
        if (defined('MIGRATION_RUNNING')) return true;
        exit(0);
    }

    // Create the table (Access syntax)
    $sql = "CREATE TABLE tblEmailLog (
        ID AUTOINCREMENT PRIMARY KEY,
        datestamp DATETIME,
        mail_to MEMO,
        mail_cc MEMO,
        mail_bcc MEMO,
        mail_from VARCHAR(255),
        mail_from_name VARCHAR(255),
        mail_subject VARCHAR(255),
        mail_body MEMO,
        mail_attachments MEMO,
        mail_status VARCHAR(20),
        mail_error MEMO,
        mail_environment VARCHAR(10),
        mail_user VARCHAR(100)
    )";

    $conn->exec($sql);

    // Create index on datestamp for cleanup queries and sorting
    try {
        $conn->exec("CREATE INDEX idx_EmailLog_datestamp ON tblEmailLog (datestamp)");
    } catch (\Exception $e) {
        echo "  ⚠ Index idx_EmailLog_datestamp niet aangemaakt: " . $e->getMessage() . "\n";
    }

    // Create index on mail_status for filtering
    try {
        $conn->exec("CREATE INDEX idx_EmailLog_status ON tblEmailLog (mail_status)");
    } catch (\Exception $e) {
        echo "  ⚠ Index idx_EmailLog_status niet aangemaakt: " . $e->getMessage() . "\n";
    }

    echo "✓ tblEmailLog aangemaakt\n";
    if (defined('MIGRATION_RUNNING')) return true;
    exit(0);

} catch (\Exception $e) {
    echo "✗ Fout: " . $e->getMessage() . "\n";
    if (defined('MIGRATION_RUNNING')) return false;
    exit(1);
}
