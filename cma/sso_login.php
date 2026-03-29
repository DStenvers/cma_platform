<?php
/**
 * SSO Login Initiator
 *
 * Start de OAuth2 Authorization Code flow door redirect naar de IDP.
 * Genereert state/nonce voor CSRF bescherming.
 * Shows a branded loading screen while redirecting.
 */

use App\Library\Application;
use App\Library\Request;
use App\Library\Response;
use Cma\Services\SsoService;
use Cma\Services\MenuService;

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

// DEBUG: Show what URL is being generated (remove after testing)
if (Request::hasQuery('debug')) {
    echo '<pre>';
    echo 'HTTP_HOST: ' . Request::server('HTTP_HOST', 'not set') . "\n";
    echo 'SERVER_NAME: ' . Request::server('SERVER_NAME', 'not set') . "\n";
    echo 'HTTPS: ' . Request::server('HTTPS', 'not set') . "\n";
    echo 'Callback URL: ' . SsoService::getCallbackUrl() . "\n";
    echo 'Full Auth URL: ' . $authUrl . "\n";
    echo "\n--- SSO Config from .env ---\n";
    echo 'sso_idp_url: [' . Application::get('sso_idp_url', '(not set)') . "]\n";
    echo 'sso_client_id: [' . Application::get('sso_client_id', '(not set)') . "]\n";
    echo 'sso_client_base_url: [' . Application::get('sso_client_base_url', '(not set)') . "]\n";
    echo 'sso_idp_authendpoint: [' . Application::get('sso_idp_authendpoint', '(not set)') . "]\n";
    echo "\n--- GLOBALS Application array ---\n";
    print_r($GLOBALS['Application'] ?? 'Not set');
    echo '</pre>';
    exit;
}

// Get application config from menu.json
$appConfig = MenuService::getApplicationConfig();
$logoPath = $appConfig['logo'] ?? 'images/logo.svg';
$backgroundColor = $appConfig['backgroundColor'] ?? '#667eea';

Response::noCache();

// Show branded loading screen with JavaScript redirect
// This provides visual feedback while the SSO redirect happens
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Content Management Applicatie - Aanmelden...</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: <?= htmlspecialchars($backgroundColor) ?>;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .sso-loading {
            background: white;
            border-radius: 16px;
            padding: 48px 64px;
            text-align: center;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            max-width: 400px;
            width: 90%;
        }
        .sso-logo {
            max-width: 180px;
            max-height: 80px;
            margin-bottom: 24px;
        }
        .sso-title {
            font-size: var(--font-size-2xl);
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 8px;
        }
        .sso-subtitle {
            font-size: var(--font-size-md);
            color: #6b7280;
            margin-bottom: 32px;
        }
        .sso-spinner {
            width: 48px;
            height: 48px;
            border: 4px solid #e5e7eb;
            border-top-color: #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 24px;
        }
        .sso-status {
            font-size: var(--font-size);
            color: #9ca3af;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        html.dark-mode body {
            background: <?= htmlspecialchars($backgroundColor) ?>;
            filter: brightness(0.8);
        }
        html.dark-mode .sso-loading {
            background: #1f2937;
        }
        html.dark-mode .sso-title {
            color: #f9fafb;
        }
        html.dark-mode .sso-subtitle {
            color: #9ca3af;
        }
        html.dark-mode .sso-spinner {
            border-color: #374151;
            border-top-color: #818cf8;
        }
    </style>
    <script>if(window.matchMedia&&window.matchMedia('(prefers-color-scheme:dark)').matches)document.documentElement.classList.add('dark-mode');</script>
</head>
<body>
    <div class="sso-loading">
        <img src="<?= htmlspecialchars($logoPath) ?>" alt="Logo" class="sso-logo" onerror="this.style.display='none'">
        <h1 class="sso-title">Content Management Applicatie</h1>
        <p class="sso-subtitle">Single Sign-On</p>
        <div class="sso-spinner"></div>
        <p class="sso-status">Bezig met aanmelden...</p>
    </div>
    <script>
        // Redirect after a brief moment to show the loading screen
        setTimeout(function() {
            window.location.href = <?= json_encode($authUrl) ?>;
        }, 500);
    </script>
</body>
</html>
<?php exit;
