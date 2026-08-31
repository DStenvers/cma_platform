<?php
use App\Library\Arr;
use App\Library\Cache;
use App\Library\Database;
use App\Library\Error;
use App\Library\Html;
use App\Library\Request;
use App\Library\Response;
use App\Library\Server;
use Cma\SecurityHelper;

require_once __DIR__ . '/bootstrap.inc';

// The sidebar shell (main.php) is the only layout — classic tabs retired.
if (SecurityHelper::isLoggedIn()) {
    // Legacy deep link: default.asp/default.php?FormID=<n>&ID=<x>. That shape
    // still sits in old CMA-monitoring notifications, mails and bookmarks, and
    // reaches this file either directly or via the site's .asp->.php rewrite.
    // Hand the parameters to the shell instead of dropping them on the floor:
    // main.php loads form.php with them and FormRoute resolves the numeric
    // FormID to the JSON form name.
    $legacyFormId = (int) Request::queryInt('FormID');
    if ($legacyFormId > 0) {
        $legacyRecordId = Request::queryId('ID');
        header('Location: /cma/main.php?page=form.php&FormID=' . $legacyFormId
            . ($legacyRecordId !== '' ? '&ID=' . $legacyRecordId : ''));
        exit;
    }

    // Land on the main.php shell directly (it defaults to the dashboard) rather
    // than the /cma/dashboard friendly-URL, which only resolves when URL
    // Rewrite is working. main.php always loads.
    header('Location: /cma/main.php');
    exit;
}

// Not logged in - redirect to login.php
header('Location: /cma/login.php');
exit;
?>
