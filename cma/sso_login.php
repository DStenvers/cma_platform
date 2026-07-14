<?php
/**
 * SSO Login Initiator
 *
 * Start de OAuth2 Authorization Code flow door redirect naar de IDP.
 * Genereert state/nonce voor CSRF bescherming.
 * Redirects immediately: the login page shows the "Bezig met aanmelden"
 * feedback inside the login dialog, so no intermediate screen is rendered.
 */

use App\Library\Application;
use App\Library\Request;
use App\Library\Response;
use Cma\Services\SsoService;

require_once __DIR__ . '/bootstrap.inc';

// Controleer of SSO is ingeschakeld
if (!SsoService::isEnabled()) {
    header('Location: login.php?error=sso_disabled');
    exit;
}

// Bepaal return URL
$returnUrl = Request::query('returnUrl', '');
if (empty($returnUrl)) {
    $returnUrl = Request::server('HTTP_REFERER', '');
}

// Valideer return URL (moet binnen onze domain zijn)
if (!empty($returnUrl)) {
    $parsedUrl = parse_url($returnUrl);
    $serverHost = Request::server('HTTP_HOST', '');

    // Alleen accepteren als het dezelfde host is of een relatieve URL
    if (isset($parsedUrl['host']) && $parsedUrl['host'] !== $serverHost) {
        $returnUrl = 'main.php'; // Fallback naar main
    }
}

if (empty($returnUrl)) {
    $returnUrl = 'main.php';
}

// Genereer authorization URL
$authUrl = SsoService::getAuthorizationUrl($returnUrl);

Response::noCache();
Response::redirect($authUrl);
