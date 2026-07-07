<?php
/**
 * Tests for App\Library\Session — data get/set/typed accessors + flash.
 *
 * Run with: php tests/TestRunner.php SessionTest
 *
 * Session reads/writes $_SESSION, which is just an array in CLI. We snapshot
 * $_SESSION in setUp and restore it in tearDown so tests don't leak state.
 *
 * Lifecycle methods (regenerate/destroy) cannot fully run under CLI because the
 * session is opened with read_and_close (no active session to destroy). Those
 * tests assert the methods return a bool and don't throw — see notes.
 */

require_once __DIR__ . '/TestRunner.php';

use App\Library\Session;

class SessionTest extends TestCase
{
    /** @var array */
    private $sessionSnapshot;

    public function setUp(): void
    {
        // init() may have been triggered already; ensure $_SESSION exists.
        if (!isset($_SESSION)) { $_SESSION = []; }
        $this->sessionSnapshot = $_SESSION;
        $_SESSION = [];
    }

    public function tearDown(): void
    {
        $_SESSION = $this->sessionSnapshot;
    }

    // ========================================================================
    // get / set / has
    // ========================================================================

    public function testSetAndGet(): void
    {
        Session::set('user_id', 123);
        $this->assertEquals(123, Session::get('user_id'));
    }

    public function testGetMissingReturnsDefault(): void
    {
        $this->assertNull(Session::get('nope'));
        $this->assertEquals('fallback', Session::get('nope', 'fallback'));
    }

    public function testSetReturnsTrue(): void
    {
        $this->assertTrue(Session::set('k', 'v'));
    }

    public function testOverwrite(): void
    {
        Session::set('k', 'first');
        Session::set('k', 'second');
        $this->assertEquals('second', Session::get('k'));
    }

    public function testHasTrueWhenPresent(): void
    {
        Session::set('present', 'x');
        $this->assertTrue(Session::has('present'));
    }

    public function testHasFalseWhenAbsent(): void
    {
        $this->assertFalse(Session::has('absent'));
    }

    public function testHasTrueForNullValue(): void
    {
        // has() uses array_key_exists(), so an explicit null still reads present.
        Session::set('nullkey', null);
        $this->assertTrue(Session::has('nullkey'));
        // An absent key is still absent.
        Session::remove('nullkey');
        $this->assertFalse(Session::has('nullkey'));
    }

    // ========================================================================
    // remove
    // ========================================================================

    public function testRemovePresentReturnsTrue(): void
    {
        Session::set('gone', 'x');
        $this->assertTrue(Session::remove('gone'));
        $this->assertFalse(Session::has('gone'));
    }

    public function testRemoveAbsentReturnsFalse(): void
    {
        $this->assertFalse(Session::remove('never'));
    }

    // ========================================================================
    // all / clear
    // ========================================================================

    public function testAllReturnsData(): void
    {
        Session::set('a', 1);
        Session::set('b', 2);
        $all = Session::all();
        $this->assertIsArray($all);
        $this->assertArrayHasKey('a', $all);
        $this->assertArrayHasKey('b', $all);
        $this->assertEquals(2, $all['b']);
    }

    public function testClearEmptiesSession(): void
    {
        Session::set('a', 1);
        Session::set('b', 2);
        $this->assertTrue(Session::clear());
        $this->assertCount(0, Session::all());
        $this->assertFalse(Session::has('a'));
    }

    // ========================================================================
    // Typed accessors: int
    // ========================================================================

    public function testIntFromInt(): void
    {
        Session::set('n', 42);
        $this->assertEquals(42, Session::int('n'));
    }

    public function testIntCoercesStringDigits(): void
    {
        Session::set('n', '17');
        $this->assertSame(17, Session::int('n'));
    }

    public function testIntMissingReturnsDefault(): void
    {
        $this->assertSame(0, Session::int('missing'));
        $this->assertSame(99, Session::int('missing', 99));
    }

    public function testIntFromNonNumericString(): void
    {
        Session::set('n', 'abc');
        $this->assertSame(0, Session::int('n'));
    }

    // ========================================================================
    // Typed accessors: bool
    // ========================================================================

    public function testBoolFromRealBool(): void
    {
        Session::set('flag', true);
        $this->assertTrue(Session::bool('flag'));
    }

    public function testBoolFromTruthyStrings(): void
    {
        foreach (['1', 'true', 'TRUE', 'yes', 'on'] as $truthy) {
            Session::set('flag', $truthy);
            $this->assertTrue(Session::bool('flag'), "'{$truthy}' should be true");
        }
    }

    public function testBoolFromFalsyStrings(): void
    {
        foreach (['0', 'false', 'no', 'off', ''] as $falsy) {
            Session::set('flag', $falsy);
            $this->assertFalse(Session::bool('flag'), "'{$falsy}' should be false");
        }
    }

    public function testBoolFromIntFallsThroughToCast(): void
    {
        Session::set('flag', 1);
        $this->assertTrue(Session::bool('flag'));
        Session::set('flag', 0);
        $this->assertFalse(Session::bool('flag'));
    }

    public function testBoolMissingReturnsDefault(): void
    {
        $this->assertFalse(Session::bool('missing'));
        $this->assertTrue(Session::bool('missing', true));
    }

    // ========================================================================
    // Typed accessors: string
    // ========================================================================

    public function testStringFromInt(): void
    {
        Session::set('s', 123);
        $this->assertSame('123', Session::string('s'));
    }

    public function testStringMissingReturnsDefault(): void
    {
        $this->assertSame('', Session::string('missing'));
        $this->assertSame('def', Session::string('missing', 'def'));
    }

    // ========================================================================
    // Typed accessors: float
    // ========================================================================

    public function testFloatFromString(): void
    {
        Session::set('f', '3.14');
        $this->assertSame(3.14, Session::float('f'));
    }

    public function testFloatMissingReturnsDefault(): void
    {
        $this->assertSame(0.0, Session::float('missing'));
        $this->assertSame(1.5, Session::float('missing', 1.5));
    }

    // ========================================================================
    // flash — one-shot semantics
    // ========================================================================

    public function testFlashSetAndRead(): void
    {
        Session::flash('msg', 'saved');
        $this->assertEquals('saved', Session::getFlash('msg'));
    }

    public function testFlashConsumedAfterFirstRead(): void
    {
        Session::flash('msg', 'once');
        $this->assertEquals('once', Session::getFlash('msg'));
        // Second read must return the default — it was consumed.
        $this->assertNull(Session::getFlash('msg'));
        $this->assertEquals('fb', Session::getFlash('msg', 'fb'));
    }

    public function testFlashMissingReturnsDefault(): void
    {
        $this->assertNull(Session::getFlash('never'));
        $this->assertEquals('d', Session::getFlash('never', 'd'));
    }

    public function testFlashStoredUnderFlashBucket(): void
    {
        Session::flash('m', 'v');
        $this->assertArrayHasKey('__flash', $_SESSION);
        $this->assertEquals('v', $_SESSION['__flash']['m']);
    }

    // ========================================================================
    // Lifecycle — bool-returning, must not throw (CLI-limited, see notes)
    // ========================================================================

    public function testIsStartedReturnsBool(): void
    {
        $this->assertTrue(is_bool(Session::isStarted()));
    }

    public function testIdReturnsString(): void
    {
        $this->assertIsString(Session::id());
    }

    public function testRegenerateReturnsBoolWithoutThrowing(): void
    {
        // Under CLI read_and_close there is no active session lock, so
        // session_regenerate_id() returns false. We only assert it's a bool
        // and does not throw.
        $result = @Session::regenerate();
        $this->assertTrue(is_bool($result));
    }

    public function testDestroyReturnsBoolWithoutThrowing(): void
    {
        // session_destroy() returns false under CLI (no active session), but
        // it still resets $_SESSION and must not throw. Suppress the expected
        // setcookie "headers already sent" warning with @.
        Session::set('temp', 'x');
        $result = @Session::destroy();
        $this->assertTrue(is_bool($result));
        $this->assertCount(0, Session::all());
    }
}
