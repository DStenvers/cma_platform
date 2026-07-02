<?php
/**
 * Migration 9.15.0: Create api_call_log
 *
 * (Renumbered from 9.11.0 — that version was never registered in the
 * migrations.json manifest, so on sites already past 9.13.0 the high-water-mark
 * runner never saw it as pending. This version sits above the target so it
 * becomes pending everywhere. Idempotent, so re-running where the table already
 * exists is a no-op.)
 *
 * Every outbound LLM / vision / paid-API call gets a row so the CMA
 * can inspect cost, patterns and errors. Originally lived as a
 * project-side migration (mijntoprecepten 029_api_call_log) — moved
 * upstream here because the CMA monitoring form queries [api_call_log]
 * directly and fresh installs without the project migration errored
 * with "cannot find the input table or query 'api_call_log'".
 *
 * Table name stays snake_case (not the usual tbl-prefix convention)
 * so existing CMA form definitions that reference [api_call_log]
 * keep working without a rename.
 *
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

    // Force a fetch: the PDO ODBC Access driver often surfaces "table does not
    // exist" only at fetch time, so a bare query()+assume-exists false-positives
    // on a missing table. Treat a non-throwing false return as missing too.
    $tableExists = false;
    try {
        $stmt = $conn->query("SELECT TOP 1 [id] FROM [api_call_log]");
        if ($stmt !== false) {
            $stmt->fetchColumn();   // forces the driver to actually touch the table
            $tableExists = true;
        }
    } catch (\Exception $e) {
        // Table doesn't exist — the fetch (or query) surfaced it here.
    }

    if ($tableExists) {
        echo "✓ api_call_log bestaat al\n";
        if (defined('MIGRATION_RUNNING')) return true;
        exit(0);
    }

    // Schema mirrors the original project-side migration so existing
    // consumers (writers + CMA monitoring form) keep working unchanged.
    $sql = "CREATE TABLE [api_call_log] (
        [id]             AUTOINCREMENT PRIMARY KEY,
        [user_id]        LONG,
        [called_from]    VARCHAR(60),
        [provider]       VARCHAR(20) NOT NULL,
        [model]          VARCHAR(80),
        [endpoint]       MEMO,
        [request_chars]  LONG,
        [response_chars] LONG,
        [http_status]    LONG,
        [latency_ms]     LONG,
        [ok]             YESNO NOT NULL,
        [error_message]  MEMO,
        [recipe_id]      LONG,
        [called_at]      DATETIME NOT NULL
    )";

    $conn->exec($sql);

    try {
        $conn->exec("CREATE INDEX [ix_apicall_called_at] ON [api_call_log] ([called_at])");
    } catch (\Exception $e) {
        echo "  ⚠ Index ix_apicall_called_at niet aangemaakt: " . $e->getMessage() . "\n";
    }
    try {
        $conn->exec("CREATE INDEX [ix_apicall_provider] ON [api_call_log] ([provider], [called_at])");
    } catch (\Exception $e) {
        echo "  ⚠ Index ix_apicall_provider niet aangemaakt: " . $e->getMessage() . "\n";
    }
    try {
        $conn->exec("CREATE INDEX [ix_apicall_user] ON [api_call_log] ([user_id], [called_at])");
    } catch (\Exception $e) {
        echo "  ⚠ Index ix_apicall_user niet aangemaakt: " . $e->getMessage() . "\n";
    }

    echo "✓ api_call_log aangemaakt\n";
    if (defined('MIGRATION_RUNNING')) return true;
    exit(0);

} catch (\Exception $e) {
    echo "✗ Fout: " . $e->getMessage() . "\n";
    if (defined('MIGRATION_RUNNING')) return false;
    exit(1);
}
