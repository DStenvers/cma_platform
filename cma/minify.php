<?php
/**
 * Minify.php - CSS/JS file server with minification support
 * Uses matthiasmullie/minify library
 *
 * Loaded via bootstrap wrapper — autoloader and helpers are already available.
 * Release session lock since we only serve static assets.
 */

// Release session lock — minify serves static assets, doesn't need sessions
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

// Clean the wrapper's output buffer — we set our own content-type headers
if (ob_get_level() > 0) {
    ob_end_clean();
}

use MatthiasMullie\Minify\CSS;
use MatthiasMullie\Minify\JS;
use App\Library\Request;

// Determine environment - P = Production, others = Development/Test/Local/Acceptance
$environment = $_ENV['APP_ENVIRONMENT'] ?? $GLOBALS['Application']['omgeving'] ?? 'P';
$isProduction = (strtoupper($environment) === 'P');

// Configuration - minification active in production only, browser caching always enabled
// The URL version parameter (v=xxx) handles cache busting when files change
$MINIFY_ACTIVE = $isProduction;
$DISK_CACHE_ACTIVE = $isProduction; // Only disk-cache minified content in production

$config = [
    'cache_dir' => dirname(__DIR__) . '/cache/cma/minify',  // Cache directory in site root
    'cache_time' => 86400 * 28,  // Browser cache: 28 days (URL versioning handles cache busting)
    'gzip' => true,              // Enable gzip compression
];

// Allowed extensions
$allowedExtensions = ['css', 'js'];

/**
 * Get file extension
 */
function getExtension(string $file): string
{
    return strtolower(pathinfo($file, PATHINFO_EXTENSION));
}

/**
 * Resolve file path - find the actual file
 */
function resolveFilePath(string $requestedFile, string $basePath): ?string
{
    $fullPath = $basePath . '/' . $requestedFile;
    $fullPath = str_replace('//', '/', $fullPath);

    // Normalize the path
    $realPath = realpath($fullPath);
    if ($realPath && is_file($realPath)) {
        return $realPath;
    }

    // Try without leading slash
    $fullPath = $basePath . '/' . ltrim($requestedFile, '/');
    $realPath = realpath($fullPath);
    if ($realPath && is_file($realPath)) {
        return $realPath;
    }

    return null;
}

/**
 * Check if a pre-built .min.js exists and is newer than the source file.
 * Returns the .min.js path if valid, null otherwise.
 */
function getPrebuiltMinFile(string $sourcePath): ?string
{
    // Only for .js files (not .min.js themselves)
    if (substr($sourcePath, -3) !== '.js' || substr($sourcePath, -7) === '.min.js') {
        return null;
    }

    $minPath = substr($sourcePath, 0, -3) . '.min.js';
    if (file_exists($minPath) && filemtime($minPath) >= filemtime($sourcePath)) {
        return $minPath;
    }

    return null;
}

/**
 * Get cache file path
 */
function getCachePath(string $cacheDir, string $cacheKey, string $ext, bool $gzipped = false): string
{
    return $cacheDir . '/' . $cacheKey . '.' . $ext . ($gzipped ? '.gz' : '');
}

/**
 * Check if cache is valid
 */
function isCacheValid(string $cachePath, int $latestMtime): bool
{
    if (!file_exists($cachePath)) {
        return false;
    }
    return filemtime($cachePath) >= $latestMtime;
}

/**
 * Ensure cache directory exists
 */
function ensureCacheDir(string $cacheDir): bool
{
    if (!is_dir($cacheDir)) {
        return mkdir($cacheDir, 0755, true);
    }
    return true;
}

/**
 * Clean old cache files (runs probabilistically to avoid overhead)
 * Keeps only files modified in the last 24 hours
 */
function cleanOldCacheFiles(string $cacheDir): void
{
    // Only run 1% of the time
    if (mt_rand(1, 100) > 1) {
        return;
    }

    $maxAge = 86400; // 24 hours
    $now = time();

    $files = glob($cacheDir . '/*.{js,css}', GLOB_BRACE);
    if ($files === false) {
        return;
    }

    foreach ($files as $file) {
        $mtime = filemtime($file);
        if ($mtime !== false && ($now - $mtime) > $maxAge) {
            @unlink($file);
        }
    }
}

/**
 * Rewrite relative URLs in CSS content to be relative to the minify.php location
 * This fixes font-face and background-image paths when CSS is served from /cma/minify.php
 *
 * @param string $cssContent The CSS content
 * @param string $originalRequestedPath The original requested path (e.g., "../library/library.css")
 */
function rewriteCssUrls(string $cssContent, string $originalRequestedPath): string
{
    // Get the directory of the original CSS file from the requested path
    // e.g., "../library/library.css" -> "../library"
    // e.g., "style.css" -> "."
    $relativeDir = dirname($originalRequestedPath);
    if ($relativeDir === '.') {
        $relativeDir = '';
    }

    // Rewrite url() references
    $cssContent = preg_replace_callback(
        '/url\s*\(\s*[\'"]?([^\'"\)]+)[\'"]?\s*\)/i',
        function ($matches) use ($relativeDir) {
            $url = $matches[1];

            // Skip absolute URLs, data URIs, and protocol-relative URLs
            if (preg_match('#^(https?://|data:|//|/)#i', $url)) {
                return $matches[0];
            }

            // Skip already-absolute paths that start with ../
            // But only if we don't have a relative dir to prepend
            if ($relativeDir === '' && strpos($url, '../') === 0) {
                return $matches[0];
            }

            // Build new relative path from /cma/minify.php to the font/image
            // Original CSS is in e.g. ../library/, font is in fonts/file.woff
            // We need to output ../library/fonts/file.woff
            if ($relativeDir !== '') {
                $newUrl = $relativeDir . '/' . $url;
            } else {
                $newUrl = $url;
            }
            // Normalize the path
            $newUrl = str_replace('//', '/', $newUrl);

            return "url('" . $newUrl . "')";
        },
        $cssContent
    );

    return $cssContent;
}

/**
 * Send response with appropriate headers
 */
function sendResponse(string $content, string $contentType, int $cacheTime, string $etag, bool $gzip = false): void
{
    // Check if client has cached version
    $ifNoneMatch = Request::server('HTTP_IF_NONE_MATCH', '');
    if ($ifNoneMatch === $etag) {
        http_response_code(304);
        exit;
    }

    // Check if client accepts gzip
    $acceptGzip = $gzip && strpos(Request::server('HTTP_ACCEPT_ENCODING', ''), 'gzip') !== false;

    // Remove any existing cache headers (e.g., from session_start) before setting our own
    header_remove('Cache-Control');
    header_remove('Pragma');
    header_remove('Expires');

    header('Content-Type: ' . $contentType . '; charset=utf-8');
    header('Cache-Control: public, max-age=' . $cacheTime);
    header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $cacheTime) . ' GMT');
    header('ETag: ' . $etag);
    header('X-Content-Type-Options: nosniff');
    header('Vary: Accept-Encoding');

    if ($acceptGzip) {
        header('Content-Encoding: gzip');
        echo gzencode($content, 9);
    } else {
        echo $content;
    }
}

// ============================================================================
// Main execution
// ============================================================================

$basePath = __DIR__;
$requestedFile = Request::query('f', '');

if (empty($requestedFile)) {
    http_response_code(400);
    die('Missing file parameter. Usage: minify.php?f=path/to/file.css');
}

// Security: prevent directory traversal (but allow ../ for parent directory access)
$requestedFile = str_replace(['..\\', "\0"], '', $requestedFile);
// Normalize multiple slashes
$requestedFile = preg_replace('#/+#', '/', $requestedFile);

// Handle multiple files (comma-separated)
$files = array_map('trim', explode(',', $requestedFile));
$files = array_filter($files); // Remove empty entries

if (empty($files)) {
    http_response_code(400);
    die('No valid files specified');
}

// Determine extension from first file
$ext = getExtension($files[0]);
if (!in_array($ext, $allowedExtensions)) {
    http_response_code(403);
    die('File type not allowed: ' . htmlspecialchars($ext));
}

// Validate all files have same extension and are in allowed paths
// Store both original paths and resolved paths for URL rewriting
$fileData = []; // Array of ['original' => ..., 'resolved' => ...]
$latestMtime = 0;

foreach ($files as $file) {
    // Check extension matches
    if (getExtension($file) !== $ext) {
        http_response_code(400);
        die('All files must have the same extension');
    }

    // Resolve the file path
    $resolvedPath = resolveFilePath($file, $basePath);
    if ($resolvedPath === null) {
        http_response_code(404);
        die('File not found: ' . htmlspecialchars($file));
    }

    $fileData[] = [
        'original' => $file,
        'resolved' => $resolvedPath
    ];
    $mtime = filemtime($resolvedPath);
    if ($mtime > $latestMtime) {
        $latestMtime = $mtime;
    }
}

// Generate cache key based on files and their modification times
$cacheKey = md5(implode('|', $files) . '|' . $latestMtime);
$etag = '"' . $cacheKey . '"';

// Check if client has current version
$ifNoneMatch = Request::server('HTTP_IF_NONE_MATCH', '');
if ($ifNoneMatch === $etag) {
    http_response_code(304);
    exit;
}

// Set content type
$contentType = ($ext === 'css') ? 'text/css' : 'application/javascript';

// Check disk cache (skip cache if minification is disabled for easier debugging)
$cacheDir = $config['cache_dir'];
$cachePath = getCachePath($cacheDir, $cacheKey, $ext);

if ($MINIFY_ACTIVE && isCacheValid($cachePath, $latestMtime)) {
    // Serve from cache
    $content = file_get_contents($cachePath);
    sendResponse($content, $contentType, $config['cache_time'], $etag, $config['gzip']);
    exit;
}

// Minify the files (or just combine if MINIFY_ACTIVE is false)
try {
    if ($ext === 'css') {
        // For CSS, we need to rewrite URLs before minifying
        $content = '';
        foreach ($fileData as $file) {
            $fileContent = file_get_contents($file['resolved']);
            // Rewrite relative URLs to be correct from /cma/minify.php
            // Pass the original requested path so we can calculate proper relative URLs
            $fileContent = rewriteCssUrls($fileContent, $file['original']);
            $content .= $fileContent . "\n";
        }

        // Minify the combined CSS (if enabled)
        if ($MINIFY_ACTIVE) {
            $minifier = new CSS();
            $minifier->add($content);
            $content = $minifier->minify();
        }
    } else {
        // JS files - no URL rewriting needed
        // In production: use pre-built .min.js files (from terser) when available
        // In dev/test: always use raw source files for easier debugging
        $prebuiltContent = '';
        $needsMinify = [];
        $rawFiles = [];

        foreach ($fileData as $file) {
            $minFile = $MINIFY_ACTIVE ? getPrebuiltMinFile($file['resolved']) : null;
            if ($minFile !== null) {
                // Use the pre-built terser-minified version directly (production only)
                $prebuiltContent .= file_get_contents($minFile) . ";\n";
            } elseif ($MINIFY_ACTIVE) {
                // No pre-built version, collect for MatthiasMullie (production only)
                $needsMinify[] = $file;
            } else {
                // Dev/test mode: always use raw source for easier debugging
                $rawFiles[] = $file;
            }
        }

        if (!empty($needsMinify)) {
            $minifier = new JS();
            foreach ($needsMinify as $file) {
                $minifier->add($file['resolved']);
            }
            $content = $prebuiltContent . $minifier->minify();
        } elseif (!empty($rawFiles)) {
            // Dev mode: mix of pre-built and raw files
            $content = $prebuiltContent;
            foreach ($rawFiles as $file) {
                $content .= "/* === " . $file['original'] . " === */\n";
                $content .= file_get_contents($file['resolved']) . ";\n\n";
            }
        } else {
            // All files had pre-built versions
            $content = $prebuiltContent;
        }
    }

    // Save to cache (only when minification is active)
    if ($MINIFY_ACTIVE && ensureCacheDir($cacheDir)) {
        file_put_contents($cachePath, $content);
        // Probabilistically clean old cache files
        cleanOldCacheFiles($cacheDir);
    }

    // Send response
    sendResponse($content, $contentType, $config['cache_time'], $etag, $config['gzip']);

} catch (Exception $e) {
    http_response_code(500);
    die('Minification error: ' . htmlspecialchars($e->getMessage()));
}
