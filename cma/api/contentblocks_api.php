<?php
/**
 * Content Blocks API
 *
 * API endpoint for managing content block templates.
 * Operations: list, get, save, delete
 *
 * This replaces direct form post handling for the JSON-based content blocks.
 */

use App\Library\Request;
use App\Library\Response;
use App\Library\Server;
use Cma\SecurityHelper;

require_once __DIR__ . '/../bootstrap.inc';

// Developer-only tool
if (!SecurityHelper::isDeveloper()) {
    Response::json(['success' => false, 'message' => 'Toegang geweigerd'], 403);
    exit;
}

Response::noCache();
header('Content-Type: application/json');

// File path for content blocks (in site root, not CMA)
$contentBlocksFile = dirname(__DIR__, 2) . '/assets/contentblocks/contentblocks.json';

/**
 * Load content blocks from JSON file
 */
function loadContentBlocks(string $file): array {
    if (!file_exists($file)) {
        return [];
    }
    $json = file_get_contents($file);
    $data = json_decode($json, true);
    return $data['templates'] ?? [];
}

/**
 * Save content blocks to JSON file
 */
function saveContentBlocks(string $file, array $blocks): bool {
    $data = ['templates' => array_values($blocks)];
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    return file_put_contents($file, $json) !== false;
}

// Determine action
$action = Request::query('action', Request::post('action', ''));

// GET requests
if (Request::isGet()) {
    $action = $action ?: 'list';

    if ($action === 'list') {
        // Return list of all content blocks
        $blocks = loadContentBlocks($contentBlocksFile);
        $listData = [];
        foreach ($blocks as $block) {
            $listData[] = [
                'id' => $block['id'] ?? '',
                'title' => $block['title'] ?? '',
                'description' => $block['description'] ?? ''
            ];
        }
        Response::json([
            'success' => true,
            'data' => $listData,
            'count' => count($listData)
        ]);
        exit;
    }

    if ($action === 'get') {
        // Get single content block
        $id = Request::query('id', '');
        if (empty($id)) {
            Response::json(['success' => false, 'message' => 'ID is verplicht'], 400);
            exit;
        }

        $blocks = loadContentBlocks($contentBlocksFile);
        foreach ($blocks as $block) {
            if ($block['id'] === $id) {
                // Return block with variables as JSON string for form display
                $block['variables'] = !empty($block['variables'])
                    ? json_encode($block['variables'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
                    : '';
                Response::json(['success' => true, 'data' => $block]);
                exit;
            }
        }

        Response::json(['success' => false, 'message' => 'Blok niet gevonden'], 404);
        exit;
    }
}

// POST requests
if (Request::isPost()) {
    $action = $action ?: 'save';

    if ($action === 'save') {
        $id = Request::post('id', '');
        $title = Request::post('title', '');
        $description = Request::post('description', '');
        $html = Request::post('html', '');
        $variablesJson = Request::post('variables', '');

        // Validate required fields
        if (empty($id)) {
            Response::json(['success' => false, 'message' => 'ID is verplicht'], 400);
            exit;
        }
        if (empty($title)) {
            Response::json(['success' => false, 'message' => 'Titel is verplicht'], 400);
            exit;
        }

        // Check for recursive contentblock references in HTML template
        // Contentblocks are stored as <!--BLOCK{...}--> markers
        if (preg_match('/<!--BLOCK\{/i', $html)) {
            Response::json([
                'success' => false,
                'message' => 'HTML template mag geen contentblocks bevatten (zou recursie veroorzaken)'
            ], 400);
            exit;
        }

        // Parse variables JSON
        $variables = [];
        if (!empty($variablesJson)) {
            $variables = json_decode($variablesJson, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                Response::json([
                    'success' => false,
                    'message' => 'Ongeldige JSON in variabelen: ' . json_last_error_msg()
                ], 400);
                exit;
            }
        }

        $blocks = loadContentBlocks($contentBlocksFile);

        // Find existing or add new
        $found = false;
        foreach ($blocks as &$block) {
            if ($block['id'] === $id) {
                $block['title'] = $title;
                $block['description'] = $description;
                $block['html'] = $html;
                $block['variables'] = $variables;
                $found = true;
                break;
            }
        }
        unset($block);

        if (!$found) {
            // Add new block
            $blocks[] = [
                'id' => $id,
                'title' => $title,
                'description' => $description,
                'html' => $html,
                'variables' => $variables
            ];
        }

        if (saveContentBlocks($contentBlocksFile, $blocks)) {
            Response::json([
                'success' => true,
                'message' => $found ? 'Blok bijgewerkt' : 'Blok toegevoegd',
                'id' => $id
            ]);
        } else {
            Response::json(['success' => false, 'message' => 'Kon bestand niet opslaan'], 500);
        }
        exit;
    }

    if ($action === 'delete') {
        $id = Request::post('id', Request::query('id', ''));

        if (empty($id)) {
            Response::json(['success' => false, 'message' => 'ID is verplicht'], 400);
            exit;
        }

        $blocks = loadContentBlocks($contentBlocksFile);
        $originalCount = count($blocks);
        $blocks = array_filter($blocks, fn($b) => ($b['id'] ?? '') !== $id);

        if (count($blocks) === $originalCount) {
            Response::json(['success' => false, 'message' => 'Blok niet gevonden'], 404);
            exit;
        }

        if (saveContentBlocks($contentBlocksFile, $blocks)) {
            Response::json(['success' => true, 'message' => 'Blok verwijderd']);
        } else {
            Response::json(['success' => false, 'message' => 'Kon bestand niet opslaan'], 500);
        }
        exit;
    }
}

// Unknown action
Response::json(['success' => false, 'message' => 'Onbekende actie'], 400);
