<?php
/**
 * BootstrapAppStartedVlagTest.php — de "applicatie gestart"-vlag leegt de cache niet bij
 * elke request.
 *
 * Bootstrap::init() bewaart na Application_OnStart() een vlag. Met APCu kwam die alleen in
 * APCu en werd .app_started nooit aangemaakt; de reset-controle ("APCu zegt gestart, maar
 * het bestand is weg → Cache::clear()") ging daardoor bij ELKE request af op T/A/P: de hele
 * cache leeg en Application_OnStart() telkens opnieuw. Nu wordt het bestand altijd
 * aangemaakt en zegt de APCu-vlag of dat lukte ('file') of niet ('apcu'); alleen dan telt
 * een ontbrekend bestand als reset.
 *
 * Broncontrole: init() heeft een volledige site nodig (app.php, global.asa, sessie). De
 * site mijnRINO toetst hetzelfde blok in zijn _bootstrap.php ook op gedrag
 * (.tests/AppStartedVlagTest.php, met een nep-APCu over vijf requests).
 *
 *   php tests/TestRunner.php BootstrapAppStartedVlagTest
 */

require_once __DIR__ . '/TestRunner.php';

class BootstrapAppStartedVlagTest extends TestCase
{
    private function bron(): string
    {
        $pad = __DIR__ . '/../../src/helpers/Bootstrap.php';
        $this->assertTrue(is_file($pad), 'Bootstrap.php niet gevonden');
        return (string) file_get_contents($pad);
    }

    public function testBestandWordtOokMetApcuAangemaakt(): void
    {
        $bron = $this->bron();
        // touch() mag niet meer in de else-tak van function_exists('apcu_store') staan.
        $this->assertTrue(
            (bool) preg_match('/\$flagWritten\s*=\s*@touch\(self::\$rootDir \. \'\/\.app_started\'\);\s*if \(function_exists\(\'apcu_store\'\)\)/', $bron),
            '.app_started wordt vóór (en los van) apcu_store aangemaakt'
        );
        $this->assertFalse(
            (bool) preg_match("/apcu_store\('application_started',\s*true/", $bron),
            "de vlag is geen kale true meer (dan weet de controle niet of het bestand bestaat)"
        );
        $this->assertTrue(
            str_contains($bron, "apcu_store('application_started', \$flagWritten ? 'file' : 'apcu', 0)"),
            "de vlag zegt 'file' of 'apcu'"
        );
    }

    public function testResetAlleenAlsHetBestandErWasGeweest(): void
    {
        $this->assertTrue(
            str_contains($this->bron(), "if (\$appStarted && \$appStarted !== 'apcu' && !\$fileCacheExists) {"),
            "een ontbrekend bestand leegt de cache alleen als de vlag niet 'apcu' is"
        );
    }
}
