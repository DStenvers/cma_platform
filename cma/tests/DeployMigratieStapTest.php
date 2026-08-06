<?php
/**
 * DeployMigratieStapTest.php — de deployer past migraties toe, op de goede plek.
 *
 * De volgorde is het hele punt en is niet uit een draaiende deploy te toetsen:
 *
 *   composer update  →  test-gate  →  MIGRATIES  →  recycle
 *
 * Ná composer, want de nieuwe migratiescripts moeten op schijf staan. Vóór de
 * recycle, want anders schakelt de app-pool om naar code die een schema
 * verwacht dat er nog niet is. En mislukt de migratie, dan moet de deploy
 * falen — een groene deploy op een half gemigreerde database is precies wat
 * niemand merkt.
 *
 * Twee stille-mislukking-vallen liggen eronder, allebei hier vastgelegd:
 *   1. Het onderhoudsscherm in _bootstrap.php stopt élk verzoek met een 503 +
 *      exit. Draait de loper binnen het onderhoudsvenster van de deploy (en dat
 *      is de bedoeling), dan moet die poort de CLI doorlaten.
 *   2. Zo'n afbreking eindigt met exitcode 0. De loper heeft een wachter die er
 *      een 1 van maakt, anders leest de deployer het als "migraties gedaan".
 *
 *   php tests/TestRunner.php DeployMigratieStapTest
 */

require_once __DIR__ . '/TestRunner.php';

class DeployMigratieStapTest extends TestCase
{
    private function deployBron(): string
    {
        $pad = __DIR__ . '/../../templates/deploy.php.template';
        $this->assertTrue(is_file($pad), 'deploy.php.template niet gevonden');
        return (string) file_get_contents($pad);
    }

    private function loperBron(): string
    {
        $pad = __DIR__ . '/../migrate.php';
        $this->assertTrue(is_file($pad), 'cma/migrate.php niet gevonden');
        return (string) file_get_contents($pad);
    }

    private function positie(string $bron, string $naald, string $wat): int
    {
        $pos = strpos($bron, $naald);
        $this->assertTrue($pos !== false, "$wat niet gevonden in de bron — pas deze test aan");
        return (int) $pos;
    }

    public function testDeMigratieStapStaatNaComposerEnVoorDeRecycle(): void
    {
        $bron = $this->deployBron();

        $composer = $this->positie($bron, "\$composerPkgs = \$envRead('DEPLOY_COMPOSER_UPDATE'", 'de composer-stap');
        $testgate = $this->positie($bron, "\$runTests = \$envRead('DEPLOY_RUN_TESTS'", 'de test-gate');
        $migratie = $this->positie($bron, "\$migrate = \$envRead('DEPLOY_MIGRATE'", 'de migratiestap');
        $recycle  = $this->positie($bron, '// 4f. Recycle on success', 'de recycle-stap');

        $this->assertTrue($composer < $migratie, 'migraties draaien vóór composer — dan ontbreken de nieuwe scripts');
        $this->assertTrue($testgate < $migratie, 'migraties draaien vóór de test-gate — dan muteert een falende build de database');
        $this->assertTrue($migratie < $recycle, 'migraties draaien ná de recycle — dan serveert de pool nieuwe code op een oud schema');
    }

    public function testEenMislukteMigratieLaatDeDeployFalen(): void
    {
        $bron = $this->deployBron();
        $vanaf = $this->positie($bron, "\$migrate = \$envRead('DEPLOY_MIGRATE'", 'de migratiestap');
        $blok = substr($bron, $vanaf, 2200);

        $this->assertTrue(
            str_contains($blok, '$failed     = true;'),
            'de migratiestap zet $failed niet — een mislukte migratie zou de deploy groen laten'
        );
        $this->assertTrue(
            str_contains($blok, 'cma/migrate.php'),
            'de migratiestap roept cma/migrate.php niet aan'
        );
    }

    public function testGeenPhpBinaryIsEenFoutGeenStilleOverslag(): void
    {
        $bron = $this->deployBron();
        $vanaf = $this->positie($bron, "\$php = null;", 'de php-binary-probe');
        $blok = substr($bron, $vanaf, 1600);

        $this->assertTrue(
            str_contains($blok, "no php binary found"),
            'zonder php-binary wordt er niets gemeld; dan slaat de deploy de migraties stil over'
        );
    }

    public function testDeStapIsUitTeZettenMaarStaatStandaardAan(): void
    {
        $bron = $this->deployBron();
        $this->assertTrue(
            str_contains($bron, "\$envRead('DEPLOY_MIGRATE', '1')"),
            'DEPLOY_MIGRATE heeft niet 1 als default — dan draait een site zonder .env-regel geen migraties'
        );
        $this->assertTrue(
            str_contains($bron, "\$migrate !== '0' && \$migrate !== '-'"),
            'DEPLOY_MIGRATE=0 zet de stap niet uit'
        );
    }

    public function testDeLoperWeigertBuitenDeOpdrachtregel(): void
    {
        $bron = $this->loperBron();
        $guard = strpos($bron, "PHP_SAPI !== 'cli'");
        $bootstrap = strpos($bron, "require_once __DIR__ . '/bootstrap.inc'");

        $this->assertTrue($guard !== false, 'de CLI-guard is weg — dan is dit een ongeauthenticeerd schrijf-endpoint');
        $this->assertTrue($bootstrap !== false, 'de bootstrap-require is weg');
        $this->assertTrue($guard < $bootstrap, 'de guard staat ná de bootstrap — dan draait een webverzoek al halve app');
    }

    public function testDeLoperPasseertHetOnderhoudsschermEnMerktEenAfgebrokenBoot(): void
    {
        $bron = $this->loperBron();
        $uri = $this->positie($bron, "\$_SERVER['REQUEST_URI'] ??= '/cma/migrate.php'", 'de REQUEST_URI-shim');
        $wachter = $this->positie($bron, 'register_shutdown_function', 'de shutdown-wachter');
        $bootstrap = $this->positie($bron, "require_once __DIR__ . '/bootstrap.inc'", 'de bootstrap-require');

        $this->assertTrue($uri < $bootstrap, 'de shim staat ná de bootstrap — te laat, de onderhoudspoort heeft dan al afgebroken');
        $this->assertTrue($wachter < $bootstrap, 'de wachter staat ná de bootstrap — een afbreking blijft dan exit 0');
        $this->assertTrue(str_contains($bron, 'exit(1)'), 'de wachter maakt er geen mislukking van');
    }

    public function testHetOnderhoudsschermLaatDeOpdrachtregelDoor(): void
    {
        $pad = __DIR__ . '/../../templates/_bootstrap.php.template';
        $this->assertTrue(is_file($pad), '_bootstrap.php.template niet gevonden');
        $bron = (string) file_get_contents($pad);

        $poort = $this->positie($bron, "\$flag = __DIR__ . '/maintenance.flag'", 'de onderhoudspoort');
        $cli = strpos($bron, "PHP_SAPI === 'cli'");

        $this->assertTrue($cli !== false, 'de CLI-uitzondering ontbreekt — dan blokkeert het onderhoudsscherm de migratieloper');
        $this->assertTrue($cli < $poort, 'de CLI-uitzondering staat ná het lezen van de vlag');
    }
}
