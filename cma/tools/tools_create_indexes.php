<?php
/**
 * CMA Index Creation Tool
 * Creates performance indexes on Access databases
 */

use App\Library\Database;
use App\Library\Request;
use App\Library\Response;
use Cma\ToolbarHelper;

require_once __DIR__ . '/../bootstrap.inc';

Response::noCache();
cma_html_header('CMA - Create Performance Indexes');
echo '<BODY class="contentbody tools">';

ToolbarHelper::report('Create Performance Indexes', false, false, false);
echo '<div id="c" class="tools">';

// Define indexes to create
// NOTE: tblForms and tblControls indexes removed - forms now use JSON definitions
$indexes = [
    // Repository database indexes
    'rep' => [
        ['table' => 'tblMenuItems', 'name' => 'idx_tblMenuItems_fkFormID', 'columns' => ['fkFormID']],
        ['table' => 'tblMenuItems', 'name' => 'idx_tblMenuItems_fkMenuID', 'columns' => ['fkMenuID']],
        ['table' => 'tblMenuItems', 'name' => 'idx_tblMenuItems_ExecutionOrder', 'columns' => ['ExecutionOrder']],
        ['table' => 'tblMenu', 'name' => 'idx_tblMenu_ExecutionOrder', 'columns' => ['ExecutionOrder']],
        ['table' => 'tblModules', 'name' => 'idx_tblModules_blnActive', 'columns' => ['blnActive']],
        ['table' => 'tblReports', 'name' => 'idx_tblReports_fkModule', 'columns' => ['fkModule']],
        ['table' => 'tblReports', 'name' => 'idx_tblReports_fkParentReport', 'columns' => ['fkParentReport']],
        ['table' => 'tblReports', 'name' => 'idx_tblReports_Visible', 'columns' => ['Visible']],
    ],
    // CMAUsers database indexes
    'users' => [
        ['table' => 'tblUsers', 'name' => 'idx_tblUsers_userLogin', 'columns' => ['userLogin']],
        ['table' => 'tblGroups', 'name' => 'idx_tblGroups_grpName', 'columns' => ['grpName']],
        ['table' => 'tblGroupMembers', 'name' => 'idx_tblGroupMembers_fkGroup', 'columns' => ['fkGroup']],
        ['table' => 'tblGroupMembers', 'name' => 'idx_tblGroupMembers_fkUser', 'columns' => ['fkUser']],
        ['table' => 'tblUserDataNotifications', 'name' => 'idx_tblUserDataNotifications_fkUser', 'columns' => ['fkUser']],
        ['table' => 'tblUserDataNotifications', 'name' => 'idx_tblUserDataNotifications_fkStore', 'columns' => ['fkStore']],
    ],
];

$action = Request::post('action', '');

if ($action === 'create') {
    echo '<h3>Creating Indexes...</h3>';
    echo '<table cellpadding="5" cellspacing="0" border="1">';
    echo '<tr><th>Database</th><th>Table</th><th>Index Name</th><th>Status</th></tr>';

    foreach ($indexes as $dbName => $dbIndexes) {
        foreach ($dbIndexes as $idx) {
            $table = $idx['table'];
            $name = $idx['name'];
            $cols = implode(', ', $idx['columns']);

            // Access SQL for creating index
            $sql = "CREATE INDEX [{$name}] ON [{$table}] ([" . implode('], [', $idx['columns']) . "])";

            echo "<tr><td>{$dbName}</td><td>{$table}</td><td>{$name}</td><td>";

            try {
                // Get connection for this database
                $conn = Database::getConnection($dbName);
                $conn->exec($sql);
                echo '<span style="color:green">✓ Created</span>';
            } catch (\Exception $e) {
                $msg = $e->getMessage();
                if (stripos($msg, 'already exists') !== false || stripos($msg, 'duplicate') !== false) {
                    echo '<span style="color:blue">Already exists</span>';
                } else {
                    echo '<span style="color:red">Error: ' . htmlspecialchars(substr($msg, 0, 100)) . '</span>';
                }
            }

            echo '</td></tr>';
        }
    }

    echo '</table>';
    echo '<br><br><a href="tools_create_indexes.php">← Back</a>';

} else {
    // Show overview and confirmation
    echo '<h3>Performance Index Summary</h3>';
    echo '<p>This tool will create indexes to improve query performance based on profiler analysis.</p>';

    echo '<h4>Identified Slow Queries:</h4>';
    echo '<table cellpadding="5" cellspacing="0" border="1">';
    echo '<tr><th>Page</th><th>Query Time</th><th>Expected Improvement</th></tr>';
    echo '<tr><td>sec_group_maint.php</td><td style="color:red">670ms</td><td>50-100ms (6-13x faster)</td></tr>';
    echo '<tr><td>sec_user_maint.php</td><td style="color:orange">88ms</td><td>20-40ms (2-4x faster)</td></tr>';
    echo '<tr><td>listReports.php</td><td style="color:orange">91ms</td><td>20-40ms (2-4x faster)</td></tr>';
    echo '<tr><td>task.php</td><td style="color:orange">78ms</td><td>15-30ms (2-5x faster)</td></tr>';
    echo '<tr><td>login.php</td><td>61ms</td><td>5-15ms (4-12x faster)</td></tr>';
    echo '</table>';

    echo '<h4>Indexes to Create:</h4>';
    echo '<table cellpadding="5" cellspacing="0" border="1">';
    echo '<tr><th>Database</th><th>Table</th><th>Index Name</th><th>Columns</th></tr>';

    foreach ($indexes as $dbName => $dbIndexes) {
        foreach ($dbIndexes as $idx) {
            echo '<tr>';
            echo '<td>' . htmlspecialchars($dbName) . '</td>';
            echo '<td>' . htmlspecialchars($idx['table']) . '</td>';
            echo '<td>' . htmlspecialchars($idx['name']) . '</td>';
            echo '<td>' . htmlspecialchars(implode(', ', $idx['columns'])) . '</td>';
            echo '</tr>';
        }
    }

    echo '</table>';

    echo '<br><br>';
    echo '<form method="post">';
    echo '<input type="hidden" name="action" value="create">';
    echo '<button type="submit" class="btn-success">Create All Indexes</button>';
    echo '</form>';

    echo '<br><p><strong>Note:</strong> If an index already exists, it will be skipped.</p>';
}

echo '</div></BODY></HTML>';
?>
