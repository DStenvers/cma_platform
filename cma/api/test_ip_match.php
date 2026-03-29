<?php
/**
 * Test endpoint for IP address CIDR matching
 * Only available in development/test environments
 *
 * This is a public test endpoint (no auth required)
 * Excluded from login check in bootstrap.inc, but environment-protected below
 */

use App\Library\Application;
use App\Library\Request;
use Cma\SecurityHelper;

require_once __DIR__ . '/../bootstrap.inc';

header('Content-Type: application/json');

// Only allow in development/test environments (O=Ontwikkeling, T=Test, L=Local)
// Also allow when env is empty or defaults (local dev without explicit config)
$env = Application::get('env', '');
if (in_array($env, ['P', 'A'])) {  // Block production and acceptance only
    echo json_encode(['success' => false, 'error' => 'Only available in development/test environments']);
    exit;
}

$ip = Request::query('ip', '');
$pattern = Request::query('pattern', '');

if ($ip === '' || $pattern === '') {
    echo json_encode(['success' => false, 'error' => 'Missing ip or pattern parameter']);
    exit;
}

$matches = SecurityHelper::ipMatchesPattern($ip, $pattern);

echo json_encode([
    'success' => true,
    'ip' => $ip,
    'pattern' => $pattern,
    'matches' => $matches
]);
