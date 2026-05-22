<?php
/**
 * Platform — Google OAuth 2.0 / OIDC sign-in helper.
 *
 * ============================================================
 *   SETUP — DO THIS ONCE PER ENVIRONMENT (dev / acc / prod)
 * ============================================================
 *
 * 1. https://console.cloud.google.com/ → create / pick a project.
 *
 * 2. APIs & Services → OAuth consent screen.
 *       User type: External
 *       Scopes:    userinfo.email, userinfo.profile, openid
 *       Test users: add your own gmail while the app is in Testing.
 *
 * 3. APIs & Services → Credentials → Create credentials →
 *    OAuth client ID, Application type: Web application.
 *    Authorized redirect URIs (one line per environment, exact match
 *    against the path GoogleOAuth::REDIRECT_PATH below):
 *        https://<host>/auth/google/callback
 *        http://localhost:<port>/auth/google/callback
 *
 * 4. Paste credentials into the consuming project's `.env.local`:
 *        GOOGLE_CLIENT_ID=...
 *        GOOGLE_CLIENT_SECRET=GOCSPX-...
 *    Both env vars must be non-empty; ::isConfigured() returns false
 *    when either is missing and the consuming project should hide
 *    the login button entirely.
 *
 * 5. Reload — the consuming project's login UI activates the
 *    Google button when ::isConfigured() returns true.
 *
 * ============================================================
 *
 * Notes & gotchas
 * ---------------
 *  - OpenID Connect (scope=openid email profile).  Google returns a
 *    signed id_token alongside the access_token; ::decodeIdToken()
 *    pulls the claims WITHOUT verifying the RS256 signature — safe
 *    because the token came over HTTPS in the response body of a
 *    server-to-server call authenticated with our client_secret.  A
 *    switch to an implicit / PKCE-only flow MUST add signature
 *    verification (firebase/php-jwt or equivalent).
 *
 *  - `state` is a CSRF token tied to the consumer's session.  The
 *    callback handler in the consuming project rejects mismatches.
 *
 *  - Account linking by email: we trust `email_verified` on @gmail
 *    and on Google-Workspace-hosted domains (unless the org admin
 *    disabled verification).  Consuming projects MUST reject sign-
 *    ins where `email_verified` is false so a third party can't
 *    impersonate someone who signed up with email+password before
 *    they linked their Google account.
 *
 *  - TLS CA fallback: Windows PHP boxes that ship without
 *    `curl.cainfo` set can't verify Google's TLS chain.  We retry
 *    with peer verification off in non-prod environments (env != P)
 *    and log a warning so prod doesn't silently ride on the bypass.
 *    Configure `curl.cainfo` in php.ini before deploying.
 */
declare(strict_types=1);

namespace App\Library;

final class GoogleOAuth
{
    public const REDIRECT_PATH = '/auth/google/callback';

    private const AUTHORIZE_URL = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN_URL     = 'https://oauth2.googleapis.com/token';

    public static function isConfigured(): bool
    {
        return self::clientId() !== '' && self::clientSecret() !== '';
    }

    public static function clientId(): string
    {
        return trim((string)($_ENV['GOOGLE_CLIENT_ID'] ?? ''));
    }

    public static function clientSecret(): string
    {
        return trim((string)($_ENV['GOOGLE_CLIENT_SECRET'] ?? ''));
    }

    /** Build the redirect URI from the current request — must match
     *  what's registered in Google Cloud Console byte-for-byte. */
    public static function redirectUri(): string
    {
        // IIS sets $_SERVER['HTTPS']='off' on plain HTTP; the !empty
        // check on its own would mark every request as HTTPS.
        $https  = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        $scheme = $https ? 'https' : 'http';
        $host   = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
        return $scheme . '://' . $host . self::REDIRECT_PATH;
    }

    /** Authorize URL with all params populated.  Caller redirects to it. */
    public static function authorizeUrl(string $state): string
    {
        $params = [
            'client_id'     => self::clientId(),
            'redirect_uri'  => self::redirectUri(),
            'response_type' => 'code',
            'scope'         => 'openid email profile',
            'state'         => $state,
            // Forces the chooser even if the cook is already signed in
            // to one Google account — handy when devving / testing.
            'prompt'        => 'select_account',
            'access_type'   => 'online',
        ];
        return self::AUTHORIZE_URL . '?' . http_build_query($params);
    }

    /**
     * Exchange an authorization code for tokens.  Returns the decoded
     * JSON response from Google, or null on any error (network, 4xx,
     * malformed).
     *
     * @return array{access_token?:string, id_token?:string, expires_in?:int, token_type?:string}|null
     */
    public static function exchangeCode(string $code): ?array
    {
        $body = http_build_query([
            'code'          => $code,
            'client_id'     => self::clientId(),
            'client_secret' => self::clientSecret(),
            'redirect_uri'  => self::redirectUri(),
            'grant_type'    => 'authorization_code',
        ]);

        $result = self::doTokenPost($body, true);
        $isCaError = stripos($result['err'], 'unable to get local issuer certificate') !== false
                  || stripos($result['err'], 'CAfile')                                 !== false;
        if ($isCaError && self::isNonProd()) {
            error_log('[GoogleOAuth] TLS CA store missing — retrying without peer verification (dev only). '
                    . 'Configure curl.cainfo in php.ini before deploying.');
            $result = self::doTokenPost($body, false);
        }

        $resp = $result['body'];
        $http = $result['http'];
        $err  = $result['err'];
        if ($resp === null || $http >= 400) {
            error_log('[GoogleOAuth::exchangeCode] http=' . $http . ' err=' . $err . ' resp=' . substr((string)$resp, 0, 500));
            return null;
        }
        $data = json_decode((string)$resp, true);
        return is_array($data) ? $data : null;
    }

    /** @return array{body:?string,http:int,err:string} */
    private static function doTokenPost(string $body, bool $verifyPeer): array
    {
        // curl_close() is deprecated in PHP 8.5; the handle is freed
        // automatically when it goes out of scope.
        $ch = curl_init(self::TOKEN_URL);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_SSL_VERIFYPEER => $verifyPeer,
            CURLOPT_SSL_VERIFYHOST => $verifyPeer ? 2 : 0,
        ]);
        $resp = curl_exec($ch);
        $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = (string)curl_error($ch);
        return [
            'body' => $resp === false ? null : (string)$resp,
            'http' => $http,
            'err'  => $err,
        ];
    }

    private static function isNonProd(): bool
    {
        $env = strtoupper((string)($_ENV['APP_ENVIRONMENT'] ?? ''));
        return $env !== 'P';
    }

    /**
     * Decode the id_token (a JWT) and return its claims.  Signature
     * NOT verified — see class header for the rationale.  Use only
     * when the token came from a direct server-to-server token
     * exchange call.
     *
     * @return array<string,mixed>|null
     */
    public static function decodeIdToken(string $idToken): ?array
    {
        $parts = explode('.', $idToken);
        if (count($parts) !== 3) { return null; }
        $payload = strtr($parts[1], '-_', '+/');
        $pad = strlen($payload) % 4;
        if ($pad > 0) { $payload .= str_repeat('=', 4 - $pad); }
        $json = base64_decode($payload, true);
        if ($json === false) { return null; }
        $claims = json_decode($json, true);
        return is_array($claims) ? $claims : null;
    }
}
