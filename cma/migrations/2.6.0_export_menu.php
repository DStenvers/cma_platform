<?php
/**
 * Export Menu to JSON
 *
 * Exports tblMenu and tblMenuItems from the repository database
 * to config/menu.json for JSON-based menu handling.
 *
 * Can be run standalone or as a migration script.
 */

use App\Library\Database;
use App\Library\Response;
use App\Library\Server;
use Cma\SecurityHelper;

// Fix path when running as migration
$basePath = defined('MIGRATION_RUNNING') ? dirname(__DIR__) : __DIR__;
if (strpos($basePath, 'migrations') !== false) {
    $basePath = dirname($basePath);
}
require_once $basePath . '/bootstrap.inc';

// Check if running as migration (CLI, internal call, or ?migration=1) or standalone
$isMigration = defined('MIGRATION_RUNNING') || php_sapi_name() === 'cli' || \App\Library\Request::hasQuery('migration');

if (!$isMigration) {
    // Standalone mode - check permissions
    if (!SecurityHelper::isDeveloper()) {
        http_response_code(403);
        echo "Toegang geweigerd - alleen developers";
        exit(1);
    }
    Response::noCache();
}

// Export menu data
$result = exportMenuToJson();

if ($isMigration) {
    // Return result for migration service
    if ($result['success']) {
        echo "✓ " . $result['message'] . "\n";
        if (!empty($result['details'])) {
            foreach ($result['details'] as $detail) {
                echo "  - " . $detail . "\n";
            }
        }
        // Use return instead of exit when included directly (MIGRATION_RUNNING constant)
        // This allows the calling script to continue
        if (defined('MIGRATION_RUNNING')) {
            return true;
        }
        exit(0);
    } else {
        echo "✗ " . $result['message'] . "\n";
        if (defined('MIGRATION_RUNNING')) {
            return false;
        }
        exit(1);
    }
} else {
    // Standalone mode - show HTML result
    cma_html_header('Menu exporteren');
    echo '<body class="contentbody tools">';
    echo '<div id="c">';

    if ($result['success']) {
        $detailsHtml = '';
        if (!empty($result['details'])) {
            $detailsHtml = '<br><strong>Details:</strong><ul>';
            foreach ($result['details'] as $detail) {
                $detailsHtml .= '<li>' . htmlspecialchars($detail) . '</li>';
            }
            $detailsHtml .= '</ul>';
        }
        echo '<lib-message type="success"><strong>Menu succesvol geëxporteerd!</strong><br>' . htmlspecialchars($result['message']) . $detailsHtml . '</lib-message>';

        echo '<h3>Gegenereerd bestand</h3>';
        echo '<pre style="background:#f5f5f5;padding:15px;border-radius:4px;max-height:500px;overflow:auto;">';
        echo htmlspecialchars(file_get_contents(dirname(__DIR__, 2) . '/data/menu.json'));
        echo '</pre>';
    } else {
        echo '<lib-message type="error"><strong>Export mislukt!</strong><br>' . htmlspecialchars($result['message']) . '</lib-message>';
    }

    echo '</div></body></html>';
}

/**
 * Export menu tables to JSON file
 */
function exportMenuToJson(): array
{
    $details = [];

    try {
        // Get all menus
        $menuSql = "SELECT ID, Name, ExecutionOrder FROM tblMenu ORDER BY ExecutionOrder, Name";
        $menuRs = Database::openRS($menuSql, 'rep');

        if ($menuRs === null) {
            return ['success' => false, 'message' => 'Kan tblMenu niet lezen: ' . Database::getLastError()];
        }

        $menus = [];
        $menuCount = 0;

        while (!$menuRs->EOF) {
            $menuId = (int)$menuRs->fields['ID'];
            $menuName = $menuRs->fields['Name'];

            $menu = [
                'id' => $menuId,
                'name' => $menuName,
                'order' => (int)($menuRs->fields['ExecutionOrder'] ?? 0),
                'items' => []
            ];

            // Get menu items for this menu
            $itemSql = "SELECT mi.ID, mi.Name, mi.Href, mi.ExecutionOrder, mi.fkFormID, " .
                       "f.FormName, f.ID as FormID " .
                       "FROM tblMenuItems mi " .
                       "LEFT JOIN tblForms f ON mi.fkFormID = f.ID " .
                       "WHERE mi.fkMenuID = " . $menuId . " " .
                       "ORDER BY mi.ExecutionOrder, mi.Name";

            $itemRs = Database::openRS($itemSql, 'rep');

            if ($itemRs !== null) {
                while (!$itemRs->EOF) {
                    $item = [
                        'id' => (int)$itemRs->fields['ID'],
                        'name' => $itemRs->fields['Name'] ?? '',
                        'order' => (int)($itemRs->fields['ExecutionOrder'] ?? 0),
                        'visible' => true
                    ];

                    // Add form reference if available
                    $formId = $itemRs->fields['fkFormID'];
                    $formName = $itemRs->fields['FormName'];
                    $href = $itemRs->fields['Href'] ?? '';

                    if ($formId && $formName) {
                        $item['formId'] = (int)$formId;
                        $item['form'] = $formName;
                    } elseif ($href) {
                        // Convert old href formats to new
                        $item['href'] = convertHref($href);
                    }

                    $menu['items'][] = $item;
                    $itemRs->MoveNext();
                }
            }

            $menus[] = $menu;
            $menuCount++;
            $details[] = "Menu '{$menuName}': " . count($menu['items']) . " items";

            $menuRs->MoveNext();
        }

        // Build final structure with version
        $config = [
            '$schema' => 'schema/menu.schema.json',
            'version' => '1.0.0',
            'menus' => $menus
        ];

        // Write to site-level config (not CMA internal)
        $jsonPath = dirname(__DIR__, 2) . '/data/menu.json';
        $json = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            return ['success' => false, 'message' => 'JSON encoding mislukt: ' . json_last_error_msg()];
        }

        if (file_put_contents($jsonPath, $json) === false) {
            return ['success' => false, 'message' => 'Kan bestand niet schrijven: ' . $jsonPath];
        }

        $totalItems = array_sum(array_map(fn($m) => count($m['items']), $menus));

        return [
            'success' => true,
            'message' => "Geëxporteerd: {$menuCount} menu's, {$totalItems} items naar config/menu.json",
            'details' => $details
        ];

    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Fout: ' . $e->getMessage()];
    }
}

/**
 * Convert old href formats to new format
 */
function convertHref(string $href): string
{
    // Remove target specifications
    $href = preg_replace('/\s*target=\w+/i', '', $href);
    $href = trim($href);

    // Convert contentframe.php URLs
    if (strpos($href, 'contentframe.php') !== false) {
        // Extract pageL parameter
        if (preg_match('/pageL=([^&\s]+)/', $href, $matches)) {
            $pageL = $matches[1];

            // Map known pages to JSON forms
            $pageToForm = [
                'url_list.php' => 'form.php?formname=marketing_urls',
                'mod_list.php' => 'form.php?formname=modules',
            ];

            if (isset($pageToForm[$pageL])) {
                return $pageToForm[$pageL];
            }

            // Return just the pageL for other pages
            return $pageL;
        }
    }

    // Convert .asp to .php
    $href = str_ireplace('.asp', '.php', $href);

    return $href;
}
