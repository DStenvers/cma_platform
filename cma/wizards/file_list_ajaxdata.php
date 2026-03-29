<?php
use App\Library\Cookie;
use Cma\ToolbarHelper;
use App\Library\File;
use App\Library\Request;
use App\Library\Response;
use App\Library\Server;
use App\Library\Str;
use App\Library\Image;

require_once __DIR__ . '/../bootstrap.inc';

Response::noCache();
define("VIEW_LIST", '0');
define("VIEW_THUMB", '1');
define("VIEW_COOKIE", 'CMA_Listview');

$bToolbar = true;
$CurrentView = Cookie::get(VIEW_COOKIE, '');
if ($CurrentView == '') {
    $CurrentView = VIEW_LIST;
}

/**
* WriteItem - Output a file/folder item
*/
function WriteItem($elt_class, $icon, $icon_large, $kb, $onclick, $href, $sFolder, $text)
{
    global $CurrentView;
    $sWidthHeight = '';
    $max_kb = 250;

    echo '<div';
    if ($elt_class != '' && strpos($elt_class, 'current') !== false) {
        echo ' id="default" ';
    }

    if ($CurrentView == VIEW_LIST) {
        if ($icon == '../../library/images/ext/ext_doc.gif') {
            $elt_class = 'ext_doc';
            $icon = '';
        }
        if ($icon == '../../library/images/ext/ext_docx.gif') {
            $elt_class .= ' ext_docx';
        }
        if ($icon == '../../library/images/ext/ext_pdf.gif') {
            $elt_class .= ' ext_pdf';
        }
        if ($icon == '../../library/images/ext/ext_jpg.gif') {
            $elt_class .= ' ext_jpg';
        }
        if ($icon == '../../library/images/ext/ext_gif.gif') {
            $elt_class .= ' ext_gif';
        }
        if ($icon == '../../library/images/ext/ext_png.gif') {
            $elt_class .= ' ext_png';
        }
        if ($icon == '../images/ftv2folderclosed.gif') {
            $elt_class .= ' fld';
        }
        if ($icon != '') {
            echo ' style="background-image:url(\'' . $icon . '\')"';
        }
        echo ' class="' . trim($elt_class) . '"';
    }

    if ($onclick != '') {
        echo ' onclick="' . $onclick . '"';
    }
    echo '>';

    if ($CurrentView == VIEW_THUMB) {
        echo '<div class="img_container">';
        echo '<a';
        if ($kb > 0) {
            echo ' title="' . ($kb < 1024 ? $kb . 'kb' : round($kb / 1024, 1) . 'mb') . '"';
        }
        if ($href != '') {
            echo ' href="' . $href . '"';
        }
        if ($onclick != '') {
            echo ' onclick="' . $onclick . '"';
        }
        echo '>';

        if (IsImage($sFolder . $text)) {
            if ($kb > $max_kb) {
                echo '<img src="../../library/images/ext/ext_img_too_large.gif">';
            } else {
                GetImageSizeString($sFolder . $text, $sWidthHeight);
                echo '<img src="' . str_replace('\\', '/', $sFolder . $text) . '" ' . $sWidthHeight . '>';
            }
        } else {
            echo '<img src="' . $icon_large . '">';
        }
        echo '</a>';
        echo '</div>';
        echo '<span title="' . $text . PHP_EOL . ($kb < 1024 ? $kb . 'kb' : round($kb / 1024, 1) . 'mb') . '">';
    }

    if ($CurrentView == VIEW_LIST && $href != '') {
        echo '<a href="' . $href . '">';
    }
    echo htmlspecialchars($text);
    if ($CurrentView == VIEW_THUMB || ($CurrentView == VIEW_LIST && $href != '')) {
        echo '</a>';
    }
    if ($CurrentView == VIEW_THUMB) {
        echo '</span>';
    }
    echo '</div>';
}

$sRootURL = Request::query('basepath', '');
$sSubPath = Request::query('path', '');
$sSubPathUp = '';
$sFile = Request::query('file', '');
$bImage = Request::query('image', '') != '';

if ($sRootURL == '') {
    $sRootURL = '/';
}

$sRootMap = Server::mapPath($sRootURL);

if (substr($sSubPath, 0, 1) == '/') {
    $sSubPath = substr($sSubPath, 1);
}

$lastSlash = strrpos(rtrim($sSubPath, '/'), '/');
if ($lastSlash !== false) {
    $sSubPathUp = substr($sSubPath, 0, $lastSlash + 1);
}

echo '<fieldset style="margin:0px;width:100%;min-height:100%"><legend>Server: ' . htmlspecialchars($sRootURL . $sSubPath) . '</legend>';

if ($bToolbar) {
    ToolbarHelper::writeJS();
    ToolbarHelper::start(false);
    ToolbarHelper::button('<a href=javascript:setlisttype(' . VIEW_LIST . ')>', "<img src=../images/tb_file_list.gif alt='Lijstweergave'", $CurrentView != VIEW_LIST, '');
    ToolbarHelper::button('<a href=javascript:setlisttype(' . VIEW_THUMB . ')>', "<img src=../images/tb_file_thumb.gif alt='Thumbnails'", $CurrentView != VIEW_THUMB, '');
    ToolbarHelper::end(false);
}

echo '<div class="file_list"' . ($CurrentView == VIEW_THUMB ? ' id=thumb' : '') . '>';

if ($sSubPath != '') {
    $sURL = 'file_list.php?' . Request::server('QUERY_STRING');
    $sURL = Request::addToURL($sURL, 'basepath', $sRootURL);
    $sURL = Request::addToURL($sURL, 'path', $sSubPathUp);
    WriteItem('', '../images/folderup.gif', '../images/folderup.gif', 0, 'javascript:window.parent.selectpath(\'' . Str::JscriptSafe($sRootURL) . '\',\'' . Str::JscriptSafe($sSubPathUp) . '\')', $sURL, '', '(Map terug)');
}

// Check for existence of this folder
$strCurFolder = $sRootMap . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $sSubPath);
$strCurFolder = rtrim($strCurFolder, DIRECTORY_SEPARATOR);

if (!is_dir($strCurFolder)) {
    // Try to create the directory using URL path (File::createFolder handles mapping)
    $urlPath = rtrim($sRootURL . $sSubPath, '/');
    if (!File::createFolder($urlPath)) {
        echo '<div><BR>Map ' . htmlspecialchars($urlPath) . ' bestaat niet en kon niet worden aangemaakt!</div>';
    }
}

if (is_dir($strCurFolder)) {
    // List subdirectories
    $items = scandir($strCurFolder);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;

        $fullPath = $strCurFolder . DIRECTORY_SEPARATOR . $item;
        if (is_dir($fullPath)) {
            $folderName = strtolower($item);
            if (substr($folderName, 0, 3) != '_vt' && $folderName != '_private') {
                $sURL = 'file_list.php?' . Request::server('QUERY_STRING');
                $sURL = Request::addToURL($sURL, 'basepath', $sRootURL);
                $sURL = Request::addToURL($sURL, 'path', $sSubPath . $item . '/');
                WriteItem('', '../images/ftv2folderclosed.gif', '../images/folder_large.gif', 0, 'window.parent.selectpath(\'' . Str::JscriptSafe($sRootURL) . '\',\'' . Str::JscriptSafe($sSubPath . $item . '/') . '\')', $sURL, '', $item);
            }
        }
    }

    // List files
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;

        $fullPath = $strCurFolder . DIRECTORY_SEPARATOR . $item;
        if (is_file($fullPath)) {
            if (IsImage($item) || !$bImage) {
                $sLink = 'setbg(this);return parent.selectfile(\'' . Str::JscriptSafe($sRootURL) . '\',\'' . Str::JscriptSafe($sSubPath) . '\',\'' . Str::JscriptSafe($item) . '\')';
                $sExt = GetExtension($item);
                if (strlen($sExt) > 4) {
                    $sExt = '';
                }
                $fileSize = round(filesize($fullPath) / 1024, 0);
                WriteItem(($item == $sFile ? 'current' : ''), '../../library/images/ext/ext_' . $sExt . '.gif', getLargeIconName($item), $fileSize, $sLink, '', str_replace('/', '\\', $sRootURL . $sSubPath), $item);
            }
        }
    }
}

echo '</div>';
echo '</fieldset>';

/**
 * IsImage - Check if file is an image by extension
 */
function IsImage($sFilename)
{
    $ext = strtolower(GetExtension($sFilename));
    return in_array($ext, ['gif', 'jpg', 'jpe', 'jpeg', 'png', 'jfif', 'svg']);
}

/**
 * GetExtension - Get file extension
 */
function GetExtension($sFilename)
{
    $pos = strrpos($sFilename, '.');
    if ($pos !== false) {
        return strtolower(substr($sFilename, $pos + 1));
    }
    return '';
}

/**
 * GetImageSizeString - Get width/height attributes for image
 */
function GetImageSizeString($sFile, &$sWidthHeight)
{
    $intMax = 70;
    $blnerror = true;

    if (strtolower(substr($sFile, -4)) == '.svg') {
        $sWidthHeight = 'width=100%';
    } else {
        $mappedFile = Server::mapPath($sFile);
        if (file_exists($mappedFile)) {
            $size = @getimagesize($mappedFile);
            if ($size !== false) {
                $iWidth = $size[0];
                $iHeight = $size[1];
                if ($iHeight != 0 && $iWidth != 0) {
                    if ($iWidth > $iHeight) {
                        $sWidthHeight = 'width=' . min($intMax, $iWidth) . ' height=' . min(floor($iHeight * $intMax / $iWidth), $iHeight);
                    } else {
                        $sWidthHeight = 'height=' . min($intMax, $iHeight) . ' width=' . min(floor($iWidth * $intMax / $iHeight), $iWidth);
                    }
                    $blnerror = false;
                }
            }
        }
        if ($blnerror) {
            $sWidthHeight = 'height=' . $intMax . ' width=' . $intMax;
        }
    }
}

/**
 * getLargeIconName - Get large icon name for file type
 */
function getLargeIconName($sFilename)
{
    $sExtension = GetExtension($sFilename);
    $sRetval = '../../library/images/ext/';
    if (in_array(strtolower($sExtension), ['pdf', 'doc', 'docx', 'xls', 'css', 'zip', 'ppt', 'pptx', 'rar', 'txt'])) {
        return $sRetval . 'ext_' . $sExtension . '_large.gif';
    }
    return $sRetval . 'ext_large.gif';
}
?>
