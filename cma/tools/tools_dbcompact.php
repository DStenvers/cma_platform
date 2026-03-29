<?php
use App\Library\Application;
use Cma\ToolbarHelper;
use App\Library\Database;
use App\Library\Error;
use App\Library\Request;
use App\Library\Response;
use App\Library\Server;

require_once __DIR__ . '/../bootstrap.inc';
Response::noCache();
cma_html_header('CMA - Database Compact');
echo '<BODY class="contentbody tools">';

/**
* Dbcompact - Compact a database for optimization
* Note: MS Access JRO.JetEngine is not available in PHP.
* This functionality requires the original ASP/VBScript environment.
*/
function dbCompact($iDatabase)
{
    // Access 97 FORMAT: Jet OLEDB:Engine Type=4;
    // Access 2000/XP FORMAT: Jet OLEDB:Engine Type=5;
    define("JET_CONN_PARTIAL", 'Provider=Microsoft.Jet.OLEDB.4.0;Jet OLEDB:Engine Type=5;Data source=');

    // Get database info from JSON config
    $dbConfig = \Cma\ConfigLoader::getDatabase(intval($iDatabase));
    if ($dbConfig === null) {
        Error::show('Kan database niet vinden');
        return;
    }

    $strFullName = $dbConfig['connectionString'] ?? '';
    $strDBName = $dbConfig['name'] ?? ('Database ' . $iDatabase);

    // Extract database path from connection string
    $pos = stripos($strFullName, '[');
    if ($pos !== false) {
        $strFullName = substr($strFullName, $pos + 1);
        $pos = stripos($strFullName, ']');
        if ($pos !== false) {
            $strFullName = substr($strFullName, 0, $pos);
        }
    }

    $strFullName = Server::mapPath('../' . $strFullName);
    $strCopyName = str_replace('.mdb', '_temp.mdb', strtolower($strFullName));

    // Check if database file exists
    if (!file_exists($strFullName)) {
        echo $strFullName . ' not found!';
        return;
    }

    // Note: Database compaction via JRO.JetEngine is not available in PHP
    // This would require COM objects which are Windows/IIS specific
    echo '<lib-message type="warning">';
    echo '<strong>Opmerking:</strong> Database optimalisatie (compacting) via JRO.JetEngine is niet beschikbaar in de PHP versie.<br>';
    echo 'Gebruik Microsoft Access om de database "' . htmlspecialchars($strDBName) . '" handmatig te comprimeren.<br>';
    echo 'Database pad: ' . htmlspecialchars($strFullName);
    echo '</lib-message>';
}

$iDatabase = Request::post('Database', '');
ToolbarHelper::report('Database optimaliseren', false, false, false);
echo '<div id="c" class="tools">';

if ($iDatabase == '') {
    echo '<FORM action="tools_dbcompact.php" method=POST id=main name=main style=margin:0px>';
    echo '<table cellpadding=0 cellspacing=0>';
    echo '<tr valign=TOP><td><a name=aquery><B>Database:</B> ';
    echo ToolsDatabaseSelect($iDatabase, true, false, true);
    echo '</td></tr></table>';
    echo '<br><br>';
    echo '<button class="btn btn-primary" onclick="document.forms.main.compact.disabled=true;document.forms.main.submit()" id="compact">Optimaliseer</button>';
    echo '</FORM>';
} else {
    dbCompact($iDatabase);
}

echo '</div></body></html>';
?>
