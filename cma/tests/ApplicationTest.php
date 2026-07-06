<?php
/**
 * Tests for App\Library\Application
 *
 * Run with: php tests/TestRunner.php ApplicationTest
 *
 * Application stores config in $GLOBALS['Application'] (keys lowercased for
 * case-insensitive access). setUp/tearDown save & restore that global plus the
 * private static log flag so tests never leak state or write log files.
 */

require_once __DIR__ . '/TestRunner.php';

use App\Library\Application;

class ApplicationTest extends TestCase
{
    /** @var mixed snapshot of $GLOBALS['Application'] */
    private $savedGlobal;
    private bool $globalWasSet = false;

    public function setUp(): void
    {
        // Snapshot global state.
        $this->globalWasSet = isset($GLOBALS['Application']);
        $this->savedGlobal = $GLOBALS['Application'] ?? null;

        // Start every test from a clean, known state.
        $GLOBALS['Application'] = [];
        // Force logging off so no test ever writes a log file, and reset the
        // lazily-initialised private static flag to a deterministic value.
        Application::setLogEnabled(false);
        Application::resetStats();
    }

    public function tearDown(): void
    {
        // Restore the global exactly as it was.
        if ($this->globalWasSet) {
            $GLOBALS['Application'] = $this->savedGlobal;
        } else {
            unset($GLOBALS['Application']);
        }
        // Leave logging off and stats reset for the next class.
        Application::setLogEnabled(false);
        Application::resetStats();
    }

    // ========================================================================
    // get
    // ========================================================================

    public function testGetReturnsStoredValue(): void
    {
        Application::set('foo', 'bar');
        $this->assertEquals('bar', Application::get('foo'));
    }

    public function testGetMissingReturnsEmptyStringByDefault(): void
    {
        $this->assertEquals('', Application::get('does_not_exist'));
    }

    public function testGetMissingReturnsProvidedDefault(): void
    {
        $this->assertEquals('fallback', Application::get('does_not_exist', 'fallback'));
    }

    public function testGetMissingReturnsNullDefault(): void
    {
        $this->assertNull(Application::get('nope', null));
    }

    public function testGetInitializesGlobalWhenAbsent(): void
    {
        unset($GLOBALS['Application']);
        $value = Application::get('anything', 'def');
        $this->assertEquals('def', $value);
        // get() should have created the array.
        $this->assertIsArray($GLOBALS['Application']);
    }

    // --- falsy-but-present values must NOT fall through to the default ------

    public function testGetPresentZeroIntNotReplacedByDefault(): void
    {
        Application::set('count', 0);
        $this->assertSame(0, Application::get('count', 99));
    }

    public function testGetPresentZeroStringNotReplacedByDefault(): void
    {
        Application::set('code', '0');
        $this->assertSame('0', Application::get('code', 'default'));
    }

    public function testGetPresentFalseNotReplacedByDefault(): void
    {
        Application::set('flag', false);
        $this->assertSame(false, Application::get('flag', true));
    }

    public function testGetPresentEmptyStringNotReplacedByDefault(): void
    {
        Application::set('empty', '');
        $this->assertSame('', Application::get('empty', 'default'));
    }

    public function testGetPresentEmptyArrayNotReplacedByDefault(): void
    {
        Application::set('arr', []);
        $this->assertSame([], Application::get('arr', ['x']));
    }

    // --- null quirk: stored null is indistinguishable from missing on get() -

    public function testGetStoredNullFallsBackToDefault(): void
    {
        // Because get() uses the null-coalescing operator, an explicitly stored
        // null is returned as the default, not as null.
        Application::set('nullable', null);
        $this->assertEquals('def', Application::get('nullable', 'def'));
    }

    // ========================================================================
    // set / overwrite
    // ========================================================================

    public function testSetThenGet(): void
    {
        Application::set('name', 'value');
        $this->assertEquals('value', Application::get('name'));
    }

    public function testSetOverwritesExisting(): void
    {
        Application::set('key', 'first');
        Application::set('key', 'second');
        $this->assertEquals('second', Application::get('key'));
    }

    public function testSetStoresVariousTypes(): void
    {
        Application::set('int', 42);
        Application::set('bool', true);
        Application::set('array', ['a' => 1]);
        $this->assertSame(42, Application::get('int'));
        $this->assertSame(true, Application::get('bool'));
        $this->assertSame(['a' => 1], Application::get('array'));
    }

    public function testSetInitializesGlobalWhenAbsent(): void
    {
        unset($GLOBALS['Application']);
        Application::set('k', 'v');
        $this->assertEquals('v', Application::get('k'));
    }

    // ========================================================================
    // case-insensitivity (keys are lowercased internally)
    // ========================================================================

    public function testKeysAreCaseInsensitiveForGet(): void
    {
        Application::set('MyKey', 'v');
        $this->assertEquals('v', Application::get('mykey'));
        $this->assertEquals('v', Application::get('MYKEY'));
        $this->assertEquals('v', Application::get('MyKey'));
    }

    public function testKeysAreCaseInsensitiveForSetOverwrite(): void
    {
        Application::set('Mixed', 'first');
        Application::set('MIXED', 'second');
        $this->assertEquals('second', Application::get('mixed'));
        // Only one underlying key should exist.
        $this->assertCount(1, Application::all());
    }

    public function testKeysAreCaseInsensitiveForHasAndRemove(): void
    {
        Application::set('CaseKey', 'v');
        $this->assertTrue(Application::has('casekey'));
        Application::remove('CASEKEY');
        $this->assertFalse(Application::has('CaseKey'));
    }

    public function testStoredKeyIsLowercased(): void
    {
        Application::set('UpperName', 'v');
        $all = Application::all();
        $this->assertArrayHasKey('uppername', $all);
    }

    // ========================================================================
    // has
    // ========================================================================

    public function testHasReturnsTrueForExisting(): void
    {
        Application::set('present', 'x');
        $this->assertTrue(Application::has('present'));
    }

    public function testHasReturnsFalseForMissing(): void
    {
        $this->assertFalse(Application::has('missing'));
    }

    public function testHasReturnsFalseForStoredNull(): void
    {
        // has() uses isset(), which is false for a null value even though the
        // key is present in the underlying array.
        Application::set('nullkey', null);
        $this->assertFalse(Application::has('nullkey'));
    }

    public function testHasReturnsTrueForFalsyNonNull(): void
    {
        Application::set('zero', 0);
        Application::set('emptystr', '');
        Application::set('false', false);
        $this->assertTrue(Application::has('zero'));
        $this->assertTrue(Application::has('emptystr'));
        $this->assertTrue(Application::has('false'));
    }

    public function testHasInitializesGlobalWhenAbsent(): void
    {
        unset($GLOBALS['Application']);
        $this->assertFalse(Application::has('whatever'));
        $this->assertIsArray($GLOBALS['Application']);
    }

    // ========================================================================
    // remove
    // ========================================================================

    public function testRemoveDeletesKey(): void
    {
        Application::set('temp', 'v');
        Application::remove('temp');
        $this->assertFalse(Application::has('temp'));
    }

    public function testRemoveNonExistentKeyIsNoOp(): void
    {
        // Should not throw or warn.
        Application::remove('never_existed');
        $this->assertFalse(Application::has('never_existed'));
    }

    public function testRemoveWhenGlobalAbsentIsNoOp(): void
    {
        unset($GLOBALS['Application']);
        Application::remove('anything');
        // Nothing to assert beyond "did not throw"; confirm still absent/empty.
        $this->assertFalse(Application::has('anything'));
    }

    public function testRemoveLeavesOtherKeysIntact(): void
    {
        Application::set('a', '1');
        Application::set('b', '2');
        Application::remove('a');
        $this->assertFalse(Application::has('a'));
        $this->assertTrue(Application::has('b'));
        $this->assertEquals('2', Application::get('b'));
    }

    // ========================================================================
    // all / getAll
    // ========================================================================

    public function testAllReturnsEverything(): void
    {
        Application::set('x', '1');
        Application::set('y', '2');
        $all = Application::all();
        $this->assertCount(2, $all);
        $this->assertEquals('1', $all['x']);
        $this->assertEquals('2', $all['y']);
    }

    public function testAllReturnsEmptyArrayWhenNothingSet(): void
    {
        $this->assertSame([], Application::all());
    }

    public function testAllReturnsEmptyArrayWhenGlobalAbsent(): void
    {
        unset($GLOBALS['Application']);
        $this->assertSame([], Application::all());
    }

    public function testGetAllIsAliasOfAll(): void
    {
        Application::set('k', 'v');
        $this->assertSame(Application::all(), Application::getAll());
    }

    // ========================================================================
    // clear
    // ========================================================================

    public function testClearEmptiesEverything(): void
    {
        Application::set('a', '1');
        Application::set('b', '2');
        Application::clear();
        $this->assertSame([], Application::all());
        $this->assertFalse(Application::has('a'));
    }

    public function testClearOnEmptyIsSafe(): void
    {
        Application::clear();
        $this->assertSame([], Application::all());
    }

    // ========================================================================
    // getStats / resetStats
    // ========================================================================

    public function testStatsHasExpectedKeys(): void
    {
        $stats = Application::getStats();
        $this->assertArrayHasKey('gets', $stats);
        $this->assertArrayHasKey('sets', $stats);
        $this->assertArrayHasKey('total', $stats);
    }

    public function testGetIncrementsGetCount(): void
    {
        Application::resetStats();
        Application::get('a');
        Application::get('b');
        $this->assertSame(2, Application::getStats()['gets']);
    }

    public function testSetIncrementsSetCount(): void
    {
        Application::resetStats();
        Application::set('a', '1');
        Application::set('b', '2');
        Application::set('c', '3');
        $this->assertSame(3, Application::getStats()['sets']);
    }

    public function testTotalIsGetsPlusSets(): void
    {
        Application::resetStats();
        Application::set('a', '1');   // sets = 1
        Application::get('a');        // gets = 1
        Application::get('a');        // gets = 2
        $stats = Application::getStats();
        $this->assertSame(2, $stats['gets']);
        $this->assertSame(1, $stats['sets']);
        $this->assertSame(3, $stats['total']);
    }

    public function testHasAndRemoveAndAllDoNotAffectStats(): void
    {
        Application::resetStats();
        Application::set('a', '1');   // sets = 1
        Application::has('a');
        Application::remove('a');
        Application::all();
        Application::clear();
        $stats = Application::getStats();
        $this->assertSame(0, $stats['gets']);
        $this->assertSame(1, $stats['sets']);
    }

    public function testGetIncrementsCountEvenForMissingKey(): void
    {
        Application::resetStats();
        Application::get('definitely_missing');
        $this->assertSame(1, Application::getStats()['gets']);
    }

    public function testResetStatsZeroesCounters(): void
    {
        Application::set('a', '1');
        Application::get('a');
        Application::resetStats();
        $stats = Application::getStats();
        $this->assertSame(0, $stats['gets']);
        $this->assertSame(0, $stats['sets']);
        $this->assertSame(0, $stats['total']);
    }

    // ========================================================================
    // setLogEnabled (verify the logging path without asserting header/output)
    // ========================================================================

    public function testSetLogEnabledFalseWritesNothing(): void
    {
        $logFile = sys_get_temp_dir() . '/app-log-test-' . bin2hex(random_bytes(4)) . '.log';
        Application::set('app_log_file', $logFile);
        Application::setLogEnabled(false);
        Application::get('anything');
        $this->assertFalse(file_exists($logFile), 'No log file should be written when logging is disabled');
    }

    public function testSetLogEnabledTrueWritesLogLine(): void
    {
        $logFile = sys_get_temp_dir() . '/app-log-test-' . bin2hex(random_bytes(4)) . '.log';
        Application::set('app_log_file', $logFile);
        Application::setLogEnabled(true);
        try {
            Application::get('some_key');
            $this->assertTrue(file_exists($logFile), 'Log file should be created when logging is enabled');
            $contents = file_get_contents($logFile);
            $this->assertStringContainsString('APP GET', $contents);
            $this->assertStringContainsString('some_key', $contents);
        } finally {
            Application::setLogEnabled(false);
            if (file_exists($logFile)) {
                @unlink($logFile);
            }
        }
    }
}
