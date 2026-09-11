<?php
/**
 * Guard: inloggen (cma/login.php) en de sessiecontrole (SecurityHelper::getCurrentUserData)
 * mogen alleen de kolommen van tblUsers opvragen die ze zelf gebruiken.
 *
 * De voorkeur- en debugkolommen (prefTheme, userGUID, SkipTips, prefDebugMode, ...)
 * worden toegevoegd door migraties, en die draaien pas NA het inloggen (main.php).
 * Een gebruikersdatabase die uit een andere omgeving is gekopieerd mist die kolommen;
 * stonden ze in de login-query, dan faalde de query ("Te weinig parameters") en was
 * iedereen buitengesloten — zonder dat een migratie ooit nog aan de beurt kwam.
 */

class LoginMinimaleKolommenTest extends TestCase
{
    /** Kolommen die pas na een migratie bestaan en dus nooit in een login-query mogen staan. */
    private const MIGRATIE_KOLOMMEN = [
        'prefTheme', 'prefMenuStyle', 'prefPopupStyle', 'userGUID',
        'prefSqlLogging', 'prefDebugMode', 'prefDebugOverlay', 'SkipTips',
    ];

    /** @return string[] alle SELECT-kolomlijsten op tblUsers in een bestand */
    private function tblUsersSelects(string $file): array
    {
        $src = file_get_contents($file);
        preg_match_all('/select\s+([^\n\x27"]+?)\s+from\s+tblUsers\b/i', $src, $m);
        return $m[1];
    }

    public function testLoginVraagtAlleenBasiskolommenOp(): void
    {
        $selects = $this->tblUsersSelects(__DIR__ . '/../login.php');
        $this->assertTrue(count($selects) >= 3, 'login.php hoort minstens drie SELECTs op tblUsers te hebben');
        foreach ($selects as $kolommen) {
            foreach (self::MIGRATIE_KOLOMMEN as $kolom) {
                $this->assertFalse(
                    stripos($kolommen, $kolom) !== false,
                    "login.php vraagt migratiekolom $kolom op in: select $kolommen from tblUsers"
                );
            }
        }
    }

    public function testSessiecontroleVraagtAlleenBasiskolommenOp(): void
    {
        $selects = $this->tblUsersSelects(__DIR__ . '/../classes/SecurityHelper.php');
        $this->assertTrue(count($selects) >= 1);
        foreach ($selects as $kolommen) {
            // getUserGuid() mag userGUID lezen: die vangt een ontbrekende kolom zelf af.
            if (trim($kolommen) === 'userGUID') {
                continue;
            }
            foreach (self::MIGRATIE_KOLOMMEN as $kolom) {
                $this->assertFalse(
                    stripos($kolommen, $kolom) !== false,
                    "SecurityHelper.php vraagt migratiekolom $kolom op in: select $kolommen from tblUsers"
                );
            }
        }
    }

    public function testFoutmeldingToontDriverfoutEnVerbinding(): void
    {
        $src = file_get_contents(__DIR__ . '/../login.php');
        $this->assertTrue(strpos($src, 'Database::getLastError()') !== false, 'de echte driverfout hoort in de melding');
        $this->assertTrue(strpos($src, "Database::getDsn('users')") !== false, 'de geopende verbinding hoort in de melding');
    }
}
