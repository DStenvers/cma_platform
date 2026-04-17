<?php
use App\Library\LibUpload;
use App\Library\Request;
use App\Library\Response;
use App\Library\Server;
use Cma\FormControlHelper;

require_once __DIR__ . '/../bootstrap.inc';

/**
* Main
*/
function main()
{
    Response::noCache();
    set_time_limit(999999);
    $sRootURL = Request::query('basepath', '');
    $bImage = Request::query('image', '') != '';
    $sSubPath = null;
    $sFullLocalPath = null;
    $sURL = null;
    $sResizeType = null;
    $sNewname = null;
    $filename = null;
    $lCurWidth = null;
    $lCurHeight = null;
    $lCurRatio = null;
    $lReqWidth = null;
    $lReqHeight = null;
    $lReqRatio = null;
    $sFullName = null;
    if ($sRootURL == '') {
        $sRootURL = '/';
    }
    $objUpload = new LibUpload();
    $sSubPath = $objUpload->objUpload('hid_path')->value;
    $objUpload->Random = (is_null($objUpload->objUpload('random')->value) ? "" : strtoupper($objUpload->objUpload('random')->value)) == 'Y';
    $objUpload->Path = $sRootURL . $sSubPath;
    $objUpload->Fieldname = 'blob';

    // Detect overwrite: replace selected file with uploaded file
    $bOverwrite = false;
    if (($_POST['replacefilename'] ?? '') !== '' && ($_POST['replacefileJN'] ?? '') !== '') {
        $objUpload->Filename = basename($_POST['replacefilename']);
        $bOverwrite = true;
    }

    $objUpload->Save();
    $filename = $objUpload->filename();
    $sResizeType = $objUpload->objUpload('resizetype')->value;
    if ($sResizeType == strval(FormControlHelper::IMG_MAXIMUM) || $sResizeType == strval(FormControlHelper::IMG_FIXED)) {
        $sFullName = $sRootURL . $sSubPath . $filename;
        $lReqHeight = intval(round(floatval($objUpload->objUpload('resizeheight')->value)));
        $lReqWidth = intval(round(floatval($objUpload->objUpload('resizewidth')->value)));
        $dummy = null;
        gfxSpex(Server::mapPath($sFullName), $lCurWidth, $lCurHeight, $dummy, $dummy);
        if ($lCurWidth!= '' && $lCurHeight!= '') {
            if ($sResizeType == strval(FormControlHelper::IMG_FIXED)) {
                $lCurRatio = round($lCurWidth / $lCurHeight, 2);
                $lReqRatio = round($lReqWidth / $lReqHeight, 2);
                if ($lReqRatio != $lCurRatio) {
                    echo '				<html>';
                    echo '				<body class="wizardbody">';
                    echo '				<script type="text/javascript">;
                    alert(\'Het formaat van de afbeelding (hoogte/breedte verhouding) komt niet overeen met de vereiste afmetingen van ' . ($lReqWidth) . '' . 'pixels breed bij ' . ($lReqHeight) . '' . 'pixels hoog!\');
                    window.location = \'' . (Request::addAllToURL("file_upload.php")) . '' . '\';
                    </script>';
                    echo '				</body>';
                    echo '				</html>';
                    exit();
                }
            }
            if ($lReqHeight != $lCurHeight || $lReqWidth != $lCurWidth) {
                $info = pathinfo($filename);
                $sNewname = $info['filename'] . '_chk.' . $info['extension'];
                Image::thumbnail($sFullName, $sRootURL . $sSubPath . $sNewname, $lReqHeight, $lReqWidth);
                if (file_exists(Server::mapPath($sRootURL . $sSubPath . $sNewname))) {
                    $filename = $sNewname;
                }
            }
        }
    }
    $objUpload = null;

    // Cache busting: append ?versie= timestamp so browsers reload overwritten files
    $sFileParam = $bOverwrite ? $filename . '?versie=' . time() : $filename;

    $sURL = Request::addAllToURL('file_upload.php');
    $sURL = Request::addToURL($sURL, 'file', $sFileParam);
    $sURL = Request::addToURL($sURL, 'upload', 'true');
    $sURL = Request::addToURL($sURL, 'filename', $sFileParam);
    Response::redirect($sURL);
}
// Call main function
main();
?>
