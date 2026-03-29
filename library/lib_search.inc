<?php
// save search in database for reporting purposes
/**
* Lib Searchsave
*
*/
use App\Library\Database;
use App\Library\Request;
use App\Library\SQL;

/**
 * Lib Searchsave
 */
function lib_SearchSave($strSearchFor)
{
    $strInsertSQL = "";
    $search_conn = null;
    $strSearchFor = lib_HTML_RemoveScripts($strSearchFor);
    if ($strSearchFor != '') {
        $strInsertSQL = 'INSERT INTO tblSearches (SearchText,SearchReferer) VALUES (' . SQL::postString((is_null(substr($strSearchFor, 0, max(0, min(255, strlen($strSearchFor))))) ? "" : strtoupper(substr($strSearchFor, 0, max(0, min(255, strlen($strSearchFor))))))) . ',' . SQL::postString((is_null(substr(Request::server('HTTP_REFERER', ''), 0, max(0, min(255, strlen(Request::server('HTTP_REFERER', '')))))) ? "" : strtoupper(substr(Request::server('HTTP_REFERER', ''), 0, max(0, min(255, strlen(Request::server('HTTP_REFERER', '')))))))) . ')';
        Database::execute($strInsertSQL, []);
    }
}
/**
* Function used for displaying text results, it will hilight the searched text
*/
/**
* Lib Hilighttext
*
*/
function lib_HilightText($strText, $strHilightText)
{
    $intPos = 0;
    $strHilightPre = '<font class=s>';
    $strHilightPost = '</font>';
    if ($strHilightText != '') {
        $intPos = (($pos = stripos((is_null($strText) ? "" : strtoupper($strText)), (is_null($strHilightText) ? "" : strtoupper($strHilightText)))) !== false ? $pos + 1 : 0);
        while ($intPos > 0) {
            $strText = substr($strText, 0, max(0, min($intPos - 1, strlen($strText)))) . $strHilightPre . substr($strText, max(0, $intPos - 1), (is_null($strHilightText) ? 0 : strlen($strHilightText))) . $strHilightPost . substr($strText, max(0, $intPos + (is_null($strHilightText) ? 0 : strlen($strHilightText)) - 1));
            $intPos = (($pos = stripos($strText, $strHilightText, max(0, $intPos + (is_null($strHilightPre) ? 0 : strlen($strHilightPre)) + (is_null($strHilightText) ? 0 : strlen($strHilightText)) - 1))) !== false ? $pos + 1 : 0);
        }
    }
    return $strText;
}
?>
