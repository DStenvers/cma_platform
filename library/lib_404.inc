<?php
// Sends a 404 mail to the specified address
/**
* Lib 404Mailerror
*
*/
use App\Library\Application;
use App\Library\Cache;
use App\Library\Database;
use App\Library\Request;
use App\Library\Response;
use App\Library\SQL;
use App\Library\Str;
use App\Library\Arr;

/**
 * Lib 404Mailerror
 */
function lib_404mailError($cTo)
{
    global $objMail, $sNotFoundUrl, $strUserAgent;
    if (!lib_isSearchEngine()) {
        $sReferer = Request::server('HTTP_REFERER', '');
        $objMail = new LibMailer();
        $objMail->Subject = 'Pagina niet gevonden notificatie website ' . (is_null(Request::server('SERVER_NAME', '')) ? "" : strtoupper(Request::server('SERVER_NAME', '')));
        $objMail->Body = '<table><tr valign=top><td width=1% >Url</td><td width=99% ><a href="' . $sNotFoundUrl . '">' . $sNotFoundUrl . '</a></td></tr>' . ($sReferer == '' ? '' : '<tr><td width=1% nowrap>Verwijzing vanaf&nbsp;</td><td width=99% ><a href="' . $sReferer . '">' . $sReferer . '</a></td></tr>') . '<tr valign=top><td width=1% nowrap>IP adres</td><td width=99% >' . Request::server('REMOTE_HOST', '') . '</td></tr><tr valign=top><td width=1% >Browser</td><td width=99% >' . $strUserAgent . '</td></tr></table>';
        $objMail->From = $cTo;
        $objMail->FromName = $cTo;
        $objMail->AddRecipients($cTo);
        $objMail->UseTemplate = false;
        $objMail->Send();
        $objMail = null;
    } else {
        $response_status = '404 Not found';
        http_response_code(404);
    }
}
// Zoek een directorynaam en tracht het bijbehorende actie te ondernemen (cached!)
/**
* Lib 404Scandir
*
*/
function lib_404ScanDir($sDir)
{
    global $blnDebug_404, $cache_pre_identifier, $conn;
    $cacheID = $cache_pre_identifier . CACHE_DIR_INDENT . '_' . $sDir;
    $fieldsRS = null;
    $fieldsSQL = null;
    $filesRS = null;
    $filesSQL = null;
    $sFileContent = '';
    $marketingURLRS = null;
    $marketingURLSQL = null;
    if (Cache::get($cacheID) != '') {
        $sFileContent = Cache::get($cacheID);
    } else {
        $marketingURLSQL = 'SELECT Dir, Page FROM tblCMAMarketingUrl WHERE Dir=' . SQL::postString($sDir);
        $marketingURLRS = Database::openRS($marketingURLSQL, 'data', adOpenForwardOnly, adLockReadOnly);
        if ($marketingURLSQL === null) {
            throw new \Exception('Database query failed: ' . Database::getLastError());
        }
        $marketingURLSQL_current_row = $marketingURLSQL->fetch(PDO::FETCH_ASSOC);
        if ($marketingURLRS_current_row !== false) {
            $sFileContent = '<script>window.location=' . Str::JscriptSafe(Str::trim(Arr::field($marketingURLRS_current_row, 'Page') . '')) . ';</script>';
            if ($blnDebug_404) {
                echo 'Marketing URL: ' . Arr::field($marketingURLRS_current_row, 'Page') . '<BR>';
            } else {
                Response::redirect(trim(Arr::field($marketingURLRS_current_row, 'Page')));
            }
        }
        $marketingURLRS = null;
        if ($sFileContent == '') {
            $fieldsSQL = ' SELECT tblDatabases.ConnectionString, tblSqlStatements.Table, tblForms.Name, tblControls.FieldName, tblControls.Caption, tblControls.dirTemplate, tblControls.dirFilename, tblControls.ImgPath  FROM (tblDatabases INNER JOIN tblModules ON tblDatabases.ID = tblModules.fkDatabase) INNER JOIN (tblSqlStatements INNER JOIN (tblForms INNER JOIN tblControls ON tblForms.ID = tblControls.FormID) ON tblSqlStatements.ID = tblForms.SqlID) ON tblModules.ID = tblForms.fkModule  WHERE ((tblModules.blnActive=True) AND (tblControls.enabled and tblForms.Visible=True) AND (tblControls.ControlTypeID=' . CONSTFLDTYPE_DIRECTORY . '))  ORDER BY tblForms.Name, tblControls.Caption';
            $fieldsRS = Database::openRS($fieldsSQL, 'rep', adOpenForwardOnly, adLockReadOnly);
            if ($fieldsSQL === null) {
                throw new \Exception('Database query failed: ' . Database::getLastError());
            }
            $fieldsSQL_current_row = $fieldsSQL->fetch(PDO::FETCH_ASSOC);
            if ($fieldsRS_current_row !== false) {
                $filesRS = null;
                while (!($fieldsRS->eof())) {
                    // TODO: : Optimize this, is a bit sluggish!
                    OpenConnection(Arr::field($fieldsRS_current_row, 'ConnectionString'));
                    $filesSQL = 'SELECT ID FROM ' . Arr::field($fieldsRS_current_row, 'Table');
                    // 'response.write filesSQL
                    $filesRS = Database::openRS($filesSQL, $conn, adOpenForwardOnly, adLockReadOnly);
                    if ($filesRS === null) {
                        throw new \Exception('Database query failed: ' . Database::getLastError());
                    }
                    $filesRS->MoveNext();
                    if (!$filesRS->eof()) {
                        $sFileContent = Arr::field($fieldsRS_current_row, 'dirTemplate');
                        if ((($pos = stripos($sFileContent, '[ID]', max(0, 1 - 1))) !== false ? $pos + 1 : 0) > 0) {
                            $sFileContent = str_replace('[ID]', Arr::field($filesRS_current_row, 'ID'), $sFileContent);
                        }
                    }
                }
                $filesRS = null;
            }
            $fieldsRS = null;
            Cache::set($cacheID, $sFileContent);
        }
    }
    return $sFileContent;
}
// Retrieve the complete name without the domain name
/**
* Lib 404File Retrieve
*
*/
function lib_404File_Retrieve()
{
    global $sNotFoundUrl;
    return str_replace('http://' . Request::server('SERVER_NAME', '') . '/', '', $sNotFoundUrl);
}
// Retrieve a directory name from the
/**
* Lib 404Dir Retrieve
*
*/
function lib_404Dir_Retrieve()
{
    global $sNotFoundUrl;
    $lsBadLink = $sNotFoundUrl;
    // try and find the dir information
    if (substr($lsBadLink, max(0, strlen($lsBadLink) - 1)) == '/') {
        $lsBadLink = substr($lsBadLink, 0, max(0, min((is_null($lsBadLink) ? 0 : strlen($lsBadLink)) - 1, strlen($lsBadLink))));
    }
    return substr($lsBadLink, max(0, (($pos = strrpos($lsBadLink, '/')) !== false ? $pos + 1 : 0) + 1 - 1));
}
// Convert an url formatted as /[type]/[id]/[title] to and ID , on success the ID is returned
/**
* Lib 404 Rewriteurlid
*
*/
function lib_404_rewriteURLID($sType)
{
    global $sNotFoundUrl;
    $sID = '';
    if ((($pos = stripos($sNotFoundUrl, '/' . $sType . '/')) !== false ? $pos + 1 : 0) > 0) {
        $sID = substr($sNotFoundUrl, max(0, (($pos = stripos($sNotFoundUrl, '/' . $sType . '/')) !== false ? $pos + 1 : 0) + (is_null('/' . $sType . '/') ? 0 : strlen('/' . $sType . '/')) - 1));
        $sID = substr($sID, 0, max(0, min((($pos = stripos($sID, '/')) !== false ? $pos + 1 : 0) - 1, strlen($sID))));
    }
    return $sID;
}
/**
* Lib 404Urlremovereference
*
*/
function lib_404UrlRemoveReference($sUrl)
{
    return substr($sUrl, max(0, ((($pos = stripos($sUrl, '404.php?404;')) !== false ? $pos + 1 : 0) > 0 ? (($pos = stripos($sUrl, '404.php?404;')) !== false ? $pos + 1 : 0) + 12 : 1) - 1));
}
?>
