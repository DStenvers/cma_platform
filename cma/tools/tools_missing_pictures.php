<?php
use App\Library\Application;
use Cma\ToolbarHelper;
use Cma\JsonFormLoader;
use Cma\CmaRepository;
use App\Library\Database;
use App\Library\Response;
use App\Library\Server;

require_once __DIR__ . '/../bootstrap.inc';
Response::noCache();
cma_html_header('CMA - Missing Pictures');
echo '<BODY class="contentbody tools">';

/**
 * Get all image fields from JSON form definitions
 *
 * @return array Array of image field configurations
 */
function getImageFieldsFromJson(): array
{
    $imageFields = [];
    $formNames = JsonFormLoader::listForms();

    foreach ($formNames as $formName) {
        $formDef = JsonFormLoader::loadRaw($formName);
        if ($formDef === null) {
            continue;
        }

        // Get database connection info
        $database = $formDef['database'] ?? 'data';
        $table = $formDef['table'] ?? '';

        if (empty($table)) {
            continue;
        }

        // Check each control for image types
        $controls = $formDef['controls'] ?? [];
        foreach ($controls as $control) {
            $controlType = $control['type'] ?? '';

            // Check for image or thumbnail types
            if ($controlType === 'image' || $controlType === 'thumbnail') {
                $imageFields[] = [
                    'formName' => $formDef['title'] ?? $formName,
                    'table' => $table,
                    'database' => $database,
                    'fieldName' => $control['field'] ?? '',
                    'caption' => $control['label'] ?? $control['field'] ?? '',
                    'imgPath' => $control['imgPath'] ?? $control['path'] ?? '',
                ];
            }
        }
    }

    return $imageFields;
}

/**
 * Main
 */
function main()
{
    global $conn;

    ToolbarHelper::report('Missing Pictures', false, false, false);
    echo '<div id="c" class="tools">';

    // Get all image fields from JSON form definitions
    $imageFields = getImageFieldsFromJson();

    if (empty($imageFields)) {
        echo '<p>Geen afbeeldingsvelden gevonden in formulierdefinities.</p>';
        echo '</div></BODY></HTML>';
        return;
    }

    echo '<TABLE width=100% cellspacing=2 cellpadding=2 border=0>';

    $strCurrentColor = Application::get('color_row1', '');
    $currentDatabase = null;

    foreach ($imageFields as $field) {
        if (empty($field['fieldName']) || empty($field['table'])) {
            continue;
        }

        echo '<TR><TH colspan=2 align=left><B>' . htmlspecialchars($field['formName']) . ' (' . htmlspecialchars($field['caption']) . ')</B></TH></TR>';

        // Open correct database connection
        $database = $field['database'];
        if ($database !== $currentDatabase) {
            if (is_numeric($database)) {
                CmaRepository::openConnectionById((int)$database);
            } else {
                $conn = Database::getConnection($database ?: 'data');
            }
            $currentDatabase = $database;
        }

        // Retrieve the expected files and try to find them on the server
        $picturesSQL = 'SELECT [' . $field['fieldName'] . '] as Filename FROM [' . $field['table'] . '] WHERE ([' . $field['fieldName'] . "] <> '' AND [" . $field['fieldName'] . '] IS NOT NULL) ORDER BY 1';

        $picturesRS = Database::openRS($picturesSQL, $conn, adOpenForwardOnly, adLockReadOnly);
        if ($picturesRS === null) {
            echo '<TR><TD colspan=2>Fout bij ophalen: ' . htmlspecialchars(Database::getLastError()) . '</TD></TR>';
            continue;
        }

        $missingCount = 0;
        while (!$picturesRS->EOF) {
            $filename = $picturesRS->fields['Filename'] ?? '';
            if (!empty($filename)) {
                $strFileName = Application::get('base_path', '') . $field['imgPath'] . $filename;
                $strRealFileName = Server::mapPath($strFileName);
                if (!file_exists($strRealFileName)) {
                    echo '<TR><TD>' . htmlspecialchars($strFileName) . '</TD></TR>';
                    $missingCount++;
                }
            }
            $picturesRS->MoveNext();
        }

        if ($missingCount === 0) {
            echo '<TR><TD style="color:green">Alle afbeeldingen aanwezig</TD></TR>';
        }

        // Alternate row colors
        $strCurrentColor = ($strCurrentColor === Application::get('color_row1', ''))
            ? Application::get('color_row2', '')
            : Application::get('color_row1', '');
    }

    echo '</TABLE>';
    echo '</div></BODY></HTML>';
}

main();
