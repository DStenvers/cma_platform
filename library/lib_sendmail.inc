<?php
// For compatibility reasons (older sites do not use the class so i created rewrote this function as a wrapper function of the class)
/**
* Lib Sendemail
*
*/
use App\Library\Application;

/**
 * Lib Sendemail
 */
function lib_SendEMail($strFrom, $strFromEMail, $strToName, $strToEMail, $strSubject, $strBody, $blnBCCWebmaster)
{
    $objMail = null;
    $strRetval = "";
    $objMail = new LibMailer();
    $objMail->Body = str_replace(PHP_EOL, '<BR>', $strBody);
    $objMail->Subject = $strSubject;
    $objMail->From = $strFromEMail;
    $objMail->FromName = $strFrom;
    $objMail->AddRecipients($strToEMail);
    if (BLNBCCWEBMASTER && Application::get('emailpostfix', '')!= '') {
        $objMail->AddRecipientBCC();
    }
    if (!$objMail->Send()) {
        $strRetval = $objMail->strError;
    }
    $objMail = null;
    return $strRetval;
}
?>
