<?php
// Depending upon application setting will use the username from the posted page or from the logon
/**
* Lib Getuser
*
*/
use App\Library\Application;
use App\Library\Cookie;
use App\Library\Database;
use App\Library\Request;

/**
 * Lib Getuser
 */
function lib_getuser($strFormField)
{
    if (Application::get('askuser', '')) {
        return Request::post($strFormField, '');
    } else {
        return lib_remoteuser();
    }
}
// Will directly retrieve the remote user and remove any workgroup or extention attached to it
/**
* Lib Remoteuser
*
*/
function lib_remoteuser()
{
    $strtmp = "";
    $intpos = 0;
    $strtmp = (is_null(Request::server('REMOTE_USER', '')) ? "" : strtolower(Request::server('REMOTE_USER', '')));
    if ($strtmp == '') {
        $strtmp = 'onbekend';
    } else {
        $intpos = (($pos = stripos($strtmp, '/')) !== false ? $pos + 1 : 0);
        if ($intpos == 0) {
            $intpos = (($pos = stripos($strtmp, '\\')) !== false ? $pos + 1 : 0);
        }
        if ($intpos > 0) {
            $strtmp = substr($strtmp, max(0, $intpos + 1 - 1));
        }
        $intpos = (($pos = stripos($strtmp, '.')) !== false ? $pos + 1 : 0);
        if ($intpos > 0) {
            $strtmp = substr($strtmp, 0, max(0, min($intpos - 1, strlen($strtmp))));
        }
    }
    return $strtmp;
}
// Depending upon application setting will use the email address from the posted page or compile it from the logon
/**
* Lib Getemail
*
*/
function lib_getemail($strFormField)
{
    if (Application::get('askemail', '')) {
        return Request::post($strFormField, '');
    } else {
        return lib_remoteuser() . Application::get('emailpostfix', '');
    }
}
// Determined if the current user had supervisor priviliges for a specific part of the intranet
/**
* Lib Is Supervisor
*
*/
function lib_is_supervisor($strSub)
{
    if (Application::get('askuser', '')) {
        return Cookie::get('Supervisor', '') == 'Yes';
    } else {
        return (($pos = stripos((is_null(Application::get('SUP_' . (is_null($strSub) ? "" : strtoupper($strSub)), '')) ? "" : strtolower(Application::get('SUP_' . (is_null($strSub) ? "" : strtoupper($strSub)), ''))), (is_null('|' . lib_remoteuser() . '|') ? "" : strtolower('|' . lib_remoteuser() . '|')))) !== false ? $pos + 1 : 0) > 0;
    }
}
// Determined if the current user has Delete/Change rights to a specific record identified by the parameters
/**
* Lib Check Rights From Table
*
*/
function lib_check_rights_from_table($strSub, $objConn, $strTable, $strIDField, $strIDValue, $strUserField)
{
    $strSQL = "";
    $recSet = null;
    $blnSupervisor = null;
    $blnRetval = null;
    $blnSupervisor = lib_is_supervisor($strSub);
    $blnRetval = $blnSupervisor;
    if (!Application::get('askuser', '')) {
        if (!$blnSupervisor) {
            $strSQL = 'SELECT ' . $strUserField . ' from ' . $strTable . ' where ' . $strIDField . '=' . STRIDVALUE;
            $recSet = Database::openRS();
            strSQL();
            objConn();
            if (!$recSet->eof) {
                $recSet->movefirst();
                $blnRetval = (is_null($recSet->fields($strUserField)) ? "" : strtolower($recSet->fields($strUserField))) == lib_remoteuser();
            }
            $recSet->close();
            $recSet = null;
        }
    }
    return $blnRetval;
}
?>
