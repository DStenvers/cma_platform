<?php
/**
 * SSO Callback Handler
 *
 * Verwerkt de response van de Identity Provider na authenticatie.
 * Exchange authorization code voor tokens en logt gebruiker in.
 */

use App\Library\Cookie;
use App\Library\Request;
use App\Library\Response;
use Cma\SecurityHelper;
use Cma\Services\Logger;
use Cma\Services\SsoService;
use Cma\Services\MenuService;

require_once __DIR__ . '/bootstrap.inc';

Response::noCache();

// Haal parameters op
$code = Request::query('code', '');
$state = Request::query('state', '');
$error = Request::query('error', '');
$errorDescription = Request::query('error_description', '');

// Check voor error van IDP
if (!empty($error)) {
    handleError('IDP fout: ' . $error . ($errorDescription ? ' - ' . $errorDescription : ''));
    exit;
}

// Valideer state parameter (CSRF bescherming)
if (!SsoService::validateState($state)) {
    handleError('Ongeldige state parameter. Mogelijk CSRF aanval of verlopen sessie.');
    exit;
}

// Valideer code parameter
if (empty($code)) {
    handleError('Geen authorization code ontvangen van IDP.');
    exit;
}

// Exchange code voor tokens
$tokenResponse = SsoService::exchangeCodeForToken($code);
if ($tokenResponse === null) {
    handleError('Fout bij het ophalen van tokens van IDP.');
    exit;
}

// Parse ID token
$idToken = $tokenResponse['id_token'] ?? '';
if (empty($idToken)) {
    handleError('Geen ID token ontvangen van IDP.');
    exit;
}

$tokenData = SsoService::parseIdToken($idToken);
if ($tokenData === null) {
    handleError('Ongeldige ID token ontvangen.');
    exit;
}

// Haal email (sub claim) uit token
$email = $tokenData['sub'] ?? '';
if (empty($email)) {
    handleError('Geen email adres in token gevonden.');
    exit;
}

// Zoek gebruiker in CMA database
$user = SsoService::findCmaUserByEmail($email);
if ($user === null) {
    handleError('Account niet gevonden in CMA beheer. Neem contact op met de administrator.', $email);
    exit;
}

// Login succesvol - zet cookies (userID + userGUID for dual validation)
Cookie::set(SecurityHelper::COOKIE_USERID, (string)$user['id']);

// Get or generate userGUID for dual validation
$userGUID = $user['guid'] ?? '';
if (empty($userGUID)) {
    // Generate GUID if not present and save to database
    $userGUID = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
    $conn = \App\Library\Database::getConnection('users');
    if ($conn) {
        \App\Library\Database::query("UPDATE tblUsers SET userGUID = ? WHERE ID = ?", [$userGUID, $user['id']], $conn);
    }
}
Cookie::set(SecurityHelper::COOKIE_USERGUID, $userGUID);

// Laatste login opslaan
Cookie::set(SecurityHelper::COOKIE_LAST_LOGIN, $user['login']);

// Verwijder SSO cookies
SsoService::clearSsoCookies();

// Redirect naar originele URL of main.php
$returnUrl = SsoService::getReturnUrl();
if (empty($returnUrl) || !isValidReturnUrl($returnUrl)) {
    $returnUrl = 'main.php';
}

// Get application config from menu.json
$appConfig = MenuService::getApplicationConfig();
$logoPath = $appConfig['logo'] ?? 'images/logo.svg';
$backgroundColor = $appConfig['backgroundColor'] ?? '#667eea';

// Show success loading screen before redirect
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Content Management Applicatie - Aangemeld</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
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
        .sso-logo { max-width: 180px; max-height: 80px; margin-bottom: 24px; }
        .sso-title { font-size: var(--font-size-2xl); font-weight: 600; color: #1f2937; margin-bottom: 8px; }
        .sso-subtitle { font-size: var(--font-size-md); color: #6b7280; margin-bottom: 32px; }
        .sso-check {
            width: 48px; height: 48px;
            margin: 0 auto 24px;
            color: #10b981;
        }
        .sso-status { font-size: var(--font-size); color: #9ca3af; }
        html.dark-mode body { background: <?= htmlspecialchars($backgroundColor) ?>; filter: brightness(0.8); }
        html.dark-mode .sso-loading { background: #1f2937; }
        html.dark-mode .sso-title { color: #f9fafb; }
        html.dark-mode .sso-subtitle { color: #9ca3af; }
    </style>
    <script>if(window.matchMedia&&window.matchMedia('(prefers-color-scheme:dark)').matches)document.documentElement.classList.add('dark-mode');</script>
</head>
<body>
    <div class="sso-loading">
        <img src="<?= htmlspecialchars($logoPath) ?>" alt="Logo" class="sso-logo" onerror="this.style.display='none'">
        <h1 class="sso-title">Content Management Applicatie</h1>
        <p class="sso-subtitle">Welkom, <?= htmlspecialchars($user['fullName']) ?>!</p>
        <svg class="sso-check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" stroke-linecap="round" stroke-linejoin="round"/>
            <polyline points="22,4 12,14.01 9,11.01" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        <p class="sso-status">Laden...</p>
    </div>
    <script>
        setTimeout(function() {
            window.location.href = <?= json_encode($returnUrl) ?>;
        }, 800);
    </script>
</body>
</html>
<?php exit;

/**
 * Toon foutmelding en redirect naar login
 */
function handleError(string $message, string $email = ''): void
{
    // Log de fout
    Logger::error('SSO Login Error', ['message' => $message, 'email' => $email ?: null]);

    // Verwijder SSO cookies
    SsoService::clearSsoCookies();

    // Redirect naar login met foutmelding
    $errorParam = urlencode($message);
    header("Location: login.php?sso_error=$errorParam");
    exit;
}

/**
 * Valideer of return URL veilig is
 */
function isValidReturnUrl(string $url): bool
{
    // Lege URL is niet geldig
    if (empty($url)) {
        return false;
    }

    // Relatieve URLs zijn OK
    if ($url[0] === '/' || strpos($url, 'http') !== 0) {
        return true;
    }

    // Absolute URLs moeten binnen onze domain zijn
    $parsedUrl = parse_url($url);
    $serverHost = Request::server('HTTP_HOST', '');

    return isset($parsedUrl['host']) && $parsedUrl['host'] === $serverHost;
}
