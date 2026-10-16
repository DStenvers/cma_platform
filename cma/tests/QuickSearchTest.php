<?php
/**
 * QuickSearchTest — eenvoudig zoeken in boom en tabel.
 *
 * De oude list.asp zocht in álle tekstachtige velden van het formulier (ook memo's
 * en kolommen die niet in de lijst staan) en kende " en "-logica: "jan en amsterdam"
 * moet aan beide termen voldoen. De nieuwe zocht alleen in de zichtbare lijstkolommen
 * en negeerde " en ". ListServiceHelper::quickSearchTerms/quickSearchConditions
 * dragen nu beide weergaven.
 *
 * Een combobox (fkDocent, fkDeelname) bewaart een sleutel, maar de gebruiker zoekt op
 * wat hij ZIET: de naam van de docent of deelnemer. Zo'n veld zoekt daarom via een
 * subquery op de weergavetekst van het gekoppelde record.
 *
 *   php cma/tests/TestRunner.php QuickSearchTest
 */

require_once __DIR__ . '/TestRunner.php';
require_once dirname(__DIR__) . '/classes/Services/ListServiceHelper.php';

use Cma\Services\ListServiceHelper;

class QuickSearchTest extends TestCase
{
    private array $jsonData = [
        'table' => 'tblPersonen',
        'idField' => 'ID',
        'listQuery' => 'SELECT ID, Naam FROM tblPersonen',
        'fields' => [
            ['name' => 'Naam', 'type' => 'textbox'],
            ['name' => 'Opmerking', 'type' => 'memo'],
            ['name' => 'fkStad', 'type' => 'combobox'],
            ['name' => 'Actief', 'type' => 'checkbox'],
            ['name' => 'Berekend', 'type' => 'label', 'sql' => 'SELECT 1'],
        ],
    ];

    public function testTermenGesplitstOpEn(): void
    {
        $this->assertSame(['jan', 'amsterdam'], ListServiceHelper::quickSearchTerms('jan en amsterdam'));
        $this->assertSame(['jan', 'amsterdam'], ListServiceHelper::quickSearchTerms('jan AND amsterdam'));
        $this->assertSame(['jansen'], ListServiceHelper::quickSearchTerms('jansen'));
        $this->assertSame(['groningen'], ListServiceHelper::quickSearchTerms('groningen')); // "en" in een woord splitst niet
    }

    public function testMemoEnOngetoondeTekstveldenWordenDoorzocht(): void
    {
        $fields = [];
        foreach ($this->jsonData['fields'] as $f) $fields[strtolower($f['name'])] = $f;
        $cond = ListServiceHelper::quickSearchConditions('jan', $this->jsonData, [['field' => 'Naam']], $fields, 'tblPersonen', 'ID');
        $this->assertTrue(in_array("[Naam] LIKE '%jan%'", $cond, true), implode(' | ', $cond));
        $this->assertTrue(in_array("[Opmerking] LIKE '%jan%'", $cond, true), implode(' | ', $cond));
        $joined = implode(' | ', $cond);
        $this->assertStringNotContainsString('fkStad', $joined);
        $this->assertStringNotContainsString('Actief', $joined);
        $this->assertStringNotContainsString('Berekend', $joined);
    }

    public function testNumeriekeTermMatchtOokHetId(): void
    {
        $cond = ListServiceHelper::quickSearchConditions('42', $this->jsonData, [], [], 'tblPersonen', 'ID');
        $this->assertTrue(in_array('[tblPersonen].[ID] = 42', $cond, true), implode(' | ', $cond));
    }

    public function testKolomGekwalificeerdBijJoin(): void
    {
        $data = $this->jsonData;
        $data['listQuery'] = 'SELECT tblPersonen.ID, tblPersonen.Naam FROM tblPersonen INNER JOIN tblStad ON tblStad.ID = tblPersonen.fkStad';
        $cond = ListServiceHelper::quickSearchConditions('x', $data, [], [], 'tblPersonen', 'ID');
        $this->assertTrue(in_array("[tblPersonen].[Opmerking] LIKE '%x%'", $cond, true), implode(' | ', $cond));
    }

    public function testAndereZoekcriteriaOmzeilenHetVerplichteFilter(): void
    {
        $this->assertFalse(ListServiceHelper::hasOtherFilters([], 'fkOpleiding'));
        $this->assertFalse(ListServiceHelper::hasOtherFilters(['fkOpleiding' => '12'], 'fkOpleiding'));
        $this->assertFalse(ListServiceHelper::hasOtherFilters(['Naam' => '', 'Datum' => ['from' => '']], 'fkOpleiding'));
        $this->assertTrue(ListServiceHelper::hasOtherFilters(['Naam' => 'jan'], 'fkOpleiding'));
        $this->assertTrue(ListServiceHelper::hasOtherFilters(['Datum' => ['from' => '2026-01-01']], 'fkOpleiding'));
    }

    public function testQuickSearchFieldsWintAltijd(): void
    {
        $data = $this->jsonData + ['quickSearchFields' => 'Naam'];
        $cond = ListServiceHelper::quickSearchConditions('x', $data, [], [], 'tblPersonen', 'ID');
        $this->assertSame(["[Naam] LIKE '%x%'"], $cond);
    }
    private function comboData(): array
    {
        return [
            'table' => 'tblAgendaReserveringen',
            'idField' => 'ID',
            'listQuery' => 'SELECT tblAgendaReserveringen.ID, tblDocenten.VolledigeNaam AS Docent FROM tblDocenten INNER JOIN tblAgendaReserveringen ON tblDocenten.ID = tblAgendaReserveringen.fkDocent',
            'fields' => [
                ['name' => 'Opmerking', 'type' => 'memo'],
                ['name' => 'fkDocent', 'type' => 'combobox', 'sourceTable' => 'tblDocenten', 'idField' => 'ID', 'displayField' => 'VolledigeNaam',
                    'sql' => 'SELECT tblDocenten.ID, tblDocenten.VolledigeNaam FROM tblDocenten ORDER BY tblDocenten.Achternaam'],
                ['name' => 'fkDeelname', 'type' => 'combobox', 'sourceTable' => 'tblDeelname', 'idField' => 'ID', 'displayField' => 'Descr',
                    'sql' => "SELECT tblDeelname.ID, [VolledigeNaam] & \" (\" & [code] & \")\" AS Descr\r\nFROM tblDeelnemers INNER JOIN tblDeelname ON tblDeelnemers.ID = tblDeelname.fkDeelnemer\r\nORDER BY tblDeelnemers.Achternaam;"],
            ],
        ];
    }

    public function testComboZoektOpWeergavetekstViaBrontabel(): void
    {
        $cond = ListServiceHelper::quickSearchConditions('jan', $this->comboData(), [], [], 'tblAgendaReserveringen', 'ID');
        $this->assertTrue(in_array("[tblAgendaReserveringen].[fkDocent] IN (SELECT [ID] FROM [tblDocenten] WHERE [VolledigeNaam] LIKE '%jan%')", $cond, true), implode(' | ', $cond));
        // tekstvelden blijven gewoon meedoen
        $this->assertTrue(in_array("[tblAgendaReserveringen].[Opmerking] LIKE '%jan%'", $cond, true), implode(' | ', $cond));
    }

    public function testComboMetAliasGebruiktDeSqlZonderOrderBy(): void
    {
        $cond = ListServiceHelper::quickSearchConditions('jan', $this->comboData(), [], [], 'tblAgendaReserveringen', 'ID');
        $deelname = array_values(array_filter($cond, fn($c) => str_contains($c, 'fkDeelname')));
        $this->assertCount(1, $deelname, implode(' | ', $cond));
        $this->assertStringContainsString('[tblAgendaReserveringen].[fkDeelname] IN (SELECT [ID] FROM (SELECT tblDeelname.ID,', $deelname[0]);
        $this->assertStringContainsString(") AS cma_qs WHERE [Descr] LIKE '%jan%')", $deelname[0]);
        $this->assertStringNotContainsString('ORDER BY', $deelname[0]);
        $this->assertStringNotContainsString(';', $deelname[0]);
    }

    public function testComboZoektermWordtGeescaped(): void
    {
        $f = $this->comboData()['fields'][1];
        $cond = ListServiceHelper::comboSearchCondition($f, '[fkDocent]', "o'brien");
        $this->assertSame('[fkDocent] IN (SELECT [ID] FROM [tblDocenten] WHERE [VolledigeNaam] LIKE ' . \App\Library\SQL::postString("%o'brien%") . ')', $cond);
        $this->assertStringNotContainsString("o'brien", $cond);
    }

    public function testComboZonderBrontabelGebruiktDeSql(): void
    {
        $f = ['name' => 'fkOpleiding', 'type' => 'combobox', 'idField' => 'id', 'displayField' => 'descr',
            'sql' => "SELECT ID, [code] & ' - ' & [Titel] as Descr from tblOpleidingen order by Code, Titel"];
        $this->assertSame(
            "[fkOpleiding] IN (SELECT [id] FROM (SELECT ID, [code] & ' - ' & [Titel] as Descr from tblOpleidingen) AS cma_qs WHERE [descr] LIKE '%kp%')",
            ListServiceHelper::comboSearchCondition($f, '[fkOpleiding]', 'kp'));
    }

    public function testComboDieNietVeiligKanWordtOvergeslagen(): void
    {
        // record-parameter: Access zou om een parameterwaarde vragen en de hele lijst faalt
        $param = ['type' => 'combobox', 'idField' => 'ID', 'displayField' => 'Descr',
            'sql' => 'SELECT ID, Naam & Code AS Descr FROM tblX WHERE fkOpleiding = [fkOpleiding]'];
        $this->assertNull(ListServiceHelper::comboSearchCondition($param, '[fkX]', 'a'));
        // GROUP BY: SQL::addClause zou de buitenste query op die GROUP BY splitsen
        $group = ['type' => 'combobox', 'idField' => 'ID', 'displayField' => 'Descr',
            'sql' => 'SELECT ID, First(Naam) AS Descr FROM tblX GROUP BY ID'];
        $this->assertNull(ListServiceHelper::comboSearchCondition($group, '[fkX]', 'a'));
        // geen weergaveveld
        $this->assertNull(ListServiceHelper::comboSearchCondition(['type' => 'combobox', 'sourceTable' => 'tblX', 'idField' => 'ID'], '[fkX]', 'a'));
        // andere database dan het formulier
        $other = ['type' => 'combobox', 'sourceTable' => 'tblUsers', 'idField' => 'ID', 'displayField' => 'userFullName', 'database' => 'users'];
        $this->assertNull(ListServiceHelper::comboSearchCondition($other, '[fkUser]', 'a', 'data'));
    }

    public function testQuickSearchFieldsMetComboZoektOpWeergavetekst(): void
    {
        $data = $this->comboData() + ['quickSearchFields' => 'Opmerking, fkDocent'];
        $cond = ListServiceHelper::quickSearchConditions('jan', $data, [], [], 'tblAgendaReserveringen', 'ID');
        $this->assertSame([
            "[Opmerking] LIKE '%jan%'",
            "[tblAgendaReserveringen].[fkDocent] IN (SELECT [ID] FROM [tblDocenten] WHERE [VolledigeNaam] LIKE '%jan%')",
        ], $cond);
    }
}
