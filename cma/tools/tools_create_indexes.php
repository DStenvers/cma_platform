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
use Cma\Services\PerformanceLogger;

/**
 * Suggest which site-specific tables/forms to optimise, based on the performance
 * log. Query-level logging isn't wired into the DB layer, so the richest signal
 * is slow API calls (form_api.php) — we aggregate those by form over the last few
 * days. When the perf-log is off we can't suggest anything, so we hint how to turn
 * it on. Read-only: this proposes, it never creates app-specific indexes (the
 * right column is a human call).
 */
function cma_render_perf_index_suggestions(): void
{
    echo '<h3 style="margin-top:28px;">Voorstel op basis van de performance-log</h3>';

    if (!PerformanceLogger::isEnabled()) {
        echo '<lib-message type="info">';
        echo 'De <span class="cma-tool__strong">performance-log staat uit</span>, dus er zijn geen metingen om site-specifieke voorstellen op te baseren. ';
        echo 'Zet hem aan (<code>PERF_LOG_ENABLED=true</code> in <code>.env</code>, of via de systeeminstellingen) en laat de site een tijdje draaien — daarna toont deze tool welke formulieren/tabellen op déze site het traagst zijn, zodat je gericht kunt indexeren op hun filter-, sorteer- en FK-kolommen.';
        echo '</lib-message>';
        return;
    }

    // Aggregate slow entries over the last few days. Query entries (if ever wired
    // up) carry a table; API entries carry the form in their URL.
    $SLOW_MS = 100.0;
    $agg = []; // key => ['label','kind','count','total','max']
    for ($d = 0; $d < 5; $d++) {
        $date = date('Y-m-d', strtotime("-{$d} days"));
        foreach (PerformanceLogger::readLogs($date, null, 20000) as $e) {
            $ms = (float)($e['ms'] ?? 0);
            if ($ms < $SLOW_MS) {
                continue;
            }
            $type = $e['type'] ?? '';
            $key = null; $label = null; $kind = null;
            if ($type === 'query') {
                $sql = $e['ctx']['sql'] ?? '';
                if (preg_match('/\bfrom\s+\[?([a-z0-9_]+)\]?/i', $sql, $m)) {
                    $kind = 'Tabel'; $label = $m[1]; $key = 'q:' . strtolower($m[1]);
                }
            } elseif ($type === 'api' || $type === 'fetch') {
                $url = $e['url'] ?? ($e['ctx']['url'] ?? '');
                if ($url !== '' && preg_match('/[?&]form=([a-z0-9_]+)/i', $url, $m)) {
                    $kind = 'Formulier'; $label = $m[1]; $key = 'f:' . strtolower($m[1]);
                } elseif (!empty($e['name'])) {
                    $kind = 'Endpoint'; $label = $e['name']; $key = 'a:' . strtolower($e['name']);
                }
            }
            if ($key === null) {
                continue;
            }
            if (!isset($agg[$key])) {
                $agg[$key] = ['label' => $label, 'kind' => $kind, 'count' => 0, 'total' => 0.0, 'max' => 0.0];
            }
            $agg[$key]['count']++;
            $agg[$key]['total'] += $ms;
            $agg[$key]['max'] = max($agg[$key]['max'], $ms);
        }
    }

    if (empty($agg)) {
        echo '<lib-message type="success">Geen trage calls (&ge; ' . (int)$SLOW_MS . ' ms) in de performance-log van de afgelopen dagen — niets te optimaliseren.</lib-message>';
        return;
    }

    // Rank by total time spent (impact), show the top 15.
    uasort($agg, function ($a, $b) { return $b['total'] <=> $a['total']; });
    $agg = array_slice($agg, 0, 15, true);

    echo '<p>De traagste site-specifieke formulieren/tabellen op déze site (laatste 5 dagen, &ge; ' . (int)$SLOW_MS . ' ms), gesorteerd op totale tijd. Kandidaten om te indexeren op hun filter-, sorteer- en FK-kolommen; bekijk de bijbehorende query in <a href="tools_query.php" target="_top">SQL uitvoeren</a>.</p>';
    echo '<table cellpadding="5" cellspacing="0" border="1">';
    echo '<tr><th>Soort</th><th>Formulier / tabel</th><th style="text-align:right">Trage calls</th><th style="text-align:right">Gemiddeld</th><th style="text-align:right">Max</th></tr>';
    foreach ($agg as $row) {
        $avg = $row['count'] > 0 ? $row['total'] / $row['count'] : 0;
        echo '<tr>';
        echo '<td>' . htmlspecialchars($row['kind']) . '</td>';
        echo '<td>' . htmlspecialchars($row['label']) . '</td>';
        echo '<td style="text-align:right">' . $row['count'] . '</td>';
        echo '<td style="text-align:right">' . number_format($avg, 0) . ' ms</td>';
        echo '<td style="text-align:right">' . number_format($row['max'], 0) . ' ms</td>';
        echo '</tr>';
    }
    echo '</table>';
    echo '<p style="color:var(--text-muted);font-size:var(--font-size-sm);">Tip: query-niveau logging (met exacte tabel + kolommen) is nog niet ingeschakeld in de DB-laag; deze lijst is afgeleid van API-metingen per formulier.</p>';
}

require_once __DIR__ . '/../bootstrap.inc';

Response::noCache();
cma_html_header('Indexen aanmaken');
echo '<BODY class="contentbody tools">';

ToolbarHelper::report('Indexen aanmaken', false, false, false);
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
    echo '<h3>CMA-standaard indexen</h3>';
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

    echo '<br><p><span class="cma-tool__strong">Let op:</span> een index die al bestaat wordt overgeslagen.</p>';

    cma_render_perf_index_suggestions();
}

echo '</div></BODY></HTML>';
?>
