<?php

namespace Cma\Services;

use App\Library\Application;
use App\Library\Cookie;
use App\Library\Database;
use App\Library\Request;
use App\Library\Server;
use App\Library\SQL;

/**
 * SSO Service voor OAuth2 authenticatie
 *
 * Implementeert OAuth2 Authorization Code flow met STB Identity Provider.
 * Hergebruikt configuratie uit .env bestanden.
 */
class SsoService
{
    // Cookie namen voor SSO state management
    private const COOKIE_SSO_STATE = 'cma_sso_state';
    private const COOKIE_SSO_NONCE = 'cma_sso_nonce';
    private const COOKIE_SSO_RETURN_URL = 'cma_sso_return_url';

    /**
     * Controleer of SSO is ingeschakeld
     */
    public static function isEnabled(): bool
    {
        return Application::get('cma_sso_enabled', 'false') === 'true';
    }

    /**
     * Controleer of SSO verplicht is (geen lokale login)
     */
    public static function isForced(): bool
    {
        return Application::get('cma_force_sso', 'false') === 'true';
    }

    /**
     * Genereer een random string voor state/nonce
     */
    public static function generateRandomString(int $length = 32): string
    {
        $bytes = random_bytes($length);
        return bin2hex($bytes);
    }

    /**
     * Genereer state parameter en sla op in cookie
     */
    public static function generateState(): string
    {
        $state = self::generateRandomString(16);
        Cookie::set(self::COOKIE_SSO_STATE, $state, 0, '/', '', false, true);
        return $state;
    }

    /**
     * Genereer nonce parameter en sla op in cookie
     */
    public static function generateNonce(): string
    {
        $nonce = self::generateRandomString(16);
        Cookie::set(self::COOKIE_SSO_NONCE, $nonce, 0, '/', '', false, true);
        return $nonce;
    }

    /**
     * Sla return URL op in cookie
     */
    public static function setReturnUrl(string $url): void
    {
        Cookie::set(self::COOKIE_SSO_RETURN_URL, $url, 0, '/', '', false, true);
    }

    /**
     * Haal return URL op uit cookie
     */
    public static function getReturnUrl(): string
    {
        $url = Cookie::get(self::COOKIE_SSO_RETURN_URL, '');
        Cookie::delete(self::COOKIE_SSO_RETURN_URL);
        return $url;
    }

    /**
     * Valideer state parameter tegen cookie
     */
    public static function validateState(string $state): bool
    {
        $storedState = Cookie::get(self::COOKIE_SSO_STATE, '');
        Cookie::delete(self::COOKIE_SSO_STATE);

        if (empty($state) || empty($storedState)) {
            return false;
        }

        return hash_equals($storedState, $state);
    }

    /**
     * Bouw de authorization URL voor redirect naar IDP
     */
    public static function getAuthorizationUrl(string $returnUrl = ''): string
    {
        $state = self::generateState();
        $nonce = self::generateNonce();

        if (!empty($returnUrl)) {
            self::setReturnUrl($returnUrl);
        }

        $idpUrl = rtrim(Application::get('sso_idp_url', Application::get('sso_idp_conn', '')), '/');
        $authEndpoint = Application::get('sso_idp_authendpoint', 'oauth2/authorize');
        $clientId = Application::get('sso_client_id', '');
        $redirectUri = self::getCallbackUrl();
        $scope = Application::get('sso_login_scope', 'openid');
        $responseType = Application::get('sso_login_type', 'code');
        $prompt = Application::get('sso_login_prompt', 'login');

        $params = [
            'response_type' => $responseType,
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'scope' => $scope,
            'state' => $state,
            'nonce' => $nonce,
            'prompt' => $prompt,
        ];

        return $idpUrl . '/' . $authEndpoint . '?' . http_build_query($params);
    }

    /**
     * Haal de callback URL op
     *
     * Uses sso_client_base_url from config, or auto-detects from current request
     */
    public static function getCallbackUrl(): string
    {
        $baseUrl = Application::get('sso_client_base_url', '');

        // Auto-detect base URL if not configured or if it doesn't match current host
        if (empty($baseUrl) || self::shouldAutoDetectBaseUrl($baseUrl)) {
            $protocol = (!empty(Request::server('HTTPS')) && Request::server('HTTPS') !== 'off') ? 'https://' : 'http://';
            $host = Request::server('HTTP_HOST', Request::server('SERVER_NAME', 'localhost'));
            $baseUrl = $protocol . $host;
        }

        $baseUrl = rtrim($baseUrl, '/');
        $callbackPath = Application::get('cma_sso_callback_url', 'cma/sso_callback.php');
        return $baseUrl . '/' . $callbackPath;
    }

    /**
     * Check if we should auto-detect base URL instead of using configured value
     * Returns true if configured URL is for a different domain (e.g., local dev vs production)
     */
    private static function shouldAutoDetectBaseUrl(string $configuredUrl): bool
    {
        $currentHost = Request::server('HTTP_HOST', Request::server('SERVER_NAME'));
        if (empty($currentHost)) {
            return false;
        }

        // Parse configured URL to get host
        $parsed = parse_url($configuredUrl);
        $configuredHost = $parsed['host'] ?? '';

        // If hosts don't match, auto-detect (e.g., config says mijn.rino.lc but we're on test-mijn.rino.nl)
        return !empty($configuredHost) && $configuredHost !== $currentHost;
    }

    /**
     * Exchange authorization code voor tokens
     *
     * @param string $code Authorization code van IDP
     * @return array|null Token response of null bij fout
     */
    public static function exchangeCodeForToken(string $code): ?array
    {
        $idpUrl = rtrim(Application::get('sso_idp_url', Application::get('sso_idp_conn', '')), '/');
        $tokenEndpoint = Application::get('sso_idp_tokenendpoint', 'oauth2/token');
        $tokenUrl = $idpUrl . '/' . $tokenEndpoint;

        $clientId = Application::get('sso_client_id', '');
        $clientSecret = Application::get('sso_client_secret', '');
        $redirectUri = self::getCallbackUrl();
        $grantType = Application::get('sso_grand_type', 'authorization_code');

        $postData = [
            'grant_type' => $grantType,
            'code' => $code,
            'redirect_uri' => $redirectUri,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
        ];

        // cURL request naar token endpoint
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $tokenUrl,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($postData),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded',
                'Accept: application/json',
            ],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        if ($error) {
            Logger::error('SSO Token Exchange Error', ['error' => $error]);
            return null;
        }

        if ($httpCode !== 200) {
            Logger::error('SSO Token Exchange HTTP Error', ['httpCode' => $httpCode, 'response' => $response]);
            return null;
        }

        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            Logger::error('SSO Token Exchange JSON Error', ['error' => json_last_error_msg()]);
            return null;
        }

        // Check voor error in response
        if (isset($data['error'])) {
            Logger::error('SSO Token Exchange IDP Error', ['error' => $data['error'], 'description' => $data['error_description'] ?? '']);
            return null;
        }

        return $data;
    }

    /**
     * Parse en valideer ID token (JWT)
     *
     * @param string $idToken JWT id_token van IDP
     * @return array|null Decoded payload of null bij fout
     */
    public static function parseIdToken(string $idToken): ?array
    {
        $parts = explode('.', $idToken);
        if (count($parts) !== 3) {
            Logger::warning('SSO: Invalid JWT format', ['expectedParts' => 3, 'actualParts' => count($parts)]);
            return null;
        }

        // Decode payload (part 1)
        $payload = self::base64UrlDecode($parts[1]);
        if ($payload === false) {
            Logger::warning('SSO: Failed to decode JWT payload');
            return null;
        }

        $data = json_decode($payload, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            Logger::warning('SSO: Invalid JWT payload JSON', ['error' => json_last_error_msg()]);
            return null;
        }

        // Valideer nonce
        $storedNonce = Cookie::get(self::COOKIE_SSO_NONCE, '');
        Cookie::delete(self::COOKIE_SSO_NONCE);

        if (!empty($storedNonce) && isset($data['nonce'])) {
            if (!hash_equals($storedNonce, $data['nonce'])) {
                Logger::warning('SSO: Nonce mismatch');
                return null;
            }
        }

        // Valideer expiration
        if (isset($data['exp']) && $data['exp'] < time()) {
            Logger::warning('SSO: Token expired', ['exp' => $data['exp'], 'now' => time()]);
            return null;
        }

        return $data;
    }

    /**
     * Base64 URL decode (JWT variant)
     */
    private static function base64UrlDecode(string $input): string|false
    {
        $remainder = strlen($input) % 4;
        if ($remainder) {
            $input .= str_repeat('=', 4 - $remainder);
        }
        return base64_decode(strtr($input, '-_', '+/'));
    }

    /**
     * Zoek CMA user op email adres
     *
     * @param string $email Email adres uit SSO token (sub claim)
     * @return array|null User data of null indien niet gevonden
     */
    public static function findCmaUserByEmail(string $email): ?array
    {
        if (empty($email)) {
            return null;
        }

        $conn = Database::getConnection('users');
        if ($conn === null) {
            Logger::error('SSO: Cannot connect to users database');
            return null;
        }

        // Zoek op email (primair) of login (fallback)
        $emailSafe = SQL::postString(strtolower($email));
        $sql = "SELECT * FROM tblUsers WHERE (LCASE(userEMail) = $emailSafe OR LCASE(userLogin) = $emailSafe)";

        $rs = Database::openRS($sql, $conn, adOpenForwardOnly);
        if ($rs === null || $rs->EOF) {
            return null;
        }

        return [
            'id' => $rs->fields['ID'],
            'login' => $rs->fields['userLogin'],
            'fullName' => $rs->fields['userFullName'],
            'email' => $rs->fields['userEMail'],
            'guid' => $rs->fields['userGUID'] ?? '',
            'level' => $rs->fields['userLevel'] ?? ($rs->fields['userAdministrator'] ? 1 : 0),
            'isAdmin' => !empty($rs->fields['userAdministrator']),
            'skipNotify' => !empty($rs->fields['userSkipNotifyOwnRecords']),
        ];
    }

    /**
     * Haal de naam van de SSO provider op voor weergave
     */
    public static function getProviderName(): string
    {
        return Application::get('sso_provider_name', 'SSO login');
    }

    /**
     * Verwijder alle SSO gerelateerde cookies
     */
    public static function clearSsoCookies(): void
    {
        Cookie::delete(self::COOKIE_SSO_STATE);
        Cookie::delete(self::COOKIE_SSO_NONCE);
        Cookie::delete(self::COOKIE_SSO_RETURN_URL);
    }
}
