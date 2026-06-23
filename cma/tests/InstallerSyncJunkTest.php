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

    private function syncDirectory(string $src, string $dest): void
    {
        $m = new \ReflectionMethod(Installer::class, 'syncDirectory');
        $m->setAccessible(true);
        $m->invoke(null, $src, $dest, [], null);
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
}
