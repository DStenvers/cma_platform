<?php
/**
 * Tests for PKCE (RFC 7636, S256) in Cma\Services\SsoService
 *
 * Run with: php tests/TestRunner.php SsoPkceTest
 *
 * STB CRM (de nieuwe IdP van mijnRINO) weigert een autorisatie zonder PKCE:
 * "Only code challenge method 'S256' is supported". De authorize-URL moet daarom een
 * code_challenge + code_challenge_method=S256 bevatten, en het token-verzoek de
 * bijbehorende code_verifier (uit een cookie).
 */

require_once __DIR__ . '/TestRunner.php';
require_once __DIR__ . '/../classes/Services/SsoService.php';

use Cma\Services\SsoService;

class SsoPkceTest extends TestCase
{
    public function testChallengeIsBase64UrlSha256OfVerifier(): void
    {
        // Onafhankelijk berekend: printf '%s' <verifier> | openssl dgst -sha256 -binary | base64 | tr '+/' '-_' | tr -d '='
        $this->assertEquals(
            'KIaEMwBXRcbccLuCYa2t2RIgEK8_XHssZTyzdAtcp9M',
            SsoService::codeChallenge('mijnrino-pkce-test-verifier-0123456789-abcdefghijklmnop')
        );
    }

    public function testChallengeHasNoPaddingOrUnsafeCharacters(): void
    {
        $challenge = SsoService::codeChallenge('abc');
        $this->assertEquals(43, strlen($challenge));
        $this->assertEquals(1, preg_match('/^[A-Za-z0-9_-]+$/', $challenge));
    }

    public function testVerifierMeetsRfcLengthAndCharset(): void
    {
        // generateCodeVerifier() zet een cookie; in de CLI-runner zijn de headers al verstuurd.
        $verifier = @SsoService::generateCodeVerifier();
        $this->assertTrue(strlen($verifier) >= 43 && strlen($verifier) <= 128);
        $this->assertEquals(1, preg_match('/^[A-Za-z0-9._~-]+$/', $verifier));
        $this->assertNotEquals($verifier, @SsoService::generateCodeVerifier());
    }

    public function testAuthorizeUrlCarriesS256Challenge(): void
    {
        $src = (string) file_get_contents(__DIR__ . '/../classes/Services/SsoService.php');
        $this->assertTrue(strpos($src, "'code_challenge' => self::codeChallenge(\$codeVerifier)") !== false);
        $this->assertTrue(strpos($src, "'code_challenge_method' => 'S256'") !== false);
    }

    public function testTokenRequestSendsVerifierFromCookie(): void
    {
        $src = (string) file_get_contents(__DIR__ . '/../classes/Services/SsoService.php');
        $this->assertTrue(strpos($src, "Cookie::get(self::COOKIE_SSO_CODE_VERIFIER, '')") !== false);
        $this->assertTrue(strpos($src, "\$postData['code_verifier'] = \$codeVerifier;") !== false);
    }
}
