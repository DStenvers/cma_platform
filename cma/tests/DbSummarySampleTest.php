<?php
/**
 * DbSummarySampleTest.php — de recordweergave in de database-structuurtool.
 *
 * De structuurtool vertelde alleen hoe een tabel eruitziet. Per tabel zit er nu een
 * uitklapper naar de eerste records, met alle kolommen erbij — dat is het stuk dat je bij
 * het uitzoeken van een melding mist: is die kolom eigenlijk gevuld, staat er "0" of NULL,
 * hoe ziet zo'n datum er echt uit.
 *
 * Twee dingen moeten daarbij hard vastliggen, want ze gaan over gegevens die van buiten
 * komen:
 *   - de tabelnaam komt uit de URL en gaat de query in, dus die moet letterlijk in de
 *     lijst van SchemaHelper voorkomen;
 *   - de waarden komen uit de database en gaan de HTML in, dus die moeten ontsnapt worden.
 *
 *   php tests/TestRunner.php DbSummarySampleTest
 */

require_once __DIR__ . '/TestRunner.php';
require_once __DIR__ . '/../tools/dbsummary_helpers.php';

class DbSummarySampleTest extends TestCase
{
    private function toolSrc(): string
    {
        return (string) file_get_contents(__DIR__ . '/../tools/tools_dbsummary.php');
    }

    // ── de celweergave ──────────────────────────────────────────────────────────

    public function testNullIsNotTheSameAsAnEmptyString(): void
    {
        // Allebei zien ze er in HTML uit als niets, terwijl juist dat verschil is
        // waar je naar zit te kijken.
        $this->assertTrue(
            str_contains(dbsummary_sample_cell(null), 'NULL'),
            'NULL moet als NULL te zien zijn'
        );
        $this->assertEquals('', dbsummary_sample_cell(''), 'een lege tekst blijft leeg');
    }

    public function testValuesAreEscaped(): void
    {
        $uit = dbsummary_sample_cell('<script>alert(1)</script>');
        $this->assertFalse(str_contains($uit, '<script>'), 'gegevens mogen geen HTML worden');
        $this->assertTrue(str_contains($uit, '&lt;script&gt;'), 'de waarde blijft wel leesbaar');
    }

    public function testLongValuesAreShortenedButKeepTheirFullValueInTheTooltip(): void
    {
        $lang = str_repeat('a', 500);
        $uit = dbsummary_sample_cell($lang);
        $this->assertTrue(str_contains($uit, '&hellip;'), 'een memo-kolom wordt afgekapt');
        $this->assertTrue(str_contains($uit, 'title="'), 'de rest staat in de tooltip');
        // En de tooltip zelf heeft ook een grens: een blob van megabytes hoort niet in
        // de pagina.
        $this->assertTrue(strlen($uit) < 1000, 'ook de tooltip is begrensd');
    }

    public function testShortValuesArePassedThroughWhole(): void
    {
        $this->assertEquals('Jan Jansen', dbsummary_sample_cell('Jan Jansen'));
        $this->assertEquals('0', dbsummary_sample_cell('0'), '0 is een waarde, geen leegte');
    }

    public function testControlCharactersAreDropped(): void
    {
        // Access levert uit sommige kolommen stuurtekens; die breken de HTML-weergave.
        $this->assertEquals('ab', dbsummary_sample_cell("a\x00\x07b"));
    }

    // ── de tool zelf ────────────────────────────────────────────────────────────

    public function testTheTableNameIsCheckedAgainstTheSchemaBeforeItReachesTheQuery(): void
    {
        $src = $this->toolSrc();
        $this->assertTrue(
            str_contains($src, "in_array(\$sampleTable, \$bekend, true)"),
            'de tabelnaam uit de URL moet letterlijk in de lijst van SchemaHelper staan'
        );
        $this->assertTrue(
            str_contains($src, "array_column(SchemaHelper::getTables(\$schemaConn), 'name')"),
            'die lijst komt van SchemaHelper, niet van een eigen filtertje'
        );
    }

    public function testTheSampleQueryIsLimitedAndDialectProcessed(): void
    {
        $src = $this->toolSrc();
        $this->assertTrue(
            str_contains($src, "'SELECT TOP ' . DBSUMMARY_SAMPLE_ROWS . ' * FROM ['"),
            'de query haalt niet de hele tabel op'
        );
        $this->assertTrue(
            str_contains($src, 'SQL::processSQL($schemaConn,'),
            'TOP moet vertaald worden op een database die dat niet kent'
        );
    }

    public function testEveryTableWithRecordsGetsTheLink(): void
    {
        $src = $this->toolSrc();
        $this->assertTrue(str_contains($src, 'dbsToonRecords'), 'de uitklapper moet aangeroepen worden');
        $this->assertTrue(str_contains($src, 'db-sample-row'), 'er moet een rij zijn om in uit te klappen');
        // De uitklapper laadt pas bij een klik: alle tabellen vooraf uitlezen zou de
        // pagina onbruikbaar traag maken.
        $this->assertTrue(str_contains($src, 'cel.dataset.geladen'), 'één keer laden, daarna onthouden');
    }

    public function testTheHandlerLivesInThePageNotInTheAjaxResponse(): void
    {
        // HTML die via innerHTML binnenkomt voert zijn eigen <script> niet uit; stond de
        // functie in het AJAX-antwoord, dan deed de link niets.
        $src = $this->toolSrc();
        $paginaScript = strpos($src, 'window.dbsToonRecords');
        $ajaxTak = strpos($src, "// AJAX request - de eerste 10 records");
        $this->assertTrue($paginaScript !== false, 'de handler moet er zijn');
        $this->assertTrue($ajaxTak !== false && $paginaScript > $ajaxTak,
            'de handler hoort in het paginascript, onderaan, niet in het AJAX-antwoord');
    }
}
