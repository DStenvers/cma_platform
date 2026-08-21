<?php
/**
 * DeployComposerLockTest — composer.lock mag de volgende pull niet blokkeren.
 *
 * Draaien met: php cma/tests/TestRunner.php DeployComposerLockTest
 *
 * DE FOUT. De deploy doet `git pull` en daarna `composer update`. Die update HERSCHRIJFT
 * composer.lock op de server — bij een dev-branch verandert de referentie zelfs elke keer.
 * De volgende pull vindt dan een gewijzigd bestand en stopt:
 *
 *     error: Your local changes to the following files would be overwritten by merge:
 *             composer.lock
 *     Aborting
 *
 * Daarmee staat niet alleen de deploy stil (hij komt niet eens bij composer), maar ook een
 * handmatige `git pull` op die server. Gemeld op test-mijn.rino.nl: "every time i try to
 * update the site".
 *
 * TWEE DINGEN LOSSEN HET OP, en ze staan allebei in de template:
 *
 *   1. De pre-pull reset haalt composer.lock uit HEAD terug — net als de andere bestanden die
 *      zowel in git staan als door composer of de Installer worden herschreven: composer.json,
 *      deploy.php, deploy_status.php en cma/package.json (ROOT_SYNCED_FILES). `git checkout -- .` alleen
 *      volstaat niet: dat herstelt uit de INDEX, dus een bestand dat ook al ge-add was
 *      blijft afwijken. Wat er wordt teruggezet gaat naar de log, zodat weggooien zichtbaar
 *      blijft.
 *   2. DEPLOY_COMPOSER_UPDATE=install draait `composer install` in plaats van update.
 *      vendor/ volgt dan exact de meegecommitte lock en de server schrijft die lock niet
 *      meer - dan ontstaat het verschil sowieso niet.
 *
 * Statisch: leest de template, draait geen deploy.
 */

require_once __DIR__ . '/TestRunner.php';

class DeployComposerLockTest extends TestCase
{
    private function bron(): string
    {
        $pad = __DIR__ . '/../../templates/deploy.php.template';
        $this->assertTrue(is_file($pad), 'deploy.php.template niet gevonden');
        return (string) file_get_contents($pad);
    }

    public function testDeLockGaatVoorDePullTerugNaarHead(): void
    {
        $bron = $this->bron();
        $this->assertTrue(strpos($bron, "git checkout HEAD -- ' . \$herstelbaar") !== false,
            'de herstelbare bestanden worden uit HEAD teruggezet, niet uit de index');
        foreach (['composer.lock', 'composer.json', 'deploy.php', 'deploy_status.php', 'cma/package.json'] as $bestand) {
            $this->assertTrue(strpos($bron, "'" . $bestand . "'") !== false,
                $bestand . ' staat in het rijtje dat vóór de pull uit HEAD terugkomt');
        }

        // De volgorde telt: eerst terugzetten, dan pas de pipeline met de pull.
        $posReset = strpos($bron, "git checkout HEAD -- ");
        $posPull  = strpos($bron, "\$pipeline = \$envRead('DEPLOY_PIPELINE'");
        $this->assertTrue($posReset !== false && $posPull !== false && $posReset < $posPull,
            'het terugzetten gebeurt vóór de pipeline die pullt');
    }

    public function testWatErWordtWeggegooidKomtInDeLog(): void
    {
        $this->assertTrue(strpos($this->bron(), 'git status --porcelain') !== false,
            'de deploy kijkt wat er lokaal gewijzigd is');
        $this->assertTrue(strpos($this->bron(), 'pre-pull reset') !== false,
            'en schrijft dat in de log');
    }

    public function testInstallOnlyModus(): void
    {
        $bron = $this->bron();
        $this->assertTrue(strpos($bron, "strcasecmp(trim(\$composerPkgs), 'install') === 0") !== false,
            'DEPLOY_COMPOSER_UPDATE=install wordt herkend');
        $this->assertTrue(strpos($bron, "\$composerInstallOnly") !== false,
            'en stuurt de composer-stap');
        $this->assertTrue(strpos($bron, 'DEPLOY_COMPOSER_UPDATE=install') !== false,
            'de kop van het bestand legt uit wanneer je dat wilt');
    }

    public function testGitMagInDeWerkkopieWerken(): void
    {
        // IIS draait de deploy als de app-pool-identiteit; die is niet de eigenaar van de
        // werkkopie, en git weigert dat sinds CVE-2022-24765 ("detected dubious ownership").
        // Elk git-commando eindigt dan op 128 en de deploy valt stil vóór hij iets doet.
        $bron = $this->bron();
        $this->assertTrue(strpos($bron, "GIT_CONFIG_KEY_0=safe.directory") !== false,
            'de deploy zet safe.directory voor zijn eigen git-processen');
        $this->assertTrue(strpos($bron, "GIT_CONFIG_VALUE_0=") !== false,
            'en vult daar het pad van deze werkkopie in');

        // Vóór de reset én de pull, anders is het te laat.
        $posSafe  = strpos($bron, 'GIT_CONFIG_KEY_0');
        $posReset = strpos($bron, "git checkout HEAD -- ");
        $this->assertTrue($posSafe !== false && $posReset !== false && $posSafe < $posReset,
            'dat gebeurt vóór het eerste git-commando');

        // Alleen als de omgeving het niet al zelf regelt.
        $this->assertTrue(strpos($bron, "getenv('GIT_CONFIG_COUNT')") !== false,
            'een bestaande GIT_CONFIG_COUNT wordt niet overschreven');
    }

    public function testDeResetNogSteedsUitTeZettenIs(): void
    {
        // Een site die zijn eigen wijzigingen op de server bewaart, moet dit kunnen weigeren.
        $this->assertTrue(strpos($this->bron(), "DEPLOY_NO_RESET") !== false,
            'DEPLOY_NO_RESET blijft bestaan');
    }
}
