<?php
/**
 * Tests for the initial sort indicator in library/webcomponents/lib-table.js
 *
 * WHY THIS EXISTS
 * A list arrives already sorted — the query has an ORDER BY — but the interface showed
 * nothing: the indicator only appeared once the user clicked a sort option themselves.
 * Consumers worked around that by SIMULATING a click after loading the rows, e.g.
 *
 *     $("table.filtering tr th:nth-child(4) .dropdown-filter-sort .a---z").click();
 *
 * That has two failure modes. It re-sorts client-side over rows that were already in the
 * right order, and it addresses the column by a hardcoded index — so on a tab with a
 * different column layout it sorts on whatever happens to sit in that position. On the
 * mijnRINO task tabs that was "Startdatum" on one tab and a free-text remarks column on
 * another, silently replacing the server's ordering.
 *
 * `<th data-sorted="asc|desc">` replaces that: the component marks the column and leaves
 * the rows alone. The tests below pin the part that matters — marking without sorting.
 *
 * Run with: php cma/tests/TestRunner.php LibTableInitialSortTest
 */

require_once __DIR__ . '/TestRunner.php';

class LibTableInitialSortTest extends TestCase
{
    private function source(): string
    {
        $path = dirname(__DIR__, 2) . '/library/webcomponents/lib-table.js';
        $this->assertTrue(is_file($path), 'library/webcomponents/lib-table.js is missing');
        return (string)file_get_contents($path);
    }

    /** The body of one top-level method, brace-matched. */
    private function methodBody(string $src, string $name): string
    {
        // De DEFINITIE zoeken, niet de eerste aanroep: `this->_markInitialSort()` staat
        // eerder in het bestand dan de methode zelf, en brace-matchen vanaf die aanroep
        // levert het verkeerde blok op.
        $gevonden = preg_match('/\n\s*' . preg_quote($name, '/') . '\s*\([^)]*\)\s*\{/', $src, $m, PREG_OFFSET_CAPTURE);
        $this->assertTrue($gevonden === 1, "method $name() not found");
        $start = $m[0][1];
        $open = strpos($src, '{', $start);
        $depth = 0;
        for ($i = $open; $i < strlen($src); $i++) {
            if ($src[$i] === '{') { $depth++; }
            elseif ($src[$i] === '}') {
                $depth--;
                if ($depth === 0) { return substr($src, $open, $i - $open + 1); }
            }
        }
        $this->assertTrue(false, "method $name() is not brace-balanced");
        return '';
    }

    public function testTheMarkerMethodExists(): void
    {
        $this->assertTrue(strpos($this->source(), '_markInitialSort()') !== false,
            '_markInitialSort() is gone');
    }

    public function testItReadsTheAttributeFromAHeaderCell(): void
    {
        $body = $this->methodBody($this->source(), '_markInitialSort');
        $this->assertTrue(strpos($body, 'data-sorted') !== false, 'data-sorted is not read');
        $this->assertTrue(strpos($body, 'thead th') !== false, 'it does not look at a header cell');
    }

    /**
     * The whole point: mark, do not reorder. If this method ever starts calling the
     * sorter, the query's ordering is being thrown away again — which is the bug the
     * attribute was introduced to remove.
     */
    public function testItDoesNotResortTheRows(): void
    {
        $body = $this->methodBody($this->source(), '_markInitialSort');
        foreach (['_sort(', '.sort(', 'appendChild', '.click('] as $verboden) {
            $this->assertTrue(strpos($body, $verboden) === false,
                "_markInitialSort() uses $verboden — it must only mark, never reorder");
        }
    }

    public function testItSetsTheSameMarkersAsARealClick(): void
    {
        $src = $this->source();
        $mark = $this->methodBody($src, '_markInitialSort');
        // The indicator on the column header, and the active option inside the menu.
        $this->assertTrue(strpos($mark, "'sorted'") !== false, 'the column indicator is not set');
        $this->assertTrue(strpos($mark, "'active'") !== false, 'the menu option is not marked active');
        // Same class names the click path uses, so the two cannot drift apart.
        $sort = $this->methodBody($src, '_sort');
        $this->assertTrue(strpos($sort, "'sorted'") !== false, '_sort() no longer uses the sorted class');
        foreach (['a---z', 'z---a'] as $optie) {
            $this->assertTrue(strpos($mark, $optie) !== false, "_markInitialSort() does not know $optie");
        }
    }

    public function testColumnsThatCannotBeSortedAreSkipped(): void
    {
        $body = $this->methodBody($this->source(), '_markInitialSort');
        $this->assertTrue(strpos($body, 'data-no-sort') !== false,
            'a column with data-no-sort would still get an indicator');
    }

    /**
     * A PHP-rendered table already carries its filter menus, so _initializeFilters()
     * returns early. The marking has to happen on that path too, or the attribute does
     * nothing on exactly the tables that come from the server.
     */
    public function testMarkingAlsoRunsForServerRenderedTables(): void
    {
        $body = $this->methodBody($this->source(), '_initializeFilters');
        $voorReturn = substr($body, 0, strpos($body, 'return;') ?: strlen($body));
        $this->assertTrue(strpos($voorReturn, '_markInitialSort') !== false,
            'the early-return path (PHP-generated table) never marks');
        $this->assertTrue(substr_count($body, '_markInitialSort') >= 2,
            'marking does not run on both paths');
    }

    public function testBundleIsRebuilt(): void
    {
        $dir = dirname(__DIR__, 2) . '/library/webcomponents/';
        $this->assertTrue(is_file($dir . 'lib-table.min.js'), 'lib-table.min.js is missing');
        $this->assertTrue(
            filemtime($dir . 'lib-table.min.js') >= filemtime($dir . 'lib-table.js'),
            'lib-table.min.js is older than its source — run `npm run build:minify` in cma/'
        );
        $this->assertTrue(strpos((string)file_get_contents($dir . 'lib-table.min.js'), 'data-sorted') !== false,
            'the bundle does not carry data-sorted');
    }
}
