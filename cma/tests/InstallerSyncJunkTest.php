<?php
/**
 * Regression test for App\Library\Installer::syncDirectory skipping OS-junk
 * files (desktop.ini / Thumbs.db / .DS_Store).
 *
 * Run with: php tests/TestRunner.php InstallerSyncJunkTest
 *
 * A Windows desktop.ini that got committed under library/fonts/ was synced to
 * consumer sites; its system/hidden/readonly attributes made copy() fail with
 * "Permission denied", and because copyFile() throws, that one file aborted the
 * ENTIRE post-update sync (composer update terminated, site left half-synced).
 * syncDirectory now skips these files so they never reach copyFile().
 *
 * Pure filesystem test via reflection on the private static syncDirectory — no
 * DB, composer or bootstrap.
 */

require_once __DIR__ . '/TestRunner.php';

use App\Library\Installer;

class InstallerSyncJunkTest extends TestCase
{
    private string $tmpRoot;

    public function setUp(): void
    {
        $this->tmpRoot = sys_get_temp_dir() . '/installer-sync-' . bin2hex(random_bytes(6));
        mkdir($this->tmpRoot, 0755, true);
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

    private function write(string $rel, string $contents = "x\n"): void
    {
        $abs = $this->tmpRoot . '/' . $rel;
        if (!is_dir(dirname($abs))) mkdir(dirname($abs), 0755, true);
        file_put_contents($abs, $contents);
    }

    private function syncDirectory(string $src, string $dest): array
    {
        $m = new \ReflectionMethod(Installer::class, 'syncDirectory');
        $m->setAccessible(true);
        return (array)$m->invoke(null, $src, $dest, [], null);
    }

    public function testJunkFilesAreSkippedRealFilesAreCopied(): void
    {
        $src = $this->tmpRoot . '/src';
        $dest = $this->tmpRoot . '/dest';

        $this->write('src/real.txt', 'keep-me');
        $this->write('src/fonts/icon.svg', '<svg/>');
        // The junk that broke production, plus its siblings.
        $this->write('src/fonts/desktop.ini', "[.ShellClassInfo]\n");
        $this->write('src/Thumbs.db', 'thumbs');
        $this->write('src/.DS_Store', 'dsstore');

        $this->syncDirectory($src, $dest);

        // Real content copied.
        $this->assertTrue(file_exists($dest . '/real.txt'), 'real.txt should be synced');
        $this->assertEquals('keep-me', file_get_contents($dest . '/real.txt'));
        $this->assertTrue(file_exists($dest . '/fonts/icon.svg'), 'icon.svg should be synced');

        // Junk skipped — never copied.
        $this->assertFalse(file_exists($dest . '/fonts/desktop.ini'), 'desktop.ini must be skipped');
        $this->assertFalse(file_exists($dest . '/Thumbs.db'), 'Thumbs.db must be skipped');
        $this->assertFalse(file_exists($dest . '/.DS_Store'), '.DS_Store must be skipped');
    }

    public function testJunkSkipIsCaseInsensitive(): void
    {
        $src = $this->tmpRoot . '/src2';
        $dest = $this->tmpRoot . '/dest2';

        $this->write('src2/Desktop.INI', "[.ShellClassInfo]\n");
        $this->write('src2/real.txt', 'keep');

        $this->syncDirectory($src, $dest);

        $this->assertTrue(file_exists($dest . '/real.txt'));
        $this->assertFalse(file_exists($dest . '/Desktop.INI'), 'Desktop.INI (mixed case) must be skipped');
    }

    /**
     * The regression that motivated this: a single unwritable file must NOT
     * abort the whole sync and strand a critical top-level file. We force one
     * copy() to fail (its destination pre-exists as a directory) and assert
     * that every other file — crucially library.inc — is still synced, and
     * that the failure is reported back to the caller rather than swallowed.
     */
    public function testOneUnwritableFileDoesNotStrandTheRest(): void
    {
        $src = $this->tmpRoot . '/src3';
        $dest = $this->tmpRoot . '/dest3';

        $this->write('src3/library.inc', 'new-library-inc');
        $this->write('src3/fonts/blocked.bin', 'cannot-copy-me');
        $this->write('src3/lib_html.inc', 'html');

        // Make the destination of blocked.bin already exist AS A DIRECTORY so
        // copy() fails on it the way a locked/permission-denied file would.
        mkdir($dest . '/fonts/blocked.bin', 0755, true);

        // Suppress the expected copy() warning; we assert on behaviour, not noise.
        $errors = @$this->syncDirectory($src, $dest);

        // The important guarantee: the top-level requirer got overwritten even
        // though a sibling file failed.
        $this->assertTrue(file_exists($dest . '/library.inc'), 'library.inc must be synced despite a sibling failure');
        $this->assertEquals('new-library-inc', file_get_contents($dest . '/library.inc'));
        $this->assertTrue(file_exists($dest . '/lib_html.inc'), 'lib_html.inc must be synced');

        // The failure is surfaced, not silently swallowed.
        $this->assertEquals(1, count($errors), 'exactly one file should be reported as failed');
        $this->assertStringContainsString('blocked.bin', $errors[0]);
    }

    // ------------------------------------------------------------------
    // Additional coverage: nested-dir recursion + nested junk skip,
    // junk-only source, clean sync reports no errors.
    // ------------------------------------------------------------------

    public function testNestedDirectoriesAreRecursedAndNestedJunkSkipped(): void
    {
        $src = $this->tmpRoot . '/src4';
        $dest = $this->tmpRoot . '/dest4';

        $this->write('src4/a/b/deep.txt', 'deep-content');
        $this->write('src4/a/b/desktop.ini', "[.ShellClassInfo]\n");
        $this->write('src4/a/Thumbs.db', 'thumbs');

        $errors = $this->syncDirectory($src, $dest);

        $this->assertTrue(file_exists($dest . '/a/b/deep.txt'), 'nested real file must be synced');
        $this->assertEquals('deep-content', file_get_contents($dest . '/a/b/deep.txt'));
        $this->assertFalse(file_exists($dest . '/a/b/desktop.ini'), 'nested junk must be skipped');
        $this->assertFalse(file_exists($dest . '/a/Thumbs.db'), 'nested Thumbs.db must be skipped');
        $this->assertEquals(0, count($errors), 'a clean nested sync reports no errors');
    }

    public function testJunkOnlySourceProducesNoRealFiles(): void
    {
        $src = $this->tmpRoot . '/src5';
        $dest = $this->tmpRoot . '/dest5';

        $this->write('src5/desktop.ini', "x\n");
        $this->write('src5/.DS_Store', 'x');

        $errors = $this->syncDirectory($src, $dest);

        // Destination dir is created, but nothing copied into it.
        $this->assertTrue(is_dir($dest), 'destination dir is created even for a junk-only source');
        $this->assertFalse(file_exists($dest . '/desktop.ini'));
        $this->assertFalse(file_exists($dest . '/.DS_Store'));
        $this->assertEquals(0, count($errors));
    }
}
