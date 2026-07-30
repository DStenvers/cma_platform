<?php
/**
 * 404 Error Handler for CMA
 *
 * Logs 404 errors to a daily log file accessible from logreader.php.
 * Attempts to find common misplaced files (like icons) and redirect if found.
 */

// Don't use bootstrap - this needs to be lightweight and not trigger sessions
// (which would override cache headers on static files)
// Note: Direct $_SERVER access is used here because bootstrap is not loaded,
// so Request/Session/Cookie wrapper classes are not available.

// Get the requested URL
$requestUri = $_SERVER['REQUEST_URI'] ?? '/unknown';
$referrer = $_SERVER['HTTP_REFERER'] ?? '';

// Parse to get the file that was requested
$parsedUrl = parse_url($requestUri);
$requestedPath = $parsedUrl['path'] ?? $requestUri;

// Check if this is an icon that should be in /cma/assets/icons/
$iconRedirect = null;
if (preg_match('#/assets/icons/([^/]+\.svg)$#', $requestedPath, $matches)) {
    $iconFile = $matches[1];
    $correctPath = '/cma/assets/icons/' . $iconFile;
    $fullPath = __DIR__ . '/assets/icons/' . $iconFile;

    if (file_exists($fullPath)) {
        // Icon exists in correct location - redirect
        $iconRedirect = $correctPath;
    }
}

// Log the 404 error
$logDir = dirname(__DIR__) . '/.logs/404';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0755, true);
}

$logFile = $logDir . '/404_' . date('Y-m-d') . '.log';

$entry = [
    'ts' => date('Y-m-d\TH:i:s'),
    'url' => $requestUri,
    'referer' => $referrer,
    'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
    'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
    'ua' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 200),
];

// Add redirect info if we found the file
if ($iconRedirect) {
    $entry['redirect'] = $iconRedirect;
    $entry['type'] = 'icon_redirect';
} else {
    $entry['type'] = 'not_found';
}

// Write to log file
$line = json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
@file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);

// If we found an icon redirect, perform it
if ($iconRedirect) {
    header('Location: ' . $iconRedirect, true, 301);
    exit;
}

// Return the 404 response
http_response_code(404);

// For API requests, return JSON
if (strpos($requestUri, '/api/') !== false ||
    (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'error' => 'Not Found',
        'message' => 'The requested resource could not be found.',
        'path' => $requestedPath
    ]);
    exit;
}

// For regular requests the platform renders the page — the same one the site's
// own 404 handler uses, so both look alike and a change lands in one place.
header('Content-Type: text/html; charset=utf-8');
$notFoundHomeUrl   = '/cma/';
$notFoundHomeLabel = 'Terug naar Dashboard';
require __DIR__ . '/notfound_page.inc';
