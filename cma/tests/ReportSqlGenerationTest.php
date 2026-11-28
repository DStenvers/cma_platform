<?php
/**
 * ReportSqlGenerationTest — de rapportontwerper bouwt SQL; die SQL moet geldig zijn én de juiste rijen geven.
 *
 * Run with: php cma/tests/TestRunner.php ReportSqlGenerationTest
 *
 * Werkwijze: elke definitie gaat door de echte QueryBuilder, de SQL draait daarna op een SQLite-geheugendatabase met een
 * klein bestelschema. Zo test dit wat de database accepteert en teruggeeft, niet wat wij denken dat hij accepteert.
 * SQLite kent de Access-eigenaardigheden niet; de hulpfunctie sqlVoorSqlite() vertaalt alleen die: `TOP n` (wordt LIMIT n),
 * een datumletterlijk `#m/d/Y#` (wordt 'Y-m-d') en de apostrof-notatie `' & chr(39) & '` (wordt ''). De geneste haakjes van de
 * JOINs, de blokhaken om namen en de rest van de SQL gaan ongewijzigd naar SQLite.
 *
 * Opbouw: eerst één tabel, dan twee gerelateerde, dan vier tot vijf tabellen; daarna voorwaarden, sortering, groepering en de
 * gevallen die geen geldige SQL kunnen worden en dus een duidelijke foutmelding moeten geven.
 *
 * Voor later (bewust nog niet gebouwd; zie de testNogNietOndersteund-tests, die het huidige gedrag vastleggen):
 *  - aggregaties (COUNT/SUM/AVG/MIN/MAX) bij groepering: nu is groeperen hetzelfde als DISTINCT;
 *  - tabelaliassen, zodat een tabel twee keer kan voorkomen (zelfkoppeling, bv. begeleider en student in dezelfde tabel);
 *  - voorwaardegroepen (geneste AND/OR) in plaats van losse haakjes; HAVING; berekende velden; subselects (IN/EXISTS);
 *  - FULL JOIN bestaat niet in Access; nu wordt hij doorgegeven, hij hoort daar een foutmelding te geven of een UNION te worden;
 *  - TOP n PERCENT en een offset (paginering) voor grote rapporten.
 */

require_once __DIR__ . '/TestRunner.php';
foreach ([dirname(__DIR__, 2) . '/vendor/autoload.php', dirname(__DIR__) . '/vendor/autoload.php'] as $__a) {
    if (is_file($__a)) { require_once $__a; break; }
}
require_once dirname(__DIR__) . '/classes/SchemaHelper.php';
require_once dirname(__DIR__) . '/classes/QueryBuilder.php';
require_once dirname(__DIR__) . '/classes/SqlParser.php';

use Cma\QueryBuilder;
use Cma\SqlParser;

class ReportSqlGenerationTest extends TestCase
{
    private \PDO $pdo;

    public function setUp(): void
    {
        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $vandaag = date('Y-m-d');
        $gisteren = date('Y-m-d', strtotime('yesterday'));
        $morgen = date('Y-m-d', strtotime('tomorrow'));
        $dezeMaand = date('Y-m-15');
        $this->pdo->exec("
            CREATE TABLE tblKlant (ID INTEGER PRIMARY KEY, Naam TEXT, Plaats TEXT, Actief INTEGER, Korting REAL);
            CREATE TABLE tblOrder (ID INTEGER PRIMARY KEY, fkKlant INTEGER, Datum TEXT, Totaal REAL);
            CREATE TABLE tblOrderRegel (ID INTEGER PRIMARY KEY, fkOrder INTEGER, fkProduct INTEGER, Aantal INTEGER);
            CREATE TABLE tblProduct (ID INTEGER PRIMARY KEY, Omschrijving TEXT, fkCategorie INTEGER, Prijs REAL);
            CREATE TABLE tblCategorie (ID INTEGER PRIMARY KEY, Code TEXT);
            INSERT INTO tblKlant VALUES (1,'Jansen','Utrecht',1,0.0),(2,'O''Brien','Zwolle',0,5.5),(3,'De Vries',NULL,1,NULL),(4,'Bakker','',1,2.0);
            INSERT INTO tblOrder VALUES (1,1,'$vandaag',100.0),(2,1,'$gisteren',50.0),(3,2,'$morgen',75.0),(4,3,'$dezeMaand',20.0),(5,NULL,'2020-01-01',1.0);
            INSERT INTO tblOrderRegel VALUES (1,1,1,2),(2,1,2,1),(3,2,1,5),(4,4,3,1);
            INSERT INTO tblProduct VALUES (1,'Pen',1,1.5),(2,'Papier',1,4.0),(3,'Schaar',2,9.0),(4,'Zonder categorie',NULL,1.0);
            INSERT INTO tblCategorie VALUES (1,'KANTOOR'),(2,'KNIP');
        ");
    }

    // ---------- hulpfuncties ----------

    private function veld(string $tabel, string $veld, string $alias = ''): array
    {
        return ['table' => $tabel, 'field' => $veld, 'alias' => $alias, 'visible' => true];
    }

    private function join(string $tabel, string $on, string $type = 'INNER'): array
    {
        return ['table' => $tabel, 'type' => $type, 'on' => $on];
    }

    private function cond(string $tabel, string $veld, string $op, string $v = '', string $v2 = '', string $type = 'text', string $logica = 'AND', array $extra = []): array
    {
        return ['table' => $tabel, 'field' => $veld, 'operator' => $op, 'value' => $v, 'value2' => $v2, 'typeCategory' => $type, 'logic' => $logica] + $extra;
    }

    /** De Access-eigenaardigheden vertaald naar SQLite; al het andere blijft zoals de QueryBuilder het maakte. */
    private function sqlVoorSqlite(string $sql): string
    {
        $limit = '';
        if (preg_match('/\bTOP (\d+)\s/i', $sql, $m)) {
            $limit = ' LIMIT ' . $m[1];
            $sql = preg_replace('/\bTOP \d+\s/i', '', $sql, 1);
        }
        $sql = preg_replace_callback('#\#(\d{2})/(\d{2})/(\d{4})\##', fn($d) => "'" . $d[3] . '-' . $d[1] . '-' . $d[2] . "'", $sql);
        $sql = str_replace("' & chr(39) & '", "''", $sql);
        return $sql . $limit;
    }

    /** @return array{sql:string, rijen:array, fouten:array} */
    private function draai(array $definitie, ?int $limiet = null): array
    {
        $b = new QueryBuilder($definitie);
        $sql = $b->toSql($limiet);
        $rijen = $this->pdo->query($this->sqlVoorSqlite($sql))->fetchAll(\PDO::FETCH_ASSOC);
        return ['sql' => $sql, 'rijen' => $rijen, 'fouten' => $b->getErrors()];
    }

    private function kolom(array $rijen, string $kolom): array
    {
        return array_map(fn($r) => $r[$kolom], $rijen);
    }

    private function klantOrder(string $type = 'INNER'): array
    {
        return ['name' => 'tblKlant', 'joins' => [$this->join('tblOrder', '[tblKlant].[ID] = [tblOrder].[fkKlant]', $type)]];
    }

    /** Vijf tabellen in een keten: klant - order - regel - product - categorie. */
    private function keten(string $type = 'INNER'): array
    {
        return [
            ['name' => 'tblKlant', 'joins' => [$this->join('tblOrder', '[tblKlant].[ID] = [tblOrder].[fkKlant]', $type)]],
            ['name' => 'tblOrder', 'joins' => [$this->join('tblOrderRegel', '[tblOrder].[ID] = [tblOrderRegel].[fkOrder]', $type)]],
            ['name' => 'tblOrderRegel', 'joins' => [$this->join('tblProduct', '[tblOrderRegel].[fkProduct] = [tblProduct].[ID]', $type)]],
            ['name' => 'tblProduct', 'joins' => [$this->join('tblCategorie', '[tblProduct].[fkCategorie] = [tblCategorie].[ID]', $type)]],
        ];
    }

    // ---------- 1. één tabel ----------

    public function testEenTabelZonderVeldenGeeftSelectSter(): void
    {
        $r = $this->draai(['tables' => [['name' => 'tblKlant']]]);
        $this->assertSame("SELECT *\nFROM [tblKlant]", $r['sql']);
        $this->assertCount(4, $r['rijen']);
    }

    public function testEenTabelMetVeldenEnAlias(): void
    {
        $r = $this->draai(['tables' => [['name' => 'tblKlant']], 'fields' => [$this->veld('tblKlant', 'Naam'), $this->veld('tblKlant', 'Plaats', 'Woonplaats')]]);
        $this->assertStringContainsString('SELECT [tblKlant].[Naam], [tblKlant].[Plaats] AS [Woonplaats]', $r['sql']);
        $this->assertSame(['Naam', 'Woonplaats'], array_keys($r['rijen'][0]));
    }

    public function testAliasMetSpatiePuntEnHaken(): void
    {
        $r = $this->draai(['tables' => [['name' => 'tblKlant']], 'fields' => [
            $this->veld('tblKlant', 'Naam', 'Mijn naam'), $this->veld('tblKlant', 'Plaats', 'a.b'), $this->veld('tblKlant', 'Korting', '[x]; DROP')]]);
        $this->assertSame(['Mijn naam', 'a_b', 'x; DROP'], array_keys($r['rijen'][0]));
        $this->assertCount(4, $r['rijen']);
    }

    public function testOnzichtbaarVeldDoetNietMee(): void
    {
        $r = $this->draai(['tables' => [['name' => 'tblKlant']], 'fields' => [$this->veld('tblKlant', 'Naam'), ['table' => 'tblKlant', 'field' => 'Plaats', 'visible' => false]]]);
        $this->assertSame(['Naam'], array_keys($r['rijen'][0]));
    }

    public function testDistinctTopEnSortering(): void
    {
        $r = $this->draai([
            'tables' => [['name' => 'tblKlant']], 'fields' => [$this->veld('tblKlant', 'Actief')], 'distinct' => true,
            'sorting' => [['table' => 'tblKlant', 'field' => 'Actief', 'direction' => 'desc']],
        ]);
        $this->assertStringContainsString('SELECT DISTINCT [tblKlant].[Actief]', $r['sql']);
        $this->assertSame([1, 0], array_map('intval', $this->kolom($r['rijen'], 'Actief')));

        $r = $this->draai(['tables' => [['name' => 'tblKlant']], 'fields' => [$this->veld('tblKlant', 'Naam')], 'distinct' => true, 'topN' => 2,
            'sorting' => [['table' => 'tblKlant', 'field' => 'Naam', 'direction' => 'ASC']]]);
        $this->assertStringContainsString('SELECT DISTINCT TOP 2 ', $r['sql'], 'Access: TOP komt na DISTINCT');
        $this->assertSame(['Bakker', 'De Vries'], $this->kolom($r['rijen'], 'Naam'));
    }

    public function testExplicieteLimietGaatVoorTopN(): void
    {
        $r = $this->draai(['tables' => [['name' => 'tblKlant']], 'fields' => [$this->veld('tblKlant', 'Naam')], 'topN' => 3], 1);
        $this->assertStringContainsString('SELECT TOP 1 ', $r['sql']);
        $this->assertCount(1, $r['rijen']);
    }

    public function testSorteerrichtingOnzinWordtAsc(): void
    {
        $r = $this->draai(['tables' => [['name' => 'tblKlant']], 'fields' => [$this->veld('tblKlant', 'Naam')],
            'sorting' => [['table' => 'tblKlant', 'field' => 'Naam', 'direction' => 'DESC; DROP TABLE x']]]);
        $this->assertStringContainsString('[tblKlant].[Naam] ASC', $r['sql']);
        $this->assertSame('Bakker', $r['rijen'][0]['Naam']);
    }

    // ---------- 2. twee gerelateerde tabellen ----------

    public function testInnerJoinGeeftAlleenKlantenMetOrder(): void
    {
        $r = $this->draai(['tables' => [$this->klantOrder()], 'fields' => [$this->veld('tblKlant', 'Naam'), $this->veld('tblOrder', 'Totaal')]]);
        $this->assertStringContainsString("FROM ([tblKlant]\nINNER JOIN [tblOrder] ON [tblKlant].[ID] = [tblOrder].[fkKlant])", $r['sql']);
        $this->assertCount(4, $r['rijen'], 'order 5 heeft geen klant en valt weg');
        $this->assertEmpty($r['fouten']);
    }

    public function testLeftJoinBehoudtKlantenZonderOrder(): void
    {
        $r = $this->draai(['tables' => [$this->klantOrder('LEFT')], 'fields' => [$this->veld('tblKlant', 'Naam'), $this->veld('tblOrder', 'Totaal')]]);
        $this->assertCount(5, $r['rijen'], 'Bakker heeft geen order en blijft staan');
    }

    public function testRightJoinBehoudtOrdersZonderKlant(): void
    {
        $r = $this->draai(['tables' => [$this->klantOrder('RIGHT')], 'fields' => [$this->veld('tblKlant', 'Naam'), $this->veld('tblOrder', 'Totaal')]]);
        $this->assertCount(5, $r['rijen'], 'order 5 zonder klant blijft staan');
    }

    public function testJoinTypeOnzinWordtInner(): void
    {
        $r = $this->draai(['tables' => [['name' => 'tblKlant', 'joins' => [$this->join('tblOrder', '[tblKlant].[ID] = [tblOrder].[fkKlant]', 'CROSS; DROP')]]], 'fields' => [$this->veld('tblKlant', 'Naam')]]);
        $this->assertStringContainsString('INNER JOIN', $r['sql']);
    }

    public function testJoinMetEersteTabelZonderJoinsEnTweedeMetJoin(): void
    {
        // zoals de ontwerper het doet: elke tabel in tables[], de join staat bij de tabel waarvandaan hij is gelegd
        $r = $this->draai(['tables' => [['name' => 'tblKlant'], ['name' => 'tblOrder']], 'fields' => [$this->veld('tblKlant', 'Naam')]]);
        $this->assertSame("SELECT [tblKlant].[Naam]\nFROM [tblKlant]", $r['sql'], 'een ongebruikte, niet verbonden tabel blijft buiten de FROM');
        $this->assertEmpty($r['fouten']);
    }

    // ---------- 3. meerdere tabellen ----------

    public function testKetenVanVijfTabellen(): void
    {
        $r = $this->draai([
            'tables' => $this->keten(),
            'fields' => [$this->veld('tblKlant', 'Naam'), $this->veld('tblProduct', 'Omschrijving'), $this->veld('tblCategorie', 'Code'), $this->veld('tblOrderRegel', 'Aantal')],
            'sorting' => [['table' => 'tblKlant', 'field' => 'Naam', 'direction' => 'ASC'], ['table' => 'tblProduct', 'field' => 'Omschrijving', 'direction' => 'ASC']],
        ]);
        $this->assertStringContainsString('FROM (((([tblKlant]', $r['sql'], 'vier joins = vier openende haken (Access)');
        $this->assertSame(4, preg_match_all('/INNER JOIN/', $r['sql']));
        $this->assertEmpty($r['fouten']);
        $this->assertSame(['De Vries', 'Jansen', 'Jansen', 'Jansen'], $this->kolom($r['rijen'], 'Naam'), 'regels met een product zonder categorie vallen weg bij INNER');
    }

    public function testKetenMetLeftJoinsHoudtRegelsZonderCategorie(): void
    {
        $r = $this->draai(['tables' => $this->keten('LEFT'), 'fields' => [$this->veld('tblKlant', 'Naam'), $this->veld('tblCategorie', 'Code')]]);
        $this->assertGreaterThan(4, count($r['rijen']));
        $this->assertEmpty($r['fouten']);
    }

    public function testSterVanTabellenRondOmEenBasistabel(): void
    {
        $r = $this->draai(['tables' => [['name' => 'tblOrder', 'joins' => [
            $this->join('tblKlant', '[tblOrder].[fkKlant] = [tblKlant].[ID]'),
            $this->join('tblOrderRegel', '[tblOrder].[ID] = [tblOrderRegel].[fkOrder]'),
        ]]], 'fields' => [$this->veld('tblOrder', 'ID'), $this->veld('tblKlant', 'Naam'), $this->veld('tblOrderRegel', 'Aantal')]]);
        $this->assertStringContainsString('FROM (([tblOrder]', $r['sql'], 'twee joins = twee openende haken');
        $this->assertCount(4, $r['rijen']);
    }

    public function testJoinMetTweeVoorwaardenInDeOn(): void
    {
        $r = $this->draai(['tables' => [['name' => 'tblKlant', 'joins' => [$this->join('tblOrder', '[tblKlant].[ID] = [tblOrder].[fkKlant] AND [tblOrder].[Totaal] > 60')]]],
            'fields' => [$this->veld('tblKlant', 'Naam'), $this->veld('tblOrder', 'Totaal')]]);
        $this->assertCount(2, $r['rijen'], 'orders van 100 en 75');
    }

    public function testGevaarlijkeOnVoorwaardeWordtGeweigerd(): void
    {
        $b = new QueryBuilder(['tables' => [['name' => 'tblKlant', 'joins' => [$this->join('tblOrder', '1=1; DROP TABLE tblKlant')]]], 'fields' => [$this->veld('tblKlant', 'Naam')]]);
        $sql = $b->toSql();
        $this->assertStringContainsString('ON 1=0', $sql);
        $this->assertNotEmpty($b->getErrors());
    }

    // ---------- 4. voorwaarden ----------

    private function metVoorwaarde(array $cond, array $extra = []): array
    {
        return $this->draai(['tables' => [['name' => 'tblKlant']], 'fields' => [$this->veld('tblKlant', 'Naam')], 'conditions' => array_merge([$cond], $extra)]);
    }

    public function testTekstVoorwaarden(): void
    {
        $this->assertSame(['Jansen'], $this->kolom($this->metVoorwaarde($this->cond('tblKlant', 'Naam', '=', 'Jansen'))['rijen'], 'Naam'));
        $this->assertCount(3, $this->metVoorwaarde($this->cond('tblKlant', 'Naam', '<>', 'Jansen'))['rijen']);
        $this->assertSame(['Jansen'], $this->kolom($this->metVoorwaarde($this->cond('tblKlant', 'Naam', 'contains', 'ans'))['rijen'], 'Naam'));
        $this->assertSame(['Bakker'], $this->kolom($this->metVoorwaarde($this->cond('tblKlant', 'Naam', 'starts', 'Bak'))['rijen'], 'Naam'));
        $this->assertSame(['Bakker'], $this->kolom($this->metVoorwaarde($this->cond('tblKlant', 'Naam', 'ends', 'ker'))['rijen'], 'Naam'));
    }

    public function testApostrofInEenWaardeGeeftGeldigeSql(): void
    {
        $this->assertSame(["O'Brien"], $this->kolom($this->metVoorwaarde($this->cond('tblKlant', 'Naam', '=', "O'Brien"))['rijen'], 'Naam'));
    }

    public function testInjectiepogingInEenTekstwaardeBlijftTekst(): void
    {
        $r = $this->metVoorwaarde($this->cond('tblKlant', 'Naam', '=', "x' OR '1'='1"));
        $this->assertCount(0, $r['rijen']);
        $r = $this->metVoorwaarde($this->cond('tblKlant', 'Naam', 'contains', "%' OR 1=1 --"));
        $this->assertCount(0, $r['rijen']);
    }

    public function testLeegEnNietLeegOpTekst(): void
    {
        $this->assertCount(2, $this->metVoorwaarde($this->cond('tblKlant', 'Plaats', 'empty'))['rijen'], 'NULL en lege tekst');
        $this->assertCount(2, $this->metVoorwaarde($this->cond('tblKlant', 'Plaats', 'notempty'))['rijen']);
    }

    public function testLeegOpGetalVergelijktNietMetLegeTekst(): void
    {
        $r = $this->metVoorwaarde($this->cond('tblKlant', 'Korting', 'empty', '', '', 'number'));
        $this->assertStringContainsString('[tblKlant].[Korting] IS NULL', $r['sql']);
        $this->assertStringNotContainsString("''", $r['sql'], "Access: een getal met '' vergelijken is een 'Data type mismatch'");
        $this->assertSame(['De Vries'], $this->kolom($r['rijen'], 'Naam'));
        $r = $this->metVoorwaarde($this->cond('tblKlant', 'Korting', 'notempty', '', '', 'number'));
        $this->assertCount(3, $r['rijen']);
    }

    public function testGetalVoorwaarden(): void
    {
        $this->assertCount(1, $this->metVoorwaarde($this->cond('tblKlant', 'Korting', '=', '5,5', '', 'number'))['rijen'], 'komma als decimaalteken');
        $this->assertCount(1, $this->metVoorwaarde($this->cond('tblKlant', 'Korting', '>', '5', '', 'number'))['rijen']);
        $this->assertCount(2, $this->metVoorwaarde($this->cond('tblKlant', 'Korting', '<=', '2', '', 'number'))['rijen']);
        $this->assertCount(2, $this->metVoorwaarde($this->cond('tblKlant', 'Korting', 'between', '1', '6', 'number'))['rijen']);
    }

    public function testBetweenZonderTweedeWaardeWordtGroterOfGelijk(): void
    {
        $r = $this->metVoorwaarde($this->cond('tblKlant', 'Korting', 'between', '2', '', 'number'));
        $this->assertStringContainsString('>= 2', $r['sql']);
        $this->assertStringNotContainsString('BETWEEN', $r['sql']);
        $this->assertCount(2, $r['rijen']);
    }

    public function testGetalWaardeDieGeenGetalIsWordtGemeld(): void
    {
        $b = new QueryBuilder(['tables' => [['name' => 'tblKlant']], 'fields' => [$this->veld('tblKlant', 'Naam')], 'conditions' => [$this->cond('tblKlant', 'Korting', '=', '1 OR 1=1', '', 'number')]]);
        $sql = $b->toSql();
        $this->assertStringNotContainsString('WHERE', $sql);
        $this->assertCount(1, $b->getErrors());
        $this->assertStringContainsString('geen getal', $b->getErrors()[0]);
    }

    public function testDatumWaardeDieGeenDatumIsWordtGemeld(): void
    {
        $b = new QueryBuilder(['tables' => [['name' => 'tblOrder']], 'fields' => [$this->veld('tblOrder', 'ID')], 'conditions' => [$this->cond('tblOrder', 'Datum', 'before', 'gisteren-ish', '', 'date')]]);
        $sql = $b->toSql();
        $this->assertStringNotContainsString('NULL', $sql, 'vroeger: Datum < NULL, altijd onwaar zonder melding');
        $this->assertCount(1, $b->getErrors());
        $this->assertStringContainsString('geen datum', $b->getErrors()[0]);
    }

    public function testJaNeeOpEenJaNeeVeld(): void
    {
        $this->assertCount(3, $this->metVoorwaarde($this->cond('tblKlant', 'Actief', 'yes', '', '', 'boolean'))['rijen']);
        $this->assertCount(1, $this->metVoorwaarde($this->cond('tblKlant', 'Actief', 'no', '', '', 'boolean'))['rijen']);
    }

    private function metOrderVoorwaarde(string $op, string $v = '', string $v2 = ''): array
    {
        return $this->draai(['tables' => [['name' => 'tblOrder']], 'fields' => [$this->veld('tblOrder', 'ID')], 'conditions' => [$this->cond('tblOrder', 'Datum', $op, $v, $v2, 'date')]]);
    }

    public function testDatumVoorwaarden(): void
    {
        $this->assertCount(1, $this->metOrderVoorwaarde('=', '2020-01-01')['rijen']);
        $this->assertCount(1, $this->metOrderVoorwaarde('before', '2021-01-01')['rijen']);
        $this->assertCount(1, $this->metOrderVoorwaarde('=', '01-01-2020')['rijen'], 'Nederlandse notatie');
        $this->assertCount(5, $this->metOrderVoorwaarde('after', '2019-12-31')['rijen']);
        $this->assertCount(1, $this->metOrderVoorwaarde('between', '2019-01-01', '2020-12-31')['rijen']);
    }

    public function testVandaagGeeftDeOrderVanVandaag(): void
    {
        $r = $this->metOrderVoorwaarde('today');
        $this->assertSame(['1'], array_map('strval', $this->kolom($r['rijen'], 'ID')), 'vroeger lege reeks: >= vandaag AND < vandaag');
    }

    public function testDezeWeekEnDezeMaandBevattenVandaag(): void
    {
        $week = array_map('intval', $this->kolom($this->metOrderVoorwaarde('thisweek')['rijen'], 'ID'));
        $this->assertTrue(in_array(1, $week, true), 'de order van vandaag staat in deze week');
        $maand = array_map('intval', $this->kolom($this->metOrderVoorwaarde('thismonth')['rijen'], 'ID'));
        $this->assertTrue(in_array(1, $maand, true) && in_array(4, $maand, true), 'vandaag en de 15e staan in deze maand');
        $this->assertFalse(in_array(5, $maand, true));
    }

    public function testPeriodeVoorwaardeBlijftBinnenHaakjesBijOr(): void
    {
        $r = $this->metOrderVoorwaarde('today');
        $this->assertMatchesRegularExpression('/\(\[tblOrder\]\.\[Datum\] >= .* AND \[tblOrder\]\.\[Datum\] < .*\)/', $r['sql']);
        $r = $this->draai(['tables' => [['name' => 'tblOrder']], 'fields' => [$this->veld('tblOrder', 'ID')], 'conditions' => [
            $this->cond('tblOrder', 'Totaal', '>', '1000', '', 'number'),
            $this->cond('tblOrder', 'Datum', 'today', '', '', 'date', 'OR'),
        ]]);
        $this->assertCount(1, $r['rijen'], 'niets boven 1000 OF vandaag');
    }

    public function testEenVoorwaardeZonderWaardeWordtOvergeslagen(): void
    {
        $r = $this->metVoorwaarde($this->cond('tblKlant', 'Naam', '=', ''));
        $this->assertStringNotContainsString('WHERE', $r['sql']);
        $this->assertCount(4, $r['rijen']);
    }

    public function testOnbekendeOperatorGeeftGeenWhere(): void
    {
        $r = $this->metVoorwaarde($this->cond('tblKlant', 'Naam', 'bestaatniet', 'x'));
        $this->assertStringNotContainsString('WHERE', $r['sql']);
    }

    public function testAndOrEnHaakjes(): void
    {
        $r = $this->draai(['tables' => [['name' => 'tblKlant']], 'fields' => [$this->veld('tblKlant', 'Naam')], 'conditions' => [
            $this->cond('tblKlant', 'Naam', '=', 'Jansen', '', 'text', 'AND', ['prefix' => '(']),
            $this->cond('tblKlant', 'Naam', '=', 'Bakker', '', 'text', 'OR', ['suffix' => ')']),
            $this->cond('tblKlant', 'Actief', 'yes', '', '', 'boolean', 'AND'),
        ]]);
        $this->assertSame(['Jansen', 'Bakker'], $this->kolom($r['rijen'], 'Naam'));
        $r = $this->draai(['tables' => [['name' => 'tblKlant']], 'fields' => [$this->veld('tblKlant', 'Naam')], 'conditions' => [
            $this->cond('tblKlant', 'Naam', '=', 'Jansen'), $this->cond('tblKlant', 'Naam', '=', 'Bakker', '', 'text', 'ONZIN'),
        ]]);
        $this->assertStringContainsString("AND [tblKlant].[Naam] = 'Bakker'", $r['sql'], 'een onbekende logica wordt AND');
    }

    public function testHaakjesVeldAcceptteertAlleenHaakjes(): void
    {
        $b = new QueryBuilder(['tables' => [['name' => 'tblKlant']], 'fields' => [$this->veld('tblKlant', 'Naam')], 'conditions' => [
            $this->cond('tblKlant', 'Naam', '=', 'Jansen', '', 'text', 'AND', ['prefix' => '1=1; DROP TABLE tblKlant; --']),
        ]]);
        $sql = $b->toSql();
        $this->assertStringNotContainsString('DROP', $sql);
        $this->assertStringContainsString("WHERE [tblKlant].[Naam] = 'Jansen'", $sql);
        $this->assertNotEmpty($b->getErrors());
        $r = $this->draai(['tables' => [['name' => 'tblKlant']], 'fields' => [$this->veld('tblKlant', 'Naam')], 'conditions' => [
            $this->cond('tblKlant', 'Naam', '=', 'Jansen', '', 'text', 'AND', ['prefix' => ' ( (', 'suffix' => ') ) ']),
        ]]);
        $this->assertEmpty($r['fouten']);
        $this->assertCount(1, $r['rijen']);
    }

    public function testVoorwaardeOpEenGekoppeldeTabel(): void
    {
        $r = $this->draai(['tables' => [$this->klantOrder()], 'fields' => [$this->veld('tblKlant', 'Naam'), $this->veld('tblOrder', 'Totaal')], 'conditions' => [$this->cond('tblOrder', 'Totaal', '>=', '75', '', 'number')]]);
        $this->assertCount(2, $r['rijen']);
    }

    public function testParameterInEenVoorwaarde(): void
    {
        $b = new QueryBuilder(['tables' => [['name' => 'tblKlant']], 'fields' => [$this->veld('tblKlant', 'Naam')], 'conditions' => [$this->cond('tblKlant', 'Plaats', '=', '@stad')]]);
        $b->setParameters(['stad' => 'Zwolle']);
        $rijen = $this->pdo->query($this->sqlVoorSqlite($b->toSql()))->fetchAll(\PDO::FETCH_ASSOC);
        $this->assertSame(["O'Brien"], $this->kolom($rijen, 'Naam'));

        $b = new QueryBuilder(['tables' => [['name' => 'tblKlant']], 'fields' => [$this->veld('tblKlant', 'Naam')], 'conditions' => [$this->cond('tblKlant', 'Plaats', '=', '@stad')]]);
        $this->assertStringNotContainsString('WHERE', $b->toSql(), 'een parameter zonder waarde laat de voorwaarde vervallen');
    }

    public function testVoorwaardenVanOudeStijlOpHetVeld(): void
    {
        $r = $this->draai(['tables' => [['name' => 'tblKlant']], 'fields' => [
            ['table' => 'tblKlant', 'field' => 'Naam', 'visible' => true, 'filter' => ['operator' => 'starts', 'value' => 'Ja']],
        ]]);
        $this->assertSame(['Jansen'], $this->kolom($r['rijen'], 'Naam'));
    }

    // ---------- 5. groepering ----------

    public function testGroepering(): void
    {
        $r = $this->draai(['tables' => [$this->klantOrder()], 'fields' => [$this->veld('tblKlant', 'Naam')], 'grouping' => [['table' => 'tblKlant', 'field' => 'Naam']]]);
        $this->assertStringContainsString('GROUP BY [tblKlant].[Naam]', $r['sql']);
        $this->assertCount(3, $r['rijen'], 'een rij per klant met order');
    }

    public function testGroeperingNeemtZichtbareVeldenEnVoorwaardeveldenMee(): void
    {
        $r = $this->draai(['tables' => [$this->klantOrder()], 'fields' => [$this->veld('tblKlant', 'Naam'), $this->veld('tblOrder', 'Datum')],
            'conditions' => [$this->cond('tblOrder', 'Totaal', '>', '10', '', 'number')], 'grouping' => [['table' => 'tblKlant', 'field' => 'Naam']]]);
        $this->assertStringContainsString('GROUP BY [tblKlant].[Naam], [tblOrder].[Totaal], [tblOrder].[Datum]', $r['sql']);
        $this->assertEmpty($r['fouten']);
    }

    public function testGroeperingAlsGewoneTekstVeldnaam(): void
    {
        $r = $this->draai(['tables' => [['name' => 'tblKlant']], 'fields' => [$this->veld('tblKlant', 'Plaats')], 'grouping' => ['Plaats']]);
        $this->assertStringContainsString('GROUP BY [Plaats]', $r['sql']);
    }

    // ---------- 6. geen geldige SQL mogelijk: een duidelijke melding ----------

    public function testTabelZonderRelatieGeeftEenMeldingInPlaatsVanKapotteSql(): void
    {
        $b = new QueryBuilder(['tables' => [['name' => 'tblKlant'], ['name' => 'tblProduct']], 'fields' => [$this->veld('tblKlant', 'Naam'), $this->veld('tblProduct', 'Omschrijving')]]);
        $b->toSql();
        $this->assertCount(1, $b->getErrors());
        $this->assertStringContainsString("'tblProduct'", $b->getErrors()[0]);
        $this->assertStringContainsString('niet verbonden', $b->getErrors()[0]);
    }

    public function testTabelZonderRelatieDieAlleenInEenVoorwaardeStaat(): void
    {
        $b = new QueryBuilder(['tables' => [['name' => 'tblKlant'], ['name' => 'tblProduct']], 'fields' => [$this->veld('tblKlant', 'Naam')], 'conditions' => [$this->cond('tblProduct', 'Prijs', '>', '1', '', 'number')]]);
        $b->toSql();
        $this->assertCount(1, $b->getErrors());
    }

    public function testOnzichtbaarVeldVanOngekoppeldeTabelIsGeenFout(): void
    {
        $b = new QueryBuilder(['tables' => [['name' => 'tblKlant'], ['name' => 'tblProduct']], 'fields' => [$this->veld('tblKlant', 'Naam'), ['table' => 'tblProduct', 'field' => 'Prijs', 'visible' => false]]]);
        $b->toSql();
        $this->assertEmpty($b->getErrors());
    }

    public function testOnClauseNaarTabelBuitenDeFromWordtGemeld(): void
    {
        $b = new QueryBuilder(['tables' => [['name' => 'tblKlant', 'joins' => [$this->join('tblOrder', '[tblKlant].[ID] = [tblCategorie].[ID]')]]], 'fields' => [$this->veld('tblKlant', 'Naam')]]);
        $b->toSql();
        $this->assertNotEmpty($b->getErrors());
    }

    public function testNogNietOndersteundZelfkoppelingWordtGemeld(): void
    {
        $b = new QueryBuilder(['tables' => [['name' => 'tblKlant', 'joins' => [$this->join('tblKlant', '[tblKlant].[ID] = [tblKlant].[ID]')]]], 'fields' => [$this->veld('tblKlant', 'Naam')]]);
        $b->toSql();
        $this->assertNotEmpty($b->getErrors());
        $this->assertStringContainsString('twee keer', $b->getErrors()[0]);
    }

    public function testLegeDefinitieGeeftGeenFoutMaarOokGeenFrom(): void
    {
        $b = new QueryBuilder([]);
        $this->assertSame('SELECT *', $b->toSql());
        $this->assertEmpty($b->getErrors());
        $b = new QueryBuilder(['tables' => [['name' => '']], 'fields' => [$this->veld('', '')]]);
        $this->assertSame('SELECT *', $b->toSql());
    }

    // ---------- 7. SQL-modus ----------

    public function testSqlModusGeeftDeTekstTerugEnPastAlleenTopToe(): void
    {
        $b = new QueryBuilder(['mode' => 'sql', 'rawSql' => 'SELECT Naam FROM tblKlant']);
        $this->assertSame('SELECT Naam FROM tblKlant', $b->toSql());
        $this->assertSame('SELECT TOP 5 Naam FROM tblKlant', $b->toSql(5));
        $b = new QueryBuilder(['mode' => 'sql', 'rawSql' => 'SELECT TOP 2 Naam FROM tblKlant']);
        $this->assertSame('SELECT TOP 2 Naam FROM tblKlant', $b->toSql(5), 'een bestaande TOP blijft');
    }

    // ---------- 8. onderlinge samenhang ----------

    public function testAlleOnderdelenSamenInEenRapport(): void
    {
        $r = $this->draai([
            'tables' => $this->keten('LEFT'),
            'fields' => [$this->veld('tblKlant', 'Naam', 'Klant'), $this->veld('tblProduct', 'Omschrijving', 'Product'), $this->veld('tblOrderRegel', 'Aantal')],
            'conditions' => [
                $this->cond('tblOrderRegel', 'Aantal', '>=', '1', '', 'number'),
                $this->cond('tblKlant', 'Actief', 'yes', '', '', 'boolean'),
            ],
            'distinct' => true, 'topN' => 3,
            'sorting' => [['table' => 'tblOrderRegel', 'field' => 'Aantal', 'direction' => 'DESC']],
        ]);
        $this->assertEmpty($r['fouten']);
        $this->assertSame(['Klant', 'Product', 'Aantal'], array_keys($r['rijen'][0]));
        $this->assertLessThan(4, count($r['rijen']));
        $this->assertSame(5, (int) $r['rijen'][0]['Aantal']);
    }

    public function testDezelfdeDefinitieTweeKeerGeeftDezelfdeSql(): void
    {
        $def = ['tables' => $this->keten(), 'fields' => [$this->veld('tblKlant', 'Naam')], 'conditions' => [$this->cond('tblKlant', 'Naam', 'contains', 'a')]];
        $this->assertSame((new QueryBuilder($def))->toSql(), (new QueryBuilder($def))->toSql());
    }

    // ---------- 9. heen en terug: SQL die de ontwerper maakt, leest de parser weer in ----------

    public function testSqlUitDeOntwerperKomtStructureelTerugUitDeParser(): void
    {
        $def = ['tables' => $this->keten('LEFT'), 'distinct' => true, 'topN' => 5,
            'fields' => [$this->veld('tblKlant', 'Naam'), $this->veld('tblProduct', 'Omschrijving', 'Product')],
            'sorting' => [['table' => 'tblKlant', 'field' => 'Naam', 'direction' => 'DESC']]];
        $p = SqlParser::parse((new QueryBuilder($def))->toSql());
        $this->assertNotNull($p, 'de parser leest zijn eigen bron');
        $this->assertSame(['tblKlant', 'tblOrder', 'tblOrderRegel', 'tblProduct', 'tblCategorie'], array_map(fn($t) => $t['name'] ?? $t, $p['tables']));
        $this->assertCount(4, $p['joins']);
        $this->assertSame('LEFT', $p['joins'][0]['type']);
        $this->assertTrue((bool) $p['distinct']);
        $this->assertSame(5, $p['topN']);
        $this->assertSame('desc', $p['sorting'][0]['direction']);
        $this->assertSame('Product', $p['fields'][1]['alias']);
    }

    public function testParserHerbouwdTotDezelfdeSql(): void
    {
        $def = ['tables' => $this->keten(), 'fields' => [$this->veld('tblKlant', 'Naam'), $this->veld('tblOrderRegel', 'Aantal', 'Stuks')],
            'sorting' => [['table' => 'tblKlant', 'field' => 'Naam', 'direction' => 'ASC']]];
        $sql = (new QueryBuilder($def))->toSql();
        $p = SqlParser::parse($sql);
        $tables = [];
        foreach ($p['tables'] as $i => $t) {
            $naam = $t['name'] ?? $t;
            $tables[] = $i === 0 ? ['name' => $naam, 'joins' => $p['joins']] : ['name' => $naam];
        }
        $terug = ['tables' => $tables, 'fields' => $p['fields'], 'sorting' => $p['sorting'], 'grouping' => $p['grouping']];
        $this->assertSame($sql, (new QueryBuilder($terug))->toSql(), 'definitie -> SQL -> parser -> definitie -> SQL is stabiel');
    }

    public function testNogNietOndersteundVoorwaardenWordenNietTeruggelezen(): void
    {
        // Wat de parser niet teruglas, valt weg zodra de gebruiker van SQL-modus terug naar het ontwerp gaat. Dit legt dat vast; wie
        // WHERE-parsing bouwt, mag deze test omdraaien.
        $sql = (new QueryBuilder(['tables' => [['name' => 'tblKlant']], 'fields' => [$this->veld('tblKlant', 'Naam')],
            'conditions' => [$this->cond('tblKlant', 'Naam', '=', 'x')]]))->toSql();
        $this->assertStringContainsString('WHERE', $sql);
        $this->assertFalse(array_key_exists('conditions', SqlParser::parse($sql)));
    }

    public function testParserWeigertWatHijNietKan(): void
    {
        $this->assertNull(SqlParser::parse('SELECT a FROM t UNION SELECT b FROM u'));
        $this->assertNull(SqlParser::parse('WITH x AS (SELECT 1) SELECT * FROM x'));
        $this->assertNull(SqlParser::parse('SELECT a INTO nieuw FROM t'));
    }

    // ---------- 10. SQL-modus: TOP op de goede plek en alleen een enkele SELECT ----------

    public function testTopKomtNaDistinctInSqlModus(): void
    {
        $this->assertSame('SELECT DISTINCT TOP 5 Naam FROM tblKlant', QueryBuilder::addTop('SELECT DISTINCT Naam FROM tblKlant', 5));
        $this->assertSame('SELECT DISTINCTROW TOP 5 Naam FROM tblKlant', QueryBuilder::addTop('SELECT DISTINCTROW Naam FROM tblKlant', 5));
        $this->assertSame('SELECT TOP 5 Naam FROM tblKlant', QueryBuilder::addTop('SELECT Naam FROM tblKlant', 5));
        $this->assertSame('SELECT DISTINCT TOP 2 Naam FROM tblKlant', QueryBuilder::addTop('SELECT DISTINCT TOP 2 Naam FROM tblKlant', 5), 'een bestaande TOP blijft');
        $this->assertSame('select TOP 3 a from t', QueryBuilder::addTop('select a from t', 3));
        $this->assertSame('SELECT a FROM t', QueryBuilder::addTop('SELECT a FROM t', 0));
        $b = new QueryBuilder(['mode' => 'sql', 'rawSql' => 'SELECT DISTINCT Plaats FROM tblKlant']);
        $r = $this->pdo->query($this->sqlVoorSqlite($b->toSql(2)))->fetchAll(\PDO::FETCH_ASSOC);
        $this->assertStringContainsString('SELECT DISTINCT TOP 2 ', $b->toSql(2), 'vroeger: SELECT TOP 2 DISTINCT (ongeldig in Access)');
        $this->assertCount(2, $r);
    }

    public function testRawSqlAlleenEenEnkeleSelect(): void
    {
        $this->assertNull(QueryBuilder::rawSqlError('SELECT Naam FROM tblKlant WHERE Plaats = \'Utrecht\''));
        $this->assertNull(QueryBuilder::rawSqlError("  select * from tblKlant;  "), 'een afsluitende puntkomma mag');
        $this->assertSame('SQL-query is verplicht', QueryBuilder::rawSqlError('  '));
        $this->assertSame('Alleen SELECT-queries zijn toegestaan', QueryBuilder::rawSqlError('DELETE FROM tblKlant'));
        foreach (['SELECT * INTO nieuw FROM tblKlant', 'SELECT 1; DROP TABLE tblKlant', 'SELECT * FROM t WHERE a=1 UPDATE x SET y=1',
                  'SELECT * FROM tblKlant; SELECT * FROM tblOrder'] as $gevaarlijk) {
            $this->assertNotNull(QueryBuilder::rawSqlError($gevaarlijk), $gevaarlijk);
        }
        $this->assertStringContainsString('INTO', (string) QueryBuilder::rawSqlError('SELECT * INTO nieuw FROM tblKlant'), 'SELECT ... INTO maakt een tabel aan in Access');
    }

    public function testFullJoinBestaatNietInAccessEnWordtLeftMetEenMelding(): void
    {
        $def = ['tables' => [['name' => 'tblKlant', 'joins' => [$this->join('tblOrder', '[tblKlant].[ID] = [tblOrder].[fkKlant]', 'FULL')]]], 'fields' => [$this->veld('tblKlant', 'Naam')]];
        $b = new QueryBuilder($def);
        $sql = $b->toSql();
        $this->assertStringContainsString('LEFT JOIN', $sql);
        $this->assertStringNotContainsString('FULL', $sql);
        $this->assertCount(1, $b->getErrors());
        $this->assertStringContainsString('Access', $b->getErrors()[0]);
        // op SQLite (heeft FULL JOIN sinds 3.39) blijft het een FULL JOIN
        $b = new QueryBuilder($def, $this->pdo);
        $sql = $b->toSql();
        $this->assertStringContainsString('FULL JOIN', $sql);
        $this->assertEmpty($b->getErrors());
    }

    // ---------- 11. SQL zoals Access hem schrijft: zonder blokhaken, met geneste haakjes ----------

    public function testBasistabelBlijftBehoudenBijGenesteHaakjesZonderBlokhaken(): void
    {
        $sql = 'SELECT tblKlant.Naam, tblProduct.Omschrijving FROM ((tblKlant INNER JOIN tblOrder ON tblKlant.ID = tblOrder.fkKlant) '
             . 'INNER JOIN tblOrderRegel ON tblOrder.ID = tblOrderRegel.fkOrder) INNER JOIN tblProduct ON tblOrderRegel.fkProduct = tblProduct.ID';
        $p = SqlParser::parse($sql);
        $this->assertNotNull($p);
        $this->assertSame(['tblKlant', 'tblOrder', 'tblOrderRegel', 'tblProduct'], array_map(fn($t) => $t['name'] ?? $t, $p['tables']), 'vroeger ontbrak tblKlant');
        $this->assertCount(3, $p['joins']);
    }

    public function testEnkeleHaakjesEnGeenHaakjesGevenDezelfdeTabellen(): void
    {
        $zonder = SqlParser::parse('SELECT a.x FROM tblA INNER JOIN tblB ON tblA.id = tblB.fk INNER JOIN tblC ON tblB.id = tblC.fk');
        $met = SqlParser::parse('SELECT a.x FROM (tblA INNER JOIN tblB ON tblA.id = tblB.fk) INNER JOIN tblC ON tblB.id = tblC.fk');
        $this->assertSame(['tblA', 'tblB', 'tblC'], array_map(fn($t) => $t['name'] ?? $t, $zonder['tables']));
        $this->assertSame(array_map(fn($t) => $t['name'] ?? $t, $zonder['tables']), array_map(fn($t) => $t['name'] ?? $t, $met['tables']));
    }

    public function testKommaJoinWordtNietStilletjesAfgekapt(): void
    {
        $this->assertNull(SqlParser::parse('SELECT tblA.n FROM tblA, tblB WHERE tblA.id = tblB.fk'), 'meer tabellen dan het ontwerp kan tonen: SQL-modus');
        $this->assertNull(SqlParser::parse('SELECT x FROM (SELECT y FROM t) AS d'), 'een afgeleide tabel kan het ontwerp niet tonen');
        $p = SqlParser::parse('SELECT tblA.n FROM tblA INNER JOIN tblB ON tblA.id = tblB.fk AND tblB.x = 1 WHERE tblA.n = 1');
        $this->assertNotNull($p, 'een komma in de WHERE of een AND in de ON is geen komma-join');
    }
}
