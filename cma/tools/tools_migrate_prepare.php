<?php
use App\Library\Application;
use App\Library\Error;
use Cma\ToolbarHelper;
use Cma\SchemaHelper;
use App\Library\Database;
use App\Library\Request;
use App\Library\Response;
use Cma\SecurityHelper;

require_once __DIR__ . '/../bootstrap.inc';

// Developer-only tool
if (!SecurityHelper::isDeveloper()) {
    Error::page('Toegang geweigerd', 'Deze functie is alleen beschikbaar voor developers.', false);
    exit;
}

Response::noCache();
cma_html_header('CMA - Migration Prepare', '', false);
define("ADRICASCADE", 1);
define("ADRINONE", 0);
define("ADRISETDEFAULT", 3);
define("ADRISETNULL", 2);
define("ADKEYPRIMARY", 1);
define("ADKEYFOREIGN", 2);
define("ADKEYUNIQUE", 3);

$JSONresult = '';
$sqls = '';

ToolbarHelper::writeJS();
echo '</HEAD>';
echo '<BODY class="contentbody tools tool-migrate-prepare">';

$intDatabase = (Request::post('Database', '') != '' ? Request::post('Database', '') : Request::query('Database', ''));
$bJSON = Request::query('JSON', '') != '';

if (!$bJSON) {
    ToolbarHelper::report('Database migratie naar SQL Server voorbereiden', false, false, false, false, 'Analyseer database schema voor SQL Server migratie');
    echo '<div id="c" class="tools">';
}

// Show warning about ADOX limitations
if (!$bJSON) {
    echo '<lib-message type="warning">';
    echo '<strong>Let op:</strong> Deze tool maakt gebruik van ADOX (ActiveX Data Objects Extensions) ';
    echo 'en directe schema-toegang wat niet volledig beschikbaar is in PHP.<br>';
    echo 'Voor volledige migratie-ondersteuning, gebruik de ASP/VBScript versie of database migration tools.';
    echo '</lib-message>';
}

if ($intDatabase == '') {
    echo '<FORM method=POST id=main name=main style=margin:0px>';
    echo '<table cellpadding=0 cellspacing=0>';
    echo '<tr valign=TOP><td><a name=aquery><B>Database:</B> ';
    echo ToolsDatabaseSelect($intDatabase, true, false, true);
    echo '</td></tr></table>';
    echo '<br><br>';
    echo '<button class="btn btn-primary" onclick="document.forms.main.compact.disabled=true;document.forms.main.submit()" id="compact">Start</button>';
    echo '</FORM>';
} else {
    dbSummary($intDatabase);
    if ($bJSON) {
        echo $JSONresult;
    }
}

if (!$bJSON) {
    echo '</div></body></html>';
}

/**
* Dbsummary - Generate SQL Server migration scripts
*/
function dbSummary($intDatabase)
{
    global $bJSON, $JSONresult;

    // ADO Schema constant for tables
    $adSchemaTables = 20;

    // Get database info from JSON config
    $dbConfig = \Cma\ConfigLoader::getDatabase(intval($intDatabase));
    if ($dbConfig === null) {
        echo '<lib-message type="error">Database niet gevonden in configuratie</lib-message>';
        return;
    }

    $dbTitle = $dbConfig['name'] ?? ('Database ' . $intDatabase);
    $dbConnStr = $dbConfig['connectionString'] ?? '';

    if (!$bJSON) {
        echo '<table cellpadding=1 cellspacing=2>';
        echo '<tr><td><B>' . htmlspecialchars($dbTitle) . '</B></td>';
        echo '<td colspan=99>' . htmlspecialchars($dbConnStr) . '</td></tr>';
    } else {
        $JSONresult = '{ "tables" : [ ';
    }

    // Note: In PHP we use PDO which doesn't have direct schema access like ADO
    // This would need Database::getSchema() which may not be fully implemented
    echo '<tr><td colspan="99">';
    echo '<p><em>Schema informatie ophalen vereist ADOX catalog - niet beschikbaar in PHP versie</em></p>';
    echo '<p>Voor SQL Server migratie, overweeg:</p>';
    echo '<ul>';
    echo '<li>SQL Server Migration Assistant (SSMA) voor Access</li>';
    echo '<li>Handmatige export via Access naar SQL Server</li>';
    echo '<li>De ASP/VBScript versie van deze tool</li>';
    echo '</ul>';
    echo '</td></tr>';

    if ($bJSON) {
        $JSONresult .= '] }';
    }

    if (!$bJSON) {
        echo '</table>';
        echo '<p>Einde rapport.</p>';
    }
}

/**
 * Get table details - placeholder for ADOX functionality
 */
function GetTableDetails($strTable, $bFirst)
{
    // Note: This function requires ADOX which is not available in PHP
    // The original VBScript used ADOX.Catalog to enumerate columns and indexes
    echo '<tr><td colspan="5"><em>Tabel details voor ' . htmlspecialchars($strTable) . ' niet beschikbaar (ADOX vereist)</em></td></tr>';
}
?>
