<?php
require_once __DIR__ . '/../../_bootstrap.php';
use App\Library\Database;
use App\Library\Application;

// Check available connections
echo "Checking database connections:\n";
echo "conn_data: " . Application::get('conn_data', 'not set') . "\n";
echo "conn_rep: " . Application::get('conn_rep', 'not set') . "\n";

// Try with rep connection name instead of conn_rep
$conn = 'rep';

// Check table structure
echo "\nTable structure:\n";
$sql = "SELECT TOP 1 * FROM tblSubForms";
$rs = Database::openRS($sql, $conn);
if ($rs && !$rs->EOF) {
    foreach ($rs->fields as $key => $val) {
        if (!is_numeric($key)) {
            echo "  $key\n";
        }
    }
} else {
    echo "Query failed: " . Database::getLastError() . "\n";
}

// Find subforms for form 69 (opleidingen_deelnemers)
echo "\nAll subforms in tblSubForms for fkForm=69:\n";
$sql = "SELECT * FROM tblSubForms WHERE fkForm = 69 ORDER BY Sortorder";
$rs = Database::openRS($sql, $conn);
if ($rs) {
    $count = 0;
    while (!$rs->EOF) {
        $count++;
        echo "Row $count:\n";
        foreach ($rs->fields as $key => $val) {
            if (!is_numeric($key) && $val !== null && $val !== '') {
                echo "  $key = $val\n";
            }
        }
        $rs->MoveNext();
    }
    echo "Total: $count rows\n";
} else {
    echo "Query failed: " . Database::getLastError() . "\n";
}

// List all unique parent form IDs
echo "\nUnique parent form IDs in tblSubForms:\n";
$sql = "SELECT DISTINCT fkForm FROM tblSubForms ORDER BY fkForm";
$rs = Database::openRS($sql, $conn);
if ($rs) {
    while (!$rs->EOF) {
        echo "  " . $rs->fields['fkForm'] . "\n";
        $rs->MoveNext();
    }
}
