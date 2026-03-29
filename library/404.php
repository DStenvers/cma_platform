<?php
/**
 * 404 Error Handler
 *
 * PHP replacement for ASP's 404.asp.
 * Handles friendly URL resolution, hack detection, directory scanning,
 * and fallback to site-specific 404.inc.
 */
use App\Library\Application;
use App\Library\Arr;
use App\Library\Profiler;
use App\Library\Request;
use App\Library\Response;
use App\Library\Server;

function main()
{
    $blnDebug_404 = false;

    // Retrieve the URL without error and port indicators
    $sNotFoundUrl = trim(str_replace('%20', ' ', str_replace(':80', '', str_replace('404;', '', Request::server('QUERY_STRING')))));
    $s404NewURL = $sNotFoundUrl;

    // Ignore list for bots, hacking attempts, common non-pages
    $sIgnoreFiles = 'mailto:,tel:,/old,/new,/backup,/bak,/wp,/wordpress,/bk,menuicons,.cfm,nyet.gif,.json,-advice,.map,config,.env,.xml,phpmyadmin,humans.txt,verify-,is.local/pages/,crossdomain.xml,labels.rdf,(null),url(data:,loader.aspx,favicon,apple-touch-icon,robots.txt,javascript:,_vti_,MSOffice/cltreq.php,onsubmit=,boxsizing.htc,com_phpshop,ct=http://,.smi,fckeditor,autodiscover.xml';
    $arrIgnoreFiles = Arr::splitAlways($sIgnoreFiles, ',');
    $sDirectoryFound = '';

    // Include custom 404 handling rules (site-specific)
    $custom404 = __DIR__ . '/../404.inc';
    if (file_exists($custom404)) {
        include $custom404;
    }

    Profiler::start();

    if (empty($sNotFoundUrl)) {
        // Direct call - nothing to do
        Profiler::end();
        return;
    }

    // Fix URL-encoded parameters
    $s404NewURL = str_ireplace('%3F', '?', $s404NewURL);
    $s404NewURL = str_ireplace('%3D', '=', $s404NewURL);
    $s404NewURL = str_ireplace('%26', '&', $s404NewURL);
    $s404NewURL = str_replace('&amp;', '&', $s404NewURL);
    if ($s404NewURL !== $sNotFoundUrl) {
        Response::redirect($s404NewURL);
    }

    // Extension image fallback
    if (stripos($s404NewURL, '/library/images/ext/ext_') !== false) {
        Response::redirect('/library/images/ext/ext_.gif');
        exit();
    }

    // Directory with query string but no file - try index.php / default.php
    if (stripos($s404NewURL, '/?') !== false) {
        $basePath = Application::get('base_path', '/');
        $docRoot = Request::server('DOCUMENT_ROOT');
        if (file_exists($docRoot . $basePath . 'index.php')) {
            Response::redirect(str_replace('/?', '/index.php?', $s404NewURL));
        } elseif (file_exists($docRoot . $basePath . 'default.php')) {
            Response::redirect(str_replace('/?', '/default.php?', $s404NewURL));
        } else {
            exit();
        }
    }

    // Check ignore list
    if (Arr::findInstr($arrIgnoreFiles, $sNotFoundUrl) > -1) {
        if ($blnDebug_404) {
            echo 'Ignoring<br> ';
        }
        http_response_code(404);
        exit();
    }

    // Database-driven directory/marketing URL scanning
    if (function_exists('lib_404File_Retrieve') && function_exists('lib_404ScanDir')) {
        $sDirectoryFound = lib_404ScanDir(lib_404File_Retrieve());
        if ($sDirectoryFound === '') {
            $sDirectoryFound = lib_404ScanDir(lib_404Dir_Retrieve());
        }
    }

    if ($sDirectoryFound !== '') {
        if ($blnDebug_404) {
            echo htmlspecialchars($sDirectoryFound);
        } else {
            echo $sDirectoryFound;
        }
        http_response_code(200);
    } else {
        // Gmail space fix
        if (strpos($sNotFoundUrl, '+') !== false) {
            Response::redirect(str_replace('+', ' ', $sNotFoundUrl));
        }

        // Skip search engines
        if (function_exists('lib_isSearchEngine') && lib_isSearchEngine()) {
            if ($blnDebug_404) {
                echo 'Search engine found.<br>';
            }
        } else {
            // Default 404 response
            http_response_code(404);
            echo '<!DOCTYPE html><html><head>';
            echo '<title>Pagina niet gevonden</title>';
            echo '<style>body { font-family: Tahoma, Verdana, sans-serif; margin: 40px; }</style>';
            echo '<meta name="robots" content="noindex">';
            echo '</head><body>';
            echo '<h1 style="color:#333; border-bottom:1px solid #333; padding-bottom:10px;">Pagina niet gevonden (404)</h1>';
            echo '<p>De pagina die u zoekt bestaat niet of is verplaatst.</p>';
            echo '<p><a href="/">Ga naar de homepage</a></p>';
            echo '</body></html>';
        }
    }

    Profiler::end();
}

main();
?>
