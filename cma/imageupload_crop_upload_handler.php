<?php
/**
 * Image Upload Handler for Crop Wizard
 *
 * Receives image uploads from lib-fileuploader in imageupload_crop.php
 * and stores them in the specified path.
 *
 * Query Parameters:
 *   path - Upload directory (relative to site root, e.g. "/uploads/images/")
 *
 * POST:
 *   qqfile - The uploaded file (lib-fileuploader convention)
 *
 * Returns JSON:
 *   { "success": true, "filename": "name.jpg" }
 *   { "success": false, "error": "Error message" }
 */

// This handler is POSTed to directly (lib-fileuploader / fetch) with no page
// context, and IIS auto_prepend of _bootstrap.php is not reliable for such
// direct uploads — so it must NOT depend on any App\Library class (that caused
// a "Class App\Library\Request not found" fatal on live). It reads only
// superglobals. Do NOT include bootstrap.inc either: its login-redirect logic
// breaks the JSON response.

header('Content-Type: application/json; charset=utf-8');

// Discard any output buffered by auto-prepend
if (ob_get_level()) {
    ob_end_clean();
}

// Get path parameter (superglobal — no platform autoloader dependency)
$path = isset($_GET['path']) ? (string) $_GET['path'] : '';

if ($path === '') {
    echo json_encode(['success' => false, 'error' => 'Geen upload pad opgegeven']);
    exit;
}

// Security: normalize and validate the path
$path = str_replace('\\', '/', $path);
$path = rtrim($path, '/') . '/';
$path = ltrim($path, '/');

// Prevent directory traversal
if (strpos($path, '..') !== false) {
    echo json_encode(['success' => false, 'error' => 'Ongeldig pad']);
    exit;
}

// Only allow uploads within known image directories
$allowedPrefixes = ['uploads/', 'images/'];
$isAllowed = false;
foreach ($allowedPrefixes as $prefix) {
    if (stripos($path, $prefix) !== false) {
        $isAllowed = true;
        break;
    }
}
if (!$isAllowed) {
    echo json_encode(['success' => false, 'error' => 'Upload pad niet toegestaan']);
    exit;
}

// Check for uploaded file
$fileKey = isset($_FILES['qqfile']) ? 'qqfile' : (isset($_FILES['file']) ? 'file' : null);

if ($fileKey === null) {
    echo json_encode(['success' => false, 'error' => 'Geen bestand ontvangen']);
    exit;
}

$file = $_FILES[$fileKey];

if ($file['error'] !== UPLOAD_ERR_OK) {
    $errors = [
        UPLOAD_ERR_INI_SIZE => 'Bestand te groot (server limiet)',
        UPLOAD_ERR_FORM_SIZE => 'Bestand te groot (formulier limiet)',
        UPLOAD_ERR_PARTIAL => 'Bestand niet volledig ge-upload',
        UPLOAD_ERR_NO_FILE => 'Geen bestand geselecteerd',
        UPLOAD_ERR_NO_TMP_DIR => 'Geen tijdelijke map beschikbaar',
        UPLOAD_ERR_CANT_WRITE => 'Kan niet naar schijf schrijven',
    ];
    echo json_encode(['success' => false, 'error' => $errors[$file['error']] ?? 'Upload fout']);
    exit;
}

// Validate file is an image by extension
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($ext, ['jpg', 'jpeg', 'png'])) {
    echo json_encode(['success' => false, 'error' => 'Alleen JPG en PNG bestanden zijn toegestaan']);
    exit;
}

// Sanitize filename
$originalName = $file['name'];
$filename = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $originalName);

if ($filename === '' || $filename === '.' || $filename === '..') {
    $filename = 'image_' . time() . '.jpg';
}

// Build full target path (relative to site root, one level up from cma/)
$siteRoot = dirname(__DIR__) . '/';
$fullDir = $siteRoot . $path;

// Create directory if it doesn't exist
if (!is_dir($fullDir)) {
    if (!@mkdir($fullDir, 0755, true)) {
        echo json_encode(['success' => false, 'error' => 'Kan upload map niet aanmaken']);
        exit;
    }
}

$targetPath = $fullDir . $filename;

// Move uploaded file
if (move_uploaded_file($file['tmp_name'], $targetPath)) {
    echo json_encode([
        'success' => true,
        'filename' => $filename,
        'path' => '/' . $path . $filename
    ]);
} else {
    echo json_encode(['success' => false, 'error' => 'Kan bestand niet opslaan']);
}
