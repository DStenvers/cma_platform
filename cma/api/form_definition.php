<?php
/**
 * Form Definition API
 *
 * API for updating JSON form definitions.
 * Developer-only access.
 *
 * Endpoints:
 * - POST ?action=updateSubformParentField - Update parentField for a subform
 */

require_once __DIR__ . '/../bootstrap.inc';

use App\Library\Response;
use App\Library\Request;
use Cma\SecurityHelper;
use Cma\JsonFormLoader;

// Security check - require developer access
if (!SecurityHelper::isDeveloper()) {
    Response::json(['success' => false, 'error' => 'Alleen developers kunnen formulierdefinities aanpassen'], 403);
    exit;
}

Response::noCache();
header('Content-Type: application/json; charset=utf-8');

$action = Request::post('action', Request::query('action', ''));

switch ($action) {
    case 'updateSubformParentField':
        handleUpdateSubformParentField();
        break;

    default:
        Response::json(['success' => false, 'error' => 'Onbekende actie: ' . $action], 400);
}

/**
 * Update parentField for a subform in the parent form's JSON definition
 */
function handleUpdateSubformParentField(): void
{
    $parentFormName = Request::post('parentFormName', '');
    $subformIndex = (int)Request::post('subformIndex', -1);
    $parentField = Request::post('parentField', '');

    // Validate inputs
    if (empty($parentFormName)) {
        Response::json(['success' => false, 'error' => 'parentFormName is verplicht']);
        exit;
    }
    if ($subformIndex < 0) {
        Response::json(['success' => false, 'error' => 'subformIndex is verplicht']);
        exit;
    }
    if (empty($parentField)) {
        Response::json(['success' => false, 'error' => 'parentField is verplicht']);
        exit;
    }

    // Load the parent form definition
    $formDef = JsonFormLoader::loadRaw($parentFormName);
    if (!$formDef) {
        Response::json(['success' => false, 'error' => "Formulier '$parentFormName' niet gevonden"]);
        exit;
    }

    // Check if subforms exist
    if (empty($formDef['subforms']) || !isset($formDef['subforms'][$subformIndex])) {
        Response::json(['success' => false, 'error' => "Subform index $subformIndex niet gevonden in '$parentFormName'"]);
        exit;
    }

    // Update the parentField
    $formDef['subforms'][$subformIndex]['parentField'] = $parentField;

    // Get the form file path
    $filePath = JsonFormLoader::getFilePath($parentFormName);
    if (!$filePath || !file_exists($filePath)) {
        Response::json(['success' => false, 'error' => "Kan formulierbestand niet vinden voor '$parentFormName'"]);
        exit;
    }

    // Save the updated definition
    $json = json_encode($formDef, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        Response::json(['success' => false, 'error' => 'JSON encode fout: ' . json_last_error_msg()]);
        exit;
    }

    if (file_put_contents($filePath, $json) === false) {
        Response::json(['success' => false, 'error' => 'Kon bestand niet opslaan: ' . $filePath]);
        exit;
    }

    // Clear form cache
    JsonFormLoader::clearCache($parentFormName);

    $subformTitle = $formDef['subforms'][$subformIndex]['title'] ?? "Subform $subformIndex";
    Response::json([
        'success' => true,
        'message' => "parentField '$parentField' toegevoegd aan subform '$subformTitle'",
        'formName' => $parentFormName,
        'subformIndex' => $subformIndex,
        'parentField' => $parentField,
    ]);
}
