<?php
/**
 * Update Menu JSON to use form names instead of IDs
 *
 * Replaces formId with formName in config/menu.json
 * The formName is the JSON filename (without .json extension)
 */

use App\Library\Database;
use App\Library\Response;
use Cma\JsonFormLoader;
use Cma\SecurityHelper;
use Cma\Services\MenuService;

require_once __DIR__ . '/../bootstrap.inc';

// Check if running as migration
$isMigration = defined('MIGRATION_RUNNING') || php_sapi_name() === 'cli' || \App\Library\Request::hasQuery('migration');

if (!$isMigration) {
    if (!SecurityHelper::isDeveloper()) {
        http_response_code(403);
        echo "Toegang geweigerd - alleen developers";
        exit(1);
    }
    Response::noCache();
}

$result = updateMenuFormNames();

if ($isMigration) {
    if ($result['success']) {
        echo "✓ " . $result['message'] . "\n";
        if (!empty($result['details'])) {
            foreach ($result['details'] as $detail) {
                echo "  - " . $detail . "\n";
            }
        }
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
    cma_html_header('Menu formnames bijwerken');
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
        echo '<lib-message type="success"><strong>Menu succesvol bijgewerkt!</strong><br>' . htmlspecialchars($result['message']) . $detailsHtml . '</lib-message>';
    } else {
        echo '<lib-message type="error"><strong>Bijwerken mislukt!</strong><br>' . htmlspecialchars($result['message']) . '</lib-message>';
    }

    echo '</div></body></html>';
}

/**
 * Update menu.json to use form names instead of IDs
 */
function updateMenuFormNames(): array
{
    global $connrep;

    $details = [];
    $menuPath = dirname(__DIR__, 2) . '/data/menu.json';
    $definitionsDir = __DIR__ . '/../assets/forms/definitions';

    try {
        // Load current menu
        if (!file_exists($menuPath)) {
            return ['success' => false, 'message' => 'menu.json niet gevonden'];
        }

        $menuJson = file_get_contents($menuPath);
        $menuData = json_decode($menuJson, true);

        if ($menuData === null) {
            return ['success' => false, 'message' => 'Kan menu.json niet parsen: ' . json_last_error_msg()];
        }

        // Build a map of formId to JSON filename by scanning all definition files
        $formIdToName = [];
        $jsonFiles = glob($definitionsDir . '/*.json');

        foreach ($jsonFiles as $file) {
            $content = file_get_contents($file);
            $formDef = json_decode($content, true);
            if ($formDef && isset($formDef['sourceFormId'])) {
                $jsonName = basename($file, '.json');
                $formIdToName[$formDef['sourceFormId']] = $jsonName;
            }
        }

        $details[] = "Gevonden: " . count($formIdToName) . " formulieren met sourceFormId";

        // Build a map of disabled/invisible forms from database
        $disabledForms = [];
        $rs = Database::openRS("SELECT ID, FormName, Visible FROM tblForms WHERE Visible = False OR Visible IS NULL", $connrep);
        if ($rs !== null) {
            while (!$rs->EOF) {
                $disabledForms[(int)$rs->fields['ID']] = $rs->fields['FormName'] ?? '';
                $rs->MoveNext();
            }
        }

        // Update menu items
        $updated = 0;
        $skippedDisabled = 0;
        $skippedDisabledList = [];

        foreach ($menuData['menus'] as &$menu) {
            foreach ($menu['items'] as &$item) {
                if (isset($item['formId'])) {
                    $formId = (int)$item['formId'];
                    if (isset($formIdToName[$formId])) {
                        // Replace formId with formName
                        $item['formName'] = $formIdToName[$formId];
                        unset($item['formId']);
                        unset($item['form']); // Remove old display name
                        $updated++;
                    } elseif (isset($disabledForms[$formId])) {
                        // Form is disabled in database - skip silently, just count
                        $skippedDisabled++;
                        $formName = $disabledForms[$formId] ?: $item['name'] ?? '';
                        $skippedDisabledList[] = "{$formId} ({$formName})";
                    } else {
                        // Form exists in menu but has no JSON and is not disabled - this is unexpected
                        $details[] = "Overgeslagen: formId {$formId} ({$item['name']}) - formulier niet gevonden in database of JSON";
                    }
                }
            }
            unset($item);
        }
        unset($menu);

        // Add summary for disabled forms
        if ($skippedDisabled > 0) {
            $details[] = "Overgeslagen: {$skippedDisabled} uitgeschakelde formulieren (Visible=False)";
        }

        // Save updated menu
        $newJson = json_encode($menuData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($newJson === false) {
            return ['success' => false, 'message' => 'Kan JSON niet encoderen: ' . json_last_error_msg()];
        }

        if (file_put_contents($menuPath, $newJson) === false) {
            return ['success' => false, 'message' => 'Kan menu.json niet schrijven'];
        }

        // Clear menu cache
        MenuService::clearCache();

        $message = "Bijgewerkt: {$updated} menu items met formName";
        if ($skippedDisabled > 0) {
            $message .= ", {$skippedDisabled} uitgeschakeld";
        }

        return [
            'success' => true,
            'message' => $message,
            'details' => $details
        ];

    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Fout: ' . $e->getMessage()];
    }
}
