<?php
use App\Library\Response;
use Cma\ToolbarHelper;
use App\Library\Server;

require_once __DIR__ . '/../bootstrap.inc';
Response::noCache();
cma_html_header('CMA - All pictures on the site');
echo '<BODY class="contentbody tools">';
define("STRROOTFOLDER", '../');
/**
* Iteratefolders
*
*/
function IterateFolders($folderPath, $sParent, $intLevel)
{
    $blnFirst = true;

    // Get files in directory
    $realPath = Server::mapPath($folderPath);
    if (!is_dir($realPath)) {
        return;
    }

    $files = scandir($realPath);
    foreach ($files as $fileName) {
        if ($fileName === '.' || $fileName === '..') {
            continue;
        }

        $fullPath = $realPath . '/' . $fileName;
        if (is_file($fullPath)) {
            $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            if (in_array($ext, ['gif', 'jpg', 'jpeg', 'png'])) {
                if ($blnFirst) {
                    echo '<h1>' . $sParent . '</h1><hr size=1 color=darkgray>';
                    echo '<table>';
                    $blnFirst = false;
                }
                echo '<tr><td>' . $fileName . '</td><td><img src="' . $sParent . $fileName . '"></td></tr>';
            }
        }
    }

    if (!$blnFirst) {
        echo '</table>';
    }

    // Process subdirectories
    foreach ($files as $subFolder) {
        if ($subFolder === '.' || $subFolder === '..') {
            continue;
        }

        $subPath = $realPath . '/' . $subFolder;
        if (is_dir($subPath)) {
            if (stripos($subFolder, '_vti_') === false && strtolower($subFolder) != 'cma') {
                IterateFolders($sParent . $subFolder . '/', $sParent . $subFolder . '/', $intLevel + 1);
            }
        }
    }
}

ToolbarHelper::report('All pictures on the site', false, false, false);
echo '<div id="c" class="tools">';
$rootFolder = Server::mapPath(STRROOTFOLDER);
if (is_dir($rootFolder)) {
    IterateFolders(STRROOTFOLDER, STRROOTFOLDER, 1);
} else {
    echo '<p>Root folder not found: ' . STRROOTFOLDER . '</p>';
}
echo '</div></body></html>';
?>
