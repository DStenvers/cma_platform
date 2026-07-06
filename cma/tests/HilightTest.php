<?php
/**
 * Unit tests for App\Library\Hilight (text highlighting helper).
 *
 * Covers: text(), multiple(), setDefaultClass()/getDefaultClass().
 * Focus areas: case-insensitive matching with original-case preservation,
 * empty inputs, absent terms, adjacent matches, regex-metacharacter safety
 * (the term must be matched literally, NOT interpreted as a regex), unicode,
 * and the wrapping CSS class.
 *
 * Run with: php tests/TestRunner.php HilightTest
 */

require_once __DIR__ . '/TestRunner.php';

use App\Library\Hilight;

class HilightTest extends TestCase
{
    /** @var string Saved global default class, restored in tearDown. */
    private string $savedDefaultClass;

    public function setUp(): void
    {
        $this->savedDefaultClass = Hilight::getDefaultClass();
    }

    public function tearDown(): void
    {
        Hilight::setDefaultClass($this->savedDefaultClass);
    }

    // --- text(): basic highlighting -------------------------------------

    public function testHighlightsTermInText(): void
    {
        $result = Hilight::text('Hello World', 'World');
        $this->assertStringContainsString('<span class="s">World</span>', $result);
        $this->assertStringContainsString('Hello ', $result);
    }

    public function testDefaultClassIsS(): void
    {
        $this->assertEquals('s', Hilight::getDefaultClass());
    }

    public function testCustomClassArgument(): void
    {
        $result = Hilight::text('Hello World', 'World', 'hl');
        $this->assertStringContainsString('<span class="hl">World</span>', $result);
        $this->assertStringNotContainsString('class="s"', $result);
    }

    public function testCaseInsensitiveMatch(): void
    {
        $result = Hilight::text('WORLD wide', 'world');
        // The match itself is highlighted even though case differs.
        $this->assertStringContainsString('<span class="s">WORLD</span>', $result);
    }

    public function testPreservesOriginalCase(): void
    {
        // Search term casing must not overwrite the matched text's casing.
        $result = Hilight::text('Hello World', 'wOrLd');
        $this->assertStringContainsString('<span class="s">World</span>', $result);
        $this->assertStringNotContainsString('wOrLd', $result);
    }

    public function testMultipleOccurrences(): void
    {
        $result = Hilight::text('ab ab ab', 'ab');
        $this->assertEquals(3, substr_count($result, '<span class="s">ab</span>'));
    }

    // --- text(): empty / absent inputs ----------------------------------

    public function testEmptyTermReturnsTextUnchanged(): void
    {
        // Must NOT wrap the whole string and must not crash.
        $result = Hilight::text('Hello', '');
        $this->assertEquals('Hello', $result);
        $this->assertStringNotContainsString('<span', $result);
    }

    public function testEmptyTextReturnsEmpty(): void
    {
        $result = Hilight::text('', 'anything');
        $this->assertEquals('', $result);
    }

    public function testTermNotPresent(): void
    {
        $result = Hilight::text('Hello World', 'xyz');
        $this->assertEquals('Hello World', $result);
        $this->assertStringNotContainsString('<span', $result);
    }

    // --- text(): adjacency ----------------------------------------------

    public function testAdjacentMatchesAreNonOverlapping(): void
    {
        // 'aaaa' with term 'aa' yields two non-overlapping matches.
        $result = Hilight::text('aaaa', 'aa');
        $this->assertEquals(2, substr_count($result, '<span class="s">aa</span>'));
    }

    // --- text(): regex-metacharacter safety (the critical group) --------

    public function testDotIsLiteralNotWildcard(): void
    {
        // If '.' were treated as a regex wildcard it would wrap every char.
        // It must only wrap the literal dot.
        $result = Hilight::text('a.b', '.');
        $this->assertEquals('a<span class="s">.</span>b', $result);
    }

    public function testDotStarDoesNotMatchEverything(): void
    {
        // '.*' as a regex matches the entire string; as a literal it is absent.
        $result = Hilight::text('abc', '.*');
        $this->assertEquals('abc', $result);
        $this->assertStringNotContainsString('<span', $result);
    }

    public function testAsteriskIsLiteral(): void
    {
        $result = Hilight::text('a*b', '*');
        $this->assertEquals('a<span class="s">*</span>b', $result);
    }

    public function testParenAndBracketAreLiteral(): void
    {
        $resultParen = Hilight::text('f(x)', '(');
        $this->assertEquals('f<span class="s">(</span>x)', $resultParen);

        $resultBracket = Hilight::text('a[i]b', '[');
        $this->assertEquals('a<span class="s">[</span>i]b', $resultBracket);
    }

    public function testForwardSlashInTermIsHandled(): void
    {
        // The delimiter is '/', so a term containing '/' must not break the pattern.
        $result = Hilight::text('path/to/file', '/to/');
        $this->assertStringContainsString('<span class="s">/to/</span>', $result);
    }

    // --- text(): unicode ------------------------------------------------

    public function testUnicodeTerm(): void
    {
        $result = Hilight::text('un café noir', 'café');
        $this->assertStringContainsString('<span class="s">café</span>', $result);
    }

    // --- text(): surrounding markup passthrough -------------------------
    // NOTE: Hilight does NOT HTML-escape the text or the term (see report).
    // These tests lock in the actual current behaviour.

    public function testSurroundingHtmlIsNotEscaped(): void
    {
        // Documents that raw HTML around the match passes through untouched.
        $result = Hilight::text('<b>bold</b> text', 'text');
        $this->assertStringContainsString('<b>bold</b>', $result);
        $this->assertStringContainsString('<span class="s">text</span>', $result);
    }

    public function testMatchedHtmlTermIsNotEscaped(): void
    {
        // Term containing '<' is matched literally and emitted unescaped.
        $result = Hilight::text('value <x> here', '<x>');
        $this->assertStringContainsString('<span class="s"><x></span>', $result);
    }

    // --- multiple() -----------------------------------------------------

    public function testMultipleTerms(): void
    {
        $result = Hilight::multiple('foo bar baz', ['foo', 'baz']);
        $this->assertStringContainsString('<span class="s">foo</span>', $result);
        $this->assertStringContainsString('<span class="s">baz</span>', $result);
        // 'bar' was not in the list, so it stays unwrapped.
        $this->assertStringNotContainsString('<span class="s">bar</span>', $result);
    }

    public function testMultipleSkipsEmptyTerms(): void
    {
        $result = Hilight::multiple('hello world', ['', 'world']);
        $this->assertEquals(1, substr_count($result, '<span class="s">'));
        $this->assertStringContainsString('<span class="s">world</span>', $result);
    }

    public function testMultipleWithNoTerms(): void
    {
        $result = Hilight::multiple('unchanged text', []);
        $this->assertEquals('unchanged text', $result);
    }

    public function testMultipleCustomClass(): void
    {
        // Terms deliberately avoid letters that occur in the injected markup
        // (<span class="mark">) to sidestep the re-scan quirk documented below.
        $result = Hilight::multiple('x y z', ['x', 'y'], 'mark');
        $this->assertStringContainsString('<span class="mark">x</span>', $result);
        $this->assertStringContainsString('<span class="mark">y</span>', $result);
    }

    public function testMultipleRescansInjectedMarkup(): void
    {
        // KNOWN QUIRK (see report): multiple() feeds the output of one term's
        // highlighting back into the next term's search. A later term that
        // matches characters in the injected <span class="..."> markup corrupts
        // the result. Here the second term 'c' matches the 'c' of 'class'.
        $result = Hilight::multiple('a b c', ['a', 'c'], 'mark');
        // The 'c' inside class="mark" gets wrapped, breaking the first span.
        $this->assertStringContainsString('<span class="mark">c</span>lass', $result);
    }

    // --- setDefaultClass() / getDefaultClass() --------------------------

    public function testSetAndGetDefaultClass(): void
    {
        Hilight::setDefaultClass('hilite');
        $this->assertEquals('hilite', Hilight::getDefaultClass());
    }

    public function testDefaultClassAffectsText(): void
    {
        Hilight::setDefaultClass('mark');
        $result = Hilight::text('Hello World', 'World');
        $this->assertStringContainsString('<span class="mark">World</span>', $result);
    }

    public function testExplicitClassOverridesDefault(): void
    {
        Hilight::setDefaultClass('mark');
        $result = Hilight::text('Hello World', 'World', 'other');
        $this->assertStringContainsString('<span class="other">World</span>', $result);
        $this->assertStringNotContainsString('class="mark"', $result);
    }
}
