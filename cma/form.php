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

// Get form identifier - JSON form name or legacy FormID
$formName = Request::query('form', '');

// Clean URL support: /form/Parent/parentId/subform/new rewrites to popup=subform&popupID=new
// Translate popup parameters to proper form parameters
$popup = Request::query('popup', '');
$popupID = Request::query('popupID', '');
if ($popup !== '') {
    // The popup is the actual form we want to load
    $parentFormName = $formName; // Original form becomes parent reference
    $formName = $popup;

    // formID becomes parentID
    $formID = Request::query('formID', '');
    if ($formID !== '' && !Request::hasQuery('parentID')) {
        // Internal routing shim: set parentID from formID for popup subform navigation
        $_GET['parentID'] = $formID;
    }

    // popupID of "new" means New=Y
    if (strtolower($popupID) === 'new') {
        // Internal routing shim: translate popup "new" to New=Y parameter
        $_GET['New'] = 'Y';
    } elseif ($popupID !== '') {
        // Internal routing shim: translate popupID to record id parameter
        $_GET['id'] = $popupID;
    }
}

// Legacy support: FormID parameter maps to JSON form via sourceFormId
if (empty($formName)) {
    $formID = Request::queryInt('FormID');
    if ($formID > 0) {
        // Look up JSON form name by sourceFormId
        $formName = JsonFormLoader::getFormNameBySourceId($formID);
    }
}

// Check for direct record mode (ID or id parameter), new record mode (New=Y), or copy mode (copy=Y)
$directRecordId = null;
$isNewMode = Request::query('New', '') === 'Y';
$isCopyMode = Request::query('copy', '') === 'Y';
// Use queryId() - handles numeric, GUID, and alphanumeric IDs like "C47"
$idParam = Request::queryId('ID');
if ($idParam !== '') {
    $directRecordId = $idParam;
} else {
    $idParam = Request::queryId('id');
    if ($idParam !== '') {
        $directRecordId = $idParam;
    } elseif ($isNewMode) {
        $directRecordId = 0; // 0 = new record mode
    }
}
// Copy mode: load the record but treat as new (ID provided + copy=Y)

// Parent context for subforms (filters list and sets default value)
$parentID = Request::query('parentID', '');
$parentField = Request::query('parentField', '');

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
            <strong><?= htmlspecialchars($title) ?></strong><br>
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

// Debug: collect all relevant info BEFORE access check
$_debugCookies = [
    'CMAU' => Cookie::get(SecurityHelper::COOKIE_USERID, '(not set)'),
    'CMAU_ORIGINAL' => Cookie::get('CMAU_ORIGINAL', '(not set)'),
];
$_debugSecurityChecks = [
    'isLoggedIn' => SecurityHelper::isLoggedIn(),
    'isAdmin' => SecurityHelper::isAdmin(),
    'isDeveloper' => SecurityHelper::isDeveloper(),
    'getUserLevel' => SecurityHelper::getUserLevel(),
];

// Output debug as HTTP header (visible in browser DevTools Network tab)
// Add CORS header to expose custom headers to JavaScript
header('Access-Control-Expose-Headers: X-Debug-Cookies, X-Debug-Security, X-Debug-AccessLevel, X-Debug-RawCookie-Keys');
header('X-Debug-Cookies: ' . json_encode($_debugCookies));
header('X-Debug-Security: ' . json_encode($_debugSecurityChecks));
// Also output raw cookie array for comparison
header('X-Debug-RawCookie-Keys: ' . implode(',', array_keys(Cookie::all())));

// Check access rights using centralized menu-based access (same logic as menu filtering)
$accessLevel = MenuService::getFormAccessLevel($formName);

// Add access level to debug
$_debugSecurityChecks['accessLevel'] = $accessLevel;
header('X-Debug-AccessLevel: ' . $accessLevel);

// TEMPORARY: Dump debug info for troubleshooting 403 issue
if (Request::hasQuery('debug') || $accessLevel == SecurityHelper::ACCESS_NONE) {
    error_log("form.php ACCESS DEBUG: form=$formName, cookies=" . json_encode($_debugCookies) . ", security=" . json_encode($_debugSecurityChecks));
}

if ($accessLevel == SecurityHelper::ACCESS_NONE) {
    // Debug info for 403 errors - helps diagnose cookie/session issues
    // ALWAYS show debug info for now to diagnose the issue
    $debugInfo = sprintf(
        '<br><br><strong>Debug info (access denied):</strong><br>' .
        '<small style="color:#666; font-family:monospace;">' .
        'formName: %s<br>' .
        'Cookies: %s<br>' .
        'SecurityChecks: %s<br>' .
        'Cookie keys: %s<br>' .
        'REQUEST_URI: %s<br>' .
        '</small>',
        htmlspecialchars($formName),
        htmlspecialchars(json_encode($_debugCookies)),
        htmlspecialchars(json_encode($_debugSecurityChecks)),
        htmlspecialchars(implode(', ', array_keys(Cookie::all()))),
        htmlspecialchars(Request::server('REQUEST_URI', '(not set)'))
    );
    showFormError('Geen toegang', 'Je hebt geen toegang tot dit formulier.' . $debugInfo, 403);
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
    // - is-creating: new record mode (ID=0 or New=Y or parentID+parentField)
    // - has-record: editing existing record (ID > 0)
    // - mode-detail: direct record access (hides list panel) - unless view param is set
    // - mode-tree/mode-table: when view param is explicitly set
    // - data-loading: hides form until data is loaded (prevents empty form flash)
    $bodyClasses = [];
    // isAddRelatedRecord only applies when there's NO existing record ID
    // When both parentID+parentField AND a record ID exist, we're editing, not creating
    $isAddRelatedRecord = $parentID !== '' && $parentField !== '' && ($directRecordId === null || $directRecordId === 0);
    $viewParam = Request::query('view', '');
    $hasExplicitView = !empty($viewParam);

    if ($isNewMode || $directRecordId === 0 || $isAddRelatedRecord) {
        $bodyClasses[] = 'is-creating';
        $bodyClasses[] = 'mode-detail';
    } elseif ($directRecordId !== null && $directRecordId > 0) {
        $bodyClasses[] = 'has-record';
        // Hide form until data is loaded - prevents skeleton flash (red borders, empty fields)
        // JS removes this class once record data has been applied
        $bodyClasses[] = 'data-loading';
        // Only use mode-detail if no explicit view parameter is set
        // When view=tree or view=table is set, show tree/table with the record
        if (!$hasExplicitView) {
            $bodyClasses[] = 'mode-detail';
        } else {
            // Explicit view: set the corresponding mode class
            $bodyClasses[] = $viewParam === 'table' ? 'mode-table' : 'mode-tree';
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
