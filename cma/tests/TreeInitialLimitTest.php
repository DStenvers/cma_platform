<?php
/**
 * The first page of the list: the tree filters client-side, so it must carry the first 1000 records
 * (a record further down was unfindable by search); the table searches server-side and keeps a small page.
 *
 *   php cma/tests/TestRunner.php TreeInitialLimitTest
 */
require_once __DIR__ . '/TestRunner.php';

class TreeInitialLimitTest extends TestCase
{
    public function testTreeStartsWithAThousandRecordsAndTheTableWithTwoHundred(): int
    {
        $src = (string) file_get_contents(dirname(__DIR__) . '/form_api.php');
        $this->assertTrue(
            (bool) preg_match('/\$limit = \$displayMode == 2 \? 200 : 1000;/', $src),
            'form_api.php: initial limit is 1000 for the tree and 200 for the table'
        );
        $this->assertFalse(strpos($src, '$limit = 200; // Smaller initial page') !== false, 'no fixed 200 for the tree any more');
        return 0;
    }
}
