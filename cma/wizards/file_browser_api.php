<?php
/**
 * File Browser API
 *
 * JSON API for the modern file browser component.
 * Handles listing, uploading, and deleting files.
 */

use App\Library\Application;
use App\Library\Request;
use App\Library\Response;
use App\Library\Server;
use App\Library\File;

require_once __DIR__ . '/../bootstrap.inc';

Response::noCache();
header('Content-Type: application/json');

// Parameters
$action = Request::query('action', Request::post('action', 'list'));
$basePath = Request::query('basepath', Request::post('basepath', '/'));
$currentPath = Request::query('path', Request::post('path', ''));
$imageOnly = Request::query('image', '') !== '';
$file = Request::query('file', Request::post('file', ''));

// Security: Ensure basePath ends with /
if (substr($basePath, -1) !== '/') {
    $basePath .= '/';
}

// Security: Clean and validate path (no directory traversal)
$currentPath = str_replace(['..', '\\'], ['', '/'], $currentPath);
$currentPath = preg_replace('#/+#', '/', $currentPath);
if ($currentPath && substr($currentPath, 0, 1) === '/') {
    $currentPath = substr($currentPath, 1);
}

// Get mapped path
$rootPath = Server::mapPath($basePath);
$fullPath = $rootPath . str_replace('/', DIRECTORY_SEPARATOR, $currentPath);
$fullPath = rtrim($fullPath, DIRECTORY_SEPARATOR);

// Handle actions
try {
    switch ($action) {
        case 'list':
            handleList($basePath, $currentPath, $fullPath, $imageOnly);
            break;

        case 'delete':
            handleDelete($fullPath, $file);
            break;

        case 'upload':
            handleUpload($fullPath);
            break;

        case 'mkdir':
            handleMkdir($fullPath, Request::post('name', ''));
            break;

        default:
            jsonError('Onbekende actie: ' . $action);
    }
} catch (Exception $e) {
    jsonError($e->getMessage());
}

/**
 * List files and folders
 */
function handleList($basePath, $currentPath, $fullPath, $imageOnly)
{
    if (!is_dir($fullPath)) {
        // Try to create directory
        if (!File::createFolder($fullPath)) {
            jsonError('Map bestaat niet: ' . $fullPath);
            return;
        }
    }

    $items = [];
    $breadcrumb = buildBreadcrumb($basePath, $currentPath);

    // List contents
    $entries = @scandir($fullPath);
    if ($entries === false) {
        jsonError('Kan map niet lezen');
        return;
    }

    // First: folders
    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..') continue;

        $entryPath = $fullPath . DIRECTORY_SEPARATOR . $entry;
        $lowerEntry = strtolower($entry);

        if (is_dir($entryPath)) {
            // Skip hidden and system folders
            if (substr($lowerEntry, 0, 1) === '.' ||
                substr($lowerEntry, 0, 3) === '_vt' ||
                $lowerEntry === '_private') {
                continue;
            }

            $items[] = [
                'type' => 'folder',
                'name' => $entry,
                'path' => $currentPath . $entry . '/'
            ];
        }
    }

    // Then: files
    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..') continue;

        $entryPath = $fullPath . DIRECTORY_SEPARATOR . $entry;

        if (is_file($entryPath)) {
            $isImage = isImageFile($entry);

            // If imageOnly mode, skip non-images
            if ($imageOnly && !$isImage) {
                continue;
            }

            $stat = @stat($entryPath);
            $items[] = [
                'type' => 'file',
                'name' => $entry,
                'size' => $stat ? $stat['size'] : 0,
                'modified' => $stat ? date('Y-m-d H:i', $stat['mtime']) : '',
                'isImage' => $isImage
            ];
        }
    }

    echo json_encode([
        'success' => true,
        'basePath' => $basePath,
        'currentPath' => $currentPath,
        'breadcrumb' => $breadcrumb,
        'items' => $items
    ]);
}

/**
 * Build breadcrumb array
 */
function buildBreadcrumb($basePath, $currentPath)
{
    $breadcrumb = [
        ['name' => basename($basePath) ?: 'Root', 'path' => '']
    ];

    if ($currentPath) {
        $parts = array_filter(explode('/', $currentPath));
        $accumulated = '';
        foreach ($parts as $part) {
            $accumulated .= $part . '/';
            $breadcrumb[] = [
                'name' => $part,
                'path' => $accumulated
            ];
        }
    }

    return $breadcrumb;
}

/**
 * Delete a file
 */
function handleDelete($fullPath, $file)
{
    if (empty($file)) {
        jsonError('Geen bestand opgegeven');
        return;
    }

    // Security: no directory traversal
    if (strpos($file, '..') !== false || strpos($file, '/') !== false || strpos($file, '\\') !== false) {
        jsonError('Ongeldige bestandsnaam');
        return;
    }

    $filePath = $fullPath . DIRECTORY_SEPARATOR . $file;

    if (!file_exists($filePath)) {
        jsonError('Bestand bestaat niet');
        return;
    }

    if (is_dir($filePath)) {
        jsonError('Kan geen mappen verwijderen via deze functie');
        return;
    }

    if (!@unlink($filePath)) {
        jsonError('Kan bestand niet verwijderen');
        return;
    }

    echo json_encode(['success' => true]);
}

/**
 * Handle file upload
 */
function handleUpload($fullPath)
{
    if (!is_dir($fullPath)) {
        jsonError('Doelmap bestaat niet');
        return;
    }

    if (empty($_FILES['files'])) {
        jsonError('Geen bestanden ontvangen');
        return;
    }

    $files = $_FILES['files'];
    $uploaded = [];
    $errors = [];

    // Handle multiple files
    if (is_array($files['name'])) {
        for ($i = 0; $i < count($files['name']); $i++) {
            if ($files['error'][$i] === UPLOAD_ERR_OK) {
                $name = sanitizeFilename($files['name'][$i]);
                $dest = $fullPath . DIRECTORY_SEPARATOR . $name;

                if (move_uploaded_file($files['tmp_name'][$i], $dest)) {
                    $uploaded[] = $name;
                } else {
                    $errors[] = $name . ': upload mislukt';
                }
            } else {
                $errors[] = $files['name'][$i] . ': fout ' . $files['error'][$i];
            }
        }
    } else {
        // Single file
        if ($files['error'] === UPLOAD_ERR_OK) {
            $name = sanitizeFilename($files['name']);
            $dest = $fullPath . DIRECTORY_SEPARATOR . $name;

            if (move_uploaded_file($files['tmp_name'], $dest)) {
                $uploaded[] = $name;
            } else {
                $errors[] = $name . ': upload mislukt';
            }
        } else {
            $errors[] = $files['name'] . ': fout ' . $files['error'];
        }
    }

    echo json_encode([
        'success' => count($errors) === 0,
        'uploaded' => $uploaded,
        'errors' => $errors
    ]);
}

/**
 * Create directory
 */
function handleMkdir($fullPath, $name)
{
    if (empty($name)) {
        jsonError('Geen mapnaam opgegeven');
        return;
    }

    // Security: sanitize name
    $name = sanitizeFilename($name);

    $newPath = $fullPath . DIRECTORY_SEPARATOR . $name;

    if (file_exists($newPath)) {
        jsonError('Map bestaat al');
        return;
    }

    if (!@mkdir($newPath, 0755, true)) {
        jsonError('Kan map niet aanmaken');
        return;
    }

    echo json_encode(['success' => true, 'name' => $name]);
}

/**
 * Check if file is an image
 */
function isImageFile($filename)
{
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp', 'bmp', 'ico']);
}

/**
 * Sanitize filename
 */
function sanitizeFilename($name)
{
    // Remove directory traversal
    $name = basename($name);
    // Remove special characters
    $name = preg_replace('/[^\w\.\-\s]/', '', $name);
    // Remove multiple spaces/dots
    $name = preg_replace('/\s+/', ' ', $name);
    $name = preg_replace('/\.+/', '.', $name);
    return trim($name);
}

/**
 * Return JSON error
 */
function jsonError($message)
{
    echo json_encode([
        'success' => false,
        'error' => $message
    ]);
}
