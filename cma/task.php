<?php
/**
* Main - Daily scheduled tasks
*/
use App\Library\Application;
use App\Library\Cache;
use App\Library\Database;
use App\Library\Error;
use App\Library\Response;

// Without this, a request where IIS auto_prepend didn't fire fatals on the
// first Response::* call → HTTP 500. This also re-instates bootstrap's login
// gate. NOTE: this is a daily batch job with side effects (sends e-mail, flushes
// the whole cache); it should be moved to CLI-only — see status report.
require_once __DIR__ . '/bootstrap.inc';

/**
 * Main
 */
function main()
{
    Response::noCache();
    // Dit script bevat alle taken die 1x per dag kunnen worden uitgevoerd
    // Melding op lege databronnen
    $SQL = null;
    set_time_limit(99999);

    // Data-source expiry alerts (tblUserDataNotifications) are retired. The classic
    // task mailed a subscriber only when an XML store had no records on day N while it
    // still had some on day N-1 (XMLStore_RecordCount); the XML-store runtime was never
    // ported, so that condition cannot be evaluated here. The port mailed every
    // subscription on every run instead. No mail is sent; a subscription that is still
    // in the table is logged once per run so it is not forgotten.
    $RS = Database::openRS('SELECT tblUserDataNotifications.fkStore, tblUserDataNotifications.notBeschrijving, tblUsers.userEMail FROM tblUsers INNER JOIN tblUserDataNotifications ON tblUsers.ID = tblUserDataNotifications.fkUser', 'users', adOpenForwardOnly, 3);
    if ($RS !== null) {
        while (!$RS->EOF) {
            error_log('[task.php] gegevensmelding overgeslagen (XML-store-controle niet beschikbaar): store ' . ($RS->fields['fkStore'] ?? '?') . ' voor ' . ($RS->fields['userEMail'] ?? '?'));
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
