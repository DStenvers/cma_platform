<?php
/**
 * Picture Analysis Tool
 *
 * Analyzes all images used in forms for size compliance and finds unused images.
 * Uses JSON form definitions instead of repository database tables.
 */

use App\Library\Application;
use Cma\FormControlHelper;
use Cma\ToolbarHelper;
use Cma\JsonFormLoader;
use Cma\CmaRepository;
use App\Library\Cache;
use App\Library\Database;
use App\Library\Response;
use App\Library\Server;

require_once __DIR__ . '/../bootstrap.inc';
require_once __DIR__ . '/../library/lib_imgformat.inc';
require_once __DIR__ . '/../library/lib_cache.inc';
Response::noCache();
cma_html_header('CMA - Beelden analyse');
echo '<BODY class="contentbody tools">';
define("STRERRORFONT", '<font color=red>');
define("STRCACHEPREFIX", 'CMA_ImageSearch_');

// Global array for image fields (used by helper functions)
$imageFields = [];

/**
 * Get all image fields from JSON form definitions
 *
 * @return array Array of image field configurations
 */
function getImageFieldsFromJson(): array
{
    $fields = [];
    $formNames = JsonFormLoader::listForms();

    foreach ($formNames as $formName) {
        $formDef = JsonFormLoader::loadRaw($formName);
        if ($formDef === null) {
            continue;
        }

        $database = $formDef['database'] ?? 'data';
        $table = $formDef['table'] ?? '';
        $formTitle = $formDef['title'] ?? $formName;

        if (empty($table)) {
            continue;
        }

        $controls = $formDef['controls'] ?? [];
        foreach ($controls as $control) {
            $controlType = $control['type'] ?? '';

            if ($controlType === 'image' || $controlType === 'thumbnail') {
                $fields[] = [
                    'formName' => $formTitle,
                    'table' => $table,
                    'database' => $database,
                    'fieldName' => $control['field'] ?? '',
                    'caption' => $control['label'] ?? $control['field'] ?? '',
                    'imgPath' => $control['imgPath'] ?? $control['path'] ?? '',
                    'imgHeightField' => $control['imgHeightField'] ?? '',
                    'imgWidthField' => $control['imgWidthField'] ?? '',
                    'imgResizeType' => $control['imgResizeType'] ?? 0,
                    'imgResizeWidth' => $control['imgResizeWidth'] ?? 0,
                    'imgResizeHeight' => $control['imgResizeHeight'] ?? 0,
                    'isRequired' => $control['required'] ?? false,
                ];
            }
        }
    }

    return $fields;
}

/**
 * Get distinct image paths from form definitions
 *
 * @param array $imageFields Image fields array
 * @return array Unique image paths
 */
function getDistinctImagePaths(array $imageFields): array
{
    $paths = [];
    foreach ($imageFields as $field) {
        $imgPath = $field['imgPath'] ?? '';
        if (!empty($imgPath) && !in_array($imgPath, $paths)) {
            $paths[] = $imgPath;
        }
    }
    return $paths;
}

/**
 * Open database connection by database identifier
 */
function openDbConnection($database): void
{
    global $conn;

    if (is_numeric($database)) {
        CmaRepository::openConnectionById((int)$database);
    } else {
        $conn = Database::getConnection($database ?: 'data');
    }
}

/**
 * Main
 */
function main()
{
    global $conn;
    $strCurrentColor = '';
    $currentDatabase = null;

    // kan ff duren!
    set_time_limit(60000);

    ToolbarHelper::report('Beelden analyse', false, false, false);
    echo '<div id="c" class="tools">';

    // Get all image fields from JSON form definitions
    $imageFields = getImageFieldsFromJson();
    $imageFields = $imageFields;

    if (empty($imageFields)) {
        echo '<p>Geen afbeeldingsvelden gevonden in formulierdefinities.</p>';
        echo '</div></BODY></HTML>';
        return;
    }

    echo '<TABLE width=100% cellspacing=2 cellpadding=2>';
    echo '<tr><td><h2>Analyse gebruikte beelden</h2></td></tr>';

    $strCurrentColor = Application::get('color_row1', '');

    foreach ($imageFields as $field) {
        if (empty($field['fieldName']) || empty($field['table'])) {
            continue;
        }

        echo '<TR><TH colspan=2 align=left><B>' . htmlspecialchars($field['formName']) . ' - ' . htmlspecialchars($field['caption']);

        if ($field['imgResizeType'] == FormControlHelper::IMG_MAXIMUM) {
            echo ' - (Maximaal ' . intval($field['imgResizeHeight']) . 'px hoog x ' . intval($field['imgResizeWidth']) . 'px breed)';
        } elseif ($field['imgResizeType'] == FormControlHelper::IMG_FIXED) {
            echo ' - (Vast formaat ' . intval($field['imgResizeHeight']) . 'px hoog x ' . intval($field['imgResizeWidth']) . 'px breed)';
        }
        echo '</B></TH></TR>';

        // Switch database if needed
        if ($field['database'] !== $currentDatabase) {
            openDbConnection($field['database']);
            $currentDatabase = $field['database'];
        }

        // Retrieve the expected files and try to find them on the server
        $picturesSQL = 'SELECT [' . $field['fieldName'] . '] as Filename, ID FROM [' . $field['table'] . '] WHERE ([' . $field['fieldName'] . "] <> '' AND [" . $field['fieldName'] . '] IS NOT NULL) ORDER BY 1';
        $picturesRS = Database::openRS($picturesSQL, $conn, adOpenForwardOnly);

        if ($picturesRS === null) {
            echo '<TR><TD>Fout bij ophalen: ' . htmlspecialchars(Database::getLastError()) . '</TD></TR>';
            continue;
        }

        while (!$picturesRS->EOF) {
            $filename = $picturesRS->fields['Filename'] ?? '';
            $recordId = $picturesRS->fields['ID'] ?? '';

            if (!empty($filename)) {
                $strFileName = Application::get('base_path', '') . $field['imgPath'] . $filename;
                $strRealFileName = Server::mapPath($strFileName);

                if (!file_exists($strRealFileName)) {
                    echo '<TR><TD>' . htmlspecialchars($strFileName) . STRERRORFONT . ' ontbreekt</font></TD></TR>';
                } else {
                    $sImageWidth = 0;
                    $sImageHeight = 0;
                    $iDepth = 0;
                    $sImageType = '';

                    if (gfxSpex($strRealFileName, $sImageWidth, $sImageHeight, $iDepth, $sImageType)) {
                        // Update width/height fields if configured
                        if (!empty($field['imgWidthField']) || !empty($field['imgHeightField'])) {
                            $setParts = [];
                            if (!empty($field['imgWidthField'])) {
                                $setParts[] = '[' . $field['imgWidthField'] . ']=' . intval($sImageWidth);
                            }
                            if (!empty($field['imgHeightField'])) {
                                $setParts[] = '[' . $field['imgHeightField'] . ']=' . intval($sImageHeight);
                            }

                            if (!empty($setParts)) {
                                $setsql = 'UPDATE [' . $field['table'] . '] SET ' . implode(',', $setParts) . ' WHERE ID=' . intval($recordId);
                                Database::execute($setsql, $conn);
                            }
                        }

                        // Check resize requirements
                        if ($field['imgResizeType'] == FormControlHelper::IMG_MAXIMUM) {
                            if ($sImageHeight > $field['imgResizeHeight']) {
                                echo '<TR><TD>' . htmlspecialchars($strFileName) . ': ' . STRERRORFONT . ' te hoog ' . $sImageHeight . ' i.p.v. ' . $field['imgResizeHeight'] . '</font></TD></TR>';
                            }
                            if ($sImageWidth > $field['imgResizeWidth']) {
                                echo '<TR><TD>' . htmlspecialchars($strFileName) . ': ' . STRERRORFONT . ' te breed ' . $sImageWidth . ' i.p.v. ' . $field['imgResizeWidth'] . '</font></TD></TR>';
                            }
                        } elseif ($field['imgResizeType'] == FormControlHelper::IMG_FIXED) {
                            if ($sImageHeight != $field['imgResizeHeight']) {
                                echo '<TR><TD>' . htmlspecialchars($strFileName) . ': ' . STRERRORFONT . 'hoogte ' . $sImageHeight . ' niet correct</font></TD></TR>';
                            }
                            if ($sImageWidth != $field['imgResizeWidth']) {
                                echo '<TR><TD>' . htmlspecialchars($strFileName) . ': ' . STRERRORFONT . 'breedte ' . $sImageWidth . ' niet correct</font></TD></TR>';
                            }
                        }
                    } else {
                        echo '<TR><TD>' . STRERRORFONT . htmlspecialchars($strFileName) . ' kan grootte niet achterhalen!</font></TD></TR>';
                    }
                }
            }
            $picturesRS->MoveNext();
        }

        // Alternate row colors
        $strCurrentColor = ($strCurrentColor === Application::get('color_row1', ''))
            ? Application::get('color_row2', '')
            : Application::get('color_row1', '');
    }

    // Unused images section
    echo '<form name="main" target="_self" action="tools_picture_analyse_delete.php" method="post">';
    echo '<tr><td><h2>Ongebruikte beelden</h2></td></tr>';
    echo '<tr><td><table cellpadding="2" cellspacing="2">';

    $imagePaths = getDistinctImagePaths($imageFields);
    foreach ($imagePaths as $imgPath) {
        $sDir = Application::get('base_path', '') . $imgPath;
        if (strtolower($sDir) != strtolower(Application::get('cma_htmledit_img_path', '')) &&
            strtolower($sDir) != strtolower(Application::get('path_images', ''))) {
            StartDirectory($sDir, $imageFields);
        }
    }

    echo '</table></td></tr>';
    echo '<tr><td colspan="2"><input name="submit" type="submit" value="Verwijder de aangevinkte ongebruikte beelden"></form></td></tr>';

    echo '</TABLE>';
    echo '</div></BODY></HTML>';
    Cache::delete(STRCACHEPREFIX);
}

/**
 * Initiates a search through a directory
 */
function StartDirectory($controlPath, $imageFields)
{
    $realPath = Server::mapPath($controlPath);
    if (is_dir($realPath)) {
        ExamineDirectory($realPath, $controlPath, 1, $imageFields);
    } else {
        echo '<tr><td></td><td colspan="2">Directory ' . STRERRORFONT . htmlspecialchars($controlPath) . '</font> niet gevonden!</td></tr>';
    }
}

/**
 * Gets all the files in a directory and checks for references
 */
function ExamineDirectory($realPath, $controlPath, $level, $imageFields)
{
    echo '<tr><th colspan="9" align="left">' . htmlspecialchars($controlPath) . '</th></tr>';

    if (!is_dir($realPath)) {
        return;
    }

    $files = scandir($realPath);
    foreach ($files as $filename) {
        if ($filename === '.' || $filename === '..') {
            continue;
        }

        $fullPath = $realPath . '/' . $filename;
        if (is_file($fullPath)) {
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            if (in_array($ext, ['gif', 'jpg', 'jpeg', 'png'])) {
                $blnFound = FindPictureReference($controlPath, $filename, $imageFields);
                if (!$blnFound) {
                    echo PHP_EOL . '<tr><td><input type="checkbox" checked name="delete" value="' . htmlspecialchars($controlPath . $filename) . '"></td><td>' . htmlspecialchars($filename) . '</td>';
                    echo '<td><a target="_blank" href="' . htmlspecialchars($controlPath . $filename) . '"><img src="' . htmlspecialchars($controlPath . $filename) . '" width="40" border="0"></a></td>';
                    echo '</tr>';
                }
            }
        }
    }
}

/**
 * Searches for a reference to a picture using JSON form definitions
 */
function FindPictureReference($path, $filename, $imageFields)
{
    global $conn;
    $basePath = Application::get('base_path', '');
    $TempPath = substr($path, strlen($basePath));

    // Find fields that use this image path
    foreach ($imageFields as $field) {
        $fieldImgPath = $field['imgPath'] ?? '';

        if (strtolower($fieldImgPath) === strtolower($TempPath)) {
            // Open correct database
            openDbConnection($field['database']);

            // Search for reference in this field
            $searchSQL = 'SELECT count(*) as cnt FROM [' . $field['table'] . '] WHERE (lcase([' . $field['fieldName'] . '])=' . Database::quote(strtolower($filename)) . ')';
            $searchRS = Database::openRS($searchSQL, $conn, adOpenForwardOnly);

            if ($searchRS !== null && !$searchRS->EOF) {
                $cnt = $searchRS->fields['cnt'] ?? 0;
                if ($cnt > 0) {
                    return true;
                }
            }
        }
    }

    return false;
}

main();
