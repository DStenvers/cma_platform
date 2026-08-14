<?php
/**
 * MigratieHelperfunctiesTest — een migratie moet zijn eigen hulpfuncties kunnen vinden.
 *
 * Run with: php cma/tests/TestRunner.php MigratieHelperfunctiesTest
 *
 * Waarom deze test bestaat:
 * 8.0.0 en 8.1.0 hadden allebei hun hulpfunctie ONDER de afsluitende `return`
 * gezet, ingepakt in een `if (!function_exists(...))`. PHP hijst alleen een
 * onvoorwaardelijke declaratie naar boven; een voorwaardelijke niet. De functie
 * werd dus nooit gedefinieerd, en het blok stond bovendien achter een `return`
 * en werd nooit bereikt:
 *
 *   ✗ Versie 8.0.0 MISLUKT: Call to undefined function findParentFieldInTable()
 *
 * Dat bleef jaren onzichtbaar omdat de aanroep in een tak zat die alleen draait
 * als er een subform zónder parentField gevonden wordt — op een site waar de
 * definities al goed stonden kwam de regel nooit langs. Een verse installatie
 * loopt er meteen op stuk.
 *
 * De regel die deze test bewaakt is daarom de simpele: declareer boven de code
 * die je aanroept, en nooit achter de `return`. Het onderscheid tussen wél en
 * niet gehesen worden is precies de subtiliteit die hier twee keer misging.
 */

require_once __DIR__ . '/TestRunner.php';
require_once __DIR__ . '/../classes/SchemaHelper.php';

use Cma\SchemaHelper;

class MigratieHelperfunctiesTest extends TestCase
{
    /** @return string[] Alle migratiescripts van het platform. */
    private function migratieBestanden(): array
    {
        return glob(__DIR__ . '/../migrations/*.php') ?: [];
    }

    public function testGeenFunctiedeclaratieAchterDeReturn(): void
    {
        $overtreders = [];

        foreach ($this->migratieBestanden() as $bestand) {
            $regels = file($bestand, FILE_IGNORE_NEW_LINES) ?: [];

            // Een `return` op kolom 0 is de afsluiting van het script zelf; alles
            // wat daarna komt draait niet meer.
            $returnRegel = null;
            foreach ($regels as $nr => $regel) {
                if (preg_match('/^return\b/', $regel)) {
                    $returnRegel = $nr;
                    break;
                }
            }
            if ($returnRegel === null) {
                continue;
            }

            foreach (array_slice($regels, $returnRegel + 1, null, true) as $nr => $regel) {
                if (preg_match('/^\s*(function\s+\w+|if\s*\(\s*!\s*function_exists)/', $regel)) {
                    $overtreders[] = basename($bestand) . ':' . ($nr + 1);
                    break;
                }
            }
        }

        $this->assertEquals([], $overtreders,
            'declaratie achter de return: ' . implode(', ', $overtreders));
    }

    /**
     * Geen globale functie achter een `function_exists`-hek.
     *
     * Zo'n hek verschijnt om één reden: twee migraties hebben dezelfde hulpfunctie
     * nodig en de loper includeert ze in hetzelfde proces, dus de tweede declaratie
     * zou fataal zijn. Het hek lost dat op en levert er twee problemen voor terug.
     *
     * Ten eerste wordt een voorwaardelijke declaratie niet gehesen, dus staat hij
     * onder de aanroep dan bestaat de functie daar niet. Ten tweede — en dat is de
     * echte schade — blijven het twee kopieën die uit elkaar groeien: die van 8.0.0
     * kende drie schrijfwijzen van een foreign key, die van 8.1.0 acht, en welke
     * won hing af van welke migratie als eerste draaide.
     *
     * Een hulpfunctie die twee migraties delen hoort in een klasse. Daar is er één
     * van, en hij is te testen.
     */
    public function testGeenFunctionExistsHekInEenMigratie(): void
    {
        $overtreders = [];
        foreach ($this->migratieBestanden() as $bestand) {
            $bron = (string) file_get_contents($bestand);
            // Alleen een hek dat een DECLARATIE omsluit telt. `function_exists('curl_init')`
            // vraagt of een PHP-extensie aanwezig is — dat is een andere vraag en prima.
            if (preg_match('/if\s*\(\s*!\s*function_exists\s*\([^)]*\)\s*\)\s*\{\s*(\/\/[^\n]*\n\s*)*function\s+\w+/', $bron)) {
                $overtreders[] = basename($bestand);
            }
        }

        $this->assertEquals([], $overtreders,
            'gedeelde hulpfunctie hoort in een klasse, niet achter een hek: '
            . implode(', ', $overtreders));
    }

    // ---- De hulpfunctie zelf, tegen een echte database ------------------------

    private function db(string ...$kolommen): \PDO
    {
        $conn = new \PDO('sqlite::memory:');
        $conn->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $conn->exec('CREATE TABLE tblDeelname (ID INTEGER PRIMARY KEY, '
            . implode(' TEXT, ', $kolommen) . ' TEXT)');
        return $conn;
    }

    public function testVindtDeGebruikelijkeSchrijfwijzen(): void
    {
        foreach ([
            'fkDeelnemers'   => 'fkDeelnemers',      // meervoud, zoals de tabel heet
            'fkDeelnemer'    => 'fkDeelnemer',       // enkelvoud (-s eraf)
            'fk_Deelnemers'  => 'fk_Deelnemers',     // met liggend streepje
            'Deelnemers_ID'  => 'Deelnemers_ID',
            'DeelnemersID'   => 'DeelnemersID',
            'fktblDeelnemers' => 'fktblDeelnemers',  // met tbl-voorvoegsel
        ] as $kolom => $verwacht) {
            $gevonden = SchemaHelper::findForeignKeyTo($this->db($kolom), 'tblDeelname', 'tblDeelnemers');
            $this->assertEquals($verwacht, $gevonden, "kolom $kolom werd niet herkend");
        }
    }

    public function testNederlandsMeervoudOpEn(): void
    {
        // tblOpleidingen -> fkOpleiding
        $this->assertEquals('fkOpleiding',
            SchemaHelper::findForeignKeyTo($this->db('fkOpleiding'), 'tblDeelname', 'tblOpleidingen'));
    }

    public function testDeSpecifiekeNaamWintOngeachtKolomvolgorde(): void
    {
        // Staat de minder specifieke naam eerst in de tabel, dan mag dat de uitkomst
        // niet bepalen — anders hangt de gekozen sleutel af van hoe de tabel toevallig
        // is opgebouwd, en verschilt het antwoord per site.
        $this->assertEquals('fkDeelnemer',
            SchemaHelper::findForeignKeyTo($this->db('Deelnemers_ID', 'fkDeelnemer'), 'tblDeelname', 'tblDeelnemers'));
    }

    public function testHoofdlettersDoenErNietToe(): void
    {
        $this->assertEquals('FKDEELNEMER',
            SchemaHelper::findForeignKeyTo($this->db('FKDEELNEMER'), 'tblDeelname', 'tblDeelnemers'));
    }

    public function testGeenTrefferLevertNull(): void
    {
        $this->assertNull(SchemaHelper::findForeignKeyTo($this->db('omschrijving'), 'tblDeelname', 'tblDeelnemers'));
        // Een tabel die niet bestaat is geen fout maar een leeg antwoord: de
        // migratie laat de definitie dan met rust.
        $this->assertNull(SchemaHelper::findForeignKeyTo($this->db('fkDeelnemer'), 'tblBestaatNiet', 'tblDeelnemers'));
        $this->assertNull(SchemaHelper::findForeignKeyTo($this->db('fkDeelnemer'), 'tblDeelname', ''));
    }
}
