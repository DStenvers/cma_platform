<?php
/**
 * Modern File Browser
 *
 * A clean, single-page file browser with:
 * - File/folder listing with navigation
 * - File upload with drag & drop
 * - Create directory
 * - Delete files/folders
 * - File details preview
 * - Overwrite confirmation
 *
 * Parameters:
 * - basepath: Base path for file operations (required)
 * - fieldname: Field name to update when file is selected
 * - image: If set, only show image files
 * - file: Pre-selected file
 */

use App\Library\Application;
use App\Library\File;
use App\Library\Image;
use App\Library\Request;
use App\Library\Response;
use App\Library\ResponsiveImage;
use App\Library\Server;
use App\Library\Str;
use Cma\SecurityHelper;

require_once __DIR__ . '/../bootstrap.inc';

// Set JSON mode early for AJAX actions to prevent debug output breaking JSON responses
if (Request::query('action', '') !== '' || Request::post('action', '') !== '') {
    \App\Library\Debug::setJsonMode(true);
}

// Check if user can see technical details
$showDetails = SecurityHelper::isAdmin() || SecurityHelper::isDeveloper();

Response::noCache();

$basePath = Request::query('basepath', '');
$fieldName = Request::query('fieldname', '');
$imageOnly = Request::query('image', '') !== '';
$currentFile = strtok(Request::query('file', ''), '?'); // Strip ?versie= cache buster if present
$currentPath = Request::query('path', '');

// Image constraint parameters (from old file-pages.php wizard)
$resizeType = Request::queryInt('resizetype');   // 0=none, 1=maximum, 2=fixed
$resizeWidth = Request::queryInt('resizewidth');
$resizeHeight = Request::queryInt('resizeheight');

// Layout mode - show alignment/border/margin options for images
$includeLayout = Request::query('layout', '1') !== '0';

// File filter pattern (e.g., "*.jpg", "*.pdf")
$fileSpec = Request::query('filespec', '*.*');

// If currentFile contains a path, extract the directory and filename
// e.g., "downloads/formulieren/subdir/test.pdf" with basePath "downloads/formulieren/"
// should result in currentPath = "subdir/" and currentFile = "test.pdf"
if ($currentFile !== '' && strpos($currentFile, '/') !== false) {
    // Check if file path starts with basePath
    if ($basePath !== '' && strpos($currentFile, $basePath) === 0) {
        // Remove basePath prefix
        $relativePath = substr($currentFile, strlen($basePath));
    } else {
        $relativePath = $currentFile;
    }

    // Extract directory and filename
    $lastSlash = strrpos($relativePath, '/');
    if ($lastSlash !== false) {
        $currentPath = substr($relativePath, 0, $lastSlash + 1);
        $currentFile = substr($relativePath, $lastSlash + 1);
    }
}

// Ensure basepath starts with / and ends with /
if ($basePath !== '' && substr($basePath, 0, 1) !== '/') {
    $basePath = '/' . $basePath;
}
if ($basePath !== '' && substr($basePath, -1) !== '/') {
    $basePath .= '/';
}

// Get the full filesystem path
$siteRoot = Server::mapPath('/');
$fullBasePath = $siteRoot . str_replace('/', DIRECTORY_SEPARATOR, $basePath);

// Security: verify basePath resolves within the site root
$resolvedBase = realpath($fullBasePath);
$resolvedRoot = realpath($siteRoot);
if ($resolvedBase !== false && $resolvedRoot !== false && strpos($resolvedBase, $resolvedRoot) !== 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Ongeldig basispad']);
    exit;
}

// Ensure the base directory exists (use URL path with File::createFolder)
$basePathError = '';
$basePathDetails = '';
if (!is_dir($fullBasePath)) {
    if (!File::createFolder(rtrim($basePath, '/'))) {
        $basePathError = 'De map "' . $basePath . '" bestaat niet en kon niet worden aangemaakt.';
        // Collect technical details for admin/developer
        if ($showDetails) {
            $parentDir = dirname($fullBasePath);
            $basePathDetails = "URL pad: " . $basePath . "\n";
            $basePathDetails .= "Fysiek pad: " . $fullBasePath . "\n";
            $basePathDetails .= "Ouder map: " . $parentDir . "\n";
            $basePathDetails .= "Ouder map bestaat: " . (is_dir($parentDir) ? 'Ja' : 'Nee') . "\n";
            if (is_dir($parentDir)) {
                $basePathDetails .= "Ouder map schrijfbaar: " . (is_writable($parentDir) ? 'Ja' : 'Nee') . "\n";
            }
            $basePathDetails .= "PHP user: " . (function_exists('posix_getpwuid') ? posix_getpwuid(posix_geteuid())['name'] : get_current_user()) . "\n";
        }
    }
}
// Recheck after creation attempt
if (!is_dir($fullBasePath) && $basePathError === '') {
    $basePathError = 'De map "' . $basePath . '" bestaat niet.';
    if ($showDetails) {
        $basePathDetails = "URL pad: " . $basePath . "\n";
        $basePathDetails .= "Fysiek pad: " . $fullBasePath . "\n";
    }
}

// Handle AJAX requests
$action = Request::query('action', '') ?: Request::post('action', '');
if ($action !== '') {
    header('Content-Type: application/json');
    $path = Request::query('path', '') ?: Request::post('path', '');

    // Sanitize path - prevent directory traversal
    $path = str_replace('..', '', $path);
    $path = preg_replace('#/+#', '/', $path);
    $fullPath = $fullBasePath . str_replace('/', DIRECTORY_SEPARATOR, $path);

    // Security: verify resolved path is within basePath (catches bypass of str_replace)
    $resolvedFull = realpath($fullPath);
    $resolvedBase = realpath($fullBasePath);
    if ($resolvedFull !== false && $resolvedBase !== false && strpos($resolvedFull, $resolvedBase) !== 0) {
        echo json_encode(['success' => false, 'error' => 'Ongeldig pad']);
        exit;
    }

    switch ($action) {
        case 'list':
            $fileSpecAjax = Request::query('filespec', '*.*');
            echo json_encode(listDirectory($fullPath, $basePath, $path, $imageOnly, $fileSpecAjax));
            break;

        case 'upload':
            $urlPath = rtrim($basePath . $path, '/');
            echo json_encode(handleUpload($fullPath, $urlPath, Request::post('overwrite', '0')));
            break;

        case 'delete':
            $file = Request::post('file', '');
            echo json_encode(deleteFile($fullPath, $file));
            break;

        case 'mkdir':
            $name = Request::post('name', '');
            $urlPath = rtrim($basePath . $path, '/');
            echo json_encode(createDirectory($fullPath, $urlPath, $name));
            break;

        case 'details':
            $file = Request::query('file', '');
            echo json_encode(getFileDetails($fullPath, $file, $basePath . $path));
            break;

        case 'rotate':
            $file = Request::post('file', '');
            $degrees = Request::postInt('degrees', 90);
            echo json_encode(rotateImage($fullPath, $file, $degrees));
            break;

        case 'resize':
            $file = Request::post('file', '');
            $maxWidth = Request::postInt('width', 0);
            $maxHeight = Request::postInt('height', 0);
            echo json_encode(resizeImage($fullPath, $file, $maxWidth, $maxHeight));
            break;

        case 'crop':
            $file = Request::post('file', '');
            $x = Request::postInt('x', 0);
            $y = Request::postInt('y', 0);
            $width = Request::postInt('width', 0);
            $height = Request::postInt('height', 0);
            $destWidth = Request::postInt('destWidth', 0);
            $destHeight = Request::postInt('destHeight', 0);
            echo json_encode(cropImage($fullPath, $file, $x, $y, $width, $height, $destWidth, $destHeight));
            break;

        case 'filter':
            $file = Request::post('file', '');
            $filter = Request::post('filter', '');
            $arg = Request::post('arg', '');
            echo json_encode(applyImageFilter($fullPath, $file, $filter, $arg));
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Onbekende actie']);
    }
    exit;
}

/**
 * List directory contents
 */
function listDirectory(string $fullPath, string $basePath, string $relativePath, bool $imageOnly, string $fileSpec = '*.*'): array {
    if (!is_dir($fullPath)) {
        // Try to create it using URL path (File::createFolder handles mapping)
        $urlPath = rtrim($basePath . $relativePath, '/');
        if (!File::createFolder($urlPath)) {
            return ['success' => false, 'error' => 'Map bestaat niet en kon niet worden aangemaakt'];
        }
    }

    $items = [];
    $files = @scandir($fullPath);

    if ($files === false) {
        return ['success' => false, 'error' => 'Kan map niet lezen'];
    }

    // Add parent directory link if not at root
    if ($relativePath !== '' && $relativePath !== '/') {
        $parentPath = dirname($relativePath);
        if ($parentPath === '.' || $parentPath === '\\') {
            $parentPath = '';
        }
        $items[] = [
            'name' => '..',
            'type' => 'parent',
            'path' => $parentPath
        ];
    }

    // First pass: directories
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        if (strpos($file, '.') === 0) continue; // Skip hidden files

        $filePath = $fullPath . DIRECTORY_SEPARATOR . $file;

        if (is_dir($filePath)) {
            // Skip system folders
            $lowerName = strtolower($file);
            if (in_array($lowerName, ['_vti_cnf', '_vti_pvt', '_private', '.git', 'node_modules'])) continue;

            $items[] = [
                'name' => $file,
                'type' => 'folder',
                'path' => ($relativePath ? $relativePath . '/' : '') . $file
            ];
        }
    }

    // Parse filespec into extensions list (e.g., "*.jpg;*.png" or "*.pdf")
    $allowedExtensions = [];
    if ($fileSpec !== '*.*' && $fileSpec !== '*') {
        $patterns = preg_split('/[;,\s]+/', $fileSpec);
        foreach ($patterns as $pattern) {
            $pattern = trim($pattern);
            if (preg_match('/^\*\.(\w+)$/', $pattern, $m)) {
                $allowedExtensions[] = strtolower($m[1]);
            }
        }
    }

    // Second pass: files
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        if (strpos($file, '.') === 0) continue;

        $filePath = $fullPath . DIRECTORY_SEPARATOR . $file;

        if (is_file($filePath)) {
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp', 'bmp']);

            // Skip non-images if image-only mode
            if ($imageOnly && !$isImage) continue;

            // Skip if doesn't match filespec
            if (!empty($allowedExtensions) && !in_array($ext, $allowedExtensions)) continue;

            $size = filesize($filePath);
            $modified = filemtime($filePath);

            $item = [
                'name' => $file,
                'type' => 'file',
                'ext' => $ext,
                'size' => $size,
                'sizeFormatted' => formatFileSize($size),
                'modified' => date('d-m-Y H:i', $modified),
                'modifiedTs' => $modified,
                'isImage' => $isImage,
                'path' => ($relativePath ? $relativePath . '/' : '') . $file
            ];

            // Add image dimensions if it's an image
            if ($isImage && $ext !== 'svg') {
                $dimensions = @getimagesize($filePath);
                if ($dimensions) {
                    $item['width'] = $dimensions[0];
                    $item['height'] = $dimensions[1];
                }
            }

            $items[] = $item;
        }
    }

    return [
        'success' => true,
        'path' => $relativePath,
        'basePath' => $basePath,
        'items' => $items
    ];
}

/**
 * Handle file upload
 */
function handleUpload(string $fullPath, string $urlPath, string $overwrite): array {
    if (!isset($_FILES['file'])) {
        return ['success' => false, 'error' => 'Geen bestand ontvangen'];
    }

    $file = $_FILES['file'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors = [
            UPLOAD_ERR_INI_SIZE => 'Bestand te groot (server limiet)',
            UPLOAD_ERR_FORM_SIZE => 'Bestand te groot (formulier limiet)',
            UPLOAD_ERR_PARTIAL => 'Bestand niet volledig geüpload',
            UPLOAD_ERR_NO_FILE => 'Geen bestand geselecteerd',
            UPLOAD_ERR_NO_TMP_DIR => 'Geen tijdelijke map beschikbaar',
            UPLOAD_ERR_CANT_WRITE => 'Kan niet naar schijf schrijven',
        ];
        return ['success' => false, 'error' => $errors[$file['error']] ?? 'Upload fout'];
    }

    // Sanitize filename
    $filename = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $file['name']);

    // Block dangerous executable extensions
    $blockedExtensions = ['php', 'phtml', 'phar', 'php3', 'php4', 'php5', 'php7', 'asp', 'aspx', 'jsp', 'sh', 'cgi', 'pl', 'exe', 'bat', 'cmd', 'com', 'htaccess', 'htpasswd'];
    $uploadExt = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    if (in_array($uploadExt, $blockedExtensions)) {
        return ['success' => false, 'error' => 'Bestandstype niet toegestaan'];
    }

    $targetPath = $fullPath . DIRECTORY_SEPARATOR . $filename;

    // Check if file exists
    if (file_exists($targetPath) && $overwrite !== '1') {
        return ['success' => false, 'error' => 'Bestand bestaat al', 'exists' => true, 'filename' => $filename];
    }

    // Ensure directory exists (use URL path with File::createFolder)
    if (!is_dir($fullPath)) {
        File::createFolder($urlPath);
    }

    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        return ['success' => true, 'filename' => $filename, 'message' => 'Bestand geüpload', 'modifiedTs' => filemtime($targetPath)];
    } else {
        return ['success' => false, 'error' => 'Kan bestand niet opslaan'];
    }
}

/**
 * Delete a file or folder
 */
function deleteFile(string $fullPath, string $file): array {
    if ($file === '' || $file === '.' || $file === '..') {
        return ['success' => false, 'error' => 'Ongeldige bestandsnaam'];
    }

    // Sanitize — strip ?versie= cache buster before filesystem operations
    $file = stripVersionQuery(basename($file));
    $targetPath = $fullPath . DIRECTORY_SEPARATOR . $file;

    if (!file_exists($targetPath)) {
        return ['success' => false, 'error' => 'Bestand niet gevonden'];
    }

    if (is_dir($targetPath)) {
        // Check if directory is empty
        $contents = @scandir($targetPath);
        if ($contents && count($contents) > 2) {
            return ['success' => false, 'error' => 'Map is niet leeg'];
        }
        if (@rmdir($targetPath)) {
            return ['success' => true, 'message' => 'Map verwijderd'];
        }
    } else {
        if (@unlink($targetPath)) {
            // Clean up responsive variants if this was an image
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'])) {
                ResponsiveImage::deleteVariants($targetPath);
            }
            return ['success' => true, 'message' => 'Bestand verwijderd'];
        }
    }

    return ['success' => false, 'error' => 'Kan niet verwijderen'];
}

/**
 * Create a new directory
 */
function createDirectory(string $fullPath, string $urlPath, string $name): array {
    if ($name === '') {
        return ['success' => false, 'error' => 'Naam is verplicht'];
    }

    // Sanitize directory name
    $name = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $name);
    $targetPath = $fullPath . DIRECTORY_SEPARATOR . $name;

    if (file_exists($targetPath)) {
        return ['success' => false, 'error' => 'Map bestaat al'];
    }

    // Use URL path with File::createFolder for proper handling
    $newDirUrlPath = $urlPath . '/' . $name;
    if (File::createFolder($newDirUrlPath)) {
        return ['success' => true, 'message' => 'Map aangemaakt', 'name' => $name];
    }

    return ['success' => false, 'error' => 'Kan map niet aanmaken'];
}

/**
 * Get file details
 */
function getFileDetails(string $fullPath, string $file, string $webPath): array {
    $file = stripVersionQuery(basename($file));

    // Ensure webPath ends with / so URL is correctly formed (e.g. /templates/subfolder/ + file.jpg)
    if ($webPath !== '' && substr($webPath, -1) !== '/') {
        $webPath .= '/';
    }

    $targetPath = $fullPath . DIRECTORY_SEPARATOR . $file;

    if (!file_exists($targetPath) || !is_file($targetPath)) {
        return ['success' => false, 'error' => 'Bestand niet gevonden'];
    }

    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp', 'bmp']);
    $size = filesize($targetPath);
    $modified = filemtime($targetPath);

    $details = [
        'success' => true,
        'name' => $file,
        'ext' => $ext,
        'size' => $size,
        'sizeFormatted' => formatFileSize($size),
        'modified' => date('d-m-Y H:i:s', $modified),
        'modifiedTs' => $modified,
        'isImage' => $isImage,
        'url' => $webPath . $file . '?versie=' . $modified
    ];

    if ($isImage && $ext !== 'svg') {
        $dimensions = @getimagesize($targetPath);
        if ($dimensions) {
            $details['width'] = $dimensions[0];
            $details['height'] = $dimensions[1];
        }
    }

    return $details;
}

/**
 * Format file size
 */
function formatFileSize(int $bytes): string {
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 1) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 1) . ' KB';
    }
    return $bytes . ' B';
}

/**
 * Strip ?versie= (or any query string) from a filename.
 * Cache-busting parameters must not reach filesystem operations.
 */
function stripVersionQuery(string $filename): string {
    $pos = strpos($filename, '?');
    return $pos !== false ? substr($filename, 0, $pos) : $filename;
}

/**
 * Rotate an image
 */
function rotateImage(string $fullPath, string $file, int $degrees): array {
    $file = stripVersionQuery(basename($file));
    $targetPath = $fullPath . DIRECTORY_SEPARATOR . $file;

    if (!file_exists($targetPath)) {
        return ['success' => false, 'error' => 'Bestand niet gevonden'];
    }

    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'])) {
        return ['success' => false, 'error' => 'Bestandstype ondersteunt geen rotatie'];
    }

    if (Image::rotate($targetPath, $targetPath, $degrees)) {
        // Regenerate responsive variants
        ResponsiveImage::deleteVariants($targetPath);
        ResponsiveImage::generate($targetPath);

        // Get new dimensions
        $dimensions = @getimagesize($targetPath);
        return [
            'success' => true,
            'message' => 'Afbeelding geroteerd',
            'width' => $dimensions ? $dimensions[0] : null,
            'height' => $dimensions ? $dimensions[1] : null
        ];
    }

    return ['success' => false, 'error' => 'Kan afbeelding niet roteren'];
}

/**
 * Resize an image
 */
function resizeImage(string $fullPath, string $file, int $maxWidth, int $maxHeight): array {
    $file = stripVersionQuery(basename($file));
    $targetPath = $fullPath . DIRECTORY_SEPARATOR . $file;

    if (!file_exists($targetPath)) {
        return ['success' => false, 'error' => 'Bestand niet gevonden'];
    }

    if ($maxWidth <= 0 && $maxHeight <= 0) {
        return ['success' => false, 'error' => 'Breedte of hoogte is verplicht'];
    }

    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'])) {
        return ['success' => false, 'error' => 'Bestandstype ondersteunt geen formaat wijzigen'];
    }

    if (Image::resize($targetPath, $targetPath, $maxWidth, $maxHeight)) {
        // Regenerate responsive variants
        ResponsiveImage::deleteVariants($targetPath);
        ResponsiveImage::generate($targetPath);

        // Get new dimensions
        $dimensions = @getimagesize($targetPath);
        return [
            'success' => true,
            'message' => 'Afbeelding verkleind',
            'width' => $dimensions ? $dimensions[0] : null,
            'height' => $dimensions ? $dimensions[1] : null
        ];
    }

    return ['success' => false, 'error' => 'Kan afbeelding niet verkleinen'];
}

/**
 * Crop an image
 */
function cropImage(string $fullPath, string $file, int $x, int $y, int $width, int $height, int $destWidth, int $destHeight): array {
    $file = stripVersionQuery(basename($file));
    $targetPath = $fullPath . DIRECTORY_SEPARATOR . $file;

    if (!file_exists($targetPath)) {
        return ['success' => false, 'error' => 'Bestand niet gevonden'];
    }

    if ($width <= 0 || $height <= 0) {
        return ['success' => false, 'error' => 'Ongeldige bijsnijdafmetingen'];
    }

    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'])) {
        return ['success' => false, 'error' => 'Bestandstype ondersteunt geen bijsnijden'];
    }

    // If destination dimensions are provided, crop and resize
    if ($destWidth > 0 && $destHeight > 0) {
        $success = Image::cropAndResize($targetPath, $targetPath, $x, $y, $width, $height, $destWidth, $destHeight);
    } else {
        $success = Image::crop($targetPath, $targetPath, $x, $y, $width, $height);
    }

    if ($success) {
        // Regenerate responsive variants
        ResponsiveImage::deleteVariants($targetPath);
        ResponsiveImage::generate($targetPath);

        // Get new dimensions
        $dimensions = @getimagesize($targetPath);
        return [
            'success' => true,
            'message' => 'Afbeelding bijgesneden',
            'width' => $dimensions ? $dimensions[0] : null,
            'height' => $dimensions ? $dimensions[1] : null
        ];
    }

    return ['success' => false, 'error' => 'Kan afbeelding niet bijsnijden'];
}

/**
 * Apply image filter (brightness, sharpen)
 */
function applyImageFilter(string $fullPath, string $file, string $filter, string $arg): array {
    $file = stripVersionQuery(basename($file));
    $targetPath = $fullPath . DIRECTORY_SEPARATOR . $file;

    if (!file_exists($targetPath)) {
        return ['success' => false, 'error' => 'Bestand niet gevonden'];
    }

    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'])) {
        return ['success' => false, 'error' => 'Bestandstype ondersteunt geen filters'];
    }

    // Load image
    $imageInfo = @getimagesize($targetPath);
    if ($imageInfo === false) {
        return ['success' => false, 'error' => 'Kan afbeelding niet lezen'];
    }
    $source = null;
    switch ($ext) {
        case 'png':
            $source = @imagecreatefrompng($targetPath);
            break;
        case 'gif':
            $source = @imagecreatefromgif($targetPath);
            break;
        case 'webp':
            $source = @imagecreatefromwebp($targetPath);
            break;
        default:
            $source = @imagecreatefromjpeg($targetPath);
    }

    if (!$source) {
        return ['success' => false, 'error' => 'Kan afbeelding niet laden'];
    }

    // Apply filter
    $success = false;
    switch ($filter) {
        case 'brightness':
            // +/- brightness adjustment
            $value = ($arg === '-') ? -15 : 15;
            $success = imagefilter($source, IMG_FILTER_BRIGHTNESS, $value);
            break;

        case 'sharpen':
            // Apply sharpening convolution matrix
            $sharpenMatrix = [
                [-1, -1, -1],
                [-1, 16, -1],
                [-1, -1, -1]
            ];
            $divisor = array_sum(array_map('array_sum', $sharpenMatrix));
            $success = imageconvolution($source, $sharpenMatrix, $divisor, 0);
            break;

        default:
            imagedestroy($source);
            return ['success' => false, 'error' => 'Onbekend filter'];
    }

    if (!$success) {
        imagedestroy($source);
        return ['success' => false, 'error' => 'Filter kon niet worden toegepast'];
    }

    // Save image — preserve alpha for transparency-capable formats
    $saved = false;
    if (in_array($ext, ['png', 'gif', 'webp'])) {
        imagealphablending($source, false);
        imagesavealpha($source, true);
    }
    switch ($ext) {
        case 'png':
            $saved = imagepng($source, $targetPath, 9);
            break;
        case 'gif':
            $saved = imagegif($source, $targetPath);
            break;
        case 'webp':
            $saved = imagewebp($source, $targetPath, 80);
            break;
        default:
            $saved = imagejpeg($source, $targetPath, 92);
    }

    imagedestroy($source);

    if ($saved) {
        // Regenerate responsive variants
        ResponsiveImage::deleteVariants($targetPath);
        ResponsiveImage::generate($targetPath);

        return ['success' => true, 'message' => 'Filter toegepast'];
    }

    return ['success' => false, 'error' => 'Kan afbeelding niet opslaan'];
}

// Get application base path for URLs
$appBasePath = Application::get('base_path', '/');
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <script>
    // Inherit dark mode from parent window
    try {
        if (window.parent && window.parent !== window && window.parent.document.documentElement.classList.contains('dark-mode')) {
            document.documentElement.classList.add('dark-mode');
        }
    } catch(e) {}
    </script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>File Browser</title>
    <?php
    // Combined CSS via minify.php
    $fbCss = minify_asset('../library/css/lib-variables.css,assets/css/colors.css,../library/library.css,assets/css/style.css,assets/css/form.css');
    echo '<link rel="stylesheet" href="' . $fbCss . '">' . PHP_EOL;
    ?>
    <!-- Cropper.js for image editing -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.js"></script>
    <?php
    // Combined JS via minify.php
    $fbJs = minify_asset('../library/error-handler.js,../library/webcomponents/lib-dialog.js,webcomponents/cma-toolbar.js,webcomponents/cma-fold.js');
    echo '<script src="' . $fbJs . '"></script>' . PHP_EOL;
    ?>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: var(--font-family);
            font-size: var(--font-size);
            margin: 0;
            padding: 0;
            background: var(--bg-body);
            height: 100vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .browser-container {
            display: flex;
            flex: 1;
            min-height: 0;
        }

        /* File list panel */
        .file-list-panel {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: var(--bg-surface);
            border-right: 1px solid var(--border-color);
            min-width: 0;
        }

        /* Toolbar separator */
        .tb-sep {
            width: 1px;
            height: 20px;
            background: var(--border-color);
            margin: 0 6px;
            display: inline-block;
        }

        .path-bar {
            padding: 4px 8px;
            background: linear-gradient(to bottom, var(--bg-surface, #f5f5f5) 0%, var(--bg-surface-alt, #eaeaea) 100%);
            font-size: var(--font-size);
            border-bottom: none;
            display: flex;
            align-items: center;
            gap: 5px;
            min-height: 35px;
            box-sizing: border-box;
        }

        .path-bar .path {
            flex: 1;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .file-list {
            flex: 1;
            overflow-y: auto;
            padding: 5px;
        }

        .file-item {
            display: flex;
            align-items: center;
            padding: 8px 10px;
            cursor: pointer;
            border-radius: 4px;
            gap: 10px;
        }

        .file-item:hover {
            background: var(--bg-hover);
        }

        .file-item.selected {
            background: var(--bg-hover);
        }

        .file-item .icon {
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: var(--font-size-xl);
        }

        /* Thumbnail images in list mode - same size as icons */
        .file-item .thumb-img {
            width: 24px;
            height: 24px;
            object-fit: cover;
            border-radius: 2px;
            flex-shrink: 0;
        }

        /* Folder icons always yellow */
        .lnr-folder, .lnr-folder-plus { color: #f9a825; }

        .file-item .icon.image { color: #43a047; }
        .file-item .icon.file { color: #757575; }
        .file-item .icon.pdf { color: #e53935; }
        .file-item .icon.doc { color: #1565c0; }

        .file-item .name {
            flex: 1;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .file-item .meta {
            font-size: var(--font-size-xs);
            color: var(--text-muted);
        }

        /* Details panel */
        .details-panel {
            width: 300px;
            min-width: 200px;
            max-width: 500px;
            background: var(--bg-surface);
            display: flex;
            flex-direction: column;
        }

        .toolbar-title {
            font-weight: 600;
            font-size: var(--font-size);
        }

        .details-content {
            flex: 1;
            overflow-y: auto;
            padding: 15px;
        }

        .preview-image {
            max-width: 100%;
            max-height: 200px;
            object-fit: contain;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            margin-bottom: 15px;
        }

        .details-table {
            width: 100%;
            font-size: var(--font-size);
        }

        .details-table td {
            padding: 5px 0;
        }

        .details-table td:first-child {
            color: var(--text-muted);
            width: 80px;
        }

        .no-selection {
            color: var(--text-muted);
            text-align: center;
            padding: 30px;
        }

        /* Footer with action buttons - uses standard .btn from colors.css */
        .footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            padding: 12px;
            background: var(--bg-header);
            border-top: 1px solid var(--border-color);
            flex-shrink: 0;
        }

        .btn-danger {
            color: var(--color-danger);
            border-color: var(--color-danger);
        }

        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* Upload dropzone */
        .dropzone {
            border: 2px dashed var(--border-color);
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            margin: 10px;
            transition: all 0.2s;
        }

        .dropzone.dragover {
            border-color: var(--color-primary, #1976d2);
            background: var(--color-primary-light, #e3f2fd);
        }

        .dropzone input[type="file"] {
            display: none;
        }

        .dropzone button.btn-primary {
            float: inherit;
        }

        /* Toast messages */
        .toast {
            position: fixed;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            padding: 12px 24px;
            background: #323232;
            color: #fff;
            border-radius: 4px;
            font-size: var(--font-size-md);
            z-index: 2000;
            opacity: 0;
            transition: opacity 0.3s;
        }

        .toast.show {
            opacity: 1;
        }

        .toast.error {
            background: #c62828;
        }

        .toast.success {
            background: #2e7d32;
        }

        /* Loading state */
        .loading {
            text-align: center;
            padding: 40px;
            color: var(--text-muted, #999);
        }

        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 40px;
            color: var(--text-muted, #999);
        }

        /* Thumbnail view */
        .file-list.view-thumb {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            padding: 10px;
        }

        .file-list.view-thumb .file-item {
            flex-direction: column;
            width: 100px;
            height: 110px;
            padding: 10px 5px;
            text-align: center;
            border: 1px solid var(--border-color, #ddd);
            border-radius: 4px;
        }

        .file-list.view-thumb .file-item .icon {
            font-size: 32px;
            height: 50px;
            width: 50px;
            margin-bottom: 5px;
        }

        .file-list.view-thumb .file-item .thumb-img {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 4px;
            margin-bottom: 5px;
        }

        .file-list.view-thumb .file-item .name {
            font-size: var(--font-size-xs);
            line-height: 1.2;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .file-list.view-thumb .file-item .meta {
            display: none;
        }

        /* Active view button */
        .tb-btn.active {
            background: var(--color-primary-light, #e3f2fd);
        }

        /* Layout options */
        .layout-options {
            border-top: 1px solid var(--border-color, #ddd);
            padding: 10px 15px;
            background: transparent;
        }

        .layout-header {
            font-weight: 600;
            font-size: var(--font-size-sm);
            margin-bottom: 10px;
            color: var(--text-muted, #666);
        }

        .layout-form {
            display: grid;
            grid-template-columns: auto 1fr;
            gap: 8px;
            align-items: center;
            font-size: var(--font-size-sm);
        }

        .layout-form label {
            color: var(--text-muted, #666);
        }

        .layout-form input,
        .layout-form select {
            padding: 4px 8px;
            border: 1px solid var(--border-color, #ccc);
            border-radius: 3px;
            font-size: var(--font-size-sm);
        }

        .align-buttons {
            display: flex;
        }

        .align-btn {
            width: 30px;
            height: 28px;
            border: 1px solid var(--border-color, #ccc);
            border-right: none;
            background: var(--bg-surface, #fff);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0;
            border-radius: 0;
        }

        .align-btn:first-child { border-radius: 3px 0 0 3px; }
        .align-btn:last-child { border-radius: 0 3px 3px 0; border-right: 1px solid var(--border-color, #ccc); }

        .align-btn.active {
            background: var(--color-primary, #1976d2);
            border-color: var(--color-primary, #1976d2);
        }

        .align-btn.active svg line { stroke: #fff; }

        .align-btn svg {
            width: 16px;
            height: 12px;
        }

        .align-btn svg line {
            stroke: var(--text-muted, #666);
            stroke-width: 0.8;
            stroke-linecap: round;
        }

        .border-row {
            display: flex;
            gap: 6px;
            align-items: center;
        }

        .border-row input[type="number"] {
            width: 50px;
        }

        .border-row select {
            flex: 1;
            min-width: 0;
        }

        .border-row input[type="color"] {
            width: 28px;
            height: 28px;
            padding: 2px;
            border: 1px solid var(--border-color, #ccc);
            border-radius: 3px;
            cursor: pointer;
        }

        .margin-box {
            display: grid;
            grid-template-columns: 1fr auto 1fr;
            grid-template-rows: auto auto auto;
            align-items: center;
            justify-items: center;
            gap: 2px;
            width: 170px;
        }

        .margin-box input {
            width: 52px;
            padding: 3px 4px;
            text-align: center;
            border: 1px solid var(--border-color, #ccc);
            border-radius: 3px;
            font-size: var(--font-size-xs);
        }

        .margin-box .margin-top    { grid-column: 2; grid-row: 1; }
        .margin-box .margin-left   { grid-column: 1; grid-row: 2; }
        .margin-box .margin-center { grid-column: 2; grid-row: 2; width: 36px; height: 24px; border: 1px dashed var(--border-color, #ccc); border-radius: 2px; background: var(--bg-surface-alt, #f5f5f5); }
        .margin-box .margin-right  { grid-column: 3; grid-row: 2; }
        .margin-box .margin-bottom { grid-column: 2; grid-row: 3; }

        /* Dimension constraint warning */
        .dimension-warning {
            background: var(--color-warning-bg, #fff3cd);
            color: var(--color-warning-text, #856404);
            padding: 8px 10px;
            font-size: var(--font-size-xs);
            border-radius: 4px;
            margin-top: 10px;
        }

        .dimension-error {
            background: var(--color-error-bg, #f8d7da);
            color: var(--color-error-text, #721c24);
        }

        /* Image editor */
        .image-editor-container {
            display: flex;
            flex-direction: column;
            height: 100%;
        }

        #imageEditorDialog cma-toolbar .btn {
            padding: 6px 10px;
            font-size: var(--font-size-sm);
        }

        #imageEditorDialog .zoom-control {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        #imageEditorDialog .zoom-control label {
            font-size: var(--font-size-sm);
            color: var(--text-muted, #666);
        }

        #imageEditorDialog .zoom-control input[type="range"] {
            width: 80px;
            height: 4px;
            cursor: pointer;
        }

        #imageEditorDialog .zoom-control #zoomValue {
            font-size: var(--font-size-sm);
            min-width: 40px;
            color: var(--text-muted, #666);
        }

        #imageEditorDialog .aspect-ratio-select {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: var(--font-size-sm);
        }

        .image-editor-canvas {
            flex: 1;
            overflow: hidden;
            background: #1a1a1a;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 300px;
        }

        .image-editor-canvas img {
            max-width: 100%;
            max-height: 100%;
        }

        .image-editor-info {
            padding: 10px;
            background: var(--bg-surface-alt, #f5f5f5);
            border-top: 1px solid var(--border-color, #ddd);
            font-size: var(--font-size-sm);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .image-editor-info .dimensions {
            color: var(--text-muted, #666);
        }

        .image-editor-info .crop-info {
            color: var(--color-primary, #1976d2);
            font-weight: 500;
        }

        /* Aspect ratio buttons */
        .aspect-ratio-select {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .aspect-ratio-select label {
            font-size: var(--font-size-sm);
            color: var(--text-muted, #666);
        }

        .aspect-ratio-select select {
            padding: 4px 8px;
            border: 1px solid var(--border-color, #ccc);
            border-radius: 3px;
            font-size: var(--font-size-sm);
            width: auto;
        }

        /* Edit button in details panel */
        .edit-actions {
            display: flex;
            gap: 8px;
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid var(--border-color, #ddd);
        }

        .edit-actions .btn {
            flex: 1;
            font-size: var(--font-size-sm);
            padding: 6px 10px;
        }

        /* View file link */
        .view-file-link {
            color: var(--color-primary, #1976d2);
            margin-left: 6px;
            opacity: 0.7;
            transition: opacity 0.15s;
        }

        .view-file-link:hover {
            opacity: 1;
        }
    </style>
</head>
<body>
    <?php if ($basePathError !== ''): ?>
    <lib-dialog id="errorDialog" heading="Fout" type="danger" size="<?= $basePathDetails ? 'medium' : 'small' ?>" modal no-close-on-escape>
        <p><?= htmlspecialchars($basePathError) ?></p>
        <?php if ($basePathDetails): ?>
        <details class="lib-dialog-details">
            <summary>Details</summary>
            <pre class="lib-dialog-details-content"><?= htmlspecialchars($basePathDetails) ?></pre>
        </details>
        <style>
            .lib-dialog-details { margin-top: 12px; font-size: var(--font-size-sm); }
            .lib-dialog-details summary { cursor: pointer; color: var(--text-secondary, #666); padding: 4px 0; }
            .lib-dialog-details summary:hover { color: var(--text-primary, #333); }
            .lib-dialog-details-content {
                margin-top: 8px;
                padding: 10px;
                background: var(--bg-code, #f5f5f5);
                border: 1px solid var(--border-color, #ddd);
                border-radius: 4px;
                overflow-x: auto;
                font-family: monospace;
                font-size: var(--font-size-xs);
                line-height: 1.4;
                max-height: 200px;
                overflow-y: auto;
                white-space: pre-wrap;
                word-break: break-word;
            }
        </style>
        <?php endif; ?>
        <div slot="footer">
            <button class="btn btn-primary" onclick="window.close(); return false;">Sluiten</button>
        </div>
    </lib-dialog>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('errorDialog').open();
        });
    </script>
    <?php endif; ?>

    <cma-toolbar>
        <left>
            <span class="tb-btn">
                <a href="javascript:showUploadDialog()" title="Bestand uploaden">
                    <span class="lnr lnr-upload"></span>
                    <span class="tb-btn-text">Uploaden</span>
                </a>
            </span>
            <span class="tb-btn">
                <a href="javascript:showMkdirDialog()" title="Nieuwe map aanmaken">
                    <span class="lnr lnr-folder-plus"></span>
                    <span class="tb-btn-text">Nieuwe map</span>
                </a>
            </span>
            <span class="tb-sep"></span>
            <span class="tb-btn disabled" id="btnDelete">
                <a href="javascript:deleteSelected()" title="Geselecteerd bestand verwijderen">
                    <span class="lnr lnr-trash"></span>
                    <span class="tb-btn-text">Verwijderen</span>
                </a>
            </span>
            <span class="tb-sep"></span>
            <span class="tb-btn">
                <a href="javascript:refreshList()" title="Lijst vernieuwen">
                    <span class="lnr lnr-sync"></span>
                </a>
            </span>
        </left>
        <right>
            <span class="tb-btn" id="btnViewList">
                <a href="javascript:setViewMode('list')" title="Lijstweergave">
                    <span class="lnr lnr-list"></span>
                </a>
            </span>
            <span class="tb-btn" id="btnViewThumb">
                <a href="javascript:setViewMode('thumb')" title="Miniaturen">
                    <span class="lnr lnr-layers"></span>
                </a>
            </span>
        </right>
    </cma-toolbar>

    <div class="browser-container">
        <div class="file-list-panel">
            <div class="path-bar">
                <span class="lnr lnr-folder"></span>
                <span class="path" id="currentPath"><?= htmlspecialchars($basePath) ?></span>
            </div>

            <div class="dropzone" id="dropzone">
                <input type="file" id="fileInput" multiple>
                <p>Sleep bestanden hierheen of klik op de knop</p>
                <button type="button" class="btn btn-primary" onclick="document.getElementById('fileInput').click()">
                    <span class="lnr lnr-upload"></span> Bestand uploaden
                </button>
            </div>

            <div class="file-list" id="fileList">
                <div class="loading">Laden...</div>
            </div>
        </div>

        <cma-fold orientation="vertical" target="#detailsPanel" min-size="200" max-size="500" storage-key="file-browser-details" reverse></cma-fold>

        <div class="details-panel" id="detailsPanel">
            <cma-toolbar>
                <left><span class="toolbar-title">Details</span></left>
            </cma-toolbar>
            <div class="details-content" id="detailsContent">
                <div class="no-selection">Selecteer een bestand om details te bekijken</div>
            </div>
            <?php if ($includeLayout && $imageOnly): ?>
            <div class="layout-options" id="layoutOptions" style="display: none;">
                <div class="layout-header">Weergave</div>
                <input type="hidden" id="imgAlignment" value="">
                <div class="layout-form">
                    <label>Uitlijning:</label>
                    <div class="align-buttons">
                        <button type="button" class="align-btn" data-align="left" title="Links uitlijnen">
                            <svg viewBox="0 0 16 14"><line x1="1" y1="2" x2="15" y2="2"/><line x1="1" y1="5" x2="11" y2="5"/><line x1="1" y1="8" x2="13" y2="8"/><line x1="1" y1="11" x2="9" y2="11"/></svg>
                        </button>
                        <button type="button" class="align-btn" data-align="center" title="Centreren">
                            <svg viewBox="0 0 16 14"><line x1="1" y1="2" x2="15" y2="2"/><line x1="3" y1="5" x2="13" y2="5"/><line x1="2" y1="8" x2="14" y2="8"/><line x1="4" y1="11" x2="12" y2="11"/></svg>
                        </button>
                        <button type="button" class="align-btn" data-align="right" title="Rechts uitlijnen">
                            <svg viewBox="0 0 16 14"><line x1="1" y1="2" x2="15" y2="2"/><line x1="5" y1="5" x2="15" y2="5"/><line x1="3" y1="8" x2="15" y2="8"/><line x1="7" y1="11" x2="15" y2="11"/></svg>
                        </button>
                    </div>

                    <label>Rand:</label>
                    <div class="border-row">
                        <input type="color" id="imgBorderColor" value="#000000">
                        <input type="number" id="imgBorder" value="0" min="0" max="20">
                        <select id="imgBorderStyle">
                            <option value="solid">Doorlopend</option>
                            <option value="dashed">Streepjes</option>
                            <option value="dotted">Puntjes</option>
                            <option value="double">Dubbel</option>
                        </select>
                    </div>

                    <label>Marge (px):</label>
                    <div class="margin-box">
                        <input type="number" class="margin-top" id="imgMarginTop" value="10" min="0" max="50" title="Boven">
                        <input type="number" class="margin-left" id="imgMarginLeft" value="10" min="0" max="50" title="Links">
                        <div class="margin-center"></div>
                        <input type="number" class="margin-right" id="imgMarginRight" value="10" min="0" max="50" title="Rechts">
                        <input type="number" class="margin-bottom" id="imgMarginBottom" value="10" min="0" max="50" title="Onder">
                    </div>

                    <label>Afronding (px):</label>
                    <input type="number" id="imgBorderRadius" value="0" min="0" max="50" style="width: 60px;">

                    <label>CSS class:</label>
                    <input type="text" id="imgCssClass" placeholder="bijv. shadow rounded" style="width: 100%;">

                    <label>Alt tekst:</label>
                    <input type="text" id="imgAlt" placeholder="Beschrijving" style="width: 100%;">
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="footer">
        <button class="btn btn-cancel" onclick="cancelSelection()">Annuleren</button>
        <button class="btn btn-primary" id="btnSelect" onclick="confirmSelection()" disabled>Selecteren</button>
    </div>

    <!-- Image Editor Dialog -->
    <lib-dialog id="imageEditorDialog" heading="Afbeelding bewerken" size="fullscreen" modal>
        <div class="image-editor-container">
            <cma-toolbar>
                <left>
                    <span class="tb-btn" title="Linksom draaien"><a href="javascript:imageEditor.rotate(-90)"><span class="lnr lnr-undo"></span><span class="tb-btn-text">90°</span></a></span>
                    <span class="tb-btn" title="Rechtsom draaien"><a href="javascript:imageEditor.rotate(90)"><span class="lnr lnr-redo"></span><span class="tb-btn-text">90°</span></a></span>
                    <span class="tb-btn" title="180° draaien"><a href="javascript:imageEditor.rotate(180)"><span class="tb-btn-text">180°</span></a></span>
                    <span class="tb-btn" title="Selectie resetten"><a href="javascript:imageEditor.reset()"><span class="lnr lnr-sync"></span><span class="tb-btn-text">Herstel</span></a></span>
                    <span class="tb-btn" title="Donkerder maken"><a href="javascript:imageEditor.brightness('-')"><span class="lnr lnr-circle-minus"></span></a></span>
                    <span class="tb-btn" title="Lichter maken"><a href="javascript:imageEditor.brightness('+')"><span class="lnr lnr-sun"></span></a></span>
                    <span class="tb-btn" title="Verscherpen"><a href="javascript:imageEditor.sharpen()"><span class="lnr lnr-magic-wand"></span><span class="tb-btn-text">Scherp</span></a></span>
                </left>
                <right>
                    <div class="zoom-control">
                        <label>Uitvoer:</label>
                        <input type="range" id="outputZoom" min="10" max="100" value="100"
                               oninput="imageEditor.setOutputZoom(this.value)" title="Uitvoergrootte">
                        <span id="zoomValue">100%</span>
                    </div>
                    <div class="aspect-ratio-select">
                        <label>Verhouding:</label>
                        <select id="aspectRatioSelect" onchange="imageEditor.setAspectRatio(this.value)">
                            <option value="free">Vrij</option>
                            <option value="1">1:1 (Vierkant)</option>
                            <option value="1.333">4:3</option>
                            <option value="1.778">16:9</option>
                            <option value="0.75">3:4 (Portret)</option>
                            <option value="custom">Aangepast...</option>
                        </select>
                    </div>
                    <div id="customAspectInputs" style="display: none;">
                        <input type="number" id="customAspectW" placeholder="B" style="width: 50px;" min="1" value="<?= $resizeWidth ?: 800 ?>">
                        <span style="color: var(--text-muted);">×</span>
                        <input type="number" id="customAspectH" placeholder="H" style="width: 50px;" min="1" value="<?= $resizeHeight ?: 600 ?>">
                        <span class="tb-btn" style="margin-left: 4px;"><a href="javascript:imageEditor.applyCustomAspect()"><span class="tb-btn-text">OK</span></a></span>
                    </div>
                </right>
            </cma-toolbar>
            <div class="image-editor-canvas">
                <img id="editorImage" alt="Bewerken">
            </div>
            <div class="image-editor-info">
                <span class="dimensions">Origineel: <span id="editorOrigSize">-</span></span>
                <span class="crop-info">Selectie: <span id="editorCropSize">-</span></span>
            </div>
        </div>
        <div slot="footer">
            <button class="btn btn-cancel" onclick="imageEditor.cancel()">Annuleren</button>
            <button class="btn btn-primary" onclick="imageEditor.save()">Opslaan</button>
        </div>
    </lib-dialog>

    <!-- Resize Confirm Dialog (for upload) -->
    <lib-dialog id="resizeConfirmDialog" heading="Afbeelding te groot" size="small" modal>
        <p>De afbeelding is groter dan de toegestane afmetingen (<span id="resizeMaxDims"></span>).</p>
        <p>Huidige afmetingen: <strong id="resizeCurrentDims"></strong></p>
        <p>Wat wil je doen?</p>
        <div slot="footer">
            <button class="btn btn-cancel" onclick="resizeConfirm.cancel()">Annuleren</button>
            <button class="btn" onclick="resizeConfirm.uploadAsIs()">Uploaden zoals het is</button>
            <button class="btn btn-primary" onclick="resizeConfirm.resizeAndUpload()">Verkleinen en uploaden</button>
        </div>
    </lib-dialog>

    <!-- Toast -->
    <div class="toast" id="toast"></div>

    <script>
    (function() {
        'use strict';

        // Wrap fetch to always include credentials (needed when running in iframe with IIS auth)
        const _origFetch = window.fetch;
        window.fetch = function(url, opts) {
            opts = opts || {};
            opts.credentials = opts.credentials || 'include';
            return _origFetch.call(window, url, opts);
        };

        // Configuration from PHP
        const CONFIG = {
            basePath: <?= json_encode($basePath) ?>,
            fieldName: <?= json_encode($fieldName) ?>,
            imageOnly: <?= $imageOnly ? 'true' : 'false' ?>,
            currentFile: <?= json_encode($currentFile) ?>,
            // Image constraints
            resizeType: <?= json_encode($resizeType) ?>,  // 0=none, 1=maximum, 2=fixed
            resizeWidth: <?= json_encode($resizeWidth) ?>,
            resizeHeight: <?= json_encode($resizeHeight) ?>,
            // Layout options for images
            includeLayout: <?= $includeLayout ? 'true' : 'false' ?>,
            // File filter pattern
            fileSpec: <?= json_encode($fileSpec) ?>
        };

        let currentPath = <?= json_encode($currentPath) ?>;
        let selectedFile = null;
        let viewMode = localStorage.getItem('CMA_Listview') || 'list';

        // DOM elements
        const fileList = document.getElementById('fileList');
        const detailsContent = document.getElementById('detailsContent');
        const btnSelect = document.getElementById('btnSelect');
        const btnDelete = document.getElementById('btnDelete');
        const currentPathEl = document.getElementById('currentPath');
        const dropzone = document.getElementById('dropzone');
        const fileInput = document.getElementById('fileInput');
        const detailsPanel = document.getElementById('detailsPanel');

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            applyViewMode();
            loadDirectory(currentPath);
            setupDragDrop();
            setupFileInput();
        });

        // View mode
        window.setViewMode = function(mode) {
            viewMode = mode;
            localStorage.setItem('CMA_Listview', mode);
            applyViewMode();
            // Re-render if we have items
            if (fileList.querySelector('.file-item')) {
                // Just update the class, items already rendered
                fileList.className = 'file-list' + (viewMode === 'thumb' ? ' view-thumb' : '');
            }
        };

        function applyViewMode() {
            const btnList = document.getElementById('btnViewList');
            const btnThumb = document.getElementById('btnViewThumb');

            if (viewMode === 'thumb') {
                fileList.classList.add('view-thumb');
                btnThumb.classList.add('active');
                btnList.classList.remove('active');
            } else {
                fileList.classList.remove('view-thumb');
                btnList.classList.add('active');
                btnThumb.classList.remove('active');
            }
        }

        // Load directory listing
        window.loadDirectory = function(path) {
            // Prevent navigation outside base path
            path = path || '';
            // Remove any leading slashes and normalize
            path = path.replace(/^\/+/, '').replace(/\/+/g, '/');
            // Prevent directory traversal
            if (path.includes('..') || path.startsWith('/')) {
                showToast('Navigatie buiten basismap niet toegestaan', 'error');
                return;
            }

            currentPath = path;
            selectedFile = null;
            updateButtons();
            showNoSelection();

            currentPathEl.textContent = CONFIG.basePath + currentPath;
            fileList.innerHTML = '<div class="loading">Laden...</div>';

            fetch('?action=list&path=' + encodeURIComponent(currentPath) + '&basepath=' + encodeURIComponent(CONFIG.basePath) + (CONFIG.imageOnly ? '&image=1' : '') + '&filespec=' + encodeURIComponent(CONFIG.fileSpec))
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        renderFileList(data.items);
                    } else {
                        fileList.innerHTML = '<div class="empty-state">' + (data.error || 'Fout bij laden') + '</div>';
                    }
                })
                .catch(err => {
                    fileList.innerHTML = '<div class="empty-state">Fout bij laden: ' + err.message + '</div>';
                });
        };

        window.refreshList = function() {
            loadDirectory(currentPath);
        };

        // Render file list
        function renderFileList(items) {
            if (items.length === 0) {
                fileList.innerHTML = '<div class="empty-state">Map is leeg</div>';
                return;
            }

            // Apply view mode class
            fileList.className = 'file-list' + (viewMode === 'thumb' ? ' view-thumb' : '');

            let html = '';
            for (const item of items) {
                const icon = getIcon(item);
                const meta = item.sizeFormatted || '';

                html += '<div class="file-item" data-type="' + item.type + '" data-name="' + escapeHtml(item.name) + '" data-path="' + escapeHtml(item.path || '') + '"';
                // Store dimensions for validation and modifiedTs for cache busting
                if (item.width) html += ' data-width="' + item.width + '"';
                if (item.height) html += ' data-height="' + item.height + '"';
                if (item.modifiedTs) html += ' data-modifiedts="' + item.modifiedTs + '"';
                html += '>';

                // For images under 2MB, show actual thumbnail in both list and thumb view
                const maxThumbSize = 2 * 1024 * 1024; // 2MB
                if (item.isImage && item.size && item.size < maxThumbSize) {
                    const thumbUrl = CONFIG.basePath + currentPath + (currentPath ? '/' : '') + item.name + '?versie=' + (item.modifiedTs || '');
                    html += '<img class="thumb-img" src="' + escapeHtml(thumbUrl) + '" alt="" loading="lazy">';
                } else {
                    html += '<span class="icon ' + icon.class + '"></span>';
                }

                html += '<span class="name" title="' + escapeHtml(item.name) + '">' + escapeHtml(item.name) + '</span>';
                if (meta) {
                    html += '<span class="meta">' + meta + '</span>';
                }
                html += '</div>';
            }

            fileList.innerHTML = html;

            // Add click handlers
            fileList.querySelectorAll('.file-item').forEach(el => {
                el.addEventListener('click', function() {
                    handleItemClick(this);
                });
                el.addEventListener('dblclick', function() {
                    handleItemDblClick(this);
                });
            });

            // Pre-select current file if set
            if (CONFIG.currentFile) {
                const item = fileList.querySelector('[data-name="' + CSS.escape(CONFIG.currentFile) + '"]');
                if (item) {
                    handleItemClick(item);
                }
            }
        }

        function getIcon(item) {
            if (item.type === 'folder' || item.type === 'parent') {
                return { class: 'folder lnr lnr-folder' };
            }
            if (item.isImage) {
                return { class: 'image lnr lnr-file-image' };
            }
            if (item.ext === 'pdf') {
                return { class: 'pdf lnr lnr-file-empty' };
            }
            if (['doc', 'docx'].includes(item.ext)) {
                return { class: 'doc lnr lnr-file-empty' };
            }
            return { class: 'file lnr lnr-file-empty' };
        }

        function handleItemClick(el) {
            const type = el.dataset.type;
            const name = el.dataset.name;
            const path = el.dataset.path;
            const width = el.dataset.width;
            const height = el.dataset.height;
            const modifiedTs = el.dataset.modifiedts;

            // Single click on folder/parent navigates into it
            if (type === 'folder' || type === 'parent') {
                loadDirectory(path);
                return;
            }

            // Clear previous selection for files
            fileList.querySelectorAll('.file-item').forEach(i => i.classList.remove('selected'));

            if (type === 'file') {
                el.classList.add('selected');
                selectedFile = { name, path, width, height, modifiedTs };
                loadFileDetails(name);
                updateButtons();
            } else {
                selectedFile = null;
                showNoSelection();
                updateButtons();
            }
        }

        function handleItemDblClick(el) {
            const type = el.dataset.type;
            const path = el.dataset.path;

            if (type === 'folder') {
                loadDirectory(path);
            } else if (type === 'parent') {
                loadDirectory(path);
            } else if (type === 'file') {
                confirmSelection();
            }
        }

        // Load file details
        function loadFileDetails(filename) {
            fetch('?action=details&path=' + encodeURIComponent(currentPath) + '&file=' + encodeURIComponent(filename) + '&basepath=' + encodeURIComponent(CONFIG.basePath))
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        renderDetails(data);
                    }
                });
        }

        function renderDetails(file) {
            let html = '';

            if (file.isImage) {
                html += '<img src="' + escapeHtml(file.url) + '" class="preview-image" id="previewImage" alt="Voorbeeld">';
            }

            html += '<table class="details-table">';
            html += '<tr><td>Naam:</td><td>' + escapeHtml(file.name) + ' <a href="' + escapeHtml(file.url) + '" onclick="showFullImage(\'' + escapeHtml(file.url).replace(/'/g, "\\'") + '\', \'' + escapeHtml(file.name).replace(/'/g, "\\'") + '\'); return false;" title="Open bestand op echte formaat" class="view-file-link"><span class="lnr lnr-eye"></span></a></td></tr>';
            html += '<tr><td>Type:</td><td>' + escapeHtml(file.ext.toUpperCase()) + '</td></tr>';
            html += '<tr><td>Grootte:</td><td>' + file.sizeFormatted + '</td></tr>';
            if (file.width && file.height) {
                html += '<tr><td>Afmetingen:</td><td>' + file.width + ' x ' + file.height + ' px</td></tr>';

                // Store dimensions and modifiedTs in selectedFile for validation/cache busting
                if (selectedFile) {
                    selectedFile.width = file.width;
                    selectedFile.height = file.height;
                    if (file.modifiedTs) selectedFile.modifiedTs = file.modifiedTs;
                }

                // Show dimension constraint warning if applicable
                if (CONFIG.resizeType > 0) {
                    const imgW = parseInt(file.width);
                    const imgH = parseInt(file.height);
                    let warning = '';
                    let isError = false;

                    if (CONFIG.resizeType === 1) {
                        // Maximum
                        if (CONFIG.resizeHeight > 0 && imgH > CONFIG.resizeHeight) {
                            warning = 'Te hoog (max ' + CONFIG.resizeHeight + 'px)';
                            isError = true;
                        }
                        if (CONFIG.resizeWidth > 0 && imgW > CONFIG.resizeWidth) {
                            warning += (warning ? ', ' : '') + 'Te breed (max ' + CONFIG.resizeWidth + 'px)';
                            isError = true;
                        }
                        if (!isError && (CONFIG.resizeWidth > 0 || CONFIG.resizeHeight > 0)) {
                            warning = 'Maximaal ' + CONFIG.resizeWidth + 'x' + CONFIG.resizeHeight + ' px toegestaan';
                        }
                    } else if (CONFIG.resizeType === 2) {
                        // Fixed
                        if ((CONFIG.resizeWidth > 0 && imgW !== CONFIG.resizeWidth) ||
                            (CONFIG.resizeHeight > 0 && imgH !== CONFIG.resizeHeight)) {
                            warning = 'Vereiste afmetingen: ' + CONFIG.resizeWidth + 'x' + CONFIG.resizeHeight + ' px';
                            isError = true;
                        }
                    }

                    if (warning) {
                        html += '</table>';
                        html += '<div class="dimension-warning' + (isError ? ' dimension-error' : '') + '">' + warning + '</div>';
                        html += '<table class="details-table" style="margin-top: 10px;">';
                    }
                }
            }
            html += '<tr><td>Gewijzigd:</td><td>' + file.modified + '</td></tr>';
            html += '</table>';

            // Add edit button for raster images (not SVG) - only when layout options are shown (HTML editor)
            if (file.isImage && file.ext !== 'svg' && CONFIG.includeLayout) {
                html += '<div class="edit-actions">';
                html += '<button class="btn btn-primary" onclick="openImageEditor()" title="Bijsnijden en draaien">';
                html += '<span class="lnr lnr-crop"></span> Bewerken';
                html += '</button>';
                html += '</div>';
            }

            detailsContent.innerHTML = html;

            // Show/hide layout options for images
            const layoutOptions = document.getElementById('layoutOptions');
            if (layoutOptions) {
                layoutOptions.style.display = file.isImage ? 'block' : 'none';
                if (file.isImage) updatePreviewLayout();
            }
        }

        function updatePreviewLayout() {
            const img = document.getElementById('previewImage');
            if (!img) return;

            const alignment = document.getElementById('imgAlignment')?.value || '';
            const border = parseInt(document.getElementById('imgBorder')?.value || '0', 10);
            const borderStyle = document.getElementById('imgBorderStyle')?.value || 'solid';
            const borderColor = document.getElementById('imgBorderColor')?.value || '#000000';
            const mTop = parseInt(document.getElementById('imgMarginTop')?.value || '0', 10);
            const mRight = parseInt(document.getElementById('imgMarginRight')?.value || '0', 10);
            const mBottom = parseInt(document.getElementById('imgMarginBottom')?.value || '0', 10);
            const mLeft = parseInt(document.getElementById('imgMarginLeft')?.value || '0', 10);

            // Reset inline styles (class styles still apply)
            img.style.cssText = 'max-width:100%;max-height:200px;object-fit:contain;';

            // Border
            if (border > 0) {
                img.style.border = border + 'px ' + borderStyle + ' ' + borderColor;
            }

            // Border radius
            const borderRadius = parseInt(document.getElementById('imgBorderRadius')?.value || '0', 10);
            if (borderRadius > 0) {
                img.style.borderRadius = borderRadius + 'px';
            }

            // Margin
            img.style.marginTop = mTop + 'px';
            img.style.marginRight = mRight + 'px';
            img.style.marginBottom = mBottom + 'px';
            img.style.marginLeft = mLeft + 'px';

            // Alignment
            if (alignment === 'left') {
                img.style.float = 'left';
                img.style.marginRight = Math.max(mRight, 10) + 'px';
            } else if (alignment === 'right') {
                img.style.float = 'right';
                img.style.marginLeft = Math.max(mLeft, 10) + 'px';
            } else if (alignment === 'center') {
                img.style.display = 'block';
                img.style.marginLeft = 'auto';
                img.style.marginRight = 'auto';
            }
        }

        // Alignment button toggle
        document.querySelectorAll('.align-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var wasActive = this.classList.contains('active');
                document.querySelectorAll('.align-btn').forEach(function(b) { b.classList.remove('active'); });
                if (!wasActive) {
                    this.classList.add('active');
                    document.getElementById('imgAlignment').value = this.dataset.align;
                } else {
                    document.getElementById('imgAlignment').value = '';
                }
                updatePreviewLayout();
            });
        });

        // Border color auto-sets minimum border width
        var imgBorderColorEl = document.getElementById('imgBorderColor');
        if (imgBorderColorEl) {
            imgBorderColorEl.addEventListener('input', function() {
                var borderEl = document.getElementById('imgBorder');
                if (borderEl && parseInt(borderEl.value || '0', 10) < 1) {
                    borderEl.value = 1;
                }
                updatePreviewLayout();
            });
        }

        // Bind layout option change events
        ['imgBorder', 'imgBorderRadius', 'imgMarginTop', 'imgMarginRight', 'imgMarginBottom', 'imgMarginLeft'].forEach(function(id) {
            var el = document.getElementById(id);
            if (el) el.addEventListener('input', updatePreviewLayout);
        });
        // Select elements fire 'change', not 'input'
        ['imgBorderStyle'].forEach(function(id) {
            var el = document.getElementById(id);
            if (el) el.addEventListener('change', updatePreviewLayout);
        });

        window.showFullImage = function(url, name) {
            // Use lib_window_ImageZoom from parent/top window (loaded via library.js)
            var fn = (window.parent && window.parent.lib_window_ImageZoom) || (window.top && window.top.lib_window_ImageZoom);
            if (fn) {
                fn(url, name);
            } else {
                window.open(url, '_blank');
            }
        }

        function showNoSelection() {
            detailsContent.innerHTML = '<div class="no-selection">Selecteer een bestand om details te bekijken</div>';
            // Hide layout options
            const layoutOptions = document.getElementById('layoutOptions');
            if (layoutOptions) {
                layoutOptions.style.display = 'none';
            }
        }

        function updateButtons() {
            btnSelect.disabled = !selectedFile;
            // Delete button uses class for disabled state (tb-btn style)
            if (selectedFile) {
                btnDelete.classList.remove('disabled');
            } else {
                btnDelete.classList.add('disabled');
            }
        }

        // Selection actions
        window.confirmSelection = function() {
            if (!selectedFile) return;

            // Build path relative to basepath (excluding basepath from result)
            // Append ?versie= cache buster so browsers reload overwritten files
            const relativePath = (currentPath ? currentPath + '/' : '') + selectedFile.name
                + (selectedFile.modifiedTs ? '?versie=' + selectedFile.modifiedTs : '');

            // Image dimension validation (from old file-pages.php)
            if (selectedFile.width && selectedFile.height && CONFIG.resizeType > 0) {
                const imgWidth = parseInt(selectedFile.width);
                const imgHeight = parseInt(selectedFile.height);

                if (CONFIG.resizeType === 1) {
                    // Maximum size check
                    if (CONFIG.resizeHeight > 0 && imgHeight > CONFIG.resizeHeight) {
                        libAlert('De afbeelding is te hoog. Maximum is ' + CONFIG.resizeHeight + ' pixels, huidige hoogte is ' + imgHeight + ' pixels.');
                        return;
                    }
                    if (CONFIG.resizeWidth > 0 && imgWidth > CONFIG.resizeWidth) {
                        libAlert('De afbeelding is te breed. Maximum is ' + CONFIG.resizeWidth + ' pixels, huidige breedte is ' + imgWidth + ' pixels.');
                        return;
                    }
                } else if (CONFIG.resizeType === 2) {
                    // Fixed size check
                    if ((CONFIG.resizeWidth > 0 && imgWidth !== CONFIG.resizeWidth) ||
                        (CONFIG.resizeHeight > 0 && imgHeight !== CONFIG.resizeHeight)) {
                        libAlert('De afbeelding heeft niet de correcte afmetingen. Vereist: ' +
                              CONFIG.resizeWidth + 'x' + CONFIG.resizeHeight + ' px, huidig: ' +
                              imgWidth + 'x' + imgHeight + ' px.');
                        return;
                    }
                }
            }

            console.log('[file-browser] confirmSelection:', { relativePath, fieldName: CONFIG.fieldName });

            // Always post a message to parent so listeners can pick it up
            // This is the most reliable mechanism (avoids Shadow DOM / iframe traversal issues)
            // Collect layout options
            var layoutData = {};
            var layoutEl = document.getElementById('layoutOptions');
            if (layoutEl && layoutEl.style.display !== 'none') {
                layoutData.alignment = document.getElementById('imgAlignment')?.value || '';
                layoutData.border = parseInt(document.getElementById('imgBorder')?.value || '0', 10);
                layoutData.borderStyle = document.getElementById('imgBorderStyle')?.value || 'solid';
                layoutData.borderColor = document.getElementById('imgBorderColor')?.value || '#000000';
                layoutData.borderRadius = parseInt(document.getElementById('imgBorderRadius')?.value || '0', 10);
                layoutData.marginTop = parseInt(document.getElementById('imgMarginTop')?.value || '0', 10);
                layoutData.marginRight = parseInt(document.getElementById('imgMarginRight')?.value || '0', 10);
                layoutData.marginBottom = parseInt(document.getElementById('imgMarginBottom')?.value || '0', 10);
                layoutData.marginLeft = parseInt(document.getElementById('imgMarginLeft')?.value || '0', 10);
                layoutData.cssClass = (document.getElementById('imgCssClass')?.value || '').trim();
                layoutData.alt = (document.getElementById('imgAlt')?.value || '').trim();
            }

            if (window.parent && window.parent !== window) {
                window.parent.postMessage({
                    type: 'file-browser-select',
                    fieldName: CONFIG.fieldName,
                    value: relativePath,
                    layout: layoutData
                }, window.location.origin);
            }

            // Helper to find and update field in a document
            function updateFieldInDoc(doc) {
                // Try callback mechanism first (set by form-controller)
                const formLayout = doc.querySelector('.form-layout');
                if (formLayout && typeof formLayout._fileSelectCallback === 'function') {
                    console.log('[file-browser] Using callback mechanism');
                    formLayout._fileSelectCallback(relativePath);
                    return true;
                }

                // Direct field update
                if (CONFIG.fieldName) {
                    const field = doc.getElementById(CONFIG.fieldName) ||
                                  doc.querySelector('[name="' + CONFIG.fieldName + '"]');
                    if (field) {
                        console.log('[file-browser] Setting field value directly');
                        field.value = relativePath;
                        field.dispatchEvent(new Event('change', { bubbles: true }));
                        field.dispatchEvent(new Event('input', { bubbles: true }));
                        return true;
                    }
                }
                return false;
            }

            // Helper to search through iframes for the form
            function searchFramesForField(doc) {
                const frames = doc.querySelectorAll('iframe');
                for (const frame of frames) {
                    try {
                        if (frame.contentDocument && updateFieldInDoc(frame.contentDocument)) {
                            return true;
                        }
                    } catch (e) {
                        // Cross-origin, skip
                    }
                }
                return false;
            }

            // Search up from parent to top for the form field
            if (window.parent && window.parent !== window) {
                try {
                    let fieldFound = false;

                    // Walk up the window hierarchy: parent, parent.parent, ..., top
                    let win = window.parent;
                    const visited = new Set();
                    while (win && !visited.has(win)) {
                        visited.add(win);
                        try {
                            const doc = win.document;
                            // Try iframes in this window first
                            if (!fieldFound) fieldFound = searchFramesForField(doc);
                            // Then try the document itself
                            if (!fieldFound) fieldFound = updateFieldInDoc(doc);
                        } catch (e) { /* cross-origin, skip */ }
                        if (fieldFound || win === window.top) break;
                        win = win.parent;
                    }

                    if (fieldFound) {
                        console.log('[file-browser] Field updated successfully');
                    } else {
                        console.warn('[file-browser] Could not find field:', CONFIG.fieldName);
                    }

                    // Close the lib_OpenWindowCentered popup
                    if (typeof window.parent.lib_OpenWindowCenteredClose === 'function') {
                        window.parent.lib_OpenWindowCenteredClose(true);
                        return;
                    }

                    // Try to close lib-dialog
                    const dialog = window.frameElement?.closest('lib-dialog');
                    if (dialog && typeof dialog.close === 'function') {
                        dialog.close();
                        return;
                    }
                } catch (e) {
                    cmaLog.error('[file-browser] Error accessing parent:', e);
                }
            }

            // Real popup window (window.opener exists)
            if (window.opener && !window.opener.closed) {
                try {
                    const openerDoc = window.opener.document;
                    let fieldFound = updateFieldInDoc(openerDoc);
                    if (!fieldFound) {
                        fieldFound = searchFramesForField(openerDoc);
                    }
                    window.close();
                    return;
                } catch (e) {
                    cmaLog.error('[file-browser] Error accessing opener:', e);
                }
            }

            // Fallback
            console.log('[file-browser] Selected file (fallback):', relativePath);
        };

        window.cancelSelection = function() {
            // lib_OpenWindowCentered popup (iframe in parent)
            if (window.parent && window.parent !== window) {
                if (typeof window.parent.lib_OpenWindowCenteredClose === 'function') {
                    window.parent.lib_OpenWindowCenteredClose(true);
                    return;
                }

                try {
                    const dialog = window.frameElement?.closest('lib-dialog');
                    if (dialog && typeof dialog.close === 'function') {
                        dialog.close();
                        return;
                    }
                } catch (e) {}
            }

            // Real popup window
            if (window.opener) {
                window.close();
            }
        };

        // Delete
        window.deleteSelected = async function() {
            if (!selectedFile) return;

            if (!await libConfirm('Weet je zeker dat je "' + selectedFile.name + '" wilt verwijderen?', {
                title: 'Bestand verwijderen',
                confirmText: 'Verwijderen',
                cancelText: 'Annuleren',
                type: 'danger'
            })) {
                return;
            }

            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('file', selectedFile.name);
            formData.append('path', currentPath);

            fetch('?basepath=' + encodeURIComponent(CONFIG.basePath) + '&path=' + encodeURIComponent(currentPath), {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    loadDirectory(currentPath);
                } else {
                    showToast(data.error, 'error');
                }
            });
        };

        // Create directory using libPrompt
        window.showMkdirDialog = async function() {
            const name = await libPrompt('Naam van de map:', {
                title: 'Nieuwe map',
                placeholder: 'Mapnaam',
                confirmText: 'Aanmaken',
                required: true
            });

            if (!name) return; // User cancelled

            const formData = new FormData();
            formData.append('action', 'mkdir');
            formData.append('name', name);
            formData.append('path', currentPath);

            fetch('?basepath=' + encodeURIComponent(CONFIG.basePath) + '&path=' + encodeURIComponent(currentPath), {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    loadDirectory(currentPath);
                } else {
                    showToast(data.error, 'error');
                }
            });
        };

        // Upload
        window.showUploadDialog = function() {
            fileInput.click();
        };

        function setupFileInput() {
            fileInput.addEventListener('change', function() {
                if (this.files.length > 0) {
                    uploadFiles(this.files);
                }
            });
        }

        function setupDragDrop() {
            dropzone.addEventListener('dragover', function(e) {
                e.preventDefault();
                this.classList.add('dragover');
            });

            dropzone.addEventListener('dragleave', function() {
                this.classList.remove('dragover');
            });

            dropzone.addEventListener('drop', function(e) {
                e.preventDefault();
                this.classList.remove('dragover');
                if (e.dataTransfer.files.length > 0) {
                    uploadFiles(e.dataTransfer.files);
                }
            });
        }

        function uploadFiles(files) {
            for (let i = 0; i < files.length; i++) {
                uploadFile(files[i], false);
            }
        }

        async function uploadFile(file, overwrite) {
            const formData = new FormData();
            formData.append('action', 'upload');
            formData.append('file', file);
            formData.append('overwrite', overwrite ? '1' : '0');
            formData.append('path', currentPath);

            try {
                const response = await fetch('?basepath=' + encodeURIComponent(CONFIG.basePath) + '&path=' + encodeURIComponent(currentPath), {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();

                if (data.success) {
                    showToast('Bestand geüpload: ' + data.filename, 'success');
                    loadDirectory(currentPath);
                } else if (data.exists) {
                    // File exists, ask to overwrite using libConfirm
                    const confirmed = await libConfirm(
                        'Het bestand "' + data.filename + '" bestaat al. Wil je het overschrijven?',
                        {
                            title: 'Bestand bestaat al',
                            type: 'warning',
                            confirmText: 'Overschrijven',
                            cancelText: 'Annuleren'
                        }
                    );
                    if (confirmed) {
                        uploadFile(file, true);
                    }
                } else {
                    showToast(data.error, 'error');
                }
            } catch (err) {
                showToast('Upload mislukt: ' + err.message, 'error');
            }
        }

        // Toast
        function showToast(message, type) {
            const toast = document.getElementById('toast');
            toast.textContent = message;
            toast.className = 'toast ' + (type || '') + ' show';
            setTimeout(() => {
                toast.classList.remove('show');
            }, 3000);
        }

        // Escape HTML
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // ═══════════════════════════════════════════════════════════════
        // IMAGE EDITOR
        // ═══════════════════════════════════════════════════════════════

        window.imageEditor = (function() {
            let cropper = null;
            let currentFile = null;
            let originalWidth = 0;
            let originalHeight = 0;
            let pendingRotation = 0;
            let outputZoom = 100; // Output size percentage (10-100%)

            const dialog = document.getElementById('imageEditorDialog');
            const editorImage = document.getElementById('editorImage');
            const origSizeEl = document.getElementById('editorOrigSize');
            const cropSizeEl = document.getElementById('editorCropSize');
            const aspectSelect = document.getElementById('aspectRatioSelect');
            const customInputs = document.getElementById('customAspectInputs');

            function open(filename) {
                currentFile = filename;
                pendingRotation = 0;
                outputZoom = 100;
                document.getElementById('outputZoom').value = 100;
                document.getElementById('zoomValue').textContent = '100%';

                // Build image URL (basePath already starts with /)
                const imageUrl = CONFIG.basePath + currentPath + (currentPath ? '/' : '') + filename + '?versie=' + Date.now();
                editorImage.src = imageUrl;

                // Load image to get dimensions
                const img = new Image();
                img.onload = function() {
                    originalWidth = img.width;
                    originalHeight = img.height;
                    origSizeEl.textContent = originalWidth + ' × ' + originalHeight + ' px';

                    // Initialize cropper
                    if (cropper) {
                        cropper.destroy();
                    }

                    // Determine aspect ratio based on constraints
                    let aspectRatio = NaN; // Free
                    if (CONFIG.resizeType === 2 && CONFIG.resizeWidth > 0 && CONFIG.resizeHeight > 0) {
                        // Fixed size - force aspect ratio
                        aspectRatio = CONFIG.resizeWidth / CONFIG.resizeHeight;
                        aspectSelect.value = 'custom';
                        customInputs.style.display = 'flex';
                    }

                    cropper = new Cropper(editorImage, {
                        aspectRatio: aspectRatio,
                        viewMode: 1,
                        autoCropArea: 1,
                        responsive: true,
                        crop: function(e) {
                            const data = e.detail;
                            cropSizeEl.textContent = Math.round(data.width) + ' × ' + Math.round(data.height) + ' px';
                        }
                    });
                };
                img.src = imageUrl;

                // Reset aspect ratio selector
                if (CONFIG.resizeType !== 2) {
                    aspectSelect.value = 'free';
                    customInputs.style.display = 'none';
                }

                dialog.open();
            }

            function setAspectRatio(value) {
                if (!cropper) return;

                if (value === 'custom') {
                    customInputs.style.display = 'flex';
                    return;
                }

                customInputs.style.display = 'none';

                if (value === 'free') {
                    cropper.setAspectRatio(NaN);
                } else {
                    cropper.setAspectRatio(parseFloat(value));
                }
            }

            function applyCustomAspect() {
                if (!cropper) return;
                const w = parseInt(document.getElementById('customAspectW').value) || 1;
                const h = parseInt(document.getElementById('customAspectH').value) || 1;
                cropper.setAspectRatio(w / h);
            }

            function rotate(degrees) {
                if (!cropper) return;
                pendingRotation += degrees;
                cropper.rotate(degrees);
            }

            function reset() {
                if (!cropper) return;
                cropper.reset();
                pendingRotation = 0;
            }

            function brightness(direction) {
                if (!currentFile) return;

                const formData = new FormData();
                formData.append('action', 'filter');
                formData.append('file', currentFile);
                formData.append('filter', 'brightness');
                formData.append('arg', direction);
                formData.append('path', currentPath);

                fetch('?basepath=' + encodeURIComponent(CONFIG.basePath), {
                    method: 'POST',
                    body: formData
                })
                .then(r => r.json())
                .then(result => {
                    if (result.success) {
                        showToast(direction === '+' ? 'Afbeelding lichter gemaakt' : 'Afbeelding donkerder gemaakt', 'success');
                        // Reload the image in the editor
                        reloadEditorImage();
                    } else {
                        showToast(result.error || 'Filter toepassen mislukt', 'error');
                    }
                })
                .catch(err => {
                    showToast('Fout bij filter: ' + err.message, 'error');
                });
            }

            function sharpen() {
                if (!currentFile) return;

                const formData = new FormData();
                formData.append('action', 'filter');
                formData.append('file', currentFile);
                formData.append('filter', 'sharpen');
                formData.append('path', currentPath);

                fetch('?basepath=' + encodeURIComponent(CONFIG.basePath), {
                    method: 'POST',
                    body: formData
                })
                .then(r => r.json())
                .then(result => {
                    if (result.success) {
                        showToast('Afbeelding verscherpt', 'success');
                        // Reload the image in the editor
                        reloadEditorImage();
                    } else {
                        showToast(result.error || 'Verscherpen mislukt', 'error');
                    }
                })
                .catch(err => {
                    showToast('Fout bij verscherpen: ' + err.message, 'error');
                });
            }

            function reloadEditorImage() {
                // Reload the image to show filter changes
                const imageUrl = CONFIG.basePath + currentPath + (currentPath ? '/' : '') + currentFile + '?versie=' + Date.now();

                // Destroy and recreate cropper to reload image
                const cropData = cropper ? cropper.getData() : null;
                const aspectRatio = cropper ? cropper.options.aspectRatio : NaN;

                if (cropper) {
                    cropper.destroy();
                }

                editorImage.src = imageUrl;
                const img = new Image();
                img.onload = function() {
                    originalWidth = img.width;
                    originalHeight = img.height;
                    origSizeEl.textContent = originalWidth + ' × ' + originalHeight + ' px';

                    cropper = new Cropper(editorImage, {
                        aspectRatio: aspectRatio,
                        viewMode: 1,
                        autoCropArea: 1,
                        responsive: true,
                        ready: function() {
                            // Try to restore previous crop area if it still fits
                            if (cropData && cropData.width > 0 && cropData.height > 0) {
                                // Cropper will handle if coordinates are out of bounds
                                cropper.setData(cropData);
                            }
                        },
                        crop: function(e) {
                            const data = e.detail;
                            cropSizeEl.textContent = Math.round(data.width) + ' × ' + Math.round(data.height) + ' px';
                        }
                    });
                };
                img.src = imageUrl;
            }

            function setOutputZoom(value) {
                outputZoom = parseInt(value) || 100;
                document.getElementById('zoomValue').textContent = outputZoom + '%';
            }

            function cancel() {
                if (cropper) {
                    cropper.destroy();
                    cropper = null;
                }
                dialog.close();
            }

            function save() {
                if (!cropper || !currentFile) return;

                const cropData = cropper.getData(true); // Get rounded values

                // First, apply rotation if any
                let savePromise = Promise.resolve();

                if (pendingRotation !== 0) {
                    // Normalize rotation to -180 to 180
                    let normalizedRotation = pendingRotation % 360;
                    if (normalizedRotation > 180) normalizedRotation -= 360;
                    if (normalizedRotation < -180) normalizedRotation += 360;

                    if (normalizedRotation !== 0) {
                        const rotateData = new FormData();
                        rotateData.append('action', 'rotate');
                        rotateData.append('file', currentFile);
                        rotateData.append('degrees', normalizedRotation);
                        rotateData.append('path', currentPath);

                        savePromise = fetch('?basepath=' + encodeURIComponent(CONFIG.basePath), {
                            method: 'POST',
                            body: rotateData
                        }).then(r => r.json());
                    }
                }

                // Then crop
                savePromise.then(rotateResult => {
                    if (rotateResult && !rotateResult.success) {
                        showToast(rotateResult.error, 'error');
                        return;
                    }

                    // Determine destination dimensions
                    let destWidth = 0, destHeight = 0;
                    if (CONFIG.resizeType === 2) {
                        // Fixed size - ignore zoom, use exact dimensions
                        destWidth = CONFIG.resizeWidth;
                        destHeight = CONFIG.resizeHeight;
                    } else if (CONFIG.resizeType === 1) {
                        // Maximum - calculate proportional size
                        const cropRatio = cropData.width / cropData.height;
                        if (CONFIG.resizeWidth > 0 && CONFIG.resizeHeight > 0) {
                            const maxRatio = CONFIG.resizeWidth / CONFIG.resizeHeight;
                            if (cropRatio > maxRatio) {
                                destWidth = Math.min(cropData.width, CONFIG.resizeWidth);
                                destHeight = Math.round(destWidth / cropRatio);
                            } else {
                                destHeight = Math.min(cropData.height, CONFIG.resizeHeight);
                                destWidth = Math.round(destHeight * cropRatio);
                            }
                        }
                        // Apply zoom to maximum-constrained dimensions
                        if (outputZoom < 100 && destWidth > 0) {
                            destWidth = Math.round(destWidth * outputZoom / 100);
                            destHeight = Math.round(destHeight * outputZoom / 100);
                        }
                    } else {
                        // No constraints - apply zoom to crop dimensions
                        if (outputZoom < 100) {
                            destWidth = Math.round(cropData.width * outputZoom / 100);
                            destHeight = Math.round(cropData.height * outputZoom / 100);
                        }
                        // If zoom is 100%, destWidth/destHeight stay 0 = keep original crop size
                    }

                    const cropFormData = new FormData();
                    cropFormData.append('action', 'crop');
                    cropFormData.append('file', currentFile);
                    cropFormData.append('x', Math.round(cropData.x));
                    cropFormData.append('y', Math.round(cropData.y));
                    cropFormData.append('width', Math.round(cropData.width));
                    cropFormData.append('height', Math.round(cropData.height));
                    cropFormData.append('destWidth', destWidth);
                    cropFormData.append('destHeight', destHeight);
                    cropFormData.append('path', currentPath);

                    return fetch('?basepath=' + encodeURIComponent(CONFIG.basePath), {
                        method: 'POST',
                        body: cropFormData
                    }).then(r => r.json());
                })
                .then(cropResult => {
                    if (cropResult && cropResult.success) {
                        showToast('Afbeelding opgeslagen', 'success');
                        cancel();
                        loadDirectory(currentPath);

                        // Update selected file dimensions
                        if (selectedFile && selectedFile.name === currentFile) {
                            selectedFile.width = cropResult.width;
                            selectedFile.height = cropResult.height;
                        }
                    } else if (cropResult) {
                        showToast(cropResult.error, 'error');
                    }
                })
                .catch(err => {
                    showToast('Fout bij opslaan: ' + err.message, 'error');
                });
            }

            return {
                open: open,
                setAspectRatio: setAspectRatio,
                applyCustomAspect: applyCustomAspect,
                rotate: rotate,
                reset: reset,
                brightness: brightness,
                sharpen: sharpen,
                setOutputZoom: setOutputZoom,
                cancel: cancel,
                save: save
            };
        })();

        // Open image editor from details panel
        window.openImageEditor = function() {
            if (selectedFile && selectedFile.name) {
                imageEditor.open(selectedFile.name);
            }
        };

        // ═══════════════════════════════════════════════════════════════
        // RESIZE CONFIRM (for upload)
        // ═══════════════════════════════════════════════════════════════

        window.resizeConfirm = (function() {
            let pendingFile = null;
            const dialog = document.getElementById('resizeConfirmDialog');

            function show(file, imgWidth, imgHeight) {
                pendingFile = file;
                document.getElementById('resizeMaxDims').textContent = CONFIG.resizeWidth + ' × ' + CONFIG.resizeHeight + ' px';
                document.getElementById('resizeCurrentDims').textContent = imgWidth + ' × ' + imgHeight + ' px';
                dialog.open();
            }

            function cancel() {
                pendingFile = null;
                dialog.close();
            }

            function uploadAsIs() {
                if (pendingFile) {
                    uploadFile(pendingFile, false);
                }
                cancel();
            }

            function resizeAndUpload() {
                // Not implemented yet - would need client-side canvas resize or server-side
                // For now, just upload
                if (pendingFile) {
                    uploadFile(pendingFile, false);
                }
                cancel();
            }

            return {
                show: show,
                cancel: cancel,
                uploadAsIs: uploadAsIs,
                resizeAndUpload: resizeAndUpload
            };
        })();

    })();
    </script>
</body>
</html>
