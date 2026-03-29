<?php
/**
 * File Upload Wizard
 *
 * Provides upload functionality for files/images in the wizard.
 */
use App\Library\Application;
use App\Library\File;
use App\Library\Request;
use App\Library\Response;
use App\Library\Server;
use App\Library\Str;

require_once __DIR__ . '/../bootstrap.inc';

Response::noCache();

define("PAGE_WIDTH", 220);
define("LEGEND_MARGIN", 20);

$blnDirFrom = strtolower(Request::query('dir', '')) !== 'n';
$sRootURL = Request::query('basepath', '');
$sSubPath = Request::query('path', '');
$bImage = strtolower(Request::query('image', '')) !== 'n';

if ($sRootURL == '') {
    $sRootURL = '/';
}

$sListURL = Request::addAllToURL('file_list.php');
$sListURL = Request::addToURL($sListURL, 'basepath', $sRootURL);
$sListURL = Request::addToURL($sListURL, 'path', $sSubPath);

echo '<HTML lang="nl"><head>';
cma_error_handler();
echo '<script>(function(){if(window.matchMedia&&window.matchMedia("(prefers-color-scheme:dark)").matches){document.documentElement.style.backgroundColor="#1a1a1a";}})();</script>';
echo '<script>';
echo 'function fCheckFileName(sFileName, bImage) {';
echo '    if (sFileName == "") {';
echo '        return false;';
echo '    }';
echo '    var sExt = sFileName.substring(sFileName.lastIndexOf(".") + 1, sFileName.length).toLowerCase();';
echo '    if (sExt != "gif" && sExt != "jpeg" && sExt != "jpe" && sExt != "jpg" && sExt != "jfif" && sExt != "png" && sExt != "svg" && bImage) {';
echo '        modal_alert("Alleen afbeeldingen met de extentie GIF, PNG, SVG en JPG worden ondersteund!");';
echo '        return false;';
echo '    }';
echo '    waitmsg();';
echo '    return true;';
echo '}';
echo 'function waitmsg() {';
if ($blnDirFrom) {
    echo '    var dirfrm = document.getElementById("dirfrm");';
    echo '    if (dirfrm) dirfrm.style.display = "none";';
}
echo '    document.getElementById("formulier").style.display = "none";';
echo '    document.getElementById("wait").style.display = "block";';
echo '}';
echo 'function fSetImage(sControl, sPath, sFilename, nWidth, nHeight) {';
echo '    parent.selectfile(' . Str::JscriptSafe($sRootURL) . ', ' . Str::JscriptSafe($sSubPath) . ', sFilename);';
echo '}';
echo 'function PlaceImage() {';
echo '    var args = {};';
echo '    var blnModal = false;';
echo '    var scr_width = window.screen.availWidth - 200;';
echo '    var scr_height = window.screen.availHeight - 100;';
echo '    args["mode"] = 2;';
echo '    if (top.window.dialogArguments) {';
echo '        args["height"] = top.window.dialogArguments["resizeheight"];';
echo '        args["width"] = top.window.dialogArguments["resizewidth"];';
echo '    }';
echo '    args["random"] = true;';
echo '    args["path"] = ' . Str::JscriptSafe($sRootURL) . ' + ' . Str::JscriptSafe($sSubPath) . ';';
echo '    var bActiveXControl = false;';
echo '    var sUrl = "../imageupload_crop.php";';
echo '    if (blnModal) {';
echo '        var arr = showModelessDialog(sUrl, args, "dialogWidth:" + scr_width.toString() + "px;dialogHeight:" + scr_height.toString() + "px;status:no;resize:yes;help:no;scroll:no");';
echo '        if (arr) {';
echo '            fSetImage("", "", arr["filename"], arr["width"], arr["height"]);';
echo '        }';
echo '    } else {';
echo '        sUrl = sUrl + "?mode=" + args["mode"].toString() + "&height=" + args["height"] + "&width=" + args["width"] + "&random=" + args["random"] + "&path=" + args["path"];';
echo '        var w = window.screen.availWidth;';
echo '        var h = window.screen.availHeight;';
echo '        var popW = Math.min(w, 1024);';
echo '        var popH = scr_height;';
echo '        var leftPos = (w - popW) / 2, topPos = (h - popH) / 2;';
echo '        var x = window.open(sUrl, "popup_image_wizard", "resizable=yes,scrollbars=no,toolbar=no,status=no,location=no,menubar=no,width=" + popW + ",height=" + popH + ",top=" + topPos + ",left=" + leftPos);';
echo '        if (x) { x.opener = window; x.focus(); }';
echo '    }';
echo '}';
echo '</script>';
echo '</head>';
echo '<BODY style="margin:0px" class="wizardcontent">';

// Handle folder creation
if (Request::post('name_dir', '') != '') {
    $newFolderName = Str::stripIllegalChars(str_replace(' ', '_', Request::post('name_dir', '')));
    $newFolderPath = Server::mapPath($sRootURL . $sSubPath) . '/' . $newFolderName;
    File::createFolder($newFolderPath);
    echo '<script>';
    echo 'parent.frames.list.location = ' . Str::JscriptSafe(Request::addAllToURL('file_list.php')) . ';';
    echo '</script>';
}

echo '<FIELDSET style="width:' . PAGE_WIDTH . 'px;margin:0px">';
echo '<LEGEND>Beeld bijsnijden</LEGEND>';
echo '<INPUT type="button" class="btn btn-primary" value="Afbeelding plaatsen" style="width:100%;margin:0px;" onclick="javascript:PlaceImage()">';
echo '</FIELDSET>';

echo '<FIELDSET style="width:' . PAGE_WIDTH . 'px;margin:0px">';
echo '<LEGEND>Bestand plaatsen</LEGEND>';
echo '<div id="formulier">';

$sRootMap = Server::mapPath($sRootURL);
$sFile = Request::query('file', '');

// Extract subpath from file if present
if (strpos($sFile, '/') !== false) {
    $sSubPath = substr($sFile, 0, strrpos($sFile, '/') + 1);
}

echo '<font class="small">Selecteer \'Bladeren\' om een ';
echo ($bImage ? 'afbeel&shy;ding' : 'bestand');
echo ' te selec&shy;teren. Druk daarna op \'Plaats ';
echo ($bImage ? 'afbeelding' : 'bestand');
echo '\' om het bestand op de server te zetten.<br><br></font>';

$formAction = Request::addAllToURL('file_outputfile.php' . ($bImage ? '' : 'x'));
echo '<FORM METHOD="POST" name="Main" id="Main" style="margin:0px" ENCTYPE="multipart/form-data" ACTION="/cma/wizards/' . htmlspecialchars($formAction) . '" onsubmit=\'return fCheckFileName(this.blob.value, ' . ($bImage ? 'true' : 'false') . ');\'>';
echo '<INPUT TYPE="file" NAME="blob" style="width:' . (PAGE_WIDTH - LEGEND_MARGIN) . 'px"><BR />';
echo '<INPUT TYPE="hidden" NAME="hid_path" VALUE="' . htmlspecialchars($sSubPath) . '">';
echo '<INPUT TYPE="hidden" NAME="resizetype" id="resizetype" VALUE="">';
echo '<INPUT TYPE="hidden" NAME="resizeheight" id="resizeheight" VALUE="">';
echo '<INPUT TYPE="hidden" NAME="resizewidth" id="resizewidth" VALUE="">';
echo '<INPUT TYPE="hidden" NAME="random" id="random" VALUE="J">';

if ($sFile != '') {
    echo '<INPUT TYPE="hidden" NAME="replacefilename" value="' . htmlspecialchars($sFile) . '">';
    echo '<INPUT TYPE="checkbox" NAME="replacefileJN" value="Y"><font style="font-size:var(--font-size-2xs)">Vervang geselecteerde bestand</font><br/><br/>';
} else {
    echo '<INPUT TYPE="checkbox" NAME="OverWriteFile" value="Y"><font style="font-size:var(--font-size-2xs)">Overschrijf bestand</font><br/><br/>';
}

echo '<INPUT TYPE="submit" NAME="Enter" style="width:' . (PAGE_WIDTH - LEGEND_MARGIN) . 'px" VALUE="<- Plaats ' . ($bImage ? 'afbeelding' : 'bestand') . '">';
echo '</FORM>';
echo '</div>';
echo '<div id="wait" style="display:none">Even geduld, het ' . ($bImage ? 'beeld' : 'bestand') . ' wordt op de server geplaatst...</div>';
echo '</FIELDSET>';

echo '<script type="text/javascript">';
if (Request::query('upload', '') != '') {
    // Upload succeeded, now activate file in other frames
    echo 'parent.selectfile(' . Str::JscriptSafe($sRootURL) . ',' . Str::JscriptSafe($sSubPath) . ',' . Str::JscriptSafe($sFile) . ');';
    echo 'parent.frames.list.location = ' . Str::JscriptSafe($sListURL) . ';';
}
echo 'if (top.window.dialogArguments) {';
echo '    document.getElementById("random").value = top.window.dialogArguments["random"];';
echo '    document.getElementById("resizetype").value = top.window.dialogArguments["resizetype"];';
echo '    document.getElementById("resizeheight").value = top.window.dialogArguments["resizeheight"];';
echo '    document.getElementById("resizewidth").value = top.window.dialogArguments["resizewidth"];';
echo '}';
echo '</script>';

if ($blnDirFrom) {
    echo '<DIV id="dirfrm" style="width:' . PAGE_WIDTH . 'px">';
    echo '<FIELDSET style="width:' . PAGE_WIDTH . 'px;margin:0px"><LEGEND>Directory aanmaken</LEGEND>';
    echo '<FORM style="margin:0px" method="post">';
    echo '<INPUT type="text" name="name_dir" style="width:100%">';
    echo '<INPUT type="submit" value="Maak aan" style="width:100%">';
    echo '</FORM>';
    echo '</FIELDSET>';
    echo '</DIV>';
}

echo '</BODY>';
echo '</HTML>';
?>
