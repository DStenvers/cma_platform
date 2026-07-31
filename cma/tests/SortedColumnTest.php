<?php
/**
 * Tests for showing the sorting a list already arrives in.
 *
 * A query has an ORDER BY, so the rows come in a known order — but the table header
 * showed nothing until the user sorted a column themselves. Consumers papered over that
 * by simulating a click on a hardcoded column index after loading the rows, which
 * re-sorted client-side and pointed at the wrong column whenever the layout differed.
 *
 * Two pieces replace that: SQL::sortedColumn() reads the column out of the query, and
 * LibTable::Render() puts data-sorted on that header. <lib-table> then marks the column
 * and leaves the row order alone.
 *
 * The rule these tests pin down is "say nothing rather than something wrong": every case
 * where the column cannot be identified with certainty must produce no attribute at all.
 *
 * Run with: php tests/TestRunner.php SortedColumnTest
 */

require_once __DIR__ . '/TestRunner.php';
if (!defined('ADDATE')) {
    define('ADDATE', 7); // adDate — matches the ADO constant used by Render()
}
require_once dirname(__DIR__) . '/../library/lib_date.inc';
require_once dirname(__DIR__) . '/../library/classes/class_table.inc';

use App\Library\RecordSet;
use App\Library\SQL;

class SortedColumnTest extends TestCase
{
    // ---------------------------------------------------------------- SQL::sortedColumn

    public function testReadsAPlainColumn(): void
    {
        $this->assertEquals(
            ['field' => 'Naam', 'direction' => 'asc'],
            SQL::sortedColumn('SELECT ID, Naam FROM tblX ORDER BY Naam')
        );
    }

    public function testStripsTheTablePrefixAndBrackets(): void
    {
        $this->assertEquals(
            ['field' => 'datumDeadline', 'direction' => 'asc'],
            SQL::sortedColumn('SELECT * FROM tblTasks ORDER BY tblTasks.datumDeadline')
        );
        $this->assertEquals(
            ['field' => 'Achternaam', 'direction' => 'desc'],
            SQL::sortedColumn('SELECT * FROM tblX ORDER BY [tblX].[Achternaam] DESC')
        );
    }

    public function testDirectionIsCaseInsensitiveAndDefaultsToAscending(): void
    {
        $this->assertEquals('desc', SQL::sortedColumn('SELECT * FROM t ORDER BY Datum desc')['direction']);
        $this->assertEquals('asc', SQL::sortedColumn('SELECT * FROM t ORDER BY Datum ASC')['direction']);
        $this->assertEquals('asc', SQL::sortedColumn('SELECT * FROM t order by Datum')['direction']);
    }

    /**
     * Secondary terms only break ties. Marking them would claim the list is sorted on a
     * column it visibly is not.
     */
    public function testOnlyTheFirstTermCounts(): void
    {
        $this->assertEquals(
            ['field' => 'Achternaam', 'direction' => 'asc'],
            SQL::sortedColumn('SELECT * FROM t ORDER BY Achternaam, Roepnaam DESC')
        );
    }

    public function testNothingToPointAt(): void
    {
        $geen = [
            'SELECT * FROM t'                                  => 'geen ORDER BY',
            'SELECT * FROM t ORDER BY 2'                       => 'een kolomnummer wijst geen kop aan',
            'SELECT * FROM t ORDER BY IIf(x, 1, 2)'            => 'een expressie is geen kolom',
            "SELECT * FROM t ORDER BY Roepnaam &' '& Naam"     => 'een samenstelling is geen kolom',
            ''                                                 => 'lege query',
        ];
        foreach ($geen as $sql => $waarom) {
            $this->assertNull(SQL::sortedColumn($sql), $waarom);
        }
        $this->assertNull(SQL::sortedColumn(null), 'null-query');
    }

    /**
     * De rapportages sorteren op de bronkolom maar tonen de alias: ORDER BY
     * tblOpleidingen.code hoort bij de kop "Opleiding". Zonder die vertaling wijst de
     * sortering naar een naam die in de lijst niet bestaat en markeert er niets.
     */
    public function testResolvesTheSelectAlias(): void
    {
        $sql = 'SELECT tblOpleidingen.code AS Opleiding, tblDeelnemers.VolledigeNaam AS Deelnemer'
             . ' FROM tblOpleidingen INNER JOIN tblDeelnemers ON x = y'
             . ' ORDER BY tblOpleidingen.code, tblDeelnemers.Achternaam DESC';
        $this->assertEquals(['field' => 'Opleiding', 'direction' => 'asc'], SQL::sortedColumn($sql));
    }

    public function testResolvesAnAliasWithSpaces(): void
    {
        $this->assertEquals(
            ['field' => 'Datum aanvraag', 'direction' => 'desc'],
            SQL::sortedColumn('SELECT a.Datum AS [Datum aanvraag] FROM a ORDER BY a.Datum DESC')
        );
    }

    /** Een komma binnen een functieaanroep splitst de SELECT-lijst niet. */
    public function testAFunctionCallIsOneColumn(): void
    {
        $this->assertEquals(
            ['field' => 'Motivatie', 'direction' => 'asc'],
            SQL::sortedColumn('SELECT left(t.Toelichting, 80) AS Motivatie, t.ID FROM t ORDER BY Motivatie')
        );
    }

    /**
     * Twee tabellen met dezelfde kolomnaam: welke van de twee de kale sorteerterm bedoelt
     * is niet te zeggen, dus valt er niets aan te wijzen.
     */
    public function testAnAmbiguousBareTermResolvesToNoAlias(): void
    {
        $this->assertEquals('code',
            SQL::sortedColumn('SELECT x.code AS A, y.code AS B FROM x, y ORDER BY code')['field'],
            'geen gok tussen twee kandidaten; de kale naam bestaat niet als kolom en markeert dus niets');
    }

    /** LIMIT/OFFSET horen niet bij de sorteerterm. */
    public function testStopsAtTheClauseAfterOrderBy(): void
    {
        $this->assertEquals(
            ['field' => 'Datum', 'direction' => 'desc'],
            SQL::sortedColumn('SELECT * FROM t ORDER BY Datum DESC LIMIT 10 OFFSET 20')
        );
    }

    // ------------------------------------------------------------- LibTable::Render()

    /** Rendert een tabel met vaste rijen en de opgegeven query/kopteksten. */
    private function render(string $sql, ?array $captions = null, bool $showId = false): string
    {
        $rows = [
            ['ID' => 1, 'Opleiding' => 'GZ', 'Deadline' => '2026-04-01 00:00:00'],
            ['ID' => 2, 'Opleiding' => 'KP', 'Deadline' => '2026-05-15 00:00:00'],
        ];
        $t = new \LibTable();
        $t->Recordset    = new RecordSet(new \ArrayIterator($rows), false, true);
        $t->SQL          = $sql;
        $t->IDField      = 'ID';
        $t->ShowIDField  = $showId;
        $t->ShowCaptions = true;
        $t->RowsPerPage  = 99999;
        if ($captions !== null) {
            $t->FieldCaptions = $captions;
        }
        ob_start();
        $t->Render();
        return ob_get_clean();
    }

    /** De koppen met hun data-sorted-waarde, in volgorde: ['Opleiding' => null, ...]. */
    private function koppen(string $html): array
    {
        preg_match('#<THEAD>.*?</THEAD>#si', $html, $m);
        // \b na TH: anders matcht <THEAD> zelf als eerste kolomkop.
        preg_match_all('#<TH\b([^>]*)>(.*?)</TH>#si', $m[0] ?? '', $koppen, PREG_SET_ORDER);
        $uit = [];
        foreach ($koppen as $th) {
            $uit[trim(strip_tags($th[2]))] = preg_match('/data-sorted="(\w+)"/i', $th[1], $s) ? $s[1] : null;
        }
        return $uit;
    }

    public function testMarksTheColumnTheQuerySortsOn(): void
    {
        $koppen = $this->koppen($this->render('SELECT ID, Opleiding, Deadline FROM t ORDER BY Deadline'));
        $this->assertEquals(['Opleiding' => null, 'Deadline' => 'asc'], $koppen,
            'alleen de gesorteerde kolom draagt data-sorted');
    }

    public function testMarksTheDirection(): void
    {
        $koppen = $this->koppen($this->render('SELECT ID, Opleiding, Deadline FROM t ORDER BY Deadline DESC'));
        $this->assertEquals('desc', $koppen['Deadline'], 'DESC hoort ook als DESC in de kop te staan');
    }

    /**
     * De id-kolom is verborgen, dus de kolomindex van Deadline schuift op. Met eigen
     * kopteksten loopt de teller over de KOPPEN — als die twee niet gelijk lopen, komt de
     * aanwijzing op de verkeerde kolom terecht.
     */
    public function testIndexFollowsTheVisibleColumnsNotTheQuery(): void
    {
        $koppen = $this->koppen($this->render(
            'SELECT ID, Opleiding, Deadline FROM t ORDER BY Deadline',
            ['Opleiding', 'Uiterste datum']
        ));
        $this->assertEquals(['Opleiding' => null, 'Uiterste datum' => 'asc'], $koppen);
    }

    public function testTheIdColumnCanBeMarkedWhenItIsShown(): void
    {
        $koppen = $this->koppen($this->render('SELECT ID, Opleiding, Deadline FROM t ORDER BY ID DESC', null, true));
        $this->assertEquals('desc', $koppen['ID'], 'een getoonde id-kolom mag de sortering dragen');
        $this->assertNull($koppen['Deadline']);
    }

    public function testSaysNothingWhenTheSortColumnIsNotShown(): void
    {
        // Op de verborgen id-kolom valt niets aan te wijzen.
        $html = $this->render('SELECT ID, Opleiding, Deadline FROM t ORDER BY ID');
        $this->assertStringNotContainsString('data-sorted', $html);

        // Een kolom die helemaal niet in de lijst staat evenmin.
        $html = $this->render('SELECT ID, Opleiding, Deadline FROM t ORDER BY Aangemaakt');
        $this->assertStringNotContainsString('data-sorted', $html);
    }

    public function testSaysNothingWithoutAnOrderBy(): void
    {
        $this->assertStringNotContainsString('data-sorted',
            $this->render('SELECT ID, Opleiding, Deadline FROM t'));
    }

    /**
     * Eigen kopteksten die niet één-op-één op de getoonde velden passen: dan is de
     * kolomindex onbetrouwbaar en hoort er niets gemarkeerd te worden.
     */
    public function testSaysNothingWhenCaptionsDoNotLineUp(): void
    {
        $this->assertStringNotContainsString('data-sorted', $this->render(
            'SELECT ID, Opleiding, Deadline FROM t ORDER BY Deadline',
            ['Opleiding', 'Deadline', 'Extra kolom']
        ));
    }
}
