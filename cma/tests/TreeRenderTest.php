<?php
/**
 * Tests for Cma\Services\TreeService::getJsonFormTreeHtml — the grouped
 * tree the user sees in the CMA list panel.
 *
 * Run with: php tests/TestRunner.php TreeRenderTest
 *
 * Integration-style: TestHarness short-circuits auth, the DB connection
 * lookup and form-def loading so production code runs without a real CMA
 * or database. A StubConnection feeds the SELECT result rows the tree
 * builder consumes; every query it runs is recorded but for the tree the
 * stub returns exactly the rows we enqueue regardless of SQL text.
 *
 * The single most important case here is the EMPTY GROUP VALUE path
 * (testEmptyGroupValueDegradesToLeegFolder): a row whose grouping field
 * is '' / null historically crashed the tree. It must instead degrade
 * into a "[leeg]" folder and never throw.
 */

require_once __DIR__ . '/TestRunner.php';
require_once __DIR__ . '/StubConnection.php';
require_once __DIR__ . '/TestHarness.php';
require_once dirname(__DIR__) . '/classes/form_constants.php';
require_once dirname(__DIR__) . '/classes/SecurityHelper.php';
require_once dirname(__DIR__) . '/classes/JsonFormLoader.php';
require_once dirname(__DIR__) . '/classes/FormDefinition.php';
require_once dirname(__DIR__) . '/classes/FormDataProvider.php'; // defines Cma\ListMode
require_once dirname(__DIR__) . '/classes/Services/Logger.php';
require_once dirname(__DIR__) . '/classes/Services/PerformanceLogger.php';
require_once dirname(__DIR__) . '/classes/Services/BaseFormService.php';
require_once dirname(__DIR__) . '/classes/Services/ListServiceHelper.php';
require_once dirname(__DIR__) . '/classes/Services/TreeService.php';

use App\Library\Application;
use Cma\Services\TreeService;

class TreeRenderTest extends TestCase
{
    private StubConnection $conn;

    public function setUp(): void
    {
        TestHarness::loginAsAdmin();
        TestHarness::silenceLogger();

        $this->conn = StubConnection::create();
        TestHarness::injectConnection('data', $this->conn);
    }

    public function tearDown(): void
    {
        TestHarness::reset();
    }

    /**
     * A JSON form definition for a table-backed tree. groupFields controls
     * how many grouping levels the tree builds; detailField is the leaf
     * label. Shape matches what JsonFormLoader::load() returns (the def
     * wrapped under '_json').
     */
    private function injectTreeForm(string $name, array $groupFields, string $detailField = 'naam'): void
    {
        $def = ['_json' => [
            'name'        => $name,
            'title'       => 'Producten',
            'table'       => 'tblProducts',
            'idField'     => 'ID',
            'database'    => 'data',
            'detailField' => $detailField,
            'groupFields' => $groupFields,
            'fields'      => [
                ['name' => 'naam', 'type' => 'textbox', 'caption' => 'Naam'],
                ['name' => 'categorie', 'type' => 'textbox', 'caption' => 'Categorie'],
                ['name' => 'subcat', 'type' => 'textbox', 'caption' => 'Subcategorie'],
            ],
        ]];
        TestHarness::injectFormDef($name, $def);
    }

    /** Pull the folder labels out of a treeData node array. */
    private function folderLabels(array $treeData): array
    {
        $labels = [];
        foreach ($treeData as $node) {
            if (($node['type'] ?? '') === 'folder') {
                $labels[] = $node['label'];
            }
        }
        return $labels;
    }

    // ------------------------------------------------------------------
    // 1. Grouped tree — happy path
    // ------------------------------------------------------------------

    public function testGroupedTreeGroupsRowsByDistinctGroupValue(): void
    {
        $this->injectTreeForm('tree_form', ['categorie']);
        $this->conn->enqueueResult([
            ['ID' => 1, 'naam' => 'Appel',  'categorie' => 'Fruit'],
            ['ID' => 2, 'naam' => 'Peer',   'categorie' => 'Fruit'],
            ['ID' => 3, 'naam' => 'Wortel', 'categorie' => 'Groente'],
        ]);

        $result = TreeService::getJsonFormTreeHtml('tree_form');

        $this->assertTrue($result['success'] ?? false, 'Grouped tree must succeed: ' . ($result['error'] ?? ''));
        $this->assertEquals(3, $result['count'] ?? -1, 'count must equal number of rows');
        $this->assertTrue($result['hasGrouping'] ?? false, 'hasGrouping must be true when groupFields set');

        $this->assertArrayHasKey('treeData', $result, 'grouped tree must return treeData');
        $labels = $this->folderLabels($result['treeData']);
        $this->assertEquals(['Fruit', 'Groente'], $labels, 'one folder per distinct group value, in first-seen order');

        // Grouped trees deliver structure via treeData, not html.
        $this->assertEquals('', $result['html'] ?? 'x', 'grouped tree html is emptied in favour of treeData');

        // Fruit folder holds 2 leaves, Groente 1.
        $this->assertCount(2, $result['treeData'][0]['children'], 'Fruit folder has 2 leaf items');
        $this->assertCount(1, $result['treeData'][1]['children'], 'Groente folder has 1 leaf item');
    }

    // ------------------------------------------------------------------
    // 2. Empty group value — the known historical crash class
    // ------------------------------------------------------------------

    public function testEmptyGroupValueDegradesToLeegFolder(): void
    {
        // A row whose grouping field is '' and one that is NULL. Neither
        // may fatal; both must land in a single "[leeg]" fallback folder.
        $this->injectTreeForm('tree_empty', ['categorie']);
        $this->conn->enqueueResult([
            ['ID' => 1, 'naam' => 'Zonder cat', 'categorie' => ''],
            ['ID' => 2, 'naam' => 'Null cat',   'categorie' => null],
        ]);

        $result = TreeService::getJsonFormTreeHtml('tree_empty');

        $this->assertTrue($result['success'] ?? false, 'empty group value must NOT fatal: ' . ($result['error'] ?? ''));
        $this->assertEquals(2, $result['count'] ?? -1, 'both rows still counted');
        $this->assertArrayHasKey('treeData', $result, 'still returns a tree');

        $labels = $this->folderLabels($result['treeData']);
        $this->assertEquals(['[leeg]'], $labels, 'empty/null group values collapse into one "[leeg]" folder');
        $this->assertCount(2, $result['treeData'][0]['children'], 'both empty-group rows go under [leeg]');
    }

    public function testMixedEmptyAndPresentGroupValues(): void
    {
        // Present + empty in the same result: real folder(s) plus a [leeg]
        // catch-all, no crash, all rows accounted for.
        $this->injectTreeForm('tree_mixed', ['categorie']);
        $this->conn->enqueueResult([
            ['ID' => 1, 'naam' => 'Appel',    'categorie' => 'Fruit'],
            ['ID' => 2, 'naam' => 'Los item', 'categorie' => ''],
        ]);

        $result = TreeService::getJsonFormTreeHtml('tree_mixed');

        $this->assertTrue($result['success'] ?? false, 'mixed groups must not fatal');
        $this->assertEquals(2, $result['count'] ?? -1);
        $labels = $this->folderLabels($result['treeData']);
        $this->assertContains('Fruit', $labels, 'present group keeps its own folder');
        $this->assertContains('[leeg]', $labels, 'empty group gets the [leeg] fallback folder');
    }

    // ------------------------------------------------------------------
    // 3. No grouping configured — flat simple list
    // ------------------------------------------------------------------

    public function testNoGroupingReturnsFlatSimpleList(): void
    {
        $this->injectTreeForm('flat_form', []); // no groupFields
        $this->conn->enqueueResult([
            ['ID' => 1, 'naam' => 'Appel'],
            ['ID' => 2, 'naam' => 'Peer'],
        ]);

        $result = TreeService::getJsonFormTreeHtml('flat_form');

        $this->assertTrue($result['success'] ?? false, 'flat list must succeed');
        $this->assertEquals(2, $result['count'] ?? -1);
        $this->assertFalse($result['hasGrouping'] ?? true, 'hasGrouping must be false without groupFields');

        // Flat trees deliver their nodes as html, not treeData.
        $this->assertArrayHasKey('html', $result);
        $this->assertStringContainsString('id="simpletree"', $result['html'], 'flat list uses the simpletree wrapper');
        $this->assertStringContainsString('Appel', $result['html'], 'leaf labels are in the html');
        $this->assertStringContainsString('Peer', $result['html']);
        $this->assertFalse(isset($result['treeData']), 'flat list must not build treeData');
    }

    // ------------------------------------------------------------------
    // 4. Empty result set
    // ------------------------------------------------------------------

    public function testEmptyResultSetGroupedIsValidAndEmpty(): void
    {
        $this->injectTreeForm('tree_none', ['categorie']);
        $this->conn->enqueueResult([]); // zero rows

        $result = TreeService::getJsonFormTreeHtml('tree_none');

        $this->assertTrue($result['success'] ?? false, 'empty result must still succeed');
        $this->assertEquals(0, $result['count'] ?? -1, 'count 0 for no rows');
        // No flat items → no treeData assembled.
        $this->assertFalse(isset($result['treeData']), 'no treeData when there are no rows');
    }

    public function testEmptyResultSetFlatShowsNoDataMessage(): void
    {
        $this->injectTreeForm('flat_none', []);
        $this->conn->enqueueResult([]);

        $result = TreeService::getJsonFormTreeHtml('flat_none');

        $this->assertTrue($result['success'] ?? false);
        $this->assertEquals(0, $result['count'] ?? -1);
        $this->assertStringContainsString('Geen gegevens gevonden', $result['html'] ?? '', 'flat empty list shows a friendly message');
    }

    // ------------------------------------------------------------------
    // 5. Multi-level grouping (Group1 + Group2)
    // ------------------------------------------------------------------

    public function testMultiLevelGroupingNestsFolders(): void
    {
        $this->injectTreeForm('tree_multi', ['categorie', 'subcat']);
        $this->conn->enqueueResult([
            ['ID' => 1, 'naam' => 'Appel',  'categorie' => 'Fruit',   'subcat' => 'Zacht'],
            ['ID' => 2, 'naam' => 'Peer',   'categorie' => 'Fruit',   'subcat' => 'Hard'],
            ['ID' => 3, 'naam' => 'Wortel', 'categorie' => 'Groente', 'subcat' => 'Hard'],
        ]);

        $result = TreeService::getJsonFormTreeHtml('tree_multi');

        $this->assertTrue($result['success'] ?? false, 'multi-level tree must succeed');
        $this->assertEquals(3, $result['count'] ?? -1);
        $tree = $result['treeData'] ?? [];
        $this->assertEquals(['Fruit', 'Groente'], $this->folderLabels($tree), 'level-1 folders');

        // Fruit's children must be level-2 FOLDERS (Zacht, Hard), not leaves.
        $fruitChildren = $tree[0]['children'] ?? [];
        $this->assertEquals('folder', $fruitChildren[0]['type'] ?? '', 'level-2 nodes are folders');
        $sub = $this->folderLabels($fruitChildren);
        $this->assertEquals(['Zacht', 'Hard'], $sub, 'Fruit nests two subcategory folders');

        // The deepest folder holds the leaf item.
        $leaf = $fruitChildren[0]['children'][0] ?? [];
        $this->assertEquals('item', $leaf['type'] ?? '', 'leaf item sits at the deepest level');
        $this->assertEquals('Appel', $leaf['label'] ?? '', 'leaf carries its label');
    }

    // ------------------------------------------------------------------
    // Guards: missing form, connection failure
    // ------------------------------------------------------------------

    public function testMissingFormIsRejected(): void
    {
        $result = TreeService::getJsonFormTreeHtml('does_not_exist');
        $this->assertFalse($result['success'] ?? true, 'unknown form must fail cleanly');
        $this->assertNotEmpty($result['error'] ?? '', 'a failure message must be present');
    }
}
