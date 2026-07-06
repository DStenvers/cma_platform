<?php
/**
 * Tests for App\Library\Log
 *
 * Run with: php tests/TestRunner.php LogTest
 *
 * Log is a static file-logging helper that resolves paths through
 * Server::mapPath(). To keep every write inside a throwaway temp dir, setUp
 * points Server's cached document-root and allowed-paths at $tmpRoot via
 * reflection; absolute virtual paths ("/x.log") then resolve to $tmpRoot/x.log.
 * The temp dir already sits under sys_get_temp_dir(), which mapPath allows.
 *
 * tearDown closes any open handle before removing the tree so the file isn't
 * locked on cleanup.
 */

require_once __DIR__ . '/TestRunner.php';

use App\Library\Log;
use App\Library\Server;

class LogTest extends TestCase
{
    private string $tmpRoot;

    public function setUp(): void
    {
        $this->tmpRoot = sys_get_temp_dir() . '/log-' . bin2hex(random_bytes(6));
        mkdir($this->tmpRoot, 0755, true);
        $this->pointServerAt($this->tmpRoot);
        Log::close(); // ensure a clean handle between tests
    }

    public function tearDown(): void
    {
        Log::close();
        $this->resetServer();
        $this->rmrf($this->tmpRoot);
    }

    /**
     * Reset Server's cached statics to null so Server::init() re-initializes
     * naturally for subsequent test classes (avoids leaking our sandbox).
     */
    private function resetServer(): void
    {
        foreach (['documentRoot', 'allowedPaths'] as $prop) {
            $ref = new \ReflectionProperty(Server::class, $prop);
            $ref->setAccessible(true);
            $ref->setValue(null, $prop === 'allowedPaths' ? [] : null);
        }
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

    /**
     * Force Server::mapPath() to treat $dir as the document root and the only
     * allowed sandbox prefix, so absolute virtual paths land inside our temp dir.
     */
    private function pointServerAt(string $dir): void
    {
        $norm = str_replace('\\', '/', rtrim($dir, '/\\'));
        $root = new \ReflectionProperty(Server::class, 'documentRoot');
        $root->setAccessible(true);
        $root->setValue(null, $norm);
        $allowed = new \ReflectionProperty(Server::class, 'allowedPaths');
        $allowed->setAccessible(true);
        $allowed->setValue(null, [$norm, str_replace('\\', '/', sys_get_temp_dir())]);
    }

    // ========================================================================
    // init / getFilename
    // ========================================================================

    public function testInitReturnsTrue(): void
    {
        $this->assertTrue(Log::init('/app.log'));
    }

    public function testGetFilenameNullBeforeInit(): void
    {
        Log::close();
        $this->assertNull(Log::getFilename());
    }

    public function testGetFilenameAfterInit(): void
    {
        Log::init('/app.log');
        $name = Log::getFilename();
        $this->assertNotNull($name);
        $this->assertStringContainsString('app.log', $name);
    }

    public function testInitCreatesMissingDirectory(): void
    {
        $this->assertTrue(Log::init('/deep/nested/dir/app.log'));
        $this->assertTrue(is_dir($this->tmpRoot . '/deep/nested/dir'));
    }

    // ========================================================================
    // write / writeLine
    // ========================================================================

    public function testWriteCreatesFileWithContent(): void
    {
        Log::init('/w.log');
        $this->assertTrue(Log::write('hello'));
        Log::close();
        $this->assertTrue(file_exists($this->tmpRoot . '/w.log'));
        $this->assertEquals('hello', file_get_contents($this->tmpRoot . '/w.log'));
    }

    public function testWriteLineAppendsNewline(): void
    {
        Log::init('/wl.log');
        Log::writeLine('a line');
        Log::close();
        $contents = file_get_contents($this->tmpRoot . '/wl.log');
        $this->assertEquals('a line' . PHP_EOL, $contents);
    }

    public function testWriteAppendsAcrossCalls(): void
    {
        Log::init('/multi.log');
        Log::write('one');
        Log::write('two');
        Log::close();
        $this->assertEquals('onetwo', file_get_contents($this->tmpRoot . '/multi.log'));
    }

    public function testWriteLineMultipleLines(): void
    {
        Log::init('/lines.log');
        Log::writeLine('first');
        Log::writeLine('second');
        Log::close();
        $expected = 'first' . PHP_EOL . 'second' . PHP_EOL;
        $this->assertEquals($expected, file_get_contents($this->tmpRoot . '/lines.log'));
    }

    public function testAppendPreservesExistingFileContents(): void
    {
        // Pre-seed the file; init() opens in append mode, so prior content stays.
        file_put_contents($this->tmpRoot . '/pre.log', "existing\n");
        Log::init('/pre.log');
        Log::write('added');
        Log::close();
        $this->assertEquals("existing\nadded", file_get_contents($this->tmpRoot . '/pre.log'));
    }

    public function testWriteUnicodeContent(): void
    {
        Log::init('/unicode.log');
        $msg = 'café — 日本語 — Ø';
        Log::write($msg);
        Log::close();
        $this->assertEquals($msg, file_get_contents($this->tmpRoot . '/unicode.log'));
    }

    public function testReInitSwitchesTargetFile(): void
    {
        Log::init('/first.log');
        Log::write('in-first');
        Log::init('/second.log'); // closes first, opens second
        Log::write('in-second');
        Log::close();
        $this->assertEquals('in-first', file_get_contents($this->tmpRoot . '/first.log'));
        $this->assertEquals('in-second', file_get_contents($this->tmpRoot . '/second.log'));
    }

    // ========================================================================
    // close
    // ========================================================================

    public function testCloseResetsFilename(): void
    {
        Log::init('/c.log');
        $this->assertNotNull(Log::getFilename());
        Log::close();
        $this->assertNull(Log::getFilename());
    }

    public function testCloseIsIdempotent(): void
    {
        Log::close();
        Log::close(); // second close must not error
        $this->assertNull(Log::getFilename());
    }

    // ========================================================================
    // Fail-safe behavior (no fatal / exception on bad target)
    // ========================================================================

    public function testInitFailsSafeWhenParentIsAFile(): void
    {
        // A regular file where a directory is expected makes mkdir() fail and
        // the dir remain absent: init() must return false, not throw.
        file_put_contents($this->tmpRoot . '/blocker', 'i am a file');
        $result = @Log::init('/blocker/child.log'); // suppress expected mkdir warning
        $this->assertFalse($result, 'init() should fail-safe to false when the dir cannot be made');
        // No log file was created under the (impossible) path.
        $this->assertFalse(file_exists($this->tmpRoot . '/blocker/child.log'));
    }
}
