<?php
/**
 * InstallerMinifyBouwTest — wanneer bouwt de installer de .min-bestanden zelf?
 *
 * WAAROM DEZE TEST ER IS. De installer meldde alleen wát er onverkleind zou uitgaan,
 * met de opdracht erbij om het zelf te doen. Dat werkt alleen als iemand de
 * composer-uitvoer leest én die opdracht daarna ook uitvoert. Onverkleind serveren is
 * correct — alleen groter — dus het valt nooit vanzelf op; een site serveerde
 * maandenlang de bron.
 *
 * Nu bouwt de installer zelf, mits het kan. De afweging "kan het hier?" is
 * losgetrokken van het draaien, zodat hij te toetsen is zonder een proces te starten.
 * Twee dingen moeten er staan: het bouwscript en cma/node_modules met terser — die
 * laatste zet alleen `npm install` neer, en dat kan composer niet voor de beheerder
 * doen. Ontbreekt er iets, dan is dat een mededeling en geen fout: een update mag
 * hier nooit op stuklopen.
 *
 *   php cma/tests/TestRunner.php InstallerMinifyBouwTest
 */

require_once __DIR__ . '/TestRunner.php';
require_once dirname(__DIR__, 2) . '/src/Installer.php';

use App\Library\Installer;

class InstallerMinifyBouwTest extends TestCase
{
    private string $tijdelijk = '';

    private function maakSite(bool $metScript, bool $metNodeModules): string
    {
        $wortel = sys_get_temp_dir() . '/minifybouw_' . bin2hex(random_bytes(6));
        mkdir($wortel . '/cma/tools', 0777, true);
        if ($metScript) {
            file_put_contents($wortel . '/cma/tools/build-minify.js', '// proef');
        }
        if ($metNodeModules) {
            mkdir($wortel . '/cma/node_modules/terser', 0777, true);
        }
        $this->tijdelijk = $wortel;
        return $wortel;
    }

    private function ruimOp(): void
    {
        if ($this->tijdelijk === '') {
            return;
        }
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->tijdelijk, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $pad) {
            $pad->isDir() ? @rmdir($pad->getPathname()) : @unlink($pad->getPathname());
        }
        @rmdir($this->tijdelijk);
        $this->tijdelijk = '';
    }

    public function testMetScriptEnNodeModulesMagErGebouwdWorden(): void
    {
        $plan = Installer::minifyBouwplan($this->maakSite(true, true));
        $this->assertTrue($plan['kan'], 'alles staat er, dus bouwen mag');
        $this->assertEquals('', $plan['reden']);
        $this->ruimOp();
    }

    public function testZonderNodeModulesWordtErNietGebouwd(): void
    {
        $plan = Installer::minifyBouwplan($this->maakSite(true, false));
        $this->assertFalse($plan['kan']);
        $this->assertStringContainsString('npm install', $plan['reden'], 'met de opdracht die het oplost');
        $this->ruimOp();
    }

    public function testZonderBouwscriptWordtErNietGebouwd(): void
    {
        $plan = Installer::minifyBouwplan($this->maakSite(false, true));
        $this->assertFalse($plan['kan']);
        $this->assertStringContainsString('build-minify.js', $plan['reden']);
        $this->ruimOp();
    }

    public function testEenLegeMapLevertGeenFoutMaarEenReden(): void
    {
        $leeg = sys_get_temp_dir() . '/minifybouw_leeg_' . bin2hex(random_bytes(4));
        mkdir($leeg, 0777, true);
        $plan = Installer::minifyBouwplan($leeg);
        $this->assertFalse($plan['kan'], 'niets aanwezig');
        $this->assertTrue($plan['reden'] !== '', 'en altijd met een reden erbij');
        @rmdir($leeg);
    }

    public function testDeBouwstapStaatVoorHetRapport(): void
    {
        // De volgorde is het punt: eerst bouwen, dan melden wat er nog overblijft.
        // Andersom zou het rapport bestanden noemen die een regel later al klaar zijn.
        $bron = file_get_contents(dirname(__DIR__, 2) . '/src/Installer.php');
        $bouw = strpos($bron, 'self::buildMinifiedAssets($projectRoot)');
        $rapport = strpos($bron, 'self::reportUnminifiedAssets($projectRoot)');

        $this->assertTrue($bouw !== false, 'de bouwstap moet aangeroepen worden');
        $this->assertTrue($rapport !== false, 'het rapport ook');
        $this->assertTrue($bouw < $rapport, 'bouwen komt vóór melden');
    }

    public function testHetPlatformKentZijnEigenBouwscript(): void
    {
        // Het echte platform: het script moet bestaan, anders bouwt geen enkele site.
        $plan = Installer::minifyBouwplan(dirname(__DIR__, 2));
        $this->assertStringContainsString('cma/tools/build-minify.js', $plan['script']);
        $this->assertTrue(is_file($plan['script']), 'het bouwscript hoort mee te komen');
    }
}
