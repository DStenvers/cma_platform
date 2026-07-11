<?php
/**
 * CMA Index Creation Tool
 *
 * Creates performance indexes on the CMA-standard tables via ODBC/Access DDL.
 * CREATE INDEX is plain SQL DDL that the Access ODBC driver supports (unlike
 * "compact & repair", which has no ODBC equivalent), so this tool genuinely works.
 *
 * Targets:
 *   - users (CMAUsers): authentication / group-maintenance tables.
 *   - data  (main DB): the CMA-standard audit log tblCMAMonitoring.
 *
 * The deprecated repository database is deliberately NOT touched — forms, menus
 * and reports moved to JSON, so its tables are legacy (and the connection is
 * marked DEPRECATED_rep in databases.json). App-specific business tables in the
 * data DB vary per site, so they aren't indexed here either.
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

// Define indexes to create. Only CMA-standard tables — the deprecated repository
// DB is not touched (tblMenu*/tblReports/tblModules moved to JSON), and
// app-specific business tables in the data DB are site-specific.
$indexes = [
    // CMAUsers database — authentication + group maintenance.
    'users' => [
        ['table' => 'tblUsers', 'name' => 'idx_tblUsers_userLogin', 'columns' => ['userLogin']],
        ['table' => 'tblGroups', 'name' => 'idx_tblGroups_grpName', 'columns' => ['grpName']],
        ['table' => 'tblGroupMembers', 'name' => 'idx_tblGroupMembers_fkGroup', 'columns' => ['fkGroup']],
        ['table' => 'tblGroupMembers', 'name' => 'idx_tblGroupMembers_fkUser', 'columns' => ['fkUser']],
        ['table' => 'tblUserDataNotifications', 'name' => 'idx_tblUserDataNotifications_fkUser', 'columns' => ['fkUser']],
        ['table' => 'tblUserDataNotifications', 'name' => 'idx_tblUserDataNotifications_fkStore', 'columns' => ['fkStore']],
    ],
    // Main (data) database — the CMA audit log grows to tens of thousands of
    // rows and every dashboard/activity query filters and sorts on datestamp
    // (WHERE datestamp >= … / ORDER BY datestamp DESC), so index that first;
    // Actie/Username speed the GROUP BY aggregations.
    'data' => [
        ['table' => 'tblCMAMonitoring', 'name' => 'idx_tblCMAMonitoring_datestamp', 'columns' => ['datestamp']],
        ['table' => 'tblCMAMonitoring', 'name' => 'idx_tblCMAMonitoring_Actie', 'columns' => ['Actie']],
        ['table' => 'tblCMAMonitoring', 'name' => 'idx_tblCMAMonitoring_Username', 'columns' => ['Username']],
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
                // Access reuses error -1403 for BOTH "index already exists" and
                // "table not found", so classify on the message text, not the code.
                // The duplicate-index message is "De tabel X bevat al een index met
                // de naam Y"; table-not-found is "Kan de invoertabel … niet vinden".
                if (stripos($msg, 'bevat al een index') !== false || stripos($msg, 'already has an index') !== false
                    || stripos($msg, 'already exists') !== false || stripos($msg, 'duplicate') !== false
                    || stripos($msg, 'bestaat al') !== false) {
                    echo '<span style="color:blue">Bestaat al</span>';
                } elseif (stripos($msg, 'invoertabel') !== false || stripos($msg, 'cannot find') !== false
                       || stripos($msg, 'input table') !== false || stripos($msg, 'niet vinden') !== false) {
                    // The table doesn't exist in this database on this site — skip.
                    echo '<span style="color:gray">Tabel niet aanwezig — overgeslagen</span>';
                } else {
                    echo '<span style="color:red">Error: ' . htmlspecialchars(substr($msg, 0, 120)) . '</span>';
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
    echo '<p>Deze tool maakt indexes aan op de CMA-standaard tabellen om veelgebruikte queries te versnellen. Access-DDL <code>CREATE INDEX</code> loopt gewoon via ODBC. De <span class="cma-tool__strong">deprecated</span> repository-database wordt niet aangeraakt; app-specifieke tabellen in de hoofddatabase zijn per site verschillend en horen daar niet.</p>';
    echo '<ul>';
    echo '<li><span class="cma-tool__strong">users</span> — inlog- en groepsbeheer (tblUsers, tblGroups, tblGroupMembers, tblUserDataNotifications).</li>';
    echo '<li><span class="cma-tool__strong">data</span> (hoofddatabase) — het audit-log <code>tblCMAMonitoring</code> op <code>datestamp</code> (elke dashboard/activiteit-query filtert en sorteert hierop), plus <code>Actie</code>/<code>Username</code> voor de aggregaties.</li>';
    echo '</ul>';

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

    echo '<br><p><span class="cma-tool__strong">Note:</span> If an index already exists, it will be skipped.</p>';
}

echo '</div></BODY></HTML>';
?>
