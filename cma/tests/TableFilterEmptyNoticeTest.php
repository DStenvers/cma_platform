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
        $this->assertTrue(strpos($js, "rijen.length > 0\n            && !rijen.some(row => row.style.display !== 'none')") !== false, 'only when rows exist and all are hidden (the rows in the DOM, also those loaded while scrolling)');
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

    public function testSearchWithoutMatchesKeepsFilterAndSearchTerm(): void
    {
        $js = (string) file_get_contents(__DIR__ . '/../../library/webcomponents/lib-table.js');
        $this->assertTrue(strpos($js, 'if (!this._rows || this._rows.length === 0) return;') !== false, 'an empty table does not overwrite the saved column filter');
        $fc = (string) file_get_contents(__DIR__ . '/../assets/js/form-controller.js');
        $this->assertTrue(strpos($fc, '} else if (this.searchTerm) {') !== false, 'a search without matches keeps the search field enabled and filled (list and tree)');
    }

    public function testFieldChooserAndEyeLiveInLibTable(): void
    {
        $js = (string) file_get_contents(__DIR__ . '/../../library/webcomponents/lib-table.js');
        $this->assertTrue(strpos($js, "eye.className = 'lib-table-col-eye'") !== false, 'an eye per column header');
        $this->assertTrue(strpos($js, 'openFieldChooser() {') !== false, 'the field chooser is a method of lib-table');
        $this->assertTrue(strpos($js, 'Verbergen en het filter uitzetten?') !== false, 'asks before hiding a filtered column');
        $this->assertTrue(strpos($js, 'this._restoreHiddenColumns();') !== false, 'hidden columns come back after a render');
        $prefs = (string) file_get_contents(__DIR__ . '/../assets/js/table-preferences.js');
        $this->assertTrue(strpos($prefs, 'class CmaFieldChooser') === false, 'the old CMA chooser is gone');
        $css = (string) file_get_contents(__DIR__ . '/../assets/css/form.css');
        $this->assertTrue(strpos($css, '.field-chooser-modal') === false, 'its styles moved to lib-table.css');
    }
}
