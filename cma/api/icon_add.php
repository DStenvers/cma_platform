<?php
/**
 * Icon Add API
 *
 * Adds an icon to the optimized icon set (shared-icons.js and style.css).
 * Used by the storybook "+" button to add unused icons.
 *
 * POST parameters:
 *   name - icon name (e.g. 'alarm-clock')
 *   code - hex code (e.g. 'e856')
 */

use App\Library\Request;
use App\Library\Response;
use Cma\SecurityHelper;

require_once __DIR__ . '/../bootstrap.inc';

Response::setContentType('application/json');

// Developer access required
if (!SecurityHelper::isDeveloper()) {
    Response::json(['error' => 'Alleen toegankelijk voor developers'], 403);
}

if (Request::server('REQUEST_METHOD') !== 'POST') {
    Response::json(['error' => 'POST required'], 405);
}

$name = trim(Request::post('name', ''));
$code = trim(Request::post('code', ''));

// Validate name: lowercase letters, numbers, hyphens only
if (!$name || !preg_match('/^[a-z0-9-]+$/', $name)) {
    Response::json(['error' => 'Ongeldige icoonnaam'], 400);
}

// Validate code: 4-character hex
if (!$code || !preg_match('/^[a-f0-9]{3,5}$/', $code)) {
    Response::json(['error' => 'Ongeldige icooncode'], 400);
}

$cmaRoot = dirname(__DIR__);
$sharedIconsPath = $cmaRoot . '/webcomponents/shared-icons.js';
$styleCssPath = $cmaRoot . '/assets/css/style.css';

// --- Update shared-icons.js ---
$jsContent = file_get_contents($sharedIconsPath);
if ($jsContent === false) {
    Response::json(['error' => 'Kan shared-icons.js niet lezen'], 500);
}

// Check if icon already exists
if (preg_match("/'" . preg_quote($name, '/') . "'\\s*:/", $jsContent)) {
    Response::json(['error' => 'Icoon bestaat al in shared-icons.js'], 409);
}

// Insert before the closing '};' of ICON_CODES
// Find the last entry line before '};'
$jsContent = preg_replace(
    "/(        '[a-z0-9-]+':\\s*'[a-f0-9]+')\n(    \\};)/",
    "$1,\n        '{$name}': '{$code}'\n$2",
    $jsContent,
    1
);

if (file_put_contents($sharedIconsPath, $jsContent) === false) {
    Response::json(['error' => 'Kan shared-icons.js niet schrijven'], 500);
}

// --- Update style.css ---
$cssContent = file_get_contents($styleCssPath);
if ($cssContent === false) {
    Response::json(['error' => 'Kan style.css niet lezen'], 500);
}

// Check if CSS rule already exists
if (strpos($cssContent, ".lnr-{$name}::before") !== false) {
    Response::json(['success' => true, 'message' => 'Icoon toegevoegd aan shared-icons.js (CSS bestond al)']);
}

// Find the last .lnr-*::before rule and insert after it
$newRule = ".lnr-{$name}::before {content:\"\\{$code}\"}";

// Insert after the last icon CSS rule (before the dirty state indicator comment)
$cssContent = preg_replace(
    '/(\.lnr-[a-z0-9-]+::before\s*\{[^}]+\})\n(\/\* Dirty state)/',
    "$1\n{$newRule}\n$2",
    $cssContent,
    1
);

if (file_put_contents($styleCssPath, $cssContent) === false) {
    Response::json(['error' => 'Kan style.css niet schrijven'], 500);
}

Response::json([
    'success' => true,
    'message' => "Icoon '{$name}' toegevoegd aan shared-icons.js en style.css"
]);
