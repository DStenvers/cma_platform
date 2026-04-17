<?php
use App\Library\Error;
use App\Library\Request;
use App\Library\Response;
use App\Library\Server;
use App\Library\Str;

require_once __DIR__ . '/../bootstrap.inc';

/**
* Main
*/
function main()
{
    Response::noCache();
    echo '<HTML lang="nl"><head>';
    cma_error_handler();
    echo '<script>(function(){if(window.matchMedia&&window.matchMedia("(prefers-color-scheme:dark)").matches){document.documentElement.style.backgroundColor="#1a1a1a";}})();</script>';
    echo '<BODY class=wizardcontent> ';
    // Reconstruct path server-side from basepath — never trust a full filesystem path from POST
    $basePath = Request::query('basepath', '');
    $subPath = Request::query('path', '');
    $fileName = basename(strtok(Request::post('file', ''), '?'));
    $sFile = Server::mapPath($basePath . $subPath) . DIRECTORY_SEPARATOR . $fileName;

    // Verify path is within the allowed base directory
    $allowedRoot = realpath(Server::mapPath($basePath));
    $resolvedFile = realpath(dirname($sFile)) . DIRECTORY_SEPARATOR . $fileName;
    if ($allowedRoot === false || !str_starts_with($resolvedFile, $allowedRoot)) {
        Error::show('Ongeldige bestandslocatie.');
        echo '</BODY></HTML>';
        return;
    }

    if (file_exists($sFile)) {
        @unlink($sFile);
        echo '<script>';
        echo 'parent.selectfile(' . Str::JscriptSafe(Request::query('basepath', '')) . ',' . Str::JscriptSafe(Request::query('basepath', '')) . ",'');";
        echo "parent.list.location='" . Request::addAllToURL('file_list.php') . "';";
        echo "window.location='" . Request::addAllToURL('file_controls.php') . "';";
        echo '</script>';
    } else {
        Error::show("Bestand '" . Request::post('path', '') . Request::post('file', '') . "' niet gevonden.");
    }
    echo '</BODY></HTML>';
}
// Call main function
main();
?>
