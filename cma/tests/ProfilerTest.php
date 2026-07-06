<?php
/**
 * Tests for App\Library\Profiler
 *
 * Run with: php tests/TestRunner.php ProfilerTest
 *
 * Profiler is a static timing/profiling helper. Its private statics are reset
 * before each test via reflection so tests start from a clean slate regardless
 * of run order. Timing assertions only check elapsed >= 0 (and > 0 after a tiny
 * usleep) — never an exact duration, which would be flaky.
 *
 * Debug::write() (called internally by end/tussenstand/markEnd) is a no-op
 * while Debug is disabled, which it is by default in the test process, so those
 * paths are safe to exercise. end()'s yellow-box echo is captured with ob_*.
 */

require_once __DIR__ . '/TestRunner.php';

use App\Library\Profiler;

class ProfilerTest extends TestCase
{
    private string $tmpRoot;

    public function setUp(): void
    {
        $this->tmpRoot = sys_get_temp_dir() . '/profiler-' . bin2hex(random_bytes(6));
        mkdir($this->tmpRoot, 0755, true);

        // Reset all Profiler statics to a clean slate for each test.
        $this->setStatic('enabled', null);
        $this->setStatic('start', null);
        $this->setStatic('lastTussenstand', null);
        $this->setStatic('threshold', 0);
        $this->setStatic('logFile', null);
        $this->setStatic('marks', []);
    }

    public function tearDown(): void
    {
        $this->rmrf($this->tmpRoot);
    }

    private function rmrf(string $path): void
    {
        if (!file_exists($path)) return;
        if (is_file($path) || is_link($path)) { @unlink($path); return; }
        foreach ((array)scandir($path) as $e) {
            if ($e === '.' || $e === '..') continue;
            $this->rmrf($path . '/' . $e);
        }
        @rmdir($path);
    }

    private function setStatic(string $prop, $value): void
    {
        $ref = new \ReflectionProperty(Profiler::class, $prop);
        $ref->setAccessible(true);
        $ref->setValue(null, $value);
    }

    private function getStatic(string $prop)
    {
        $ref = new \ReflectionProperty(Profiler::class, $prop);
        $ref->setAccessible(true);
        return $ref->getValue();
    }

    // ========================================================================
    // setEnabled / isEnabled / verbose
    // ========================================================================

    public function testSetEnabledTrue(): void
    {
        Profiler::setEnabled(true);
        $this->assertTrue(Profiler::isEnabled());
    }

    public function testSetEnabledFalse(): void
    {
        Profiler::setEnabled(false);
        $this->assertFalse(Profiler::isEnabled());
    }

    public function testVerboseTogglesEnabled(): void
    {
        Profiler::verbose(true);
        $this->assertTrue(Profiler::isEnabled());
        Profiler::verbose(false);
        $this->assertFalse(Profiler::isEnabled());
    }

    public function testIsEnabledReturnsBool(): void
    {
        // With no explicit override, isEnabled() must still return a bool
        // (auto-detected from environment) and must not throw.
        $result = Profiler::isEnabled();
        $this->assertTrue(is_bool($result), 'isEnabled() should return a bool');
    }

    // ========================================================================
    // start / getElapsed
    // ========================================================================

    public function testGetElapsedNonNegative(): void
    {
        Profiler::start();
        $elapsed = Profiler::getElapsed();
        $this->assertTrue($elapsed >= 0, 'elapsed should be >= 0, got ' . $elapsed);
    }

    public function testGetElapsedPositiveAfterSleep(): void
    {
        Profiler::start();
        usleep(2000); // 2ms
        $elapsed = Profiler::getElapsed();
        $this->assertGreaterThan(0, $elapsed);
    }

    public function testStartResetsElapsed(): void
    {
        Profiler::start();
        usleep(3000);
        $first = Profiler::getElapsed();
        Profiler::start(); // restart
        $second = Profiler::getElapsed();
        // After a restart the elapsed clock is (near) zero again, so the second
        // reading must be smaller than the first accumulated one.
        $this->assertLessThan($first, $second);
    }

    public function testGetElapsedAutoInitsWithoutStart(): void
    {
        // getElapsed() calls init() which seeds $start, so it works even if
        // start() was never called explicitly.
        $this->setStatic('enabled', null);
        $this->setStatic('start', null);
        $elapsed = Profiler::getElapsed();
        $this->assertTrue($elapsed >= 0, 'auto-init elapsed should be >= 0');
    }

    // ========================================================================
    // mark / markEnd
    // ========================================================================

    public function testMarkEndReturnsNonNegative(): void
    {
        Profiler::mark('op');
        $elapsed = Profiler::markEnd('op');
        $this->assertTrue($elapsed >= 0, 'markEnd elapsed should be >= 0');
    }

    public function testMarkEndPositiveAfterSleep(): void
    {
        Profiler::mark('slow');
        usleep(2000);
        $elapsed = Profiler::markEnd('slow');
        $this->assertGreaterThan(0, $elapsed);
    }

    public function testMarkEndUnknownMarkerDoesNotThrow(): void
    {
        // Ending a marker that was never started must not throw; it falls back
        // to "now" as the start, so elapsed is ~0 and non-negative.
        $elapsed = Profiler::markEnd('never-started');
        $this->assertTrue($elapsed >= 0, 'unknown marker elapsed should be >= 0');
    }

    public function testMultipleIndependentMarks(): void
    {
        Profiler::mark('a');
        usleep(1000);
        Profiler::mark('b');
        $a = Profiler::markEnd('a');
        $b = Profiler::markEnd('b');
        // 'a' was started earlier, so it should measure at least as much as 'b'.
        $this->assertTrue($a >= 0);
        $this->assertTrue($b >= 0);
        $this->assertTrue($a >= $b, "a ($a) should be >= b ($b)");
    }

    public function testMarkEndConsumesTheMark(): void
    {
        // After markEnd the mark is unset; a second markEnd on the same name is
        // treated as unknown (fresh "now") and still returns non-negative.
        Profiler::mark('once');
        usleep(2000);
        $first = Profiler::markEnd('once');
        $second = Profiler::markEnd('once');
        $this->assertGreaterThan(0, $first);
        // Second call had no stored start -> ~0, and definitely less than first.
        $this->assertLessThan($first, $second);
        $marks = $this->getStatic('marks');
        $this->assertFalse(array_key_exists('once', $marks), 'mark should be consumed');
    }

    public function testMarkEndWithThresholdReturnsElapsedRegardless(): void
    {
        // Threshold only gates logging, not the return value.
        Profiler::setEnabled(true);
        Profiler::mark('m');
        $elapsed = Profiler::markEnd('m', 100000.0, 'details');
        $this->assertTrue($elapsed >= 0, 'elapsed returned even when under threshold');
    }

    // ========================================================================
    // threshold
    // ========================================================================

    public function testThresholdIsStored(): void
    {
        Profiler::threshold(250.0);
        $this->assertEquals(250.0, $this->getStatic('threshold'));
    }

    // ========================================================================
    // end (no-op when disabled, yellow box / debug when enabled)
    // ========================================================================

    public function testEndDisabledProducesNoOutput(): void
    {
        Profiler::setEnabled(false);
        Profiler::start();
        ob_start();
        Profiler::end();
        $out = ob_get_clean();
        $this->assertEquals('', $out, 'end() must be a no-op when disabled');
    }

    public function testEndEnabledInTestEnvEchoesYellowBox(): void
    {
        // Application 'test' flag makes end() emit the yellow timing box.
        \App\Library\Application::set('test', true);
        Profiler::setEnabled(true);
        Profiler::start();
        ob_start();
        Profiler::end();
        $out = ob_get_clean();
        \App\Library\Application::set('test', false);
        $this->assertStringContainsString('profiler', $out);
        $this->assertStringContainsString('ms', $out);
    }

    // ========================================================================
    // tussenstand
    // ========================================================================

    public function testTussenstandDisabledIsNoOp(): void
    {
        Profiler::setEnabled(false);
        Profiler::start();
        ob_start();
        Profiler::tussenstand('checkpoint');
        $out = ob_get_clean();
        $this->assertEquals('', $out, 'tussenstand() must be a no-op when disabled');
    }

    public function testTussenstandEnabledDoesNotThrow(): void
    {
        // With Debug disabled (default in tests), tussenstand logs nothing but
        // must run cleanly and advance the checkpoint clock.
        Profiler::setEnabled(true);
        Profiler::start();
        ob_start();
        Profiler::tussenstand('step 1');
        usleep(1000);
        Profiler::tussenstand('step 2');
        ob_get_clean();
        // No assertion beyond "did not throw"; reaching here is success.
        $this->assertTrue(true);
    }

    // ========================================================================
    // log (CSV append)
    // ========================================================================

    public function testLogWritesCsvLine(): void
    {
        Profiler::setEnabled(true);
        $logFile = $this->tmpRoot . '/profiler.csv';
        $this->setStatic('logFile', $logFile);

        Profiler::log('CMA', '42', 'save', 'admin', 'record 7');

        $this->assertTrue(file_exists($logFile), 'log file should be created');
        $contents = file_get_contents($logFile);
        $this->assertStringContainsString('CMA', $contents);
        $this->assertStringContainsString('42', $contents);
        $this->assertStringContainsString('save', $contents);
        $this->assertStringContainsString('admin', $contents);
        $this->assertStringContainsString(';', $contents);
    }

    public function testLogAppendsMultipleLines(): void
    {
        Profiler::setEnabled(true);
        $logFile = $this->tmpRoot . '/profiler-append.csv';
        $this->setStatic('logFile', $logFile);

        Profiler::log('CMA', '1', 'first');
        Profiler::log('CMA', '2', 'second');

        $lines = array_values(array_filter(explode("\n", file_get_contents($logFile))));
        $this->assertEquals(2, count($lines), 'two log calls should append two lines');
    }

    public function testLogStripsNewlinesFromDetails(): void
    {
        Profiler::setEnabled(true);
        $logFile = $this->tmpRoot . '/profiler-nl.csv';
        $this->setStatic('logFile', $logFile);

        Profiler::log('CMA', '5', 'act', '', "line1\r\nline2\nline3");

        $contents = file_get_contents($logFile);
        // Only the single trailing record-terminator newline should remain.
        $this->assertEquals(1, substr_count($contents, "\n"));
        $this->assertStringContainsString('line1 line2 line3', $contents);
    }

    public function testLogCreatesMissingDirectory(): void
    {
        Profiler::setEnabled(true);
        $logFile = $this->tmpRoot . '/nested/deep/profiler.csv';
        $this->setStatic('logFile', $logFile);

        Profiler::log('Voorkant', '10', 'view');

        $this->assertTrue(file_exists($logFile), 'log() should create missing dirs');
    }
}
