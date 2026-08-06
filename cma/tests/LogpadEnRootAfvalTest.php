<?php
/**
 * Drie manieren waarop een site zijn eigen wortel vervuilt, en waarom ze allemaal STIL waren.
 *
 * 1. PROFILER_LOG_FILE / SQL_LOG_FILE gingen kaal naar fopen(). Een relatief pad hangt dan aan
 *    de werkmap van het script, en die is voor een verzoek uit /cma/ een andere dan voor een
 *    verzoek uit de siteroot — hetzelfde logbestand belandde dus op twee plekken. Wie zijn
 *    logs bij elkaar wilde houden moest een ABSOLUUT pad opgeven, en dat verschilt per
 *    machine; dus stond het nergens ingevuld en bleef profiler.csv in de wortel verschijnen,
 *    ook nadat iemand hem had opgeruimd. Nu: relatief = vanaf de siteroot.
 *
 * 2. De cache-clear-tool minificeert elk *.js in de siteroot. Daar liggen ook
 *    gereedschapsbestanden die nooit naar een browser gaan (playwright.config.js), en die
 *    kregen een .min.js naast zich die niemand laadt — en die na elke opruiming terugkwam.
 *
 * 3. blockedit.js gaf CKEditor '/cma.css' mee: de OUDE locatie. De Installer zet dat bestand
 *    tegenwoordig in assets/css/, en cma.js, CKEditor/config.js en cma-htmledit.js wijzen daar
 *    alle drie al naartoe. Door die ene achtergebleven regel moest elke site een kopie in de
 *    wortel laten staan. Ontbreekt die, dan is er geen foutmelding: de editor werkt, maar de
 *    inhoud ziet er tijdens het bewerken anders uit dan op de site.
 *
 * Run met: php cma/tests/TestRunner.php LogpadEnRootAfvalTest
 */

require_once __DIR__ . '/TestRunner.php';

use App\Library\Database;
use App\Library\Profiler;

class LogpadEnRootAfvalTest extends TestCase
{
    private array $envBackup = [];

    public function setUp(): void
    {
        $this->envBackup = [
            'PROFILER_LOG_FILE' => $_ENV['PROFILER_LOG_FILE'] ?? null,
            'SQL_LOG_FILE'      => $_ENV['SQL_LOG_FILE'] ?? null,
        ];
        $_SERVER['DOCUMENT_ROOT'] = '/var/www/site';
    }

    public function tearDown(): void
    {
        foreach ($this->envBackup as $k => $v) {
            if ($v === null) { unset($_ENV[$k]); } else { $_ENV[$k] = $v; }
        }
    }

    /** @return mixed */
    private function padVia(string $klasse, string $waarde)
    {
        $m = new \ReflectionMethod($klasse, 'isAbsoluutPad');
        $m->setAccessible(true);
        return $m->invoke(null, $waarde);
    }

    // ---- 1. relatief hangt aan de siteroot -------------------------------

    public function testUnixPadGeldtAlsAbsoluut(): void
    {
        $this->assertTrue($this->padVia(Profiler::class, '/var/log/profiler.csv'));
        $this->assertTrue($this->padVia(Database::class, '/var/log/sql.log'));
    }

    public function testWindowsPadGeldtAlsAbsoluut(): void
    {
        $this->assertTrue($this->padVia(Profiler::class, 'C:\\logs\\profiler.csv'));
        $this->assertTrue($this->padVia(Profiler::class, '\\\\server\\share\\profiler.csv'));
    }

    public function testRelatiefPadGeldtNietAlsAbsoluut(): void
    {
        // Dit is het geval waar het om begonnen is: ".logs/profiler.csv" hoort aan de
        // siteroot geplakt te worden, niet aan de werkmap van het script.
        $this->assertFalse($this->padVia(Profiler::class, '.logs/profiler.csv'));
        $this->assertFalse($this->padVia(Database::class, 'logs/sql_queries.log'));
        $this->assertFalse($this->padVia(Profiler::class, ''));
    }

    public function testEenLetterMetDubbelePuntIsGeenStationsaanduiding(): void
    {
        // "c:" alleen is te kort om een pad te zijn; niet als absoluut zien.
        $this->assertFalse($this->padVia(Profiler::class, 'c:'));
    }

    /**
     * Broncontrole op de samenstelling zelf: een levende Profiler::init() vraagt een
     * Application-opzet die deze suite bewust niet optuigt.
     */
    public function testDeBronPlaktEenRelatiefPadAanDeSiteroot(): void
    {
        foreach ([
            'src/helpers/Profiler.php' => 'profiler.csv',
            'src/helpers/Database.php' => 'sql_queries.log',
        ] as $bestand => $wat) {
            $bron = file_get_contents(__DIR__ . '/../../' . $bestand);
            $this->assertStringContainsString('self::isAbsoluutPad(', $bron,
                "$bestand kijkt niet meer of het pad absoluut is ($wat)");
            $this->assertStringContainsString("\$siteRoot . '/' . ltrim(", $bron,
                "$bestand plakt een relatief pad niet meer aan de siteroot ($wat)");
        }
    }

    // ---- 2. gereedschapsconfiguratie wordt niet geminificeerd ------------

    public function testDeMinifierSlaatConfigBestandenOver(): void
    {
        $bron = file_get_contents(__DIR__ . '/../tools/tools_clearcache.php');
        $this->assertStringContainsString("substr(basename(\$jsFile), -10) === '.config.js'", $bron,
            'playwright.config.js e.d. worden weer geminificeerd, met .min.js-afval in de siteroot');

        // De uitzondering moet VOOR de terser-aanroep staan, anders doet hij niets.
        $iSkip = strpos($bron, "=== '.config.js'");
        $iRun  = strpos($bron, '$_terserCmd');
        $this->assertTrue($iSkip !== false && $iRun !== false && $iSkip < strrpos($bron, '$_terserCmd'),
            'de uitzondering staat na het minificeren');
    }

    // ---- 3. de blokeditor wijst naar de huidige locatie ------------------

    public function testBlokeditorGebruiktDeHuidigeCmaCss(): void
    {
        foreach (['cma/assets/js/blockedit.js', 'cma/assets/js/blockedit.min.js'] as $bestand) {
            $bron = file_get_contents(__DIR__ . '/../../' . $bestand);
            $this->assertStringContainsString('/assets/css/cma.css', $bron,
                "$bestand wijst niet naar de huidige locatie van cma.css");
            $this->assertStringNotContainsString("'/cma.css'", $bron,
                "$bestand gebruikt weer het oude rootpad");
            $this->assertStringNotContainsString('"/cma.css"', $bron,
                "$bestand gebruikt weer het oude rootpad");
        }
    }
}
