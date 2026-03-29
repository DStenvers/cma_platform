<?php
/**
 * Database Consistency Tool
 *
 * Checks database contents against file system and configuration.
 * Uses JSON form definitions instead of repository database tables.
 */

use App\Library\Application;
use App\Library\Arr;
use Cma\FormControlHelper;
use Cma\ToolbarHelper;
use Cma\JsonFormLoader;
use Cma\CmaRepository;
use App\Library\Cache;
use App\Library\Database;
use App\Library\File;
use App\Library\Response;
use App\Library\SQL;
use App\Library\Server;

require_once __DIR__ . '/../bootstrap.inc';
require_once __DIR__ . '/../../library/lib_imgformat.inc';
Response::noCache();
cma_html_header('CMA - Database Consistency');
echo '<BODY class="contentbody tools tool-db-consistency">';
define("SHOW_ALL_IMAGE_INFO", false);
define("SHOW_UNRESOLVED_ERRORS", true);
define("STRERRORFONT", '<span class="text-error">');
define("STRCACHEPREFIX", 'CMA_ImageSearch_');

/**
 * Get all fields of a specific control type from JSON form definitions
 *
 * @param array $controlTypes Array of control type names to match
 * @return array Array of field configurations
 */
function getFieldsByControlType(array $controlTypes): array
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
        $sourceFormId = $formDef['sourceFormId'] ?? 0;

        if (empty($table)) {
            continue;
        }

        $controls = $formDef['controls'] ?? [];
        foreach ($controls as $control) {
            $controlType = $control['type'] ?? '';

            if (in_array($controlType, $controlTypes)) {
                $fields[] = [
                    'formName' => $formTitle,
                    'formId' => $sourceFormId,
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
                    'controlType' => $controlType,
                ];
            }
        }
    }

    return $fields;
}

/**
 * Get distinct image paths from JSON form definitions
 *
 * @return array Array of unique image paths
 */
function getDistinctImagePaths(): array
{
    $paths = [];
    $formNames = JsonFormLoader::listForms();

    foreach ($formNames as $formName) {
        $formDef = JsonFormLoader::loadRaw($formName);
        if ($formDef === null) {
            continue;
        }

        $controls = $formDef['controls'] ?? [];
        foreach ($controls as $control) {
            $controlType = $control['type'] ?? '';
            if ($controlType === 'image' || $controlType === 'thumbnail') {
                $imgPath = $control['imgPath'] ?? $control['path'] ?? '';
                if (!empty($imgPath) && !in_array($imgPath, $paths)) {
                    $paths[] = $imgPath;
                }
            }
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
    global $conn, $connrep;
    $currentDatabase = null;

    // kan ff duren!
    set_time_limit(60000);

    ToolbarHelper::report('Controleer bestanden', false, false, false, false, 'Afbeeldingen, bestanden en verwijzingen controleren');
    echo '<div id="c" class="tools">';

    // =========================================================================
    // Section 1: Beeldformaten herstellen
    // =========================================================================
    echo '<h3>Afbeeldingen</h3>Vergelijkt de afbeeldingsformaten in de database met de bestanden op de server. Afwijkende afmetingen worden waar mogelijk automatisch hersteld.<BR>&nbsp;';

    $imageFields = getFieldsByControlType(['image', 'thumbnail']);

    echo '<TABLE width=100% cellspacing=2 cellpadding=2>';

    foreach ($imageFields as $field) {
        if (empty($field['fieldName']) || empty($field['table'])) {
            continue;
        }

        echo '<TR><TH colspan=2 align=left><B>' . htmlspecialchars($field['formName']) . ' - ' . htmlspecialchars($field['caption']) . '</B></TH></TR>';

        // Switch database if needed
        if ($field['database'] !== $currentDatabase) {
            openDbConnection($field['database']);
            $currentDatabase = $field['database'];
        }

        // Retrieve the expected files and try to find them on the server
        $picturesSQL = 'SELECT [' . $field['fieldName'] . '] as Filename, ID FROM [' . $field['table'] . '] WHERE ([' . $field['fieldName'] . "] <> '' AND [" . $field['fieldName'] . '] IS NOT NULL) ORDER BY 1';
        $picturesRS = Database::openRS($picturesSQL, $conn, adOpenForwardOnly);

        if ($picturesRS === null) {
            echo '<TR><TD>Fout bij ophalen van afbeeldingen: ' . htmlspecialchars(Database::getLastError()) . '</TD></TR>';
            continue;
        }

        while (!$picturesRS->EOF) {
            $filename = $picturesRS->fields['Filename'] ?? '';
            $recordId = $picturesRS->fields['ID'] ?? '';

            if (!empty($filename)) {
                $strFileName = Application::get('base_path', '') . $field['imgPath'] . $filename;
                $strRealFileName = Server::mapPath($strFileName);
                $sFormLink = '<a href="form.php?form=' . urlencode($field['formName']) . '&ID=' . urlencode($recordId) . '" target="_blank">[Formulier]</a>&nbsp;';

                if (!file_exists($strRealFileName)) {
                    echo '<TR><TD>' . $sFormLink . htmlspecialchars($strFileName) . STRERRORFONT . ' ontbreekt</span></TD></TR>';
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

                                if (SHOW_ALL_IMAGE_INFO) {
                                    echo '<TR><TD>' . $sFormLink . htmlspecialchars($strFileName) . ': breedte ' . $sImageWidth . ', hoogte ' . $sImageHeight . '</TD></TR>';
                                }
                            }
                        }

                        // Check resize requirements
                        if ($field['imgResizeType'] == FormControlHelper::IMG_MAXIMUM) {
                            if ($sImageHeight > $field['imgResizeHeight']) {
                                echo '<TR><TD>' . $sFormLink . htmlspecialchars($strFileName) . ': ' . STRERRORFONT . ' te hoog ' . $sImageHeight . ' i.p.v. ' . $field['imgResizeHeight'] . '</span></TD></TR>';
                            }
                            if ($sImageWidth > $field['imgResizeWidth']) {
                                echo '<TR><TD>' . $sFormLink . htmlspecialchars($strFileName) . ': ' . STRERRORFONT . ' te breed ' . $sImageWidth . ' i.p.v. ' . $field['imgResizeWidth'] . '</span></TD></TR>';
                            }
                        } elseif ($field['imgResizeType'] == FormControlHelper::IMG_FIXED) {
                            if ($sImageHeight != $field['imgResizeHeight']) {
                                echo '<TR><TD>' . $sFormLink . htmlspecialchars($strFileName) . ': ' . STRERRORFONT . 'hoogte ' . $sImageHeight . ' niet correct</span></TD></TR>';
                            }
                            if ($sImageWidth != $field['imgResizeWidth']) {
                                echo '<TR><TD>' . $sFormLink . htmlspecialchars($strFileName) . ': ' . STRERRORFONT . 'breedte ' . $sImageWidth . ' niet correct</span></TD></TR>';
                            }
                        }
                    } else {
                        echo '<TR><TD>' . STRERRORFONT . htmlspecialchars($strFileName) . ' kan grootte niet achterhalen!</span></TD></TR>';
                    }
                }
            }
            $picturesRS->MoveNext();
        }
    }

    echo '</TABLE>';

    // =========================================================================
    // Section 2: Unused images
    // =========================================================================
    // Collect unused images first (buffer output)
    $unusedCount = 0;
    $imagePaths = getDistinctImagePaths();
    ob_start();
    foreach ($imagePaths as $imgPath) {
        $sDir = Application::get('base_path', '') . $imgPath;
        if (strtolower($sDir) != strtolower(Application::get('cma_htmledit_img_path', '')) &&
            strtolower($sDir) != strtolower(Application::get('path_images', ''))) {
            StartDirectory($sDir, $imageFields, $unusedCount);
        }
    }
    $unusedImagesHtml = ob_get_clean();

    echo '<hr size=1><h3>Ongebruikte afbeeldingen</h3>Toont afbeeldingen op de server waarnaar geen enkel databaserecord verwijst.<BR>&nbsp;';
    if ($unusedCount > 0) {
        echo '<form name="main" target="_self" action="tools_consistency_picture_delete.php" method="post">';
        echo '<table cellpadding="2" cellspacing="2">';
        echo $unusedImagesHtml;
        echo '</table>';
        echo '<input name="submit" type="submit" value="Verwijder de aangevinkte bestanden">';
        echo '</form>';
    }

    // =========================================================================
    // Section 3: Externe bestanden controleren
    // =========================================================================
    echo "<hr size=1><h3>Externe bestanden</h3>Controleert of bestanden waarnaar de database verwijst (PDF's, downloads) daadwerkelijk op de server aanwezig zijn.<BR>&nbsp;";

    $fileFields = getFieldsByControlType(['file']);
    $currentDatabase = null;

    echo '<TABLE width=100% cellspacing=2 cellpadding=2>';
    foreach ($fileFields as $field) {
        if (empty($field['fieldName']) || empty($field['table'])) {
            continue;
        }

        echo '<TR><TH colspan=2 align=left><B>' . htmlspecialchars($field['formName']) . ' (' . htmlspecialchars($field['caption']) . ')</B></TH></TR>';

        if ($field['database'] !== $currentDatabase) {
            openDbConnection($field['database']);
            $currentDatabase = $field['database'];
        }

        // Check/create directory
        $dirPath = Server::mapPath(Application::get('base_path', '') . $field['imgPath']);
        if (!empty($field['imgPath']) && !is_dir($dirPath)) {
            if (File::createFolder($dirPath)) {
                echo '<TR><TD>' . htmlspecialchars($field['imgPath']) . ' aangemaakt!</TD></TR>';
            } else {
                echo '<TR><TD>Kon ' . STRERRORFONT . htmlspecialchars($field['imgPath']) . '</span> niet aanmaken!</TD></TR>';
            }
        }

        $filesSQL = 'SELECT [' . $field['fieldName'] . '] as Filename, ID FROM [' . $field['table'] . '] WHERE ([' . $field['fieldName'] . "] <> '' AND [" . $field['fieldName'] . '] IS NOT NULL) ORDER BY 1';
        $filesRS = Database::openRS($filesSQL, $conn, adOpenForwardOnly, adLockReadOnly);

        if ($filesRS !== null) {
            while (!$filesRS->EOF) {
                $filename = $filesRS->fields['Filename'] ?? '';
                $recordId = $filesRS->fields['ID'] ?? '';

                if (!empty($filename)) {
                    $strFileName = $field['imgPath'] . $filename;
                    $strRealFileName = Server::mapPath(Application::get('base_path', '') . $strFileName);

                    if (!empty($strRealFileName) && !file_exists($strRealFileName)) {
                        if (SHOW_UNRESOLVED_ERRORS) {
                            echo '<TR><TD><a href="form.php?form=' . urlencode($field['formName']) . '&ID=' . urlencode($recordId) . '" target="_blank">[Formulier]</a>&nbsp;' . STRERRORFONT . htmlspecialchars($strFileName) . '</span> ontbreekt!</TD></TR>';
                        }
                    }
                }
                $filesRS->MoveNext();
            }
        }
    }
    echo '</TABLE>';

    // =========================================================================
    // Section 4: XMLStore fields
    // =========================================================================
    echo '<hr size=1><h3>XMLStores</h3>Zoekt naar XMLStore-verwijzingen in de database die niet meer in de repository bestaan.<BR>&nbsp;';

    $connrep = Database::getRepConnection();
    $strIDs = table_value_list('rep', 'tblXMLStore', 'lcase(name)', "'");

    $xmlStoreFields = getFieldsByControlType(['xmlstore']);
    $currentDatabase = null;

    echo '<TABLE width=100% cellspacing=2 cellpadding=2>';
    if ($strIDs != '()') {
        foreach ($xmlStoreFields as $field) {
            if (empty($field['fieldName']) || empty($field['table'])) {
                continue;
            }

            echo '<TR><TH colspan=2 align=left><B>' . htmlspecialchars($field['formName']) . ' - ' . htmlspecialchars($field['caption']) . '</B></TH></TR>';

            if ($field['database'] !== $currentDatabase) {
                openDbConnection($field['database']);
                $currentDatabase = $field['database'];
            }

            // Retrieve the XMLStores that are not stored in the repository
            $storesSQL = 'SELECT [' . $field['fieldName'] . '] as Store, ID FROM [' . $field['table'] . '] WHERE (lcase([' . $field['fieldName'] . ']) not in ' . $strIDs . ')';
            $storesRS = Database::openRS($storesSQL, $conn, adOpenForwardOnly);

            if ($storesRS !== null) {
                while (!$storesRS->EOF) {
                    $store = $storesRS->fields['Store'] ?? '';
                    $recordId = $storesRS->fields['ID'] ?? '';

                    if (!empty($store) && SHOW_UNRESOLVED_ERRORS) {
                        echo '<TR><TD><a href="form.php?form=' . urlencode($field['formName']) . '&ID=' . urlencode($recordId) . '" target="_blank">[Formulier]</a>&nbsp;XMLStore ' . htmlspecialchars($store) . ' niet gevonden!</TD></TR>';
                    }
                    $storesRS->MoveNext();
                }
            }
        }
    }
    echo '</TABLE>';

    // =========================================================================
    // Section 5: Gebruikers-id's controleren
    // =========================================================================
    echo '<hr size=1><h3>Gebruikers</h3>Signaleert gebruikers-ID\'s in de database die niet meer voorkomen in de gebruikerslijst.<BR>&nbsp;';

    $strIDs = table_value_list('users', 'tblUsers', 'ID', '');

    $userListFields = getFieldsByControlType(['userlist']);
    $currentDatabase = null;

    echo '<TABLE width=100% cellspacing=2 cellpadding=2>';
    if ($strIDs != '()') {
        foreach ($userListFields as $field) {
            if (empty($field['fieldName']) || empty($field['table'])) {
                continue;
            }

            if ($field['database'] !== $currentDatabase) {
                openDbConnection($field['database']);
                $currentDatabase = $field['database'];
            }

            // Retrieve the USER-IDs that are not stored in the user database
            $usersSQL = 'SELECT [' . $field['fieldName'] . '] as userID, ID FROM [' . $field['table'] . '] WHERE ([' . $field['fieldName'] . '] not in ' . $strIDs . ')';
            $usersRS = Database::openRS($usersSQL, $conn, adOpenForwardOnly);

            if ($usersRS !== null) {
                while (!$usersRS->EOF) {
                    $userId = $usersRS->fields['userID'] ?? '';
                    $recordId = $usersRS->fields['ID'] ?? '';

                    if (!empty($userId)) {
                        echo '<TR><TD><a href="form.php?form=' . urlencode($field['formName']) . '&ID=' . urlencode($recordId) . '" target="_blank">[Formulier]</a>&nbsp;Gebruiker met ID ' . htmlspecialchars($userId) . ' niet gevonden!</TD></TR>';
                    }
                    $usersRS->MoveNext();
                }
            }
        }
    }
    echo '</TABLE>';

    echo '</div></BODY></HTML>';
    Cache::delete(STRCACHEPREFIX);
}

/**
 * Initiates a search through a directory
 */
function StartDirectory($controlPath, $imageFields, &$unusedCount)
{
    $realPath = Server::mapPath($controlPath);
    if (!is_dir($realPath)) {
        if (File::createFolder($realPath)) {
            echo '<tr><td></td><td colspan=2>Directory ' . htmlspecialchars($controlPath) . ' is aangemaakt!</td></tr>';
        } else {
            echo '<tr><td></td><td colspan=2>Directory ' . STRERRORFONT . htmlspecialchars($controlPath) . '</span> kon niet worden aangemaakt!</td></tr>';
        }
    } else {
        ExamineDirectory($realPath, $controlPath, $imageFields, $unusedCount);
    }
}

/**
 * Gets all the files in a directory and checks for references
 */
function ExamineDirectory($realPath, $controlPath, $imageFields, &$unusedCount)
{
    echo '<tr><th colspan=9 align=left>' . htmlspecialchars($controlPath) . '</th></tr>';

    if (!is_dir($realPath)) {
        return;
    }

    $files = scandir($realPath);
    foreach ($files as $filename) {
        if ($filename === '.' || $filename === '..') {
            continue;
        }

        $fullPath = $realPath . DIRECTORY_SEPARATOR . $filename;
        if (is_file($fullPath)) {
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            if (in_array($ext, ['gif', 'jpg', 'jpeg', 'png'])) {
                $blnFound = FindPictureReference($controlPath, $filename, $imageFields);
                if (!$blnFound) {
                    $unusedCount++;
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

/**
 * Compile a list of ID's in the form of (xx,xx,xx) or ('xx','xx') (if delim equals ')
 */
function table_value_list($strConn, $strTable, $strField, $strDelim)
{
    global $connrep, $conn;
    $strRetval = '';

    $connection = ($strConn === 'rep') ? $connrep : (($strConn === 'users') ? Database::getConnection('users') : $conn);
    $sql = 'SELECT ' . $strField . ' FROM ' . $strTable;
    $rs = Database::openRS($sql, $connection, adOpenForwardOnly);

    if ($rs !== null) {
        $values = [];
        while (!$rs->EOF) {
            $row = $rs->fetchAssoc();
            $val = $row[$strField] ?? array_values($row)[0] ?? '';
            $values[] = $strDelim . $val . $strDelim;
            $rs->MoveNext();
        }
        if (!empty($values)) {
            $strRetval = '(' . implode(', ', $values) . ')';
        } else {
            $strRetval = '()';
        }
    } else {
        $strRetval = '()';
    }

    return $strRetval;
}

main();
