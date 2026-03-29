<?php
/**
 * Copy and Version ContentBlocks JSON - Migration Script
 *
 * Copies cma_contentblocks.json from site root to cma/assets/contentblocks/
 * and adds version field to the file.
 */

use App\Library\Response;
use Cma\SecurityHelper;

// Fix path when running as migration
$basePath = defined('MIGRATION_RUNNING') ? dirname(__DIR__) : __DIR__;
if (strpos($basePath, 'migrations') !== false) {
    $basePath = dirname($basePath);
}
require_once $basePath . '/bootstrap.inc';

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

// Copy and version contentblocks
$result = copyAndVersionContentBlocks();

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
    cma_html_header('ContentBlocks kopieren en versie toevoegen');
    echo '<body class="contentbody tools">';
    echo '<div id="c">';

    if ($result['success']) {
        $detailsHtml = '';
        if (!empty($result['details'])) {
            $detailsHtml = '<ul>';
            foreach ($result['details'] as $detail) {
                $detailsHtml .= '<li>' . htmlspecialchars($detail) . '</li>';
            }
            $detailsHtml .= '</ul>';
        }
        echo '<lib-message type="success"><strong>ContentBlocks bijgewerkt!</strong><br>' . htmlspecialchars($result['message']) . $detailsHtml . '</lib-message>';
    } else {
        echo '<lib-message type="error"><strong>Update mislukt!</strong><br>' . htmlspecialchars($result['message']) . '</lib-message>';
    }

    echo '</div></body></html>';
}

/**
 * Copy contentblocks from site root and add version field
 */
function copyAndVersionContentBlocks(): array
{
    $version = '1.0.0';
    $siteRoot = dirname(dirname(__DIR__)); // site/ folder
    $sourcePath = $siteRoot . '/cma_contentblocks.json';
    $targetDir = dirname(__DIR__) . '/assets/contentblocks';
    $targetPath = $targetDir . '/contentblocks.json';
    $details = [];

    try {
        // Ensure target directory exists
        if (!is_dir($targetDir)) {
            if (!mkdir($targetDir, 0755, true)) {
                return ['success' => false, 'message' => 'Kan directory niet aanmaken: ' . $targetDir];
            }
            $details[] = 'Directory aangemaakt: assets/contentblocks/';
        }

        // Check if source file exists in site root
        $sourceExists = file_exists($sourcePath);
        $targetExists = file_exists($targetPath);

        // Determine which file to use as source
        $contentToProcess = null;

        if ($sourceExists) {
            // Copy from site root
            $contentToProcess = file_get_contents($sourcePath);
            if ($contentToProcess === false) {
                return ['success' => false, 'message' => 'Kan cma_contentblocks.json niet lezen'];
            }
            $details[] = 'Bronbestand gelezen: cma_contentblocks.json';
        } elseif ($targetExists) {
            // Use existing target file
            $contentToProcess = file_get_contents($targetPath);
            if ($contentToProcess === false) {
                return ['success' => false, 'message' => 'Kan contentblocks.json niet lezen'];
            }
            $details[] = 'Bestaand bestand gebruikt: assets/contentblocks/contentblocks.json';
        } else {
            return ['success' => false, 'message' => 'Geen contentblocks bestand gevonden (verwacht: site/cma_contentblocks.json of cma/assets/contentblocks/contentblocks.json)'];
        }

        // Parse JSON
        $json = json_decode($contentToProcess, true);
        if ($json === null) {
            return ['success' => false, 'message' => 'Ongeldige JSON: ' . json_last_error_msg()];
        }

        // Check if already has correct version
        if (isset($json['version']) && $json['version'] === $version) {
            return [
                'success' => true,
                'message' => 'contentblocks.json heeft al versie ' . $version,
                'details' => $details
            ];
        }

        // Build new JSON with schema and version at the beginning
        $newJson = [
            '$schema' => '../forms/schema/contentblocks.schema.json',
            'version' => $version,
            'description' => 'Content block templates for rich content editing',
        ];

        // Merge with existing content (preserving templates array)
        foreach ($json as $key => $value) {
            if ($key !== '$schema' && $key !== 'version' && $key !== 'description') {
                $newJson[$key] = $value;
            }
        }

        // Count templates
        $templateCount = count($newJson['templates'] ?? []);

        // Save to target location
        $newContent = json_encode($newJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (file_put_contents($targetPath, $newContent) === false) {
            return ['success' => false, 'message' => 'Kan contentblocks.json niet opslaan'];
        }
        $details[] = 'Bestand opgeslagen met versie ' . $version;
        $details[] = $templateCount . ' templates verwerkt';

        return [
            'success' => true,
            'message' => 'ContentBlocks gekopieerd en versie ' . $version . ' toegevoegd',
            'details' => $details
        ];

    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Fout: ' . $e->getMessage()];
    }
}
