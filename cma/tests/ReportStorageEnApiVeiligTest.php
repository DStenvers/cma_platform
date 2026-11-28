<?php
/**
 * Opslag en uitvoering van rapportdefinities uit de rapportontwerper.
 *
 *  - Ruwe SQL in een definitie (SQL-modus) komt uit de browser. Preview, tellen en export draaien wat QueryBuilder::toSql() teruggeeft; dat
 *    is vroeger zonder controle de ruwe tekst geweest, zodat een ingelogde CMA-gebruiker met "mode":"sql" een DELETE of DROP kon laten draaien.
 *  - Opslaan schrijft atomair (tijdelijk bestand + rename) en gebruikt alleen een veilig ID in de bestandsnaam.
 */
require_once __DIR__ . '/TestRunner.php';
foreach ([dirname(__DIR__, 2) . '/vendor/autoload.php', dirname(__DIR__) . '/vendor/autoload.php'] as $__a) {
    if (is_file($__a)) { require_once $__a; break; }
}
require_once dirname(__DIR__) . '/classes/SchemaHelper.php';
require_once dirname(__DIR__) . '/classes/QueryBuilder.php';
require_once dirname(__DIR__) . '/classes/ReportStorage.php';

use Cma\QueryBuilder;
use Cma\ReportStorage;

class ReportStorageEnApiVeiligTest extends TestCase
{
    private string $dir;

    public function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/reportstorage-' . bin2hex(random_bytes(6));
        mkdir($this->dir, 0775, true);
        ReportStorage::useDirectory($this->dir);
    }

    public function tearDown(): void
    {
        ReportStorage::useDirectory(null);
        $this->wis($this->dir);
    }

    private function wis(string $d): void
    {
        foreach (glob($d . '/{,.}*', GLOB_BRACE) ?: [] as $f) {
            if (in_array(basename($f), ['.', '..'], true)) { continue; }
            is_dir($f) ? $this->wis($f) : @unlink($f);
        }
        @rmdir($d);
    }

    // ---------- ruwe SQL ----------

    public function testToSqlWeigertEenWijzigendeOpdrachtInSqlModus(): void
    {
        foreach (['DELETE FROM tblKlant', 'DROP TABLE tblKlant', 'UPDATE tblKlant SET a=1', 'SELECT * INTO kopie FROM tblKlant',
                  'SELECT 1; DELETE FROM tblKlant', 'INSERT INTO t VALUES (1)'] as $sql) {
            $b = new QueryBuilder(['mode' => 'sql', 'rawSql' => $sql]);
            $this->assertSame('', $b->toSql(), $sql);
            $this->assertCount(1, $b->getErrors(), $sql);
        }
    }

    public function testPreviewEnTellenVoerenDanNietsUit(): void
    {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE tblKlant (ID INTEGER, Naam TEXT); INSERT INTO tblKlant VALUES (1, \'a\')');
        $b = new QueryBuilder(['mode' => 'sql', 'rawSql' => 'DELETE FROM tblKlant'], $pdo);
        $r = $b->executePreview(0);
        $this->assertFalse($r['success']);
        $this->assertFalse($b->executeCount()['success']);
        $this->assertSame('1', (string) $pdo->query('SELECT COUNT(*) FROM tblKlant')->fetchColumn(), 'de rij is er nog');
    }

    public function testEenGewoneSelectInSqlModusLooptDoor(): void
    {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE tblKlant (ID INTEGER, Naam TEXT); INSERT INTO tblKlant VALUES (1, \'a\')');
        $b = new QueryBuilder(['mode' => 'sql', 'rawSql' => 'SELECT Naam FROM tblKlant'], $pdo);
        $r = $b->executePreview(0);
        $this->assertTrue($r['success']);
        $this->assertCount(1, $r['data']);
        $this->assertSame(1, $b->executeCount()['count']);
    }

    public function testTekstwaardenEnBlokhakenZijnGeenOpdrachten(): void
    {
        $this->assertNull(QueryBuilder::rawSqlError("SELECT Naam FROM tblKlant WHERE Status = 'update' OR Opmerking LIKE '%delete; drop%'"));
        $this->assertNull(QueryBuilder::rawSqlError('SELECT [Update], [Delete] FROM [Insert]'));
        $this->assertNull(QueryBuilder::rawSqlError("SELECT 'a''; DROP TABLE x' AS t FROM tblKlant"), 'een verdubbelde apostrof houdt de tekstwaarde bij elkaar');
        $this->assertNotNull(QueryBuilder::rawSqlError("SELECT 'a' FROM t; DELETE FROM t"));
        $this->assertNotNull(QueryBuilder::rawSqlError("SELECT * FROM t WHERE a = 'x' DELETE"));
    }

    public function testRawSqlDieGeenTekstIsWordtGeweigerd(): void
    {
        $b = new QueryBuilder(['mode' => 'sql', 'rawSql' => ['SELECT 1']]);
        $this->assertSame('', $b->toSql());
        $this->assertNotEmpty($b->getErrors());
    }

    // ---------- opslag ----------

    public function testOpslaanEnLaden(): void
    {
        $r = ReportStorage::save(['name' => 'Mijn rapport', 'tables' => [['name' => 't']]], '7');
        $this->assertTrue($r['success']);
        $geladen = ReportStorage::load($r['id'], '7');
        $this->assertSame('Mijn rapport', $geladen['name']);
        $this->assertSame([], glob($this->dir . '/*/*/*.tmp'), 'geen tijdelijk bestand achtergelaten');
        $this->assertNull(ReportStorage::load($r['id'], '8'), 'een ander ziet het persoonlijke rapport niet');
    }

    public function testOverschrijvenLaatAltijdLeesbareJsonAchter(): void
    {
        $id = ReportStorage::save(['name' => 'a'], '7')['id'];
        for ($i = 0; $i < 20; $i++) {
            ReportStorage::save(['id' => $id, 'name' => 'versie ' . $i, 'blob' => str_repeat('x', 5000)], '7');
            $this->assertSame('versie ' . $i, ReportStorage::load($id, '7')['name']);
        }
    }

    public function testGevaarlijkIdWordtSchoongemaaktOfVervangen(): void
    {
        $r = ReportStorage::save(['id' => '../../x', 'name' => 'a'], '7');
        $this->assertTrue($r['success']);
        $this->assertSame('x', $r['id'], 'alleen letters, cijfers, - en _');
        $r = ReportStorage::save(['id' => '../..', 'name' => 'b'], '7');
        $this->assertTrue($r['success']);
        $this->assertTrue(strlen($r['id']) >= 30, 'een leeg ID krijgt een nieuw ID');
        foreach (glob($this->dir . '/*/*/.json') ?: [] as $f) {
            $this->fail('verborgen bestand .json aangemaakt: ' . $f);
        }
        $this->assertSame([], glob($this->dir . '/../*x.json'), 'niets buiten de rapportenmap');
    }

    public function testNaamIsVerplicht(): void
    {
        $this->assertFalse(ReportStorage::save(['name' => ''], '7')['success']);
    }
}
