<?php
/**
 * RecursiveTreeTest — recurseField maakt een zelfverwijzende boom (list.asp
 * recurseTree): ouder-id-kolom, wortels zonder ouder, record met kinderen wordt
 * een aanklikbare map, wezen komen als wortel, een lus breekt niet.
 *
 *   php cma/tests/TestRunner.php RecursiveTreeTest
 */

require_once __DIR__ . '/TestRunner.php';
require_once dirname(__DIR__) . '/classes/Services/ListServiceHelper.php';
require_once dirname(__DIR__) . '/classes/Services/BaseFormService.php';
require_once dirname(__DIR__) . '/classes/Services/TreeService.php';

use Cma\Services\TreeService;

class RecursiveTreeTest extends TestCase
{
    public function testNestenOpOuder(): void
    {
        $items = [
            ['id' => '1', 'label' => 'Root', 'parent' => ''],
            ['id' => '2', 'label' => 'Kind', 'parent' => '1'],
            ['id' => '3', 'label' => 'Kleinkind', 'parent' => '2'],
            ['id' => '4', 'label' => 'Wees', 'parent' => '99'],
        ];
        $tree = TreeService::buildRecursiveTree($items);
        $this->assertSame(2, count($tree));
        $this->assertSame('folder', $tree[0]['type']);
        $this->assertSame('1', $tree[0]['id']);
        $this->assertSame('Kind', $tree[0]['children'][0]['label']);
        $this->assertSame('folder', $tree[0]['children'][0]['type']);
        $this->assertSame('item', $tree[0]['children'][0]['children'][0]['type']);
        $this->assertSame('Wees', $tree[1]['label']);
    }

    public function testLusBreektNiet(): void
    {
        $items = [
            ['id' => '1', 'label' => 'A', 'parent' => '2'],
            ['id' => '2', 'label' => 'B', 'parent' => '1'],
        ];
        $tree = TreeService::buildRecursiveTree($items);
        $this->assertTrue(count($tree) >= 0); // geen oneindige recursie
    }
}
