<?php
/**
 * SubformTableLayoutTest.php — the column model of the subform list.
 *
 * The table used to fake its own layout: display:flex on the <table>, a scrolling
 * tbody, and every <tr> promoted to its own display:table. Each row then worked out
 * its columns over whatever width it happened to get. A scrollbar that takes up
 * space (Windows; an overlay scrollbar hides the problem) made every body row 15px
 * narrower than the header, and the columns drifted apart - measured in the browser:
 * 2px on the second column running up to 14px on the eighth.
 *
 * One real table cannot do that: header and rows share a single column model.
 * The scrolling moved one level out, to .subform-list, and the header stays in view
 * with position:sticky. That keeps what the tbody-scroll was there for in the first
 * place - the toolbar above the list and the table header must not scroll away -
 * while header and rows now sit in the SAME scroll box, so their widths cannot
 * diverge.
 *
 * Cypress measures the actual geometry (cypress/e2e/forms/subforms.cy.js,
 * "Column alignment") but needs a running CMA; this holds the stylesheet to the
 * contract without one.
 *
 *   php tests/TestRunner.php SubformTableLayoutTest
 */

require_once __DIR__ . '/TestRunner.php';

class SubformTableLayoutTest extends TestCase
{
    /** The .subform-table block from form.css, without the surrounding rules. */
    private function blok(): string
    {
        $css = (string) file_get_contents(__DIR__ . '/../assets/css/form.css');
        $start = strpos($css, '.subform-table {');
        $this->assertTrue($start !== false, 'the .subform-table rule must exist');
        return substr($css, $start, 900);
    }

    public function testTheTableIsARealTableAgain(): void
    {
        $b = $this->blok();
        $this->assertFalse(
            (bool) preg_match('/\.subform-table\s*\{[^}]*display:\s*flex/s', $b),
            'a flex table splits the column model per row - that is what drifted'
        );
        $this->assertTrue(str_contains($b, 'width: 100%'), 'the table spans its container');
        $this->assertTrue(str_contains($b, 'table-layout: fixed'), 'equal column widths, as before');
    }

    public function testTheBodyIsNoLongerItsOwnScrollBox(): void
    {
        $css = (string) file_get_contents(__DIR__ . '/../assets/css/form.css');
        $this->assertFalse(
            (bool) preg_match('/\.subform-table tbody\s*\{[^}]*overflow-y:\s*auto/s', $css),
            'a scrolling tbody is exactly what made the rows narrower than the header'
        );
        $this->assertFalse(
            (bool) preg_match('/\.subform-table (thead|tbody) tr[^{]*\{[^}]*display:\s*table/s', $css),
            'rows must not be promoted to tables of their own'
        );
    }

    public function testTheHeaderSticksWithAnOpaqueBackground(): void
    {
        $css = (string) file_get_contents(__DIR__ . '/../assets/css/form.css');
        $this->assertTrue(
            (bool) preg_match('/(\.subform-table thead th[^{]*)\{([^}]*)\}/s', $css, $m),
            'there must be a rule for the sticky header cells'
        );
        $selector = $m[1];
        $regel = $m[2];
        $this->assertTrue(str_contains($regel, 'position: sticky'), 'the header stays in view');
        $this->assertTrue(str_contains($regel, 'top: 0'), 'and sticks to the top of the scrollport');
        $this->assertTrue(
            str_contains($regel, 'background-color'),
            'without its own background the rows show through the header'
        );
        // lib-table.css puts the first header cell on position:relative (it anchors
        // the kebab and the sort arrow). That selector outweighs a bare `thead th`,
        // so the first column has to be named here or it scrolls away on its own.
        $this->assertTrue(
            str_contains($selector, ':first-child'),
            'the first header cell needs the sticky rule too — lib-table.css puts it on position:relative'
        );
    }

    public function testTheListIsTheScrollBoxAndTheToolbarStaysPut(): void
    {
        // The scroll has to sit on .subform-list: one level higher (.subform-content)
        // would take the toolbar along, which is exactly what the tbody-scroll was
        // introduced to prevent.
        $css = (string) file_get_contents(__DIR__ . '/../assets/css/form.css');
        $this->assertTrue(
            (bool) preg_match('/\.subform-list\s*\{[^}]*overflow-y:\s*auto/s', $css),
            '.subform-list must be the scroll box'
        );
        $this->assertTrue(
            (bool) preg_match('/#subformContent > \.tab-pane\s*\{[^}]*flex-direction:\s*column/s', $css),
            'the pane must be a column: toolbar on top, list underneath'
        );
        $this->assertTrue(
            (bool) preg_match('/#subformContent > \.tab-pane > \.toolbar\s*\{[^}]*flex:\s*0 0 auto/s', $css),
            'the toolbar must not shrink or scroll along'
        );
    }

    public function testThePaneIsShownAsAFlexColumn(): void
    {
        // An inline display:block from the tab switch would beat the stylesheet and
        // the toolbar would scroll away again.
        $js = (string) file_get_contents(__DIR__ . '/../assets/js/form-controller.js');
        $this->assertTrue(
            str_contains($js, "paneIndex === index ? 'flex' : 'none'"),
            'the active subform pane must be shown as flex, not block'
        );
    }
}
