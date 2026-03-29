<?php
/**
 * Tests for App\Library\Str
 *
 * Run with: php tests/TestRunner.php StrTest
 *
 * Note: The installed helpers package may have stricter type hints than the
 * source code in app/library/. Tests that rely on nullable params or methods
 * only in the source version are guarded with method_exists / try-catch.
 */

require_once __DIR__ . '/TestRunner.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Library\Str;

class StrTest extends TestCase
{
    /**
     * Helper: check if a method accepts null for first param
     */
    private function acceptsNull(string $method): bool
    {
        $ref = new ReflectionMethod(Str::class, $method);
        $params = $ref->getParameters();
        return !empty($params) && $params[0]->allowsNull();
    }

    // ========================================================================
    // removeDiacritics
    // ========================================================================

    public function testRemoveDiacriticsBasic(): void
    {
        $this->assertEquals('Hello World', Str::removeDiacritics('Hello World'));
    }

    public function testRemoveDiacriticsAccentedChars(): void
    {
        $this->assertEquals('eeeE', Str::removeDiacritics('éèêË'));
    }

    public function testRemoveDiacriticsGerman(): void
    {
        $this->assertEquals('aou', Str::removeDiacritics('äöü'));
        $this->assertEquals('ss', Str::removeDiacritics('ß'));
    }

    public function testRemoveDiacriticsNordic(): void
    {
        $this->assertEquals('AE', Str::removeDiacritics('Æ'));
        $this->assertEquals('ae', Str::removeDiacritics('æ'));
        $this->assertEquals('O', Str::removeDiacritics('Ø'));
    }

    public function testRemoveDiacriticsEmpty(): void
    {
        $this->assertEquals('', Str::removeDiacritics(''));
    }

    public function testRemoveDiacriticsNull(): void
    {
        if (!$this->acceptsNull('removeDiacritics')) { return; }
        $this->assertEquals('', Str::removeDiacritics(null));
    }

    public function testReplaceDiacriticsAlias(): void
    {
        if (!method_exists(Str::class, 'replaceDiacritics')) { return; }
        $this->assertEquals('cafe', Str::replaceDiacritics('café'));
    }

    // ========================================================================
    // slug
    // ========================================================================

    public function testSlugBasic(): void
    {
        $this->assertEquals('hello-world', Str::slug('Hello World'));
    }

    public function testSlugDiacritics(): void
    {
        $this->assertEquals('hello-world', Str::slug('Héllo Wörld!'));
    }

    public function testSlugSpecialChars(): void
    {
        $this->assertEquals('test-123', Str::slug('  Test  123  '));
    }

    public function testSlugCustomSeparator(): void
    {
        $this->assertEquals('hello_world', Str::slug('Hello World', '_'));
    }

    public function testSlugMultipleSeparators(): void
    {
        $this->assertEquals('a-b-c', Str::slug('a---b---c'));
    }

    // ========================================================================
    // padRight / padLeft
    // ========================================================================

    public function testPadRight(): void
    {
        $this->assertEquals('hi   ', Str::padRight('hi', 5));
    }

    public function testPadRightCustomChar(): void
    {
        $this->assertEquals('hi...', Str::padRight('hi', 5, '.'));
    }

    public function testPadLeft(): void
    {
        $this->assertEquals('   hi', Str::padLeft('hi', 5));
    }

    public function testPadLeftCustomChar(): void
    {
        $this->assertEquals('000hi', Str::padLeft('hi', 5, '0'));
    }

    // ========================================================================
    // truncate
    // ========================================================================

    public function testTruncateShortString(): void
    {
        $this->assertEquals('hello', Str::truncate('hello', 10));
    }

    public function testTruncateLongString(): void
    {
        $this->assertEquals('hello w...', Str::truncate('hello world', 10));
    }

    public function testTruncateCustomSuffix(): void
    {
        $this->assertEquals('hello~', Str::truncate('hello world', 6, '~'));
    }

    public function testTruncateExactLength(): void
    {
        $this->assertEquals('hello', Str::truncate('hello', 5));
    }

    // ========================================================================
    // between
    // ========================================================================

    public function testBetweenBasic(): void
    {
        $this->assertEquals('world', Str::between('hello [world] end', '[', ']'));
    }

    public function testBetweenNotFound(): void
    {
        $this->assertEquals('', Str::between('hello world', '[', ']'));
    }

    public function testBetweenNoEnd(): void
    {
        $this->assertEquals('', Str::between('hello [world', '[', ']'));
    }

    public function testBetweenHtmlTag(): void
    {
        $this->assertEquals('content', Str::between('<b>content</b>', '<b>', '</b>'));
    }

    // ========================================================================
    // sanitize
    // ========================================================================

    public function testSanitizeDefault(): void
    {
        $this->assertEquals('Hello123', Str::sanitize('Hello! @#$% 123'));
    }

    public function testSanitizeCustomChars(): void
    {
        $this->assertEquals('123', Str::sanitize('abc123xyz', '0-9'));
    }

    public function testSanitizeEmpty(): void
    {
        $this->assertEquals('', Str::sanitize(''));
    }

    // ========================================================================
    // numbersOnly / numbersOnlyAndComma
    // ========================================================================

    public function testNumbersOnly(): void
    {
        $this->assertEquals('123', Str::numbersOnly('abc123def'));
    }

    public function testNumbersOnlyEmpty(): void
    {
        $this->assertEquals('', Str::numbersOnly(''));
    }

    public function testNumbersOnlyAndComma(): void
    {
        $this->assertEquals('1,2,3', Str::numbersOnlyAndComma('ID: 1, 2, 3'));
    }

    public function testNumbersOnlyAndCommaEmpty(): void
    {
        $this->assertEquals('', Str::numbersOnlyAndComma(''));
    }

    // ========================================================================
    // removeSpaces
    // ========================================================================

    public function testRemoveSpaces(): void
    {
        $this->assertEquals('helloworld', Str::removeSpaces('hello world'));
    }

    public function testRemoveSpacesEmpty(): void
    {
        $this->assertEquals('', Str::removeSpaces(''));
    }

    // ========================================================================
    // trim
    // ========================================================================

    public function testTrimBasic(): void
    {
        $this->assertEquals('hello', Str::trim('  hello  '));
    }

    public function testTrimCustomChars(): void
    {
        $this->assertEquals('hello', Str::trim('--hello--', '-'));
    }

    public function testTrimEmpty(): void
    {
        $this->assertEquals('', Str::trim(''));
    }

    // ========================================================================
    // stripEnd / stripStart
    // ========================================================================

    public function testStripEnd(): void
    {
        $this->assertEquals('hello', Str::stripEnd('hello.txt', ['.txt']));
    }

    public function testStripEndNoMatch(): void
    {
        $this->assertEquals('hello.doc', Str::stripEnd('hello.doc', ['.txt']));
    }

    public function testStripEndMultiplePatterns(): void
    {
        $this->assertEquals('hello', Str::stripEnd('hello.txt', ['.doc', '.txt']));
    }

    public function testStripStart(): void
    {
        $this->assertEquals('world', Str::stripStart('hello world', ['hello ']));
    }

    public function testStripStartNoMatch(): void
    {
        $this->assertEquals('world', Str::stripStart('world', ['hello ']));
    }

    // ========================================================================
    // startsWith / endsWith / contains
    // ========================================================================

    public function testStartsWithTrue(): void
    {
        $this->assertTrue(Str::startsWith('hello world', 'hello'));
    }

    public function testStartsWithFalse(): void
    {
        $this->assertFalse(Str::startsWith('hello world', 'world'));
    }

    public function testStartsWithEmpty(): void
    {
        $this->assertTrue(Str::startsWith('hello', ''));
    }

    public function testEndsWithTrue(): void
    {
        $this->assertTrue(Str::endsWith('hello world', 'world'));
    }

    public function testEndsWithFalse(): void
    {
        $this->assertFalse(Str::endsWith('hello world', 'hello'));
    }

    public function testContainsCaseInsensitive(): void
    {
        $this->assertTrue(Str::contains('Hello World', 'hello'));
    }

    public function testContainsCaseSensitive(): void
    {
        $this->assertFalse(Str::contains('Hello World', 'hello', true));
    }

    public function testContainsExactMatch(): void
    {
        $this->assertTrue(Str::contains('Hello World', 'Hello', true));
    }

    // ========================================================================
    // replace
    // ========================================================================

    public function testReplaceCaseInsensitive(): void
    {
        $this->assertEquals('Hi World', Str::replace('Hello World', 'hello', 'Hi'));
    }

    public function testReplaceCaseSensitive(): void
    {
        $this->assertEquals('Hello World', Str::replace('Hello World', 'hello', 'Hi', true));
    }

    public function testReplaceMultiple(): void
    {
        $this->assertEquals('Hi Hi', Str::replace('Hello Hello', 'Hello', 'Hi'));
    }

    // ========================================================================
    // length / upper / lower / capitalize
    // ========================================================================

    public function testLength(): void
    {
        $this->assertEquals(5, Str::length('hello'));
    }

    public function testLengthEmpty(): void
    {
        $this->assertEquals(0, Str::length(''));
    }

    public function testUpper(): void
    {
        $this->assertEquals('HELLO', Str::upper('hello'));
    }

    public function testUpperEmpty(): void
    {
        $this->assertEquals('', Str::upper(''));
    }

    public function testLower(): void
    {
        $this->assertEquals('hello', Str::lower('HELLO'));
    }

    public function testLowerEmpty(): void
    {
        $this->assertEquals('', Str::lower(''));
    }

    public function testCapitalize(): void
    {
        $this->assertEquals('Hello', Str::capitalize('hello'));
    }

    public function testCapitalizeAlreadyUpper(): void
    {
        $this->assertEquals('HELLO', Str::capitalize('HELLO'));
    }

    public function testFirstUpper(): void
    {
        if (!method_exists(Str::class, 'firstUpper')) { return; }
        $this->assertEquals('Hello world', Str::firstUpper('hello world'));
    }

    // ========================================================================
    // padZero (may not be in installed version)
    // ========================================================================

    public function testPadZeroDefault(): void
    {
        if (!method_exists(Str::class, 'padZero')) { return; }
        $this->assertEquals('05', Str::padZero(5));
    }

    public function testPadZeroCustomLength(): void
    {
        if (!method_exists(Str::class, 'padZero')) { return; }
        $this->assertEquals('00123', Str::padZero(123, 5));
    }

    public function testPadZeroAlreadyLong(): void
    {
        if (!method_exists(Str::class, 'padZero')) { return; }
        $this->assertEquals('123', Str::padZero(123, 2));
    }

    // ========================================================================
    // JscriptSafe
    // ========================================================================

    public function testJscriptSafeBasic(): void
    {
        $this->assertEquals("'hello'", Str::JscriptSafe('hello'));
    }

    public function testJscriptSafeEmpty(): void
    {
        $this->assertEquals("''", Str::JscriptSafe(''));
    }

    public function testJscriptSafeQuotes(): void
    {
        $result = Str::JscriptSafe("it's a \"test\"");
        $this->assertStringContainsString("\\'", $result);
        $this->assertStringContainsString('\\"', $result);
    }

    public function testJscriptSafeBrackets(): void
    {
        $result = Str::JscriptSafe('array[0]');
        $this->assertStringContainsString('String.fromCharCode(91)', $result);
        $this->assertStringContainsString('String.fromCharCode(93)', $result);
    }

    public function testJscriptSafeNewlines(): void
    {
        $result = Str::JscriptSafe("line1\r\nline2");
        $this->assertStringContainsString('\\r\\n', $result);
    }

    public function testJscriptSafeBackslash(): void
    {
        $result = Str::JscriptSafe('path\\to\\file');
        $this->assertStringContainsString('\\\\', $result);
    }

    // ========================================================================
    // toUtf8 (may not be in installed version)
    // ========================================================================

    public function testToUtf8ValidString(): void
    {
        if (!method_exists(Str::class, 'toUtf8')) { return; }
        $this->assertEquals('hello', Str::toUtf8('hello'));
    }

    public function testToUtf8Array(): void
    {
        if (!method_exists(Str::class, 'toUtf8')) { return; }
        $input = ['name' => 'hello', 'nested' => ['a' => 'world']];
        $result = Str::toUtf8($input);
        $this->assertIsArray($result);
        $this->assertEquals('hello', $result['name']);
    }
}
