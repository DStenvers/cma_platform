<?php
/**
 * DEPRECATED: This tool uses the legacy tblForms/tblControls database structure.
 * Form definitions are now stored as JSON files in assets/forms/.
 * This tool needs to be rewritten to scan JSON form definitions for image fields.
 *
 * To find image fields in JSON forms, scan for:
 * - fields with type: "image" or type: "thumbnail"
 * - fields with path, widthField, heightField properties
 */
use App\Library\Application;
use Cma\FormControlHelper;
use Cma\ToolbarHelper;
use App\Library\Database;
use App\Library\Response;
use App\Library\Server;

require_once __DIR__ . '/../bootstrap.inc';
require_once __DIR__ . '/../library/lib_imgformat.inc';
Response::noCache();
cma_html_header('CMA - Beeldformaten herstellen');
echo '<BODY class="contentbody tools">';
/**
* Main
*
*/
function main()
{
    global $conn, $connrep;
    $fieldsRS = null;
    $picturesSQL = null;
    $picturesRS = null;
    $strCurrentColor = "";
    $strFileName = "";
    $strRealFileName = "";
    $sImageWidth = null;
    $sImageHeight = null;
    $iDepth = null;
    $sImageType = null;
    $setsql = null;

    // First retrieve the names of fields referring to pictures
    $fieldsSQL = ' SELECT tblDatabases.ConnectionString, tblSqlStatements.[Table], tblForms.FormName, tblControls.FieldName, tblControls.Caption, tblControls.ImgPath, tblControls.ImgHeightField, tblControls.ImgWidthField  FROM (tblDatabases INNER JOIN tblModules ON tblDatabases.ID = tblModules.fkDatabase) INNER JOIN (tblSqlStatements INNER JOIN (tblForms INNER JOIN tblControls ON tblForms.ID = tblControls.FormID) ON tblSqlStatements.ID = tblForms.SqlID) ON tblModules.ID = tblForms.fkModule  WHERE ((tblModules.blnActive=True) AND (tblForms.[Visible]=True) AND (tblControls.ControlTypeID=' . FormControlHelper::TYPE_IMAGE . ' or tblControls.ControlTypeID=' . FormControlHelper::TYPE_THUMBNAIL . ' ) and ( (tblControls.ImgHeightField is not null) or (tblControls.ImgWidthField is not null) ) )  ORDER BY tblForms.FormName, tblControls.Caption';
    $fieldsRS = Database::openRS($fieldsSQL, $connrep, adOpenForwardOnly, adLockReadOnly);
    if ($fieldsRS === null) {
        throw new \Exception('Database query failed: ' . Database::getLastError());
    }

    ToolbarHelper::report('Beeldformaten herstellen', false, false, false);
    echo '<div id="c" class="tools">';
    echo '<TABLE width=100% cellspacing=2 cellpadding=2>';
    if (!$fieldsRS->EOF) {
        $strCurrentColor = Application::get('color_row1', '');
        while (!$fieldsRS->EOF) {
            echo '<TR><TH colspan=2 align=left><B>' . $fieldsRS->fields['FormName'] . ' (' . $fieldsRS->fields['Caption'] . ')</B></TH></TR>';
            // TODO: : Optimize this, is a bit sluggish!
            OpenConnection($fieldsRS->fields['ConnectionString']);
            // retrieve the expected files and try to find them on the server
            $picturesSQL = 'SELECT ' . $fieldsRS->fields['FieldName'] . ' as Filename, ID FROM ' . $fieldsRS->fields['Table'] . ' WHERE (' . $fieldsRS->fields['FieldName'] . "<>'')  ORDER BY 1";
            $picturesRS = Database::openRS($picturesSQL, $conn, adOpenForwardOnly, adLockReadOnly);
            if ($picturesRS === null) {
                throw new \Exception('Database query failed: ' . Database::getLastError());
            }
            while (!$picturesRS->EOF) {
                $strFileName = Application::get('base_path', '') . $fieldsRS->fields['ImgPath'] . $picturesRS->fields['Filename'];
                $strRealFileName = Server::mapPath($strFileName);
                if (!file_exists($strRealFileName)) {
                    echo '<TR><TD>' . $strFileName . ' ontbreekt </TD></TR>';
                } else {
                    if (gfxSpex($strRealFileName, $sImageWidth, $sImageHeight, $iDepth, $sImageType)) {
                        $setsql = 'UPDATE ' . $fieldsRS->fields['Table'] . ' SET ';
                        if ($fieldsRS->fields['ImgWidthField'] != '') {
                            $setsql .= $fieldsRS->fields['ImgWidthField'] . '=' . $sImageWidth;
                        }
                        if ($fieldsRS->fields['ImgHeightField'] != '') {
                            if ($fieldsRS->fields['ImgWidthField'] != '') {
                                $setsql .= ',';
                            }
                            $setsql .= $fieldsRS->fields['ImgHeightField'] . '=' . $sImageHeight;
                        }
                        $setsql .= ' WHERE ID=' . $picturesRS->fields['ID'];
                        Database::execute($setsql);
                        echo '<TR><TD>' . $strFileName . ': breedte ' . $sImageWidth . ', hoogte ' . $sImageHeight . '</TD></TR>';
                    } else {
                        echo '<TR><TD>Kan grootte niet achterhalen!</TD></TR>';
                    }
                }
                $picturesRS->MoveNext();
            }
            if ($strCurrentColor == Application::get('color_row1', '')) {
                $strCurrentColor = Application::get('color_row2', '');
            } else {
                $strCurrentColor = Application::get('color_row1', '');
            }
            $fieldsRS->MoveNext();
        }
    }
    echo '</TABLE></div></BODY></HTML>';
}
main();
?>
