<?php
use App\Library\Application;
use Cma\ToolbarHelper;
use App\Library\Cache;
use App\Library\Database;
use App\Library\Error;
use App\Library\Request;
use App\Library\Response;
use App\Library\SQL;
use App\Library\Server;

require_once __DIR__ . '/../bootstrap.inc';
Response::noCache();
cma_html_header('CMA - Copy Module');
echo '<BODY class="contentbody tools tool-dev-copymod">';

set_time_limit(60 * 60);
$iRepository = Request::query('Repository', '');
$iModule = Request::query('Module', '');
$iDatabase = Request::query('Database', '');
$bron = null;

ToolbarHelper::report('Module kopiëren', false, false, false, false, 'Kopieer bestaande modules voor ontwikkeling');
echo '<div id="c" class="tools">';

// Note: This tool requires filesystem access and direct database file access
// which is limited in the PHP version
echo '<lib-message type="warning">';
echo '<strong>Let op:</strong> Deze tool is niet volledig beschikbaar in de PHP versie.<br>';
echo 'Het kopiëren van modules tussen repositories vereist toegang tot de repository bestanden.<br>';
echo 'Gebruik de ASP/VBScript versie of kopieer handmatig via de database.';
echo '</lib-message>';

if ($iRepository != '' && $iModule != '' && $iDatabase != '') {
    // Show what would be copied
    echo '<h3>Module kopiëren configuratie:</h3>';
    echo '<p>Repository: ' . htmlspecialchars($iRepository) . '</p>';
    echo '<p>Module ID: ' . htmlspecialchars($iModule) . '</p>';
    echo '<p>Doeldatabase ID: ' . htmlspecialchars($iDatabase) . '</p>';
} else {
    DisplayForm();
}

/**
* Displayform
*/
function displayform()
{
    global $iDatabase, $iModule, $iRepository, $lang_required_entry;
    $intCols = 2;
    echo '<FORM action="tools_dev_copymod.php" method=GET id=main name=main style=margin:0px>';
    echo '<table cellpadding=2 cellspacing=2 width=600>';
    echo '<tr><td colspan=' . $intCols . '>Deze wizard kan een volledige module uit een andere repository kopiëren naar de huidige repository.<BR>&nbsp;</td></tr>';
    echo '<tr><td nowrap width=1%><B>Repository waarvanuit module<BR />gekopieerd moet worden&nbsp;<span class="req" title="' . ($lang_required_entry ?? 'Verplicht') . '">*</span>:&nbsp;</B></td><td>';

    if ($iRepository != '') {
        echo '<input type=text value="' . htmlspecialchars($iRepository) . '" style=width:240px name=repository readonly> ';
    } else {
        echo '<em>(Repository selectie niet beschikbaar in PHP versie)</em>';
        echo '<input type=text style=width:240px name=Repository placeholder="Pad naar repository...">';
    }
    echo '&nbsp;</td></tr>';

    echo '<tr><td height=24><B>Te kopiëren module&nbsp;<span class="req" title="' . ($lang_required_entry ?? 'Verplicht') . '">*</span>:&nbsp;</B></td><td>';
    echo '<input type=text style=width:240px name=Module placeholder="Module ID...">';
    echo '</td></tr>';

    echo '<tr><td nowrap><B>Gegevens komen in database&nbsp;<span class="req">*</span>:&nbsp;</B></td><td>';
    echo ToolsDatabaseSelect($iDatabase ?? '', ($iDatabase ?? '') == '', false, true);
    echo '&nbsp;</td></tr>';

    echo '<tr><td colspan=' . $intCols . '><br><button class="btn btn-primary" onclick="document.forms.main.go.disabled=true;document.forms.main.submit()" id="go">Verder</button></td></tr>';

    echo '</table>';
    echo '</form>';
}

echo '</div></body></html>';
?>
