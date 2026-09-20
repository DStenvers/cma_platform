<?php
use App\Library\Application;
use App\Library\Database;
use App\Library\Response;
use App\Library\SQL;
use App\Library\Server;

require_once __DIR__ . '/bootstrap.inc';
require_once __DIR__ . '/classes/Services/TemplateService.php';

// Editing site pages is an administrator's job: it writes files in the webroot.
if (!\Cma\SecurityHelper::isAdmin()) {
    \App\Library\Error::page('Geen toegang', 'Alleen beheerders kunnen pagina\'s wijzigen.', true);
    exit;
}

Response::noCache();
cma_html_header('CMA - Fill Repository');
echo '<body class="contentbody">';

$sBasePath = Server::mapPath(Application::get('base_path', ''));

if (Database::execute('delete * from tblSiteFiles', [])) {
    echo "<h2>Invoegen wijzigbare pagina's</h2>";
    IterateFolders($sBasePath, '', 0);
    echo '<h2>Gereed...</h2>';
}
echo '<script>';
echo "window.parent.frames['L'].location.reload(true);";
echo "window.location='blank.php';";
echo '</script>';

/**
* IterateFolders - Recursively scan folders for editable templates
*/
function IterateFolders($folderPath, $sParent, $iLevel)
{
    if (!is_dir($folderPath)) {
        return;
    }

    $iLenBasePath = strlen(Server::mapPath(Application::get('base_path', '')));
    $folderName = basename($folderPath);

    echo '<HR size=1 color=blue>' . $sParent . $folderName . '/<BR>';

    $items = scandir($folderPath);

    // Process files
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;

        $fullPath = $folderPath . DIRECTORY_SEPARATOR . $item;

        if (is_file($fullPath)) {
            $ext = strtolower(pathinfo($item, PATHINFO_EXTENSION));

            // Only process page files (the site is PHP now; asp/htm/html kept for old content)
            if (in_array($ext, ['php', 'asp', 'htm', 'html'])) {
                $sFileTitle = '';
                $bFileEditable = IsTemplate($fullPath, $sFileTitle);
                echo htmlspecialchars($item) . '----' . htmlspecialchars($sFileTitle) . '<BR>';

                $sFilePath = str_replace('\\', '/', substr($fullPath, $iLenBasePath + 1));
                $sFileName = $item;

                if ($bFileEditable) {
                    // Remove filename from path to get directory
                    $sFilePath = dirname($sFilePath);
                    if ($sFilePath === '.') {
                        $sFilePath = '';
                    }
                    $sFilePath .= '/';

                    echo '- ' . htmlspecialchars($sFilePath . $sFileName) . ' (' . htmlspecialchars($sFileTitle) . ')<br>';
                    $sSQL = ' insert into tblSiteFiles (FilePath, FileName, FileTitle, FileEditable) values (' . SQL::postString($sFilePath) . ', ' . SQL::postString($sFileName) . ', ' . SQL::postString($sFileTitle) . ', ' . SQL::postBoolean($bFileEditable) . ')';
                    Database::execute($sSQL, []);
                }
            }
        }
    }

    // Process subdirectories
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;

        $fullPath = $folderPath . DIRECTORY_SEPARATOR . $item;

        if (is_dir($fullPath)) {
            $itemLower = strtolower($item);
            // Skip cma, _vti*, _private and the code/dependency directories (PHP files there are no pages)
            $skip = ['cma', 'vendor', 'node_modules', 'src', 'library', 'api', '.git', '.cache', 'data', 'logs', '.logs', 'tests', '.tests'];
            if (!in_array($itemLower, $skip, true) && substr($itemLower, 0, 4) !== '_vti' && substr($itemLower, 0, 8) !== '_private') {
                IterateFolders($fullPath, $sParent . $folderName . '/', $iLevel + 1);
            }
        }
    }
}

/**
* IsTemplate - Check if file is an editable template
*/
function IsTemplate($filePath, &$sTitle)
{
    $sTitle = basename($filePath);
    $content = @file_get_contents($filePath);
    if ($content === false) {
        return false;
    }
    // One reader of the markers and the title: TemplateService (also used by the editor)
    $sTitle = \Cma\Services\TemplateService::title($content, $sTitle);
    return \Cma\Services\TemplateService::isTemplate($content);
}

/**
* GetExtension - Get file extension
*/
function GetExtension($sFileName)
{
    $pos = strrpos($sFileName, '.');
    if ($pos !== false) {
        return strtolower(substr($sFileName, $pos + 1));
    }
    return '';
}

echo '</body>';
echo '</html>';
?>
