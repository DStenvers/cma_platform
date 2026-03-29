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
$logDir = __DIR__ . '/logs';
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

// For regular requests, show a simple HTML page
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Pagina niet gevonden</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f7fa;
            color: #333;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            text-align: center;
            max-width: 500px;
        }
        h1 {
            font-size: 120px;
            font-weight: 700;
            color: #204496;
            line-height: 1;
            margin-bottom: 10px;
        }
        h2 {
            font-size: var(--font-size-3xl);
            font-weight: 600;
            color: #555;
            margin-bottom: 20px;
        }
        p {
            color: #666;
            margin-bottom: 30px;
            line-height: 1.6;
        }
        .path {
            background: #e9ecef;
            padding: 8px 12px;
            border-radius: 4px;
            font-family: monospace;
            font-size: var(--font-size);
            word-break: break-all;
            margin-bottom: 30px;
        }
        a {
            display: inline-block;
            padding: 12px 24px;
            background: #204496;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 500;
        }
        a:hover {
            background: #163070;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>404</h1>
        <h2>Pagina niet gevonden</h2>
        <p>De pagina die je zoekt bestaat niet of is verplaatst.</p>
        <div class="path"><?= htmlspecialchars($requestedPath) ?></div>
        <a href="/cma/">Terug naar Dashboard</a>
    </div>
</body>
</html>
