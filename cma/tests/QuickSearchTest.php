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
}
