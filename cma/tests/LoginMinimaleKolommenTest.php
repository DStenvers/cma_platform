<?php
/**
 * Inloggen (cma/login.php) en de sessiecontrole (SecurityHelper::getCurrentUserData)
 * vragen alleen kolommen van tblUsers op die (a) ze zelf gebruiken en (b) de database
 * echt heeft.
 *
 * De voorkeur- en debugkolommen (prefTheme, userGUID, SkipTips, prefDebugMode, ...)
 * worden toegevoegd door migraties, en die draaien pas NA het inloggen (main.php).
 * Een gebruikersdatabase die uit een andere omgeving is gekopieerd mist die kolommen
 * (en soms zelfs userIPAddresses); Access laat dan de hele query falen met "te weinig
 * parameters" en iedereen stond buiten — zonder dat een migratie nog aan de beurt kwam.
 */

require_once __DIR__ . '/TestRunner.php';
require_once __DIR__ . '/../classes/SecurityHelper.php';

use Cma\SecurityHelper;

class LoginMinimaleKolommenTest extends TestCase
{
    /** Kolommen die pas na een migratie bestaan en dus nooit hard in een login-query mogen staan. */
    private const MIGRATIE_KOLOMMEN = [
        'prefTheme', 'prefMenuStyle', 'prefPopupStyle', 'userGUID',
        'prefSqlLogging', 'prefDebugMode', 'prefDebugOverlay', 'SkipTips',
    ];

    /** @return string[] regels met een SELECT op tblUsers */
    private function tblUsersSelectRegels(string $file): array
    {
        $regels = [];
        foreach (file($file) as $nr => $regel) {
            if (preg_match('/select\b.*\bfrom\s+tblUsers\b/i', $regel)) {
                $regels[$nr + 1] = $regel;
            }
        }
        return $regels;
    }

    public function testLoginVraagtGeenMigratiekolommenOp(): void
    {
        $regels = $this->tblUsersSelectRegels(__DIR__ . '/../login.php');
        $this->assertTrue(count($regels) >= 3, 'login.php hoort minstens drie SELECTs op tblUsers te hebben');
        foreach ($regels as $nr => $regel) {
            foreach (self::MIGRATIE_KOLOMMEN as $kolom) {
                $this->assertFalse(stripos($regel, $kolom) !== false, "login.php:$nr vraagt migratiekolom $kolom op");
            }
        }
    }

    public function testLoginVraagtAlleenBestaandeKolommenOp(): void
    {
        $src = file_get_contents(__DIR__ . '/../login.php');
        $this->assertStringContainsString('SecurityHelper::tblUsersKolommen($dbconn', $src, 'de login-query hoort via tblUsersKolommen() te lopen');
        $this->assertStringContainsString("\$rs->fields['userIPAddresses'] ?? ''", $src, 'userIPAddresses mag ontbreken en wordt dan als leeg gelezen');
    }

    public function testSessiecontroleVraagtAlleenBestaandeBasiskolommenOp(): void
    {
        $file = __DIR__ . '/../classes/SecurityHelper.php';
        $src = file_get_contents($file);
        $this->assertStringContainsString("self::tblUsersKolommen(\$conn, ['ID', 'userLogin', 'userFullName', 'userEMail', 'userSkipNotifyOwnRecords', 'userLevel'])", $src);
        foreach ($this->tblUsersSelectRegels($file) as $nr => $regel) {
            // getUserGuid() mag userGUID lezen: die vangt een ontbrekende kolom zelf af.
            if (preg_match('/SELECT userGUID FROM tblUsers/', $regel)) {
                continue;
            }
            foreach (self::MIGRATIE_KOLOMMEN as $kolom) {
                $this->assertFalse(stripos($regel, $kolom) !== false, "SecurityHelper.php:$nr vraagt migratiekolom $kolom op");
            }
        }
    }

    public function testKiesBestaandeKolommenLaatOntbrekendeWeg(): void
    {
        $bestaand = ['ID', 'userLogin', 'userPassword', 'userFullName'];
        $gewenst = ['ID', 'userLogin', 'userPassword', 'userIPAddresses'];
        $this->assertEquals(['ID', 'userLogin', 'userPassword'], SecurityHelper::kiesBestaandeKolommen($bestaand, $gewenst));
    }

    public function testKiesBestaandeKolommenIsHoofdletterongevoeligEnBehoudtVolgorde(): void
    {
        // Access geeft namen terug zoals aangemaakt; een kopie kan afwijken in hoofdletters.
        $bestaand = ['id', 'USERLOGIN', 'userpassword', 'useripaddresses'];
        $gewenst = ['ID', 'userLogin', 'userPassword', 'userIPAddresses'];
        $this->assertEquals($gewenst, SecurityHelper::kiesBestaandeKolommen($bestaand, $gewenst));
    }

    public function testKiesBestaandeKolommenMetLegeTabelGeeftNiets(): void
    {
        $this->assertEquals([], SecurityHelper::kiesBestaandeKolommen([], ['ID', 'userLogin']));
    }

    public function testFoutmeldingToontDriverfoutVerbindingEnKolommen(): void
    {
        $src = file_get_contents(__DIR__ . '/../login.php');
        $this->assertStringContainsString('Database::getLastError()', $src, 'de echte driverfout hoort in de melding');
        $this->assertStringContainsString("Database::getDsn('users')", $src, 'de geopende verbinding hoort in de melding');
        $this->assertStringContainsString('Aanwezige kolommen: ', $src);
        $this->assertStringContainsString('Ontbrekende kolommen: ', $src);
    }
}
