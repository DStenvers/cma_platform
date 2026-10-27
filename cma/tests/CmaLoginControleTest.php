<?php
/**
 * Inloggen wordt op één plek bepaald: SecurityHelper.
 *
 * Gevonden 27-10-2026 vanuit de site mijnRINO: cma/details_getdata.php (de keuzelijstgegevens
 * voor select2) had geen eigen controle, en de poort in bootstrap.inc keek alleen of er een
 * cookie CMAU stond. Met een zelf gezette CMAU=1 kwam je erdoor. Op ruim twintig plekken las
 * code de cookie zelf (Cookie::get(COOKIE_USERID)), dus ook daar telde een kale cookie.
 *
 * Nu:
 *  - de poort in bootstrap.inc vraagt SecurityHelper::isLoggedIn() (CMAU + CMAG tegen de database);
 *  - details_getdata.php controleert dat ook zelf en geeft JSON met 401;
 *  - getCurrentUserId() geeft alleen bij een geldige sessie een ID, anders '';
 *  - de login-cookies worden alleen in SecurityHelper gelezen en geschreven
 *    (startSession, endSession, hasSessionCookie, getOriginalUserId, ...).
 *
 *   php tests/TestRunner.php CmaLoginControleTest
 */

require_once __DIR__ . '/TestRunner.php';
require_once __DIR__ . '/../classes/SecurityHelper.php';

use Cma\SecurityHelper;

class CmaLoginControleTest extends TestCase
{
    private const LOGIN_COOKIES = 'CMAU|CMAG|CMAADM|CMALEVEL|CMAUNAME|CMAU_ORIGINAL';
    private const LOGIN_CONSTANTEN = 'COOKIE_USERID|COOKIE_USERGUID|COOKIE_ADMIN|COOKIE_LEVEL|COOKIE_USERNAME|COOKIE_ORIGINAL_USERID';

    /** Alle PHP-bronbestanden van het platform, zonder tests, vendor en SecurityHelper zelf. */
    private function bronbestanden(): array
    {
        $wortel = realpath(__DIR__ . '/../..');
        $uit = [];
        foreach (['cma', 'src'] as $map) {
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($wortel . '/' . $map, FilesystemIterator::SKIP_DOTS));
            foreach ($it as $f) {
                $pad = str_replace('\\', '/', $f->getPathname());
                if (!preg_match('/\.(php|inc)$/', $pad)) continue;
                if (preg_match('#/(tests|vendor|node_modules)/#', $pad)) continue;
                if (str_ends_with($pad, 'classes/SecurityHelper.php')) continue;
                $uit[] = $pad;
            }
        }
        return $uit;
    }

    public function testNiemandLeestDeLoginCookiesBuitenSecurityHelper(): void
    {
        $patroon = '/(Cookie::(get|has|set|delete)\(\s*([\'"](' . self::LOGIN_COOKIES . ')[\'"]|(\\\\?Cma\\\\)?SecurityHelper::(' . self::LOGIN_CONSTANTEN . ')))'
                 . '|(\$_COOKIE\[\s*([\'"](' . self::LOGIN_COOKIES . ')[\'"]|(\\\\?Cma\\\\)?SecurityHelper::(' . self::LOGIN_CONSTANTEN . ')))/';
        $treffers = [];
        foreach ($this->bronbestanden() as $pad) {
            foreach (file($pad) as $nr => $regel) {
                if (preg_match($patroon, $regel) && !preg_match('#^\s*(\*|//)#', $regel)) {
                    $treffers[] = basename(dirname($pad)) . '/' . basename($pad) . ':' . ($nr + 1);
                }
            }
        }
        $this->assertEquals([], $treffers, 'login-cookies buiten SecurityHelper: ' . implode(', ', $treffers));
    }

    public function testDetailsGetdataControleertZelfDeSessie(): void
    {
        $bron = (string) file_get_contents(__DIR__ . '/../details_getdata.php');
        $controle = strpos($bron, 'if (!SecurityHelper::isLoggedIn())');
        $uitvoer = strpos($bron, "echo '{\"data\":['");
        $this->assertTrue($controle !== false, 'details_getdata.php vraagt SecurityHelper::isLoggedIn()');
        $this->assertTrue($uitvoer !== false && $controle < $uitvoer, 'de controle staat vóór de eerste uitvoer');
        $this->assertTrue(str_contains($bron, 'http_response_code(401)'), 'zonder sessie: 401');
    }

    public function testPoortInBootstrapVraagtIsLoggedIn(): void
    {
        $bron = (string) file_get_contents(__DIR__ . '/../bootstrap.inc');
        $this->assertTrue(str_contains($bron, "if (!\$_skipLoginCheck) {\n    if (!SecurityHelper::isLoggedIn()) {")
            || str_contains($bron, "if (!\$_skipLoginCheck) {\r\n    if (!SecurityHelper::isLoggedIn()) {"),
            'de poort gebruikt isLoggedIn(), geen cookiecontrole');
        $this->assertTrue(str_contains($bron, "stripos(\$_scriptPath, 'details_getdata.php') !== false"),
            'details_getdata.php krijgt bij de poort JSON in plaats van een HTML-doorverwijzing');
    }

    public function testZelfGezetteCookieGeeftGeenGebruikersId(): void
    {
        // Geen database met deze gebruiker en geen CMAG: geen sessie, dus geen ID.
        $_COOKIE[SecurityHelper::COOKIE_USERID] = '999999';
        unset($_COOKIE[SecurityHelper::COOKIE_USERGUID]);
        SecurityHelper::clearUserCache();
        $this->assertTrue(SecurityHelper::hasSessionCookie(), 'de cookie staat er wel');
        $this->assertEquals('', SecurityHelper::getCurrentUserId(), 'maar is geen sessie');
        unset($_COOKIE[SecurityHelper::COOKIE_USERID]);
        SecurityHelper::clearUserCache();
    }
}
