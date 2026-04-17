<?php
/**
 * File Controls Wizard
 *
 * Shows details and preview of the selected file in the wizard.
 */
use App\Library\Application;
use App\Library\Request;
use App\Library\Response;
use App\Library\Server;
use App\Library\Str;

require_once __DIR__ . '/../bootstrap.inc';

Response::noCache();

// Get parameters
$sRootURL = Request::query('basepath', '');
$bImage = Request::query('image', '') != '';
$sSubPath = Request::query('path', '');
$sFile = strtok(Request::query('file', ''), '?'); // Strip ?versie= cache buster

// Check if file is an image
$ext = strtolower(pathinfo($sFile, PATHINFO_EXTENSION));
$isImage = in_array($ext, ['gif', 'jpg', 'jpeg', 'png', 'svg']);

// Fix wrong slashes
$sFile = str_replace('\\', '/', $sFile);
$sRootURL = str_replace('\\', '/', $sRootURL);

// Extract just the filename if path is included
if (strpos($sFile, '/') !== false) {
    $sFile = basename($sFile);
}

// Map root URL to server path
if ($sRootURL == '') {
    $sRootMap = '';
} else {
    if (substr($sRootURL, 0, 1) != '/') {
        $sRootURL = '/' . $sRootURL;
    }
    $sRootMap = Server::mapPath($sRootURL);
}

// Get image dimensions if applicable
$sWidthHeight = '';
$sWidth = '';
$sHeight = '';

if ($sRootURL . $sSubPath . $sFile != '' && $isImage) {
    myGetImageSize($sRootURL . $sSubPath . $sFile, $sWidthHeight, $sWidth, $sHeight);
}

// Build actual file path
$actualFilePath = $sRootURL . $sSubPath;
$actualFilePath = str_replace('//', '/', $actualFilePath);

if ($actualFilePath != '') {
    $mappedPath = Server::mapPath($actualFilePath);
} else {
    $mappedPath = '';
}

$sFullname = str_replace('//', '/', $sSubPath . '/' . $sFile);
$bFileExists = ($sFile != '' ? file_exists($sRootMap . $sFullname) : false);

// Start HTML output
echo '<!DOCTYPE HTML>' . PHP_EOL;
echo '<HTML lang="nl"><head>' . PHP_EOL;
cma_error_handler();
echo '<script>(function(){if(window.matchMedia&&window.matchMedia("(prefers-color-scheme:dark)").matches){document.documentElement.style.backgroundColor="#1a1a1a";}})();</script>' . PHP_EOL;
echo '<script type="text/javascript">' . PHP_EOL;
echo '    function showPreview(sName) {' . PHP_EOL;
echo '        document.getElementById("img_preview").src=sName;' . PHP_EOL;
echo '    }' . PHP_EOL;
echo '</script>' . PHP_EOL;
echo '</head>' . PHP_EOL;
echo '<BODY style="margin:0px" class="wizardcontent">' . PHP_EOL;

echo '<fieldset style="width:100%;height:auto;margin:0px;padding-top:0px;padding-left:8px;padding-right:8px;padding-bottom:0px"><legend>Details geselecteerde bestand</legend>' . PHP_EOL;
echo '<table cellpadding="0" cellspacing="0" style="width:100%;height:90px">' . PHP_EOL;
echo '<tr><td valign="top" style="width:10%;text-align:center;padding-top:5px">' . PHP_EOL;

if ($isImage) {
    if ($sFile != '' && $bFileExists) {
        echo '<img style="cursor:pointer;border:1px dotted #6699CC" onclick="javascript:lib_window_ImageZoom(img_preview.src,\'\')" id="img_preview" src="' . htmlspecialchars(Application::get('pict_pixel', '')) . '" ' . $sWidthHeight . ' align="top" alt="Bekijk afbeelding">';
    } else {
        echo '<br><br>[Hier&nbsp;komt&nbsp;<br>een&nbsp;voorbeeld]';
    }
} else {
    if ($sFile != '') {
        echo '<input type="button" class="btn btn-primary" onclick="window.open(\'' . htmlspecialchars($sRootURL . $sSubPath . $sFile) . '\')" value="Open bestand" style="width:90px">';
    }
}

echo '</td>' . PHP_EOL;
echo '<td valign="top" width="90%" valign="absmiddle" rowspan="2" style="padding-left:6px">' . PHP_EOL;

if ($sFile != '') {
    echo '<table cellpadding="2" cellspacing="0" style="width:100%">' . PHP_EOL;
    echo '<tr><td style="width:1%" nowrap>Naam bestand&nbsp;</TD>' . PHP_EOL;
    echo '<td style="width:99%"><INPUT type="text" id="txt_filename" name="txt_filename" value="' . htmlspecialchars($sFile) . '" readonly style="border:0px;width:100%;font-weight:bold;height:auto;padding:0px"></td></tr>' . PHP_EOL;

    if ($bFileExists) {
        $fullFilePath = $sRootMap . $sFullname;
        $fileSize = round(filesize($fullFilePath) / 1024, 0);
        $dateCreated = date('Y-m-d H:i:s', filectime($fullFilePath));
        $dateModified = date('Y-m-d H:i:s', filemtime($fullFilePath));

        echo '<tr><td>Grootte</TD><TD nowrap><b>' . $fileSize . '</b> Kb</TD></tr>' . PHP_EOL;
        echo '<tr><td>Datums</TD><TD>Aangemaakt <b>' . $dateCreated . '</b>, gewijzigd <b>' . $dateModified . '</b></TD></tr>' . PHP_EOL;

        if ($isImage) {
            echo '<tr><td>Afmetingen</TD><TD>hoogte <b>' . $sHeight . '</B>px, breedte <B>' . $sWidth . '</B>px</TD></tr>' . PHP_EOL;
            echo '<script>top.content.document.getElementById("imgwidth").value=\'' . $sWidth . '\';top.content.document.getElementById("imgheight").value=\'' . $sHeight . '\';</script>' . PHP_EOL;
        }
    } else {
        echo '<tr><td colspan="9"><font color="RED">Bestand \'' . htmlspecialchars($sRootMap . $sFullname) . '\' niet gevonden of de bestandsnaam is te lang..</font></td></tr>';
    }
    echo '</table>';
} else {
    echo '&nbsp;';
}

echo '</td></tr>' . PHP_EOL;
echo '<tr><td valign="top" align="center">' . PHP_EOL;

if ($sFile != '' && $bFileExists) {
    echo '<form id="delform" name="delform" style="margin:0px" action="' . htmlspecialchars(Request::addAllToURL('file_controls_delete.php')) . '" method="post">' . PHP_EOL;
    echo '<input type="hidden" name="path" value="' . htmlspecialchars($mappedPath) . '">' . PHP_EOL;
    echo '<input type="hidden" name="file" value="' . htmlspecialchars($sFile) . '">' . PHP_EOL;
    echo '<input type="button" class="btn btn-danger" onclick="libConfirm(\'Wil je dit bestand echt verwijderen?\').then(function(ok){if(ok){document.forms.delform.submit()}})" value="Verwijderen" style="width:90px">' . PHP_EOL;
    echo '</form>' . PHP_EOL;
}

echo '</td></tr>' . PHP_EOL;
echo '</table>' . PHP_EOL;
echo '</fieldset>' . PHP_EOL;
echo '</BODY>' . PHP_EOL;

if ($isImage && $sFile != '') {
    echo '<script>window.setTimeout(function(){showPreview(' . Str::JscriptSafe($actualFilePath . $sFile) . ')},1);</script>';
}

echo '</HTML>' . PHP_EOL;

/**
 * Get image dimensions and generate width/height attributes
 *
 * @param string $sFile Path to image file
 * @param string &$sWidthHeight Width/height HTML attributes
 * @param string &$sWidth Width value
 * @param string &$sHeight Height value
 */
function myGetImageSize($sFile, &$sWidthHeight, &$sWidth, &$sHeight)
{
    $intMax = 60;
    $blnError = true;

    // SVG files are scalable
    if (strtolower(pathinfo($sFile, PATHINFO_EXTENSION)) == 'svg') {
        $sWidthHeight = 'width="100%"';
        $sWidth = 'schaalbaar';
        $sHeight = 'schaalbaar';
        return;
    }

    $mappedFile = Server::mapPath($sFile);
    if (file_exists($mappedFile)) {
        $size = @getimagesize($mappedFile);
        if ($size !== false) {
            $iWidth = $size[0];
            $iHeight = $size[1];

            if (is_numeric($iHeight) && is_numeric($iWidth) && $iHeight != 0 && $iWidth != 0) {
                if ($iWidth > $iHeight) {
                    $sWidthHeight = 'width="' . min($intMax, $iWidth) . '" height="' . min(floor($iHeight * $intMax / $iWidth), $iHeight) . '"';
                } else {
                    $sWidthHeight = 'height="' . min($intMax, $iHeight) . '" width="' . min(floor($iWidth * $intMax / $iHeight), $iWidth) . '"';
                }
                $sWidth = $iWidth;
                $sHeight = $iHeight;
                $blnError = false;
            }
        }
    }

    if ($blnError) {
        $sWidthHeight = 'height="' . $intMax . '" width="' . $intMax . '"';
        $sWidth = '0';
        $sHeight = '0';
    }
}
?>
