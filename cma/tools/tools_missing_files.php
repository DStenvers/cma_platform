<?php
/**
 * DEPRECATED: This tool uses the legacy tblForms/tblControls database structure.
 * Form definitions are now stored as JSON files in assets/forms/.
 * This tool needs to be rewritten to scan JSON form definitions for file fields.
 *
 * To find file fields in JSON forms, scan for:
 * - fields with type: "file" (ControlTypeID=9)
 * - fields with path property
 */
use App\Library\Application;
use Cma\FormControlHelper;
use Cma\ToolbarHelper;
use App\Library\Cache;
use App\Library\Database;
use App\Library\Response;
use App\Library\Server;

require_once __DIR__ . '/../bootstrap.inc';
Response::noCache();
cma_html_header('CMA - Ontbrekende bestanden');
echo '<BODY class="contentbody tools">';
define("STRERRORFONT", '<font color=red>');
define("STRCACHEPREFIX", 'CMA_ImageSearch_');
/**
* Main
*
*/
function main()
{
    global $conn, $connrep;
    $sOldConnection = null;
    $sDir = null;
    // kan ff duren!
    set_time_limit(60000);
    $fieldsRS = null;
    $fieldsSQL = null;
    $picturesSQL = null;
    $picturesRS = null;
    $strCurrentColor1 = "";
    $strFileName = "";
    $strRealFileName = "";
    $sImageWidth = null;
    $sImageHeight = null;
    $iDepth = null;
    $sImageType = null;
    $setsql = null;
    // First retrieve the names of fields referring to pictures
    $fieldsSQL = ' SELECT tblDatabases.ConnectionString, tblSqlStatements.[Table], tblForms.FormName, tblControls.FieldName, tblControls.Caption, tblControls.ImgPath, tblControls.ImgHeightField, tblControls.ImgWidthField, tblControls.ImgResizeType, tblControls.ImgResizeWidth, tblControls.ImgResizeHeight, tblControls.IsRequired  FROM (tblDatabases INNER JOIN tblModules ON tblDatabases.ID = tblModules.fkDatabase) INNER JOIN (tblSqlStatements INNER JOIN (tblForms INNER JOIN tblControls ON tblForms.ID = tblControls.FormID) ON tblSqlStatements.ID = tblForms.SqlID) ON tblModules.ID = tblForms.fkModule  WHERE ((tblModules.blnActive=True) AND (tblForms.[Visible]=True) AND (tblControls.enabled) AND (tblControls.ControlTypeID=' . FormControlHelper::TYPE_FILE . ') ) ORDER BY tblForms.FormName, tblControls.Caption';
    $fieldsRS = Database::openRS($fieldsSQL, $connrep, adOpenForwardOnly);
    if ($fieldsRS === null) {
        throw new \Exception('Database query failed: ' . Database::getLastError());
    }
    $fieldsRS->MoveNext();
    ToolbarHelper::report('Ontbrekende bestanden analyse', false, false, false);
    echo '<div id="c" class="tools">';
    echo '<TABLE width=100% cellspacing=2 cellpadding=2>';
    if (!$fieldsRS->EOF) {
        $sOldConnection = '';
        while (!($fieldsRS->EOF)) {
            echo '<TR><TH colspan=2 align=left><B>' . $fieldsRS->fields['FormName'] . ' - ' . $fieldsRS->fields['Caption'];
            echo '</B></TH></TR>';
            // TODO: : Optimize this, is a bit sluggish!
            if ($fieldsRS->fields['ConnectionString'] != $sOldConnection) {
                if (is_object($conn)) {
                }
                OpenConnection($fieldsRS->fields['ConnectionString']);
                $sOldConnection = $fieldsRS->fields['ConnectionString'];
            }
            // retrieve the expected files and try to find them on the server
            $picturesSQL = 'SELECT ' . $fieldsRS->fields['FieldName'] . ' as Filename, ID FROM ' . $fieldsRS->fields['Table'] . ' WHERE (' . $fieldsRS->fields['FieldName'] . "<>'') ORDER BY 1";
            $picturesRS = Database::openRS($picturesSQL, $conn, adOpenForwardOnly);
            if ($picturesRS === null) {
                throw new \Exception('Database query failed: ' . Database::getLastError());
            }
            while (!$picturesRS->EOF) {
                $strFileName = Application::get('base_path', '') . $fieldsRS->fields['ImgPath'] . $picturesRS->fields['Filename'];
                $strRealFileName = Server::mapPath($strFileName);
                if (!file_exists($strRealFileName)) {
                    echo '<TR><TD>' . $strFileName . STRERRORFONT . ' ontbreekt</TD></TR>';
                }
                $picturesRS->MoveNext();
            }
            $fieldsRS->MoveNext();
        }
    }
    echo '</TABLE>';
    echo '</div></BODY></HTML>';
    Cache::delete($STRCACHEPREFIX);
}
main();
?>
