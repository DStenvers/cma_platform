<?php
/**
 * LogreaderBronnenTest — de keuzelijst toont alleen logs die er zijn.
 *
 * Run with: php cma/tests/TestRunner.php LogreaderBronnenTest
 *
 * Waarom deze test bestaat:
 * De logreader bood elke bekende logbron aan, ook die op deze site nooit
 * aangemaakt wordt. "Ongeautoriseerde toegang" verschijnt pas als de RBAC-poort
 * iemand tegenhoudt, en de cache-log pas als er iets gecachet is — op een site
 * waar dat niet gebeurt kiest de beheerder dus iets dat gegarandeerd "Log
 * bestand niet gevonden" oplevert. Dat leest als kapot, terwijl er niets aan de
 * hand is.
 *
 * Twee dingen die daarbij niet mogen sneuvelen: de gekozen bron blijft in de
 * lijst staan (anders wijst de keuzelijst iets anders aan dan wat eronder
 * getoond wordt), en een bron met datumrotatie telt als aanwezig zodra er één
 * bestand van bestaat — niet alleen dat van de gekozen datum.
 */

require_once __DIR__ . '/TestRunner.php';
require_once __DIR__ . '/../tools/logreader_bronnen.inc';

class LogreaderBronnenTest extends TestCase
{
    private string $tmp;

    public function setUp(): void
    {
        $this->tmp = sys_get_temp_dir() . '/logreader-' . bin2hex(random_bytes(6));
        mkdir($this->tmp, 0755, true);
    }

    public function tearDown(): void
    {
        foreach ((array) glob($this->tmp . '/*') as $f) {
            @unlink($f);
        }
        @rmdir($this->tmp);
    }

    private function bestand(string $naam): string
    {
        $pad = $this->tmp . '/' . $naam;
        file_put_contents($pad, '');
        return $pad;
    }

    public function testEenBestandDatErNietIsVerdwijntUitDeLijst(): void
    {
        $bronnen = [
            'cache'        => ['name' => 'Cache Log', 'path' => $this->bestand('cache.log')],
            'unauthorized' => ['name' => 'Ongeautoriseerde toegang', 'path' => $this->tmp . '/bestaat-niet.log'],
        ];

        $over = cma_logreader_beschikbare_bronnen($bronnen, 'cache', []);
        $this->assertEquals(['cache'], array_keys($over));
    }

    public function testEenLeegBestandTeltAlsAanwezig(): void
    {
        // Het bestand bestaat, dus het systeem heeft het aangemaakt. Leeg is een
        // uitkomst, geen reden om de keuze te verbergen.
        $bronnen = ['cache' => ['name' => 'Cache Log', 'path' => $this->bestand('cache.log')]];
        $this->assertEquals(['cache'], array_keys(cma_logreader_beschikbare_bronnen($bronnen, 'perf', [])));
    }

    public function testDeGekozenBronBlijftAltijdStaan(): void
    {
        // Anders zou de keuzelijst een andere bron aanwijzen dan wat eronder staat
        // — bijvoorbeeld vlak nadat je het log geleegd én verwijderd hebt.
        $bronnen = ['unauthorized' => ['name' => 'Ongeautoriseerde toegang', 'path' => $this->tmp . '/weg.log']];
        $this->assertEquals(['unauthorized'],
            array_keys(cma_logreader_beschikbare_bronnen($bronnen, 'unauthorized', [])));
    }

    public function testDatumgeroteerdeBronTeltZodraErEenDatumIs(): void
    {
        // path wijst naar de gekozen datum; de bron hoort te blijven staan zolang
        // er van welke datum dan ook een bestand is.
        $bronnen = [
            'perf'  => ['name' => 'Performance Log', 'path' => $this->tmp . '/perf_2026-08-14.log'],
            'debug' => ['name' => 'Debug Log',       'path' => $this->tmp . '/debug_2026-08-14.log'],
        ];

        $over = cma_logreader_beschikbare_bronnen($bronnen, 'perf', [
            'perf'  => ['2026-08-01'],
            'debug' => [],
        ]);
        $this->assertEquals(['perf'], array_keys($over));
    }

    public function testVolgordeBlijftZoalsGedefinieerd(): void
    {
        $bronnen = [
            'perf'   => ['name' => 'Performance Log', 'path' => $this->bestand('perf.log')],
            'cache'  => ['name' => 'Cache Log',       'path' => $this->tmp . '/weg.log'],
            'deploy' => ['name' => 'Deploy Log',      'path' => $this->bestand('deploy.log')],
        ];

        $this->assertEquals(['perf', 'deploy'],
            array_keys(cma_logreader_beschikbare_bronnen($bronnen, 'perf', [])));
    }

    public function testEenPadDatLeegIsTeltNietMee(): void
    {
        // ini_get('error_log') levert een lege string op als er geen PHP-log is
        // ingesteld; is_file('') zou dat anders als "niet gevonden" doorlaten.
        $bronnen = ['php' => ['name' => 'PHP Error Log', 'path' => '']];
        $this->assertEquals([], array_keys(cma_logreader_beschikbare_bronnen($bronnen, 'perf', [])));
    }
}
