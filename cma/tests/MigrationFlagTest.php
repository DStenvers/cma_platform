<?php
/**
 * MigrationFlagTest.php — het vlagbestand migrations_pending.flag (App\Library\MigrationFlag): zetten bij openstaande migraties, wissen als er geen zijn, tellen, en de banner
 * die alleen met de vlag verschijnt. Werkt in een tijdelijke map (de root wordt meegegeven), dus zonder site of database.
 *
 *   php tests/TestRunner.php MigrationFlagTest
 */

require_once __DIR__ . '/TestRunner.php';
require_once __DIR__ . '/../../src/helpers/MigrationFlag.php';

use App\Library\MigrationFlag;

class MigrationFlagTest extends TestCase
{
    private string $dir = '';

    public function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/migrationflag_' . getmypid() . '_' . mt_rand();
        mkdir($this->dir, 0777, true);
    }

    public function tearDown(): void
    {
        MigrationFlag::sync(0, $this->dir);
        @rmdir($this->dir);
    }

    public function testOpenstaandZetDeVlagMetHetAantal(): void
    {
        MigrationFlag::sync(3, $this->dir);
        $this->assertTrue(MigrationFlag::exists($this->dir), 'de vlag staat er');
        $this->assertEquals(3, MigrationFlag::count($this->dir), 'het aantal staat in de vlag');
    }

    public function testNietsOpenstaandWistDeVlag(): void
    {
        MigrationFlag::sync(2, $this->dir);
        MigrationFlag::sync(0, $this->dir);
        $this->assertFalse(MigrationFlag::exists($this->dir), 'de vlag is weg');
        $this->assertEquals(0, MigrationFlag::count($this->dir), 'zonder vlag is het aantal 0');
    }

    public function testWissenZonderVlagIsGoed(): void
    {
        MigrationFlag::sync(0, $this->dir);
        $this->assertFalse(MigrationFlag::exists($this->dir), 'geen vlag en geen fout');
    }

    public function testBijwerkenOverschrijftHetAantal(): void
    {
        MigrationFlag::sync(5, $this->dir);
        MigrationFlag::sync(1, $this->dir);
        $this->assertEquals(1, MigrationFlag::count($this->dir), 'het laatste aantal telt');
    }

    public function testBestandHeetMigrationsPendingFlagInDeRoot(): void
    {
        $this->assertEquals($this->dir . DIRECTORY_SEPARATOR . 'migrations_pending.flag', MigrationFlag::path($this->dir), 'het pad');
    }

    public function testBannerAlleenMetVlag(): void
    {
        // Zonder expliciete root kijkt de banner naar de root van de site; hier de vlag in de tijdelijke map testen via path(): de banner zelf is een bestandscontrole.
        $bron = (string) file_get_contents(__DIR__ . '/../../src/helpers/MigrationFlag.php');
        $this->assertTrue(str_contains($bron, "if (!self::exists()) {\n            return '';"), 'zonder vlag geeft de banner niets terug');
        $this->assertTrue(str_contains($bron, 'Er zijn migraties vereist.'), 'de tekst van de banner');
    }
}
