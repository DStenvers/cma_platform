<?php
/**
 * QueryToolScheidingTest — SQL met puntkomma's IN de gegevens mag niet uiteenvallen.
 *
 * WAAROM DEZE TEST ER IS. Een geplakte dump van tblAlgemeneInfo leverde in de querytool
 * tientallen meldingen op: "Invalid SQL statement; expected DELETE, INSERT, PROCEDURE,
 * SELECT, or UPDATE" en "COUNT field incorrect". Er was niets mis met die SQL. De tool
 * splitste op één ; — en HTML staat vol met puntkomma's: &nbsp;, &#39;, &amp;, en CSS in
 * een style-attribuut. Elke opdracht viel dus middenin een tekstwaarde uiteen.
 *
 * Daar kwam een tweede kwaal overheen: accessDoubleQuotesToSingle() herschrijft " naar '
 * voor met de hand geplakte Access-SQL. Op een dump met "-begrenzers herschreef dat álle
 * begrenzers, waarna elke apostrof in de inhoud (video's, KBS'en) een string afsloot —
 * "Syntax error in string in query expression".
 *
 * Vastgelegd: op ;; splitsen zodra dat in de invoer staat, en die invoer dan met rust
 * laten. Eén losse query zonder ;; verandert niet.
 *
 *   php cma/tests/TestRunner.php QueryToolScheidingTest
 */

require_once __DIR__ . '/TestRunner.php';

class QueryToolScheidingTest extends TestCase
{
    /** Dezelfde keuze als de tool maakt. */
    private function scheiding(string $sql): string
    {
        return strpos($sql, ';;') !== false ? ';;' : ';';
    }

    private function splits(string $sql): array
    {
        $delen = explode($this->scheiding($sql), $sql);
        return array_values(array_filter(array_map('trim', $delen), fn($d) => $d !== ''));
    }

    public function testHtmlMetEntiteitenValtNietMeerUiteen(): void
    {
        // Precies de vorm die misging: &nbsp; en &#39; in de waarde.
        $sql = "DELETE FROM [tblAlgemeneInfo];;\n"
             . "INSERT INTO [tblAlgemeneInfo] ([ID], [Inhoud]) VALUES (1, "
             . "'<p>Bereikbaar via&nbsp;<a>mail</a> en video&#39;s</p>');;";

        $delen = $this->splits($sql);
        $this->assertEquals(2, count($delen), 'twee opdrachten, niet meer');
        $this->assertStringContainsString('&nbsp;', $delen[1], 'de entiteit blijft heel');
        $this->assertStringContainsString('&#39;', $delen[1]);
    }

    public function testMetSplitsenOpEnkeleKommaGingHetMis(): void
    {
        // De oude situatie, ter vergelijking: dit is waarom de meldingen kwamen.
        $sql = "INSERT INTO [t] ([a]) VALUES ('x&nbsp;y');";
        $this->assertTrue(count(explode(';', $sql)) > 2, 'op ; valt dit in stukken');
    }

    public function testCssInEenStyleAttribuutBlijftHeel(): void
    {
        $sql = "UPDATE [t] SET [a] = 'x' & '<div style=\"color:red;font-weight:bold\">y</div>' WHERE [ID] = 1;;";
        $delen = $this->splits($sql);
        $this->assertEquals(1, count($delen));
        $this->assertStringContainsString('font-weight:bold', $delen[0]);
    }

    public function testEenGewoneQueryZonderDubbelePuntkommaVerandertNiet(): void
    {
        $sql = "SELECT * FROM tblLogins; SELECT * FROM tblDocenten;";
        $this->assertEquals(';', $this->scheiding($sql), 'zonder ;; blijft het gedrag zoals het was');
        $this->assertEquals(2, count($this->splits($sql)));
    }

    public function testDeToolKiestDeScheidingEnSlaatDeQuoteOmzettingOver(): void
    {
        $bron = (string) file_get_contents(dirname(__DIR__) . '/tools/tools_query.php');
        $this->assertStringContainsString(
            "\$scheiding = strpos(\$sqlToRun, ';;') !== false ? ';;' : ';';",
            $bron,
            'de keuze moet in de tool staan'
        );
        $this->assertStringContainsString(
            "Arr::splitAlways(\$sqlToRun, \$scheiding)",
            $bron,
            'en gebruikt worden'
        );
        $this->assertStringContainsString(
            "if (\$isAccess && \$scheiding !== ';;')",
            $bron,
            'machinaal opgemaakte SQL blijft ongemoeid'
        );
    }
}
