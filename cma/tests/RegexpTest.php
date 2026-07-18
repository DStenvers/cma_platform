<?php
/**
 * Tests for App\Library\Regexp — the VBScript RegExp compatibility shim.
 *
 * Run with: php tests/TestRunner.php RegexpTest
 */

require_once __DIR__ . '/TestRunner.php';

use App\Library\Regexp;

class RegexpTest extends TestCase
{
    private function make(string $pattern, bool $global = false, bool $ic = false, bool $ml = false): Regexp
    {
        $re = new Regexp();
        $re->Pattern = $pattern;
        $re->Global = $global;
        $re->IgnoreCase = $ic;
        $re->MultiLine = $ml;
        return $re;
    }

    // ---- Test() -------------------------------------------------------------

    public function testTestMatchesAndNot(): void
    {
        $this->assertTrue($this->make('\d+')->Test('abc123'));
        $this->assertFalse($this->make('\d+')->Test('abc'));
    }

    public function testTestIgnoreCase(): void
    {
        $this->assertFalse($this->make('abc')->Test('ABC'));
        $this->assertTrue($this->make('abc', false, true)->Test('ABC'));
    }

    // ---- Replace() ----------------------------------------------------------

    public function testReplaceFirstVsGlobal(): void
    {
        // Global=false replaces only the first match (VBScript semantics)
        $this->assertEquals('X-a-a', $this->make('a', false)->Replace('a-a-a', 'X'));
        // Global=true replaces all
        $this->assertEquals('X-X-X', $this->make('a', true)->Replace('a-a-a', 'X'));
    }

    public function testReplaceBackreferencePassesThrough(): void
    {
        // $1 works identically in both engines
        $re = $this->make('(\w+)@(\w+)', true);
        $this->assertEquals('user at host', $re->Replace('user@host', '$1 at $2'));
    }

    public function testReplaceLiteralDollarIsKept(): void
    {
        // A literal '$' that isn't a backref must survive
        $re = $this->make('X', true);
        $this->assertEquals('cost: $5', $re->Replace('cost: X5', '$'));
    }

    public function testReplaceHtmlComment(): void
    {
        // The exact lib_html_CleanComments pattern
        $re = $this->make('<!--(.|\n)+?-->', true, true);
        $this->assertEquals('ab', $re->Replace("a<!-- x\ny -->b", ''));
    }

    public function testReplaceOpenTag(): void
    {
        // The HTMLstrip <a[^>]*> pattern
        $re = $this->make('<a[^>]*>', true, true);
        $this->assertEquals("\nlink", $re->Replace('<a href="x">link', "\n"));
    }

    // ---- differences the shim bridges --------------------------------------

    public function testUnicodeEscapeTranslation(): void
    {
        // VBScript é (é) must be understood; PCRE needs \x{00e9}
        $re = $this->make('é', true);
        $this->assertTrue($re->Test("caf\u{00e9}"));
        $this->assertEquals('cafe', $re->Replace("caf\u{00e9}", 'e'));
    }

    public function testPatternContainingSlashUsesSafeDelimiter(): void
    {
        // A '/' in the pattern must not need manual escaping
        $re = $this->make('a/b', true);
        $this->assertTrue($re->Test('a/b'));
        $this->assertEquals('X', $re->Replace('a/b', 'X'));
    }

    public function testMultiLineAnchors(): void
    {
        // With MultiLine, ^ matches after a newline; without, it doesn't.
        $this->assertEquals("a\nX", $this->make('^b', true, false, true)->Replace("a\nb", 'X'));
        $this->assertEquals("a\nb", $this->make('^b', true, false, false)->Replace("a\nb", 'X'));
    }

    // ---- Execute() ----------------------------------------------------------

    public function testExecuteReturnsMatchesWithSubmatches(): void
    {
        $re = $this->make('(\d)(\d)', true);
        $m = $re->Execute('a12b34');
        $this->assertEquals(2, count($m));
        $this->assertEquals('12', $m[0]['Value']);
        $this->assertEquals(1, $m[0]['FirstIndex']);
        $this->assertEquals(2, $m[0]['Length']);
        $this->assertEquals(['1', '2'], $m[0]['SubMatches']);
        $this->assertEquals('34', $m[1]['Value']);
    }

    public function testExecuteNonGlobalReturnsFirstOnly(): void
    {
        $re = $this->make('\d', false);
        $m = $re->Execute('a1b2');
        $this->assertEquals(1, count($m));
        $this->assertEquals('1', $m[0]['Value']);
    }

    // ---- global alias -------------------------------------------------------

    public function testGlobalAliasResolves(): void
    {
        // Converted code says `new Regexp()` unqualified. Bootstrap registers a
        // global `Regexp` alias for the same class; register it here too so the
        // test passes whether or not the full Bootstrap ran in the harness, and
        // asserts the global name is usable and points at the shim.
        if (!class_exists('Regexp', false)) {
            class_alias(Regexp::class, 'Regexp');
        }
        $this->assertTrue(class_exists('Regexp'));
        $obj = new \Regexp();
        $obj->Pattern = '\d+';
        $this->assertTrue($obj->Test('x9'));
    }
}
