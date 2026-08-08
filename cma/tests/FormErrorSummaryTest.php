<?php
/**
 * Tests for the form error summary in library/formval_nl.js
 *
 * WHY THIS EXISTS
 * Field errors used to be shown as a small flag next to the field, positioned with
 * lib_getAbsoluteOffsetLeft/Top(). Those offsets are wrong whenever the field sits in a
 * tab, in a scrolling container, or in an element whose offsetWidth is 0 — so the flag
 * regularly appeared somewhere other than the field it belonged to, and it only ever
 * showed ONE error at a time. It is replaced by a div.form_errors at the top of the form
 * listing every error, each line linking to its field.
 *
 * Two things are guarded here. First that the flag does not creep back: it was shown from
 * three different places (validation, focus, and the first-error handler), so removing it
 * from one is not enough. Second that the minified bundles are rebuilt — the site loads
 * formval_nl.min.js, so a source change without a rebuild leaves the old behaviour live
 * while the source says otherwise. That gap is invisible in a diff.
 *
 * Run with: php cma/tests/TestRunner.php FormErrorSummaryTest
 */

require_once __DIR__ . '/TestRunner.php';

class FormErrorSummaryTest extends TestCase
{
    private function lib(string $rel): string
    {
        $path = dirname(__DIR__, 2) . '/library/' . $rel;
        $this->assertTrue(is_file($path), "library/$rel is missing");
        return (string)file_get_contents($path);
    }

    /** The body of one top-level `function <name>( … ) { … }`, brace-matched. */
    private function functionBody(string $src, string $name): string
    {
        $start = strpos($src, 'function ' . $name . '(');
        $this->assertTrue($start !== false, "function $name() not found");
        $open = strpos($src, '{', $start);
        $this->assertTrue($open !== false, "function $name() has no body");
        $depth = 0;
        for ($i = $open; $i < strlen($src); $i++) {
            if ($src[$i] === '{') { $depth++; }
            elseif ($src[$i] === '}') {
                $depth--;
                if ($depth === 0) {
                    return substr($src, $open, $i - $open + 1);
                }
            }
        }
        $this->assertTrue(false, "function $name() is not brace-balanced");
        return '';
    }

    // ========================================================================
    // The flag is gone — from every place it was shown
    // ========================================================================

    public function testSourceShowsNoTooltipAtAll(): void
    {
        $src = $this->lib('formval_nl.js');
        // Substring match on purpose: any call shape (tooltip.show / .fade / .hide)
        // means a flag is being positioned next to a field again.
        $this->assertTrue(strpos($src, 'tooltip.show') === false, 'formval_nl.js calls tooltip.show again');
        $this->assertTrue(strpos($src, 'tooltip.fade') === false, 'formval_nl.js calls tooltip.fade again');
    }

    public function testShowErrorOnlyMarksTheFieldAndDoesNotPosition(): void
    {
        $body = $this->functionBody($this->lib('formval_nl.js'), 'form_field_show_error');
        $this->assertTrue(strpos($body, 'form_field_set_valid_classname') !== false,
            'form_field_show_error no longer marks the field');
        // Absolute offsets were the whole reason the flag landed in the wrong place.
        $this->assertTrue(strpos($body, 'lib_getAbsoluteOffset') === false,
            'form_field_show_error is positioning something again');
    }

    public function testFocusDoesNotBringTheFlagBack(): void
    {
        $body = $this->functionBody($this->lib('formval_nl.js'), 'form_field_focus');
        $this->assertTrue(strpos($body, 'tooltip') === false, 'focusing a field shows a flag again');
    }

    // ========================================================================
    // The summary
    // ========================================================================

    public function testReportReceivesTheFormAndRendersTheSummary(): void
    {
        $src = $this->lib('formval_nl.js');
        // Without the form there is nothing to insert the summary into.
        $this->assertTrue(preg_match('/function\s+form_valid_report\s*\([^)]*\bform\b[^)]*\)/', $src) === 1,
            'form_valid_report() does not take the form');
        $this->assertTrue(preg_match('/form_valid_report\s*\([^)]*,\s*form\s*\)/', $src) === 1,
            'form_valid() does not pass the form to form_valid_report()');

        $body = $this->functionBody($src, 'form_valid_report');
        $this->assertTrue(strpos($body, 'form_errors_render') !== false,
            'form_valid_report() does not render the summary');
        // A caller that omits the form must still get a visible message.
        $this->assertTrue(strpos($body, 'lib_alertbox') !== false,
            'form_valid_report() lost its fallback for callers without a form');
    }

    public function testSummaryIsInsertedAtTheTopOfTheFormAndAnnounced(): void
    {
        $body = $this->functionBody($this->lib('formval_nl.js'), 'form_errors_render');
        $this->assertTrue(strpos($body, 'insertBefore') !== false && strpos($body, 'firstChild') !== false,
            'the summary is not inserted at the top of the form');
        $this->assertTrue(strpos($body, "'role', 'alert'") !== false || strpos($body, '"role", "alert"') !== false,
            'the summary is not announced to a screen reader');
    }

    public function testSummaryDisappearsWhenTheFormIsValid(): void
    {
        $src = $this->lib('formval_nl.js');
        $this->assertTrue(strpos($src, 'function form_errors_clear') !== false, 'form_errors_clear() is missing');
        // A stale list of errors that are already fixed is worse than none.
        $this->assertTrue(strpos($this->functionBody($src, 'form_field_blur'), 'form_errors_render') !== false,
            'repairing a field does not update the summary');
    }

    public function testSummaryLinesRevealTheirFieldIncludingItsTab(): void
    {
        $src = $this->lib('formval_nl.js');
        $body = $this->functionBody($src, 'form_field_reveal');
        $this->assertTrue(strpos($body, 'tabpanel') !== false, 'form_field_reveal() no longer activates the tab');
        $this->assertTrue(strpos($body, 'focus') !== false, 'form_field_reveal() no longer focuses the field');
        $this->assertTrue(strpos($this->functionBody($src, 'form_errors_regel'), 'form_field_reveal') !== false,
            'a summary line does not jump to its field');
    }

    // ========================================================================
    // A summary line is a button, not a link
    // ========================================================================

    /**
     * The line does not navigate — it opens the tab the field sits in and focuses it.
     * As an <a href="#"> that was announced as a link going nowhere (the box carries
     * role=alert, so a screen reader reads the whole list), and any path that misses
     * preventDefault() writes a '#' into the URL — which this app uses for state.
     * type=button is required as well: the summary sits INSIDE the form, and a button
     * without a type submits it.
     */
    public function testSummaryLineIsAButtonAndNotAnAnchor(): void
    {
        $body = $this->functionBody($this->lib('formval_nl.js'), 'form_errors_regel');

        $this->assertTrue(strpos($body, "createElement('button')") !== false,
            'a summary line is not a button');
        $this->assertTrue(strpos($body, "type = 'button'") !== false,
            "the button has no type='button' — clicking an error would submit the form");
        $this->assertTrue(strpos($body, "href") === false,
            'the anchor is back; a summary line navigates nowhere and must not carry an href');
    }

    // ========================================================================
    // Styling
    // ========================================================================

    public function testSummaryIsStyled(): void
    {
        $css = $this->lib('library.css');
        $this->assertTrue(strpos($css, '.form_errors') !== false, '.form_errors has no styling');
        // The field marker is the other half of the change and predates it.
        $this->assertTrue(strpos($css, '.invalid') !== false, '.invalid styling disappeared');
        // A bare <button> inherits the browser's own look; without this it stops
        // reading as a line of text in the list.
        $this->assertTrue(strpos($css, '.form_errors__veld') !== false,
            '.form_errors__veld has no styling — the button keeps its browser chrome');
    }

    /** The intro line above the list is not bold — the red frame already carries the alarm. */
    public function testSummaryIntroIsNotBold(): void
    {
        $css = $this->lib('library.css');
        $pos = strpos($css, '.form_errors__intro {');
        $this->assertTrue($pos !== false, '.form_errors__intro has no styling');
        $regel = substr($css, $pos, strpos($css, '}', $pos) - $pos);
        $this->assertTrue(strpos($regel, 'font-weight:normal') !== false,
            'the intro line of the error summary is bold again: ' . trim($regel));
    }

    /**
     * No underline at rest, underline on hover/focus.
     *
     * A summary with five errors is five lines in a list; underlining them all turns the
     * block into noise. Colour and position already say they are clickable. On hover the
     * underline comes back, because that is what tells you which line you are on — and
     * focus gets it too, otherwise keyboard users lose that cue entirely.
     */
    public function testSummaryLinksUnderlineOnlyOnHover(): void
    {
        $css = $this->lib('library.css');
        // The rule at rest: both the link and the field button, together on one line.
        $rust = '.form_errors a, .form_errors__veld {';
        $pos  = strpos($css, $rust);
        $this->assertTrue($pos !== false, 'the rest state of .form_errors a is gone');
        $regel = substr($css, $pos, strpos($css, '}', $pos) - $pos);
        $this->assertTrue(strpos($regel, 'text-decoration:none') !== false,
            'the links in the error summary are underlined at rest: ' . trim($regel));

        // And the hover/focus rule, which must carry the underline.
        $hoverPos = strpos($css, '.form_errors a:hover');
        $this->assertTrue($hoverPos !== false, 'the hover state of .form_errors a is gone');
        $hover = substr($css, $hoverPos, strpos($css, '}', $hoverPos) - $hoverPos);
        $this->assertTrue(strpos($hover, 'text-decoration:underline') !== false,
            'hovering an error line gives no underline: ' . trim($hover));
        // Keyboard users see the same thing.
        $this->assertTrue(strpos($hover, ':focus') !== false,
            'only :hover is styled — a focused error line shows nothing');
    }

    // ========================================================================
    // The bundles the site actually loads
    // ========================================================================

    public function testMinifiedBundlesAreRebuilt(): void
    {
        $root = dirname(__DIR__, 2) . '/library/';
        foreach ([['formval_nl.js', 'formval_nl.min.js'], ['library.css', 'library.min.css']] as $paar) {
            [$bron, $min] = $paar;
            $this->assertTrue(is_file($root . $min), "library/$min is missing");
            $this->assertTrue(
                filemtime($root . $min) >= filemtime($root . $bron),
                "library/$min is older than library/$bron — run `npm run build:minify` in cma/"
            );
        }
    }

    public function testBundlesCarryTheNewBehaviour(): void
    {
        // The site loads the .min files. Asserting the source alone would pass while the
        // flag is still live in production.
        $min = $this->lib('formval_nl.min.js');
        $this->assertTrue(strpos($min, 'tooltip.show') === false, 'the bundle still shows the flag');
        $this->assertTrue(strpos($min, 'form_errors') !== false, 'the bundle has no summary');
        $this->assertTrue(strpos($this->lib('library.min.css'), 'form_errors') !== false,
            'the CSS bundle has no .form_errors styling');
    }
}
