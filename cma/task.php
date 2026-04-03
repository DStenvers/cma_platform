<?php
/**
* Main - Daily scheduled tasks
*/
use App\Library\Application;
use App\Library\Cache;
use App\Library\Database;
use App\Library\Email;
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

    // NOTE: XMLStore_GetName, XMLStore_RecordCount and dateAdd were legacy ASP functions
    // that were never converted to PHP. The data notification feature is currently non-functional.
    // TODO: Reimplement data source expiry notifications using a PHP-native approach.
    // For now, log a warning and skip the notification logic to prevent fatal errors.

    // ophalen alle meldingen die gewenst zijn
    $SQL = 'SELECT tblUserDataNotifications.fkStore, tblUserDataNotifications.notAantalDagen, tblUserDataNotifications.notParameter, tblUserDataNotifications.notBeschrijving, tblUsers.userFullName, tblUsers.userEMail FROM tblUsers INNER JOIN tblUserDataNotifications ON tblUsers.ID = tblUserDataNotifications.fkUser';
    $RS = Database::openRS($SQL, 'users', adOpenForwardOnly, 3);
    if ($RS === null) {
        error_log('[task.php] Database query for notifications failed: ' . Database::getLastError());
    } else {
        while (!$RS->EOF) {
            try {
                $rs_current_row = $RS->fields;
                $notDagen = (int)$RS->fields['notAantalDagen'];
                $futureDate = date('F j, Y', strtotime('+' . $notDagen . ' days'));
                // Strip current year for brevity
                $futureDateShort = strtolower(str_replace(' ' . date('Y'), '', $futureDate));

                // Build notification message
                $sNotification = 'Over <b>' . $notDagen . ' dagen (' . $futureDateShort . ')</b> is de informatie in de gegevensbron <b>' . htmlspecialchars($RS->fields['notBeschrijving'] ?? '') . "</b> verlopen.<br><br><font style=font-size:var(--font-size-2xs)>Je ontvangt dit bericht als een service om te voorkomen dat er lege elementen op je site ontstaan. Eventueel kun je ook de bijbehorende pagina's uitschakelen.</font>";

                $objMail = new Email();
                $objMail->setCMATemplate(true);
                $objMail->setBody($sNotification);
                $objMail->setSubject('Tools CMA gegevensmelding (' . Application::get('company', '') . ')');
                $objMail->addRecipient($RS->fields['userEMail']);
                $objMail->setFrom('diederik@stenversonline.nl');
                $objMail->addRecipientBCC('diederik@stenversonline.nl');
                if (!$objMail->send()) {
                    error_log('[task.php] Failed to send notification email to ' . $RS->fields['userEMail'] . ': ' . $objMail->getError());
                }
                $objMail = null;
            } catch (\Throwable $e) {
                error_log('[task.php] Error processing notification for user ' . ($RS->fields['userEMail'] ?? 'unknown') . ': ' . $e->getMessage());
            }
            $RS->MoveNext();
        }
    }
    // Complete cache leegmaken
    Cache::delete('');
    Cache::clearAllFiles();
}
// Call main function
main();
?>
