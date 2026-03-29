<?php
/**
* Main
*/
use App\Library\Application;
use App\Library\Cache;
use App\Library\Database;
use App\Library\Error;
use App\Library\Response;

/**
 * Main
 */
function main()
{
    Response::noCache();
    // Dit script bevat alle taken die 1x per dag kunnen worden uitgevoerd
    // Melding op lege databronnen
    $SQL = null;
    $intAantalopPeilDatum = 0;
    $objMail = null;
    $sNotification = null;
    $sStoreName = null;
    set_time_limit(99999);
    // ophalen alle meldingen die gewenst zijn
    $SQL = 'SELECT tblUserDataNotifications.fkStore, tblUserDataNotifications.notAantalDagen, tblUserDataNotifications.notParameter, tblUserDataNotifications.notBeschrijving, tblUsers.userFullName, tblUsers.userEMail FROM tblUsers INNER JOIN tblUserDataNotifications ON tblUsers.ID = tblUserDataNotifications.fkUser';
    $RS = Database::openRS($SQL, 'users', adOpenForwardOnly, 3);
    if ($RS === null) {
        throw new \Exception('Database query failed: ' . Database::getLastError());
    }
    while (!$RS->EOF) {
        $rs_current_row = $RS->fields;
        $sStoreName = (is_null(XMLStore_GetName($RS->fields['fkStore'])) ? "" : strtoupper(XMLStore_GetName($RS->fields['fkStore'])));
        $intAantalopPeilDatum = XMLStore_RecordCount($sStoreName, $RS->fields['notParameter'], $RS->fields['notAantalDagen']);
        if ($intAantalopPeilDatum == 0 && XMLStore_RecordCount($sStoreName, $RS->fields['notParameter'], $RS->fields['notAantalDagen'] - 1) > 0) {
            $sNotification = 'Over <b>' . $RS->fields['notAantalDagen'] . ' dagen (' . (is_null(str_replace(' ' . date("Y", strtotime(date("Y-m-d"))), '', date("F j, Y", strtotime(dateAdd('d', $RS->fields['notAantalDagen'], date("Y-m-d")))))) ? "" : strtolower(str_replace(' ' . date("Y", strtotime(date("Y-m-d"))), '', date("F j, Y", strtotime(dateAdd('d', $RS->fields['notAantalDagen'], date("Y-m-d"))))))) . ')</b> is de informatie in de gegevensbron <b>' . $sStoreName . "</b> verlopen.<br><br><font style=font-size:var(--font-size-2xs)>Je ontvangt dit bericht als een service om te voorkomen dat er lege elementen op je site ontstaan. Eventueel kun je ook de bijbehorende pagina's uitschakelen.</font>";
            $objMail = new LibMailer();
            $objMail->CMATemplate = true;
            $objMail->Body = $sNotification;
            $objMail->Subject = 'Tools CMA gegevensmelding (' . Application::get('company', '') . ')';
            $objMail->AddRecipients($RS->fields['userEMail']);
            $objMail->From = 'diederik@stenversonline.nl';
            $objMail->AddRecipientBCC('diederik@stenversonline.nl');
            if (!$objMail->Send()) {
                Error::page('Error', $objMail->strError, true);
            }
            $objMail = null;
        }
        $RS->MoveNext();
    }
    // Complete cache leegmaken
    Cache::delete('');
    Cache::clearAllFiles();
}
// Call main function
main();
?>
