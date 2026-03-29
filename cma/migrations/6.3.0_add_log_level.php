<?php
/**
 * Migration: Populate LogLevel column in tblCMAMonitoring
 *
 * This script populates the LogLevel column based on Actie text patterns.
 * The column itself is created by migrations.json (addColumn).
 *
 * LogLevel values: 'info', 'warning', 'error'
 */

use App\Library\Database;

require_once __DIR__ . '/../bootstrap.inc';

// Check if running as migration
$isMigration = defined('MIGRATION_RUNNING') || \App\Library\Request::hasQuery('migration');

if (!$isMigration) {
    // Show confirmation page when run directly
    echo '<!DOCTYPE html><html><head><title>Populate LogLevel</title></head><body>';
    echo '<h1>Populate LogLevel in tblCMAMonitoring</h1>';
    echo '<p>This will populate LogLevel values based on Actie text.</p>';
    echo '<p><a href="?migration=1">Run migration</a></p>';
    echo '</body></html>';
    exit;
}

// Get data connection
$conn = Database::getConnection('data');
if ($conn === null) {
    echo '✗ Kan geen verbinding maken met de data database';
    exit(1);
}

// Check if LogLevel column exists (should be created by migrations.json addColumn)
try {
    $stmt = $conn->query("SELECT TOP 1 LogLevel FROM tblCMAMonitoring");
} catch (\Exception $e) {
    echo '✗ LogLevel kolom bestaat niet - voer eerst de addColumn migratie uit';
    exit(1);
}

// Populate LogLevel based on Actie patterns
$errorPatterns = ['fail', 'fout', 'error', 'mislukt', 'denied', 'geweigerd'];
$warningPatterns = ['warning', 'waarschuwing'];

// Update error records
$errorWhere = [];
foreach ($errorPatterns as $pattern) {
    $errorWhere[] = "Actie LIKE '%$pattern%'";
}
$errorSql = "UPDATE tblCMAMonitoring SET LogLevel = 'error' WHERE LogLevel IS NULL AND (" . implode(' OR ', $errorWhere) . ")";

try {
    $conn->exec($errorSql);
    echo "✓ Error records bijgewerkt\n";
} catch (\Exception $e) {
    echo "✗ Fout bij bijwerken error records: " . $e->getMessage() . "\n";
}

// Update warning records
$warnWhere = [];
foreach ($warningPatterns as $pattern) {
    $warnWhere[] = "Actie LIKE '%$pattern%'";
}
$warnSql = "UPDATE tblCMAMonitoring SET LogLevel = 'warning' WHERE LogLevel IS NULL AND (" . implode(' OR ', $warnWhere) . ")";

try {
    $conn->exec($warnSql);
    echo "✓ Warning records bijgewerkt\n";
} catch (\Exception $e) {
    echo "✗ Fout bij bijwerken warning records: " . $e->getMessage() . "\n";
}

// Update remaining records as info
$infoSql = "UPDATE tblCMAMonitoring SET LogLevel = 'info' WHERE LogLevel IS NULL";

try {
    $conn->exec($infoSql);
    echo "✓ Info records bijgewerkt\n";
} catch (\Exception $e) {
    echo "✗ Fout bij bijwerken info records: " . $e->getMessage() . "\n";
}

// Count results
try {
    $countSql = "SELECT LogLevel, Count(*) AS cnt FROM tblCMAMonitoring GROUP BY LogLevel";
    $stmt = $conn->query($countSql);
    $counts = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    echo "\nResultaat:\n";
    foreach ($counts as $row) {
        echo "  " . ($row['LogLevel'] ?: '(null)') . ": " . $row['cnt'] . " records\n";
    }
} catch (\Exception $e) {
    // Ignore count errors
}

echo "\n✓ Migratie voltooid";
return true;
