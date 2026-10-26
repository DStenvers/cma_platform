<?php
/**
 * Tests for the "account niet gevonden" path of the CMA SSO login
 * (Cma\Services\SsoService::identityCandidates / accountNotFoundMessage, cma/sso_callback.php).
 *
 * Gemeld: "Account niet gevonden in CMA beheer. Neem contact op met de administrator." zei niet
 * WELK account de IdP doorgaf en ook niet hoe je het oplost. Daarnaast zocht de callback alleen
 * op sub; is dat bij een IdP geen e-mailadres, dan werd een bestaande gebruiker nooit gevonden.
 *
 * Run with: php tests/TestRunner.php SsoAccountNietGevondenTest
 */

require_once __DIR__ . '/TestRunner.php';
require_once __DIR__ . '/../classes/Services/SsoService.php';

use Cma\Services\SsoService;

class SsoAccountNietGevondenTest extends TestCase
{
    public function testCandidatesInOrderUniqueAndNonEmpty(): void
    {
        $this->assertEquals(['12345', 'a.b@rino.nl'], SsoService::identityCandidates(
            ['sub' => '12345', 'email' => 'a.b@rino.nl', 'preferred_username' => 'A.B@rino.nl', 'upn' => '']
        ));
        $this->assertEquals(['a.b@rino.nl'], SsoService::identityCandidates(['sub' => 'a.b@rino.nl']));
        $this->assertEquals([], SsoService::identityCandidates(['sub' => '', 'email' => ['x']]));
    }

    public function testMessageNamesTheAccountAndTheFix(): void
    {
        $msg = SsoService::accountNotFoundMessage(['a.b@rino.nl']);
        $this->assertTrue(str_contains($msg, 'ingelogd als a.b@rino.nl'), 'noemt het account');
        $this->assertTrue(str_contains($msg, 'bij Gebruikers het e-mailadres van jouw gebruiker op a.b@rino.nl'), 'zegt hoe het op te lossen');
        $this->assertFalse(str_contains($msg, 'Neem contact op met de administrator'), 'niet meer de kale melding');
    }

    public function testMessageWhenIdpSendsNoEmail(): void
    {
        $msg = SsoService::accountNotFoundMessage(['8f2c-77']);
        $this->assertTrue(str_contains($msg, 'stuurde geen e-mailadres mee, alleen 8f2c-77'));
        $this->assertTrue(str_contains($msg, 'claim sub of email'));
    }

    public function testMessageWhenTokenHasNoIdentity(): void
    {
        $this->assertTrue(str_contains(SsoService::accountNotFoundMessage([]), 'gaf geen account door'));
    }

    public function testCallbackTriesEveryCandidateAndUsesTheMessage(): void
    {
        $src = (string) file_get_contents(__DIR__ . '/../sso_callback.php');
        $this->assertTrue(str_contains($src, 'foreach ($candidates as $candidate)'));
        $this->assertTrue(str_contains($src, 'handleError(SsoService::accountNotFoundMessage($candidates)'));
        $this->assertFalse(str_contains($src, 'Account niet gevonden in CMA beheer'));
        $login = (string) file_get_contents(__DIR__ . '/../login.php');
        $this->assertTrue(str_contains($login, 'white-space:pre-line'), 'meerdere regels zichtbaar als regels');
    }
}
