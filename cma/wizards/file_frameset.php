<?php
/**
 * File Frameset Wizard
 *
 * Provides a frameset for file browsing with:
 * - list: File listing frame
 * - upload: Optional upload frame
 * - controls: File controls/preview frame
 */
use App\Library\Application;
use App\Library\Request;
use App\Library\Response;
use App\Library\Str;

/**
 * Main
 */
function main()
{
    Response::noCache();

    $parBasePath = Request::query('basepath', '');
    $parPath = Request::query('path', '');
    $parFile = Request::query('file', '');
    $blnUpload = Request::query('upload', '') != '';

    // Extract path from file if present
    $sPath = $parFile;
    if (strpos($sPath, '/') !== false) {
        // Get path up to last slash (excluding trailing slash)
        $sPath = substr($sPath, 0, strrpos(rtrim($sPath, '/'), '/') + 1);
    } else {
        $sPath = '';
    }

    // Build base URLs for frames
    $sBaseList = Request::addToURL(Request::addAllToURL(Application::get('base_path', '') . 'cma/wizards/file_list.php'), 'path', $sPath);
    $sBaseControls = Request::addToURL(Application::get('base_path', '') . 'cma/wizards/file_controls.php', 'image', (Request::query('image', '') != '' ? Request::query('image', '') : 'n'));
    $sBaseUpload = Request::addToURL(Application::get('base_path', '') . 'cma/wizards/file_upload.php', 'image', (Request::query('image', '') != '' ? Request::query('image', '') : 'n'));

    echo '<html lang="nl"><head>';
    echo '<script src="/library/error-handler.js"></script>';
    echo '<script>(function(){if(window.matchMedia&&window.matchMedia("(prefers-color-scheme:dark)").matches){document.documentElement.style.backgroundColor="#1a1a1a";}})();</script>';
    echo '<script type="text/javascript">';
    echo 'var filename_elt = (parent.document.getElementById("filename") ? parent.document.getElementById("filename") : ( parent.filename ? parent.filename : (top.document.getElementById("content") && top.document.getElementById("content").contentDocument ? top.document.getElementById("content").contentDocument.getElementById("filename") : null)));';

    // Set initial filename value if file parameter provided
    if ($parFile != '') {
        echo 'if (filename_elt) {';
        echo '    filename_elt.value = ' . Str::JscriptSafe($parPath . $parFile) . ';';
        echo '} else {';
        echo '    console.log("Kan filename element niet vinden");';
        echo '}';
    }

    // JavaScript function to select a file
    echo 'function selectfile(basepath, path, filespec) {';
    echo '    var strNewPath;';
    echo '    if (frames["controls"]) {';
    echo '        strNewPath = ' . Str::JscriptSafe($sBaseControls) . ' + "&basepath=" + basepath + "&path=" + path + "&file=" + filespec;';
    echo '        if (frames["controls"].document.location != strNewPath) frames["controls"].document.location.assign(strNewPath);';
    echo '    }';
    echo '    if (frames["upload"]) {';
    echo '        strNewPath = ' . Str::JscriptSafe($sBaseUpload) . ' + "&basepath=" + basepath + "&path=" + path + "&file=" + filespec;';
    echo '        if (frames["upload"].document.location != strNewPath) frames["upload"].document.location.assign(strNewPath);';
    echo '    }';
    echo '    if (filename_elt) filename_elt.value = path + filespec;';
    echo '}';

    // JavaScript function to select a path/folder
    echo 'function selectpath(basepath, path) {';
    echo '    if (frames["upload"]) {';
    echo '        frames["upload"].document.location = ' . Str::JscriptSafe($sBaseUpload) . ' + "&path=" + path + "&basepath=" + basepath;';
    echo '    }';
    echo '    if (frames["controls"]) {';
    echo '        frames["controls"].document.location = ' . Str::JscriptSafe($sBaseControls) . ' + "&path=" + path + "&basepath=" + basepath;';
    echo '    }';
    echo '}';

    echo '</script>';
    echo '</head>';

    // Build frameset
    echo '<frameset rows="*,125" frameborder="0" border="0">';

    if ($blnUpload) {
        // With upload: two-column layout for list + upload
        echo '<frameset cols="*,220" frameborder="0" border="0">';
        $listSrc = Request::addToURL(Request::addToURL($sBaseList, 'dummy', date("H:i:s")), 'path', $sPath);
        echo '<frame src="' . htmlspecialchars($listSrc) . '" name="list" noresize border="0" frameborder="0">';
        $uploadSrc = Request::addToURL(Request::addToURL($sBaseUpload, 'path', $sPath), 'basepath', $parBasePath);
        echo '<frame src="' . htmlspecialchars($uploadSrc) . '" name="upload" noresize border="0" frameborder="0">';
        echo '</frameset>';
    } else {
        // Without upload: just the list frame
        $listSrc = Request::addToURL(Request::addToURL($sBaseList, 'dummy', date("H:i:s")), 'path', $sPath);
        echo '<frame src="' . htmlspecialchars($listSrc) . '" name="list" noresize border="0" frameborder="0">';
    }

    // Controls frame at bottom
    $controlsSrc = Request::addToURL(Request::addAllToURL(Application::get('base_path', '') . 'cma/wizards/file_controls.php'), 'path', $sPath);
    echo '<frame src="' . htmlspecialchars($controlsSrc) . '" name="controls" noresize border="0" frameborder="0">';

    echo '</frameset>';
    echo '</html>';
}

// Call main function
main();
?>
