<?php
/**
 * Tests for forced SSO in the CMA (CMA_SSO_FORCE): cma/login.php, cma/logout.php,
 * cma/sso_login.php, cma/sso_callback.php and Cma\Services\SsoService.
 *
 * Gevraagd: "force the use of SSO in the cma, all users are internal" — los van de front-end
 * ("let it be optional in front-end ... CMA_SSO_FORCE?"). Na uitloggen moet de IdP opnieuw om
 * het wachtwoord vragen, anders logt de lopende IdP-sessie je meteen weer in.
 *
 * Run with: php tests/TestRunner.php CmaSsoForceTest
 */

require_once __DIR__ . '/TestRunner.php';
require_once __DIR__ . '/../classes/Services/SsoService.php';

use Cma\Services\SsoService;

class CmaSsoForceTest extends TestCase
{
    private function src(string $file): string
    {
        return (string) file_get_contents(__DIR__ . '/../' . $file);
    }

    public function testSettingHasItsOwnCmaEnvName(): void
    {
        $settings = (string) file_get_contents(__DIR__ . '/../../src/helpers/Settings.php');
        $this->assertTrue(str_contains($settings, "'sso_force' => ['env' => 'CMA_SSO_FORCE'"), 'eigen naam, los van FORCE_SSO van de front-end');
    }

    public function testLoginRedirectsToIdpAndIgnoresPasswordPost(): void
    {
        $src = $this->src('login.php');
        $this->assertTrue(str_contains($src, '$bSsoForced = SsoService::isEnabled() && SsoService::isForced();'));
        $this->assertTrue(str_contains($src, "if (\$bSsoForced && \$intUserID == '' && Request::query('sso_error', '') === '') {"), 'niet ingelogd -> IdP, behalve bij een te tonen fout');
        $this->assertTrue(str_contains($src, "header('Location: ' . SsoService::forcedLoginUrl());"));
        $this->assertTrue(str_contains($src, "if (Request::post('naam', '') != '' && !\$bSsoForced) {"), 'geposte naam/wachtwoord genegeerd');
        $this->assertTrue(str_contains($src, "if (\$bSsoForced) {\n                // Alleen de foutmelding en de SSO-knop"), 'geen wachtwoordformulier');
    }

    public function testLogoutMarksAndCallbackClears(): void
    {
        $this->assertTrue(str_contains($this->src('logout.php'), "Cookie::set(SsoService::COOKIE_SSO_LOGGED_OUT, '1');"));
        $this->assertTrue(str_contains($this->src('sso_callback.php'), 'Cookie::delete(SsoService::COOKIE_SSO_LOGGED_OUT);'));
        $this->assertTrue(str_contains($this->src('classes/Services/SsoService.php'), "'sso_login.php' . (Cookie::get(self::COOKIE_SSO_LOGGED_OUT, '') !== '' ? '?prompt=login' : '')"), 'na uitloggen prompt=login');
    }

    public function testPromptOnlyLoginFromOutsideAndEmptyIsOmitted(): void
    {
        $this->assertTrue(str_contains($this->src('sso_login.php'), "strtolower((string) Request::query('prompt', '')) === 'login' ? 'login' : ''"));
        $svc = $this->src('classes/Services/SsoService.php');
        $this->assertTrue(str_contains($svc, "if (\$prompt !== '') {\n            \$params['prompt'] = \$prompt;"), 'lege prompt weggelaten');
        $this->assertFalse(str_contains($svc, "'prompt' => \$prompt,"), 'niet meer altijd in de parameters');
    }

    public function testLoginRequiredFallsBackToInteractiveLogin(): void
    {
        $this->assertTrue(SsoService::needsInteractiveLogin('login_required'));
        $this->assertTrue(SsoService::needsInteractiveLogin('interaction_required'));
        $this->assertFalse(SsoService::needsInteractiveLogin('access_denied'));
        $this->assertTrue(str_contains($this->src('sso_callback.php'), "header('Location: sso_login.php?prompt=login');"));
    }
}
