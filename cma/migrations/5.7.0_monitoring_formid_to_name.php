<?php
/**
 * Migration: Translate tblCMAMonitoring.Formid to Form (JSON form name)
 *
 * This script:
 * 1. Reads all records in tblCMAMonitoring that have a Formid but no Form value
 * 2. Uses JsonFormLoader to look up the JSON form name for each Formid
 * 3. Updates the Form field with the corresponding form name
 *
 * After this migration, the Formid field can be deprecated in favor of Form.
 */

use App\Library\Database;
use Cma\JsonFormLoader;

require_once __DIR__ . '/../bootstrap.inc';

// Check if running as migration
$isMigration = defined('MIGRATION_RUNNING') || \App\Library\Request::hasQuery('migration');

if (!$isMigration) {
    // Show confirmation page when run directly
    echo '<!DOCTYPE html><html><head><title>Migrate Monitoring FormID to Form Name</title></head><body>';
    echo '<h1>Migrate tblCMAMonitoring FormID to Form Name</h1>';
    echo '<p>This will update all monitoring records to use the Form (JSON form name) field instead of Formid.</p>';
    echo '<p><a href="?migration=1">Run migration</a></p>';
    echo '</body></html>';
    exit;
}

// Get data connection
try {
    $conn = Database::getConnection('data');
    if ($conn === null) {
        echo '✗ Kan geen verbinding maken met de data database';
        if (defined('MIGRATION_RUNNING')) {
            return false;
        }
        exit(1);
    }
} catch (\Exception $e) {
    echo '✗ Kan geen verbinding maken met de data database: ' . $e->getMessage();
    if (defined('MIGRATION_RUNNING')) {
        return false;
    }
    exit(1);
}

// Check if Form column exists
$hasFormColumn = false;
try {
    $stmt = $conn->query("SELECT TOP 1 Form FROM tblCMAMonitoring");
    $hasFormColumn = true;
} catch (\Exception $e) {
    // Column doesn't exist yet
    $hasFormColumn = false;
}

if (!$hasFormColumn) {
    echo '✗ De Form kolom bestaat nog niet in tblCMAMonitoring. Voer eerst de addColumn migratie uit.';
    if (defined('MIGRATION_RUNNING')) {
        return false;
    }
    exit(1);
}

// Check if Formid column exists
$hasFormidColumn = false;
try {
    $stmt = $conn->query("SELECT TOP 1 Formid FROM tblCMAMonitoring");
    $hasFormidColumn = true;
} catch (\Exception $e) {
    // Column doesn't exist
    $hasFormidColumn = false;
}

if (!$hasFormidColumn) {
    echo '✓ De Formid kolom bestaat niet in tblCMAMonitoring. Migratie niet nodig.';
    if (defined('MIGRATION_RUNNING')) {
        return true;
    }
    exit(0);
}

// Get all records with Formid but empty Form
$sql = "SELECT ID, Formid FROM tblCMAMonitoring WHERE Form IS NULL OR Form = ''";
$stmt = $conn->query($sql);
$records = $stmt->fetchAll(\PDO::FETCH_ASSOC);

$count = count($records);
$updated = 0;
$skipped = 0;
$errors = 0;

// Build a cache of formId -> formName mappings
$formIdToName = [];

foreach ($records as $record) {
    $id = $record['ID'];
    $formId = (int)$record['Formid'];

    // Skip if no Formid
    if ($formId <= 0) {
        $skipped++;
        continue;
    }

    // Look up form name from cache or JsonFormLoader
    if (!isset($formIdToName[$formId])) {
        $formName = JsonFormLoader::getFormNameBySourceId($formId);
        $formIdToName[$formId] = $formName;
    }

    $formName = $formIdToName[$formId];

    // Skip if no form name found (form might have been deleted)
    if ($formName === null || $formName === '') {
        $skipped++;
        continue;
    }

    // Update the record
    try {
        $updateSql = "UPDATE tblCMAMonitoring SET Form = ? WHERE ID = ?";
        $updateStmt = $conn->prepare($updateSql);
        $updateStmt->execute([$formName, $id]);
        $updated++;
    } catch (\Exception $e) {
        $errors++;
        if (!$isMigration) {
            echo "Fout bij record $id: " . $e->getMessage() . "\n";
        }
    }
}

// Output results
$msg = "✓ Migratie voltooid: $updated van $count records bijgewerkt";
if ($skipped > 0) {
    $msg .= ", $skipped overgeslagen (geen formId of form niet gevonden)";
}
if ($errors > 0) {
    $msg .= ", $errors fouten";
}

echo $msg;

// Show summary of form mappings found
if (!$isMigration && !empty($formIdToName)) {
    echo "\n\nGevonden form mappings:\n";
    foreach ($formIdToName as $fid => $fname) {
        echo "  FormID $fid -> " . ($fname ?: '(niet gevonden)') . "\n";
    }
}

return true;
