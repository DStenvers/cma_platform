<?php
/**
 * ErrorLogRotationTest.php — dagrotatie + retentie van het PHP-errorlog.
 *
 * Aanleiding: één ongeroteerd bestand groeide op een dev-site naar 403 MB.
 * Elke mislukte query schrijft een volledige stacktrace, dus een testrun of een
 * kapotte databaseverbinding vult in een uur honderden megabytes — en dan opent
 * niemand het nog.
 *
 *   php tests/TestRunner.php ErrorLogRotationTest
 */

require_once __DIR__ . '/TestRunner.php';

use App\Library\ErrorHandler;

class ErrorLogRotationTest extends TestCase
{
    private string $dir = '';

    public function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/errlog-' . bin2hex(random_bytes(6)) . '/phperrors';
        mkdir($this->dir, 0777, true);
    }

    public function tearDown(): void
    {
        foreach (glob($this->dir . '/*') ?: [] as $f) { @unlink($f); }
        @rmdir($this->dir);
        @rmdir(dirname($this->dir));
    }

    private function configured(): string
    {
        return $this->dir . '/php_errors.log';
    }

    /** prepareDailyErrorLog is privé — dit is de enige ingang die telt. */
    private function prepare(): string
    {
        $method = (new ReflectionClass(ErrorHandler::class))->getMethod('prepareDailyErrorLog');
        $method->setAccessible(true);
        return (string) $method->invoke(null, $this->configured());
    }

    private function dated(int $daysAgo): string
    {
        return $this->dir . '/php_errors_' . date('Y-m-d', strtotime("-{$daysAgo} days")) . '.log';
    }

    public function testWritesToTodaysFile(): void
    {
        $active = $this->prepare();
        $this->assertEquals($this->dated(0), $active, 'het actieve log is het dagbestand van vandaag');
        $this->assertTrue(is_file($active), 'en dat bestand wordt aangemaakt');
    }

    public function testKeepsFilesInsideTheRetentionWindow(): void
    {
        file_put_contents($this->dated(1), 'x');
        file_put_contents($this->dated(6), 'x');
        $this->prepare();
        $this->assertTrue(is_file($this->dated(1)), 'gisteren blijft');
        $this->assertTrue(is_file($this->dated(6)), 'zes dagen terug blijft (binnen 7)');
    }

    public function testPrunesFilesBeyondTheRetentionWindow(): void
    {
        file_put_contents($this->dated(8), 'x');
        file_put_contents($this->dated(45), 'x');
        $this->prepare();
        $this->assertFalse(is_file($this->dated(8)), 'acht dagen terug wordt opgeruimd');
        $this->assertFalse(is_file($this->dated(45)), 'en alles daarvoor ook');
    }

    /**
     * De opruiming hoort alleen te draaien bij het aanmaken van het dagbestand.
     * Zou hij bij elke aanroep draaien, dan scant elke request de map — en dat
     * voor een uitkomst die maar één keer per dag kan veranderen.
     */
    public function testPruneOnlyRunsOnRollover(): void
    {
        $this->prepare();                       // maakt vandaag aan + ruimt op
        $stale = $this->dated(30);
        file_put_contents($stale, 'na de rollover neergezet');

        $this->prepare();                       // tweede aanroep: geen rollover
        $this->assertTrue(is_file($stale), 'geen tweede scan zolang het dagbestand bestaat');
    }

    /** Het oude ongedateerde bestand is geen dagbestand en blijft met rust. */
    public function testLeavesTheLegacyFileAlone(): void
    {
        file_put_contents($this->configured(), 'historie van vóór de rotatie');
        $this->prepare();
        $this->assertTrue(is_file($this->configured()), 'php_errors.log wordt niet weggegooid');
    }

    /** Een bestandsnaam zonder geldige datum mag nooit als "oud" gelden. */
    public function testIgnoresFilesThatDoNotCarryADate(): void
    {
        $odd = $this->dir . '/php_errors_backup.log';
        file_put_contents($odd, 'x');
        $this->prepare();
        $this->assertTrue(is_file($odd), 'niet-gedateerde bestanden blijven staan');
    }

    public function testActiveLogIsExposedForTooling(): void
    {
        $this->assertTrue(
            method_exists(ErrorHandler::class, 'getErrorLogFile'),
            'tools moeten het actieve pad kunnen opvragen in plaats van het zelf samen te stellen'
        );
    }
}
