<?php
/**
 * Unified Form Page
 *
 * Combines list.php + details.php + detailsRepNew.php into a single frameless page.
 * Uses static templates cached in APCu and cma/assets/forms/.
 * Data is loaded dynamically via AJAX.
 *
 * URL Parameters:
 * - form (required): Named form from JSON definitions (e.g., 'users', 'opleidingen')
 * - ID (optional): Record ID to load. If provided, shows only detail area (no list panel)
 *   - ID=0: Add mode without subforms
 *   - ID=N: Edit mode for specific record with subforms
 * - ParentID (optional): Parent record ID for popup mode
 * - ParentField (optional): Parent field name
 * - search (optional): Search term
 */

use App\Library\Application;
use App\Library\Cookie;
use App\Library\Error;
use App\Library\Request;
use App\Library\Response;
use Cma\FormTemplate;
use Cma\JsonFormLoader;
use Cma\SecurityHelper;
use Cma\Services\MenuService;

require_once __DIR__ . '/bootstrap.inc';

// Routing: a single canonical contract (Cma\FormRoute) normalises every
// parameter spelling the IIS rewrite rules and legacy links can emit
// (formID, id/ID, popup/popupID, New, numeric FormID) into one route state.
// This is the server-side mirror of cma/assets/js/url-manager.js, and the
// reason a deep link /cma/form/<form>/<id> opens the record server-side
// without relying on any client-side URL rewriting. See FormRoute for the
// full contract.
require_once __DIR__ . '/classes/FormRoute.php';
$route = \Cma\FormRoute::fromRequest();

$formName       = $route->form;
$directRecordId = $route->recordId;   // null in new/list mode; may be '0'
$isNewMode      = $route->isNew;
$isCopyMode     = $route->isCopy;
$parentID       = $route->parentIdString();
$parentField    = $route->parentField;

// If parentID is provided but parentField is not, try to look it up from the parent form's subform definition
if ($parentID !== '' && $parentField === '' && !empty($formName)) {
    $formDef = JsonFormLoader::loadRaw($formName);
    if ($formDef && !empty($formDef['parentForm'])) {
        // Load parent form definition
        $parentFormDef = JsonFormLoader::loadRaw($formDef['parentForm']);
        if ($parentFormDef && !empty($parentFormDef['subforms'])) {
            // Find this form in the parent's subforms
            foreach ($parentFormDef['subforms'] as $subform) {
                if (isset($subform['form']) && $subform['form'] === $formName && !empty($subform['parentField'])) {
                    $parentField = $subform['parentField'];
                    break;
                }
            }
        }
    }
}

// Helper function to show error in proper layout for iframe/sidepanel context
function showFormError(string $title, string $message, int $httpCode = 400): void {
    // CRITICAL: Never cache error responses
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    http_response_code($httpCode);
    ?><!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($title) ?></title>
    <link rel="stylesheet" href="minify.php?f=assets/css/style.css">
    <?php cma_script('../library/webcomponents/lib-message.js'); ?>
    <style>
        body { background: #f5f5f5; padding: 40px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
        .error-container { max-width: 500px; margin: 0 auto; }
    </style>
</head>
<body>
    <div class="error-container">
        <lib-message type="error">
            <span class="cma-page__strong"><?= htmlspecialchars($title) ?></span><br>
            <?= $message ?>
        </lib-message>
    </div>
</body>
</html><?php
    exit;
}

// Validate form name
if (empty($formName)) {
    showFormError('Ongeldige aanvraag', 'form parameter is verplicht (bijv. form=opleidingen).', 400);
}

// Check if JSON form exists
if (!JsonFormLoader::exists($formName)) {
    showFormError('Formulier niet gevonden', "Het formulier '$formName' bestaat niet.", 404);
}

// Check if cookies are available at all - if not, show helpful error
if (!Cookie::has(SecurityHelper::COOKIE_USERID)) {
    // No cookies - likely a cookie transmission issue
    $cookieDebug = [
        'hasCookies' => !empty(Cookie::all()),
        'cookieKeys' => array_keys(Cookie::all()),
        'requestUri' => Request::server('REQUEST_URI'),
        'httpHost' => Request::server('HTTP_HOST'),
        'isAjax' => !empty(Request::server('HTTP_X_REQUESTED_WITH')),
    ];
    showFormError(
        'Sessie verlopen of cookies niet beschikbaar',
        'Je bent niet ingelogd of je sessie is verlopen. ' .
        '<br><br><a href="/cma/default.php" style="color: #0066cc;">Opnieuw inloggen</a>' .
        '<br><br><small style="color:#666;">Debug: ' . htmlspecialchars(json_encode($cookieDebug)) . '</small>',
        401
    );
}

// Check access rights using centralized menu-based access (same logic as menu filtering)
$accessLevel = MenuService::getFormAccessLevel($formName);

if ($accessLevel == SecurityHelper::ACCESS_NONE) {
    showFormError('Geen toegang', 'Je hebt geen toegang tot dit formulier.', 403);
}

// Validate the form definition before rendering. A form whose JSON is missing
// crucial information must fail loudly on screen, not silently half-work (e.g. a
// checklist whose relation columns are absent saves nothing). Any problem blocks
// the form with the full list. Runs after the access check so definition details
// are never shown to unauthorised users. See JsonFormLoader::validateDefinition().
$defProblems = JsonFormLoader::validateDefinition($formName);
if (!empty($defProblems)) {
    $list = '<ul style="text-align:left; margin:12px 0 0; padding-left:20px;">'
        . implode('', array_map(
            fn($p) => '<li>' . htmlspecialchars($p) . '</li>',
            $defProblems
        ))
        . '</ul>';
    showFormError(
        "Formulierdefinitie '$formName' is ongeldig",
        'De volgende verplichte gegevens ontbreken of zijn onjuist:' . $list,
        500
    );
}

// HTTP Cache optimization with ETag
// Generate ETag from form definition modification time + access level
$formDefPath = __DIR__ . '/assets/forms/definitions/' . $formName . '.json';
if (file_exists($formDefPath)) {
    $etag = '"' . md5(filemtime($formDefPath) . '_' . $accessLevel) . '"';

    // Check If-None-Match header - return 304 if unchanged
    if (Request::server('HTTP_IF_NONE_MATCH') === $etag) {
        http_response_code(304);
        exit;
    }

    header('ETag: ' . $etag);
    header('Cache-Control: public, max-age=86400, stale-while-revalidate=604800');
}

// Get template (from cache or generate)
try {
    $template = FormTemplate::getForJsonForm($formName, $accessLevel);

    // Build data attributes for form state (no more window globals!)
    // These are read by CmaFormController from the form-layout container
    $dataAttrs = [];
    // Always include form name for easy DOM access
    $dataAttrs[] = 'data-json-form="' . htmlspecialchars($formName, ENT_QUOTES) . '"';
    // The definition title (e.g. "E-mail log") so the SPA breadcrumb shows the
    // human title instead of a title-cased form name ("Emaillog") for forms that
    // aren't in the sidebar menu (opened via the launcher). Falls back to name.
    $rawFormDef = JsonFormLoader::loadRaw($formName);
    $formTitle = (is_array($rawFormDef) && !empty($rawFormDef['title'])) ? $rawFormDef['title'] : $formName;
    $dataAttrs[] = 'data-form-title="' . htmlspecialchars($formTitle, ENT_QUOTES) . '"';
    if ($directRecordId !== null) {
        $dataAttrs[] = 'data-record-id="' . htmlspecialchars((string)$directRecordId, ENT_QUOTES) . '"';
    }
    if ($isCopyMode) {
        $dataAttrs[] = 'data-copy-mode="true"';
    }
    if ($parentID !== '') {
        $dataAttrs[] = 'data-parent-id="' . htmlspecialchars($parentID, ENT_QUOTES) . '"';
    }
    if ($parentField !== '') {
        $dataAttrs[] = 'data-parent-field="' . htmlspecialchars($parentField, ENT_QUOTES) . '"';
    }

    // Inject data attributes into form-layout div
    if (!empty($dataAttrs)) {
        $dataAttrStr = ' ' . implode(' ', $dataAttrs);
        $template = preg_replace(
            '/(<div[^>]*class="[^"]*form-layout[^"]*")/',
            '$1' . $dataAttrStr,
            $template,
            1
        );
    }

    // Inject body classes based on current request (template is cached without these)
    // - is-creating: new record mode (New=Y or parentID+parentField without ID)
    // - has-record: editing existing record (any ID including 0)
    // - mode-detail: direct record access (hides list panel) - unless view param is set
    // - mode-tree/mode-table: when view param is explicitly set
    // - data-loading: hides form until data is loaded (prevents empty form flash)
    $bodyClasses = [];
    // isAddRelatedRecord only applies when there's NO existing record ID
    // When both parentID+parentField AND a record ID exist, we're editing, not creating
    $isAddRelatedRecord = $parentID !== '' && $parentField !== '' && $directRecordId === null;
    $viewParam = Request::query('view', '');
    $hasExplicitView = !empty($viewParam);

    if ($isNewMode || $isAddRelatedRecord) {
        $bodyClasses[] = 'is-creating';
        $bodyClasses[] = 'mode-detail';
    } elseif ($directRecordId !== null && $directRecordId !== '') {
        $bodyClasses[] = 'has-record';
        // Hide form until data is loaded - prevents skeleton flash (red borders, empty fields)
        // JS removes this class once record data has been applied
        $bodyClasses[] = 'data-loading';
        if ($hasExplicitView) {
            // Explicit ?view= wins: set the corresponding mode class
            $bodyClasses[] = $viewParam === 'table' ? 'mode-table' : 'mode-tree';
        } else {
            // No explicit view: honour the persisted list mode. form-controller.js stores
            // it in a cookie (cma_listMode_<form>, falling back to cma_lastViewMode) so the
            // server renders the master list + form in the right mode on first paint - no
            // detail->tree flash, and a deep link to a record keeps the list visible.
            $storedMode = Cookie::get('cma_listMode_' . $formName, '');
            if ($storedMode === '') {
                $storedMode = Cookie::get('cma_lastViewMode', '');
            }
            // Display mode 2 = table, 1/empty = tree (see LIST_MODE in form-controller.js)
            $bodyClasses[] = ((string)$storedMode === '2') ? 'mode-table' : 'mode-tree';
        }
    }
    if (!empty($bodyClasses)) {
        // Add classes to body tag
        $template = preg_replace(
            '/<body\s+class="([^"]*)"/',
            '<body class="$1 ' . implode(' ', $bodyClasses) . '"',
            $template
        );
    }

    // In nomenu mode (sidebar layout), strip HTML wrapper and keep only body content
    // CSS/JS is already loaded in main.php shell
    if (defined('CMA_NOMENU_MODE') && CMA_NOMENU_MODE) {
        // Extract body content
        if (preg_match('/<body[^>]*>(.*)<\/body>/is', $template, $matches)) {
            $bodyContent = $matches[1];

            // Extract ALL script tags from the template (head + body)
            // These contain CMA.formConfig and the CmaFormController initialization
            if (preg_match_all('/<script>(.+?)<\/script>/is', $template, $scriptMatches)) {
                foreach ($scriptMatches[1] as $scriptContent) {
                    // Only output scripts that contain CMA config or controller init
                    // Skip external script references (those are in main.php shell)
                    if (strpos($scriptContent, 'CMA.formConfig') !== false ||
                        strpos($scriptContent, 'CmaFormController') !== false ||
                        strpos($scriptContent, 'window.CMA') !== false) {
                        echo '<script>' . $scriptContent . '</script>' . PHP_EOL;
                    }
                }
            }

            // In nomenu mode, body classes need to be applied to parent body in main.php
            // Output a script to add the classes immediately (before content renders)
            if (!empty($bodyClasses)) {
                echo '<script>(function(){var c=' . json_encode($bodyClasses) . ';c.forEach(function(cls){document.body.classList.add(cls);});})();</script>' . PHP_EOL;
            }

            echo $bodyContent;
        } else {
            // Fallback: output full template
            echo $template;
        }
    } else {
        // Output full template
        Response::cacheExpires(1440); // Cache for 24 hours (template is static, data is loaded via AJAX)
        echo $template;
    }
} catch (Exception $e) {
    http_response_code(500);
    Error::page('Fout bij laden formulier', htmlspecialchars($e->getMessage()), true);
}
