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
 * The header stays put with position:sticky, and .subform-content does the
 * scrolling - it already had overflow-y:auto.
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
            (bool) preg_match('/\.subform-table thead th\s*\{([^}]*)\}/s', $css, $m),
            'there must be a rule for the sticky header cells'
        );
        $regel = $m[1];
        $this->assertTrue(str_contains($regel, 'position: sticky'), 'the header stays in view');
        $this->assertTrue(str_contains($regel, 'top: 0'), 'and sticks to the top of the scrollport');
        $this->assertTrue(
            str_contains($regel, 'background-color'),
            'without its own background the rows show through the header'
        );
    }

    public function testTheScrollingAncestorIsStillThere(): void
    {
        // Taking the scroll off the tbody only works because .subform-content
        // already scrolls; without it the list would just grow and push the form.
        $css = (string) file_get_contents(__DIR__ . '/../assets/css/form.css');
        $this->assertTrue(
            (bool) preg_match('/\.subform-content\s*\{[^}]*overflow-y:\s*auto/s', $css),
            '.subform-content must keep overflow-y:auto - it is the scroll box now'
        );
    }
}
