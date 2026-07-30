<?php

// Include-guard: dit bestand weigert zichzelf een tweede keer uit te voeren.
// require_once dekt het gewone geval al — die lost het pad eerst op — maar niet een
// kale require/include, en niet een pad dat via een junction anders uitkomt. De naam
// is uniek per bestand, zodat de guard niet van de aanroeper afhangt.
if (defined('INC_LIB_SENDMAIL')) { return; }
define('INC_LIB_SENDMAIL', 1);
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
