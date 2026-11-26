<?php
/**
 * Tests voor inloggen in het CMA met een e-mailcode als de SSO down is:
 * Cma\Services\LoginCodeService, SsoService::isDown() en cma/login.php.
 *
 * Run with: php tests/TestRunner.php LoginCodeServiceTest
 */

require_once __DIR__ . '/TestRunner.php';
require_once __DIR__ . '/../classes/Services/LoginCodeService.php';

use Cma\Services\LoginCodeService;

class LoginCodeServiceTest extends TestCase
{
    private function src(string $file): string
    {
        return (string) file_get_contents(__DIR__ . '/../' . $file);
    }

    private function rij(string $code, int $verstuurdOver = 60, array $extra = []): array
    {
        $nu = 1000000;
        return $extra + [
            'ID' => 7, 'userGUID' => '{ABC-123}',
            'login_code_hash' => LoginCodeService::hash($code, '{ABC-123}'),
            'login_code_verstuurd' => $nu - $verstuurdOver,
            'login_code_gebruikt' => 0, 'login_code_fout' => 0,
        ];
    }

    public function testCodeIsZesCijfers(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $this->assertTrue(preg_match('/^\d{6}$/', LoginCodeService::maakCode()) === 1);
        }
    }

    public function testJuisteCodeIsOk(): void
    {
        $this->assertSame('ok', LoginCodeService::beoordeel($this->rij('123456'), '123456', 1000000));
        $this->assertSame('ok', LoginCodeService::beoordeel($this->rij('123456'), '123 456', 1000000), 'spaties in de code mogen');
    }

    public function testOnjuisteLegeEnOntbrekendeCode(): void
    {
        $this->assertSame('onjuist', LoginCodeService::beoordeel($this->rij('123456'), '654321', 1000000));
        $this->assertSame('onjuist', LoginCodeService::beoordeel($this->rij('123456'), '', 1000000));
        $this->assertSame('geen_code', LoginCodeService::beoordeel(['login_code_hash' => ''], '123456', 1000000));
    }

    public function testVerlopenOpgebruiktEnGeblokkeerd(): void
    {
        $this->assertSame('verlopen', LoginCodeService::beoordeel($this->rij('123456', LoginCodeService::GELDIG_MIN * 60 + 1), '123456', 1000000));
        $this->assertSame('opgebruikt', LoginCodeService::beoordeel($this->rij('123456', 60, ['login_code_gebruikt' => LoginCodeService::MAX_GEBRUIK]), '123456', 1000000));
        $this->assertSame('geblokkeerd', LoginCodeService::beoordeel($this->rij('123456', 60, ['login_code_fout' => LoginCodeService::MAX_FOUT]), '123456', 1000000), 'ook de juiste code werkt niet meer na te veel foute pogingen');
    }

    public function testZoutIsGuidOfAndersHetId(): void
    {
        $zonder = ['ID' => 9, 'userGUID' => '', 'login_code_hash' => LoginCodeService::hash('111111', 'u9'),
                   'login_code_verstuurd' => 1000000 - 5, 'login_code_gebruikt' => 0, 'login_code_fout' => 0];
        $this->assertSame('ok', LoginCodeService::beoordeel($zonder, '111111', 1000000));
        $this->assertTrue(LoginCodeService::hash('111111', '{AA}') !== LoginCodeService::hash('111111', '{BB}'), 'zelfde code, andere gebruiker, andere hash');
    }

    public function testLoginCodeRegelsAanvraagGeeftNietsPrijs(): void
    {
        $svc = $this->src('classes/Services/LoginCodeService.php');
        $this->assertTrue(str_contains($svc, 'time() - $vorig < self::HERZEND_SEC'), 'herhaling binnen een minuut wordt genegeerd');
        $this->assertTrue(str_contains($svc, 'login_code_gebruikt<?'), 'voorwaardelijk ophogen tegen gelijktijdig gebruik');
        $this->assertTrue(str_contains($svc, "'users'"), 'tblUsers staat in de users-database, niet in data');
    }

    public function testSsoDownLeestAlleenDeVlag(): void
    {
        $svc = $this->src('classes/Services/SsoService.php');
        $this->assertTrue(str_contains($svc, 'public static function isDown(): bool'));
        $this->assertTrue(str_contains($svc, "'sso_status.json'"), 'zelfde vlagbestand als de site-taak');
        $this->assertTrue(str_contains($svc, "!empty(\$vlag['down'])"));
        $this->assertTrue(str_contains($svc, "strtoupper((string) Application::get('omgeving', '')) !== 'P'"), '?sso_down=1 nooit op productie');
    }

    public function testLoginPaginaToontCodeformulierInPlaatsVanSsoKnop(): void
    {
        $src = $this->src('login.php');
        $this->assertTrue(str_contains($src, '$bSsoDown = SsoService::isDown();'));
        $this->assertTrue(str_contains($src, 'Single Sign-On is niet actief.'));
        $this->assertTrue(str_contains($src, "if (\$bSsoDown) {\n                // Controls horen"), 'bij down geen SSO-knop');
        $this->assertTrue(str_contains($src, '} elseif (SsoService::isEnabled()) {'), 'SSO-knop alleen als de SSO niet down is');
        $this->assertTrue(str_contains($src, "LoginCodeService::aanvragen(\$strCodeEmail);"));
        $this->assertTrue(str_contains($src, 'LoginCodeService::login($rijCode);'));
        $this->assertTrue(str_contains($src, 'Als dit e-mailadres bij een gebruiker hoort'), 'geeft niet prijs of het adres bestaat');
        $this->assertTrue(str_contains($src, "event.stopPropagation()"), 'Enter in de codevelden verstuurt niet het wachtwoordformulier');
    }

    public function testMigratieVoegtDeVierKolommenToe(): void
    {
        $mig = $this->src('migrations/9.26.0_users_login_code.php');
        foreach (['login_code_hash', 'login_code_verstuurd', 'login_code_gebruikt', 'login_code_fout'] as $kolom) {
            $this->assertTrue(str_contains($mig, "'$kolom'"), $kolom);
        }
        $this->assertTrue(str_contains($mig, "'tblUsers'"));
    }
}
