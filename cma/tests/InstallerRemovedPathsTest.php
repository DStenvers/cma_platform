<?php
/**
 * Tests for App\Library\Installer::cleanRemovedPaths (extracted from
 * run() in v1.20.4 so it's callable in isolation).
 *
 * Run with: php tests/TestRunner.php InstallerRemovedPathsTest
 *
 * cleanRemovedPaths is the safety net that deletes files retired from
 * the platform package from each consumer site on `composer update`.
 * Without it, retired tools and .md docs would stay in consumer sites
 * forever — exactly the noise the user objected to about `todo.md`.
 *
 * Pure filesystem test: builds a fake project root in a per-test temp
 * dir, drops files there, calls cleanRemovedPaths, asserts the right
 * ones disappeared. No DB, no composer, no bootstrap.
 */

require_once __DIR__ . '/TestRunner.php';

use App\Library\Installer;

class InstallerRemovedPathsTest extends TestCase
{
    private string $tmpRoot;

    /**
     * The actual REMOVED_PATHS values, read once via reflection. We
     * deliberately do NOT hardcode them in the test — the goal is to
     * verify the LOOP, not pin the list. New retirements should just
     * work without changing this test.
     */
    private array $removedPaths;

    public function setUp(): void
    {
        $this->tmpRoot = sys_get_temp_dir() . '/installer-test-' . bin2hex(random_bytes(6));
        if (!mkdir($this->tmpRoot, 0755, true)) {
            throw new \RuntimeException('Could not create tmp dir: ' . $this->tmpRoot);
        }

        $ref = new \ReflectionClass(Installer::class);
        $this->removedPaths = $ref->getConstant('REMOVED_PATHS');
        if (!is_array($this->removedPaths) || empty($this->removedPaths)) {
            throw new \RuntimeException('REMOVED_PATHS constant missing or empty');
        }
    }

    public function tearDown(): void
    {
        // Best-effort recursive delete; PHP's built-in doesn't recurse
        // directories so do it manually.
        $this->rmrf($this->tmpRoot);
    }

    private function rmrf(string $path): void
    {
        if (!file_exists($path)) return;
        if (is_file($path) || is_link($path)) { @unlink($path); return; }
        foreach ((array)scandir($path) as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            $this->rmrf($path . '/' . $entry);
        }
        @rmdir($path);
    }

    /**
     * Helper: drop a file at $relPath under tmpRoot with $contents,
     * creating parent dirs as needed.
     */
    private function touch(string $relPath, string $contents = "x\n"): void
    {
        $abs = $this->tmpRoot . '/' . $relPath;
        $dir = dirname($abs);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($abs, $contents);
    }

    // ------------------------------------------------------------------
    // Behaviour: present files get removed, return-list reflects reality
    // ------------------------------------------------------------------

    public function testRemovesExistingFilesAndReportsThem(): void
    {
        // Drop every REMOVED_PATHS entry into the fake project root.
        foreach ($this->removedPaths as $relPath) {
            $this->touch($relPath);
        }

        $removed = Installer::cleanRemovedPaths($this->tmpRoot);

        // All present, so all should have been removed.
        $this->assertEquals(count($this->removedPaths), count($removed));

        // And the files really are gone on disk.
        foreach ($this->removedPaths as $relPath) {
            $this->assertFalse(file_exists($this->tmpRoot . '/' . $relPath), "Expected $relPath to be gone");
        }
    }

    // ------------------------------------------------------------------
    // Behaviour: missing files are no-op (idempotent)
    // ------------------------------------------------------------------

    public function testIsIdempotentWhenNothingToRemove(): void
    {
        // tmpRoot has no platform files — cleanRemovedPaths should
        // silently do nothing and return an empty list.
        $removed = Installer::cleanRemovedPaths($this->tmpRoot);
        $this->assertEquals([], $removed);
    }

    public function testReRunReportsZeroAfterFirstSuccess(): void
    {
        // Drop one retired file, clean it, then clean again — the second
        // pass must report zero (idempotency from the operator's view).
        $first = $this->removedPaths[0];
        $this->touch($first);

        $firstRun = Installer::cleanRemovedPaths($this->tmpRoot);
        $this->assertEquals(1, count($firstRun));

        $secondRun = Installer::cleanRemovedPaths($this->tmpRoot);
        $this->assertEquals(0, count($secondRun));
    }

    // ------------------------------------------------------------------
    // Behaviour: unrelated files are NOT touched
    // ------------------------------------------------------------------

    public function testLeavesUnrelatedFilesAlone(): void
    {
        // A consumer site has plenty of files that look similar to the
        // retired paths but aren't on the list (e.g. cma/tools/own.php,
        // cma/docs/site-specific.md). Those must survive intact.
        $this->touch('cma/tools/keep_me.php', 'safe-content');
        $this->touch('cma/docs/site-notes.md', 'project notes');
        $this->touch('cma/web.config', '<config/>');

        Installer::cleanRemovedPaths($this->tmpRoot);

        $this->assertTrue(file_exists($this->tmpRoot . '/cma/tools/keep_me.php'));
        $this->assertTrue(file_exists($this->tmpRoot . '/cma/docs/site-notes.md'));
        $this->assertTrue(file_exists($this->tmpRoot . '/cma/web.config'));
        // And their contents are untouched.
        $this->assertEquals('safe-content', file_get_contents($this->tmpRoot . '/cma/tools/keep_me.php'));
    }

    public function testRemovesOnlyMatchingPathsWhenMixed(): void
    {
        // Mix retired + safe files. Verify only the retired ones go.
        $retired = $this->removedPaths[0];
        $this->touch($retired);
        $this->touch('cma/tools/keep_me.php');

        $removed = Installer::cleanRemovedPaths($this->tmpRoot);

        $this->assertEquals([$retired], $removed);
        $this->assertTrue(file_exists($this->tmpRoot . '/cma/tools/keep_me.php'));
        $this->assertFalse(file_exists($this->tmpRoot . '/' . $retired));
    }

    // ------------------------------------------------------------------
    // Behaviour: directories with retired-path names are NOT removed
    // (is_file() rejects them, unlink would fail anyway)
    // ------------------------------------------------------------------

    public function testDirectoryAtRemovedPathIsNotTouched(): void
    {
        // Edge case: what if the consumer made a directory where a
        // retired file used to live? is_file() returns false for dirs,
        // so unlink never runs and the dir survives untouched.
        $relPath = $this->removedPaths[0];
        mkdir($this->tmpRoot . '/' . $relPath, 0755, true);
        $this->touch($relPath . '/keep.txt');

        Installer::cleanRemovedPaths($this->tmpRoot);

        $this->assertTrue(is_dir($this->tmpRoot . '/' . $relPath));
        $this->assertTrue(file_exists($this->tmpRoot . '/' . $relPath . '/keep.txt'));
    }

    // ------------------------------------------------------------------
    // Sanity: the list itself isn't accidentally empty
    // ------------------------------------------------------------------

    public function testRemovedPathsListIsNotEmpty(): void
    {
        // If someone ever clears REMOVED_PATHS by accident, the cleanup
        // becomes a no-op forever and retired files pile up on consumer
        // sites. Catch that mistake here.
        $this->assertNotEquals(0, count($this->removedPaths));
    }
}
