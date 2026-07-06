<?php
/**
 * Tests for App\Library\Cookie
 *
 * Run with: php tests/TestRunner.php CookieTest
 *
 * Read/parse logic (get/has/all/getTyped) is fully testable by manipulating the
 * $_COOKIE superglobal directly. Write logic (set/delete/clear) calls
 * setcookie(), which in the CLI test runner returns false because the runner has
 * already emitted output (headers "sent"). We therefore only assert that those
 * methods don't throw and return a bool — never that a real cookie header is
 * emitted. setUp/tearDown snapshot & restore $_COOKIE so no state leaks.
 */

require_once __DIR__ . '/TestRunner.php';

use App\Library\Cookie;

class CookieTest extends TestCase
{
    private array $savedCookie;

    public function setUp(): void
    {
        $this->savedCookie = $_COOKIE;
        $_COOKIE = [];
    }

    public function tearDown(): void
    {
        $_COOKIE = $this->savedCookie;
    }

    // ========================================================================
    // get
    // ========================================================================

    public function testGetReturnsExistingValue(): void
    {
        $_COOKIE['user'] = 'alice';
        $this->assertEquals('alice', Cookie::get('user'));
    }

    public function testGetMissingReturnsEmptyStringByDefault(): void
    {
        $this->assertEquals('', Cookie::get('missing'));
    }

    public function testGetMissingReturnsProvidedDefault(): void
    {
        $this->assertEquals('fallback', Cookie::get('missing', 'fallback'));
    }

    public function testGetMissingReturnsNullDefault(): void
    {
        $this->assertNull(Cookie::get('missing', null));
    }

    public function testGetPresentEmptyStringNotReplacedByDefault(): void
    {
        $_COOKIE['empty'] = '';
        // '' is present, so the ?? default must NOT kick in.
        $this->assertSame('', Cookie::get('empty', 'default'));
    }

    public function testGetPresentZeroStringNotReplacedByDefault(): void
    {
        $_COOKIE['zero'] = '0';
        $this->assertSame('0', Cookie::get('zero', 'default'));
    }

    public function testGetIsCaseSensitive(): void
    {
        $_COOKIE['Name'] = 'v';
        // Cookie names are case-sensitive (unlike Application keys).
        $this->assertEquals('', Cookie::get('name'));
        $this->assertEquals('v', Cookie::get('Name'));
    }

    // ========================================================================
    // has
    // ========================================================================

    public function testHasReturnsTrueForExisting(): void
    {
        $_COOKIE['token'] = 'abc';
        $this->assertTrue(Cookie::has('token'));
    }

    public function testHasReturnsFalseForMissing(): void
    {
        $this->assertFalse(Cookie::has('nope'));
    }

    public function testHasReturnsTrueForEmptyStringValue(): void
    {
        // isset('') is true, so an empty-string cookie counts as present.
        $_COOKIE['blank'] = '';
        $this->assertTrue(Cookie::has('blank'));
    }

    public function testHasReturnsTrueForZeroStringValue(): void
    {
        $_COOKIE['z'] = '0';
        $this->assertTrue(Cookie::has('z'));
    }

    // ========================================================================
    // all
    // ========================================================================

    public function testAllReturnsEverything(): void
    {
        $_COOKIE['a'] = '1';
        $_COOKIE['b'] = '2';
        $all = Cookie::all();
        $this->assertCount(2, $all);
        $this->assertEquals('1', $all['a']);
        $this->assertEquals('2', $all['b']);
    }

    public function testAllReturnsEmptyArrayWhenNoCookies(): void
    {
        $this->assertSame([], Cookie::all());
    }

    // ========================================================================
    // getTyped
    // ========================================================================

    public function testGetTypedInt(): void
    {
        $_COOKIE['n'] = '42';
        $this->assertSame(42, Cookie::getTyped('n', 'int'));
    }

    public function testGetTypedIntFromNonNumericIsZero(): void
    {
        $_COOKIE['n'] = 'abc';
        $this->assertSame(0, Cookie::getTyped('n', 'int'));
    }

    public function testGetTypedIntZeroValueNotDefault(): void
    {
        // '0' is present (not the '' sentinel), so it must cast, not fall back.
        $_COOKIE['n'] = '0';
        $this->assertSame(0, Cookie::getTyped('n', 'int', 999));
    }

    public function testGetTypedFloat(): void
    {
        $_COOKIE['f'] = '3.14';
        $this->assertSame(3.14, Cookie::getTyped('f', 'float'));
    }

    public function testGetTypedBoolTrueValues(): void
    {
        foreach (['true', '1', 'yes', 'on'] as $truthy) {
            $_COOKIE['b'] = $truthy;
            $this->assertTrue(Cookie::getTyped('b', 'bool'), "'{$truthy}' should be true");
        }
    }

    public function testGetTypedBoolFalseValues(): void
    {
        foreach (['false', '0', 'no', 'off'] as $falsy) {
            $_COOKIE['b'] = $falsy;
            $this->assertFalse(Cookie::getTyped('b', 'bool'), "'{$falsy}' should be false");
        }
    }

    public function testGetTypedArray(): void
    {
        $_COOKIE['arr'] = json_encode(['x' => 1, 'y' => 2]);
        $result = Cookie::getTyped('arr', 'array');
        $this->assertIsArray($result);
        $this->assertEquals(1, $result['x']);
        $this->assertEquals(2, $result['y']);
    }

    public function testGetTypedArrayFromList(): void
    {
        $_COOKIE['arr'] = '[1,2,3]';
        $this->assertSame([1, 2, 3], Cookie::getTyped('arr', 'array'));
    }

    public function testGetTypedArrayInvalidJsonReturnsDefault(): void
    {
        $_COOKIE['arr'] = 'not json';
        $this->assertSame(['fallback'], Cookie::getTyped('arr', 'array', ['fallback']));
    }

    public function testGetTypedUnknownTypeReturnsRawString(): void
    {
        $_COOKIE['x'] = 'raw-value';
        $this->assertSame('raw-value', Cookie::getTyped('x', 'string'));
    }

    public function testGetTypedMissingReturnsDefault(): void
    {
        // Missing cookie -> get() returns '' -> getTyped returns default.
        $this->assertNull(Cookie::getTyped('missing', 'int'));
        $this->assertSame(7, Cookie::getTyped('missing', 'int', 7));
    }

    public function testGetTypedEmptyStringReturnsDefault(): void
    {
        // A present-but-empty cookie is treated as "missing" by getTyped.
        $_COOKIE['e'] = '';
        $this->assertSame('D', Cookie::getTyped('e', 'int', 'D'));
    }

    // ========================================================================
    // set — CLI: setcookie() fails after output, so we only assert no-throw/bool
    // ========================================================================

    public function testSetReturnsBoolAndDoesNotThrow(): void
    {
        $result = @Cookie::set('c', 'v', 3600);
        $this->assertTrue(is_bool($result), 'set() should return a bool');
    }

    public function testSetAcceptsNullValueWithoutThrowing(): void
    {
        // The ?? '' guard inside set() must handle a null value.
        $result = @Cookie::set('c', null);
        $this->assertTrue(is_bool($result));
    }

    public function testSetWithAllParametersDoesNotThrow(): void
    {
        $result = @Cookie::set('c', 'v', 60, '/app', 'example.com', true, false);
        $this->assertTrue(is_bool($result));
    }

    // ========================================================================
    // delete — CLI: setcookie() fails after output, assert no-throw/bool only
    // ========================================================================

    public function testDeleteReturnsBoolAndDoesNotThrow(): void
    {
        $_COOKIE['gone'] = 'x';
        $result = @Cookie::delete('gone');
        $this->assertTrue(is_bool($result));
    }

    public function testDeleteNonExistentDoesNotThrow(): void
    {
        $result = @Cookie::delete('never');
        $this->assertTrue(is_bool($result));
    }

    // ========================================================================
    // clear — iterates existing cookies calling delete(); assert no-throw only
    // ========================================================================

    public function testClearDoesNotThrow(): void
    {
        $_COOKIE['a'] = '1';
        $_COOKIE['b'] = '2';
        @Cookie::clear();
        // No assertion on $_COOKIE contents: in CLI setcookie() fails so the
        // unset inside delete() never runs. We only verify clear() is safe.
        $this->assertTrue(true);
    }

    public function testClearOnEmptyCookieJarDoesNotThrow(): void
    {
        @Cookie::clear();
        $this->assertTrue(true);
    }
}
