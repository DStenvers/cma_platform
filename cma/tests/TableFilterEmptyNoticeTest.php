<?php
/**
 * lib-table: when rows exist but a column filter hides every one of them, the table shows a centred notice with a button "Filter uitzetten" (instead of looking like a search without
 * matches). Without rows at all (a search without matches) nothing is added. The behaviour itself is checked in a browser (a small table with a filter that hides everything:
 * notice shown, button restores the rows, empty list gets no notice); this test guards the pieces it relies on.
 *
 *   php cma/tests/TestRunner.php TableFilterEmptyNoticeTest
 */
require_once __DIR__ . '/TestRunner.php';

class TableFilterEmptyNoticeTest extends TestCase
{
    public function testNoticeIsWiredIntoTheViewUpdate(): void
    {
        $js = (string) file_get_contents(__DIR__ . '/../../library/webcomponents/lib-table.js');
        $this->assertTrue(strpos($js, '_updateFilterEmptyNotice(ergensGefilterd)') !== false, '_updateView() refreshes the notice');
        $this->assertTrue(strpos($js, "this._rows.length > 0\n            && !this._rows.some(row => row.style.display !== 'none')") !== false, 'only when rows exist and all are hidden');
        $this->assertTrue(strpos($js, 'Filter uitzetten') !== false, 'the button text');
        $this->assertTrue(strpos($js, "this.clearFilters();\n            this._saveFilters();") !== false, 'the button clears the filters and the remembered filter state');
    }

    public function testNoticeIsStyledAndCentred(): void
    {
        $css = (string) file_get_contents(__DIR__ . '/../../library/webcomponents/lib-table.css');
        $this->assertTrue(strpos($css, 'lib-table .lib-table-filter-empty') !== false && strpos($css, 'align-items: center') !== false, 'centred notice');
        $min = (string) file_get_contents(__DIR__ . '/../../library/webcomponents/lib-table.min.css');
        $this->assertTrue(strpos($min, 'lib-table-filter-empty') !== false, 'the minified stylesheet carries it too');
        $minJs = (string) file_get_contents(__DIR__ . '/../../library/webcomponents/lib-table.min.js');
        $this->assertTrue(strpos($minJs, 'lib-table-filter-empty') !== false, 'the minified script carries it too');
    }
}
