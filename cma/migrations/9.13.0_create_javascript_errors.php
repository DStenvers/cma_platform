<?php
/**
 * Migration 9.13.0: Create tblCMAJavascriptErrors
 *
 * Client-side JavaScript errors caught by error-handler.js are POSTed to
 * form_api.php (action=logJsError), which INSERTs them into this table in the
 * 'data' database. dashboard_stats.php and the logreader surface them.
 *
 * History: an earlier migration (2.2.0) tried to create this table but
 * targeted the deprecated 'rep' database with invalid Access DDL (DEFAULT
 * clauses + VARCHAR > 255), so it never ran. That migration was removed; this
 * one creates the table where the code actually writes — 'data' — with
 * Access-compatible DDL (no DEFAULT clauses; MEMO for anything over 255 chars).
 *
 * Column widths mirror the substr() caps in form_api.php's logJsError handler.
 * Idempotent: existence-probed CREATE.
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
        $conn->query("SELECT TOP 1 [id] FROM [tblCMAJavascriptErrors]");
        $tableExists = true;
    } catch (\Exception $e) {
        // Table doesn't exist
    }

    if ($tableExists) {
        echo "✓ tblCMAJavascriptErrors bestaat al\n";
        if (defined('MIGRATION_RUNNING')) return true;
        exit(0);
    }

    // Access DDL: no DEFAULT clauses (Jet/ACE rejects them); Text caps at 255,
    // so anything longer is MEMO. datestamp is filled with Now() on INSERT.
    $sql = "CREATE TABLE [tblCMAJavascriptErrors] (
        [id]            AUTOINCREMENT PRIMARY KEY,
        [datestamp]     DATETIME,
        [error_message] MEMO,
        [error_url]     MEMO,
        [error_line]    LONG,
        [error_column]  LONG,
        [error_stack]   MEMO,
        [user_login]    VARCHAR(100),
        [user_agent]    MEMO,
        [page_url]      MEMO,
        [extra_info]    MEMO
    )";

    $conn->exec($sql);

    try {
        $conn->exec("CREATE INDEX [ix_jserror_datestamp] ON [tblCMAJavascriptErrors] ([datestamp])");
    } catch (\Exception $e) {
        echo "  ⚠ Index ix_jserror_datestamp niet aangemaakt: " . $e->getMessage() . "\n";
    }

    echo "✓ tblCMAJavascriptErrors aangemaakt\n";
    if (defined('MIGRATION_RUNNING')) return true;
    exit(0);

} catch (\Exception $e) {
    echo "✗ Fout: " . $e->getMessage() . "\n";
    if (defined('MIGRATION_RUNNING')) return false;
    exit(1);
}
