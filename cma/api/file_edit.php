<?php
/**
 * File Edit API - Opens files in the system editor
 *
 * SECURITY: Only available in local environment (L) for developers
 */

use App\Library\Application;
use App\Library\Response;
use Cma\SecurityHelper;

require_once __DIR__ . '/../bootstrap.inc';

Response::noCache();
header('Content-Type: application/json');

// Security checks
if (!SecurityHelper::isDeveloper()) {
    echo json_encode(['success' => false, 'error' => 'Toegang geweigerd']);
    exit;
}

$env = Application::get('omgeving', '');
$isDev = in_array($env, ['L', 'O', 'T']);
if (!$isDev) {
    echo json_encode(['success' => false, 'error' => 'Alleen beschikbaar in ontwikkelomgeving']);
    exit;
}

// Get request data
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';
$path = $input['path'] ?? '';

if ($action !== 'open_editor' || empty($path)) {
    echo json_encode(['success' => false, 'error' => 'Ongeldige aanvraag']);
    exit;
}

// Validate file exists and is readable
if (!file_exists($path)) {
    echo json_encode(['success' => false, 'error' => 'Bestand niet gevonden']);
    exit;
}

// Only allow specific file types for security
$allowedExtensions = ['ini', 'conf', 'cfg', 'json', 'env'];
$extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
$basename = basename($path);

// Allow php.ini specifically, or files with allowed extensions
$isAllowed = $basename === 'php.ini'
          || $basename === '.env'
          || in_array($extension, $allowedExtensions);

if (!$isAllowed) {
    echo json_encode(['success' => false, 'error' => 'Bestandstype niet toegestaan']);
    exit;
}

// Try to open in system editor
$success = false;
$escapedPath = escapeshellarg($path);

if (PHP_OS_FAMILY === 'Windows') {
    // Windows: try VS Code, then Notepad++, then notepad
    $editors = [
        'code ' . $escapedPath,                    // VS Code
        'notepad++ ' . $escapedPath,               // Notepad++
        'notepad ' . $escapedPath,                 // Notepad (fallback)
    ];

    foreach ($editors as $cmd) {
        // Use popen to run in background without waiting
        $handle = @popen('start /B ' . $cmd . ' 2>NUL', 'r');
        if ($handle !== false) {
            pclose($handle);
            $success = true;
            break;
        }
    }
} else {
    // Linux/Mac: try common editors
    $editors = [
        'code ' . $escapedPath,                    // VS Code
        'nano ' . $escapedPath,                    // nano
        'vi ' . $escapedPath,                      // vi
    ];

    // Check DISPLAY for GUI editors
    if (getenv('DISPLAY')) {
        array_unshift($editors, 'gedit ' . $escapedPath);
        array_unshift($editors, 'code ' . $escapedPath);
    }

    foreach ($editors as $cmd) {
        $handle = @popen($cmd . ' &', 'r');
        if ($handle !== false) {
            pclose($handle);
            $success = true;
            break;
        }
    }
}

if ($success) {
    echo json_encode(['success' => true, 'message' => 'Editor geopend']);
} else {
    echo json_encode(['success' => false, 'error' => 'Kon editor niet openen', 'path' => $path]);
}
