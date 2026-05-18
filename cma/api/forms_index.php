<?php
/**
 * API Endpoint: list of available form definitions.
 *
 * GET /cma/api/forms_index.php           — returns all forms
 * GET /cma/api/forms_index.php?q=foo     — substring-filtered
 *
 * Backs the `form` field combo on _menu_items (and any other place
 * that needs to pick from "all forms in this CMA").  Skips system
 * forms with a leading underscore (_menus, _menu_items, etc.) and
 * subform definitions (which always contain an underscore after the
 * parent form name).
 *
 * Response shape matches what lib-combo's ajax mode expects:
 *   [{ "id": "users", "text": "Gebruikers (users)" }, ...]
 */

use App\Library\Request;
use App\Library\Response;
use Cma\JsonFormLoader;
use Cma\SecurityHelper;

require_once __DIR__ . '/../bootstrap.inc';

Response::noCache();
header('Content-Type: application/json; charset=utf-8');

if (!SecurityHelper::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Niet ingelogd']);
    exit;
}

$q = strtolower(trim((string) Request::query('q', '')));

$forms = JsonFormLoader::listForms();
sort($forms, SORT_STRING | SORT_FLAG_CASE);

$results = [];
foreach ($forms as $name) {
    // Skip system forms (leading underscore) — they're not user-pickable
    if ($name === '' || $name[0] === '_') continue;

    $def   = JsonFormLoader::loadRaw($name);
    $title = $def['title'] ?? $name;
    $text  = $title === $name ? $name : sprintf('%s (%s)', $title, $name);

    if ($q !== '' && stripos($name, $q) === false && stripos($title, $q) === false) {
        continue;
    }

    $results[] = ['id' => $name, 'text' => $text];
}

echo json_encode($results);
